<?php

namespace App\Models;

use App\Support\Input;

class OrderShipping
{
    public string $status = 'pending';
    public string $deliveryMode = 'shipment';
    public ?string $shipmentKind = null;
    public ?float $total = null;
    public ?int $carrierId = null;
    public ?string $carrier = null;
    public ?string $trackingCode = null;
    public ?string $shippedAt = null;
    public ?string $deliveredAt = null;
    public ?string $estimatedDeliveryAt = null;
    public ?string $eta = null;
    public ?string $logisticsNotes = null;
    public ?int $bagId = null;
    public ?int $lineId = null;

    public static function fromArray(array $data): self
    {
        $shipping = new self();
        $shipping->status = (string) ($data['fulfillment_status'] ?? $data['status'] ?? 'pending');
        $shipping->deliveryMode = (string) ($data['delivery_mode'] ?? $data['deliveryMode'] ?? 'shipment');
        $shipping->shipmentKind = self::nullableString($data['shipment_kind'] ?? $data['shipmentKind'] ?? null);
        $shipping->total = self::nullableFloat($data['shipping_total'] ?? $data['total'] ?? null);
        $shipping->carrierId = isset($data['carrier_id']) ? (int) $data['carrier_id'] : (isset($data['carrierId']) ? (int) $data['carrierId'] : null);
        $shipping->carrier = self::nullableString($data['shipping_carrier'] ?? $data['carrier'] ?? null);
        $shipping->trackingCode = self::nullableString($data['tracking_code'] ?? $data['trackingCode'] ?? null);
        $shipping->shippedAt = self::nullableString($data['shipped_at'] ?? $data['shippedAt'] ?? null);
        $shipping->deliveredAt = self::nullableString($data['delivered_at'] ?? $data['deliveredAt'] ?? null);
        $shipping->estimatedDeliveryAt = self::nullableString($data['estimated_delivery_at'] ?? $data['estimatedDeliveryAt'] ?? null);
        $shipping->eta = self::nullableString($data['shipping_eta'] ?? $data['eta'] ?? null);
        $shipping->logisticsNotes = self::nullableString($data['logistics_notes'] ?? $data['logisticsNotes'] ?? null);
        $shipping->bagId = isset($data['bag_id']) ? (int) $data['bag_id'] : (isset($data['bagId']) ? (int) $data['bagId'] : null);
        if ($shipping->bagId !== null && $shipping->bagId <= 0) {
            $shipping->bagId = null;
        }
        $shipping->lineId = isset($data['line_id']) ? (int) $data['line_id'] : (isset($data['lineId']) ? (int) $data['lineId'] : null);
        return $shipping;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }
}
