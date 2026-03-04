<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\FinanceEntry;
use App\Repositories\BankRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\FinanceCategoryRepository;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentTerminalRepository;
use App\Repositories\PieceLotRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\VendorRepository;
use App\Services\FinanceEntryService;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class FinanceController
{
    private FinanceEntryRepository $entries;
    private FinanceCategoryRepository $categories;
    private PaymentMethodRepository $paymentMethods;
    private BankAccountRepository $bankAccounts;
    private PaymentTerminalRepository $paymentTerminals;
    private OrderRepository $orders;
    private VendorRepository $vendors;
    private PieceLotRepository $lots;
    private ProductSupplyRepository $supplies;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        new BankRepository($pdo);
        $this->entries = new FinanceEntryRepository($pdo);
        $this->categories = new FinanceCategoryRepository($pdo);
        $this->paymentMethods = new PaymentMethodRepository($pdo);
        $this->bankAccounts = new BankAccountRepository($pdo);
        $this->paymentTerminals = new PaymentTerminalRepository($pdo);
        $this->orders = new OrderRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->lots = new PieceLotRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $searchQuery = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPageOptions = [50, 100, 200];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }
        $sortKey = trim((string) ($_GET['sort_key'] ?? $_GET['sort'] ?? 'due'));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? $_GET['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $allowedSort = ['id', 'type', 'description', 'category', 'supplier', 'amount', 'due', 'status', 'paid', 'payment', 'origin'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'due';
        }
        $columnFilterKeys = [
            'filter_id',
            'filter_type',
            'filter_description',
            'filter_category',
            'filter_supplier',
            'filter_amount',
            'filter_due',
            'filter_status',
            'filter_paid',
            'filter_payment',
            'filter_origin',
        ];
        $columnFilters = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                Auth::requirePermission('finance_entries.delete', $this->entries->getPdo());
                $id = (int) $_POST['delete_id'];
                if ($id > 0) {
                    $this->entries->delete($id);
                    $success = 'Lançamento excluído.';
                }
            } elseif (isset($_POST['mark_paid_id'])) {
                Auth::requirePermission('finance_entries.edit', $this->entries->getPdo());
                $id = (int) $_POST['mark_paid_id'];
                if ($id > 0) {
                    $this->markAsPaid($id, $errors, $success);
                }
            } elseif (isset($_POST['mark_unpaid_id'])) {
                Auth::requirePermission('finance_entries.edit', $this->entries->getPdo());
                $id = (int) $_POST['mark_unpaid_id'];
                if ($id > 0) {
                    $this->markAsUnpaid($id, $errors, $success);
                }
            }
        }

        $vendorOptions = $this->vendors->all();
        $filters = $this->readFilters($_GET, $vendorOptions);
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        foreach ($columnFilterKeys as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }
            $raw = trim((string) $_GET[$key]);
            if ($raw === '') {
                continue;
            }
            $filters[$key] = $raw;
            $columnFilters[$key] = $raw;
        }
        $queryFilters = $filters;
        $queryFilters['supplier_pessoa_id'] = (int) ($filters['supplier_pessoa_id_resolved'] ?? 0);
        if ($queryFilters['supplier_pessoa_id'] <= 0) {
            $queryFilters['supplier_pessoa_id'] = 0;
            if (!empty($filters['supplier_search'])) {
                $queryFilters['supplier_search'] = (string) $filters['supplier_search'];
            }
        }
        $totalRows = $this->entries->countForList($queryFilters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $entryRows = $this->entries->paginateForList($queryFilters, $perPage, $offset, $sortKey, $sortDir);

        $summary = $this->buildSummary($this->entries->summary());
        $overdue = $this->buildOverdueSummary($this->entries->overdueSummary());
        $bankAccountOptions = $this->bankAccounts->active();
        $paymentTerminalOptions = $this->paymentTerminals->active();
        $paymentMethodOptions = $this->paymentMethods->active();
        $statementAggregate = $this->entries->statementSummary($queryFilters);
        $statementSummary = $this->buildStatementSummary(
            $statementAggregate,
            $filters,
            $bankAccountOptions,
            $paymentTerminalOptions,
            $paymentMethodOptions
        );

        $salesSummary = [
            'connected' => false,
            'error' => null,
            'paid_total' => 0.0,
            'pending_total' => 0.0,
            'paid_orders' => 0,
            'pending_orders' => 0,
            'rows' => [],
        ];

        $categoryOptions = $this->categories->all();
        View::render('finance/list', [
            'rows' => $entryRows,
            'filters' => $filters,
            'summary' => $summary,
            'overdue' => $overdue,
            'salesSummary' => $salesSummary,
            'categoryOptions' => $categoryOptions,
            'vendorOptions' => $vendorOptions,
            'bankAccountOptions' => $bankAccountOptions,
            'paymentTerminalOptions' => $paymentTerminalOptions,
            'paymentMethodOptions' => $paymentMethodOptions,
            'statementSummary' => $statementSummary,
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'errors' => $errors,
            'success' => $success,
            'typeOptions' => FinanceEntryService::typeOptions(),
            'statusOptions' => FinanceEntryService::statusOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Financeiro',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();
        $orderPreview = null;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $paymentMethodOptions = $this->paymentMethods->active();
        $bankAccountOptions = $this->bankAccounts->active();
        $paymentTerminalOptions = $this->paymentTerminals->active();
        $categoryOptions = $this->categories->active();
        $vendorOptions = $this->vendors->all();
        $lotOptions = $this->lots->list([], 200);

        $orderOptions = $this->listOrderOptions();

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('finance_entries.edit', $this->entries->getPdo());
            $editing = true;
            $entry = $this->entries->find((int) $_GET['id']);
            if ($entry) {
                $formData = $this->entryToForm($entry);
            } else {
                $errors[] = 'Lançamento não encontrado.';
                $editing = false;
            }
        }

        if (!$editing && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
            $prefill = $this->prefillFromOrder((int) $_GET['order_id'], $errors);
            if (!empty($prefill)) {
                $formData = array_merge($formData, $prefill);
                $orderPreview = $prefill['order_preview'] ?? null;
                unset($formData['order_preview']);
            }
        }

        if (!$editing && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lot_id'])) {
            $lotId = (int) $_GET['lot_id'];
            if ($lotId > 0) {
                $formData['lot_id'] = (string) $lotId;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'finance_entries.edit' : 'finance_entries.create', $this->entries->getPdo());
            $service = new FinanceEntryService();
            $normalizedPost = $this->normalizeSupplierInput($_POST, $vendorOptions);
            $supplierRaw = isset($_POST['supplier_pessoa_id']) ? trim((string) $_POST['supplier_pessoa_id']) : '';
            if ($supplierRaw !== '' && ($normalizedPost['supplier_pessoa_id'] ?? '') === '') {
                $errors[] = 'Fornecedor inválido. Selecione um fornecedor válido da lista.';
            }
            [$entry, $serviceErrors] = $service->validate($normalizedPost, $paymentMethodOptions);
            $errors = array_merge($errors, $serviceErrors);
            $recurrence = $service->parseRecurrence($normalizedPost, $errors);
            $editing = (bool) ($entry->id ?? false);
            if (empty($errors)) {
                if ($recurrence['enabled'] && !$editing) {
                    $result = $this->createRecurringEntries($entry, $recurrence);
                    $success = $result['count'] > 1
                        ? 'Lançamentos recorrentes criados: ' . $result['count'] . ' registros.'
                        : 'Lançamento salvo com sucesso.';
                    $formData = $this->entryToForm($result['first_entry'] ?? $entry);
                } else {
                    $this->entries->save($entry);
                    $success = $editing ? 'Lançamento atualizado com sucesso.' : 'Lançamento salvo com sucesso.';
                    $formData = $this->entryToForm($entry);
                }
            } else {
                $formData = array_merge($this->emptyForm(), $normalizedPost);
            }
        }

        $lotCost = $this->resolveLotCost((int) $formData['lot_id']);

        if ($formData['category_id'] !== '') {
            $selectedCategoryId = (int) $formData['category_id'];
            $exists = false;
            foreach ($categoryOptions as $category) {
                if ((int) ($category['id'] ?? 0) === $selectedCategoryId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $selectedCategory = $this->categories->find($selectedCategoryId);
                if ($selectedCategory) {
                    $categoryOptions[] = [
                        'id' => $selectedCategory->id,
                        'name' => $selectedCategory->name,
                    ];
                }
            }
        }

        View::render('finance/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'orderPreview' => $orderPreview,
            'lotCost' => $lotCost,
            'typeOptions' => FinanceEntryService::typeOptions(),
            'statusOptions' => FinanceEntryService::statusOptions(),
            'categoryOptions' => $categoryOptions,
            'vendorOptions' => $vendorOptions,
            'lotOptions' => $lotOptions,
            'orderOptions' => $orderOptions,
            'paymentMethodOptions' => $paymentMethodOptions,
            'bankAccountOptions' => $bankAccountOptions,
            'paymentTerminalOptions' => $paymentTerminalOptions,
            'recurrenceOptions' => FinanceEntryService::recurrenceFrequencyOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar lançamento financeiro' : 'Novo lançamento financeiro',
        ]);
    }

    /**
     * @param array{enabled: bool, frequency: string, count: int} $recurrence
      * @return array{count: int, first_entry: ?FinanceEntry, last_entry: ?FinanceEntry}
     */
    private function createRecurringEntries(FinanceEntry $entry, array $recurrence): array
    {
        $count = max(1, (int) ($recurrence['count'] ?? 1));
        $frequency = (string) ($recurrence['frequency'] ?? 'mensal');
        $created = 0;
          $firstEntry = null;
        $lastEntry = null;

        $dueDate = $entry->dueDate ? \DateTime::createFromFormat('Y-m-d', $entry->dueDate) : null;
        if (!$dueDate) {
            $this->entries->save($entry);
            return ['count' => 1, 'first_entry' => $entry, 'last_entry' => $entry];
        }

        for ($i = 1; $i <= $count; $i++) {
            $data = [
                'type' => $entry->type,
                'description' => $entry->description,
                'category_id' => $entry->categoryId,
                'supplier_pessoa_id' => $entry->supplierPessoaId,
                'lot_id' => $entry->lotId,
                'order_id' => $entry->orderId,
                'amount' => $entry->amount,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $i === 1 ? $entry->status : 'pendente',
                'paid_at' => $i === 1 ? $entry->paidAt : null,
                'paid_amount' => $i === 1 ? $entry->paidAmount : null,
                'bank_account_id' => $entry->bankAccountId,
                'payment_method_id' => $entry->paymentMethodId,
                'payment_terminal_id' => $entry->paymentTerminalId,
                'notes' => $entry->notes,
            ];

            $instance = FinanceEntry::fromArray($data);
            $this->entries->save($instance);
            if ($firstEntry === null) {
                $firstEntry = $instance;
            }
            $lastEntry = $instance;
            $created++;

            if ($i < $count) {
                $this->incrementDate($dueDate, $frequency);
            }
        }

        return ['count' => $created, 'first_entry' => $firstEntry, 'last_entry' => $lastEntry];
    }

    private function incrementDate(\DateTime $date, string $frequency): void
    {
        if ($frequency === 'semanal') {
            $date->modify('+1 week');
        } elseif ($frequency === 'anual') {
            $date->modify('+1 year');
        } else {
            $date->modify('+1 month');
        }
    }

    private function markAsPaid(int $id, array &$errors, string &$success): void
    {
        $entry = $this->entries->find($id);
        if (!$entry) {
            $errors[] = 'Lançamento não encontrado.';
            return;
        }

        $paymentMethods = $this->paymentMethods->all();
        $methodMap = $this->mapById($paymentMethods);
        if ($entry->paymentMethodId && isset($methodMap[$entry->paymentMethodId])) {
            $method = $methodMap[$entry->paymentMethodId];
            if (!empty($method['requires_bank_account']) && !$entry->bankAccountId) {
                $errors[] = 'Método exige conta bancária para marcar como pago.';
                return;
            }
            if (!empty($method['requires_terminal']) && !$entry->paymentTerminalId) {
                $errors[] = 'Método exige maquininha para marcar como pago.';
                return;
            }
        }

        $entry->status = 'pago';
        $entry->paidAt = date('Y-m-d H:i:s');
        $entry->paidAmount = $entry->amount;
        $this->entries->save($entry);
        $success = 'Pagamento marcado como realizado.';
    }

    private function markAsUnpaid(int $id, array &$errors, string &$success): void
    {
        $entry = $this->entries->find($id);
        if (!$entry) {
            $errors[] = 'Lançamento não encontrado.';
            return;
        }

        $entry->status = 'pendente';
        $entry->paidAt = null;
        $entry->paidAmount = null;
        $this->entries->save($entry);
        $success = 'Pagamento marcado como pendente.';
    }

    /**
     * @param array<int, array<string, mixed>> $vendorOptions
     */
    private function readFilters(array $source, array $vendorOptions): array
    {
        $supplierRaw = isset($source['supplier_pessoa_id']) ? trim((string) $source['supplier_pessoa_id']) : '';
        $supplierResolved = $this->resolveSupplierPessoaIdFromInput($supplierRaw, $vendorOptions);
        $supplierSearch = '';
        if ($supplierRaw !== '' && $supplierResolved <= 0 && !ctype_digit($supplierRaw)) {
            $supplierSearch = $supplierRaw;
        }

        return [
            'type' => isset($source['type']) ? trim((string) $source['type']) : '',
            'status' => isset($source['status']) ? trim((string) $source['status']) : '',
            'category_id' => isset($source['category_id']) ? (int) $source['category_id'] : 0,
            'supplier_pessoa_id' => $supplierRaw,
            'supplier_pessoa_id_resolved' => $supplierResolved,
            'supplier_search' => $supplierSearch,
            'bank_account_id' => isset($source['bank_account_id']) ? (int) $source['bank_account_id'] : 0,
            'payment_terminal_id' => isset($source['payment_terminal_id']) ? (int) $source['payment_terminal_id'] : 0,
            'payment_method_id' => isset($source['payment_method_id']) ? (int) $source['payment_method_id'] : 0,
            'due_from' => isset($source['due_from']) ? trim((string) $source['due_from']) : '',
            'due_to' => isset($source['due_to']) ? trim((string) $source['due_to']) : '',
            'paid_from' => isset($source['paid_from']) ? trim((string) $source['paid_from']) : '',
            'paid_to' => isset($source['paid_to']) ? trim((string) $source['paid_to']) : '',
            'search' => trim((string) ($source['search'] ?? $source['q'] ?? '')),
        ];
    }

    /**
     * @param array<string, float|int> $aggregate
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $bankAccountOptions
     * @param array<int, array<string, mixed>> $paymentTerminalOptions
     * @param array<int, array<string, mixed>> $paymentMethodOptions
     * @return array<string, mixed>|null
     */
    private function buildStatementSummary(
        array $aggregate,
        array $filters,
        array $bankAccountOptions,
        array $paymentTerminalOptions,
        array $paymentMethodOptions
    ): ?array {
        $bankAccountId = (int) ($filters['bank_account_id'] ?? 0);
        $paymentTerminalId = (int) ($filters['payment_terminal_id'] ?? 0);
        $paymentMethodId = (int) ($filters['payment_method_id'] ?? 0);
        if ($bankAccountId <= 0 && $paymentTerminalId <= 0 && $paymentMethodId <= 0) {
            return null;
        }

        $bankMap = $this->mapById($bankAccountOptions);
        $terminalMap = $this->mapById($paymentTerminalOptions);
        $methodMap = $this->mapById($paymentMethodOptions);

        $scopeParts = [];
        if ($bankAccountId > 0) {
            $bankRow = $bankMap[$bankAccountId] ?? null;
            if ($bankRow) {
                $bankName = trim((string) ($bankRow['bank_name'] ?? ''));
                $label = trim((string) ($bankRow['label'] ?? ''));
                $scopeParts[] = $bankName !== '' && $label !== ''
                    ? 'Conta: ' . $bankName . ' · ' . $label
                    : 'Conta: ' . ($label !== '' ? $label : ('#' . $bankAccountId));
            } else {
                $scopeParts[] = 'Conta #' . $bankAccountId;
            }
        }
        if ($paymentTerminalId > 0) {
            $terminalRow = $terminalMap[$paymentTerminalId] ?? null;
            $scopeParts[] = 'Maquininha: ' . trim((string) ($terminalRow['name'] ?? ('#' . $paymentTerminalId)));
        }
        if ($paymentMethodId > 0) {
            $methodRow = $methodMap[$paymentMethodId] ?? null;
            $scopeParts[] = 'Meio: ' . trim((string) ($methodRow['name'] ?? ('#' . $paymentMethodId)));
        }

        $credits = (float) ($aggregate['credits'] ?? 0);
        $debits = (float) ($aggregate['debits'] ?? 0);
        $movementCount = (int) ($aggregate['movement_count'] ?? 0);
        $pendingReceivable = (float) ($aggregate['pending_receivable'] ?? 0);
        $pendingPayable = (float) ($aggregate['pending_payable'] ?? 0);

        return [
            'scope' => implode(' · ', $scopeParts),
            'credits' => $credits,
            'debits' => $debits,
            'balance' => $credits - $debits,
            'movement_count' => $movementCount,
            'pending_receivable' => $pendingReceivable,
            'pending_payable' => $pendingPayable,
        ];
    }

    private function buildSummary(array $rows): array
    {
        $summary = [
            'receber' => [
                'open_total' => 0.0,
                'paid_total' => 0.0,
                'open_count' => 0,
                'paid_count' => 0,
            ],
            'pagar' => [
                'open_total' => 0.0,
                'paid_total' => 0.0,
                'open_count' => 0,
                'paid_count' => 0,
            ],
        ];

        foreach ($rows as $row) {
            $type = strtolower((string) ($row['type'] ?? ''));
            if (!isset($summary[$type])) {
                continue;
            }
            $status = strtolower((string) ($row['status'] ?? ''));
            $totalAmount = (float) ($row['total_amount'] ?? 0);
            $totalPaid = (float) ($row['total_paid'] ?? 0);
            $count = (int) ($row['total_entries'] ?? 0);

            if ($status === 'pago') {
                $summary[$type]['paid_total'] += $totalPaid;
                $summary[$type]['paid_count'] += $count;
            } elseif ($status === 'parcial') {
                $summary[$type]['paid_total'] += $totalPaid;
                $summary[$type]['paid_count'] += $count;
                $summary[$type]['open_total'] += max(0, $totalAmount - $totalPaid);
                $summary[$type]['open_count'] += $count;
            } elseif ($status === 'pendente') {
                $summary[$type]['open_total'] += $totalAmount;
                $summary[$type]['open_count'] += $count;
            }
        }

        return $summary;
    }

    private function buildOverdueSummary(array $rows): array
    {
        $summary = [
            'receber' => ['total_amount' => 0.0, 'total_entries' => 0],
            'pagar' => ['total_amount' => 0.0, 'total_entries' => 0],
        ];

        foreach ($rows as $row) {
            $type = strtolower((string) ($row['type'] ?? ''));
            if (!isset($summary[$type])) {
                continue;
            }
            $summary[$type]['total_amount'] = (float) ($row['total_amount'] ?? 0);
            $summary[$type]['total_entries'] = (int) ($row['total_entries'] ?? 0);
        }

        return $summary;
    }

    private function entryToForm($entry): array
    {
        return [
            'id' => $entry->id ?? '',
            'type' => $entry->type ?? 'pagar',
            'description' => $entry->description ?? '',
            'category_id' => $entry->categoryId ?? '',
            'supplier_pessoa_id' => $entry->supplierPessoaId ?? '',
            'lot_id' => $entry->lotId ?? '',
            'order_id' => $entry->orderId ?? '',
            'amount' => number_format((float) ($entry->amount ?? 0), 2, '.', ''),
            'due_date' => $entry->dueDate ?? '',
            'status' => $entry->status ?? 'pendente',
            'paid_at' => $entry->paidAt ? substr((string) $entry->paidAt, 0, 10) : '',
            'paid_amount' => $entry->paidAmount !== null ? number_format((float) $entry->paidAmount, 2, '.', '') : '',
            'bank_account_id' => $entry->bankAccountId ?? '',
            'payment_method_id' => $entry->paymentMethodId ?? '',
            'payment_terminal_id' => $entry->paymentTerminalId ?? '',
            'notes' => $entry->notes ?? '',
            'recurrence_enabled' => '0',
            'recurrence_frequency' => 'mensal',
            'recurrence_count' => 3,
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'type' => 'pagar',
            'description' => '',
            'category_id' => '',
            'supplier_pessoa_id' => '',
            'lot_id' => '',
            'order_id' => '',
            'amount' => '0.00',
            'due_date' => '',
            'status' => 'pendente',
            'paid_at' => '',
            'paid_amount' => '',
            'bank_account_id' => '',
            'payment_method_id' => '',
            'payment_terminal_id' => '',
            'notes' => '',
            'recurrence_enabled' => '0',
            'recurrence_frequency' => 'mensal',
            'recurrence_count' => 3,
        ];
    }

    private function resolveLotCost(int $lotId): ?float
    {
        if ($lotId <= 0) {
            return null;
        }
        $rows = $this->supplies->listByLotId($lotId);
        if (empty($rows)) {
            return null;
        }
        $total = 0.0;
        foreach ($rows as $row) {
            $cost = $row['cost'] ?? null;
            if ($cost !== null) {
                $total += (float) $cost;
            }
        }
        return $total > 0 ? $total : null;
    }

    private function listOrderOptions(): array
    {
        $rows = $this->orders->listOrders([], 120, 0);
        $options = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['id'] ?? $row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $customerName = trim((string) ($row['billing_name'] ?? ''));
            $totalValue = (float) ($row['total'] ?? $row['total_sales'] ?? 0);
            $total = number_format($totalValue, 2, ',', '.');
            $paymentStatus = (string) ($row['payment_status'] ?? '');
            $label = '#' . $orderId;
            if ($customerName !== '') {
                $label .= ' - ' . $customerName;
            }
            if ($paymentStatus !== '') {
                $label .= ' (' . $paymentStatus . ')';
            }
            $label .= ' - R$ ' . $total;
            $options[] = [
                'id' => $orderId,
                'label' => $label,
                'status' => $paymentStatus,
                'total_sales' => $totalValue,
                'date_created' => $row['date_created'] ?? null,
            ];
        }
        return $options;
    }

    private function prefillFromOrder(int $orderId, array &$errors): array
    {
        if ($orderId <= 0) {
            return [];
        }

        if ($this->entries->existsByOrderId($orderId)) {
            $errors[] = 'Já existe um lançamento vinculado a este pedido.';
        }

        $summary = $this->orders->find($orderId);
        if (!$summary) {
            $errors[] = 'Pedido não encontrado.';
            return [];
        }

        $paymentStatus = strtolower((string) ($summary['payment_status'] ?? ''));
        $isPaid = $paymentStatus === 'paid';

        $customerLabel = trim((string) ($summary['billing_name'] ?? ''));

        $description = 'Venda pedido #' . $orderId;
        if ($customerLabel !== '') {
            $description .= ' - ' . $customerLabel;
        }

        $dateCreated = $summary['date_created'] ?? null;
        $totalSales = (float) ($summary['total'] ?? $summary['total_sales'] ?? 0);
        $dueDate = $dateCreated ? substr((string) $dateCreated, 0, 10) : '';

        return [
            'type' => 'receber',
            'description' => $description,
            'order_id' => (string) $orderId,
            'amount' => number_format($totalSales, 2, '.', ''),
            'due_date' => $dueDate,
            'status' => $isPaid ? 'pago' : 'pendente',
            'paid_at' => $isPaid && $dateCreated ? substr((string) $dateCreated, 0, 10) : '',
            'paid_amount' => $isPaid ? number_format($totalSales, 2, '.', '') : '',
            'order_preview' => [
                'order_id' => $orderId,
                'customer' => $customerLabel,
                'total_sales' => $totalSales,
                'date_created' => $summary['date_created'] ?? null,
                'payment_status' => $paymentStatus,
            ],
        ];
    }

    private function mapById(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, array<string, mixed>> $vendorOptions
     * @return array<string, mixed>
     */
    private function normalizeSupplierInput(array $input, array $vendorOptions = []): array
    {
        $normalized = $input;
        $supplierRaw = isset($input['supplier_pessoa_id']) ? trim((string) $input['supplier_pessoa_id']) : '';
        $supplierPessoaId = $this->resolveSupplierPessoaIdFromInput($supplierRaw, $vendorOptions);
        $normalized['supplier_pessoa_id'] = $supplierPessoaId > 0 ? $supplierPessoaId : '';
        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $vendorOptions
     */
    private function resolveSupplierPessoaIdFromInput(string $supplierInput, array $vendorOptions): int
    {
        $supplierInput = trim($supplierInput);
        if ($supplierInput === '') {
            return 0;
        }

        if (ctype_digit($supplierInput)) {
            $numeric = (int) $supplierInput;
            if ($numeric <= 0) {
                return 0;
            }

            foreach ($vendorOptions as $vendor) {
                if ((int) ($vendor['id'] ?? 0) === $numeric) {
                    return $numeric;
                }
            }

            foreach ($vendorOptions as $vendor) {
                if ((int) ($vendor['id_vendor'] ?? 0) === $numeric) {
                    return (int) ($vendor['id'] ?? 0);
                }
            }

            return $numeric;
        }

        $normalizedInput = $this->normalizeSearchText($supplierInput);
        foreach ($vendorOptions as $vendor) {
            $vendorName = trim((string) ($vendor['full_name'] ?? ''));
            if ($vendorName === '') {
                continue;
            }
            if ($this->normalizeSearchText($vendorName) === $normalizedInput) {
                return (int) ($vendor['id'] ?? 0);
            }
        }

        return 0;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = \function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
