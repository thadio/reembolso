<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\OrderPayment;
use App\Models\OrderShipping;
use App\Models\Bag;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\VoucherAccount;
use App\Repositories\BagRepository;
use App\Repositories\CarrierRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\PaymentTerminalRepository;
use App\Repositories\CustomerHistoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\PersonRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Repositories\OrderReturnRepository;
use App\Repositories\OrderRepository;
use App\Repositories\SalesChannelRepository;
use App\Services\CatalogCustomerService;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\OrderLifecycleService;
use App\Services\OrderDeliveryPolicy;
use App\Services\ConsignmentCreditService;
use App\Services\OrderReturnService;
use App\Services\OrderFinanceSyncService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use PDO;

class OrderController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private OrderService $service;
    private SalesChannelRepository $salesChannels;
    private CarrierRepository $carriers;
    private PaymentMethodRepository $paymentMethods;
    private BankAccountRepository $bankAccounts;
    private PaymentTerminalRepository $paymentTerminals;
    private VoucherAccountRepository $voucherAccounts;
    private OrderReturnRepository $orderReturns;
    private BagRepository $bags;
    private OrderReturnService $orderReturnService;
    private ?CustomerHistoryRepository $history;
    private ProductRepository $products;
    private CustomerRepository $customers;
    private PersonRepository $persons;
    private OrderRepository $orders;
    private OrderLifecycleService $lifecycle;
    private OrderDeliveryPolicy $deliveryPolicy;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->service = new OrderService();
        $this->salesChannels = new SalesChannelRepository($pdo);
        $this->carriers = new CarrierRepository($pdo);
        $this->paymentMethods = new PaymentMethodRepository($pdo);
        $this->bankAccounts = new BankAccountRepository($pdo);
        $this->paymentTerminals = new PaymentTerminalRepository($pdo);
        $this->voucherAccounts = new VoucherAccountRepository($pdo);
        $this->orderReturns = new OrderReturnRepository($pdo);
        $this->bags = new BagRepository($pdo);
        $this->orderReturnService = new OrderReturnService();
        $this->history = $pdo ? new CustomerHistoryRepository($pdo) : null;
        $this->products = new ProductRepository($pdo);
        $this->customers = new CustomerRepository($pdo);
        $this->persons = new PersonRepository($pdo);
        $this->orders = new OrderRepository($pdo);
        $this->lifecycle = new OrderLifecycleService();
        $this->deliveryPolicy = new OrderDeliveryPolicy();
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        if (isset($_GET['success'])) {
            $success = trim((string) $_GET['success']);
        }
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($searchQuery === '') {
            $searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        }
        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : '';
        if ($sortKey === '') {
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'date';
        }
        $sortDir = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : '';
        if ($sortDir === '') {
            $sortDir = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'desc';
        }
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

        $columnFilters = [];
        foreach (['id', 'order', 'customer', 'state', 'origin', 'status', 'payment', 'fulfillment', 'total', 'date', 'items'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            $value = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';
            if ($value !== '') {
                $columnFilters[$param] = $value;
            }
        }

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
            $orderId = (int) $_POST['delete_id'];
            if ($orderId > 0) {
                try {
                    Auth::requirePermission('orders.delete', $this->pdo);
                    $previousOrder = $this->orders->findOrderWithDetails($orderId);
                    if (!$previousOrder) {
                        throw new \RuntimeException('Pedido não encontrado.');
                    }
                    $previousStatus = OrderService::normalizeOrderStatus((string) ($previousOrder['status'] ?? ''));
                    $userId = Auth::userId();
                    if ($userId === null || $userId <= 0) {
                        throw new \RuntimeException('Usuário autenticado inválido para exclusão.');
                    }
                    $deletedAt = date('Y-m-d H:i:s');
                    $this->orders->trash($orderId, $deletedAt, $userId);
                    $success = 'Pedido enviado para a lixeira.';
                    if (!$this->isTerminalStockStatus($previousStatus)) {
                        [$stockMessages, $stockErrors] = $this->restockOrderItems($orderId, 'Envio para lixeira');
                        $success = $this->appendConsignmentResult($success, $stockMessages, []);
                        if (!empty($stockErrors)) {
                            $errors = array_merge($errors, $stockErrors);
                        }
                    }
                    [$debitMessages, $debitErrors] = $this->applyConsignmentDebits($orderId, 'order_trash', [
                        'event_label' => 'Pedido na lixeira',
                    ]);
                    $success = $this->appendConsignmentResult($success, $debitMessages, $debitErrors);
                    $syncResult = $this->syncOrderFinanceEntries($orderId);
                    $success = $this->appendFinanceSyncMessage($success, $syncResult);
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao excluir pedido: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Pedido inválido para exclusão.';
            }
        }

        // Inline payment status update from list page
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inline_action'])) {
            $inlineAction = (string) $_POST['inline_action'];
            $inlineOrderId = (int) ($_POST['inline_order_id'] ?? 0);
            $inlineRedirect = 'pedido-list.php?' . http_build_query(array_filter([
                'page' => $_POST['_page'] ?? '',
                'per_page' => $_POST['_per_page'] ?? '',
                'status' => $_POST['_status'] ?? '',
                'q' => $_POST['_q'] ?? '',
                'sort_key' => $_POST['_sort_key'] ?? '',
                'sort_dir' => $_POST['_sort_dir'] ?? '',
            ], fn($v) => $v !== ''));

            if ($inlineOrderId > 0) {
                try {
                    if ($inlineAction === 'inline_payment') {
                        Auth::requirePermission('orders.payment', $this->pdo);
                        $currentOrder = $this->orders->findOrderWithDetails($inlineOrderId);
                        if (!$currentOrder) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }
                        $payment = OrderPayment::fromArray($_POST);
                        $paymentStatus = OrderService::normalizePaymentStatus($payment->status);
                        $paymentMethod = $payment->methodTitle ?: $payment->method;
                        $paymentMethod = $paymentMethod !== null ? trim((string) $paymentMethod) : '';
                        $candidateOrder = $currentOrder;
                        $candidateOrder['payment_status'] = $paymentStatus;
                        $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
                        $status = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
                        $paymentStatus = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? $paymentStatus));
                        $paymentMethodError = $this->validatePaidStatusPaymentMethodSelection($paymentStatus, $paymentMethod, 'inline');
                        if ($paymentMethodError !== null) {
                            throw new \RuntimeException($paymentMethodError);
                        }
                        $payload = $this->lifecycle->toPersistencePayload($snapshot, $currentOrder);
                        $payload['payment_method'] = $paymentMethod !== '' ? $paymentMethod : null;
                        $this->orders->updateStatusComplete($inlineOrderId, $status, null, $payload);
                        $success = 'Pagamento do pedido #' . $inlineOrderId . ' atualizado.';
                        [$creditMessages, $creditErrors] = $this->applyConsignmentCredits($inlineOrderId, $paymentStatus);
                        $success = $this->appendConsignmentResult($success, $creditMessages, $creditErrors);
                        $syncResult = $this->syncOrderFinanceEntries($inlineOrderId);
                        $success = $this->appendFinanceSyncMessage($success, $syncResult);
                    } elseif ($inlineAction === 'inline_fulfillment') {
                        if (!Auth::can('orders.fulfillment') && !Auth::can('orders.edit')) {
                            Auth::requirePermission('orders.fulfillment', $this->pdo);
                        }
                        $currentOrder = $this->orders->findOrderWithDetails($inlineOrderId);
                        if (!$currentOrder) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }
                        $newFulfillment = OrderService::normalizeFulfillmentStatus((string) ($_POST['fulfillment_status'] ?? 'pending'));
                        $candidateOrder = $currentOrder;
                        $candidateOrder['fulfillment_status'] = $newFulfillment;
                        $candidateOrder['delivery_status'] = $newFulfillment;
                        $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
                        $status = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
                        $payload = $this->lifecycle->toPersistencePayload($snapshot, $candidateOrder);
                        $this->orders->updateStatusComplete($inlineOrderId, $status, null, $payload);
                        $success = 'Entrega do pedido #' . $inlineOrderId . ' atualizada.';
                        $syncResult = $this->syncOrderFinanceEntries($inlineOrderId);
                        $success = $this->appendFinanceSyncMessage($success, $syncResult);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao atualizar pedido #' . $inlineOrderId . ': ' . $e->getMessage();
                }
            }
            if (!empty($success) && empty($errors)) {
                header('Location: ' . $inlineRedirect . '&success=' . urlencode($success));
                exit;
            }
        }

        $rows = [];
        $orderItems = [];
        $returnSummaryByOrder = [];
        $bagStatusByOrder = [];
        $bagFeeByOrder = [];
        $paymentMethodOptions = [];
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 120;
        $perPageOptions = [50, 100, 120, 200];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 120;
        }
        $totalOrders = 0;
        $totalPages = 1;

        if (!$this->connectionError && $this->pdo) {
            try {
                $paymentMethodOptions = $this->paymentMethods->active();
                $filters = [];
                if ($statusFilter !== '') {
                    $filters['status'] = $statusFilter;
                }
                if ($searchQuery !== '') {
                    $filters['search'] = $searchQuery;
                }
                foreach ($columnFilters as $key => $value) {
                    $filters[$key] = $value;
                }

                $totalOrders = $this->orders->countOrders($filters);
                $totalPages = max(1, (int) ceil($totalOrders / $perPage));
                if ($page > $totalPages) {
                    $page = $totalPages;
                }

                $offset = max(0, ($page - 1) * $perPage);
                $rows = $this->normalizeOrdersListRows(
                    $this->orders->listOrders($filters, $perPage, $offset, $sortKey, $sortDir)
                );

                $orderIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
                    return (int) ($row['order_id'] ?? 0);
                }, $rows))));

                if (!empty($orderIds)) {
                    $orderItems = $this->normalizeOrdersListItems(
                        $this->orders->listOrderItemsWithProducts($orderIds)
                    );
                    $rows = $this->applyOrdersItemsCount($rows, $orderItems);
                    $returnSummaryByOrder = $this->orderReturns->returnSummaryByOrderIds($orderIds);
                    $bagStatusByOrder = $this->bags->bagStatusByOrderIds($orderIds);
                    $bagFeeByOrder = $this->bags->openingFeeStatusByOrderIds($orderIds);
                    $rows = $this->normalizeOrdersListRows($rows, $returnSummaryByOrder, $bagFeeByOrder);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao carregar pedidos: ' . $e->getMessage();
            }
        }

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        View::render('orders/list', [
            'rows' => $rows,
            'orderItems' => $orderItems,
            'returnSummaryByOrder' => $returnSummaryByOrder,
            'bagStatusByOrder' => $bagStatusByOrder,
            'bagFeeByOrder' => $bagFeeByOrder,
            'errors' => $errors,
            'success' => $success,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'statusOptions' => OrderService::statusFilterOptions(),
            'paymentStatusOptions' => OrderService::paymentStatusOptions(),
            'paymentMethodOptions' => $paymentMethodOptions,
            'fulfillmentStatusOptions' => OrderService::fulfillmentStatusOptions(),
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Pedidos',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $fullEdit = false;
        $formData = $this->emptyForm();
        $orderData = null;
        $orderSummary = null;
        $items = [];
        $itemStocks = [];
        $productOptions = [];
        $customerOptions = [];
        $bagContext = null;
        $orderReturns = [];
        $orderReturnItems = [];
        $orderReturnAlreadyReturned = [];
        $orderReturnAvailableMap = [];
        $orderReturnFormData = [];
        $orderReturnItemsInput = [];
        $orderReturnErrors = [];
        $orderReturnSuccess = '';
        $consignmentPreview = null;
        $originalLineItemIds = [];
        $step = isset($_GET['step']) ? trim((string) $_GET['step']) : '';
        $allowedSteps = ['payment', 'shipping', 'status'];
        if (!in_array($step, $allowedSteps, true)) {
            $step = '';
        }
        $fullEdit = isset($_GET['edit']) && $_GET['edit'] === '1';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $salesChannelOptions = $this->salesChannels->activeNames();
        $carrierOptions = $this->carriers->active();
        $paymentMethodOptions = $this->paymentMethods->active();
        $bankAccountOptions = $this->bankAccounts->active();
        $paymentTerminalOptions = $this->paymentTerminals->active();
        $voucherAccountOptions = $this->voucherAccounts->active();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'update_customer_address') {
                $this->handleCustomerAddressUpdate();
                return;
            }
            if ($action === 'create_customer') {
                $this->handleCustomerCreate();
                return;
            }
            if ($action === 'ensure_open_bag') {
                $this->handleEnsureOpenBag();
                return;
            }
        }

        // Buscar produtos e clientes do banco local
        $productFilters = [
            'stock_positive' => true,
        ];
        $productOptions = $this->listAllProductsForOrderLocal($productFilters);
        $openBagLookup = $this->bags ? $this->bags->listOpenSummaryByPerson() : [];
        $customersFromDB = $this->listCustomersLocal();
        foreach ($customersFromDB as $customer) {
            $userId = (int) ($customer['user_id'] ?? $customer['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $customerOptions[] = $this->buildCustomerOption($customer, $openBagLookup);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            $editingId = (int) $_GET['id'];
            if ($editingId <= 0) {
                $errors[] = 'ID inválido.';
            } else {
                Auth::requirePermission('orders.view', $this->pdo);
                if ($fullEdit) {
                    Auth::requirePermission('orders.edit', $this->pdo);
                }
                try {
                    // MIGRADO: Usar OrderRepository local
                    $orderData = $this->orders->findOrderWithDetails($editingId);
                    if (!$orderData) {
                        throw new \RuntimeException('Pedido não encontrado.');
                    }
                    $orderData = $this->reconcileOrderLifecycleState($editingId, $orderData);
                    $formData = $this->orderToForm($orderData);
                    // MIGRADO: Removido parâmetro $repoLegado
                    $formData = $this->applyCustomerFallback($formData, $orderData);
                    if ($fullEdit) {
                        $formData['items'] = $this->orderItemsToForm($orderData);
                        $formData['payments'] = $this->orderPaymentsToForm($orderData);
                        $originalLineItemIds = $this->extractOrderLineItemIds($orderData);
                    }
                    $editing = true;
                    $items = $orderData['line_items'] ?? [];
                    $orderSummary = $this->buildSummary($orderData);
                    // MIGRADO: Removido parâmetro $repoLegado
                    $itemStocks = $this->resolveItemStocks($items);
                    $bagContext = $this->buildBagContext($orderData);
                    $formData = $this->applyLifecycleSnapshotToFormData($formData, $orderData, $bagContext);
                    $orderReturns = $this->orderReturns->listByOrder($editingId);
                    if (empty($orderReturnItems)) {
                        $orderReturnItems = $this->orderItemsForReturn($orderData);
                    }
                    if (empty($orderReturnAlreadyReturned)) {
                        $orderReturnAlreadyReturned = $this->orderReturns->returnedQuantitiesByOrder($editingId);
                    }
                    if (empty($orderReturnAvailableMap)) {
                        $orderReturnAvailableMap = $this->buildReturnAvailableMap($orderReturnItems, $orderReturnAlreadyReturned, []);
                    }
                    if (empty($orderReturnFormData)) {
                        $orderReturnFormData = $this->emptyReturnForm($editingId, $this->extractReturnCustomer($orderData));
                    }
                    $orderReturnItems = $this->orderItemsForReturn($orderData);
                    $orderReturnAlreadyReturned = $this->orderReturns->returnedQuantitiesByOrder($editingId);
                    $orderReturnAvailableMap = $this->buildReturnAvailableMap($orderReturnItems, $orderReturnAlreadyReturned, []);
                    $orderReturnFormData = $this->emptyReturnForm($editingId, $this->extractReturnCustomer($orderData));
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao carregar pedido: ' . $e->getMessage();
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$editing && isset($_GET['sku'])) {
            $quickSaleIdentifier = isset($_GET['sku']) ? (int) $_GET['sku'] : 0;
            
            if ($quickSaleIdentifier > 0) {
                $quickSaleQuantity = isset($_GET['quantity']) ? (int) $_GET['quantity'] : 0;
                if ($quickSaleQuantity <= 0) {
                    $quickSaleQuantity = 1;
                }
                $quickSaleVariationId = isset($_GET['variation_id']) ? (int) $_GET['variation_id'] : (int) ($_GET['variation'] ?? 0);
                if ($quickSaleVariationId < 0) {
                    $quickSaleVariationId = 0;
                }

                $quickItem = [
                    'product_sku' => (string) $quickSaleIdentifier,
                    'variation_id' => $quickSaleVariationId > 0 ? (string) $quickSaleVariationId : '',
                    'quantity' => (string) $quickSaleQuantity,
                ];

                // MIGRADO: Usar ProductRepository local (sem suporte a variações por enquanto)
                $quickProduct = $this->products->find($quickSaleIdentifier);
                if ($quickProduct) {
                    $quickItem['product_name'] = (string) ($quickProduct['nome'] ?? '');
                    if (!empty($quickProduct['sku'])) {
                        $quickItem['product_sku'] = (string) $quickProduct['sku'];
                    }
                    if (isset($quickProduct['preco_venda'])) {
                        $quickItem['price'] = (string) $quickProduct['preco_venda'];
                    }
                    
                    // Adicionar produto às opções se não estiver lá
                    $existingIds = array_map('intval', array_column($productOptions, 'sku'));
                    if (!in_array($quickSaleIdentifier, $existingIds, true)) {
                        // Adaptar formato para compatibilidade com frontend
                        $quickProduct['ID'] = $quickProduct['sku'];
                        $quickProduct['post_title'] = $quickProduct['nome'] ?? '';
                        $quickProduct['price'] = $quickProduct['preco_venda'] ?? 0;
                        $quickProduct['variations'] = []; // TODO: Implementar suporte a variações
                        $productOptions[] = $quickProduct;
                    }
                }

                $formData['items'] = array_merge([$quickItem], ($formData['items'] ?? []));
            }
        }

        if ($success === '' && isset($_GET['success'])) {
            $success = trim((string) $_GET['success']);
        }
        if ($orderReturnSuccess === '' && isset($_GET['return_success'])) {
            $orderReturnSuccess = trim((string) $_GET['return_success']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            $editingId = $editing ? (int) $_POST['id'] : 0;
            $action = $_POST['action'] ?? 'create';
            $preserveFormInput = false;
            if ($action === 'update_full') {
                $fullEdit = true;
            }
            // quick path: if cancelling a return from the order page, handle full undo (stock/voucher)
            if ($action === 'order_return_cancel') {
                Auth::requirePermission('order_returns.edit', $this->pdo);
                $returnId = isset($_POST['return_id']) ? (int) $_POST['return_id'] : 0;
                $returnRow = $returnId > 0 ? $this->orderReturns->find($returnId) : null;
                if (!$returnRow || (int) ($returnRow['order_id'] ?? 0) !== ($editing ? (int) $_POST['id'] : 0)) {
                    $orderReturnErrors[] = 'Devolução inválida para cancelamento.';
                    // continue to normal flow so view renders errors
                } else {
                    [$undoMsgs, $undoErrors] = $this->undoOrderReturn($returnId);
                    if (!empty($undoErrors)) {
                        $orderReturnErrors = array_merge($orderReturnErrors, $undoErrors);
                    } else {
                        $orderReturnSuccess = 'Devolução cancelada com sucesso.' . (!empty($undoMsgs) ? ' ' . implode(' ', $undoMsgs) : '');
                        header('Location: pedido-cadastro.php?id=' . ($editing ? (int) $_POST['id'] : 0) . '&return_success=' . urlencode($orderReturnSuccess));
                        exit;
                    }
                }
            }

            if ($editing) {
                try {
                    // Removido ensureOrderRepository() - não mais necessário
                    if ($action === 'consignment_preview' || $action === 'consignment_apply') {
                        Auth::requirePermission('orders.edit', $this->pdo);
                        $dryRun = $action === 'consignment_preview';
                        if (!$dryRun && empty($_POST['consignment_confirm'])) {
                            $consignmentPreview = [
                                'messages' => [],
                                'errors' => ['Confirme a prévia de consignação antes de aplicar.'],
                            ];
                            $preserveFormInput = true;
                        } else {
                            // MIGRADO: Remover parâmetro $repoLegado
                            [$consignmentMessages, $consignmentErrors] = $this->reprocessConsignment($editingId, $dryRun);
                            $consignmentPreview = [
                                'messages' => $consignmentMessages,
                                'errors' => $consignmentErrors,
                            ];
                            if ($dryRun) {
                                $preserveFormInput = true;
                            } elseif (!empty($consignmentErrors)) {
                                $errors = array_merge($errors, $consignmentErrors);
                                $preserveFormInput = true;
                            } else {
                                $success = $this->appendConsignmentResult('Consignação reprocessada.', $consignmentMessages, []);
                                header('Location: pedido-cadastro.php?id=' . $editingId . '&success=' . urlencode($success));
                                exit;
                            }
                        }
                    } elseif ($action === 'update_full') {
                        Auth::requirePermission('orders.edit', $this->pdo);
                        [$order, $validationErrors] = $this->service->validate($_POST, false, $salesChannelOptions);
                        $errors = array_merge($errors, $validationErrors);
                        // MIGRADO: Removido parâmetro $repoLegado
                        $voucherPersonIds = $this->resolveVoucherPersonMatchIds((int) ($_POST['pessoa_id'] ?? 0));
                        if (empty($errors)) {
                            if ($order->personId !== null && $order->personId <= 0) {
                                $order->personId = null;
                            }
                            // MIGRADO: Removido parâmetro $repoLegado
                            $resolvedPersonId = $this->resolvePersonIdFromInput((int) ($order->personId ?? 0));
                            if ($order->personId && !$resolvedPersonId) {
                                $errors[] = 'Cliente informado é inválido.';
                            } else {
                                $order->personId = $resolvedPersonId;
                            }
                        }
                        if (empty($errors)) {
                            [$paymentEntries, $paymentErrors] = $this->normalizePaymentEntries(
                                $_POST['payments'] ?? [],
                                $paymentMethodOptions,
                                $bankAccountOptions,
                                $paymentTerminalOptions,
                                $voucherAccountOptions,
                                $voucherPersonIds
                            );
                            if (!empty($paymentErrors)) {
                                $errors = array_merge($errors, $paymentErrors);
                            } else {
                                $order->paymentEntries = $paymentEntries;
                                $this->applyStatusRules($order, $paymentEntries);
                                $paymentMethodError = $this->validatePaidStatusPaymentMethodSelection(
                                    (string) ($order->payment->status ?? 'none'),
                                    $this->resolvePaymentMethodLabel($order->payment->method ?? null, $order->payment->methodTitle ?? null),
                                    'full_edit'
                                );
                                if ($paymentMethodError !== null) {
                                    $errors[] = $paymentMethodError;
                                }
                            }
                        }

                        $previousOrderData = null;
                        $previousPaymentEntries = [];
                        if (empty($errors)) {
                            // MIGRADO: Usar OrderRepository local
                            $previousOrderData = $this->orders->findOrderWithDetails($editingId);
                            if (!$previousOrderData) {
                                $errors[] = 'Pedido não encontrado.';
                            } else {
                                $previousOrderQuantities = $this->orderItemsQuantityMap($previousOrderData);
                                // MIGRADO: Removido parâmetro $repoLegado
                                $stockIssues = $this->validateStock($order->items, $previousOrderQuantities);
                                if (!empty($stockIssues)) {
                                    $errors = array_merge($errors, $stockIssues);
                                }
                            }
                        }

                        if (empty($errors)) {
                            if ($previousOrderData) {
                                $previousPaymentEntries = $this->orderPaymentsToForm($previousOrderData);
                            }
                            $lineItemDeletes = $this->buildRemovedLineItems($_POST['original_line_items'] ?? '', $_POST['items'] ?? []);
                            
                            // MIGRADO: Atualizar pedido via OrderRepository local
                            $orderData = $order->toArray();
                            $orderData['shipping_info'] = $this->decodeShippingInfoPayload($orderData['shipping_info'] ?? null);
                            $previousShippingInfo = isset($previousOrderData['shipping_info']) && is_array($previousOrderData['shipping_info'])
                                ? $previousOrderData['shipping_info']
                                : [];
                            $this->appendDeliveryTimelineEvents(
                                $previousShippingInfo,
                                $orderData['shipping_info'],
                                (string) ($orderData['fulfillment_status'] ?? 'pending')
                            );
                            $orderData['id'] = $editingId;
                            $orderData['opening_fee_deferred'] = $this->isOpeningFeeDeferred($_POST);
                            if (!empty($orderData['opening_fee_deferred'])) {
                                $shippingTotal = $this->normalizeMoney($_POST['shipping_total'] ?? null);
                                $orderData['opening_fee_value'] = $shippingTotal !== null && $shippingTotal > 0
                                    ? $shippingTotal
                                    : $this->resolveOpeningFee();
                            }
                            $this->orders->save($orderData);
                            
                            // Atualizar items (delete + insert via saveOrderItems)
                            if (!empty($order->items)) {
                                $this->orders->saveOrderItems($editingId, $orderData['items']);
                            }
                            
                            $this->applyVoucherBalanceDelta($previousPaymentEntries, $paymentEntries ?? []);
                            $success = 'Pedido atualizado.';
                            $syncResult = $this->syncOrderFinanceEntries($editingId);
                            $success = $this->appendFinanceSyncMessage($success, $syncResult);
                            header('Location: pedido-cadastro.php?id=' . $editingId . '&success=' . urlencode($success));
                            exit;
                        }

                        $preserveFormInput = true;
                    } elseif ($action === 'order_return_save') {
                        Auth::requirePermission('order_returns.create', $this->pdo);
                        // MIGRADO: Usar OrderRepository local
                        $orderData = $this->orders->findOrderWithDetails($editingId);
                        if (!$orderData) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }
                        $orderReturnItems = $this->orderItemsForReturn($orderData);
                        $returnCustomer = $this->extractReturnCustomer($orderData);
                        $orderReturnAlreadyReturned = $this->orderReturns->returnedQuantitiesByOrder($editingId);
                        $orderReturnAvailableMap = $this->buildReturnAvailableMap($orderReturnItems, $orderReturnAlreadyReturned, []);
                        $orderReturnItemsInput = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
                        $orderReturnFormData = $this->emptyReturnForm($editingId, $returnCustomer);

                        $returnInput = $_POST;
                        $returnInput['order_id'] = $editingId;
                        $returnInput['id'] = $returnInput['return_id'] ?? '';
                        unset($returnInput['return_id']);
                        if ($returnCustomer) {
                            $returnInput['pessoa_id'] = $returnCustomer['id'] ?? null;
                            $returnInput['customer_name'] = $returnCustomer['name'] ?? null;
                            $returnInput['customer_email'] = $returnCustomer['email'] ?? null;
                        }

                        [$return, $returnItemsList, $validationErrors] = $this->orderReturnService->validate(
                            $returnInput,
                            $orderReturnItems,
                            $orderReturnAlreadyReturned,
                            []
                        );
                        $orderReturnErrors = array_merge($orderReturnErrors, $validationErrors);
                        if (empty($orderReturnErrors)) {
                            $currentUser = Auth::user();
                            if (!$return->createdBy && isset($currentUser['id'])) {
                                $return->createdBy = (int) $currentUser['id'];
                            }
                            $savedId = $this->orderReturns->save($return, $returnItemsList);
                            $return->id = $savedId;
                            $restockMessages = [];
                            $consignmentMessages = [];
                            $consignmentErrors = [];
                            if ($this->shouldRestockReturn($return)) {
                                Auth::requirePermission('order_returns.restock', $this->pdo);
                                $restockMessages = $this->restockReturnItems($returnItemsList);
                                if ($this->hasRestockFailures($restockMessages)) {
                                    $orderReturnErrors[] = 'Falha ao restocar produtos da devolução.';
                                } else {
                                    $this->orderReturns->markRestocked($savedId);
                                    $return->restockedAt = date('Y-m-d H:i:s');
                                    if ($this->pdo) {
                                        $consignmentService = new ConsignmentCreditService($this->pdo);
                                        [$consignmentMessages, $consignmentErrors] = $consignmentService->debitForReturn($return, $returnItemsList);
                                    }
                                }
                            }
                            if ($returnCustomer) {
                                $this->logReturnHistory($returnCustomer['id'] ?? 0, 'return_create', [
                                    'order_id' => $editingId,
                                    'return_id' => $savedId,
                                    'status' => $return->status,
                                    'refund_method' => $return->refundMethod,
                                    'items' => $this->historyItemsFromReturn($returnItemsList),
                                ]);
                                if (!empty($restockMessages)) {
                                    $this->logReturnHistory($returnCustomer['id'] ?? 0, 'return_restock', [
                                        'return_id' => $savedId,
                                        'notes' => $restockMessages,
                                    ]);
                                }
                            }

                            $voucherCreated = false;
                            $voucherMessage = '';
                            if ($return->refundMethod === 'voucher' && !$return->voucherAccountId) {
                                Auth::requirePermission('order_returns.refund', $this->pdo);
                                // MIGRADO: Removido parâmetro $repoLegado
                                [$voucherCreated, $voucherMessage] = $this->createVoucherFromReturn($return, $returnCustomer, $savedId);
                            }

                            if ($voucherCreated) {
                                $this->orderReturns->updateRefundStatus($savedId, 'done', $return->voucherAccountId);
                                if ($returnCustomer) {
                                    $this->logReturnHistory($returnCustomer['id'] ?? 0, 'return_refund', [
                                        'return_id' => $savedId,
                                        'refund_method' => 'voucher',
                                        'voucher_id' => $return->voucherAccountId,
                                        'amount' => $return->refundAmount,
                                    ]);
                                }
                            }

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
                            $orderReturnSuccess = implode(' ', $successParts);
                            header('Location: pedido-cadastro.php?id=' . $editingId . '&return_success=' . urlencode($orderReturnSuccess));
                            exit;
                        }
                        $orderReturnFormData = array_merge($orderReturnFormData, $returnInput);
                    } elseif ($action === 'order_return_cancel') {
                        Auth::requirePermission('order_returns.edit', $this->pdo);
                        $returnId = isset($_POST['return_id']) ? (int) $_POST['return_id'] : 0;
                        $returnRow = $returnId > 0 ? $this->orderReturns->find($returnId) : null;
                        if (!$returnRow || (int) ($returnRow['order_id'] ?? 0) !== $editingId) {
                            $orderReturnErrors[] = 'Devolução inválida para cancelamento.';
                        } elseif (!empty($returnRow['restocked_at'])) {
                            $orderReturnErrors[] = 'Devolução já restocada: cancelamento bloqueado.';
                        } elseif (($returnRow['refund_status'] ?? '') === 'done' && ($returnRow['refund_method'] ?? '') !== 'none') {
                            $orderReturnErrors[] = 'Devolução com reembolso concluído não pode ser cancelada.';
                        } else {
                            $this->orderReturns->updateStatus($returnId, 'cancelled');
                            $orderReturnSuccess = 'Devolução cancelada com sucesso.';
                            header('Location: pedido-cadastro.php?id=' . $editingId . '&return_success=' . urlencode($orderReturnSuccess));
                            exit;
                        }
                    } elseif ($action === 'bag_open' || $action === 'bag_add') {
                        // MIGRADO: Removido parâmetro $repoLegado
                        $message = $this->handleBagActionFromOrder($editingId, $action);
                        header('Location: pedido-cadastro.php?id=' . $editingId . '&step=shipping&success=' . urlencode($message));
                        exit;
                    } elseif ($action === 'payment') {
                        Auth::requirePermission('orders.payment', $this->pdo);
                        $currentOrder = $this->orders->findOrderWithDetails($editingId);
                        if (!$currentOrder) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }

                        $payment = OrderPayment::fromArray($_POST);
                        $paymentStatus = OrderService::normalizePaymentStatus($payment->status);

                        $paymentMethod = $payment->methodTitle ?: $payment->method;
                        $paymentMethod = $paymentMethod !== null ? trim((string) $paymentMethod) : '';
                        $candidateOrder = $currentOrder;
                        $candidateOrder['payment_status'] = $paymentStatus;
                        $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
                        $status = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
                        $paymentStatus = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? $paymentStatus));
                        $paymentMethodError = $this->validatePaidStatusPaymentMethodSelection($paymentStatus, $paymentMethod, 'payment_tab');
                        if ($paymentMethodError !== null) {
                            throw new \RuntimeException($paymentMethodError);
                        }
                        $payload = $this->lifecycle->toPersistencePayload($snapshot, $currentOrder);
                        $payload['payment_method'] = $paymentMethod !== '' ? $paymentMethod : null;
                        $this->orders->updateStatusComplete($editingId, $status, null, $payload);

                        $success = 'Pagamento atualizado.';
                        [$creditMessages, $creditErrors] = $this->applyConsignmentCredits($editingId, $paymentStatus);
                        $success = $this->appendConsignmentResult($success, $creditMessages, $creditErrors);
                        $eventType = null;
                        $eventLabel = null;
                        if ($paymentStatus === 'refunded') {
                            $eventType = 'payment_refund';
                            $eventLabel = 'Estorno de pagamento';
                        } elseif ($paymentStatus === 'failed') {
                            $eventType = 'payment_failed';
                            $eventLabel = 'Pagamento falhou';
                        }
                        if ($eventType) {
                            [$debitMessages, $debitErrors] = $this->applyConsignmentDebits($editingId, $eventType, [
                                'event_label' => $eventLabel,
                            ]);
                            $success = $this->appendConsignmentResult($success, $debitMessages, $debitErrors);
                        }
                        $syncResult = $this->syncOrderFinanceEntries($editingId);
                        $success = $this->appendFinanceSyncMessage($success, $syncResult);
                        header('Location: pedido-cadastro.php?id=' . $editingId . '&success=' . urlencode($success));
                        exit;
                    } elseif ($action === 'shipping') {
                        if (!Auth::can('orders.fulfillment') && !Auth::can('orders.edit')) {
                            Auth::requirePermission('orders.fulfillment', $this->pdo);
                        }

                        $currentOrder = $this->orders->findOrderWithDetails($editingId);
                        if (!$currentOrder) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }

                        $shipping = OrderShipping::fromArray($_POST);
                        $resolvedDelivery = $this->deliveryPolicy->resolve([
                            'delivery_mode' => (string) ($_POST['delivery_mode'] ?? ($currentOrder['delivery_mode'] ?? 'shipment')),
                            'shipment_kind' => (string) ($_POST['shipment_kind'] ?? ($currentOrder['shipment_kind'] ?? '')),
                            'fulfillment_status' => $shipping->status,
                            'carrier_id' => $shipping->carrierId,
                            'tracking_code' => $shipping->trackingCode,
                            'estimated_delivery_at' => $shipping->estimatedDeliveryAt ?? $shipping->eta,
                            'shipped_at' => $shipping->shippedAt,
                            'delivered_at' => $shipping->deliveredAt,
                            'logistics_notes' => $shipping->logisticsNotes,
                            'bag_id' => $_POST['bag_id'] ?? ($currentOrder['bag_id'] ?? null),
                        ], true);

                        $shipping->status = OrderService::normalizeFulfillmentStatus((string) ($resolvedDelivery['fulfillment_status'] ?? 'pending'));
                        $shipping->deliveryMode = OrderService::normalizeDeliveryMode((string) ($resolvedDelivery['delivery_mode'] ?? 'shipment'));
                        $shipping->shipmentKind = $resolvedDelivery['shipment_kind'] !== null
                            ? (string) $resolvedDelivery['shipment_kind']
                            : null;
                        $shipping->carrierId = $resolvedDelivery['carrier_id'] !== null ? (int) $resolvedDelivery['carrier_id'] : null;
                        $shipping->trackingCode = $resolvedDelivery['tracking_code'] !== null ? (string) $resolvedDelivery['tracking_code'] : null;
                        $shipping->estimatedDeliveryAt = $resolvedDelivery['estimated_delivery_at'] !== null
                            ? (string) $resolvedDelivery['estimated_delivery_at']
                            : null;
                        $shipping->eta = $shipping->estimatedDeliveryAt;
                        $shipping->shippedAt = $resolvedDelivery['shipped_at'] !== null ? (string) $resolvedDelivery['shipped_at'] : null;
                        $shipping->deliveredAt = $resolvedDelivery['delivered_at'] !== null ? (string) $resolvedDelivery['delivered_at'] : null;
                        $shipping->logisticsNotes = $resolvedDelivery['logistics_notes'] !== null ? (string) $resolvedDelivery['logistics_notes'] : null;
                        $shipping->bagId = $resolvedDelivery['bag_id'] !== null ? (int) $resolvedDelivery['bag_id'] : null;

                        $shippingErrors = [];
                        if (!empty($resolvedDelivery['errors']) && is_array($resolvedDelivery['errors'])) {
                            foreach ($resolvedDelivery['errors'] as $deliveryError) {
                                $shippingErrors[] = (string) $deliveryError;
                            }
                        }

                        if ((int) ($shipping->carrierId ?? 0) > 0) {
                            $carrier = $this->carriers->find((int) $shipping->carrierId);
                            if (!$carrier || $carrier->status !== 'ativo') {
                                $shippingErrors[] = 'Transportadora inválida ou inativa.';
                            } else {
                                $shipping->carrier = $carrier->name;
                            }
                        }

                        $orderPersonId = $this->resolvePersonIdFromOrderRow($currentOrder);
                        $previousBagId = isset($currentOrder['bag_id']) ? (int) $currentOrder['bag_id'] : 0;
                        if ($shipping->deliveryMode === 'shipment' && $shipping->shipmentKind === 'bag_deferred') {
                            if ((int) ($shipping->bagId ?? 0) <= 0) {
                                $shippingErrors[] = 'Selecione uma sacolinha para envio diferido.';
                            } else {
                                $bag = $this->bags->find((int) $shipping->bagId);
                                if (!$bag) {
                                    $shippingErrors[] = 'Sacolinha informada não existe.';
                                } else {
                                    if ($orderPersonId > 0) {
                                        $bagOwnershipError = $this->validateBagOwnership($bag, $orderPersonId);
                                        if ($bagOwnershipError !== null) {
                                            $shippingErrors[] = $bagOwnershipError;
                                        }
                                    }
                                    if ((int) $shipping->bagId !== $previousBagId) {
                                        $bagEligibilityError = $this->validateOpenBagForAdd($bag);
                                        if ($bagEligibilityError !== null) {
                                            $shippingErrors[] = $bagEligibilityError;
                                        }
                                    }
                                }
                            }
                        } else {
                            $shipping->bagId = null;
                        }

                        if (!empty($shippingErrors)) {
                            $errors = array_merge($errors, $shippingErrors);
                            $preserveFormInput = true;
                        } else {
                            $previousShippingInfo = isset($currentOrder['shipping_info']) && is_array($currentOrder['shipping_info'])
                                ? $currentOrder['shipping_info']
                                : [];
                            $shippingInfo = $previousShippingInfo;
                            $shippingInfo['status'] = $shipping->status;
                            $shippingInfo['delivery_mode'] = $shipping->deliveryMode;
                            $shippingInfo['shipment_kind'] = $shipping->shipmentKind;
                            $shippingInfo['total'] = $shipping->total;
                            $shippingInfo['carrier_id'] = $shipping->carrierId;
                            $shippingInfo['bag_id'] = $shipping->bagId;
                            $shippingInfo['carrier'] = $shipping->carrier;
                            $shippingInfo['tracking_code'] = $shipping->trackingCode;
                            $shippingInfo['eta'] = $shipping->eta;
                            $shippingInfo['estimated_delivery_at'] = $shipping->estimatedDeliveryAt ?? $shipping->eta;
                            $shippingInfo['shipped_at'] = $shipping->shippedAt;
                            $shippingInfo['delivered_at'] = $shipping->deliveredAt;
                            $shippingInfo['logistics_notes'] = $shipping->logisticsNotes;
                            $this->appendDeliveryTimelineEvents($previousShippingInfo, $shippingInfo, $shipping->status);

                            $candidateOrder = $currentOrder;
                            $candidateOrder['shipping_info'] = $shippingInfo;
                            $candidateOrder['delivery_mode'] = $shipping->deliveryMode;
                            $candidateOrder['shipment_kind'] = $shipping->shipmentKind;
                            $candidateOrder['delivery_status'] = $shipping->status;
                            $candidateOrder['fulfillment_status'] = $shipping->status;
                            $candidateOrder['bag_id'] = $shipping->bagId;
                            $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
                            $status = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
                            $payload = $this->lifecycle->toPersistencePayload($snapshot, $candidateOrder);
                            $payload['shipping_info'] = $shippingInfo;
                            $payload['shipment_kind'] = $shipping->shipmentKind;
                            $payload['bag_id'] = $shipping->bagId;
                            $this->orders->updateStatusComplete($editingId, $status, null, $payload);

                            $success = 'Entrega/logística atualizada.';
                            $syncResult = $this->syncOrderFinanceEntries($editingId);
                            $success = $this->appendFinanceSyncMessage($success, $syncResult);
                            header('Location: pedido-cadastro.php?id=' . $editingId . '&success=' . urlencode($success));
                            exit;
                        }
                    } elseif ($action === 'cancel') {
                        Auth::requirePermission('orders.cancel', $this->pdo);
                        $previousOrder = $this->orders->findOrderWithDetails($editingId);
                        $previousStatus = OrderService::normalizeOrderStatus((string) ($previousOrder['status'] ?? ''));
                        // MIGRADO: Usar OrderRepository local
                        $this->orders->updateStatusComplete($editingId, 'cancelled', null, [
                            'fulfillment_status' => 'pending',
                        ]);
                        $success = 'Pedido cancelado.';
                        if (!$this->isTerminalStockStatus($previousStatus)) {
                            [$stockMessages, $stockErrors] = $this->restockOrderItems($editingId, 'Cancelamento');
                            $success = $this->appendConsignmentResult($success, $stockMessages, []);
                            if (!empty($stockErrors)) {
                                $errors = array_merge($errors, $stockErrors);
                            }
                        }
                        [$debitMessages, $debitErrors] = $this->applyConsignmentDebits($editingId, 'order_cancel', [
                            'event_label' => 'Cancelamento do pedido',
                        ]);
                        $success = $this->appendConsignmentResult($success, $debitMessages, $debitErrors);
                        $syncResult = $this->syncOrderFinanceEntries($editingId);
                        $success = $this->appendFinanceSyncMessage($success, $syncResult);
                    } else {
                        $canEditLifecycle = Auth::can('orders.edit');
                        $canPaymentLifecycle = $canEditLifecycle || Auth::can('orders.payment');
                        $canFulfillmentLifecycle = $canEditLifecycle || Auth::can('orders.fulfillment');
                        if (!$canEditLifecycle && !$canPaymentLifecycle && !$canFulfillmentLifecycle) {
                            Auth::requirePermission('orders.edit', $this->pdo);
                        }
                        $previousOrder = $this->orders->findOrderWithDetails($editingId);
                        if (!$previousOrder) {
                            throw new \RuntimeException('Pedido não encontrado.');
                        }
                        $previousStatus = OrderService::normalizeOrderStatus((string) ($previousOrder['status'] ?? ''));
                        $requestedStatus = isset($_POST['status'])
                            ? OrderService::normalizeOrderStatus((string) $_POST['status'])
                            : OrderService::normalizeOrderStatus((string) ($previousOrder['status'] ?? 'open'));
                        if (!$canEditLifecycle) {
                            $requestedStatus = OrderService::normalizeOrderStatus((string) ($previousOrder['status'] ?? 'open'));
                        }
                        $paymentStatus = OrderService::normalizePaymentStatus((string) ($_POST['payment_status'] ?? ($previousOrder['payment_status'] ?? 'none')));
                        if (!$canPaymentLifecycle) {
                            $paymentStatus = OrderService::normalizePaymentStatus((string) ($previousOrder['payment_status'] ?? 'none'));
                        }
                        $statusNormalizationNote = '';
                        $currentPaymentEntries = $this->extractPaymentEntriesFromOrder($previousOrder);
                        if (!empty($currentPaymentEntries)) {
                            $derivedFromEntries = $this->derivePaymentStatusFromEntries(
                                $currentPaymentEntries,
                                (float) ($previousOrder['total'] ?? 0)
                            );
                            if ($derivedFromEntries !== $paymentStatus) {
                                $paymentStatus = $derivedFromEntries;
                                $statusNormalizationNote = ' Status de pagamento ajustado automaticamente conforme os pagamentos adicionados.';
                            }
                        }

                        $fulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($_POST['fulfillment_status'] ?? ($previousOrder['fulfillment_status'] ?? 'pending')));
                        if (!$canFulfillmentLifecycle) {
                            $fulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($previousOrder['fulfillment_status'] ?? 'pending'));
                        }
                        $deliveryMode = OrderService::normalizeDeliveryMode(
                            (string) ($_POST['delivery_mode'] ?? ($previousOrder['delivery_mode'] ?? ''))
                        );
                        $shipmentKind = OrderService::normalizeShipmentKind(
                            (string) ($_POST['shipment_kind'] ?? ($previousOrder['shipment_kind'] ?? '')),
                            $deliveryMode
                        );
                        if (!$canFulfillmentLifecycle) {
                            $deliveryMode = OrderService::normalizeDeliveryMode((string) ($previousOrder['delivery_mode'] ?? 'shipment'));
                            $shipmentKind = OrderService::normalizeShipmentKind(
                                (string) ($previousOrder['shipment_kind'] ?? ''),
                                $deliveryMode
                            );
                        }
                        $salesChannel = isset($_POST['sales_channel']) ? trim((string) $_POST['sales_channel']) : '';
                        $salesChannel = $salesChannel === '' ? null : $salesChannel;
                        if (!$canEditLifecycle) {
                            $salesChannel = isset($previousOrder['sales_channel']) ? trim((string) $previousOrder['sales_channel']) : '';
                            $salesChannel = $salesChannel === '' ? null : $salesChannel;
                        }

                        $carrierId = (int) ($_POST['carrier_id'] ?? ($previousOrder['carrier_id'] ?? 0));
                        $bagId = (int) ($_POST['bag_id'] ?? ($previousOrder['bag_id'] ?? 0));
                        $carrierName = '';
                        if ($canFulfillmentLifecycle) {
                            $resolvedDelivery = $this->deliveryPolicy->resolve([
                                'delivery_mode' => $deliveryMode,
                                'shipment_kind' => $shipmentKind,
                                'fulfillment_status' => $fulfillmentStatus,
                                'carrier_id' => $carrierId,
                                'tracking_code' => (string) ($_POST['tracking_code'] ?? ''),
                                'estimated_delivery_at' => (string) ($_POST['estimated_delivery_at'] ?? ($_POST['shipping_eta'] ?? '')),
                                'shipped_at' => (string) ($_POST['shipped_at'] ?? ''),
                                'delivered_at' => (string) ($_POST['delivered_at'] ?? ''),
                                'logistics_notes' => (string) ($_POST['logistics_notes'] ?? ''),
                                'bag_id' => $bagId > 0 ? $bagId : null,
                            ], true);
                            if (!empty($resolvedDelivery['errors']) && is_array($resolvedDelivery['errors'])) {
                                foreach ($resolvedDelivery['errors'] as $deliveryError) {
                                    $errors[] = (string) $deliveryError;
                                }
                            }

                            $deliveryMode = (string) ($resolvedDelivery['delivery_mode'] ?? $deliveryMode);
                            $shipmentKind = $resolvedDelivery['shipment_kind'] !== null ? (string) $resolvedDelivery['shipment_kind'] : null;
                            $fulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($resolvedDelivery['fulfillment_status'] ?? $fulfillmentStatus));
                            $carrierId = isset($resolvedDelivery['carrier_id']) && (int) $resolvedDelivery['carrier_id'] > 0
                                ? (int) $resolvedDelivery['carrier_id']
                                : 0;
                            $bagId = isset($resolvedDelivery['bag_id']) && (int) $resolvedDelivery['bag_id'] > 0
                                ? (int) $resolvedDelivery['bag_id']
                                : 0;
                        }
                        if ($canFulfillmentLifecycle) {
                            $orderPersonId = $this->resolvePersonIdFromOrderRow($previousOrder);
                            $previousBagId = isset($previousOrder['bag_id']) ? (int) $previousOrder['bag_id'] : 0;
                            if ($deliveryMode === 'shipment' && $shipmentKind === 'bag_deferred') {
                                if ($bagId <= 0) {
                                    $errors[] = 'Selecione uma sacolinha para envio diferido.';
                                } else {
                                    $bag = $this->bags->find($bagId);
                                    if (!$bag) {
                                        $errors[] = 'Sacolinha informada não existe.';
                                    } else {
                                        if ($orderPersonId > 0) {
                                            $bagOwnershipError = $this->validateBagOwnership($bag, $orderPersonId);
                                            if ($bagOwnershipError !== null) {
                                                $errors[] = $bagOwnershipError;
                                            }
                                        }
                                        if ($bagId !== $previousBagId) {
                                            $bagEligibilityError = $this->validateOpenBagForAdd($bag);
                                            if ($bagEligibilityError !== null) {
                                                $errors[] = $bagEligibilityError;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $bagId = 0;
                            }
                        }
                        if ($canFulfillmentLifecycle && $carrierId > 0) {
                            $carrier = $this->carriers->find($carrierId);
                            if (!$carrier || $carrier->status !== 'ativo') {
                                $errors[] = 'Transportadora inválida ou inativa.';
                            } else {
                                $carrierName = $carrier->name;
                            }
                        }

                        $status = $requestedStatus;

                        if ($salesChannel !== null && !empty($salesChannelOptions) && !in_array($salesChannel, $salesChannelOptions, true)) {
                            $errors[] = 'Canal de venda inválido.';
                        }

                        if (empty($errors)) {
                            $previousShippingInfo = isset($previousOrder['shipping_info']) && is_array($previousOrder['shipping_info'])
                                ? $previousOrder['shipping_info']
                                : [];
                            $shippingInfo = $previousShippingInfo;
                            $shippingInfo['status'] = $fulfillmentStatus;
                            if ($canFulfillmentLifecycle) {
                                $shippingInfo['delivery_mode'] = $deliveryMode;
                                $shippingInfo['shipment_kind'] = $shipmentKind;
                                $shippingInfo['total'] = $this->normalizeMoney($_POST['shipping_total'] ?? ($shippingInfo['total'] ?? null));
                                $shippingInfo['carrier_id'] = $carrierId > 0 ? $carrierId : null;
                                $shippingInfo['bag_id'] = $bagId > 0 ? $bagId : null;
                                $shippingInfo['carrier'] = $carrierName !== '' ? $carrierName : (string) ($shippingInfo['carrier'] ?? '');
                                $shippingInfo['tracking_code'] = (string) ($_POST['tracking_code'] ?? ($shippingInfo['tracking_code'] ?? ''));
                                $shippingInfo['eta'] = (string) ($_POST['shipping_eta'] ?? ($shippingInfo['eta'] ?? ''));
                                $shippingInfo['estimated_delivery_at'] = (string) ($_POST['estimated_delivery_at'] ?? ($shippingInfo['estimated_delivery_at'] ?? $shippingInfo['eta'] ?? ''));
                                $shippingInfo['shipped_at'] = (string) ($_POST['shipped_at'] ?? ($shippingInfo['shipped_at'] ?? ''));
                                $shippingInfo['delivered_at'] = (string) ($_POST['delivered_at'] ?? ($shippingInfo['delivered_at'] ?? ''));
                                $shippingInfo['logistics_notes'] = (string) ($_POST['logistics_notes'] ?? ($shippingInfo['logistics_notes'] ?? ''));
                                $this->appendDeliveryTimelineEvents($previousShippingInfo, $shippingInfo, $fulfillmentStatus);
                            }

                            $paymentMethod = trim((string) ($_POST['payment_method_title'] ?? $_POST['payment_method'] ?? ''));
                            $paymentMethodSubmitted = array_key_exists('payment_method_title', $_POST) || array_key_exists('payment_method', $_POST);
                            if (!$paymentMethodSubmitted || !$canPaymentLifecycle) {
                                $paymentMethod = trim((string) ($previousOrder['payment_method'] ?? ''));
                            }
                            $paymentMethod = $paymentMethod !== '' ? $paymentMethod : null;

                            $candidateOrder = $previousOrder;
                            $candidateOrder['status'] = $requestedStatus;
                            $candidateOrder['payment_status'] = $paymentStatus;
                            $candidateOrder['fulfillment_status'] = $fulfillmentStatus;
                            $candidateOrder['delivery_mode'] = $deliveryMode;
                            $candidateOrder['shipment_kind'] = $shipmentKind;
                            $candidateOrder['shipping_info'] = $shippingInfo;
                            $candidateOrder['bag_id'] = $bagId > 0 ? $bagId : null;
                            $candidateOrder['sales_channel'] = $salesChannel;
                            $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
                            $snapshotStatus = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? $requestedStatus));
                            $snapshotPaymentStatus = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? $paymentStatus));
                            $snapshotFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? $fulfillmentStatus));

                            if (!in_array($requestedStatus, ['cancelled', 'trash', 'deleted'], true)) {
                                $status = $snapshotStatus;
                            }
                            if ($snapshotPaymentStatus !== $paymentStatus) {
                                $statusNormalizationNote = ' Status de pagamento ajustado automaticamente conforme o ledger.';
                            }
                            $paymentStatus = $snapshotPaymentStatus;
                            $fulfillmentStatus = $snapshotFulfillmentStatus;
                            $shippingInfo['status'] = $fulfillmentStatus;
                            $paymentMethodError = $this->validatePaidStatusPaymentMethodSelection($paymentStatus, $paymentMethod, 'status');
                            if ($paymentMethodError !== null) {
                                $errors[] = $paymentMethodError;
                            } else {
                                $this->orders->updateStatusComplete($editingId, $status, null, [
                                    'payment_status' => $paymentStatus,
                                    'fulfillment_status' => $fulfillmentStatus,
                                    'delivery_mode' => $deliveryMode,
                                    'shipment_kind' => $shipmentKind,
                                    'bag_id' => $bagId > 0 ? $bagId : null,
                                    'sales_channel' => $salesChannel,
                                    'payment_method' => $paymentMethod,
                                    'shipping_info' => $shippingInfo,
                                ]);

                                $success = 'Acompanhamento atualizado.' . $statusNormalizationNote;
                                if ($this->isTerminalStockStatus($status) && !$this->isTerminalStockStatus($previousStatus)) {
                                    [$stockMessages, $stockErrors] = $this->restockOrderItems($editingId, 'Alteração de status');
                                    if (!empty($stockMessages)) {
                                        $success = $this->appendConsignmentResult($success, $stockMessages, []);
                                    }
                                    if (!empty($stockErrors)) {
                                        $errors = array_merge($errors, $stockErrors);
                                    }
                                }
                                [$creditMessages, $creditErrors] = $this->applyConsignmentCredits($editingId, $paymentStatus);
                                if (!empty($creditMessages)) {
                                    $success = $this->appendConsignmentResult($success, $creditMessages, []);
                                }
                                if (!empty($creditErrors)) {
                                    $errors = array_merge($errors, $creditErrors);
                                }
                                $debitEvents = [];
                                if ($status === 'cancelled') {
                                    $debitEvents['order_cancel'] = 'Cancelamento do pedido';
                                }
                                if ($status === 'refunded' || $paymentStatus === 'refunded') {
                                    $debitEvents['payment_refund'] = 'Estorno de pagamento';
                                }
                                if ($status === 'failed' || $paymentStatus === 'failed') {
                                    $debitEvents['payment_failed'] = 'Pagamento falhou';
                                }
                                foreach ($debitEvents as $eventType => $eventLabel) {
                                    [$debitMessages, $debitErrors] = $this->applyConsignmentDebits($editingId, $eventType, [
                                        'event_label' => $eventLabel,
                                    ]);
                                    if (!empty($debitMessages)) {
                                        $success = $this->appendConsignmentResult($success, $debitMessages, []);
                                    }
                                    if (!empty($debitErrors)) {
                                        $errors = array_merge($errors, $debitErrors);
                                    }
                                }
                                $syncResult = $this->syncOrderFinanceEntries($editingId);
                                $success = $this->appendFinanceSyncMessage($success, $syncResult);
                            }
                        }
                    }

                    // MIGRADO: Usar OrderRepository local
                    $orderData = $this->orders->findOrderWithDetails($editingId);
                    if (!$orderData) {
                        throw new \RuntimeException('Pedido não encontrado.');
                    }
                    $orderData = $this->reconcileOrderLifecycleState($editingId, $orderData);
                    $formData = $this->orderToForm($orderData);
                    // MIGRADO: Removido parâmetro $repoLegado
                    $formData = $this->applyCustomerFallback($formData, $orderData);
                    $editing = true;
                    if ($fullEdit) {
                        $formData['items'] = $this->orderItemsToForm($orderData);
                        $formData['payments'] = $this->orderPaymentsToForm($orderData);
                        $originalLineItemIds = $this->extractOrderLineItemIds($orderData);
                    }
                    $items = $orderData['line_items'] ?? [];
                    $orderSummary = $this->buildSummary($orderData);
                    // MIGRADO: Removido parâmetro $repoLegado
                    $itemStocks = $this->resolveItemStocks($items);
                    $bagContext = $this->buildBagContext($orderData);
                    $formData = $this->applyLifecycleSnapshotToFormData($formData, $orderData, $bagContext);
                    $orderReturns = $this->orderReturns->listByOrder($editingId);
                    if ($preserveFormInput) {
                        $formData = array_merge($formData, $_POST);
                        if (isset($_POST['items']) && is_array($_POST['items'])) {
                            $formData['items'] = $_POST['items'];
                        }
                        if (isset($_POST['payments']) && is_array($_POST['payments'])) {
                            $formData['payments'] = $_POST['payments'];
                        }
                        $fullEdit = true;
                        $originalLineItemIds = $this->parseLineItemIds($_POST['original_line_items'] ?? '');
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao atualizar pedido: ' . $e->getMessage();
                }
            } else {
                Auth::requirePermission('orders.create', $this->pdo);
                $bagAction = 'none';
                $rawDeliveryMode = (string) ($_POST['delivery_mode'] ?? 'shipment');
                $_POST['delivery_mode'] = OrderService::normalizeDeliveryMode($rawDeliveryMode);
                if ((string) $_POST['delivery_mode'] === 'shipment') {
                    $_POST['shipment_kind'] = (string) ($_POST['shipment_kind'] ?? '');
                    if ($_POST['shipment_kind'] === '') {
                        $_POST['shipment_kind'] = OrderService::normalizeShipmentKind('', 'shipment') ?? 'tracked';
                    }
                    if ((string) $_POST['shipment_kind'] === 'bag_deferred') {
                        $personIdInput = (int) ($_POST['pessoa_id'] ?? 0);
                        if ((int) ($_POST['bag_id'] ?? 0) > 0) {
                            $bagAction = 'add_to_bag';
                        } elseif ($personIdInput > 0) {
                            $openBag = $this->bags->findOpenByPerson($personIdInput);
                            if ($openBag && $openBag->id && $this->validateOpenBagForAdd($openBag) === null) {
                                $_POST['bag_id'] = (string) $openBag->id;
                                $bagAction = 'add_to_bag';
                            } else {
                                $bagAction = 'open_bag';
                            }
                        } else {
                            $bagAction = 'open_bag';
                        }
                    }
                } else {
                    $_POST['shipment_kind'] = '';
                    $_POST['bag_id'] = '';
                    $bagAction = 'none';
                }
                $deferOpeningFee = $bagAction === 'open_bag' && $this->isOpeningFeeDeferred($_POST);
                if ($deferOpeningFee) {
                    $_POST['shipping_total'] = '0.00';
                }
                [$order, $validationErrors] = $this->service->validate($_POST, false, $salesChannelOptions, false);
                $errors = array_merge($errors, $validationErrors);
                // MIGRADO: Removido parâmetro $repoLegado
                $voucherPersonIds = $this->resolveVoucherPersonMatchIds((int) ($_POST['pessoa_id'] ?? 0));
                if (empty($errors)) {
                    if ($order->personId !== null && $order->personId <= 0) {
                        $order->personId = null;
                    }
                    // MIGRADO: Removido parâmetro $repoLegado
                    $resolvedPersonId = $this->resolvePersonIdFromInput((int) ($order->personId ?? 0));
                    if ($order->personId && !$resolvedPersonId) {
                        $errors[] = 'Cliente informado é inválido.';
                    } else {
                        $order->personId = $resolvedPersonId;
                    }
                }

                if (empty($errors)) {
                    [$paymentEntries, $paymentErrors] = $this->normalizePaymentEntries(
                        $_POST['payments'] ?? [],
                        $paymentMethodOptions,
                        $bankAccountOptions,
                        $paymentTerminalOptions,
                        $voucherAccountOptions,
                        $voucherPersonIds
                    );
                    if (!empty($paymentErrors)) {
                        $errors = array_merge($errors, $paymentErrors);
                    } else {
                        $order->paymentEntries = $paymentEntries;
                        $this->applyStatusRules($order, $paymentEntries);
                        $paymentMethodError = $this->validatePaidStatusPaymentMethodSelection(
                            (string) ($order->payment->status ?? 'none'),
                            $this->resolvePaymentMethodLabel($order->payment->method ?? null, $order->payment->methodTitle ?? null),
                            'create'
                        );
                        if ($paymentMethodError !== null) {
                            $errors[] = $paymentMethodError;
                        }
                    }
                }

                if (empty($errors) && $bagAction !== 'none') {
                    if (!$order->personId) {
                        $errors[] = 'Para usar sacolinha, informe a cliente.';
                    } else {
                        $openBag = $this->bags->findOpenByPerson($order->personId);
                        if ($bagAction === 'open_bag' && $openBag) {
                            if ($this->isBagExpired($openBag)) {
                                $errors[] = $this->bagExpiredMessage($openBag) . ' Feche/cancele a sacolinha atual antes de abrir uma nova.';
                            } else {
                                $errors[] = 'Cliente já possui sacolinha aberta #' . $openBag->id . '. Use "Adicionar a sacolinha".';
                            }
                        }
                        if ($bagAction === 'add_to_bag') {
                            if (!$openBag) {
                                $openingFee = $this->resolveOpeningFee();
                                $errors[] = 'Não há sacolinha aberta para esta cliente. Selecione "Abrir sacolinha" (taxa R$ ' . number_format($openingFee, 2, ',', '.') . ').';
                            } else {
                                $bagEligibilityError = $this->validateOpenBagForAdd($openBag);
                                if ($bagEligibilityError !== null) {
                                    $errors[] = $bagEligibilityError;
                                }
                                $postedBagId = (int) ($_POST['bag_id'] ?? 0);
                                if ($postedBagId > 0 && (int) $openBag->id !== $postedBagId) {
                                    $errors[] = 'A cliente só pode receber itens na sacolinha aberta #' . (int) $openBag->id . '.';
                                }
                                $_POST['bag_id'] = (string) (int) $openBag->id;
                            }
                        }
                    }
                }

                if (empty($errors)) {
                    // MIGRADO: Removido parâmetro $repoLegado
                    $stockIssues = $this->validateStock($order->items);
                    if (!empty($stockIssues)) {
                        $errors = array_merge($errors, $stockIssues);
                    }
                }

                if (empty($errors)) {
                    try {
                        // MIGRADO: Criar pedido via OrderRepository local
                        $orderData = $order->toArray();
                        $orderData['shipping_info'] = $this->decodeShippingInfoPayload($orderData['shipping_info'] ?? null);
                        $emptyTimelineBase = [];
                        $this->appendDeliveryTimelineEvents(
                            $emptyTimelineBase,
                            $orderData['shipping_info'],
                            (string) ($orderData['fulfillment_status'] ?? 'pending')
                        );
                        $orderData['opening_fee_deferred'] = $deferOpeningFee;
                        if ($deferOpeningFee) {
                            $orderData['opening_fee_value'] = $this->resolveOpeningFee();
                        }
                        $newId = $this->orders->save($orderData);
                        
                        // Salvar items do pedido
                        if (!empty($order->items)) {
                            $this->orders->saveOrderItems($newId, $orderData['items']);
                        }
                        
                        // Criar array no formato esperado pelos métodos existentes
                        $created = array_merge($orderData, ['id' => $newId]);
                        
                        $this->applyVoucherDebits($paymentEntries);
                        // MIGRADO: Removido parâmetro $repoLegado
                        $this->handleBagFlow($bagAction, $order, $created, $deferOpeningFee);
                        $success = 'Pedido criado com sucesso.' . $this->buildCreateBagSuccessMessage($bagAction, (int) ($order->personId ?? 0));
                        if ($newId > 0) {
                            [$creditMessages, $creditErrors] = $this->applyConsignmentCredits($newId, $order->payment->status);
                            $success = $this->appendConsignmentResult($success, $creditMessages, $creditErrors);
                            $syncResult = $this->syncOrderFinanceEntries($newId);
                            $success = $this->appendFinanceSyncMessage($success, $syncResult);
                        }
                        header('Location: pedido-cadastro.php?id=' . $newId . '&step=payment&success=' . urlencode($success));
                        exit;
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao criar pedido: ' . $e->getMessage();
                        $formData = array_merge($formData, $_POST);
                    }
                } else {
                    $formData = array_merge($formData, $_POST);
                }
            }
        }

        View::render('orders/form', [
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'formData' => $formData,
            'orderSummary' => $orderSummary,
            'items' => $items,
            'itemStocks' => $itemStocks,
            'productOptions' => $productOptions,
            'customerOptions' => $customerOptions,
            'step' => $step,
            'statusOptions' => OrderService::statusOptions(),
            'paymentStatusOptions' => OrderService::paymentStatusOptions(),
            'fulfillmentStatusOptions' => OrderService::fulfillmentStatusOptions(),
            'salesChannelOptions' => $salesChannelOptions,
            'deliveryModeOptions' => OrderService::deliveryModeOptions(),
            'shipmentKindOptions' => OrderService::shipmentKindOptions(),
            'carrierOptions' => $carrierOptions,
            'paymentMethodOptions' => $paymentMethodOptions,
            'bankAccountOptions' => $bankAccountOptions,
            'paymentTerminalOptions' => $paymentTerminalOptions,
            'voucherAccountOptions' => $voucherAccountOptions,
            'openingFeeDefault' => $this->resolveOpeningFee(),
            'bagContext' => $bagContext,
            'orderReturns' => $orderReturns,
            'orderReturnStatusOptions' => OrderReturnService::statusOptions(),
            'orderReturnRefundStatusOptions' => OrderReturnService::refundStatusOptions(),
            'orderReturnReturnMethodOptions' => OrderReturnService::returnMethodOptions(),
            'orderReturnRefundMethodOptions' => OrderReturnService::refundMethodOptions(),
            'orderReturnItems' => $orderReturnItems,
            'orderReturnAlreadyReturned' => $orderReturnAlreadyReturned,
            'orderReturnAvailableMap' => $orderReturnAvailableMap,
            'orderReturnFormData' => $orderReturnFormData,
            'orderReturnItemsInput' => $orderReturnItemsInput,
            'orderReturnErrors' => $orderReturnErrors,
            'orderReturnSuccess' => $orderReturnSuccess,
            'consignmentPreview' => $consignmentPreview,
            'fullEdit' => $fullEdit,
            'originalLineItemIds' => $originalLineItemIds,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Acompanhamento de pedido' : 'Novo pedido',
        ]);
    }

    // Método ensureOrderRepository() REMOVIDO - não mais necessário após migração para OrderRepository

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'pessoa_id' => '',
            'status' => 'open',
            'currency' => 'BRL',
            'buyer_note' => '',
            'sales_channel' => '',
            'delivery_mode' => 'shipment',
            'shipment_kind' => 'tracked',
            'delivery_status' => 'pending',
            'payment_status' => 'none',
            'payment_method' => '',
            'payment_method_title' => '',
            'transaction_id' => '',
            'set_paid' => '',
            'fulfillment_status' => 'pending',
            'shipping_total' => '',
            'carrier_id' => '',
            'bag_id' => '',
            'shipping_carrier' => '',
            'tracking_code' => '',
            'shipping_eta' => '',
            'estimated_delivery_at' => '',
            'shipped_at' => '',
            'delivered_at' => '',
            'logistics_notes' => '',
            'line_id' => '',
            'billing_full_name' => '',
            'billing_email' => '',
            'billing_phone' => '',
            'billing_address_1' => '',
            'billing_address_2' => '',
            'billing_number' => '',
            'billing_neighborhood' => '',
            'billing_city' => '',
            'billing_state' => '',
            'billing_postcode' => '',
            'billing_country' => 'BR',
            'shipping_full_name' => '',
            'shipping_email' => '',
            'shipping_phone' => '',
            'shipping_address_1' => '',
            'shipping_address_2' => '',
            'shipping_number' => '',
            'shipping_neighborhood' => '',
            'shipping_city' => '',
            'shipping_state' => '',
            'shipping_postcode' => '',
            'shipping_country' => 'BR',
            'items' => [],
            'payments' => [],
        ];
    }

    private function orderToForm(array $order): array
    {
        $meta = $this->extractMeta($order['meta_data'] ?? []);
        $billing = $order['billing'] ?? [];
        $shipping = $order['shipping'] ?? [];
        $shippingLine = $order['shipping_lines'][0] ?? null;
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order);
        $status = $snapshot['status'];
        $deliveryMode = OrderService::normalizeDeliveryMode(
            (string) ($snapshot['delivery_mode'] ?? ($order['delivery_mode'] ?? 'shipment'))
        );
        $shipmentKind = OrderService::normalizeShipmentKind(
            (string) ($snapshot['shipment_kind'] ?? ($order['shipment_kind'] ?? '')),
            $deliveryMode
        );
        $paymentStatus = $snapshot['payment_status'];
        $fulfillmentStatus = $snapshot['fulfillment_status'];

        return array_merge($this->emptyForm(), [
            'id' => (string) ($order['id'] ?? ''),
            'pessoa_id' => (string) ($order['pessoa_id'] ?? ''),
            'status' => $status,
            'currency' => (string) ($order['currency'] ?? 'BRL'),
            'buyer_note' => (string) ($order['customer_note'] ?? ''),
            'sales_channel' => (string) ($meta['retrato_sales_channel'] ?? ($order['sales_channel'] ?? '')),
            'delivery_mode' => $deliveryMode,
            'shipment_kind' => $shipmentKind ?? '',
            'delivery_status' => $fulfillmentStatus,
            'payment_status' => $paymentStatus,
            'payment_method' => (string) ($order['payment_method'] ?? ''),
            'payment_method_title' => (string) ($order['payment_method_title'] ?? ''),
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'set_paid' => $paymentStatus === 'paid' ? '1' : '',
            'fulfillment_status' => $fulfillmentStatus,
            'shipping_total' => (string) ($shippingLine['total'] ?? ''),
            'line_id' => $shippingLine ? (string) ($shippingLine['id'] ?? '') : '',
            'carrier_id' => (string) ($order['carrier_id'] ?? ($order['shipping_info']['carrier_id'] ?? '')),
            'bag_id' => (string) ($order['bag_id'] ?? ($order['shipping_info']['bag_id'] ?? ($meta['retrato_bag_id'] ?? ''))),
            'shipping_carrier' => (string) ($order['shipping_info']['carrier'] ?? ($meta['retrato_shipping_provider'] ?? '')),
            'tracking_code' => (string) ($order['tracking_code'] ?? ($meta['retrato_tracking_code'] ?? '')),
            'shipping_eta' => (string) ($order['estimated_delivery_at'] ?? ($order['shipping_info']['eta'] ?? ($meta['retrato_shipping_eta'] ?? ''))),
            'estimated_delivery_at' => (string) ($order['estimated_delivery_at'] ?? ($order['shipping_info']['estimated_delivery_at'] ?? '')),
            'shipped_at' => (string) ($order['shipped_at'] ?? ($order['shipping_info']['shipped_at'] ?? '')),
            'delivered_at' => (string) ($order['delivered_at'] ?? ($order['shipping_info']['delivered_at'] ?? '')),
            'logistics_notes' => (string) ($order['logistics_notes'] ?? ($order['shipping_info']['logistics_notes'] ?? '')),
            'billing_full_name' => trim((string) ($billing['full_name'] ?? '')) !== '' ? trim((string) $billing['full_name']) : trim((string) ($billing['first_name'] ?? '') . ' ' . (string) ($billing['last_name'] ?? '')),
            'billing_email' => (string) ($billing['email'] ?? ''),
            'billing_phone' => (string) ($billing['phone'] ?? ''),
            'billing_address_1' => (string) ($billing['address_1'] ?? ''),
            'billing_address_2' => (string) ($billing['address_2'] ?? ''),
            'billing_number' => (string) ($billing['number'] ?? ($meta['billing_number'] ?? '')),
            'billing_neighborhood' => (string) ($billing['neighborhood'] ?? ($meta['billing_neighborhood'] ?? '')),
            'billing_city' => (string) ($billing['city'] ?? ''),
            'billing_state' => (string) ($billing['state'] ?? ''),
            'billing_postcode' => (string) ($billing['postcode'] ?? ''),
            'billing_country' => (string) ($billing['country'] ?? 'BR'),
            'shipping_full_name' => trim((string) ($shipping['full_name'] ?? '')) !== '' ? trim((string) $shipping['full_name']) : trim((string) ($shipping['first_name'] ?? '') . ' ' . (string) ($shipping['last_name'] ?? '')),
            'shipping_email' => (string) ($shipping['email'] ?? ''),
            'shipping_phone' => (string) ($shipping['phone'] ?? ''),
            'shipping_address_1' => (string) ($shipping['address_1'] ?? ''),
            'shipping_address_2' => (string) ($shipping['address_2'] ?? ''),
            'shipping_number' => (string) ($shipping['number'] ?? ($meta['shipping_number'] ?? '')),
            'shipping_neighborhood' => (string) ($shipping['neighborhood'] ?? ($meta['shipping_neighborhood'] ?? '')),
            'shipping_city' => (string) ($shipping['city'] ?? ''),
            'shipping_state' => (string) ($shipping['state'] ?? ''),
            'shipping_postcode' => (string) ($shipping['postcode'] ?? ''),
            'shipping_country' => (string) ($shipping['country'] ?? 'BR'),
            'opening_fee_deferred' => !empty($meta['retrato_opening_fee_deferred']) ? '1' : '',
            'opening_fee_value' => (string) ($meta['retrato_opening_fee_value'] ?? ''),
            'lifecycle_snapshot' => $snapshot,
            'pending_codes' => $snapshot['pending_codes'] ?? [],
            'blocking_pending_codes' => $snapshot['blocking_pending_codes'] ?? [],
            'due_now' => (string) ($snapshot['totals']['due_now'] ?? '0'),
            'paid_total' => (string) ($snapshot['totals']['net_paid'] ?? '0'),
            'balance_due_now' => (string) ($snapshot['totals']['balance_due_now'] ?? '0'),
            'due_later' => (string) ($snapshot['totals']['due_later'] ?? '0'),
        ]);
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $orderData
     * @param array<string, mixed>|null $bagContext
     * @return array<string, mixed>
     */
    private function applyLifecycleSnapshotToFormData(array $formData, array $orderData, ?array $bagContext = null): array
    {
        $context = [];
        if (is_array($bagContext)) {
            $context['bag'] = $bagContext;
        }
        $snapshot = $this->computeLifecycleSnapshotFromOrder($orderData, $context);
        $formData['status'] = (string) ($snapshot['status'] ?? ($formData['status'] ?? 'open'));
        $formData['payment_status'] = (string) ($snapshot['payment_status'] ?? ($formData['payment_status'] ?? 'none'));
        $formData['fulfillment_status'] = (string) ($snapshot['fulfillment_status'] ?? ($formData['fulfillment_status'] ?? 'pending'));
        $formData['delivery_mode'] = (string) ($snapshot['delivery_mode'] ?? ($formData['delivery_mode'] ?? 'shipment'));
        $formData['shipment_kind'] = (string) ($snapshot['shipment_kind'] ?? ($formData['shipment_kind'] ?? ''));
        $formData['lifecycle_snapshot'] = $snapshot;
        $formData['pending_codes'] = $snapshot['pending_codes'] ?? [];
        $formData['blocking_pending_codes'] = $snapshot['blocking_pending_codes'] ?? [];
        $formData['due_now'] = (string) ($snapshot['totals']['due_now'] ?? '0');
        $formData['paid_total'] = (string) ($snapshot['totals']['net_paid'] ?? '0');
        $formData['balance_due_now'] = (string) ($snapshot['totals']['balance_due_now'] ?? '0');
        $formData['due_later'] = (string) ($snapshot['totals']['due_later'] ?? '0');
        return $formData;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderItemsToForm(array $orderData): array
    {
        $items = [];
        $lineItems = $orderData['line_items'] ?? [];
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productSku = (int) ($item['product_sku'] ?? 0);
            if ($productSku <= 0) {
                continue;
            }
            $variationId = (int) ($item['variation_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;
            $price = null;
            if (isset($item['price'])) {
                $price = (float) $item['price'];
            } elseif (isset($item['total']) && $qty > 0) {
                $price = ((float) $item['total']) / $qty;
            } elseif (isset($item['subtotal']) && $qty > 0) {
                $price = ((float) $item['subtotal']) / $qty;
            }
            $items[] = [
                'line_id' => (int) ($item['id'] ?? 0),
                'product_sku' => $productSku,
                'variation_id' => $variationId > 0 ? $variationId : '',
                'quantity' => $qty,
                'price' => $price !== null ? number_format($price, 2, '.', '') : '',
                'product_name' => (string) ($item['name'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, int>
     */
    private function orderItemsQuantityMap(array $orderData): array
    {
        $map = [];
        $lineItems = $orderData['line_items'] ?? [];
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productSku = (int) ($item['product_sku'] ?? 0);
            if ($productSku <= 0) {
                continue;
            }
            $variationId = (int) ($item['variation_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $key = $variationId > 0 ? 'v:' . $variationId : 'p:' . $productSku;
            if (!isset($map[$key])) {
                $map[$key] = 0;
            }
            $map[$key] += $quantity;
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderPaymentsToForm(array $orderData): array
    {
        $meta = $this->extractMeta($orderData['meta_data'] ?? []);
        $raw = $meta['retrato_payment_entries'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $derivedPaymentStatus = $this->derivePaymentStatus($orderData);
        $total = (float) ($orderData['total'] ?? 0);
        if ($derivedPaymentStatus === 'paid' && $total > 0) {
            $methodLabel = trim((string) ($orderData['payment_method'] ?? ''));
            if ($methodLabel === '') {
                $methodLabel = 'Pagamento';
            }

            return [[
                'method_id' => 0,
                'method_name' => $methodLabel,
                'method_type' => 'manual',
                'amount' => $total,
                'fee' => 0,
                'paid' => true,
                'bank_account_id' => null,
                'terminal_id' => null,
                'voucher_account_id' => null,
            ]];
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private function extractOrderLineItemIds(array $orderData): array
    {
        $ids = [];
        $lineItems = $orderData['line_items'] ?? [];
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lineId = (int) ($item['id'] ?? 0);
            if ($lineId > 0) {
                $ids[] = $lineId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function parseLineItemIds($value): array
    {
        if (is_array($value)) {
            $raw = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_filter(array_map('trim', explode(',', $value)));
            }
        } else {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $lineId = (int) $id;
            if ($lineId > 0) {
                $ids[] = $lineId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $originalInput
     * @param mixed $submittedItems
     * @return array<int, array<string, mixed>>
     */
    private function buildRemovedLineItems($originalInput, $submittedItems): array
    {
        $originalIds = $this->parseLineItemIds($originalInput);
        if (empty($originalIds) || !is_array($submittedItems)) {
            return [];
        }

        $submittedIds = [];
        foreach ($submittedItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lineId = (int) ($item['line_id'] ?? 0);
            if ($lineId > 0) {
                $submittedIds[] = $lineId;
            }
        }

        $removedIds = array_diff($originalIds, $submittedIds);
        if (empty($removedIds)) {
            return [];
        }

        $removed = [];
        foreach ($removedIds as $lineId) {
            $removed[] = [
                'id' => (int) $lineId,
                'quantity' => 0,
            ];
        }

        return $removed;
    }

    private function derivePaymentStatus(array $order): string
    {
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order);
        return OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'none'));
    }

    private function deriveFulfillmentStatus(array $order): string
    {
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order);
        return OrderService::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? 'pending'));
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function computeLifecycleSnapshotFromOrder(array $order, array $context = []): array
    {
        $snapshot = $this->lifecycle->computeSnapshot($order, $this->buildLifecycleContext($order, $context));
        $snapshot['status'] = (string) ($snapshot['order_status'] ?? 'open');
        $snapshot['payment_status'] = (string) ($snapshot['payment_status'] ?? 'none');
        $snapshot['fulfillment_status'] = (string) ($snapshot['fulfillment_status'] ?? 'pending');
        return $snapshot;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildLifecycleContext(array $order, array $context = []): array
    {
        $resolved = $context;
        $meta = $this->extractMeta((array) ($order['meta_data'] ?? []));

        $openingFeeDeferred = $this->normalizeBoolLike($meta['retrato_opening_fee_deferred'] ?? null);
        $openingFeeValue = $this->normalizeMoney($meta['retrato_opening_fee_value'] ?? null) ?? 0.0;
        if (!isset($resolved['opening_fee_due_later']) && $openingFeeDeferred && $openingFeeValue > 0) {
            $resolved['opening_fee_due_later'] = $openingFeeValue;
        }
        if (!isset($resolved['opening_fee_due_now'])) {
            $resolved['opening_fee_due_now'] = 0.0;
        }

        $bagContext = $resolved['bag'] ?? null;
        if (is_array($bagContext)) {
            if (!isset($resolved['opening_fee_due_later']) && isset($bagContext['opening_fee_due_later'])) {
                $resolved['opening_fee_due_later'] = (float) $bagContext['opening_fee_due_later'];
            }
            if (!isset($resolved['opening_fee_due_now']) && isset($bagContext['opening_fee_due_now'])) {
                $resolved['opening_fee_due_now'] = (float) $bagContext['opening_fee_due_now'];
            }
        }

        if (!empty($resolved['return_summary']) && is_array($resolved['return_summary'])) {
            $returnedQty = (int) ($resolved['return_summary']['returned_qty'] ?? 0);
            $totalQty = (int) ($resolved['return_summary']['total_qty'] ?? 0);
            if ($returnedQty > 0 && $totalQty > 0 && $returnedQty >= $totalQty) {
                $resolved['force_returned'] = true;
            }
        }

        return $resolved;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractPaymentEntriesFromOrder(array $order): array
    {
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order);
        $entries = $snapshot['ledger']['entries'] ?? [];
        return is_array($entries) ? $entries : [];
    }

    private function derivePaymentStatusFromEntries(array $entries, float $orderTotal): string
    {
        $order = [
            'status' => 'open',
            'subtotal' => $orderTotal,
            'shipping_total' => 0.0,
            'line_items' => [],
            'meta_data' => [
                [
                    'key' => 'retrato_payment_entries',
                    'value' => $entries,
                ],
            ],
        ];
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order);
        return OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'none'));
    }

    /**
     * @param array<int, array<string, mixed>> $rawEntries
     * @param array<int, array<string, mixed>> $methods
     * @param array<int, array<string, mixed>> $bankAccounts
     * @param array<int, array<string, mixed>> $terminals
     * @param array<int, array<string, mixed>> $voucherAccounts
     * @param int|array<int, int>|null $personIds
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function normalizePaymentEntries(
        array $rawEntries,
        array $methods,
        array $bankAccounts,
        array $terminals,
        array $voucherAccounts,
        array|int|null $personIds
    ): array
    {
        $errors = [];
        $entries = [];

        if (empty($rawEntries)) {
            return [$entries, $errors];
        }

        $personMatchIds = [];
        if (is_array($personIds)) {
            foreach ($personIds as $candidate) {
                $id = (int) $candidate;
                if ($id > 0) {
                    $personMatchIds[$id] = true;
                }
            }
        } else {
            $id = (int) $personIds;
            if ($id > 0) {
                $personMatchIds[$id] = true;
            }
        }

        $methodLookup = $this->indexById($methods);
        $bankLookup = $this->indexById($bankAccounts);
        $terminalLookup = $this->indexById($terminals);
        $voucherLookup = $this->indexById($voucherAccounts);
        $voucherUsage = [];

        foreach ($rawEntries as $index => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $position = $index + 1;
            $methodId = isset($raw['method_id']) ? (int) $raw['method_id'] : 0;
            if ($methodId <= 0 || !isset($methodLookup[$methodId])) {
                $errors[] = 'Pagamento #' . $position . ': método inválido.';
                continue;
            }
            $method = $methodLookup[$methodId];
            $amount = $this->normalizeMoney($raw['amount'] ?? null);
            if ($amount === null || $amount <= 0) {
                $errors[] = 'Pagamento #' . $position . ': valor inválido.';
                continue;
            }
            $fee = $this->normalizeMoney($raw['fee'] ?? null);
            if ($fee === null) {
                $fee = 0.0;
            }
            if ($fee < 0) {
                $errors[] = 'Pagamento #' . $position . ': taxa inválida.';
                continue;
            }
            $paid = $this->normalizePaidFlag($raw['paid'] ?? null);

            $bankAccountId = isset($raw['bank_account_id']) ? (int) $raw['bank_account_id'] : 0;
            $terminalId = isset($raw['terminal_id']) ? (int) $raw['terminal_id'] : 0;
            $requiresBank = !empty($method['requires_bank_account']);
            $requiresTerminal = !empty($method['requires_terminal']);

            $bankAccount = $bankAccountId > 0 ? ($bankLookup[$bankAccountId] ?? null) : null;
            if ($requiresBank && !$bankAccount) {
                $errors[] = 'Pagamento #' . $position . ': selecione a conta bancaria.';
                continue;
            }

            $terminal = $terminalId > 0 ? ($terminalLookup[$terminalId] ?? null) : null;
            if ($requiresTerminal && !$terminal) {
                $errors[] = 'Pagamento #' . $position . ': selecione a maquininha.';
                continue;
            }

            $voucherAccountId = isset($raw['voucher_account_id']) ? (int) $raw['voucher_account_id'] : 0;
            $voucherAccount = null;
            $voucherLabel = null;
            $voucherCode = null;
            $methodType = strtolower((string) ($method['type'] ?? ''));
            if ($methodType === 'voucher') {
                if (empty($personMatchIds)) {
                    $errors[] = 'Pagamento #' . $position . ': informe a cliente para usar cupom/crédito.';
                    continue;
                }
                if ($voucherAccountId <= 0 || !isset($voucherLookup[$voucherAccountId])) {
                    $errors[] = 'Pagamento #' . $position . ': selecione o cupom/crédito.';
                    continue;
                }
                $voucherAccount = $voucherLookup[$voucherAccountId];
                $voucherPersonId = (int) ($voucherAccount['pessoa_id'] ?? 0);
                if ($voucherPersonId <= 0 || !isset($personMatchIds[$voucherPersonId])) {
                    $errors[] = 'Pagamento #' . $position . ': cupom/crédito nao pertence a esta cliente.';
                    continue;
                }
                $voucherStatus = (string) ($voucherAccount['status'] ?? '');
                if ($voucherStatus !== '' && $voucherStatus !== 'ativo') {
                    $errors[] = 'Pagamento #' . $position . ': cupom/crédito inativo.';
                    continue;
                }
                $balance = (float) ($voucherAccount['balance'] ?? 0);
                $nextUsage = ($voucherUsage[$voucherAccountId] ?? 0.0) + $amount;
                if (($nextUsage - $balance) > 0.00001) {
                    $errors[] = 'Pagamento #' . $position . ': saldo insuficiente no cupom/crédito.';
                    continue;
                }
                $voucherUsage[$voucherAccountId] = $nextUsage;
                $voucherLabel = trim((string) ($voucherAccount['label'] ?? ''));
                $voucherCode = trim((string) ($voucherAccount['code'] ?? ''));
                if ($voucherLabel === '' && $voucherCode !== '') {
                    $voucherLabel = $voucherCode;
                }
                if ($voucherLabel === '') {
                    $voucherLabel = 'Cupom/crédito #' . $voucherAccountId;
                }
            }

            $entries[] = [
                'method_id' => $methodId,
                'method_name' => (string) ($method['name'] ?? ''),
                'method_type' => (string) ($method['type'] ?? ''),
                'amount' => $amount,
                'fee' => $fee,
                'paid' => $paid,
                'fee_type' => (string) ($method['fee_type'] ?? 'none'),
                'fee_value' => (float) ($method['fee_value'] ?? 0),
                'bank_account_id' => $bankAccount ? (int) ($bankAccount['id'] ?? 0) : null,
                'bank_account_label' => $bankAccount ? $this->formatBankAccountLabel($bankAccount) : null,
                'bank_pix_key' => $bankAccount['pix_key'] ?? null,
                'bank_pix_key_type' => $bankAccount['pix_key_type'] ?? null,
                'terminal_id' => $terminal ? (int) ($terminal['id'] ?? 0) : null,
                'terminal_label' => $terminal['name'] ?? null,
                'voucher_account_id' => $voucherAccount ? (int) ($voucherAccount['id'] ?? 0) : null,
                'voucher_account_label' => $voucherLabel,
                'voucher_account_code' => $voucherCode,
            ];
        }

        return [$entries, $errors];
    }

    private function applyVoucherDebits(array $entries): void
    {
        if (empty($entries)) {
            return;
        }
        $debits = [];
        foreach ($entries as $entry) {
            $methodType = strtolower((string) ($entry['method_type'] ?? ''));
            if ($methodType !== 'voucher') {
                continue;
            }
            $accountId = (int) ($entry['voucher_account_id'] ?? 0);
            $amount = (float) ($entry['amount'] ?? 0);
            if ($accountId <= 0 || $amount <= 0) {
                continue;
            }
            $debits[$accountId] = ($debits[$accountId] ?? 0.0) + $amount;
        }
        foreach ($debits as $accountId => $amount) {
            $this->voucherAccounts->debitBalance((int) $accountId, (float) $amount);
        }
    }

    private function applyVoucherBalanceDelta(array $beforeEntries, array $afterEntries): void
    {
        $before = $this->summarizeVoucherAmounts($beforeEntries);
        $after = $this->summarizeVoucherAmounts($afterEntries);
        if (empty($before) && empty($after)) {
            return;
        }

        $voucherIds = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($voucherIds as $voucherId) {
            $beforeAmount = $before[$voucherId] ?? 0.0;
            $afterAmount = $after[$voucherId] ?? 0.0;
            $delta = $afterAmount - $beforeAmount;
            if (abs($delta) < 0.00001) {
                continue;
            }
            if ($delta > 0) {
                $this->voucherAccounts->debitBalance((int) $voucherId, (float) $delta);
            } else {
                $this->voucherAccounts->creditBalance((int) $voucherId, (float) abs($delta));
            }
        }
    }

    /**
     * @return array<int, float>
     */
    private function summarizeVoucherAmounts(array $entries): array
    {
        $totals = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $methodType = strtolower((string) ($entry['method_type'] ?? ''));
            if ($methodType !== 'voucher') {
                continue;
            }
            $voucherId = (int) ($entry['voucher_account_id'] ?? 0);
            $amount = (float) ($entry['amount'] ?? 0);
            if ($voucherId <= 0 || $amount <= 0) {
                continue;
            }
            $totals[$voucherId] = ($totals[$voucherId] ?? 0.0) + $amount;
        }

        return $totals;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function applyConsignmentCredits(int $orderId, ?string $paymentStatus): array
    {
        if ($paymentStatus !== 'paid') {
            return [[], []];
        }
        $service = new ConsignmentCreditService($this->pdo);
        return $service->generateForOrder($orderId, $paymentStatus);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function applyConsignmentDebits(int $orderId, string $eventType, array $options = []): array
    {
        if ($eventType === '') {
            return [[], []];
        }
        $service = new ConsignmentCreditService($this->pdo);
        return $service->debitForOrderEvent($orderId, $eventType, $options);
    }

    /**
     * @param array<int, string> $messages
     * @param array<int, string> $errors
     */
    private function appendConsignmentResult(string $base, array $messages, array $errors): string
    {
        $parts = [];
        if ($base !== '') {
            $parts[] = $base;
        }
        if (!empty($messages)) {
            $parts[] = implode(' ', $messages);
        }
        if (!empty($errors)) {
            $parts[] = 'Falhas consignação: ' . implode(' ', $errors);
        }
        return trim(implode(' ', $parts));
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function reprocessConsignment(int $orderId, bool $dryRun): array
    {
        // Fluxo local sem integrações legadas.
        $messages = [];
        $errors = [];

        if (!$this->pdo) {
            return [$messages, ['Sem conexão com banco local.']];
        }
        if ($orderId <= 0) {
            return [$messages, ['Pedido inválido para consignação.']];
        }

        try {
            // MIGRADO: Usar OrderRepository local
            $orderData = $this->orders->findOrderWithDetails($orderId);
            if (!$orderData) {
                return [$messages, ['Pedido não encontrado.']];
            }
        } catch (\Throwable $e) {
            return [$messages, ['Erro ao carregar pedido: ' . $e->getMessage()]];
        }

        $formData = $this->orderToForm($orderData);
        $paymentStatus = OrderService::normalizePaymentStatus((string) ($formData['payment_status'] ?? 'none'));
        $status = $this->normalizeOrderStatus((string) ($formData['status'] ?? 'open'));

        $consignment = new ConsignmentCreditService($this->pdo);
        if ($paymentStatus === 'paid') {
            // MIGRADO: Remover parâmetro $repoLegado
            [$creditMessages, $creditErrors] = $consignment->generateForOrder($orderId, $paymentStatus, $dryRun);
            if (!empty($creditMessages)) {
                $messages = array_merge($messages, $creditMessages);
            }
            if (!empty($creditErrors)) {
                $errors = array_merge($errors, $creditErrors);
            }
        }

        $eventMap = [];
        if ($status === 'cancelled') {
            $eventMap['order_cancel'] = 'Cancelamento do pedido';
        }
        if ($status === 'trash') {
            $eventMap['order_trash'] = 'Pedido na lixeira';
        }
        if ($status === 'deleted') {
            $eventMap['order_delete'] = 'Pedido excluído';
        }
        if ($paymentStatus === 'refunded' || $status === 'refunded') {
            $eventMap['payment_refund'] = 'Estorno de pagamento';
        }
        if ($paymentStatus === 'failed' || $status === 'failed') {
            $eventMap['payment_failed'] = 'Pagamento falhou';
        }

        foreach ($eventMap as $eventType => $eventLabel) {
            [$debitMessages, $debitErrors] = $consignment->debitForOrderEvent($orderId, $eventType, [
                'event_label' => $eventLabel,
            ], $dryRun);
            if (!empty($debitMessages)) {
                $messages = array_merge($messages, $debitMessages);
            }
            if (!empty($debitErrors)) {
                $errors = array_merge($errors, $debitErrors);
            }
        }

        $returnRows = $this->orderReturns->listByOrder($orderId);
        foreach ($returnRows as $returnRow) {
            $returnId = (int) ($returnRow['id'] ?? 0);
            if ($returnId <= 0) {
                continue;
            }
            $items = $this->orderReturns->listItems($returnId);
            $returnRow['items'] = $items;
            $return = OrderReturn::fromArray($returnRow);

            if (!empty($return->restockedAt) && $return->status !== 'cancelled') {
                [$returnMessages, $returnErrors] = $consignment->debitForReturn($return, $return->items, $dryRun);
                if (!empty($returnMessages)) {
                    $messages = array_merge($messages, $returnMessages);
                }
                if (!empty($returnErrors)) {
                    $errors = array_merge($errors, $returnErrors);
                }
            }

            if ($return->status === 'cancelled') {
                $eventAt = $return->updatedAt ?: date('Y-m-d H:i:s');
                [$undoMessages, $undoErrors] = $consignment->creditForReturnUndo(
                    $orderId,
                    $returnId,
                    $eventAt,
                    $return->notes,
                    $dryRun
                );
                if (!empty($undoMessages)) {
                    $messages = array_merge($messages, $undoMessages);
                }
                if (!empty($undoErrors)) {
                    $errors = array_merge($errors, $undoErrors);
                }
            }
        }

        return [$messages, $errors];
    }

    private function normalizeOrderStatus(string $status): string
    {
        return OrderService::normalizeOrderStatus($status);
    }

    private function applyStatusRules(Order $order, array $entries): void
    {
        $order->paymentEntries = $entries;
        $snapshot = $this->computeLifecycleSnapshotFromOrder($order->toArray());
        $order->payment->status = (string) ($snapshot['payment_status'] ?? 'none');
        $order->payment->setPaid = $order->payment->status === 'paid';
        $order->shippingInfo->status = (string) ($snapshot['fulfillment_status'] ?? 'pending');
        $order->status = (string) ($snapshot['status'] ?? 'open');

        if (count($entries) === 1) {
            $entry = $entries[0];
            $order->payment->method = (string) ($entry['method_type'] ?? '');
            $order->payment->methodTitle = (string) ($entry['method_name'] ?? '');
            return;
        }

        if (count($entries) > 1) {
            $order->payment->method = 'multi';
            $order->payment->methodTitle = 'Pagamento dividido';
        }
    }

    private function resolvePaymentMethodLabel(?string $method, ?string $methodTitle): ?string
    {
        $label = trim((string) ($methodTitle ?? ''));
        if ($label !== '') {
            return $label;
        }

        $label = trim((string) ($method ?? ''));
        return $label !== '' ? $label : null;
    }

    private function validatePaidStatusPaymentMethodSelection(string $paymentStatus, ?string $paymentMethod, string $context = ''): ?string
    {
        if (OrderService::normalizePaymentStatus($paymentStatus) !== 'paid') {
            return null;
        }

        if (trim((string) $paymentMethod) !== '') {
            return null;
        }

        return match ($context) {
            'inline' => 'Selecione o metodo de pagamento/recebimento para marcar o pedido como Pago.',
            'payment_tab' => 'Selecione o metodo de pagamento/recebimento antes de atualizar o status para Pago.',
            'status' => 'Nao foi possivel salvar: pedido marcado como Pago exige metodo de pagamento/recebimento.',
            'full_edit', 'create' => 'Pedido com status Pago exige metodo de pagamento/recebimento informado.',
            default => 'Para marcar o pedido como Pago, informe o metodo de pagamento/recebimento.',
        };
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
            $this->appendTimelineEvent($timeline, 'delivery_defined', 'Entrega definida: ' . $label);
        }

        $beforeKind = OrderService::normalizeShipmentKind(
            (string) ($before['shipment_kind'] ?? ''),
            $afterMode !== '' ? $afterMode : $beforeMode
        );
        $afterKind = OrderService::normalizeShipmentKind(
            (string) ($after['shipment_kind'] ?? ''),
            $afterMode !== '' ? $afterMode : $beforeMode
        );
        if ($afterKind !== null && $afterKind !== $beforeKind) {
            $kindLabel = OrderService::shipmentKindOptions()[$afterKind] ?? $afterKind;
            $this->appendTimelineEvent($timeline, 'delivery_defined', 'Tipo de envio definido: ' . $kindLabel);
        }

        $beforeCarrier = trim((string) ($before['carrier'] ?? ''));
        $afterCarrier = trim((string) ($after['carrier'] ?? ''));
        $beforeCarrierId = (int) ($before['carrier_id'] ?? 0);
        $afterCarrierId = (int) ($after['carrier_id'] ?? 0);
        if (($afterCarrierId > 0 || $afterCarrier !== '')
            && ($afterCarrierId !== $beforeCarrierId || $afterCarrier !== $beforeCarrier)) {
            $carrierLabel = $afterCarrier !== '' ? $afterCarrier : ('#' . $afterCarrierId);
            $this->appendTimelineEvent($timeline, 'carrier_defined', 'Transportadora definida: ' . $carrierLabel);
        }

        $beforeTracking = trim((string) ($before['tracking_code'] ?? ''));
        $afterTracking = trim((string) ($after['tracking_code'] ?? ''));
        if ($afterTracking !== '' && $afterTracking !== $beforeTracking) {
            $this->appendTimelineEvent($timeline, 'tracking_informed', 'Código de rastreio informado: ' . $afterTracking);
        }

        $beforeStatus = OrderService::normalizeFulfillmentStatus((string) ($before['status'] ?? 'pending'));
        $afterStatus = OrderService::normalizeFulfillmentStatus($newStatus);
        if ($afterStatus !== $beforeStatus) {
            $statusLabel = OrderService::fulfillmentStatusOptions()[$afterStatus] ?? $afterStatus;
            $this->appendTimelineEvent($timeline, 'delivery_status_changed', 'Status de entrega alterado: ' . $statusLabel);
        }
        if ($afterStatus === 'delivered' && $beforeStatus !== 'delivered') {
            $this->appendTimelineEvent($timeline, 'delivery_completed', 'Entrega concluída');
        }

        $after['timeline'] = $timeline;
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     */
    private function appendTimelineEvent(array &$timeline, string $type, string $label): void
    {
        $timeline[] = [
            'type' => $type,
            'label' => $label,
            'at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeShippingInfoPayload($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function indexById(array $rows): array
    {
        $output = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $output[$id] = $row;
            }
        }
        return $output;
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
    }

    private function normalizePaidFlag($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower((string) $value);
        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }
        return true;
    }

    private function normalizeBoolLike($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }

    private function formatBankAccountLabel(array $bankAccount): string
    {
        $bankName = trim((string) ($bankAccount['bank_name'] ?? ''));
        $label = trim((string) ($bankAccount['label'] ?? ''));
        if ($bankName !== '' && $label !== '') {
            return $bankName . ' - ' . $label;
        }
        if ($label !== '') {
            return $label;
        }
        if ($bankName !== '') {
            return $bankName;
        }
        $id = isset($bankAccount['id']) ? (int) $bankAccount['id'] : 0;
        return $id > 0 ? 'Conta #' . $id : 'Conta';
    }

    private function extractMeta(array $metaData): array
    {
        $output = [];
        foreach ($metaData as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $key = $meta['key'] ?? null;
            if ($key === null) {
                continue;
            }
            $output[$key] = $meta['value'] ?? null;
        }

        return $output;
    }

    /**
     * @return array{created: int, deleted: int, error: ?string}
     */
    private function syncOrderFinanceEntries(int $orderId): array
    {
        $result = [
            'created' => 0,
            'deleted' => 0,
            'error' => null,
        ];

        if (!$this->pdo || $orderId <= 0) {
            return $result;
        }

        try {
            $syncService = new OrderFinanceSyncService($this->pdo);
            $syncResult = $syncService->sync($orderId);
            $result['created'] = (int) ($syncResult['created'] ?? 0);
            $result['deleted'] = (int) ($syncResult['deleted'] ?? 0);
        } catch (\Throwable $e) {
            error_log('Falha ao sincronizar financeiro do pedido #' . $orderId . ': ' . $e->getMessage());
            $result['error'] = 'Falha ao sincronizar financeiro';
        }

        return $result;
    }

    /**
     * @param array{created?: int, deleted?: int, error?: ?string} $syncResult
     */
    private function appendFinanceSyncMessage(string $message, array $syncResult): string
    {
        if (!empty($syncResult['error'])) {
            return trim($message . ' Aviso: nao foi possivel sincronizar creditos financeiros automaticamente.');
        }

        $created = (int) ($syncResult['created'] ?? 0);
        $deleted = (int) ($syncResult['deleted'] ?? 0);
        if ($created > 0 || $deleted > 0) {
            return trim($message . ' Financeiro sincronizado.');
        }

        return $message;
    }

    private function reconcileOrderLifecycleState(int $orderId, array $orderData): array
    {
        if ($orderId <= 0 || empty($orderData)) {
            return $orderData;
        }

        $currentStatus = OrderService::normalizeOrderStatus((string) ($orderData['status'] ?? 'open'));
        if (in_array($currentStatus, ['trash', 'deleted'], true)) {
            return $orderData;
        }

        $snapshot = $this->computeLifecycleSnapshotFromOrder($orderData);
        $targetStatus = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
        $targetPaymentStatus = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'none'));
        $targetFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? 'pending'));

        if (in_array($currentStatus, ['cancelled', 'refunded'], true) && $targetStatus !== $currentStatus) {
            $targetStatus = $currentStatus;
        }

        $currentPaymentStatus = OrderService::normalizePaymentStatus((string) ($orderData['payment_status'] ?? 'none'));
        $currentFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($orderData['fulfillment_status'] ?? 'pending'));

        if (
            $currentStatus === $targetStatus
            && $currentPaymentStatus === $targetPaymentStatus
            && $currentFulfillmentStatus === $targetFulfillmentStatus
        ) {
            return $orderData;
        }

        try {
            $payload = $this->lifecycle->toPersistencePayload($snapshot, $orderData);
            $payload['status'] = $targetStatus;
            $this->orders->updateStatusComplete($orderId, $targetStatus, null, $payload);
            $reloaded = $this->orders->findOrderWithDetails($orderId);
            return $reloaded ?: $orderData;
        } catch (\Throwable $e) {
            return $orderData;
        }
    }

    private function syncOrderCompletionIfReady(int $orderId, ?string $paymentStatusOverride, ?string $fulfillmentStatusOverride): void
    {
        // MIGRADO: Usar OrderRepository local
        $orderData = $this->orders->findOrderWithDetails($orderId);
        if (!$orderData) {
            return;
        }

        $snapshot = $this->computeLifecycleSnapshotFromOrder($orderData);
        if ($paymentStatusOverride !== null) {
            $snapshot['payment_status'] = OrderService::normalizePaymentStatus($paymentStatusOverride);
        }
        if ($fulfillmentStatusOverride !== null) {
            $snapshot['fulfillment_status'] = OrderService::normalizeFulfillmentStatus($fulfillmentStatusOverride);
        }
        $snapshot['status'] = OrderService::deriveLifecycleStatus(
            (string) ($orderData['status'] ?? 'open'),
            (string) ($snapshot['payment_status'] ?? 'none'),
            (string) ($snapshot['fulfillment_status'] ?? 'pending')
        );

        $targetStatus = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? 'open'));
        $paymentStatus = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'none'));
        $fulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? 'pending'));
        $currentStatus = OrderService::normalizeOrderStatus((string) ($orderData['status'] ?? 'open'));
        $currentPaymentStatus = OrderService::normalizePaymentStatus((string) ($orderData['payment_status'] ?? 'none'));
        $currentFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($orderData['fulfillment_status'] ?? 'pending'));

        if (
            $targetStatus === $currentStatus
            && $paymentStatus === $currentPaymentStatus
            && $fulfillmentStatus === $currentFulfillmentStatus
        ) {
            return;
        }

        $payload = $this->lifecycle->toPersistencePayload($snapshot, $orderData);
        $this->orders->updateStatusComplete($orderId, $targetStatus, null, $payload);
    }

    // MIGRADO: Removido parâmetro $repoLegado, usando CustomerRepository local
    private function resolvePersonIdFromInput(int $rawPersonId): ?int
    {
        if ($rawPersonId <= 0) {
            return null;
        }

        $personRow = $this->customers->findAsArray($rawPersonId);
        if ($personRow) {
            $personId = $this->resolvePersonIdFromCustomerRow($personRow);
            return $personId > 0 ? $personId : null;
        }

        $person = $this->persons->find($rawPersonId);
        if ($person && $person->id) {
            return (int) $person->id;
        }

        return null;
    }

    private function buildCustomerOption(array $customer, ?array $openBagLookup = null): array
    {
        $personId = $this->resolvePersonIdFromCustomerRow($customer);
        $openBagSummary = null;
        if ($openBagLookup !== null) {
            if ($personId > 0 && isset($openBagLookup[$personId]) && is_array($openBagLookup[$personId])) {
                $openBagSummary = $openBagLookup[$personId];
            }
        } elseif ($personId > 0 && $this->bags) {
            $openBag = $this->bags->findOpenByPerson($personId);
            if ($openBag && $openBag->id) {
                $totals = $this->bags->getTotals((int) $openBag->id);
                $openBagSummary = [
                    'id' => (int) $openBag->id,
                    'pessoa_id' => (int) ($openBag->personId ?? $personId),
                    'opened_at' => (string) ($openBag->openedAt ?? ''),
                    'expected_close_at' => (string) ($openBag->expectedCloseAt ?? ''),
                    'items_qty' => (int) ($totals['items_qty'] ?? 0),
                    'items_total' => (float) ($totals['items_total'] ?? 0),
                    'items_weight' => (float) ($totals['items_weight'] ?? 0),
                    'opening_fee_value' => (float) ($openBag->openingFeeValue ?? 0),
                    'opening_fee_paid' => !empty($openBag->openingFeePaid),
                    'opening_fee_paid_at' => (string) ($openBag->openingFeePaidAt ?? ''),
                ];
            }
        }
        $hasOpenBag = $openBagSummary !== null;

        return [
            'id' => $personId,
            'user_id' => (int) ($customer['user_id'] ?? 0),
            'name' => $this->resolveFullName($customer),
            'full_name' => $this->resolveFullName($customer),
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'street' => $customer['street'] ?? '',
            'street2' => $customer['street2'] ?? '',
            'number' => $customer['number'] ?? '',
            'neighborhood' => $customer['neighborhood'] ?? '',
            'city' => $customer['city'] ?? '',
            'state' => $customer['state'] ?? '',
            'zip' => $customer['zip'] ?? '',
            'country' => $customer['billing_country'] ?? ($customer['country'] ?? 'BR'),
            'shipping_full_name' => $this->resolveShippingName($customer),
            'shipping_address_1' => $customer['shipping_address_1'] ?? '',
            'shipping_address_2' => $customer['shipping_address_2'] ?? '',
            'shipping_number' => $customer['shipping_number'] ?? '',
            'shipping_neighborhood' => $customer['shipping_neighborhood'] ?? '',
            'shipping_city' => $customer['shipping_city'] ?? '',
            'shipping_state' => $customer['shipping_state'] ?? '',
            'shipping_postcode' => $customer['shipping_postcode'] ?? '',
            'shipping_country' => $customer['shipping_country'] ?? '',
            'has_open_bag' => $hasOpenBag,
            'open_bag' => $openBagSummary,
        ];
    }

    private function resolvePersonIdFromCustomerRow(array $customer): int
    {
        $candidates = [
            $customer['pessoa_id'] ?? null,
            $customer['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function resolvePersonIdFromOrderRow(array $order): int
    {
        $candidates = [
            $order['pessoa_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function resolvePersonIdFromReturnRow(array $returnRow): int
    {
        $candidates = [
            $returnRow['pessoa_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<int, string> $permissions
     */
    private function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (Auth::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $permissions
     */
    private function requireAnyPermissionJson(array $permissions, string $message): void
    {
        if ($this->hasAnyPermission($permissions)) {
            return;
        }

        $this->logInlineCustomerFlow('permission_denied', [
            'required_permissions' => $permissions,
            'evaluated_permissions' => $this->permissionEvaluation($permissions),
        ]);

        $this->respondJson([
            'ok' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * @param array<int, string> $permissions
     * @return array<string, bool>
     */
    private function permissionEvaluation(array $permissions): array
    {
        $map = [];
        foreach ($permissions as $permission) {
            $map[$permission] = Auth::can($permission);
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function summarizeInlineCustomerPayload(array $input): array
    {
        $maskEmail = static function (string $email): string {
            $email = trim($email);
            if ($email === '') {
                return '';
            }
            $parts = explode('@', $email, 2);
            if (count($parts) !== 2 || $parts[0] === '') {
                return '***';
            }
            return substr($parts[0], 0, 1) . '***@' . $parts[1];
        };

        $maskTail = static function (string $value, int $tail = 2): string {
            $value = preg_replace('/\D+/', '', $value);
            $value = is_string($value) ? $value : '';
            if ($value === '') {
                return '';
            }
            if (strlen($value) <= $tail) {
                return str_repeat('*', strlen($value));
            }
            return str_repeat('*', strlen($value) - $tail) . substr($value, -$tail);
        };

        return [
            'full_name_len' => strlen(trim((string) ($input['fullName'] ?? ''))),
            'email' => $maskEmail((string) ($input['email'] ?? '')),
            'phone' => $maskTail((string) ($input['phone'] ?? ''), 2),
            'cpf_cnpj' => $maskTail((string) ($input['cpfCnpj'] ?? ''), 3),
            'state' => strtoupper(trim((string) ($input['state'] ?? ''))),
            'country' => strtoupper(trim((string) ($input['country'] ?? ''))),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInlineCustomerFlow(string $event, array $context = []): void
    {
        $user = Auth::user();
        $entry = [
            'scope' => 'orders.inline_customer',
            'event' => $event,
            'route' => (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'user_id' => (int) ($user['id'] ?? 0),
            'user_role' => (string) ($user['role'] ?? ''),
            'context' => $context,
            'ts' => date('c'),
        ];
        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log('[orders.inline_customer] ' . ($encoded !== false ? $encoded : $event));
    }

    private function ensurePersonIsCustomer(int $personId): void
    {
        if ($personId <= 0 || !$this->pdo) {
            return;
        }

        $roles = new PersonRoleRepository($this->pdo);
        $roleRows = $roles->listByPerson($personId);
        $hasCustomerRole = false;
        foreach ($roleRows as $row) {
            if (trim((string) ($row['role'] ?? '')) === 'cliente') {
                $hasCustomerRole = true;
                break;
            }
        }
        if (!$hasCustomerRole) {
            $roles->assign($personId, 'cliente', 'orders_inline');
        }

        $person = $this->persons->find($personId);
        if (!$person) {
            return;
        }

        $metadata = $person->metadata ?? [];
        $changed = false;
        if (!isset($metadata['is_cliente']) || $metadata['is_cliente'] !== true) {
            $metadata['is_cliente'] = true;
            $changed = true;
        }

        $tipos = $metadata['tipos'] ?? [];
        if (!is_array($tipos)) {
            $tipos = [];
        }
        if (!in_array('cliente', $tipos, true)) {
            $tipos[] = 'cliente';
            $metadata['tipos'] = array_values(array_unique($tipos));
            $changed = true;
        }

        if ($changed) {
            $person->metadata = $metadata;
            $person->lastSyncedAt = date('Y-m-d H:i:s');
            $this->persons->save($person);
        }
    }

    private function buildCustomerOptionByPersonId(int $personId): ?array
    {
        if ($personId <= 0) {
            return null;
        }

        $customerRow = $this->customers->findAsArray($personId);
        if ($customerRow) {
            return $this->buildCustomerOption($customerRow);
        }

        $person = $this->persons->find($personId);
        if (!$person || !$person->id) {
            return null;
        }

        return $this->buildCustomerOption([
            'id' => $person->id,
            'pessoa_id' => $person->id,
            'user_id' => $person->id,
            'full_name' => $person->fullName,
            'name' => $person->fullName,
            'email' => $person->email ?? '',
            'phone' => $person->phone ?? '',
            'street' => $person->street ?? '',
            'street2' => $person->street2 ?? '',
            'number' => $person->number ?? '',
            'neighborhood' => $person->neighborhood ?? '',
            'city' => $person->city ?? '',
            'state' => $person->state ?? '',
            'zip' => $person->zip ?? '',
            'country' => $person->country ?? 'BR',
            'shipping_full_name' => $person->fullName,
            'shipping_address_1' => $person->street ?? '',
            'shipping_address_2' => $person->street2 ?? '',
            'shipping_number' => $person->number ?? '',
            'shipping_neighborhood' => $person->neighborhood ?? '',
            'shipping_city' => $person->city ?? '',
            'shipping_state' => $person->state ?? '',
            'shipping_postcode' => $person->zip ?? '',
            'shipping_country' => $person->country ?? 'BR',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function extractAddressPayload(array $input): array
    {
        $fields = [
            'full_name',
            'email',
            'phone',
            'address_1',
            'address_2',
            'number',
            'neighborhood',
            'city',
            'state',
            'postcode',
            'country',
        ];
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = trim((string) ($input[$field] ?? ''));
        }

        return $payload;
    }

    // MIGRADO: Removido parâmetro $repoLegado, usando CustomerRepository local
    private function handleCustomerAddressUpdate(): void
    {
        $rawPersonId = (int) ($_POST['pessoa_id'] ?? 0);
        $personId = $this->resolvePersonIdFromInput($rawPersonId);
        $this->logInlineCustomerFlow('address_update_request', [
            'raw_person_id' => $rawPersonId,
            'resolved_person_id' => $personId,
            'section' => (string) ($_POST['section'] ?? ''),
            'permissions' => $this->permissionEvaluation(['customers.edit', 'people.edit']),
        ]);
        if ($personId === null) {
            $this->respondJson([
                'ok' => false,
                'message' => 'Cliente inválido.',
            ], 400);
        }

        $section = trim((string) ($_POST['section'] ?? ''));
        if (!in_array($section, ['billing', 'shipping'], true)) {
            $this->respondJson([
                'ok' => false,
                'message' => 'Seção inválida.',
            ], 400);
        }

        try {
            $this->requireAnyPermissionJson(
                ['customers.edit', 'people.edit'],
                'Sem permissão para editar cliente.'
            );
            $payload = $this->extractAddressPayload($_POST);
            $person = $this->persons->find((int) $personId);
            if (!$person) {
                $this->respondJson([
                    'ok' => false,
                    'message' => 'Pessoa não encontrada para atualização.',
                ], 404);
            }

            if ($section === 'billing') {
                if ($payload['full_name'] !== '') {
                    $person->fullName = $payload['full_name'];
                }
                if ($payload['email'] !== '') {
                    $person->email = $payload['email'];
                }
                if ($payload['phone'] !== '') {
                    $person->phone = $payload['phone'];
                }
            }

            if ($payload['address_1'] !== '') {
                $person->street = $payload['address_1'];
            }
            if ($payload['address_2'] !== '') {
                $person->street2 = $payload['address_2'];
            }
            if ($payload['number'] !== '') {
                $person->number = $payload['number'];
            }
            if ($payload['neighborhood'] !== '') {
                $person->neighborhood = $payload['neighborhood'];
            }
            if ($payload['city'] !== '') {
                $person->city = $payload['city'];
            }
            if ($payload['state'] !== '') {
                $person->state = strtoupper($payload['state']);
            }
            if ($payload['postcode'] !== '') {
                $person->zip = $payload['postcode'];
            }
            if ($payload['country'] !== '') {
                $person->country = strtoupper($payload['country']);
            }

            $this->persons->save($person);
            $this->ensurePersonIsCustomer((int) $personId);
        } catch (\Throwable $e) {
            $this->logInlineCustomerFlow('address_update_failed', [
                'person_id' => (int) $personId,
                'error' => $e->getMessage(),
            ]);
            $this->respondJson([
                'ok' => false,
                'message' => 'Erro ao atualizar cliente: ' . $e->getMessage(),
            ], 400);
        }

        $customer = $this->buildCustomerOptionByPersonId((int) $personId);
        $response = [
            'ok' => true,
            'customer' => $customer,
        ];

        $this->logInlineCustomerFlow('address_update_success', [
            'person_id' => (int) $personId,
        ]);
        $this->respondJson($response);
    }

    // MIGRADO: Removido parâmetro $repoLegado, usando apenas fluxo autônomo local
    private function handleCustomerCreate(): void
    {
        $input = $_POST;
        $permissions = ['customers.create', 'people.create'];
        $this->logInlineCustomerFlow('create_request', [
            'payload' => $this->summarizeInlineCustomerPayload($input),
            'permissions' => $this->permissionEvaluation($permissions),
        ]);

        $transactionStarted = false;
        try {
            $this->requireAnyPermissionJson(
                $permissions,
                'Sem permissão para criar cliente.'
            );

            if (!$this->pdo) {
                throw new \RuntimeException('Sem conexão com banco para criar cliente.');
            }

            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            if (!isset($input['status']) || $input['status'] === '') {
                $input['status'] = 'ativo';
            }
            if (isset($input['state']) && $input['state'] !== '') {
                $input['state'] = strtoupper(trim((string) $input['state']));
            }
            if (isset($input['country']) && $input['country'] !== '') {
                $country = strtoupper(trim((string) $input['country']));
                if (in_array($country, ['BRASIL', 'BRAZIL'], true)) {
                    $country = 'BR';
                }
                $input['country'] = $country;
            }

            $service = new CustomerService();
            [$customer, $errors] = $service->validate($input, false);
            if (!empty($errors)) {
                $this->respondJson([
                    'ok' => false,
                    'message' => implode(' ', $errors),
                    'errors' => $errors,
                ], 400);
            }

            $email = trim((string) ($input['email'] ?? ''));
            $existing = $email !== '' ? $this->persons->findByEmail($email) : null;
            if ($existing && $existing->id) {
                $personId = (int) $existing->id;
                $this->ensurePersonIsCustomer($personId);
                $existingOption = $this->buildCustomerOptionByPersonId($personId);
                if ($transactionStarted && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                    $transactionStarted = false;
                }
                $this->logInlineCustomerFlow('create_existing_customer_selected', [
                    'person_id' => $personId,
                    'email' => $this->summarizeInlineCustomerPayload(['email' => $email])['email'],
                ]);
                $this->respondJson([
                    'ok' => true,
                    'existing' => true,
                    'message' => 'Já existe cliente com este e-mail. Cliente existente selecionado.',
                    'customer' => $existingOption,
                ]);
            }

            $catalog = new CatalogCustomerService(null, $this->pdo);
            $created = $catalog->create($customer);
            $personId = (int) ($created['id'] ?? 0);
            if ($personId <= 0) {
                throw new \RuntimeException('Falha ao criar cliente: ID inválido retornado.');
            }

            $this->ensurePersonIsCustomer($personId);
            $customerOption = $this->buildCustomerOptionByPersonId($personId);
            if (!$customerOption) {
                throw new \RuntimeException('Cliente criado, mas não foi possível carregar dados para seleção.');
            }

            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->commit();
                $transactionStarted = false;
            }

            $this->logInlineCustomerFlow('create_success', [
                'person_id' => $personId,
            ]);
            $this->respondJson([
                'ok' => true,
                'customer' => $customerOption,
            ]);
        } catch (\Throwable $e) {
            if ($transactionStarted && $this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logInlineCustomerFlow('create_failed', [
                'error' => $e->getMessage(),
            ]);
            $this->respondJson([
                'ok' => false,
                'message' => 'Erro ao criar cliente: ' . $e->getMessage(),
            ], 400);
        }
    }

    private function handleEnsureOpenBag(): void
    {
        $rawPersonId = (int) ($_POST['pessoa_id'] ?? 0);
        $personId = $this->resolvePersonIdFromInput($rawPersonId);
        if ($personId === null) {
            $this->respondJson([
                'ok' => false,
                'message' => 'Cliente inválido.',
            ], 400);
        }

        try {
            if (!Auth::can('orders.create') && !Auth::can('orders.edit')) {
                Auth::requirePermission('orders.create', $this->pdo);
            }

            $openedNow = false;
            $bag = $this->bags->findOpenByPerson((int) $personId);

            if ($bag) {
                $bagEligibilityError = $this->validateOpenBagForAdd($bag);
                if ($bagEligibilityError !== null) {
                    throw new \RuntimeException($bagEligibilityError);
                }
            } else {
                $customer = $this->customers->findAsArray((int) $personId);
                if (!$customer) {
                    throw new \RuntimeException('Cliente não encontrado.');
                }

                $bag = new Bag();
                $bag->personId = (int) $personId;
                $bag->customerName = $this->resolveFullName($customer);
                $customerEmail = trim((string) ($customer['email'] ?? ''));
                $bag->customerEmail = $customerEmail !== '' ? $customerEmail : null;
                $bag->status = 'aberta';
                $bag->openedAt = date('Y-m-d H:i:s');
                $bag->expectedCloseAt = date('Y-m-d H:i:s', strtotime($bag->openedAt . ' +30 days'));
                $bag->openingFeeValue = $this->resolveOpeningFee();
                $bag->openingFeePaid = false;
                $bag->openingFeePaidAt = null;

                try {
                    $this->bags->save($bag);
                    $openedNow = true;
                } catch (\Throwable $saveError) {
                    // Conflito concorrente: outra requisição abriu a sacolinha no meio do fluxo.
                    $existingOpenBag = $this->bags->findOpenByPerson((int) $personId);
                    if (!$existingOpenBag) {
                        throw $saveError;
                    }
                    $bag = $existingOpenBag;
                }
            }

            $customer = $this->customers->findAsArray((int) $personId);
            $customerOption = $customer ? $this->buildCustomerOption($customer) : null;
            $bagSummary = null;
            if (is_array($customerOption) && isset($customerOption['open_bag']) && is_array($customerOption['open_bag'])) {
                $bagSummary = $customerOption['open_bag'];
            }

            $this->respondJson([
                'ok' => true,
                'opened' => $openedNow,
                'message' => $openedNow
                    ? 'Sacolinha aberta automaticamente com este pedido.'
                    : 'Sacolinha aberta da cliente carregada.',
                'bag' => $bagSummary,
                'customer' => $customerOption,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'ok' => false,
                'message' => 'Não foi possível preparar a sacolinha: ' . $e->getMessage(),
            ], 400);
        }
    }

    private function resolveFullName(array $row): string
    {
        // Prefer full_name (pessoas table unified name field)
        $fullName = trim((string) ($row['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }
        // Legacy WooCommerce fallback: first_name + last_name
        $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        return (string) ($row['display_name'] ?? $row['email'] ?? 'Cliente');
    }

    private function resolveShippingName(array $row): string
    {
        // Prefer shipping_full_name (pessoas table unified name)
        $shippingFullName = trim((string) ($row['shipping_full_name'] ?? ''));
        if ($shippingFullName !== '') {
            return $shippingFullName;
        }
        // Legacy WooCommerce fallback: shipping_first_name + shipping_last_name
        $name = trim((string) (($row['shipping_first_name'] ?? '') . ' ' . ($row['shipping_last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        return $this->resolveFullName($row);
    }

    // MIGRADO: Removido parâmetro $repoLegado, usando CustomerRepository local
    private function applyCustomerFallback(array $formData, array $order): array
    {
        $billingName = trim((string) ($formData['billing_full_name'] ?? ''));
        $billingEmail = trim((string) ($formData['billing_email'] ?? ''));
        if ($billingName !== '' && $billingEmail !== '') {
            return $formData;
        }

        $personId = $this->resolvePersonIdFromOrderRow($order);
        if ($personId <= 0) {
            return $formData;
        }

        // MIGRADO: Usar CustomerRepository local
        $customer = $this->customers->findAsArray($personId);
        if (!$customer) {
            return $formData;
        }

        $resolvedPersonId = $this->resolvePersonIdFromCustomerRow($customer);
        if ($resolvedPersonId > 0) {
            $formData['pessoa_id'] = (string) $resolvedPersonId;
        }

        if ($billingName === '') {
            $resolvedName = $this->resolveFullName($customer);
            if ($resolvedName !== '') {
                $formData['billing_full_name'] = $resolvedName;
            }
        }

        if ($billingEmail === '') {
            $email = trim((string) ($customer['email'] ?? ''));
            if ($email !== '') {
                $formData['billing_email'] = $email;
            }
        }

        return $formData;
    }

    /**
     * @return int[]
     * MIGRADO: Removido parâmetro $repoLegado, usando CustomerRepository local
     */
    private function resolveVoucherPersonMatchIds(int $rawPersonId): array
    {
        $personId = $this->resolvePersonIdFromInput($rawPersonId);
        if ($personId === null || $personId <= 0) {
            return [];
        }
        return [$personId];
    }

    private function buildBagContext(array $orderData): array
    {
        $shippingInfo = isset($orderData['shipping_info']) && is_array($orderData['shipping_info'])
            ? $orderData['shipping_info']
            : [];
        $deliveryMode = OrderService::normalizeDeliveryMode(
            (string) ($orderData['delivery_mode'] ?? ($shippingInfo['delivery_mode'] ?? 'shipment'))
        );
        $shipmentKind = OrderService::normalizeShipmentKind(
            (string) ($orderData['shipment_kind'] ?? ($shippingInfo['shipment_kind'] ?? '')),
            $deliveryMode
        );

        $bagAction = 'none';
        if ($deliveryMode === 'shipment' && $shipmentKind === 'bag_deferred') {
            $bagId = isset($orderData['bag_id']) && (int) $orderData['bag_id'] > 0
                ? (int) $orderData['bag_id']
                : (isset($shippingInfo['bag_id']) && (int) $shippingInfo['bag_id'] > 0 ? (int) $shippingInfo['bag_id'] : 0);
            $bagAction = $bagId > 0 ? 'add_to_bag' : 'open_bag';
        }

        $orderId = (int) ($orderData['id'] ?? 0);
        $orderItems = $orderData['line_items'] ?? [];
        $orderItemsCount = is_array($orderItems) ? count($orderItems) : 0;

        $personId = $this->resolvePersonIdFromOrderRow($orderData);
        $openBag = $personId > 0 ? $this->bags->findOpenByPerson($personId) : null;
        $bagTotals = $openBag && $openBag->id
            ? $this->bags->getTotals($openBag->id)
            : ['items_qty' => 0, 'items_total' => 0.0, 'items_weight' => 0.0];
        $orderInBag = $openBag && $openBag->id && $orderId > 0
            ? $this->bags->hasOrderItems($openBag->id, $orderId)
            : false;

        $openingFee = $this->resolveOpeningFeeFromOrder($orderData);
        $openingFeeDueLater = 0.0;
        $openingFeeDueNow = 0.0;
        if ($openBag && !$openBag->openingFeePaid && $openBag->openingFeeValue > 0) {
            $openingFeeDueLater = (float) $openBag->openingFeeValue;
        } elseif ($bagAction === 'open_bag' && !$openBag && $openingFee > 0) {
            $openingFeeDueNow = $openingFee;
        }

        return [
            'action' => $bagAction,
            'pessoa_id' => $personId,
            'open_bag' => $openBag,
            'bag_totals' => $bagTotals,
            'opening_fee' => $openingFee,
            'order_id' => $orderId,
            'order_items_count' => $orderItemsCount,
            'order_in_bag' => $orderInBag,
            'opening_fee_due_later' => $openingFeeDueLater,
            'opening_fee_due_now' => $openingFeeDueNow,
        ];
    }

    // MIGRADO: Removido parâmetro $repoLegado (buildBagItemsFromOrderData já migrado)
    private function handleBagActionFromOrder(
        int $orderId,
        string $action
    ): string {
        // MIGRADO: Usar OrderRepository local
        $orderData = $this->orders->findOrderWithDetails($orderId);
        if (!$orderData) {
            throw new \RuntimeException('Pedido não encontrado.');
        }
        if (!$orderData) {
            throw new \RuntimeException('Pedido não encontrado.');
        }

        $personId = $this->resolvePersonIdFromOrderRow($orderData);
        if ($personId <= 0) {
            throw new \RuntimeException('Cliente inválido para sacolinha.');
        }

        $openBag = $this->bags->findOpenByPerson($personId);
        if ($action === 'bag_open') {
            Auth::requirePermission('bags.create', $this->pdo);
            if ($openBag) {
                if ($this->isBagExpired($openBag)) {
                    throw new \RuntimeException($this->bagExpiredMessage($openBag) . ' Feche/cancele a sacolinha atual antes de abrir uma nova.');
                }
                throw new \RuntimeException('Cliente já possui sacolinha aberta #' . $openBag->id . '.');
            }
            $bag = $this->openBagFromOrder($orderData);
            // MIGRADO: Removido parâmetro $repoLegado
            $items = $this->buildBagItemsFromOrderData($orderData);
            $this->bags->addItems($bag->id, $items);
            $this->markOrderAsBagDeferred((int) ($orderData['id'] ?? 0), (int) $bag->id, $orderData);
            return 'Sacolinha aberta e itens adicionados.';
        }

        Auth::requirePermission('bags.edit', $this->pdo);
        if (!$openBag) {
            throw new \RuntimeException('Não há sacolinha aberta para esta cliente.');
        }
        $bagEligibilityError = $this->validateOpenBagForAdd($openBag);
        if ($bagEligibilityError !== null) {
            throw new \RuntimeException($bagEligibilityError);
        }
        if ($this->bags->hasOrderItems($openBag->id, $orderId)) {
            throw new \RuntimeException('Este pedido já está registrado na sacolinha.');
        }
        // MIGRADO: Removido parâmetro $repoLegado
        $items = $this->buildBagItemsFromOrderData($orderData);
        $this->bags->addItems($openBag->id, $items);
        $shippingTotal = isset($orderData['shipping_total']) ? (float) $orderData['shipping_total'] : null;
        $this->settleBagOpeningFeeIfNeeded($openBag, $shippingTotal, $this->resolveOrderDate($orderData));
        $this->markOrderAsBagDeferred((int) ($orderData['id'] ?? 0), (int) $openBag->id, $orderData);
        return 'Itens adicionados a sacolinha existente.';
    }

    private function openBagFromOrder(array $orderData): Bag
    {
        $bag = new Bag();
        $bag->personId = $this->resolvePersonIdFromOrderRow($orderData);
        $bag->customerName = $this->resolveBagCustomerNameFromOrder($orderData);
        $bag->customerEmail = $this->resolveBagCustomerEmailFromOrder($orderData);
        $bag->status = 'aberta';
        $bag->openedAt = $this->resolveOrderDate($orderData);
        $bag->expectedCloseAt = date('Y-m-d H:i:s', strtotime($bag->openedAt . ' +30 days'));
        $bag->openingFeeValue = $this->resolveOpeningFeeFromOrder($orderData);
        $shippingTotal = isset($orderData['shipping_total']) ? (float) $orderData['shipping_total'] : 0.0;
        $bag->openingFeePaid = $shippingTotal > 0;
        $bag->openingFeePaidAt = $bag->openingFeePaid ? $bag->openedAt : null;
        $this->bags->save($bag);
        return $bag;
    }

    private function resolveOpeningFeeFromOrder(array $orderData): float
    {
        if (!empty($orderData['shipping_total'])) {
            return (float) $orderData['shipping_total'];
        }

        $customField = isset($orderData['custom_field']) && is_array($orderData['custom_field'])
            ? $orderData['custom_field']
            : $this->decodeJson($orderData['custom_field'] ?? null);
        if (isset($customField['retrato_opening_fee_value'])) {
            $openingFeeValue = (float) $customField['retrato_opening_fee_value'];
            if ($openingFeeValue > 0) {
                return $openingFeeValue;
            }
        }

        return $this->resolveOpeningFee();
    }

    private function resolveBagCustomerNameFromOrder(array $orderData): ?string
    {
        $billing = $orderData['billing'] ?? [];
        $fullName = trim((string) ($billing['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }
        $fullName = trim((string) (($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')));
        if ($fullName !== '') {
            return $fullName;
        }
        $shipping = $orderData['shipping'] ?? [];
        $fullName = trim((string) ($shipping['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }
        $fullName = trim((string) (($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')));
        return $fullName !== '' ? $fullName : null;
    }

    private function resolveBagCustomerEmailFromOrder(array $orderData): ?string
    {
        $billing = $orderData['billing'] ?? [];
        $email = trim((string) ($billing['email'] ?? ''));
        return $email !== '' ? $email : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    // MIGRADO: Removido parâmetro $repoLegado (resolveBagItemDetails já migrado)
    private function buildBagItemsFromOrderData(array $orderData): array
    {
        $items = [];
        $orderId = isset($orderData['id']) ? (int) $orderData['id'] : null;
        $purchaseDate = $this->resolveOrderDate($orderData);
        $lineItems = $orderData['line_items'] ?? [];

        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productSku = (int) ($item['product_sku'] ?? 0);
            $variationId = (int) ($item['variation_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;
            $total = (float) ($item['total'] ?? 0);
            $unit = 0.0;
            if (isset($item['price'])) {
                $unit = (float) $item['price'];
            } elseif (!empty($item['subtotal']) && $qty > 0) {
                $unit = ((float) $item['subtotal']) / $qty;
            } elseif ($qty > 0) {
                $unit = $total / $qty;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $sku = $item['sku'] ?? null;
            $image = null;
            if (isset($item['image']) && is_array($item['image'])) {
                $image = $item['image']['src'] ?? null;
            } elseif (!empty($item['image_src'])) {
                $image = $item['image_src'];
            }

            $description = null;
            // MIGRADO: Removido verificação $repoLegado e parâmetro
            if ($productSku > 0) {
                $details = $this->resolveBagItemDetails($productSku, $variationId);
                if ($name === '') {
                    $name = $details['name'];
                }
                if (!$sku && $details['sku']) {
                    $sku = $details['sku'];
                }
                if (!$image && $details['image']) {
                    $image = $details['image'];
                }
                $description = $details['description'];
            }

            if ($name === '') {
                $name = 'Produto';
            }

            $items[] = [
                'order_id' => $orderId,
                'product_sku' => $productSku ?: null,
                'variation_id' => $variationId ?: null,
                'sku' => $sku ? (string) $sku : null,
                'name' => $name,
                'description' => $description,
                'image_url' => $image ? (string) $image : null,
                'quantity' => $qty,
                'unit_price' => $unit,
                'total_price' => $total,
                'purchased_at' => $purchaseDate,
            ];
        }

        return $items;
    }

    private function resolveOpeningFee(): float
    {
        return 35.0;
    }

    private function validateBagOwnership(Bag $bag, int $personId): ?string
    {
        $bagPersonId = (int) ($bag->personId ?? 0);
        if ($personId <= 0 || $bagPersonId <= 0 || $bagPersonId === $personId) {
            return null;
        }

        return 'Sacolinha #' . (int) ($bag->id ?? 0) . ' pertence a outra cliente.';
    }

    private function validateOpenBagForAdd(Bag $bag): ?string
    {
        $status = strtolower(trim((string) ($bag->status ?? '')));
        if ($status !== 'aberta') {
            return 'Sacolinha #' . (int) ($bag->id ?? 0) . ' não está aberta para receber itens.';
        }
        if ($this->isBagExpired($bag)) {
            return $this->bagExpiredMessage($bag);
        }
        return null;
    }

    private function isBagExpired(Bag $bag): bool
    {
        $expiresAt = $this->resolveBagExpiresAt($bag);
        if ($expiresAt === null) {
            return false;
        }
        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            return false;
        }
        return $timestamp < time();
    }

    private function resolveBagExpiresAt(Bag $bag): ?string
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

    private function bagExpiredMessage(Bag $bag): string
    {
        $bagId = (int) ($bag->id ?? 0);
        $expiresAt = $this->resolveBagExpiresAt($bag);
        if ($expiresAt === null) {
            return 'Sacolinha #' . $bagId . ' está vencida para novas inclusões.';
        }

        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            return 'Sacolinha #' . $bagId . ' está vencida para novas inclusões.';
        }

        return 'Sacolinha #' . $bagId . ' venceu em ' . date('d/m/Y H:i', $timestamp) . '.';
    }

    private function isOpeningFeeDeferred(array $input): bool
    {
        if (!isset($input['opening_fee_deferred'])) {
            return false;
        }
        $raw = $input['opening_fee_deferred'];
        return $raw === '1' || $raw === 'on';
    }

    private function settleBagOpeningFeeIfNeeded(?Bag $bag, ?float $shippingTotal, string $paidAt): void
    {
        if (!$bag || $bag->openingFeePaid) {
            return;
        }
        if ($shippingTotal === null || $shippingTotal <= 0) {
            return;
        }
        if ($bag->openingFeeValue <= 0) {
            $bag->openingFeeValue = $shippingTotal;
        }
        $bag->openingFeePaid = true;
        $bag->openingFeePaidAt = $paidAt;
        $this->bags->save($bag);
    }

    // MIGRADO: Removido parâmetro $repoLegado (buildBagItems já migrado)
    private function handleBagFlow(
        string $bagAction,
        Order $order,
        array $created,
        bool $deferOpeningFee = false
    ): void {
        if ($bagAction === 'none' || !$order->personId || !$this->bags->getPdo()) {
            return;
        }

        $bag = null;
        if ($bagAction === 'add_to_bag') {
            $bag = $this->bags->findOpenByPerson($order->personId);
            if (!$bag) {
                throw new \RuntimeException('Não há sacolinha aberta para esta cliente.');
            }
            $bagEligibilityError = $this->validateOpenBagForAdd($bag);
            if ($bagEligibilityError !== null) {
                throw new \RuntimeException($bagEligibilityError);
            }
        }

        if ($bagAction === 'open_bag') {
            $bag = new Bag();
            $bag->personId = (int) $order->personId;
            $bag->customerName = $this->resolveBagCustomerName($order);
            $bag->customerEmail = $this->resolveBagCustomerEmail($order);
            $bag->status = 'aberta';
            $bag->openedAt = $this->resolveOrderDate($created);
            $bag->expectedCloseAt = date('Y-m-d H:i:s', strtotime($bag->openedAt . ' +30 days'));
            $shippingTotal = $order->shippingInfo->total;
            $openingFeeValue = $shippingTotal;
            if ($openingFeeValue === null || $openingFeeValue <= 0) {
                $openingFeeValue = $this->resolveOpeningFeeFromOrder($created);
            }
            $bag->openingFeeValue = $openingFeeValue;
            $bag->openingFeePaid = !$deferOpeningFee && $shippingTotal !== null && $shippingTotal > 0;
            $bag->openingFeePaidAt = $bag->openingFeePaid ? $bag->openedAt : null;
            $this->bags->save($bag);
        }

        if (!$bag || !$bag->id) {
            return;
        }

        // MIGRADO: Removido parâmetro $repoLegado
        $items = $this->buildBagItems($order, $created);
        $this->bags->addItems($bag->id, $items);
        if ($bagAction === 'add_to_bag') {
            $this->settleBagOpeningFeeIfNeeded($bag, $order->shippingInfo->total, $this->resolveOrderDate($created));
        }
        $createdOrderId = isset($created['id']) ? (int) $created['id'] : 0;
        if ($createdOrderId > 0) {
            $this->markOrderAsBagDeferred($createdOrderId, (int) $bag->id, $created);
        }
    }

    private function buildCreateBagSuccessMessage(string $bagAction, int $personId): string
    {
        if ($bagAction === 'none' || $personId <= 0) {
            return '';
        }

        $openBag = $this->bags->findOpenByPerson($personId);
        if (!$openBag || !$openBag->id) {
            return '';
        }

        if ($bagAction === 'open_bag') {
            return ' Sacolinha #' . (int) $openBag->id . ' aberta com este pedido.';
        }
        if ($bagAction === 'add_to_bag') {
            return ' Pedido vinculado à sacolinha #' . (int) $openBag->id . '.';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $orderData
     */
    private function markOrderAsBagDeferred(int $orderId, int $bagId, array $orderData = []): void
    {
        if ($orderId <= 0 || $bagId <= 0) {
            return;
        }

        $order = !empty($orderData) && (int) ($orderData['id'] ?? 0) === $orderId
            ? $orderData
            : $this->orders->findOrderWithDetails($orderId);
        if (!$order) {
            return;
        }

        $shippingInfoBefore = isset($order['shipping_info']) && is_array($order['shipping_info'])
            ? $order['shipping_info']
            : [];
        $shippingInfo = $shippingInfoBefore;
        $shippingInfo['delivery_mode'] = 'shipment';
        $shippingInfo['shipment_kind'] = 'bag_deferred';
        $shippingInfo['bag_id'] = $bagId;
        if (empty($shippingInfo['status']) || $shippingInfo['status'] === 'not_required') {
            $shippingInfo['status'] = 'pending';
        }
        if (!isset($shippingInfo['timeline']) || !is_array($shippingInfo['timeline'])) {
            $shippingInfo['timeline'] = [];
        }
        $shippingInfo['timeline'][] = [
            'type' => 'bag_linked',
            'label' => 'Pedido vinculado à sacolinha #' . $bagId,
            'at' => date('Y-m-d H:i:s'),
        ];
        $this->appendDeliveryTimelineEvents($shippingInfoBefore, $shippingInfo, (string) $shippingInfo['status']);

        $candidateOrder = $order;
        $candidateOrder['delivery_mode'] = 'shipment';
        $candidateOrder['shipment_kind'] = 'bag_deferred';
        $candidateOrder['bag_id'] = $bagId;
        $candidateOrder['shipping_info'] = $shippingInfo;
        $candidateOrder['delivery_status'] = (string) ($shippingInfo['status'] ?? 'pending');
        $candidateOrder['fulfillment_status'] = (string) ($shippingInfo['status'] ?? 'pending');

        $snapshot = $this->computeLifecycleSnapshotFromOrder($candidateOrder);
        $status = OrderService::normalizeOrderStatus((string) ($snapshot['status'] ?? ($order['status'] ?? 'open')));
        $payload = $this->lifecycle->toPersistencePayload($snapshot, $candidateOrder);
        $payload['shipping_info'] = $shippingInfo;
        $payload['delivery_mode'] = 'shipment';
        $payload['shipment_kind'] = 'bag_deferred';
        $payload['bag_id'] = $bagId;
        $this->orders->updateStatusComplete($orderId, $status, null, $payload);
    }

    private function resolveBagCustomerName(Order $order): ?string
    {
        $name = trim((string) ($order->billing['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $name = trim((string) ($order->shipping['full_name'] ?? ''));
        return $name !== '' ? $name : null;
    }

    private function resolveBagCustomerEmail(Order $order): ?string
    {
        $email = trim((string) ($order->billing['email'] ?? ''));
        return $email !== '' ? $email : null;
    }

    private function resolveOrderDate(array $created): string
    {
        $raw = $created['date_created'] ?? $created['date_created_gmt'] ?? null;
        if ($raw) {
            $timestamp = strtotime((string) $raw);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * @return array<int, array<string, mixed>>
     * MIGRADO: Removido parâmetro $repoLegado (resolveBagItemDetails já migrado)
     */
    private function buildBagItems(Order $order, array $created): array
    {
        $items = [];
        $orderId = isset($created['id']) ? (int) $created['id'] : null;
        $purchaseDate = $this->resolveOrderDate($created);

        foreach ($order->items as $item) {
            $productSku = $item->productSku;
            $variationId = $item->variationId;
            // MIGRADO: Removido parâmetro $repoLegado
            $details = $this->resolveBagItemDetails($productSku, $variationId);
            $qty = max(1, (int) $item->quantity);
            $unit = $item->price ?? 0.0;
            $items[] = [
                'order_id' => $orderId,
                'product_sku' => $productSku ?: null,
                'variation_id' => $variationId ?: null,
                'sku' => $details['sku'],
                'name' => $details['name'],
                'description' => $details['description'],
                'image_url' => $details['image'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'total_price' => $unit * $qty,
                'purchased_at' => $purchaseDate,
            ];
        }

        return $items;
    }

    /**
     * @return array{name: string, sku: ?string, image: ?string, description: ?string}
     * MIGRADO: Removido parâmetro $repoLegado, usando ProductRepository local
     */
    private function resolveBagItemDetails(int $productId, int $variationId): array
    {
        $name = '';
        $sku = null;
        $image = null;
        $description = null;
        // MIGRADO: Usar ProductRepository local
        $primaryId = $variationId > 0 ? $variationId : $productId;
        $primary = $primaryId > 0 ? $this->products->findById($primaryId) : null;
        
        if ($primary) {
            $name = trim((string) ($primary['nome'] ?? ''));
            $sku = $primary['sku'] ?? null;
            $image = $primary['image_src'] ?? null;
            $description = $this->normalizeBagDescription($primary['descricao'] ?? null);
        }

        if ($name === '' && $productId > 0 && $variationId > 0) {
            // Se é variação, tentar buscar o produto pai
            $product = $this->products->findById($productId);
            if ($product) {
                $name = trim((string) ($product['nome'] ?? ''));
                if (!$sku) {
                    $sku = $product['sku'] ?? null;
                }
                if (!$image) {
                    $image = $product['image_src'] ?? null;
                }
                if ($description === null) {
                    $description = $this->normalizeBagDescription($product['descricao'] ?? null);
                }
            }
        }

        if ($name === '') {
            $name = 'Produto';
        }

        return [
            'name' => $name,
            'sku' => $sku ? (string) $sku : null,
            'image' => $image ? (string) $image : null,
            'description' => $description,
        ];
    }

    private function normalizeBagDescription($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > 180) {
            $text = substr($text, 0, 177) . '...';
        }
        return $text;
    }

    private function buildSummary(array $order): array
    {
        return [
            'status' => (string) ($order['status'] ?? ''),
            'currency' => (string) ($order['currency'] ?? 'BRL'),
            'total' => (string) ($order['total'] ?? ''),
            'discount_total' => (string) ($order['discount_total'] ?? ''),
            'shipping_total' => (string) ($order['shipping_total'] ?? ''),
            'total_tax' => (string) ($order['total_tax'] ?? ''),
            'date_created' => (string) ($order['date_created'] ?? ''),
            'date_paid' => (string) ($order['date_paid'] ?? ''),
            'date_completed' => (string) ($order['date_completed'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOrdersListRows(
        array $rows,
        array $returnSummaryByOrder = [],
        array $bagFeeByOrder = []
    ): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $orderId = (int) ($row['order_id'] ?? $row['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $billing = isset($row['billing']) && is_array($row['billing']) ? $row['billing'] : [];
            $shipping = isset($row['shipping']) && is_array($row['shipping']) ? $row['shipping'] : [];
            $statusRaw = $row['status_raw'] ?? ($row['status'] ?? null);
            $paymentStatusRaw = $row['payment_status_raw'] ?? ($row['payment_status'] ?? null);
            $fulfillmentStatusRaw = $row['fulfillment_status_raw'] ?? ($row['fulfillment_status'] ?? null);
            $returnSummary = $returnSummaryByOrder[$orderId] ?? [];
            $returnedQty = (int) ($returnSummary['returned_qty'] ?? 0);
            $totalQty = (int) ($row['items_count'] ?? 0);
            $bagFee = $bagFeeByOrder[$orderId] ?? [];
            $openingFeeDueLater = 0.0;
            if (!empty($bagFee) && empty($bagFee['opening_fee_paid'])) {
                $openingFeeDueLater = max(0.0, (float) ($bagFee['opening_fee_value'] ?? 0));
            }
            $snapshotBase = $row;
            $snapshotBase['status'] = $statusRaw;
            $snapshotBase['payment_status'] = $paymentStatusRaw;
            $snapshotBase['fulfillment_status'] = $fulfillmentStatusRaw;
            $snapshot = $this->computeLifecycleSnapshotFromOrder($snapshotBase, [
                'return_summary' => [
                    'returned_qty' => $returnedQty,
                    'total_qty' => $totalQty,
                ],
                'opening_fee_due_later' => $openingFeeDueLater,
            ]);
            $customerDisplayName = trim((string) ($billing['full_name'] ?? ($row['billing_name'] ?? '')));
            if ($customerDisplayName === '') {
                $customerDisplayName = trim((string) ($shipping['full_name'] ?? ''));
            }

            $normalized[] = array_merge($row, [
                'order_id' => $orderId,
                'status_raw' => $statusRaw,
                'payment_status_raw' => $paymentStatusRaw,
                'fulfillment_status_raw' => $fulfillmentStatusRaw,
                'status' => $snapshot['status'],
                'payment_status' => $snapshot['payment_status'],
                'fulfillment_status' => $snapshot['fulfillment_status'],
                'lifecycle_snapshot' => $snapshot,
                'pending_codes' => $snapshot['pending_codes'] ?? [],
                'blocking_pending_codes' => $snapshot['blocking_pending_codes'] ?? [],
                'pending_count' => (int) ($snapshot['pending_count'] ?? 0),
                'blocking_pending_count' => (int) ($snapshot['blocking_pending_count'] ?? 0),
                'due_now' => (float) ($snapshot['totals']['due_now'] ?? 0),
                'paid_total' => (float) ($snapshot['totals']['net_paid'] ?? 0),
                'balance_due_now' => (float) ($snapshot['totals']['balance_due_now'] ?? 0),
                'due_later' => (float) ($snapshot['totals']['due_later'] ?? 0),
                'order_status_label' => (string) ($snapshot['labels']['order_status'] ?? $snapshot['status']),
                'payment_status_label' => (string) ($snapshot['labels']['payment_status'] ?? $snapshot['payment_status']),
                'fulfillment_status_label' => (string) ($snapshot['labels']['fulfillment_status'] ?? $snapshot['fulfillment_status']),
                'total_sales' => $row['total_sales'] ?? ($row['total'] ?? null),
                'date_created' => $row['date_created'] ?? ($row['ordered_at'] ?? ($row['created_at'] ?? null)),
                'billing_first_name' => (string) ($row['billing_first_name'] ?? ($billing['first_name'] ?? '')),
                'billing_last_name' => (string) ($row['billing_last_name'] ?? ($billing['last_name'] ?? '')),
                'billing_full_name' => (string) ($billing['full_name'] ?? ($row['billing_name'] ?? '')),
                'shipping_first_name' => (string) ($row['shipping_first_name'] ?? ($shipping['first_name'] ?? '')),
                'shipping_last_name' => (string) ($row['shipping_last_name'] ?? ($shipping['last_name'] ?? '')),
                'shipping_full_name' => (string) ($shipping['full_name'] ?? ''),
                'shipping_email' => (string) ($row['shipping_email'] ?? ($shipping['email'] ?? '')),
                'shipping_state' => (string) ($row['shipping_state'] ?? ($shipping['state'] ?? '')),
                'customer_display_name' => (string) ($row['customer_display_name'] ?? $customerDisplayName),
                'customer_email' => (string) ($row['customer_email'] ?? ($row['billing_email'] ?? ($shipping['email'] ?? ''))),
                'items_count' => (int) ($row['items_count'] ?? 0),
            ]);
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function normalizeOrdersListItems(array $items): array
    {
        $byOrder = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $orderId = (int) ($item['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $quantity = (int) ($item['product_qty'] ?? ($item['quantity'] ?? 0));
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $byOrder[$orderId][] = array_merge($item, [
                'product_qty' => $quantity,
                'product_net_revenue' => (float) ($item['product_net_revenue'] ?? ($item['total'] ?? 0)),
                'product_name' => (string) ($item['product_name'] ?? ($item['name'] ?? '')),
                'product_sku' => $item['product_sku'] ?? ($item['sku'] ?? ''),
            ]);
        }

        return $byOrder;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<int, array<string, mixed>>> $orderItems
     * @return array<int, array<string, mixed>>
     */
    private function applyOrdersItemsCount(array $rows, array $orderItems): array
    {
        if (empty($rows) || empty($orderItems)) {
            return $rows;
        }

        $countByOrder = [];
        foreach ($orderItems as $orderId => $items) {
            $countByOrder[(int) $orderId] = 0;
            foreach ($items as $item) {
                $quantity = (int) ($item['product_qty'] ?? ($item['quantity'] ?? 0));
                $countByOrder[(int) $orderId] += $quantity > 0 ? $quantity : 1;
            }
        }

        foreach ($rows as &$row) {
            $orderId = (int) ($row['order_id'] ?? $row['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            if (!isset($row['items_count']) || (int) $row['items_count'] <= 0) {
                $row['items_count'] = $countByOrder[$orderId] ?? 0;
            }
        }
        unset($row);

        return $rows;
    }

    // MIGRADO: Removido parâmetro $repoLegado, usando ProductRepository local
    private function resolveItemStocks(array $items): array
    {
        $stocks = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productSku = (int) ($item['product_sku'] ?? 0);
            if ($productSku <= 0 || isset($stocks[$productSku])) {
                continue;
            }
            
            // MIGRADO: Usar ProductRepository local
            $product = $this->products->findById($productSku);
            if (!$product) {
                continue;
            }
            
            $availableQty = (int) ($product['quantity'] ?? 0);
            $availabilityStatus = (
                $availableQty > 0
                && $this->isOrderSaleableProductStatus((string) ($product['status'] ?? ''))
            ) ? 'instock' : 'outofstock';

            $stocks[$productSku] = [
                'sku' => $product['sku'] ?? '',
                'quantity' => $availableQty,
                'availability_status' => $availabilityStatus,
                'image_src' => $product['image_src'] ?? '',
            ];
        }

        return $stocks;
    }

    // Método listAllProductsForOrder() REMOVIDO - usar listAllProductsForOrderLocal() local

    /**
     * @param array<int, \App\Models\OrderItem> $items
     * @return array<int, string>
     * MIGRADO: Removido parâmetro $repoLegado, usando ProductRepository local
     */
    private function validateStock(array $items, array $previousQuantities = []): array
    {
        $errors = [];
        $grouped = [];
        foreach ($items as $index => $item) {
            $productSku = $item->productSku;
            if ($productSku <= 0) {
                continue;
            }
            $variationId = $item->variationId > 0 ? $item->variationId : 0;
            $key = $variationId > 0 ? 'v:' . $variationId : 'p:' . $productSku;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_sku' => $productSku,
                    'variation_id' => $variationId,
                    'quantity' => 0,
                    'index' => $index,
                    'count' => 0,
                ];
            }
            $grouped[$key]['quantity'] += max(0, (int) $item->quantity);
            $grouped[$key]['count'] += 1;
        }

        foreach ($grouped as $group) {
            $key = $group['variation_id'] > 0 ? 'v:' . $group['variation_id'] : 'p:' . $group['product_sku'];
            $needed = max(0, $group['quantity'] - ($previousQuantities[$key] ?? 0));
            if ($needed === 0) {
                continue;
            }
            
            // MIGRADO: Usar ProductRepository local
            $stockId = $group['variation_id'] > 0 ? $group['variation_id'] : $group['product_sku'];
            $product = $this->products->findById($stockId);
            
            if (!$product) {
                $errors[] = 'Item #' . ($group['index'] + 1) . ': produto não encontrado.';
                continue;
            }
            
            $stockQty = (int) ($product['quantity'] ?? 0);
            $status = strtolower((string) ($product['status'] ?? 'draft'));

            if ($stockQty <= 0) {
                $errors[] = 'Item #' . ($group['index'] + 1) . ': produto sem disponibilidade.';
                continue;
            }

            if (!$this->isOrderSaleableProductStatus($status)) {
                $errors[] = 'Item #' . ($group['index'] + 1) . ': produto indisponível para venda.';
                continue;
            }
            
            if ($needed > $stockQty) {
                $label = $group['count'] > 1 ? 'soma das quantidades' : 'quantidade';
                $errors[] = 'Item #' . ($group['index'] + 1) . ': ' . $label . ' acima da disponibilidade (' . $stockQty . ').';
            }
        }

        return $errors;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function restockOrderItems(int $orderId, string $reason): array
    {
        $messages = [];
        $errors = [];
        if ($orderId <= 0) {
            return [$messages, $errors];
        }

        $items = $this->orders->getOrderItems($orderId);
        if (empty($items)) {
            return [$messages, $errors];
        }

        $bySku = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = (int) ($item['product_sku'] ?? 0);
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            if ($sku <= 0 || $qty <= 0) {
                continue;
            }
            $bySku[$sku] = ($bySku[$sku] ?? 0) + $qty;
        }

        foreach ($bySku as $sku => $qty) {
            $product = $this->products->find((int) $sku);
            if (!$product) {
                $errors[] = 'SKU ' . $sku . ' não encontrado para estorno de disponibilidade.';
                continue;
            }
            $ok = $this->products->incrementQuantity(
                (int) $sku,
                $qty,
                'ajuste',
                $reason . ' do pedido #' . $orderId
            );
            if (!$ok) {
                $errors[] = 'Falha ao estornar disponibilidade do SKU ' . $sku . '.';
                continue;
            }
            $messages[] = 'SKU ' . $sku . ': +' . $qty . ' unidade(s) estornada(s).';
        }

        return [$messages, $errors];
    }

    private function isTerminalStockStatus(?string $status): bool
    {
        $value = OrderService::normalizeOrderStatus((string) $status);
        return in_array($value, ['cancelled', 'refunded', 'failed', 'trash', 'deleted'], true);
    }

    private function isOrderSaleableProductStatus(?string $status): bool
    {
        $value = strtolower(trim((string) $status));
        return in_array($value, ['disponivel', 'draft'], true);
    }

    private function emptyReturnForm(int $orderId, array $customer): array
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
     * @return array{id: int|null, name: string, email: string}
     */
    private function extractReturnCustomer(array $order): array
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
            'id' => $order['pessoa_id'] ?? null,
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderItemsForReturn(array $order): array
    {
        $items = [];
        $lines = $order['line_items'] ?? [];
        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            if ($lineId <= 0) {
                continue;
            }
            $qty = (int) ($line['quantity'] ?? 0);
            $unitPrice = $this->unitPriceFromOrderLine($line);
            $productSku = (int) ($line['product_sku'] ?? 0);
            $items[$lineId] = [
                'line_id' => $lineId,
                'product_sku' => $productSku,
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
    private function buildReturnAvailableMap(array $orderItems, array $returned, array $currentItems): array
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

    private function unitPriceFromOrderLine(array $line): float
    {
        $qty = (int) ($line['quantity'] ?? 1);
        $total = isset($line['total']) ? (float) $line['total'] : 0.0;
        if ($qty <= 0) {
            $qty = 1;
        }
        return $qty > 0 ? ($total / $qty) : 0.0;
    }

    private function shouldRestockReturn(OrderReturn $return): bool
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

    /**
     * @param array<int, OrderReturnItem> $items
     * @return array<int, string>
     */
    private function restockReturnItems(array $items): array
    {
        $messages = [];
        foreach ($items as $item) {
            $productSku = (int) ($item->productSku ?? 0);
            $quantity = max(0, (int) ($item->quantity ?? 0));
            if ($productSku <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $this->products->find($productSku);
            if (!$product) {
                $messages[] = 'Produto SKU ' . $productSku . ' não encontrado para reposição de disponibilidade.';
                continue;
            }

            $ok = $this->products->incrementQuantity(
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
     * Undo restock for return items (subtract quantities).
     * @param array<int, OrderReturnItem> $items
     * @return array<int, string>
     */
    private function unRestockReturnItems(array $items): array
    {
        $messages = [];
        foreach ($items as $item) {
            $productSku = (int) ($item->productSku ?? 0);
            $quantity = max(0, (int) ($item->quantity ?? 0));
            if ($productSku <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $this->products->find($productSku);
            if (!$product) {
                $messages[] = 'Produto SKU ' . $productSku . ' não encontrado para desfazer reposição de disponibilidade.';
                continue;
            }

            $currentQty = (int) ($product['quantity'] ?? 0);
            if ($currentQty < $quantity) {
                $messages[] = 'SKU ' . $productSku . ': quantidade atual insuficiente para desfazer reposição.';
                continue;
            }

            $ok = $this->products->decrementQuantity(
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
     * Undo a return inside the order context: revert restock, revert voucher if voucher refund, update DB and history.
     * Returns [messages, errors]
     * @return array{0: array<int,string>,1: array<int,string>}
     */
    private function undoOrderReturn(int $returnId): array
    {
        $errors = [];
        $messages = [];
        $returnRow = $this->orderReturns->find($returnId);
        if (!$returnRow) {
            $errors[] = 'Devolução não encontrada.';
            return [$messages, $errors];
        }

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

        if (!empty($returnRow['restocked_at'])) {
            Auth::requirePermission('order_returns.restock', $this->pdo);
            $restockMsgs = $this->unRestockReturnItems($items);
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
            $messages = array_merge($messages, $restockMsgs);
            $this->orderReturns->clearRestocked($returnId);
            $messages[] = 'Marca de reposição removida.';
            $returnPessoaId = $this->resolvePersonIdFromReturnRow($returnRow);
            if ($this->history && $returnPessoaId > 0) {
                $this->logReturnHistory($returnPessoaId, 'return_unrestock', ['return_id' => $returnId, 'notes' => $restockMsgs]);
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

        if ($voucherId && $refundStatus === 'done' && $refundMethod === 'voucher') {
            Auth::requirePermission('order_returns.refund', $this->pdo);
            try {
                $deletedBy = '';
                $currentUser = Auth::user();
                if (!empty($currentUser['email'])) {
                    $deletedBy = $currentUser['email'];
                } elseif (!empty($currentUser['name'])) {
                    $deletedBy = $currentUser['name'];
                }
                $this->voucherAccounts->trash($voucherId, date('Y-m-d H:i:s'), $deletedBy ?: null);
                $messages[] = 'Crédito/cupom (ID ' . $voucherId . ') enviado para a lixeira.';
                $returnPessoaId = $this->resolvePersonIdFromReturnRow($returnRow);
                if ($this->history && $returnPessoaId > 0) {
                    $this->logReturnHistory($returnPessoaId, 'return_refund_revert', ['return_id' => $returnId, 'voucher_id' => $voucherId]);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao remover crédito/cupom: ' . $e->getMessage();
                return [$messages, $errors];
            }
            $this->orderReturns->updateRefundStatus($returnId, 'pending', null);
        }

        $this->orderReturns->updateStatus($returnId, 'cancelled');
        $returnPessoaId = $this->resolvePersonIdFromReturnRow($returnRow);
        if ($this->history && $returnPessoaId > 0) {
            $this->logReturnHistory($returnPessoaId, 'return_cancel', ['return_id' => $returnId, 'notes' => 'Devolução cancelada via pedido']);
        }

        return [$messages, $errors];
    }

    /**
     * @param array<int, OrderReturnItem> $items
     * @return array<int, string>
     */
    private function historyItemsFromReturn(array $items): array
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

    private function logReturnHistory(int $personId, string $action, array $payload): void
    {
        if ($personId <= 0 || !$this->history) {
            return;
        }
        $this->history->log($personId, $action, $payload);
    }

    /**
     * @return array{0: bool, 1: string}
     * MIGRADO: Removido parâmetro $repoLegado, usando CustomerRepository local
     */
    private function createVoucherFromReturn(
        OrderReturn $return,
        ?array $customer,
        int $returnId
    ): array
    {
        $personId = isset($customer['id']) ? (int) $customer['id'] : 0;
        if ($personId <= 0) {
            return [false, 'Crédito não criado: pedido sem cliente identificado.'];
        }

        // MIGRADO: Usar CustomerRepository local
        $resolvedCustomer = $this->customers->findAsArray($personId);
        
        if ($resolvedCustomer) {
            $resolvedPersonId = $this->resolvePersonIdFromCustomerRow($resolvedCustomer);
            if ($resolvedPersonId > 0) {
                $personId = $resolvedPersonId;
            }
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
            $this->voucherAccounts->save($account);
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

    /**
     * Lista produtos do banco local (tabela produtos) para usar no formulário de pedido
     */
    private function listAllProductsForOrderLocal(array $filters): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT
            p.sku AS ID,
            p.sku AS id,
            CAST(p.sku AS CHAR) AS sku,
            p.name,
            p.name AS post_title,
            p.price,
            p.price AS regular_price,
            p.quantity AS quantity,
            CASE
                WHEN p.quantity > 0 AND p.status IN ('disponivel', 'draft') THEN 'instock'
                ELSE 'outofstock'
            END AS availability_status,
            p.status,
            'simple' AS type,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.image_url')),
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.thumbnail_url'))
            ) AS image_src
        FROM products p
        WHERE p.status NOT IN ('archived', 'baixado')";

        if (!empty($filters['stock_positive'])) {
            $sql .= " AND p.quantity > 0 AND p.status IN ('disponivel', 'draft')";
        }

        $sql .= " ORDER BY p.updated_at DESC";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        // Adiciona array vazio de variations para compatibilidade
        foreach ($rows as &$row) {
            $row['variations'] = [];
            $row['image_url'] = (string) ($row['image_src'] ?? '');
        }
        unset($row);

        return $rows;
    }

    /**
     * Lista clientes do banco local (view vw_clientes_compat baseada em pessoas)
     */
    private function listCustomersLocal(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $viewSql = "SELECT
            id as user_id,
            id as pessoa_id,
            id,
            full_name,
            full_name as name,
            email,
            phone,
            cpf_cnpj,
            street as shipping_address_1,
            street2 as shipping_address_2,
            number as shipping_number,
            neighborhood as shipping_neighborhood,
            city as shipping_city,
            state as shipping_state,
            zip as shipping_postcode,
            country as shipping_country,
            full_name as shipping_full_name,
            email as shipping_email,
            phone as shipping_phone,
            status
        FROM vw_clientes_compat
        WHERE status = 'ativo'
        ORDER BY updated_at DESC";

        try {
            $stmt = $this->pdo->query($viewSql);
            if ($stmt) {
                return $stmt->fetchAll();
            }
        } catch (\Throwable $e) {
            // fallback para bases sem view de compatibilidade
        }

        $fallbackSql = "SELECT
            p.id as user_id,
            p.id as pessoa_id,
            p.id,
            p.full_name,
            p.full_name as name,
            p.email,
            p.phone,
            p.cpf_cnpj,
            p.street as shipping_address_1,
            p.street2 as shipping_address_2,
            p.number as shipping_number,
            p.neighborhood as shipping_neighborhood,
            p.city as shipping_city,
            p.state as shipping_state,
            p.zip as shipping_postcode,
            p.country as shipping_country,
            p.full_name as shipping_full_name,
            p.email as shipping_email,
            p.phone as shipping_phone,
            p.status
        FROM pessoas p
        LEFT JOIN pessoas_papeis pr ON pr.pessoa_id = p.id AND pr.role = 'cliente'
        WHERE p.status = 'ativo'
          AND (
            pr.pessoa_id IS NOT NULL
            OR JSON_EXTRACT(p.metadata, '$.is_cliente') = TRUE
            OR JSON_CONTAINS(COALESCE(p.metadata, JSON_OBJECT()), '\"cliente\"', '$.tipos')
          )
        ORDER BY p.updated_at DESC";

        $stmt = $this->pdo->query($fallbackSql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Resolve disponibilidade de itens usando banco local
     */
    private function resolveItemStocksLocal(array $items): array
    {
        $stocks = [];
        if (!$this->pdo) {
            return $stocks;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productSku = (int) ($item['product_sku'] ?? 0);
            if ($productSku <= 0 || isset($stocks[$productSku])) {
                continue;
            }
            
            $product = $this->products->find($productSku);
            if (!$product) {
                continue;
            }

            $availableQty = isset($product->quantity) ? (int) $product->quantity : null;
            $availabilityStatus = (
                ($availableQty ?? 0) > 0
                && $this->isOrderSaleableProductStatus((string) ($product->status ?? ''))
            ) ? 'instock' : 'outofstock';

            $stocks[$productSku] = [
                'sku' => $product->sku ?? '',
                'quantity' => $availableQty,
                'availability_status' => $availabilityStatus,
                'image_src' => (string) ($product->images[0]['src'] ?? ''),
            ];
        }

        return $stocks;
    }
}
