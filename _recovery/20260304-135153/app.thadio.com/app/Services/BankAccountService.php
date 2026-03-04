<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Support\Input;

class BankAccountService
{
    public const PIX_KEY_TYPE_OPTIONS = [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'random' => 'Chave aleatoria',
        'other' => 'Outro',
    ];

    /**
     * @return array{0: BankAccount, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $bankId = isset($input['bank_id']) ? (int) $input['bank_id'] : 0;
        if ($bankId <= 0) {
            $errors[] = 'Banco é obrigatório.';
        }

        $label = (string) ($input['label'] ?? '');
        if ($label === '') {
            $errors[] = 'Identificação da conta é obrigatória.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $pixKey = $input['pix_key'] ?? null;
        $pixKeyType = $input['pix_key_type'] ?? null;
        if ($pixKey !== null && $pixKey !== '') {
            if ($pixKeyType === null || $pixKeyType === '') {
                $errors[] = 'Informe o tipo da chave PIX.';
            } elseif (!array_key_exists($pixKeyType, self::PIX_KEY_TYPE_OPTIONS)) {
                $errors[] = 'Tipo de chave PIX inválido.';
            }
        } elseif ($pixKeyType !== null && $pixKeyType !== '') {
            $errors[] = 'Informe a chave PIX.';
        }

        $account = BankAccount::fromArray([
            'id' => $input['id'] ?? null,
            'bank_id' => $bankId,
            'label' => $label,
            'holder' => $input['holder'] ?? null,
            'branch' => $input['branch'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'pix_key' => $pixKey,
            'pix_key_type' => $pixKeyType,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$account, $errors];
    }

    public static function pixKeyTypeOptions(): array
    {
        return self::PIX_KEY_TYPE_OPTIONS;
    }
}
