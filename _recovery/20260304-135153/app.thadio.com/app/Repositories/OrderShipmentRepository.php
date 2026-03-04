<?php

namespace App\Repositories;

use PDO;

class OrderShipmentRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela order_shipments: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS order_shipments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          carrier VARCHAR(80) NULL,
          service VARCHAR(80) NULL,
          tracking_code VARCHAR(120) NULL,
          status ENUM('pendente','preparando','enviado','entregue','cancelado') NOT NULL DEFAULT 'pendente',
          shipped_at DATETIME NULL,
          delivered_at DATETIME NULL,
          eta DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_order_shipments_order (order_id),
          INDEX idx_order_shipments_status (status),
          INDEX idx_order_shipments_tracking (tracking_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
