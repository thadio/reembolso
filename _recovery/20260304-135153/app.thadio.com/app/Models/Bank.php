<?php

namespace App\Models;

class Bank
{
    public ?int $id = null;
    public string $name = '';
    public ?string $code = null;
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $bank = new self();
        $bank->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $bank->name = (string) ($data['name'] ?? '');
        $bank->code = self::nullableString($data['code'] ?? null);
        $bank->description = self::nullableString($data['description'] ?? null);
        $bank->status = (string) ($data['status'] ?? 'ativo');

        return $bank;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':code' => $this->code,
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
