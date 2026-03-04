<?php

namespace App\Services;

use App\Models\DeliveryType;
use App\Support\Input;

class DeliveryTypeService
{
    public const AVAILABILITY_OPTIONS = [
        'all' => 'Todos os estados',
        'df_only' => 'Apenas Distrito Federal',
    ];
    public const BAG_ACTION_OPTIONS = [
        'none' => 'Sem ação de sacolinha',
        'open_bag' => 'Abrir sacolinha',
        'add_to_bag' => 'Adicionar a sacolinha',
    ];

    /**
     * @return array{0: DeliveryType, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $availability = $input['availability'] ?? 'all';
        if (!array_key_exists($availability, self::AVAILABILITY_OPTIONS)) {
            $errors[] = 'Disponibilidade inválida.';
        }

        $bagAction = $input['bag_action'] ?? 'none';
        if (!array_key_exists($bagAction, self::BAG_ACTION_OPTIONS)) {
            $errors[] = 'Ação de sacolinha inválida.';
        }

        $basePrice = $this->normalizeMoney($input['base_price'] ?? null);
        if ($basePrice === null) {
            $basePrice = 0.0;
        }
        if ($basePrice < 0) {
            $errors[] = 'Valor base inválido.';
        }

        $southPrice = $this->normalizeMoney($input['south_price'] ?? null);
        if ($southPrice !== null && $southPrice < 0) {
            $errors[] = 'Valor para região sul inválido.';
        }

        $type = DeliveryType::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'description' => $input['description'] ?? null,
            'status' => $status,
            'base_price' => $basePrice,
            'south_price' => $southPrice,
            'availability' => $availability,
            'bag_action' => $bagAction,
        ]);

        return [$type, $errors];
    }

    public static function availabilityOptions(): array
    {
        return self::AVAILABILITY_OPTIONS;
    }

    public static function bagActionOptions(): array
    {
        return self::BAG_ACTION_OPTIONS;
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
