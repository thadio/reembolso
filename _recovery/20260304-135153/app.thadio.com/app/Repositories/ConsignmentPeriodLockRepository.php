<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;

class ConsignmentPeriodLockRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private const TABLE = 'consignment_period_locks';

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    // ─── QUERIES ────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByYearMonth(string $yearMonth): ?array
    {
        if (!$this->pdo || $yearMonth === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE `year_month` = :ym LIMIT 1");
        $stmt->execute([':ym' => $yearMonth]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a year-month is currently locked.
     */
    public function isLocked(string $yearMonth): bool
    {
        $lock = $this->findByYearMonth($yearMonth);
        if (!$lock) {
            return false;
        }
        return empty($lock['unlocked_at']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query(
            "SELECT l.*,
                    COALESCE(lp.full_name, CONCAT('User #', l.locked_by)) AS locked_by_name,
                    COALESCE(up.full_name, CONCAT('User #', l.unlocked_by)) AS unlocked_by_name
             FROM " . self::TABLE . " l
             LEFT JOIN pessoas lp ON lp.id = l.locked_by
             LEFT JOIN pessoas up ON up.id = l.unlocked_by
             ORDER BY l.`year_month` DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    /**
     * Lock a period.
     */
    public function lock(string $yearMonth, int $userId, ?string $notes = null): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existing = $this->findByYearMonth($yearMonth);
        if ($existing) {
            // Already locked — re-lock if it was unlocked
            if (!empty($existing['unlocked_at'])) {
                $this->updateById((int) $existing['id'], [
                    'locked_at' => date('Y-m-d H:i:s'),
                    'locked_by' => $userId,
                    'notes' => $notes,
                    'unlocked_at' => null,
                    'unlocked_by' => null,
                    'unlock_reason' => null,
                ]);
            }
            return (int) $existing['id'];
        }

        $sql = "INSERT INTO " . self::TABLE . " (`year_month`, locked_at, locked_by, notes)
                VALUES (:ym, :lat, :lby, :notes)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ym' => $yearMonth,
            ':lat' => date('Y-m-d H:i:s'),
            ':lby' => $userId,
            ':notes' => $notes,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLog($this->pdo, 'consignment_period_lock', $id, 'lock', null, [
            'year_month' => $yearMonth,
            'locked_by' => $userId,
            'notes' => $notes,
        ]);
        return $id;
    }

    /**
     * Unlock a period.
     */
    public function unlock(string $yearMonth, int $userId, string $reason): void
    {
        if (!$this->pdo) {
            return;
        }

        $existing = $this->findByYearMonth($yearMonth);
        if (!$existing || !empty($existing['unlocked_at'])) {
            return; // not locked
        }

        $this->updateById((int) $existing['id'], [
            'unlocked_at' => date('Y-m-d H:i:s'),
            'unlocked_by' => $userId,
            'unlock_reason' => $reason,
        ]);

        $this->auditLog($this->pdo, 'consignment_period_lock', (int) $existing['id'], 'unlock', $existing, [
            'year_month' => $yearMonth,
            'unlocked_by' => $userId,
            'unlock_reason' => $reason,
        ]);
    }

    private function updateById(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach ($data as $col => $val) {
            $fields[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }
        if (empty($fields)) {
            return;
        }
        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    // ─── SCHEMA ─────────────────────────────────────────────────

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `year_month` CHAR(7) NOT NULL,
          locked_at DATETIME NOT NULL,
          locked_by BIGINT UNSIGNED NOT NULL,
          notes TEXT NULL,
          unlocked_at DATETIME NULL,
          unlocked_by BIGINT UNSIGNED NULL,
          unlock_reason TEXT NULL,
          UNIQUE KEY uniq_period_lock (`year_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
