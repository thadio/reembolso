<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class RateLimiter
{
    private ?bool $supportsLockoutColumn = null;

    public function __construct(private PDO $db)
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $this->cleanup(max($windowSeconds, 60));

        if ($this->supportsLockoutColumn()) {
            $stmt = $this->db->prepare(
                'SELECT attempts, first_attempt_at, lockout_until
                 FROM login_attempts
                 WHERE throttle_key = :throttle_key
                 LIMIT 1'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT attempts, first_attempt_at
                 FROM login_attempts
                 WHERE throttle_key = :throttle_key
                 LIMIT 1'
            );
        }
        $stmt->execute(['throttle_key' => $key]);
        $record = $stmt->fetch();

        if ($record === false) {
            return false;
        }

        $now = time();
        if ($this->supportsLockoutColumn()) {
            $lockoutUntilRaw = (string) ($record['lockout_until'] ?? '');
            if ($lockoutUntilRaw !== '') {
                $lockoutUntil = strtotime($lockoutUntilRaw);
                if ($lockoutUntil !== false && $lockoutUntil > $now) {
                    return true;
                }

                // Lockout expirado: reinicia contador para nao manter bloqueio dentro da janela antiga.
                $this->clear($key);

                return false;
            }
        }

        $firstAttemptRaw = (string) ($record['first_attempt_at'] ?? '');
        $firstAttemptAt = $firstAttemptRaw !== '' ? strtotime($firstAttemptRaw) : false;
        if ($firstAttemptAt === false) {
            return false;
        }

        if (($firstAttemptAt + $windowSeconds) < $now) {
            return false;
        }

        return ((int) ($record['attempts'] ?? 0)) >= $maxAttempts;
    }

    public function lockoutRemainingSeconds(string $key): int
    {
        if (!$this->supportsLockoutColumn()) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT lockout_until
             FROM login_attempts
             WHERE throttle_key = :throttle_key
             LIMIT 1'
        );
        $stmt->execute(['throttle_key' => $key]);
        $record = $stmt->fetch();

        if ($record === false) {
            return 0;
        }

        $lockoutUntilRaw = (string) ($record['lockout_until'] ?? '');
        $lockoutUntil = $lockoutUntilRaw !== '' ? strtotime($lockoutUntilRaw) : false;
        if ($lockoutUntil === false) {
            return 0;
        }

        $remaining = $lockoutUntil - time();

        return $remaining > 0 ? $remaining : 0;
    }

    public function hit(string $key, int $maxAttempts, int $windowSeconds, int $lockoutSeconds): void
    {
        $this->cleanup(max($windowSeconds, $lockoutSeconds, 60));

        $stmt = $this->db->prepare(
            'SELECT attempts, first_attempt_at
             FROM login_attempts
             WHERE throttle_key = :throttle_key
             LIMIT 1'
        );
        $stmt->execute(['throttle_key' => $key]);
        $record = $stmt->fetch();

        $now = date('Y-m-d H:i:s');
        $attempts = 1;
        $firstAttemptAt = $now;

        if ($record !== false) {
            $recordFirstRaw = (string) ($record['first_attempt_at'] ?? '');
            $recordFirstAt = $recordFirstRaw !== '' ? strtotime($recordFirstRaw) : false;
            if ($recordFirstAt !== false && ($recordFirstAt + $windowSeconds) >= time()) {
                $attempts = ((int) ($record['attempts'] ?? 0)) + 1;
                $firstAttemptAt = $recordFirstRaw;
            }
        }

        $lockoutUntil = null;
        if ($attempts >= $maxAttempts) {
            $lockoutUntil = date('Y-m-d H:i:s', time() + $lockoutSeconds);
        }

        if ($this->supportsLockoutColumn()) {
            $upsert = $this->db->prepare(
                'INSERT INTO login_attempts (
                    throttle_key,
                    attempts,
                    first_attempt_at,
                    last_attempt_at,
                    lockout_until
                ) VALUES (
                    :throttle_key,
                    :attempts,
                    :first_attempt_at,
                    NOW(),
                    :lockout_until
                )
                ON DUPLICATE KEY UPDATE
                    attempts = VALUES(attempts),
                    first_attempt_at = VALUES(first_attempt_at),
                    last_attempt_at = NOW(),
                    lockout_until = VALUES(lockout_until)'
            );

            $upsert->execute([
                'throttle_key' => $key,
                'attempts' => $attempts,
                'first_attempt_at' => $firstAttemptAt,
                'lockout_until' => $lockoutUntil,
            ]);

            return;
        }

        $upsert = $this->db->prepare(
            'INSERT INTO login_attempts (
                throttle_key,
                attempts,
                first_attempt_at,
                last_attempt_at
            ) VALUES (
                :throttle_key,
                :attempts,
                :first_attempt_at,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                attempts = VALUES(attempts),
                first_attempt_at = VALUES(first_attempt_at),
                last_attempt_at = NOW()'
        );

        $upsert->execute([
            'throttle_key' => $key,
            'attempts' => $attempts,
            'first_attempt_at' => $firstAttemptAt,
        ]);
    }

    public function clear(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE throttle_key = :throttle_key');
        $stmt->execute(['throttle_key' => $key]);
    }

    private function cleanup(int $ttlSeconds): void
    {
        if (!$this->supportsLockoutColumn()) {
            $stmt = $this->db->prepare(
                'DELETE FROM login_attempts
                 WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)'
            );
            $stmt->bindValue(':seconds', $ttlSeconds, PDO::PARAM_INT);
            $stmt->execute();

            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts
             WHERE lockout_until IS NULL
               AND last_attempt_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)'
        );
        $stmt->bindValue(':seconds', $ttlSeconds, PDO::PARAM_INT);
        $stmt->execute();

        $stmtLocked = $this->db->prepare(
            'DELETE FROM login_attempts
             WHERE lockout_until IS NOT NULL
               AND lockout_until < NOW()'
        );
        $stmtLocked->execute();
    }

    private function supportsLockoutColumn(): bool
    {
        if ($this->supportsLockoutColumn !== null) {
            return $this->supportsLockoutColumn;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'login_attempts'
                   AND COLUMN_NAME = 'lockout_until'
                 LIMIT 1"
            );
            $stmt->execute();
            $this->supportsLockoutColumn = $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            $this->supportsLockoutColumn = false;
        }

        return $this->supportsLockoutColumn;
    }
}
