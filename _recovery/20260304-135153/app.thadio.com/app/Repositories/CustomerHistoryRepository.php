<?php

namespace App\Repositories;

use App\Support\Auth;
use PDO;

class CustomerHistoryRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function log(int $customerId, string $action, array $payload = []): void
    {
        if (!$this->pdo || $customerId <= 0) {
            return;
        }

        $user = Auth::user();
        $stmt = $this->pdo->prepare(
            'INSERT INTO cliente_historico (pessoa_id, action, payload, user_id, user_email) ' .
            'VALUES (:pessoa_id, :action, :payload, :user_id, :user_email)'
        );

        $payloadJson = null;
        if (!empty($payload)) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadJson = $encoded === false ? null : $encoded;
        }

        $stmt->execute([
            ':pessoa_id' => $customerId,
            ':action' => $action,
            ':payload' => $payloadJson,
            ':user_id' => $user['id'] ?? null,
            ':user_email' => $user['email'] ?? null,
        ]);
    }

    public function listByCustomer(int $customerId, int $limit = 0): array
    {
        if (!$this->pdo || $customerId <= 0) {
            return [];
        }

        $useLimit = $limit > 0;
        $sql = 'SELECT action, payload, user_id, user_email, created_at ' .
            'FROM cliente_historico ' .
            'WHERE pessoa_id = :pessoa_id ' .
            'ORDER BY created_at DESC';
        if ($useLimit) {
            $sql .= ' LIMIT :limit';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pessoa_id', $customerId, PDO::PARAM_INT);
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cliente_historico (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NOT NULL,
          action VARCHAR(40) NOT NULL,
          payload JSON NULL,
          user_id BIGINT NULL,
          user_email VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_pessoa (pessoa_id),
          INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sql);

        // Adicionar pessoa_id se ainda não existir (migração)
        $this->ensureColumn('pessoa_id', 'BIGINT UNSIGNED NULL', 'customer_id');
        
        // Backfill pessoa_id a partir de customer_id se necessário
        try {
            $this->pdo->exec(
                "UPDATE cliente_historico 
                 SET pessoa_id = customer_id 
                 WHERE (pessoa_id IS NULL OR pessoa_id = 0) 
                   AND customer_id IS NOT NULL 
                   AND customer_id > 0"
            );
        } catch (\Throwable $e) {
            // Silenciosamente falha se customer_id não existir mais
        }
    }

    private function ensureColumn(string $column, string $definition, ?string $after = null): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cliente_historico' 
                   AND COLUMN_NAME = :column"
            );
            $stmt->execute([':column' => $column]);
            
            if ($stmt->fetchColumn() == 0) {
                $afterClause = $after ? "AFTER {$after}" : '';
                $this->pdo->exec("ALTER TABLE cliente_historico ADD COLUMN {$column} {$definition} {$afterClause}");
            }
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar coluna {$column} em cliente_historico: " . $e->getMessage());
        }
    }
}
