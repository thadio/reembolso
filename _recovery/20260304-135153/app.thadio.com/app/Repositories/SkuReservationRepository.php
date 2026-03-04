<?php

namespace App\Repositories;

use PDO;

class SkuReservationRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $shouldMigrate = function_exists('shouldRunSchemaMigrations') ? \shouldRunSchemaMigrations() : true;
        if ($this->pdo && $shouldMigrate) {
            $this->ensureTable();
        }
    }

    public function hasConnection(): bool
    {
        return $this->pdo !== null;
    }

    public function maxReservedNumericSku(): int
    {
        if (!$this->pdo) {
            return 0;
        }
        // Limita a SKUs dentro do range operacional para evitar distorção por reservas obsoletas.
        $stmt = $this->pdo->query("SELECT MAX(CAST(sku AS UNSIGNED)) AS max_sku
            FROM sku_reservations
            WHERE sku REGEXP '^[0-9]+$'
              AND CAST(sku AS UNSIGNED) < 900000000");
        $value = $stmt ? $stmt->fetchColumn() : null;
        return $value !== false && $value !== null ? (int) $value : 0;
    }

    public function listByContextKey(string $context, string $contextKey, ?string $sessionId = null): array
    {
        if (!$this->pdo || $contextKey === '') {
            return [];
        }
        $sql = "SELECT id, sku, context, context_key, session_id, user_id, created_at
            FROM sku_reservations
            WHERE context = :context AND context_key = :context_key";
        $params = [
            ':context' => $context,
            ':context_key' => $contextKey,
        ];
        if ($sessionId) {
            $sql .= " AND session_id = :session_id";
            $params[':session_id'] = $sessionId;
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function insertReservation(
        string $sku,
        string $context,
        ?string $contextKey,
        ?string $sessionId,
        ?int $userId
    ): ?array {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("INSERT INTO sku_reservations
            (sku, context, context_key, session_id, user_id)
            VALUES (:sku, :context, :context_key, :session_id, :user_id)");
        $stmt->execute([
            ':sku' => $sku,
            ':context' => $context,
            ':context_key' => $contextKey,
            ':session_id' => $sessionId,
            ':user_id' => $userId,
        ]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'sku' => $sku,
            'context' => $context,
            'context_key' => $contextKey,
            'session_id' => $sessionId,
            'user_id' => $userId,
        ];
    }

    public function consumeById(int $id, string $context, string $contextKey, ?string $sessionId = null): ?string
    {
        if (!$this->pdo || $id <= 0 || $contextKey === '') {
            return null;
        }
        $this->pdo->beginTransaction();
        try {
            $sql = "SELECT sku FROM sku_reservations
                WHERE id = :id AND context = :context AND context_key = :context_key";
            $params = [
                ':id' => $id,
                ':context' => $context,
                ':context_key' => $contextKey,
            ];
            if ($sessionId) {
                $sql .= " AND session_id = :session_id";
                $params[':session_id'] = $sessionId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $sku = $stmt->fetchColumn();
            if ($sku === false || $sku === null) {
                $this->pdo->rollBack();
                return null;
            }
            $delSql = "DELETE FROM sku_reservations WHERE id = :id";
            $delParams = [':id' => $id];
            if ($sessionId) {
                $delSql .= " AND session_id = :session_id";
                $delParams[':session_id'] = $sessionId;
            }
            $delStmt = $this->pdo->prepare($delSql);
            $delStmt->execute($delParams);
            $this->pdo->commit();
            return (string) $sku;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function releaseByContextKey(string $context, string $contextKey, ?string $sessionId = null): int
    {
        if (!$this->pdo || $contextKey === '') {
            return 0;
        }
        $sql = "DELETE FROM sku_reservations WHERE context = :context AND context_key = :context_key";
        $params = [
            ':context' => $context,
            ':context_key' => $contextKey,
        ];
        if ($sessionId) {
            $sql .= " AND session_id = :session_id";
            $params[':session_id'] = $sessionId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function cleanupExpired(int $minutes): int
    {
        if (!$this->pdo || $minutes <= 0) {
            return 0;
        }
        $minutes = max(1, (int) $minutes);
        $sql = "DELETE FROM sku_reservations
            WHERE created_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS sku_reservations (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          sku VARCHAR(60) NOT NULL,
          context VARCHAR(40) NOT NULL,
          context_key VARCHAR(64) NULL,
          session_id VARCHAR(128) NULL,
          user_id INT UNSIGNED NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_sku_reservations_sku (sku),
          INDEX idx_sku_reservations_context (context, context_key),
          INDEX idx_sku_reservations_session (session_id),
          INDEX idx_sku_reservations_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
