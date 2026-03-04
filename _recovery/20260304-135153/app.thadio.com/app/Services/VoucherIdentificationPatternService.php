<?php

namespace App\Services;

use App\Models\VoucherIdentificationPattern;
use App\Support\Input;

class VoucherIdentificationPatternService
{
    /**
     * @return array{0: VoucherIdentificationPattern, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $label = (string) ($input['label'] ?? '');
        if ($label === '') {
            $errors[] = 'Identificação é obrigatória.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $pattern = VoucherIdentificationPattern::fromArray([
            'id' => $input['id'] ?? null,
            'label' => $label,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$pattern, $errors];
    }
}
