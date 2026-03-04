<?php

namespace App\Models;

class SalesChannel
{
    public ?int $id = null;
    public string $name = '';
    public ?string $description = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $channel = new self();
        $channel->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $channel->name = (string) ($data['name'] ?? '');
        $channel->description = self::nullableString($data['description'] ?? null);
        $channel->status = (string) ($data['status'] ?? 'ativo');

        return $channel;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
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
