<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BankAccountRepository;
use App\Repositories\ConsignmentPayoutItemRepository;
use App\Repositories\ConsignmentPayoutRepository;
use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentReportViewRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\PersonRepository;
use App\Repositories\ProductRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Services\ConsignmentIntegrityService;
use App\Services\ConsignmentPayoutService;
use App\Services\ConsignmentPeriodLockService;
use App\Services\ConsignmentProductStateService;
use App\Services\ConsignmentReportService;
use App\Services\ConsignmentSalesService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use PDO;
use PDOException;

/**
 * ConsignmentModuleController
 *
 * Controller central da aba "Consignação".
 * Métodos: dashboard, products, sales, payoutForm, payoutList, payoutShow,
 *          payoutConfirm, payoutCancel, payoutReceipt, reportVendor, reportInternal,
 *          inconsistencies, legacyReconciliation, reindex, bulkAction, periodLock, periodUnlock.
 */
class ConsignmentModuleController
{
    private ?PDO $pdo;
    private ?string $connectionError;

    // Repositories
    private ConsignmentProductRegistryRepository $registry;
    private ConsignmentSaleRepository $sales;
    private ConsignmentPayoutRepository $payouts;
    private ConsignmentPayoutItemRepository $payoutItems;
    private ProductRepository $products;
    private PersonRepository $persons;
    private VoucherAccountRepository $vouchers;
    private VoucherCreditEntryRepository $ledger;
    private BankAccountRepository $bankAccounts;

    // Services
    private ConsignmentPayoutService $payoutService;
    private ConsignmentProductStateService $stateService;
    private ConsignmentSalesService $salesService;
    private ConsignmentIntegrityService $integrityService;
    private ConsignmentPeriodLockService $periodLockService;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;

        $this->registry = new ConsignmentProductRegistryRepository($pdo);
        $this->sales = new ConsignmentSaleRepository($pdo);
        $this->payouts = new ConsignmentPayoutRepository($pdo);
        $this->payoutItems = new ConsignmentPayoutItemRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->persons = new PersonRepository($pdo);
        $this->vouchers = new VoucherAccountRepository($pdo);
        $this->ledger = new VoucherCreditEntryRepository($pdo);
        $this->bankAccounts = new BankAccountRepository($pdo);

