<?php

namespace App\Repositories;

use PDO;

class OrderAddressRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela order_addresses: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS order_addresses (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          address_type ENUM('billing','shipping') NOT NULL,
          full_name VARCHAR(200) NULL,
          email VARCHAR(255) NULL,
          phone VARCHAR(50) NULL,
          document VARCHAR(40) NULL,
          country VARCHAR(60) NULL,
          state VARCHAR(60) NULL,
          city VARCHAR(120) NULL,
          neighborhood VARCHAR(120) NULL,
          street VARCHAR(200) NULL,
          number VARCHAR(40) NULL,
          street2 VARCHAR(200) NULL,
          zip VARCHAR(30) NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_order_addresses_order (order_id),
          INDEX idx_order_addresses_type (address_type),
          INDEX idx_order_addresses_zip (zip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
