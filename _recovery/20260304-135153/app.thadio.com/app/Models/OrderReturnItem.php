<?php

namespace App\Models;

use App\Support\Input;

class OrderReturnItem
{
    public ?int $id = null;
    public int $returnId = 0;
    public ?int $orderItemId = null;
    public int $productSku = 0;
    public int $variationId = 0;
    public int $quantity = 0;
    public float $unitPrice = 0.0;
    public ?string $productName = null;
    public ?string $sku = null;

    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $item->returnId = isset($data['return_id']) ? (int) $data['return_id'] : (int) ($data['returnId'] ?? 0);
        $item->orderItemId = isset($data['order_item_id']) ? (int) $data['order_item_id'] : (isset($data['orderItemId']) ? (int) $data['orderItemId'] : null);
        $item->productSku = isset($data['product_sku']) ? (int) $data['product_sku'] : (int) ($data['productSku'] ?? 0);
        $item->variationId = isset($data['variation_id']) ? (int) $data['variation_id'] : (int) ($data['variationId'] ?? 0);
        $item->quantity = isset($data['quantity']) ? (int) $data['quantity'] : 0;
        $unitPrice = Input::parseNumber($data['unit_price'] ?? $data['unitPrice'] ?? null);
        $item->unitPrice = $unitPrice ?? 0.0;
        $item->productName = self::nullableString($data['product_name'] ?? $data['productName'] ?? null);
        $item->sku = self::nullableString($data['sku'] ?? null);
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
}
