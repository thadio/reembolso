<?php

namespace App\Repositories;

use App\Models\Product;
use App\Support\AuditableTrait;
use PDO;

/**
 * Compatibility adapter for legacy catalog product flows.
 *
 * Source of truth is always `products`.
 */
class CatalogProductRepository
{
    use AuditableTrait;

    private PDO $pdo;
    private ProductRepository $products;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->products = new ProductRepository($pdo);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0, string $sortKey = 'created_at', string $sortDir = 'DESC'): array
    {
        $rows = $this->products->list(
            $this->toProductFilters($filters),
            $limit,
            $offset,
            $this->mapSortKey($sortKey),
            $sortDir
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->toCatalogRow($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        return $this->products->count($this->toProductFilters($filters));
    }

    public function find(int $id): ?array
    {
        $row = $this->products->findAsArray($id);
        return $row ? $this->toCatalogRow($row) : null;
    }

    public function findBySku(string $sku): ?array
    {
        $value = trim($sku);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return $this->find((int) $value);
    }

    public function findById(int $id): ?object
    {
        $row = $this->find($id);
        return $row ? $this->toLegacyObject($row) : null;
    }

    /**
     * @return array<int, object>
     */
    public function findAll(array $filters = []): array
    {
        $rows = $this->list($filters, 1000, 0, 'created_at', 'DESC');
        return array_map(fn (array $row): object => $this->toLegacyObject($row), $rows);
    }

    public function create(array $data): int
    {
        return $this->save($data);
    }

    public function save(array $data): int
    {
        $existing = null;
        $sku = 0;
        if (isset($data['id']) && (int) $data['id'] > 0) {
            $sku = (int) $data['id'];
            $existing = $this->products->find($sku);
        } elseif (isset($data['sku']) && ctype_digit((string) $data['sku'])) {
            $sku = (int) $data['sku'];
            $existing = $this->products->find($sku);
        }

        $payload = [];
        if ($existing) {
            $payload = $existing->toArray();
        }

        $payload['sku'] = $sku > 0 ? $sku : $this->products->nextSku();
        $payload['name'] = trim((string) ($data['name'] ?? ($payload['name'] ?? '')));
        $payload['slug'] = $this->nullableString($data['slug'] ?? ($payload['slug'] ?? null));
        $payload['short_description'] = $this->nullableString($data['short_description'] ?? ($payload['short_description'] ?? null));
        $payload['description'] = $this->nullableString($data['description'] ?? ($payload['description'] ?? null));
        $payload['brand_id'] = $this->nullableInt($data['brand_id'] ?? ($payload['brand_id'] ?? null));
        $payload['category_id'] = $this->nullableInt($data['category_id'] ?? ($payload['category_id'] ?? null));
        $payload['price'] = $this->nullableFloat($data['price'] ?? ($payload['price'] ?? null));
        $payload['cost'] = $this->nullableFloat($data['cost'] ?? ($payload['cost'] ?? null));
        $payload['suggested_price'] = $this->nullableFloat($data['suggested_price'] ?? ($payload['suggested_price'] ?? null));
        $payload['profit_margin'] = $this->nullableFloat($data['margin'] ?? ($payload['profit_margin'] ?? null));
        $payload['weight'] = $this->nullableFloat($data['weight'] ?? ($payload['weight'] ?? null));
        $payload['visibility'] = $this->normalizeVisibility((string) ($data['visibility'] ?? ($payload['visibility'] ?? 'public')));

        $quantity = $this->nullableInt($data['quantity'] ?? ($payload['quantity'] ?? null));
        if ($quantity === null) {
            $quantity = (int) ($payload['quantity'] ?? 1);
        }
        $payload['quantity'] = max(0, (int) $quantity);

        $statusInput = (string) ($data['status'] ?? ($payload['status_unified'] ?? ($payload['status'] ?? 'draft')));
        $payload['status'] = $this->toUnifiedStatus($statusInput, $payload['quantity']);

        $metadata = $this->decodeMetadata($data['metadata'] ?? ($payload['metadata'] ?? null));
        $metadata = $this->applyLegacyMetadataFields($metadata, $data);
        $payload['metadata'] = $metadata;

        $product = Product::fromArray($payload);
        $saved = $this->products->save($product);

        return (int) $saved->sku;
    }

    public function update(int $id, array $data): int
    {
        $data['id'] = $id;
        return $this->save($data);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $product = $this->products->find($id);
        if (!$product) {
            return false;
        }

        $product->status = $this->toUnifiedStatus($status, (int) $product->quantity);
        $this->products->save($product);

        return true;
    }

    public function delete(int $id): bool
    {
        $product = $this->products->find($id);
        if (!$product) {
            return false;
        }

        $product->status = 'archived';
        $this->products->save($product);

        return true;
    }

    public function nextSku(string $prefix = 'PRD'): string
    {
        return (string) $this->products->nextSku();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Backward-compatibility hook used by legacy tests.
     * Source of truth remains `products`.
     */
    private function ensureTable(): void
    {
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
     * @return array<string, mixed>
     */
    private function toProductFilters(array $filters): array
    {
        $normalized = [];

        if (!empty($filters['search'])) {
            $normalized['search'] = trim((string) $filters['search']);
        }

        $columnMap = [
            'sku' => 'sku',
            'name' => 'name',
            'price' => 'price',
            'quantity' => 'quantity',
            'visibility' => 'visibility',
            'supplier' => 'supplier',
            'source' => 'source',
            'brand' => 'brand',
            'category' => 'category',
        ];
        foreach ($columnMap as $sourceKey => $targetKey) {
            $raw = '';
            if (isset($filters[$sourceKey])) {
                $raw = trim((string) $filters[$sourceKey]);
            }
            if ($raw === '' && isset($filters['filter_' . $sourceKey])) {
                $raw = trim((string) $filters['filter_' . $sourceKey]);
            }
            if ($raw !== '') {
                $normalized[$targetKey] = $raw;
            }
        }

        if (!empty($filters['brand_id'])) {
            $normalized['brand_id'] = (int) $filters['brand_id'];
        }

        if (!empty($filters['category_id'])) {
            $normalized['category_id'] = (int) $filters['category_id'];
        }

        $statusValue = $filters['status'] ?? ($filters['filter_status'] ?? null);
        if ($statusValue !== null && $statusValue !== '') {
            if (is_array($statusValue)) {
                $statuses = [];
                foreach ($statusValue as $status) {
                    $mapped = $this->toUnifiedStatus((string) $status, 1);
                    if ($mapped !== '') {
                        $statuses[] = $mapped;
                    }
                }
                if (!empty($statuses)) {
                    $normalized['status'] = $statuses;
                }
            } else {
                $statusText = trim((string) $statusValue);
                if (strpos($statusText, ',') !== false) {
                    $statuses = [];
                    foreach (explode(',', $statusText) as $statusPart) {
                        $mapped = $this->toUnifiedStatus((string) $statusPart, 1);
                        if ($mapped !== '') {
                            $statuses[] = $mapped;
                        }
                    }
                    if (!empty($statuses)) {
                        $normalized['status'] = $statuses;
                    }
                } else {
                    $normalized['status'] = $this->toUnifiedStatus($statusText, 1);
                }
            }
        }

        if (!empty($filters['only_active'])) {
            $normalized['status'] = 'disponivel';
            $normalized['stock_positive'] = true;
        }

        return $normalized;
    }

    private function mapSortKey(string $sortKey): string
    {
        return match ($sortKey) {
            'id' => 'id',
            'sku' => 'sku',
            'name' => 'name',
            'brand_id' => 'brand_id',
            'category_id' => 'category_id',
            'price' => 'price',
            'quantity' => 'quantity',
            'status' => 'status',
            'visibility' => 'visibility',
            'source' => 'source',
            'supplier_pessoa_id' => 'supplier_pessoa_id',
            'updated_at' => 'updated_at',
            default => 'created_at',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toCatalogRow(array $row): array
    {
        $unifiedStatus = (string) ($row['status_unified'] ?? ($row['status'] ?? 'draft'));
        $metadata = $this->decodeMetadata($row['metadata'] ?? null);

        $brandId = $this->nullableInt($row['brand_id'] ?? null);
        $categoryId = $this->nullableInt($row['category_id'] ?? null);
        $supplierPessoaId = $this->nullableInt($row['supplier_pessoa_id'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['sku'] ?? ''),
            'slug' => $row['slug'] ?? null,
            'name' => (string) ($row['name'] ?? ''),
            'short_description' => $row['short_description'] ?? null,
            'description' => $row['description'] ?? null,
            'brand_id' => $brandId,
            'brand_name' => $this->lookupName('catalog_brands', $brandId),
            'category_id' => $categoryId,
            'category_name' => $this->lookupName('catalog_categories', $categoryId),
            'price' => $this->nullableFloat($row['price'] ?? null),
            'cost' => $this->nullableFloat($row['cost'] ?? null),
            'suggested_price' => $this->nullableFloat($row['suggested_price'] ?? null),
            'margin' => $this->nullableFloat($row['profit_margin'] ?? ($row['margin'] ?? null)),
            'status' => $unifiedStatus,
            'visibility' => $this->normalizeVisibility((string) ($row['visibility'] ?? 'public')),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'weight' => $this->nullableFloat($row['weight'] ?? null),
            'source' => (string) ($row['source'] ?? ''),
            'supplier_pessoa_id' => $supplierPessoaId,
            'supplier_name' => $this->lookupSupplierName($supplierPessoaId),
            'image_src' => (string) ($row['image_src'] ?? ''),
            'metadata' => $metadata,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toLegacyObject(array $row): object
    {
        $metadata = $this->decodeMetadata($row['metadata'] ?? null);

        return (object) [
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['sku'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'short_description' => (string) ($row['short_description'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
            'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            'brand_name' => $row['brand_name'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : null,
            'sale_price' => isset($metadata['sale_price']) ? (float) $metadata['sale_price'] : null,
            'cost' => isset($row['cost']) ? (float) $row['cost'] : null,
            'suggested_price' => isset($row['suggested_price']) ? (float) $row['suggested_price'] : null,
            'margin' => isset($row['margin']) ? (float) $row['margin'] : null,
            'type' => (string) ($metadata['type'] ?? 'simple'),
            'status' => (string) ($row['status'] ?? 'draft'),
            'visibility' => (string) ($row['visibility'] ?? 'public'),
            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : 0,
            'manage_stock' => isset($metadata['manage_stock']) ? (bool) $metadata['manage_stock'] : false,
            'featured' => isset($metadata['featured']) ? (bool) $metadata['featured'] : false,
            'length' => $metadata['length'] ?? null,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'weight' => isset($row['weight']) ? (float) $row['weight'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyLegacyMetadataFields(array $metadata, array $data): array
    {
        if (array_key_exists('type', $data)) {
            $type = trim((string) $data['type']);
            if ($type !== '') {
                $metadata['type'] = $type;
            }
        }

        $salePrice = $this->nullableFloat($data['sale_price'] ?? null);
        if ($salePrice !== null) {
            $metadata['sale_price'] = $salePrice;
        }

        if (array_key_exists('manage_stock', $data)) {
            $metadata['manage_stock'] = (bool) $data['manage_stock'];
        }

        if (array_key_exists('featured', $data)) {
            $metadata['featured'] = (bool) $data['featured'];
        }

        foreach (['length', 'width', 'height'] as $dimensionField) {
            $value = $this->nullableFloat($data[$dimensionField] ?? null);
            if ($value !== null) {
                $metadata[$dimensionField] = $value;
            }
        }

        return $metadata;
    }

    private function toUnifiedStatus(string $status, int $quantity): string
    {
        $value = strtolower(trim($status));

        return match ($value) {
            'active', 'publish', 'disponivel' => $quantity > 0 ? 'disponivel' : 'esgotado',
            'reservado' => 'reservado',
            'esgotado', 'outofstock', 'vendido', 'sold' => 'esgotado',
            'trash', 'archived', 'inactive' => 'archived',
            default => 'draft',
        };
    }

    private function normalizeVisibility(string $visibility): string
    {
        $value = strtolower(trim($visibility));
        return in_array($value, ['public', 'catalog', 'search', 'hidden'], true) ? $value : 'public';
    }

    /**
     * @param mixed $metadata
     * @return array<string, mixed>
     */
    private function decodeMetadata($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function lookupSupplierName(?int $pessoaId): ?string
    {
        if ($pessoaId === null || $pessoaId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT full_name FROM pessoas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $pessoaId]);
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }

    private function lookupName(string $table, ?int $id): ?string
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT name FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
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
}
