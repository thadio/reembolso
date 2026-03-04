<?php

namespace App\Repositories;

use PDO;

class FinanceEntriesRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela finance_entries: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS finance_entries (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          entry_type ENUM('pagar','receber') NOT NULL,
          pessoa_id BIGINT UNSIGNED NULL,
          order_id INT UNSIGNED NULL,
          consignment_id BIGINT UNSIGNED NULL,
          category_id INT UNSIGNED NULL,
          amount DECIMAL(12,2) NOT NULL,
          due_date DATE NULL,
          status ENUM('pendente','pago','cancelado','parcial') NOT NULL DEFAULT 'pendente',
          paid_at DATETIME NULL,
          paid_amount DECIMAL(12,2) NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_finance_entries_type (entry_type),
          INDEX idx_finance_entries_status (status),
          INDEX idx_finance_entries_pessoa (pessoa_id),
          INDEX idx_finance_entries_order (order_id),
          INDEX idx_finance_entries_consignment (consignment_id),
          INDEX idx_finance_entries_due (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
