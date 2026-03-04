<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;

class ConsignmentProductRegistryRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private const TABLE = 'consignment_product_registry';

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

    public function findByProductId(int $productId): ?array
    {
        if (!$this->pdo || $productId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE product_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
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
            $where[] = "r.supplier_pessoa_id = :supplier";
            $params[':supplier'] = (int) $filters['supplier_pessoa_id'];
        }

        if (!empty($filters['consignment_status'])) {
            if (is_array($filters['consignment_status'])) {
                $placeholders = [];
                foreach ($filters['consignment_status'] as $i => $s) {
                    $key = ':cs_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $s;
                }
                $where[] = 'r.consignment_status IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = "r.consignment_status = :cs";
                $params[':cs'] = $filters['consignment_status'];
            }
        }

        if (!empty($filters['received_from'])) {
            $where[] = "r.received_at >= :rfrom";
            $params[':rfrom'] = $filters['received_from'];
        }
        if (!empty($filters['received_to'])) {
            $where[] = "r.received_at <= :rto";
            $params[':rto'] = $filters['received_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.name LIKE :search OR CAST(p.sku AS CHAR) LIKE :search OR COALESCE(f.full_name, '') LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['filter_sku'])) {
            $where[] = "CAST(p.sku AS CHAR) LIKE :filter_sku";
            $params[':filter_sku'] = '%' . $filters['filter_sku'] . '%';
        }

        if (!empty($filters['filter_product_name'])) {
            $where[] = "p.name LIKE :filter_product_name";
            $params[':filter_product_name'] = '%' . $filters['filter_product_name'] . '%';
        }

        if (!empty($filters['filter_supplier_name'])) {
            $where[] = "(COALESCE(f.full_name, '') LIKE :filter_supplier_name OR CAST(r.supplier_pessoa_id AS CHAR) LIKE :filter_supplier_name)";
            $params[':filter_supplier_name'] = '%' . $filters['filter_supplier_name'] . '%';
        }

        if (!empty($filters['filter_consignment_status'])) {
            $rawStatus = trim((string) $filters['filter_consignment_status']);
            if (strpos($rawStatus, ',') !== false) {
                $statusValues = array_values(array_filter(array_map('trim', explode(',', $rawStatus))));
                if (!empty($statusValues)) {
                    $statusPlaceholders = [];
                    foreach ($statusValues as $idx => $value) {
                        $key = ':filter_cs_' . $idx;
                        $statusPlaceholders[] = $key;
                        $params[$key] = $value;
                    }
                    $where[] = 'r.consignment_status IN (' . implode(',', $statusPlaceholders) . ')';
                }
            } else {
                $where[] = "r.consignment_status LIKE :filter_consignment_status";
                $params[':filter_consignment_status'] = '%' . $rawStatus . '%';
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM " . self::TABLE . " r
                      LEFT JOIN products p ON p.sku = r.product_id
                      LEFT JOIN vw_fornecedores_compat f ON f.id = r.supplier_pessoa_id
                      {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT r.*,
                       COALESCE(p.name, CONCAT('Produto #', r.product_id)) AS product_name,
                       COALESCE(NULLIF(TRIM(CAST(p.sku AS CHAR)), ''), TRIM(CAST(r.product_id AS CHAR))) AS sku,
                       p.sku AS product_sku, p.price AS product_price,
                       p.name AS name,
                       p.price AS price,
                       p.status AS product_status, p.source AS product_source,
                       p.percentual_consignacao AS product_percent,
                       p.consignment_status AS product_consignment_status,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', r.supplier_pessoa_id)) AS supplier_name
                FROM " . self::TABLE . " r
                LEFT JOIN products p ON p.sku = r.product_id
                LEFT JOIN vw_fornecedores_compat f ON f.id = r.supplier_pessoa_id
                {$whereClause}
                ORDER BY r.created_at DESC, r.id DESC
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
     * @return array<int, array<string, mixed>>
     */
    public function listBySupplier(int $supplierPessoaId): array
    {
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE supplier_pessoa_id = :sid ORDER BY created_at DESC"
        );
        $stmt->execute([':sid' => $supplierPessoaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Summary counts grouped by consignment_status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query(
            "SELECT consignment_status, COUNT(*) AS cnt FROM " . self::TABLE . " GROUP BY consignment_status"
        );
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['consignment_status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Summary for dashboard: counts + value sums by status.
     *
     * @return array<string, array{count: int, value: float}>
     */
    public function dashboardSummary(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT r.consignment_status,
                       COUNT(*) AS cnt,
                       COALESCE(SUM(p.price), 0) AS total_price
                FROM " . self::TABLE . " r
                LEFT JOIN products p ON p.sku = r.product_id
                GROUP BY r.consignment_status";
        $stmt = $this->pdo->query($sql);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['consignment_status']] = [
                'count' => (int) $row['cnt'],
                'value' => (float) $row['total_price'],
            ];
        }

        // "Em estoque" do painel deve refletir estoque vendável atual, não apenas status legado.
        $stockStmt = $this->pdo->query(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(p.price), 0) AS total_price
             FROM " . self::TABLE . " r
             INNER JOIN products p ON p.sku = r.product_id
             WHERE r.consignment_status = 'em_estoque'
               AND p.quantity > 0
               AND p.status <> 'baixado'"
        );
        $stockRow = $stockStmt ? $stockStmt->fetch(PDO::FETCH_ASSOC) : null;
        $result['em_estoque'] = [
            'count' => (int) ($stockRow['cnt'] ?? 0),
            'value' => (float) ($stockRow['total_price'] ?? 0),
        ];

        return $result;
    }

    /**
     * Aging distribution for products in stock.
     *
     * @return array{0_30: int, 31_60: int, 61_90: int, over_90: int}
     */
    public function agingDistribution(): array
    {
        if (!$this->pdo) {
            return ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
        }
        $sql = "SELECT
                  SUM(CASE WHEN DATEDIFF(NOW(), r.received_at) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) AS d_0_30,
                  SUM(CASE WHEN DATEDIFF(NOW(), r.received_at) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS d_31_60,
                  SUM(CASE WHEN DATEDIFF(NOW(), r.received_at) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) AS d_61_90,
                  SUM(CASE WHEN DATEDIFF(NOW(), r.received_at) > 90 THEN 1 ELSE 0 END) AS d_over_90
                FROM " . self::TABLE . " r
                INNER JOIN products p ON p.sku = r.product_id
                WHERE r.consignment_status = 'em_estoque'
                  AND p.quantity > 0
                  AND p.status <> 'baixado'
                  AND r.received_at IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            '0_30' => (int) ($row['d_0_30'] ?? 0),
            '31_60' => (int) ($row['d_31_60'] ?? 0),
            '61_90' => (int) ($row['d_61_90'] ?? 0),
            'over_90' => (int) ($row['d_over_90'] ?? 0),
        ];
    }

    /**
     * Aging by supplier (top N).
     *
     * @return array<int, array{supplier_pessoa_id: int, supplier_name: string, count_in_stock: int, avg_days: float, count_over_90: int, potential_value: float}>
     */
    public function agingBySupplier(int $limit = 10): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT r.supplier_pessoa_id,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', r.supplier_pessoa_id)) AS supplier_name,
                       COUNT(*) AS count_in_stock,
                       AVG(DATEDIFF(NOW(), r.received_at)) AS avg_days,
                       SUM(CASE WHEN DATEDIFF(NOW(), r.received_at) > 90 THEN 1 ELSE 0 END) AS count_over_90,
                       COALESCE(SUM(p.price), 0) AS potential_value
                FROM " . self::TABLE . " r
                INNER JOIN products p ON p.sku = r.product_id
                LEFT JOIN vw_fornecedores_compat f ON f.id = r.supplier_pessoa_id
                WHERE r.consignment_status = 'em_estoque'
                  AND p.quantity > 0
                  AND p.status <> 'baixado'
                  AND r.received_at IS NOT NULL
                GROUP BY r.supplier_pessoa_id, supplier_name
                ORDER BY avg_days DESC
                LIMIT :lim";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    /**
     * Insert a new registry entry. Returns the inserted ID.
     */
    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO " . self::TABLE . " (
                  product_id, supplier_pessoa_id, consignment_supplier_original_id,
                  origin_type, intake_id, consignment_id, received_at,
                  consignment_percent_snapshot, minimum_price_snapshot,
                  consignment_status, status_changed_at, status_changed_by,
                  detached_at, original_source, notes
                ) VALUES (
                  :product_id, :supplier_pessoa_id, :consignment_supplier_original_id,
                  :origin_type, :intake_id, :consignment_id, :received_at,
                  :consignment_percent_snapshot, :minimum_price_snapshot,
                  :consignment_status, :status_changed_at, :status_changed_by,
                  :detached_at, :original_source, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':product_id' => $data['product_id'],
            ':supplier_pessoa_id' => $data['supplier_pessoa_id'],
            ':consignment_supplier_original_id' => $data['consignment_supplier_original_id'] ?? $data['supplier_pessoa_id'],
            ':origin_type' => $data['origin_type'] ?? 'lote_produtos',
            ':intake_id' => $data['intake_id'] ?? null,
            ':consignment_id' => $data['consignment_id'] ?? null,
            ':received_at' => $data['received_at'] ?? date('Y-m-d'),
            ':consignment_percent_snapshot' => $data['consignment_percent_snapshot'] ?? null,
            ':minimum_price_snapshot' => $data['minimum_price_snapshot'] ?? null,
            ':consignment_status' => $data['consignment_status'] ?? 'em_estoque',
            ':status_changed_at' => $data['status_changed_at'] ?? date('Y-m-d H:i:s'),
            ':status_changed_by' => $data['status_changed_by'] ?? null,
            ':detached_at' => $data['detached_at'] ?? null,
            ':original_source' => $data['original_source'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $this->auditLog('INSERT', 'consignment_registry', $id, null, $data);

        return $id;
    }

    /**
     * Upsert by product_id.
     */
    public function upsert(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existing = $this->findByProductId((int) $data['product_id']);
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * Update a registry entry.
     */
    public function update(int $id, array $data): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }

        $old = $this->find($id);

        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'supplier_pessoa_id', 'consignment_supplier_original_id',
            'origin_type', 'intake_id', 'consignment_id', 'received_at',
            'consignment_percent_snapshot', 'minimum_price_snapshot',
            'consignment_status', 'status_changed_at', 'status_changed_by',
            'detached_at', 'original_source', 'notes',
        ];

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

        $this->auditLog('UPDATE', 'consignment_registry', $id, $old, $data);
    }

    /**
     * Update status for a specific product.
     */
    public function updateStatusByProduct(int $productId, string $status, ?int $userId = null, ?string $notes = null): void
    {
        if (!$this->pdo || $productId <= 0) {
            return;
        }

        $existing = $this->findByProductId($productId);
        if (!$existing) {
            return;
        }

        $this->update((int) $existing['id'], [
            'consignment_status' => $status,
            'status_changed_at' => date('Y-m-d H:i:s'),
            'status_changed_by' => $userId,
            'notes' => $notes !== null
                ? (($existing['notes'] ?? '') !== '' ? $existing['notes'] . "\n" . $notes : $notes)
                : $existing['notes'],
        ]);
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
          supplier_pessoa_id BIGINT UNSIGNED NOT NULL COMMENT 'Fornecedora atual (NULL quando vira proprio)',
          consignment_supplier_original_id BIGINT UNSIGNED NULL COMMENT 'Fornecedora original — preservado sempre',
          origin_type ENUM('pre_lote','sku_consignment','lote_produtos','manual') NOT NULL DEFAULT 'lote_produtos',
          intake_id INT UNSIGNED NULL COMMENT 'FK consignacao_recebimentos (se veio de pré-lote)',
          consignment_id BIGINT UNSIGNED NULL COMMENT 'FK consignments (se veio de consignação por SKU)',
          received_at DATE NULL COMMENT 'Data de recebimento na loja',
          consignment_percent_snapshot DECIMAL(5,2) NULL COMMENT 'Snapshot do %% no momento do cadastro',
          minimum_price_snapshot DECIMAL(10,2) NULL COMMENT 'Preço mínimo acordado (se houver)',
          consignment_status ENUM('em_estoque','vendido_pendente','vendido_pago','proprio_pos_pgto','devolvido','doado','descartado') NOT NULL DEFAULT 'em_estoque',
          status_changed_at DATETIME NULL,
          status_changed_by BIGINT UNSIGNED NULL COMMENT 'user_id que alterou',
          detached_at DATETIME NULL COMMENT 'Quando virou proprio_pos_pgto',
          original_source VARCHAR(30) NULL COMMENT 'source original do produto antes de reclassificar',
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consign_registry_product (product_id),
          INDEX idx_consign_registry_supplier (supplier_pessoa_id),
          INDEX idx_consign_registry_original_supplier (consignment_supplier_original_id),
          INDEX idx_consign_registry_status (consignment_status),
          INDEX idx_consign_registry_received (received_at),
          INDEX idx_consign_registry_intake (intake_id),
          INDEX idx_consign_registry_consignment (consignment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
