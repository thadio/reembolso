<?php

namespace App\Models;

use App\Support\Input;

class OrderReturn
{
    public ?int $id = null;
    public int $orderId = 0;
    public ?int $pessoaId = null;
    public ?string $customerName = null;
    public ?string $customerEmail = null;
    public string $status = 'requested';
    public string $returnMethod = 'pending';
    public string $refundMethod = 'none';
    public string $refundStatus = 'pending';
    public float $refundAmount = 0.0;
    public ?int $voucherAccountId = null;
    public ?string $trackingCode = null;
    public ?string $expectedAt = null;
    public ?string $receivedAt = null;
    public ?string $restockedAt = null;
    public ?string $notes = null;
    public ?int $createdBy = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    /** @var array<int, OrderReturnItem> */
    public array $items = [];

    public static function fromArray(array $data): self
    {
        $return = new self();
        $return->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $return->orderId = isset($data['order_id']) ? (int) $data['order_id'] : (int) ($data['orderId'] ?? 0);
        $return->pessoaId = isset($data['pessoa_id'])
            ? (int) $data['pessoa_id']
            : (isset($data['pessoaId']) ? (int) $data['pessoaId'] : null);
        $return->customerName = self::nullableString($data['customer_name'] ?? $data['customerName'] ?? null);
        $return->customerEmail = self::nullableString($data['customer_email'] ?? $data['customerEmail'] ?? null);
        $return->status = (string) ($data['status'] ?? 'requested');
        $return->returnMethod = (string) ($data['return_method'] ?? $data['returnMethod'] ?? 'pending');
        $return->refundMethod = (string) ($data['refund_method'] ?? $data['refundMethod'] ?? 'none');
        $return->refundStatus = (string) ($data['refund_status'] ?? $data['refundStatus'] ?? 'pending');
        $refundAmount = Input::parseNumber($data['refund_amount'] ?? $data['refundAmount'] ?? null);
        $return->refundAmount = $refundAmount ?? 0.0;
        $return->voucherAccountId = isset($data['voucher_account_id']) ? (int) $data['voucher_account_id'] : (isset($data['voucherAccountId']) ? (int) $data['voucherAccountId'] : null);
        $return->trackingCode = self::nullableString($data['tracking_code'] ?? $data['trackingCode'] ?? null);
        $return->expectedAt = self::nullableString($data['expected_at'] ?? $data['expectedAt'] ?? null);
        $return->receivedAt = self::nullableString($data['received_at'] ?? $data['receivedAt'] ?? null);
        $return->restockedAt = self::nullableString($data['restocked_at'] ?? $data['restockedAt'] ?? null);
        $return->notes = self::nullableString($data['notes'] ?? null);
        $return->createdBy = isset($data['created_by']) ? (int) $data['created_by'] : (isset($data['createdBy']) ? (int) $data['createdBy'] : null);
        $return->createdAt = self::nullableString($data['created_at'] ?? $data['createdAt'] ?? null);
        $return->updatedAt = self::nullableString($data['updated_at'] ?? $data['updatedAt'] ?? null);

        $items = $data['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $itemData) {
                if (!is_array($itemData)) {
                    continue;
                }
                $return->items[] = OrderReturnItem::fromArray($itemData);
            }
        }

        return $return;
    }

    public function toDbParams(): array
    {
        return [
            ':order_id' => $this->orderId,
            ':pessoa_id' => $this->pessoaId,
            ':customer_name' => $this->customerName,
            ':customer_email' => $this->customerEmail,
            ':status' => $this->status,
            ':return_method' => $this->returnMethod,
            ':refund_method' => $this->refundMethod,
            ':refund_status' => $this->refundStatus,
            ':refund_amount' => $this->refundAmount,
            ':voucher_account_id' => $this->voucherAccountId,
            ':tracking_code' => $this->trackingCode,
            ':expected_at' => $this->expectedAt,
            ':received_at' => $this->receivedAt,
            ':restocked_at' => $this->restockedAt,
            ':notes' => $this->notes,
            ':created_by' => $this->createdBy,
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
