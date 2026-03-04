<?php

namespace App\Models;

class Carrier
{
    public ?int $id = null;
    public string $name = '';
    public string $carrierType = 'transportadora';
    public ?string $siteUrl = null;
    public ?string $trackingUrlTemplate = null;
    public string $status = 'ativo';
    public ?string $notes = null;

    public static function fromArray(array $data): self
    {
        $carrier = new self();
        $carrier->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $carrier->name = trim((string) ($data['name'] ?? ''));
        $carrier->carrierType = trim((string) ($data['carrier_type'] ?? ($data['carrierType'] ?? 'transportadora')));
        if ($carrier->carrierType === '') {
            $carrier->carrierType = 'transportadora';
        }
        $carrier->siteUrl = self::nullableString($data['site_url'] ?? ($data['siteUrl'] ?? null));
        $carrier->trackingUrlTemplate = self::nullableString($data['tracking_url_template'] ?? ($data['trackingUrlTemplate'] ?? null));
        $carrier->status = trim((string) ($data['status'] ?? 'ativo'));
        if ($carrier->status === '') {
            $carrier->status = 'ativo';
        }
        $carrier->notes = self::nullableString($data['notes'] ?? null);
        return $carrier;
    }

    public function toDbParams(): array
    {
        return [
            ':name' => $this->name,
            ':carrier_type' => $this->carrierType,
            ':site_url' => $this->siteUrl,
            ':tracking_url_template' => $this->trackingUrlTemplate,
            ':status' => $this->status,
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
