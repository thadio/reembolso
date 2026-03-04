<?php

namespace App\Services;

use App\Models\Bag;
use App\Support\Input;

class BagService
{
    public const STATUS_OPTIONS = [
        'aberta' => 'Aberta',
        'fechada' => 'Fechada',
        'despachada' => 'Despachada',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];

    /**
     * @return array{0: Bag, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $personId = (int) ($input['pessoa_id'] ?? 0);
        if ($personId <= 0) {
            $errors[] = 'Cliente é obrigatório.';
        }

        $status = $input['status'] ?? 'aberta';
        if (!array_key_exists($status, self::STATUS_OPTIONS)) {
            $errors[] = 'Status inválido.';
        }

        $openedAt = $this->normalizeDateTime($input['opened_at'] ?? '');
        if ($openedAt === null) {
            $openedAt = date('Y-m-d H:i:s');
        }

        $expectedCloseAt = $this->normalizeDateTime($input['expected_close_at'] ?? '');
        if ($expectedCloseAt === null) {
            $expectedCloseAt = date('Y-m-d H:i:s', strtotime($openedAt . ' +30 days'));
        }

        $closedAt = $this->normalizeDateTime($input['closed_at'] ?? '');

        $feeValue = $this->normalizeMoney($input['opening_fee_value'] ?? null);
        if ($feeValue === null) {
            $feeValue = 0.0;
        }
        if ($feeValue < 0) {
            $errors[] = 'Valor de abertura inválido.';
        }

        $feePaid = !empty($input['opening_fee_paid']) && ($input['opening_fee_paid'] === '1' || $input['opening_fee_paid'] === 'on');
        $feePaidAt = $this->normalizeDateTime($input['opening_fee_paid_at'] ?? '');
        if ($feePaid && $feePaidAt === null) {
            $feePaidAt = date('Y-m-d H:i:s');
        }
        if (!$feePaid) {
            $feePaidAt = null;
        }

        $bag = Bag::fromArray([
            'id' => $input['id'] ?? null,
            'pessoa_id' => $personId,
            'customer_name' => $input['customer_name'] ?? null,
            'customer_email' => $input['customer_email'] ?? null,
            'status' => $status,
            'opened_at' => $openedAt,
            'expected_close_at' => $expectedCloseAt,
            'closed_at' => $closedAt,
            'opening_fee_value' => $feeValue,
            'opening_fee_paid' => $feePaid ? 1 : 0,
            'opening_fee_paid_at' => $feePaidAt,
            'notes' => $input['notes'] ?? null,
        ]);

        return [$bag, $errors];
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (strpos($value, 'T') !== false) {
            $value = str_replace('T', ' ', $value);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
