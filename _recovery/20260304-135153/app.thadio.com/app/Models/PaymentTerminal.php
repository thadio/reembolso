<?php

namespace App\Models;

class PaymentTerminal
{
    public ?int $id = null;
    public string $name = '';
    public string $type = 'both';
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $terminal = new self();
        $terminal->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $terminal->name = (string) ($data['name'] ?? '');
        $terminal->type = (string) ($data['type'] ?? 'both');
        $terminal->description = self::nullableString($data['description'] ?? null);
        $terminal->status = (string) ($data['status'] ?? 'ativo');

        return $terminal;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':type' => $this->type,
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
