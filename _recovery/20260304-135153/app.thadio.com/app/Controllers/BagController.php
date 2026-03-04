<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Bag;
use App\Repositories\BagRepository;
use App\Repositories\CarrierRepository;
use App\Repositories\OrderRepository;
use App\Services\BagService;
use App\Services\OrderLifecycleService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class BagController
{
    private BagRepository $repository;
    private CarrierRepository $carriers;
    private OrderRepository $orders;
    private OrderLifecycleService $lifecycle;
    private BagService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new BagRepository($pdo);
        $this->carriers = new CarrierRepository($pdo);
        $this->orders = new OrderRepository($pdo);
        $this->lifecycle = new OrderLifecycleService();
        $this->service = new BagService();
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
            try {
                Auth::requirePermission('bags.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Sacolinha excluida.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir sacolinha: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all($statusFilter !== '' ? $statusFilter : null);
        $openCount = $this->repository->countOpen();

        View::render('bags/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'statusFilter' => $statusFilter,
            'statusOptions' => BagService::statusOptions(),
            'openCount' => $openCount,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Sacolinhas',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();
        $items = [];
        $shipments = [];
        $carrierOptions = $this->carriers->active();

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('bags.edit', $this->repository->getPdo());
            $editing = true;
            $bag = $this->repository->find((int) $_GET['id']);
            if ($bag) {
                $formData = $this->bagToForm($bag);
                $items = $bag->id ? $this->repository->listItems((int) $bag->id) : [];
                $shipments = $bag->id ? $this->repository->listShipments((int) $bag->id) : [];
            } else {
                $errors[] = 'Sacolinha não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string) ($_POST['action'] ?? 'save'));
            if (in_array($action, ['dispatch', 'deliver'], true)) {
                Auth::requirePermission('bags.edit', $this->repository->getPdo());
                $bagId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                $editing = $bagId > 0;
                $bag = $bagId > 0 ? $this->repository->find($bagId) : null;
                if (!$bag) {
                    $errors[] = 'Sacolinha não encontrada.';
                } else {
                    try {
                        $success = $action === 'dispatch'
                            ? $this->dispatchBag($bag, $_POST)
                            : $this->deliverBag($bag, $_POST);
                        $bag = $this->repository->find((int) $bag->id) ?? $bag;
                        $formData = $this->bagToForm($bag);
                        $items = $bag->id ? $this->repository->listItems((int) $bag->id) : [];
                        $shipments = $bag->id ? $this->repository->listShipments((int) $bag->id) : [];
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao atualizar remessa: ' . $e->getMessage();
                        $formData = array_merge($this->emptyForm(), $_POST);
                    }
                }
            } else {
                $editing = isset($_POST['id']) && $_POST['id'] !== '';
                Auth::requirePermission($editing ? 'bags.edit' : 'bags.create', $this->repository->getPdo());
                [$bag, $errors] = $this->service->validate($_POST);
                $editing = (bool) ($bag->id ?? false);
                if (empty($errors)) {
                    try {
                        $this->repository->save($bag);
                        $success = $editing ? 'Sacolinha atualizada com sucesso.' : 'Sacolinha aberta com sucesso.';
                        $formData = $this->bagToForm($bag);
                        $items = $bag->id ? $this->repository->listItems((int) $bag->id) : [];
                        $shipments = $bag->id ? $this->repository->listShipments((int) $bag->id) : [];
                    } catch (PDOException $e) {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                } else {
                    $formData = array_merge($this->emptyForm(), $_POST);
                }
            }
        }

        if ($editing && empty($shipments) && !empty($formData['id'])) {
            $shipments = $this->repository->listShipments((int) $formData['id']);
        }

        View::render('bags/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'items' => $items,
            'shipments' => $shipments,
            'carrierOptions' => $carrierOptions,
            'statusOptions' => BagService::statusOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Detalhe da sacolinha' : 'Abrir sacolinha',
        ]);
    }

    public function customerHistory(): void
    {
        $errors = [];
        $personId = isset($_GET['pessoa_id']) ? (int) $_GET['pessoa_id'] : 0;
        if ($personId <= 0) {
            $errors[] = 'Cliente inválido.';
        }

        $rows = $personId > 0 ? $this->repository->listByPerson($personId) : [];

        View::render('bags/customer', [
            'rows' => $rows,
            'errors' => $errors,
            'pessoaId' => $personId,
            'statusOptions' => BagService::statusOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Historico de sacolinhas',
        ]);
    }

    private function bagToForm($bag): array
    {
        return [
            'id' => $bag->id ?? '',
            'pessoa_id' => $bag->personId ?? '',
            'customer_name' => $bag->customerName ?? '',
            'customer_email' => $bag->customerEmail ?? '',
            'status' => $bag->status ?? 'aberta',
            'opened_at' => $this->formatDateTime($bag->openedAt),
            'expected_close_at' => $this->formatDateTime($bag->expectedCloseAt),
            'closed_at' => $this->formatDateTime($bag->closedAt),
            'opening_fee_value' => isset($bag->openingFeeValue) ? number_format((float) $bag->openingFeeValue, 2, '.', '') : '0.00',
            'opening_fee_paid' => !empty($bag->openingFeePaid),
            'opening_fee_paid_at' => $this->formatDateTime($bag->openingFeePaidAt),
            'notes' => $bag->notes ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dispatchBag(Bag $bag, array $input): string
    {
        $bagId = (int) ($bag->id ?? 0);
        if ($bagId <= 0) {
            throw new \RuntimeException('Sacolinha inválida.');
        }
        if (in_array($bag->status, ['cancelada', 'entregue'], true)) {
            throw new \RuntimeException('Sacolinha não pode ser despachada neste status.');
        }

        $carrierId = isset($input['shipment_carrier_id']) ? (int) $input['shipment_carrier_id'] : 0;
        $trackingCode = trim((string) ($input['shipment_tracking_code'] ?? ''));
        if ($carrierId <= 0) {
            throw new \RuntimeException('Transportadora da remessa é obrigatória.');
        }
        if ($trackingCode === '') {
            throw new \RuntimeException('Código de rastreio da remessa é obrigatório.');
        }
        $carrier = $this->carriers->find($carrierId);
        if (!$carrier || $carrier->status !== 'ativo') {
            throw new \RuntimeException('Transportadora inválida ou inativa.');
        }

        $shippedAt = $this->normalizeDateTime($input['shipment_shipped_at'] ?? null) ?? date('Y-m-d H:i:s');
        $estimatedDeliveryAt = $this->normalizeDateTime($input['shipment_estimated_delivery_at'] ?? null);
        $notes = trim((string) ($input['shipment_notes'] ?? ''));

        $shipmentId = $this->repository->createShipment($bagId, [
            'status' => 'shipped',
            'carrier_id' => $carrierId,
            'tracking_code' => $trackingCode,
            'estimated_delivery_at' => $estimatedDeliveryAt,
            'shipped_at' => $shippedAt,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        if ($shipmentId <= 0) {
            throw new \RuntimeException('Falha ao registrar remessa da sacolinha.');
        }

        $bag->status = 'despachada';
        if ($bag->closedAt === null || trim((string) $bag->closedAt) === '') {
            $bag->closedAt = $shippedAt;
        }
        $this->repository->save($bag);

        $shipment = $this->repository->latestShipment($bagId) ?? [];
        $this->syncBagOrders($bagId, $shipment, 'shipped', (string) $carrier->name);

        return 'Sacolinha despachada com sucesso.';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function deliverBag(Bag $bag, array $input): string
    {
        $bagId = (int) ($bag->id ?? 0);
        if ($bagId <= 0) {
            throw new \RuntimeException('Sacolinha inválida.');
        }
        if ($bag->status === 'cancelada') {
            throw new \RuntimeException('Sacolinha cancelada não pode ser entregue.');
        }

        $shipment = $this->repository->latestShipment($bagId);
        if (!$shipment) {
            throw new \RuntimeException('Nenhuma remessa encontrada para esta sacolinha.');
        }

        $deliveredAt = $this->normalizeDateTime($input['shipment_delivered_at'] ?? null) ?? date('Y-m-d H:i:s');
        $shipment['status'] = 'delivered';
        $shipment['delivered_at'] = $deliveredAt;
        if (empty($shipment['shipped_at'])) {
            $shipment['shipped_at'] = date('Y-m-d H:i:s');
        }
        $ok = $this->repository->updateShipment((int) ($shipment['id'] ?? 0), $shipment);
        if (!$ok) {
            throw new \RuntimeException('Falha ao atualizar remessa da sacolinha.');
        }

        $bag->status = 'entregue';
        if ($bag->closedAt === null || trim((string) $bag->closedAt) === '') {
            $bag->closedAt = $deliveredAt;
        }
        $this->repository->save($bag);

        $carrierName = '';
        $carrierId = isset($shipment['carrier_id']) ? (int) $shipment['carrier_id'] : 0;
        if ($carrierId > 0) {
            $carrier = $this->carriers->find($carrierId);
            $carrierName = $carrier ? (string) $carrier->name : '';
        }
        $this->syncBagOrders($bagId, $shipment, 'delivered', $carrierName);

        return 'Sacolinha marcada como entregue.';
    }

    /**
     * @param array<string, mixed> $shipment
     */
    private function syncBagOrders(int $bagId, array $shipment, string $fulfillmentStatus, string $carrierName): void
    {
        $orderIds = $this->repository->listOrderIds($bagId);
        if (empty($orderIds)) {
            return;
        }

        $carrierId = isset($shipment['carrier_id']) && (int) $shipment['carrier_id'] > 0
            ? (int) $shipment['carrier_id']
            : null;
        $trackingCode = trim((string) ($shipment['tracking_code'] ?? ''));
        $trackingCode = $trackingCode !== '' ? $trackingCode : null;
        $estimatedDeliveryAt = $this->normalizeDateTime($shipment['estimated_delivery_at'] ?? null);
        $shippedAt = $this->normalizeDateTime($shipment['shipped_at'] ?? null);
        $deliveredAt = $this->normalizeDateTime($shipment['delivered_at'] ?? null);

        foreach ($orderIds as $orderId) {
            try {
                $order = $this->orders->findOrderWithDetails((int) $orderId);
                if (!$order) {
                    continue;
                }

                $shippingInfo = isset($order['shipping_info']) && is_array($order['shipping_info'])
                    ? $order['shipping_info']
                    : [];
                $shippingInfo['delivery_mode'] = 'shipment';
                $shippingInfo['shipment_kind'] = 'bag_deferred';
                $shippingInfo['bag_id'] = $bagId;
                $shippingInfo['status'] = $fulfillmentStatus;
                $shippingInfo['carrier_id'] = $carrierId;
                $shippingInfo['carrier'] = $carrierName !== '' ? $carrierName : (string) ($shippingInfo['carrier'] ?? '');
                $shippingInfo['tracking_code'] = $trackingCode;
                $shippingInfo['estimated_delivery_at'] = $estimatedDeliveryAt;
                $shippingInfo['eta'] = $estimatedDeliveryAt;
                $shippingInfo['shipped_at'] = $shippedAt;
                if ($fulfillmentStatus === 'delivered') {
                    $shippingInfo['delivered_at'] = $deliveredAt ?? date('Y-m-d H:i:s');
                }
                if (!isset($shippingInfo['timeline']) || !is_array($shippingInfo['timeline'])) {
                    $shippingInfo['timeline'] = [];
                }
                $shippingInfo['timeline'][] = [
                    'type' => $fulfillmentStatus === 'delivered' ? 'delivery_completed' : 'delivery_status_changed',
                    'label' => $fulfillmentStatus === 'delivered'
                        ? 'Sacolinha #' . $bagId . ' entregue'
                        : 'Sacolinha #' . $bagId . ' despachada' . ($trackingCode ? ' (' . $trackingCode . ')' : ''),
                    'at' => date('Y-m-d H:i:s'),
                ];

                $candidateOrder = $order;
                $candidateOrder['delivery_mode'] = 'shipment';
                $candidateOrder['shipment_kind'] = 'bag_deferred';
                $candidateOrder['bag_id'] = $bagId;
                $candidateOrder['delivery_status'] = $fulfillmentStatus;
                $candidateOrder['fulfillment_status'] = $fulfillmentStatus;
                $candidateOrder['shipping_info'] = $shippingInfo;

                $snapshot = $this->lifecycle->computeSnapshot($candidateOrder);
                $snapshot['status'] = (string) ($snapshot['order_status'] ?? ($order['status'] ?? 'open'));
                $payload = $this->lifecycle->toPersistencePayload($snapshot, $candidateOrder);
                $payload['shipping_info'] = $shippingInfo;
                $payload['delivery_mode'] = 'shipment';
                $payload['shipment_kind'] = 'bag_deferred';
                $payload['bag_id'] = $bagId;
                $this->orders->updateStatusComplete((int) $orderId, (string) ($snapshot['status'] ?? 'open'), null, $payload);
            } catch (\Throwable $e) {
                error_log('Falha ao sincronizar pedido da sacolinha #' . $bagId . ': ' . $e->getMessage());
            }
        }
    }

    private function emptyForm(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            'id' => '',
            'pessoa_id' => '',
            'customer_name' => '',
            'customer_email' => '',
            'status' => 'aberta',
            'opened_at' => $this->formatDateTime($now),
            'expected_close_at' => $this->formatDateTime(date('Y-m-d H:i:s', strtotime('+30 days'))),
            'closed_at' => '',
            'opening_fee_value' => '0.00',
            'opening_fee_paid' => false,
            'opening_fee_paid_at' => '',
            'notes' => '',
        ];
    }

    private function formatDateTime(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('Y-m-d\TH:i', $timestamp);
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (strpos($raw, 'T') !== false) {
            $raw = str_replace('T', ' ', $raw);
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