        $this->payoutService = new ConsignmentPayoutService($pdo);
        $this->stateService = new ConsignmentProductStateService($pdo);
        $this->salesService = new ConsignmentSalesService($pdo);
        $this->integrityService = new ConsignmentIntegrityService($pdo);
        $this->periodLockService = new ConsignmentPeriodLockService($pdo);
    }

    /* =====================================================================
     * 1. DASHBOARD
     * ===================================================================== */

    public function dashboard(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $flashError = trim((string) $_SESSION['flash_error']);
            if ($flashError !== '') {
                $errors[] = $flashError;
            }
            unset($_SESSION['flash_error']);
        }

        // Summary cards
        $summary = $this->registry->dashboardSummary();
        $soldSummary = $this->sales->dashboardSoldSummary();
        foreach (['vendido_pendente', 'vendido_pago'] as $soldStatus) {
            $summary[$soldStatus] = [
                'count' => (int) ($soldSummary[$soldStatus]['count'] ?? ($summary[$soldStatus]['count'] ?? 0)),
                'value' => (float) ($soldSummary[$soldStatus]['value'] ?? ($summary[$soldStatus]['value'] ?? 0)),
                'commission' => (float) ($soldSummary[$soldStatus]['commission'] ?? 0),
            ];
        }
        $agingDist = $this->registry->agingDistribution();
        $agingBySupplier = $this->registry->agingBySupplier(10);
        $pendingBySupplier = $this->sales->pendingSummaryBySupplier(10);

        // Alerts: items paid then returned in last 30 days
        $recentOwnItemsCount = $this->countRecentOwnItems(30);
        // Alerts: legacy payouts without payout_id
        $legacyUnlinked = $this->countLegacyUnlinkedPayouts();
        // Period locks
        $periodLocks = $this->periodLockService->listAll();

        View::render('consignment_module/dashboard', [
            'summary'           => $summary,
            'agingDist'         => $agingDist,
            'agingBySupplier'   => $agingBySupplier,
            'pendingBySupplier' => $pendingBySupplier,
            'recentOwnItemsCount' => $recentOwnItemsCount,
            'legacyUnlinked'    => $legacyUnlinked,
            'periodLocks'       => $periodLocks,
            'errors'            => $errors,
        ], ['title' => 'Consignação — Painel']);
    }

    /* =====================================================================
     * 2. PRODUCTS (Produtos Consignados)
     * ===================================================================== */

    public function products(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        // Flash messages
        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $err = trim((string) $_SESSION['flash_error']);
            if ($err !== '') $errors[] = $err;
            unset($_SESSION['flash_error']);
        }

        // Handle POST bulk actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
            $this->handleBulkAction($errors, $success);
            // Redirect PRG
            $_SESSION['flash_success'] = $success;
            if ($errors) $_SESSION['flash_error'] = implode(' | ', $errors);
            header('Location: consignacao-produtos.php?' . http_build_query($_GET));
            exit;
        }

        // Filters
        $filters = $this->buildProductFilters();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->sanitizePerPage((int) ($_GET['per_page'] ?? 50));

        $result = $this->registry->paginate($filters, $page, $perPage);
        $rows = $result['items'] ?? $result['rows'] ?? [];
        $total = $result['total'] ?? 0;

        // Supplier dropdown
        $suppliers = $this->loadConsignmentSuppliers();

        // Status options
        $statusOptions = self::consignmentStatusLabels();

        View::render('consignment_module/products', [
            'rows'          => $rows,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'filters'       => $filters,
            'suppliers'     => $suppliers,
            'statusOptions' => $statusOptions,
            'errors'        => $errors,
            'success'       => $success,
        ], ['title' => 'Consignação — Produtos Consignados']);
    }

    /* =====================================================================
     * 3. SALES (Vendas Consignadas)
     * ===================================================================== */

    public function sales(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $filters = $this->buildSaleFilters();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->sanitizePerPage((int) ($_GET['per_page'] ?? 50));

        $result = $this->sales->paginate($filters, $page, $perPage);
        $rows = $result['items'] ?? $result['rows'] ?? [];
        $total = $result['total'] ?? 0;

        $suppliers = $this->loadConsignmentSuppliers();

        View::render('consignment_module/sales', [
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'filters'   => $filters,
            'suppliers' => $suppliers,
            'errors'    => $errors,
        ], ['title' => 'Consignação — Vendas Consignadas']);
    }

    /* =====================================================================
     * 4. PAYOUT — FORM (Novo / Editar Rascunho)
     * ===================================================================== */

    public function payoutForm(string $formRoute = 'consignacao-pagamento-cadastro.php', bool $isDedicatedSupplierPage = false): void
    {
        $errors = [];
        $success = '';
        $formRoute = trim($formRoute) !== '' ? trim($formRoute) : 'consignacao-pagamento-cadastro.php';
        $confirmedEditFlagRaw = strtolower(trim((string) ($_GET['edit_confirmed'] ?? $_POST['allow_confirmed_edit'] ?? '')));
        $allowConfirmedEdit = in_array($confirmedEditFlagRaw, ['1', 'true', 'yes', 'on'], true);
        $isEditingConfirmed = false;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $payoutId = (int) ($_GET['id'] ?? 0);
        $payout = null;
        $existingItems = [];

        if ($payoutId > 0) {
            $payout = $this->payouts->find($payoutId);
            if (!$payout) {
                $errors[] = 'Pagamento não encontrado.';
            } else {
                $payoutStatus = (string) ($payout['status'] ?? '');
                if ($payoutStatus === 'rascunho') {
                    $existingItems = $this->payoutItems->listByPayout($payoutId);
                } elseif ($payoutStatus === 'confirmado' && $allowConfirmedEdit) {
                    Auth::requirePermission('consignment_module.confirm_payout', $this->pdo);
                    $isEditingConfirmed = true;
                    $existingItems = $this->payoutItems->listByPayout($payoutId);
                } else {
                    $errors[] = $payoutStatus === 'confirmado'
                        ? 'Para editar PIX confirmado, use a ação "Editar PIX" nos detalhes do pagamento.'
                        : 'Somente pagamentos em rascunho podem ser editados.';
                }
            }
        }

        $postAction = trim((string) ($_POST['submit_action'] ?? ''));

        // Handle POST: create draft, update or save supplier PIX
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
            $this->handlePayoutFormPost($payout, $errors, $success);
            if ($success && $postAction !== 'save_supplier_pix') {
                $_SESSION['flash_success'] = $success;
                if (($payout && (int) ($payout['id'] ?? 0) > 0 && (($payout['status'] ?? '') === 'confirmado' || $postAction === 'edit_confirmed'))) {
                    header('Location: consignacao-pagamento-list.php?id=' . (int) $payout['id'] . '&action=show');
                } else {
                    header('Location: consignacao-pagamento-list.php');
                }
                exit;
            }
        }

        // Pre-selected supplier (from GET or existing payout)
        $selectedSupplier = (int) ($_GET['supplier_pessoa_id'] ?? $_POST['supplier_pessoa_id'] ?? ($payout['supplier_pessoa_id'] ?? 0));
        $soldFrom = trim((string) ($_GET['sold_from'] ?? $_POST['sold_from'] ?? ''));
        $soldTo = trim((string) ($_GET['sold_to'] ?? $_POST['sold_to'] ?? ''));

        // Supplier list eligible for quick payout creation (paid orders with pending payout)
        $pendingSupplierCandidates = $this->sales->pendingPayoutCandidatesBySupplier(
            $soldFrom ?: null,
            $soldTo ?: null,
            500
        );
        $candidateSupplierIds = array_values(array_unique(array_filter(array_map(
            static fn(array $candidate): int => (int) ($candidate['supplier_pessoa_id'] ?? 0),
            $pendingSupplierCandidates
        ))));
        $statusBreakdownBySupplier = $this->loadSupplierStatusBreakdown(
            $candidateSupplierIds,
            $soldFrom ?: null,
            $soldTo ?: null
        );
        foreach ($pendingSupplierCandidates as &$candidate) {
            $supplierId = (int) ($candidate['supplier_pessoa_id'] ?? 0);
            $status = $statusBreakdownBySupplier[$supplierId] ?? [];
            $candidate = array_merge($candidate, $status);
        }
        unset($candidate);

        // Load pending sales for the selected supplier
        $pendingSales = [];
        if ($selectedSupplier > 0) {
            if ($isEditingConfirmed && $payoutId > 0) {
                $pendingSales = $this->sales->listSelectableBySupplierForPayoutEdit(
                    $selectedSupplier,
                    $payoutId,
                    $soldFrom ?: null,
                    $soldTo ?: null
                );
            } else {
                $pendingSales = $this->sales->listPendingBySupplier(
                    $selectedSupplier,
                    $soldFrom ?: null,
                    $soldTo ?: null
                );
            }
        }

        // Bank accounts
        $activeBankAccounts = $this->bankAccounts->all();

        // Suppliers
        $suppliers = $this->loadConsignmentSuppliers();

        // Person data for pix key pre-fill
        $supplierPerson = $selectedSupplier > 0 ? $this->persons->find($selectedSupplier) : null;

        View::render('consignment_module/payout_form', [
            'payout'             => $payout,
            'existingItems'      => $existingItems,
            'pendingSales'       => $pendingSales,
            'pendingSupplierCandidates' => $pendingSupplierCandidates,
            'selectedSupplier'   => $selectedSupplier,
            'suppliers'          => $suppliers,
            'supplierPerson'     => $supplierPerson,
            'activeBankAccounts' => $activeBankAccounts,
            'formRoute'          => $formRoute,
            'isDedicatedSupplierPage' => $isDedicatedSupplierPage,
            'isEditingConfirmed' => $isEditingConfirmed,
            'errors'             => $errors,
            'success'            => $success,
        ], ['title' => $payoutId > 0 ? 'Consignação — Editar Pagamento' : 'Consignação — Novo Pagamento']);
    }

    /* =====================================================================
     * 5. PAYOUT — LIST
     * ===================================================================== */

    public function payoutList(): void
    {
        $errors = [];
        $success = '';
        $importSummary = ['imported' => 0, 'linked_sales' => 0, 'unmatched' => 0];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }

        // Autoimporta PIX legados vindos do módulo de cupom/crédito para aparecerem
        // como pagamentos de consignação já vinculados às vendas/produtos.
        if (empty($errors) && Auth::can('consignment_module.create_payout')) {
            $importSummary = $this->importLegacyPixPayoutsFromVoucherLedger($errors);
            if (($importSummary['imported'] ?? 0) > 0) {
                $importMsg = (int) $importSummary['imported'] . ' pagamento(s) PIX legado(s) importado(s), com '
                    . (int) ($importSummary['linked_sales'] ?? 0) . ' venda(s) vinculada(s).';
                $success = $success !== '' ? ($success . ' | ' . $importMsg) : $importMsg;
            }
        }

        $filters = [];
        if (!empty($_GET['supplier_pessoa_id'])) $filters['supplier_pessoa_id'] = (int) $_GET['supplier_pessoa_id'];
        if (!empty($_GET['status'])) $filters['status'] = trim((string) $_GET['status']);
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = trim((string) $_GET['date_from']);
            $filters['received_from'] = $filters['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = trim((string) $_GET['date_to']);
            $filters['received_to'] = $filters['date_to'];
        }
        if (!empty($_GET['q'])) $filters['search'] = trim((string) $_GET['q']);
        if (!empty($_GET['filter_id'])) $filters['filter_id'] = trim((string) $_GET['filter_id']);
        if (!empty($_GET['filter_supplier_name'])) $filters['filter_supplier_name'] = trim((string) $_GET['filter_supplier_name']);
        if (!empty($_GET['filter_method'])) $filters['filter_method'] = trim((string) $_GET['filter_method']);
        if (!empty($_GET['filter_status'])) $filters['filter_status'] = trim((string) $_GET['filter_status']);
        if (!empty($_GET['filter_reference'])) $filters['filter_reference'] = trim((string) $_GET['filter_reference']);
        if (!empty($_GET['filter_total_amount'])) $filters['filter_total_amount'] = trim((string) $_GET['filter_total_amount']);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->sanitizePerPage((int) ($_GET['per_page'] ?? 50));

        $result = $this->payouts->paginate($filters, $page, $perPage);
        $rows = $result['items'] ?? $result['rows'] ?? [];
        $total = $result['total'] ?? 0;

        $suppliers = $this->loadConsignmentSuppliers();

        View::render('consignment_module/payout_list', [
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'filters'   => $filters,
            'suppliers' => $suppliers,
            'importSummary' => $importSummary,
            'errors'    => $errors,
            'success'   => $success,
        ], ['title' => 'Consignação — Pagamentos']);
    }

    /* =====================================================================
     * 6. PAYOUT — SHOW (Detalhes)
     * ===================================================================== */

    public function payoutShow(): void
    {
        $errors = [];
        $success = '';
        $payoutId = (int) ($_GET['id'] ?? 0);
        $payout = $payoutId > 0 ? $this->payouts->find($payoutId) : null;

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $flashError = trim((string) $_SESSION['flash_error']);
            if ($flashError !== '') {
                $errors[] = $flashError;
            }
            unset($_SESSION['flash_error']);
        }

        if (!$payout) {
            $errors[] = 'Pagamento não encontrado.';
        }

        $items = $payout ? $this->payoutItems->listByPayout($payoutId) : [];
        $items = $this->enrichWithPersonNames($items, 'supplier_pessoa_id');

        $supplierPerson = null;
        if ($payout && !empty($payout['supplier_pessoa_id'])) {
            $supplierPerson = $this->persons->find((int) $payout['supplier_pessoa_id']);
        }

        View::render('consignment_module/payout_show', [
            'payout'         => $payout,
            'items'          => $items,
            'supplierPerson' => $supplierPerson,
            'success'        => $success,
            'errors'         => $errors,
        ], ['title' => 'Consignação — Pagamento #' . $payoutId]);
    }

    /* =====================================================================
     * 7. PAYOUT — CONFIRM
     * ===================================================================== */

    public function payoutConfirm(): void
    {
        $errors = [];
        $payoutId = (int) ($_POST['payout_id'] ?? 0);

        if ($payoutId <= 0) {
            $_SESSION['flash_error'] = 'Pagamento inválido.';
            header('Location: consignacao-pagamento-list.php');
            exit;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        $financeData = [
            'category'    => trim((string) ($_POST['finance_category'] ?? 'Pagamento consignação')),
            'description' => trim((string) ($_POST['finance_description'] ?? '')),
        ];

        $result = $this->payoutService->confirm($payoutId, $userId, $financeData);

        if (!empty($result['errors'])) {
            $_SESSION['flash_error'] = implode(' | ', $result['errors']);
        } else {
            $_SESSION['flash_success'] = 'Pagamento #' . $payoutId . ' confirmado com sucesso.';
        }

        header('Location: consignacao-pagamento-list.php?id=' . $payoutId . '&action=show');
        exit;
    }

    /* =====================================================================
     * 8. PAYOUT — CANCEL
     * ===================================================================== */

    public function payoutCancel(): void
    {
        $payoutId = (int) ($_POST['payout_id'] ?? 0);
        $reason = trim((string) ($_POST['cancelation_reason'] ?? ''));

        if ($payoutId <= 0) {
            $_SESSION['flash_error'] = 'Pagamento inválido.';
            header('Location: consignacao-pagamento-list.php');
            exit;
        }

        if ($reason === '') {
            $_SESSION['flash_error'] = 'Motivo do cancelamento é obrigatório.';
            header('Location: consignacao-pagamento-list.php?id=' . $payoutId . '&action=show');
            exit;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        $result = $this->payoutService->cancel($payoutId, $userId, $reason);

        if (!empty($result['errors'])) {
            $_SESSION['flash_error'] = implode(' | ', $result['errors']);
        } else {
            $_SESSION['flash_success'] = 'Pagamento #' . $payoutId . ' cancelado.';
        }

        header('Location: consignacao-pagamento-list.php?id=' . $payoutId . '&action=show');
        exit;
    }

    /* =====================================================================
     * 9. PAYOUT — RECEIPT (Recibo impressão)
     * ===================================================================== */

    public function payoutReceipt(): void
    {
        $payoutId = (int) ($_GET['id'] ?? 0);
        $data = $this->payoutService->getReceiptData($payoutId);

        if (!$data) {
            echo '<p>Pagamento não encontrado.</p>';
            return;
        }

        $supplierPerson = null;
        if (!empty($data['payout']['supplier_pessoa_id'])) {
            $supplierPerson = $this->persons->find((int) $data['payout']['supplier_pessoa_id']);
        }

        View::render('consignment_module/payout_receipt_print', [
            'receipt'        => $data,
            'supplierPerson' => $supplierPerson,
        ], ['title' => 'Recibo Pagamento #' . $payoutId, 'layout' => __DIR__ . '/../Views/print-layout.php']);
    }

    /**
     * Prévia de documento para itens marcados no formulário de pagamento.
     */
    public function payoutPreview(): void
    {
        $errors = [];

        $payoutId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $supplierPessoaId = (int) ($_POST['supplier_pessoa_id'] ?? $_GET['supplier_pessoa_id'] ?? 0);

        $rawSaleIds = $_POST['sale_ids'] ?? $_GET['sale_ids'] ?? [];
        $rawSaleIds = is_array($rawSaleIds) ? $rawSaleIds : [$rawSaleIds];
        $saleIds = array_values(array_unique(array_filter(
            array_map('intval', $rawSaleIds),
            static fn (int $id): bool => $id > 0
        )));

        $payout = $payoutId > 0 ? $this->payouts->find($payoutId) : null;
        if ($payout && $supplierPessoaId <= 0) {
            $supplierPessoaId = (int) ($payout['supplier_pessoa_id'] ?? 0);
        }

        // Se nenhuma venda veio marcada, tenta usar os itens já salvos do rascunho.
        if (empty($saleIds) && $payoutId > 0) {
            $draftItems = $this->payoutItems->listByPayout($payoutId);
            $saleIds = array_values(array_unique(array_filter(array_map(
                static fn (array $item): int => (int) ($item['consignment_sale_id'] ?? 0),
                $draftItems
            ), static fn (int $id): bool => $id > 0)));
        }

        $selection = $this->collectPreviewSalesSelection($supplierPessoaId, $saleIds);
        $errors = array_merge($errors, $selection['errors']);
        $selectedSales = $selection['sales'];

        $method = trim((string) ($_POST['method'] ?? $_GET['method'] ?? ($payout['method'] ?? 'pix')));
        if ($method === '') {
            $method = 'pix';
        }

        $payoutDate = trim((string) ($_POST['payout_date'] ?? $_GET['payout_date'] ?? ($payout['payout_date'] ?? date('Y-m-d'))));
        if ($payoutDate === '') {
            $payoutDate = date('Y-m-d');
        }

        $previewData = $this->buildPreviewReceiptData(
            $supplierPessoaId,
            $selectedSales,
            [
                'id' => $payoutId,
                'payout_date' => $payoutDate,
                'method' => $method,
                'reference' => trim((string) ($_POST['reference'] ?? $_GET['reference'] ?? ($payout['reference'] ?? ''))),
                'pix_key' => trim((string) ($_POST['pix_key'] ?? $_GET['pix_key'] ?? ($payout['pix_key'] ?? ''))),
                'notes' => trim((string) ($_POST['notes'] ?? $_GET['notes'] ?? ($payout['notes'] ?? ''))),
            ]
        );

        $supplierPerson = $supplierPessoaId > 0 ? $this->persons->find($supplierPessoaId) : null;

        View::render('consignment_module/payout_receipt_print', [
            'receipt'        => $previewData,
            'supplierPerson' => $supplierPerson,
            'documentMode'   => 'preview',
            'errors'         => $errors,
        ], ['title' => 'Prévia de Pagamento de Consignação', 'layout' => __DIR__ . '/../Views/print-layout.php']);
    }

    /**
     * Exporta em lote os espelhos prévios de pagamento por fornecedora.
     * Gera ZIP com PDFs (quando houver conversor disponível) ou HTMLs imprimíveis.
     */
    public function payoutPreviewBatchExport(): void
    {
        $soldFrom = trim((string) ($_POST['sold_from'] ?? ''));
        $soldTo = trim((string) ($_POST['sold_to'] ?? ''));
        $scope = strtolower(trim((string) ($_POST['batch_scope'] ?? 'all')));
        if (!in_array($scope, ['all', 'selected'], true)) {
            $scope = 'all';
        }

        $selectedSupplierIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['batch_supplier_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));

        $pendingSupplierCandidates = $this->sales->pendingPayoutCandidatesBySupplier(
            $soldFrom !== '' ? $soldFrom : null,
            $soldTo !== '' ? $soldTo : null,
            1000
        );
        $candidateMap = [];
        foreach ($pendingSupplierCandidates as $candidate) {
            $supplierId = (int) ($candidate['supplier_pessoa_id'] ?? 0);
            if ($supplierId <= 0) {
                continue;
            }
            $candidateMap[$supplierId] = $candidate;
        }

        $availableSupplierIds = array_keys($candidateMap);
        $targetSupplierIds = $scope === 'all'
            ? $availableSupplierIds
            : array_values(array_intersect($selectedSupplierIds, $availableSupplierIds));

        if (empty($targetSupplierIds)) {
            $message = $scope === 'selected'
                ? 'Selecione ao menos uma fornecedora para exportar o lote de espelhos.'
                : 'Nenhuma fornecedora com itens pendentes para o período informado.';
            $this->redirectBackToPayoutFormWithMessage($message, $soldFrom, $soldTo, false);
        }

        if (!class_exists(\ZipArchive::class)) {
            $this->redirectBackToPayoutFormWithMessage(
                'Extensão ZIP não disponível no PHP para exportação em lote.',
                $soldFrom,
                $soldTo,
                false
            );
        }

        $exportAt = date('Y-m-d H:i:s');
        $exportStamp = date('Ymd-His');
        $tmpDir = $this->createTemporaryExportDir('consignacao_espelho_lote_');
        register_shutdown_function(function () use ($tmpDir): void {
            $this->removeDirectoryRecursive($tmpDir);
        });

        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . 'extrato-' . $exportStamp . '.zip';
        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            $this->redirectBackToPayoutFormWithMessage(
                'Não foi possível iniciar a exportação em lote (ZIP).',
                $soldFrom,
                $soldTo,
                false
            );
        }

        $sofficeBin = $this->resolveSofficeBinary();
        $pdfConverterAvailable = $sofficeBin !== null;
        $summaryRows = [];
        $exportedDocuments = 0;
        $batchPayoutDate = trim((string) ($_POST['batch_payout_date'] ?? date('Y-m-d')));
        if ($batchPayoutDate === '') {
            $batchPayoutDate = date('Y-m-d');
        }

        foreach ($targetSupplierIds as $supplierPessoaId) {
            $sales = $this->sales->listPendingBySupplier(
                (int) $supplierPessoaId,
                $soldFrom !== '' ? $soldFrom : null,
                $soldTo !== '' ? $soldTo : null
            );

            if (empty($sales)) {
                $summaryRows[] = [
                    'supplier_id' => (int) $supplierPessoaId,
                    'supplier_name' => (string) ($candidateMap[$supplierPessoaId]['supplier_name'] ?? ('Fornecedor #' . $supplierPessoaId)),
                    'pieces_count' => 0,
                    'total_commission' => 0.0,
                    'file_name' => '',
                    'format' => '',
                    'status' => 'sem_itens_no_periodo',
                ];
                continue;
            }

            $supplierPerson = $this->persons->find((int) $supplierPessoaId);
            $supplierName = (string) (
                $supplierPerson->fullName
                ?? $supplierPerson->full_name
                ?? ($candidateMap[$supplierPessoaId]['supplier_name'] ?? ('Fornecedor #' . $supplierPessoaId))
            );

            $piecesCount = count($sales);
            $totalCommission = 0.0;
            foreach ($sales as $sale) {
                $totalCommission += (float) ($sale['credit_amount'] ?? 0);
            }

            $previewData = $this->buildPreviewReceiptData(
                (int) $supplierPessoaId,
                $sales,
                [
                    'payout_date' => $batchPayoutDate,
                    'method' => 'pix',
                    'reference' => '[LOTE] Exportação em ' . $exportStamp,
                    'notes' => 'Espelho prévio em lote. Documento sem efeito financeiro.',
                    'preview_generated_at' => $exportAt,
                ]
            );

            $html = $this->renderPreviewReceiptHtml($previewData, $supplierPerson, [], true);

            $baseFileName = $this->buildBatchPreviewFileBaseName(
                $supplierName,
                $exportStamp,
                $piecesCount,
                $totalCommission
            );

            $htmlPath = $tmpDir . DIRECTORY_SEPARATOR . $baseFileName . '.html';
            file_put_contents($htmlPath, $html);

            $addedFileName = basename($htmlPath);
            $addedFormat = 'html';
            $status = 'ok_html';

            if ($pdfConverterAvailable && $sofficeBin) {
                $convertedPdfPath = $this->convertHtmlToPdfWithSoffice($sofficeBin, $htmlPath, $tmpDir);
                if ($convertedPdfPath && is_file($convertedPdfPath)) {
                    $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $baseFileName . '.pdf';
                    if ($convertedPdfPath !== $pdfPath) {
                        @rename($convertedPdfPath, $pdfPath);
                    }
                    if (is_file($pdfPath)) {
                        $zip->addFile($pdfPath, basename($pdfPath));
                        $addedFileName = basename($pdfPath);
                        $addedFormat = 'pdf';
                        $status = 'ok_pdf';
                        $exportedDocuments++;
                    } else {
                        $zip->addFile($htmlPath, basename($htmlPath));
                        $exportedDocuments++;
                        $status = 'erro_renomear_pdf_fallback_html';
                    }
                } else {
                    $zip->addFile($htmlPath, basename($htmlPath));
                    $exportedDocuments++;
                    $status = 'erro_converter_pdf_fallback_html';
                }
            } else {
                $zip->addFile($htmlPath, basename($htmlPath));
                $exportedDocuments++;
            }

            $summaryRows[] = [
                'supplier_id' => (int) $supplierPessoaId,
                'supplier_name' => $supplierName,
                'pieces_count' => $piecesCount,
                'total_commission' => round($totalCommission, 2),
                'file_name' => $addedFileName,
                'format' => $addedFormat,
                'status' => $status,
            ];
        }

        $summaryCsvPath = $tmpDir . DIRECTORY_SEPARATOR . 'extrato-' . $exportStamp . '.csv';
        file_put_contents($summaryCsvPath, $this->buildBatchPreviewSummaryCsv($summaryRows, $exportAt, $pdfConverterAvailable));
        $zip->addFile($summaryCsvPath, basename($summaryCsvPath));

        if (!$pdfConverterAvailable) {
            $readmePath = $tmpDir . DIRECTORY_SEPARATOR . 'LEIA-ME.txt';
            $readmeText = "Conversor PDF indisponível neste ambiente.\n" .
                "Arquivos foram exportados como HTML imprimível.\n" .
                "Para gerar PDF no servidor, instale LibreOffice e configure APP_SOFFICE_BIN.\n";
            file_put_contents($readmePath, $readmeText);
            $zip->addFile($readmePath, basename($readmePath));
        }

        $zip->close();

        if ($exportedDocuments <= 0 || !is_file($zipPath)) {
            $this->redirectBackToPayoutFormWithMessage(
                'Não foi possível gerar documentos para o lote selecionado.',
                $soldFrom,
                $soldTo,
                false
            );
        }

        $downloadName = ($pdfConverterAvailable ? 'espelhos-consignacao-pdf-lote-' : 'espelhos-consignacao-html-lote-')
            . $exportStamp . '.zip';
        $this->streamDownloadFile($zipPath, $downloadName, 'application/zip');
    }

    /* =====================================================================
     * 10. REPORT — VENDOR (por fornecedora)
     * ===================================================================== */

    public function reportVendor(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $supplierFilter = (int) ($_GET['supplier_pessoa_id'] ?? 0);
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $dateField = trim((string) ($_GET['date_field'] ?? 'sold_at'));
        if (!in_array($dateField, ['sold_at', 'received_at', 'paid_at'], true)) {
            $dateField = 'sold_at';
        }
        $reportFilters = $this->buildVendorReportFilters();
        $printMode = ($_GET['print'] ?? '') === '1';
        $csvMode = in_array(strtolower(trim((string) ($_GET['format'] ?? $_GET['export'] ?? ''))), ['csv', '1'], true);

        $reportData = null;
        if ($supplierFilter > 0) {
            $reportData = $this->buildVendorReport($supplierFilter, $dateFrom, $dateTo, $dateField, $reportFilters);
        }

        $suppliers = $this->loadConsignmentSuppliers();
        $supplierPerson = $supplierFilter > 0 ? $this->persons->find($supplierFilter) : null;
        $periodBadge = $this->resolveReportPeriodBadge($dateFrom, $dateTo);

        if ($csvMode && $reportData) {
            $this->streamVendorReportCsv($reportData, $supplierPerson, $dateFrom, $dateTo, $dateField);
            return;
        }

        if ($printMode && $reportData) {
            View::render('consignment_module/report_vendor_print', [
                'report'         => $reportData,
                'supplierPerson' => $supplierPerson,
                'dateFrom'       => $dateFrom,
                'dateTo'         => $dateTo,
                'dateField'      => $dateField,
                'reportFilters'  => $reportFilters,
            ], ['title' => 'Relatório Fornecedora', 'layout' => __DIR__ . '/../Views/print-layout.php']);
            return;
        }

        View::render('consignment_module/report_vendor', [
            'report'           => $reportData,
            'supplierFilter'   => $supplierFilter,
            'supplierPerson'   => $supplierPerson,
            'suppliers'        => $suppliers,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'dateField'        => $dateField,
            'reportFilters'    => $reportFilters,
            'periodBadge'      => $periodBadge,
            'errors'           => $errors,
        ], ['title' => 'Consignação — Relatório Fornecedora']);
    }

    /* =====================================================================
     * 11. REPORT — INTERNAL (gestão)
     * ===================================================================== */

    public function reportInternal(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $agingDist = $this->registry->agingDistribution();
        $agingBySupplier = $this->registry->agingBySupplier(20);
        $pendingBySupplier = $this->sales->pendingSummaryBySupplier(50);
        $recentOwnItems = $this->fetchRecentOwnItems(90);
        $legacyUnlinked = $this->countLegacyUnlinkedPayouts();

        // Supplier ranking: vendas, giro, devolução
        $supplierRanking = $this->buildSupplierRanking();

        // Legacy analysis summary
        $legacyAnalysis = $this->buildLegacyAnalysis();

        // Date filters
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $periodBadge = $this->resolveReportPeriodBadge($dateFrom, $dateTo);

        View::render('consignment_module/report_internal', [
            'aging'             => $agingDist,
            'agingDist'         => $agingDist,
            'agingBySupplier'   => $agingBySupplier,
            'pendingBySupplier' => $pendingBySupplier,
            'ownItems'          => $recentOwnItems,
            'recentOwnItems'    => $recentOwnItems,
            'legacyUnlinked'    => $legacyUnlinked,
            'legacyAnalysis'    => $legacyAnalysis,
            'ranking'           => $supplierRanking,
            'supplierRanking'   => $supplierRanking,
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
            'periodBadge'       => $periodBadge,
            'errors'            => $errors,
        ], ['title' => 'Consignação — Relatório Interno']);
    }

    /* =====================================================================
     * 12. INCONSISTENCIES
     * ===================================================================== */

    public function inconsistencies(): void
    {
        $errors = [];
        $success = '';
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }

        // Handle POST reindex
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = trim((string) $_POST['action']);
            if ($action === 'reindex') {
                $this->reindex();
                return;
            }
        }

        $checks = $this->integrityService->runAllChecks();
        // Normalize check format for the view: ensure 'label', 'issues' keys exist
        $checks = array_map(function (array $check): array {
            $type = (string) ($check['type'] ?? '');
            $count = (int) ($check['count'] ?? 0);
            $issues = $check['details'] ?? [];

            if ($count > 0 && empty($issues) && $type !== '') {
                $issues = $this->integrityService->getCheckDetails($type, 100);
            }
            if (!isset($check['label'])) {
                $check['label'] = $check['description'] ?? $check['type'] ?? '';
            }
            $check['issues'] = $issues;
            if (!isset($check['check'])) {
                $check['check'] = $check['type'] ?? '';
            }
            return $check;
        }, $checks);

        $periodLocks = $this->periodLockService->listAll();

        View::render('consignment_module/inconsistencies', [
            'checks'      => $checks,
            'periodLocks' => $periodLocks,
            'errors'      => $errors,
            'success'     => $success,
        ], ['title' => 'Consignação — Inconsistências']);
    }

    /* =====================================================================
     * 13. LEGACY RECONCILIATION
     * ===================================================================== */

    public function legacyReconciliation(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $err = trim((string) $_SESSION['flash_error']);
            if ($err !== '') {
                $errors[] = $err;
            }
            unset($_SESSION['flash_error']);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->sanitizePerPage((int) ($_GET['per_page'] ?? 50));
        $perPageOptions = [25, 50, 100, 200];

        $totalMovements = $this->countLegacyPayoutMovements();
        $totalPages = max(1, (int) ceil($totalMovements / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Load legacy payout movements (event_type='payout' and payout_id IS NULL)
        $legacyPayouts = $this->fetchLegacyPayouts($perPage, $offset);
        $legacyPayouts = $this->enrichWithPersonNames($legacyPayouts, 'pessoa_id');

        // Enrich each legacy payout with eligible sales for its supplier (pending + paid-return exception)
        // and normalize field names.
        foreach ($legacyPayouts as &$mov) {
            $mov['amount'] = (float) ($mov['credit_amount'] ?? $mov['amount'] ?? 0);
            $mov['supplier_pessoa_id'] = (int) ($mov['pessoa_id'] ?? $mov['vendor_pessoa_id'] ?? 0);
            if (!isset($mov['supplier_name'])) {
                $mov['supplier_name'] = $mov['full_name'] ?? '(sem nome)';
            }
            // Attach pending sales for this supplier
            $supplierPid = (int) ($mov['supplier_pessoa_id'] ?? 0);
            if ($supplierPid > 0) {
                $mov['pending_sales'] = $this->fetchPendingSalesForSupplier($supplierPid);
            } else {
                $mov['pending_sales'] = [];
            }
        }
        unset($mov);

        View::render('consignment_module/legacy_reconciliation', [
            'legacyPayouts'    => $legacyPayouts,
            'page'             => $page,
            'perPage'          => $perPage,
            'perPageOptions'   => $perPageOptions,
            'totalMovements'   => $totalMovements,
            'totalPages'       => $totalPages,
            'errors'           => $errors,
            'success'          => $success,
        ], ['title' => 'Consignação — Conciliação de Legados']);
    }

    /**
     * POST: Confirm a legacy reconciliation by creating a retroactive payout.
     */
    public function legacyReconciliationConfirm(): void
    {
        $errors = [];
        $movementId = (int) ($_POST['movement_id'] ?? 0);
        $saleIds = array_map('intval', (array) ($_POST['sale_ids'] ?? []));
        $saleIds = array_filter($saleIds);

        if ($movementId <= 0 || empty($saleIds)) {
            $_SESSION['flash_error'] = 'Dados insuficientes para conciliação.';
            header('Location: consignacao-inconsistencias.php?action=legacy');
            exit;
        }

        if (!$this->pdo) {
            $_SESSION['flash_error'] = 'Erro na conciliação: sem conexão com banco.';
            header('Location: consignacao-inconsistencias.php?action=legacy');
            exit;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            // Get the legacy movement
            $movement = $this->ledger->find($movementId);
            if (!$movement) {
                throw new \RuntimeException('Movimento legado não encontrado.');
            }

            $supplierPessoaId = (int) ($movement['pessoa_id'] ?? $movement['vendor_pessoa_id'] ?? 0);
            if ($supplierPessoaId <= 0) {
                $voucherAccountId = (int) ($movement['voucher_account_id'] ?? 0);
                if ($voucherAccountId > 0) {
                    $supplierLookup = $this->pdo->prepare("SELECT pessoa_id FROM cupons_creditos WHERE id = :id LIMIT 1");
                    $supplierLookup->execute([':id' => $voucherAccountId]);
                    $supplierPessoaId = (int) ($supplierLookup->fetchColumn() ?: 0);
                }
            }
            if ($supplierPessoaId <= 0) {
                throw new \RuntimeException('Não foi possível identificar a fornecedora da movimentação.');
            }

            $selectedSales = $this->fetchSalesForLegacyReconciliation($saleIds);
            if (count($selectedSales) !== count($saleIds)) {
                throw new \RuntimeException('Uma ou mais vendas selecionadas não foram encontradas.');
            }

            $invalidSales = [];
            $salesMissingRequiredFields = [];
            foreach ($selectedSales as $sale) {
                $saleId = (int) ($sale['id'] ?? 0);
                $saleSupplier = (int) ($sale['supplier_pessoa_id'] ?? 0);
                $saleStatus = (string) ($sale['sale_status'] ?? '');
                $payoutStatus = (string) ($sale['payout_status'] ?? '');
                $legacyPaidReturnException = $this->isLegacyPaidReturnExceptionSale($sale);
                $allowedStatus = ($saleStatus === 'ativa') || $legacyPaidReturnException;
                if ($saleId <= 0 || $saleSupplier !== $supplierPessoaId || !$allowedStatus || $payoutStatus !== 'pendente') {
                    $invalidSales[] = $saleId;
                }

                $sku = trim((string) ($sale['sku'] ?? ''));
                $productName = trim((string) ($sale['product_name'] ?? ''));
                if ($sku === '' || $productName === '') {
                    $salesMissingRequiredFields[] = $saleId;
                }
            }

            if (!empty($invalidSales)) {
                $invalidSales = array_values(array_unique(array_filter($invalidSales)));
                throw new \RuntimeException('Há vendas inválidas para esta conciliação: #' . implode(', #', $invalidSales) . '.');
            }
            if (!empty($salesMissingRequiredFields)) {
                $salesMissingRequiredFields = array_values(array_unique(array_filter($salesMissingRequiredFields)));
                throw new \RuntimeException('SKU e produto são obrigatórios. Corrija as vendas: #' . implode(', #', $salesMissingRequiredFields) . '.');
            }

            $totalAmount = abs((float) ($movement['credit_amount'] ?? 0));

            // Create retroactive payout
            $payoutData = [
                'supplier_pessoa_id'  => $supplierPessoaId,
                'payout_date'         => substr($movement['event_at'] ?? date('Y-m-d'), 0, 10),
                'method'              => 'pix',
                'total_amount'        => $totalAmount,
                'items_count'         => count($selectedSales),
                'status'              => 'confirmado',
                'reference'           => '[LEGACY] Conciliação retrospectiva do movimento #' . $movementId,
                'notes'               => '[LEGACY] Conciliado em ' . date('Y-m-d H:i:s') . ' por user #' . $userId,
                'confirmed_at'        => date('Y-m-d H:i:s'),
                'confirmed_by'        => $userId,
                'created_by'          => $userId,
            ];

            $payoutId = $this->payouts->create($payoutData);

            // Link sales to this payout
            foreach ($selectedSales as $sale) {
                $saleId = (int) ($sale['id'] ?? 0);

                $this->payoutItems->create([
                    'payout_id'          => $payoutId,
                    'consignment_sale_id' => $saleId,
                    'product_id'         => (int) ($sale['product_id'] ?? 0),
                    'order_id'           => (int) ($sale['order_id'] ?? 0),
                    'order_item_id'      => (int) ($sale['order_item_id'] ?? 0),
                    'amount'             => (float) ($sale['credit_amount'] ?? 0),
                    'percent_applied'    => (float) ($sale['percent_applied'] ?? 0),
                ]);

                $this->sales->markPaidByPayout([$saleId], $payoutId, $payoutData['payout_date']);

                // Transition product to vendido_pago if still vendido_pendente
                $productId = (int) ($sale['product_id'] ?? 0);
                $currentStatus = $this->stateService->getCurrentStatus($productId);
                if ($currentStatus === 'vendido_pendente') {
                    $this->stateService->transition($productId, 'vendido_pago', [
                        'user_id' => $userId,
                        'notes'   => '[LEGACY] Conciliação retrospectiva payout #' . $payoutId,
                    ]);
                }
            }

            // Update legacy movement with payout_id reference
            $this->ledger->update($movementId, ['payout_id' => $payoutId]);

            $this->pdo->commit();

            $_SESSION['flash_success'] = 'Conciliação realizada: Payout retroativo #' . $payoutId . ' criado com ' . count($selectedSales) . ' item(ns).';
        } catch (\Throwable $e) {
            if ($this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Erro na conciliação: ' . $e->getMessage();
        }

        header('Location: consignacao-inconsistencias.php?action=legacy');
        exit;
    }

    /* =====================================================================
     * 13b. BACKFILL RECONCILIATION REVIEW
     * ===================================================================== */

    /**
     * Show a comprehensive review page for all 52 unlinked legacy payout movements,
     * grouped by reconciliation category with action buttons.
     */
    public function backfillReconciliationReview(): void
    {
        $errors = [];
        $success = '';
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }
        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $errors[] = trim((string) $_SESSION['flash_error']);
            unset($_SESSION['flash_error']);
        }

        // 1. Load ALL unlinked payout movements
        $allMovs = $this->pdo->query("
            SELECT m.id AS mov_id, m.voucher_account_id, m.credit_amount, m.event_at, m.event_notes, m.type,
                   cc.pessoa_id AS supplier_id, cc.customer_name AS supplier_name, cc.balance AS voucher_balance, cc.id AS voucher_id
            FROM cupons_creditos_movimentos m
            JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
            WHERE m.type = 'debito' AND m.event_type = 'payout' AND (m.payout_id IS NULL OR m.payout_id = 0)
            ORDER BY cc.customer_name COLLATE utf8mb4_unicode_ci, m.event_at
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. For each movement, get reconciliation candidates:
        //    - pending active sales
        //    - legacy paid-return exceptions (revertida + pendente)
        //    - already paid sales
        $categories = ['A' => [], 'C' => [], 'B' => [], 'D' => []];
        $voucherDivergences = [];

        // Group movements by supplier to handle multi-payout suppliers
        $bySupplier = [];
        foreach ($allMovs as $mov) {
            $bySupplier[$mov['supplier_id']][] = $mov;
        }

        foreach ($allMovs as &$mov) {
            $sid = (int) $mov['supplier_id'];
            $payoutAmount = (float) $mov['credit_amount'];

            // Fetch sales
            $pending = $this->pdo->prepare("
                SELECT cs.id, cs.credit_amount, cs.sold_at, cs.product_id,
                       cs.sale_status, cs.payout_status, cs.reversed_at, cs.reversal_event_type,
                       COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', cs.product_id)) AS product_name,
                       COALESCE(
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           TRIM(CAST(cs.product_id AS CHAR))
                       ) AS sku
                FROM consignment_sales cs
                LEFT JOIN order_items oi ON oi.id = cs.order_item_id
                LEFT JOIN products p  ON p.sku  = cs.product_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE cs.supplier_pessoa_id = ?
                  AND cs.payout_status = 'pendente'
                  AND (
                      cs.sale_status = 'ativa'
                      OR (
                          cs.sale_status = 'revertida'
                          AND (
                              cs.reversed_at IS NOT NULL
                              OR COALESCE(cs.reversal_event_type, '') <> ''
                          )
                      )
                  )
                ORDER BY cs.sold_at
            ");
            $pending->execute([$sid]);
            $mov['pending_sales'] = $pending->fetchAll(\PDO::FETCH_ASSOC);

            $pago = $this->pdo->prepare("
                SELECT cs.id, cs.credit_amount, cs.sold_at, cs.product_id,
                       COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', cs.product_id)) AS product_name,
                       COALESCE(
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           TRIM(CAST(cs.product_id AS CHAR))
                       ) AS sku
                FROM consignment_sales cs
                LEFT JOIN order_items oi ON oi.id = cs.order_item_id
                LEFT JOIN products p  ON p.sku  = cs.product_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE cs.supplier_pessoa_id = ?
                  AND cs.payout_status = 'pago'
                  AND cs.sale_status IN ('ativa', 'revertida')
                ORDER BY cs.sold_at
            ");
            $pago->execute([$sid]);
            $mov['pago_sales'] = $pago->fetchAll(\PDO::FETCH_ASSOC);

            $pagoTotal = array_sum(array_map(fn($s) => (float) $s['credit_amount'], $mov['pago_sales']));
            $pendingTotal = array_sum(array_map(fn($s) => (float) $s['credit_amount'], $mov['pending_sales']));
            $mov['pago_total'] = $pagoTotal;
            $mov['pending_total'] = $pendingTotal;

            // Classify
            if (empty($mov['pending_sales']) && abs($pagoTotal - $payoutAmount) < 0.01) {
                // Cat A: already reconciled, no pending, pago matches exactly
                $mov['category'] = 'A';
                $mov['match_type'] = 'phase4_exact';
                $mov['matched_sales'] = $mov['pago_sales'];
                $categories['A'][] = $mov;
            } elseif (!empty($mov['pago_sales']) && abs($pagoTotal - $payoutAmount) < 0.01 && !empty($mov['pending_sales'])) {
                // Cat C: pago matches exactly but there are extra pending sales
                $mov['category'] = 'C';
                $mov['match_type'] = 'phase4_with_remaining';
                $mov['matched_sales'] = $mov['pago_sales'];
                $categories['C'][] = $mov;
            } elseif (!empty($mov['pago_sales']) && abs($pagoTotal - $payoutAmount) < 0.01) {
                // Also Cat A variant
                $mov['category'] = 'A';
                $mov['match_type'] = 'phase4_exact';
                $mov['matched_sales'] = $mov['pago_sales'];
                $categories['A'][] = $mov;
            } else {
                // Try subset-sum on pending sales
                $amounts = array_map(fn($s) => intval(round((float) $s['credit_amount'] * 100)), $mov['pending_sales']);
                $target = intval(round($payoutAmount * 100));
                $n = count($amounts);
                $subsetMatch = null;

                if ($n > 0 && $n <= 25) {
                    // Brute force or meet-in-the-middle
                    if ($n <= 20) {
                        for ($mask = 1; $mask < (1 << $n); $mask++) {
                            $s = 0;
                            for ($i = 0; $i < $n; $i++) {
                                if ($mask & (1 << $i)) $s += $amounts[$i];
                            }
                            if ($s === $target) {
                                $subsetMatch = [];
                                for ($i = 0; $i < $n; $i++) {
                                    if ($mask & (1 << $i)) $subsetMatch[] = $mov['pending_sales'][$i];
                                }
                                break;
                            }
                        }
                    } else {
                        // Meet-in-the-middle for 20-25 items
                        $subsetMatch = $this->meetInTheMiddleSubsetSum($mov['pending_sales'], $target);
                    }
                }

                if ($subsetMatch !== null) {
                    $mov['category'] = 'B';
                    $mov['match_type'] = 'subset_exact';
                    $mov['matched_sales'] = $subsetMatch;
                    $matchedIds = array_map(fn($s) => $s['id'], $subsetMatch);
                    $mov['remaining_sales'] = array_filter($mov['pending_sales'], fn($s) => !in_array($s['id'], $matchedIds));
                    $categories['B'][] = $mov;
                } else {
                    // Check if pago sale matches (single pago = payout, like Kelly/Vanessa)
                    if (!empty($mov['pago_sales']) && abs($pagoTotal - $payoutAmount) < 0.01) {
                        $mov['category'] = 'A';
                        $mov['match_type'] = 'phase4_exact';
                        $mov['matched_sales'] = $mov['pago_sales'];
                        $categories['A'][] = $mov;
                    } else {
                        $mov['category'] = 'D';
                        $mov['match_type'] = 'no_match';
                        $mov['matched_sales'] = [];
                        // Compute near-miss info
                        $mov['diagnostic'] = $this->buildDiagnostic($mov, $amounts, $target, $mov['pending_sales']);
                        $categories['D'][] = $mov;
                    }
                }
            }
        }
        unset($mov);

        // 3. Check voucher balance divergences
        $voucherDivergences = $this->pdo->query("
            SELECT cc.id, cc.pessoa_id, cc.customer_name, cc.balance AS stored_balance,
                   (SELECT COALESCE(SUM(CASE WHEN m2.type='credito' THEN m2.credit_amount ELSE -m2.credit_amount END),0)
                    FROM cupons_creditos_movimentos m2 WHERE m2.voucher_account_id = cc.id) AS calc_balance
            FROM cupons_creditos cc
            WHERE cc.type = 'credito' AND cc.scope = 'consignacao'
            HAVING ABS(cc.balance - calc_balance) > 0.01
            ORDER BY cc.id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Enrich voucher divergences with movements
        foreach ($voucherDivergences as &$v) {
            $movs = $this->pdo->prepare("
                SELECT m.id, m.credit_amount, m.type, m.event_type, m.event_at, m.event_notes,
                       m.product_name, m.sku, m.buyer_name
                FROM cupons_creditos_movimentos m
                WHERE m.voucher_account_id = ?
                ORDER BY m.event_at, m.id
            ");
            $movs->execute([$v['id']]);
            $v['movements'] = $movs->fetchAll(\PDO::FETCH_ASSOC);
            $v['diff'] = (float) $v['stored_balance'] - (float) $v['calc_balance'];
        }
        unset($v);

        // Summary counts
        $summary = [
            'total'       => count($allMovs),
            'cat_a'       => count($categories['A']),
            'cat_c'       => count($categories['C']),
            'cat_b'       => count($categories['B']),
            'cat_d'       => count($categories['D']),
            'voucher_div' => count($voucherDivergences),
        ];

        View::render('consignment_module/backfill_reconciliation_review', [
            'categories'          => $categories,
            'voucherDivergences'  => $voucherDivergences,
            'summary'             => $summary,
            'errors'              => $errors,
            'success'             => $success,
        ], ['title' => 'Consignação — Revisão de Reconciliação']);
    }

    /**
     * POST: Execute a reconciliation action for a specific movement.
     */
    public function backfillReconciliationAction(): void
    {
        $movementId = (int) ($_POST['movement_id'] ?? 0);
        $action = trim((string) ($_POST['reconciliation_action'] ?? ''));
        $saleIds = array_map('intval', (array) ($_POST['sale_ids'] ?? []));
        $saleIds = array_filter($saleIds);
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        // --- Actions that don't require a specific movement_id ---
        try {
            if ($action === 'bulk_link_ac') {
                // Bulk-link all A+C movements (already reconciled) in one go
                $unlinked = $this->pdo->query("
                    SELECT m.id
                    FROM cupons_creditos_movimentos m
                    JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
                    WHERE cc.scope = 'consignacao' AND m.type = 'debito' AND m.payout_id IS NULL
                ")->fetchAll(\PDO::FETCH_COLUMN);

                if (empty($unlinked)) {
                    $_SESSION['flash_success'] = 'Nenhum movimento pendente para vincular.';
                    header('Location: consignacao-inconsistencias.php?action=backfill_review');
                    exit;
                }

                // For each, check if pago sales already cover the amount (Cat A logic)
                $linked = 0;
                foreach ($unlinked as $mid) {
                    $mov = $this->pdo->prepare("
                        SELECT m.id, m.credit_amount, cc.pessoa_id AS supplier_pessoa_id
                        FROM cupons_creditos_movimentos m
                        JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
                        WHERE m.id = ?
                    ");
                    $mov->execute([$mid]);
                    $m = $mov->fetch(\PDO::FETCH_ASSOC);
                    if (!$m) continue;

                    $target = (float) $m['credit_amount'];
                    $pagoSum = $this->pdo->prepare("
                        SELECT COALESCE(SUM(credit_amount), 0)
                        FROM consignment_sales
                        WHERE supplier_pessoa_id = ?
                          AND payout_status = 'pago'
                          AND sale_status IN ('ativa', 'revertida')
                    ");
                    $pagoSum->execute([$m['supplier_pessoa_id']]);
                    $pago = (float) $pagoSum->fetchColumn();

                    if (abs($pago - $target) < 0.01) {
                        $this->linkLegacyMovement((int) $mid, $userId, '[BULK A+C] Já reconciliado — pago sum matches');
                        $linked++;
                    }
                }

                $_SESSION['flash_success'] = "{$linked} movimento(s) Cat A+C vinculados como já reconciliados.";
                header('Location: consignacao-inconsistencias.php?action=backfill_review');
                exit;
            }

            if ($action === 'fix_voucher_balance') {
                $voucherId = (int) ($_POST['voucher_id'] ?? 0);
                $correctBalance = $_POST['correct_balance'] ?? null;
                if ($voucherId <= 0 || $correctBalance === null) {
                    throw new \RuntimeException('Dados insuficientes para corrigir saldo.');
                }
                $oldBalance = $this->pdo->prepare("SELECT balance FROM cupons_creditos WHERE id = ?");
                $oldBalance->execute([$voucherId]);
                $old = $oldBalance->fetchColumn();

                $this->pdo->prepare("UPDATE cupons_creditos SET balance = ? WHERE id = ?")->execute([(float) $correctBalance, $voucherId]);

                $noteText = "[VOUCHER-FIX " . date('Y-m-d H:i') . "] Saldo corrigido: {$old} → {$correctBalance} — user #{$userId}";
                $this->pdo->prepare("INSERT INTO cupons_creditos_movimentos (voucher_account_id, credit_amount, type, event_type, event_at, event_notes) VALUES (?, 0, 'credito', 'ajuste_saldo', NOW(), ?)")
                    ->execute([$voucherId, $noteText]);

                $_SESSION['flash_success'] = "Voucher #{$voucherId}: Saldo corrigido de R\$ " . number_format((float) $old, 2, ',', '.') . " para R\$ " . number_format((float) $correctBalance, 2, ',', '.');
                header('Location: consignacao-inconsistencias.php?action=backfill_review');
                exit;
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Erro: ' . $e->getMessage();
            header('Location: consignacao-inconsistencias.php?action=backfill_review');
            exit;
        }

        // --- Actions that require a specific movement_id ---
        if ($movementId <= 0) {
            $_SESSION['flash_error'] = 'Movimento inválido.';
            header('Location: consignacao-inconsistencias.php?action=backfill_review');
            exit;
        }

        try {
            switch ($action) {
                case 'approve_subset':
                    // Cat B: mark matched pending sales as pago via retroactive payout
                    if (empty($saleIds)) {
                        throw new \RuntimeException('Nenhuma venda selecionada.');
                    }
                    $this->executeRetroPayout($movementId, $saleIds, $userId, $notes);
                    $_SESSION['flash_success'] = "mov#{$movementId}: Reconciliação aprovada — " . count($saleIds) . " venda(s) marcadas como pago.";
                    break;

                case 'link_existing':
                    // Cat A/C: just link the movement to indicate it's reconciled (sales already pago)
                    $this->linkLegacyMovement($movementId, $userId, $notes);
                    $_SESSION['flash_success'] = "mov#{$movementId}: Movimento vinculado como já reconciliado.";
                    break;

                case 'skip':
                    // Cat D: mark as reviewed but unresolvable, add note
                    $noteText = "\n[REVIEW " . date('Y-m-d H:i') . "] Marcado como irreconciliável" . ($notes ? ": {$notes}" : '') . " — user #{$userId}";
                    $this->pdo->prepare("UPDATE cupons_creditos_movimentos SET event_notes = CONCAT(COALESCE(event_notes,''), ?) WHERE id = ?")->execute([$noteText, $movementId]);
                    $_SESSION['flash_success'] = "mov#{$movementId}: Marcado como irreconciliável.";
                    break;

                case 'approve_near_miss':
                    // Cat D near-miss: approve with tolerance note
                    if (empty($saleIds)) {
                        throw new \RuntimeException('Nenhuma venda selecionada.');
                    }
                    $this->executeRetroPayout($movementId, $saleIds, $userId, "[NEAR-MISS] " . $notes);
                    $_SESSION['flash_success'] = "mov#{$movementId}: Reconciliação near-miss aprovada — " . count($saleIds) . " venda(s).";
                    break;

                default:
                    throw new \RuntimeException("Ação desconhecida: {$action}");
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Erro em mov#{$movementId}: " . $e->getMessage();
        }

        header('Location: consignacao-inconsistencias.php?action=backfill_review');
        exit;
    }

    /**
     * Create a retroactive payout linking a legacy movement to specific sales.
     */
    private function executeRetroPayout(
        int $movementId,
        array $saleIds,
        int $userId,
        string $notes = '',
        string $sourceTag = 'BACKFILL-REVIEW'
    ): int
    {
        $sourceTag = strtoupper(trim($sourceTag));
        $sourceTag = preg_replace('/[^A-Z0-9_-]/', '', $sourceTag ?? '') ?: 'BACKFILL-REVIEW';

        $this->pdo->beginTransaction();
        try {
            $movement = $this->pdo->prepare("SELECT * FROM cupons_creditos_movimentos WHERE id = ?");
            $movement->execute([$movementId]);
            $mov = $movement->fetch(\PDO::FETCH_ASSOC);
            if (!$mov) throw new \RuntimeException('Movimento não encontrado.');

            $supplierPessoaId = (int) ($mov['vendor_pessoa_id'] ?? 0);
            if ($supplierPessoaId <= 0) {
                // Resolve from voucher account
                $acc = $this->pdo->prepare("SELECT pessoa_id FROM cupons_creditos WHERE id = ?");
                $acc->execute([$mov['voucher_account_id']]);
                $supplierPessoaId = (int) ($acc->fetchColumn() ?: 0);
            }

            $totalAmount = abs((float) $mov['credit_amount']);
            $payoutDate = substr($mov['event_at'] ?? date('Y-m-d'), 0, 10);
            $eventId = (int) ($mov['event_id'] ?? 0);
            $pixKey = $this->extractPixKeyFromMovementNotes((string) ($mov['event_notes'] ?? ''));
            $reference = '[' . $sourceTag . '] mov #' . $movementId . ($eventId > 0 ? (' evt#' . $eventId) : '');
            if (mb_strlen($reference) > 255) {
                $reference = mb_substr($reference, 0, 255);
            }

            $noteLines = [];
            $noteLines[] = '[' . $sourceTag . '] ' . date('Y-m-d H:i:s') . ' user#' . $userId . ($notes ? " — {$notes}" : '');
            $originNotes = trim((string) ($mov['event_notes'] ?? ''));
            if ($originNotes !== '') {
                $noteLines[] = 'Origem PIX: ' . $originNotes;
            }
            $payoutNotes = implode("\n", $noteLines);

            $payoutData = [
                'supplier_pessoa_id'  => $supplierPessoaId,
                'payout_date'         => $payoutDate,
                'method'              => 'pix',
                'total_amount'        => $totalAmount,
                'items_count'         => count($saleIds),
                'status'              => 'confirmado',
                'reference'           => $reference,
                'notes'               => $payoutNotes,
                'pix_key'             => $pixKey,
                'voucher_account_id'  => (int) ($mov['voucher_account_id'] ?? 0) > 0 ? (int) ($mov['voucher_account_id'] ?? 0) : null,
                'confirmed_at'        => date('Y-m-d H:i:s'),
                'confirmed_by'        => $userId,
                'created_by'          => $userId,
            ];

            $payoutId = $this->payouts->create($payoutData);

            foreach ($saleIds as $saleId) {
                $sale = $this->sales->find($saleId);
                if (!$sale) continue;

                $legacyProductId = (int) ($sale['product_id'] ?? 0);
                $resolvedProductId = $legacyProductId > 0
                    ? $this->resolveRealProductSku($saleId, $legacyProductId)
                    : 0;
                if ($resolvedProductId <= 0) {
                    $resolvedProductId = $legacyProductId;
                }

                // Keep sale/ledger aligned to real SKU when product_id is a legacy/orphan identifier.
                if ($resolvedProductId > 0 && $resolvedProductId !== $legacyProductId) {
                    $this->sales->update($saleId, ['product_id' => $resolvedProductId]);
                    $ledgerCreditId = (int) ($sale['ledger_credit_movement_id'] ?? 0);
                    if ($ledgerCreditId > 0) {
                        $this->ledger->update($ledgerCreditId, ['product_id' => $resolvedProductId]);
                    }
                    $sale['product_id'] = $resolvedProductId;
                }

                $isLegacyException = $this->isLegacyPaidReturnExceptionSale($sale);

                $this->payoutItems->create([
                    'payout_id'           => $payoutId,
                    'consignment_sale_id' => $saleId,
                    'product_id'          => $resolvedProductId > 0 ? $resolvedProductId : (int) ($sale['product_id'] ?? 0),
                    'order_id'            => (int) ($sale['order_id'] ?? 0),
                    'order_item_id'       => (int) ($sale['order_item_id'] ?? 0),
                    'amount'              => (float) ($sale['credit_amount'] ?? 0),
                    'percent_applied'     => (float) ($sale['percent_applied'] ?? 0),
                ]);

                $this->sales->markPaidByPayout([$saleId], $payoutId, $payoutDate);

                $productId = $resolvedProductId > 0 ? $resolvedProductId : (int) ($sale['product_id'] ?? 0);
                $currentStatus = $this->stateService->getCurrentStatus($productId);

                // Fallback: resolve real SKU via order_items when product_id is a legacy ID
                if ($currentStatus === null && $productId > 0) {
                    $realSku = $this->resolveRealProductSku($saleId, $productId);
                    if ($realSku > 0 && $realSku !== $productId) {
                        $currentStatus = $this->stateService->getCurrentStatus($realSku);
                        if ($currentStatus === 'vendido_pendente') {
                            $productId = $realSku;
                        }
                    }
                }

                if ($currentStatus === 'vendido_pendente') {
                    $this->stateService->transition($productId, 'vendido_pago', [
                        'user_id' => $userId,
                        'notes'   => '[' . $sourceTag . '] payout #' . $payoutId,
                    ]);
                }

                if ($isLegacyException && $productId > 0) {
                    $this->applyLegacyPaidReturnExceptionAdjustments(
                        $productId,
                        (int) ($sale['supplier_pessoa_id'] ?? 0),
                        $userId,
                        $payoutId,
                        $sourceTag
                    );
                }
            }

            // Link movement
            $this->pdo->prepare("UPDATE cupons_creditos_movimentos SET payout_id = ? WHERE id = ?")->execute([$payoutId, $movementId]);

            $this->pdo->commit();
            return (int) $payoutId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Link a legacy movement as reconciled without creating a new payout (sales already marked pago by Phase 4).
     */
    private function linkLegacyMovement(int $movementId, int $userId, string $notes = ''): void
    {
        $noteText = "\n[REVIEW " . date('Y-m-d H:i') . "] Vinculado como já reconciliado (Phase 4)" . ($notes ? ": {$notes}" : '') . " — user #{$userId}";
        $this->pdo->prepare("UPDATE cupons_creditos_movimentos SET event_notes = CONCAT(COALESCE(event_notes,''), ?) WHERE id = ?")->execute([$noteText, $movementId]);
    }

    /**
     * Meet-in-the-middle subset sum for sets of 20-40 items.
     * @return array|null matched sales or null
     */
    private function meetInTheMiddleSubsetSum(array $sales, int $targetCents): ?array
    {
        $n = count($sales);
        $half = intdiv($n, 2);
        $amounts = array_map(fn($s) => intval(round((float) $s['credit_amount'] * 100)), $sales);

        // Build left half sums
        $leftSums = []; // sum => mask
        $leftN = $half;
        for ($mask = 0; $mask < (1 << $leftN); $mask++) {
            $s = 0;
            for ($i = 0; $i < $leftN; $i++) {
                if ($mask & (1 << $i)) $s += $amounts[$i];
            }
            $leftSums[$s] = $mask;
        }

        // Scan right half
        $rightN = $n - $half;
        for ($mask = 0; $mask < (1 << $rightN); $mask++) {
            $s = 0;
            for ($i = 0; $i < $rightN; $i++) {
                if ($mask & (1 << $i)) $s += $amounts[$half + $i];
            }
            $need = $targetCents - $s;
            if ($need >= 0 && isset($leftSums[$need]) && ($leftSums[$need] > 0 || $mask > 0)) {
                $result = [];
                $lm = $leftSums[$need];
                for ($i = 0; $i < $leftN; $i++) {
                    if ($lm & (1 << $i)) $result[] = $sales[$i];
                }
                for ($i = 0; $i < $rightN; $i++) {
                    if ($mask & (1 << $i)) $result[] = $sales[$half + $i];
                }
                if (!empty($result)) return $result;
            }
        }

        return null;
    }

    /**
     * Build diagnostic info for Category D movements.
     */
    private function buildDiagnostic(array $mov, array $amountsCents, int $targetCents, array $pendingSales = []): array
    {
        $diagnostic = [];
        $payoutAmount = (float) $mov['credit_amount'];
        $pendingTotal = (float) $mov['pending_total'];
        $pagoTotal = (float) $mov['pago_total'];

        if (abs($pagoTotal - $payoutAmount) < 0.01) {
            $diagnostic['type'] = 'already_reconciled';
            $diagnostic['message'] = 'Vendas pago já somam exatamente o valor do payout. Já reconciliado pelo Phase 4.';
        } else {
            // Try FIFO
            $sorted = $amountsCents;
            sort($sorted);
            $fifoSum = 0;
            $fifoCount = 0;
            foreach ($sorted as $a) {
                if ($fifoSum + $a <= $targetCents) {
                    $fifoSum += $a;
                    $fifoCount++;
                }
            }
            $fifoGap = $targetCents - $fifoSum;

            // Near-miss: best sum closest to target
            $n = count($amountsCents);
            $bestDiff = PHP_INT_MAX;
            $bestSum = 0;
            $bestMask = 0;
            if ($n <= 20) {
                for ($mask = 1; $mask < (1 << $n); $mask++) {
                    $s = 0;
                    for ($i = 0; $i < $n; $i++) {
                        if ($mask & (1 << $i)) $s += $amountsCents[$i];
                    }
                    $diff = abs($s - $targetCents);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestSum = $s;
                        $bestMask = $mask;
                    }
                }
            }

            $diagnostic['type'] = 'no_exact_match';
            $diagnostic['fifo_sum'] = $fifoSum / 100;
            $diagnostic['fifo_count'] = $fifoCount;
            $diagnostic['fifo_gap'] = $fifoGap / 100;
            $diagnostic['near_miss_sum'] = $bestSum > 0 ? $bestSum / 100 : null;
            $diagnostic['near_miss_diff'] = $bestDiff < PHP_INT_MAX ? $bestDiff / 100 : null;
            $diagnostic['pending_count'] = $n;
            $diagnostic['message'] = "Nenhum subconjunto exato encontrado.";

            // Extract near-miss sale IDs
            $nearMissSaleIds = [];
            if ($bestDiff < PHP_INT_MAX && $bestDiff <= 100 && !empty($pendingSales)) {
                for ($i = 0; $i < $n; $i++) {
                    if ($bestMask & (1 << $i)) {
                        $nearMissSaleIds[] = (int) $pendingSales[$i]['id'];
                    }
                }
                $diagnostic['message'] .= " Near-miss: R\$ " . number_format($bestSum / 100, 2, ',', '.') . " (diff R\$ " . number_format($bestDiff / 100, 2, ',', '.') . ").";
            }
            $diagnostic['near_miss_sale_ids'] = $nearMissSaleIds;
        }

        return $diagnostic;
    }

    /* =====================================================================
     * 14. REINDEX
     * ===================================================================== */

    public function reindex(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        try {
            // Reindex: sync consignment_status from registry to products
            $sql = "UPDATE products p
                    INNER JOIN consignment_product_registry r ON r.product_id = p.sku
                    SET p.consignment_status = r.consignment_status
                    WHERE p.consignment_status IS NULL
                       OR p.consignment_status != r.consignment_status";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $synced = $stmt->rowCount();

            $_SESSION['flash_success'] = 'Reindexação concluída. ' . $synced . ' produto(s) sincronizado(s).';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Erro na reindexação: ' . $e->getMessage();
        }

        header('Location: consignacao-inconsistencias.php');
        exit;
    }

    /* =====================================================================
     * 15. BULK ACTION (from Products page)
     * ===================================================================== */

    private function handleBulkAction(array &$errors, string &$success): void
    {
        $action = trim((string) ($_POST['bulk_action'] ?? ''));
        $productIds = array_map('intval', (array) ($_POST['product_ids'] ?? []));
        $productIds = array_filter($productIds);

        if (empty($productIds)) {
            $errors[] = 'Selecione pelo menos um produto.';
            return;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $justification = trim((string) ($_POST['justification'] ?? ''));

        switch ($action) {
            case 'devolver_fornecedor':
                Auth::requirePermission('consignment_module.edit_product_state', $this->pdo);
                $this->bulkTransitionToWriteoff($productIds, 'devolvido', 'devolucao_fornecedor', $userId, $justification, $errors, $success);
                break;

            case 'doar':
                Auth::requirePermission('consignment_module.edit_product_state', $this->pdo);
                $this->bulkTransitionToWriteoff($productIds, 'doado', 'doacao', $userId, $justification, $errors, $success);
                break;

            case 'descartar':
                Auth::requirePermission('consignment_module.edit_product_state', $this->pdo);
                $this->bulkTransitionToWriteoff($productIds, 'descartado', 'lixo', $userId, $justification, $errors, $success);
                break;

            case 'reativar_consignado':
                Auth::requirePermission('consignment_module.admin_override', $this->pdo);
                if ($justification === '') {
                    $errors[] = 'Justificativa obrigatória para reativar como consignado.';
                    return;
                }
                $this->bulkReactivate($productIds, $userId, $justification, $errors, $success);
                break;

            default:
                $errors[] = 'Ação em lote inválida.';
        }
    }

    private function bulkTransitionToWriteoff(array $productIds, string $targetStatus, string $destination, int $userId, string $justification, array &$errors, string &$success): void
    {
        $processed = 0;
        $skipped = [];

        foreach ($productIds as $productId) {
            $currentStatus = $this->stateService->getCurrentStatus($productId);

            // Block writeoff for vendido_pendente (spec rule 4.6b)
            if ($currentStatus === 'vendido_pendente') {
                $sale = $this->sales->findActiveByProduct($productId);
                $orderId = $sale ? (int) ($sale['order_id'] ?? 0) : 0;
                $skipped[] = "Produto #{$productId}: venda ativa (Pedido #{$orderId}). Registre devolução/cancelamento do pedido primeiro.";
                continue;
            }

            // Block writeoff for vendido_pago without admin_override (spec rule 4.6a)
            if ($currentStatus === 'vendido_pago' && $destination === 'devolucao_fornecedor') {
                $skipped[] = "Produto #{$productId}: já pago à fornecedora — requer permissão administrativa.";
                continue;
            }

            // Check if transition is valid
            if (!$this->stateService->isAllowed($currentStatus ?? '', $targetStatus)) {
                $skipped[] = "Produto #{$productId}: transição {$currentStatus} → {$targetStatus} não permitida.";
                continue;
            }

            try {
                $this->stateService->transition($productId, $targetStatus, [
                    'user_id' => $userId,
                    'notes'   => $justification ?: "Bulk action: {$destination}",
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $skipped[] = "Produto #{$productId}: " . $e->getMessage();
            }
        }

        if ($processed > 0) {
            $success = "{$processed} produto(s) processado(s) com sucesso.";
        }
        if (!empty($skipped)) {
            foreach ($skipped as $msg) {
                $errors[] = $msg;
            }
        }
    }

    private function bulkReactivate(array $productIds, int $userId, string $justification, array &$errors, string &$success): void
    {
        $processed = 0;
        foreach ($productIds as $productId) {
            try {
                $this->stateService->transition($productId, 'em_estoque', [
                    'admin_override' => true,
                    'user_id'        => $userId,
                    'notes'          => $justification,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Produto #{$productId}: " . $e->getMessage();
            }
        }

        if ($processed > 0) {
            $success = "{$processed} produto(s) reativado(s) como consignado(s).";
        }
    }

    /* =====================================================================
     * 16. PERIOD LOCK / UNLOCK
     * ===================================================================== */

    public function periodLock(): void
    {
        $yearMonth = trim((string) ($_POST['year_month'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $_SESSION['flash_error'] = 'Período inválido. Use formato AAAA-MM.';
            header('Location: consignacao-inconsistencias.php');
            exit;
        }

        try {
            $this->periodLockService->lock($yearMonth, $userId, $notes ?: null);
            $_SESSION['flash_success'] = "Período {$yearMonth} fechado com sucesso.";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Erro ao fechar período: ' . $e->getMessage();
        }

        header('Location: consignacao-inconsistencias.php');
        exit;
    }

    public function periodUnlock(): void
    {
        $yearMonth = trim((string) ($_POST['year_month'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        if ($reason === '') {
            $_SESSION['flash_error'] = 'Motivo obrigatório para reabrir período.';
            header('Location: consignacao-inconsistencias.php');
            exit;
        }

        try {
            $this->periodLockService->unlock($yearMonth, $userId, $reason);
            $_SESSION['flash_success'] = "Período {$yearMonth} reaberto.";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Erro ao reabrir período: ' . $e->getMessage();
        }

        header('Location: consignacao-inconsistencias.php');
        exit;
    }

    /* =====================================================================
     * HELPERS — Filters
     * ===================================================================== */

    private function buildVendorReportFilters(): array
    {
        $filters = [];
        if (!empty($_GET['consignment_status'])) {
            $filters['consignment_status'] = trim((string) $_GET['consignment_status']);
        }
        if (!empty($_GET['payout_status'])) {
            $filters['payout_status'] = trim((string) $_GET['payout_status']);
        }
        if (!empty($_GET['q'])) {
            $filters['search'] = trim((string) $_GET['q']);
        }
        return $filters;
    }

    private function buildProductFilters(): array
    {
        $filters = [];
        if (!empty($_GET['supplier_pessoa_id'])) $filters['supplier_pessoa_id'] = (int) $_GET['supplier_pessoa_id'];
        if (!empty($_GET['consignment_status'])) $filters['consignment_status'] = trim((string) $_GET['consignment_status']);
        if (!empty($_GET['product_status'])) $filters['product_status'] = trim((string) $_GET['product_status']);
        if (!empty($_GET['date_from'])) $filters['date_from'] = trim((string) $_GET['date_from']);
        if (!empty($_GET['date_to'])) $filters['date_to'] = trim((string) $_GET['date_to']);
        if (!empty($_GET['aging_min'])) $filters['aging_min'] = (int) $_GET['aging_min'];
        if (!empty($_GET['aging_max'])) $filters['aging_max'] = (int) $_GET['aging_max'];
        if (!empty($_GET['q'])) $filters['search'] = trim((string) $_GET['q']);
        if (!empty($_GET['filter_sku'])) $filters['filter_sku'] = trim((string) $_GET['filter_sku']);
        if (!empty($_GET['filter_product_name'])) $filters['filter_product_name'] = trim((string) $_GET['filter_product_name']);
        if (!empty($_GET['filter_supplier_name'])) $filters['filter_supplier_name'] = trim((string) $_GET['filter_supplier_name']);
        if (!empty($_GET['filter_consignment_status'])) $filters['filter_consignment_status'] = trim((string) $_GET['filter_consignment_status']);
        return $filters;
    }

    private function buildSaleFilters(): array
    {
        $filters = [];
        if (!empty($_GET['supplier_pessoa_id'])) $filters['supplier_pessoa_id'] = (int) $_GET['supplier_pessoa_id'];
        if (!empty($_GET['sold_from'])) $filters['sold_from'] = trim((string) $_GET['sold_from']);
        if (!empty($_GET['sold_to'])) $filters['sold_to'] = trim((string) $_GET['sold_to']);
        if (!empty($_GET['paid_from'])) $filters['paid_from'] = trim((string) $_GET['paid_from']);
        if (!empty($_GET['paid_to'])) $filters['paid_to'] = trim((string) $_GET['paid_to']);
        if (!empty($_GET['sale_status'])) $filters['sale_status'] = trim((string) $_GET['sale_status']);
        if (!empty($_GET['payout_status'])) $filters['payout_status'] = trim((string) $_GET['payout_status']);
        if (!empty($_GET['q'])) $filters['search'] = trim((string) $_GET['q']);
        if (!empty($_GET['filter_order_id'])) $filters['filter_order_id'] = trim((string) $_GET['filter_order_id']);
        if (!empty($_GET['filter_sku'])) $filters['filter_sku'] = trim((string) $_GET['filter_sku']);
        if (!empty($_GET['filter_product_name'])) $filters['filter_product_name'] = trim((string) $_GET['filter_product_name']);
        if (!empty($_GET['filter_supplier_name'])) $filters['filter_supplier_name'] = trim((string) $_GET['filter_supplier_name']);
        if (!empty($_GET['filter_sale_status'])) $filters['filter_sale_status'] = trim((string) $_GET['filter_sale_status']);
        if (!empty($_GET['filter_payout_status'])) $filters['filter_payout_status'] = trim((string) $_GET['filter_payout_status']);
        return $filters;
    }

    /* =====================================================================
     * HELPERS — Payout Form POST handling
     * ===================================================================== */

    private function handlePayoutFormPost(?array $existingPayout, array &$errors, string &$success): void
    {
        $supplierPessoaId = (int) ($_POST['supplier_pessoa_id'] ?? 0);
        $saleIds = array_map('intval', (array) ($_POST['sale_ids'] ?? []));
        $saleIds = array_values(array_unique(array_filter($saleIds, static fn (int $id): bool => $id > 0)));

        $payoutDate = trim((string) ($_POST['payout_date'] ?? date('Y-m-d')));
        $method = trim((string) ($_POST['method'] ?? 'pix'));
        $pixKey = trim((string) ($_POST['pix_key'] ?? ''));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $originBankAccountId = (int) ($_POST['origin_bank_account_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $action = trim((string) ($_POST['submit_action'] ?? 'draft'));

        if ($action === 'save_supplier_pix') {
            $this->saveSupplierPixKey($supplierPessoaId, $pixKey, $errors, $success);
            return;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        $data = [
            'supplier_pessoa_id'     => $supplierPessoaId,
            'payout_date'            => $payoutDate,
            'method'                 => $method,
            'pix_key'                => $pixKey,
            'reference'              => $reference,
            'origin_bank_account_id' => $originBankAccountId,
            'notes'                  => $notes,
            'created_by'             => $userId,
        ];

        // Resolve the supplier's consignment voucher account
        $voucherAccountId = (int) ($_POST['voucher_account_id'] ?? 0);
        if ($voucherAccountId <= 0 && $supplierPessoaId > 0) {
            $voucherAccountId = $this->resolveConsignmentVoucherAccount($supplierPessoaId);
        }
        if ($voucherAccountId > 0) {
            $data['voucher_account_id'] = $voucherAccountId;
        } else {
            $errors[] = 'Não foi possível localizar a conta de crédito consignação da fornecedora. Verifique se existe uma conta voucher com scope=consignacao.';
            return;
        }

        if ($action === 'confirm' || $action === 'edit_confirmed') {
            Auth::requirePermission('consignment_module.confirm_payout', $this->pdo);
        }

        if ($existingPayout) {
            $payoutId = (int) $existingPayout['id'];
            $existingSupplierPessoaId = (int) ($existingPayout['supplier_pessoa_id'] ?? 0);
            if ($existingSupplierPessoaId > 0 && $supplierPessoaId !== $existingSupplierPessoaId) {
                $errors[] = 'Não é permitido alterar a fornecedora de um pagamento existente.';
                return;
            }

            $existingStatus = (string) ($existingPayout['status'] ?? '');
            if ($existingStatus === 'confirmado') {
                $allowConfirmedEditRaw = strtolower(trim((string) ($_POST['allow_confirmed_edit'] ?? '')));
                $allowConfirmedEdit = in_array($allowConfirmedEditRaw, ['1', 'true', 'yes', 'on'], true);
                if (!$allowConfirmedEdit) {
                    $errors[] = 'Edição de PIX confirmado não autorizada nesta tela.';
                    return;
                }

                $result = $this->payoutService->reprocessConfirmed($payoutId, $data, $saleIds, $userId);
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                    return;
                }
                $success = 'Pagamento PIX #' . $payoutId . ' reprocessado com sucesso. Itens e vínculos foram atualizados.';
                return;
            }
            if ($existingStatus !== 'rascunho') {
                $errors[] = 'Somente pagamentos em rascunho podem ser editados neste fluxo.';
                return;
            }

            $validation = $this->validateDraftSalesSelection($supplierPessoaId, $saleIds);
            if (!empty($validation['errors'])) {
                $errors = array_merge($errors, $validation['errors']);
                return;
            }
            $validatedSales = $validation['sales'];
            $totalAmount = (float) ($validation['total_amount'] ?? 0);

            // Update existing draft — delete old items and recreate with validated sales
            $this->payoutItems->deleteByPayout($payoutId);
            $data['total_amount'] = round($totalAmount, 2);
            $data['items_count'] = count($validatedSales);
            $this->payouts->update($payoutId, $data);

            foreach ($validatedSales as $sale) {
                $amount = (float) ($sale['credit_amount'] ?? 0);
                $this->payoutItems->create([
                    'payout_id'           => $payoutId,
                    'consignment_sale_id' => (int) ($sale['id'] ?? 0),
                    'product_id'          => (int) ($sale['product_id'] ?? 0),
                    'order_id'            => (int) ($sale['order_id'] ?? 0),
                    'order_item_id'       => (int) ($sale['order_item_id'] ?? 0),
                    'amount'              => $amount,
                    'percent_applied'     => (float) ($sale['percent_applied'] ?? 0),
                ]);
            }

            if ($action === 'confirm') {
                $result = $this->payoutService->confirm($payoutId, $userId);
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                    return;
                }
                $success = 'Pagamento #' . $payoutId . ' confirmado com sucesso.';
            } else {
                $success = 'Rascunho #' . $payoutId . ' atualizado.';
            }
        } else {
            // Create new draft
            $result = $this->payoutService->createDraft($data, $saleIds);
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
                return;
            }

            $payoutId = $result['payout_id'] ?? 0;

            if ($action === 'confirm' && $payoutId > 0) {
                $confirmResult = $this->payoutService->confirm($payoutId, $userId);
                if (!empty($confirmResult['errors'])) {
                    $errors = array_merge($errors, $confirmResult['errors']);
                    return;
                }
                $success = 'Pagamento #' . $payoutId . ' criado e confirmado com sucesso.';
            } else {
                $success = 'Rascunho #' . $payoutId . ' salvo.';
            }
        }
    }

    private function saveSupplierPixKey(int $supplierPessoaId, string $pixKey, array &$errors, string &$success): void
    {
        if ($supplierPessoaId <= 0) {
            $errors[] = 'Selecione a fornecedora para cadastrar a chave PIX.';
            return;
        }

        $pixKey = trim($pixKey);
        if ($pixKey === '') {
            $errors[] = 'Informe a chave PIX para salvar no cadastro da fornecedora.';
            return;
        }

        $person = $this->persons->find($supplierPessoaId);
        if (!$person) {
            $errors[] = 'Fornecedora não encontrada.';
            return;
        }

        $person->pixKey = $pixKey;

        try {
            $this->persons->save($person);
            $success = 'Chave PIX salva no cadastro da fornecedora.';
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao salvar chave PIX no cadastro da fornecedora: ' . $e->getMessage();
        }
    }

    /**
     * Validate selected sales before creating/updating a payout draft.
     *
     * @param int[] $saleIds
     * @return array{sales: array<int, array<string, mixed>>, total_amount: float, errors: array<int, string>}
     */
    private function validateDraftSalesSelection(int $supplierPessoaId, array $saleIds): array
    {
        $errors = [];
        $validSales = [];
        $totalAmount = 0.0;

        if ($supplierPessoaId <= 0) {
            return [
                'sales' => [],
                'total_amount' => 0.0,
                'errors' => ['Fornecedora inválida.'],
            ];
        }

        if (empty($saleIds)) {
            return [
                'sales' => [],
                'total_amount' => 0.0,
                'errors' => ['Selecione pelo menos um item de venda para o pagamento.'],
            ];
        }

        foreach ($saleIds as $saleId) {
            $sale = $this->sales->find((int) $saleId);
            if (!$sale) {
                $errors[] = "Venda #{$saleId} não encontrada.";
                continue;
            }
            if ((int) ($sale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                $errors[] = "Venda #{$saleId} pertence a outra fornecedora.";
                continue;
            }
            if (($sale['sale_status'] ?? '') !== 'ativa') {
                $errors[] = "Venda #{$saleId} já foi revertida.";
                continue;
            }
            if (($sale['payout_status'] ?? '') !== 'pendente') {
                $errors[] = "Venda #{$saleId} já foi paga.";
                continue;
            }
            if ($this->periodLockService->isDateInLockedPeriod($sale['sold_at'] ?? null)) {
                $yearMonth = substr((string) ($sale['sold_at'] ?? ''), 0, 7);
                $errors[] = "Venda #{$saleId} pertence ao período {$yearMonth} que está fechado.";
                continue;
            }

            $validSales[] = $sale;
            $totalAmount += (float) ($sale['credit_amount'] ?? 0);
        }

        return [
            'sales' => $validSales,
            'total_amount' => $totalAmount,
            'errors' => $errors,
        ];
    }

    /**
     * @param int[] $saleIds
     * @return array{sales: array<int, array<string, mixed>>, total_amount: float, errors: array<int, string>}
     */
    private function collectPreviewSalesSelection(int $supplierPessoaId, array $saleIds): array
    {
        $errors = [];
        $selectedSales = [];
        $totalAmount = 0.0;

        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn (int $id): bool => $id > 0)));

        if ($supplierPessoaId <= 0) {
            $errors[] = 'Fornecedora inválida para gerar a prévia.';
            return [
                'sales' => [],
                'total_amount' => 0.0,
                'errors' => $errors,
            ];
        }

        if (empty($saleIds)) {
            $errors[] = 'Marque pelo menos um item para gerar a prévia.';
            return [
                'sales' => [],
                'total_amount' => 0.0,
                'errors' => $errors,
            ];
        }

        foreach ($saleIds as $saleId) {
            $sale = $this->sales->find((int) $saleId);
            if (!$sale) {
                $errors[] = "Venda #{$saleId} não encontrada.";
                continue;
            }
            if ((int) ($sale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                $errors[] = "Venda #{$saleId} pertence a outra fornecedora.";
                continue;
            }

            $selectedSales[] = $sale;
            $totalAmount += (float) ($sale['credit_amount'] ?? 0);
        }

        return [
            'sales' => $selectedSales,
            'total_amount' => $totalAmount,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @param array<string, mixed> $overrides
     * @return array{payout: array<string, mixed>, items: array<int, array<string, mixed>>, hash: string, preview_generated_at: string}
     */
    private function buildPreviewReceiptData(int $supplierPessoaId, array $sales, array $overrides = []): array
    {
        $receiptItems = [];
        $totalAmount = 0.0;
        $saleIdList = [];

        foreach ($sales as $sale) {
            $saleId = (int) ($sale['id'] ?? 0);
            if ($saleId > 0) {
                $saleIdList[] = $saleId;
            }
            $amount = (float) ($sale['credit_amount'] ?? 0);
            $totalAmount += $amount;

            $receiptItems[] = [
                'consignment_sale_id' => $saleId,
                'product_id' => (int) ($sale['product_id'] ?? 0),
                'order_id' => (int) ($sale['order_id'] ?? 0),
                'order_item_id' => (int) ($sale['order_item_id'] ?? 0),
                'amount' => $amount,
                'percent_applied' => (float) ($sale['percent_applied'] ?? 0),
                'sku' => (string) ($sale['sku'] ?? ''),
                'product_name' => (string) ($sale['product_name'] ?? ''),
                'sale' => $sale,
            ];
        }

        $previewGeneratedAt = (string) ($overrides['preview_generated_at'] ?? date('Y-m-d H:i:s'));
        $hashSeed = $supplierPessoaId . '|' . implode(',', $saleIdList) . '|' . round($totalAmount, 2) . '|' . $previewGeneratedAt;

        return [
            'payout' => [
                'id' => (int) ($overrides['id'] ?? 0),
                'supplier_pessoa_id' => $supplierPessoaId,
                'payout_date' => (string) ($overrides['payout_date'] ?? date('Y-m-d')),
                'method' => (string) ($overrides['method'] ?? 'pix'),
                'total_amount' => round($totalAmount, 2),
                'items_count' => count($receiptItems),
                'status' => 'previa',
                'reference' => (string) ($overrides['reference'] ?? ''),
                'pix_key' => (string) ($overrides['pix_key'] ?? ''),
                'notes' => (string) ($overrides['notes'] ?? ''),
                'confirmed_at' => null,
            ],
            'items' => $receiptItems,
            'hash' => md5($hashSeed),
            'preview_generated_at' => $previewGeneratedAt,
        ];
    }

    /**
     * Renderiza o espelho prévio para HTML completo (com layout de impressão).
     */
    private function renderPreviewReceiptHtml(array $receipt, ?object $supplierPerson, array $errors = [], bool $hidePrintActions = false): string
    {
        ob_start();
        View::render('consignment_module/payout_receipt_print', [
            'receipt' => $receipt,
            'supplierPerson' => $supplierPerson,
            'documentMode' => 'preview',
            'errors' => $errors,
            'hidePrintActions' => $hidePrintActions,
        ], [
            'title' => 'Prévia de Pagamento de Consignação',
            'layout' => __DIR__ . '/../Views/print-layout.php',
        ]);
        return (string) ob_get_clean();
    }

    private function redirectBackToPayoutFormWithMessage(string $message, string $soldFrom = '', string $soldTo = '', bool $success = false): void
    {
        $query = [];
        if ($soldFrom !== '') {
            $query['sold_from'] = $soldFrom;
        }
        if ($soldTo !== '') {
            $query['sold_to'] = $soldTo;
        }
        $url = 'consignacao-pagamento-cadastro.php';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        if ($success) {
            $_SESSION['flash_success'] = $message;
        } else {
            $_SESSION['flash_error'] = $message;
        }
        header('Location: ' . $url);
        exit;
    }

    private function createTemporaryExportDir(string $prefix): string
    {
        $baseTmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $dir = $baseTmp . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório temporário para exportação.');
        }
        return $dir;
    }

    private function resolveSofficeBinary(): ?string
    {
        $candidates = [];

        $envBin = trim((string) (getenv('APP_SOFFICE_BIN') ?: ''));
        if ($envBin !== '') {
            $candidates[] = $envBin;
        }

        if (function_exists('shell_exec')) {
            $which = trim((string) @shell_exec('command -v soffice 2>/dev/null'));
            if ($which !== '') {
                $candidates[] = $which;
            }
        }

        $candidates[] = '/opt/homebrew/bin/soffice';
        $candidates[] = '/usr/local/bin/soffice';
        $candidates[] = '/usr/bin/soffice';

        $candidates = array_values(array_unique(array_filter($candidates, static fn (string $v): bool => trim($v) !== '')));
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function convertHtmlToPdfWithSoffice(string $sofficeBin, string $htmlPath, string $outputDir): ?string
    {
        if (!is_file($htmlPath) || !is_dir($outputDir)) {
            return null;
        }

        $command = escapeshellarg($sofficeBin)
            . ' --headless --convert-to pdf --outdir '
            . escapeshellarg($outputDir) . ' '
            . escapeshellarg($htmlPath) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $expectedPdfPath = $outputDir . DIRECTORY_SEPARATOR . pathinfo($htmlPath, PATHINFO_FILENAME) . '.pdf';
        if ($exitCode !== 0 || !is_file($expectedPdfPath)) {
            error_log('ConsignmentModuleController::convertHtmlToPdfWithSoffice fail: ' . implode(' | ', $output));
            return null;
        }

        return $expectedPdfPath;
    }

    private function buildBatchPreviewFileBaseName(string $supplierName, string $exportStamp, int $piecesCount, float $totalCommission): string
    {
        $supplierToken = $this->sanitizeFileToken($supplierName);
        $commissionToken = str_replace('.', '-', number_format($totalCommission, 2, '.', ''));
        $piecesToken = max(0, $piecesCount) . 'pecas';
        return 'espelho-consignacao_' . $supplierToken . '_' . $exportStamp . '_' . $piecesToken . '_comissao-' . $commissionToken;
    }

    private function sanitizeFileToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'sem-nome';
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized !== false && is_string($normalized)) {
            $value = $normalized;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'sem-nome';
    }

    /**
     * @param array<int, array<string, mixed>> $summaryRows
     */
    private function buildBatchPreviewSummaryCsv(array $summaryRows, string $exportAt, bool $pdfConverterAvailable): string
    {
        $temp = fopen('php://temp', 'r+');
        if ($temp === false) {
            return '';
        }

        fputcsv($temp, [
            'exported_at',
            'pdf_converter',
            'supplier_id',
            'supplier_name',
            'pieces_count',
            'total_commission',
            'file_name',
            'format',
            'status',
        ], ';');

        foreach ($summaryRows as $row) {
            fputcsv($temp, [
                $exportAt,
                $pdfConverterAvailable ? 'available' : 'missing',
                (int) ($row['supplier_id'] ?? 0),
                (string) ($row['supplier_name'] ?? ''),
                (int) ($row['pieces_count'] ?? 0),
                number_format((float) ($row['total_commission'] ?? 0), 2, '.', ''),
                (string) ($row['file_name'] ?? ''),
                (string) ($row['format'] ?? ''),
                (string) ($row['status'] ?? ''),
            ], ';');
        }

        rewind($temp);
        $csv = stream_get_contents($temp);
        fclose($temp);
        return (string) $csv;
    }

    private function streamDownloadFile(string $path, string $downloadName, string $contentType): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Arquivo de exportação não encontrado para download.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectoryRecursive($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($dir);
    }

    /* =====================================================================
     * HELPERS — Reporting
     * ===================================================================== */

    private function buildVendorReport(int $supplierPessoaId, string $dateFrom, string $dateTo, string $dateField, array $filters = []): array
    {
        $pdo = $this->pdo;

        // Summary metrics
        $report = [
            'supplier_pessoa_id' => $supplierPessoaId,
            'summary' => [],
            'items'   => [],
        ];

        if (!$pdo) {
            return $report;
        }

        $supplierIds = $this->resolveVendorReportSupplierIds($supplierPessoaId);
        if (empty($supplierIds)) {
            $supplierIds = [$supplierPessoaId];
        }

        // Items in stock
        [$inStockScopeSql, $inStockScopeParams] = $this->buildVendorRegistrySupplierScopeSql($supplierIds, 'sum_stock');
        $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(p.price), 0) as total_value
                FROM consignment_product_registry r
                INNER JOIN products p ON p.sku = r.product_id
                WHERE {$inStockScopeSql}
                  AND r.consignment_status = 'em_estoque'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($inStockScopeParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['summary']['in_stock_count'] = (int) ($row['cnt'] ?? 0);
        $report['summary']['in_stock_value'] = (float) ($row['total_value'] ?? 0);

        // Sold items pending payment
        [$soldPendingInSql, $soldPendingParams] = $this->buildNamedInClause('sum_pending_sid', $supplierIds);
        $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(cs.credit_amount), 0) as total_credit
                FROM consignment_sales cs
                WHERE cs.supplier_pessoa_id IN ({$soldPendingInSql})
                  AND cs.sale_status = 'ativa'
                  AND cs.payout_status = 'pendente'";
        $params = $soldPendingParams;
        if ($dateFrom !== '') {
            $sql .= " AND cs.sold_at >= :df";
            $params[':df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $sql .= " AND cs.sold_at <= :dt";
            $params[':dt'] = $dateTo . ' 23:59:59';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['summary']['sold_pending_count'] = (int) ($row['cnt'] ?? 0);
        $report['summary']['sold_pending_credit'] = (float) ($row['total_credit'] ?? 0);

        // Sold items paid
        [$soldPaidInSql, $soldPaidParams] = $this->buildNamedInClause('sum_paid_sid', $supplierIds);
        $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(cs.credit_amount), 0) as total_credit
                FROM consignment_sales cs
                WHERE cs.supplier_pessoa_id IN ({$soldPaidInSql})
                  AND cs.payout_status = 'pago'";
        $params = $soldPaidParams;
        if ($dateFrom !== '') {
            $sql .= " AND cs.paid_at >= :df";
            $params[':df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $sql .= " AND cs.paid_at <= :dt";
            $params[':dt'] = $dateTo . ' 23:59:59';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['summary']['sold_paid_count'] = (int) ($row['cnt'] ?? 0);
        $report['summary']['sold_paid_credit'] = (float) ($row['total_credit'] ?? 0);

        // Returned to supplier
        [$returnedScopeSql, $returnedScopeParams] = $this->buildVendorRegistrySupplierScopeSql($supplierIds, 'sum_ret');
        $sql = "SELECT COUNT(*) as cnt FROM consignment_product_registry r
                WHERE {$returnedScopeSql} AND r.consignment_status = 'devolvido'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($returnedScopeParams);
        $report['summary']['returned_count'] = (int) ($stmt->fetchColumn() ?: 0);

        // Donated
        [$donatedScopeSql, $donatedScopeParams] = $this->buildVendorRegistrySupplierScopeSql($supplierIds, 'sum_don');
        $sql = "SELECT COUNT(*) as cnt FROM consignment_product_registry r
                WHERE {$donatedScopeSql} AND r.consignment_status = 'doado'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($donatedScopeParams);
        $report['summary']['donated_count'] = (int) ($stmt->fetchColumn() ?: 0);

        // Avg aging of in-stock items
        [$agingScopeSql, $agingScopeParams] = $this->buildVendorRegistrySupplierScopeSql($supplierIds, 'sum_aging');
        $sql = "SELECT AVG(DATEDIFF(NOW(), r.received_at)) as avg_aging
                FROM consignment_product_registry r
                WHERE {$agingScopeSql}
                  AND r.consignment_status = 'em_estoque'
                  AND r.received_at IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($agingScopeParams);
        $report['summary']['avg_aging_days'] = round((float) ($stmt->fetchColumn() ?: 0), 1);

        // Detail items
        [$detailPoolRegistryScopeSql, $detailPoolRegistryScopeParams] = $this->buildVendorRegistrySupplierScopeSql($supplierIds, 'det_pool_reg');
        [$detailPoolSalesInSql, $detailPoolSalesParams] = $this->buildNamedInClause('det_pool_sale_sid', $supplierIds);
        [$detailLatestSalesInSql, $detailLatestSalesParams] = $this->buildNamedInClause('det_latest_sale_sid', $supplierIds);

        $sql = "SELECT
                    pool.product_id,
                    COALESCE(
                        NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                        TRIM(CAST(pool.product_id AS CHAR))
                    ) AS sku,
                    COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', pool.product_id)) AS product_name,
                    COALESCE(p.price, p2.price, 0) AS price,
                    COALESCE(p.status, p2.status) AS product_status,
                    r.received_at,
                    COALESCE(
                        r.consignment_status,
                        CASE
                            WHEN ls.id IS NULL THEN ''
                            WHEN ls.payout_status = 'pago' THEN 'vendido_pago'
                            ELSE 'vendido_pendente'
                        END
                    ) AS consignment_status,
                    ls.sold_at,
                    ls.order_id,
                    ls.net_amount,
                    ls.percent_applied,
                    ls.credit_amount,
                    ls.payout_status,
                    ls.paid_at
                FROM (
                    SELECT DISTINCT r.product_id
                    FROM consignment_product_registry r
                    WHERE {$detailPoolRegistryScopeSql}
                    UNION
                    SELECT DISTINCT cs.product_id
                    FROM consignment_sales cs
                    WHERE cs.supplier_pessoa_id IN ({$detailPoolSalesInSql})
                ) pool
                LEFT JOIN consignment_product_registry r ON r.product_id = pool.product_id
                LEFT JOIN products p ON p.sku = pool.product_id
                LEFT JOIN (
                    SELECT cs_inner.*
                    FROM consignment_sales cs_inner
                    INNER JOIN (
                        SELECT product_id, MAX(id) AS latest_id
                        FROM consignment_sales
                        WHERE sale_status = 'ativa'
                          AND supplier_pessoa_id IN ({$detailLatestSalesInSql})
                        GROUP BY product_id
                    ) latest ON latest.latest_id = cs_inner.id
                ) ls ON ls.product_id = pool.product_id
                LEFT JOIN order_items oi ON oi.id = ls.order_item_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE 1=1";

        $params = $detailPoolRegistryScopeParams + $detailPoolSalesParams + $detailLatestSalesParams;

        $statusFilter = trim((string) ($filters['consignment_status'] ?? ''));
        if ($statusFilter !== '') {
            $sql .= " AND COALESCE(r.consignment_status, CASE WHEN ls.id IS NULL THEN '' WHEN ls.payout_status = 'pago' THEN 'vendido_pago' ELSE 'vendido_pendente' END) = :filter_consignment_status";
            $params[':filter_consignment_status'] = $statusFilter;
        }

        $payoutFilter = trim((string) ($filters['payout_status'] ?? ''));
        if ($payoutFilter !== '') {
            $sql .= " AND COALESCE(ls.payout_status, '') = :filter_payout_status";
            $params[':filter_payout_status'] = $payoutFilter;
        }

        $searchFilter = trim((string) ($filters['search'] ?? ''));
        if ($searchFilter !== '') {
            $sql .= " AND (
                CAST(pool.product_id AS CHAR) LIKE :filter_search
                OR COALESCE(p.name, p2.name, oi.product_name, '') LIKE :filter_search
                OR CAST(COALESCE(p.sku, p2.sku, oi.product_sku, pool.product_id) AS CHAR) LIKE :filter_search
                OR CAST(COALESCE(ls.order_id, '') AS CHAR) LIKE :filter_search
                OR COALESCE(r.consignment_status, CASE WHEN ls.id IS NULL THEN '' WHEN ls.payout_status = 'pago' THEN 'vendido_pago' ELSE 'vendido_pendente' END) LIKE :filter_search
                OR COALESCE(ls.payout_status, '') LIKE :filter_search
            )";
            $params[':filter_search'] = '%' . $searchFilter . '%';
        }

        if ($dateField === 'received_at' && $dateFrom !== '') {
            $sql .= " AND r.received_at >= :df";
            $params[':df'] = $dateFrom;
        }
        if ($dateField === 'received_at' && $dateTo !== '') {
            $sql .= " AND r.received_at <= :dt";
            $params[':dt'] = $dateTo;
        }
        if ($dateField === 'sold_at' && $dateFrom !== '') {
            $sql .= " AND ls.sold_at >= :df";
            $params[':df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateField === 'sold_at' && $dateTo !== '') {
            $sql .= " AND ls.sold_at <= :dt";
            $params[':dt'] = $dateTo . ' 23:59:59';
        }
        if ($dateField === 'paid_at' && $dateFrom !== '') {
            $sql .= " AND ls.paid_at >= :df";
            $params[':df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateField === 'paid_at' && $dateTo !== '') {
            $sql .= " AND ls.paid_at <= :dt";
            $params[':dt'] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY COALESCE(r.received_at, DATE(ls.sold_at), DATE(ls.paid_at)) DESC, pool.product_id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $report['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $report;
    }

    /**
     * @param int[] $supplierIds
     * @return array<int, array<string, float|int>>
     */
    private function loadSupplierStatusBreakdown(array $supplierIds, ?string $soldFrom = null, ?string $soldTo = null): array
    {
        $cleanSupplierIds = array_values(array_unique(array_filter(array_map('intval', $supplierIds), static fn(int $id): bool => $id > 0)));
        if (empty($cleanSupplierIds)) {
            return [];
        }

        $defaults = [
            'in_stock_count' => 0,
            'in_stock_revenue_potential' => 0.0,
            'in_stock_commission_potential' => 0.0,
            'sold_pending_count' => 0,
            'sold_pending_revenue_potential' => 0.0,
            'sold_pending_commission_potential' => 0.0,
            'sold_paid_count' => 0,
            'sold_paid_revenue_effective' => 0.0,
            'sold_paid_commission_paid' => 0.0,
            'returned_count' => 0,
            'returned_revenue_potential' => 0.0,
            'returned_commission_potential' => 0.0,
            'donated_count' => 0,
            'donated_revenue_potential' => 0.0,
            'donated_commission_potential' => 0.0,
        ];

        $result = [];
        foreach ($cleanSupplierIds as $supplierId) {
            $result[$supplierId] = $defaults;
        }

        if (!$this->pdo) {
            return $result;
        }

        // Registry-based statuses: em_estoque, devolvido, doado (valores potenciais).
        [$registryInSql, $registryParams] = $this->buildNamedInClause('pay_form_reg_sid', $cleanSupplierIds);
        $registrySql = "SELECT r.supplier_pessoa_id AS supplier_id,
                               SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN 1 ELSE 0 END) AS in_stock_count,
                               SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN COALESCE(p.price, 0) ELSE 0 END) AS in_stock_revenue_potential,
                               SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN COALESCE(p.price, 0) * (COALESCE(NULLIF(r.consignment_percent_snapshot, 0), NULLIF(p.percentual_consignacao, 0), NULLIF(sp.avg_percent_applied, 0), 0) / 100) ELSE 0 END) AS in_stock_commission_potential,
                               SUM(CASE WHEN r.consignment_status = 'devolvido' THEN 1 ELSE 0 END) AS returned_count,
                               SUM(CASE WHEN r.consignment_status = 'devolvido' THEN COALESCE(p.price, 0) ELSE 0 END) AS returned_revenue_potential,
                               SUM(CASE WHEN r.consignment_status = 'devolvido' THEN COALESCE(p.price, 0) * (COALESCE(NULLIF(r.consignment_percent_snapshot, 0), NULLIF(p.percentual_consignacao, 0), NULLIF(sp.avg_percent_applied, 0), 0) / 100) ELSE 0 END) AS returned_commission_potential,
                               SUM(CASE WHEN r.consignment_status = 'doado' THEN 1 ELSE 0 END) AS donated_count,
                               SUM(CASE WHEN r.consignment_status = 'doado' THEN COALESCE(p.price, 0) ELSE 0 END) AS donated_revenue_potential,
                               SUM(CASE WHEN r.consignment_status = 'doado' THEN COALESCE(p.price, 0) * (COALESCE(NULLIF(r.consignment_percent_snapshot, 0), NULLIF(p.percentual_consignacao, 0), NULLIF(sp.avg_percent_applied, 0), 0) / 100) ELSE 0 END) AS donated_commission_potential
                        FROM consignment_product_registry r
                        LEFT JOIN products p ON p.sku = r.product_id
                        LEFT JOIN (
                            SELECT s.supplier_pessoa_id,
                                   AVG(NULLIF(s.percent_applied, 0)) AS avg_percent_applied
                            FROM consignment_sales s
                            WHERE s.sale_status = 'ativa'
                            GROUP BY s.supplier_pessoa_id
                        ) sp ON sp.supplier_pessoa_id = r.supplier_pessoa_id
                        WHERE r.supplier_pessoa_id IN ({$registryInSql})
                        GROUP BY r.supplier_pessoa_id";
        $registryStmt = $this->pdo->prepare($registrySql);
        $registryStmt->execute($registryParams);
        while ($row = $registryStmt->fetch(PDO::FETCH_ASSOC)) {
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                continue;
            }
            if (!isset($result[$supplierId])) {
                $result[$supplierId] = $defaults;
            }
            $result[$supplierId]['in_stock_count'] = (int) ($row['in_stock_count'] ?? 0);
            $result[$supplierId]['in_stock_revenue_potential'] = (float) ($row['in_stock_revenue_potential'] ?? 0);
            $result[$supplierId]['in_stock_commission_potential'] = (float) ($row['in_stock_commission_potential'] ?? 0);
            $result[$supplierId]['returned_count'] = (int) ($row['returned_count'] ?? 0);
            $result[$supplierId]['returned_revenue_potential'] = (float) ($row['returned_revenue_potential'] ?? 0);
            $result[$supplierId]['returned_commission_potential'] = (float) ($row['returned_commission_potential'] ?? 0);
            $result[$supplierId]['donated_count'] = (int) ($row['donated_count'] ?? 0);
            $result[$supplierId]['donated_revenue_potential'] = (float) ($row['donated_revenue_potential'] ?? 0);
            $result[$supplierId]['donated_commission_potential'] = (float) ($row['donated_commission_potential'] ?? 0);
        }

        // Sales-based statuses: vendido_pendente e vendido_pago.
        [$salesInSql, $salesParams] = $this->buildNamedInClause('pay_form_sale_sid', $cleanSupplierIds);
        $salesSql = "SELECT s.supplier_pessoa_id AS supplier_id,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pendente' THEN 1 ELSE 0 END) AS sold_pending_count,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pendente' THEN COALESCE(s.net_amount, 0) ELSE 0 END) AS sold_pending_revenue_potential,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pendente' THEN COALESCE(s.credit_amount, 0) ELSE 0 END) AS sold_pending_commission_potential,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pago' THEN 1 ELSE 0 END) AS sold_paid_count,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pago' THEN COALESCE(s.net_amount, 0) ELSE 0 END) AS sold_paid_revenue_effective,
                            SUM(CASE WHEN s.sale_status = 'ativa' AND s.payout_status = 'pago' THEN COALESCE(s.credit_amount, 0) ELSE 0 END) AS sold_paid_commission_paid
                     FROM consignment_sales s
                     WHERE s.supplier_pessoa_id IN ({$salesInSql})";
        $params = $salesParams;
        if ($soldFrom) {
            $salesSql .= " AND s.sold_at >= :pay_form_sold_from";
            $params[':pay_form_sold_from'] = $soldFrom . ' 00:00:00';
        }
        if ($soldTo) {
            $salesSql .= " AND s.sold_at <= :pay_form_sold_to";
            $params[':pay_form_sold_to'] = $soldTo . ' 23:59:59';
        }
        $salesSql .= " GROUP BY s.supplier_pessoa_id";
        $salesStmt = $this->pdo->prepare($salesSql);
        $salesStmt->execute($params);
        while ($row = $salesStmt->fetch(PDO::FETCH_ASSOC)) {
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                continue;
            }
            if (!isset($result[$supplierId])) {
                $result[$supplierId] = $defaults;
            }
            $result[$supplierId]['sold_pending_count'] = (int) ($row['sold_pending_count'] ?? 0);
            $result[$supplierId]['sold_pending_revenue_potential'] = (float) ($row['sold_pending_revenue_potential'] ?? 0);
            $result[$supplierId]['sold_pending_commission_potential'] = (float) ($row['sold_pending_commission_potential'] ?? 0);
            $result[$supplierId]['sold_paid_count'] = (int) ($row['sold_paid_count'] ?? 0);
            $result[$supplierId]['sold_paid_revenue_effective'] = (float) ($row['sold_paid_revenue_effective'] ?? 0);
            $result[$supplierId]['sold_paid_commission_paid'] = (float) ($row['sold_paid_commission_paid'] ?? 0);
        }

        // Sem recorte de período por venda, o "Com. paga" deve refletir o valor
        // oficial dos pagamentos já confirmados (consignment_payouts.total_amount).
        // Isso evita divergência quando há ajuste histórico/reconciliação.
        $hasSoldWindow = ($soldFrom !== null && trim($soldFrom) !== '')
            || ($soldTo !== null && trim($soldTo) !== '');
        if (!$hasSoldWindow) {
            [$payoutInSql, $payoutParams] = $this->buildNamedInClause('pay_form_payout_sid', $cleanSupplierIds);
            $payoutSql = "SELECT py.supplier_pessoa_id AS supplier_id,
                                 COALESCE(SUM(py.total_amount), 0) AS sold_paid_commission_paid
                          FROM consignment_payouts py
                          WHERE py.status = 'confirmado'
                            AND py.supplier_pessoa_id IN ({$payoutInSql})
                          GROUP BY py.supplier_pessoa_id";
            $payoutStmt = $this->pdo->prepare($payoutSql);
            $payoutStmt->execute($payoutParams);
            while ($row = $payoutStmt->fetch(PDO::FETCH_ASSOC)) {
                $supplierId = (int) ($row['supplier_id'] ?? 0);
                if ($supplierId <= 0 || !isset($result[$supplierId])) {
                    continue;
                }
                $result[$supplierId]['sold_paid_commission_paid'] = (float) ($row['sold_paid_commission_paid'] ?? 0);
            }
        }

        return $result;
    }

    private function resolveVendorReportSupplierIds(int $supplierPessoaId): array
    {
        if ($supplierPessoaId <= 0) {
            return [];
        }

        $ids = [$supplierPessoaId => true];

        if (!$this->pdo) {
            return array_map('intval', array_keys($ids));
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, id_vendor
                 FROM vw_fornecedores_compat
                 WHERE id = :sid OR id_vendor = :sid
                 LIMIT 1"
            );
            $stmt->execute([':sid' => $supplierPessoaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $compatId = (int) ($row['id'] ?? 0);
                $compatVendorId = (int) ($row['id_vendor'] ?? 0);
                if ($compatId > 0) {
                    $ids[$compatId] = true;
                }
                if ($compatVendorId > 0) {
                    $ids[$compatVendorId] = true;
                }
            }
        } catch (\Throwable $e) {
            error_log('resolveVendorReportSupplierIds: ' . $e->getMessage());
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @return array{0:string,1:array<string,int>}
     */
    private function buildNamedInClause(string $prefix, array $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            $intValue = (int) $value;
            if ($intValue > 0) {
                $clean[$intValue] = true;
            }
        }
        if (empty($clean)) {
            $clean = [0 => true];
        }

        $params = [];
        $placeholders = [];
        $idx = 0;
        foreach (array_keys($clean) as $value) {
            $name = ':' . $prefix . '_' . $idx;
            $placeholders[] = $name;
            $params[$name] = (int) $value;
            $idx++;
        }

        return [implode(', ', $placeholders), $params];
    }

    /**
     * @return array{0:string,1:array<string,int>}
     */
    private function buildVendorRegistrySupplierScopeSql(array $supplierIds, string $prefix): array
    {
        [$currentInSql, $currentParams] = $this->buildNamedInClause($prefix . '_current', $supplierIds);
        [$originalInSql, $originalParams] = $this->buildNamedInClause($prefix . '_original', $supplierIds);
        $sql = "(r.supplier_pessoa_id IN ({$currentInSql}) OR r.consignment_supplier_original_id IN ({$originalInSql}))";
        return [$sql, $currentParams + $originalParams];
    }

    private function buildSupplierRanking(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT
                    r.supplier_pessoa_id,
                    pe.full_name,
                    COUNT(DISTINCT r.product_id) as total_received,
                    SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN 1 ELSE 0 END) as in_stock,
                    SUM(CASE WHEN r.consignment_status IN ('vendido_pendente','vendido_pago') THEN 1 ELSE 0 END) as total_sold,
                    SUM(CASE WHEN r.consignment_status = 'devolvido' THEN 1 ELSE 0 END) as total_returned,
                    SUM(CASE WHEN r.consignment_status = 'doado' THEN 1 ELSE 0 END) as donated,
                    COALESCE(SUM(CASE WHEN r.consignment_status IN ('vendido_pendente','vendido_pago') THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT r.product_id), 0), 0) as sell_through_rate,
                    COALESCE((SELECT SUM(cs.credit_amount) FROM consignment_sales cs WHERE cs.supplier_pessoa_id = r.supplier_pessoa_id AND cs.sale_status = 'ativa'), 0) as total_revenue,
                    COALESCE((SELECT AVG(DATEDIFF(NOW(), r2.received_at)) FROM consignment_product_registry r2 WHERE r2.supplier_pessoa_id = r.supplier_pessoa_id AND r2.consignment_status = 'em_estoque'), 0) as avg_aging
                FROM consignment_product_registry r
                LEFT JOIN pessoas pe ON pe.id = r.supplier_pessoa_id
                GROUP BY r.supplier_pessoa_id, pe.full_name
                ORDER BY total_sold DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildLegacyAnalysis(): array
    {
        if (!$this->pdo) {
            return [];
        }

        try {
            $sql = "SELECT
                        COUNT(*) as total_movements,
                        SUM(CASE WHEN payout_id IS NULL OR payout_id = 0 THEN 1 ELSE 0 END) as unlinked_count,
                        SUM(CASE WHEN payout_id IS NULL OR payout_id = 0 THEN credit_amount ELSE 0 END) as unlinked_value,
                        SUM(CASE WHEN payout_id IS NOT NULL AND payout_id > 0 THEN 1 ELSE 0 END) as linked_count
                    FROM cupons_creditos_movimentos
                    WHERE event_type = 'payout'
                      AND type = 'debito'";
            $stmt = $this->pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (\Throwable $e) {
            error_log('buildLegacyAnalysis: ' . $e->getMessage());
            return [];
        }
    }

    /* =====================================================================
     * HELPERS — Data Enrichment
     * ===================================================================== */

    private function enrichWithPersonNames(array $rows, string $personIdField): array
    {
        $ids = array_unique(array_filter(array_map(function ($r) use ($personIdField) {
            return (int) ($r[$personIdField] ?? 0);
        }, $rows)));

        if (empty($ids)) return $rows;

        $persons = $this->persons->findByIds($ids);
        $nameMap = [];
        foreach ($persons as $p) {
            $id = (int) ($p->id ?? $p['id'] ?? 0);
            $name = $p->fullName ?? $p['full_name'] ?? '(sem nome)';
            $nameMap[$id] = $name;
        }

        foreach ($rows as &$row) {
            $pid = (int) ($row[$personIdField] ?? 0);
            $row['supplier_name'] = $nameMap[$pid] ?? '(desconhecido)';
        }
        unset($row);

        return $rows;
    }

    private function loadConsignmentSuppliers(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT
                    s.supplier_pessoa_id,
                    MAX(COALESCE(v.full_name, p.full_name, CONCAT('Fornecedor #', s.supplier_pessoa_id))) AS full_name
                FROM (
                    SELECT supplier_pessoa_id
                    FROM consignment_product_registry
                    WHERE supplier_pessoa_id IS NOT NULL
                    UNION
                    SELECT consignment_supplier_original_id AS supplier_pessoa_id
                    FROM consignment_product_registry
                    WHERE consignment_supplier_original_id IS NOT NULL
                    UNION
                    SELECT supplier_pessoa_id
                    FROM consignment_sales
                    WHERE supplier_pessoa_id IS NOT NULL
                ) s
                LEFT JOIN vw_fornecedores_compat v
                       ON v.id = s.supplier_pessoa_id OR v.id_vendor = s.supplier_pessoa_id
                LEFT JOIN pessoas p ON p.id = s.supplier_pessoa_id
                GROUP BY s.supplier_pessoa_id
                ORDER BY full_name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRecentOwnItems(int $days): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT r.*, p.sku, p.name as product_name, p.price
                FROM consignment_product_registry r
                INNER JOIN products p ON p.sku = r.product_id
                WHERE r.consignment_status = 'proprio_pos_pgto'
                  AND r.detached_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY r.detached_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countRecentOwnItems(int $days): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM consignment_product_registry r
                WHERE r.consignment_status = 'proprio_pos_pgto'
                  AND r.detached_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function countLegacyUnlinkedPayouts(): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM cupons_creditos_movimentos
                WHERE event_type = 'payout'
                  AND (payout_id IS NULL OR payout_id = 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function countLegacyPayoutMovements(): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM cupons_creditos_movimentos m
                INNER JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
                WHERE m.type = 'debito'
                  AND m.event_type = 'payout'
                  AND (m.payout_id IS NULL OR m.payout_id = 0)
                  AND cc.type = 'credito'
                  AND (cc.scope = 'consignacao' OR cc.label LIKE 'Crédito consignação%')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Autoimporta lançamentos PIX legados (cupom/crédito) para a lista de pagamentos de consignação.
     *
     * @param array<int, string> $errors
     * @return array{imported:int, linked_sales:int, unmatched:int}
     */
    private function importLegacyPixPayoutsFromVoucherLedger(array &$errors): array
    {
        $summary = [
            'imported' => 0,
            'linked_sales' => 0,
            'unmatched' => 0,
        ];

        if (!$this->pdo) {
            return $summary;
        }

        $movements = $this->fetchLegacyPixMovementsForAutoImport();
        if (empty($movements)) {
            return $summary;
        }

        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        /** @var array<int, array{paid: array<int, array<string, mixed>>, pending: array<int, array<string, mixed>>, exception: array<int, array<string, mixed>>}> $salesPoolBySupplier */
        $salesPoolBySupplier = [];

        foreach ($movements as $movement) {
            $movementId = (int) ($movement['id'] ?? 0);
            $supplierPessoaId = (int) ($movement['supplier_pessoa_id'] ?? $movement['vendor_pessoa_id'] ?? 0);

            if ($movementId <= 0 || $supplierPessoaId <= 0) {
                $summary['unmatched']++;
                continue;
            }

            if (!isset($salesPoolBySupplier[$supplierPessoaId])) {
                $salesPoolBySupplier[$supplierPessoaId] = $this->fetchUnlinkedSalesPoolForSupplier($supplierPessoaId);
            }

            $selectedSales = $this->pickSalesForLegacyPixMovement($movement, $salesPoolBySupplier[$supplierPessoaId]);
            if (empty($selectedSales)) {
                $summary['unmatched']++;
                continue;
            }

            $saleIds = array_values(array_filter(array_map(static function (array $sale): int {
                return (int) ($sale['id'] ?? 0);
            }, $selectedSales), static fn(int $id): bool => $id > 0));

            if (empty($saleIds)) {
                $summary['unmatched']++;
                continue;
            }

            try {
                $this->executeRetroPayout(
                    $movementId,
                    $saleIds,
                    $userId,
                    'Importado automaticamente de lançamento PIX em cupom/crédito.',
                    'AUTO-PIX-IMPORT'
                );

                $summary['imported']++;
                $summary['linked_sales'] += count($saleIds);

                $selectedMap = array_fill_keys($saleIds, true);
                foreach (['paid', 'pending', 'exception'] as $bucket) {
                    $salesPoolBySupplier[$supplierPessoaId][$bucket] = array_values(array_filter(
                        $salesPoolBySupplier[$supplierPessoaId][$bucket],
                        static function (array $sale) use ($selectedMap): bool {
                            $saleId = (int) ($sale['id'] ?? 0);
                            return $saleId <= 0 || !isset($selectedMap[$saleId]);
                        }
                    ));
                }
            } catch (\Throwable $e) {
                $errors[] = 'Falha ao importar movimento PIX #' . $movementId . ': ' . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Carrega movimentos legados de payout (PIX) ainda não vinculados.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchLegacyPixMovementsForAutoImport(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT m.id, m.voucher_account_id, m.vendor_pessoa_id, m.credit_amount,
                       m.event_at, m.event_notes, m.event_label, m.event_id,
                       cc.pessoa_id AS supplier_pessoa_id
                FROM cupons_creditos_movimentos m
                INNER JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
                WHERE m.type = 'debito'
                  AND m.event_type = 'payout'
                  AND (m.payout_id IS NULL OR m.payout_id = 0)
                  AND cc.type = 'credito'
                  AND (cc.scope = 'consignacao' OR cc.label LIKE 'Crédito consignação%')
                ORDER BY COALESCE(m.event_at, m.created_at) ASC, m.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{paid: array<int, array<string, mixed>>, pending: array<int, array<string, mixed>>, exception: array<int, array<string, mixed>>}
     */
    private function fetchUnlinkedSalesPoolForSupplier(int $supplierPessoaId): array
    {
        $pool = ['paid' => [], 'pending' => [], 'exception' => []];
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return $pool;
        }

        $sql = "SELECT cs.id, cs.product_id, cs.order_id, cs.order_item_id, cs.credit_amount,
                       cs.percent_applied, cs.payout_status, cs.sold_at, cs.paid_at,
                       cs.sale_status, cs.reversed_at, cs.reversal_event_type,
                       COALESCE(
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(cs.product_id AS CHAR)), '')
                       ) AS order_item_sku
                FROM consignment_sales cs
                LEFT JOIN order_items oi ON oi.id = cs.order_item_id
                WHERE cs.supplier_pessoa_id = :sid
                  AND (cs.payout_id IS NULL OR cs.payout_id = 0)
                  AND cs.credit_amount > 0
                  AND cs.product_id IS NOT NULL
                  AND cs.product_id > 0
                  AND (
                      cs.sale_status = 'ativa'
                      OR (
                          cs.sale_status = 'revertida'
                          AND (
                              cs.payout_status = 'pago'
                              OR (
                                  cs.payout_status = 'pendente'
                                  AND (
                                      cs.reversed_at IS NOT NULL
                                      OR COALESCE(cs.reversal_event_type, '') <> ''
                                  )
                              )
                          )
                      )
                  )
                ORDER BY cs.sold_at ASC, cs.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid' => $supplierPessoaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (($row['payout_status'] ?? '') === 'pago') {
                $pool['paid'][] = $row;
            } elseif ($this->isLegacyPaidReturnExceptionSale($row)) {
                $row['legacy_reconciliation_tag'] = 'paid_return_exception';
                $pool['exception'][] = $row;
            } else {
                $pool['pending'][] = $row;
            }
        }

        return $pool;
    }

    /**
     * Tenta resolver quais vendas um movimento PIX legado pagou.
     *
     * @param array{paid: array<int, array<string, mixed>>, pending: array<int, array<string, mixed>>, exception?: array<int, array<string, mixed>>} $pool
     * @return array<int, array<string, mixed>>
     */
    private function pickSalesForLegacyPixMovement(array $movement, array $pool): array
    {
        $targetCents = $this->moneyToCents(abs((float) ($movement['credit_amount'] ?? 0)));
        if ($targetCents <= 0) {
            return [];
        }

        $movementAt = $this->normalizeDateTimeValue((string) ($movement['event_at'] ?? ''));
        $paidEligible = $this->filterSalesEligibleForMovement($pool['paid'] ?? [], $movementAt);
        $pendingEligible = $this->filterSalesEligibleForMovement($pool['pending'] ?? [], $movementAt);
        $exceptionEligible = $this->filterSalesEligibleForMovement($pool['exception'] ?? [], $movementAt);

        $match = $this->findFifoPrefixByAmount($paidEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $match = $this->findFifoPrefixByAmount($pendingEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $match = $this->findExactSubsetByAmount($paidEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $match = $this->findExactSubsetByAmount($pendingEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $match = $this->findExactSubsetByAmount($exceptionEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $pendingAndException = [];
        $pendingExceptionSeen = [];
        foreach ([$pendingEligible, $exceptionEligible] as $group) {
            foreach ($group as $sale) {
                $saleId = (int) ($sale['id'] ?? 0);
                if ($saleId > 0 && isset($pendingExceptionSeen[$saleId])) {
                    continue;
                }
                if ($saleId > 0) {
                    $pendingExceptionSeen[$saleId] = true;
                }
                $pendingAndException[] = $sale;
            }
        }
        $match = $this->findExactSubsetByAmount($pendingAndException, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        $allEligible = [];
        $seen = [];
        foreach ([$paidEligible, $pendingEligible, $exceptionEligible] as $group) {
            foreach ($group as $sale) {
                $saleId = (int) ($sale['id'] ?? 0);
                if ($saleId > 0 && isset($seen[$saleId])) {
                    continue;
                }
                if ($saleId > 0) {
                    $seen[$saleId] = true;
                }
                $allEligible[] = $sale;
            }
        }

        $match = $this->findExactSubsetByAmount($allEligible, $targetCents);
        if (!empty($match)) {
            return $match;
        }

        return $this->findFifoPrefixByAmount($allEligible, $targetCents);
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<int, array<string, mixed>>
     */
    private function filterSalesEligibleForMovement(array $sales, ?string $movementAt): array
    {
        $sales = array_values($sales);
        if ($movementAt === null) {
            return $sales;
        }

        $eligible = [];
        foreach ($sales as $sale) {
            $soldAt = $this->normalizeDateTimeValue((string) ($sale['sold_at'] ?? $sale['paid_at'] ?? ''));
            if ($soldAt === null || $soldAt <= $movementAt) {
                $eligible[] = $sale;
            }
        }

        return !empty($eligible) ? $eligible : $sales;
    }

    /**
     * Busca combinação por prefixo FIFO (vendas mais antigas primeiro).
     *
     * @param array<int, array<string, mixed>> $sales
     * @return array<int, array<string, mixed>>
     */
    private function findFifoPrefixByAmount(array $sales, int $targetCents): array
    {
        if ($targetCents <= 0) {
            return [];
        }

        $sum = 0;
        $matched = [];
        foreach ($sales as $sale) {
            $amountCents = $this->moneyToCents((float) ($sale['credit_amount'] ?? 0));
            if ($amountCents <= 0) {
                continue;
            }

            if (($sum + $amountCents) > ($targetCents + 1)) {
                break;
            }

            $sum += $amountCents;
            $matched[] = $sale;

            if (abs($sum - $targetCents) <= 1) {
                return $matched;
            }
        }

        return [];
    }

    /**
     * Busca subconjunto exato por valor (em centavos).
     *
     * @param array<int, array<string, mixed>> $sales
     * @return array<int, array<string, mixed>>
     */
    private function findExactSubsetByAmount(array $sales, int $targetCents): array
    {
        if ($targetCents <= 0) {
            return [];
        }

        $filtered = [];
        foreach ($sales as $sale) {
            $amountCents = $this->moneyToCents((float) ($sale['credit_amount'] ?? 0));
            if ($amountCents > 0) {
                $filtered[] = $sale;
            }
        }

        $n = count($filtered);
        if ($n === 0) {
            return [];
        }

        if ($n <= 20) {
            $amounts = array_map(fn($sale): int => $this->moneyToCents((float) ($sale['credit_amount'] ?? 0)), $filtered);
            $limit = 1 << $n;
            for ($mask = 1; $mask < $limit; $mask++) {
                $sum = 0;
                for ($i = 0; $i < $n; $i++) {
                    if ($mask & (1 << $i)) {
                        $sum += $amounts[$i];
                    }
                }
                if ($sum === $targetCents) {
                    $result = [];
                    for ($i = 0; $i < $n; $i++) {
                        if ($mask & (1 << $i)) {
                            $result[] = $filtered[$i];
                        }
                    }
                    return $result;
                }
            }
            return [];
        }

        if ($n <= 30) {
            $match = $this->meetInTheMiddleSubsetSum($filtered, $targetCents);
            return $match ?? [];
        }

        return [];
    }

    private function moneyToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function normalizeDateTimeValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function extractPixKeyFromMovementNotes(string $notes): ?string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return null;
        }

        if (preg_match('/(?:^|\|)\s*PIX\s+([^|\n\r]+)/iu', $notes, $matches)) {
            $pixKey = trim((string) ($matches[1] ?? ''));
            if ($pixKey !== '') {
                return mb_strlen($pixKey) > 255 ? mb_substr($pixKey, 0, 255) : $pixKey;
            }
        }

        return null;
    }

    /**
     * Resolve the real product SKU for a consignment sale when product_id is a legacy ID
     * that doesn't exist in products.sku. Falls back to order_items.product_sku.
     */
    private function resolveRealProductSku(int $saleId, int $legacyProductId): int
    {
        if (!$this->pdo || $saleId <= 0) {
            return $legacyProductId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT oi.product_sku
             FROM consignment_sales cs
             JOIN order_items oi ON oi.id = cs.order_item_id
             WHERE cs.id = :sid
             LIMIT 1"
        );
        $stmt->execute([':sid' => $saleId]);
        $realSku = (int) ($stmt->fetchColumn() ?: 0);

        return $realSku > 0 ? $realSku : $legacyProductId;
    }

    /**
     * Applies "paid-return exception" reclassification for legacy reconciliation:
     * item remained in payout history, but sale is reverted and item returned to stock as store-owned.
     */
    private function applyLegacyPaidReturnExceptionAdjustments(
        int $productId,
        int $supplierPessoaId,
        int $userId,
        int $payoutId,
        string $sourceTag
    ): void {
        if (!$this->pdo || $productId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE products
                SET source = 'consignacao_quitada',
                    supplier_pessoa_id = NULL,
                    percentual_consignacao = NULL,
                    consignment_status = 'vendido_pago',
                    consignment_detached_at = COALESCE(consignment_detached_at, :detached_at),
                    updated_at = NOW()
              WHERE sku = :sku"
        );
        $stmt->execute([
            ':detached_at' => $now,
            ':sku' => $productId,
        ]);

        $registry = $this->registry->findByProductId($productId);
        if (!$registry) {
            return;
        }

        $existingNotes = trim((string) ($registry['notes'] ?? ''));
        $exceptionNote = '[' . $sourceTag . '] [EXCECAO] Venda revertida vinculada ao payout #'
            . $payoutId . ' (comissão já paga, sem reabertura de comissão).';

        $notes = $existingNotes;
        if ($notes === '') {
            $notes = $exceptionNote;
        } elseif (mb_strpos($notes, $exceptionNote) === false) {
            $notes .= "\n" . $exceptionNote;
        }

        $detachedAt = trim((string) ($registry['detached_at'] ?? '')) !== ''
            ? $registry['detached_at']
            : $now;
        $originalSource = trim((string) ($registry['original_source'] ?? '')) !== ''
            ? $registry['original_source']
            : 'consignacao';

        $this->registry->update((int) $registry['id'], [
            'supplier_pessoa_id' => $supplierPessoaId > 0
                ? $supplierPessoaId
                : ($registry['supplier_pessoa_id'] ?? null),
            'consignment_status' => 'vendido_pago',
            'detached_at' => $detachedAt,
            'original_source' => $originalSource,
            'status_changed_at' => $now,
            'status_changed_by' => $userId > 0 ? $userId : null,
            'notes' => $notes,
        ]);
    }

    private function fetchLegacyPayouts(int $limit = 0, int $offset = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $useLimit = $limit > 0;
        $offset = max(0, $offset);
        $sql = "SELECT m.*, cc.label as account_label, cc.pessoa_id
                FROM cupons_creditos_movimentos m
                INNER JOIN cupons_creditos cc ON cc.id = m.voucher_account_id
                WHERE m.type = 'debito'
                  AND m.event_type = 'payout'
                  AND (m.payout_id IS NULL OR m.payout_id = 0)
                  AND cc.type = 'credito'
                  AND (cc.scope = 'consignacao' OR cc.label LIKE 'Crédito consignação%')
                ORDER BY m.event_at DESC";
        if ($useLimit) {
            $sql .= "\n                LIMIT :limit OFFSET :offset";
        }
        $stmt = $this->pdo->prepare($sql);
        if ($useLimit) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch sales candidates for a supplier (used in legacy reconciliation).
     * Includes:
     * - ativa + pendente
     * - exceção legado: revertida + pendente (venda desfeita pós-pagamento)
     */
    private function fetchPendingSalesForSupplier(int $supplierPessoaId): array
    {
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return [];
        }

        $sql = "SELECT cs.id, cs.product_id,
                       COALESCE(
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(cs.product_id AS CHAR)), '')
                       ) AS sku,
                       COALESCE(
                           NULLIF(TRIM(oi.product_name), ''),
                           NULLIF(TRIM(p.name), ''),
                           NULLIF(TRIM(p2.name), '')
                       ) AS product_name,
                       cs.order_id, cs.order_item_id, cs.sold_at, cs.credit_amount AS amount,
                       cs.sale_status, cs.payout_status, cs.reversal_event_type, cs.reversed_at,
                       CASE
                           WHEN cs.sale_status = 'revertida'
                            AND cs.payout_status = 'pendente'
                            AND (cs.reversed_at IS NOT NULL OR COALESCE(cs.reversal_event_type, '') <> '')
                           THEN 1 ELSE 0
                       END AS is_paid_return_exception
                FROM consignment_sales cs
                LEFT JOIN products p ON p.sku = cs.product_id
                LEFT JOIN order_items oi ON oi.id = cs.order_item_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE cs.supplier_pessoa_id = :sid
                  AND cs.payout_status = 'pendente'
                  AND (
                      cs.sale_status = 'ativa'
                      OR (
                          cs.sale_status = 'revertida'
                          AND (
                              cs.reversed_at IS NOT NULL
                              OR COALESCE(cs.reversal_event_type, '') <> ''
                          )
                      )
                  )
                ORDER BY cs.sold_at ASC, cs.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid' => $supplierPessoaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch selected sales with normalized SKU/product name for legacy reconciliation validation.
     *
     * @param int[] $saleIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchSalesForLegacyReconciliation(array $saleIds): array
    {
        if (!$this->pdo || empty($saleIds)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $saleIds), fn(int $id): bool => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT cs.id, cs.product_id, cs.order_id, cs.order_item_id,
                       cs.supplier_pessoa_id, cs.sale_status, cs.payout_status,
                       cs.credit_amount, cs.percent_applied, cs.reversed_at, cs.reversal_event_type,
                       COALESCE(
                           NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                           NULLIF(TRIM(CAST(cs.product_id AS CHAR)), '')
                       ) AS sku,
                       COALESCE(
                           NULLIF(TRIM(oi.product_name), ''),
                           NULLIF(TRIM(p.name), ''),
                           NULLIF(TRIM(p2.name), '')
                       ) AS product_name
                FROM consignment_sales cs
                LEFT JOIN products p ON p.sku = cs.product_id
                LEFT JOIN order_items oi ON oi.id = cs.order_item_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                WHERE cs.id IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($rows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId > 0) {
                $byId[$rowId] = $row;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * Legacy exception: sale reverted after being paid historically.
     * These rows can appear as revertida + pendente in backfill and still must be linked to payout.
     */
    private function isLegacyPaidReturnExceptionSale(array $sale): bool
    {
        $saleStatus = (string) ($sale['sale_status'] ?? '');
        $payoutStatus = (string) ($sale['payout_status'] ?? '');
        if ($saleStatus !== 'revertida' || $payoutStatus !== 'pendente') {
            return false;
        }

        $hasReversalTrace = trim((string) ($sale['reversed_at'] ?? '')) !== ''
            || trim((string) ($sale['reversal_event_type'] ?? '')) !== '';
        if (!$hasReversalTrace) {
            return false;
        }

        return ((float) ($sale['credit_amount'] ?? 0)) > 0;
    }

    /**
     * Resolve a period lock badge for report screens.
     *
     * @return array{year_month: string, locked: bool, label: string, badge: string}|null
     */
    private function resolveReportPeriodBadge(string $dateFrom, string $dateTo): ?array
    {
        $ymFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? substr($dateFrom, 0, 7) : '';
        $ymTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? substr($dateTo, 0, 7) : '';

        $yearMonth = $ymFrom !== '' ? $ymFrom : $ymTo;
        if ($yearMonth === '') {
            return null;
        }

        $locked = $this->periodLockService->isLocked($yearMonth);
        return [
            'year_month' => $yearMonth,
            'locked' => $locked,
            'label' => $locked ? 'Período fechado' : 'Período aberto',
            'badge' => $locked ? 'warning' : 'success',
        ];
    }

    /**
     * Stream vendor report as CSV.
     */
    private function streamVendorReportCsv(array $report, ?object $supplierPerson, string $dateFrom, string $dateTo, string $dateField): void
    {
        $supplierName = trim((string) ($supplierPerson->fullName ?? $supplierPerson->full_name ?? 'fornecedora'));
        $safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($supplierName));
        $safeName = trim((string) $safeName, '-');
        if ($safeName === '') {
            $safeName = 'fornecedora';
        }

        $filename = 'consignacao-relatorio-' . $safeName . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            echo 'Erro ao gerar CSV.';
            return;
        }

        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Relatório de consignação por fornecedora']);
        fputcsv($out, ['Fornecedora', $supplierName !== '' ? $supplierName : 'Fornecedora']);
        fputcsv($out, ['Período inicial', $dateFrom !== '' ? $dateFrom : '']);
        fputcsv($out, ['Período final', $dateTo !== '' ? $dateTo : '']);
        fputcsv($out, ['Filtro de data', $dateField]);
        fputcsv($out, []);

        $summary = $report['summary'] ?? [];
        fputcsv($out, ['Resumo']);
        fputcsv($out, ['Métrica', 'Valor']);
        fputcsv($out, ['Peças em estoque', (int) ($summary['in_stock_count'] ?? 0)]);
        fputcsv($out, ['Valor em estoque', (float) ($summary['in_stock_value'] ?? 0)]);
        fputcsv($out, ['Peças vendidas (pendente)', (int) ($summary['sold_pending_count'] ?? 0)]);
        fputcsv($out, ['Comissão pendente', (float) ($summary['sold_pending_credit'] ?? 0)]);
        fputcsv($out, ['Peças vendidas (pago)', (int) ($summary['sold_paid_count'] ?? 0)]);
        fputcsv($out, ['Comissão paga', (float) ($summary['sold_paid_credit'] ?? 0)]);
        fputcsv($out, ['Peças devolvidas', (int) ($summary['returned_count'] ?? 0)]);
        fputcsv($out, ['Peças doadas', (int) ($summary['donated_count'] ?? 0)]);
        fputcsv($out, ['Aging médio (dias)', (float) ($summary['avg_aging_days'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Detalhamento']);
        fputcsv($out, [
            'SKU',
            'Produto',
            'Categoria',
            'Recebido em',
            'Status',
            'Venda #',
            'Vendido em',
            'Preço venda',
            '%',
            'Comissão',
            'Status pagamento',
            'Pago em',
        ]);
        foreach ((array) ($report['items'] ?? []) as $item) {
            fputcsv($out, [
                $item['sku'] ?? '',
                $item['product_name'] ?? '',
                $item['category_name'] ?? ($item['category'] ?? ''),
                $item['received_at'] ?? '',
                $item['consignment_status'] ?? '',
                $item['order_id'] ?? '',
                $item['sold_at'] ?? '',
                (float) ($item['price'] ?? 0),
                (float) ($item['percent_applied'] ?? 0),
                (float) ($item['credit_amount'] ?? 0),
                $item['payout_status'] ?? '',
                $item['paid_at'] ?? '',
            ]);
        }

        fclose($out);
    }

    private function sanitizePerPage(int $value): int
    {
        $allowed = [25, 50, 100, 200];
        return in_array($value, $allowed) ? $value : 50;
    }

    /* =====================================================================
     * HELPERS — Static data
     * ===================================================================== */

    public static function consignmentStatusLabels(): array
    {
        return [
            'em_estoque'       => ['label' => 'Em estoque', 'badge' => 'info'],
            'vendido_pendente' => ['label' => 'Vendido (pendente)', 'badge' => 'warning'],
            'vendido_pago'     => ['label' => 'Vendido (pago)', 'badge' => 'success'],
            'proprio_pos_pgto' => ['label' => 'Próprio (pós-pgto)', 'badge' => 'secondary'],
            'devolvido'        => ['label' => 'Devolvido', 'badge' => 'dark'],
            'doado'            => ['label' => 'Doado', 'badge' => 'dark'],
            'descartado'       => ['label' => 'Descartado', 'badge' => 'dark'],
        ];
    }

    public static function payoutStatusLabels(): array
    {
        return [
            'rascunho'   => ['label' => 'Rascunho', 'badge' => 'secondary'],
            'confirmado' => ['label' => 'Confirmado', 'badge' => 'success'],
            'cancelado'  => ['label' => 'Cancelado', 'badge' => 'danger'],
        ];
    }

    public static function saleStatusLabels(): array
    {
        return [
            'ativa'    => ['label' => 'Ativa', 'badge' => 'success'],
            'revertida' => ['label' => 'Revertida', 'badge' => 'danger'],
        ];
    }

    public static function payoutMethodLabels(): array
    {
        return [
            'pix'           => 'PIX',
            'transferencia' => 'Transferência',
            'dinheiro'      => 'Dinheiro',
            'outro'         => 'Outro',
        ];
    }

    /**
     * Resolve the consignment voucher account ID for a supplier.
     */
    private function resolveConsignmentVoucherAccount(int $supplierPessoaId): int
    {
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return 0;
        }

        $accounts = $this->vouchers->listByPerson($supplierPessoaId, false);
        foreach ($accounts as $acc) {
            if (($acc['scope'] ?? '') === 'consignacao' && ($acc['type'] ?? '') === 'credito' && ($acc['status'] ?? '') === 'ativo') {
                return (int) ($acc['id'] ?? 0);
            }
        }
        // Fallback: any consignment credit account
        foreach ($accounts as $acc) {
            if (($acc['scope'] ?? '') === 'consignacao' && ($acc['type'] ?? '') === 'credito') {
                return (int) ($acc['id'] ?? 0);
            }
        }
        // Fallback: label match
        foreach ($accounts as $acc) {
            if (($acc['type'] ?? '') === 'credito' && stripos($acc['label'] ?? '', 'consignação') !== false) {
                return (int) ($acc['id'] ?? 0);
            }
        }

        return 0;
    }

    /* =====================================================================
     * DYNAMIC REPORT — Relatório Dinâmico de Consignação
     * ===================================================================== */

    /**
     * Página principal do relatório dinâmico.
     * Suporta: geração, preview, export CSV/Excel/PDF, print.
     */
    public function dynamicReport(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        // Read parameters
        $supplierFilter = (int) ($_GET['supplier_pessoa_id'] ?? 0);
        $viewId = (int) ($_GET['view_id'] ?? 0);
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $dateField = trim((string) ($_GET['date_field'] ?? 'sold_at'));
        if (!in_array($dateField, ['sold_at', 'received_at', 'paid_at'], true)) {
            $dateField = 'sold_at';
        }
        $detailLevel = trim((string) ($_GET['detail_level'] ?? ''));
        $sortField = trim((string) ($_GET['sort_field'] ?? 'received_at'));
        $sortDir = trim((string) ($_GET['sort_dir'] ?? 'DESC'));
        $groupBy = trim((string) ($_GET['group_by'] ?? ''));
        $exportFormat = strtolower(trim((string) ($_GET['format'] ?? $_GET['export'] ?? '')));
        $printMode = ($_GET['print'] ?? '') === '1';
        $printSummaryMode = strtolower(trim((string) ($_GET['print_summary_mode'] ?? 'both')));
        if (!in_array($printSummaryMode, ['both', 'historical', 'filtered', 'none'], true)) {
            $printSummaryMode = 'both';
        }

        // Load field overrides from URL first
        $selectedFields = [];
        if (!empty($_GET['fields'])) {
            if (is_string($_GET['fields'])) {
                $selectedFields = array_filter(array_map('trim', explode(',', $_GET['fields'])));
            } elseif (is_array($_GET['fields'])) {
                $selectedFields = array_filter(array_map('trim', $_GET['fields']));
            }
        }
        if (empty($selectedFields)) {
            $selectedFields = ConsignmentReportService::defaultFieldKeys();
        }

        // Build runtime filters
        $runtimeFilters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_field' => $dateField,
            'sort_field' => $sortField,
            'sort_dir' => $sortDir,
        ];
        if (!empty($_GET['consignment_status'])) {
            $runtimeFilters['consignment_status'] = trim((string) $_GET['consignment_status']);
        }
        if (!empty($_GET['payout_status'])) {
            $runtimeFilters['payout_status'] = trim((string) $_GET['payout_status']);
        }
        if (!empty($_GET['q'])) {
            $runtimeFilters['search'] = trim((string) $_GET['q']);
        }
        if (!empty($_GET['only_pending_payment'])) {
            $runtimeFilters['only_pending_payment'] = true;
        }
        if (!empty($_GET['only_sold'])) {
            $runtimeFilters['only_sold'] = true;
        }
        if (!empty($_GET['aging_min_days'])) {
            $runtimeFilters['aging_min_days'] = (int) $_GET['aging_min_days'];
        }
        if (!empty($_GET['only_donation_authorized'])) {
            $runtimeFilters['only_donation_authorized'] = true;
        }

        $reportService = null;
        $viewConfig = null;
        $availableViews = [];
        $reportData = null;

        $effectiveViewConfig = [
            'fields_config' => $selectedFields,
            'detail_level' => $detailLevel !== '' ? $detailLevel : 'both',
            'sort_config' => ['field' => $sortField, 'direction' => $sortDir],
            'group_by' => $groupBy,
            'filters_config' => [],
        ];

        if (!($this->pdo instanceof PDO)) {
            if (!$this->connectionError) {
                $errors[] = 'Sem conexão com banco de dados.';
            }
        } else {
            try {
                $reportService = new ConsignmentReportService($this->pdo);
                $viewRepo = $reportService->getViewRepository();

                // Ensure system presets exist
                try {
                    $reportService->ensureSystemPresets();
                } catch (\Throwable $e) {
                    // Silently continue if presets already exist
                }

                try {
                    $availableViews = $viewRepo->listAll();
                    if ($viewId > 0) {
                        $viewConfig = $viewRepo->find($viewId);
                    }
                    if (!$viewConfig) {
                        $viewConfig = $viewRepo->findDefault($supplierFilter > 0 ? $supplierFilter : null);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao carregar modelos de relatório: ' . $e->getMessage();
                }

                if (empty($_GET['fields']) && $viewConfig) {
                    $selectedFields = $viewConfig['fields_config'] ?? ConsignmentReportService::defaultFieldKeys();
                }
                if (empty($selectedFields)) {
                    $selectedFields = ConsignmentReportService::defaultFieldKeys();
                }

                // Merge view config with runtime overrides
                $effectiveViewConfig = [
                    'fields_config' => $selectedFields,
                    'detail_level' => $detailLevel ?: ($viewConfig['detail_level'] ?? 'both'),
                    'sort_config' => ['field' => $sortField, 'direction' => $sortDir],
                    'group_by' => $groupBy ?: ($viewConfig['group_by'] ?? ''),
                    'filters_config' => $viewConfig['filters_config'] ?? [],
                ];

                // Generate report data if supplier selected
                if ($supplierFilter > 0) {
                    try {
                        $reportData = $reportService->generateReport(
                            $supplierFilter,
                            $effectiveViewConfig,
                            $runtimeFilters
                        );
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao gerar relatório: ' . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao inicializar relatório dinâmico: ' . $e->getMessage();
            }
        }

        // Handle exports
        $supplierPerson = $supplierFilter > 0 ? $this->persons->find($supplierFilter) : null;
        $supplierName = '';
        if ($supplierPerson) {
            $supplierName = trim((string) (is_object($supplierPerson)
                ? ($supplierPerson->fullName ?? $supplierPerson->full_name ?? '')
                : ($supplierPerson['full_name'] ?? $supplierPerson['fullName'] ?? '')));
        }

        if ($reportData && $reportService && $exportFormat === 'csv') {
            $reportService->exportCsv($reportData, $selectedFields, $supplierName, $dateFrom, $dateTo);
            return;
        }
        if ($reportData && $reportService && $exportFormat === 'excel') {
            $reportService->exportExcel($reportData, $selectedFields, $supplierName, $dateFrom, $dateTo);
            return;
        }
        if ($reportData && $printMode) {
            View::render('consignment_module/dynamic_report_print', [
                'report'         => $reportData,
                'supplierPerson' => $supplierPerson,
                'selectedFields' => $selectedFields,
                'fieldMetadata'  => ConsignmentReportService::fieldMetadata(),
                'dateFrom'       => $dateFrom,
                'dateTo'         => $dateTo,
                'dateField'      => $dateField,
                'detailLevel'    => $effectiveViewConfig['detail_level'],
                'printSummaryMode' => $printSummaryMode,
                'statusLabels'   => self::consignmentStatusLabels(),
            ], ['title' => 'Relatório Dinâmico — Impressão', 'layout' => __DIR__ . '/../Views/print-layout.php']);
            return;
        }

        // JSON response for AJAX field preview
        if (($exportFormat === 'json' || !empty($_GET['ajax'])) && $reportData) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'summary' => $reportData['summary'] ?? [],
                'summary_filtered' => $reportData['summary_filtered'] ?? [],
                'items'   => $reportData['items'] ?? [],
                'total'   => $reportData['total'] ?? 0,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $suppliers = [];
        try {
            $suppliers = $this->loadConsignmentSuppliers();
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar fornecedoras: ' . $e->getMessage();
        }

        $periodBadge = null;
        try {
            $periodBadge = $this->resolveReportPeriodBadge($dateFrom, $dateTo);
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar status do período: ' . $e->getMessage();
        }

        View::render('consignment_module/dynamic_report', [
            'report'             => $reportData,
            'supplierFilter'     => $supplierFilter,
            'supplierPerson'     => $supplierPerson,
            'suppliers'          => $suppliers,
            'dateFrom'           => $dateFrom,
            'dateTo'             => $dateTo,
            'dateField'          => $dateField,
            'detailLevel'        => $effectiveViewConfig['detail_level'],
            'sortField'          => $sortField,
            'sortDir'            => $sortDir,
            'groupBy'            => $effectiveViewConfig['group_by'],
            'printSummaryMode'   => $printSummaryMode,
            'selectedFields'     => $selectedFields,
            'fieldMetadata'      => ConsignmentReportService::fieldMetadata(),
            'fieldsByCategory'   => ConsignmentReportService::fieldsByCategory(),
            'availableViews'     => $availableViews,
            'currentViewId'      => $viewId,
            'currentView'        => $viewConfig,
            'statusLabels'       => self::consignmentStatusLabels(),
            'runtimeFilters'     => $runtimeFilters,
            'periodBadge'        => $periodBadge,
            'errors'             => $errors,
        ], ['title' => 'Consignação — Relatório Dinâmico']);
    }

    /* =====================================================================
     * REPORT VIEWS CRUD — Gerenciamento de modelos de relatório
     * ===================================================================== */

    /**
     * Página de gerenciamento de modelos de relatório.
     */
    public function reportViews(): void
    {
        $errors = [];
        $success = '';
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $err = trim((string) $_SESSION['flash_error']);
            if ($err !== '') $errors[] = $err;
            unset($_SESSION['flash_error']);
        }

        if (!($this->pdo instanceof PDO)) {
            if (!$this->connectionError) {
                $errors[] = 'Sem conexão com banco de dados.';
            }
            View::render('consignment_module/report_views', [
                'views'            => [],
                'suppliers'        => [],
                'fieldMetadata'    => ConsignmentReportService::fieldMetadata(),
                'fieldsByCategory' => ConsignmentReportService::fieldsByCategory(),
                'systemPresets'    => ConsignmentReportService::systemPresets(),
                'errors'           => $errors,
                'success'          => $success,
            ], ['title' => 'Consignação — Modelos de Relatório']);
            return;
        }

        try {
            $reportService = new ConsignmentReportService($this->pdo);
            $viewRepo = $reportService->getViewRepository();
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao inicializar modelos de relatório: ' . $e->getMessage();
            View::render('consignment_module/report_views', [
                'views'            => [],
                'suppliers'        => [],
                'fieldMetadata'    => ConsignmentReportService::fieldMetadata(),
                'fieldsByCategory' => ConsignmentReportService::fieldsByCategory(),
                'systemPresets'    => ConsignmentReportService::systemPresets(),
                'errors'           => $errors,
                'success'          => $success,
            ], ['title' => 'Consignação — Modelos de Relatório']);
            return;
        }

        try {
            $reportService->ensureSystemPresets();
        } catch (\Throwable $e) {
            // ignore
        }

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string) ($_POST['action'] ?? ''));
            $user = Auth::user();
            $userId = (int) ($user['id'] ?? 0);

            try {
                switch ($action) {
                    case 'create':
                        $name = trim((string) ($_POST['name'] ?? ''));
                        if ($name === '') {
                            throw new \RuntimeException('Nome do modelo é obrigatório.');
                        }
                        $fieldsRaw = $_POST['fields'] ?? [];
                        $fields = is_array($fieldsRaw) ? $fieldsRaw : array_filter(array_map('trim', explode(',', (string) $fieldsRaw)));
                        if (empty($fields)) {
                            $fields = ConsignmentReportService::defaultFieldKeys();
                        }
                        $viewRepo->create([
                            'name'          => $name,
                            'description'   => trim((string) ($_POST['description'] ?? '')),
                            'fields_config' => $fields,
                            'detail_level'  => trim((string) ($_POST['detail_level'] ?? 'both')),
                            'sort_config'   => [
                                'field' => trim((string) ($_POST['sort_field'] ?? 'received_at')),
                                'direction' => trim((string) ($_POST['sort_dir'] ?? 'DESC')),
                            ],
                            'group_by'      => trim((string) ($_POST['group_by'] ?? '')) ?: null,
                            'created_by'    => $userId,
                        ]);
                        $_SESSION['flash_success'] = 'Modelo "' . $name . '" criado com sucesso.';
                        break;

                    case 'update':
                        $id = (int) ($_POST['view_id'] ?? 0);
                        if ($id <= 0) throw new \RuntimeException('ID do modelo inválido.');
                        $name = trim((string) ($_POST['name'] ?? ''));
                        if ($name === '') throw new \RuntimeException('Nome é obrigatório.');
                        $fieldsRaw = $_POST['fields'] ?? [];
                        $fields = is_array($fieldsRaw) ? $fieldsRaw : array_filter(array_map('trim', explode(',', (string) $fieldsRaw)));
                        $viewRepo->update($id, [
                            'name'          => $name,
                            'description'   => trim((string) ($_POST['description'] ?? '')),
                            'fields_config' => $fields,
                            'detail_level'  => trim((string) ($_POST['detail_level'] ?? 'both')),
                            'sort_config'   => [
                                'field' => trim((string) ($_POST['sort_field'] ?? 'received_at')),
                                'direction' => trim((string) ($_POST['sort_dir'] ?? 'DESC')),
                            ],
                            'group_by'      => trim((string) ($_POST['group_by'] ?? '')) ?: null,
                        ]);
                        $_SESSION['flash_success'] = 'Modelo atualizado com sucesso.';
                        break;

                    case 'clone':
                        $sourceId = (int) ($_POST['source_id'] ?? 0);
                        $newName = trim((string) ($_POST['new_name'] ?? ''));
                        if ($sourceId <= 0 || $newName === '') throw new \RuntimeException('Dados insuficientes para clonar.');
                        $viewRepo->cloneView($sourceId, $newName, $userId);
                        $_SESSION['flash_success'] = 'Modelo clonado como "' . $newName . '".';
                        break;

                    case 'delete':
                        $id = (int) ($_POST['view_id'] ?? 0);
                        if ($id <= 0) throw new \RuntimeException('ID do modelo inválido.');
                        $viewRepo->delete($id);
                        $_SESSION['flash_success'] = 'Modelo excluído.';
                        break;

                    case 'set_default':
                        $id = (int) ($_POST['view_id'] ?? 0);
                        $supplierId = (int) ($_POST['supplier_pessoa_id'] ?? 0);
                        if ($id <= 0) throw new \RuntimeException('ID do modelo inválido.');
                        $viewRepo->clearDefaults($supplierId > 0 ? $supplierId : null);
                        $updateData = ['is_default' => 1];
                        if ($supplierId > 0) {
                            $updateData['default_for_supplier_id'] = $supplierId;
                        }
                        $viewRepo->update($id, $updateData);
                        $_SESSION['flash_success'] = 'Modelo definido como padrão.';
                        break;

                    case 'export_view':
                        $id = (int) ($_POST['view_id'] ?? 0);
                        $view = $viewRepo->find($id);
                        if (!$view) throw new \RuntimeException('Modelo não encontrado.');
                        header('Content-Type: application/json; charset=utf-8');
                        header('Content-Disposition: attachment; filename="report-view-' . $id . '.json"');
                        echo json_encode($view, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        return;
                }
            } catch (\Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }

            header('Location: consignacao-relatorio-modelos.php');
            exit;
        }

        $views = [];
        try {
            $views = $viewRepo->listAll();
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar modelos de relatório: ' . $e->getMessage();
        }

        $suppliers = [];
        try {
            $suppliers = $this->loadConsignmentSuppliers();
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar fornecedoras: ' . $e->getMessage();
        }

        View::render('consignment_module/report_views', [
            'views'            => $views,
            'suppliers'        => $suppliers,
            'fieldMetadata'    => ConsignmentReportService::fieldMetadata(),
            'fieldsByCategory' => ConsignmentReportService::fieldsByCategory(),
            'systemPresets'    => ConsignmentReportService::systemPresets(),
            'errors'           => $errors,
            'success'          => $success,
        ], ['title' => 'Consignação — Modelos de Relatório']);
    }
}
