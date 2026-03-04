<?php

namespace App\Repositories;

use App\Models\Product;
use PDO;

/**
 * Compatibility adapter for legacy inventory item screens.
 *
 * Source of truth is always `products`.
 */
class InventoryItemRepository
{
    private ?PDO $pdo;
    private ProductRepository $products;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->products = new ProductRepository($pdo);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Backward-compatibility hook used by legacy tests.
     * Source of truth remains `products`.
     */
    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $reflection = new \ReflectionClass($this->products);
        foreach (['ensureTable', 'ensureColumnsAndIndexes'] as $methodName) {
            if (!$reflection->hasMethod($methodName)) {
                continue;
            }
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            $method->invoke($this->products);
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0, string $sortKey = 'entered_at', string $sortDir = 'desc'): array
    {
        if (!$this->pdo) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $this->toUnifiedStatus((string) $filters['status']);
        }

        if (!empty($filters['source'])) {
            $where[] = 'p.source = :source';
            $params[':source'] = $this->normalizeSource((string) $filters['source']);
        }

        if (!empty($filters['supplier_pessoa_id'])) {
            $where[] = 'p.supplier_pessoa_id = :supplier_pessoa_id';
            $params[':supplier_pessoa_id'] = (int) $filters['supplier_pessoa_id'];
        }

        $supplierSearch = trim((string) ($filters['supplier_search'] ?? ''));
        if ($supplierSearch !== '' && empty($filters['supplier_pessoa_id'])) {
            $where[] = 'sup.full_name LIKE :supplier_search';
            $params[':supplier_search'] = '%' . $supplierSearch . '%';
        }

