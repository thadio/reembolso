<?php

namespace App\Services;

use App\Models\VoucherAccount;
use App\Support\Input;

class VoucherAccountService
{
    public const TYPE_OPTIONS = [
        'cupom' => 'Cupom',
        'credito' => 'Credito',
    ];

    /**
     * @return array{0: VoucherAccount, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : 0;
        $personId = 0;
        if (isset($input['pessoa_id']) && $input['pessoa_id'] !== '') {
            $personId = (int) $input['pessoa_id'];
        } elseif (isset($input['person_id']) && $input['person_id'] !== '') {
            $personId = (int) $input['person_id'];
        }

        if ($personId <= 0 && $customerId > 0) {
            $personId = $customerId;
        }
        if ($customerId <= 0 && $personId > 0) {
            $customerId = $personId;
        }

        if ($personId <= 0) {
            $errors[] = 'Cliente e obrigatorio.';
        }

        $label = (string) ($input['label'] ?? '');
        if ($label === '') {
            $errors[] = 'Identificacao e obrigatoria.';
        }

        $type = (string) ($input['type'] ?? 'credito');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo invalido.';
        }

        $code = trim((string) ($input['code'] ?? ''));
        if ($type === 'cupom' && $code === '') {
            $errors[] = 'Codigo do cupom e obrigatorio.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status invalido.';
        }

        $balance = $this->normalizeMoney($input['balance'] ?? null);
        if ($balance === null) {
            $balance = 0.0;
        }

        $account = VoucherAccount::fromArray([
            'id' => $input['id'] ?? null,
            'pessoa_id' => $personId,
            'person_id' => $personId,
            'customer_id' => $customerId,
            'label' => $label,
            'type' => $type,
            'code' => $code !== '' ? $code : null,
            'description' => $input['description'] ?? null,
            'status' => $status,
            'balance' => $balance,
        ]);

        return [$account, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
