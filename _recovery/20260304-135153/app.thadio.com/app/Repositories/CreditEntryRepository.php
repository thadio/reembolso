<?php

namespace App\Repositories;

use PDO;

class CreditEntryRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela credit_entries: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS credit_entries (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          credit_account_id BIGINT UNSIGNED NOT NULL,
          entry_type ENUM('credit','debit','credito','debito') NOT NULL,
          amount DECIMAL(12,2) NOT NULL,
          balance_after DECIMAL(12,2) NULL,
          description VARCHAR(255) NULL,
          reference_type VARCHAR(40) NULL,
          reference_id BIGINT UNSIGNED NULL,
          occurred_at DATETIME NOT NULL,
          origin VARCHAR(40) NULL,
          ref_type VARCHAR(40) NULL,
          ref_id BIGINT UNSIGNED NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_credit_entries_account (credit_account_id),
          INDEX idx_credit_entries_occurred (occurred_at),
          INDEX idx_credit_entries_origin (origin),
          INDEX idx_credit_entries_ref (ref_type, ref_id),
          INDEX idx_credit_entries_reference (reference_type, reference_id),
          INDEX idx_credit_entries_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
    
    /**
     * Save credit entry (immutable, insert only)
     */
    public function save(array $data): int
    {
        $sql = "INSERT INTO credit_entries (
            credit_account_id, entry_type, amount, balance_after,
            description, reference_type, reference_id, occurred_at
        ) VALUES (
            :credit_account_id, :entry_type, :amount, :balance_after,
            :description, :reference_type, :reference_id, :occurred_at
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':credit_account_id' => $data['credit_account_id'],
            ':entry_type' => $data['entry_type'],
            ':amount' => $data['amount'],
            ':balance_after' => $data['balance_after'] ?? null,
            ':description' => $data['description'] ?? null,
            ':reference_type' => $data['reference_type'] ?? null,
            ':reference_id' => $data['reference_id'] ?? null,
            ':occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s'),
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Find credit entry by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM credit_entries WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * List entries for a specific credit account
     */
    public function listByAccount(int $creditAccountId, int $limit = 0): array
    {
        $useLimit = $limit > 0;
        $sql = "SELECT * FROM credit_entries 
                WHERE credit_account_id = :account_id 
                ORDER BY occurred_at DESC, id DESC";
        if ($useLimit) {
            $sql .= "\n                LIMIT :limit";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':account_id', $creditAccountId, PDO::PARAM_INT);
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
