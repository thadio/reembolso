<?php

namespace App\Services;

use App\Models\PaymentTerminal;
use App\Support\Input;

class PaymentTerminalService
{
    public const TYPE_OPTIONS = [
        'debit' => 'Cartão de débito',
        'credit' => 'Cartão de crédito',
        'both' => 'Débito e crédito',
        'link' => 'Link de pagamento',
        'other' => 'Outro',
    ];

    /**
     * @return array{0: PaymentTerminal, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $type = (string) ($input['type'] ?? 'both');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo inválido.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $terminal = PaymentTerminal::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'type' => $type,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$terminal, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }
}
