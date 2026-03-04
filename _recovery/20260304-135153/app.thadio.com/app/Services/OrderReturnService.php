<?php

namespace App\Services;

use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Support\Input;

class OrderReturnService
{
    public const STATUS_OPTIONS = [
        'requested' => 'Solicitado',
        'awaiting_item' => 'Aguardando devolução',
        'in_transit' => 'Em trânsito',
        'received' => 'Devolução recebida',
        'not_delivered' => 'Produto não entregue à cliente',
        'cancelled' => 'Cancelado',
    ];

    public const RETURN_METHODS = [
        'immediate' => 'Recebida no ato (produto em mãos)',
        'not_delivered' => 'Produto não chegou a ser entregue',
        'mail' => 'Envio pelos Correios (rastreamento opcional)',
        'courier' => 'Envio/retirada via motorista/uber',
        'dropoff' => 'Cliente levará depois',
    ];

    public const REFUND_METHODS = [
        'voucher' => 'Gerar crédito/cupom',
        'pix' => 'Pix',
        'cash' => 'Dinheiro',
        'card' => 'Estorno no cartão/maquininha',
        'transfer' => 'Transferência/depósito',
        'none' => 'Sem reembolso',
    ];

    public const REFUND_STATUS = [
        'pending' => 'A fazer',
        'done' => 'Feito',
    ];

    /**
     * @param array<int|string, array<string, mixed>> $orderItems
     * @param array<int, int> $alreadyReturned
     * @param array<int, array<string, mixed>> $existingItems
     * @return array{0: OrderReturn, 1: array<int, OrderReturnItem>, 2: array<int, string>}
     */
    public function validate(array $input, array $orderItems, array $alreadyReturned = [], array $existingItems = []): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
        if ($orderId <= 0) {
            $errors[] = 'Pedido inválido para devolução.';
        }

        $orderItems = $this->normalizeOrderItems($orderItems);
        if (empty($orderItems)) {
            $errors[] = 'Itens do pedido não encontrados.';
        }

        $existingMap = $this->mapExistingItems($existingItems);
        $availableMap = $this->buildAvailableMap($orderItems, $alreadyReturned, $existingMap);

