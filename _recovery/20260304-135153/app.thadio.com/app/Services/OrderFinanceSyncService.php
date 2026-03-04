<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\OrderRepository;
use App\Support\Input;
use PDO;

class OrderFinanceSyncService
{
    public const AUTO_TAG = '[AUTO_ORDER_RECEIPT]';

    private ?PDO $pdo;
    private OrderRepository $orders;
    private FinanceEntryRepository $financeEntries;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->orders = new OrderRepository($pdo);
        $this->financeEntries = new FinanceEntryRepository($pdo);
    }

    /**
     * @return array{created: int, deleted: int}
     */
    public function sync(int $orderId): array
    {
        if (!$this->pdo || $orderId <= 0) {
            return ['created' => 0, 'deleted' => 0];
        }

        $orderData = $this->orders->findOrderWithDetails($orderId);
        if (!$orderData) {
            return ['created' => 0, 'deleted' => 0];
        }

        $payloads = $this->buildReceivableEntries($orderData);
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $deleted = $this->financeEntries->deleteAutoEntriesByOrderIdAndTag($orderId, self::AUTO_TAG);
            $created = 0;
            foreach ($payloads as $payload) {
                $entry = FinanceEntry::fromArray($payload);
                $this->financeEntries->save($entry);
                $created++;
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }

            return [
                'created' => $created,
                'deleted' => $deleted,
            ];
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildReceivableEntries(array $orderData): array
    {
        $orderId = (int) ($orderData['id'] ?? 0);
        if ($orderId <= 0) {
            return [];
        }
        if (!$this->shouldGenerateReceivables($orderData)) {
            return [];
        }

        $rawEntries = $this->extractPaymentEntries($orderData);
        if (empty($rawEntries)) {
            return [];
        }

        $paidAt = $this->resolvePaidAt($orderData);
        $dueDate = substr($paidAt, 0, 10);
        $totalEntries = count($rawEntries);
        $orderMarkedPaid = $this->isOrderMarkedPaid($orderData);
        $entries = [];

        foreach ($rawEntries as $index => $rawEntry) {
            if (!is_array($rawEntry)) {
                continue;
            }
            if (!$orderMarkedPaid && !$this->isPaidFlag($rawEntry['paid'] ?? null)) {
                continue;
            }

            $methodType = strtolower(trim((string) ($rawEntry['method_type'] ?? '')));
            if ($methodType === 'voucher') {
                continue;
            }

            $gross = $this->parseMoney($rawEntry['amount'] ?? null);
            if ($gross <= 0) {
                continue;
            }
            $fee = max(0.0, $this->parseMoney($rawEntry['fee'] ?? null));
            $net = round(max(0.0, $gross - $fee), 2);
            if ($net <= 0) {
                continue;
            }

            $methodName = trim((string) ($rawEntry['method_name'] ?? 'Recebimento'));
            if ($methodName === '') {
                $methodName = 'Recebimento';
            }
            $description = 'Recebimento pedido #' . $orderId . ' - ' . $methodName;
            if ($totalEntries > 1) {
                $description .= ' (' . ($index + 1) . '/' . $totalEntries . ')';
            }

            $bankAccountId = (int) ($rawEntry['bank_account_id'] ?? 0);
            $paymentMethodId = (int) ($rawEntry['method_id'] ?? 0);
            $terminalId = (int) ($rawEntry['terminal_id'] ?? 0);
            $notesPayload = [
                'tag' => self::AUTO_TAG,
                'order_id' => $orderId,
                'payment_index' => $index + 1,
                'gross_amount' => $gross,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                'method_name' => $methodName,
                'method_type' => $methodType,
                'generated_at' => date('Y-m-d H:i:s'),
            ];
            $notes = self::AUTO_TAG . ' ' . json_encode($notesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $entries[] = [
                'type' => 'receber',
                'description' => $description,
                'category_id' => null,
                'supplier_pessoa_id' => null,
                'lot_id' => null,
                'order_id' => $orderId,
                'amount' => $net,
                'due_date' => $dueDate,
                'status' => 'pago',
                'paid_at' => $paidAt,
                'paid_amount' => $net,
                'bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
                'payment_method_id' => $paymentMethodId > 0 ? $paymentMethodId : null,
                'payment_terminal_id' => $terminalId > 0 ? $terminalId : null,
                'notes' => $notes,
            ];
        }

        return $entries;
    }

    private function shouldGenerateReceivables(array $orderData): bool
    {
        $rawStatus = strtolower(trim((string) ($orderData['status'] ?? 'open')));
        if (in_array($rawStatus, ['failed', 'falhou'], true)) {
            return false;
        }

        $status = OrderService::normalizeOrderStatus($rawStatus);
        if (in_array($status, ['cancelled', 'refunded', 'trash', 'deleted'], true)) {
            return false;
        }

        return true;
    }

    private function isOrderMarkedPaid(array $orderData): bool
    {
        $status = strtolower(trim((string) ($orderData['payment_status'] ?? '')));
        if ($status !== '' && in_array($status, ['paid', 'pago'], true)) {
            return true;
        }

        $meta = $this->extractMeta((array) ($orderData['meta_data'] ?? []));
        $metaStatus = strtolower(trim((string) ($meta['retrato_payment_status'] ?? '')));
        return $metaStatus !== '' && in_array($metaStatus, ['paid', 'pago'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractPaymentEntries(array $orderData): array
    {
        $meta = $this->extractMeta((array) ($orderData['meta_data'] ?? []));
        $raw = $meta['retrato_payment_entries'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * @param array<int, array<string, mixed>> $metaData
     * @return array<string, mixed>
     */
    private function extractMeta(array $metaData): array
    {
        $meta = [];
        foreach ($metaData as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = isset($entry['key']) ? (string) $entry['key'] : '';
            if ($key === '') {
                continue;
            }
            $meta[$key] = $entry['value'] ?? null;
        }
        return $meta;
    }

    private function resolvePaidAt(array $orderData): string
    {
        $candidates = [
            $orderData['date_paid'] ?? null,
            $orderData['completed_at'] ?? null,
            $orderData['updated_at'] ?? null,
            $orderData['ordered_at'] ?? null,
            $orderData['date_created'] ?? null,
            $orderData['created_at'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeDateTime($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * @param mixed $value
     */
    private function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $parsed = Input::parseNumber($value);
        return $parsed !== null ? (float) $parsed : 0.0;
    }

    /**
     * @param mixed $value
     */
    private function isPaidFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return true;
        }
        return !in_array($normalized, ['0', 'false', 'off', 'nao', 'não'], true);
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
