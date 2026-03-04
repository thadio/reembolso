<?php

namespace App\Repositories;

use App\Models\Product;
use App\Support\AuditableTrait;
use PDO;

class ProductRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ?InventoryMovementRepository $movementRepo = null;

    private const STATUS_UNIFIED = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];

    /** @var array<string, string> */
    private const LEGACY_TO_UNIFIED = [
        'publish' => 'disponivel',
        'active' => 'disponivel',
        'instock' => 'disponivel',
        'draft' => 'draft',
        'pending' => 'draft',
        'private' => 'archived',
        'reservado' => 'reservado',
        'reserved' => 'reservado',
        'outofstock' => 'esgotado',
        'sold' => 'esgotado',
        'vendido' => 'esgotado',
        'esgotado' => 'esgotado',
        'trash' => 'archived',
        'archived' => 'archived',
        'inactive' => 'archived',
        'baixado' => 'baixado',
    ];

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->movementRepo = new InventoryMovementRepository($pdo);

        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
                $this->ensureColumnsAndIndexes();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela products: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function find(int $sku): ?Product
    {
        if (!$this->pdo || $sku <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Product::fromArray($row) : null;
    }

    public function findById(int $id): ?Product
    {
        return $this->find($id);
    }

    /**
     * @param int|string $sku
     */
    public function findBySku($sku): ?Product
    {
        $value = trim((string) $sku);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return $this->find((int) $value);
    }

    public function findAsArray(int $sku): ?array
    {
        $product = $this->find($sku);
        return $product ? $this->productToLegacyRow($product) : null;
    }

    /**
     * @param array<int, int|string> $skus
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $skus): array
    {
        if (!$this->pdo || empty($skus)) {
            return [];
        }

        $normalized = [];
        foreach ($skus as $sku) {
            $value = (int) $sku;
            if ($value > 0) {
                $normalized[$value] = $value;
            }
        }

        if (empty($normalized)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $sql = "SELECT * FROM products WHERE sku IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($normalized));

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $this->productToLegacyRow(Product::fromArray($row));
        }

        return $rows;
    }

    public function nextSku(int $base = 19000): int
    {
        if (!$this->pdo) {
            return $base;
        }

        $max = $this->maxNumericSku();

        if ($max < $base) {
            return $base;
        }

        return $max + 1;
    }

    public function maxNumericSku(): int
    {
        if (!$this->pdo) {
            return 0;
        }

        // Limita a busca a SKUs dentro do range operacional (< 900000000)
        // para evitar que SKUs sintéticos/legado de migração distorçam o próximo SKU.
        $stmt = $this->pdo->query('SELECT MAX(sku) AS max_sku FROM products WHERE sku < 900000000');
        $max = (int) ($stmt ? ($stmt->fetchColumn() ?: 0) : 0);

        return $max;
    }

    /**
     * @param int|string $sku
     */
    public function skuExists($sku, ?int $excludeId = null): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $value = (int) $sku;
        if ($value <= 0) {
            return false;
        }

        $sql = 'SELECT sku FROM products WHERE sku = :sku';
        $params = [':sku' => $value];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND sku <> :exclude';
            $params[':exclude'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function save(Product $product): Product
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        if ($product->sku <= 0) {
            $product->sku = $this->nextSku();
        }
        $product->normalizeForPersistence();

        $oldValues = $product->sku > 0 ? $this->findAsArray($product->sku) : null;
        $action = $oldValues ? 'UPDATE' : 'INSERT';

        $values = $this->buildPersistValues($product);
        $availableColumns = $this->existingColumns('products');
        if (!isset($availableColumns['sku'])) {
            throw new \RuntimeException('Tabela products sem coluna sku.');
        }

        $persisted = [];
        foreach ($values as $column => $value) {
            if (isset($availableColumns[$column])) {
                $persisted[$column] = $value;
            }
        }

        if (empty($persisted)) {
            throw new \RuntimeException('Nenhuma coluna persistível disponível em products.');
        }

        if (array_key_exists('slug', $persisted)) {
            $persisted['slug'] = $this->ensureUniqueSlug($this->nullableString($persisted['slug']), $product->sku);
        }

        if ($oldValues) {
            $set = [];
            $params = [':sku' => $product->sku];
            foreach ($persisted as $column => $value) {
                if ($column === 'sku') {
                    continue;
                }
                $placeholder = ':' . $column;
                $set[] = "{$column} = {$placeholder}";
                $params[$placeholder] = $value;
            }

            if (!empty($set)) {
                $sql = 'UPDATE products SET ' . implode(', ', $set) . ' WHERE sku = :sku';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }
        } else {
            $columns = array_keys($persisted);
            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
            $params = [];
            foreach ($persisted as $column => $value) {
                $params[':' . $column] = $value;
            }

            $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        $saved = $this->find($product->sku) ?? $product;
        $this->auditLog($action, 'products', $product->sku, $oldValues, $saved->toArray());

        return $saved;
    }

    public function delete(int $sku): void
    {
        if (!$this->pdo || $sku <= 0) {
            return;
        }

        $oldValues = $this->findAsArray($sku);
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE sku = :sku');
        $stmt->execute([':sku' => $sku]);
        $this->auditLog('DELETE', 'products', $sku, $oldValues, null);
    }

    public function permanentDelete(int $id): bool
    {
        if (!$this->pdo || $id <= 0) {
            return false;
        }

        $oldValues = $this->findAsArray($id);
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE sku = :sku');
        $ok = $stmt->execute([':sku' => $id]);
        if ($ok) {
            $this->auditLog('DELETE', 'products', $id, $oldValues, null);
        }

        return $ok;
    }

    public function trash(int $id, ?string $deletedAt = null, ?int $deletedBy = null): bool
    {
        if (!$this->pdo || $id <= 0) {
            return false;
        }

        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        $metadata = $product->metadata;
        $metadata['deleted_at'] = $deletedAt ?? date('Y-m-d H:i:s');
        if ($deletedBy !== null && $deletedBy > 0) {
            $metadata['deleted_by'] = $deletedBy;
        }

        $product->status = 'archived';
        $product->metadata = $metadata;
        $this->save($product);

        return true;
    }

    public function restore(int $id, string $restoreStatus = 'draft'): bool
    {
        if (!$this->pdo || $id <= 0) {
            return false;
        }

        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        $status = $this->normalizeStatusInput($restoreStatus, $product->quantity);
        if ($status === 'archived') {
            $status = $product->quantity > 0 ? 'disponivel' : 'draft';
        }

        $metadata = $product->metadata;
        unset($metadata['deleted_at'], $metadata['deleted_by']);
        $product->metadata = $metadata;
        $product->status = $status;
        $this->save($product);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if (!$this->pdo || $id <= 0) {
            return false;
        }

        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $merged = array_merge($existing->toArray(), $data);
        $product = Product::fromArray($merged);
        $this->save($product);

        return true;
    }

    public function updateQuantity(
        int $sku,
        int $newQuantity,
        ?int $orderId = null,
        string $movementType = 'ajuste',
        ?string $notes = null
    ): bool {
        if (!$this->pdo || $sku <= 0) {
            return false;
        }

        $newQuantity = max(0, $newQuantity);
        $startedTransaction = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $current = $this->lockStockState($sku);
            if ($current === null) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $oldQuantity = (int) ($current['quantity'] ?? 0);
            $oldStatus = (string) ($current['status'] ?? 'draft');
            $nextStatus = $this->resolveStatusAfterQuantity($oldStatus, $newQuantity, $movementType);

            $stmt = $this->pdo->prepare(
                'UPDATE products SET
                    quantity = :quantity,
                    status = :status,
                    last_order_id = CASE WHEN :order_id IS NULL THEN last_order_id ELSE :order_id END,
                    last_sold_at = CASE WHEN :sold_flag = 1 THEN NOW() ELSE last_sold_at END
                 WHERE sku = :sku'
            );
            $ok = $stmt->execute([
                ':sku' => $sku,
                ':quantity' => $newQuantity,
                ':status' => $nextStatus,
                ':order_id' => $orderId,
                ':sold_flag' => ($movementType === 'venda' && $newQuantity < $oldQuantity) ? 1 : 0,
            ]);

            if (!$ok) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            if ($oldQuantity !== $newQuantity && $this->movementRepo) {
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $this->movementRepo->log(
                    $sku,
                    $this->normalizeMovementType($movementType),
                    $oldQuantity,
                    $newQuantity,
                    $orderId,
                    $userId,
                    $notes
                );
            }

            $newValues = $this->findAsArray($sku);
            $this->auditLog('UPDATE', 'products', $sku, [
                'quantity' => $oldQuantity,
                'status' => $oldStatus,
            ], $newValues);

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erro ao atualizar quantidade do produto SKU ' . $sku . ': ' . $e->getMessage());
            return false;
        }
    }

    public function decrementQuantity(int $sku, int $quantity = 1, ?int $orderId = null, ?string $notes = null): bool
    {
        if (!$this->pdo || $sku <= 0 || $quantity <= 0) {
            return false;
        }

        $startedTransaction = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $current = $this->lockStockState($sku);
            if ($current === null) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $oldQuantity = (int) ($current['quantity'] ?? 0);
            $oldStatus = (string) ($current['status'] ?? 'draft');
            if (!$this->isOrderSaleableStockStatus($oldStatus) || $oldQuantity < $quantity) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $nextQty = $oldQuantity - $quantity;
            $nextStatus = $this->resolveStatusAfterQuantity($oldStatus, $nextQty, 'venda');

            $stmt = $this->pdo->prepare(
                'UPDATE products SET
                    quantity = :quantity,
                    status = :status,
                    last_order_id = CASE WHEN :order_id IS NULL THEN last_order_id ELSE :order_id END,
                    last_sold_at = NOW()
                 WHERE sku = :sku'
            );
            $ok = $stmt->execute([
                ':sku' => $sku,
                ':quantity' => $nextQty,
                ':status' => $nextStatus,
                ':order_id' => $orderId,
            ]);

            if (!$ok) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            if ($oldQuantity !== $nextQty && $this->movementRepo) {
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $this->movementRepo->log(
                    $sku,
                    'venda',
                    $oldQuantity,
                    $nextQty,
                    $orderId,
                    $userId,
                    $notes
                );
            }

            $newValues = $this->findAsArray($sku);
            $this->auditLog('UPDATE', 'products', $sku, [
                'quantity' => $oldQuantity,
                'status' => $oldStatus,
            ], $newValues);

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erro ao debitar quantidade do produto SKU ' . $sku . ': ' . $e->getMessage());
            return false;
        }
    }

    public function incrementQuantity(int $sku, int $quantity = 1, string $movementType = 'ajuste', ?string $notes = null): bool
    {
        if (!$this->pdo || $sku <= 0 || $quantity <= 0) {
            return false;
        }

        $startedTransaction = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $current = $this->lockStockState($sku);
            if ($current === null) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $oldQuantity = (int) ($current['quantity'] ?? 0);
            $oldStatus = (string) ($current['status'] ?? 'draft');
            $nextQty = $oldQuantity + $quantity;
            $nextStatus = $this->resolveStatusAfterQuantity($oldStatus, $nextQty, $movementType);

            $stmt = $this->pdo->prepare(
                'UPDATE products SET
                    quantity = :quantity,
                    status = :status,
                    last_order_id = CASE WHEN :order_id IS NULL THEN last_order_id ELSE :order_id END
                 WHERE sku = :sku'
            );
            $ok = $stmt->execute([
                ':sku' => $sku,
                ':quantity' => $nextQty,
                ':status' => $nextStatus,
                ':order_id' => null,
            ]);

            if (!$ok) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            if ($oldQuantity !== $nextQty && $this->movementRepo) {
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $this->movementRepo->log(
                    $sku,
                    $this->normalizeMovementType($movementType),
                    $oldQuantity,
                    $nextQty,
                    null,
                    $userId,
                    $notes
                );
            }

            $newValues = $this->findAsArray($sku);
            $this->auditLog('UPDATE', 'products', $sku, [
                'quantity' => $oldQuantity,
                'status' => $oldStatus,
            ], $newValues);

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erro ao incrementar quantidade do produto SKU ' . $sku . ': ' . $e->getMessage());
            return false;
        }
    }

    public function isAvailable(int $sku, int $quantity = 1): bool
    {
        if (!$this->pdo || $sku <= 0 || $quantity <= 0) {
            return false;
        }

        $sql = "SELECT quantity, status
                FROM products
                WHERE sku = :sku
                  AND quantity >= :quantity
                  AND status IN ('disponivel', 'draft')
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sku' => $sku,
            ':quantity' => $quantity,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0, string $sortKey = 'created_at', string $sortDir = 'DESC'): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $sort = $this->normalizeSort($sortKey, $sortDir);

        $sql = 'SELECT p.* FROM products p WHERE 1=1';
        $params = [];
        $this->applyFilters($sql, $params, $filters, 'p');
        $sql .= " ORDER BY {$sort['column']} {$sort['direction']} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $this->productToLegacyRow(Product::fromArray($row));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM products p WHERE 1=1';
        $params = [];
        $this->applyFilters($sql, $params, $filters, 'p');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listForOrders(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $rows = $this->list($filters, $limit, $offset, 'updated_at', 'DESC');
        foreach ($rows as &$row) {
            $row['ID'] = (int) ($row['id'] ?? 0);
            $row['post_title'] = (string) ($row['name'] ?? '');
            $row['regular_price'] = $row['price'] ?? null;
            $row['variations'] = [];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countProductsForBulk(array $filters): int
    {
        return $this->count($this->normalizeBulkFilters($filters));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listProductsForBulk(array $filters, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->list($this->normalizeBulkFilters($filters), $limit, $offset, 'created_at', 'DESC');
        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['ID'] = (int) ($row['id'] ?? 0);
            $row['name'] = (string) ($row['name'] ?? '');
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listForBulk(array $filters, int $limit = 50, int $offset = 0): array
    {
        return $this->listProductsForBulk($filters, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, int>
     */
    public function searchProductIds(array $filters): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = 'SELECT p.sku FROM products p WHERE 1=1';
        $params = [];
        $this->applyFilters($sql, $params, $this->normalizeBulkFilters($filters), 'p');
        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $ids = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = (int) ($row['sku'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWithVendor(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT
                    p.sku,
                    p.sku AS id,
                    p.name,
                    p.price,
                    p.quantity AS stock,
                    p.status,
                    pes.nome AS fornecedor,
                    pes.full_name AS supplier_name
                FROM products p
                LEFT JOIN pessoas pes ON p.supplier_pessoa_id = pes.id
                ORDER BY p.created_at DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listCategories(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $result = [];

        if ($this->tableExists('catalog_categories')) {
            $stmt = $this->pdo->query('SELECT name, slug FROM catalog_categories ORDER BY name ASC');
            while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    $slug = $name;
                }
                $result[] = ['name' => $name, 'slug' => $slug];
            }
            return $result;
        }

        if ($this->tableHasColumn('products', 'category_id')) {
            $stmt = $this->pdo->query('SELECT DISTINCT category_id FROM products WHERE category_id IS NOT NULL ORDER BY category_id');
            while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $id = (int) ($row['category_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $result[] = ['name' => 'Categoria #' . $id, 'slug' => (string) $id];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listBrands(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $result = [];

        if ($this->tableExists('catalog_brands')) {
            $stmt = $this->pdo->query('SELECT name, slug FROM catalog_brands ORDER BY name ASC');
            while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    $slug = $name;
                }
                $result[] = ['name' => $name, 'slug' => $slug];
            }
            return $result;
        }

        if ($this->tableHasColumn('products', 'brand_id')) {
            $stmt = $this->pdo->query('SELECT DISTINCT brand_id FROM products WHERE brand_id IS NOT NULL ORDER BY brand_id');
            while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $id = (int) ($row['brand_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $result[] = ['name' => 'Marca #' . $id, 'slug' => (string) $id];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listTags(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $tags = [];
        $stmt = $this->pdo->query("SELECT metadata FROM products WHERE metadata IS NOT NULL AND metadata != ''");
        while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            if (!is_array($metadata)) {
                continue;
            }
            $this->collectTagEntries($metadata['tags'] ?? null, $tags);
            $this->collectTagEntries($metadata['tag_ids'] ?? null, $tags);
        }

        if (!empty($tags)) {
            uasort($tags, static function (array $a, array $b): int {
                return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        }

        return array_values($tags);
    }

    /**
     * Compatibilidade com contrato antigo.
     * @param array<int, int|string> $productIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function listVariationsForProducts(array $productIds): array
    {
        return [];
    }

    public function updateStock(int $sku, int $quantity): bool
    {
        return $this->updateQuantity($sku, $quantity);
    }

    public function decrementStock(int $sku, int $quantity): bool
    {
        return $this->decrementQuantity($sku, $quantity);
    }

    public function incrementStock(int $sku, int $quantity): bool
    {
        return $this->incrementQuantity($sku, $quantity);
    }

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS products (
          sku BIGINT UNSIGNED PRIMARY KEY,
          name VARCHAR(255) NOT NULL,
          slug VARCHAR(255) NULL UNIQUE,
          description TEXT NULL,
          short_description VARCHAR(500) NULL,
          ribbon VARCHAR(50) NULL,
          brand_id BIGINT UNSIGNED NULL,
          category_id BIGINT UNSIGNED NULL,
          collection_id BIGINT UNSIGNED NULL,
          price DECIMAL(10,2) NULL,
          cost DECIMAL(10,2) NULL,
          suggested_price DECIMAL(10,2) NULL,
          profit_margin DECIMAL(8,4) NULL,
          source ENUM('compra','consignacao','doacao') NOT NULL DEFAULT 'compra',
          supplier_pessoa_id BIGINT UNSIGNED NULL,
          percentual_consignacao DECIMAL(5,2) NULL,
          weight DECIMAL(8,3) NULL,
          size VARCHAR(20) NULL,
          color VARCHAR(50) NULL,
          condition_grade ENUM('novo','usado','usado_com_detalhes') NOT NULL DEFAULT 'usado',
          quantity INT UNSIGNED NOT NULL DEFAULT 1,
          status ENUM('draft','disponivel','reservado','esgotado','baixado','archived') NOT NULL DEFAULT 'draft',
          visibility ENUM('public','catalog','search','hidden') NOT NULL DEFAULT 'public',
          batch_id BIGINT UNSIGNED NULL,
          barcode VARCHAR(120) NULL,
          last_order_id BIGINT UNSIGNED NULL,
          last_sold_at DATETIME NULL,
          metadata JSON NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_products_name (name),
          INDEX idx_products_status_quantity (status, quantity),
          INDEX idx_products_updated_at (updated_at),
          INDEX idx_products_status_updated_at (status, updated_at),
          INDEX idx_products_source (source),
          INDEX idx_products_supplier (supplier_pessoa_id),
          INDEX idx_products_brand (brand_id),
          INDEX idx_products_category (category_id),
          INDEX idx_products_visibility (visibility),
          INDEX idx_products_last_order (last_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    /**
     * Hardening for databases created before unified schema finalized.
     */
    private function ensureColumnsAndIndexes(): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->ensureColumn('products', 'brand_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('products', 'suggested_price', 'DECIMAL(10,2) NULL');
        $this->ensureColumn('products', 'visibility', "ENUM('public','catalog','search','hidden') NOT NULL DEFAULT 'public'");
        $this->ensureColumn('products', 'batch_id', 'BIGINT UNSIGNED NULL');
        $this->ensureColumn('products', 'barcode', 'VARCHAR(120) NULL');

        $this->ensureIndex('products', 'idx_products_brand', 'brand_id');
        $this->ensureIndex('products', 'idx_products_visibility', 'visibility');
        $this->ensureIndex('products', 'idx_products_updated_at', 'updated_at');
        $this->ensureIndex('products', 'idx_products_status_updated_at', 'status, updated_at');

        // Módulo de consignação: expandir ENUM source + novos campos
        $this->ensureSourceEnumExpansion();
        $this->ensureColumn('products', 'consignment_status', "ENUM('em_estoque','vendido_pendente','vendido_pago','proprio_pos_pgto','devolvido','doado','descartado') NULL DEFAULT NULL");
        $this->ensureColumn('products', 'consignment_detached_at', 'DATETIME NULL DEFAULT NULL');
        $this->ensureIndex('products', 'idx_products_consignment_status', 'consignment_status');
    }

    /**
     * Expand the source ENUM to include 'consignacao_quitada'.
     */
    private function ensureSourceEnumExpansion(): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM products LIKE 'source'");
            $stmt->execute();
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($col && isset($col['Type']) && strpos($col['Type'], 'consignacao_quitada') === false) {
                $this->pdo->exec("ALTER TABLE products MODIFY COLUMN source ENUM('compra','consignacao','doacao','consignacao_quitada') NOT NULL DEFAULT 'compra'");
            }
        } catch (\Throwable $e) {
            error_log('Falha ao expandir ENUM source: ' . $e->getMessage());
        }
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($this->tableHasColumn($table, $column)) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function ensureIndex(string $table, string $indexName, string $column): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD INDEX %s (%s)', $table, $indexName, $column));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(string &$sql, array &$params, array $filters, string $alias): void
    {
        $statuses = $this->normalizeStatuses($filters['status'] ?? ($filters['statuses'] ?? null));
        if (!empty($statuses)) {
            $placeholders = [];
            foreach ($statuses as $idx => $status) {
                $key = ':status_' . $idx;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $sql .= " AND {$alias}.status IN (" . implode(',', $placeholders) . ')';
        }

        if (!empty($filters['stock_positive'])) {
            $sql .= " AND {$alias}.quantity > 0";
        }

        if (array_key_exists('available_for_sale', $filters)) {
            $availableForSale = filter_var($filters['available_for_sale'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($availableForSale === true) {
                $sql .= " AND {$alias}.status = 'disponivel' AND {$alias}.quantity > 0";
            } elseif ($availableForSale === false) {
                $sql .= " AND NOT ({$alias}.status = 'disponivel' AND {$alias}.quantity > 0)";
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (
                {$alias}.name LIKE :search
                OR CAST({$alias}.sku AS CHAR) LIKE :search
                OR {$alias}.description LIKE :search
                OR EXISTS (
                    SELECT 1
                    FROM catalog_brands cb
                    WHERE cb.id = {$alias}.brand_id
                      AND cb.name LIKE :search
                )
                OR EXISTS (
                    SELECT 1
                    FROM catalog_categories cc
                    WHERE cc.id = {$alias}.category_id
                      AND cc.name LIKE :search
                )
            )";
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (isset($filters['sku']) && $filters['sku'] !== '') {
            $sql .= " AND CAST({$alias}.sku AS CHAR) LIKE :sku_filter";
            $params[':sku_filter'] = '%' . trim((string) $filters['sku']) . '%';
        }

        if (isset($filters['name']) && $filters['name'] !== '') {
            $sql .= " AND {$alias}.name LIKE :name_filter";
            $params[':name_filter'] = '%' . trim((string) $filters['name']) . '%';
        }

        if (isset($filters['price']) && $filters['price'] !== '') {
            $sql .= " AND CAST({$alias}.price AS CHAR) LIKE :price_filter";
            $params[':price_filter'] = '%' . trim((string) $filters['price']) . '%';
        }

        if (isset($filters['quantity']) && $filters['quantity'] !== '') {
            $sql .= " AND CAST({$alias}.quantity AS CHAR) LIKE :quantity_filter";
            $params[':quantity_filter'] = '%' . trim((string) $filters['quantity']) . '%';
        }

        if (isset($filters['visibility']) && $filters['visibility'] !== '') {
            $sql .= " AND {$alias}.visibility LIKE :visibility_filter";
            $params[':visibility_filter'] = '%' . trim((string) $filters['visibility']) . '%';
        }

        if (isset($filters['brand']) && $filters['brand'] !== '') {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM catalog_brands cb2
                WHERE cb2.id = {$alias}.brand_id
                  AND cb2.name LIKE :brand_filter
            )";
            $params[':brand_filter'] = '%' . trim((string) $filters['brand']) . '%';
        }

        if (isset($filters['category']) && $filters['category'] !== '') {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM catalog_categories cc2
                WHERE cc2.id = {$alias}.category_id
                  AND cc2.name LIKE :category_filter
            )";
            $params[':category_filter'] = '%' . trim((string) $filters['category']) . '%';
        }

        if (isset($filters['supplier']) && $filters['supplier'] !== '') {
            $supplierRaw = trim((string) $filters['supplier']);
            if (ctype_digit($supplierRaw)) {
                $sql .= " AND {$alias}.supplier_pessoa_id = :supplier";
                $params[':supplier'] = (int) $supplierRaw;
            } else {
                $sql .= " AND EXISTS (
                    SELECT 1
                    FROM pessoas pf
                    WHERE pf.id = {$alias}.supplier_pessoa_id
                      AND pf.full_name LIKE :supplier_name_filter
                )";
                $params[':supplier_name_filter'] = '%' . $supplierRaw . '%';
            }
        }

        if (isset($filters['source']) && $filters['source'] !== '') {
            $sql .= " AND {$alias}.source = :source";
            $params[':source'] = trim((string) $filters['source']);
        }

        if (isset($filters['brand_id']) && $filters['brand_id'] !== '') {
            $sql .= " AND {$alias}.brand_id = :brand_id";
            $params[':brand_id'] = (int) $filters['brand_id'];
        }

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $sql .= " AND {$alias}.category_id = :category_id";
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $filters['ids']), static fn (int $v): bool => $v > 0)));
            if (!empty($ids)) {
                $placeholders = [];
                foreach ($ids as $idx => $id) {
                    $key = ':id_' . $idx;
                    $placeholders[] = $key;
                    $params[$key] = $id;
                }
                $sql .= " AND {$alias}.sku IN (" . implode(',', $placeholders) . ')';
            }
        }
    }

    /**
     * @param mixed $statusFilter
     * @return array<int, string>
     */
    private function normalizeStatuses($statusFilter): array
    {
        if ($statusFilter === null || $statusFilter === '') {
            return [];
        }

        $raw = is_array($statusFilter) ? $statusFilter : [$statusFilter];
        $result = [];

        foreach ($raw as $value) {
            $normalized = $this->normalizeStatusInput((string) $value, 1);
            if ($normalized === '') {
                continue;
            }
            $result[$normalized] = $normalized;
        }

        return array_values($result);
    }

    private function normalizeStatusInput(string $status, int $quantity): string
    {
        $value = strtolower(trim($status));
        if ($value === '') {
            return '';
        }

        if (isset(self::LEGACY_TO_UNIFIED[$value])) {
            $mapped = self::LEGACY_TO_UNIFIED[$value];
            if ($mapped === 'disponivel' && $quantity <= 0) {
                return 'esgotado';
            }
            return $mapped;
        }

        if (in_array($value, self::STATUS_UNIFIED, true)) {
            return $value;
        }

        return '';
    }

    private function normalizeVisibility(string $visibility): string
    {
        $value = strtolower(trim($visibility));
        return in_array($value, ['public', 'catalog', 'search', 'hidden'], true) ? $value : 'public';
    }

    /**
     * @return array{column: string, direction: string}
     */
    private function normalizeSort(string $sortKey, string $sortDir): array
    {
        $allowed = [
            'id' => 'p.sku',
            'sku' => 'p.sku',
            'name' => 'p.name',
            'price' => 'p.price',
            'status' => 'p.status',
            'created_at' => 'p.created_at',
            'updated_at' => 'p.updated_at',
            'quantity' => 'p.quantity',
            'source' => 'p.source',
            'supplier_pessoa_id' => 'p.supplier_pessoa_id',
        ];

        $column = $allowed[$sortKey] ?? 'p.created_at';
        $direction = strtoupper(trim($sortDir)) === 'ASC' ? 'ASC' : 'DESC';

        return ['column' => $column, 'direction' => $direction];
    }

    /**
     * @return array{quantity:int,status:string}|null
     */
    private function lockStockState(int $sku): ?array
    {
        if (!$this->pdo || $sku <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT quantity, status
             FROM products
             WHERE sku = :sku
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'quantity' => (int) ($row['quantity'] ?? 0),
            'status' => (string) ($row['status'] ?? 'draft'),
        ];
    }

    private function isOrderSaleableStockStatus(string $status): bool
    {
        $value = strtolower(trim($status));
        return in_array($value, ['disponivel', 'draft'], true);
    }

    private function resolveStatusAfterQuantity(string $currentStatus, int $quantity, string $movementType): string
    {
        $currentStatus = $this->normalizeStatusInput($currentStatus, $quantity) ?: 'draft';

        if (in_array($currentStatus, ['archived', 'baixado'], true) && $movementType !== 'ajuste') {
            return $currentStatus;
        }

        if ($movementType === 'baixa') {
            return 'baixado';
        }

        if ($quantity <= 0) {
            return 'esgotado';
        }

        if ($movementType === 'reserva') {
            return 'reservado';
        }

        if ($currentStatus === 'draft') {
            return 'draft';
        }

        return 'disponivel';
    }

    private function normalizeMovementType(string $movementType): string
    {
        $value = strtolower(trim($movementType));
        return in_array($value, ['venda', 'devolucao', 'ajuste', 'baixa'], true) ? $value : 'ajuste';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeBulkFilters(array $filters): array
    {
        $normalized = $filters;

        if (!empty($filters['availability']) && is_string($filters['availability'])) {
            $availability = strtolower(trim($filters['availability']));
            if ($availability === 'available') {
                $normalized['available_for_sale'] = true;
            }
            if ($availability === 'unavailable') {
                $normalized['available_for_sale'] = false;
            }
        }

        if (!isset($normalized['statuses']) && !isset($normalized['status']) && empty($filters['include_archived'])) {
            $normalized['statuses'] = ['draft', 'disponivel', 'reservado', 'esgotado'];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPersistValues(Product $product): array
    {
        $metadata = $product->metadata;
        if (!empty($product->images)) {
            $metadata['images'] = $product->images;
        }

        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->shortDescription,
            'ribbon' => $product->ribbon,
            'brand_id' => $product->brandId,
            'category_id' => $product->categoryId,
            'collection_id' => $product->collectionId,
            'price' => $product->price,
            'cost' => $product->cost,
            'suggested_price' => $product->suggestedPrice,
            'profit_margin' => $product->profitMargin,
            'source' => $product->source,
            'supplier_pessoa_id' => $product->supplierPessoaId,
            'percentual_consignacao' => $product->percentualConsignacao,
            'weight' => $product->weight,
            'size' => $product->size,
            'color' => $product->color,
            'condition_grade' => $product->conditionGrade,
            'quantity' => $product->quantity,
            'status' => $product->status,
            'visibility' => $this->normalizeVisibility($product->visibility),
            'batch_id' => $product->batchId,
            'barcode' => $product->barcode,
            'last_order_id' => $product->lastOrderId,
            'last_sold_at' => $product->lastSoldAt,
            'metadata' => empty($metadata)
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function ensureUniqueSlug(?string $slug, int $sku): ?string
    {
        if (!$this->pdo || $slug === null || $slug === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT sku FROM products WHERE slug = :slug AND sku <> :sku LIMIT 1'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':sku' => $sku,
        ]);
        $conflict = $stmt->fetchColumn();

        if ($conflict === false) {
            return substr($slug, 0, 255);
        }

        $base = preg_replace('/-\d+$/', '', $slug) ?: $slug;
        $candidate = $base . '-' . $sku;
        return substr($candidate, 0, 255);
    }

    /**
     * @return array<string, true>
     */
    private function existingColumns(string $table): array
    {
        $result = [];
        if (!$this->pdo) {
            return $result;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $table]);

        while ($column = $stmt->fetchColumn()) {
            if (!is_string($column)) {
                continue;
            }
            $result[$column] = true;
        }

        return $result;
    }

    private function productToLegacyRow(Product $product): array
    {
        $row = $product->toArray();
        $legacyStatus = $product->legacyStatus();

        $row['id'] = $product->sku;
        $row['ID'] = $product->sku;
        $row['status_unified'] = $product->status;
        $row['status'] = $legacyStatus;
        $row['post_status'] = $legacyStatus;
        $row['quantity'] = $product->quantity;
        $row['availability_status'] = $product->stockStatus();
        $row['regular_price'] = $product->price;
        $row['price'] = $product->price;
        $row['post_title'] = $product->name;
        $row['variations'] = [];

        return $row;
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
    private function collectTagEntries($value, array &$tags): void
    {
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->collectTagEntries($decoded, $tags);
                return;
            }

            if (str_contains($value, ',')) {
                foreach (explode(',', $value) as $piece) {
                    $this->addTag($piece, $piece, $tags);
                }
                return;
            }

            $this->addTag($value, $value, $tags);
            return;
        }

        if (!is_array($value)) {
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $this->addTag($text, $text, $tags);
                }
            }
            return;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $name = (string) ($item['name'] ?? ($item['slug'] ?? ($item['id'] ?? '')));
                $slug = (string) ($item['slug'] ?? $name);
                $this->addTag($name, $slug, $tags);
                continue;
            }

            if (is_scalar($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $this->addTag($text, $text, $tags);
                }
            }
        }
    }

    private function addTag(string $name, string $slug, array &$tags): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $slug = trim($slug);
        if ($slug === '') {
            $slug = $name;
        }
        $key = strtolower($name);
        $tags[$key] = ['name' => $name, 'slug' => $slug];
    }

    private function tableExists(string $table): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
             LIMIT 1'
        );
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
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

    private function indexExists(string $table, string $indexName): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':index' => $indexName,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
