<?php

namespace App\Services;

class OrderDeliveryPolicy
{
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

    public static function deliveryModeOptions(): array
    {
        return self::DELIVERY_MODE_OPTIONS;
    }

    public static function shipmentKindOptions(): array
    {
        return self::SHIPMENT_KIND_OPTIONS;
    }

    public static function normalizeDeliveryMode(
        ?string $mode
    ): string {
        $normalized = self::normalizeKey($mode);
        $resolved = match ($normalized) {
            'immediate_in_hand', 'immediate-in-hand', 'imediata_em_maos', 'imediata-em-maos', 'imediata', 'em_maos', 'em-maos', 'maos' => 'immediate_in_hand',
            'store_pickup', 'store-pickup', 'retirada_na_loja', 'retirada-na-loja', 'retirada', 'pickup', 'balcao' => 'store_pickup',
            'shipment', 'shipping', 'envio', 'enviado', 'frete', 'correios', 'transportadora', 'local_delivery', 'local-delivery', 'entrega_local', 'entrega-local', 'motoboy', 'delivery_local', 'delivery-local' => 'shipment',
            default => '',
        };
        if ($resolved !== '') {
            return $resolved;
        }

        return 'shipment';
    }

    public static function normalizeShipmentKind(
        ?string $kind,
        string $deliveryMode
    ): ?string {
        if ($deliveryMode !== 'shipment') {
            return null;
        }

        $normalized = self::normalizeKey($kind);
        $resolved = match ($normalized) {
            'tracked', 'rastreavel', 'rastreável', 'shipping', 'envio', 'correios', 'transportadora' => 'tracked',
            'local_courier', 'local-courier', 'local_delivery', 'local-delivery', 'entrega_local', 'entrega-local', 'motoboy' => 'local_courier',
            'bag_deferred', 'bag-deferred', 'sacolinha', 'bag', 'deferred', 'enviar_depois' => 'bag_deferred',
            default => '',
        };
        if ($resolved !== '') {
            return $resolved;
        }

        return 'tracked';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function resolve(
        array $input,
        bool $validateRequired = true,
        bool $requireTrackingCode = true
    ): array
    {
        $deliveryMode = self::normalizeDeliveryMode((string) ($input['delivery_mode'] ?? ''));
        $shipmentKind = self::normalizeShipmentKind(
            (string) ($input['shipment_kind'] ?? ''),
            $deliveryMode
        );

        $status = OrderService::normalizeFulfillmentStatus((string) ($input['fulfillment_status'] ?? ($input['status'] ?? 'pending')));
        $carrierId = isset($input['carrier_id']) && (int) $input['carrier_id'] > 0 ? (int) $input['carrier_id'] : null;
        $trackingCode = trim((string) ($input['tracking_code'] ?? ''));
        $estimatedDeliveryAt = self::normalizeDateTime($input['estimated_delivery_at'] ?? ($input['eta'] ?? null));
        $shippedAt = self::normalizeDateTime($input['shipped_at'] ?? null);
        $deliveredAt = self::normalizeDateTime($input['delivered_at'] ?? null);
        $logisticsNotes = trim((string) ($input['logistics_notes'] ?? ''));
        $logisticsNotes = $logisticsNotes !== '' ? $logisticsNotes : null;
        $bagId = isset($input['bag_id']) && (int) $input['bag_id'] > 0 ? (int) $input['bag_id'] : null;
        $errors = [];

        if ($deliveryMode === 'immediate_in_hand') {
            $status = 'delivered';
            $shipmentKind = null;
            $carrierId = null;
            $trackingCode = '';
            $estimatedDeliveryAt = null;
            $shippedAt = $shippedAt ?: date('Y-m-d H:i:s');
            $deliveredAt = $deliveredAt ?: date('Y-m-d H:i:s');
            $bagId = null;
        } elseif ($deliveryMode === 'store_pickup') {
            $shipmentKind = null;
            $carrierId = null;
            $trackingCode = '';
            $estimatedDeliveryAt = null;
            $shippedAt = null;
            $deliveredAt = $status === 'delivered' ? ($deliveredAt ?: date('Y-m-d H:i:s')) : null;
            $bagId = null;
        } else {
            $shipmentKind = $shipmentKind ?? 'tracked';
            if ($shipmentKind === 'tracked' && $validateRequired) {
                if ($carrierId === null) {
                    $errors[] = 'Selecione a transportadora para envio rastreável.';
                }
                if ($requireTrackingCode && $trackingCode === '') {
                    $errors[] = 'Informe o código de rastreio para envio rastreável.';
                }
            }
            if ($shipmentKind === 'bag_deferred') {
                if ($status !== 'shipped' && $status !== 'delivered') {
                    $status = 'pending';
                }
                $estimatedDeliveryAt = null;
            } else {
                $bagId = null;
            }
            if ($status === 'delivered' && $deliveredAt === null) {
                $deliveredAt = date('Y-m-d H:i:s');
            }
        }

        return [
            'delivery_mode' => $deliveryMode,
            'shipment_kind' => $shipmentKind,
            'fulfillment_status' => $status,
            'delivery_status' => $status,
            'carrier_id' => $carrierId,
            'tracking_code' => $trackingCode !== '' ? $trackingCode : null,
            'estimated_delivery_at' => $estimatedDeliveryAt,
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
            'logistics_notes' => $logisticsNotes,
            'bag_id' => $bagId,
            'errors' => $errors,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, 'T')) {
            $raw = str_replace('T', ' ', $raw);
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function normalizeKey(?string $value): string
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
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;
        return trim($normalized, '_');
    }
}
