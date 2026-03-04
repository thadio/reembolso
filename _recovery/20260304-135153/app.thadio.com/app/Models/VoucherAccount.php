<?php

namespace App\Models;

use App\Support\Input;

class VoucherAccount
{
    public ?int $id = null;
    public int $personId = 0;
    public int $customerId = 0;
    public ?string $customerName = null;
    public ?string $customerEmail = null;
    public string $label = '';
    public string $type = 'credito';
    public ?string $scope = null;
    public ?string $code = null;
    public ?string $description = null;
    public string $status = 'ativo';
    public float $balance = 0.0;
    public ?string $deletedAt = null;
    public ?string $deletedBy = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public static function fromArray(array $data): self
    {
        $account = new self();
        $account->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;

        $personId = 0;
        if (isset($data['pessoa_id'])) {
            $personId = (int) $data['pessoa_id'];
        } elseif (isset($data['person_id'])) {
            $personId = (int) $data['person_id'];
        } elseif (isset($data['personId'])) {
            $personId = (int) $data['personId'];
        }
        $account->personId = $personId > 0 ? $personId : 0;

        $customerId = 0;
        if (isset($data['customer_id'])) {
            $customerId = (int) $data['customer_id'];
        } elseif (isset($data['customerId'])) {
            $customerId = (int) $data['customerId'];
        }
        if ($customerId <= 0) {
            $customerId = $account->personId;
        }
        $account->customerId = $customerId > 0 ? $customerId : 0;

        $account->customerName = self::nullableString($data['customer_name'] ?? $data['customerName'] ?? null);
        $account->customerEmail = self::nullableString($data['customer_email'] ?? $data['customerEmail'] ?? null);
        $account->label = (string) ($data['label'] ?? '');
        $account->type = (string) ($data['type'] ?? 'credito');
        $account->scope = self::nullableString($data['scope'] ?? null);
        $account->code = self::nullableString($data['code'] ?? null);
        $account->description = self::nullableString($data['description'] ?? null);
        $account->status = (string) ($data['status'] ?? 'ativo');
        $balance = Input::parseNumber($data['balance'] ?? null);
        $account->balance = $balance ?? 0.0;
        $account->deletedAt = self::nullableString($data['deleted_at'] ?? $data['deletedAt'] ?? null);
        $account->deletedBy = self::nullableString($data['deleted_by'] ?? $data['deletedBy'] ?? null);
        $account->createdAt = self::nullableString($data['created_at'] ?? $data['createdAt'] ?? null);
        $account->updatedAt = self::nullableString($data['updated_at'] ?? $data['updatedAt'] ?? null);

        return $account;
    }

    public function toDbParams(): array
    {
        return [
            ':pessoa_id' => $this->personId,
            ':customer_id' => $this->customerId > 0 ? $this->customerId : $this->personId,
            ':customer_name' => $this->customerName,
            ':customer_email' => $this->customerEmail,
            ':label' => $this->label,
            ':type' => $this->type,
            ':scope' => $this->scope,
            ':code' => $this->code,
            ':description' => $this->description,
            ':status' => $this->status,
            ':balance' => $this->balance,
            ':deleted_at' => $this->deletedAt,
            ':deleted_by' => $this->deletedBy,
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
