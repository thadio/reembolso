<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Input;

class OrderService
{
    public const STATUS_LABELS = OrderLifecycleService::ORDER_STATUS_LABELS;

    public const PAYMENT_STATUS_LABELS = OrderLifecycleService::PAYMENT_STATUS_LABELS;

    public const FULFILLMENT_STATUS_LABELS = OrderLifecycleService::FULFILLMENT_STATUS_LABELS;

    public const SALES_CHANNEL_OPTIONS = [
        'Instagram',
        'WhatsApp',
        'Site',
        'Loja física',
    ];

    public const DELIVERY_MODE_OPTIONS = [
        'immediate_in_hand' => 'Em mãos agora',
        'store_pickup' => 'Retirada na loja',
        'shipment' => 'Enviar/Entregar',
    ];

    public const SHIPMENT_KIND_OPTIONS = [
        'tracked' => 'Rastreável (Correios/Transportadora)',
        'local_courier' => 'Entrega local (motoboy)',
        'bag_deferred' => 'Sacolinha (enviar depois)',
    ];

    /**
     * @return array{0: Order, 1: array<int, string>}
     */
    public function validate(
        array $input,
        bool $editing,
        array $salesChannelOptions = [],
        bool $requireTrackingCode = true
    ): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $price = $this->normalizeMoney($item['price'] ?? null);
                $adjustType = (string) ($item['adjust_type'] ?? '');
                $adjustValue = $this->normalizeMoney($item['adjust_value'] ?? null);
                if ($price !== null && $adjustType !== '' && $adjustValue !== null) {
                    $input['items'][$index]['price'] = $this->applyItemAdjustment($price, $adjustType, $adjustValue);
                }
            }
        }

        if (!empty($input['shipping_same_as_billing'])) {
            $input['shipping'] = $input['billing'] ?? $this->extractAddress($input, 'billing');
        }

        $order = Order::fromArray($input);
        $order->status = self::normalizeOrderStatus($order->status);
        $order->payment->status = self::normalizePaymentStatus($order->payment->status);
        $order->shippingInfo->status = self::normalizeFulfillmentStatus($order->shippingInfo->status);
        $shippingInfoInput = isset($input['shipping_info']) && is_array($input['shipping_info'])
            ? $input['shipping_info']
            : [];
        $hasExplicitDeliverySelection =
            array_key_exists('delivery_mode', $input)
            || array_key_exists('shipment_kind', $input)
            || array_key_exists('carrier_id', $input)
            || array_key_exists('tracking_code', $input)
            || !empty($shippingInfoInput['delivery_mode'])
            || !empty($shippingInfoInput['shipment_kind'])
            || !empty($shippingInfoInput['carrier_id'])
            || !empty($shippingInfoInput['tracking_code']);
        $policy = new OrderDeliveryPolicy();
        $resolvedDelivery = $policy->resolve([
            'delivery_mode' => (string) ($input['delivery_mode'] ?? ($shippingInfoInput['delivery_mode'] ?? $order->shippingInfo->deliveryMode)),
            'shipment_kind' => (string) ($input['shipment_kind'] ?? ($shippingInfoInput['shipment_kind'] ?? $order->shippingInfo->shipmentKind)),
            'fulfillment_status' => $order->shippingInfo->status,
            'carrier_id' => $order->shippingInfo->carrierId,
            'tracking_code' => $order->shippingInfo->trackingCode,
            'estimated_delivery_at' => $order->shippingInfo->estimatedDeliveryAt ?? $order->shippingInfo->eta,
            'shipped_at' => $order->shippingInfo->shippedAt,
            'delivered_at' => $order->shippingInfo->deliveredAt,
            'logistics_notes' => $order->shippingInfo->logisticsNotes,
            'bag_id' => $input['bag_id'] ?? ($shippingInfoInput['bag_id'] ?? $order->shippingInfo->bagId),
        ], $hasExplicitDeliverySelection, $requireTrackingCode);

        $order->shippingInfo->deliveryMode = (string) $resolvedDelivery['delivery_mode'];
        $order->shippingInfo->shipmentKind = $resolvedDelivery['shipment_kind'] !== null
            ? (string) $resolvedDelivery['shipment_kind']
            : null;
        $order->shippingInfo->status = (string) $resolvedDelivery['fulfillment_status'];
        $order->shippingInfo->carrierId = $resolvedDelivery['carrier_id'] !== null
            ? (int) $resolvedDelivery['carrier_id']
            : null;
        $order->shippingInfo->trackingCode = $resolvedDelivery['tracking_code'] !== null
            ? (string) $resolvedDelivery['tracking_code']
            : null;
        $order->shippingInfo->estimatedDeliveryAt = $resolvedDelivery['estimated_delivery_at'] !== null
            ? (string) $resolvedDelivery['estimated_delivery_at']
            : null;
        $order->shippingInfo->eta = $order->shippingInfo->estimatedDeliveryAt;
        $order->shippingInfo->shippedAt = $resolvedDelivery['shipped_at'] !== null
            ? (string) $resolvedDelivery['shipped_at']
            : null;
        $order->shippingInfo->deliveredAt = $resolvedDelivery['delivered_at'] !== null
            ? (string) $resolvedDelivery['delivered_at']
            : null;
        $order->shippingInfo->logisticsNotes = $resolvedDelivery['logistics_notes'] !== null
            ? (string) $resolvedDelivery['logistics_notes']
            : null;
        $order->shippingInfo->bagId = $resolvedDelivery['bag_id'] !== null
            ? (int) $resolvedDelivery['bag_id']
            : null;

        if (!empty($resolvedDelivery['errors']) && is_array($resolvedDelivery['errors'])) {
            foreach ($resolvedDelivery['errors'] as $deliveryError) {
                $errors[] = (string) $deliveryError;
            }
        }

        if (!array_key_exists($order->status, self::STATUS_LABELS)) {
            $errors[] = 'Status do pedido inválido.';
        }

        $paymentStatus = $order->payment->status;
        if (!array_key_exists($paymentStatus, self::PAYMENT_STATUS_LABELS)) {
            $errors[] = 'Status de pagamento inválido.';
        }

        $fulfillmentStatus = $order->shippingInfo->status;
        if (!array_key_exists($fulfillmentStatus, self::FULFILLMENT_STATUS_LABELS)) {
            $errors[] = 'Status de envio inválido.';
        }

        $salesChannel = trim((string) ($input['sales_channel'] ?? ''));
        if ($salesChannel !== '' && !empty($salesChannelOptions) && !in_array($salesChannel, $salesChannelOptions, true)) {
            $errors[] = 'Canal de venda inválido.';
        }

        if (!$editing) {
            if (!$order->personId && $this->isEmpty($order->billing['full_name'] ?? '')) {
                $errors[] = 'Informe o cliente ou o nome do comprador.';
            }
            if ($this->isEmpty($order->billing['email'] ?? '')) {
                $errors[] = 'E-mail do comprador é obrigatório.';
            }
        }

        if (!$editing) {
            if (empty($order->items)) {
                $errors[] = 'Adicione ao menos um item.';
            } else {
                foreach ($order->items as $index => $item) {
                    if ($item->productSku <= 0) {
                        $errors[] = 'Item #' . ($index + 1) . ': produto inválido.';
                    }
                    if ($item->quantity <= 0) {
                        $errors[] = 'Item #' . ($index + 1) . ': quantidade inválida.';
                    }
                    if ($item->price !== null && $item->price < 0) {
                        $errors[] = 'Item #' . ($index + 1) . ': preço inválido.';
                    }
                }
            }
        }

        if ($order->shippingInfo->total !== null && $order->shippingInfo->total < 0) {
            $errors[] = 'Valor do frete inválido.';
        }

        return [$order, $errors];
    }

    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    public static function statusFilterOptions(): array
    {
        return array_merge(self::STATUS_LABELS, [
            'trash' => 'Lixeira',
        ]);
    }

    public static function paymentStatusOptions(): array
    {
        return self::PAYMENT_STATUS_LABELS;
    }

    public static function fulfillmentStatusOptions(): array
    {
        return self::FULFILLMENT_STATUS_LABELS;
    }

    public static function salesChannelOptions(): array
    {
        return self::SALES_CHANNEL_OPTIONS;
    }

    public static function deliveryModeOptions(): array
    {
        return self::DELIVERY_MODE_OPTIONS;
    }

    public static function shipmentKindOptions(): array
    {
        return self::SHIPMENT_KIND_OPTIONS;
    }

    public static function normalizeOrderStatus(?string $status): string
    {
        return OrderLifecycleService::normalizeOrderStatus($status);
    }

    public static function normalizePaymentStatus(?string $status): string
    {
        return OrderLifecycleService::normalizePaymentStatus($status);
    }

    public static function normalizeFulfillmentStatus(?string $status): string
    {
        return OrderLifecycleService::normalizeFulfillmentStatus($status);
    }

    public static function deriveLifecycleStatus(
        string $currentOrderStatus,
        string $paymentStatus,
        string $fulfillmentStatus
    ): string {
        return OrderLifecycleService::deriveOrderStatusFromDimensions(
            $currentOrderStatus,
            $paymentStatus,
            $fulfillmentStatus
        );
    }

    public static function normalizeDeliveryMode(
        ?string $mode
    ): string {
        return OrderDeliveryPolicy::normalizeDeliveryMode($mode);
    }

    public static function normalizeShipmentKind(
        ?string $kind,
        string $deliveryMode
    ): ?string {
        return OrderDeliveryPolicy::normalizeShipmentKind($kind, $deliveryMode);
    }

    private function extractAddress(array $input, string $prefix): array
    {
        $fields = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'address_1' => '',
            'address_2' => '',
            'number' => '',
            'neighborhood' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
        ];

        foreach ($fields as $key => $value) {
            $flatKey = $prefix . '_' . $key;
            if (isset($input[$flatKey]) && $input[$flatKey] !== '') {
                $fields[$key] = (string) $input[$flatKey];
            }
        }

        return $fields;
    }

    private function isEmpty(string $value): bool
    {
        return trim($value) === '';
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }

    private function applyItemAdjustment(float $price, string $type, float $value): float
    {
        $adjustment = max(0.0, $value);
        $final = $price;

        switch ($type) {
            case 'discount_percent':
                $final = $price - ($price * ($adjustment / 100));
                break;
            case 'discount_value':
                $final = $price - $adjustment;
                break;
            case 'increase_percent':
                $final = $price + ($price * ($adjustment / 100));
                break;
            case 'increase_value':
                $final = $price + $adjustment;
                break;
        }

        if (is_infinite($final) || is_nan($final)) {
            $final = $price;
        }

        return max(0.0, $final);
    }

    private static function normalizeStatusKey(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $normalized = strtolower($normalized);
        if (function_exists('iconv')) {
            $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
            if ($translit !== false) {
                $normalized = $translit;
            }
        }
        $normalized = preg_replace('/[^a-z0-9 _-]+/', '', $normalized);
        $normalized = str_replace([' ', '_'], '-', $normalized);
        $normalized = preg_replace('/-+/', '-', $normalized);

        return trim((string) $normalized, '-');
    }
}
