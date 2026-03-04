<?php

namespace App\Models;

use App\Support\Input;
use ArrayAccess;

/**
 * Unified product model.
 *
 * `sku` is the only persisted identifier. Legacy aliases are exposed through
 * magic getters/setters so old controllers keep working while data stays in
 * `products` only.
 */
class Product implements ArrayAccess
{
    public int $sku = 0;
    public string $name = '';
    public ?string $slug = null;

    public ?string $description = null;
    public ?string $shortDescription = null;
    public ?string $ribbon = null;

    public ?int $brandId = null;
    public ?int $categoryId = null;
    public ?int $collectionId = null;

    public ?float $price = null;
    public ?float $cost = null;
    public ?float $suggestedPrice = null;
    public ?float $profitMargin = null;

    public string $source = 'compra';
    public ?int $supplierPessoaId = null;
    public ?float $percentualConsignacao = null;

    public ?float $weight = null;
    public ?string $size = null;
    public ?string $color = null;
    public string $conditionGrade = 'usado';

    public int $quantity = 1;
    public string $status = 'draft';
    public string $visibility = 'public';
    public ?int $batchId = null;
    public ?string $barcode = null;

    public ?int $lastOrderId = null;
    public ?string $lastSoldAt = null;

    /**
     * @var array<string, mixed>
     */
    public array $metadata = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $images = [];

    public ?string $consignmentStatus = null;
    public ?string $consignmentDetachedAt = null;

    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Legacy status labels used by old product screens.
     */
    public function legacyStatus(): string
    {
        return match ($this->status) {
            'draft' => 'draft',
            'disponivel', 'reservado', 'esgotado' => 'publish',
            'baixado', 'archived' => 'trash',
            default => 'draft',
        };
    }

