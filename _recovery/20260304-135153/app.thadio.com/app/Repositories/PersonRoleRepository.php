<?php

namespace App\Repositories;

use PDO;

class PersonRoleRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela pessoas_papeis: ' . $e->getMessage());
            }
        }
    }

    public function assign(int $personId, string $role, ?string $context = null, ?array $payload = null): void
    {
        if (!$this->pdo || $personId <= 0 || $role === '') {
            return;
        }

        $payloadJson = null;
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadJson = $encoded === false ? null : $encoded;
        }

        $sql = 'INSERT INTO pessoas_papeis (pessoa_id, role, context, payload)
                VALUES (:pessoa_id, :role, :context, :payload)
                ON DUPLICATE KEY UPDATE payload = VALUES(payload)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pessoa_id' => $personId,
            ':role' => $role,
            ':context' => $context,
            ':payload' => $payloadJson,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByPerson(int $personId): array
    {
        if (!$this->pdo || $personId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT role, context, payload, created_at, updated_at FROM pessoas_papeis WHERE pessoa_id = :id');
        $stmt->execute([':id' => $personId]);
        return $stmt->fetchAll();
    }

    /**
     * @param int[] $personIds
     * @return array<int, array<int, array{role:string, context:?string, payload:mixed}>>
     */
    public function listByPeople(array $personIds): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $personIds), static function (int $id): bool {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT pessoa_id, role, context, payload FROM pessoas_papeis WHERE pessoa_id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $personId = (int) ($row['pessoa_id'] ?? 0);
            if ($personId <= 0) {
                continue;
            }
            $payload = $row['payload'] ?? null;
            if (is_string($payload) && $payload !== '') {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = $decoded;
                }
            }
            $map[$personId][] = [
                'role' => (string) ($row['role'] ?? ''),
                'context' => $row['context'] ?? null,
                'payload' => $payload,
            ];
        }

        return $map;
    }

    /**
     * @param array<int, string> $roles
     */
    public function replaceForContext(int $personId, array $roles, ?string $context = null): void
    {
        if (!$this->pdo || $personId <= 0) {
            return;
        }
        $contextValue = $context ?? '';
        $deleteStmt = $this->pdo->prepare('DELETE FROM pessoas_papeis WHERE pessoa_id = :id AND ' . ($context === null ? 'context IS NULL' : 'context = :context'));
        $params = [':id' => $personId];
        if ($context !== null) {
            $params[':context'] = $contextValue;
        }
        $deleteStmt->execute($params);

        foreach ($roles as $role) {
            $role = trim((string) $role);
            if ($role === '') {
                continue;
            }
            $this->assign($personId, $role, $context);
        }
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS pessoas_papeis (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NOT NULL,
          role VARCHAR(60) NOT NULL,
          context VARCHAR(60) NULL,
          payload JSON NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_pessoa_role_context (pessoa_id, role, context),
          INDEX idx_pessoas_papeis_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
