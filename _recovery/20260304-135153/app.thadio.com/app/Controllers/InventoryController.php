<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\UserRepository;
use App\Services\ProductImageService;

use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use PDO;

class InventoryController
{
    private InventoryRepository $inventories;
    private ProductRepository $products;
    private ProductSupplyRepository $supplies;
    private UserRepository $users;
    private ProductImageService $imageService;

    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->inventories = new InventoryRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->users = new UserRepository($pdo);
        $this->imageService = new ProductImageService();
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            Auth::requireLogin($this->inventories->getPdo());
            $currentUser = Auth::user();
        }

        $openInventory = $this->inventories->getOpen();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleAction($openInventory, $currentUser, $errors);
            return;
        }

        $closedInventory = null;
        $pendingRows = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['open_inventory'])) {
                try {
                    Auth::requirePermission('inventory.open', $this->inventories->getPdo());
                    if ($openInventory) {
                        $errors[] = 'Ja existe um batimento em aberto.';
                    } else {
                        $defaultReason = trim((string) ($_POST['default_reason'] ?? ''));
                        $blindCount = !empty($_POST['blind_count']);
                        $inventoryId = $this->inventories->create([
                            'status' => 'aberto',
                            'blind_count' => $blindCount,
                            'default_reason' => $defaultReason !== '' ? $defaultReason : null,
                            'opened_by' => (int) ($currentUser['id'] ?? 0),
                        ]);
                        if ($defaultReason === '') {
                            $defaultReason = $this->buildDefaultReason($inventoryId);
                            $this->inventories->updateDefaultReason($inventoryId, $defaultReason);
                        }
                        $openInventory = $this->inventories->find($inventoryId);
                        $success = 'Batimento aberto com sucesso.';
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao abrir batimento: ' . $e->getMessage();
                }
            } elseif (isset($_POST['close_inventory'])) {
                try {
                    Auth::requirePermission('inventory.close', $this->inventories->getPdo());
                    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
                    if (!$openInventory || $openInventory['id'] != $inventoryId) {
                        $errors[] = 'Batimento em aberto não encontrado.';
                    } else {
                        $this->liftExecutionLimit();
                        $this->inventories->close($inventoryId, (int) ($currentUser['id'] ?? 0));
                        $pending = $this->buildPendingItems($inventoryId);
                        $this->inventories->savePendingItems($inventoryId, $pending);
                        $openInventory = null;
                        $closedInventory = $this->inventories->find($inventoryId);
                        $pendingRows = $this->inventories->listPending($inventoryId);
                        $success = 'Batimento fechado. Itens pendentes listados abaixo.';
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao fechar batimento: ' . $e->getMessage();
                }
            } elseif (isset($_POST['bulk_zero'])) {
                try {
                    Auth::requirePermission('inventory.close', $this->inventories->getPdo());
                    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
                    $pendingIds = $_POST['pending_ids'] ?? [];
                    if (!is_array($pendingIds)) {
                        $pendingIds = [];
                    }
                    $reason = trim((string) ($_POST['bulk_reason'] ?? ''));
                    $pendingRows = $this->inventories->listPending($inventoryId);
                    if ($pendingIds) {
                        $pendingRows = array_values(array_filter($pendingRows, function ($row) use ($pendingIds) {
                            return in_array((string) ($row['id'] ?? ''), $pendingIds, true);
                        }));
                    }
                    if (!$pendingRows) {
                        $errors[] = 'Nenhuma pendencia selecionada.';
                    } else {
                        $this->liftExecutionLimit();
                        $this->bulkZeroPending($inventoryId, $pendingRows, $reason, (int) ($currentUser['id'] ?? 0));
                        $this->inventories->markPendingResolved(
                            $inventoryId,
                            array_map(function ($row) {
                                return (int) ($row['id'] ?? 0);
                            }, $pendingRows),
                            'zerado',
                            (int) ($currentUser['id'] ?? 0)
                        );
                        $closedInventory = $this->inventories->find($inventoryId);
                        $pendingRows = $this->inventories->listPending($inventoryId);
                        $success = 'Pendencias ajustadas em massa.';
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao ajustar pendencias: ' . $e->getMessage();
                }
            }
        }

        $openInventory = $this->inventories->getOpen();

        if (!$openInventory && isset($_GET['inventory'])) {
            $candidate = $this->inventories->find((int) $_GET['inventory']);
            if ($candidate && ($candidate['status'] ?? '') === 'fechado') {
                $closedInventory = $candidate;
                $pendingRows = $this->inventories->listPending((int) $candidate['id']);
            }
        }

        $recentScans = $openInventory ? $this->inventories->listRecentScans((int) $openInventory['id'], 8) : [];
        $summary = $openInventory ? $this->inventories->summary((int) $openInventory['id']) : null;

        $categoryOptions = [];
        $brandOptions = [];
        $tagOptions = [];
        if ($openInventory) {
            // MIGRADO: Buscar opções do banco local
            $categoryOptions = $this->products->listCategories();
            $brandOptions = $this->products->listBrands();
            $tagOptions = $this->products->listTags();
        }
        $imageUploadInfo = $this->imageService->info();
        $suggestedReason = $this->buildDefaultReason($this->inventories->nextId());

        View::render('inventory/batimento', [
            'errors' => $errors,
            'success' => $success,
            'openInventory' => $openInventory,
            'closedInventory' => $closedInventory,
            'pendingRows' => $pendingRows,
            'recentScans' => $recentScans,
            'summary' => $summary,
            'suggestedReason' => $suggestedReason,
            'categoryOptions' => $categoryOptions,
            'brandOptions' => $brandOptions,
            'tagOptions' => $tagOptions,
            'imageUploadInfo' => $imageUploadInfo,
            'esc' => [Html::class, 'esc'],
            'currentUser' => $currentUser,
            'resolveUserName' => function (?int $userId): string {
                return $this->resolveUserName($userId);
            },
            'formatDate' => function (?string $value): string {
                return $this->formatDate($value);
            },
        ], [
            'title' => 'Conferência de disponibilidade',
        ]);
    }

    public function monitor(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            Auth::requireLogin($this->inventories->getPdo());
            $currentUser = Auth::user();
        }

        $openInventory = $this->inventories->getOpen();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['close_inventory'])) {
                try {
                    Auth::requirePermission('inventory.close', $this->inventories->getPdo());
                    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
                    if (!$openInventory || $openInventory['id'] != $inventoryId) {
                        $errors[] = 'Batimento em aberto não encontrado.';
                    } else {
                        $this->liftExecutionLimit();
                        $this->inventories->close($inventoryId, (int) ($currentUser['id'] ?? 0));
                        $pending = $this->buildPendingItems($inventoryId);
                        $this->inventories->savePendingItems($inventoryId, $pending);
                        $success = 'Batimento fechado. Itens pendentes atualizados.';
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao fechar batimento: ' . $e->getMessage();
                }
            } elseif (isset($_POST['reopen_inventory'])) {
                try {
                    Auth::requirePermission('inventory.close', $this->inventories->getPdo());
                    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
                    if ($openInventory) {
                        $errors[] = 'Ja existe um batimento em aberto.';
                    } else {
                        $target = $this->inventories->find($inventoryId);
                        if (!$target || ($target['status'] ?? '') !== 'fechado') {
                            $errors[] = 'Batimento fechado não encontrado.';
                        } else {
                            $this->inventories->reopen($inventoryId);
                            $this->inventories->savePendingItems($inventoryId, []);
                            $success = 'Batimento reaberto com sucesso.';
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao reabrir batimento: ' . $e->getMessage();
                }
            }
        }

        $openInventory = $this->inventories->getOpen();

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 80;
        $perPageOptions = [40, 80, 120, 200];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 80;
        }

        $totalInventories = $this->inventories->countInventories();
        $totalPages = max(1, (int) ceil($totalInventories / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $inventories = $this->inventories->listInventories($perPage, $offset);

        $selectedId = (int) ($_GET['inventory'] ?? 0);
        if ($selectedId <= 0) {
            if ($openInventory) {
                $selectedId = (int) ($openInventory['id'] ?? 0);
            } elseif (!empty($inventories)) {
                $selectedId = (int) ($inventories[0]['id'] ?? 0);
            }
        }

        $selectedInventory = $selectedId > 0 ? $this->inventories->find($selectedId) : null;
        $items = [];
        $pending = [];
        $logs = [];
        $summary = null;
        $pendingLabel = 'Produtos a ler';

        if ($selectedInventory) {
            $summary = $this->inventories->summary($selectedId);
            $items = $this->inventories->listItems($selectedId);
            $logs = $this->inventories->listLogs($selectedId);
            if (($selectedInventory['status'] ?? '') === 'aberto') {
                try {
                    $pending = $this->buildPendingItems($selectedId);
                    $pendingLabel = 'Produtos a ler (disponibilidade positiva)';
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao calcular produtos pendentes: ' . $e->getMessage();
                }
            } else {
                $pending = $this->inventories->listPending($selectedId);
                $pendingLabel = 'Produtos não lidos ao fechar';
            }
        }

        $priceMap = $this->buildPriceMap($items, $pending);
        $metrics = $selectedInventory
            ? $this->buildInventoryMetrics($summary ?? [], $items, $pending, $logs, $priceMap)
            : null;
        $availabilityChanges = $selectedInventory ? $this->buildAvailabilityChangeMap($logs) : [];

        View::render('inventory/monitor', [
            'errors' => $errors,
            'success' => $success,
            'openInventory' => $openInventory,
            'inventories' => $inventories,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalInventories' => $totalInventories,
            'totalPages' => $totalPages,
            'selectedInventory' => $selectedInventory,
            'summary' => $summary,
            'items' => $items,
            'pending' => $pending,
            'pendingLabel' => $pendingLabel,
            'logs' => $logs,
            'metrics' => $metrics,
            'priceMap' => $priceMap,
            'availabilityChanges' => $availabilityChanges,
            'esc' => [Html::class, 'esc'],
            'formatDate' => function (?string $value): string {
                return $this->formatDate($value);
            },
        ], [
            'title' => 'Acompanhamento de Batimentos',
        ]);
    }

    private function handleAction(?array $openInventory, ?array $currentUser, array $errors): void
    {
        $action = trim((string) ($_POST['action'] ?? ''));

        try {
            if ($action === 'scan') {
                Auth::requirePermission('inventory.count', $this->inventories->getPdo());
                $this->handleScan($openInventory, $currentUser);
                return;
            }
            if ($action === 'update_product') {
                Auth::requirePermission('inventory.count', $this->inventories->getPdo());
                $this->handleUpdateProduct($openInventory, $currentUser);
                return;
            }
            if ($action === 'delete_scan') {
                Auth::requirePermission('inventory.count', $this->inventories->getPdo());
                $this->handleDeleteScan($openInventory, $currentUser);
                return;
            }
            if ($action === 'list_scans') {
                Auth::requirePermission('inventory.view', $this->inventories->getPdo());
                $this->handleListScans($openInventory, $currentUser);
                return;
            }
            if ($action === 'load_product') {
                Auth::requirePermission('inventory.count', $this->inventories->getPdo());
                $this->handleLoadProduct($openInventory, $currentUser);
                return;
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $this->respondJson([
            'ok' => false,
            'message' => 'Ação inválida ou indisponível.',
            'errors' => $errors,
        ], 400);
    }

    private function handleScan(?array $openInventory, ?array $currentUser): void
    {
        if (!$openInventory) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhum batimento aberto.'], 400);
        }
        if (!$currentUser) {
            $this->respondJson(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $skuRaw = (string) ($_POST['sku'] ?? '');
        $sku = $this->normalizeSku($skuRaw);
        $quantity = (int) ($_POST['quantity'] ?? 1);
        $mode = trim((string) ($_POST['mode'] ?? ''));
        $quantity = $quantity > 0 ? $quantity : 1;

        if ($sku === '') {
            $this->respondJson(['ok' => false, 'message' => 'Informe o SKU.'], 400);
        }

        // MIGRADO: Buscar produto localmente
        $product = $this->products->findBySku($sku);
        if (!$product && ctype_digit($sku)) {
            $product = $this->products->findById((int) $sku);
        }

        if (!$product) {
            $this->respondJson([
                'ok' => false,
                'message' => 'SKU não encontrado no sistema.',
            ], 404);
        }

        $productId = (int) $product->id;
        if ($productId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Produto inválido.'], 404);
        }

        // MIGRADO: Normalizar produto local
        $productData = $this->normalizeProduct($product);
        $productData = $this->attachSupply($productData, $productId);
        $scanSku = $productData['sku'] !== '' ? $productData['sku'] : $sku;

        $inventoryId = (int) $openInventory['id'];
        $item = $this->inventories->getItem($inventoryId, $productId);
        $duplicate = $item && (int) ($item['scan_count'] ?? 0) > 0;

        if ($duplicate && $mode === '') {
            $this->respondJson([
                'ok' => true,
                'duplicate' => true,
                'inventory' => $this->inventoryPayload($openInventory),
                'product' => $productData,
                'item' => $this->itemPayload($item),
                'message' => 'SKU já lido neste batimento.',
            ]);
        }

        $applyMode = $mode !== '' ? $mode : 'increment';
        $item = $this->inventories->applyScan(
            $inventoryId,
            $productId,
            $scanSku,
            $productData['name'],
            (int) ($currentUser['id'] ?? 0),
            $quantity,
            $applyMode
        );

        $availabilityResult = $this->syncStockFromCount($openInventory, $currentUser, $productData, $item);
        if (!empty($availabilityResult['product'])) {
            $productData = $availabilityResult['product'];
        }
        $summary = $this->inventories->summary($inventoryId);
        $recentScans = $this->recentScansPayload($inventoryId, 8);

        $this->respondJson([
            'ok' => true,
            'duplicate' => false,
            'inventory' => $this->inventoryPayload($openInventory),
            'product' => $productData,
            'item' => $this->itemPayload($item),
            'availability_updated' => $availabilityResult['updated'] ?? false,
            'availability_error' => $availabilityResult['error'] ?? null,
            'summary' => $summary,
            'recent_scans' => $recentScans,
            'message' => 'Leitura registrada.',
        ]);
    }

    private function handleUpdateProduct(?array $openInventory, ?array $currentUser): void
    {
        if (!$openInventory) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhum batimento aberto.'], 400);
        }
        if (!$currentUser) {
            $this->respondJson(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Produto inválido.'], 400);
        }
        $inventoryId = (int) ($openInventory['id'] ?? 0);
        $currentItem = $inventoryId > 0 ? $this->inventories->getItem($inventoryId, $productId) : null;

        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            $reason = (string) ($openInventory['default_reason'] ?? '');
        }

        // MIGRADO: Buscar produto do banco local
        $product = $this->products->findById($productId);
        if (!$product) {
            $this->respondJson(['ok' => false, 'message' => 'Produto não encontrado'], 404);
        }
        $before = $this->normalizeProduct($product);

        $payload = [];
        $errors = [];
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $payload['name'] = $name;
        }

        $priceRaw = trim((string) ($_POST['price'] ?? ''));
        if ($priceRaw !== '') {
            $normalizedPrice = $this->normalizePrice($priceRaw);
            if ($normalizedPrice !== '') {
                $payload['price'] = (float) $normalizedPrice;
            }
        }

        $countedQuantity = null;
        if (isset($_POST['counted_quantity'])) {
            $countedQuantity = (int) $_POST['counted_quantity'];
            if ($countedQuantity < 0) {
                $countedQuantity = 0;
            }
            $payload['quantity'] = $countedQuantity;
        }

        $status = trim((string) ($_POST['status'] ?? ''));
        if ($status !== '') {
            $statusQty = $countedQuantity ?? ((int) ($product->quantity ?? 0));
            $payload['status'] = $this->normalizeProductStatus($status, $statusQty);
        } elseif (array_key_exists('quantity', $payload)) {
            $payload['status'] = $this->normalizeProductStatus((string) ($product->status ?? 'draft'), (int) $payload['quantity']);
        }

        $visibility = trim((string) ($_POST['visibility'] ?? ($_POST['catalog_visibility'] ?? '')));
        if ($visibility !== '') {
            $payload['visibility'] = $this->normalizeVisibility($visibility);
        }

        if (array_key_exists('description', $_POST)) {
            $payload['description'] = trim((string) ($_POST['description'] ?? ''));
        }

        if (array_key_exists('short_description', $_POST)) {
            $payload['short_description'] = trim((string) ($_POST['short_description'] ?? ''));
        }

        if (array_key_exists('weight', $_POST)) {
            $weight = trim((string) ($_POST['weight'] ?? ''));
            $payload['weight'] = $weight !== '' ? Input::parseNumber($weight) : null;
        }

        if (isset($_POST['category_ids_present'])) {
            $categoryIds = $_POST['category_ids'] ?? [];
            $normalizedCategories = $this->normalizeCategoryIds($categoryIds);
            $payload['category_id'] = !empty($normalizedCategories) ? (int) ($normalizedCategories[0]['id'] ?? 0) : null;
        }

        if (array_key_exists('brand', $_POST)) {
            $brandId = (int) ($_POST['brand'] ?? 0);
            $payload['brand_id'] = $brandId > 0 ? $brandId : null;
        }

        if (isset($_POST['tag_ids_present'])) {
            $tagIds = $_POST['tag_ids'] ?? [];
            $normalizedTags = $this->normalizeTagIds($tagIds);
            $payload['tag_ids'] = array_values(array_map(
                static fn (array $tag): int => (int) ($tag['id'] ?? 0),
                $normalizedTags
            ));
        }

        $removeAllImages = !empty($_POST['remove_all_images']);
        $removeImageIds = $_POST['remove_image_ids'] ?? [];
        $removeImageSrcs = $_POST['remove_image_srcs'] ?? [];
        if (!is_array($removeImageIds)) {
            $removeImageIds = [];
        }
        if (!is_array($removeImageSrcs)) {
            $removeImageSrcs = [];
        }
        $removeImageIds = array_values(array_filter(array_map('intval', $removeImageIds)));
        $removeImageSrcs = array_values(array_filter(array_map('strval', $removeImageSrcs)));

        $preparedImages = $this->imageService->prepareUploads($_FILES['product_images'] ?? [], $errors);
        $hasImageUploads = !empty($preparedImages);
        $hasImageRemovals = $removeAllImages || !empty($removeImageIds) || !empty($removeImageSrcs);

        if (!empty($errors)) {
            $this->respondJson(['ok' => false, 'message' => implode(' ', $errors)], 400);
        }

        if ($hasImageUploads || $hasImageRemovals) {
            $existingImages = isset($before['images']) && is_array($before['images']) ? $before['images'] : [];
            $remainingImages = [];
            foreach ($existingImages as $image) {
                if ($removeAllImages) {
                    continue;
                }
                $imageId = (int) ($image['id'] ?? 0);
                $imageSrc = (string) ($image['src'] ?? '');
                if ($imageId > 0 && in_array($imageId, $removeImageIds, true)) {
                    continue;
                }
                if ($imageId <= 0 && $imageSrc !== '' && in_array($imageSrc, $removeImageSrcs, true)) {
                    continue;
                }
                $remainingImages[] = $image;
            }

            $uploadedImages = [];
            $mediaImages = []; // Código de upload para serviço externo removido - upload apenas local
            if ($hasImageUploads) {
                $uploadedImages = $this->imageService->storePrepared($preparedImages, $errors);
                if (!empty($errors)) {
                    $this->respondJson(['ok' => false, 'message' => implode(' ', $errors)], 400);
                }
                // uploadMedia() removido - era código morto (sempre falhava)
            }

            $imagesSource = !empty($mediaImages) ? $mediaImages : $uploadedImages;
            $payload['images'] = $this->buildImagesPayload($remainingImages, $imagesSource, false);
        }

        if (empty($payload)) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhuma alteração informada.'], 400);
        }

        $metadata = is_array($product->metadata ?? null) ? $product->metadata : [];
        if (isset($payload['name'])) {
            $product->name = (string) $payload['name'];
        }
        if (array_key_exists('price', $payload)) {
            $product->price = $payload['price'] !== null ? (float) $payload['price'] : null;
        }
        if (isset($payload['status'])) {
            $product->status = (string) $payload['status'];
        }
        if (isset($payload['visibility'])) {
            $product->visibility = (string) $payload['visibility'];
        }
        if (array_key_exists('description', $payload)) {
            $descriptionValue = trim((string) $payload['description']);
            $product->description = $descriptionValue !== '' ? $descriptionValue : null;
        }
        if (array_key_exists('short_description', $payload)) {
            $shortValue = trim((string) $payload['short_description']);
            $product->shortDescription = $shortValue !== '' ? $shortValue : null;
        }
        if (array_key_exists('weight', $payload)) {
            $weightValue = $payload['weight'];
            $product->weight = $weightValue !== null ? (float) $weightValue : null;
        }
        if (array_key_exists('quantity', $payload)) {
            $product->quantity = max(0, (int) $payload['quantity']);
        }
        if (array_key_exists('category_id', $payload)) {
            $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : 0;
            $product->categoryId = $categoryId > 0 ? $categoryId : null;
        }
        if (array_key_exists('brand_id', $payload)) {
            $brandId = isset($payload['brand_id']) ? (int) $payload['brand_id'] : 0;
            $product->brandId = $brandId > 0 ? $brandId : null;
        }
        if (array_key_exists('tag_ids', $payload)) {
            $metadata['tag_ids'] = array_values(array_filter(array_map('intval', $payload['tag_ids'] ?? [])));
        }
        if (array_key_exists('images', $payload)) {
            $product->images = is_array($payload['images']) ? $payload['images'] : [];
            $metadata['images'] = $product->images;
        }
        $product->metadata = $metadata;

        $product = $this->products->save($product);

        $after = $this->normalizeProduct($product);
        $after = $this->attachSupply($after, $productId);
        $skuValue = $after['sku'] !== '' ? $after['sku'] : ($before['sku'] ?? '');

        if ($countedQuantity !== null) {
            $previousCount = $currentItem ? (int) ($currentItem['counted_quantity'] ?? 0) : null;
            if ($previousCount === null || $previousCount !== $countedQuantity) {
                $this->inventories->updateCountedQuantity(
                    (int) $openInventory['id'],
                    $productId,
                    $skuValue,
                    $after['name'],
                    (int) ($currentUser['id'] ?? 0),
                    $countedQuantity
                );
                $this->inventories->overwriteLastScan(
                    (int) $openInventory['id'],
                    $productId,
                    $skuValue,
                    $after['name'],
                    (int) ($currentUser['id'] ?? 0),
                    'adjust',
                    $countedQuantity
                );
            }
        }

        $this->inventories->logAdjustment(
            (int) $openInventory['id'],
            $productId,
            $skuValue,
            'manual_update',
            $this->loggableFields($before),
            $this->loggableFields($after),
            $reason !== '' ? $reason : null,
            (int) ($currentUser['id'] ?? 0)
        );

        $this->respondJson([
            'ok' => true,
            'product' => $after,
            'summary' => $this->inventories->summary((int) $openInventory['id']),
            'recent_scans' => $this->recentScansPayload((int) $openInventory['id'], 8),
            'message' => 'Produto atualizado.',
        ]);
    }

    private function handleDeleteScan(?array $openInventory, ?array $currentUser): void
    {
        if (!$openInventory) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhum batimento aberto.'], 400);
        }
        if (!$currentUser) {
            $this->respondJson(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $inventoryId = (int) ($openInventory['id'] ?? 0);
        $scanId = (int) ($_POST['scan_id'] ?? 0);
        if ($scanId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Leitura inválida.'], 400);
        }

        $scan = $this->inventories->findScan($inventoryId, $scanId);
        if (!$scan) {
            $this->respondJson(['ok' => false, 'message' => 'Leitura não encontrada.'], 404);
        }

        $productId = (int) ($scan['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Produto inválido.'], 400);
        }

        $this->inventories->deleteScan($inventoryId, $scanId);
        $item = $this->inventories->recalculateItemFromScans($inventoryId, $productId);

        $product = null;
        try {
            // MIGRADO: Buscar produto do banco local
            $productModel = $this->products->findById($productId);
            if ($productModel) {
                $product = $this->normalizeProduct($productModel);
                $product = $this->attachSupply($product, $productId);
            }
        } catch (\Throwable $e) {
            $product = null;
        }

        $this->respondJson([
            'ok' => true,
            'message' => 'Leitura removida.',
            'product' => $product,
            'item' => $this->itemPayload($item),
            'item_removed' => $item ? false : true,
            'summary' => $this->inventories->summary($inventoryId),
            'recent_scans' => $this->recentScansPayload($inventoryId, 8),
            'availability_updated' => false,
            'availability_error' => null,
        ]);
    }

    private function handleListScans(?array $openInventory, ?array $currentUser): void
    {
        if (!$openInventory) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhum batimento aberto.'], 400);
        }
        if (!$currentUser) {
            $this->respondJson(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $inventoryId = (int) ($openInventory['id'] ?? 0);
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 50;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $query = trim((string) ($_POST['query'] ?? ''));

        $rows = $this->inventories->listScans($inventoryId, $query, $limit, $offset);
        $payload = [];
        foreach ($rows as $row) {
            $productSku = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? 0));
            $payload[] = [
                'id' => (int) ($row['id'] ?? 0),
                'product_sku' => $productSku,
                'product_id' => $productSku,
                'sku' => (string) ($row['sku'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'action' => (string) ($row['action'] ?? ''),
                'user_name' => (string) ($row['user_name'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        $hasMore = count($rows) >= max(1, min(200, $limit));

        $this->respondJson([
            'ok' => true,
            'scans' => $payload,
            'has_more' => $hasMore,
        ]);
    }

    private function handleLoadProduct(?array $openInventory, ?array $currentUser): void
    {
        if (!$openInventory) {
            $this->respondJson(['ok' => false, 'message' => 'Nenhum batimento aberto.'], 400);
        }
        if (!$currentUser) {
            $this->respondJson(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->respondJson(['ok' => false, 'message' => 'Produto inválido.'], 400);
        }

        // MIGRADO: Buscar produto do banco local
        $productModel = $this->products->findById($productId);
        if (!$productModel) {
            $this->respondJson(['ok' => false, 'message' => 'Produto não encontrado.'], 404);
        }
        $product = $this->normalizeProduct($productModel);
        $product = $this->attachSupply($product, $productId);
        $item = $this->inventories->getItem((int) ($openInventory['id'] ?? 0), $productId);

        $this->respondJson([
            'ok' => true,
            'product' => $product,
            'item' => $this->itemPayload($item),
            'message' => 'Produto carregado.',
        ]);
    }

    private function syncStockFromCount(array $openInventory, array $currentUser, array $product, ?array $item): array
    {
        if (!$item) {
            return ['updated' => false];
        }

        $productId = (int) ($product['id'] ?? 0);
        if ($productId <= 0) {
            return ['updated' => false, 'error' => 'Produto inválido'];
        }

        $counted = max(0, (int) ($item['counted_quantity'] ?? 0));
        $currentStock = isset($product['quantity']) ? (int) $product['quantity'] : 0;
        if ($currentStock === $counted) {
            return ['updated' => false];
        }

        try {
            $reason = trim((string) ($openInventory['default_reason'] ?? ''));
            $updated = $this->products->updateQuantity(
                $productId,
                $counted,
                null,
                'ajuste',
                $reason !== '' ? $reason : null
            );
            if (!$updated) {
                return ['updated' => false, 'error' => 'Produto não encontrado'];
            }

            $productModel = $this->products->findById($productId);
            if (!$productModel) {
                return ['updated' => false, 'error' => 'Produto não encontrado após ajuste'];
            }
            $after = $this->normalizeProduct($productModel);
            $after = $this->attachSupply($after, $productId);

            $beforeStatus = $this->toAvailabilityStatus($product);
            $afterStatus = $this->toAvailabilityStatus($after);
            $this->inventories->logAdjustment(
                (int) $openInventory['id'],
                $productId,
                (string) ($product['sku'] ?? ''),
                'scan_update',
                [
                    'quantity' => $product['quantity'] ?? null,
                    'availability_status' => $beforeStatus,
                    'status' => $product['status'] ?? null,
                ],
                [
                    'quantity' => $after['quantity'] ?? null,
                    'availability_status' => $afterStatus,
                    'status' => $after['status'] ?? null,
                ],
                $reason !== '' ? $reason : null,
                (int) ($currentUser['id'] ?? 0)
            );

            return ['updated' => true, 'product' => $after];
        } catch (\Throwable $e) {
            return ['updated' => false, 'error' => $e->getMessage()];
        }
    }

    private function bulkZeroPending(int $inventoryId, array $pendingRows, string $reason, int $userId): void
    {
        if ($reason === '') {
            $inventory = $this->inventories->find($inventoryId);
            $reason = (string) ($inventory['default_reason'] ?? '');
        }
        foreach ($pendingRows as $row) {
            $productId = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? 0));
            if ($productId <= 0) {
                continue;
            }

            $this->products->updateQuantity(
                $productId,
                0,
                null,
                'ajuste',
                $reason !== '' ? $reason : null
            );

            $this->inventories->logAdjustment(
                $inventoryId,
                $productId,
                (string) ($row['sku'] ?? ''),
                'bulk_zero',
                [
                    'quantity' => $row['quantity'] ?? 0,
                    'availability_status' => $this->toAvailabilityStatus($row),
                ],
                [
                    'quantity' => 0,
                    'availability_status' => 'outofstock',
                ],
                $reason !== '' ? $reason : null,
                $userId
            );
        }
    }

    private function buildPendingItems(int $inventoryId): array
    {
        // MIGRADO: Buscar produtos com disponibilidade positiva do banco local
        $existing = array_flip($this->inventories->listItemIds($inventoryId));
        $filters = ['stock_positive' => true];
        $limit = 200;
        $pending = [];
        $offset = 0;

        while (true) {
            $rows = $this->products->listProductsForBulk($filters, $limit, $offset);
            if (!$rows) {
                break;
            }
            foreach ($rows as $row) {
                $productId = (int) ($row['id'] ?? 0);
                if ($productId <= 0 || isset($existing[$productId])) {
                    continue;
                }
                $pending[] = [
                    'id' => $productId,
                    'sku' => (string) ($row['sku'] ?? ''),
                    'name' => (string) ($row['nome'] ?? $row['name'] ?? ''),
                    'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                    'availability_status' => $this->toAvailabilityStatus($row),
                ];
            }
            if (count($rows) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return $pending;
    }

    private function liftExecutionLimit(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
    }

    private function normalizeSku(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/^sku\s*/i', '', $value);
        return trim((string) $value);
    }

    private function normalizePrice(string $value): string
    {
        $parsed = Input::parseNumber($value);
        if ($parsed === null) {
            return '';
        }
        return number_format($parsed, 2, '.', '');
    }

    private function normalizeProductStatus(string $value, int $quantity): string
    {
        $status = strtolower(trim($value));
        $legacyMap = [
            'publish' => 'disponivel',
            'active' => 'disponivel',
            'pending' => 'draft',
            'private' => 'archived',
            'instock' => 'disponivel',
            'outofstock' => 'esgotado',
            'trash' => 'archived',
        ];
        $normalized = $legacyMap[$status] ?? $status;

        $allowed = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
        if (!in_array($normalized, $allowed, true)) {
            $normalized = 'draft';
        }

        if ($normalized === 'disponivel' && $quantity <= 0) {
            return 'esgotado';
        }
        if ($normalized === 'esgotado' && $quantity > 0) {
            return 'disponivel';
        }

        return $normalized;
    }

    private function normalizeVisibility(string $value): string
    {
        $visibility = strtolower(trim($value));
        if ($visibility === 'visible') {
            $visibility = 'public';
        }
        $allowed = ['public', 'catalog', 'search', 'hidden'];
        return in_array($visibility, $allowed, true) ? $visibility : 'public';
    }

    /**
     * @param array<string, mixed> $product
     */
    private function toAvailabilityStatus(array $product): string
    {
        $raw = strtolower(trim((string) ($product['availability_status'] ?? '')));
        if ($raw === 'instock' || $raw === 'outofstock') {
            return $raw;
        }

        $qty = $product['quantity'] ?? null;
        $qtyValue = $qty === null || $qty === '' ? 0 : (int) $qty;
        $status = strtolower(trim((string) ($product['status'] ?? 'draft')));
        $status = $this->normalizeProductStatus($status, $qtyValue);

        return ($status === 'disponivel' && $qtyValue > 0) ? 'instock' : 'outofstock';
    }

    // REMOVIDO: getCatalogProducts() e bootstrapCatalogReadRepository() - sistema 100% autônomo

    private function normalizeCatalogProduct(array $product): array
    {
        $images = $this->normalizeProductImages($product);
        $firstImage = $images[0] ?? null;
        $imagePayload = null;
        if (is_array($firstImage)) {
            $imagePayload = [
                'src' => (string) ($firstImage['src'] ?? ''),
                'alt' => (string) ($firstImage['alt'] ?? ($firstImage['name'] ?? '')),
            ];
        }

        $categories = is_array($product['categories'] ?? null) ? $product['categories'] : [];
        $categoryPayload = [];
        $categoryIds = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $categoryId = (int) ($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $categoryPayload[] = [
                'id' => $categoryId,
                'name' => (string) ($category['name'] ?? ''),
            ];
            $categoryIds[] = (string) $categoryId;
        }

        $brandId = $this->extractBrandId($product);
        $tags = is_array($product['tags'] ?? null) ? $product['tags'] : [];
        $tagIds = [];
        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $tagId = (int) ($tag['id'] ?? 0);
            if ($tagId <= 0) {
                continue;
            }
            $tagIds[] = (string) $tagId;
        }

        return [
            'id' => (int) ($product['id'] ?? 0),
            'sku' => (string) ($product['sku'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'price' => (string) ($product['regular_price'] ?? $product['price'] ?? ''),
            'quantity' => isset($product['quantity']) ? (int) $product['quantity'] : null,
            'availability_status' => (string) ($product['availability_status'] ?? ''),
            'manage_stock' => !empty($product['manage_stock']),
            'status' => (string) ($product['status'] ?? ''),
            'catalog_visibility' => (string) ($product['catalog_visibility'] ?? ''),
            'weight' => (string) ($product['weight'] ?? ''),
            'description' => (string) ($product['description'] ?? ''),
            'short_description' => (string) ($product['short_description'] ?? ''),
            'image' => $imagePayload,
            'images' => $images,
            'brand' => $brandId,
            'categories' => $categoryPayload,
            'category_ids' => $categoryIds,
            'tag_ids' => $tagIds,
        ];
    }

    /**
     * MIGRADO: Normaliza Product model para array usado no inventário
     */
    private function normalizeProduct($product): array
    {
        // Se já é array (legacy), usar método antigo
        if (is_array($product)) {
            return $this->normalizeCatalogProduct($product);
        }

        // MIGRADO: Converter Product model
        $images = [];
        if (!empty($product->images) && is_array($product->images)) {
            foreach ($product->images as $img) {
                if (is_array($img)) {
                    $images[] = $img;
                }
            }
        }
        
        $firstImage = $images[0] ?? null;
        $imagePayload = null;
        if (is_array($firstImage)) {
            $imagePayload = [
                'src' => (string) ($firstImage['src'] ?? ''),
                'alt' => (string) ($firstImage['alt'] ?? ($firstImage['name'] ?? '')),
            ];
        }

        $tagIds = [];
        if (isset($product->metadata['tag_ids']) && is_array($product->metadata['tag_ids'])) {
            foreach ($product->metadata['tag_ids'] as $tagId) {
                $id = (int) $tagId;
                if ($id > 0) {
                    $tagIds[] = (string) $id;
                }
            }
        }
        $categoryIds = [];
        if (!empty($product->categoryId)) {
            $categoryIds[] = (string) ((int) $product->categoryId);
        }
        $brandValue = (string) ((int) ($product->brandId ?? $product->brand ?? 0));

        return [
            'id' => (string) $product->id,
            'sku' => (string) ($product->sku ?? ''),
            'name' => (string) ($product->nome ?? $product->name ?? ''),
            'price' => (string) ($product->preco_venda ?? $product->price ?? ''),
            'quantity' => isset($product->quantity) ? (int) $product->quantity : null,
            'manage_stock' => true,
            'availability_status' => ($product->status === 'disponivel' && (int) ($product->quantity ?? 0) > 0) ? 'instock' : 'outofstock',
            'status' => (string) ($product->status ?? 'draft'),
            'catalog_visibility' => (string) ($product->visibility ?? 'public'),
            'image' => $imagePayload,
            'images' => $images,
            'categories' => [],
            'category_ids' => $categoryIds,
            'brand' => $brandValue,
            'brand_id' => $brandValue,
            'tags' => [],
            'tag_ids' => $tagIds,
        ];
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

    private function brandApiField(): string
    {
        $field = trim((string) (getenv('BRAND_API_FIELD') ?: ''));
        if ($field === '') {
            return 'brands';
        }

        return $field;
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

    /**
     * @param mixed $categoryIds
     * @return array<int, array{id:int}>
     */
    private function normalizeCategoryIds($categoryIds): array
    {
        if (!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }
        $payload = [];
        foreach ($categoryIds as $categoryId) {
            $categoryId = (int) $categoryId;
            if ($categoryId > 0) {
                $payload[] = ['id' => $categoryId];
            }
        }

        return $payload;
    }

    /**
     * @param mixed $tagIds
     * @return array<int, array{id:int}>
     */
    private function normalizeTagIds($tagIds): array
    {
        if (!is_array($tagIds)) {
            $tagIds = [$tagIds];
        }
        $payload = [];
        foreach ($tagIds as $tagId) {
            $tagId = (int) $tagId;
            if ($tagId > 0) {
                $payload[] = ['id' => $tagId];
            }
        }

        return $payload;
    }

    private function attachSupply(array $product, int $productId): array
    {
        $supplyMap = $this->supplies->listByProductIds([$productId]);
        $supply = $supplyMap[$productId] ?? null;
        $product['supply'] = [
            'source' => (string) ($supply['source'] ?? ''),
            'supplier_id' => (int) ($supply['supplier_id_vendor'] ?? 0),
            'supplier_name' => (string) ($supply['supplier_name'] ?? ''),
        ];

        return $product;
    }

    private function inventoryPayload(array $inventory): array
    {
        return [
            'id' => (int) ($inventory['id'] ?? 0),
            'blind_count' => !empty($inventory['blind_count']),
            'default_reason' => (string) ($inventory['default_reason'] ?? ''),
            'opened_at' => (string) ($inventory['opened_at'] ?? ''),
        ];
    }

    private function itemPayload(?array $item): ?array
    {
        if (!$item) {
            return null;
        }
        return [
            'counted_quantity' => (int) ($item['counted_quantity'] ?? 0),
            'scan_count' => (int) ($item['scan_count'] ?? 0),
            'last_scan_at' => (string) ($item['last_scan_at'] ?? ''),
            'last_user_name' => (string) ($item['last_user_name'] ?? ''),
        ];
    }

    private function resolveUserName(?int $userId): string
    {
        if (!$userId) {
            return '-';
        }
        $user = $this->users->find($userId);
        return $user ? $user->fullName : ('Usuário #' . $userId);
    }

    private function buildDefaultReason(int $inventoryId): string
    {
        $timestamp = date('d/m/Y H:i');
        return 'Batimento #' . $inventoryId . ' - ' . $timestamp;
    }

    private function formatDate(?string $value): string
    {
        if (!$value) {
            return '-';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return $value;
        }
        return date('d/m/Y H:i', $ts);
    }

    private function loggableFields(array $product): array
    {
        $categories = [];
        if (!empty($product['category_ids']) && is_array($product['category_ids'])) {
            foreach ($product['category_ids'] as $categoryId) {
                $categoryId = trim((string) $categoryId);
                if ($categoryId !== '') {
                    $categories[] = $categoryId;
                }
            }
        }
        $images = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $image) {
                if (!is_array($image)) {
                    continue;
                }
                $id = isset($image['id']) ? (int) $image['id'] : 0;
                $src = isset($image['src']) ? (string) $image['src'] : '';
                if ($id > 0) {
                    $images[] = 'id:' . $id;
                } elseif ($src !== '') {
                    $images[] = 'src:' . $src;
                }
            }
        }
        $tagIds = [];
        if (!empty($product['tag_ids']) && is_array($product['tag_ids'])) {
            foreach ($product['tag_ids'] as $tagId) {
                $tagId = trim((string) $tagId);
                if ($tagId !== '') {
                    $tagIds[] = $tagId;
                }
            }
        }
        $brand = trim((string) ($product['brand'] ?? ''));

        return [
            'name' => $product['name'] ?? null,
            'price' => $product['price'] ?? null,
            'quantity' => $product['quantity'] ?? null,
            'availability_status' => $product['availability_status'] ?? null,
            'status' => $product['status'] ?? null,
            'catalog_visibility' => $product['catalog_visibility'] ?? null,
            'weight' => $product['weight'] ?? null,
            'brand' => $brand !== '' ? $brand : null,
            'category_ids' => $categories,
            'tag_ids' => $tagIds,
            'images' => $images,
        ];
    }

    private function recentScansPayload(int $inventoryId, int $limit = 8): array
    {
        $rows = $this->inventories->listRecentScans($inventoryId, $limit);
        $payload = [];
        foreach ($rows as $row) {
            $productSku = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? 0));
            $payload[] = [
                'id' => (int) ($row['id'] ?? 0),
                'product_sku' => $productSku,
                'product_id' => $productSku,
                'sku' => (string) ($row['sku'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'action' => (string) ($row['action'] ?? ''),
                'user_name' => (string) ($row['user_name'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $pending
     * @return array<int, array<string, mixed>>
     */
    private function buildPriceMap(array $items, array $pending): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) (($item['product_sku'] ?? 0) ?: ($item['product_id'] ?? 0));
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        foreach ($pending as $row) {
            $id = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? $row['id'] ?? 0));
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if (!$ids) {
            return [];
        }

        // MIGRADO: Buscar produtos por IDs do banco local
        $products = [];
        foreach ($ids as $id) {
            $product = $this->products->findById($id);
            if ($product) {
                $products[] = $product;
            }
        }
        
        $map = [];
        foreach ($products as $product) {
            $productId = (int) $product->id;
            if ($productId <= 0) {
                continue;
            }
            $price = (float) ($product->preco_venda ?? 0.0);
            $map[$productId] = [
                'sku' => (string) ($product->sku ?? ''),
                'name' => (string) ($product->nome ?? $product->name ?? ''),
                'price' => $price,
                'quantity' => $product->quantity !== null ? (int) $product->quantity : null,
            ];
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<int, array{before:?int, after:?int}>
     */
    private function buildAvailabilityChangeMap(array $logs): array
    {
        if (empty($logs)) {
            return [];
        }

        $map = [];
        $ordered = array_reverse($logs);
        foreach ($ordered as $log) {
            $productId = (int) ($log['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $before = $this->decodeLogJson($log['before_json'] ?? null);
            $after = $this->decodeLogJson($log['after_json'] ?? null);

            if (!array_key_exists($productId, $map)) {
                $map[$productId] = ['before' => null, 'after' => null];
            }
            if ($map[$productId]['before'] === null && isset($before['quantity'])) {
                $map[$productId]['before'] = (int) $before['quantity'];
            }
            if (isset($after['quantity'])) {
                $map[$productId]['after'] = (int) $after['quantity'];
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $pending
     * @param array<int, array<string, mixed>> $logs
     * @param array<int, array<string, mixed>> $priceMap
     * @return array<string, mixed>
     */
    private function buildInventoryMetrics(array $summary, array $items, array $pending, array $logs, array $priceMap): array
    {
        $metrics = [
            'unique_items' => (int) ($summary['unique_items'] ?? 0),
            'total_counted' => (int) ($summary['total_counted'] ?? 0),
            'total_scans' => (int) ($summary['total_scans'] ?? 0),
            'missing_items' => count($pending),
            'missing_quantity' => 0,
            'total_read_value' => 0.0,
            'total_missing_value' => 0.0,
            'avg_value_before' => 0.0,
            'avg_value_after' => 0.0,
            'adjustment_events' => 0,
            'adjusted_items' => 0,
            'price_adjustments' => 0,
            'availability_adjustments' => 0,
            'name_adjustments' => 0,
            'status_adjustments' => 0,
            'category_adjustments' => 0,
            'photos_added' => 0,
            'photos_removed' => 0,
            'zeroed_items' => 0,
            'zeroed_quantity' => 0,
        ];

        $priceBefore = [];
        foreach ($logs as $log) {
            $productId = (int) ($log['product_id'] ?? 0);
            if ($productId <= 0 || isset($priceBefore[$productId])) {
                continue;
            }
            $before = $this->decodeLogJson($log['before_json'] ?? null);
            $after = $this->decodeLogJson($log['after_json'] ?? null);
            $changes = $this->diffLogFields($before, $after);
            if (!$changes['price']) {
                continue;
            }
            $beforePrice = $this->normalizeNumber($before['price'] ?? null);
            if ($beforePrice !== null) {
                $priceBefore[$productId] = $beforePrice;
            }
        }

        $totalBeforeValue = 0.0;
        foreach ($items as $item) {
            $productId = (int) (($item['product_sku'] ?? 0) ?: ($item['product_id'] ?? 0));
            $counted = (int) ($item['counted_quantity'] ?? 0);
            $price = isset($priceMap[$productId]['price']) ? (float) $priceMap[$productId]['price'] : 0.0;
            $beforePrice = isset($priceBefore[$productId]) ? (float) $priceBefore[$productId] : $price;
            $metrics['total_read_value'] += $counted * $price;
            $totalBeforeValue += $counted * $beforePrice;
        }

        foreach ($pending as $row) {
            $productId = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? $row['id'] ?? 0));
            $qtySource = $row['quantity'] ?? null;
            $qty = $qtySource !== null ? (int) $qtySource : 0;
            $price = isset($priceMap[$productId]['price']) ? (float) $priceMap[$productId]['price'] : 0.0;
            $metrics['missing_quantity'] += $qty;
            $metrics['total_missing_value'] += $qty * $price;
        }

        $adjustedProducts = [];
        foreach ($logs as $log) {
            $before = $this->decodeLogJson($log['before_json'] ?? null);
            $after = $this->decodeLogJson($log['after_json'] ?? null);

            $changes = $this->diffLogFields($before, $after);
            if ($changes['any']) {
                $metrics['adjustment_events'] += 1;
                $productId = (int) ($log['product_id'] ?? 0);
                if ($productId > 0) {
                    $adjustedProducts[$productId] = true;
                }
            }
            if ($changes['price']) {
                $metrics['price_adjustments'] += 1;
            }
            if ($changes['availability']) {
                $metrics['availability_adjustments'] += 1;
            }
            if ($changes['name']) {
                $metrics['name_adjustments'] += 1;
            }
            if ($changes['status']) {
                $metrics['status_adjustments'] += 1;
            }
            if ($changes['categories']) {
                $metrics['category_adjustments'] += 1;
            }
            if ($changes['images']) {
                $metrics['photos_added'] += $changes['images_added'];
                $metrics['photos_removed'] += $changes['images_removed'];
            }

            if (($log['action'] ?? '') === 'bulk_zero') {
                $metrics['zeroed_items'] += 1;
                $metrics['zeroed_quantity'] += (int) ($before['quantity'] ?? 0);
            }
        }

        $metrics['adjusted_items'] = count($adjustedProducts);
        if ($metrics['total_counted'] > 0) {
            $metrics['avg_value_after'] = $metrics['total_read_value'] / $metrics['total_counted'];
            $metrics['avg_value_before'] = $totalBeforeValue / $metrics['total_counted'];
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeLogJson(?string $payload): array
    {
        if (!$payload) {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array{any:bool, price:bool, availability:bool, name:bool, status:bool, categories:bool, images:bool, images_added:int, images_removed:int}
     */
    private function diffLogFields(array $before, array $after): array
    {
        $priceBefore = $this->normalizeNumber($before['price'] ?? null);
        $priceAfter = $this->normalizeNumber($after['price'] ?? null);
        $priceChanged = $priceBefore !== $priceAfter;

        $availabilityQtyBefore = $this->normalizeNumber($before['quantity'] ?? null);
        $availabilityQtyAfter = $this->normalizeNumber($after['quantity'] ?? null);
        $availabilityStatusBefore = $this->normalizeString($before['availability_status'] ?? null);
        $availabilityStatusAfter = $this->normalizeString($after['availability_status'] ?? null);
        $availabilityChanged = $availabilityQtyBefore !== $availabilityQtyAfter || $availabilityStatusBefore !== $availabilityStatusAfter;

        $nameChanged = $this->normalizeString($before['name'] ?? null) !== $this->normalizeString($after['name'] ?? null);
        $statusChanged = $this->normalizeString($before['status'] ?? null) !== $this->normalizeString($after['status'] ?? null);

        $beforeCategories = $this->normalizeLogList($before['category_ids'] ?? []);
        $afterCategories = $this->normalizeLogList($after['category_ids'] ?? []);
        $categoriesChanged = $beforeCategories !== $afterCategories;

        $beforeImages = $this->normalizeLogList($before['images'] ?? []);
        $afterImages = $this->normalizeLogList($after['images'] ?? []);
        $added = array_values(array_diff($afterImages, $beforeImages));
        $removed = array_values(array_diff($beforeImages, $afterImages));
        $imagesChanged = !empty($added) || !empty($removed);

        return [
            'any' => $priceChanged || $availabilityChanged || $nameChanged || $statusChanged || $categoriesChanged || $imagesChanged,
            'price' => $priceChanged,
            'availability' => $availabilityChanged,
            'name' => $nameChanged,
            'status' => $statusChanged,
            'categories' => $categoriesChanged,
            'images' => $imagesChanged,
            'images_added' => count($added),
            'images_removed' => count($removed),
        ];
    }

    private function normalizeNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    private function normalizeString($value): string
    {
        $value = $value === null ? '' : (string) $value;
        return trim($value);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeLogList($value): array
    {
        if (!is_array($value)) {
            $value = [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
        $items = array_values(array_unique($items));
        sort($items);
        return $items;
    }

    // REMOVIDO: resolveBrandTaxonomy() - não usado (sistema 100% autônomo)

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
