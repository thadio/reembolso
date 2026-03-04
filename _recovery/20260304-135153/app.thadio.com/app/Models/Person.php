<?php

namespace App\Models;

class Person
{
    public ?int $id = null;
    public string $fullName = '';
    public ?string $email = null;
    public ?string $email2 = null;
    public ?string $phone = null;
    public ?string $cpfCnpj = null;
    public ?string $pixKey = null;
    public ?string $instagram = null;
    public ?string $country = null;
    public ?string $state = null;
    public ?string $city = null;
    public ?string $neighborhood = null;
    public ?string $number = null;
    public ?string $street = null;
    public ?string $street2 = null;
    public ?string $zip = null;
    public string $status = 'ativo';
    /** @var array<string, mixed>|null */
    public ?array $metadata = null;
    public ?string $lastSyncedAt = null;

    public static function fromArray(array $data): self
    {
        $person = new self();
        $person->id = self::nullableInt($data['id'] ?? null);
        $person->fullName = (string) ($data['full_name'] ?? $data['fullName'] ?? '');
        $person->email = self::nullableString($data['email'] ?? null);
        $person->email2 = self::nullableString($data['email2'] ?? null);
        $person->phone = self::nullableString($data['phone'] ?? null);
        $person->cpfCnpj = self::nullableString($data['cpf_cnpj'] ?? $data['cpfCnpj'] ?? null);
        $person->pixKey = self::nullableString($data['pix_key'] ?? $data['pixKey'] ?? null);
        $person->instagram = self::nullableString($data['instagram'] ?? null);
        $person->country = self::nullableString($data['country'] ?? null);
        $person->state = self::nullableString($data['state'] ?? null);
        $person->city = self::nullableString($data['city'] ?? null);
        $person->neighborhood = self::nullableString($data['neighborhood'] ?? null);
        $person->number = self::nullableString($data['number'] ?? null);
        $person->street = self::nullableString($data['street'] ?? null);
        $person->street2 = self::nullableString($data['street2'] ?? null);
        $person->zip = self::nullableString($data['zip'] ?? null);
        $person->status = (string) ($data['status'] ?? 'ativo');
        $person->metadata = self::decodeMetadata($data['metadata'] ?? null);
        $person->lastSyncedAt = self::nullableString($data['last_synced_at'] ?? $data['lastSyncedAt'] ?? null);
        return $person;
    }

    public function toDbParams(): array
    {
        $metadata = $this->metadata;
        $metadataJson = null;
        if ($metadata !== null) {
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metadataJson = $encoded === false ? null : $encoded;
        }

        return [
            ':id' => $this->id,
            ':full_name' => $this->fullName,
            ':email' => $this->email,
            ':email2' => $this->email2,
            ':phone' => $this->phone,
            ':cpf_cnpj' => $this->cpfCnpj,
            ':pix_key' => $this->pixKey,
            ':instagram' => $this->instagram,
            ':country' => $this->country,
            ':state' => $this->state,
            ':city' => $this->city,
            ':neighborhood' => $this->neighborhood,
            ':number' => $this->number,
            ':street' => $this->street,
            ':street2' => $this->street2,
            ':zip' => $this->zip,
            ':status' => $this->status,
            ':metadata' => $metadataJson,
            ':last_synced_at' => $this->lastSyncedAt,
        ];
    }
    
    /**
     * Converter para array (para auditoria)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'email2' => $this->email2,
            'phone' => $this->phone,
            'cpf_cnpj' => $this->cpfCnpj,
            'pix_key' => $this->pixKey,
            'instagram' => $this->instagram,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'neighborhood' => $this->neighborhood,
            'number' => $this->number,
            'street' => $this->street,
            'street2' => $this->street2,
            'zip' => $this->zip,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'last_synced_at' => $this->lastSyncedAt,
        ];
    }

    private static function nullableString($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }

    private static function nullableInt($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeMetadata($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