    public static function fromArray(array $data): self
    {
        $product = new self();

        $product->sku = (int) ($data['sku'] ?? $data['id'] ?? 0);

        $product->name = (string) ($data['name'] ?? $data['nome'] ?? $data['post_title'] ?? '');
        $product->slug = self::nullableString($data['slug'] ?? null);

        $product->description = self::nullableString($data['description'] ?? $data['descricao'] ?? null);
        $product->shortDescription = self::nullableString(
            $data['short_description'] ?? $data['shortDescription'] ?? null
        );
        $product->ribbon = self::nullableString($data['ribbon'] ?? null);

        $product->brandId = self::nullableInt($data['brand_id'] ?? $data['brandId'] ?? $data['brand'] ?? null);
        $product->categoryId = self::nullableInt($data['category_id'] ?? $data['categoryId'] ?? null);
        $product->collectionId = self::nullableInt($data['collection_id'] ?? $data['collectionId'] ?? null);

        $product->price = self::nullableFloat($data['price'] ?? $data['preco_venda'] ?? $data['regular_price'] ?? null);
        $product->cost = self::nullableFloat($data['cost'] ?? $data['preco_custo'] ?? null);
        $product->suggestedPrice = self::nullableFloat($data['suggested_price'] ?? null);
        $product->profitMargin = self::nullableFloat($data['profit_margin'] ?? $data['margin'] ?? null);

        $product->source = self::normalizeSource((string) ($data['source'] ?? 'compra'));
        $product->supplierPessoaId = self::nullableInt(
            $data['supplier_pessoa_id'] ?? $data['supplierPessoaId'] ?? $data['supplier'] ?? null
        );
        $product->percentualConsignacao = self::nullableFloat(
            $data['percentual_consignacao'] ?? $data['percentualConsignacao'] ?? $data['consignment_percent'] ?? null
        );

        $product->weight = self::nullableFloat($data['weight'] ?? null);
        $product->size = self::nullableString($data['size'] ?? null);
        $product->color = self::nullableString($data['color'] ?? null);
        $product->conditionGrade = self::normalizeCondition((string) (
            $data['condition_grade']
            ?? $data['conditionGrade']
            ?? $data['condition']
            ?? 'usado'
        ));

        $product->quantity = (int) ($data['quantity'] ?? 1);
        if ($product->quantity < 0) {
            $product->quantity = 0;
        }

        $rawStatus = (string) (
            $data['status']
            ?? $data['post_status']
            ?? $data['availability_status']
            ?? 'draft'
        );
        $product->status = self::normalizeStatus($rawStatus, $product->quantity);
        $product->visibility = self::normalizeVisibility((string) ($data['visibility'] ?? 'public'));
        $product->batchId = self::nullableInt($data['batch_id'] ?? $data['batchId'] ?? null);
        $product->barcode = self::nullableString($data['barcode'] ?? null);

        $product->lastOrderId = self::nullableInt($data['last_order_id'] ?? $data['lastOrderId'] ?? null);
        $product->lastSoldAt = self::nullableString($data['last_sold_at'] ?? $data['lastSoldAt'] ?? null);

        $metadata = self::decodeMetadata($data['metadata'] ?? null);
        $images = self::decodeImages($data['images'] ?? null);
        if (empty($images) && isset($metadata['images']) && is_array($metadata['images'])) {
            $images = self::decodeImages($metadata['images']);
        }
        $product->images = $images;
        if (!empty($product->images)) {
            $metadata['images'] = $product->images;
        }
        $product->metadata = $metadata;

        $product->consignmentStatus = self::nullableString(
            $data['consignment_status'] ?? $data['consignmentStatus'] ?? null
        );
        $product->consignmentDetachedAt = self::nullableString(
            $data['consignment_detached_at'] ?? $data['consignmentDetachedAt'] ?? null
        );

        $product->createdAt = self::nullableString($data['created_at'] ?? $data['createdAt'] ?? null);
        $product->updatedAt = self::nullableString($data['updated_at'] ?? $data['updatedAt'] ?? null);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDbParams(): array
    {
        $metadata = $this->metadata;
        if (!empty($this->images)) {
            $metadata['images'] = $this->images;
        }

        return [
            ':sku' => $this->sku,
            ':name' => $this->name,
            ':slug' => $this->slug,
            ':description' => $this->description,
            ':short_description' => $this->shortDescription,
            ':ribbon' => $this->ribbon,
            ':brand_id' => $this->brandId,
            ':category_id' => $this->categoryId,
            ':collection_id' => $this->collectionId,
            ':price' => $this->price,
            ':cost' => $this->cost,
            ':suggested_price' => $this->suggestedPrice,
            ':profit_margin' => $this->profitMargin,
            ':source' => $this->source,
            ':supplier_pessoa_id' => $this->supplierPessoaId,
            ':percentual_consignacao' => $this->percentualConsignacao,
            ':weight' => $this->weight,
            ':size' => $this->size,
            ':color' => $this->color,
            ':condition_grade' => $this->conditionGrade,
            ':quantity' => $this->quantity,
            ':status' => $this->status,
            ':visibility' => $this->visibility,
            ':batch_id' => $this->batchId,
            ':barcode' => $this->barcode,
            ':last_order_id' => $this->lastOrderId,
            ':last_sold_at' => $this->lastSoldAt,
            ':metadata' => empty($metadata)
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $stockStatus = $this->stockStatus();
        $legacyStatus = $this->legacyStatus();

        return [
            'id' => $this->sku,
            'sku' => $this->sku,
            'name' => $this->name,
            'nome' => $this->name,
            'post_title' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'descricao' => $this->description,
            'short_description' => $this->shortDescription,
            'ribbon' => $this->ribbon,
            'brand_id' => $this->brandId,
            'category_id' => $this->categoryId,
            'collection_id' => $this->collectionId,
            'price' => $this->price,
            'preco_venda' => $this->price,
            'regular_price' => $this->price,
            'cost' => $this->cost,
            'preco_custo' => $this->cost,
            'acquisition_cost' => $this->cost,
            'suggested_price' => $this->suggestedPrice,
            'profit_margin' => $this->profitMargin,
            'margin' => $this->profitMargin,
            'source' => $this->source,
            'supplier_pessoa_id' => $this->supplierPessoaId,
            'supplier' => $this->supplierPessoaId,
            'percentual_consignacao' => $this->percentualConsignacao,
            'consignment_percent' => $this->percentualConsignacao,
            'weight' => $this->weight,
            'size' => $this->size,
            'color' => $this->color,
            'condition_grade' => $this->conditionGrade,
            'condition' => $this->conditionGrade,
            'quantity' => $this->quantity,
            'catalog_product_id' => $this->sku,
            'internal_code' => (string) $this->sku,
            'price_listed' => $this->price,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'batch_id' => $this->batchId,
            'barcode' => $this->barcode,
            'post_status' => $legacyStatus,
            'legacy_status' => $legacyStatus,
            'availability_status' => $stockStatus,
            'stockStatus' => $stockStatus,
            'last_order_id' => $this->lastOrderId,
            'last_sold_at' => $this->lastSoldAt,
            'metadata' => $this->metadata,
            'images' => $this->images,
            'image_src' => (string) ($this->images[0]['src'] ?? ''),
            'entered_at' => $this->createdAt,
            'sold_at' => $this->lastSoldAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'consignment_status' => $this->consignmentStatus,
            'consignment_detached_at' => $this->consignmentDetachedAt,
        ];
    }

    public function stockStatus(): string
    {
        if ($this->quantity <= 0) {
            return 'outofstock';
        }
        return $this->status === 'disponivel' ? 'instock' : 'outofstock';
    }

    public function __get(string $name)
    {
        return match ($name) {
            'id' => $this->sku,
            'nome', 'post_title' => $this->name,
            'descricao' => $this->description,
            'preco_venda', 'regular_price' => $this->price,
            'preco_custo' => $this->cost,
            'acquisition_cost' => $this->cost,
            'suggested_price' => $this->suggestedPrice,
            'availability_status', 'stockStatus' => $this->stockStatus(),
            'post_status', 'legacy_status' => $this->legacyStatus(),
            'supplier' => $this->supplierPessoaId,
            'consignment_percent' => $this->percentualConsignacao,
            'catalog_product_id' => $this->sku,
            'internal_code' => (string) $this->sku,
            'price_listed' => $this->price,
            'condition' => $this->conditionGrade,
            'visibility' => $this->visibility,
            'batch_id' => $this->batchId,
            'barcode' => $this->barcode,
            'sold_at' => $this->lastSoldAt,
            'consignment_status' => $this->consignmentStatus,
            'consignment_detached_at' => $this->consignmentDetachedAt,
            default => $this->metadata[$name] ?? null,
        };
    }

    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'id':
                $this->sku = (int) $value;
                return;
            case 'nome':
            case 'post_title':
                $this->name = (string) $value;
                return;
            case 'descricao':
                $this->description = self::nullableString($value);
                return;
            case 'preco_venda':
            case 'regular_price':
                $this->price = self::nullableFloat($value);
                return;
            case 'preco_custo':
                $this->cost = self::nullableFloat($value);
                return;
            case 'acquisition_cost':
                $this->cost = self::nullableFloat($value);
                return;
            case 'suggested_price':
                $this->suggestedPrice = self::nullableFloat($value);
                return;
            case 'availability_status':
            case 'stockStatus':
                $status = strtolower(trim((string) $value));
                if ($status === 'instock' && $this->quantity > 0 && $this->status === 'esgotado') {
                    $this->status = 'disponivel';
                }
                if ($status === 'outofstock' && $this->quantity <= 0 && $this->status === 'disponivel') {
                    $this->status = 'esgotado';
                }
                return;
            case 'supplier':
                $this->supplierPessoaId = self::nullableInt($value);
                return;
            case 'post_status':
                $this->status = self::normalizeStatus((string) $value, $this->quantity);
                return;
            case 'consignment_percent':
                $this->percentualConsignacao = self::nullableFloat($value);
                return;
            case 'condition':
                $this->conditionGrade = self::normalizeCondition((string) $value);
                return;
            case 'visibility':
                $this->visibility = self::normalizeVisibility((string) $value);
                return;
            case 'batch_id':
                $this->batchId = self::nullableInt($value);
                return;
            case 'barcode':
                $this->barcode = self::nullableString($value);
                return;
            case 'consignment_status':
                $this->consignmentStatus = self::nullableString($value);
                return;
            case 'consignment_detached_at':
                $this->consignmentDetachedAt = self::nullableString($value);
                return;
            default:
                $this->metadata[$name] = $value;
        }
    }

