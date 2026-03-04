<?php

namespace App\Models;

use App\Support\Input;

class PaymentMethod
{
    public ?int $id = null;
    public string $name = '';
    public string $type = 'cash';
    public ?string $description = null;
    public string $status = 'ativo';
    public string $feeType = 'none';
    public float $feeValue = 0.0;
    public bool $requiresBankAccount = false;
    public bool $requiresTerminal = false;

    public static function fromArray(array $data): self
    {
        $method = new self();
        $method->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $method->name = (string) ($data['name'] ?? '');
        $method->type = (string) ($data['type'] ?? 'cash');
        $method->description = self::nullableString($data['description'] ?? null);
        $method->status = (string) ($data['status'] ?? 'ativo');
        $method->feeType = (string) ($data['fee_type'] ?? $data['feeType'] ?? 'none');
        $feeValue = Input::parseNumber($data['fee_value'] ?? $data['feeValue'] ?? null);
        $method->feeValue = $feeValue ?? 0.0;
        $method->requiresBankAccount = !empty($data['requires_bank_account'] ?? $data['requiresBankAccount'] ?? false);
        $method->requiresTerminal = !empty($data['requires_terminal'] ?? $data['requiresTerminal'] ?? false);

        return $method;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':type' => $this->type,
            ':description' => $this->description,
            ':status' => $this->status,
            ':fee_type' => $this->feeType,
            ':fee_value' => $this->feeValue,
            ':requires_bank_account' => $this->requiresBankAccount ? 1 : 0,
            ':requires_terminal' => $this->requiresTerminal ? 1 : 0,
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
}
