<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\VoucherAccount;
use App\Repositories\CustomerHistoryRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderReturnRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Services\OrderReturnService;
use App\Services\ConsignmentCreditService;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class OrderReturnController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private OrderReturnRepository $returns;
    private OrderReturnService $service;
    private VoucherAccountRepository $vouchers;
    private ?CustomerHistoryRepository $history;
    private OrderRepository $orders;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->returns = new OrderReturnRepository($pdo);
        $this->service = new OrderReturnService();
        $this->vouchers = new VoucherAccountRepository($pdo);
        $this->history = $pdo ? new CustomerHistoryRepository($pdo) : null;
        $this->orders = new OrderRepository($pdo);
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $refundFilter = isset($_GET['refund_status']) ? trim((string) $_GET['refund_status']) : '';
        $orderFilter = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($searchQuery === '') {
            $searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        }
        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : '';
        if ($sortKey === '') {
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'id';
        }
        $sortDir = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : '';
        if ($sortDir === '') {
            $sortDir = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'desc';
        }
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';
        $columnFilters = [];
        foreach (['id', 'order', 'customer', 'status', 'refund', 'amount', 'qty', 'date'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            $value = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';
            if ($value !== '') {
                $columnFilters[$param] = $value;
            }
        }

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_GET['success'])) {
            $success = trim((string) $_GET['success']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
            Auth::requirePermission('order_returns.edit', $this->returns->getPdo());
            $cancelId = (int) $_POST['cancel_id'];
            $returnRow = $cancelId > 0 ? $this->returns->find($cancelId) : null;
            if (!$returnRow) {
                $errors[] = 'Devolução não encontrada para cancelamento.';
            } else {
                [$undoMsgs, $undoErrors] = $this->undoReturn($cancelId);
                if (!empty($undoErrors)) {
                    $errors = array_merge($errors, $undoErrors);
                } else {
                    $success = 'Devolução cancelada com sucesso.' . (!empty($undoMsgs) ? ' ' . implode(' ', $undoMsgs) : '');
                }
            }
        }

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($refundFilter !== '') {
            $filters['refund_status'] = $refundFilter;
        }
        if ($orderFilter > 0) {
            $filters['order_id'] = $orderFilter;
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        foreach ($columnFilters as $key => $value) {
            $filters[$key] = $value;
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 120;
        $perPageOptions = [50, 100, 120, 200];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 120;
        }

        $totalReturns = $this->returns->count($filters);
        $totalPages = max(1, (int) ceil($totalReturns / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $rows = $this->returns->list($filters, $perPage, $offset, $sortKey, $sortDir);

        View::render('order_returns/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'statusFilter' => $statusFilter,
            'refundFilter' => $refundFilter,
            'orderFilter' => $orderFilter,
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'statusOptions' => OrderReturnService::statusOptions(),
            'refundStatusOptions' => OrderReturnService::refundStatusOptions(),
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalReturns' => $totalReturns,
            'totalPages' => $totalPages,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Devoluções de pedidos',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $returnRow = null;
        $returnItems = [];
        $orderItems = [];
        $alreadyReturned = [];
        $availableMap = [];
        $orderData = null;
        $customer = null;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $returnId = null;
        if (isset($_GET['id'])) {
            $returnId = (int) $_GET['id'];
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && $_POST['id'] !== '') {
            $returnId = (int) $_POST['id'];
        }

        if ($returnId) {
            Auth::requirePermission('order_returns.edit', $this->returns->getPdo());
            $returnRow = $this->returns->find($returnId);
            if ($returnRow) {
                $editing = true;
                $returnItems = $returnRow['items'] ?? [];
            } else {
                $errors[] = 'Devolução não encontrada.';
            }
        } else {
            Auth::requirePermission('order_returns.create', $this->returns->getPdo());
        }

        $orderId = 0;
        if ($editing && $returnRow) {
            $orderId = (int) ($returnRow['order_id'] ?? 0);
        } elseif (isset($_GET['order_id'])) {
            $orderId = (int) $_GET['order_id'];
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
            $orderId = (int) $_POST['order_id'];
        }

        if ($orderId > 0) {
            $orderData = $this->loadOrder($orderId, $errors);
            if ($orderData) {
                $orderItems = $this->orderItemsFromOrder($orderData);
                $customer = $this->extractCustomer($orderData);
                $ignoreReturnId = $editing ? $returnId : null;
                $alreadyReturned = $this->returns->returnedQuantitiesByOrder($orderId, $ignoreReturnId);
                $availableMap = $this->buildAvailableMap($orderItems, $alreadyReturned, $returnItems);
            }
        }

        $formData = $this->emptyForm($orderId, $customer);
        if ($returnRow) {
            $formData = $this->returnToForm($returnRow);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$orderId || !$orderData) {
                $errors[] = 'Pedido inválido para processar devolução.';
            } else {
                $_POST['order_id'] = $orderId;
                if ($customer) {
                    $_POST['pessoa_id'] = $customer['id'] ?? null;
                    $_POST['customer_name'] = $customer['name'] ?? null;
                    $_POST['customer_email'] = $customer['email'] ?? null;
                }
                [$return, $items, $validationErrors] = $this->service->validate(
                    $_POST,
                    $orderItems,
                    $alreadyReturned,
                    $returnItems
                );
                $errors = array_merge($errors, $validationErrors);
                if (empty($errors)) {
                    if ($editing && $returnRow && !empty($returnRow['restocked_at']) && !$return->restockedAt) {
                        $return->restockedAt = $returnRow['restocked_at'];
                    }
                    $currentUser = Auth::user();
                    if (!$return->createdBy && isset($currentUser['id'])) {
                        $return->createdBy = (int) $currentUser['id'];
                    }
                    $savedId = $this->returns->save($return, $items);
                    $return->id = $savedId;
                    $restockMessages = [];
                    $consignmentMessages = [];
                    $consignmentErrors = [];
                    if ($this->shouldRestock($return)) {
                        Auth::requirePermission('order_returns.restock', $this->returns->getPdo());
                        $restockMessages = $this->restockItems($items);
                        if ($this->hasRestockFailures($restockMessages)) {
                            $errors[] = 'Falha ao restocar produtos da devolução.';
                        } else {
                            $this->returns->markRestocked($savedId);
                            $return->restockedAt = date('Y-m-d H:i:s');
                            if ($this->pdo) {
                                $consignmentService = new ConsignmentCreditService($this->pdo);
                                [$consignmentMessages, $consignmentErrors] = $consignmentService->debitForReturn($return, $items);
                            }
                        }
                    }
                    if ($customer) {
                        $this->logHistory($customer['id'] ?? 0, 'return_create', [
                            'order_id' => $orderId,
                            'return_id' => $savedId,
                            'status' => $return->status,
                            'refund_method' => $return->refundMethod,
                            'items' => $this->historyItems($items),
                        ]);
                        if (!empty($restockMessages)) {
                            $this->logHistory($customer['id'] ?? 0, 'return_restock', [
                                'return_id' => $savedId,
                                'notes' => $restockMessages,
                            ]);
                        }
                    }

                    $voucherCreated = false;
                    $voucherMessage = '';
                    $shouldGenerateVoucher = $return->refundMethod === 'voucher';
                    if ($shouldGenerateVoucher && !$return->voucherAccountId) {
                        Auth::requirePermission('order_returns.refund', $this->returns->getPdo());
                        [$voucherCreated, $voucherMessage] = $this->createVoucherFromReturn($return, $customer, $savedId);
                    }

                    if ($voucherCreated) {
                        $this->returns->updateRefundStatus($savedId, 'done', $return->voucherAccountId);
                        if ($customer) {
                            $this->logHistory($customer['id'] ?? 0, 'return_refund', [
                                'return_id' => $savedId,
                                'refund_method' => 'voucher',
                                'voucher_id' => $return->voucherAccountId,
                                'amount' => $return->refundAmount,
                            ]);
                        }
                    }

                    $returnRow = $this->returns->find($savedId);
                    $returnItems = $returnRow['items'] ?? $items;
                    $formData = $this->returnToForm($returnRow ?? $return);
                    $alreadyReturned = $this->returns->returnedQuantitiesByOrder($orderId, $savedId);
                    $availableMap = $this->buildAvailableMap($orderItems, $alreadyReturned, $returnItems);
                    $editing = true;
                    $successParts = ['Devolução salva com sucesso.'];
                    if (!empty($restockMessages)) {
                        $successParts[] = implode(' ', $restockMessages);
                    }
                    if (!empty($consignmentMessages)) {
                        $successParts[] = implode(' ', $consignmentMessages);
                    }
                    if (!empty($consignmentErrors)) {
                        $successParts[] = 'Falhas consignação: ' . implode(' ', $consignmentErrors);
                    }
                    if ($voucherMessage !== '') {
                        $successParts[] = $voucherMessage;
                    }
                    $success = implode(' ', $successParts);
                } else {
                    $formData = array_merge($formData, $_POST);
                }
            }
        }

        View::render('order_returns/form', [
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'formData' => $formData,
            'orderId' => $orderId,
            'orderItems' => $orderItems,
            'returnItems' => $returnItems,
            'alreadyReturned' => $alreadyReturned,
            'availableMap' => $availableMap,
            'orderData' => $orderData,
            'customer' => $customer,
            'statusOptions' => OrderReturnService::statusOptions(),
            'returnMethodOptions' => OrderReturnService::returnMethodOptions(),
            'refundMethodOptions' => OrderReturnService::refundMethodOptions(),
            'refundStatusOptions' => OrderReturnService::refundStatusOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar devolução' : 'Nova devolução',
        ]);
    }

    private function emptyForm(int $orderId, ?array $customer): array
    {
        return [
            'id' => '',
            'order_id' => $orderId ?: '',
            'pessoa_id' => $customer['id'] ?? '',
            'customer_name' => $customer['name'] ?? '',
            'customer_email' => $customer['email'] ?? '',
            'status' => 'received',
            'return_method' => 'immediate',
            'refund_method' => 'voucher',
            'refund_status' => 'done',
            'refund_amount' => '',
            'tracking_code' => '',
            'expected_at' => '',
            'received_at' => date('Y-m-d\TH:i'),
            'notes' => '',
        ];
    }

    /**
     * @param array<string, mixed>|OrderReturn $return
     */
    private function returnToForm($return): array
    {
        if ($return instanceof OrderReturn) {
            $return = [
                'id' => $return->id,
                'order_id' => $return->orderId,
                'pessoa_id' => $return->pessoaId,
                'customer_name' => $return->customerName,
                'customer_email' => $return->customerEmail,
                'status' => $return->status,
                'return_method' => $return->returnMethod,
                'refund_method' => $return->refundMethod,
                'refund_status' => $return->refundStatus,
                'refund_amount' => $return->refundAmount,
                'tracking_code' => $return->trackingCode,
                'expected_at' => $return->expectedAt,
                'received_at' => $return->receivedAt,
                'notes' => $return->notes,
                'voucher_account_id' => $return->voucherAccountId,
                'restocked_at' => $return->restockedAt,
            ];
        }

        return [
            'id' => $return['id'] ?? '',
            'order_id' => $return['order_id'] ?? '',
            'pessoa_id' => $return['pessoa_id'] ?? '',
            'customer_name' => $return['customer_name'] ?? '',
            'customer_email' => $return['customer_email'] ?? '',
            'status' => $return['status'] ?? 'awaiting_item',
            'return_method' => $return['return_method'] ?? 'dropoff',
            'refund_method' => $return['refund_method'] ?? 'voucher',
            'refund_status' => $return['refund_status'] ?? 'pending',
            'refund_amount' => isset($return['refund_amount']) ? number_format((float) $return['refund_amount'], 2, '.', '') : '',
            'tracking_code' => $return['tracking_code'] ?? '',
            'expected_at' => $return['expected_at'] ?? '',
            'received_at' => $return['received_at'] ?? '',
            'notes' => $return['notes'] ?? '',
            'voucher_account_id' => $return['voucher_account_id'] ?? '',
            'restocked_at' => $return['restocked_at'] ?? '',
        ];
    }

    private function loadOrder(int $orderId, array &$errors): ?array
    {
        try {
            $order = $this->orders->findOrderWithDetails($orderId);
            if (!$order) {
                $errors[] = 'Pedido não encontrado.';
                return null;
            }

            return $order;
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar pedido: ' . $e->getMessage();
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderItemsFromOrder(array $order): array
    {
        $items = [];
        $lines = $order['line_items'] ?? [];
        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            if ($lineId <= 0) {
                continue;
            }
            $qty = (int) ($line['quantity'] ?? 0);
            $unitPrice = $this->unitPriceFromLine($line);
            $items[$lineId] = [
                'line_id' => $lineId,
                'product_sku' => (int) ($line['product_sku'] ?? 0),
                'variation_id' => (int) ($line['variation_id'] ?? 0),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'name' => (string) ($line['name'] ?? ''),
                'sku' => (string) ($line['sku'] ?? ''),
            ];
        }
        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $orderItems
     * @param array<int, int> $returned
     * @param array<int, array<string, mixed>> $currentItems
     * @return array<int, int>
     */
    private function buildAvailableMap(array $orderItems, array $returned, array $currentItems): array
    {
        $currentMap = [];
        foreach ($currentItems as $item) {
            $lineId = isset($item['order_item_id']) ? (int) $item['order_item_id'] : 0;
            if ($lineId > 0) {
                $currentMap[$lineId] = ($currentMap[$lineId] ?? 0) + (int) ($item['quantity'] ?? 0);
            }
        }

        $available = [];
        foreach ($orderItems as $lineId => $item) {
            $sold = (int) ($item['quantity'] ?? 0);
            $already = $returned[$lineId] ?? 0;
            $current = $currentMap[$lineId] ?? 0;
            $available[$lineId] = max(0, $sold - $already + $current);
        }
        return $available;
    }

    private function extractCustomer(array $order): array
    {
        $billing = $order['billing'] ?? [];
        $shipping = $order['shipping'] ?? [];
        $name = trim((string) ($billing['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) (($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')));
        }
        if ($name === '') {
            $name = trim((string) ($shipping['full_name'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) (($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')));
        }
        if ($name === '') {
            $name = (string) ($order['customer_note'] ?? 'Cliente');
        }
        $email = trim((string) ($billing['email'] ?? $shipping['email'] ?? ''));
        return [
            'id' => $this->resolvePersonIdFromOrderRow($order) ?: null,
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @param array<int, OrderReturnItem> $items
     * @return array<int, string>
     */
    private function restockItems(array $items): array
    {
        $messages = [];
        $products = new \App\Repositories\ProductRepository($this->pdo);

        foreach ($items as $item) {
            $productSku = (int) ($item->productSku ?? 0);
            $quantity = max(0, (int) ($item->quantity ?? 0));
            if ($productSku <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $products->find($productSku);
            if (!$product) {
                $messages[] = 'Produto SKU ' . $productSku . ' não encontrado para reposição de disponibilidade.';
                continue;
            }

            $ok = $products->incrementQuantity(
                $productSku,
                $quantity,
                'devolucao',
                'Reposição automática por devolução'
            );
            if ($ok) {
                $messages[] = 'SKU ' . $productSku . ': +' . $quantity . ' unidade(s) disponíveis.';
            } else {
                $messages[] = 'Falha ao repor disponibilidade do SKU ' . $productSku . '.';
            }
        }

        return $messages;
    }

    /**
     * Subtract quantities from products in sistema local to undo a previous restock.
     * @param array<int, OrderReturnItem> $items
     * @return array<int, string> messages
     */
    private function unRestockItems(array $items): array
    {
        $messages = [];
        $products = new \App\Repositories\ProductRepository($this->pdo);

        foreach ($items as $item) {
            $productSku = (int) ($item->productSku ?? 0);
            $quantity = max(0, (int) ($item->quantity ?? 0));
            if ($productSku <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $products->find($productSku);
            if (!$product) {
                $messages[] = 'Produto SKU ' . $productSku . ' não encontrado para desfazer reposição de disponibilidade.';
                continue;
            }

            $currentQty = (int) ($product['quantity'] ?? 0);
            if ($currentQty < $quantity) {
                $messages[] = 'SKU ' . $productSku . ': quantidade atual insuficiente para desfazer reposição.';
                continue;
            }

            $ok = $products->decrementQuantity(
                $productSku,
                $quantity,
                null,
                'Desfazer reposição de devolução cancelada'
            );
            if ($ok) {
                $messages[] = 'SKU ' . $productSku . ': -' . $quantity . ' unidade(s) (desfazer reposição).';
            } else {
                $messages[] = 'Falha ao desfazer reposição do SKU ' . $productSku . '.';
            }
        }

        return $messages;
    }

    /**
     * Undo a return: revert restock, revert voucher (if possible), update statuses and log history.
     * @return array{0: array<int, string>, 1: array<int, string>} [messages, errors]
     */
    private function undoReturn(int $returnId): array
    {
        $errors = [];
        $messages = [];
        $returnRow = $this->returns->find($returnId);
        if (!$returnRow) {
            $errors[] = 'Devolução não encontrada para cancelamento.';
            return [$messages, $errors];
        }

        // If refund was done and not voucher, we cannot safely revert automatically
        $refundStatus = $returnRow['refund_status'] ?? '';
        $refundMethod = $returnRow['refund_method'] ?? '';
        $voucherId = isset($returnRow['voucher_account_id']) ? (int) $returnRow['voucher_account_id'] : null;
        if ($refundStatus === 'done' && $refundMethod !== 'voucher' && $refundMethod !== 'none') {
            $errors[] = 'Devolução com reembolso concluído não pode ser cancelada automaticamente (método: ' . $refundMethod . ').';
            return [$messages, $errors];
        }

        $items = [];
        foreach ($returnRow['items'] ?? [] as $ri) {
            $items[] = \App\Models\OrderReturnItem::fromArray($ri);
        }
        $personId = $this->resolvePersonIdFromReturnRow($returnRow);

        // If restocked, undo the stock change
        if (!empty($returnRow['restocked_at'])) {
            Auth::requirePermission('order_returns.restock', $this->returns->getPdo());
            $restockMsgs = $this->unRestockItems($items);
            $hasRestockFailure = false;
            foreach ($restockMsgs as $msg) {
                $text = strtolower((string) $msg);
                if (str_contains($text, 'falha') || str_contains($text, 'insuficiente') || str_contains($text, 'não encontrado')) {
                    $hasRestockFailure = true;
                    break;
                }
            }
            if ($hasRestockFailure) {
                $errors[] = 'Não foi possível desfazer a reposição automaticamente.';
                $messages = array_merge($messages, $restockMsgs);
                return [$messages, $errors];
            }
            if (!empty($restockMsgs)) {
                $messages = array_merge($messages, $restockMsgs);
            }
            // clear restocked flag
            $this->returns->clearRestocked($returnId);
            $messages[] = 'Marca de reposição removida.';
            if ($this->history && $personId > 0) {
                $this->logHistory($personId, 'return_unrestock', [
                    'return_id' => $returnId,
                    'notes' => $restockMsgs,
                ]);
            }

            if ($this->pdo) {
                $orderId = (int) ($returnRow['order_id'] ?? 0);
                if ($orderId > 0) {
                    $consignmentService = new ConsignmentCreditService($this->pdo);
                    [$creditMsgs, $creditErrors] = $consignmentService->creditForReturnUndo(
                        $orderId,
                        $returnId,
                        date('Y-m-d H:i:s'),
                        $returnRow['notes'] ?? null
                    );
                    if (!empty($creditMsgs)) {
                        $messages = array_merge($messages, $creditMsgs);
                    }
                    if (!empty($creditErrors)) {
                        $errors = array_merge($errors, $creditErrors);
                    }
                }
            }
        }

        // If voucher was created as refund, trash it
        if ($voucherId && $refundStatus === 'done' && $refundMethod === 'voucher') {
            Auth::requirePermission('order_returns.refund', $this->returns->getPdo());
            try {
                $deletedBy = '';
                $currentUser = Auth::user();
                if (!empty($currentUser['email'])) {
                    $deletedBy = $currentUser['email'];
                } elseif (!empty($currentUser['name'])) {
                    $deletedBy = $currentUser['name'];
                }
                $this->vouchers->trash($voucherId, date('Y-m-d H:i:s'), $deletedBy ?: null);
                $messages[] = 'Crédito/cupom (ID ' . $voucherId . ') enviado para a lixeira.';
                if ($this->history && $personId > 0) {
                    $this->logHistory($personId, 'return_refund_revert', [
                        'return_id' => $returnId,
                        'voucher_id' => $voucherId,
                    ]);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao remover crédito/cupom: ' . $e->getMessage();
                return [$messages, $errors];
            }
            // unset voucher on return
            $this->returns->updateRefundStatus($returnId, 'pending', null);
        }

        // finally mark return cancelled
        $this->returns->updateStatus($returnId, 'cancelled');

        if ($this->history && $personId > 0) {
            $this->logHistory($personId, 'return_cancel', [
                'return_id' => $returnId,
                'notes' => 'Devolução cancelada via sistema',
            ]);
        }

        return [$messages, $errors];
    }

    private function unitPriceFromLine(array $line): float
    {
        $qty = (int) ($line['quantity'] ?? 1);
        $total = isset($line['total']) ? (float) $line['total'] : 0.0;
        if ($qty <= 0) {
            $qty = 1;
        }
        return $qty > 0 ? ($total / $qty) : 0.0;
    }

    private function shouldRestock(OrderReturn $return): bool
    {
        return in_array($return->status, ['received', 'not_delivered'], true) && empty($return->restockedAt);
    }

    /**
     * @param array<int, string> $messages
     */
    private function hasRestockFailures(array $messages): bool
    {
        foreach ($messages as $msg) {
            $text = strtolower((string) $msg);
            if (str_contains($text, 'falha') || str_contains($text, 'insuficiente') || str_contains($text, 'não encontrado')) {
                return true;
            }
        }
        return false;
    }

    private function historyItems(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = [
                'product_sku' => $item->productSku,
                'variation_id' => $item->variationId,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
                'name' => $item->productName,
            ];
        }
        return $list;
    }

    private function logHistory(int $personId, string $action, array $payload): void
    {
        if ($personId <= 0 || !$this->history) {
            return;
        }
        $this->history->log($personId, $action, $payload);
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function createVoucherFromReturn(OrderReturn $return, ?array $customer, int $returnId): array
    {
        $personId = isset($customer['id']) ? (int) $customer['id'] : 0;
        if ($personId <= 0) {
            return [false, 'Crédito não criado: pedido sem cliente identificado.'];
        }

        $label = trim((string) ($_POST['voucher_label'] ?? ''));
        if ($label === '') {
            $label = 'Crédito devolução pedido #' . $return->orderId;
        }

        $account = VoucherAccount::fromArray([
            'pessoa_id' => $personId,
            'customer_name' => $customer['name'] ?? null,
            'customer_email' => $customer['email'] ?? null,
            'label' => $label,
            'type' => 'credito',
            'status' => 'ativo',
            'balance' => $return->refundAmount,
            'description' => 'Gerado automaticamente na devolução #' . $returnId,
        ]);

        try {
            $this->vouchers->save($account);
        } catch (\Throwable $e) {
            return [false, 'Erro ao criar crédito: ' . $e->getMessage()];
        }

        $return->voucherAccountId = $account->id;
        $this->registerReturnVoucherCreditEntry($return, (int) $account->id, $customer);
        return [true, 'Crédito criado e vinculado (ID ' . $account->id . ').'];
    }

    private function registerReturnVoucherCreditEntry(OrderReturn $return, int $voucherId, ?array $customer): void
    {
        if (!$this->pdo || $voucherId <= 0) {
            return;
        }
        $amount = (float) ($return->refundAmount ?? 0);
        if ($amount <= 0) {
            return;
        }

        $buyerName = $customer['name'] ?? $return->customerName ?? null;
        $buyerEmail = $customer['email'] ?? $return->customerEmail ?? null;
        $eventAt = $return->receivedAt ?? $return->createdAt ?? date('Y-m-d H:i:s');

        $repo = new VoucherCreditEntryRepository($this->pdo);
        try {
            $repo->insert([
                'voucher_account_id' => $voucherId,
                'vendor_pessoa_id' => 0,
                'order_id' => (int) ($return->orderId ?? 0),
                'order_item_id' => 0,
                'product_id' => null,
                'variation_id' => null,
                'sku' => null,
                'product_name' => null,
                'quantity' => 1,
                'unit_price' => null,
                'line_total' => null,
                'percent' => null,
                'credit_amount' => $amount,
                'sold_at' => null,
                'buyer_name' => $buyerName,
                'buyer_email' => $buyerEmail,
                'type' => 'credito',
                'event_type' => 'return',
                'event_id' => (int) ($return->id ?? 0),
                'event_label' => null,
                'event_notes' => null,
                'event_at' => $eventAt,
            ]);
        } catch (\Throwable $e) {
            // ignore to avoid blocking return flow
        }
    }

    private function resolvePersonIdFromOrderRow(array $order): int
    {
        $personId = isset($order['pessoa_id']) ? (int) $order['pessoa_id'] : 0;
        return $personId > 0 ? $personId : 0;
    }

    private function resolvePersonIdFromReturnRow(array $returnRow): int
    {
        $personId = isset($returnRow['pessoa_id']) ? (int) $returnRow['pessoa_id'] : 0;
        return $personId > 0 ? $personId : 0;
    }

    // REMOVIDO: ensureOrderRepository() - sistema 100% autônomo

    /**
     * Public wrapper to cancel a return from an external entrypoint.
     * Returns [messages, errors]
     */
    public function cancel(int $returnId): array
    {
        Auth::requirePermission('order_returns.edit', $this->returns->getPdo());
        return $this->undoReturn($returnId);
    }
}
