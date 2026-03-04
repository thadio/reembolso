<?php

namespace App\Repositories;

use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use PDO;

use App\Support\AuditableTrait;
class OrderReturnRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTables();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
        * @param array<string, mixed> $filters
        * @return array<int, array<string, mixed>>
        */
    public function list(
        array $filters = [],
        int $limit = 80,
        int $offset = 0,
        string $sortKey = 'id',
        string $sortDir = 'DESC'
    ): array
    {
        if (!$this->pdo) {
            return [];
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        $limit = $limit < 1 ? 1 : ($limit > 200 ? 200 : $limit);
        $offset = $offset < 0 ? 0 : $offset;
        $sort = $this->normalizeListSort($sortKey, $sortDir);

        $sql = "SELECT r.*,
                       COALESCE(SUM(ri.quantity), 0) AS total_quantity,
                       COUNT(DISTINCT ri.id) AS items_count
                FROM order_returns r
                LEFT JOIN order_return_items ri ON ri.return_id = r.id
                {$whereSql}
                GROUP BY r.id
                ORDER BY {$sort['column']} {$sort['direction']}, r.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(DISTINCT r.id)
                FROM order_returns r
                {$whereSql}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $count = $stmt->fetchColumn();

        return $count !== false ? (int) $count : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRefundPending(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT r.*,
                       COALESCE(SUM(ri.quantity), 0) AS total_quantity,
                       COUNT(DISTINCT ri.id) AS items_count
                FROM order_returns r
                LEFT JOIN order_return_items ri ON ri.return_id = r.id
                WHERE r.refund_status IN ('pending', 'processing')
                  AND r.status <> 'cancelled'
                GROUP BY r.id
                ORDER BY COALESCE(r.updated_at, r.created_at) ASC, r.id ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM order_returns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['items'] = $this->listItems($id);
        return $row;
    }

    /**
     * @param array<int, OrderReturnItem> $items
     */
    public function save(OrderReturn $return, array $items): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $return->id;
        $oldData = null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
            $stmt->execute([':id' => $return->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($return->id) {
            $sql = "UPDATE order_returns SET
                order_id = :order_id,
                pessoa_id = :pessoa_id,
                customer_name = :customer_name,
                customer_email = :customer_email,
                status = :status,
                return_method = :return_method,
                refund_method = :refund_method,
                refund_status = :refund_status,
                refund_amount = :refund_amount,
                voucher_account_id = :voucher_account_id,
                tracking_code = :tracking_code,
                expected_at = :expected_at,
                received_at = :received_at,
                restocked_at = :restocked_at,
                notes = :notes
              WHERE id = :id";
            $params = $return->toDbParams() + [':id' => $return->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->deleteItems((int) $return->id);
            $returnId = (int) $return->id;
        } else {
            $sql = "INSERT INTO order_returns
              (order_id, pessoa_id, customer_name, customer_email, status, return_method, refund_method,
               refund_status, refund_amount, voucher_account_id, tracking_code, expected_at, received_at,
               restocked_at, notes, created_by)
              VALUES
              (:order_id, :pessoa_id, :customer_name, :customer_email, :status, :return_method, :refund_method,
               :refund_status, :refund_amount, :voucher_account_id, :tracking_code, :expected_at, :received_at,
               :restocked_at, :notes, :created_by)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($return->toDbParams());
            $returnId = (int) $this->pdo->lastInsertId();
        }

        foreach ($items as $item) {
            $this->insertItem($returnId, $item);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
        $stmt->execute([':id' => $returnId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'order_returns',
            $returnId,
            $oldData,
            $newData
        );

        return $returnId;
    }

    public function markRestocked(int $returnId, ?string $dateTime = null): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE order_returns SET restocked_at = :restocked_at WHERE id = :id');
        $stmt->execute([
            ':restocked_at' => $dateTime ?? date('Y-m-d H:i:s'),
            ':id' => $returnId,
        ]);
    }

    public function clearRestocked(int $returnId): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE order_returns SET restocked_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $returnId]);
    }

    public function updateRefundStatus(int $returnId, string $status, ?int $voucherAccountId = null): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
        $stmt->execute([':id' => $returnId]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            'UPDATE order_returns SET refund_status = :status, voucher_account_id = :voucher_id WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':voucher_id' => $voucherAccountId,
            ':id' => $returnId,
        ]);

        if ($stmt->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
            $stmt->execute([':id' => $returnId]);
            $newData = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->auditLog('UPDATE', 'order_returns', $returnId, $oldData, $newData);
        }
    }

    public function updateStatus(int $returnId, string $status): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
        $stmt->execute([':id' => $returnId]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare('UPDATE order_returns SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $returnId,
        ]);

        if ($stmt->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM order_returns WHERE id = :id");
            $stmt->execute([':id' => $returnId]);
            $newData = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->auditLog('UPDATE', 'order_returns', $returnId, $oldData, $newData);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByOrder(int $orderId): array
    {
        return $this->list(['order_id' => $orderId], 200, 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];
        if (isset($filters['order_id'])) {
            $where[] = 'r.order_id = :order_id';
            $params[':order_id'] = (int) $filters['order_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (!empty($filters['refund_status'])) {
            $where[] = 'r.refund_status = :refund_status';
            $params[':refund_status'] = (string) $filters['refund_status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(CAST(r.id AS CHAR) LIKE :search
                OR CAST(r.order_id AS CHAR) LIKE :search
                OR r.customer_name LIKE :search
                OR r.customer_email LIKE :search
                OR r.status LIKE :search
                OR r.refund_status LIKE :search
                OR CAST(r.refund_amount AS CHAR) LIKE :search
                OR CAST(COALESCE(r.updated_at, r.created_at) AS CHAR) LIKE :search)';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $columnFilterMap = [
            'filter_id' => 'CAST(r.id AS CHAR)',
            'filter_order' => 'CAST(r.order_id AS CHAR)',
            'filter_customer' => 'r.customer_name',
            'filter_status' => 'r.status',
            'filter_refund' => 'r.refund_status',
            'filter_amount' => 'CAST(r.refund_amount AS CHAR)',
            'filter_qty' => 'CAST((SELECT COALESCE(SUM(sri.quantity), 0) FROM order_return_items sri WHERE sri.return_id = r.id) AS CHAR)',
            'filter_date' => 'CAST(COALESCE(r.updated_at, r.created_at) AS CHAR)',
        ];
        foreach ($columnFilterMap as $filterKey => $columnSql) {
            if (!isset($filters[$filterKey])) {
                continue;
            }
            $raw = trim((string) $filters[$filterKey]);
            if ($raw === '') {
                continue;
            }
            $paramKey = ':col_' . str_replace('filter_', '', $filterKey);
            $where[] = "{$columnSql} LIKE {$paramKey}";
            $params[$paramKey] = '%' . $raw . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }

    /**
     * @return array{column:string,direction:string}
     */
    private function normalizeListSort(string $sortKey, string $sortDir): array
    {
        $direction = strtoupper(trim($sortDir)) === 'ASC' ? 'ASC' : 'DESC';
        $normalized = strtolower(trim($sortKey));
        $column = match ($normalized) {
            'id' => 'r.id',
            'order' => 'r.order_id',
            'customer' => 'r.customer_name',
            'status' => 'r.status',
            'refund' => 'r.refund_status',
            'amount' => 'r.refund_amount',
            'qty' => '(SELECT COALESCE(SUM(sri.quantity), 0) FROM order_return_items sri WHERE sri.return_id = r.id)',
            'date', 'updated_at', 'created_at' => 'COALESCE(r.updated_at, r.created_at)',
            default => 'r.id',
        };

        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    /**
     * @param array<int, int> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function returnSummaryByOrderIds(array $orderIds): array
    {
        if (!$this->pdo || empty($orderIds)) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), function ($id) {
            return $id > 0;
        })));
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "SELECT r.order_id,
                       COALESCE(SUM(ri.quantity), 0) AS total_qty,
                       SUM(CASE WHEN r.refund_status IN ('pending', 'processing') THEN 1 ELSE 0 END) AS pending_count,
                       SUM(CASE WHEN r.refund_status = 'done' THEN 1 ELSE 0 END) AS done_count
                FROM order_returns r
                LEFT JOIN order_return_items ri ON ri.return_id = r.id
                WHERE r.order_id IN ({$placeholders}) AND r.status <> 'cancelled'
                GROUP BY r.order_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $map[$orderId] = [
                'returned_qty' => (int) ($row['total_qty'] ?? 0),
                'refund_pending' => !empty($row['pending_count']),
                'refund_done' => !empty($row['done_count']),
            ];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function returnedQuantitiesByOrder(int $orderId, ?int $ignoreReturnId = null): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = 'SELECT ri.order_item_id, SUM(ri.quantity) AS qty
                FROM order_return_items ri
                JOIN order_returns r ON r.id = ri.return_id
                WHERE r.order_id = :order_id AND r.status <> :cancelled';
        $params = [
            ':order_id' => $orderId,
            ':cancelled' => 'cancelled',
        ];
        if ($ignoreReturnId !== null) {
            $sql .= ' AND r.id <> :ignore';
            $params[':ignore'] = $ignoreReturnId;
        }
        $sql .= ' GROUP BY ri.order_item_id';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $lineId = (int) ($row['order_item_id'] ?? 0);
            if ($lineId > 0) {
                $map[$lineId] = (int) ($row['qty'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listItems(int $returnId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, return_id, order_item_id, product_sku, variation_id, quantity, unit_price, product_name, sku
             FROM order_return_items
             WHERE return_id = :return_id
             ORDER BY id ASC'
        );
        $stmt->execute([':return_id' => $returnId]);
        return $stmt->fetchAll();
    }

    private function insertItem(int $returnId, OrderReturnItem $item): void
    {
        if (!$this->pdo) {
            return;
        }

        $columns = ['return_id', 'order_item_id'];
        $params = [
            ':return_id' => $returnId,
            ':order_item_id' => $item->orderItemId,
        ];

        if ($this->columnExists('order_return_items', 'product_sku')) {
            $columns[] = 'product_sku';
            $params[':product_sku'] = $item->productSku;
        }
        if ($this->columnExists('order_return_items', 'product_id')) {
            $columns[] = 'product_id';
            $params[':product_id'] = $item->productSku;
        }
        if ($this->columnExists('order_return_items', 'variation_id')) {
            $columns[] = 'variation_id';
            $params[':variation_id'] = $item->variationId;
        }
        if ($this->columnExists('order_return_items', 'quantity')) {
            $columns[] = 'quantity';
            $params[':quantity'] = $item->quantity;
        }
        if ($this->columnExists('order_return_items', 'unit_price')) {
            $columns[] = 'unit_price';
            $params[':unit_price'] = $item->unitPrice;
        }
        if ($this->columnExists('order_return_items', 'product_name')) {
            $columns[] = 'product_name';
            $params[':product_name'] = $item->productName;
        }
        if ($this->columnExists('order_return_items', 'sku')) {
            $columns[] = 'sku';
            $params[':sku'] = $item->sku;
        }

        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO order_return_items (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function deleteItems(int $returnId): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM order_return_items WHERE return_id = :return_id');
        $stmt->execute([':return_id' => $returnId]);
    }

    private function ensureTables(): void
    {
        $sqlReturns = "CREATE TABLE IF NOT EXISTS order_returns (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id BIGINT UNSIGNED NOT NULL,
          pessoa_id BIGINT UNSIGNED NULL,
          customer_name VARCHAR(200) NULL,
          customer_email VARCHAR(200) NULL,
          status VARCHAR(30) NOT NULL DEFAULT 'requested',
          return_method VARCHAR(30) NOT NULL DEFAULT 'pending',
          refund_method VARCHAR(30) NOT NULL DEFAULT 'none',
          refund_status VARCHAR(30) NOT NULL DEFAULT 'pending',
          refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
          voucher_account_id BIGINT UNSIGNED NULL,
          tracking_code VARCHAR(160) NULL,
          expected_at DATETIME NULL,
          received_at DATETIME NULL,
          restocked_at DATETIME NULL,
          notes TEXT NULL,
          created_by BIGINT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_order_returns_order (order_id),
          INDEX idx_order_returns_pessoa (pessoa_id),
          INDEX idx_order_returns_status (status),
          INDEX idx_order_returns_refund_status (refund_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // MODELO UNIFICADO: product_sku em vez de product_id
        $sqlItems = "CREATE TABLE IF NOT EXISTS order_return_items (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          return_id BIGINT UNSIGNED NOT NULL,
          order_item_id BIGINT UNSIGNED NULL,
          product_sku BIGINT UNSIGNED NOT NULL COMMENT 'SKU do produto (FK para products.sku)',
          variation_id BIGINT UNSIGNED NULL,
          quantity INT NOT NULL,
          unit_price DECIMAL(10,2) NULL,
          product_name VARCHAR(255) NULL,
          sku VARCHAR(120) NULL COMMENT 'SKU como string (redundante, manter para compatibilidade)',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_order_return_items_return (return_id),
          INDEX idx_order_return_items_product_sku (product_sku),
          INDEX idx_order_return_items_order_item (order_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sqlReturns);
        $this->pdo->exec($sqlItems);

        $this->ensureReturnColumns();
        $this->ensureReturnItemColumns();
        $this->ensureReturnItemProductSkuCompatibility();
        $this->ensureReturnItemProductSkuForeignKey();
    }

    private function ensureReturnColumns(): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->addColumnIfMissing('order_returns', 'pessoa_id', 'BIGINT UNSIGNED NULL AFTER order_id');
        $this->addColumnIfMissing('order_returns', 'customer_name', 'VARCHAR(200) NULL AFTER pessoa_id');
        $this->addColumnIfMissing('order_returns', 'customer_email', 'VARCHAR(200) NULL AFTER customer_name');
        $this->addColumnIfMissing('order_returns', 'voucher_account_id', 'BIGINT UNSIGNED NULL AFTER refund_amount');
        $this->addColumnIfMissing('order_returns', 'restocked_at', 'DATETIME NULL AFTER received_at');
        $this->addColumnIfMissing('order_returns', 'created_by', 'BIGINT NULL AFTER notes');

        if ($this->columnExists('order_returns', 'customer_id')) {
            $this->pdo->exec(
                'UPDATE order_returns
                 SET pessoa_id = COALESCE(pessoa_id, customer_id)
                 WHERE pessoa_id IS NULL AND customer_id IS NOT NULL'
            );
        }
    }

    private function ensureReturnItemColumns(): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->addColumnIfMissing('order_return_items', 'product_sku', 'BIGINT UNSIGNED NULL AFTER order_item_id');
        $this->addColumnIfMissing('order_return_items', 'product_name', 'VARCHAR(255) NULL AFTER unit_price');
        $this->addColumnIfMissing('order_return_items', 'sku', 'VARCHAR(120) NULL AFTER product_name');

        if ($this->columnExists('order_return_items', 'product_id')) {
            $this->pdo->exec(
                'UPDATE order_return_items
                 SET product_sku = COALESCE(product_sku, product_id)
                 WHERE product_sku IS NULL AND product_id IS NOT NULL'
            );
        }
    }

    private function ensureReturnItemProductSkuCompatibility(): void
    {
        if (!$this->pdo || !$this->columnExists('order_return_items', 'product_sku')) {
            return;
        }

        $type = $this->columnTypeInfo('order_return_items', 'product_sku');
        if ($type === null) {
            return;
        }

        $nullCount = (int) ($this->pdo->query(
            'SELECT COUNT(*) FROM order_return_items WHERE product_sku IS NULL'
        )->fetchColumn() ?: 0);

        $shouldBeNotNull = $nullCount === 0;
        if (
            $type['data_type'] === 'bigint'
            && $type['unsigned'] === true
            && $type['nullable'] === !$shouldBeNotNull
        ) {
            return;
        }

        $nullSql = $shouldBeNotNull ? 'NOT NULL' : 'NULL';
        try {
            $this->pdo->exec(
                "ALTER TABLE order_return_items
                 MODIFY COLUMN product_sku BIGINT UNSIGNED {$nullSql}"
            );
        } catch (\Throwable $e) {
            error_log('Falha ao ajustar order_return_items.product_sku: ' . $e->getMessage());
        }
    }

    private function ensureReturnItemProductSkuForeignKey(): void
    {
        if (!$this->pdo) {
            return;
        }
        if (
            !$this->columnExists('order_return_items', 'product_sku')
            || !$this->columnExists('products', 'sku')
        ) {
            return;
        }
        if ($this->foreignKeyExists('order_return_items', 'fk_order_return_items_product_sku')) {
            return;
        }
        if (!$this->isCompatibleForeignKey('order_return_items', 'product_sku', 'products', 'sku')) {
            return;
        }

        $orphans = (int) ($this->pdo->query(
            "SELECT COUNT(*)
             FROM order_return_items ri
             WHERE ri.product_sku IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = ri.product_sku)"
        )->fetchColumn() ?: 0);
        if ($orphans > 0) {
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE order_return_items
                 ADD CONSTRAINT fk_order_return_items_product_sku
                 FOREIGN KEY (product_sku) REFERENCES products(sku)
                 ON DELETE RESTRICT
                 ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            error_log('Falha ao adicionar FK de order_return_items.product_sku: ' . $e->getMessage());
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }
        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
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
}
