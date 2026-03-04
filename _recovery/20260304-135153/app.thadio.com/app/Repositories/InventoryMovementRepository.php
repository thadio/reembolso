<?php

namespace App\Repositories;

use PDO;

/**
 * InventoryMovementRepository - Rastreamento de mudanças de disponibilidade
 *
 * Registra toda mudança de quantity em products (modelo unificado)
 * Tipos: venda, devolucao, ajuste, baixa
 */
class InventoryMovementRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela inventory_movements: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Cria tabela inventory_movements (modelo unificado)
     * Registra mudanças em products.quantity
     * FKs são adicionadas apenas se tabelas referenciadas existirem
     */
    private function ensureTable(): void
    {
        // 1) Criar tabela sem FKs
        $sql = "CREATE TABLE IF NOT EXISTS inventory_movements (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_sku BIGINT UNSIGNED NOT NULL COMMENT 'SKU do produto (FK para products)',
          movement_type ENUM('venda','devolucao','ajuste','baixa') NOT NULL COMMENT 'Tipo de movimento',
          quantity_before INT UNSIGNED NOT NULL COMMENT 'Quantidade antes da operação',
          quantity_after INT UNSIGNED NOT NULL COMMENT 'Quantidade após a operação',
          quantity_change INT NOT NULL COMMENT 'Mudança: qty_after - qty_before (pode ser negativo)',
          order_id INT UNSIGNED NULL COMMENT 'Pedido relacionado (se aplicável)',
          user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que executou a operação',
          notes TEXT NULL COMMENT 'Observações sobre o movimento',
          occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando ocorreu o movimento',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          
          INDEX idx_inventory_mov_product (product_sku),
          INDEX idx_inventory_mov_type (movement_type),
          INDEX idx_inventory_mov_order (order_id),
          INDEX idx_inventory_mov_occurred (occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Auditoria de movimentações de disponibilidade';";
        $this->pdo->exec($sql);

        // 2) Ajustar tipos locais para combinar com PKs referenciadas
        $this->ensureColumnTypeCompatibility();

        // 2) Adicionar FKs apenas se tabelas referenciadas existirem
        $this->addForeignKeysIfTableExists();
    }
    
    /**
     * Adiciona Foreign Keys apenas se as tabelas referenciadas existirem
     */
    private function addForeignKeysIfTableExists(): void
    {
        // FK para products
        if ($this->tableExists('products') && $this->canCreateForeignKey('inventory_movements', 'product_sku', 'products', 'sku')) {
            $this->addForeignKeyIfNotExists(
                'inventory_movements',
                'fk_inv_mov_product',
                'FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE'
            );
        }
        
        // FK para orders
        if ($this->tableExists('orders') && $this->canCreateForeignKey('inventory_movements', 'order_id', 'orders', 'id')) {
            $this->addForeignKeyIfNotExists(
                'inventory_movements',
                'fk_inv_mov_order',
                'FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL'
            );
        }
        
        // FK para usuarios
        if ($this->tableExists('usuarios') && $this->canCreateForeignKey('inventory_movements', 'user_id', 'usuarios', 'id')) {
            $this->addForeignKeyIfNotExists(
                'inventory_movements',
                'fk_inv_mov_user',
                'FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL'
            );
        }
    }

    private function ensureColumnTypeCompatibility(): void
    {
        $this->syncColumnTypeFromReference('inventory_movements', 'order_id', 'orders', 'id');
        $this->syncColumnTypeFromReference('inventory_movements', 'user_id', 'usuarios', 'id');
        $this->syncColumnTypeFromReference('inventory_movements', 'product_sku', 'products', 'sku');
    }

    private function syncColumnTypeFromReference(string $table, string $column, string $refTable, string $refColumn): void
    {
        if (
            !$this->tableExists($table)
            || !$this->tableExists($refTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($refTable, $refColumn)
        ) {
            return;
        }

        $local = $this->columnTypeInfo($table, $column);
        $reference = $this->columnTypeInfo($refTable, $refColumn);
        if ($local === null || $reference === null) {
            return;
        }

        if (
            $local['data_type'] === $reference['data_type']
            && $local['unsigned'] === $reference['unsigned']
        ) {
            return;
        }

        $typeSql = strtoupper($reference['data_type']) . ($reference['unsigned'] ? ' UNSIGNED' : '');
        $nullSql = $local['nullable'] ? 'NULL' : 'NOT NULL';

        try {
            $this->pdo->exec("ALTER TABLE {$table} MODIFY COLUMN {$column} {$typeSql} {$nullSql}");
        } catch (\Throwable $e) {
            error_log("Falha ao ajustar tipo {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function canCreateForeignKey(string $table, string $column, string $refTable, string $refColumn): bool
    {
        if (
            !$this->columnExists($table, $column)
            || !$this->columnExists($refTable, $refColumn)
        ) {
            return false;
        }

        $local = $this->columnTypeInfo($table, $column);
        $reference = $this->columnTypeInfo($refTable, $refColumn);
        if ($local === null || $reference === null) {
            return false;
        }

        return $local['data_type'] === $reference['data_type']
            && $local['unsigned'] === $reference['unsigned'];
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column
                 LIMIT 1'
            );
            $stmt->execute([
                ':table' => $tableName,
                ':column' => $columnName,
            ]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{data_type:string,unsigned:bool,nullable:bool}|null
     */
    private function columnTypeInfo(string $tableName, string $columnName): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column
                 LIMIT 1'
            );
            $stmt->execute([
                ':table' => $tableName,
                ':column' => $columnName,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            $columnType = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
            return [
                'data_type' => strtolower((string) ($row['DATA_TYPE'] ?? '')),
                'unsigned' => str_contains($columnType, 'unsigned'),
                'nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    private function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
            return $stmt && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    private function addForeignKeyIfNotExists(string $tableName, string $constraintName, string $definition): void
    {
        try {
            // Verificar se constraint já existe
            $sql = "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = :table 
                    AND CONSTRAINT_NAME = :constraint 
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':table' => $tableName, ':constraint' => $constraintName]);
            
            if ($stmt->rowCount() === 0) {
                // Adicionar constraint
                $alterSql = "ALTER TABLE $tableName ADD CONSTRAINT $constraintName $definition";
                $this->pdo->exec($alterSql);
            }
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar FK $constraintName: " . $e->getMessage());
        }
    }
    
    /**
     * Registra um movimento de disponibilidade
     *
     * @param int $productSku SKU do produto
     * @param string $movementType Tipo: venda, devolucao, ajuste, baixa
     * @param int $quantityBefore Quantidade antes
     * @param int $quantityAfter Quantidade depois
     * @param int|null $orderId Pedido relacionado (opcional)
     * @param int|null $userId Usuário que executou (opcional)
     * @param string|null $notes Observações (opcional)
     * @return bool
     */
    public function log(
        int $productSku,
        string $movementType,
        int $quantityBefore,
        int $quantityAfter,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $notes = null
    ): bool {
        if (!$this->pdo) {
            return false;
        }
        
        $quantityChange = $quantityAfter - $quantityBefore;
        
        $sql = "INSERT INTO inventory_movements (
                  product_sku, movement_type, quantity_before, quantity_after, 
                  quantity_change, order_id, user_id, notes
                ) VALUES (
                  :product_sku, :movement_type, :quantity_before, :quantity_after,
                  :quantity_change, :order_id, :user_id, :notes
                )";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':product_sku' => $productSku,
            ':movement_type' => $movementType,
            ':quantity_before' => $quantityBefore,
            ':quantity_after' => $quantityAfter,
            ':quantity_change' => $quantityChange,
            ':order_id' => $orderId,
            ':user_id' => $userId,
            ':notes' => $notes,
        ]);
    }
    
    /**
     * Busca histórico de movimentos de um produto
     * 
     * @param int $productSku SKU do produto
     * @param int $limit Limite de resultados
     * @return array
     */
    public function getProductHistory(int $productSku, int $limit = 50): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $sql = "SELECT 
                  im.*,
                  u.nome AS user_name,
                  o.order_number AS order_number
                FROM inventory_movements im
                LEFT JOIN usuarios u ON im.user_id = u.id
                LEFT JOIN orders o ON im.order_id = o.id
                WHERE im.product_sku = :sku
                ORDER BY im.occurred_at DESC, im.id DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sku', $productSku, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Se erro de coluna, retornar sem joins
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $sqlSimple = "SELECT * FROM inventory_movements 
                              WHERE product_sku = :sku 
                              ORDER BY occurred_at DESC, id DESC 
                              LIMIT :limit";
                $stmtSimple = $this->pdo->prepare($sqlSimple);
                $stmtSimple->bindValue(':sku', $productSku, PDO::PARAM_INT);
                $stmtSimple->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmtSimple->execute();
                return $stmtSimple->fetchAll(PDO::FETCH_ASSOC);
            }
            throw $e;
        }
    }
    
    /**
     * Busca movimentos recentes (todos os produtos)
     * 
     * @param int $limit Limite de resultados
     * @return array
     */
    public function getRecentMovements(int $limit = 100): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $sql = "SELECT 
                  im.*,
                  p.name AS product_name,
                  u.nome AS user_name,
                  o.order_number AS order_number
                FROM inventory_movements im
                INNER JOIN products p ON im.product_sku = p.sku
                LEFT JOIN usuarios u ON im.user_id = u.id
                LEFT JOIN orders o ON im.order_id = o.id
                ORDER BY im.occurred_at DESC, im.id DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
