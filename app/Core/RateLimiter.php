<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->cleanup($decaySeconds);

        $stmt = $this->db->prepare('SELECT attempts FROM login_attempts WHERE throttle_key = :throttle_key LIMIT 1');
        $stmt->execute(['throttle_key' => $key]);
        $record = $stmt->fetch();

        if ($record === false) {
            return false;
        }

        return ((int) $record['attempts']) >= $maxAttempts;
    }

    public function hit(string $key): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (throttle_key, attempts, first_attempt_at, last_attempt_at)
             VALUES (:throttle_key, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()'
        );

        $stmt->execute(['throttle_key' => $key]);
    }

    public function clear(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE throttle_key = :throttle_key');
        $stmt->execute(['throttle_key' => $key]);
    }

    private function cleanup(int $decaySeconds): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)'
        );
        $stmt->bindValue(':seconds', $decaySeconds, PDO::PARAM_INT);
        $stmt->execute();
    }
}
