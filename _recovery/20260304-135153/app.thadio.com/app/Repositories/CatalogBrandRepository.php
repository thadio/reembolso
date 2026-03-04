<?php

namespace App\Repositories;

use PDO;
use App\Support\AuditableTrait;

/**
 * Repository for catalog_brands table (product brands/marcas).
 */
class CatalogBrandRepository
{
    use AuditableTrait;

    private PDO $pdo;
    private ?bool $hasProductsBrandColumn = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if (\function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    private function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS catalog_brands (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                slug VARCHAR(120) NULL,
                description TEXT NULL,
                status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_brand_slug (slug),
                KEY idx_brand_status (status),
                KEY idx_brand_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->pdo->exec($sql);
    }

    public function list(array $filters = [], mixed $arg2 = 'name', mixed $arg3 = 'ASC', mixed $arg4 = null, mixed $arg5 = null): array
    {
        [$sortKey, $sortDir, $limit, $offset] = $this->resolveListArguments($arg2, $arg3, $arg4, $arg5);
        $filters = $this->normalizeFilters($filters);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(b.id AS CHAR) LIKE :search OR b.name LIKE :search OR b.slug LIKE :search OR b.description LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['filter_id'])) {
            $where[] = 'CAST(b.id AS CHAR) LIKE :filter_id';
            $params[':filter_id'] = '%' . trim((string) $filters['filter_id']) . '%';
        }
        if (!empty($filters['filter_name'])) {
            $where[] = 'b.name LIKE :filter_name';
            $params[':filter_name'] = '%' . trim((string) $filters['filter_name']) . '%';
        }
        if (!empty($filters['filter_slug'])) {
            $where[] = 'b.slug LIKE :filter_slug';
            $params[':filter_slug'] = '%' . trim((string) $filters['filter_slug']) . '%';
        }
        if (!empty($filters['filter_product_count'])) {
            $where[] = $this->productCountFilterSql('b.id') . ' LIKE :filter_product_count';
            $params[':filter_product_count'] = '%' . trim((string) $filters['filter_product_count']) . '%';
        }

        $allowedSortKeys = ['id', 'name', 'slug', 'status', 'created_at', 'updated_at', 'product_count'];
        $sortKey = in_array($sortKey, $allowedSortKeys) ? $sortKey : 'name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $productCountSql = $this->productCountSelectSql('b.id');

