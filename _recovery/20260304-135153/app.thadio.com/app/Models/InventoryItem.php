<?php

namespace App\Models;

class InventoryItem
{
    public ?int $id = null;
    public ?int $catalogProductId = null;
    public string $internalCode;
    public ?int $sku = null;
    public ?string $titleOverride = null;
    public ?string $conditionGrade = null;
    public ?string $size = null;
    public ?string $color = null;
    public ?string $gender = null;
    public string $source;
    public ?int $supplierPessoaId = null;
    public ?int $consignmentId = null;
    public ?float $acquisitionCost = null;
    public ?float $consignmentPercent = null;
    public ?float $priceListed = null;
    public string $status = 'disponivel';
    public string $enteredAt;
    public ?string $soldAt = null;
    public ?string $writeoffAt = null;
    public ?string $notes = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $item->catalogProductId = isset($data['catalog_product_id']) ? (int) $data['catalog_product_id'] : null;
        $item->internalCode = (string) $data['internal_code'];
        $item->sku = isset($data['sku']) ? (int) $data['sku'] : null;
        $item->titleOverride = self::nullableString($data['title_override'] ?? null);
        $item->conditionGrade = self::nullableString($data['condition_grade'] ?? null);
        $item->size = self::nullableString($data['size'] ?? null);
        $item->color = self::nullableString($data['color'] ?? null);
        $item->gender = self::nullableString($data['gender'] ?? null);
        $item->source = (string) $data['source'];
        $item->supplierPessoaId = isset($data['supplier_pessoa_id']) ? (int) $data['supplier_pessoa_id'] : null;
        $item->consignmentId = isset($data['consignment_id']) ? (int) $data['consignment_id'] : null;
        $item->acquisitionCost = isset($data['acquisition_cost']) ? (float) $data['acquisition_cost'] : null;
        $item->consignmentPercent = isset($data['consignment_percent']) ? (float) $data['consignment_percent'] : null;
        $item->priceListed = isset($data['price_listed']) ? (float) $data['price_listed'] : null;
        $item->status = (string) ($data['status'] ?? 'disponivel');
        $item->enteredAt = (string) ($data['entered_at'] ?? date('Y-m-d H:i:s'));
        $item->soldAt = self::nullableString($data['sold_at'] ?? null);
        $item->writeoffAt = self::nullableString($data['writeoff_at'] ?? null);
        $item->notes = self::nullableString($data['notes'] ?? null);
        $item->createdAt = self::nullableString($data['created_at'] ?? null);
        $item->updatedAt = self::nullableString($data['updated_at'] ?? null);

        return $item;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * Convert InventoryItem to array format for database storage and audit
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'catalog_product_id' => $this->catalogProductId,
            'internal_code' => $this->internalCode,
            'sku' => $this->sku,
            'title_override' => $this->titleOverride,
            'condition_grade' => $this->conditionGrade,
            'size' => $this->size,
            'color' => $this->color,
            'gender' => $this->gender,
            'source' => $this->source,
            'supplier_pessoa_id' => $this->supplierPessoaId,
            'consignment_id' => $this->consignmentId,
            'acquisition_cost' => $this->acquisitionCost,
            'consignment_percent' => $this->consignmentPercent,
            'price_listed' => $this->priceListed,
            'status' => $this->status,
            'entered_at' => $this->enteredAt,
            'sold_at' => $this->soldAt,
            'writeoff_at' => $this->writeoffAt,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
