<?php

namespace App\Models;

class CommemorativeDate
{
    public ?int $id = null;
    public string $name = '';
    public int $day = 1;
    public int $month = 1;
    public ?int $year = null;
    public string $scope = 'brasil';
    public ?string $category = null;
    public ?string $description = null;
    public ?string $source = null;
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $item->name = (string) ($data['name'] ?? '');
        $item->day = (int) ($data['day'] ?? 1);
        $item->month = (int) ($data['month'] ?? 1);
        $item->year = isset($data['year']) && $data['year'] !== '' ? (int) $data['year'] : null;
        $item->scope = (string) ($data['scope'] ?? 'brasil');
        $item->category = self::nullableString($data['category'] ?? null);
        $item->description = self::nullableString($data['description'] ?? null);
        $item->source = self::nullableString($data['source'] ?? null);
        $item->status = (string) ($data['status'] ?? 'ativo');

        return $item;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':day' => $this->day,
            ':month' => $this->month,
            ':year' => $this->year,
            ':scope' => $this->scope,
            ':category' => $this->category,
            ':description' => $this->description,
            ':source' => $this->source,
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
