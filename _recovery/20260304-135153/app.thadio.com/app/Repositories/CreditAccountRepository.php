<?php

namespace App\Repositories;

use PDO;

use App\Support\AuditableTrait;
class CreditAccountRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela credit_accounts: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS credit_accounts (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NOT NULL,
          label VARCHAR(120) NULL,
          account_type ENUM('credito','consignacao') NOT NULL DEFAULT 'credito',
          status ENUM('active','inactive','ativo','inativo') NOT NULL DEFAULT 'active',
          balance DECIMAL(12,2) NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_credit_accounts_pessoa (pessoa_id),
          INDEX idx_credit_accounts_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
    
    /**
     * Save credit account (insert or update)
     */
    public function save(array $data): int
    {
        $id = isset($data['id']) ? (int)$data['id'] : null;
        $isUpdate = (bool) $id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM credit_accounts WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($id) {
            // UPDATE
            $sql = "UPDATE credit_accounts SET
                pessoa_id = :pessoa_id,
                label = :label,
                account_type = :account_type,
                status = :status,
                balance = :balance
            WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':pessoa_id' => $data['pessoa_id'],
                ':label' => $data['label'] ?? null,
                ':account_type' => $data['account_type'] ?? 'credito',
                ':status' => $data['status'] ?? 'active',
                ':balance' => $data['balance'] ?? 0.00,
            ]);
        } else {
            // INSERT
            $sql = "INSERT INTO credit_accounts (
                pessoa_id, label, account_type, status, balance
            ) VALUES (
                :pessoa_id, :label, :account_type, :status, :balance
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':pessoa_id' => $data['pessoa_id'],
                ':label' => $data['label'] ?? null,
                ':account_type' => $data['account_type'] ?? 'credito',
                ':status' => $data['status'] ?? 'active',
                ':balance' => $data['balance'] ?? 0.00,
            ]);
            
            $id = (int)$this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM credit_accounts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'credit_accounts',
            $id,
            $oldData,
            $newData
        );
        
        return $id;
    }
    
    /**
     * Find credit account by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM credit_accounts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Update account balance (adds amount to current balance)
     */
    public function updateBalance(int $id, float $amount): void
    {
        $sql = "UPDATE credit_accounts SET balance = balance + :amount WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':amount' => $amount]);
    }
}
