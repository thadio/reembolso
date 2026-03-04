<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Support\Input;

class FinanceEntryService
{
    public const TYPE_OPTIONS = [
        'receber' => 'A receber',
        'pagar' => 'A pagar',
    ];

    public const STATUS_OPTIONS = [
        'pendente' => 'Pendente',
        'parcial' => 'Parcial',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
    ];

    public const RECURRENCE_FREQUENCY_OPTIONS = [
        'mensal' => 'Mensal',
        'semanal' => 'Semanal',
        'anual' => 'Anual',
    ];

    /**
     * @param array<int, array<string, mixed>> $paymentMethods
     * @return array{0: FinanceEntry, 1: array<int, string>}
     */
    public function validate(array $input, array $paymentMethods = []): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $type = (string) ($input['type'] ?? 'pagar');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo inválido.';
        }

        $description = (string) ($input['description'] ?? '');
        if ($description === '') {
            $errors[] = 'Descrição é obrigatória.';
        }

        $amount = $this->normalizeMoney($input['amount'] ?? null);
        if ($amount === null || $amount <= 0) {
            $errors[] = 'Valor inválido.';
            $amount = 0.0;
        }

        $status = (string) ($input['status'] ?? 'pendente');
        if (!array_key_exists($status, self::STATUS_OPTIONS)) {
            $errors[] = 'Status inválido.';
        }

        $dueDate = $this->normalizeDate($input['due_date'] ?? null, 'Data de vencimento', $errors);
        $paidAt = $this->normalizeDateTime($input['paid_at'] ?? null, 'Data de pagamento', $errors);
        $paidAmount = $this->normalizeMoney($input['paid_amount'] ?? null);

        if ($paidAmount !== null && $paidAmount < 0) {
            $errors[] = 'Valor pago inválido.';
        }

        if ($paidAmount !== null && $paidAmount > $amount && $amount > 0) {
            $errors[] = 'Valor pago não pode ser maior que o valor total.';
        }

        $paymentMethodId = $this->normalizeId($input['payment_method_id'] ?? null);
        $bankAccountId = $this->normalizeId($input['bank_account_id'] ?? null);
        $paymentTerminalId = $this->normalizeId($input['payment_terminal_id'] ?? null);

        $methodMap = $this->mapById($paymentMethods);
        if ($paymentMethodId !== null && isset($methodMap[$paymentMethodId])) {
            $method = $methodMap[$paymentMethodId];
            if (!empty($method['requires_bank_account']) && $bankAccountId === null) {
                $errors[] = 'Método selecionado exige conta bancária.';
            }
            if (!empty($method['requires_terminal']) && $paymentTerminalId === null) {
                $errors[] = 'Método selecionado exige maquininha.';
            }
        }

        $now = date('Y-m-d H:i:s');
        if ($status === 'cancelado') {
            $paidAmount = null;
            $paidAt = null;
        } elseif ($status === 'pago') {
            if ($paidAmount === null || $paidAmount <= 0) {
                $paidAmount = $amount;
            }
            if ($paidAt === null) {
                $paidAt = $now;
            }
        } elseif ($status === 'parcial') {
            if ($paidAmount === null || $paidAmount <= 0) {
                $errors[] = 'Informe o valor pago para status parcial.';
            }
            if ($paidAt === null && $paidAmount !== null && $paidAmount > 0) {
                $paidAt = $now;
            }
        } else {
            if ($paidAmount !== null && $paidAmount > 0) {
                if ($paidAmount >= $amount) {
                    $status = 'pago';
                } else {
                    $status = 'parcial';
                }
                if ($paidAt === null) {
                    $paidAt = $now;
                }
            }
        }

        $entry = FinanceEntry::fromArray([
            'id' => $input['id'] ?? null,
            'type' => $type,
            'description' => $description,
            'category_id' => $input['category_id'] ?? null,
            'supplier_pessoa_id' => $input['supplier_pessoa_id'] ?? null,
            'lot_id' => $input['lot_id'] ?? null,
            'order_id' => $input['order_id'] ?? null,
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => $status,
            'paid_at' => $paidAt,
            'paid_amount' => $paidAmount,
            'bank_account_id' => $bankAccountId,
            'payment_method_id' => $paymentMethodId,
            'payment_terminal_id' => $paymentTerminalId,
            'notes' => $input['notes'] ?? null,
        ]);

        return [$entry, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function recurrenceFrequencyOptions(): array
    {
        return self::RECURRENCE_FREQUENCY_OPTIONS;
    }

    /**
     * @return array{enabled: bool, frequency: string, count: int}
     */
    public function parseRecurrence(array $input, array &$errors): array
    {
        $enabled = isset($input['recurrence_enabled']) && (string) $input['recurrence_enabled'] === '1';
        $frequency = (string) ($input['recurrence_frequency'] ?? 'mensal');
        $count = isset($input['recurrence_count']) ? (int) $input['recurrence_count'] : 1;

        if ($enabled) {
            if (!array_key_exists($frequency, self::RECURRENCE_FREQUENCY_OPTIONS)) {
                $errors[] = 'Frequência de recorrência inválida.';
            }

            if ($count < 2) {
                $errors[] = 'Informe a quantidade de repetições (mínimo 2) para recorrência.';
            } elseif ($count > 60) {
                $errors[] = 'Recorrência limitada a no máximo 60 repetições.';
            }

            $dueDate = trim((string) ($input['due_date'] ?? ''));
            if ($dueDate === '') {
                $errors[] = 'Defina a data de vencimento para gerar recorrência.';
            }
        }

        $count = max(1, min(60, $count));

        return [
            'enabled' => $enabled,
            'frequency' => $frequency,
            'count' => $count,
        ];
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }

    private function normalizeId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function normalizeDate($value, string $label, array &$errors): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = trim((string) $value);
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);
        if ($dt && $dt->format('Y-m-d') === $raw) {
            return $dt->format('Y-m-d');
        }
        $errors[] = $label . ' inválida.';
        return null;
    }

    private function normalizeDateTime($value, string $label, array &$errors): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = trim((string) $value);
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt && $dt->format($format) === $raw) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        $errors[] = $label . ' inválida.';
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapById(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }
        return $map;
    }
}
