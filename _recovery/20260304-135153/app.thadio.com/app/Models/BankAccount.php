<?php

namespace App\Models;

class BankAccount
{
    public ?int $id = null;
    public int $bankId = 0;
    public string $label = '';
    public ?string $holder = null;
    public ?string $branch = null;
    public ?string $accountNumber = null;
    public ?string $pixKey = null;
    public ?string $pixKeyType = null;
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $account = new self();
        $account->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $account->bankId = isset($data['bank_id']) ? (int) $data['bank_id'] : (int) ($data['bankId'] ?? 0);
        $account->label = (string) ($data['label'] ?? '');
        $account->holder = self::nullableString($data['holder'] ?? null);
        $account->branch = self::nullableString($data['branch'] ?? null);
        $account->accountNumber = self::nullableString($data['account_number'] ?? $data['accountNumber'] ?? null);
        $account->pixKey = self::nullableString($data['pix_key'] ?? $data['pixKey'] ?? null);
        $account->pixKeyType = self::nullableString($data['pix_key_type'] ?? $data['pixKeyType'] ?? null);
        $account->description = self::nullableString($data['description'] ?? null);
        $account->status = (string) ($data['status'] ?? 'ativo');

        return $account;
    }

    public function toDbParams(): array
    {
        return [
            ':bank_id' => $this->bankId,
            ':label' => $this->label,
            ':holder' => $this->holder,
            ':branch' => $this->branch,
            ':account_number' => $this->accountNumber,
            ':pix_key' => $this->pixKey,
            ':pix_key_type' => $this->pixKeyType,
            ':description' => $this->description,
            ':status' => $this->status,
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
