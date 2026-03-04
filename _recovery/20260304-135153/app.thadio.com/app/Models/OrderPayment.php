<?php

namespace App\Models;

class OrderPayment
{
    public string $status = 'none';
    public ?string $method = null;
    public ?string $methodTitle = null;
    public ?string $transactionId = null;
    public ?string $paidAt = null;
    public bool $setPaid = false;

    public static function fromArray(array $data): self
    {
        $payment = new self();
        $payment->status = (string) ($data['payment_status'] ?? $data['status'] ?? 'none');
        $payment->method = self::nullableString($data['payment_method'] ?? $data['method'] ?? null);
        $payment->methodTitle = self::nullableString($data['payment_method_title'] ?? $data['methodTitle'] ?? null);
        $payment->transactionId = self::nullableString($data['transaction_id'] ?? $data['transactionId'] ?? null);
        $payment->paidAt = self::nullableString($data['paid_at'] ?? $data['paidAt'] ?? null);
        $setPaid = $data['set_paid'] ?? $data['setPaid'] ?? null;
        if ($setPaid !== null) {
            $payment->setPaid = $setPaid === true || $setPaid === '1' || $setPaid === 1 || $setPaid === 'on';
        } else {
            $payment->setPaid = $payment->status === 'paid';
        }
        return $payment;
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
