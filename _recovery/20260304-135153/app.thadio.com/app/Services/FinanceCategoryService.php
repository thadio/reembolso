<?php

namespace App\Services;

use App\Models\FinanceCategory;
use App\Support\Input;

class FinanceCategoryService
{
    public const TYPE_OPTIONS = [
        'pagar' => 'A pagar',
        'receber' => 'A receber',
        'ambos' => 'Ambos',
    ];

    /**
     * @return array{0: FinanceCategory, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $type = (string) ($input['type'] ?? 'ambos');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo inválido.';
        }

        $status = (string) ($input['status'] ?? 'ativo');
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $category = FinanceCategory::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'type' => $type,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$category, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }
}
