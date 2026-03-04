<?php

namespace App\Repositories;

use PDO;

class ShipmentEventRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela shipment_events: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS shipment_events (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_shipment_id BIGINT UNSIGNED NOT NULL,
          status VARCHAR(50) NOT NULL,
          description VARCHAR(255) NULL,
          location VARCHAR(120) NULL,
          occurred_at DATETIME NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_shipment_events_shipment (order_shipment_id),
          INDEX idx_shipment_events_status (status),
          INDEX idx_shipment_events_occurred (occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