        $sql = "
            SELECT 
                b.*,
                {$productCountSql}
            FROM catalog_brands b
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$sortKey} {$sortDir}
        ";

        if ($limit !== null) {
            $limit = max(0, (int) $limit);
            $offset = max(0, (int) $offset);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $filters = $this->normalizeFilters($filters);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(b.id AS CHAR) LIKE :search OR b.name LIKE :search OR b.slug LIKE :search OR b.description LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['filter_id'])) {
            $where[] = 'CAST(b.id AS CHAR) LIKE :filter_id';
            $params[':filter_id'] = '%' . trim((string) $filters['filter_id']) . '%';
        }
        if (!empty($filters['filter_name'])) {
            $where[] = 'b.name LIKE :filter_name';
            $params[':filter_name'] = '%' . trim((string) $filters['filter_name']) . '%';
        }
        if (!empty($filters['filter_slug'])) {
            $where[] = 'b.slug LIKE :filter_slug';
            $params[':filter_slug'] = '%' . trim((string) $filters['filter_slug']) . '%';
        }
        if (!empty($filters['filter_product_count'])) {
            $where[] = $this->productCountFilterSql('b.id') . ' LIKE :filter_product_count';
            $params[':filter_product_count'] = '%' . trim((string) $filters['filter_product_count']) . '%';
        }

        $sql = "SELECT COUNT(*) FROM catalog_brands b WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $productCountSql = $this->productCountSelectSql('b.id');
        $sql = "
            SELECT 
                b.*,
                {$productCountSql}
            FROM catalog_brands b
            WHERE b.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM catalog_brands WHERE slug = :slug LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findByName(string $name): ?object
    {
        $productCountSql = $this->productCountSelectSql('b.id');
        $sql = "SELECT b.*,
                       {$productCountSql}
                FROM catalog_brands b
                WHERE LOWER(b.name) = LOWER(:name)
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->toLegacyObject($result) : null;
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
        $rows = $this->list($filters, 'name', 'ASC');
        return array_map(fn(array $row): object => $this->toLegacyObject($row), $rows);
    }

    public function create(array $data): int
    {
        $normalized = $this->normalizePayload($data);
        return $this->insert($normalized);
    }

    public function save(array $data): int
    {
        $data = $this->normalizePayload($data);
        $id = $data['id'] ?? null;
        $isUpdate = (bool) $id;
        $oldData = null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM catalog_brands WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($id) {
            $result = $this->update($id, $data);
        } else {
            $result = $this->insert($data);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM catalog_brands WHERE id = :id");
        $stmt->execute([':id' => $result]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'catalog_brands',
            $result,
            $oldData,
            $newData
        );

        return $result;
    }

    private function insert(array $data): int
    {
        $sql = "
            INSERT INTO catalog_brands (
                name, slug, description, status, metadata
            ) VALUES (
                :name, :slug, :description, :status, :metadata
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'] ?? null,
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'ativa',
            ':metadata' => $this->encodeMetadata($data['metadata'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): int
    {
        $current = $this->find($id);
        if (!$current) {
            throw new \RuntimeException('Marca não encontrada para atualização.');
        }

        $merged = array_merge($current, $data);
        $merged = $this->normalizePayload($merged);

        $sql = "
            UPDATE catalog_brands SET
                name = :name,
                slug = :slug,
                description = :description,
                status = :status,
                metadata = :metadata
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':name' => $merged['name'],
            ':slug' => $merged['slug'] ?? null,
            ':description' => $merged['description'] ?? null,
            ':status' => $merged['status'] ?? 'ativa',
            ':metadata' => $this->encodeMetadata($merged['metadata'] ?? null),
        ]);

        return $id;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $status = $this->normalizeStatusToStorage($status);
        $sql = "UPDATE catalog_brands SET status = :status WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    public function delete(int $id): bool
    {
        return $this->updateStatus($id, 'inativa');
    }

    /**
     * @return array{0: string, 1: string, 2: ?int, 3: int}
     */
    private function resolveListArguments(mixed $arg2, mixed $arg3, mixed $arg4, mixed $arg5): array
    {
        if (is_int($arg2)) {
            $limit = $arg2;
            $offset = is_int($arg3) ? $arg3 : 0;
            $sortKey = is_string($arg4) ? $arg4 : 'name';
            $sortDir = is_string($arg5) ? $arg5 : 'ASC';
            return [$sortKey, $sortDir, $limit, $offset];
        }

        $sortKey = is_string($arg2) ? $arg2 : 'name';
        $sortDir = is_string($arg3) ? $arg3 : 'ASC';
        $limit = is_int($arg4) ? $arg4 : null;
        $offset = is_int($arg5) ? $arg5 : 0;

        return [$sortKey, $sortDir, $limit, $offset];
    }

    private function normalizeFilters(array $filters): array
    {
        if (!empty($filters['only_active'])) {
            $filters['status'] = 'ativa';
        }

        if (!empty($filters['status'])) {
            $filters['status'] = $this->normalizeStatusToStorage((string) $filters['status']);
        }

        unset($filters['only_active']);
        return $filters;
    }

    private function normalizeStatusToStorage(string $status): string
    {
        $value = strtolower(trim($status));
        if ($value === 'ativa' || $value === 'ativo' || $value === 'active') {
            return 'ativa';
        }
        return 'inativa';
    }

    private function normalizeStatusFromStorage(string $status): string
    {
        return strtolower(trim($status)) === 'ativa' ? 'active' : 'inactive';
    }

    private function normalizePayload(array $data): array
    {
        $normalized = $data;
        $metadata = $this->decodeMetadata($data['metadata'] ?? null);

        foreach (['logo_url', 'website', 'meta_fields'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === '' || $value === null) {
                unset($metadata[$field]);
                continue;
            }
            $metadata[$field] = $value;
        }

        $normalized['metadata'] = $metadata;
        if (isset($normalized['status'])) {
            $normalized['status'] = $this->normalizeStatusToStorage((string) $normalized['status']);
        }

        return $normalized;
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function encodeMetadata(mixed $metadata): ?string
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }
        if (is_string($metadata)) {
            return $metadata;
        }
        if (is_array($metadata)) {
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? null : $encoded;
        }
        return null;
    }

    private function toLegacyObject(array $row): object
    {
        $metadata = $this->decodeMetadata($row['metadata'] ?? null);

        return (object) [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'status' => $this->normalizeStatusFromStorage((string) ($row['status'] ?? 'ativa')),
            'logo_url' => isset($metadata['logo_url']) ? (string) $metadata['logo_url'] : '',
            'website' => isset($metadata['website']) ? (string) $metadata['website'] : '',
            'product_count' => (int) ($row['product_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function productCountSelectSql(string $brandAlias): string
    {
        if ($this->hasProductsBrandColumn()) {
            return "(SELECT COUNT(*) FROM products WHERE brand_id = {$brandAlias}) AS product_count";
        }

        return "0 AS product_count";
    }

    private function productCountFilterSql(string $brandAlias): string
    {
        if ($this->hasProductsBrandColumn()) {
            return "CAST((SELECT COUNT(*) FROM products WHERE brand_id = {$brandAlias}) AS CHAR)";
        }

        return "CAST(0 AS CHAR)";
    }

    private function hasProductsBrandColumn(): bool
    {
        if ($this->hasProductsBrandColumn !== null) {
            return $this->hasProductsBrandColumn;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'products'
                  AND column_name = 'brand_id'
            ");
            $stmt->execute();
            $this->hasProductsBrandColumn = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            $this->hasProductsBrandColumn = false;
        }

        return $this->hasProductsBrandColumn;
    }
}
