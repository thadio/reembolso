<?php

namespace App\Models;

use App\Support\Input;

class FinanceEntry
{
    public ?int $id = null;
    public string $type = 'pagar';
    public string $description = '';
    public ?int $categoryId = null;
    public ?int $supplierPessoaId = null;
    public ?int $lotId = null;
    public ?int $orderId = null;
    public float $amount = 0.0;
    public ?string $dueDate = null;
    public string $status = 'pendente';
    public ?string $paidAt = null;
    public ?float $paidAmount = null;
    public ?int $bankAccountId = null;
    public ?int $paymentMethodId = null;
    public ?int $paymentTerminalId = null;
    public ?string $notes = null;

    public static function fromArray(array $data): self
    {
        $entry = new self();
        $entry->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $entry->type = (string) ($data['type'] ?? 'pagar');
        $entry->description = (string) ($data['description'] ?? '');
        $entry->categoryId = self::nullableInt($data['category_id'] ?? $data['categoryId'] ?? null);
        $entry->supplierPessoaId = self::nullableInt($data['supplier_pessoa_id'] ?? $data['supplierPessoaId'] ?? null);
        $entry->lotId = self::nullableInt($data['lot_id'] ?? $data['lotId'] ?? null);
        $entry->orderId = self::nullableInt($data['order_id'] ?? $data['orderId'] ?? null);
        $amount = Input::parseNumber($data['amount'] ?? null);
        $entry->amount = $amount ?? 0.0;
        $entry->dueDate = self::nullableString($data['due_date'] ?? $data['dueDate'] ?? null);
        $entry->status = (string) ($data['status'] ?? 'pendente');
        $entry->paidAt = self::nullableString($data['paid_at'] ?? $data['paidAt'] ?? null);
        $entry->paidAmount = self::nullableFloat($data['paid_amount'] ?? $data['paidAmount'] ?? null);
        $entry->bankAccountId = self::nullableInt($data['bank_account_id'] ?? $data['bankAccountId'] ?? null);
        $entry->paymentMethodId = self::nullableInt($data['payment_method_id'] ?? $data['paymentMethodId'] ?? null);
        $entry->paymentTerminalId = self::nullableInt($data['payment_terminal_id'] ?? $data['paymentTerminalId'] ?? null);
        $entry->notes = self::nullableString($data['notes'] ?? null);

        return $entry;
    }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'description'         => $this->description,
            'category_id'         => $this->categoryId,
            'supplier_pessoa_id'  => $this->supplierPessoaId,
            'lot_id'              => $this->lotId,
            'order_id'            => $this->orderId,
            'amount'              => $this->amount,
            'due_date'            => $this->dueDate,
            'status'              => $this->status,
            'paid_at'             => $this->paidAt,
            'paid_amount'         => $this->paidAmount,
            'bank_account_id'     => $this->bankAccountId,
            'payment_method_id'   => $this->paymentMethodId,
            'payment_terminal_id' => $this->paymentTerminalId,
            'notes'               => $this->notes,
        ];
    }

    public function toDbParams(): array
    {
        return [
            ':type' => $this->type,
            ':description' => $this->description,
            ':category_id' => $this->categoryId,
            ':supplier_pessoa_id' => $this->supplierPessoaId,
            ':lot_id' => $this->lotId,
            ':order_id' => $this->orderId,
            ':amount' => $this->amount,
            ':due_date' => $this->dueDate,
            ':status' => $this->status,
            ':paid_at' => $this->paidAt,
            ':paid_amount' => $this->paidAmount,
            ':bank_account_id' => $this->bankAccountId,
            ':payment_method_id' => $this->paymentMethodId,
            ':payment_terminal_id' => $this->paymentTerminalId,
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

    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private static function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
