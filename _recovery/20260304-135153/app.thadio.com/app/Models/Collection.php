<?php

namespace App\Models;

class Collection
{
    public ?int $id = null;
    public string $name = '';
    public ?string $mainMenuImage = null;
    public string $externalId = '';
    public string $slug = '';
    public string $pageUrl = '';

    public static function fromArray(array $data): self
    {
        $collection = new self();
        $collection->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $collection->name = (string) ($data['name'] ?? '');
        $collection->mainMenuImage = self::nullable($data['main_menu_image'] ?? $data['mainMenuImage'] ?? null);
        $collection->externalId = (string) ($data['external_id'] ?? $data['externalId'] ?? '');
        $collection->slug = (string) ($data['slug'] ?? '');
        $collection->pageUrl = (string) ($data['page_url'] ?? $data['pageUrl'] ?? '');

        return $collection;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':main_menu_image' => $this->mainMenuImage,
            ':external_id' => $this->externalId,
            ':slug' => $this->slug,
            ':page_url' => $this->pageUrl,
        ];
    }

    private static function nullable($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }
}
