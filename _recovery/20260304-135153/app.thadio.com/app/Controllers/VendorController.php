<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\VendorRepository;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class VendorController
{
    private VendorRepository $repository;
    private ProductRepository $products;
    private ProductSupplyRepository $supplies;
    private ?PDO $pdo;
    private ?string $connectionError;
    /** @var array<string, bool> */
    private array $orderItemsColumnCache = [];
    /** @var array<string, bool> */
    private array $ordersColumnCache = [];

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new VendorRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $redirect = 'pessoa-list.php?role=fornecedor';
        Auth::requirePermission('people.view', $this->pdo);
        header('Location: ' . $redirect);
        exit;
    }

    public function form(): void
    {
        $editingId = isset($_GET['id']) ? (int) $_GET['id'] : ((isset($_POST['id']) && $_POST['id'] !== '') ? (int) $_POST['id'] : 0);
        $redirect = 'pessoa-cadastro.php?role=fornecedor';
        if ($editingId > 0) {
            $redirect .= '&id=' . $editingId;
        }
        Auth::requirePermission($editingId > 0 ? 'people.edit' : 'people.create', $this->pdo);
        header('Location: ' . $redirect);
        exit;
    }

    public function report(): void
    {
        $errors = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('vendors.report', $this->pdo);

        $supplierFilter = trim((string) ($_GET['supplier'] ?? ''));
        $normalizedSupplierInput = $this->normalizeSearchText($supplierFilter);
        if (($supplierFilter !== '' && ctype_digit($supplierFilter) && (int) $supplierFilter <= 0)
            || $normalizedSupplierInput === 'sem fornecedor'
        ) {
            $supplierFilter = '';
        }
        $sourceFilter = trim((string) ($_GET['source'] ?? ''));
        $globalFilter = trim((string) ($_GET['q'] ?? ''));

        $filters = [
            'supplier' => $supplierFilter,
            'source' => $sourceFilter,
            'global' => $globalFilter,
        ];

        $supplies = $this->supplies->listWithVendorByFilters($filters);
        $vendorOptions = $this->repository->all();

        $productMap = [];
        $productIds = [];
        foreach ($supplies as $supply) {
            $productId = (int) ($supply['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }
        if ($productIds) {
            $products = $this->products->findByIds(array_values($productIds));
            foreach ($products as $productRow) {
                $id = (int) (($productRow['id'] ?? 0) ?: ($productRow['ID'] ?? 0));
                if ($id > 0) {
                    $productMap[$id] = $productRow;
                }
            }
        }

        $rows = [];
        foreach ($supplies as $supply) {
            $productId = (int) ($supply['product_id'] ?? 0);
            $product = $productMap[$productId] ?? null;
            $priceRaw = $product
                ? ($product['price'] ?? $product['regular_price'] ?? $product['min_price'] ?? $product['max_price'] ?? null)
                : null;
            $price = $priceRaw !== null && $priceRaw !== '' ? (float) $priceRaw : null;

            $quantityRaw = $product['quantity'] ?? $product['stock'] ?? null;
            $quantity = $quantityRaw !== null && $quantityRaw !== '' ? (int) $quantityRaw : null;

            $costRaw = $supply['cost'] ?? ($product['cost'] ?? null);
            $cost = $costRaw !== null && $costRaw !== '' ? (float) $costRaw : null;
            $potential = ($price !== null && $quantity !== null) ? $price * $quantity : null;

            $supplierName = (string) ($supply['supplier_name'] ?? '');
            if ($supplierName === '') {
                $supplierName = 'Sem fornecedor';
            }
            $name = (string) ($product['name'] ?? ($product['post_title'] ?? ''));
            if ($name === '' && $productId > 0) {
                $name = 'Produto #' . $productId;
            }

            $rows[] = [
                'supplier_id' => (int) ($supply['supplier_id_vendor'] ?? 0),
                'supplier_name' => $supplierName,
                'product_id' => $productId,
                'sku' => (string) ($product['sku'] ?? $supply['sku'] ?? ''),
                'name' => $name,
                'status' => (string) ($product['post_status'] ?? ''),
                'source' => (string) ($supply['source'] ?? ''),
                'quantity' => $quantity,
                'price' => $price,
                'cost' => $cost,
                'percentual_consignacao' => $supply['percentual_consignacao'] !== null
                    ? (float) $supply['percentual_consignacao']
                    : null,
                'potential_value' => $potential,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $supplierCmp = strcmp($a['supplier_name'], $b['supplier_name']);
            if ($supplierCmp !== 0) {
                return $supplierCmp;
            }
            return strcmp($a['name'], $b['name']);
        });

        $summary = [];
        $totals = [
            'product_count' => 0,
            'unit_count' => 0,
            'potential_value' => 0.0,
            'invested_value' => 0.0,
        ];

        foreach ($rows as $row) {
            $key = (string) $row['supplier_id'];
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'supplier_id' => $row['supplier_id'],
                    'supplier_name' => $row['supplier_name'] !== '' ? $row['supplier_name'] : 'Sem fornecedor',
                    'product_count' => 0,
                    'unit_count' => 0,
                    'potential_value' => 0.0,
                    'invested_value' => 0.0,
                ];
            }

            $summary[$key]['product_count']++;
            $totals['product_count']++;

            if ($row['quantity'] !== null) {
                $summary[$key]['unit_count'] += $row['quantity'];
                $totals['unit_count'] += $row['quantity'];
            }

            if ($row['price'] !== null && $row['quantity'] !== null) {
                $summary[$key]['potential_value'] += $row['price'] * $row['quantity'];
                $totals['potential_value'] += $row['price'] * $row['quantity'];
            }

            if ($row['cost'] !== null && $row['quantity'] !== null) {
                $summary[$key]['invested_value'] += $row['cost'] * $row['quantity'];
                $totals['invested_value'] += $row['cost'] * $row['quantity'];
            }
        }

        $summaryRows = array_values($summary);
        usort($summaryRows, function (array $a, array $b): int {
            $valueCmp = $b['potential_value'] <=> $a['potential_value'];
            if ($valueCmp !== 0) {
                return $valueCmp;
            }
            return strcmp($a['supplier_name'], $b['supplier_name']);
        });

        View::render('vendors/report', [
            'rows' => $rows,
            'summaryRows' => $summaryRows,
            'totals' => $totals,
            'errors' => $errors,
            'filters' => [
                'supplier' => $supplierFilter,
                'source' => $sourceFilter,
                'global' => $globalFilter,
            ],
            'vendorOptions' => $vendorOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Relatório de produtos por fornecedor'
        ]);
    }

    public function salesReport(): void
    {
        $errors = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('vendors.report', $this->pdo);

        $startFilter = trim((string) ($_GET['start'] ?? ''));
        $endFilter = trim((string) ($_GET['end'] ?? ''));
        $rawSupplierFilter = trim((string) ($_GET['supplier'] ?? ''));
        $sourceFilter = trim((string) ($_GET['source'] ?? ''));

        $vendorOptions = $this->repository->all();
        $supplierFilter = $this->normalizeSupplierFilter($rawSupplierFilter, $vendorOptions);
        $supplierNameFilter = ($supplierFilter !== '' && !ctype_digit($supplierFilter))
            ? $this->normalizeSearchText($supplierFilter)
            : '';
        $supplierFilterDisplay = $rawSupplierFilter;
        if ($supplierFilter === '' && $rawSupplierFilter !== '') {
            $rawNormalized = $this->normalizeSearchText($rawSupplierFilter);
            if ((ctype_digit($rawSupplierFilter) && (int) $rawSupplierFilter <= 0) || $rawNormalized === 'sem fornecedor') {
                $supplierFilterDisplay = '';
            }
        }
        $items = $this->loadSalesItems($startFilter, $endFilter);
        $productIds = [];
        $orderTotals = [];

        foreach ($items as $item) {
            $orderId = (int) ($item['order_id'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            $variationId = (int) ($item['variation_id'] ?? 0);
            $refId = $productId > 0 ? $productId : $variationId;
            if ($refId > 0) {
                $productIds[$refId] = true;
            }

            $net = $this->toFloat($item['product_net_revenue'] ?? null) ?? 0.0;
            $shippingTotal = $this->toFloat($item['shipping_total'] ?? null) ?? 0.0;
            $taxTotal = $this->toFloat($item['tax_total'] ?? null) ?? 0.0;
            $orderTotal = $this->toFloat($item['order_total'] ?? null) ?? 0.0;
            $orderDiscount = $this->toFloat($item['order_discount_total'] ?? null) ?? 0.0;

            if (!isset($orderTotals[$orderId])) {
                $orderTotals[$orderId] = [
                    'net_items_total' => 0.0,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => $taxTotal,
                    'order_total' => $orderTotal,
                    'discount_total' => $orderDiscount,
                ];
            }
            $orderTotals[$orderId]['net_items_total'] += $net;
        }

        foreach ($orderTotals as &$orderTotalRow) {
            $shippingTax = max(0.0, (float) ($orderTotalRow['shipping_total'] ?? 0.0))
                + max(0.0, (float) ($orderTotalRow['tax_total'] ?? 0.0));
            $discountPool = max(0.0, (float) ($orderTotalRow['discount_total'] ?? 0.0));
            $orderTotal = (float) ($orderTotalRow['order_total'] ?? 0.0);
            $netItems = (float) ($orderTotalRow['net_items_total'] ?? 0.0);

            $additionsPool = $shippingTax;
            if ($additionsPool <= 0.0) {
                // Fallback legado: quando frete/imposto não foram persistidos por coluna.
                $fallback = $orderTotal - $netItems + $discountPool;
                $additionsPool = $fallback > 0.0 ? $fallback : 0.0;
            }

            $orderTotalRow['discount_pool'] = $discountPool;
            $orderTotalRow['additions_pool'] = $additionsPool;
        }
        unset($orderTotalRow);

        $supplyMap = $this->supplies->listByProductIds(array_keys($productIds));
        $productMap = [];
        if (!empty($productIds)) {
            $products = $this->products->findByIds(array_keys($productIds));
            foreach ($products as $productRow) {
                $sku = (int) (($productRow['id'] ?? 0) ?: ($productRow['ID'] ?? 0));
                if ($sku > 0) {
                    $productMap[$sku] = $productRow;
                }
            }
        }

        $vendorCommissionCache = [];
        $rows = [];

        foreach ($items as $item) {
            $orderId = (int) ($item['order_id'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            $variationId = (int) ($item['variation_id'] ?? 0);
            $refId = $productId > 0 ? $productId : $variationId;
            $supply = $refId > 0 ? ($supplyMap[$refId] ?? null) : null;
            $product = $refId > 0 ? ($productMap[$refId] ?? null) : null;
            $productMetadata = $this->decodeMetadata($product['metadata'] ?? null);

            $supplierId = (int) ($supply['supplier_id_vendor'] ?? 0);
            $supplierName = (string) ($supply['supplier_name'] ?? '');
            if ($supplierName === '') {
                $supplierName = 'Sem fornecedor';
            }

            if ($supplierFilter !== '') {
                if (ctype_digit($supplierFilter)) {
                    if ($supplierId !== (int) $supplierFilter) {
                        continue;
                    }
                } else {
                    $normalizedSupplierName = $this->normalizeSearchText($supplierName);
                    if ($supplierNameFilter === '' || strpos($normalizedSupplierName, $supplierNameFilter) === false) {
                        continue;
                    }
                }
            }

            $source = (string) ($supply['source'] ?? ($product['source'] ?? ''));
            if ($sourceFilter !== '' && $source !== $sourceFilter) {
                continue;
            }

            $qty = (int) ($item['product_qty'] ?? 0);
            $net = $this->toFloat($item['product_net_revenue'] ?? null) ?? 0.0;
            $unitNet = $qty > 0 ? $net / $qty : null;
            $itemUnitPrice = $this->toFloat($item['item_unit_price'] ?? null);
            $itemDiscount = $this->toFloat($item['item_discount'] ?? null);
            $itemAdditions = $this->toFloat($item['item_additions'] ?? null);

            $orderTotalsRow = $orderTotals[$orderId] ?? [
                'net_items_total' => 0.0,
                'discount_pool' => 0.0,
                'additions_pool' => 0.0,
            ];
            $netItemsTotal = (float) $orderTotalsRow['net_items_total'];
            $rate = $netItemsTotal > 0 ? ($net / $netItemsTotal) : 0.0;

            $discount = 0.0;
            if ($itemDiscount !== null && $itemDiscount > 0.0) {
                $discount = $itemDiscount;
            } elseif ((float) ($orderTotalsRow['discount_pool'] ?? 0.0) > 0.0 && $rate > 0.0) {
                $discount = (float) $orderTotalsRow['discount_pool'] * $rate;
            } elseif ($itemUnitPrice !== null && $qty > 0) {
                $derivedDiscount = ($itemUnitPrice * $qty) - $net;
                if ($derivedDiscount > 0.0) {
                    $discount = $derivedDiscount;
                }
            }

            $additions = 0.0;
            if ($itemAdditions !== null && $itemAdditions > 0.0) {
                $additions = $itemAdditions;
            } elseif ((float) ($orderTotalsRow['additions_pool'] ?? 0.0) > 0.0 && $rate > 0.0) {
                $additions = (float) $orderTotalsRow['additions_pool'] * $rate;
            }

            $cost = $this->firstNumericValue([
                $supply['cost'] ?? null,
                $item['item_cost'] ?? null,
                $product['cost'] ?? null,
                $this->extractMetadataNumeric($productMetadata, ['cost', 'acquisition_cost', 'purchase_cost', 'supplier_cost']),
            ]);
            $costTotal = $cost !== null ? $cost * $qty : null;

            $supplierPessoaId = (int) ($supply['supplier_pessoa_id'] ?? 0);
            if (!array_key_exists($supplierPessoaId, $vendorCommissionCache)) {
                $vendorCommissionCache[$supplierPessoaId] = null;
                if ($supplierPessoaId > 0) {
                    $vendor = $this->repository->findByPersonId($supplierPessoaId);
                    $vendorCommissionCache[$supplierPessoaId] = $vendor ? $vendor->commissionRate : null;
                }
            }
            $vendorCommission = $vendorCommissionCache[$supplierPessoaId] ?? null;

            $consignPercent = $this->firstPositiveNumericValue([
                $supply['percentual_consignacao'] ?? null,
                $item['item_consign_percent'] ?? null,
                $product['percentual_consignacao'] ?? null,
                $product['consignment_percent'] ?? null,
                $this->extractMetadataNumeric($productMetadata, ['percentual_consignacao', 'consignment_percent', 'commission_percentage'], true),
                $vendorCommission,
            ]);
            if ($source !== 'consignacao') {
                $consignPercent = null;
            }
            $consignPayment = null;
            if ($consignPercent !== null) {
                $consignPayment = $net * ($consignPercent / 100);
            }

            $productName = (string) ($item['product_name'] ?? '');
            if ($productName === '' && $refId > 0) {
                $productName = 'Produto #' . $refId;
            }

            $dateCreated = (string) ($item['date_created'] ?? '');
            $dateLabel = $dateCreated !== '' ? substr($dateCreated, 0, 10) : '';

            $rows[] = [
                'order_id' => $orderId,
                'order_date' => $dateLabel,
                'order_status' => (string) ($item['order_status'] ?? ''),
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'source' => $source,
                'sku' => (string) ($supply['sku'] ?? ($item['product_sku'] ?? '')),
                'product_name' => $productName,
                'quantity' => $qty,
                'unit_net' => $unitNet,
                'net_total' => $net,
                'discount' => $discount,
                'additions' => $additions,
                'cost_unit' => $cost,
                'cost_total' => $costTotal,
                'consign_percent' => $consignPercent,
                'consign_payment' => $consignPayment,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $supplierCmp = strcmp($a['supplier_name'], $b['supplier_name']);
            if ($supplierCmp !== 0) {
                return $supplierCmp;
            }
            return strcmp((string) $b['order_date'], (string) $a['order_date']);
        });

        $summary = [];
        $totals = [
            'item_count' => 0,
            'unit_count' => 0,
            'net_total' => 0.0,
            'discount_total' => 0.0,
            'additions_total' => 0.0,
            'cost_total' => 0.0,
            'consign_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $key = (string) $row['supplier_id'];
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'supplier_id' => $row['supplier_id'],
                    'supplier_name' => $row['supplier_name'],
                    'item_count' => 0,
                    'unit_count' => 0,
                    'net_total' => 0.0,
                    'discount_total' => 0.0,
                    'additions_total' => 0.0,
                    'cost_total' => 0.0,
                    'consign_total' => 0.0,
                ];
            }

            $summary[$key]['item_count']++;
            $summary[$key]['unit_count'] += (int) $row['quantity'];
            $summary[$key]['net_total'] += (float) $row['net_total'];
            $summary[$key]['discount_total'] += (float) $row['discount'];
            $summary[$key]['additions_total'] += (float) $row['additions'];
            if ($row['cost_total'] !== null) {
                $summary[$key]['cost_total'] += (float) $row['cost_total'];
            }
            if ($row['consign_payment'] !== null) {
                $summary[$key]['consign_total'] += (float) $row['consign_payment'];
            }

            $totals['item_count']++;
            $totals['unit_count'] += (int) $row['quantity'];
            $totals['net_total'] += (float) $row['net_total'];
            $totals['discount_total'] += (float) $row['discount'];
            $totals['additions_total'] += (float) $row['additions'];
            if ($row['cost_total'] !== null) {
                $totals['cost_total'] += (float) $row['cost_total'];
            }
            if ($row['consign_payment'] !== null) {
                $totals['consign_total'] += (float) $row['consign_payment'];
            }
        }

        $summaryRows = array_values($summary);
        usort($summaryRows, function (array $a, array $b): int {
            $valueCmp = $b['net_total'] <=> $a['net_total'];
            if ($valueCmp !== 0) {
                return $valueCmp;
            }
            return strcmp($a['supplier_name'], $b['supplier_name']);
        });

        View::render('vendors/sales_report', [
            'rows' => $rows,
            'summaryRows' => $summaryRows,
            'totals' => $totals,
            'errors' => $errors,
            'filters' => [
                'start' => $startFilter,
                'end' => $endFilter,
                'supplier' => $supplierFilterDisplay,
                'source' => $sourceFilter,
            ],
            'vendorOptions' => $vendorOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Relatório de venda de produtos por fornecedor'
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSalesItems(string $startFilter, string $endFilter): array
    {
        if (!$this->pdo) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($startFilter !== '') {
            $where[] = 'DATE(o.ordered_at) >= :start_date';
            $params[':start_date'] = $startFilter;
        }

        if ($endFilter !== '') {
            $where[] = 'DATE(o.ordered_at) <= :end_date';
            $params[':end_date'] = $endFilter;
        }

        $hasProductSku = $this->orderItemsHasColumn('product_sku');
        $hasProductId = $this->orderItemsHasColumn('product_id');
        $hasSku = $this->orderItemsHasColumn('sku');
        $hasProductName = $this->orderItemsHasColumn('product_name');
        $hasQuantity = $this->orderItemsHasColumn('quantity');
        $hasProductQty = $this->orderItemsHasColumn('product_qty');
        $hasTotal = $this->orderItemsHasColumn('total');
        $hasProductNetRevenue = $this->orderItemsHasColumn('product_net_revenue');

        if ($hasProductSku && $hasProductId) {
            $productRefExpr = 'COALESCE(oi.product_sku, oi.product_id)';
        } elseif ($hasProductSku) {
            $productRefExpr = 'oi.product_sku';
        } elseif ($hasProductId) {
            $productRefExpr = 'oi.product_id';
        } else {
            $productRefExpr = '0';
        }

        $productSkuExpr = $hasSku
            ? 'oi.sku'
            : "CAST({$productRefExpr} AS CHAR)";
        $productNameExpr = $hasProductName ? 'oi.product_name' : "''";

        if ($hasQuantity) {
            $quantityExpr = 'oi.quantity';
        } elseif ($hasProductQty) {
            $quantityExpr = 'oi.product_qty';
        } else {
            $quantityExpr = '1';
        }

        if ($hasTotal) {
            $totalExpr = 'oi.total';
        } elseif ($hasProductNetRevenue) {
            $totalExpr = 'oi.product_net_revenue';
        } else {
            $totalExpr = '0';
        }

        $sql = "SELECT
                    o.id AS order_id,
                    o.status AS order_status,
                    o.ordered_at AS date_created,
                    {$productRefExpr} AS product_id,
                    0 AS variation_id,
                    {$productNameExpr} AS product_name,
                    {$productSkuExpr} AS product_sku,
                    {$quantityExpr} AS product_qty,
                    {$totalExpr} AS product_net_revenue,
                    {$this->ordersColumnExpr(['shipping_total'], '0')} AS shipping_total,
                    {$this->ordersColumnExpr(['tax_total'], '0')} AS tax_total,
                    {$this->ordersColumnExpr(['total'], '0')} AS order_total,
                    {$this->ordersColumnExpr(['discount_total'], '0')} AS order_discount_total,
                    {$this->orderItemsColumnExpr(['price', 'unit_price'], 'NULL')} AS item_unit_price,
                    {$this->orderItemsColumnExpr(['coupon_amount', 'discount_amount', 'discount_total', 'discount'], 'NULL')} AS item_discount,
                    {$this->orderItemsColumnExpr(['additions_total', 'addition_total', 'fee_total', 'surcharge_total'], 'NULL')} AS item_additions,
                    {$this->orderItemsColumnExpr(['cost', 'unit_cost', 'acquisition_cost'], 'NULL')} AS item_cost,
                    {$this->orderItemsColumnExpr(['percentual_consignacao', 'consignment_percent', 'consign_percent', 'commission_rate'], 'NULL')} AS item_consign_percent
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.ordered_at DESC, o.id DESC, oi.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function orderItemsHasColumn(string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        if (array_key_exists($column, $this->orderItemsColumnCache)) {
            return $this->orderItemsColumnCache[$column];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'order_items'
                  AND column_name = :column
            ");
            $stmt->execute([':column' => $column]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
            $this->orderItemsColumnCache[$column] = $exists;
            return $exists;
        } catch (\Throwable) {
            $this->orderItemsColumnCache[$column] = false;
            return false;
        }
    }

    private function ordersHasColumn(string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        if (array_key_exists($column, $this->ordersColumnCache)) {
            return $this->ordersColumnCache[$column];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'orders'
                  AND column_name = :column
            ");
            $stmt->execute([':column' => $column]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
            $this->ordersColumnCache[$column] = $exists;
            return $exists;
        } catch (\Throwable) {
            $this->ordersColumnCache[$column] = false;
            return false;
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function orderItemsColumnExpr(array $columns, string $fallback): string
    {
        foreach ($columns as $column) {
            if ($this->orderItemsHasColumn($column)) {
                return 'oi.`' . str_replace('`', '', $column) . '`';
            }
        }
        return $fallback;
    }

    /**
     * @param array<int, string> $columns
     */
    private function ordersColumnExpr(array $columns, string $fallback): string
    {
        foreach ($columns as $column) {
            if ($this->ordersHasColumn($column)) {
                return 'o.`' . str_replace('`', '', $column) . '`';
            }
        }
        return $fallback;
    }

    /**
     * @param mixed $value
     */
    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['R$', ' '], '', $normalized);
        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (strpos($normalized, ',') !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNumericValue(array $values): ?float
    {
        foreach ($values as $value) {
            $parsed = $this->toFloat($value);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        return null;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstPositiveNumericValue(array $values): ?float
    {
        foreach ($values as $value) {
            $parsed = $this->toFloat($value);
            if ($parsed !== null && $parsed > 0.0) {
                return $parsed;
            }
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeMetadata($value): array
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
     * @param array<string, mixed> $metadata
     * @param array<int, string> $keys
     */
    private function extractMetadataNumeric(array $metadata, array $keys, bool $positiveOnly = false): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $parsed = $this->toFloat($metadata[$key]);
            if ($parsed === null) {
                continue;
            }
            if ($positiveOnly && $parsed <= 0.0) {
                continue;
            }

            return $parsed;
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $vendorOptions
     */
    private function normalizeSupplierFilter(string $supplierFilter, array $vendorOptions): string
    {
        if ($supplierFilter === '') {
            return '';
        }

        if (ctype_digit($supplierFilter)) {
            $normalizedNumeric = (int) $supplierFilter;
            return $normalizedNumeric > 0 ? (string) $normalizedNumeric : '';
        }

        $normalizedFilter = $this->normalizeSearchText($supplierFilter);
        if ($normalizedFilter === 'sem fornecedor') {
            return '';
        }

        foreach ($vendorOptions as $vendor) {
            $vendorName = trim((string) ($vendor['full_name'] ?? ''));
            if ($vendorName === '') {
                continue;
            }
            if ($this->normalizeSearchText($vendorName) === $normalizedFilter) {
                $vendorCode = (int) ($vendor['id_vendor'] ?? 0);
                return $vendorCode > 0 ? (string) $vendorCode : '';
            }
        }

        return $supplierFilter;
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
