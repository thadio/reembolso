<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class CustomerPurchaseController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private OrderRepository $orders;
    private CustomerRepository $customers;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->orders = new OrderRepository($pdo);
        $this->customers = new CustomerRepository($pdo);
    }

    public function index(): void
    {
        $errors = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if (!Auth::can('customers.view') && !Auth::can('orders.view')) {
            Auth::requirePermission('customers.view', $this->pdo);
        }

        $mode = $this->normalizeMode((string) ($_GET['mode'] ?? 'orders'));
        $rawCustomerFilter = trim((string) ($_GET['customer'] ?? ''));
        $orderStatusFilter = trim((string) ($_GET['order_status'] ?? ''));
        $startFilter = $this->normalizeDate((string) ($_GET['start'] ?? ''));
        $endFilter = $this->normalizeDate((string) ($_GET['end'] ?? ''));
        $searchFilter = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPageOptions = [50, 100, 200];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }
        $statusOptions = OrderService::statusFilterOptions();

        if ($orderStatusFilter !== '' && !array_key_exists($orderStatusFilter, $statusOptions)) {
            $orderStatusFilter = '';
        }

        if ($startFilter !== '' && $endFilter !== '' && $startFilter > $endFilter) {
            [$startFilter, $endFilter] = [$endFilter, $startFilter];
        }

        $customerIdFilter = $this->resolveCustomerId($rawCustomerFilter);
        $filters = [
            'customer_id' => $customerIdFilter,
            'customer_query' => $customerIdFilter > 0 ? '' : $rawCustomerFilter,
            'status' => $orderStatusFilter,
            'start' => $startFilter,
            'end' => $endFilter,
            'search' => $searchFilter,
        ];

        $rows = [];
        $summaryRows = [];
        $totals = $mode === 'products'
            ? ['product_lines' => 0, 'quantity_total' => 0, 'amount_total' => 0.0, 'customers' => 0]
            : ['orders' => 0, 'items' => 0, 'amount_total' => 0.0, 'customers' => 0];
        $totalRows = 0;
        $totalPages = 1;

        if (!$this->connectionError && $this->pdo) {
            try {
                if ($mode === 'products') {
                    $totalRows = $this->orders->countCustomerPurchasedProducts($filters);
                    $totalPages = max(1, (int) ceil($totalRows / $perPage));
                    if ($page > $totalPages) {
                        $page = $totalPages;
                    }
                    $offset = ($page - 1) * $perPage;
                    $rows = $this->orders->listCustomerPurchasedProducts($filters, $perPage, $offset);
                    $summaryRows = $this->orders->summarizeCustomerPurchasedProducts($filters);
                    $totals = $this->buildProductsTotalsFromSummary($summaryRows);
                } else {
                    $totalRows = $this->orders->countCustomerPurchaseOrders($filters);
                    $totalPages = max(1, (int) ceil($totalRows / $perPage));
                    if ($page > $totalPages) {
                        $page = $totalPages;
                    }
                    $offset = ($page - 1) * $perPage;
                    $rows = $this->orders->listCustomerPurchaseOrders($filters, $perPage, $offset);
                    $summaryRows = $this->orders->summarizeCustomerPurchaseOrders($filters);
                    $totals = $this->buildOrdersTotalsFromSummary($summaryRows);
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao carregar compras por clientes: ' . $e->getMessage();
            }
        }

        $customerOptions = [];
        if (!$this->connectionError && $this->pdo) {
            try {
                $customerOptions = $this->customers->listForSelect();
            } catch (\Throwable) {
                $customerOptions = [];
            }
        }

        View::render('customers/purchases', [
            'mode' => $mode,
            'rows' => $rows,
            'summaryRows' => $summaryRows,
            'totals' => $totals,
            'filters' => [
                'customer' => $rawCustomerFilter,
                'order_status' => $orderStatusFilter,
                'start' => $startFilter,
                'end' => $endFilter,
                'q' => $searchFilter,
            ],
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'statusOptions' => $statusOptions,
            'orderStatusLabels' => OrderService::statusOptions(),
            'paymentStatusLabels' => OrderService::paymentStatusOptions(),
            'customerOptions' => $customerOptions,
            'errors' => $errors,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Compras por clientes',
        ]);
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return $mode === 'products' ? 'products' : 'orders';
    }

    private function resolveCustomerId(string $rawCustomerFilter): int
    {
        if ($rawCustomerFilter === '' || !ctype_digit($rawCustomerFilter)) {
            return 0;
        }
        $customerId = (int) $rawCustomerFilter;
        return $customerId > 0 ? $customerId : 0;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }
        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $summaryRows
     * @return array<string, int|float>
     */
    private function buildOrdersTotalsFromSummary(array $summaryRows): array
    {
        $totals = [
            'orders' => 0,
            'items' => 0,
            'amount_total' => 0.0,
            'customers' => 0,
        ];
        foreach ($summaryRows as $row) {
            $totals['orders'] += (int) ($row['orders'] ?? 0);
            $totals['items'] += (int) ($row['items'] ?? 0);
            $totals['amount_total'] += (float) ($row['amount_total'] ?? 0);
        }
        $totals['customers'] = count($summaryRows);
        return $totals;
    }

    /**
     * @param array<int, array<string, mixed>> $summaryRows
     * @return array<string, int|float>
     */
    private function buildProductsTotalsFromSummary(array $summaryRows): array
    {
        $totals = [
            'product_lines' => 0,
            'quantity_total' => 0,
            'amount_total' => 0.0,
            'customers' => 0,
        ];
        foreach ($summaryRows as $row) {
            $totals['product_lines'] += (int) ($row['product_lines'] ?? 0);
            $totals['quantity_total'] += (int) ($row['quantity_total'] ?? 0);
            $totals['amount_total'] += (float) ($row['amount_total'] ?? 0);
        }
        $totals['customers'] = count($summaryRows);
        return $totals;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int|float>}
     */
    private function buildOrdersSummary(array $rows): array
    {
        $summary = [];
        $totals = [
            'orders' => 0,
            'items' => 0,
            'amount_total' => 0.0,
            'customers' => 0,
        ];

        foreach ($rows as $row) {
            $key = $this->customerSummaryKey($row);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'pessoa_id' => (int) ($row['pessoa_id'] ?? 0),
                    'customer_name' => (string) ($row['customer_name'] ?? 'Cliente não identificado'),
                    'orders' => 0,
                    'items' => 0,
                    'amount_total' => 0.0,
                ];
            }

            $itemsCount = (int) ($row['items_count'] ?? 0);
            $orderTotal = (float) ($row['order_total'] ?? 0);

            $summary[$key]['orders']++;
            $summary[$key]['items'] += $itemsCount;
            $summary[$key]['amount_total'] += $orderTotal;

            $totals['orders']++;
            $totals['items'] += $itemsCount;
            $totals['amount_total'] += $orderTotal;
        }

        $summaryRows = array_values($summary);
        usort($summaryRows, function (array $a, array $b): int {
            $amountCmp = ($b['amount_total'] <=> $a['amount_total']);
            if ($amountCmp !== 0) {
                return $amountCmp;
            }
            return strcmp((string) $a['customer_name'], (string) $b['customer_name']);
        });
        $totals['customers'] = count($summaryRows);

        return [$summaryRows, $totals];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int|float>}
     */
    private function buildProductsSummary(array $rows): array
    {
        $summary = [];
        $totals = [
            'product_lines' => 0,
            'quantity_total' => 0,
            'amount_total' => 0.0,
            'customers' => 0,
        ];

        foreach ($rows as $row) {
            $key = $this->customerSummaryKey($row);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'pessoa_id' => (int) ($row['pessoa_id'] ?? 0),
                    'customer_name' => (string) ($row['customer_name'] ?? 'Cliente não identificado'),
                    'product_lines' => 0,
                    'quantity_total' => 0,
                    'amount_total' => 0.0,
                ];
            }

            $qtyTotal = (int) ($row['quantity_total'] ?? 0);
            $amountTotal = (float) ($row['amount_total'] ?? 0);

            $summary[$key]['product_lines']++;
            $summary[$key]['quantity_total'] += $qtyTotal;
            $summary[$key]['amount_total'] += $amountTotal;

            $totals['product_lines']++;
            $totals['quantity_total'] += $qtyTotal;
            $totals['amount_total'] += $amountTotal;
        }

        $summaryRows = array_values($summary);
        usort($summaryRows, function (array $a, array $b): int {
            $amountCmp = ($b['amount_total'] <=> $a['amount_total']);
            if ($amountCmp !== 0) {
                return $amountCmp;
            }
            return strcmp((string) $a['customer_name'], (string) $b['customer_name']);
        });
        $totals['customers'] = count($summaryRows);

        return [$summaryRows, $totals];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function customerSummaryKey(array $row): string
    {
        $personId = (int) ($row['pessoa_id'] ?? 0);
        if ($personId > 0) {
            return 'id:' . $personId;
        }

        $name = strtolower(trim((string) ($row['customer_name'] ?? '')));
        if ($name !== '') {
            return 'name:' . $name;
        }

        $email = strtolower(trim((string) ($row['customer_email'] ?? '')));
        if ($email !== '') {
            return 'email:' . $email;
        }

        return 'unknown';
    }
}
