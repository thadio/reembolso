<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\OrderReturnRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\PieceLotRepository;
use App\Repositories\ProductWriteOffRepository;
use App\Repositories\SkuReservationRepository;

use App\Repositories\VendorRepository;
use App\Repositories\CatalogProductRepository;
use App\Repositories\CatalogBrandRepository;
use App\Repositories\CatalogCategoryRepository;
use App\Services\OrderService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\ProductWriteOffService;
use App\Services\SkuReservationService;

use App\Support\Auth;
use App\Support\Html;
use App\Support\CatalogLookup;
use PDO;
use PDOException;

class ProductController
{
    private ProductRepository $products;
    private OrderRepository $orders;
    private VendorRepository $vendors;
    private ProductSupplyRepository $supplies;
    private PieceLotRepository $lots;
    private ProductService $service;
    private ProductImageService $imageService;
    private ProductWriteOffRepository $writeoffs;
    private ProductWriteOffService $writeoffService;

    private ?string $connectionError;
    private CatalogProductRepository $catalogProducts;
    private CatalogBrandRepository $catalogBrands;
    private CatalogCategoryRepository $catalogCategories;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->products = new ProductRepository($pdo);
        $this->orders = new OrderRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->lots = new PieceLotRepository($pdo);
        $this->service = new ProductService();
        $this->imageService = new ProductImageService();
        $this->writeoffs = new ProductWriteOffRepository($pdo);
        $this->writeoffService = new ProductWriteOffService();
        $this->catalogProducts = new CatalogProductRepository($pdo);
        $this->catalogBrands = new CatalogBrandRepository($pdo);
        $this->catalogCategories = new CatalogCategoryRepository($pdo);
        $this->connectionError = $connectionError;
    }

    /**
     * Product list using unified products model.
     * BLOCO 1 - Prompt 1.2 ✅ COMPLETO
     */
    public function index(): void
    {
        // Require permission
        Auth::requirePermission('products.view', $this->products->getPdo());

        $errors = [];
        $success = '';

        // Handle POST actions (delete/archive)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                $productId = (int) $_POST['delete_id'];
                if ($productId > 0) {
                    try {
                        Auth::requirePermission('products.delete', $this->products->getPdo());
                        $this->catalogProducts->delete($productId);
                        $_SESSION['flash_success'] = 'Produto arquivado com sucesso.';
                        header('Location: produto-list.php');
                        exit;
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao arquivar produto: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Produto inválido para exclusão.';
                }
            }
        }

        // Read filters and pagination from query string
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
        $perPageOptions = [25, 50, 100, 200];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 50;
        }

        // Build filters array
        $filters = [];
        $searchQuery = '';
        if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
            $searchQuery = trim((string) $_GET['q']);
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = trim((string) $_GET['status']);
        }
        if (isset($_GET['brand_id']) && $_GET['brand_id'] !== '') {
            $filters['brand_id'] = (int) $_GET['brand_id'];
        }
        if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
            $filters['category_id'] = (int) $_GET['category_id'];
        }
        foreach (['sku', 'name', 'brand', 'category', 'price', 'quantity', 'visibility', 'supplier', 'source'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            if (!isset($_GET[$param])) {
                continue;
            }
            $raw = trim((string) $_GET[$param]);
            if ($raw === '') {
                continue;
            }
            $filters[$param] = $raw;
        }
        if (isset($_GET['filter_status']) && trim((string) $_GET['filter_status']) !== '') {
            $rawStatus = trim((string) $_GET['filter_status']);
            if (strpos($rawStatus, ',') !== false) {
                $filters['filter_status'] = array_values(array_filter(array_map('trim', explode(',', $rawStatus))));
            } else {
                $filters['filter_status'] = $rawStatus;
            }
        }

        // Sorting
        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : 'created_at';
        $sortDirRaw = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : 'desc';
        $sortDir = $sortDirRaw === 'asc' ? 'ASC' : 'DESC';

        // Get data from internal catalog
        try {
            $totalProducts = $this->catalogProducts->count($filters);
            $totalPages = $totalProducts > 0 ? (int) ceil($totalProducts / $perPage) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rows = $this->catalogProducts->list($filters, $perPage, $offset, $sortKey, $sortDir);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar lista de produtos: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar produtos.';
            $rows = [];
            $totalProducts = 0;
            $totalPages = 1;
        }

        // Get brands and categories for filter dropdowns
        $brands = [];
        $categories = [];
        try {
            $brands = $this->catalogBrands->list(['status' => 'ativa'], 'name', 'ASC');
            $categories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marcas/categorias: ' . $e->getMessage());
        }

        // Get status options with labels
        $statusOptions = [];
        foreach (CatalogLookup::productStatuses() as $value => $label) {
            $statusOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        // Flash messages from session
        if (isset($_SESSION['flash_success'])) {
            $success = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        View::render('products/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalProducts' => $totalProducts,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'brands' => $brands,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Produtos',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $successSku = '';
        $successWcId = '';
        $editing = false;
        $editingId = 0;
        $currentSku = '';
        $canAdjustInventory = Auth::can('products.inventory');
        $canCreateBrand = Auth::can('brands.create');
        $existingImages = [];
        $imageUploadInfo = $this->imageService->info();
        $replaceImages = false;
        $removeImageIds = [];
        $removeImageSrcs = [];
        $coverImageSelection = '';
        $createdNewProduct = false;
        $warnings = [];
        $notices = [];
        $skuReservationId = 0;
        $skuReservationSku = '';
        $skuContextKey = isset($_POST['sku_context_key'])
            ? trim((string) $_POST['sku_context_key'])
            : (isset($_GET['sku_context_key']) ? trim((string) $_GET['sku_context_key']) : '');
        if ($skuContextKey === '') {
            $skuContextKey = $this->newSkuContextKey();
        }
        $skuContext = 'product_form';
        $skuSessionId = session_id() ?: null;
        $user = Auth::user();
        $skuUserId = $user && isset($user['id']) ? (int) $user['id'] : null;
        $skuService = null;
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $formData = $this->emptyForm();
        $pdo = $this->products->getPdo();
        if ($pdo) {
            $skuService = new SkuReservationService(
                new SkuReservationRepository($pdo),
                $this->products
            );
        }
        $isSkuRetryRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
            && ((string) ($_POST['action'] ?? '') === 'retry_sku_reservation');
        if ($isSkuRetryRequest) {
            $requestEditing = isset($_POST['id']) && trim((string) $_POST['id']) !== '';
            $this->handleSkuRetryRequest(
                $requestEditing,
                $pdo,
                $skuService,
                $skuContext,
                $skuContextKey,
                $skuUserId,
                $skuSessionId
            );
            return;
        }
        $vendorOptions = [];
        try {
            $vendorOptions = $this->vendors->all();
        } catch (\Throwable $e) {
            error_log('Erro ao carregar fornecedores: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar fornecedores.';
        }
        $categoryOptions = [];
        $brandOptions = [];
        $brandTaxonomy = '';
        try {
            $brandRows = $this->catalogBrands->list(['status' => 'ativa'], 'name', 'ASC');
            foreach ($brandRows as $brandRow) {
                $brandId = (int) ($brandRow['id'] ?? 0);
                if ($brandId <= 0) {
                    continue;
                }
                $brandRow['term_id'] = $brandId;
                $brandOptions[] = $brandRow;
            }

            $categoryRows = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
            foreach ($categoryRows as $categoryRow) {
                $categoryId = (int) ($categoryRow['id'] ?? 0);
                if ($categoryId <= 0) {
                    continue;
                }
                $categoryRow['term_id'] = $categoryId;
                $categoryOptions[] = $categoryRow;
            }
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marcas/categorias no formulário de produto: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar marcas/categorias.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            $editingId = (int) $_GET['id'];
            if ($editingId <= 0) {
                $errors[] = 'Produto inválido para edição.';
            } else {
                Auth::requirePermission('products.edit', $pdo);
                try {
                    // MIGRADO: Usar ProductRepository local
                    $product = $this->products->findById($editingId);
                    if (!$product) {
                        $errors[] = 'Produto não encontrado.';
                    } else {
                        $supply = $this->supplies->findByProductId($editingId);
                        $formData = $this->productToFormWithSupply($product, $supply);
                        if (!empty($supply['lot_id'])) {
                            $lot = $this->lots->find((int) $supply['lot_id']);
                            if ($lot) {
                                $formData['lot_id'] = (string) $lot['id'];
                                $formData['lot_name'] = (string) ($lot['name'] ?? '');
                                $formData['lot_status'] = (string) ($lot['status'] ?? '');
                            }
                        }
                        $currentSku = (string) ($product->sku ?? '');
                        $existingImages = $this->normalizeProductImages(['images' => $product->images ?? []]);
                        $editing = true;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao carregar produto: ' . $e->getMessage();
                }
            }
        }

        if (!$editing && empty($formData['categoryIdsArray'])) {
            $defaultCategoryIds = $this->resolveDefaultCategoryIds($categoryOptions);
            if (!empty($defaultCategoryIds)) {
                $formData['categoryIdsArray'] = array_map('strval', $defaultCategoryIds);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = !empty($_POST['id']);
            $editingId = $editing ? (int) $_POST['id'] : 0;
            $payload = $_POST;
            $lotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : 0;
            $replaceImages = !empty($_POST['replace_images']);
            $removeImageIds = $_POST['remove_image_ids'] ?? [];
            if (!is_array($removeImageIds)) {
                $removeImageIds = [];
            }
            $removeImageIds = array_values(array_filter(array_map('intval', $removeImageIds)));
            $removeImageSrcs = $_POST['remove_image_srcs'] ?? [];
            if (!is_array($removeImageSrcs)) {
                $removeImageSrcs = [];
            }
            $removeImageSrcs = array_values(array_filter(array_map('strval', $removeImageSrcs), function ($value) {
                return $value !== '';
            }));
            $coverImageSelection = isset($_POST['cover_image']) ? trim((string) $_POST['cover_image']) : '';

            if (!$canAdjustInventory) {
                $lockedValues = $this->resolveLockedInventoryValues($editingId);
                $payload['status'] = $lockedValues['status'];
                $payload['visibility'] = $lockedValues['visibility'];
            }

            $payload['supplier_pessoa_id'] = $payload['supplier_pessoa_id']
                ?? ($payload['supplier'] ?? null);
            $payload['consignment_percent'] = $payload['consignment_percent']
                ?? ($payload['percentualConsignacao'] ?? ($payload['percentual_consignacao'] ?? null));
            $payload['status'] = $this->normalizeFormStatus(
                (string) ($payload['status'] ?? 'draft'),
                isset($payload['quantity']) ? (int) $payload['quantity'] : 0,
                (string) ($payload['stockStatus'] ?? '')
            );
            $payload['visibility'] = $this->normalizeFormVisibility(
                (string) ($payload['visibility'] ?? ($payload['catalogVisibility'] ?? 'public'))
            );

            [$product, $validationErrors] = $this->service->validate($payload);
            $errors = array_merge($errors, $validationErrors);
            Auth::requirePermission($editing ? 'products.edit' : 'products.create', $pdo);

            if (!$pdo) {
                $errors[] = 'Sem conexão com banco.';
            }

            $vendor = null;
            if ($product->supplier !== null) {
                try {
                    $vendor = $this->vendors->find((int) $product->supplier);
                } catch (\Throwable $e) {
                    error_log('Erro ao localizar fornecedor: ' . $e->getMessage());
                    $errors[] = 'Erro ao localizar fornecedor.';
                }
            }
            if (!$vendor) {
                $errors[] = 'Fornecedor inválido. Cadastre e selecione um fornecedor existente.';
            }

            $selectedLot = null;
            $existingSupply = null;
            if ($editing && $editingId > 0) {
                $existingSupply = $this->supplies->findByProductId($editingId);
                if ($lotId <= 0 && !empty($existingSupply['lot_id'])) {
                    $lotId = (int) $existingSupply['lot_id'];
                }
            }

            $vendorPessoaId = $vendor ? (int) ($vendor->id ?? 0) : 0;
            $lotAutoSelected = false;
            $lotCreated = false;
            $lotAutoReason = '';

            if ($pdo && $vendor && $vendorPessoaId > 0) {
                try {
                    if ($lotId > 0) {
                        $selectedLot = $this->lots->find($lotId);
                        if (!$selectedLot) {
                            $lotAutoReason = 'Lote informado não encontrado.';
                            $lotId = 0;
                        }
                    }

                    if ($selectedLot && (int) ($selectedLot['supplier_pessoa_id'] ?? 0) !== $vendorPessoaId) {
                        $lotAutoReason = 'Lote informado não pertence ao fornecedor.';
                        $selectedLot = null;
                        $lotId = 0;
                    }

                    if ($selectedLot && !$editing && (string) ($selectedLot['status'] ?? '') !== 'aberto') {
                        $lotAutoReason = 'Lote informado está fechado.';
                        $selectedLot = null;
                        $lotId = 0;
                    }

                    if (!$selectedLot) {
                        $selectedLot = $this->lots->latestOpenBySupplier($vendorPessoaId);
                        if (!$selectedLot) {
                            $selectedLot = $this->lots->create($vendorPessoaId);
                            $lotCreated = true;
                        }
                        $lotId = (int) ($selectedLot['id'] ?? 0);
                        $lotAutoSelected = true;
                    }
                } catch (\Throwable $e) {
                    error_log('Erro ao carregar lote do fornecedor: ' . $e->getMessage());
                    $errors[] = 'Erro ao carregar lote do fornecedor.';
                }
            }

            if ($vendor && $selectedLot) {
                if ((int) ($selectedLot['supplier_pessoa_id'] ?? 0) !== $vendorPessoaId) {
                    $errors[] = 'O lote selecionado não pertence a este fornecedor.';
                }
            }

            if ($lotAutoSelected && $selectedLot && !$errors) {
                $lotName = trim((string) ($selectedLot['name'] ?? ''));
                $lotLabel = $lotName !== '' ? $lotName : ('Lote #' . (int) ($selectedLot['id'] ?? 0));
                $message = 'Lote selecionado automaticamente: ' . $lotLabel . '.';
                if ($lotCreated) {
                    $message .= ' Nenhum lote aberto encontrado; novo lote criado.';
                } elseif ($lotAutoReason !== '') {
                    $message .= ' Motivo: ' . $lotAutoReason;
                }
                $notices[] = $message;
            }

            if (!$editing && $vendor && $lotId <= 0) {
                $errors[] = 'Selecione ou abra um lote para este fornecedor.';
            }

            $preparedImages = $this->imageService->prepareUploads($_FILES['product_images'] ?? [], $errors);

            if (empty($errors)) {
                try {
                    if ($product->source === 'doacao') {
                        $product->cost = 0.0;
                        $product->percentualConsignacao = null;
                    } elseif ($product->source !== 'consignacao') {
                        $product->percentualConsignacao = null;
                    }

                    if ($editing) {
                        if ($editingId <= 0) {
                            throw new \RuntimeException('Produto inválido para edição.');
                        }
                        // MIGRADO: Usar ProductRepository local
                        $product->id = $editingId;
                        $this->products->save($product);
                        
                        // Buscar produto atualizado
                        $updatedProduct = $this->products->findById($editingId);
                        $sku = $updatedProduct->sku ?? '';

                        $this->supplies->upsert([
                            'product_id' => $editingId,
                            'sku' => $sku,
                            'supplier_pessoa_id' => (int) ($product->supplier ?? 0),
                            'source' => $product->source,
                            'cost' => $product->cost,
                            'percentual_consignacao' => $product->percentualConsignacao,
                            'lot_id' => $lotId > 0 ? $lotId : null,
                        ]);

                        // Processar imagens
                        $existingImages = $this->normalizeProductImages(['images' => $updatedProduct->images ?? []]);
                        $hasImageRemovals = !empty($removeImageIds) || !empty($removeImageSrcs);
                        $hasCoverSelection = $coverImageSelection !== '';
                        $remainingImages = $this->filterImagesForUpdate($existingImages, $removeImageIds, $removeImageSrcs);
                        $remainingImages = $this->applyCoverSelection($remainingImages, $coverImageSelection);
                        if (!empty($preparedImages)) {
                            $uploadedImages = $this->imageService->storePrepared($preparedImages, $errors);
                            if (!empty($uploadedImages)) {
                                if (empty($errors)) {
                                    $payloadImages = $this->buildImagesPayload($remainingImages, $uploadedImages, $replaceImages);
                                    try {
                                        // MIGRADO: Atualizar imagens no produto local
                                        $updatedProduct->images = $payloadImages;
                                        $this->products->save($updatedProduct);
                                        $existingImages = $this->normalizeProductImages(['images' => $payloadImages]);
                                    } catch (\Throwable $e) {
                                        $errors[] = 'Erro ao salvar imagens: ' . $e->getMessage();
                                    }
                                }
                            }
                        }
                        if (empty($preparedImages) && ($hasImageRemovals || $hasCoverSelection)) {
                            try {
                                $payloadImages = $this->buildImagesPayload($remainingImages, [], false);
                                // MIGRADO: Atualizar imagens no produto local
                                $updatedProduct->images = $payloadImages;
                                $this->products->save($updatedProduct);
                                $existingImages = $this->normalizeProductImages(['images' => $payloadImages]);
                            } catch (\Throwable $e) {
                                $errors[] = 'Erro ao atualizar imagens: ' . $e->getMessage();
                            }
                        }

                        $successSku = $sku;
                        $successWcId = (string) $editingId;
                        $success = "Produto atualizado com sucesso. SKU {$successSku}.";
                        $formData = $this->productToFormWithSupply($updatedProduct, [
                            'supplier_pessoa_id' => (int) ($product->supplier ?? 0),
                            'source' => $product->source,
                            'cost' => $product->cost,
                            'percentual_consignacao' => $product->percentualConsignacao,
                            'lot_id' => $lotId > 0 ? $lotId : null,
                        ]);
                        if ($selectedLot) {
                            $formData['lot_id'] = (string) ($selectedLot['id'] ?? '');
                            $formData['lot_name'] = (string) ($selectedLot['name'] ?? '');
                            $formData['lot_status'] = (string) ($selectedLot['status'] ?? '');
                        }
                        $currentSku = $sku;
                        $editing = true;
                    } else {
                        // MIGRADO: Criar produto no repositório local
                        $this->products->save($product);
                        $productId = $product->id;
                        if ($productId <= 0) {
                            throw new \RuntimeException('Erro ao criar produto no banco local.');
                        }

                        // Processar reserva de SKU
                        $reservedSku = '';
                        $reservationId = isset($_POST['sku_reservation_id']) ? (int) $_POST['sku_reservation_id'] : 0;
                        if ($skuService && $reservationId > 0) {
                            try {
                                $reservedSku = (string) ($skuService->consumeReservation($reservationId, $skuContext, $skuContextKey, $skuSessionId) ?? '');
                            } catch (\Throwable $e) {
                                error_log('Erro ao consumir reserva de SKU: ' . $e->getMessage());
                            }
                        }
                        
                        // Atribuir SKU ao produto
                        if ($reservedSku !== '') {
                            // MIGRADO: Usar SKU reservado
                            $product->sku = (int) $reservedSku;
                            $this->products->save($product);
                            $skuNumeric = (int) $reservedSku;
                            $sku = $reservedSku;
                        } else {
                            // MIGRADO: Gerar novo SKU numérico
                            $maxSku = $this->products->maxNumericSku();
                            $skuNumeric = $maxSku ? $maxSku + 1 : 1;
                            $product->sku = $skuNumeric;
                            $this->products->save($product);
                            $sku = (string) $skuNumeric;
                        }

                        $this->supplies->create([
                            'product_id' => $productId,
                            'sku' => $sku,
                            'supplier_pessoa_id' => (int) ($product->supplier ?? 0),
                            'source' => $product->source,
                            'cost' => $product->cost,
                            'percentual_consignacao' => $product->percentualConsignacao,
                            'lot_id' => $lotId > 0 ? $lotId : null,
                        ]);

                        // Processar imagens
                        if (!empty($preparedImages)) {
                            $uploadedImages = $this->imageService->storePrepared($preparedImages, $errors);
                            if (!empty($uploadedImages) && empty($errors)) {
                                try {
                                    // MIGRADO: Salvar imagens no produto local
                                    $product->images = $uploadedImages;
                                    $this->products->save($product);
                                } catch (\Throwable $e) {
                                    $errors[] = 'Erro ao salvar imagens: ' . $e->getMessage();
                                }
                            }
                        }

                        $successSku = $sku;
                        $successWcId = (string) $productId;
                        $success = "Produto criado com sucesso. SKU {$successSku}.";
                        $createdNewProduct = true;
                        $formData = $this->emptyForm();
                        if (empty($formData['categoryIdsArray'])) {
                            $defaultCategoryIds = $this->resolveDefaultCategoryIds($categoryOptions);
                            if (!empty($defaultCategoryIds)) {
                                $formData['categoryIdsArray'] = array_map('strval', $defaultCategoryIds);
                            }
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Erro de banco: ' . $e->getMessage();
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao salvar produto: ' . $e->getMessage();
                }
            } else {
                $formData = array_merge($this->emptyForm(), $payload);
                if ($lotId > 0) {
                    $lot = $this->lots->find($lotId);
                    if ($lot) {
                        $formData['lot_id'] = (string) $lot['id'];
                        $formData['lot_name'] = (string) ($lot['name'] ?? '');
                        $formData['lot_status'] = (string) ($lot['status'] ?? '');
                    }
                }
                if ($editing && $editingId > 0 && empty($existingImages)) {
                    try {
                        // MIGRADO: Buscar produto local para carregar imagens
                        $product = $this->products->findById($editingId);
                        if ($product) {
                            $existingImages = $this->normalizeProductImages(['images' => $product->images ?? []]);
                        }
                    } catch (\Throwable $e) {
                        // Mantem o form sem imagens se falhar.
                    }
                }
            }
        }

        $ordersForProduct = [];
        $returnSummaryByOrder = [];
        $ordersPage = isset($_GET['orders_page']) ? max(1, (int) $_GET['orders_page']) : 1;
        $ordersPerPage = isset($_GET['orders_per_page']) ? (int) $_GET['orders_per_page'] : 120;
        $ordersPerPageOptions = [50, 100, 120, 200];
        if (!in_array($ordersPerPage, $ordersPerPageOptions, true)) {
            $ordersPerPage = 120;
        }
        $ordersTotal = 0;
        $ordersTotalPages = 1;
        if ($editing && $editingId > 0) {
            try {
                // MIGRADO: Usar OrderRepository local
                $ordersTotal = $this->orders->countOrdersForProduct($editingId);
                $ordersTotalPages = max(1, (int) ceil($ordersTotal / $ordersPerPage));
                if ($ordersPage > $ordersTotalPages) {
                    $ordersPage = $ordersTotalPages;
                }
                $ordersOffset = ($ordersPage - 1) * $ordersPerPage;
                $ordersForProduct = $this->orders->listOrdersForProduct($editingId, $ordersPerPage, $ordersOffset);
                if (!empty($ordersForProduct) && $pdo) {
                    $orderReturns = new OrderReturnRepository($pdo);
                    $returnSummaryByOrder = $orderReturns->returnSummaryByOrderIds(array_column($ordersForProduct, 'order_id'));
                }
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao carregar pedidos deste produto: ' . $e->getMessage();
            }
        }

        $writeoffHistory = [];
        $writeoffDestinationOptions = $this->writeoffService->destinationOptions();
        $writeoffReasonOptions = $this->writeoffService->reasonOptions();
        $writeoffPage = isset($_GET['writeoff_page']) ? max(1, (int) $_GET['writeoff_page']) : 1;
        $writeoffPerPage = isset($_GET['writeoff_per_page']) ? (int) $_GET['writeoff_per_page'] : 50;
        $writeoffPerPageOptions = [25, 50, 100, 200];
        if (!in_array($writeoffPerPage, $writeoffPerPageOptions, true)) {
            $writeoffPerPage = 50;
        }
        $writeoffFilters = [];
        $writeoffSortKey = isset($_GET['writeoff_sort_key']) ? trim((string) $_GET['writeoff_sort_key']) : 'created_at';
        $writeoffSortDir = strtolower(trim((string) ($_GET['writeoff_sort_dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $writeoffAllowedSort = ['created_at', 'destination', 'reason', 'quantity', 'supplier', 'stock_after', 'notes'];
        if (!in_array($writeoffSortKey, $writeoffAllowedSort, true)) {
            $writeoffSortKey = 'created_at';
        }
        $writeoffTotal = 0;
        $writeoffTotalPages = 1;
        if ($editing && $editingId > 0) {
            try {
                if (isset($_GET['writeoff_q']) && trim((string) $_GET['writeoff_q']) !== '') {
                    $writeoffFilters['search'] = trim((string) $_GET['writeoff_q']);
                }
                foreach (['created_at', 'destination', 'reason', 'quantity', 'supplier', 'stock_after', 'notes'] as $columnKey) {
                    $param = 'writeoff_filter_' . $columnKey;
                    if (!isset($_GET[$param])) {
                        continue;
                    }
                    $raw = trim((string) $_GET[$param]);
                    if ($raw === '') {
                        continue;
                    }
                    $writeoffFilters['filter_' . $columnKey] = $raw;
                }

                $writeoffTotal = $this->writeoffs->countForProduct($editingId, $writeoffFilters);
                $writeoffTotalPages = max(1, (int) ceil($writeoffTotal / $writeoffPerPage));
                if ($writeoffPage > $writeoffTotalPages) {
                    $writeoffPage = $writeoffTotalPages;
                }
                $writeoffOffset = ($writeoffPage - 1) * $writeoffPerPage;
                $writeoffHistory = $this->writeoffs->paginateForProduct(
                    $editingId,
                    $writeoffFilters,
                    $writeoffPerPage,
                    $writeoffOffset,
                    $writeoffSortKey,
                    $writeoffSortDir
                );
            } catch (\Throwable $e) {
                $errors[] = 'Erro ao carregar histórico de baixas: ' . $e->getMessage();
            }
        }

        if (!$editing) {
            if ($createdNewProduct) {
                $skuContextKey = $this->newSkuContextKey();
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$createdNewProduct) {
                $skuReservationId = isset($_POST['sku_reservation_id']) ? (int) $_POST['sku_reservation_id'] : 0;
                $skuReservationSku = trim((string) ($_POST['reserved_sku'] ?? ''));
            }
            if ($skuReservationSku === '' && $skuService) {
                try {
                    $reservation = $skuService->ensureReservation($skuContext, $skuContextKey, $skuUserId, $skuSessionId);
                    if ($reservation) {
                        $skuReservationId = (int) ($reservation['id'] ?? 0);
                        $skuReservationSku = (string) ($reservation['sku'] ?? '');
                    }
                } catch (\Throwable $e) {
                    error_log('Erro ao reservar SKU: ' . $e->getMessage());
                }
            }
            if ($skuReservationSku === '' && $pdo) {
                $skuReservationSku = (string) max($this->skuNumericStart(), $this->products->maxNumericSku() + 1);
            }
        }

        View::render('products/form', [
            'formData' => $formData,
            'errors' => $errors,
            'notices' => $notices,
            'warnings' => $warnings,
            'success' => $success,
            'successSku' => $successSku,
            'successWcId' => $successWcId,
            'editing' => $editing,
            'currentSku' => $currentSku,
            'skuReservationId' => $skuReservationId,
            'skuReservationSku' => $skuReservationSku,
            'skuContextKey' => $skuContextKey,
            'canAdjustInventory' => $canAdjustInventory,
            'canCreateBrand' => $canCreateBrand,
            'vendorOptions' => $vendorOptions,
            'categoryOptions' => $categoryOptions,
            'brandOptions' => $brandOptions,
            'brandTaxonomy' => $brandTaxonomy,
            'existingImages' => $existingImages,
            'imageUploadInfo' => $imageUploadInfo,
            'replaceImages' => $replaceImages,
            'removeImageIds' => $removeImageIds,
            'removeImageSrcs' => $removeImageSrcs,
            'coverImageSelection' => $coverImageSelection,
            'ordersForProduct' => $ordersForProduct,
            'orderReturnsByOrder' => $returnSummaryByOrder,
            'ordersPage' => $ordersPage,
            'ordersPerPage' => $ordersPerPage,
            'ordersPerPageOptions' => $ordersPerPageOptions,
            'ordersTotal' => $ordersTotal,
            'ordersTotalPages' => $ordersTotalPages,
            'orderStatusOptions' => OrderService::statusOptions(),
            'paymentStatusOptions' => OrderService::paymentStatusOptions(),
            'fulfillmentStatusOptions' => OrderService::fulfillmentStatusOptions(),
            'writeoffHistory' => $writeoffHistory,
            'writeoffPage' => $writeoffPage,
            'writeoffPerPage' => $writeoffPerPage,
            'writeoffPerPageOptions' => $writeoffPerPageOptions,
            'writeoffTotal' => $writeoffTotal,
            'writeoffTotalPages' => $writeoffTotalPages,
            'writeoffFilters' => $writeoffFilters,
            'writeoffSortKey' => $writeoffSortKey,
            'writeoffSortDir' => $writeoffSortDir,
            'writeoffDestinationOptions' => $writeoffDestinationOptions,
            'writeoffReasonOptions' => $writeoffReasonOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar produto' : 'Novo produto',
        ]);
    }

    public function bulkPublish(): void
    {
        $errors = [];
        $success = '';
        $filtersSource = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $filters = $this->readBulkFilters($filtersSource);
        $selectedIds = $this->normalizeIdList($filtersSource['selected_ids'] ?? []);
        $actionStatus = isset($_POST['action_status']) ? strtolower(trim((string) $_POST['action_status'])) : 'disponivel';
        $applyLimit = isset($_POST['apply_limit']) ? (int) $_POST['apply_limit'] : 200;
        $applyLimit = $applyLimit < 0 ? 0 : $applyLimit;

        $pdo = $this->products->getPdo();
        Auth::requirePermission('products.bulk_publish', $pdo);

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $categories = [];
        $tags = [];
        $previewRows = [];
        $totalMatched = 0;
        $allowedStatuses = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
        if (!in_array($actionStatus, $allowedStatuses, true)) {
            $actionStatus = 'disponivel';
        }

        try {
            $totalMatched = $this->products->countProductsForBulk($filters);
            $previewRows = $this->products->listProductsForBulk($filters, 50, 0);
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar prévia de produtos: ' . $e->getMessage();
            $totalMatched = 0;
            $previewRows = [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'apply') {
            if (!in_array($actionStatus, $allowedStatuses, true)) {
                $errors[] = 'Status de ação inválido.';
            } else {
                try {
                    $processed = 0;
                    $useSelection = !empty($selectedIds);
                    $limit = $useSelection
                        ? count($selectedIds)
                        : ($applyLimit === 0 ? $totalMatched : min($applyLimit, $totalMatched));

                    if ($useSelection) {
                        $targetIds = $selectedIds;
                    } else {
                        $targetIds = $this->collectBulkTargetIds($filters, $limit);
                    }

                    foreach ($targetIds as $id) {
                        $product = $this->products->findById($id);
                        if (!$product) {
                            continue;
                        }
                        $product->status = $actionStatus;
                        $this->products->save($product);
                        $processed++;
                    }

                    $label = CatalogLookup::getProductStatusLabel($actionStatus);
                    $success = "Atualizados {$processed} produtos para status {$label}.";
                    $totalMatched = $this->products->countProductsForBulk($filters);
                    $previewRows = $this->products->listProductsForBulk($filters, 50, 0);
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao atualizar produtos: ' . $e->getMessage();
                }
            }
        }

        View::render('products/bulk_publish', [
            'filters' => $filters,
            'selectedIds' => $selectedIds,
            'categories' => $categories,
            'tags' => $tags,
            'previewRows' => $previewRows,
            'totalMatched' => $totalMatched,
            'errors' => $errors,
            'success' => $success,
            'actionStatus' => $actionStatus,
            'applyLimit' => $applyLimit,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Publicação em lote',
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, int>
     */
    private function collectBulkTargetIds(array $filters, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $ids = [];
        $offset = 0;
        $batchSize = 200;

        while (count($ids) < $limit) {
            $remaining = $limit - count($ids);
            $fetchLimit = min($batchSize, $remaining);
            $rows = $this->products->listForBulk($filters, $fetchLimit, $offset);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $ids[$id] = $id;
                if (count($ids) >= $limit) {
                    break;
                }
            }

            $offset += count($rows);
            if (count($rows) < $fetchLimit) {
                break;
            }
        }

        return array_values($ids);
    }

    private function productToForm($product): array
    {
        // Se $product é um array (formato legado), usar método antigo
        if (is_array($product)) {
            return $this->legacyProductToForm($product, null);
        }
        
        // MIGRADO: Converter Product model para form array
        return [
            'id' => $product->id ?? '',
            'sku' => $product->sku ?? '',
            'skuNumeric' => $product->skuNumeric ?? '',
            'name' => $product->nome ?? $product->name ?? '',
            'slug' => $product->slug ?? '',
            'description' => $product->descricao ?? $product->description ?? '',
            'shortDescription' => $product->shortDescription ?? '',
            'price' => $product->preco_venda ?? $product->price ?? '',
            'cost' => $product->cost ?? '',
            'brand' => $product->brand ?? '',
            'source' => $product->source ?? '',
            'supplier' => $product->supplier ?? '',
            'weight' => $product->weight ?? '0.14',
            'quantity' => $product->quantity ?? '1',
            'percentualConsignacao' => $product->percentualConsignacao ?? '40',
            'status' => (string) ($product->status ?? 'draft'),
            'visibility' => (string) ($product->visibility ?? 'public'),
            'catalogVisibility' => $this->toLegacyFormVisibility((string) ($product->visibility ?? 'public')),
            'stockStatus' => $this->statusToStockStatus(
                (string) ($product->status ?? 'draft'),
                (int) ($product->quantity ?? 0)
            ),
            'categoryIdsArray' => $this->normalizeCategoryIds($product->categoryIds ?? []),
            'trackInventory' => true,
            'inStock' => $this->statusToStockStatus(
                (string) ($product->status ?? 'draft'),
                (int) ($product->quantity ?? 0)
            ) === 'instock',
            'manageVariants' => $product->manageVariants ?? false,
            'dateCreated' => $product->created_at ?? $product->dateCreated ?? '',
            'dateModified' => $product->updated_at ?? $product->dateModified ?? '',
        ];
    }
    
    /**
     * Converte Product model para form com dados de supply
     */
    private function productToFormWithSupply($product, ?array $supply): array
    {
        $form = $this->productToForm($product);
        
        // Adicionar dados do supply
        if ($supply) {
            if (isset($supply['supplier_pessoa_id'])) {
                $form['supplier'] = (string) $supply['supplier_pessoa_id'];
            }
            if (isset($supply['source'])) {
                $form['source'] = (string) $supply['source'];
            }
            if (array_key_exists('cost', $supply)) {
                $form['cost'] = $supply['cost'] !== null ? (string) $supply['cost'] : '';
            }
            if (array_key_exists('percentual_consignacao', $supply)) {
                $form['percentualConsignacao'] = $supply['percentual_consignacao'] !== null
                    ? (string) $supply['percentual_consignacao']
                    : '';
            }
            if (array_key_exists('lot_id', $supply)) {
                $form['lot_id'] = $supply['lot_id'] !== null ? (string) $supply['lot_id'] : '';
            }
        }
        
        return $form;
    }

    private function legacyProductToForm(array $legacyProduct, ?array $supply): array
    {
        $form = $this->emptyForm();

        $form['id'] = (string) ($legacyProduct['id'] ?? '');
        $form['name'] = (string) ($legacyProduct['name'] ?? '');
        $form['slug'] = (string) ($legacyProduct['slug'] ?? '');
        $form['description'] = (string) ($legacyProduct['description'] ?? '');
        $form['shortDescription'] = (string) ($legacyProduct['short_description'] ?? '');
        $form['brand'] = $this->extractBrandId($legacyProduct);
        $form['dateCreated'] = $this->formatLegacyDate($legacyProduct['date_created'] ?? '');
        $form['dateModified'] = $this->formatLegacyDate($legacyProduct['date_modified'] ?? '');

        $price = $legacyProduct['regular_price'] ?? $legacyProduct['price'] ?? '';
        $form['price'] = $price !== null ? (string) $price : '';

        $weight = $legacyProduct['weight'] ?? '';
        if ($weight !== '') {
            $form['weight'] = (string) $weight;
        }

        $statusRaw = (string) ($legacyProduct['status'] ?? $form['status']);
        $form['catalogVisibility'] = (string) ($legacyProduct['catalog_visibility'] ?? $form['catalogVisibility']);
        $form['trackInventory'] = (bool) ($legacyProduct['manage_stock'] ?? $form['trackInventory']);
        $form['stockStatus'] = (string) ($legacyProduct['availability_status'] ?? $form['stockStatus']);
        $form['quantity'] = array_key_exists('quantity', $legacyProduct) && $legacyProduct['quantity'] !== null
            ? (string) $legacyProduct['quantity']
            : $form['quantity'];
        $form['status'] = $this->normalizeFormStatus(
            $statusRaw,
            (int) $form['quantity'],
            (string) $form['stockStatus']
        );
        $form['visibility'] = $this->normalizeFormVisibility((string) $form['catalogVisibility']);

        $categoryIds = [];
        if (!empty($legacyProduct['categories']) && is_array($legacyProduct['categories'])) {
            foreach ($legacyProduct['categories'] as $category) {
                $categoryId = isset($category['id']) ? (string) $category['id'] : '';
                if ($categoryId !== '') {
                    $categoryIds[] = $categoryId;
                }
            }
        }
        $form['categoryIdsArray'] = $this->normalizeCategoryIds($categoryIds);

        if ($supply) {
            if (isset($supply['supplier_pessoa_id'])) {
                $form['supplier'] = (string) $supply['supplier_pessoa_id'];
            }
            if (isset($supply['source'])) {
                $form['source'] = (string) $supply['source'];
            }
            if (array_key_exists('cost', $supply)) {
                $form['cost'] = $supply['cost'] !== null ? (string) $supply['cost'] : '';
            }
            if (array_key_exists('percentual_consignacao', $supply)) {
                $form['percentualConsignacao'] = $supply['percentual_consignacao'] !== null
                    ? (string) $supply['percentual_consignacao']
                    : '';
            }
            if (array_key_exists('lot_id', $supply)) {
                $form['lot_id'] = $supply['lot_id'] !== null ? (string) $supply['lot_id'] : '';
            }
        }

        return $form;
    }

    /**
     * @return array<int, array{id:int, src:string, name:string}>
     */
    private function normalizeProductImages(array $productData): array
    {
        $images = [];
        if (!empty($productData['images']) && is_array($productData['images'])) {
            foreach ($productData['images'] as $image) {
                if (!is_array($image)) {
                    continue;
                }
                $id = isset($image['id']) ? (int) $image['id'] : 0;
                $src = isset($image['src']) ? (string) $image['src'] : '';
                $name = (string) ($image['name'] ?? $image['alt'] ?? '');
                if ($id <= 0 && $src === '') {
                    continue;
                }
                $images[] = [
                    'id' => $id,
                    'src' => $src,
                    'name' => $name,
                ];
            }
        }

        return $images;
    }

    /**
     * @param array<int, array{id:int, src:string, name:string}> $existingImages
     * @param array<int, int> $removeImageIds
     * @param array<int, string> $removeImageSrcs
     * @return array<int, array{id:int, src:string, name:string}>
     */
    private function filterImagesForUpdate(array $existingImages, array $removeImageIds, array $removeImageSrcs): array
    {
        if (empty($existingImages)) {
            return [];
        }

        $remaining = [];
        foreach ($existingImages as $image) {
            $imageId = (int) ($image['id'] ?? 0);
            $imageSrc = (string) ($image['src'] ?? '');
            if ($imageId > 0 && in_array($imageId, $removeImageIds, true)) {
                continue;
            }
            if ($imageId <= 0 && $imageSrc !== '' && in_array($imageSrc, $removeImageSrcs, true)) {
                continue;
            }
            $remaining[] = $image;
        }

        return $remaining;
    }

    /**
     * @param array<int, array{id:int, src:string, name:string}> $images
     * @return array<int, array{id:int, src:string, name:string}>
     */
    private function applyCoverSelection(array $images, string $coverToken): array
    {
        $coverToken = trim($coverToken);
        if ($coverToken === '' || empty($images)) {
            return $images;
        }

        $coverId = 0;
        $coverSrc = '';
        if (str_starts_with($coverToken, 'id:')) {
            $coverId = (int) substr($coverToken, 3);
        } elseif (str_starts_with($coverToken, 'src:')) {
            $coverSrc = substr($coverToken, 4);
        } else {
            if (ctype_digit($coverToken)) {
                $coverId = (int) $coverToken;
            } else {
                $coverSrc = $coverToken;
            }
        }

        $coverIndex = null;
        foreach ($images as $index => $image) {
            $imageId = (int) ($image['id'] ?? 0);
            $imageSrc = (string) ($image['src'] ?? '');
            if ($coverId > 0 && $imageId === $coverId) {
                $coverIndex = $index;
                break;
            }
            if ($coverId <= 0 && $coverSrc !== '' && $imageSrc === $coverSrc) {
                $coverIndex = $index;
                break;
            }
        }

        if ($coverIndex === null) {
            return $images;
        }

        $coverImage = $images[$coverIndex];
        unset($images[$coverIndex]);
        $images = array_values($images);
        array_unshift($images, $coverImage);
        return $images;
    }

    /**
     * @param array<int, array{id:int, src:string, name:string}> $existingImages
     * @param array<int, array<string, string>> $uploadedImages
     * @return array<int, array<string, mixed>>
     */
    private function buildImagesPayload(array $existingImages, array $uploadedImages, bool $replaceExisting): array
    {
        if ($replaceExisting) {
            return $uploadedImages;
        }

        $payload = [];
        foreach ($existingImages as $image) {
            if (!empty($image['id'])) {
                $payload[] = ['id' => (int) $image['id']];
            } elseif (!empty($image['src'])) {
                $payload[] = ['src' => (string) $image['src']];
            }
        }

        return array_merge($payload, $uploadedImages);
    }

    private function resolveLockedInventoryValues(int $editingId): array
    {
        $defaults = $this->emptyForm();
        $values = [
            'status' => $defaults['status'],
            'visibility' => $defaults['visibility'],
        ];

        if ($editingId <= 0) {
            return $values;
        }

        try {
            // MIGRADO: Buscar produto local
            $product = $this->products->findById($editingId);
            if ($product) {
                $values['status'] = (string) ($product->status ?? 'draft');
                $values['visibility'] = (string) ($product->visibility ?? 'public');
            }
        } catch (\Throwable $e) {
            // Mantém defaults se falhar.
        }

        return $values;
    }

    private function readBulkFilters(array $source): array
    {
        $status = isset($source['status']) ? trim((string) $source['status']) : '';
        $allowedStatuses = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        $availability = isset($source['availability']) ? trim((string) $source['availability']) : '';
        $dateFrom = isset($source['date_from']) ? trim((string) $source['date_from']) : '';
        $dateTo = isset($source['date_to']) ? trim((string) $source['date_to']) : '';

        $statuses = ['draft', 'disponivel', 'reservado', 'esgotado'];
        if (in_array($status, $allowedStatuses, true)) {
            $statuses = [$status];
        }

        if (!in_array($availability, ['available', 'unavailable'], true)) {
            $availability = '';
        }

        $categoryIds = $this->normalizeIdList($source['category_ids'] ?? []);
        $tagIds = $this->normalizeIdList($source['tag_ids'] ?? []);

        return [
            'status' => $status,
            'statuses' => $statuses,
            'availability' => $availability,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_ids' => $categoryIds,
            'tag_ids' => $tagIds,
        ];
    }

    private function readListFilters(array $source): array
    {
        $map = [
            'global' => 'q',
            'sku' => 'filter_sku',
            'name' => 'filter_name',
            'price' => 'filter_price',
            'quantity' => 'filter_quantity',
            'status' => 'filter_status',
            'supplier' => 'filter_supplier',
            'source' => 'filter_source',
        ];
        $filters = [];
        foreach ($map as $key => $param) {
            $raw = isset($source[$param]) ? trim((string) $source[$param]) : '';
            // Support multiple comma-separated values for server-side multi-select filters
            if ($raw !== '' && strpos($raw, ',') !== false) {
                $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), function ($v) { return $v !== ''; }));
                $filters[$key] = $parts;
            } else {
                $filters[$key] = $raw;
            }
        }
        return $filters;
    }

    /**
     * Normaliza opções remotas (fornecedor/origem) para uso nas multiselects.
     * Usa mb_strtolower para lidar com acentos em maiúsculas quando disponível,
     * caindo para strtolower como reserva.
     */
    private function normalizeRemoteOption(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function buildProductListFilters(array $filters, array $statuses, ?array $idsConstraint): array
    {
        // Start with provided statuses (may be overridden by filters)
        $productFilters = ['statuses' => $statuses];

        // support multi-value status filter (server-side)
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $productFilters['statuses'] = array_values($filters['status']);
            } else {
                // single value
                $productFilters['statuses'] = [ (string) $filters['status'] ];
            }
        }

        if (!empty($filters['sku'])) {
            $productFilters['sku'] = $filters['sku'];
        }
        if (!empty($filters['name'])) {
            $productFilters['name'] = $filters['name'];
        }
        if (!empty($filters['price'])) {
            $productFilters['price'] = $filters['price'];
        }
        if (!empty($filters['quantity'])) {
            $productFilters['quantity'] = $filters['quantity'];
        }
        if (!empty($filters['status']) && !is_array($filters['status'])) {
            $productFilters['status_like'] = $filters['status'];
        }
        if ($idsConstraint !== null) {
            $productFilters['ids'] = $idsConstraint;
        }

        // allow repository to sort by fields when provided
        if (!empty($filters['order_by'])) {
            $productFilters['order_by'] = $filters['order_by'];
            $productFilters['order_dir'] = !empty($filters['order_dir']) && strtolower($filters['order_dir']) === 'asc' ? 'asc' : 'desc';
        }

        return $productFilters;
    }

    private function buildProductListIdConstraint(array $filters, array $statuses): ?array
    {
        // MIGRADO: Usar ProductRepository local
        $ids = null;
        if (!empty($filters['global'])) {
            // Buscar IDs por search no repositório local
            $productIds = $this->products->searchProductIds([
                'statuses' => $statuses,
                'search' => $filters['global'],
            ]);
            $supplyIds = $this->supplies->listProductIdsByFilters([
                'global' => $filters['global'],
            ]);
            $ids = array_values(array_unique(array_merge($productIds, $supplyIds)));
        }

        // Support supplier/source filters as either single values or arrays (multi-select)
        $supplyIds = null;
        if (!empty($filters['supplier'])) {
            $supplierVals = is_array($filters['supplier']) ? $filters['supplier'] : [ $filters['supplier'] ];
            $supplierUnion = [];
            foreach ($supplierVals as $sv) {
                $rows = $this->supplies->listProductIdsByFilters(['supplier' => $sv]);
                $supplierUnion = array_values(array_unique(array_merge($supplierUnion, $rows)));
            }
            $supplyIds = $supplierUnion;
        }

        if (!empty($filters['source'])) {
            $sourceVals = is_array($filters['source']) ? $filters['source'] : [ $filters['source'] ];
            $sourceUnion = [];
            foreach ($sourceVals as $sv) {
                $rows = $this->supplies->listProductIdsByFilters(['source' => $sv]);
                $sourceUnion = array_values(array_unique(array_merge($sourceUnion, $rows)));
            }
            if ($supplyIds === null) {
                $supplyIds = $sourceUnion;
            } else {
                // intersection: must match both supplier and source
                $supplyIds = array_values(array_intersect($supplyIds, $sourceUnion));
            }
        }

        if ($supplyIds !== null) {
            if ($ids === null) {
                $ids = $supplyIds;
            } else {
                $ids = array_values(array_intersect($ids, $supplyIds));
            }
        }

        return $ids;
    }

    private function normalizeIdList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $ids = array_values(array_filter(array_map('intval', $value)));
        return array_values(array_unique($ids));
    }

    private function normalizeFormStatus(string $value, int $quantity, string $stockStatus = ''): string
    {
        $status = strtolower(trim($value));
        $stock = strtolower(trim($stockStatus));

        $map = [
            'draft' => 'draft',
            'pending' => 'draft',
            'publish' => 'disponivel',
            'active' => 'disponivel',
            'disponivel' => 'disponivel',
            'reservado' => 'reservado',
            'esgotado' => 'esgotado',
            'sold' => 'esgotado',
            'vendido' => 'esgotado',
            'private' => 'archived',
            'archived' => 'archived',
            'baixado' => 'baixado',
        ];

        $normalized = $map[$status] ?? 'draft';
        if ($normalized === 'disponivel' && $stock === 'outofstock') {
            $normalized = 'esgotado';
        }
        if ($normalized === 'disponivel' && $quantity <= 0) {
            $normalized = 'esgotado';
        }
        if ($normalized === 'esgotado' && $quantity > 0 && $stock !== 'outofstock') {
            $normalized = 'disponivel';
        }

        return $normalized;
    }

    private function normalizeFormVisibility(string $value): string
    {
        $visibility = strtolower(trim($value));
        if ($visibility === 'visible') {
            return 'public';
        }

        return in_array($visibility, ['public', 'catalog', 'search', 'hidden'], true) ? $visibility : 'public';
    }

    private function toLegacyFormStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return match ($value) {
            'disponivel', 'reservado', 'esgotado' => 'publish',
            'archived', 'baixado' => 'private',
            default => 'draft',
        };
    }

    private function toLegacyFormVisibility(string $visibility): string
    {
        $value = strtolower(trim($visibility));
        if ($value === 'public') {
            return 'visible';
        }

        return in_array($value, ['visible', 'catalog', 'search', 'hidden'], true) ? $value : 'visible';
    }

    private function statusToStockStatus(string $status, int $quantity): string
    {
        $value = strtolower(trim($status));
        if ($value !== 'disponivel' || $quantity <= 0) {
            return 'outofstock';
        }
        return 'instock';
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'sku' => '',
            'skuNumeric' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'shortDescription' => '',
            'price' => '',
            'cost' => '',
            'brand' => '',
            'source' => '',
            'supplier' => '',
            'weight' => '0.14',
            'quantity' => '1',
            'percentualConsignacao' => '40',
            'status' => 'draft',
            'visibility' => 'public',
            'catalogVisibility' => 'visible',
            'stockStatus' => 'instock',
            'categoryIdsArray' => [],
            'trackInventory' => true,
            'inStock' => true,
            'manageVariants' => false,
            'dateCreated' => '',
            'dateModified' => '',
            'lot_id' => '',
            'lot_name' => '',
            'lot_status' => '',
        ];
    }

    private function formatLegacyDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }
        return date('d/m/Y H:i', $timestamp);
    }

    /**
     * Normaliza IDs de categoria em array simples de strings.
     */
    private function normalizeCategoryIds(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }
        return array_values(array_unique($normalized));
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

    private function resolveBrandTaxonomy(): string
    {
        $env = trim((string) (getenv('BRAND_TAXONOMY') ?: ''));
        if ($env !== '') {
            return $env;
        }

        return 'product_brand';
    }

    private function extractBrandId(array $productData): string
    {
        $field = trim((string) (getenv('BRAND_API_FIELD') ?: 'brands'));
        if ($field === '') {
            $field = 'brands';
        }
        $brands = $productData[$field] ?? ($productData['brands'] ?? null);
        if (!is_array($brands)) {
            return '';
        }
        foreach ($brands as $brand) {
            if (is_array($brand) && isset($brand['id'])) {
                $brandId = (int) $brand['id'];
                if ($brandId > 0) {
                    return (string) $brandId;
                }
            }
        }
        return '';
    }

    private function resolveSkuForSupply(int $productId, array $productData): string
    {
        $sku = trim((string) ($productData['sku'] ?? ''));
        if ($sku !== '') {
            return $sku;
        }

        $nextSkuSeed = $this->resolveNextNumericSkuSeed();
        $skuNumeric = $this->assignNumericSku($productId, $nextSkuSeed);
        return (string) $skuNumeric;
    }

    private function resolveNextNumericSkuSeed(): int
    {
        // MIGRADO: Usar ProductRepository local
        $minSku = $this->skuNumericStart();
        $maxSku = $this->products->maxNumericSku();
        if ($maxSku > 0) {
            return max($minSku, $maxSku + 1);
        }

        return $minSku;
    }

    private function assignNumericSku(int $productId, int $startSku): int
    {
        // MIGRADO: Usar ProductRepository local
        $candidate = max(1, $startSku);
        for ($attempts = 0; $attempts < 50; $attempts++) {
            $sku = (string) $candidate;
            if ($this->products->skuExists($sku)) {
                $candidate++;
                continue;
            }
            try {
                $product = $this->products->findById($productId);
                if ($product) {
                    $product->sku = $candidate;
                    $this->products->save($product);
                    return $candidate;
                }
                throw new \RuntimeException('Produto não encontrado para atribuir SKU.');
            } catch (\Throwable $e) {
                if (!$this->isSkuError($e)) {
                    throw $e;
                }
                $candidate++;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um SKU numérico disponível.');
    }

    /**
     * @return array{sku: int, usedReserved: bool}
     */
    private function assignReservedSku(int $productId, string $reservedSku): array
    {
        // MIGRADO: Usar ProductRepository local
        $reservedSku = trim($reservedSku);
        $candidate = (int) $reservedSku;
        if ($candidate <= 0) {
            $nextSkuSeed = $this->resolveNextNumericSkuSeed();
            return [
                'sku' => $this->assignNumericSku($productId, $nextSkuSeed),
                'usedReserved' => false,
            ];
        }
        $candidate = max($candidate, $this->skuNumericStart());
        $sku = (string) $candidate;
        if ($this->products->skuExists($sku)) {
            return [
                'sku' => $this->assignNumericSku($productId, $candidate + 1),
                'usedReserved' => false,
            ];
        }
        try {
            $appliedSku = 0;
            $product = $this->products->findById($productId);
            if ($product) {
                $product->sku = $candidate;
                $this->products->save($product);
                $appliedSku = (int) ($product->sku ?? 0);
                if ($appliedSku > 0 && $appliedSku !== $candidate) {
                    return [
                        'sku' => $appliedSku,
                        'usedReserved' => false,
                    ];
                }
            }
            return [
                'sku' => $appliedSku > 0 ? $appliedSku : $candidate,
                'usedReserved' => ($appliedSku > 0 ? $appliedSku : $candidate) === $candidate,
            ];
        } catch (\Throwable $e) {
            if (!$this->isSkuError($e)) {
                throw $e;
            }
            return [
                'sku' => $this->assignNumericSku($productId, $candidate + 1),
                'usedReserved' => false,
            ];
        }
    }

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

    private function isSkuError(\Throwable $e): bool
    {
        return stripos($e->getMessage(), 'sku') !== false;
    }

    private function newSkuContextKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return uniqid('sku_', true);
        }
    }

    private function handleSkuRetryRequest(
        bool $editing,
        ?PDO $pdo,
        ?SkuReservationService $skuService,
        string $skuContext,
        string $skuContextKey,
        ?int $skuUserId,
        ?string $skuSessionId
    ): void {
        $response = [
            'ok' => false,
            'retryable' => true,
            'sku' => '',
            'reservation_id' => 0,
            'context_key' => $skuContextKey,
            'message' => 'Não foi possível gerar SKU no momento.',
        ];

        if ($editing) {
            $response['retryable'] = false;
            $response['message'] = 'A geração automática de SKU está disponível apenas para novo produto.';
            $this->respondJson($response);
            return;
        }

        if (!$pdo || !$skuService) {
            $response['retryable'] = false;
            $response['message'] = 'Sem conexão com banco para reservar SKU.';
            $this->respondJson($response);
            return;
        }

        $incomingContextKey = trim((string) ($_POST['sku_context_key'] ?? $skuContextKey));
        if ($incomingContextKey === '') {
            $incomingContextKey = $this->newSkuContextKey();
        }
        $response['context_key'] = $incomingContextKey;

        try {
            $skuService->releaseContext($skuContext, $incomingContextKey, $skuSessionId);
            $reservation = $skuService->reserveNewOne($skuContext, $incomingContextKey, $skuUserId, $skuSessionId);
            if (!$reservation) {
                $reservation = $skuService->ensureReservation($skuContext, $incomingContextKey, $skuUserId, $skuSessionId);
            }

            if ($reservation && !empty($reservation['sku'])) {
                $response['ok'] = true;
                $response['retryable'] = false;
                $response['sku'] = (string) $reservation['sku'];
                $response['reservation_id'] = (int) ($reservation['id'] ?? 0);
                $response['message'] = 'SKU reservado com sucesso.';
                $this->respondJson($response);
                return;
            }

            $response['message'] = 'SKU indisponível agora. Tentando novamente...';
            $this->respondJson($response);
        } catch (\Throwable $e) {
            error_log('Erro ao tentar reservar SKU via retry: ' . $e->getMessage());
            $response['message'] = 'Falha ao reservar SKU. Tentando novamente...';
            $this->respondJson($response);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respondJson(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function mergeSupplyData(array $productRows): array
    {
        if (empty($productRows)) {
            return [];
        }

        $ids = [];
        foreach ($productRows as $row) {
            if (isset($row['ID'])) {
                $ids[] = (int) $row['ID'];
            }
        }
        $supplyMap = $this->supplies->listByProductIds($ids);

        $rows = [];
        foreach ($productRows as $row) {
            $id = (int) ($row['ID'] ?? 0);
            $supply = $supplyMap[$id] ?? null;
            $rows[] = [
                'id' => $id,
                'sku' => (string) ($row['sku'] ?? ''),
                'name' => (string) ($row['post_title'] ?? ''),
                'price' => $row['min_price'] ?? $row['max_price'] ?? null,
                'quantity' => $row['quantity'] ?? null,
                'status' => (string) ($row['post_status'] ?? ''),
                'availability_status' => (string) ($row['availability_status'] ?? ''),
                'image_src' => (string) ($row['image_src'] ?? ''),
                'supplier' => $supply['supplier_name'] ?? '',
                'source' => $supply['source'] ?? '',
            ];
        }

        return $rows;
    }

    // ========================================================================
    // NEW RESTFUL METHODS - BLOCO 1 (Prompts 1.3, 1.4, 1.5, 1.6)
    // ========================================================================

    /**
     * Show form for creating a new product.
     * BLOCO 1 - Prompt 1.3
     */
    public function create(): void
    {
        Auth::requirePermission('products.create', $this->products->getPdo());

        $errors = [];
        $formData = [];

        // Flash messages
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        // Old input (após erro de validação)
        if (isset($_SESSION['old_input'])) {
            $formData = $_SESSION['old_input'];
            unset($_SESSION['old_input']);
        }

        // Get brands and categories for dropdowns
        $brands = [];
        $categories = [];
        try {
            $brands = $this->catalogBrands->list(['status' => 'ativa'], 'name', 'ASC');
            $categories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marcas/categorias: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar marcas/categorias.';
        }

        $statusOptions = CatalogLookup::productStatuses();
        $visibilityOptions = CatalogLookup::productVisibility();

        View::render('products/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'brands' => $brands,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'visibilityOptions' => $visibilityOptions,
            'editing' => false,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Novo Produto',
        ]);
    }

    /**
     * Store a newly created product.
     * BLOCO 1 - Prompt 1.3 ✅
     */
    public function store(): void
    {
        Auth::requirePermission('products.create', $this->products->getPdo());

        [$product, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header('Location: produto-cadastro.php');
            exit;
        }

        try {
            $productId = $this->catalogProducts->save($this->service->toArray($product));
            $_SESSION['flash_success'] = 'Produto criado com sucesso.';
            header('Location: produto-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao salvar produto: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar produto: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header('Location: produto-cadastro.php');
            exit;
        }
    }

    /**
     * Show product details.
     * BLOCO 1 - Prompt 1.4 ✅
     */
    public function show(int $id): void
    {
        Auth::requirePermission('products.view', $this->products->getPdo());

        try {
            $product = $this->catalogProducts->find($id);
            if (!$product) {
                header('HTTP/1.1 404 Not Found');
                echo '<h1>404 - Produto não encontrado</h1>';
                exit;
            }

            View::render('products/show', [
                'product' => $product,
                'statusLabel' => CatalogLookup::getProductStatusLabel($product['status'] ?? ''),
                'visibilityLabel' => CatalogLookup::getVisibilityLabel($product['visibility'] ?? ''),
                'esc' => [Html::class, 'esc'],
            ], [
                'title' => 'Detalhes do Produto',
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar produto: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>Erro ao carregar produto</h1>';
            exit;
        }
    }

    /**
     * Show form for editing a product.
     * BLOCO 1 - Prompt 1.4 ✅
     */
    public function edit(int $id): void
    {
        Auth::requirePermission('products.edit', $this->products->getPdo());

        $errors = [];
        $formData = [];

        // Flash messages
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        // Old input (tem prioridade sobre dados do banco)
        if (isset($_SESSION['old_input'])) {
            $formData = $_SESSION['old_input'];
            unset($_SESSION['old_input']);
        } else {
            // Carregar do banco
            try {
                $product = $this->catalogProducts->find($id);
                if (!$product) {
                    header('HTTP/1.1 404 Not Found');
                    echo '<h1>404 - Produto não encontrado</h1>';
                    exit;
                }
                $formData = $product;
            } catch (\Throwable $e) {
                error_log('Erro ao carregar produto: ' . $e->getMessage());
                $errors[] = 'Erro ao carregar produto.';
            }
        }

        // Get brands and categories for dropdowns
        $brands = [];
        $categories = [];
        try {
            $brands = $this->catalogBrands->list(['status' => 'ativa'], 'name', 'ASC');
            $categories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marcas/categorias: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar marcas/categorias.';
        }

        $statusOptions = CatalogLookup::productStatuses();
        $visibilityOptions = CatalogLookup::productVisibility();

        View::render('products/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'brands' => $brands,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'visibilityOptions' => $visibilityOptions,
            'editing' => true,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Editar Produto',
        ]);
    }

    /**
     * Update an existing product.
     * BLOCO 1 - Prompt 1.5 ✅
     */
    public function update(int $id): void
    {
        Auth::requirePermission('products.edit', $this->products->getPdo());

        $_POST['id'] = $id; // Garantir que o ID está no payload
        [$product, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header("Location: produto-cadastro.php?id={$id}");
            exit;
        }

        try {
            $this->catalogProducts->save($this->service->toArray($product));
            $_SESSION['flash_success'] = 'Produto atualizado com sucesso.';
            header('Location: produto-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao atualizar produto: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar produto: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header("Location: produto-cadastro.php?id={$id}");
            exit;
        }
    }

    /**
     * Soft delete (archive) a product.
     * BLOCO 1 - Prompt 1.6 ✅
     */
    public function destroy(int $id): void
    {
        Auth::requirePermission('products.delete', $this->products->getPdo());

        try {
            $this->catalogProducts->delete($id);
            $_SESSION['flash_success'] = 'Produto arquivado com sucesso.';
        } catch (\Throwable $e) {
            error_log('Erro ao arquivar produto: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao arquivar produto: ' . $e->getMessage();
        }

        header('Location: produto-list.php');
        exit;
    }
}
