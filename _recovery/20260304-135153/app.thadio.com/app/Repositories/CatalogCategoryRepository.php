<?php

namespace App\Repositories;

use PDO;
use App\Support\AuditableTrait;

/**
 * Repository for catalog_categories table (product categories/collections).
 */
class CatalogCategoryRepository
{
    use AuditableTrait;

    private PDO $pdo;
    private ?bool $hasProductsCategoryColumn = null;

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
            CREATE TABLE IF NOT EXISTS catalog_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                slug VARCHAR(120) NULL,
                description TEXT NULL,
                parent_id BIGINT UNSIGNED NULL,
                position INT DEFAULT 0,
                status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_category_slug (slug),
                KEY idx_category_status (status),
                KEY idx_category_parent (parent_id),
                KEY idx_category_position (position),
                CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->pdo->exec($sql);
    }

    public function list(array $filters = [], mixed $arg2 = 'position', mixed $arg3 = 'ASC', mixed $arg4 = null, mixed $arg5 = null): array
    {
        [$sortKey, $sortDir, $limit, $offset] = $this->resolveListArguments($arg2, $arg3, $arg4, $arg5);
        $filters = $this->normalizeFilters($filters);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(c.id AS CHAR) LIKE :search OR c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search OR p.name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (array_key_exists('parent_id', $filters)) {
            if ($filters['parent_id'] === null || $filters['parent_id'] === '') {
                $where[] = 'c.parent_id IS NULL';
            } else {
                $where[] = 'c.parent_id = :parent_id';
                $params[':parent_id'] = $filters['parent_id'];
            }
        }

        if (!empty($filters['filter_id'])) {
            $where[] = 'CAST(c.id AS CHAR) LIKE :filter_id';
            $params[':filter_id'] = '%' . trim((string) $filters['filter_id']) . '%';
        }
        if (!empty($filters['filter_name'])) {
            $where[] = 'c.name LIKE :filter_name';
            $params[':filter_name'] = '%' . trim((string) $filters['filter_name']) . '%';
        }
        if (!empty($filters['filter_slug'])) {
            $where[] = 'c.slug LIKE :filter_slug';
            $params[':filter_slug'] = '%' . trim((string) $filters['filter_slug']) . '%';
        }
        if (!empty($filters['filter_parent_name'])) {
            $where[] = 'p.name LIKE :filter_parent_name';
            $params[':filter_parent_name'] = '%' . trim((string) $filters['filter_parent_name']) . '%';
        }
        if (!empty($filters['filter_status'])) {
            $where[] = 'c.status LIKE :filter_status';
            $params[':filter_status'] = '%' . trim((string) $filters['filter_status']) . '%';
        }
        if (!empty($filters['filter_product_count'])) {
            $where[] = $this->productCountFilterSql('c.id') . ' LIKE :filter_product_count';
            $params[':filter_product_count'] = '%' . trim((string) $filters['filter_product_count']) . '%';
        }

        $allowedSortKeys = ['id', 'name', 'slug', 'position', 'status', 'parent_name', 'product_count', 'created_at', 'updated_at'];
        $sortKey = in_array($sortKey, $allowedSortKeys) ? $sortKey : 'position';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $productCountSql = $this->productCountSelectSql('c.id');
        $sortSql = match ($sortKey) {
            'parent_name' => 'parent_name',
            'product_count' => 'product_count',
            default => 'c.' . $sortKey,
        };

        $sql = "
            SELECT 
                c.*,
                p.name as parent_name,
                {$productCountSql}
            FROM catalog_categories c
            LEFT JOIN catalog_categories p ON c.parent_id = p.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$sortSql} {$sortDir}
        ";

        if ($limit !== null) {
            $limit = max(1, (int) $limit);
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
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(CAST(c.id AS CHAR) LIKE :search OR c.name LIKE :search OR c.slug LIKE :search OR c.description LIKE :search OR p.name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (array_key_exists('parent_id', $filters)) {
            if ($filters['parent_id'] === null || $filters['parent_id'] === '') {
                $where[] = 'c.parent_id IS NULL';
            } else {
                $where[] = 'c.parent_id = :parent_id';
                $params[':parent_id'] = $filters['parent_id'];
            }
        }

        if (!empty($filters['filter_id'])) {
            $where[] = 'CAST(c.id AS CHAR) LIKE :filter_id';
            $params[':filter_id'] = '%' . trim((string) $filters['filter_id']) . '%';
        }
        if (!empty($filters['filter_name'])) {
            $where[] = 'c.name LIKE :filter_name';
            $params[':filter_name'] = '%' . trim((string) $filters['filter_name']) . '%';
        }
        if (!empty($filters['filter_slug'])) {
            $where[] = 'c.slug LIKE :filter_slug';
            $params[':filter_slug'] = '%' . trim((string) $filters['filter_slug']) . '%';
        }
        if (!empty($filters['filter_parent_name'])) {
            $where[] = 'p.name LIKE :filter_parent_name';
            $params[':filter_parent_name'] = '%' . trim((string) $filters['filter_parent_name']) . '%';
        }
        if (!empty($filters['filter_status'])) {
            $where[] = 'c.status LIKE :filter_status';
            $params[':filter_status'] = '%' . trim((string) $filters['filter_status']) . '%';
        }
        if (!empty($filters['filter_product_count'])) {
            $where[] = $this->productCountFilterSql('c.id') . ' LIKE :filter_product_count';
            $params[':filter_product_count'] = '%' . trim((string) $filters['filter_product_count']) . '%';
        }

        $sql = "SELECT COUNT(*) FROM catalog_categories c LEFT JOIN catalog_categories p ON c.parent_id = p.id WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $productCountSql = $this->productCountSelectSql('c.id');
        $sql = "
            SELECT 
                c.*,
                p.name as parent_name,
                {$productCountSql}
            FROM catalog_categories c
            LEFT JOIN catalog_categories p ON c.parent_id = p.id
            WHERE c.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM catalog_categories WHERE slug = :slug LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
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
        $rows = $this->list($filters, 'position', 'ASC');
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
            $stmt = $this->pdo->prepare("SELECT * FROM catalog_categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($id) {
            $result = $this->update($id, $data);
        } else {
            $result = $this->insert($data);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM catalog_categories WHERE id = :id");
        $stmt->execute([':id' => $result]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'catalog_categories',
            $result,
            $oldData,
            $newData
        );

        return $result;
    }

    private function insert(array $data): int
    {
        $sql = "
            INSERT INTO catalog_categories (
                name, slug, description, parent_id, position, status, metadata
            ) VALUES (
                :name, :slug, :description, :parent_id, :position, :status, :metadata
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'] ?? null,
            ':description' => $data['description'] ?? null,
            ':parent_id' => $data['parent_id'] ?? null,
            ':position' => $data['position'] ?? 0,
            ':status' => $data['status'] ?? 'ativa',
            ':metadata' => $this->encodeMetadata($data['metadata'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): int
    {
        $current = $this->find($id);
        if (!$current) {
            throw new \RuntimeException('Categoria não encontrada para atualização.');
        }

        $merged = array_merge($current, $data);
        $merged = $this->normalizePayload($merged);

        $sql = "
            UPDATE catalog_categories SET
                name = :name,
                slug = :slug,
                description = :description,
                parent_id = :parent_id,
                position = :position,
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
            ':parent_id' => $merged['parent_id'] ?? null,
            ':position' => $merged['position'] ?? 0,
            ':status' => $merged['status'] ?? 'ativa',
            ':metadata' => $this->encodeMetadata($merged['metadata'] ?? null),
        ]);

        return $id;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $status = $this->normalizeStatusToStorage($status);
        $sql = "UPDATE catalog_categories SET status = :status WHERE id = :id";
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
            $sortKey = is_string($arg4) ? $arg4 : 'position';
            $sortDir = is_string($arg5) ? $arg5 : 'ASC';
            return [$sortKey, $sortDir, $limit, $offset];
        }

        $sortKey = is_string($arg2) ? $arg2 : 'position';
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

        foreach (['display_order', 'image_url', 'meta_fields'] as $field) {
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

        if (array_key_exists('display_order', $data) && $data['display_order'] !== '' && $data['display_order'] !== null) {
            $normalized['position'] = (int) $data['display_order'];
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
            'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            'parent_name' => $row['parent_name'] ?? null,
            'display_order' => isset($row['position']) ? (int) $row['position'] : (int) ($metadata['display_order'] ?? 0),
            'position' => isset($row['position']) ? (int) $row['position'] : 0,
            'status' => $this->normalizeStatusFromStorage((string) ($row['status'] ?? 'ativa')),
            'image_url' => isset($metadata['image_url']) ? (string) $metadata['image_url'] : '',
            'product_count' => (int) ($row['product_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function productCountSelectSql(string $categoryAlias): string
    {
        if ($this->hasProductsCategoryColumn()) {
            return "(SELECT COUNT(*) FROM products WHERE category_id = {$categoryAlias}) AS product_count";
        }

        return "0 AS product_count";
    }

    private function productCountFilterSql(string $categoryAlias): string
    {
        if ($this->hasProductsCategoryColumn()) {
            return "CAST((SELECT COUNT(*) FROM products WHERE category_id = {$categoryAlias}) AS CHAR)";
        }

        return "CAST(0 AS CHAR)";
    }

    private function hasProductsCategoryColumn(): bool
    {
        if ($this->hasProductsCategoryColumn !== null) {
            return $this->hasProductsCategoryColumn;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'products'
                  AND column_name = 'category_id'
            ");
            $stmt->execute();
            $this->hasProductsCategoryColumn = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            $this->hasProductsCategoryColumn = false;
        }

        return $this->hasProductsCategoryColumn;
    }
}
