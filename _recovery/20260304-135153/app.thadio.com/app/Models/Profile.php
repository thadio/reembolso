<?php

namespace App\Models;

class Profile
{
    public ?int $id = null;
    public string $name = '';
    public ?string $description = null;
    public string $status = 'ativo';
    /** @var array<string, array<int, string>> */
    public array $permissions = [];

    public static function fromArray(array $data): self
    {
        $profile = new self();
        $profile->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $profile->name = (string) ($data['name'] ?? '');
        $profile->description = self::nullable($data['description'] ?? null);
        $profile->status = (string) ($data['status'] ?? 'ativo');
        $permissions = self::decodePermissions($data['permissions'] ?? $data['permissions_json'] ?? null);
        $profile->permissions = \App\Support\Permissions::upgradeLegacy($permissions);
        return $profile;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':description' => $this->description,
            ':status' => $this->status,
            ':permissions' => json_encode($this->permissions, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function nullable($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }

    private static function decodePermissions($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
