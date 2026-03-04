<?php

namespace App\Repositories;

use PDO;

/**
 * Product supply compatibility adapter.
 *
 * Legacy table `produto_fornecimentos` was removed. Source of truth is `products`
 * and lot linkage is stored in `products.metadata.lot_id`.
 */
class ProductSupplyRepository
{
    private ?PDO $pdo;

    private const TEXT_COLLATION = 'utf8mb4_general_ci';
    private const LOT_JSON_PATH = '$.lot_id';

    /** @var array<int, string> */
    private const VALID_SOURCES = ['compra', 'consignacao', 'doacao'];

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                // Keep dependencies bootstrapped for lot/vendor joins.
                new PieceLotRepository($pdo);
                PeopleCompatViewRepository::ensure($pdo);
            } catch (\Throwable $e) {
                error_log('Falha ao preparar ProductSupplyRepository: ' . $e->getMessage());
            }
        }
    }

    public function create(array $data): void
    {
        $this->persistSupply($data);
    }

    public function upsert(array $data): void
    {
        $this->persistSupply($data);
    }

    public function findByProductId(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }

        $sql = $this->baseSelectSql() . ' WHERE p.sku = :sku LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sku' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function deleteByProductId(int $id): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }

        $current = $this->fetchProductRow($id);
        if (!$current) {
            return;
        }

        $metadata = $this->decodeMetadata($current['metadata'] ?? null);
        unset($metadata['lot_id']);

        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET supplier_pessoa_id = NULL,
                 cost = NULL,
                 percentual_consignacao = NULL,
                 metadata = :metadata
             WHERE sku = :sku'
        );
        $stmt->execute([
            ':sku' => $id,
            ':metadata' => $this->encodeMetadata($metadata),
        ]);
    }

    public function listByProductIds(array $ids): array
    {
        if (!$this->pdo || empty($ids)) {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = $this->baseSelectSql() . " WHERE p.sku IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['product_id'] ?? 0)] = $row;
        }

        return $map;
    }

    public function listBySkus(array $skus): array
    {
        if (!$this->pdo || empty($skus)) {
            return [];
        }

        $normalized = [];
        foreach ($skus as $sku) {
            $value = trim((string) $sku);
            if ($value === '') {
                continue;
            }
            $normalized[$value] = $value;
        }

        if (empty($normalized)) {
            return [];
        }

        $values = array_values($normalized);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $sql = $this->baseSelectSql() . " WHERE CAST(p.sku AS CHAR) IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $map[$sku] = $row;
            if (ctype_digit($sku)) {
                $map[(int) $sku] = $row;
            }
        }

        return $map;
    }

    public function listByLotId(int $lotId): array
    {
        if (!$this->pdo || $lotId <= 0) {
            return [];
        }

        $lotExpr = $this->lotIdExpr('p');
        $sql = $this->baseSelectSql() . " WHERE {$lotExpr} = :lot_id ORDER BY p.sku ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lot_id' => $lotId]);

        return $stmt->fetchAll();
    }

    public function listByLotIds(array $lotIds): array
    {
        if (!$this->pdo || empty($lotIds)) {
            return [];
        }

        $lotIds = array_values(array_filter(array_map('intval', $lotIds), static fn (int $id): bool => $id > 0));
        if (empty($lotIds)) {
            return [];
        }

        $lotExpr = $this->lotIdExpr('p');
        $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
        $sql = $this->baseSelectSql() . " WHERE {$lotExpr} IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($lotIds);

        return $stmt->fetchAll();
    }

    public function listAllWithVendor(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->query($this->baseSelectSql());
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listSupplierStats(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT COALESCE(
                        NULLIF(TRIM(v_person.full_name), ''),
                        CAST(COALESCE(v_person.id_vendor, p.supplier_pessoa_id) AS CHAR),
                        'Sem fornecedor'
                      ) AS supplier_name,
                       COUNT(*) AS total
                FROM products p
                LEFT JOIN vw_fornecedores_compat v_person ON v_person.id = p.supplier_pessoa_id
                GROUP BY supplier_name
                ORDER BY supplier_name ASC";
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listSourceStats(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT p.source AS source, COUNT(*) AS total
                FROM products p
                WHERE p.source IS NOT NULL AND p.source <> ''
                GROUP BY p.source
                ORDER BY p.source ASC";
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listWithVendorByFilters(array $filters): array
    {
        if (!$this->pdo) {
            return [];
        }

        $supplier = trim((string) ($filters['supplier'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));
        $global = trim((string) ($filters['global'] ?? ''));

        $where = [];
        $params = [];

        if ($supplier !== '') {
            $supplierLike = '%' . $supplier . '%';
            if (ctype_digit($supplier)) {
                $where[] = '(v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :supplier_name'
                    . ' OR p.supplier_pessoa_id = :supplier_id'
                    . ' OR v_person.id_vendor = :supplier_id'
                    . ' OR v_person.id = :supplier_id)';
                $params[':supplier_id'] = (int) $supplier;
            } else {
                $where[] = 'v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :supplier_name';
            }
            $params[':supplier_name'] = $supplierLike;
        }

        if ($source !== '') {
            $where[] = 'p.source COLLATE ' . self::TEXT_COLLATION . ' LIKE :source';
            $params[':source'] = '%' . $source . '%';
        }

        if ($global !== '') {
            $where[] = '(CAST(p.sku AS CHAR) LIKE :global'
                . ' OR p.name COLLATE ' . self::TEXT_COLLATION . ' LIKE :global'
                . ' OR p.source COLLATE ' . self::TEXT_COLLATION . ' LIKE :global'
                . ' OR v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :global)';
            $params[':global'] = '%' . $global . '%';
        }

        $sql = $this->baseSelectSql();
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY supplier_name ASC, p.sku ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listProductIdsByFilters(array $filters): array
    {
        if (!$this->pdo) {
            return [];
        }

        $supplier = trim((string) ($filters['supplier'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));
        $global = trim((string) ($filters['global'] ?? ''));

        $where = [];
        $params = [];

        if ($supplier !== '') {
            $supplierLike = '%' . $supplier . '%';
            if (ctype_digit($supplier)) {
                $where[] = '(v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :supplier_name'
                    . ' OR p.supplier_pessoa_id = :supplier_id'
                    . ' OR v_person.id_vendor = :supplier_id'
                    . ' OR v_person.id = :supplier_id)';
                $params[':supplier_id'] = (int) $supplier;
            } else {
                $where[] = 'v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :supplier_name';
            }
            $params[':supplier_name'] = $supplierLike;
        }

        if ($source !== '') {
            $where[] = 'p.source COLLATE ' . self::TEXT_COLLATION . ' LIKE :source';
            $params[':source'] = '%' . $source . '%';
        }

        if ($global !== '') {
            $where[] = '(CAST(p.sku AS CHAR) LIKE :global'
                . ' OR p.name COLLATE ' . self::TEXT_COLLATION . ' LIKE :global'
                . ' OR p.source COLLATE ' . self::TEXT_COLLATION . ' LIKE :global'
                . ' OR v_person.full_name COLLATE ' . self::TEXT_COLLATION . ' LIKE :global)';
            $params[':global'] = '%' . $global . '%';
        }

        if (empty($where)) {
            return [];
        }

        $sql = "SELECT DISTINCT p.sku AS product_id
                FROM products p
                LEFT JOIN vw_fornecedores_compat v_person ON v_person.id = p.supplier_pessoa_id
                WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function persistSupply(array $data): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $productId = $this->resolveProductId($data);
        $product = $this->fetchProductRow($productId);
        if (!$product) {
            throw new \RuntimeException('Produto #' . $productId . ' não encontrado para atualizar fornecimento.');
        }

        $currentSource = $this->normalizeSource((string) ($product['source'] ?? 'compra'));
        $source = array_key_exists('source', $data)
            ? $this->normalizeSource((string) $data['source'])
            : $currentSource;

        $hasSupplierInput = $this->containsSupplierInput($data);
        $supplierPessoaId = $hasSupplierInput
            ? $this->resolveSupplierPessoaId($data)
            : (isset($product['supplier_pessoa_id']) ? (int) $product['supplier_pessoa_id'] : null);
        if ($supplierPessoaId !== null && $supplierPessoaId <= 0) {
            $supplierPessoaId = null;
        }

        $cost = array_key_exists('cost', $data)
            ? $this->nullableDecimal($data['cost'])
            : $this->nullableDecimal($product['cost'] ?? null);

        $percentualConsignacao = array_key_exists('percentual_consignacao', $data)
            ? $this->nullableDecimal($data['percentual_consignacao'])
            : $this->nullableDecimal($product['percentual_consignacao'] ?? null);

        $metadata = $this->decodeMetadata($product['metadata'] ?? null);
        if (array_key_exists('lot_id', $data)) {
            $lotId = (int) $data['lot_id'];
            if ($lotId > 0) {
                $metadata['lot_id'] = $lotId;
            } else {
                unset($metadata['lot_id']);
            }
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET source = :source,
                 cost = :cost,
                 supplier_pessoa_id = :supplier_pessoa_id,
                 percentual_consignacao = :percentual_consignacao,
                 metadata = :metadata
             WHERE sku = :sku'
        );

        $stmt->execute([
            ':sku' => $productId,
            ':source' => $source,
            ':cost' => $cost,
            ':supplier_pessoa_id' => $supplierPessoaId,
            ':percentual_consignacao' => $percentualConsignacao,
            ':metadata' => $this->encodeMetadata($metadata),
        ]);
    }

    private function resolvePessoaIdFromVendorCode(int $vendorCode): ?int
    {
        if (!$this->pdo || $vendorCode <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id AS pessoa_id FROM vw_fornecedores_compat WHERE id_vendor = :code OR id = :code LIMIT 1'
            );
            $stmt->execute([':code' => $vendorCode]);
            $value = $stmt->fetchColumn();

            return $value !== false ? (int) $value : null;
        } catch (\Throwable $e) {
            error_log('Falha ao resolver pessoa_id por fornecedor: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveSupplierPessoaId(array $data): ?int
    {
        $supplierPessoaId = isset($data['supplier_pessoa_id']) ? (int) $data['supplier_pessoa_id'] : 0;
        if ($supplierPessoaId > 0) {
            return $supplierPessoaId;
        }

        $legacySupplierKeys = ['supplier_id_vendor', 'supplier_id', 'vendor_id', 'supplier'];
        foreach ($legacySupplierKeys as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $legacyCode = (int) $data[$key];
            if ($legacyCode <= 0) {
                continue;
            }
            $resolved = $this->resolvePessoaIdFromVendorCode($legacyCode);
            if ($resolved !== null && $resolved > 0) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveProductId(array $data): int
    {
        foreach (['sku', 'product_id', 'id'] as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if ($value === '' || !ctype_digit($value)) {
                continue;
            }
            $productId = (int) $value;
            if ($productId > 0) {
                return $productId;
            }
        }

        throw new \InvalidArgumentException('ID do produto inválido para fornecimento.');
    }

    private function containsSupplierInput(array $data): bool
    {
        foreach (['supplier_pessoa_id', 'supplier_id_vendor', 'supplier_id', 'vendor_id', 'supplier'] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    private function fetchProductRow(int $sku): ?array
    {
        if (!$this->pdo || $sku <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT sku, source, cost, supplier_pessoa_id, percentual_consignacao, metadata
             FROM products
             WHERE sku = :sku
             LIMIT 1'
        );
        $stmt->execute([':sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function lotIdExpr(string $alias): string
    {
        return "CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '" . self::LOT_JSON_PATH . "')), '') AS UNSIGNED)";
    }

    private function baseSelectSql(): string
    {
        $lotExpr = $this->lotIdExpr('p');

        return "SELECT p.sku AS product_id,
                       CAST(p.sku AS CHAR) AS sku,
                       COALESCE(v_person.id_vendor, p.supplier_pessoa_id) AS supplier_id_vendor,
                       p.supplier_pessoa_id,
                       p.source,
                       p.cost,
                       p.percentual_consignacao,
                       {$lotExpr} AS lot_id,
                       v_person.full_name AS supplier_name,
                       l.name AS lot_name,
                       l.status AS lot_status,
                       l.opened_at AS lot_opened_at
                FROM products p
                LEFT JOIN vw_fornecedores_compat v_person ON v_person.id = p.supplier_pessoa_id
                LEFT JOIN produto_lotes l ON l.id = {$lotExpr}";
    }

    /**
     * @param mixed $value
     */
    private function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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

    /**
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): ?string
    {
        if (empty($metadata)) {
            return null;
        }

        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private function normalizeSource(string $source): string
    {
        $value = strtolower(trim($source));
        return in_array($value, self::VALID_SOURCES, true) ? $value : 'compra';
    }
}
