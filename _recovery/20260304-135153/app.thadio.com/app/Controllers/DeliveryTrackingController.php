<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BagRepository;
use App\Repositories\CarrierRepository;
use App\Repositories\OrderRepository;
use App\Services\OrderLifecycleService;
use App\Services\OrderDeliveryPolicy;
use App\Services\OrderService;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class DeliveryTrackingController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private OrderRepository $orders;
    private BagRepository $bags;
    private CarrierRepository $carriers;
    private OrderLifecycleService $lifecycle;
    private OrderDeliveryPolicy $deliveryPolicy;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->orders = new OrderRepository($pdo);
        $this->bags = new BagRepository($pdo);
        $this->carriers = new CarrierRepository($pdo);
        $this->lifecycle = new OrderLifecycleService();
        $this->deliveryPolicy = new OrderDeliveryPolicy();
    }

    public function index(): void
    {
        $errors = [];
        $success = '';

        $filters = [
            'period_from' => trim((string) ($_GET['period_from'] ?? '')),
            'period_to' => trim((string) ($_GET['period_to'] ?? '')),
            'delivery_status' => trim((string) ($_GET['delivery_status'] ?? '')),
            'delivery_mode' => trim((string) ($_GET['delivery_mode'] ?? '')),
            'shipment_kind' => trim((string) ($_GET['shipment_kind'] ?? '')),
            'carrier_id' => trim((string) ($_GET['carrier_id'] ?? '')),
            'tracking_code' => trim((string) ($_GET['tracking_code'] ?? '')),
            'customer' => trim((string) ($_GET['customer'] ?? '')),
            'order_id' => trim((string) ($_GET['order_id'] ?? '')),
        ];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_delivery') {
            Auth::requirePermission('orders.fulfillment', $this->pdo);
            $orderId = (int) ($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                $errors[] = 'Pedido inválido para atualização.';
            } else {
                try {
                    $order = $this->orders->findOrderWithDetails($orderId);
                    if (!$order) {
                        throw new \RuntimeException('Pedido não encontrado.');
                    }

                    $resolvedDelivery = $this->deliveryPolicy->resolve([
                        'delivery_mode' => (string) ($_POST['delivery_mode'] ?? ($order['delivery_mode'] ?? 'shipment')),
                        'shipment_kind' => (string) ($_POST['shipment_kind'] ?? ($order['shipment_kind'] ?? '')),
                        'fulfillment_status' => (string) ($_POST['delivery_status'] ?? ($order['delivery_status'] ?? 'pending')),
                        'carrier_id' => $_POST['carrier_id'] ?? ($order['carrier_id'] ?? null),
                        'tracking_code' => $_POST['tracking_code'] ?? ($order['tracking_code'] ?? ''),
                        'estimated_delivery_at' => $_POST['estimated_delivery_at'] ?? ($order['estimated_delivery_at'] ?? ''),
                        'shipped_at' => $_POST['shipped_at'] ?? ($order['shipped_at'] ?? null),
                        'delivered_at' => $_POST['delivered_at'] ?? ($order['delivered_at'] ?? null),
                        'logistics_notes' => $_POST['logistics_notes'] ?? ($order['logistics_notes'] ?? ''),
                        'bag_id' => $_POST['bag_id'] ?? ($order['bag_id'] ?? null),
                    ], true);
                    if (!empty($resolvedDelivery['errors'])) {
                        throw new \RuntimeException(implode(' ', $resolvedDelivery['errors']));
                    }

                    $deliveryMode = (string) ($resolvedDelivery['delivery_mode'] ?? 'shipment');
                    $shipmentKind = $resolvedDelivery['shipment_kind'] !== null
                        ? (string) $resolvedDelivery['shipment_kind']
                        : null;
                    $deliveryStatus = OrderService::normalizeFulfillmentStatus((string) ($resolvedDelivery['fulfillment_status'] ?? 'pending'));
                    $carrierId = isset($resolvedDelivery['carrier_id']) && (int) $resolvedDelivery['carrier_id'] > 0
                        ? (int) $resolvedDelivery['carrier_id']
                        : 0;
                    $bagId = isset($resolvedDelivery['bag_id']) && (int) $resolvedDelivery['bag_id'] > 0
                        ? (int) $resolvedDelivery['bag_id']
                        : 0;
                    if ($deliveryMode === 'shipment' && $shipmentKind === 'bag_deferred') {
                        $this->validateBagDeferredContext($bagId, $order, $deliveryStatus);
                    } else {
                        $bagId = 0;
                    }
                    $trackingCode = trim((string) ($resolvedDelivery['tracking_code'] ?? ''));
                    $estimatedDeliveryAt = trim((string) ($resolvedDelivery['estimated_delivery_at'] ?? ''));
                    $logisticsNotes = trim((string) ($resolvedDelivery['logistics_notes'] ?? ''));

                    $carrierName = '';
                    if ($carrierId > 0) {
                        $carrier = $this->carriers->find($carrierId);
                        if (!$carrier || $carrier->status !== 'ativo') {
                            throw new \RuntimeException('Transportadora inválida ou inativa.');
                        }
                        $carrierName = $carrier->name;
                    }

                    $beforeShipping = isset($order['shipping_info']) && is_array($order['shipping_info']) ? $order['shipping_info'] : [];
                    $shippingInfo = $beforeShipping;
                    $shippingInfo['delivery_mode'] = $deliveryMode;
                    $shippingInfo['shipment_kind'] = $shipmentKind;
                    $shippingInfo['status'] = $deliveryStatus;
                    $shippingInfo['carrier_id'] = $carrierId > 0 ? $carrierId : null;
                    $shippingInfo['bag_id'] = $bagId > 0 ? $bagId : null;
                    $shippingInfo['carrier'] = $carrierName;
                    $shippingInfo['tracking_code'] = $trackingCode !== '' ? $trackingCode : null;
                    $shippingInfo['estimated_delivery_at'] = $estimatedDeliveryAt !== '' ? $estimatedDeliveryAt : null;
                    $shippingInfo['eta'] = $shippingInfo['estimated_delivery_at'];
                    $shippingInfo['logistics_notes'] = $logisticsNotes !== '' ? $logisticsNotes : null;
                    $this->appendDeliveryTimelineEvents($beforeShipping, $shippingInfo, $deliveryStatus);

                    $candidateOrder = $order;
                    $candidateOrder['delivery_mode'] = $deliveryMode;
                    $candidateOrder['shipment_kind'] = $shipmentKind;
                    $candidateOrder['delivery_status'] = $deliveryStatus;
                    $candidateOrder['fulfillment_status'] = $deliveryStatus;
                    $candidateOrder['bag_id'] = $bagId > 0 ? $bagId : null;
                    $candidateOrder['shipping_info'] = $shippingInfo;

                    $snapshot = $this->lifecycle->computeSnapshot($candidateOrder);
                    $snapshot['status'] = (string) ($snapshot['order_status'] ?? 'open');
                    $payload = $this->lifecycle->toPersistencePayload($snapshot, $candidateOrder);
                    $payload['shipping_info'] = $shippingInfo;
                    $this->orders->updateStatusComplete($orderId, (string) ($snapshot['status'] ?? 'open'), null, $payload);

                    $success = 'Entrega atualizada com sucesso.';
                    $query = http_build_query(array_filter($filters, static fn ($v) => $v !== ''));
                    header('Location: entrega-acompanhamento.php' . ($query !== '' ? '?' . $query . '&success=' . urlencode($success) : '?success=' . urlencode($success)));
                    exit;
                } catch (\Throwable $e) {
                    $errors[] = 'Falha ao atualizar entrega: ' . $e->getMessage();
                }
            }
        }

        if ($success === '' && isset($_GET['success'])) {
            $success = trim((string) $_GET['success']);
        }

        $rows = [];
        try {
            $rows = $this->orders->listDeliveries($filters);
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar acompanhamentos: ' . $e->getMessage();
        }

        View::render('deliveries/tracking', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'filters' => $filters,
            'deliveryStatusOptions' => OrderService::fulfillmentStatusOptions(),
            'deliveryModeOptions' => OrderService::deliveryModeOptions(),
            'shipmentKindOptions' => OrderService::shipmentKindOptions(),
            'carrierOptions' => $this->carriers->active(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Acompanhamento de entregas',
        ]);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function appendDeliveryTimelineEvents(array $before, array &$after, string $newStatus): void
    {
        $timeline = [];
        $currentTimeline = $after['timeline'] ?? ($before['timeline'] ?? []);
        if (is_array($currentTimeline)) {
            foreach ($currentTimeline as $event) {
                if (is_array($event)) {
                    $timeline[] = $event;
                }
            }
        }

        $beforeMode = OrderService::normalizeDeliveryMode((string) ($before['delivery_mode'] ?? ''));
        $afterMode = OrderService::normalizeDeliveryMode((string) ($after['delivery_mode'] ?? ''));
        if ($afterMode !== '' && $afterMode !== $beforeMode) {
            $label = OrderService::deliveryModeOptions()[$afterMode] ?? $afterMode;
            $timeline[] = ['type' => 'delivery_defined', 'label' => 'Entrega definida: ' . $label, 'at' => date('Y-m-d H:i:s')];
        }

        $beforeShipmentKind = OrderService::normalizeShipmentKind(
            (string) ($before['shipment_kind'] ?? ''),
            $afterMode !== '' ? $afterMode : $beforeMode
        );
        $afterShipmentKind = OrderService::normalizeShipmentKind(
            (string) ($after['shipment_kind'] ?? ''),
            $afterMode !== '' ? $afterMode : $beforeMode
        );
        if ($afterShipmentKind !== null && $afterShipmentKind !== $beforeShipmentKind) {
            $kindLabel = OrderService::shipmentKindOptions()[$afterShipmentKind] ?? $afterShipmentKind;
            $timeline[] = ['type' => 'delivery_defined', 'label' => 'Tipo de envio definido: ' . $kindLabel, 'at' => date('Y-m-d H:i:s')];
        }

        $beforeCarrier = (int) ($before['carrier_id'] ?? 0);
        $afterCarrier = (int) ($after['carrier_id'] ?? 0);
        if ($afterCarrier > 0 && $afterCarrier !== $beforeCarrier) {
            $carrierLabel = trim((string) ($after['carrier'] ?? '')) ?: ('#' . $afterCarrier);
            $timeline[] = ['type' => 'carrier_defined', 'label' => 'Transportadora definida: ' . $carrierLabel, 'at' => date('Y-m-d H:i:s')];
        }

        $beforeTracking = trim((string) ($before['tracking_code'] ?? ''));
        $afterTracking = trim((string) ($after['tracking_code'] ?? ''));
        if ($afterTracking !== '' && $afterTracking !== $beforeTracking) {
            $timeline[] = ['type' => 'tracking_informed', 'label' => 'Código de rastreio informado: ' . $afterTracking, 'at' => date('Y-m-d H:i:s')];
        }

        $beforeStatus = OrderService::normalizeFulfillmentStatus((string) ($before['status'] ?? 'pending'));
        $afterStatus = OrderService::normalizeFulfillmentStatus($newStatus);
        if ($afterStatus !== $beforeStatus) {
            $statusLabel = OrderService::fulfillmentStatusOptions()[$afterStatus] ?? $afterStatus;
            $timeline[] = ['type' => 'delivery_status_changed', 'label' => 'Status de entrega alterado: ' . $statusLabel, 'at' => date('Y-m-d H:i:s')];
        }
        if ($afterStatus === 'delivered' && $beforeStatus !== 'delivered') {
            $timeline[] = ['type' => 'delivery_completed', 'label' => 'Entrega concluída', 'at' => date('Y-m-d H:i:s')];
        }

        $after['timeline'] = $timeline;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function validateBagDeferredContext(int $bagId, array $order, string $deliveryStatus): void
    {
        if ($bagId <= 0) {
            throw new \RuntimeException('Selecione uma sacolinha válida para envio diferido.');
        }

        $bag = $this->bags->find($bagId);
        if (!$bag) {
            throw new \RuntimeException('Sacolinha informada não existe.');
        }

        $bagStatus = strtolower(trim((string) ($bag->status ?? '')));
        if ($bagStatus === 'cancelada') {
            throw new \RuntimeException('Sacolinha cancelada não pode receber logística de envio diferido.');
        }

        $orderPersonId = $this->resolveOrderPersonId($order);
        $bagPersonId = (int) ($bag->personId ?? 0);
        if ($orderPersonId > 0 && $bagPersonId > 0 && $orderPersonId !== $bagPersonId) {
            throw new \RuntimeException('Sacolinha informada pertence a outra cliente.');
        }

        if ($deliveryStatus === 'pending') {
            if ($bagStatus !== 'aberta') {
                throw new \RuntimeException('Sacolinha informada não está aberta para logística pendente.');
            }

            $expiresAt = $this->resolveBagExpiresAt($bag);
            if ($expiresAt !== null) {
                $expiresAtTs = strtotime($expiresAt);
                if ($expiresAtTs !== false && $expiresAtTs < time()) {
                    throw new \RuntimeException('Sacolinha informada venceu em ' . date('d/m/Y H:i', $expiresAtTs) . '.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $order
     */
    private function resolveOrderPersonId(array $order): int
    {
        $candidates = [
            $order['pessoa_id'] ?? null,
            $order['customer_id'] ?? null,
            $order['person_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $personId = (int) $candidate;
            if ($personId > 0) {
                return $personId;
            }
        }

        return 0;
    }

    private function resolveBagExpiresAt($bag): ?string
    {
        $expectedCloseAt = trim((string) ($bag->expectedCloseAt ?? ''));
        if ($expectedCloseAt !== '') {
            return $expectedCloseAt;
        }

        $openedAt = trim((string) ($bag->openedAt ?? ''));
        if ($openedAt === '') {
            return null;
        }

        $timestamp = strtotime($openedAt . ' +30 days');
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
