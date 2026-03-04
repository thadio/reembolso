<?php

namespace App\Repositories;

use PDO;

use App\Support\AuditableTrait;
class PieceLotRepository
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
                $this->backfillSupplierPessoaId();
                $this->removeLegacySupplierColumn();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela produto_lotes: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function find(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $sql = $this->baseSelectSql() . ' WHERE l.id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $supplierId, ?string $notes = null, ?string $openedAt = null): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $supplierPessoaId = $this->resolveSupplierPessoaId($supplierId);
        if ($supplierPessoaId === null || $supplierPessoaId <= 0) {
            throw new \InvalidArgumentException('Fornecedor invalido para abertura de lote.');
        }

        $openedAt = $openedAt ?: date('Y-m-d H:i:s');

        $insert = $this->pdo->prepare(
            "INSERT INTO produto_lotes (supplier_pessoa_id, name, opened_at, status, notes)
            VALUES (:supplier_pessoa_id, '', :opened_at, 'aberto', :notes)"
        );
        $insert->execute([
            ':supplier_pessoa_id' => $supplierPessoaId,
            ':opened_at' => $openedAt,
            ':notes' => $notes,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $name = $this->formatLotName($id, $openedAt);

        $update = $this->pdo->prepare('UPDATE produto_lotes SET name = :name WHERE id = :id');
        $update->execute([':name' => $name, ':id' => $id]);

        $newData = $this->find($id);
        $this->auditLog('INSERT', 'produto_lotes', $id, null, $newData);

        $supplierCode = $this->resolveVendorCodeFromPessoaId($supplierPessoaId) ?? $supplierPessoaId;

        return [
            'id' => $id,
            'supplier_pessoa_id' => $supplierPessoaId,
            'supplier_id_vendor' => $supplierCode,
            'name' => $name,
            'opened_at' => $openedAt,
            'status' => 'aberto',
            'notes' => $notes,
        ];
    }

    public function close(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE produto_lotes
            SET status = 'fechado', closed_at = COALESCE(closed_at, NOW())
            WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function reopen(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE produto_lotes
            SET status = 'aberto', closed_at = NULL
            WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function trash(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE produto_lotes
            SET status = 'lixeira', closed_at = COALESCE(closed_at, NOW())
            WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function list(
        array $filters = [],
        int $limit = 200,
        int $offset = 0,
        string $sortKey = 'opened_at',
        string $sortDir = 'DESC'
    ): array
    {
        if (!$this->pdo) {
            return [];
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        $sortExpr = $this->normalizeListSortKey($sortKey);
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit = $limit < 0 ? 0 : $limit;
        $offset = $offset < 0 ? 0 : $offset;

        $sql = $this->baseSelectSql() . " {$whereSql} ORDER BY {$sortExpr} {$sortDir}, l.id DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit > 0) {
            $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        $sql = 'SELECT COUNT(*) FROM produto_lotes l LEFT JOIN vw_fornecedores_compat v ON v.id = l.supplier_pessoa_id ' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $count = $stmt->fetchColumn();

        return $count !== false ? (int) $count : 0;
    }

    public function latestOpenBySupplier(int $supplierId): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        [$whereSql, $params] = $this->buildFilters([
            'supplier' => $supplierId,
            'status' => 'aberto',
        ]);

        $sql = $this->baseSelectSql() . " {$whereSql} ORDER BY l.opened_at DESC, l.id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int, array{id:int,supplier_pessoa_id:int,supplier_id_vendor:int,name:string,opened_at:string,closed_at:?string,status:string,notes:?string}>
     */
    public function listOpenLots(): array
    {
        return $this->list(['status' => 'aberto'], 500);
    }

    public function listLatestOpenMap(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT l1.id,
                       l1.supplier_pessoa_id,
                       COALESCE(v.id_vendor, l1.supplier_pessoa_id) AS supplier_id_vendor,
                       l1.name,
                       l1.opened_at,
                       l1.closed_at,
                       l1.status,
                       l1.notes
                FROM produto_lotes l1
                INNER JOIN (
                    SELECT supplier_pessoa_id, MAX(opened_at) AS max_opened
                    FROM produto_lotes
                    WHERE status = 'aberto'
                    GROUP BY supplier_pessoa_id
                ) latest ON latest.supplier_pessoa_id = l1.supplier_pessoa_id
                       AND latest.max_opened = l1.opened_at
                LEFT JOIN vw_fornecedores_compat v ON v.id = l1.supplier_pessoa_id
                WHERE l1.status = 'aberto'";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        $map = [];
        foreach ($rows as $row) {
            $key = (int) ($row['supplier_id_vendor'] ?? 0);
            if ($key <= 0) {
                $key = (int) ($row['supplier_pessoa_id'] ?? 0);
            }
            if ($key > 0) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    private function formatLotName(int $id, string $openedAt): string
    {
        $ts = strtotime($openedAt);
        $labelDate = $ts ? date('d/m/Y H:i', $ts) : $openedAt;
        return 'Lote #' . $id . ' - ' . $labelDate;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $supplier = 0;
        if (isset($filters['supplier_pessoa_id'])) {
            $supplier = (int) $filters['supplier_pessoa_id'];
        }
        if ($supplier <= 0 && isset($filters['supplier_id_vendor'])) {
            $supplier = (int) $filters['supplier_id_vendor'];
        }
        if ($supplier <= 0 && isset($filters['supplier'])) {
            $supplier = (int) $filters['supplier'];
        }

        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        $statusIn = isset($filters['status_in']) && is_array($filters['status_in']) ? $filters['status_in'] : [];
        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));

        $where = [];
        $params = [];

        if ($supplier > 0) {
            $where[] = '(l.supplier_pessoa_id = :supplier_pessoa_id OR v.id_vendor = :supplier_vendor_code)';
            $params[':supplier_pessoa_id'] = $supplier;
            $params[':supplier_vendor_code'] = $supplier;
        }

        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        } elseif (!empty($statusIn)) {
            $statusIn = array_values(array_filter(array_map('strval', $statusIn)));
            if (!empty($statusIn)) {
                $placeholders = [];
                foreach ($statusIn as $index => $value) {
                    $key = ':status_in_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $value;
                }
                $where[] = 'l.status IN (' . implode(',', $placeholders) . ')';
            }
        }

        if ($search !== '') {
            $where[] = "(CAST(l.id AS CHAR) LIKE :search
                OR COALESCE(l.name, '') LIKE :search
                OR COALESCE(v.full_name, '') LIKE :search
                OR COALESCE(l.status, '') LIKE :search
                OR CAST(COALESCE(v.id_vendor, l.supplier_pessoa_id) AS CHAR) LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $columnFilterMap = [
            'filter_id' => 'CAST(l.id AS CHAR)',
            'filter_name' => 'COALESCE(l.name, \'\')',
            'filter_supplier' => "COALESCE(v.full_name, CAST(COALESCE(v.id_vendor, l.supplier_pessoa_id) AS CHAR))",
            'filter_opened_at' => "DATE_FORMAT(l.opened_at, '%Y-%m-%d %H:%i:%s')",
            'filter_closed_at' => "DATE_FORMAT(l.closed_at, '%Y-%m-%d %H:%i:%s')",
        ];

        foreach ($columnFilterMap as $filterKey => $expr) {
            $rawValue = trim((string) ($filters[$filterKey] ?? ''));
            if ($rawValue === '') {
                continue;
            }
            $this->appendMultiLikeFilter($where, $params, $expr, $rawValue, $filterKey);
        }

        $statusColumnFilter = trim((string) ($filters['filter_status'] ?? ''));
        if ($statusColumnFilter !== '') {
            $statusValues = array_values(array_filter(array_map('trim', explode(',', $statusColumnFilter))));
            if (count($statusValues) > 1) {
                $placeholders = [];
                foreach ($statusValues as $index => $value) {
                    $key = ':filter_status_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = strtolower($value);
                }
                $where[] = 'LOWER(l.status) IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'l.status LIKE :filter_status';
                $params[':filter_status'] = '%' . ($statusValues[0] ?? $statusColumnFilter) . '%';
            }
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }

    /**
     * @param array<int, string> $where
     * @param array<string, mixed> $params
     */
    private function appendMultiLikeFilter(array &$where, array &$params, string $expr, string $rawValue, string $filterKey): void
    {
        $values = array_values(array_filter(array_map('trim', explode(',', $rawValue))));
        if (empty($values)) {
            return;
        }

        $normalizedKey = preg_replace('/[^a-z0-9_]+/i', '_', $filterKey) ?: 'filter';
        if (count($values) === 1) {
            $param = ':' . $normalizedKey;
            $where[] = "{$expr} LIKE {$param}";
            $params[$param] = '%' . $values[0] . '%';
            return;
        }

        $parts = [];
        foreach ($values as $index => $value) {
            $param = ':' . $normalizedKey . '_' . $index;
            $parts[] = "{$expr} LIKE {$param}";
            $params[$param] = '%' . $value . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    private function normalizeListSortKey(string $sortKey): string
    {
        $map = [
            'id' => 'l.id',
            'name' => 'l.name',
            'supplier' => "COALESCE(v.full_name, CAST(COALESCE(v.id_vendor, l.supplier_pessoa_id) AS CHAR))",
            'status' => 'l.status',
            'opened_at' => 'l.opened_at',
            'closed_at' => 'l.closed_at',
        ];

        $key = trim($sortKey);
        return $map[$key] ?? 'l.opened_at';
    }

    private function baseSelectSql(): string
    {
        return "SELECT l.id,
                       l.supplier_pessoa_id,
                       COALESCE(v.id_vendor, l.supplier_pessoa_id) AS supplier_id_vendor,
                       l.name,
                       l.opened_at,
                       l.closed_at,
                       l.status,
                       l.notes
                FROM produto_lotes l
                LEFT JOIN vw_fornecedores_compat v ON v.id = l.supplier_pessoa_id";
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS produto_lotes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          supplier_pessoa_id BIGINT UNSIGNED NULL,
          name VARCHAR(190) NOT NULL,
          opened_at DATETIME NOT NULL,
          closed_at DATETIME NULL,
          status ENUM('aberto','fechado','lixeira') DEFAULT 'aberto',
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_lotes_supplier_pessoa (supplier_pessoa_id),
          INDEX idx_produto_lotes_status (status),
          INDEX idx_produto_lotes_opened (opened_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->addColumnIfMissing('produto_lotes', 'supplier_pessoa_id', 'BIGINT UNSIGNED NULL', 'id');
        $this->addIndexIfMissing('produto_lotes', 'idx_lotes_supplier_pessoa', 'supplier_pessoa_id');

        try {
            $this->pdo->exec(
                "ALTER TABLE produto_lotes MODIFY status ENUM('aberto','fechado','lixeira') DEFAULT 'aberto'"
            );
        } catch (\Throwable $e) {
            // Ignore when schema cannot be altered.
        }
    }

    private function backfillSupplierPessoaId(): void
    {
        if (!$this->pdo || !$this->columnExists('produto_lotes', 'supplier_id_vendor')) {
            return;
        }

        try {
            $this->pdo->exec(
                "UPDATE produto_lotes pl
                 LEFT JOIN vw_fornecedores_compat vf ON vf.id_vendor = pl.supplier_id_vendor OR vf.id = pl.supplier_id_vendor
                 SET pl.supplier_pessoa_id = COALESCE(pl.supplier_pessoa_id, vf.id)
                 WHERE (pl.supplier_pessoa_id IS NULL OR pl.supplier_pessoa_id = 0)
                   AND pl.supplier_id_vendor IS NOT NULL
                   AND pl.supplier_id_vendor > 0
                   AND vf.id IS NOT NULL
                   AND vf.id > 0"
            );
        } catch (\Throwable $e) {
            error_log('Backfill supplier_pessoa_id em produto_lotes falhou: ' . $e->getMessage());
        }
    }

    private function removeLegacySupplierColumn(): void
    {
        $this->dropIndexIfExists('produto_lotes', 'idx_produto_lotes_supplier');
        $this->dropIndexIfExists('produto_lotes', 'idx_lotes_supplier');
        $this->dropColumnIfExists('produto_lotes', 'supplier_id_vendor');
    }

    private function resolveSupplierPessoaId(int $supplierId): ?int
    {
        if (!$this->pdo || $supplierId <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM vw_fornecedores_compat WHERE id = :id OR id_vendor = :id LIMIT 1'
            );
            $stmt->execute([':id' => $supplierId]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return (int) $value;
            }

            $stmt = $this->pdo->prepare('SELECT id FROM pessoas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $supplierId]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (int) $value : null;
        } catch (\Throwable $e) {
            error_log('Falha ao resolver supplier_pessoa_id em produto_lotes: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveVendorCodeFromPessoaId(int $pessoaId): ?int
    {
        if (!$this->pdo || $pessoaId <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id_vendor FROM vw_fornecedores_compat WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pessoaId]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (int) $value : null;
        } catch (\Throwable $e) {
            error_log('Falha ao resolver id_vendor por pessoa_id em produto_lotes: ' . $e->getMessage());
            return null;
        }
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