    /**
     * Normalize current model before persistence.
     */
    public function normalizeForPersistence(): void
    {
        if ($this->quantity < 0) {
            $this->quantity = 0;
        }
        $this->source = self::normalizeSource($this->source);
        $this->conditionGrade = self::normalizeCondition($this->conditionGrade);
        $this->status = self::normalizeStatus($this->status, $this->quantity);
        $this->visibility = self::normalizeVisibility($this->visibility);
    }

    public static function normalizeStatusForQuantity(string $status, int $quantity): string
    {
        return self::normalizeStatus($status, $quantity);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        $data = $this->toArray();
        return array_key_exists($offset, $data);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }
        $data = $this->toArray();
        return $data[$offset] ?? $this->__get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            return;
        }
        $this->__set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset)) {
            return;
        }
        switch ($offset) {
            case 'description':
            case 'descricao':
                $this->description = null;
                return;
            case 'short_description':
                $this->shortDescription = null;
                return;
            case 'slug':
                $this->slug = null;
                return;
            default:
                unset($this->metadata[$offset]);
        }
    }

    private static function normalizeSource(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['compra', 'consignacao', 'doacao'], true) ? $value : 'compra';
    }

    private static function normalizeCondition(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['novo', 'usado', 'usado_com_detalhes'], true) ? $value : 'usado';
    }

    private static function normalizeVisibility(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['public', 'catalog', 'search', 'hidden'], true) ? $value : 'public';
    }

    private static function normalizeStatus(string $value, int $quantity): string
    {
        $value = strtolower(trim($value));

        if (in_array($value, ['publish', 'active'], true)) {
            return $quantity > 0 ? 'disponivel' : 'esgotado';
        }
        if (in_array($value, ['private', 'pending'], true)) {
            return 'draft';
        }
        if ($value === 'instock') {
            return $quantity > 0 ? 'disponivel' : 'esgotado';
        }
        if ($value === 'outofstock') {
            return 'esgotado';
        }

        $allowed = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return $quantity > 0 ? 'disponivel' : 'draft';
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function decodeMetadata($value): array
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
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private static function decodeImages($value): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            return [];
        }

        $images = [];
        foreach ($value as $image) {
            if (!is_array($image)) {
                continue;
            }
            $src = trim((string) ($image['src'] ?? ''));
            $id = isset($image['id']) ? (int) $image['id'] : 0;
            if ($src === '' && $id <= 0) {
                continue;
            }
            $images[] = [
                'id' => $id,
                'src' => $src,
                'name' => (string) ($image['name'] ?? $image['alt'] ?? ''),
            ];
        }

        return $images;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }

    private static function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                return null;
            }

            // Valores decimais vindos do banco usam ponto e não devem ser
            // reinterpretados como separador de milhar.
            if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
                return (float) $raw;
            }
        }

        return Input::parseNumber($value);
    }

    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
