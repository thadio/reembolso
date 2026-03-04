<?php

namespace App\Repositories;

use PDO;
use PDOException;

class DashboardLayoutRepository
{
    private ?PDO $pdo;
    private ?bool $layoutTableExists = null;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            $this->layoutTableExists = true;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        if (!$this->pdo || $userId <= 0) {
            return null;
        }
        if (!$this->hasLayoutTable()) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT layout FROM dashboard_layouts WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $layout = json_decode((string) ($row['layout'] ?? ''), true);
            return is_array($layout) ? $layout : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function saveForUser(int $userId, array $layout): void
    {
        if (!$this->pdo || $userId <= 0) {
            return;
        }
        if (!$this->hasLayoutTable()) {
            return;
        }
        $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE);
        if ($layoutJson === false) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO dashboard_layouts (user_id, layout)
                 VALUES (:user_id, :layout)
                 ON DUPLICATE KEY UPDATE layout = :layout, updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':layout' => $layoutJson,
            ]);
        } catch (PDOException $e) {
            $this->layoutTableExists = false;
        }
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS dashboard_layouts (
            user_id INT UNSIGNED PRIMARY KEY,
            layout JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    private function hasLayoutTable(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        if ($this->layoutTableExists !== null) {
            return $this->layoutTableExists;
        }
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'dashboard_layouts'");
            if (!$stmt->execute()) {
                $this->layoutTableExists = false;
                return false;
            }
            if ($stmt->fetch()) {
                $this->layoutTableExists = true;
                return true;
            }
        } catch (PDOException $e) {
            $this->layoutTableExists = false;
            return false;
        }

        $this->layoutTableExists = $this->tryEnsureTable();
        return $this->layoutTableExists;
    }

    private function tryEnsureTable(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        try {
            $this->ensureTable();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
