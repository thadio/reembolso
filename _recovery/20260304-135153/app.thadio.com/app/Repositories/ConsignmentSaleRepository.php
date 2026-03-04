<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;

class ConsignmentSaleRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private const TABLE = 'consignment_sales';

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    // ─── QUERIES ────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByOrderItem(int $orderId, int $orderItemId, int $productId): ?array
    {
        if (!$this->pdo || $orderId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . "
             WHERE order_id = :oid AND order_item_id = :oiid AND product_id = :pid
             LIMIT 1"
        );
        $stmt->execute([':oid' => $orderId, ':oiid' => $orderItemId, ':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * List sales for a supplier with optional filters + pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{items: array, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        if (!$this->pdo) {
            return ['items' => [], 'total' => 0];
        }

        $where = [];
        $params = [];

        if (!empty($filters['supplier_pessoa_id'])) {
            $where[] = "s.supplier_pessoa_id = :supplier";
            $params[':supplier'] = (int) $filters['supplier_pessoa_id'];
        }
        if (!empty($filters['sale_status'])) {
            $where[] = "s.sale_status = :ss";
            $params[':ss'] = $filters['sale_status'];
        }
        if (!empty($filters['payout_status'])) {
            $where[] = "s.payout_status = :ps";
            $params[':ps'] = $filters['payout_status'];
        }
        if (!empty($filters['sold_from'])) {
            $where[] = "s.sold_at >= :sfrom";
            $params[':sfrom'] = $filters['sold_from'];
        }
        if (!empty($filters['sold_to'])) {
            $where[] = "s.sold_at <= :sto";
            $params[':sto'] = $filters['sold_to'];
        }
        if (!empty($filters['paid_from'])) {
            $where[] = "s.paid_at >= :pfrom";
            $params[':pfrom'] = $filters['paid_from'];
        }
        if (!empty($filters['paid_to'])) {
            $where[] = "s.paid_at <= :pto";
            $params[':pto'] = $filters['paid_to'];
        }
        if (!empty($filters['payout_id'])) {
            $where[] = "s.payout_id = :payid";
            $params[':payid'] = (int) $filters['payout_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(COALESCE(p.name, p2.name, oi.product_name, '') LIKE :search OR CAST(COALESCE(p.sku, p2.sku, oi.product_sku, s.product_id) AS CHAR) LIKE :search OR CAST(s.order_id AS CHAR) LIKE :search OR COALESCE(f.full_name, '') LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['filter_order_id'])) {
            $where[] = "CAST(s.order_id AS CHAR) LIKE :filter_order_id";
            $params[':filter_order_id'] = '%' . $filters['filter_order_id'] . '%';
        }

        if (!empty($filters['filter_sku'])) {
            $where[] = "CAST(COALESCE(p.sku, p2.sku, oi.product_sku, s.product_id) AS CHAR) LIKE :filter_sku";
            $params[':filter_sku'] = '%' . $filters['filter_sku'] . '%';
        }

        if (!empty($filters['filter_product_name'])) {
            $where[] = "COALESCE(p.name, p2.name, oi.product_name, '') LIKE :filter_product_name";
            $params[':filter_product_name'] = '%' . $filters['filter_product_name'] . '%';
        }

        if (!empty($filters['filter_supplier_name'])) {
            $where[] = "(COALESCE(f.full_name, '') LIKE :filter_supplier_name OR CAST(s.supplier_pessoa_id AS CHAR) LIKE :filter_supplier_name)";
            $params[':filter_supplier_name'] = '%' . $filters['filter_supplier_name'] . '%';
        }

        if (!empty($filters['filter_sale_status'])) {
            $rawSaleStatus = trim((string) $filters['filter_sale_status']);
            if (strpos($rawSaleStatus, ',') !== false) {
                $values = array_values(array_filter(array_map('trim', explode(',', $rawSaleStatus))));
                if (!empty($values)) {
                    $placeholders = [];
                    foreach ($values as $idx => $value) {
                        $key = ':filter_sale_status_' . $idx;
                        $placeholders[] = $key;
                        $params[$key] = $value;
                    }
                    $where[] = 's.sale_status IN (' . implode(',', $placeholders) . ')';
                }
            } else {
                $where[] = "s.sale_status LIKE :filter_sale_status";
                $params[':filter_sale_status'] = '%' . $rawSaleStatus . '%';
            }
        }

        if (!empty($filters['filter_payout_status'])) {
            $rawPayoutStatus = trim((string) $filters['filter_payout_status']);
            if (strpos($rawPayoutStatus, ',') !== false) {
                $values = array_values(array_filter(array_map('trim', explode(',', $rawPayoutStatus))));
                if (!empty($values)) {
                    $placeholders = [];
                    foreach ($values as $idx => $value) {
                        $key = ':filter_payout_status_' . $idx;
                        $placeholders[] = $key;
                        $params[$key] = $value;
                    }
                    $where[] = 's.payout_status IN (' . implode(',', $placeholders) . ')';
                }
            } else {
                $where[] = "s.payout_status LIKE :filter_payout_status";
                $params[':filter_payout_status'] = '%' . $rawPayoutStatus . '%';
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM " . self::TABLE . " s
                      LEFT JOIN order_items oi ON oi.id = s.order_item_id
                      LEFT JOIN products p  ON p.sku  = s.product_id
                      LEFT JOIN products p2 ON p2.sku = oi.product_sku
                      LEFT JOIN vw_fornecedores_compat f ON f.id = s.supplier_pessoa_id
                      {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT s.*,
                       COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', s.product_id)) AS product_name,
                       COALESCE(
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           TRIM(CAST(s.product_id AS CHAR))
                       ) AS sku,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', s.supplier_pessoa_id)) AS supplier_name
                FROM " . self::TABLE . " s
                LEFT JOIN order_items oi ON oi.id = s.order_item_id
                LEFT JOIN products p  ON p.sku  = s.product_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                LEFT JOIN vw_fornecedores_compat f ON f.id = s.supplier_pessoa_id
                {$whereClause}
                ORDER BY s.sold_at DESC, s.id DESC
                LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * List pending active sales for a given supplier (for payout selection).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPendingBySupplier(int $supplierPessoaId, ?string $soldFrom = null, ?string $soldTo = null): array
    {
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return [];
        }

        $where = "s.supplier_pessoa_id = :sid AND s.sale_status = 'ativa' AND s.payout_status = 'pendente'";
        $params = [':sid' => $supplierPessoaId];

        if ($soldFrom) {
            $where .= " AND s.sold_at >= :sfrom";
            $params[':sfrom'] = $soldFrom;
        }
        if ($soldTo) {
            $where .= " AND s.sold_at <= :sto";
            $params[':sto'] = $soldTo;
        }

        $sql = "SELECT s.*,
                       COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', s.product_id)) AS product_name,
                       COALESCE(
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           TRIM(CAST(s.product_id AS CHAR))
                       ) AS sku
                FROM " . self::TABLE . " s
                LEFT JOIN order_items oi ON oi.id = s.order_item_id
                LEFT JOIN products p  ON p.sku  = s.product_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE {$where}
                ORDER BY s.sold_at ASC, s.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List active sales selectable when editing a confirmed payout.
     *
     * Includes:
     * - Pending sales for the supplier (optionally filtered by sold_at period);
     * - Sales already linked to this payout (always included, regardless of period filter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSelectableBySupplierForPayoutEdit(
        int $supplierPessoaId,
        int $payoutId,
        ?string $soldFrom = null,
        ?string $soldTo = null
    ): array {
        if (!$this->pdo || $supplierPessoaId <= 0 || $payoutId <= 0) {
            return [];
        }

        $pendingWhere = ["s.payout_status = 'pendente'"];
        $params = [
            ':sid' => $supplierPessoaId,
            ':pid' => $payoutId,
        ];

        if ($soldFrom) {
            $pendingWhere[] = 's.sold_at >= :sfrom';
            $params[':sfrom'] = $soldFrom;
        }
        if ($soldTo) {
            $pendingWhere[] = 's.sold_at <= :sto';
            $params[':sto'] = $soldTo;
        }

        $sql = "SELECT s.*,
                       COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', s.product_id)) AS product_name,
                       COALESCE(
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           TRIM(CAST(s.product_id AS CHAR))
                       ) AS sku
                FROM " . self::TABLE . " s
                LEFT JOIN order_items oi ON oi.id = s.order_item_id
                LEFT JOIN products p  ON p.sku  = s.product_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE s.supplier_pessoa_id = :sid
                  AND s.sale_status = 'ativa'
                  AND ((" . implode(' AND ', $pendingWhere) . ") OR s.payout_id = :pid)
                ORDER BY
                  CASE WHEN s.payout_id = :pid THEN 0 ELSE 1 END,
                  s.sold_at ASC,
                  s.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List sales by payout.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByPayout(int $payoutId): array
    {
        if (!$this->pdo || $payoutId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', s.product_id)) AS product_name,
                    COALESCE(
                        NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                        TRIM(CAST(s.product_id AS CHAR))
                    ) AS sku
             FROM " . self::TABLE . " s
             LEFT JOIN order_items oi ON oi.id = s.order_item_id
             LEFT JOIN products p  ON p.sku  = s.product_id
             LEFT JOIN products p2 ON p2.sku = oi.product_sku
             WHERE s.payout_id = :pid
             ORDER BY s.sold_at ASC"
        );
        $stmt->execute([':pid' => $payoutId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List active sales for given product IDs.
     *
     * @param int[] $productIds
     * @return array<int, array<string, mixed>>
     */
    public function listActiveByProductIds(array $productIds): array
    {
        if (!$this->pdo || empty($productIds)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), fn(int $id) => $id > 0)));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM " . self::TABLE . "
                WHERE product_id IN ({$placeholders}) AND sale_status = 'ativa'
                ORDER BY sold_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Summary: total pending commission by supplier.
     *
     * @return array<int, array{
     *   supplier_pessoa_id: int,
     *   supplier_name: string,
     *   pending_count: int,
     *   pending_net_amount: float,
     *   pending_amount: float
     * }>
     */
    public function pendingSummaryBySupplier(int $limit = 50): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT s.supplier_pessoa_id,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', s.supplier_pessoa_id)) AS supplier_name,
                       COUNT(*) AS pending_count,
                       SUM(s.net_amount) AS pending_net_amount,
                       SUM(s.credit_amount) AS pending_amount
                FROM " . self::TABLE . " s
                LEFT JOIN vw_fornecedores_compat f ON f.id = s.supplier_pessoa_id
                WHERE s.sale_status = 'ativa' AND s.payout_status = 'pendente'
                GROUP BY s.supplier_pessoa_id, supplier_name
                ORDER BY pending_amount DESC
                LIMIT :lim";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dashboard summary for sold consignment items by payout status.
     *
     * Value uses sale net_amount (realized sale value), while commission keeps
     * the supplier credit amount for optional secondary displays.
     *
     * @return array{
     *   vendido_pendente: array{count: int, value: float, commission: float},
     *   vendido_pago: array{count: int, value: float, commission: float}
     * }
     */
    public function dashboardSoldSummary(): array
    {
        $summary = [
            'vendido_pendente' => ['count' => 0, 'value' => 0.0, 'commission' => 0.0],
            'vendido_pago' => ['count' => 0, 'value' => 0.0, 'commission' => 0.0],
        ];

        if (!$this->pdo) {
            return $summary;
        }

        $sql = "SELECT s.payout_status,
                       COUNT(*) AS cnt,
                       COALESCE(SUM(s.net_amount), 0) AS total_value,
                       COALESCE(SUM(s.credit_amount), 0) AS total_commission
                FROM " . self::TABLE . " s
                WHERE s.sale_status = 'ativa'
                  AND s.payout_status IN ('pendente', 'pago')
                GROUP BY s.payout_status";

        $stmt = $this->pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = ($row['payout_status'] ?? '') === 'pago' ? 'vendido_pago' : 'vendido_pendente';
            $summary[$status] = [
                'count' => (int) ($row['cnt'] ?? 0),
                'value' => (float) ($row['total_value'] ?? 0),
                'commission' => (float) ($row['total_commission'] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * Aggregated list of suppliers with pending consignment payouts.
     *
     * The records in consignment_sales are generated only for paid orders,
     * so this list already represents paid sales pending commission payout.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingPayoutCandidatesBySupplier(?string $soldFrom = null, ?string $soldTo = null, int $limit = 500): array
    {
        if (!$this->pdo) {
            return [];
        }

        $where = [
            "s.sale_status = 'ativa'",
            "s.payout_status = 'pendente'",
        ];
        $params = [];

        if ($soldFrom) {
            $where[] = 's.sold_at >= :sfrom';
            $params[':sfrom'] = $soldFrom;
        }
        if ($soldTo) {
            $where[] = 's.sold_at <= :sto';
            $params[':sto'] = $soldTo;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $limit = max(1, min(1000, $limit));

        $sql = "SELECT s.supplier_pessoa_id,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', s.supplier_pessoa_id)) AS supplier_name,
                       COUNT(*) AS pending_count,
                       COUNT(DISTINCT s.order_id) AS pending_orders_count,
                       COUNT(DISTINCT s.product_id) AS pending_products_count,
                       SUM(s.net_amount) AS pending_net_amount,
                       SUM(s.credit_amount) AS pending_commission_amount,
                       MIN(s.sold_at) AS oldest_sold_at,
                       MAX(s.sold_at) AS latest_sold_at
                FROM " . self::TABLE . " s
                LEFT JOIN vw_fornecedores_compat f ON f.id = s.supplier_pessoa_id
                {$whereClause}
                GROUP BY s.supplier_pessoa_id, supplier_name
                ORDER BY pending_commission_amount DESC, oldest_sold_at ASC
                LIMIT :lim";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    /**
     * Create a new consignment sale record. Returns the inserted ID.
     */
    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO " . self::TABLE . " (
                  product_id, order_id, order_item_id, supplier_pessoa_id,
                  sold_at, gross_amount, discount_amount, net_amount,
                  percent_applied, credit_amount, commission_formula_version,
                  ledger_credit_movement_id, sale_status, reversed_at,
                  reversal_event_type, reversal_notes,
                  payout_status, payout_id, paid_at
                ) VALUES (
                  :product_id, :order_id, :order_item_id, :supplier_pessoa_id,
                  :sold_at, :gross_amount, :discount_amount, :net_amount,
                  :percent_applied, :credit_amount, :commission_formula_version,
                  :ledger_credit_movement_id, :sale_status, :reversed_at,
                  :reversal_event_type, :reversal_notes,
                  :payout_status, :payout_id, :paid_at
                )";

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([
                ':product_id' => $data['product_id'],
                ':order_id' => $data['order_id'],
                ':order_item_id' => $data['order_item_id'],
                ':supplier_pessoa_id' => $data['supplier_pessoa_id'],
                ':sold_at' => $data['sold_at'] ?? null,
                ':gross_amount' => $data['gross_amount'] ?? 0,
                ':discount_amount' => $data['discount_amount'] ?? 0,
                ':net_amount' => $data['net_amount'] ?? 0,
                ':percent_applied' => $data['percent_applied'] ?? 0,
                ':credit_amount' => $data['credit_amount'] ?? 0,
                ':commission_formula_version' => $data['commission_formula_version'] ?? 'v1',
                ':ledger_credit_movement_id' => $data['ledger_credit_movement_id'] ?? null,
                ':sale_status' => $data['sale_status'] ?? 'ativa',
                ':reversed_at' => $data['reversed_at'] ?? null,
                ':reversal_event_type' => $data['reversal_event_type'] ?? null,
                ':reversal_notes' => $data['reversal_notes'] ?? null,
                ':payout_status' => $data['payout_status'] ?? 'pendente',
                ':payout_id' => $data['payout_id'] ?? null,
                ':paid_at' => $data['paid_at'] ?? null,
            ]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return 0; // duplicate
            }
            throw $e;
        }

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLog('create', 'consignment_sale', $id, null, $data);
        return $id;
    }

    /**
     * Upsert by (order_id, order_item_id, product_id).
     */
    public function upsert(array $data): int
    {
        $existing = $this->findByOrderItem(
            (int) $data['order_id'],
            (int) $data['order_item_id'],
            (int) $data['product_id']
        );

        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * Update a sale record.
     */
    public function update(int $id, array $data): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }

        $old = $this->find($id);

        $updatable = [
            'sold_at', 'gross_amount', 'discount_amount', 'net_amount',
            'percent_applied', 'credit_amount', 'commission_formula_version',
            'ledger_credit_movement_id',
            'sale_status', 'reversed_at', 'reversal_event_type', 'reversal_notes',
            'payout_status', 'payout_id', 'paid_at',
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($updatable as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->auditLog('update', 'consignment_sale', $id, $old, $data);
    }

    /**
     * Mark a sale as reversed.
     */
    public function markReversed(int $id, string $eventType, ?string $notes = null): void
    {
        $this->update($id, [
            'sale_status' => 'revertida',
            'reversed_at' => date('Y-m-d H:i:s'),
            'reversal_event_type' => $eventType,
            'reversal_notes' => $notes,
        ]);
    }

    /**
     * Mark sales as paid by payout.
     *
     * @param int[] $saleIds
     */
    public function markPaidByPayout(array $saleIds, int $payoutId, string $paidAt): void
    {
        if (!$this->pdo || empty($saleIds) || $payoutId <= 0) {
            return;
        }

        foreach ($saleIds as $saleId) {
            $this->update((int) $saleId, [
                'payout_status' => 'pago',
                'payout_id' => $payoutId,
                'paid_at' => $paidAt,
            ]);
        }
    }

    /**
     * Reset payout fields for sales in a given payout (for cancellation).
     */
    public function resetPayoutForSales(int $payoutId): void
    {
        if (!$this->pdo || $payoutId <= 0) {
            return;
        }

        $items = $this->listByPayout($payoutId);
        foreach ($items as $item) {
            $this->update((int) $item['id'], [
                'payout_status' => 'pendente',
                'payout_id' => null,
                'paid_at' => null,
            ]);
        }
    }

    /**
     * Find the first active sale for a given product.
     */
    public function findActiveByProduct(int $productId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $sql = "SELECT * FROM " . self::TABLE . "
                WHERE product_id = :pid AND sale_status = 'ativa'
                ORDER BY sold_at DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Count sales without matching ledger credit entries (for integrity checks).
     */
    public function countOrphanSales(): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " s
                WHERE s.sale_status = 'ativa'
                  AND s.ledger_credit_movement_id IS NOT NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM cupons_creditos_movimentos m WHERE m.id = s.ledger_credit_movement_id
                  )";
        $stmt = $this->pdo->query($sql);
        return (int) $stmt->fetchColumn();
    }

    // ─── SCHEMA ─────────────────────────────────────────────────

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id BIGINT UNSIGNED NOT NULL,
          order_id BIGINT UNSIGNED NOT NULL,
          order_item_id BIGINT UNSIGNED NOT NULL,
          supplier_pessoa_id BIGINT UNSIGNED NOT NULL,
          sold_at DATETIME NULL,
          gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço bruto do item',
          discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Descontos aplicados',
          net_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor líquido base para comissão',
          percent_applied DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          credit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor da comissão gerada',
          commission_formula_version VARCHAR(20) NOT NULL DEFAULT 'v1',
          ledger_credit_movement_id BIGINT UNSIGNED NULL COMMENT 'FK cupons_creditos_movimentos.id',
          sale_status ENUM('ativa','revertida') NOT NULL DEFAULT 'ativa',
          reversed_at DATETIME NULL,
          reversal_event_type VARCHAR(50) NULL,
          reversal_notes TEXT NULL,
          payout_status ENUM('pendente','pago') NOT NULL DEFAULT 'pendente',
          payout_id BIGINT UNSIGNED NULL COMMENT 'FK consignment_payouts',
          paid_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consign_sale_item (order_id, order_item_id, product_id),
          INDEX idx_consign_sale_product (product_id),
          INDEX idx_consign_sale_order (order_id),
          INDEX idx_consign_sale_supplier (supplier_pessoa_id),
          INDEX idx_consign_sale_payout_status (payout_status),
          INDEX idx_consign_sale_sale_status (sale_status),
          INDEX idx_consign_sale_payout (payout_id),
          INDEX idx_consign_sale_sold_at (sold_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
