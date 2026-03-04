<?php

namespace App\Repositories;

use PDO;

class FinanceTransactionRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela finance_transactions: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS finance_transactions (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          finance_entry_id BIGINT UNSIGNED NOT NULL,
          amount DECIMAL(12,2) NOT NULL,
          method_id INT UNSIGNED NULL,
          account_id INT UNSIGNED NULL,
          transaction_ref VARCHAR(120) NULL,
          occurred_at DATETIME NOT NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_finance_tx_entry (finance_entry_id),
          INDEX idx_finance_tx_method (method_id),
          INDEX idx_finance_tx_account (account_id),
          INDEX idx_finance_tx_ref (transaction_ref),
          INDEX idx_finance_tx_occurred (occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
