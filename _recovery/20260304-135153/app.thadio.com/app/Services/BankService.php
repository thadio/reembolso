<?php

namespace App\Services;

use App\Models\Bank;
use App\Support\Input;

class BankService
{
    /**
     * @return array{0: Bank, 1: array<int, string>}
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

        $bank = Bank::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'code' => $input['code'] ?? null,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$bank, $errors];
    }
}
