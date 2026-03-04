<?php

namespace App\Models;

use App\Support\Input;

class OrderItem
{
    public ?int $lineId = null;
    public int $productSku = 0;
    public int $variationId = 0;
    public int $quantity = 1;
    public ?float $price = null;
    public ?string $name = null;

    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->lineId = isset($data['line_id']) ? (int) $data['line_id'] : (isset($data['lineId']) ? (int) $data['lineId'] : null);
        $item->productSku = isset($data['product_sku']) ? (int) $data['product_sku'] : (int) ($data['productSku'] ?? 0);
        $item->variationId = isset($data['variation_id']) ? (int) $data['variation_id'] : (int) ($data['variationId'] ?? 0);
        $item->quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
        $item->price = self::nullableFloat($data['price'] ?? null);
        $item->name = self::nullableString($data['name'] ?? null);
        return $item;
    }

    public function isValid(): bool
    {
        return $this->productSku > 0 && $this->quantity > 0;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
