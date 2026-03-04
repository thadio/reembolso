<?php

namespace App\Repositories;

use PDO;

class OrderItemRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela order_items: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS order_items (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          product_sku BIGINT UNSIGNED NULL,
          product_name VARCHAR(200) NOT NULL DEFAULT '',
          sku VARCHAR(100) NULL,
          quantity INT NOT NULL DEFAULT 1,
          price DECIMAL(10,2) NOT NULL DEFAULT 0,
          total DECIMAL(10,2) NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_order_items_order (order_id),
          INDEX idx_order_items_product_sku (product_sku),
          INDEX idx_order_items_sku (sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('product_sku', "ALTER TABLE order_items ADD COLUMN product_sku BIGINT UNSIGNED NULL AFTER order_id");
        $this->ensureColumn('product_name', "ALTER TABLE order_items ADD COLUMN product_name VARCHAR(200) NOT NULL DEFAULT '' AFTER product_sku");
        $this->ensureColumn('quantity', "ALTER TABLE order_items ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER sku");
        $this->ensureColumn('price', "ALTER TABLE order_items ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER quantity");
        $this->ensureColumn('total', "ALTER TABLE order_items ADD COLUMN total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price");

        $this->backfillLegacyColumns();
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        if ($this->pdo && !$this->columnExists($column)) {
            $this->pdo->exec($ddl);
        }
    }

    private function columnExists(string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM order_items LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = (bool) $stmt->fetch();
        $stmt->closeCursor();
        return $exists;
    }

    private function backfillLegacyColumns(): void
    {
        if ($this->columnExists('product_id')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET product_sku = COALESCE(product_sku, product_id)
                 WHERE product_sku IS NULL AND product_id IS NOT NULL"
            );
        }
        if ($this->columnExists('catalog_product_id')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET product_sku = COALESCE(product_sku, catalog_product_id)
                 WHERE product_sku IS NULL AND catalog_product_id IS NOT NULL"
            );
        }
        if ($this->columnExists('name_snapshot')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET product_name = COALESCE(NULLIF(product_name, ''), name_snapshot)
                 WHERE name_snapshot IS NOT NULL AND name_snapshot <> ''"
            );
        }
        if ($this->columnExists('qty')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET quantity = COALESCE(NULLIF(quantity, 0), qty)
                 WHERE qty IS NOT NULL"
            );
        }
        if ($this->columnExists('unit_price')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET price = COALESCE(price, unit_price)
                 WHERE unit_price IS NOT NULL"
            );
        }
        if ($this->columnExists('line_total')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET total = COALESCE(total, line_total)
                 WHERE line_total IS NOT NULL"
            );
        }
        if ($this->columnExists('product_sku')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET sku = COALESCE(NULLIF(sku, ''), CAST(product_sku AS CHAR))
                 WHERE product_sku IS NOT NULL"
            );
        }
    }
}
