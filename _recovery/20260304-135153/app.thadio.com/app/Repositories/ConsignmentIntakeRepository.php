<?php

namespace App\Repositories;

use App\Models\ConsignmentIntake;
use PDO;

use App\Support\AuditableTrait;
class ConsignmentIntakeRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                PeopleCompatViewRepository::ensure($this->pdo);
            } catch (\Throwable $e) {
                error_log('Falha ao preparar views de pessoas: ' . $e->getMessage());
            }
            $this->ensureTable();
            $this->ensureProductLinkCompatibility();
            $this->ensureProductLinkForeignKey();
            $this->backfillPessoaIds();
            $this->removeLegacyVendorColumn();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function listWithTotals(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT r.*,
                COALESCE(vp.full_name, p.full_name) AS supplier_name,
                COALESCE(vp.id_vendor, r.pessoa_id) AS supplier_code,
                r.pessoa_id AS supplier_pessoa_id,
                COALESCE(vp.id_vendor, r.pessoa_id) AS vendor_id,
                COALESCE(items.total_received, 0) AS total_received,
                COALESCE(return_stats.total_returned, 0) AS total_returned,
                COALESCE(return_stats.returns_count, 0) AS returns_count
            FROM consignacao_recebimentos r
            LEFT JOIN pessoas p ON p.id = r.pessoa_id
            LEFT JOIN vw_fornecedores_compat vp ON vp.id = r.pessoa_id
            LEFT JOIN (
                SELECT intake_id, SUM(quantity) AS total_received
                FROM consignacao_recebimento_itens
                GROUP BY intake_id
            ) items ON items.intake_id = r.id
            LEFT JOIN (
                SELECT r2.intake_id, SUM(ri.quantity) AS total_returned, COUNT(DISTINCT r2.id) AS returns_count
                FROM consignacao_devolucoes r2
                JOIN consignacao_devolucao_itens ri ON ri.return_id = r2.id
                GROUP BY r2.intake_id
            ) return_stats ON return_stats.intake_id = r.id
            ORDER BY r.received_at DESC, r.id DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listPendingForDashboard(int $limit = 6): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        $sql = "SELECT r.*,
                COALESCE(vp.full_name, p.full_name) AS supplier_name,
                COALESCE(vp.id_vendor, r.pessoa_id) AS supplier_code,
                r.pessoa_id AS supplier_pessoa_id,
                COALESCE(vp.id_vendor, r.pessoa_id) AS vendor_id,
                COALESCE(items.total_received, 0) AS total_received,
                COALESCE(return_stats.total_returned, 0) AS total_returned,
                COALESCE(return_stats.returns_count, 0) AS returns_count,
                COALESCE(link_stats.total_linked, 0) AS total_linked
            FROM consignacao_recebimentos r
            LEFT JOIN pessoas p ON p.id = r.pessoa_id
            LEFT JOIN vw_fornecedores_compat vp ON vp.id = r.pessoa_id
            LEFT JOIN (
                SELECT intake_id, SUM(quantity) AS total_received
                FROM consignacao_recebimento_itens
                GROUP BY intake_id
            ) items ON items.intake_id = r.id
            LEFT JOIN (
                SELECT r2.intake_id, SUM(ri.quantity) AS total_returned, COUNT(DISTINCT r2.id) AS returns_count
                FROM consignacao_devolucoes r2
                JOIN consignacao_devolucao_itens ri ON ri.return_id = r2.id
                GROUP BY r2.intake_id
            ) return_stats ON return_stats.intake_id = r.id
            LEFT JOIN (
                SELECT intake_id, COUNT(*) AS total_linked
                FROM consignacao_recebimento_produtos
                GROUP BY intake_id
            ) link_stats ON link_stats.intake_id = r.id
            WHERE (COALESCE(items.total_received, 0) - COALESCE(return_stats.total_returned, 0)) > COALESCE(link_stats.total_linked, 0)
            ORDER BY r.received_at DESC, r.id DESC
            LIMIT {$limit}";

        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?ConsignmentIntake
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM consignacao_recebimentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? ConsignmentIntake::fromArray($row) : null;
    }

    public function findWithVendor(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $sql = "SELECT r.*,
                COALESCE(vp.full_name, p.full_name) AS supplier_name,
                COALESCE(vp.id_vendor, r.pessoa_id) AS supplier_code,
                r.pessoa_id AS supplier_pessoa_id,
                COALESCE(vp.id_vendor, r.pessoa_id) AS vendor_id
            FROM consignacao_recebimentos r
            LEFT JOIN pessoas p ON p.id = r.pessoa_id
            LEFT JOIN vw_fornecedores_compat vp ON vp.id = r.pessoa_id
            WHERE r.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listItems(int $intakeId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT category_id, quantity FROM consignacao_recebimento_itens WHERE intake_id = :intake_id ORDER BY category_id"
        );
        $stmt->execute([':intake_id' => $intakeId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int, array{category_id: int, quantity: int}> $items
     */
    public function save(ConsignmentIntake $intake, array $items): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $intake->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM consignacao_recebimentos WHERE id = :id");
            $stmt->execute([':id' => $intake->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($isUpdate) {
            $sql = "UPDATE consignacao_recebimentos
                SET pessoa_id = :pessoa_id, received_at = :received_at, notes = :notes
                WHERE id = :id";
            $params = $intake->toDbParams() + [':id' => $intake->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO consignacao_recebimentos (pessoa_id, received_at, notes)
                VALUES (:pessoa_id, :received_at, :notes)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($intake->toDbParams());
            $intake->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM consignacao_recebimentos WHERE id = :id");
        $stmt->execute([':id' => $intake->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'consignacao_recebimentos',
            $intake->id,
            $oldData,
            $newData
        );

        $deleteStmt = $this->pdo->prepare("DELETE FROM consignacao_recebimento_itens WHERE intake_id = :intake_id");
        $deleteStmt->execute([':intake_id' => $intake->id]);

        if (!empty($items)) {
            $insert = $this->pdo->prepare(
                "INSERT INTO consignacao_recebimento_itens (intake_id, category_id, quantity)
                VALUES (:intake_id, :category_id, :quantity)"
            );
            foreach ($items as $item) {
                $insert->execute([
                    ':intake_id' => $intake->id,
                    ':category_id' => $item['category_id'],
                    ':quantity' => $item['quantity'],
                ]);
            }
        }
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM consignacao_recebimentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "DELETE ri FROM consignacao_devolucao_itens ri
            JOIN consignacao_devolucoes r ON r.id = ri.return_id
            WHERE r.intake_id = :intake_id"
        );
        $stmt->execute([':intake_id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM consignacao_devolucoes WHERE intake_id = :intake_id");
        $stmt->execute([':intake_id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM consignacao_recebimento_itens WHERE intake_id = :intake_id");
        $stmt->execute([':intake_id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM consignacao_recebimento_produtos WHERE intake_id = :intake_id");
        $stmt->execute([':intake_id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM consignacao_recebimentos WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'consignacao_recebimentos', $id, $oldData, null);
    }

    /**
     * @param array<int, array{category_id: int, quantity: int}> $items
     */
    public function addReturn(int $intakeId, array $data, array $items): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO consignacao_devolucoes (intake_id, pessoa_id, returned_at, notes)
            VALUES (:intake_id, :pessoa_id, :returned_at, :notes)"
        );
        $stmt->execute([
            ':intake_id' => $intakeId,
            ':pessoa_id' => $data['pessoa_id'] ?? null,
            ':returned_at' => $data['returned_at'],
            ':notes' => $data['notes'] ?? null,
        ]);
        $returnId = (int) $this->pdo->lastInsertId();

        if (!empty($items)) {
            $insert = $this->pdo->prepare(
                "INSERT INTO consignacao_devolucao_itens (return_id, category_id, quantity)
                VALUES (:return_id, :category_id, :quantity)"
            );
            foreach ($items as $item) {
                $insert->execute([
                    ':return_id' => $returnId,
                    ':category_id' => $item['category_id'],
                    ':quantity' => $item['quantity'],
                ]);
            }
        }

        return $returnId;
    }

    public function listReturns(int $intakeId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT r.*, COALESCE(SUM(ri.quantity), 0) AS total_returned
            FROM consignacao_devolucoes r
            LEFT JOIN consignacao_devolucao_itens ri ON ri.return_id = r.id
            WHERE r.intake_id = :intake_id
            GROUP BY r.id
            ORDER BY r.returned_at DESC, r.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':intake_id' => $intakeId]);
        return $stmt->fetchAll();
    }

    public function addProductLink(int $intakeId, int $productId): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO consignacao_recebimento_produtos (intake_id, product_id)
            VALUES (:intake_id, :product_id)"
        );
        $stmt->execute([
            ':intake_id' => $intakeId,
            ':product_id' => $productId,
        ]);
    }

    public function listLinkedProducts(int $intakeId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT product_id, created_at
            FROM consignacao_recebimento_produtos
            WHERE intake_id = :intake_id
            ORDER BY created_at DESC, product_id DESC"
        );
        $stmt->execute([':intake_id' => $intakeId]);
        return $stmt->fetchAll();
    }

    public function findReturnWithIntake(int $returnId): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $sql = "SELECT r.*, c.received_at, c.pessoa_id AS intake_pessoa_id, c.notes AS intake_notes,
            COALESCE(vp.full_name, p.full_name) AS supplier_name,
            COALESCE(vp.id_vendor, c.pessoa_id) AS supplier_code,
            c.pessoa_id AS supplier_pessoa_id,
            COALESCE(vp.id_vendor, c.pessoa_id) AS vendor_id
            FROM consignacao_devolucoes r
            JOIN consignacao_recebimentos c ON c.id = r.intake_id
            LEFT JOIN pessoas p ON p.id = c.pessoa_id
            LEFT JOIN vw_fornecedores_compat vp ON vp.id = c.pessoa_id
            WHERE r.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $returnId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listReturnItems(int $returnId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT category_id, quantity FROM consignacao_devolucao_itens WHERE return_id = :return_id ORDER BY category_id"
        );
        $stmt->execute([':return_id' => $returnId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, int>
     */
    public function getItemTotalsByCategory(int $intakeId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT category_id, SUM(quantity) AS total
            FROM consignacao_recebimento_itens
            WHERE intake_id = :intake_id
            GROUP BY category_id"
        );
        $stmt->execute([':intake_id' => $intakeId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['category_id']] = (int) $row['total'];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function getReturnTotalsByCategory(int $intakeId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT ri.category_id, SUM(ri.quantity) AS total
            FROM consignacao_devolucoes r
            JOIN consignacao_devolucao_itens ri ON ri.return_id = r.id
            WHERE r.intake_id = :intake_id
            GROUP BY ri.category_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':intake_id' => $intakeId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['category_id']] = (int) $row['total'];
        }

        return $map;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS consignacao_recebimentos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NULL,
          received_at DATE NOT NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_consignacao_receb_pessoa (pessoa_id),
          INDEX idx_consignacao_receb_date (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
        $this->addColumnIfMissing('consignacao_recebimentos', 'pessoa_id', 'BIGINT UNSIGNED NULL', 'id');
        $this->addIndexIfMissing('consignacao_recebimentos', 'idx_consignacao_receb_pessoa', 'pessoa_id');

        $sqlItems = "CREATE TABLE IF NOT EXISTS consignacao_recebimento_itens (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          intake_id INT UNSIGNED NOT NULL,
          category_id INT UNSIGNED NOT NULL,
          quantity INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consignacao_receb_item (intake_id, category_id),
          INDEX idx_consignacao_receb_itens_intake (intake_id),
          INDEX idx_consignacao_receb_itens_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlItems);

        $sqlReturns = "CREATE TABLE IF NOT EXISTS consignacao_devolucoes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          intake_id INT UNSIGNED NOT NULL,
          pessoa_id INT UNSIGNED NULL,
          returned_at DATE NOT NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_consignacao_devol_intake (intake_id),
          INDEX idx_consignacao_devol_pessoa (pessoa_id),
          INDEX idx_consignacao_devol_date (returned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlReturns);
        $this->addColumnIfMissing('consignacao_devolucoes', 'pessoa_id', 'INT UNSIGNED NULL', 'intake_id');
        $this->addIndexIfMissing('consignacao_devolucoes', 'idx_consignacao_devol_pessoa', 'pessoa_id');

        $sqlReturnItems = "CREATE TABLE IF NOT EXISTS consignacao_devolucao_itens (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          return_id INT UNSIGNED NOT NULL,
          category_id INT UNSIGNED NOT NULL,
          quantity INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consignacao_devol_item (return_id, category_id),
          INDEX idx_consignacao_devol_itens_return (return_id),
          INDEX idx_consignacao_devol_itens_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlReturnItems);

        $sqlLinks = "CREATE TABLE IF NOT EXISTS consignacao_recebimento_produtos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          intake_id INT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consignacao_produto (intake_id, product_id),
          INDEX idx_consignacao_produto_intake (intake_id),
          INDEX idx_consignacao_produto (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlLinks);

    }

    private function ensureProductLinkCompatibility(): void
    {
        if (!$this->pdo || !$this->columnExists('consignacao_recebimento_produtos', 'product_id')) {
            return;
        }
        $type = $this->columnTypeInfo('consignacao_recebimento_produtos', 'product_id');
        if ($type !== null && $type['data_type'] === 'bigint' && $type['unsigned'] === true && $type['nullable'] === false) {
            return;
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE consignacao_recebimento_produtos
                 MODIFY COLUMN product_id BIGINT UNSIGNED NOT NULL'
            );
        } catch (\Throwable $e) {
            error_log('Falha ao ajustar consignacao_recebimento_produtos.product_id: ' . $e->getMessage());
        }
    }

    private function ensureProductLinkForeignKey(): void
    {
        if (!$this->pdo) {
            return;
        }
        if (
            !$this->columnExists('consignacao_recebimento_produtos', 'product_id')
            || !$this->columnExists('products', 'sku')
        ) {
            return;
        }
        if ($this->foreignKeyExists('consignacao_recebimento_produtos', 'fk_consig_receb_prod_product_sku')) {
            return;
        }
        if (!$this->isCompatibleForeignKey('consignacao_recebimento_produtos', 'product_id', 'products', 'sku')) {
            return;
        }

        $orphans = (int) ($this->pdo->query(
            "SELECT COUNT(*)
             FROM consignacao_recebimento_produtos t
             WHERE t.product_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.product_id)"
        )->fetchColumn() ?: 0);
        if ($orphans > 0) {
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE consignacao_recebimento_produtos
                 ADD CONSTRAINT fk_consig_receb_prod_product_sku
                 FOREIGN KEY (product_id) REFERENCES products(sku)
                 ON DELETE RESTRICT
                 ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            error_log('Falha ao adicionar FK de consignacao_recebimento_produtos.product_id: ' . $e->getMessage());
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition, ?string $after = null): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            if ($stmt->fetch()) {
                return;
            }
            $afterSql = $after ? " AFTER `{$after}`" : '';
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterSql}");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar coluna {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = :k');
            $stmt->execute([':k' => $indexName]);
            if ($stmt->fetch()) {
                return;
            }
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar índice {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->pdo || !$this->columnExists($table, $column)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        } catch (\Throwable $e) {
            error_log("Falha ao remover coluna {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->pdo || !$this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        } catch (\Throwable $e) {
            error_log("Falha ao remover índice {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
        );
        $stmt->execute([':t' => $table, ':i' => $indexName]);
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
     * @return array{data_type:string,unsigned:bool,nullable:bool}|null
     */
    private function columnTypeInfo(string $table, string $column): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
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
            'nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
        ];
    }

    private function backfillPessoaIds(): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            if ($this->columnExists('consignacao_recebimentos', 'vendor_id')) {
                $this->pdo->exec(
                    "UPDATE consignacao_recebimentos cr
                     JOIN vw_fornecedores_compat f ON f.id_vendor = cr.vendor_id OR f.id = cr.vendor_id
                     SET cr.pessoa_id = COALESCE(cr.pessoa_id, f.id)
                     WHERE (cr.pessoa_id IS NULL OR cr.pessoa_id = 0)
                       AND cr.vendor_id IS NOT NULL
                       AND cr.vendor_id > 0
                       AND f.id IS NOT NULL
                       AND f.id > 0"
                );
            }
        } catch (\Throwable $e) {
            error_log('Backfill pessoa_id em consignacao_recebimentos falhou: ' . $e->getMessage());
        }

        try {
            $this->pdo->exec(
                "UPDATE consignacao_devolucoes d
                 JOIN consignacao_recebimentos r ON r.id = d.intake_id
                 SET d.pessoa_id = r.pessoa_id
                 WHERE d.pessoa_id IS NULL AND r.pessoa_id IS NOT NULL"
            );
        } catch (\Throwable $e) {
            error_log('Backfill pessoa_id em consignacao_devolucoes falhou: ' . $e->getMessage());
        }
    }

    private function removeLegacyVendorColumn(): void
    {
        $this->dropIndexIfExists('consignacao_recebimentos', 'idx_consignacao_receb_vendor');
        $this->dropColumnIfExists('consignacao_recebimentos', 'vendor_id');
    }
}
