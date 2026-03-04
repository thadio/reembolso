<?php

namespace App\Models;

class Order
{
    public ?int $id = null;
    public ?int $personId = null;
    public ?int $customerId = null;
    public string $status = 'open';
    public string $currency = 'BRL';
    public ?string $buyerNote = null;
    public ?string $salesChannel = null;
    public array $billing = [];
    public array $shipping = [];
    /** @var array<int, OrderItem> */
    public array $items = [];
    /** @var array<int, array<string, mixed>> */
    public array $paymentEntries = [];
    public OrderPayment $payment;
    public OrderShipping $shippingInfo;

    public function __construct()
    {
        $this->payment = new OrderPayment();
        $this->shippingInfo = new OrderShipping();
    }

    public static function fromArray(array $data): self
    {
        $order = new self();
        $order->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $resolvedPersonId = isset($data['pessoa_id'])
            ? (int) $data['pessoa_id']
            : (isset($data['customer_id'])
                ? (int) $data['customer_id']
                : (isset($data['customerId'])
                    ? (int) $data['customerId']
                    : (isset($data['personId']) ? (int) $data['personId'] : null)));
        $order->personId = $resolvedPersonId;
        $order->customerId = $resolvedPersonId;
        $order->status = (string) ($data['status'] ?? 'open');
        $order->currency = (string) ($data['currency'] ?? 'BRL');
        $order->buyerNote = self::nullableString($data['buyer_note'] ?? $data['buyerNote'] ?? null);
        $order->salesChannel = self::nullableString($data['sales_channel'] ?? $data['salesChannel'] ?? null);

        $order->billing = self::normalizeAddress($data, 'billing');
        $order->shipping = self::normalizeAddress($data, 'shipping');

        $items = $data['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $itemData) {
                if (!is_array($itemData)) {
                    continue;
                }
                $item = OrderItem::fromArray($itemData);
                if ($item->isValid()) {
                    $order->items[] = $item;
                }
            }
        }

        $order->payment = OrderPayment::fromArray($data['payment'] ?? $data);
        $order->shippingInfo = OrderShipping::fromArray($data['shipping_info'] ?? $data);
        $entries = $data['payments'] ?? $data['payment_entries'] ?? [];
        if (is_array($entries)) {
            $order->paymentEntries = $entries;
        }

        return $order;
    }

    private static function normalizeAddress(array $data, string $prefix): array
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

        $source = $data[$prefix] ?? null;
        if (is_array($source)) {
            foreach ($fields as $key => $value) {
                if (isset($source[$key])) {
                    $fields[$key] = (string) $source[$key];
                }
            }
        }

        foreach ($fields as $key => $value) {
            $flatKey = $prefix . '_' . $key;
            if (isset($data[$flatKey]) && $data[$flatKey] !== '') {
                $fields[$key] = (string) $data[$flatKey];
            }
        }

        return $fields;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
    
    /**
     * Convert Order to array format for database storage
     */
    public function toArray(): array
    {
        $personId = $this->personId ?? $this->customerId;
        $data = [
            'pessoa_id' => $personId,
            'customer_id' => $personId,
            'status' => $this->status,
            'currency' => $this->currency,
            'buyer_note' => $this->buyerNote,
            'sales_channel' => $this->salesChannel,
        ];
        
        // Billing fields
        foreach ($this->billing as $key => $value) {
            $data['billing_' . $key] = $value;
        }
        
        // Shipping fields
        foreach ($this->shipping as $key => $value) {
            $data['shipping_' . $key] = $value;
        }
        
        // Payment
        if ($this->payment) {
            $data['payment_status'] = $this->payment->status ?? 'none';
            $paymentMethodLabel = trim((string) ($this->payment->methodTitle ?? ''));
            if ($paymentMethodLabel === '') {
                $paymentMethodLabel = trim((string) ($this->payment->method ?? ''));
            }
            $data['payment_method'] = $paymentMethodLabel !== '' ? $paymentMethodLabel : null;
        }
        
        // Shipping info
        if ($this->shippingInfo) {
            $data['fulfillment_status'] = $this->shippingInfo->status ?? 'pending';
            $data['delivery_mode'] = $this->shippingInfo->deliveryMode ?? 'shipment';
            $data['shipment_kind'] = $this->shippingInfo->shipmentKind;
            $data['delivery_status'] = $this->shippingInfo->status ?? 'pending';
            $data['shipping_total'] = $this->shippingInfo->total;
            $data['carrier_id'] = $this->shippingInfo->carrierId;
            $data['tracking_code'] = $this->shippingInfo->trackingCode;
            $data['estimated_delivery_at'] = $this->shippingInfo->estimatedDeliveryAt;
            $data['shipped_at'] = $this->shippingInfo->shippedAt;
            $data['delivered_at'] = $this->shippingInfo->deliveredAt;
            $data['logistics_notes'] = $this->shippingInfo->logisticsNotes;
            $data['bag_id'] = $this->shippingInfo->bagId;
            $data['shipping_info'] = json_encode([
                'status' => $this->shippingInfo->status,
                'delivery_mode' => $this->shippingInfo->deliveryMode,
                'shipment_kind' => $this->shippingInfo->shipmentKind,
                'total' => $this->shippingInfo->total,
                'carrier_id' => $this->shippingInfo->carrierId,
                'carrier' => $this->shippingInfo->carrier,
                'tracking_code' => $this->shippingInfo->trackingCode,
                'estimated_delivery_at' => $this->shippingInfo->estimatedDeliveryAt,
                'shipped_at' => $this->shippingInfo->shippedAt,
                'delivered_at' => $this->shippingInfo->deliveredAt,
                'logistics_notes' => $this->shippingInfo->logisticsNotes,
                'bag_id' => $this->shippingInfo->bagId,
            ]);
        }
        
        // Calculate totals from items
        $subtotal = 0;
        $itemsData = [];
        foreach ($this->items as $item) {
            $itemTotal = ($item->price ?? 0) * $item->quantity;
            $subtotal += $itemTotal;
            $itemsData[] = [
                'product_sku' => $item->productSku,
                'product_name' => $item->name,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $itemTotal,
            ];
        }
        
        $data['subtotal'] = $subtotal;
        $shippingTotal = isset($data['shipping_total']) ? (float) $data['shipping_total'] : 0.0;
        $data['total'] = $subtotal + $shippingTotal;
        $data['items'] = $itemsData;
        $data['payment_entries'] = $this->paymentEntries;
        $data['ordered_at'] = date('Y-m-d H:i:s');
        
        return $data;
    }
}
