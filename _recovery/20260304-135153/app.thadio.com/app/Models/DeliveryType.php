<?php

namespace App\Models;

use App\Support\Input;

class DeliveryType
{
    public ?int $id = null;
    public string $name = '';
    public ?string $description = null;
    public string $status = 'ativo';
    public float $basePrice = 0.0;
    public ?float $southPrice = null;
    public string $availability = 'all';
    public string $bagAction = 'none';

    public static function fromArray(array $data): self
    {
        $type = new self();
        $type->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $type->name = (string) ($data['name'] ?? '');
        $type->description = self::nullableString($data['description'] ?? null);
        $type->status = (string) ($data['status'] ?? 'ativo');
        $basePrice = Input::parseNumber($data['base_price'] ?? $data['basePrice'] ?? null);
        $type->basePrice = $basePrice ?? 0.0;
        $type->southPrice = self::nullableFloat($data['south_price'] ?? $data['southPrice'] ?? null);
        $type->availability = (string) ($data['availability'] ?? 'all');
        $type->bagAction = (string) ($data['bag_action'] ?? $data['bagAction'] ?? 'none');

        return $type;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':description' => $this->description,
            ':status' => $this->status,
            ':base_price' => $this->basePrice,
            ':south_price' => $this->southPrice,
            ':availability' => $this->availability,
            ':bag_action' => $this->bagAction,
        ];
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
