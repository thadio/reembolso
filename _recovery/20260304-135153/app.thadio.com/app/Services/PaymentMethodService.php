<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Support\Input;

class PaymentMethodService
{
    public const TYPE_OPTIONS = [
        'cash' => 'Dinheiro',
        'pix' => 'PIX',
        'debit_card' => 'Cartão de débito',
        'credit_card' => 'Cartão de crédito',
        'voucher' => 'Cupom/crédito',
        'transfer' => 'Transferência',
        'other' => 'Outro',
    ];

    public const FEE_TYPE_OPTIONS = [
        'none' => 'Sem taxa',
        'fixed' => 'Valor fixo (R$)',
        'percent' => 'Percentual (%)',
    ];

    /**
     * @return array{0: PaymentMethod, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $type = (string) ($input['type'] ?? 'cash');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo inválido.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $feeType = (string) ($input['fee_type'] ?? 'none');
        if (!array_key_exists($feeType, self::FEE_TYPE_OPTIONS)) {
            $errors[] = 'Tipo de taxa inválido.';
        }

        $feeValue = $this->normalizeMoney($input['fee_value'] ?? null);
        if ($feeValue === null) {
            $feeValue = 0.0;
        }
        if ($feeValue < 0) {
            $errors[] = 'Valor da taxa inválido.';
        }

        $requiresBankAccount = !empty($input['requires_bank_account']);
        $requiresTerminal = !empty($input['requires_terminal']);

        $method = PaymentMethod::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'type' => $type,
            'description' => $input['description'] ?? null,
            'status' => $status,
            'fee_type' => $feeType,
            'fee_value' => $feeValue,
            'requires_bank_account' => $requiresBankAccount,
            'requires_terminal' => $requiresTerminal,
        ]);

        return [$method, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function feeTypeOptions(): array
    {
        return self::FEE_TYPE_OPTIONS;
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