        $productSkuFilter = $filters['product_sku'] ?? null;
        if ($productSkuFilter !== null && $productSkuFilter !== '') {
            $where[] = 'p.sku = :product_sku';
            $params[':product_sku'] = (int) $productSkuFilter;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'p.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'p.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(p.sku AS CHAR) LIKE :search OR p.name LIKE :search OR JSON_EXTRACT(p.metadata, "$.internal_code") LIKE :search)';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sortColumn = $this->resolveSortColumn($sortKey);
        $sortDirection = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT
                    p.*,
                    sup.full_name AS supplier_name
                FROM products p
                LEFT JOIN pessoas sup ON sup.id = p.supplier_pessoa_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$sortColumn} {$sortDirection}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $row): array => $this->toInventoryRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $this->toUnifiedStatus((string) $filters['status']);
        }

        if (!empty($filters['source'])) {
            $where[] = 'p.source = :source';
            $params[':source'] = $this->normalizeSource((string) $filters['source']);
        }

        if (!empty($filters['supplier_pessoa_id'])) {
            $where[] = 'p.supplier_pessoa_id = :supplier_pessoa_id';
            $params[':supplier_pessoa_id'] = (int) $filters['supplier_pessoa_id'];
        }

        $supplierSearch = trim((string) ($filters['supplier_search'] ?? ''));
        if ($supplierSearch !== '' && empty($filters['supplier_pessoa_id'])) {
            $where[] = 'sup.full_name LIKE :supplier_search';
            $params[':supplier_search'] = '%' . $supplierSearch . '%';
        }

        $productSkuFilter = $filters['product_sku'] ?? null;
        if ($productSkuFilter !== null && $productSkuFilter !== '') {
            $where[] = 'p.sku = :product_sku';
            $params[':product_sku'] = (int) $productSkuFilter;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'p.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'p.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(p.sku AS CHAR) LIKE :search OR p.name LIKE :search OR JSON_EXTRACT(p.metadata, "$.internal_code") LIKE :search)';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql = 'SELECT COUNT(*) FROM products p WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.*, sup.full_name AS supplier_name
             FROM products p
             LEFT JOIN pessoas sup ON sup.id = p.supplier_pessoa_id
             WHERE p.sku = :sku
             LIMIT 1'
        );
        $stmt->execute([':sku' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->toInventoryRow($row) : null;
    }

    public function findByCode(string $code): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.*, sup.full_name AS supplier_name
             FROM products p
             LEFT JOIN pessoas sup ON sup.id = p.supplier_pessoa_id
             WHERE JSON_UNQUOTE(JSON_EXTRACT(p.metadata, "$.internal_code")) = :code
                OR CAST(p.sku AS CHAR) = :code
             LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->toInventoryRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sku = isset($data['id']) && (int) $data['id'] > 0
            ? (int) $data['id']
            : (isset($data['sku']) ? (int) $data['sku'] : 0);
        if ($sku <= 0) {
            $sku = $this->getNextSku();
        }

        $existing = $this->products->find($sku);
        $payload = $existing ? $existing->toArray() : [];

        $payload['sku'] = $sku;
        $payload['name'] = trim((string) ($data['title_override'] ?? ($data['product_name'] ?? ($payload['name'] ?? 'Produto #' . $sku))));
        $payload['source'] = $this->normalizeSource((string) ($data['source'] ?? ($payload['source'] ?? 'compra')));
        $payload['supplier_pessoa_id'] = $this->nullableInt($data['supplier_pessoa_id'] ?? ($payload['supplier_pessoa_id'] ?? null));
        $payload['cost'] = $this->nullableFloat($data['acquisition_cost'] ?? ($payload['cost'] ?? null));
        $payload['percentual_consignacao'] = $this->nullableFloat($data['consignment_percent'] ?? ($payload['percentual_consignacao'] ?? null));
        $payload['price'] = $this->nullableFloat($data['price_listed'] ?? ($payload['price'] ?? null));
        $payload['condition_grade'] = $this->normalizeCondition((string) ($data['condition_grade'] ?? ($payload['condition_grade'] ?? 'usado')));
        $payload['size'] = $this->nullableString($data['size'] ?? ($payload['size'] ?? null));
        $payload['color'] = $this->nullableString($data['color'] ?? ($payload['color'] ?? null));

        $status = (string) ($data['status'] ?? ($payload['status'] ?? 'disponivel'));
        $statusUnified = $this->toUnifiedStatus($status);
        $payload['status'] = $statusUnified;

        if (in_array($statusUnified, ['esgotado', 'baixado', 'archived'], true)) {
            $payload['quantity'] = 0;
        } elseif (!isset($data['quantity'])) {
            $payload['quantity'] = max(1, (int) ($payload['quantity'] ?? 1));
        } else {
            $payload['quantity'] = max(0, (int) $data['quantity']);
        }

        if ($statusUnified === 'esgotado' && ((int) $payload['quantity']) > 0) {
            $payload['status'] = 'disponivel';
        }

        $metadata = $this->decodeMetadata($payload['metadata'] ?? null);
        $internalCode = trim((string) ($data['internal_code'] ?? ($metadata['internal_code'] ?? $sku)));
        $metadata['internal_code'] = $internalCode;
        $metadata['title_override'] = $this->nullableString($data['title_override'] ?? ($metadata['title_override'] ?? null));
        $metadata['gender'] = $this->nullableString($data['gender'] ?? ($metadata['gender'] ?? null));
        $metadata['consignment_id'] = $this->nullableInt($data['consignment_id'] ?? ($metadata['consignment_id'] ?? null));
        $metadata['entered_at'] = $this->nullableString($data['entered_at'] ?? ($metadata['entered_at'] ?? null));
        $metadata['notes'] = $this->nullableString($data['notes'] ?? ($metadata['notes'] ?? null));
        $metadata['inventory_status'] = $this->toInventoryStatus($payload['status'], (int) $payload['quantity']);
        $payload['metadata'] = $metadata;

        $saved = $this->products->save(Product::fromArray($payload));

        return (int) $saved->sku;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $item = $this->find($id);
        if (!$item) {
            return false;
        }

        $data = [
            'id' => $id,
            'status' => $status,
        ];

        if ($status === 'vendido') {
            $data['quantity'] = 0;
        }

        $this->save($data);
        return true;
    }

    public function delete(int $id): bool
    {
        return $this->updateStatus($id, 'baixado');
    }

    public function getNextSku(): int
    {
        return $this->products->nextSku();
    }

    public function skuExists(int $sku, ?int $excludeId = null): bool
    {
        return $this->products->skuExists($sku, $excludeId);
    }

    private function resolveSortColumn(string $sortKey): string
    {
        return match ($sortKey) {
            'id' => 'p.sku',
            'internal_code' => 'p.sku',
            'product_name' => 'p.name',
            'supplier_name' => 'sup.full_name',
            'status' => 'p.status',
            'source' => 'p.source',
            'acquisition_cost' => 'p.cost',
            'price_listed' => 'p.price',
            default => 'p.created_at',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toInventoryRow(array $row): array
    {
        $metadata = $this->decodeMetadata($row['metadata'] ?? null);
        $statusUnified = (string) ($row['status'] ?? 'draft');
        $quantity = (int) ($row['quantity'] ?? 0);

        return [
            'id' => (int) ($row['sku'] ?? 0),
            'inventory_code' => (string) ($metadata['internal_code'] ?? ($row['sku'] ?? '')),
            'internal_code' => (string) ($metadata['internal_code'] ?? ($row['sku'] ?? '')),
            'product_sku' => (int) ($row['sku'] ?? 0),
            'sku' => (int) ($row['sku'] ?? 0),
            'title_override' => $metadata['title_override'] ?? null,
            'condition' => (string) ($row['condition_grade'] ?? 'usado'),
            'condition_grade' => (string) ($row['condition_grade'] ?? 'usado'),
            'size' => $row['size'] ?? null,
            'color' => $row['color'] ?? null,
            'gender' => $metadata['gender'] ?? null,
            'source' => (string) ($row['source'] ?? 'compra'),
            'status' => $this->toInventoryStatus($statusUnified, $quantity),
            'supplier_pessoa_id' => isset($row['supplier_pessoa_id']) ? (int) $row['supplier_pessoa_id'] : null,
            'consignment_id' => isset($metadata['consignment_id']) ? (int) $metadata['consignment_id'] : null,
            'acquisition_cost' => isset($row['cost']) ? (float) $row['cost'] : null,
            'consignment_percent' => isset($row['percentual_consignacao']) ? (float) $row['percentual_consignacao'] : null,
            'price_listed' => isset($row['price']) ? (float) $row['price'] : null,
            'published_price' => isset($row['price']) ? (float) $row['price'] : null,
            'quantity' => $quantity,
            'entered_at' => $metadata['entered_at'] ?? ($row['created_at'] ?? null),
            'sold_at' => $row['last_sold_at'] ?? null,
            'notes' => $metadata['notes'] ?? null,
            'product_name' => (string) ($row['name'] ?? ''),
            'supplier_name' => $row['supplier_name'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function toUnifiedStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return match ($value) {
            'disponivel' => 'disponivel',
            'reservado' => 'reservado',
            'vendido', 'esgotado' => 'esgotado',
            'baixado' => 'baixado',
            'archived', 'trash', 'inativo' => 'archived',
            default => 'draft',
        };
    }

    private function toInventoryStatus(string $statusUnified, int $quantity): string
    {
        $status = strtolower(trim($statusUnified));

        if ($status === 'baixado' || $status === 'archived') {
            return 'baixado';
        }
        if ($status === 'reservado') {
            return 'reservado';
        }
        if ($status === 'esgotado' || $quantity <= 0) {
            return 'esgotado';
        }

        return 'disponivel';
    }

    private function normalizeSource(string $source): string
    {
        $value = strtolower(trim($source));
        return in_array($value, ['compra', 'consignacao', 'doacao'], true) ? $value : 'compra';
    }

    private function normalizeCondition(string $condition): string
    {
        $value = strtolower(trim($condition));
        return match ($value) {
            'novo' => 'novo',
            'defeituoso' => 'usado_com_detalhes',
            'usado_com_detalhes' => 'usado_com_detalhes',
            default => 'usado',
        };
    }

    /**
     * @param mixed $value
     */
    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    /**
     * @param mixed $value
     */
    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param mixed $value
     */
    private function nullableString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeMetadata($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
