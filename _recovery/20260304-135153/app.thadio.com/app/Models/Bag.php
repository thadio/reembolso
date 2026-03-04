<?php

namespace App\Models;

use App\Support\Input;

class Bag
{
    public ?int $id = null;
    public int $personId = 0;
    public ?string $customerName = null;
    public ?string $customerEmail = null;
    public string $status = 'aberta';
    public ?string $openedAt = null;
    public ?string $expectedCloseAt = null;
    public ?string $closedAt = null;
    public float $openingFeeValue = 0.0;
    public bool $openingFeePaid = false;
    public ?string $openingFeePaidAt = null;
    public ?string $notes = null;

    public static function fromArray(array $data): self
    {
        $bag = new self();
        $bag->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $bag->personId = isset($data['pessoa_id'])
            ? (int) $data['pessoa_id']
            : (isset($data['personId']) ? (int) $data['personId'] : 0);

        $bag->customerName = self::nullableString($data['customer_name'] ?? $data['customerName'] ?? null);
        $bag->customerEmail = self::nullableString($data['customer_email'] ?? $data['customerEmail'] ?? null);
        $bag->status = (string) ($data['status'] ?? 'aberta');
        $bag->openedAt = self::nullableString($data['opened_at'] ?? $data['openedAt'] ?? null);
        $bag->expectedCloseAt = self::nullableString($data['expected_close_at'] ?? $data['expectedCloseAt'] ?? null);
        $bag->closedAt = self::nullableString($data['closed_at'] ?? $data['closedAt'] ?? null);
        $feeValue = Input::parseNumber($data['opening_fee_value'] ?? $data['openingFeeValue'] ?? null);
        $bag->openingFeeValue = $feeValue ?? 0.0;
        $bag->openingFeePaid = (bool) ($data['opening_fee_paid'] ?? $data['openingFeePaid'] ?? false);
        $bag->openingFeePaidAt = self::nullableString($data['opening_fee_paid_at'] ?? $data['openingFeePaidAt'] ?? null);
        $bag->notes = self::nullableString($data['notes'] ?? null);

        return $bag;
    }

    public function toDbParams(): array
    {
        return [
            ':pessoa_id' => $this->personId,
            ':customer_name' => $this->customerName,
            ':customer_email' => $this->customerEmail,
            ':status' => $this->status,
            ':opened_at' => $this->openedAt,
            ':expected_close_at' => $this->expectedCloseAt,
            ':closed_at' => $this->closedAt,
            ':opening_fee_value' => $this->openingFeeValue,
            ':opening_fee_paid' => $this->openingFeePaid ? 1 : 0,
            ':opening_fee_paid_at' => $this->openingFeePaidAt,
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
