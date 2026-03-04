<?php

namespace App\Repositories;

use PDO;

use App\Support\AuditableTrait;
class ConsignmentRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    /** @var array<string, bool> */
    private array $consignmentItemsColumnCache = [];

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
                new ConsignmentItemRepository($pdo);
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela consignments: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS consignments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          supplier_pessoa_id BIGINT UNSIGNED NOT NULL,
          status ENUM('aberta','fechada','pendente','liquidada') NOT NULL DEFAULT 'aberta',
          percent_default DECIMAL(5,2) NULL,
          received_at DATETIME NOT NULL,
          closed_at DATETIME NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_consignments_supplier (supplier_pessoa_id),
          INDEX idx_consignments_status (status),
          INDEX idx_consignments_received (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    /**
     * Lista consignações com filtros, paginação e ordenação
     * 
     * @param array $filters Filtros: status, supplier_pessoa_id, date_from, date_to, search
     * @param int $limit Limite de registros
     * @param int $offset Offset para paginação
     * @param string $sortKey Chave de ordenação
     * @param string $sortDir Direção: 'asc' ou 'desc'
     * @return array
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0, string $sortKey = 'received_at', string $sortDir = 'desc'): array
    {
        [$whereClause, $params] = $this->buildListWhereClause($filters);
        $sortExpr = $this->normalizeListSortKey($sortKey);
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $itemSkuExpr = $this->consignmentItemSkuExpr('ci');
        $itemQuantityExpr = $this->consignmentItemQuantityExpr('ci');

        // Query com agregações
        $sql = "
            SELECT 
                c.id,
                c.supplier_pessoa_id,
                c.status,
                c.percent_default,
                c.received_at,
                c.closed_at,
                c.notes,
                c.created_at,
                c.updated_at,
                p.full_name as supplier_name,
                COALESCE(ci_count.items_count, 0) as items_count,
                COALESCE(ci_value.total_value, 0) as total_value
            FROM consignments c
            LEFT JOIN pessoas p ON p.id = c.supplier_pessoa_id
            LEFT JOIN (
                SELECT consignment_id, COUNT(*) as items_count
                FROM consignment_items
                GROUP BY consignment_id
            ) ci_count ON ci_count.consignment_id = c.id
            LEFT JOIN (
                SELECT ci.consignment_id,
                       SUM(COALESCE(p.cost, 0) * {$itemQuantityExpr}) as total_value
                FROM consignment_items ci
                LEFT JOIN products p ON p.sku = {$itemSkuExpr}
                GROUP BY ci.consignment_id
            ) ci_value ON ci_value.consignment_id = c.id
            {$whereClause}
            ORDER BY {$sortExpr} {$sortDir}, c.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de consignações com filtros
     * 
     * @param array $filters Mesmos filtros de list()
     * @return int
     */
    public function count(array $filters = []): int
    {
        [$whereClause, $params] = $this->buildListWhereClause($filters);

        $sql = "
            SELECT COUNT(*) as total
            FROM consignments c
            LEFT JOIN pessoas p ON p.id = c.supplier_pessoa_id
            {$whereClause}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string, mixed>}
     */
    private function buildListWhereClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params[':status'] = (string) $filters['status'];
        }

        if (!empty($filters['supplier_pessoa_id'])) {
            $where[] = "c.supplier_pessoa_id = :supplier_pessoa_id";
            $params[':supplier_pessoa_id'] = (int) $filters['supplier_pessoa_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "c.received_at >= :date_from";
            $params[':date_from'] = (string) $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "c.received_at <= :date_to";
            $params[':date_to'] = (string) $filters['date_to'] . ' 23:59:59';
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $where[] = "(CAST(c.id AS CHAR) LIKE :search
                OR COALESCE(c.notes, '') LIKE :search
                OR COALESCE(p.full_name, '') LIKE :search
                OR COALESCE(c.status, '') LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $itemSkuExpr = $this->consignmentItemSkuExpr('ci_total');
        $itemQuantityExpr = $this->consignmentItemQuantityExpr('ci_total');
        $columnFilterMap = [
            'filter_id' => 'CAST(c.id AS CHAR)',
            'filter_received_at' => "DATE_FORMAT(c.received_at, '%Y-%m-%d %H:%i:%s')",
            'filter_supplier_name' => 'COALESCE(p.full_name, \'\')',
            'filter_notes' => 'COALESCE(c.notes, \'\')',
            'filter_items_count' => 'CAST((SELECT COUNT(*) FROM consignment_items ci_count WHERE ci_count.consignment_id = c.id) AS CHAR)',
            'filter_total_value' => "CAST((
                SELECT COALESCE(SUM(COALESCE(prod.cost, 0) * {$itemQuantityExpr}), 0)
                FROM consignment_items ci_total
                LEFT JOIN products prod ON prod.sku = {$itemSkuExpr}
                WHERE ci_total.consignment_id = c.id
            ) AS CHAR)",
        ];

        foreach ($columnFilterMap as $filterKey => $expr) {
            $rawValue = trim((string) ($filters[$filterKey] ?? ''));
            if ($rawValue === '') {
                continue;
            }
            $this->appendMultiLikeFilter($where, $params, $expr, $rawValue, $filterKey);
        }

        $statusFilter = trim((string) ($filters['filter_status'] ?? ''));
        if ($statusFilter !== '') {
            $statusValues = array_values(array_filter(array_map('trim', explode(',', $statusFilter))));
            if (count($statusValues) > 1) {
                $placeholders = [];
                foreach ($statusValues as $idx => $value) {
                    $key = ':filter_status_' . $idx;
                    $placeholders[] = $key;
                    $params[$key] = strtolower($value);
                }
                $where[] = 'LOWER(c.status) IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'c.status LIKE :filter_status';
                $params[':filter_status'] = '%' . ($statusValues[0] ?? $statusFilter) . '%';
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereClause, $params];
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
            $paramKey = ':' . $normalizedKey;
            $where[] = "{$expr} LIKE {$paramKey}";
            $params[$paramKey] = '%' . $values[0] . '%';
            return;
        }

        $parts = [];
        foreach ($values as $idx => $value) {
            $paramKey = ':' . $normalizedKey . '_' . $idx;
            $parts[] = "{$expr} LIKE {$paramKey}";
            $params[$paramKey] = '%' . $value . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    private function normalizeListSortKey(string $sortKey): string
    {
        $map = [
            'id' => 'c.id',
            'supplier_name' => 'supplier_name',
            'percent_default' => 'c.percent_default',
            'status' => 'c.status',
            'received_at' => 'c.received_at',
            'closed_at' => 'c.closed_at',
            'items_count' => 'items_count',
            'total_value' => 'total_value',
            'notes' => 'c.notes',
        ];

        $key = trim($sortKey);
        return $map[$key] ?? 'c.received_at';
    }

    /**
     * Busca consignação por ID
     * 
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $itemSkuExpr = $this->consignmentItemSkuExpr('ci');
        $itemQuantityExpr = $this->consignmentItemQuantityExpr('ci');
        $sql = "
            SELECT 
                c.*,
                p.full_name as supplier_name,
                COALESCE(ci_count.items_count, 0) as items_count,
                COALESCE(ci_value.total_value, 0) as total_value
            FROM consignments c
            LEFT JOIN pessoas p ON p.id = c.supplier_pessoa_id
            LEFT JOIN (
                SELECT consignment_id, COUNT(*) as items_count
                FROM consignment_items
                GROUP BY consignment_id
            ) ci_count ON ci_count.consignment_id = c.id
            LEFT JOIN (
                SELECT ci.consignment_id,
                       SUM(COALESCE(p.cost, 0) * {$itemQuantityExpr}) as total_value
                FROM consignment_items ci
                LEFT JOIN products p ON p.sku = {$itemSkuExpr}
                GROUP BY ci.consignment_id
            ) ci_value ON ci_value.consignment_id = c.id
            WHERE c.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Salva (cria ou atualiza) uma consignação
     * 
     * @param array $data
     * @return int ID da consignação
     */
    public function save(array $data): int
    {
        $id = $data['id'] ?? null;
        $isUpdate = (bool) $id;
        $oldData = null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM consignments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($id) {
            // UPDATE
            $sql = "
                UPDATE consignments SET
                    supplier_pessoa_id = :supplier_pessoa_id,
                    status = :status,
                    percent_default = :percent_default,
                    received_at = :received_at,
                    closed_at = :closed_at,
                    notes = :notes
                WHERE id = :id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':supplier_pessoa_id' => $data['supplier_pessoa_id'],
                ':status' => $data['status'] ?? 'aberta',
                ':percent_default' => $data['percent_default'] ?? null,
                ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
                ':closed_at' => $data['closed_at'] ?? null,
                ':notes' => $data['notes'] ?? null,
            ]);
        } else {
            // INSERT
            $sql = "
                INSERT INTO consignments (
                    supplier_pessoa_id,
                    status,
                    percent_default,
                    received_at,
                    closed_at,
                    notes
                ) VALUES (
                    :supplier_pessoa_id,
                    :status,
                    :percent_default,
                    :received_at,
                    :closed_at,
                    :notes
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':supplier_pessoa_id' => $data['supplier_pessoa_id'],
                ':status' => $data['status'] ?? 'aberta',
                ':percent_default' => $data['percent_default'] ?? null,
                ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
                ':closed_at' => $data['closed_at'] ?? null,
                ':notes' => $data['notes'] ?? null,
            ]);

            $id = (int)$this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM consignments WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'consignments',
            $id,
            $oldData,
            $newData
        );

        return $id;
    }

    /**
     * Atualiza status de uma consignação
     * 
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM consignments WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE consignments SET status = :status WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
        ]);

        if ($stmt->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM consignments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $newData = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->auditLog('UPDATE', 'consignments', $id, $oldData, $newData);
            return true;
        }

        return false;
    }

    /**
     * Fecha uma consignação
     * 
     * @param int $id
     * @return bool
     */
    public function close(int $id): bool
    {
        $sql = "
            UPDATE consignments 
            SET status = 'fechada', closed_at = NOW() 
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Lista itens de uma consignação
     * 
     * @param int $consignmentId
     * @return array
     */
    public function listItems(int $consignmentId): array
    {
        $itemSkuExpr = $this->consignmentItemSkuExpr('ci');
        $itemQuantityExpr = $this->consignmentItemQuantityExpr('ci');
        $sql = "
            SELECT 
                ci.id,
                ci.consignment_id,
                {$itemSkuExpr} AS product_sku,
                {$itemQuantityExpr} AS quantity,
                ci.percent_override,
                ci.minimum_price,
                ci.created_at,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.internal_code')),
                    CAST(p.sku AS CHAR)
                ) AS internal_code,
                p.sku,
                p.condition_grade,
                p.cost AS acquisition_cost,
                p.cost AS unit_cost,
                p.price AS price_listed,
                p.status AS item_status,
                p.name AS product_name
            FROM consignment_items ci
            LEFT JOIN products p ON p.sku = {$itemSkuExpr}
            WHERE ci.consignment_id = :consignment_id
            ORDER BY ci.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':consignment_id' => $consignmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function consignmentItemSkuExpr(string $alias): string
    {
        if ($this->consignmentItemsHasColumn('product_sku')) {
            return "{$alias}.product_sku";
        }

        return 'NULL';
    }

    private function consignmentItemQuantityExpr(string $alias): string
    {
        if ($this->consignmentItemsHasColumn('quantity')) {
            return "COALESCE({$alias}.quantity, 1)";
        }

        return '1';
    }

    private function consignmentItemsHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->consignmentItemsColumnCache)) {
            return $this->consignmentItemsColumnCache[$column];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'consignment_items'
                  AND column_name = :column
            ");
            $stmt->execute([':column' => $column]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
            $this->consignmentItemsColumnCache[$column] = $exists;
            return $exists;
        } catch (\Throwable) {
            $this->consignmentItemsColumnCache[$column] = false;
            return false;
        }
    }
}
