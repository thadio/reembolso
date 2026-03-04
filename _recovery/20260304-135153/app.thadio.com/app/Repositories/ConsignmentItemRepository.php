<?php

namespace App\Repositories;

use PDO;

class ConsignmentItemRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
                $this->ensureUnifiedColumns();
                $this->ensureProductSkuForeignKey();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela consignment_items: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $consignmentId = (int) ($data['consignment_id'] ?? 0);
        if ($consignmentId <= 0) {
            throw new \InvalidArgumentException('consignment_id é obrigatório.');
        }

        $productSku = (int) ($data['product_sku'] ?? 0);
        if ($productSku <= 0) {
            throw new \InvalidArgumentException('product_sku é obrigatório.');
        }

        $quantity = isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1;
        $percentOverride = isset($data['percent_override']) && $data['percent_override'] !== ''
            ? (float) $data['percent_override']
            : null;
        $minimumPrice = isset($data['minimum_price']) && $data['minimum_price'] !== ''
            ? (float) $data['minimum_price']
            : null;

        $existingStmt = $this->pdo->prepare(
            'SELECT id
             FROM consignment_items
             WHERE consignment_id = :consignment_id
               AND product_sku = :product_sku
             LIMIT 1'
        );
        $existingStmt->execute([
            ':consignment_id' => $consignmentId,
            ':product_sku' => $productSku,
        ]);
        $existingId = (int) ($existingStmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $update = $this->pdo->prepare(
                'UPDATE consignment_items
                 SET quantity = :quantity,
                     percent_override = :percent_override,
                     minimum_price = :minimum_price
                 WHERE id = :id'
            );
            $update->execute([
                ':id' => $existingId,
                ':quantity' => $quantity,
                ':percent_override' => $percentOverride,
                ':minimum_price' => $minimumPrice,
            ]);

            return $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO consignment_items (
                consignment_id,
                product_sku,
                quantity,
                percent_override,
                minimum_price
            ) VALUES (
                :consignment_id,
                :product_sku,
                :quantity,
                :percent_override,
                :minimum_price
            )'
        );
        $insert->execute([
            ':consignment_id' => $consignmentId,
            ':product_sku' => $productSku,
            ':quantity' => $quantity,
            ':percent_override' => $percentOverride,
            ':minimum_price' => $minimumPrice,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS consignment_items (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          consignment_id BIGINT UNSIGNED NOT NULL,
          product_sku BIGINT UNSIGNED NULL,
          quantity INT UNSIGNED NOT NULL DEFAULT 1,
          percent_override DECIMAL(5,2) NULL,
          minimum_price DECIMAL(10,2) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_consign_item_consignment (consignment_id),
          INDEX idx_consign_item_product_sku (product_sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    private function ensureUnifiedColumns(): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->ensureColumn('consignment_items', 'product_sku', 'BIGINT UNSIGNED NULL AFTER consignment_id');
        $this->ensureColumn('consignment_items', 'quantity', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER product_sku');

        // Migra remanescentes da coluna de transição para product_sku antes de remover.
        if ($this->columnExists('consignment_items', 'product_sku')
            && $this->columnExists('consignment_items', 'inventory_item_id')) {
            $this->pdo->exec(
                "UPDATE consignment_items
                 SET product_sku = COALESCE(product_sku, inventory_item_id)
                 WHERE product_sku IS NULL AND inventory_item_id IS NOT NULL"
            );
            $this->dropColumn('consignment_items', 'inventory_item_id');
        }
    }

    private function ensureProductSkuForeignKey(): void
    {
        if (!$this->pdo) {
            return;
        }
        if (
            !$this->columnExists('consignment_items', 'product_sku')
            || !$this->columnExists('products', 'sku')
        ) {
            return;
        }
        if ($this->foreignKeyExists('consignment_items', 'fk_consign_items_product_sku')) {
            return;
        }
        if (!$this->isCompatibleForeignKey('consignment_items', 'product_sku', 'products', 'sku')) {
            return;
        }
        if ($this->countProductOrphans('consignment_items', 'product_sku') > 0) {
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE consignment_items
                 ADD CONSTRAINT fk_consign_items_product_sku
                 FOREIGN KEY (product_sku) REFERENCES products(sku)
                 ON DELETE RESTRICT
                 ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            error_log('Falha ao adicionar FK de consignment_items.product_sku: ' . $e->getMessage());
        }
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (!$this->pdo || $this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function dropColumn(string $table, string $column): void
    {
        if (!$this->pdo || !$this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column));
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :constraint
               AND CONSTRAINT_TYPE = "FOREIGN KEY"
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':constraint' => $constraint,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function countProductOrphans(string $table, string $column): int
    {
        if (!$this->pdo || !$this->columnExists($table, $column)) {
            return 0;
        }
        $sql = "SELECT COUNT(*)
                FROM {$table} t
                WHERE t.{$column} IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.{$column})";
        $stmt = $this->pdo->query($sql);
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    private function isCompatibleForeignKey(
        string $fromTable,
        string $fromColumn,
        string $toTable,
        string $toColumn
    ): bool {
        $fromType = $this->columnTypeInfo($fromTable, $fromColumn);
        $toType = $this->columnTypeInfo($toTable, $toColumn);
        if ($fromType === null || $toType === null) {
            return false;
        }

        return $fromType['data_type'] === $toType['data_type']
            && $fromType['unsigned'] === $toType['unsigned'];
    }

    /**
     * @return array{data_type:string,unsigned:bool}|null
     */
    private function columnTypeInfo(string $table, string $column): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $columnType = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
        return [
            'data_type' => strtolower((string) ($row['DATA_TYPE'] ?? '')),
            'unsigned' => str_contains($columnType, 'unsigned'),
        ];
    }
}
