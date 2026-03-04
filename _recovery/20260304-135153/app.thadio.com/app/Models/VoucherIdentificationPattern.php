<?php

namespace App\Models;

class VoucherIdentificationPattern
{
    public ?int $id = null;
    public string $label = '';
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $pattern = new self();
        $pattern->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $pattern->label = (string) ($data['label'] ?? '');
        $pattern->description = self::nullableString($data['description'] ?? null);
        $pattern->status = (string) ($data['status'] ?? 'ativo');

        return $pattern;
    }

    public function toDbParams(): array
    {
        return [
            ':label' => $this->label,
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
