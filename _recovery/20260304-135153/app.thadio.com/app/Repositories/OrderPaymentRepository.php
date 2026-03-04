<?php

namespace App\Repositories;

use PDO;

class OrderPaymentRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela order_payments: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS order_payments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          method_id INT UNSIGNED NULL,
          method_name VARCHAR(100) NULL,
          status ENUM('pendente','pago','falhou','cancelado','estornado','parcial') NOT NULL DEFAULT 'pendente',
          amount DECIMAL(10,2) NOT NULL,
          paid_at DATETIME NULL,
          transaction_ref VARCHAR(120) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_order_payments_order (order_id),
          INDEX idx_order_payments_status (status),
          INDEX idx_order_payments_method (method_id),
          INDEX idx_order_payments_ref (transaction_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
