<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\PieceLotRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\VendorRepository;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class PieceLotController
{
    private PieceLotRepository $lots;
    private ProductSupplyRepository $supplies;
    private ProductRepository $products;
    private VendorRepository $vendors;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->lots = new PieceLotRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $filters = [
            'supplier' => isset($_GET['supplier']) ? trim((string) $_GET['supplier']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
        ];
        $searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($searchQuery === '') {
            $searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        }
        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : '';
        if ($sortKey === '') {
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'opened_at';
        }
        $sortDir = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : '';
        if ($sortDir === '') {
            $sortDir = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'desc';
        }
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';
        $columnFilters = [];
        foreach (['id', 'name', 'supplier', 'status', 'opened_at', 'closed_at'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            $raw = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';
            if ($raw !== '') {
                $columnFilters[$param] = $raw;
            }
        }
        $selectedLotId = isset($_GET['lot_id']) ? (int) $_GET['lot_id'] : 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                Auth::requirePermission('products.batch_intake', $this->lots->getPdo());
                $supplierPessoaId = isset($_POST['supplier_pessoa_id']) ? (int) $_POST['supplier_pessoa_id'] : 0;
                $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;
                if ($supplierPessoaId <= 0) {
                    $errors[] = 'Selecione um fornecedor para abrir o lote.';
                } else {
                    $vendor = $this->vendors->find($supplierPessoaId);
                    if (!$vendor) {
                        $errors[] = 'Fornecedor não encontrado.';
                    }
                }
                if (empty($errors)) {
                    $lot = $this->lots->create($supplierPessoaId, $notes);
                    $success = 'Lote aberto: ' . ($lot['name'] ?? ('Lote #' . ($lot['id'] ?? '')));
                    $filters['supplier'] = (string) $supplierPessoaId;
                }
            } elseif ($action === 'close') {
                Auth::requirePermission('products.batch_intake', $this->lots->getPdo());
                $lotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : 0;
                if ($lotId > 0) {
                    $this->lots->close($lotId);
                    $success = 'Lote #' . $lotId . ' encerrado.';
                }
            } elseif ($action === 'reopen') {
                Auth::requirePermission('products.batch_intake', $this->lots->getPdo());
                $lotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : 0;
                if ($lotId > 0) {
                    $this->lots->reopen($lotId);
                    $success = 'Lote #' . $lotId . ' reaberto.';
                }
            } elseif ($action === 'trash') {
                Auth::requirePermission('products.batch_intake', $this->lots->getPdo());
                $lotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : 0;
                if ($lotId > 0) {
                    $this->lots->trash($lotId);
                    $success = 'Lote #' . $lotId . ' enviado para a lixeira.';
                }
            }
        }

        $query = [];
        if ($filters['supplier'] !== '') {
            $query['supplier_pessoa_id'] = (int) $filters['supplier'];
        }
        if ($filters['status'] === '') {
            $query['status_in'] = ['aberto', 'fechado'];
        } elseif (in_array($filters['status'], ['aberto', 'fechado', 'lixeira'], true)) {
            $query['status'] = $filters['status'];
        } else {
            $filters['status'] = '';
            $query['status_in'] = ['aberto', 'fechado'];
        }
        if ($searchQuery !== '') {
            $query['search'] = $searchQuery;
        }
        foreach ($columnFilters as $param => $value) {
            $query[$param] = $value;
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 200;
        $perPageOptions = [100, 200, 500];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 200;
        }

        $totalLots = $this->lots->count($query);
        $totalPages = max(1, (int) ceil($totalLots / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $rows = $this->lots->list($query, $perPage, $offset, $sortKey, $sortDir);
        $openMap = $this->lots->listLatestOpenMap();
        $vendorOptions = $this->vendors->allWithCommission();
        $vendorNames = [];
        foreach ($vendorOptions as $vendor) {
            if (isset($vendor['id'])) {
                $vendorNames[(int) $vendor['id']] = $vendor['full_name'] ?? '';
            }
        }

        $lotStats = [];
        $lotSupplies = [];
        $lotProductIds = [];
        foreach ($rows as $row) {
            $lotId = (int) ($row['id'] ?? 0);
            if ($lotId > 0) {
                $lotStats[$lotId] = [
                    'total' => 0,
                    'sold' => 0,
                    'returned' => 0,
                    'available' => 0,
                    'lot_cost' => 0.0,
                    'sold_revenue' => 0.0,
                    'potential_revenue' => 0.0,
                ];
            }
        }

        if (!empty($lotStats)) {
            $lotSupplies = $this->supplies->listByLotIds(array_keys($lotStats));
            foreach ($lotSupplies as $supply) {
                $lotId = (int) ($supply['lot_id'] ?? 0);
                if ($lotId <= 0 || !isset($lotStats[$lotId])) {
                    continue;
                }
                $lotStats[$lotId]['total']++;
                $productId = (int) ($supply['product_id'] ?? 0);
                if ($productId > 0) {
                    $lotProductIds[] = $productId;
                }
            }
        }

        $lotProductIds = array_values(array_unique($lotProductIds));
        $productMap = [];
        if (!empty($lotProductIds)) {
            $productRows = $this->products->findByIds($lotProductIds);
            foreach ($productRows as $productRow) {
                $sku = (int) ($productRow['sku'] ?? ($productRow['id'] ?? 0));
                if ($sku > 0) {
                    $productMap[$sku] = $productRow;
                }
            }
        }

        if (!empty($lotSupplies)) {
            foreach ($lotSupplies as $supply) {
                $lotId = (int) ($supply['lot_id'] ?? 0);
                if ($lotId <= 0 || !isset($lotStats[$lotId])) {
                    continue;
                }

                $productSku = (int) ($supply['product_id'] ?? 0);
                $productRow = $productSku > 0 ? ($productMap[$productSku] ?? null) : null;
                if (!$productRow) {
                    $lotStats[$lotId]['returned']++;
                    continue;
                }

                $statusUnified = strtolower((string) ($productRow['status_unified'] ?? ($productRow['status'] ?? 'draft')));
                $availableQuantity = max(0, (int) ($productRow['quantity'] ?? 0));
                $isAvailable = $statusUnified === 'disponivel' && $availableQuantity > 0;
                $availableQty = $isAvailable ? $availableQuantity : 0;
                $isSold = $statusUnified === 'esgotado' && (int) ($productRow['last_order_id'] ?? 0) > 0;

                $lotStats[$lotId]['available'] += $availableQty;
                if ($isSold) {
                    $lotStats[$lotId]['sold']++;
                }

                $price = $productRow['price'] ?? $productRow['regular_price'] ?? null;
                if ($price !== null) {
                    $lotStats[$lotId]['potential_revenue'] += (float) $price * ($availableQty > 0 ? $availableQty : 1);
                }

                $cost = $supply['cost'] ?? null;
                if ($cost !== null && $cost !== '') {
                    $lotStats[$lotId]['lot_cost'] += (float) $cost;
                }
            }
        }

        $selectedLot = null;
        $selectedSupplierName = '';
        $lotProducts = [];
        if ($selectedLotId > 0) {
            $selectedLot = $this->lots->find($selectedLotId);
            if (!$selectedLot) {
                $errors[] = 'Lote não encontrado.';
            } else {
                $supplierPessoaId = (int) ($selectedLot['supplier_pessoa_id'] ?? 0);
                $selectedSupplierName = $vendorNames[$supplierPessoaId] ?? ('Fornecedor ' . $supplierPessoaId);
                $lotSupplies = $this->supplies->listByLotId($selectedLotId);
                $selectedProductMap = [];
                $selectedSkus = [];
                foreach ($lotSupplies as $supply) {
                    $sku = (int) ($supply['product_id'] ?? 0);
                    if ($sku > 0) {
                        $selectedSkus[] = $sku;
                    }
                }
                if (!empty($selectedSkus)) {
                    $productRows = $this->products->findByIds(array_values(array_unique($selectedSkus)));
                    foreach ($productRows as $productRow) {
                        $sku = (int) ($productRow['sku'] ?? ($productRow['id'] ?? 0));
                        if ($sku > 0) {
                            $selectedProductMap[$sku] = $productRow;
                        }
                    }
                }

                foreach ($lotSupplies as $supply) {
                    $productSku = (int) ($supply['product_id'] ?? 0);
                    $productRow = $selectedProductMap[$productSku] ?? null;
                    $statusUnified = strtolower((string) ($productRow['status_unified'] ?? ($productRow['status'] ?? 'draft')));
                    $availableQuantity = max(0, (int) ($productRow['quantity'] ?? 0));
                    $availability = ($statusUnified === 'disponivel' && $availableQuantity > 0) ? 'disponivel' : 'indisponivel';
                    $price = $productRow['price'] ?? $productRow['regular_price'] ?? null;
                    $lotProducts[] = [
                        'product_sku' => $productSku,
                        'product_id' => $productSku,
                        'sku' => (string) ($supply['sku'] ?? ''),
                        'source' => (string) ($supply['source'] ?? ''),
                        'cost' => $supply['cost'] ?? null,
                        'percentual_consignacao' => $supply['percentual_consignacao'] ?? null,
                        'name' => (string) ($productRow['name'] ?? ($productRow['post_title'] ?? ('SKU ' . $productSku))),
                        'price_min' => $price,
                        'price_max' => $price,
                        'availability_status' => $availability,
                        'quantity' => $availableQuantity,
                        'image_src' => (string) ($productRow['image_src'] ?? ''),
                    ];
                }
            }
        }

        View::render('piece_lots/list', [
            'errors' => $errors,
            'success' => $success,
            'rows' => $rows,
            'filters' => $filters,
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalLots' => $totalLots,
            'totalPages' => $totalPages,
            'vendorOptions' => $vendorOptions,
            'vendorNames' => $vendorNames,
            'openMap' => $openMap,
            'lotStats' => $lotStats,
            'selectedLot' => $selectedLot,
            'selectedSupplierName' => $selectedSupplierName,
            'lotProducts' => $lotProducts,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Lotes de produtos',
        ]);
    }
}
