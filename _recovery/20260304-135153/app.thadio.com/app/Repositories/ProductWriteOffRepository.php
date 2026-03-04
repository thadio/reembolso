<?php

namespace App\Repositories;

use PDO;

use App\Support\AuditableTrait;
class ProductWriteOffRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                PeopleCompatViewRepository::ensure($this->pdo);
                $this->ensureTable();
                $this->removeDeprecatedSupplierColumn();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela produto_baixas: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $productSku = isset($data['product_sku']) ? (int) $data['product_sku'] : 0;
        if ($productSku <= 0) {
            throw new \InvalidArgumentException('Produto invalido para baixa.');
        }

        $supplierPessoaId = $this->resolveSupplierPessoaId($data);

        $sql = 'INSERT INTO produto_baixas (
          product_id, sku, supplier_pessoa_id, source, destination, reason, quantity, notes,
          stock_before, stock_after, term_token, created_by, created_at
        ) VALUES (
          :product_id, :sku, :supplier_pessoa_id, :source, :destination, :reason, :quantity, :notes,
          :stock_before, :stock_after, :term_token, :created_by, NOW()
        )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':product_id' => $productSku,
            ':sku' => $data['sku'],
            ':supplier_pessoa_id' => $supplierPessoaId,
            ':source' => $data['source'] ?? '',
            ':destination' => $data['destination'] ?? '',
            ':reason' => $data['reason'] ?? '',
            ':quantity' => $data['quantity'] ?? 1,
            ':notes' => $data['notes'] ?? null,
            ':stock_before' => $data['stock_before'] ?? null,
            ':stock_after' => $data['stock_after'] ?? null,
            ':term_token' => $data['term_token'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("SELECT * FROM product_write_offs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog('INSERT', 'product_write_offs', $id, null, $newData);

        return $id;
    }

    public function find(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare($this->baseSelectSql() . ' WHERE b.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare($this->baseSelectSql() . ' WHERE b.term_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findMany(array $ids): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $value): bool => $value > 0)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare($this->baseSelectSql() . " WHERE b.id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['id'] ?? 0)] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $ordered[] = $map[$id];
            }
        }
        return $ordered;
    }

    public function listRecent(int $limit = 0): array
    {
        if (!$this->pdo) {
            return [];
        }
        $useLimit = $limit > 0;
        $sql = $this->baseSelectSql() . ' ORDER BY b.id DESC';
        if ($useLimit) {
            $sql .= ' LIMIT :limit';
        }
        $stmt = $this->pdo->prepare($sql);
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listForProduct(int $productSku, int $limit = 0): array
    {
        return $this->paginateForProduct($productSku, [], $limit, 0, 'created_at', 'DESC');
    }

    public function countForProduct(int $productSku, array $filters = []): int
    {
        if (!$this->pdo || $productSku <= 0) {
            return 0;
        }

        $params = [':product_id' => $productSku];
        $where = ['b.product_id = :product_id'];
        $this->applyProductFilters($where, $params, $filters);

        $sql = "SELECT COUNT(*)
                FROM produto_baixas b
                LEFT JOIN vw_fornecedores_compat v ON v.id = b.supplier_pessoa_id
                WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginateForProduct(
        int $productSku,
        array $filters = [],
        int $limit = 0,
        int $offset = 0,
        string $sortKey = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        if (!$this->pdo || $productSku <= 0) {
            return [];
        }

        $useLimit = $limit > 0;
        $offset = max(0, $offset);

        $params = [':product_id' => $productSku];
        $where = ['b.product_id = :product_id'];
        $this->applyProductFilters($where, $params, $filters);

        $sortMap = [
            'created_at' => 'b.created_at',
            'destination' => 'b.destination',
            'reason' => 'b.reason',
            'quantity' => 'b.quantity',
            'supplier' => 'v.full_name',
            'stock_after' => 'b.stock_after',
            'notes' => 'b.notes',
        ];
        $sortExpr = $sortMap[$sortKey] ?? 'b.created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $this->baseSelectSql()
            . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY {$sortExpr} {$sortDir}, b.id DESC";
        if ($useLimit) {
            $sql .= "\n                LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($useLimit) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBySupplier(int $supplierId, int $limit = 0): array
    {
        if (!$this->pdo || $supplierId <= 0) {
            return [];
        }
        $useLimit = $limit > 0;
        $sql = $this->baseSelectSql() . "
            WHERE b.supplier_pessoa_id = :supplier_pessoa_id
              AND b.destination = 'devolucao_fornecedor'
            ORDER BY b.created_at DESC";
        if ($useLimit) {
            $sql .= "\n            LIMIT :limit";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':supplier_pessoa_id', $supplierId, PDO::PARAM_INT);
        if ($useLimit) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function baseSelectSql(): string
    {
        return "SELECT b.id,
                       b.product_id,
                       b.product_id AS product_sku,
                       b.sku,
                       b.supplier_pessoa_id,
                       v.full_name AS supplier_name,
                       b.source,
                       b.destination,
                       b.reason,
                       b.quantity,
                       b.notes,
                       b.stock_before,
                       b.stock_after,
                       b.term_token,
                       b.created_by,
                       b.created_at
                FROM produto_baixas b
                LEFT JOIN vw_fornecedores_compat v ON v.id = b.supplier_pessoa_id";
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS produto_baixas (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id BIGINT UNSIGNED NOT NULL,
          sku VARCHAR(100) NOT NULL,
          supplier_pessoa_id BIGINT UNSIGNED NULL,
          source VARCHAR(30) NULL,
          destination ENUM('nao_localizado','doacao','devolucao_fornecedor','lixo') NOT NULL,
          reason ENUM('perdido','sem_venda','avaria','solicitacao_fornecedor') NOT NULL,
          quantity INT UNSIGNED NOT NULL,
          notes TEXT NULL,
          stock_before INT NULL,
          stock_after INT NULL,
          term_token VARCHAR(64) NULL,
          created_by INT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_product (product_id),
          KEY idx_product_created_at (product_id, created_at),
          KEY idx_baixas_supplier_pessoa (supplier_pessoa_id),
          KEY idx_destination (destination),
          KEY idx_term_token (term_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
        $this->addColumnIfMissing('produto_baixas', 'product_id', 'BIGINT UNSIGNED NULL', 'id');
        $this->addIndexIfMissing('produto_baixas', 'idx_product', 'product_id');
        $this->addCompositeIndexIfMissing('produto_baixas', 'idx_product_created_at', ['product_id', 'created_at']);

        $this->addColumnIfMissing('produto_baixas', 'supplier_pessoa_id', 'BIGINT UNSIGNED NULL', 'sku');
        $this->addIndexIfMissing('produto_baixas', 'idx_baixas_supplier_pessoa', 'supplier_pessoa_id');
    }

    /**
     * @param array<int, string> $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applyProductFilters(array &$where, array &$params, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(CAST(b.created_at AS CHAR) LIKE :search
                OR b.destination LIKE :search
                OR b.reason LIKE :search
                OR CAST(b.quantity AS CHAR) LIKE :search
                OR COALESCE(v.full_name, \'\') LIKE :search
                OR CAST(b.stock_before AS CHAR) LIKE :search
                OR CAST(b.stock_after AS CHAR) LIKE :search
                OR COALESCE(b.notes, \'\') LIKE :search)';
        }

        $filterMap = [
            'filter_created_at' => 'CAST(b.created_at AS CHAR)',
            'filter_destination' => 'b.destination',
            'filter_reason' => 'b.reason',
            'filter_quantity' => 'CAST(b.quantity AS CHAR)',
            'filter_supplier' => 'COALESCE(v.full_name, \'\')',
            'filter_notes' => 'COALESCE(b.notes, \'\')',
        ];

        foreach ($filterMap as $key => $expr) {
            $raw = trim((string) ($filters[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== ''));
            if (count($parts) > 1) {
                $orParts = [];
                foreach ($parts as $index => $part) {
                    $param = ':f_' . $key . '_' . $index;
                    $params[$param] = '%' . $part . '%';
                    $orParts[] = "{$expr} LIKE {$param}";
                }
                $where[] = '(' . implode(' OR ', $orParts) . ')';
                continue;
            }
            $param = ':f_' . $key;
            $params[$param] = '%' . ($parts[0] ?? $raw) . '%';
            $where[] = "{$expr} LIKE {$param}";
        }

        $stockFilter = trim((string) ($filters['filter_stock_after'] ?? ''));
        if ($stockFilter !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $stockFilter)), static fn (string $value): bool => $value !== ''));
            if (count($parts) > 1) {
                $orParts = [];
                foreach ($parts as $index => $part) {
                    $param = ':f_stock_' . $index;
                    $params[$param] = '%' . $part . '%';
                    $orParts[] = "(CAST(b.stock_before AS CHAR) LIKE {$param} OR CAST(b.stock_after AS CHAR) LIKE {$param})";
                }
                $where[] = '(' . implode(' OR ', $orParts) . ')';
            } else {
                $params[':f_stock'] = '%' . ($parts[0] ?? $stockFilter) . '%';
                $where[] = '(CAST(b.stock_before AS CHAR) LIKE :f_stock OR CAST(b.stock_after AS CHAR) LIKE :f_stock)';
            }
        }
    }

    private function removeDeprecatedSupplierColumn(): void
    {
        $this->dropIndexIfExists('produto_baixas', 'idx_supplier');
        $this->dropColumnIfExists('produto_baixas', 'supplier_id_vendor');
    }

    private function resolveSupplierPessoaId(array $data): ?int
    {
        $supplierPessoaId = isset($data['supplier_pessoa_id']) ? (int) $data['supplier_pessoa_id'] : 0;
        return $supplierPessoaId > 0 ? $supplierPessoaId : null;
    }

    private function addColumnIfMissing(string $table, string $column, string $definition, ?string $after = null): void
    {
        if (!$this->pdo || $this->columnExists($table, $column)) {
            return;
        }
        try {
            $afterSql = $after ? " AFTER `{$after}`" : '';
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterSql}");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar coluna {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (!$this->pdo || $this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar indice {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function addCompositeIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!$this->pdo || $this->indexExists($table, $indexName) || empty($columns)) {
            return;
        }
        $escaped = array_map(static fn (string $column): string => "`{$column}`", $columns);
        $columnSql = implode(', ', $escaped);
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnSql})");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar indice composto {$indexName} em {$table}: " . $e->getMessage());
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
            error_log("Falha ao remover indice {$indexName} em {$table}: " . $e->getMessage());
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
}
