<?php

namespace App\Models;

class FinanceCategory
{
    public ?int $id = null;
    public string $name = '';
    public string $type = 'ambos';
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $category = new self();
        $category->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $category->name = (string) ($data['name'] ?? '');
        $category->type = (string) ($data['type'] ?? 'ambos');
        $category->description = self::nullableString($data['description'] ?? null);
        $category->status = (string) ($data['status'] ?? 'ativo');

        return $category;
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