        $items = [];
        $itemsInput = $input['items'] ?? [];
        if (is_array($itemsInput)) {
            foreach ($itemsInput as $lineId => $rawItem) {
                $lineId = (int) $lineId;
                $qty = isset($rawItem['quantity']) ? (int) $rawItem['quantity'] : 0;
                if ($qty <= 0) {
                    continue;
                }
                if (!isset($orderItems[$lineId])) {
                    $errors[] = 'Item do pedido #' . $lineId . ' é inválido para devolução.';
                    continue;
                }
                $purchasedQty = $orderItems[$lineId]['quantity'];
                $already = $availableMap[$lineId] ?? 0;
                if ($qty > $already) {
                    $errors[] = 'Quantidade para o item #' . $lineId . ' excede o disponível para devolução (' . $already . ' de ' . $purchasedQty . ').';
                    continue;
                }

                $unitPrice = $this->normalizeMoney($rawItem['unit_price'] ?? $orderItems[$lineId]['unit_price']);
                $items[] = OrderReturnItem::fromArray([
                    'order_item_id' => $lineId,
                    'product_sku' => $orderItems[$lineId]['product_sku'] ?? ($orderItems[$lineId]['product_id'] ?? 0),
                    'variation_id' => $orderItems[$lineId]['variation_id'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'product_name' => $orderItems[$lineId]['name'],
                    'sku' => $orderItems[$lineId]['sku'],
                ]);
            }
        }

        if (empty($items)) {
            $errors[] = 'Selecione ao menos um produto para devolver.';
        }

        $returnMethod = (string) ($input['return_method'] ?? 'dropoff');
        if (!array_key_exists($returnMethod, self::RETURN_METHODS)) {
            $errors[] = 'Forma de devolução inválida.';
        }

        $status = (string) ($input['status'] ?? '');
        if ($status === '') {
            $status = $this->defaultStatus($returnMethod);
        }
        if (!array_key_exists($status, self::STATUS_OPTIONS)) {
            $errors[] = 'Status da devolução inválido.';
        }

        $refundMethod = (string) ($input['refund_method'] ?? 'voucher');
        if (!array_key_exists($refundMethod, self::REFUND_METHODS)) {
            $errors[] = 'Forma de reembolso inválida.';
        }

        $refundStatus = (string) ($input['refund_status'] ?? '');
        if ($refundMethod === 'voucher' || $refundMethod === 'none') {
            $refundStatus = 'done';
        }
        if ($refundStatus === '') {
            $refundStatus = 'pending';
        }
        if (!array_key_exists($refundStatus, self::REFUND_STATUS)) {
            $errors[] = 'Status do reembolso inválido.';
        }

        $trackingCode = $input['tracking_code'] ?? null;
        if ($trackingCode !== null && mb_strlen($trackingCode) > 160) {
            $errors[] = 'Código de rastreamento muito longo.';
        }

        $expectedAt = $this->normalizeDate($input['expected_at'] ?? null);
        $receivedAt = $this->normalizeDateTime($input['received_at'] ?? null);
        if (($status === 'received' || $status === 'not_delivered') && $receivedAt === null) {
            $receivedAt = date('Y-m-d H:i:s');
        }

        $refundAmount = $this->normalizeMoney($input['refund_amount'] ?? null);
        if ($refundAmount === null) {
            $refundAmount = 0.0;
            foreach ($items as $item) {
                $refundAmount += $item->unitPrice * $item->quantity;
            }
        }
        $extraShipping = $this->normalizeMoney($input['refund_extra_shipping'] ?? null) ?? 0.0;
        $extraTax = $this->normalizeMoney($input['refund_extra_tax'] ?? null) ?? 0.0;
        $maxRefund = 0.0;
        foreach ($items as $item) {
            $maxRefund += $item->unitPrice * $item->quantity;
        }
        $maxRefund += max(0.0, $extraShipping) + max(0.0, $extraTax);
        if ($refundAmount < 0) {
            $errors[] = 'Valor de reembolso inválido.';
        }
        if ($refundAmount > $maxRefund + 0.0001) {
            $errors[] = 'Valor de reembolso acima do permitido para os produtos selecionados.';
        }

        $return = OrderReturn::fromArray([
            'id' => $input['id'] ?? null,
            'order_id' => $orderId,
            'pessoa_id' => $input['pessoa_id'] ?? null,
            'customer_name' => $input['customer_name'] ?? null,
            'customer_email' => $input['customer_email'] ?? null,
            'status' => $status,
            'return_method' => $returnMethod,
            'refund_method' => $refundMethod,
            'refund_status' => $refundStatus,
            'refund_amount' => $refundAmount,
            'tracking_code' => $trackingCode,
            'expected_at' => $expectedAt,
            'received_at' => $receivedAt,
            'restocked_at' => $input['restocked_at'] ?? null,
            'notes' => $input['notes'] ?? null,
            'voucher_account_id' => $input['voucher_account_id'] ?? null,
        ]);
        $return->items = $items;

        return [$return, $items, $errors];
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function returnMethodOptions(): array
    {
        return self::RETURN_METHODS;
    }

    public static function refundMethodOptions(): array
    {
        return self::REFUND_METHODS;
    }

    public static function refundStatusOptions(): array
    {
        return self::REFUND_STATUS;
    }

    private function normalizeOrderItems(array $orderItems): array
    {
        $normalized = [];
        foreach ($orderItems as $lineId => $item) {
            $lineId = isset($item['line_id']) ? (int) $item['line_id'] : (int) $lineId;
            if ($lineId <= 0) {
                continue;
            }
            $normalized[$lineId] = [
                'line_id' => $lineId,
                'product_sku' => (int) ($item['product_sku'] ?? ($item['product_id'] ?? 0)),
                'variation_id' => (int) ($item['variation_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'unit_price' => $this->normalizeMoney($item['unit_price'] ?? $item['price'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
            ];
        }
        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $orderItems
     * @param array<int, int> $alreadyReturned
     * @param array<int, int> $existingItems
     * @return array<int, int>
     */
    private function buildAvailableMap(array $orderItems, array $alreadyReturned, array $existingItems): array
    {
        $map = [];
        foreach ($orderItems as $lineId => $item) {
            $soldQty = (int) ($item['quantity'] ?? 0);
            $returnedQty = $alreadyReturned[$lineId] ?? 0;
            $existingQty = $existingItems[$lineId] ?? 0;
            $available = $soldQty - $returnedQty + $existingQty;
            $map[$lineId] = $available < 0 ? 0 : $available;
        }
        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $existingItems
     * @return array<int, int>
     */
    private function mapExistingItems(array $existingItems): array
    {
        $map = [];
        foreach ($existingItems as $item) {
            $lineId = isset($item['order_item_id']) ? (int) $item['order_item_id'] : 0;
            if ($lineId > 0) {
                $map[$lineId] = ($map[$lineId] ?? 0) + (int) ($item['quantity'] ?? 0);
            }
        }
        return $map;
    }

    private function defaultStatus(string $returnMethod): string
    {
        switch ($returnMethod) {
            case 'immediate':
                return 'received';
            case 'not_delivered':
                return 'not_delivered';
            case 'mail':
                return 'in_transit';
            case 'courier':
            case 'dropoff':
            default:
                return 'awaiting_item';
        }
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
