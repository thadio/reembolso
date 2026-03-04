<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ConsignmentIntakeRepository;
use App\Repositories\PieceLotRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\SkuReservationRepository;
use App\Repositories\VendorRepository;
use App\Services\ProductService;
use App\Services\SkuReservationService;
use App\Support\Auth;
use App\Support\Html;
use PDO;

class BatchIntakeController
{
    private ?PDO $pdo;
    private ProductSupplyRepository $supplies;
    private VendorRepository $vendors;
    private PieceLotRepository $lots;
    private ProductService $service;
    private ProductRepository $products;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->lots = new PieceLotRepository($pdo);
        $this->service = new ProductService();
        $this->products = new ProductRepository($pdo);
        $this->connectionError = $connectionError;
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $lotSuccess = '';
        $createdItems = [];
        $warnings = [];
        $consignment = null;
        $consignmentTotals = null;
        $consignmentRepo = null;
        $selectedLotId = null;
        $selectedLot = null;
        $lotOptions = [];
        $skuContextKey = isset($_POST['sku_context_key'])
            ? trim((string) $_POST['sku_context_key'])
            : (isset($_GET['sku_context_key']) ? trim((string) $_GET['sku_context_key']) : '');
        if ($skuContextKey === '') {
            $skuContextKey = $this->newSkuContextKey();
        }
        $skuContext = 'batch_intake';
        $skuSessionId = session_id() ?: null;
        $user = Auth::user();
        $skuUserId = $user && isset($user['id']) ? (int) $user['id'] : null;
        $skuService = null;
        if ($this->pdo) {
            try {
                $skuService = new SkuReservationService(
                    new SkuReservationRepository($this->pdo),
                    $this->products
                );
            } catch (\Throwable $e) {
                error_log('Erro ao iniciar reserva de SKU: ' . $e->getMessage());
            }
        }

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'reserve_sku') {
            Auth::requirePermission('products.batch_intake', $this->pdo);
            $this->handleReserveSku($skuService, $skuContext, $skuContextKey, $skuUserId, $skuSessionId);
            return;
        }

        $consignmentId = isset($_POST['consignment_id'])
            ? (int) $_POST['consignment_id']
            : (isset($_GET['consignment']) ? (int) $_GET['consignment'] : 0);

        $vendorOptions = $this->vendors->allWithCommission();
        $selectedVendorId = isset($_POST['vendorId'])
            ? (int) $_POST['vendorId']
            : (isset($_GET['vendor']) ? (int) $_GET['vendor'] : null);
        $selectedSource = isset($_POST['source']) ? (string) $_POST['source'] : ((string) ($_GET['source'] ?? 'consignacao'));
        if ($consignmentId > 0) {
            $selectedSource = 'consignacao';
        }
        $selectedSource = in_array($selectedSource, ['consignacao', 'compra', 'doacao'], true) ? $selectedSource : 'consignacao';
        $submittedRows = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        $selectedLotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : null;
        $lotAction = isset($_POST['lot_action']) ? (string) $_POST['lot_action'] : '';

        if ($consignmentId > 0 && $this->pdo) {
            $consignmentRepo = new ConsignmentIntakeRepository($this->pdo);
            $consignment = $consignmentRepo->findWithVendor($consignmentId);
            if ($consignment) {
                $selectedVendorId = (int) ($consignment['supplier_pessoa_id'] ?? $selectedVendorId);
                $items = $consignmentRepo->listItems($consignmentId);
                $received = 0;
                foreach ($items as $item) {
                    $received += (int) ($item['quantity'] ?? 0);
                }
                $returnedMap = $consignmentRepo->getReturnTotalsByCategory($consignmentId);
                $returned = array_sum($returnedMap);
                $consignmentTotals = [
                    'received' => $received,
                    'returned' => $returned,
                    'remaining' => max(0, $received - $returned),
                ];
            } else {
                $errors[] = 'Pre-lote informado não encontrado.';
            }
        }

        $selectedVendor = $this->findVendor($vendorOptions, $selectedVendorId);
        $defaultCommission = $selectedVendor['commission_rate'] ?? null;
        $defaultCost = isset($_POST['defaultCost']) ? trim((string) $_POST['defaultCost']) : null;

        if ($selectedVendor) {
            $lotOptions = $this->lots->list(['supplier_pessoa_id' => (int) ($selectedVendor['id'] ?? 0)], 200);
        } else {
            $lotOptions = $this->lots->listOpenLots();
        }
        if ($selectedVendor && !$selectedLotId) {
            $latestLot = $this->lots->latestOpenBySupplier((int) ($selectedVendor['id'] ?? 0));
            if ($latestLot) {
                $selectedLotId = (int) $latestLot['id'];
                $selectedLot = $latestLot;
            }
        }
        if ($selectedLotId) {
            $selectedLot = $this->lots->find($selectedLotId) ?? $selectedLot;
        }
        if ($selectedLot && $selectedLotId) {
            $exists = false;
            foreach ($lotOptions as $lot) {
                if ((int) ($lot['id'] ?? 0) === (int) $selectedLotId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $lotOptions[] = $selectedLot;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lotAction === 'create_lot') {
            Auth::requirePermission('products.batch_intake', $this->pdo);
            if (!$this->pdo) {
                $errors[] = 'Sem conexão com banco.';
            }
            if (!$selectedVendor) {
                $errors[] = 'Selecione um fornecedor para abrir o lote.';
            }
            if (empty($errors) && $selectedVendor) {
                $newLot = $this->lots->create((int) ($selectedVendor['id'] ?? 0));
                $selectedLotId = (int) $newLot['id'];
                $selectedLot = $newLot;
                $lotOptions = $selectedVendor
                    ? $this->lots->list(['supplier_pessoa_id' => (int) ($selectedVendor['id'] ?? 0)], 200)
                    : $this->lots->listOpenLots();
                $lotSuccess = 'Lote aberto: ' . ($newLot['name'] ?? ('Lote #' . $selectedLotId));
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lotAction === 'reopen_lot') {
            Auth::requirePermission('products.batch_intake', $this->pdo);
            if (!$this->pdo) {
                $errors[] = 'Sem conexão com banco.';
            }
            if (!$selectedLotId) {
                $errors[] = 'Selecione um lote para reabrir.';
            }
            if (empty($errors) && $selectedLotId) {
                $this->lots->reopen($selectedLotId);
                $selectedLot = $this->lots->find($selectedLotId);
                $lotOptions = $selectedVendor
                    ? $this->lots->list(['supplier_pessoa_id' => (int) ($selectedVendor['id'] ?? 0)], 200)
                    : $this->lots->listOpenLots();
                $lotSuccess = 'Lote reaberto: ' . ($selectedLot['name'] ?? ('Lote #' . $selectedLotId));
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($lotAction, ['create_lot', 'reopen_lot'], true)) {
            Auth::requirePermission('products.batch_intake', $this->pdo);
            $items = $this->sanitizeItems($_POST['items'] ?? []);

            if (!$this->pdo) {
                $errors[] = 'Sem conexão com banco.';
            }
            if (!$selectedVendor) {
                $errors[] = 'Selecione um fornecedor para o lote.';
            }
            if (!$selectedLotId) {
                $errors[] = 'Escolha ou abra um lote para esse fornecedor.';
            } elseif ($selectedVendor && $selectedLot && (int) ($selectedLot['supplier_pessoa_id'] ?? 0) !== (int) ($selectedVendor['id'] ?? 0)) {
                $errors[] = 'O lote selecionado não pertence a este fornecedor.';
            } elseif ($selectedLot && (string) ($selectedLot['status'] ?? '') !== 'aberto') {
                $errors[] = 'Lote fechado. Reabra para inserir novos produtos.';
            }
            if (empty($items)) {
                $errors[] = 'Inclua pelo menos um produto com nome e preço.';
            }

            if (empty($errors)) {
                // MIGRADO: Buscar categorias e SKU do banco local
                $categoryOptions = $this->products->listCategories();
                $defaultCategoryIds = $this->resolveDefaultCategoryIds($categoryOptions);
                $nextSkuSeed = $this->products->maxNumericSku() + 1;
                $rowNumber = 0;
                foreach ($items as $item) {
                    $rowNumber++;

                    if ($item['name'] === '' || $item['price'] === '') {
                        $errors[] = "Linha {$rowNumber}: informe nome e preço.";
                        continue;
                    }

                    $percentualConsignacao = $item['percentualConsignacao'] ?? $defaultCommission;
                    $lineCost = $item['cost'] ?? $defaultCost;
                    if ($selectedSource === 'compra' && ($lineCost === null || $lineCost === '')) {
                        $errors[] = "Linha {$rowNumber}: informe o custo (compra/garimpo).";
                        continue;
                    }
                    if ($selectedSource === 'doacao') {
                        $lineCost = '0';
                        $percentualConsignacao = null;
                    }
                    if ($selectedSource !== 'consignacao') {
                        $percentualConsignacao = null;
                    }

                    $payload = [
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'source' => $selectedSource,
                        'supplier' => $selectedVendor['id'] ?? null,
                        'percentualConsignacao' => $percentualConsignacao,
                        'cost' => $lineCost !== null && $lineCost !== '' ? $lineCost : null,
                        'quantity' => '1',
                        'trackInventory' => '1',
                        'stockStatus' => 'instock',
                        'status' => 'draft',
                        'catalogVisibility' => 'visible',
                        'categoryIdsSelected' => array_map('strval', $defaultCategoryIds),
                    ];

                    [$product, $rowErrors] = $this->service->validate($payload);
                    if (!empty($rowErrors)) {
                        $errors[] = "Linha {$rowNumber}: " . implode(' ', $rowErrors);
                        continue;
                    }

                    try {
                        // MIGRADO: Criar produto no banco local
                        $product->sku = 0; // SKU será atribuído depois
                        $savedProduct = $this->products->save($product);
                        $productId = (int) $savedProduct->id;
                        
                        $reservedSku = '';
                        $reservationId = isset($item['skuReservationId']) ? (int) $item['skuReservationId'] : 0;
                        if ($skuService && $reservationId > 0) {
                            try {
                                $reservedSku = (string) ($skuService->consumeReservation($reservationId, $skuContext, $skuContextKey, $skuSessionId) ?? '');
                            } catch (\Throwable $e) {
                                error_log('Erro ao consumir reserva de SKU: ' . $e->getMessage());
                            }
                        }
                        
                        // MIGRADO: Atribuir SKU (reservado ou numérico)
                        if ($reservedSku !== '') {
                            $savedProduct->sku = (int) $reservedSku;
                            $this->products->save($savedProduct);
                            $skuNumeric = (int) $reservedSku;
                        } else {
                            $skuNumeric = $nextSkuSeed;
                            $savedProduct->sku = $skuNumeric;
                            $this->products->save($savedProduct);
                        }
                        $sku = (string) $skuNumeric;
                        $nextSkuSeed = $skuNumeric + 1;

                        $this->supplies->create([
                            'product_id' => $productId,
                            'sku' => $sku,
                            'supplier_pessoa_id' => (int) ($product->supplier ?? 0),
                            'source' => $product->source,
                            'cost' => $product->cost,
                            'percentual_consignacao' => $product->percentualConsignacao,
                            'lot_id' => $selectedLotId,
                        ]);
                        if ($consignmentRepo && $consignmentId > 0) {
                            $consignmentRepo->addProductLink($consignmentId, $productId);
                        }

                        $createdItems[] = [
                            'product_id' => $productId,
                            'sku' => $sku,
                            'name' => $product->name,
                            'price' => $product->price,
                        ];
                    } catch (\Throwable $e) {
                        $errors[] = "Linha {$rowNumber}: erro ao salvar no sistema local ({$e->getMessage()}).";
                    }
                }

                if (empty($errors) && count($createdItems) > 0) {
                    $success = count($createdItems) . ' produtos salvos com sucesso no lote.';
                    $closeLot = isset($_POST['close_lot']) && (string) $_POST['close_lot'] === '1';
                    if ($closeLot && $selectedLotId) {
                        $this->lots->close($selectedLotId);
                        $success .= ' Lote encerrado.';
                        $selectedLot = $this->lots->find($selectedLotId);
                    }
                    if ($skuService && $skuContextKey !== '') {
                        $skuService->releaseContext($skuContext, $skuContextKey, $skuSessionId);
                        $skuContextKey = $this->newSkuContextKey();
                    }
                    $submittedRows = [];
                }
            }
        }

        // MIGRADO: Preparar linhas com reservas SKU
        $submittedRows = $this->prepareRowsForView(
            $submittedRows,
            5,
            $skuService,
            $skuContext,
            $skuContextKey,
            $skuUserId,
            $skuSessionId
        );

        View::render('products/batch_intake', [
            'errors' => $errors,
            'warnings' => $warnings,
            'success' => $success,
            'lotSuccess' => $lotSuccess,
            'vendorOptions' => $vendorOptions,
            'selectedVendor' => $selectedVendor,
            'defaultCommission' => $defaultCommission,
            'defaultCost' => $defaultCost,
            'selectedSource' => $selectedSource,
            'consignmentId' => $consignmentId,
            'consignment' => $consignment,
            'consignmentTotals' => $consignmentTotals,
            'submittedRows' => $submittedRows,
            'createdItems' => $createdItems,
            'lotOptions' => $lotOptions,
            'selectedLotId' => $selectedLotId,
            'selectedLot' => $selectedLot,
            'skuContextKey' => $skuContextKey,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Receber lote de produtos',
        ]);
    }

    private function sanitizeItems($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));
            $percentual = isset($row['percentualConsignacao']) ? trim((string) $row['percentualConsignacao']) : '';
            $cost = isset($row['cost']) ? trim((string) $row['cost']) : '';

            if ($name === '' && $price === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'price' => $price,
                'percentualConsignacao' => $percentual !== '' ? $percentual : null,
                'cost' => $cost !== '' ? $cost : null,
                'skuReservationId' => isset($row['skuReservationId']) ? (int) $row['skuReservationId'] : 0,
                'reservedSku' => isset($row['reservedSku']) ? trim((string) $row['reservedSku']) : '',
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function prepareRowsForView(
        array $rows,
        int $defaultCount,
        ?SkuReservationService $skuService,
        string $context,
        string $contextKey,
        ?int $userId,
        ?string $sessionId
    ): array {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = [
                'name' => trim((string) ($row['name'] ?? '')),
                'price' => trim((string) ($row['price'] ?? '')),
                'percentualConsignacao' => $row['percentualConsignacao'] ?? null,
                'cost' => $row['cost'] ?? null,
                'skuReservationId' => isset($row['skuReservationId']) ? (int) $row['skuReservationId'] : 0,
                'reservedSku' => trim((string) ($row['reservedSku'] ?? $row['reserved_sku'] ?? '')),
            ];
        }

        if (empty($normalized)) {
            $defaultCount = max(1, $defaultCount);
            for ($i = 0; $i < $defaultCount; $i++) {
                $normalized[] = [
                    'name' => '',
                    'price' => '',
                    'percentualConsignacao' => null,
                    'cost' => null,
                    'skuReservationId' => 0,
                    'reservedSku' => '',
                ];
            }
        }

        $missingIndices = [];
        $usedIds = [];
        foreach ($normalized as $idx => $row) {
            $id = (int) ($row['skuReservationId'] ?? 0);
            $sku = trim((string) ($row['reservedSku'] ?? ''));
            if ($id > 0) {
                $usedIds[$id] = true;
            }
            if ($id <= 0 || $sku === '') {
                $missingIndices[] = $idx;
            }
        }

        if ($skuService && $contextKey !== '' && !empty($missingIndices)) {
            try {
                $existing = $skuService->listReservations($context, $contextKey, $sessionId);
                $unused = [];
                foreach ($existing as $reservation) {
                    $rid = (int) ($reservation['id'] ?? 0);
                    if ($rid > 0 && !isset($usedIds[$rid])) {
                        $unused[] = $reservation;
                    }
                }
                $needed = count($missingIndices);
                if (count($unused) < $needed) {
                    $requestCount = count($existing) + ($needed - count($unused));
                    // MIGRADO: Reservar SKUs sem repoLegado (usa ProductRepository internamente)
                    $all = $skuService->reserveMany($requestCount, $context, $contextKey, $userId, $sessionId);
                    if (count($all) > count($existing)) {
                        $unused = array_merge($unused, array_slice($all, count($existing)));
                    }
                }

                foreach ($missingIndices as $i => $rowIndex) {
                    if (!isset($unused[$i])) {
                        break;
                    }
                    $normalized[$rowIndex]['skuReservationId'] = (int) ($unused[$i]['id'] ?? 0);
                    $normalized[$rowIndex]['reservedSku'] = (string) ($unused[$i]['sku'] ?? '');
                }
            } catch (\Throwable $e) {
                error_log('Erro ao preparar reservas de SKU: ' . $e->getMessage());
            }
        }

        return $normalized;
    }

    private function handleReserveSku(
        ?SkuReservationService $skuService,
        string $context,
        string $contextKey,
        ?int $userId,
        ?string $sessionId
    ): void {
        $incomingKey = trim((string) ($_POST['sku_context_key'] ?? ''));
        if (!$skuService || $contextKey === '' || $incomingKey === '') {
            $this->respondJson(['ok' => false, 'message' => 'Reserva indisponível.'], 400);
        }
        
        try {
            // MIGRADO: Reservar SKU sem repoLegado
            $reservation = $skuService->reserveNewOne($context, $contextKey, $userId, $sessionId);
        } catch (\Throwable $e) {
            $this->respondJson(['ok' => false, 'message' => 'Erro ao reservar SKU.'], 500);
        }
        if (!$reservation) {
            $this->respondJson(['ok' => false, 'message' => 'Não foi possível reservar SKU.'], 500);
        }
        $this->respondJson([
            'ok' => true,
            'reservation' => [
                'id' => (int) ($reservation['id'] ?? 0),
                'sku' => (string) ($reservation['sku'] ?? ''),
            ],
        ]);
    }

    private function findVendor(array $vendors, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        foreach ($vendors as $vendor) {
            if ((int) ($vendor['id'] ?? 0) === $id) {
                return $vendor;
            }
        }

        return null;
    }

    private function resolveDefaultCategoryIds(array $categories): array
    {
        $ids = [];
        $envDefaults = getenv('DEFAULT_CATEGORY_IDS');
        if ($envDefaults !== false && $envDefaults !== '') {
            $parts = array_filter(array_map('trim', explode(',', $envDefaults)));
            foreach ($parts as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return array_values(array_unique($ids));
        }

        $slugTargets = [
            'uncategorized' => ['uncategorized', 'sem-categoria'],
            'all-products' => ['all-products', 'todos-os-produtos'],
        ];
        $nameTargets = [
            'uncategorized' => ['uncategorized', 'sem categoria', 'sem-categoria'],
            'all-products' => ['all products', 'todos os produtos'],
        ];

        foreach ($categories as $category) {
            $slug = strtolower((string) ($category['slug'] ?? ''));
            $name = strtolower((string) ($category['name'] ?? ''));
            $termId = isset($category['term_id']) ? (int) $category['term_id'] : 0;
            if ($termId <= 0) {
                continue;
            }
            if (in_array($slug, $slugTargets['uncategorized'], true) || in_array($name, $nameTargets['uncategorized'], true)) {
                $ids[] = $termId;
            }
            if (in_array($slug, $slugTargets['all-products'], true) || in_array($name, $nameTargets['all-products'], true)) {
                $ids[] = $termId;
            }
        }

        $ids = array_values(array_unique($ids));
        if (!empty($ids)) {
            return $ids;
        }

        $envDefault = getenv('DEFAULT_CATEGORY_ID');
        if ($envDefault !== false && $envDefault !== '') {
            $envDefault = (int) $envDefault;
            if ($envDefault > 0) {
                return [$envDefault];
            }
        }

        if (!empty($categories[0]['term_id'])) {
            return [(int) $categories[0]['term_id']];
        }

        return [];
    }

    // REMOVIDO: bootstrapCatalogReadRepository(), resolveNextNumericSkuSeed(), assignNumericSku(), 
    // assignReservedSku(), isSkuError() - sistema 100% autônomo

    private function skuNumericStart(): int
    {
        $raw = getenv('SKU_NUMERIC_START');
        if ($raw !== false && $raw !== '') {
            $value = (int) $raw;
            if ($value > 0) {
                return $value;
            }
        }
        return 19100;
    }

    private function newSkuContextKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return uniqid('sku_', true);
        }
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
