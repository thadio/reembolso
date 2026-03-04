<?php

namespace App\Models;

class ConsignmentIntake
{
    public ?int $id = null;
    /** Pessoa que atua como fornecedor/consignante */
    public int $personId = 0;
    /**
     * Código legado de fornecedor. Mantido para compatibilidade com tabelas antigas.
     */
    public ?int $vendorId = null;
    public string $receivedAt = '';
    public ?string $notes = null;

    public static function fromArray(array $data): self
    {
        $intake = new self();
        $intake->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $intake->personId = isset($data['pessoa_id']) ? (int) $data['pessoa_id'] : (int) ($data['person_id'] ?? 0);

        $legacyVendor = isset($data['vendor_id']) ? (int) $data['vendor_id'] : (int) ($data['vendorId'] ?? 0);
        if ($legacyVendor <= 0) {
            $legacyVendor = isset($data['supplier_code']) ? (int) $data['supplier_code'] : 0;
        }
        $intake->vendorId = $legacyVendor > 0 ? $legacyVendor : null;
        $intake->receivedAt = (string) ($data['received_at'] ?? $data['receivedAt'] ?? '');
        $intake->notes = self::nullableString($data['notes'] ?? null);

        return $intake;
    }

    public function toDbParams(): array
    {
        return [
            ':pessoa_id' => $this->personId > 0 ? $this->personId : null,
            ':received_at' => $this->receivedAt,
            ':notes' => $this->notes,
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
