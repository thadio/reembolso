<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\ProductWriteOffRepository;
use App\Repositories\VendorRepository;
use App\Services\ProductWriteOffService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use PDO;

class ProductWriteOffController
{
    private ProductWriteOffRepository $writeoffs;
    private ProductRepository $products;
    private ProductSupplyRepository $supplies;
    private VendorRepository $vendors;
    private ProductWriteOffService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->writeoffs = new ProductWriteOffRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->service = new ProductWriteOffService();
        $this->connectionError = $connectionError;
    }

    public function form(): void
    {
        $errors = [];
        $notices = [];
        $success = '';
        $termLinks = [];
        $productOptions = [];
        $initialQueryItem = $this->buildInitialWriteoffItem();
        $initialWriteoffItem = null;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('products.writeoff', $this->writeoffs->getPdo());

        $form = [
            'notes' => '',
            'destination_default' => 'nao_localizado',
            'reason_default' => 'perdido',
        ];
        $productOptions = $this->loadProductOptionsForWriteOff();
        if ($initialQueryItem) {
            $initialProduct = $this->findProductForWriteOff($initialQueryItem);
            if ($initialProduct) {
                if (empty($initialQueryItem['product_sku'])) {
                    $initialQueryItem['product_sku'] = isset($initialProduct['ID']) ? (int) $initialProduct['ID'] : null;
                }
                if (($initialQueryItem['sku'] ?? '') === '' && !empty($initialProduct['sku'])) {
                    $initialQueryItem['sku'] = (string) $initialProduct['sku'];
                }
                $initialQueryItem['product_label'] = trim((string) ($initialProduct['post_title'] ?? ''));
                $productOptions = $this->includeProductInOptions($productOptions, $initialProduct);
                $initialWriteoffItem = $initialQueryItem;
            }
        }

        $queue = [];
        $validatedItems = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = Input::trimStrings($_POST);
            $form['notes'] = $postData['notes'] ?? $form['notes'];
            $form['destination_default'] = $postData['destination_default'] ?? $form['destination_default'];
            $form['reason_default'] = $postData['reason_default'] ?? $form['reason_default'];

            $items = $this->extractPostedItems($postData);
            if (empty($items)) {
                $errors[] = 'Adicione pelo menos um produto à lista.';
            } else {
                [$validatedItems, $validationErrors] = $this->service->validateItems($items);
                $errors = array_merge($errors, $validationErrors);
            }

            if (empty($errors)) {
                foreach ($validatedItems as &$item) {
                    $item['notes'] = $form['notes'] !== '' ? $form['notes'] : null;
                }
                unset($item);
                [$queue, $queueErrors] = $this->buildWriteOffQueue($validatedItems);
                $errors = array_merge($errors, $queueErrors);
            }

            if (empty($errors) && !empty($queue)) {
                try {
                    $processed = 0;
                    foreach ($queue as $entry) {
                        $this->products->updateStock((int) $entry['product_sku'], (int) $entry['stock_after']);
                        $termToken = null;
                        if (!empty($entry['term_required'])) {
                            $termToken = bin2hex(random_bytes(12));
                        }
                        $this->writeoffs->create([
                            'product_sku' => $entry['product_sku'],
                            'sku' => $entry['sku'],
                            'supplier_pessoa_id' => $entry['supplier_pessoa_id'] ?? null,
                            'source' => $entry['source'] ?? null,
                            'destination' => $entry['destination'],
                            'reason' => $entry['reason'],
                            'quantity' => $entry['quantity'],
                            'notes' => $entry['notes'] ?? null,
                            'stock_before' => $entry['stock_before'],
                            'stock_after' => $entry['stock_after'],
                            'term_token' => $termToken,
                            'created_by' => Auth::user()['id'] ?? null,
                        ]);
                        $processed++;
                        if ($termToken) {
                            $termLinks[] = [
                                'product_label' => $entry['product_label'],
                                'link' => 'produto-baixa-termo.php?token=' . urlencode($termToken),
                            ];
                        }
                    }
                    if ($processed > 0) {
                        $success = $processed === 1
                            ? 'Baixa registrada e disponibilidade ajustada.'
                            : sprintf('%d baixas registradas e disponibilidades ajustadas.', $processed);
                        if (!empty($termLinks)) {
                            $notices[] = 'Itens consignados: gere os termos abaixo.';
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao ajustar disponibilidade no sistema: ' . $e->getMessage();
                }
            }
        }

        $recentWriteOffs = $this->writeoffs->listRecent(50);
        $vendorCache = [];
        foreach ($recentWriteOffs as &$row) {
            $supplierPessoaId = isset($row['supplier_pessoa_id']) ? (int) $row['supplier_pessoa_id'] : 0;
            if ($supplierPessoaId > 0 && empty($row['supplier_name'])) {
                if (!isset($vendorCache[$supplierPessoaId])) {
                    $vendorCache[$supplierPessoaId] = $this->vendors->find($supplierPessoaId);
                }
                $row['supplier_name'] = $vendorCache[$supplierPessoaId]->fullName ?? ('Fornecedor #' . $supplierPessoaId);
            }
        }
        unset($row);
        $vendorOptions = $this->vendors->all();

        View::render('products/writeoff-form', [
            'errors' => $errors,
            'notices' => $notices,
            'success' => $success,
            'form' => $form,
            'destinationOptions' => $this->service->destinationOptions(),
            'reasonOptions' => $this->service->reasonOptions(),
            'productOptions' => $productOptions,
            'recentWriteOffs' => $recentWriteOffs,
            'termLinks' => $termLinks,
            'initialItem' => $initialWriteoffItem,
            'vendorOptions' => $vendorOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Baixa de produto',
        ]);
    }

    public function consignmentTerm(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('products.writeoff', $this->writeoffs->getPdo());

        $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $writeoff = null;
        if ($token !== '') {
            $writeoff = $this->writeoffs->findByToken($token);
        }
        if (!$writeoff && $id > 0) {
            $writeoff = $this->writeoffs->find($id);
        }

        if (!$writeoff) {
            http_response_code(404);
            echo 'Baixa não encontrada ou sem termo disponível.';
            return;
        }

        $destination = $writeoff['destination'] ?? '';
        $isReturnTerm = $destination === 'devolucao_fornecedor' && !empty($writeoff['supplier_pessoa_id']);
        if (!$isReturnTerm && empty($writeoff['term_token'])) {
            http_response_code(404);
            echo 'Baixa não encontrada ou sem termo disponível.';
            return;
        }

        $vendor = null;
        $product = null;
        $supplierPessoaId = isset($writeoff['supplier_pessoa_id']) ? (int) $writeoff['supplier_pessoa_id'] : 0;
        if ($supplierPessoaId > 0) {
            $vendor = $this->vendors->find($supplierPessoaId);
        }
        $productSku = (int) ($writeoff['product_sku'] ?? 0);
        if ($productSku > 0) {
            $product = $this->products->findAsArray($productSku);
        }

        View::render('products/writeoff-term', [
            'errors' => $errors,
            'writeoff' => $writeoff,
            'vendor' => $vendor,
            'product' => $product,
            'destinationOptions' => $this->service->destinationOptions(),
            'reasonOptions' => $this->service->reasonOptions(),
            'esc' => [Html::class, 'esc'],
            'isReturnTerm' => $isReturnTerm,
        ], [
            'layout' => __DIR__ . '/../Views/print-layout.php',
            'title' => $isReturnTerm ? 'Termo de devolução' : 'Termo de baixa - consignação',
        ]);
    }

    public function supplierTerm(): void
    {
        $errors = [];
        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('products.writeoff', $this->writeoffs->getPdo());

        $post = Input::trimStrings($_POST);
        $supplierPessoaId = isset($post['supplier_pessoa_id']) ? (int) $post['supplier_pessoa_id'] : 0;
        $requestedIds = [];
        if (!empty($post['item_ids']) && is_array($post['item_ids'])) {
            foreach ($post['item_ids'] as $raw) {
                $id = (int) $raw;
                if ($id > 0) {
                    $requestedIds[] = $id;
                }
            }
        }

        if ($supplierPessoaId <= 0) {
            $errors[] = 'Fornecedor inválido para gerar o termo.';
        }
        if (empty($requestedIds)) {
            $errors[] = 'Selecione ao menos uma baixa para incluir no termo.';
        }

        $writeoffs = [];
        if (empty($errors)) {
            $writeoffs = $this->writeoffs->findMany($requestedIds);
            $writeoffs = array_values(array_filter($writeoffs, function ($row) use ($supplierPessoaId) {
                return (int) ($row['supplier_pessoa_id'] ?? 0) === $supplierPessoaId
                    && (($row['destination'] ?? '') === 'devolucao_fornecedor');
            }));
            if (empty($writeoffs)) {
                $errors[] = 'Nenhuma baixa válida encontrada para o fornecedor informado.';
            }
        }

        $products = [];
        if ($writeoffs) {
            foreach ($writeoffs as $writeoff) {
                $productSku = (int) ($writeoff['product_sku'] ?? 0);
                if ($productSku > 0) {
                    $products[$writeoff['id']] = $this->products->findAsArray($productSku);
                }
            }
        }

        $vendor = $supplierPessoaId > 0 ? $this->vendors->find($supplierPessoaId) : null;

        View::render('products/writeoff-vendor-term', [
            'errors' => $errors,
            'writeoffs' => $writeoffs,
            'vendor' => $vendor,
            'products' => $products,
            'destinationOptions' => $this->service->destinationOptions(),
            'reasonOptions' => $this->service->reasonOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'layout' => __DIR__ . '/../Views/print-layout.php',
            'title' => 'Termo de devolução por fornecedor',
        ]);
    }

    public function supplierReturns(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $response = ['rows' => [], 'errors' => []];

        if ($this->connectionError) {
            $response['errors'][] = 'Erro ao conectar ao banco: ' . $this->connectionError;
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }

        Auth::requirePermission('products.writeoff', $this->writeoffs->getPdo());

        $query = Input::trimStrings($_GET);
        $supplierPessoaId = isset($query['supplier_pessoa_id']) ? (int) $query['supplier_pessoa_id'] : 0;
        $limit = isset($query['limit']) ? max(0, (int) $query['limit']) : 0;

        if ($supplierPessoaId <= 0) {
            $response['errors'][] = 'Fornecedor inválido.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }

        $response['rows'] = $this->writeoffs->listBySupplier($supplierPessoaId, $limit);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function isConsignment(?array $supply): bool
    {
        if (!$supply) {
            return false;
        }
        if (!empty($supply['percentual_consignacao'])) {
            return true;
        }
        $source = strtolower((string) ($supply['source'] ?? ''));
        return $source !== '' && str_contains($source, 'consig');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, mixed>
     */
    private function extractPostedItems(array $data): array
    {
        $items = [];
        if (!empty($data['items']) && \is_array($data['items'])) {
            foreach ($data['items'] as $raw) {
                if (!\is_array($raw)) {
                    continue;
                }
                $items[] = $raw;
            }
        }
        if (empty($items) && (!empty($data['product_sku']) || !empty($data['sku']))) {
            $items[] = [
                'product_sku' => $data['product_sku'] ?? null,
                'sku' => $data['sku'] ?? '',
                'destination' => $data['destination'] ?? null,
                'reason' => $data['reason'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'variation_id' => $data['variation_id'] ?? null,
            ];
        }
        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function buildWriteOffQueue(array $items): array
    {
        $queue = [];
        $errors = [];
        foreach ($items as $index => $item) {
            $product = $this->findProductForWriteOff($item);
            if (!$product) {
                $errors[] = 'Item #' . ($index + 1) . ': produto não encontrado.';
                continue;
            }
            $productId = (int) ($product['ID'] ?? 0);
            if ($productId <= 0) {
                $errors[] = 'Item #' . ($index + 1) . ': produto inválido.';
                continue;
            }
            $stockRaw = $product['quantity'] ?? null;
            if ($stockRaw === null || $stockRaw === '') {
                $errors[] = 'Item #' . ($index + 1) . ': produto sem controle de disponibilidade.';
                continue;
            }
            $currentStock = (int) $stockRaw;
            $quantity = (int) ($item['quantity'] ?? 0);
            $newStock = $currentStock - $quantity;
            $supply = $this->supplies->findByProductId($productId);
            $supplierPessoaId = $supply && !empty($supply['supplier_pessoa_id']) ? (int) $supply['supplier_pessoa_id'] : null;
            $isConsignment = $this->isConsignment($supply);
            $queue[] = [
                'product_sku' => $productId,
                'sku' => trim((string) ($product['sku'] ?? $item['sku'] ?? '')),
                'quantity' => $quantity,
                'destination' => $item['destination'],
                'reason' => $item['reason'],
                'notes' => $item['notes'] ?? null,
                'stock_before' => $currentStock,
                'stock_after' => $newStock,
                'supplier_pessoa_id' => $supplierPessoaId,
                'source' => $supply['source'] ?? null,
                'term_required' => $isConsignment,
                'product_label' => trim((string) ($product['post_title'] ?? 'Produto')),
            ];
        }
        return [$queue, $errors];
    }

    private function findProductForWriteOff(array $item): ?array
    {
        $productSku = isset($item['product_sku']) ? (int) $item['product_sku'] : 0;
        if ($productSku > 0) {
            $product = $this->products->findAsArray($productSku);
            if ($product) {
                return $product;
            }
        }
        $sku = isset($item['sku']) ? trim((string) $item['sku']) : '';
        if ($sku === '' || !ctype_digit($sku)) {
            return null;
        }
        return $this->products->findAsArray((int) $sku);
    }

    private function loadProductOptionsForWriteOff(): array
    {
        $limit = 200;
        $offset = 0;
        $rows = [];
        $filters = [
            'stock_positive' => true,
            'order_by' => 'name',
            'order_dir' => 'asc',
        ];
        do {
            $batch = $this->products->listProductsForBulk($filters, $limit, $offset);
            if (!$batch) {
                break;
            }
            $rows = array_merge($rows, $batch);
            $offset += $limit;
        } while (count($batch) === $limit);

        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['variations'] = [];
        }
        unset($row);

        return array_column($rows, null, 'ID');
    }

    private function includeProductInOptions(array $options, array $product): array
    {
        $productId = isset($product['ID']) ? (int) $product['ID'] : 0;
        if ($productId <= 0) {
            return $options;
        }
        $existing = $options[$productId] ?? null;
        $options[$productId] = is_array($existing) ? array_merge($product, $existing) : $product;
        $options[$productId]['variations'] = [];
        return $options;
    }

    private function buildInitialWriteoffItem(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return null;
        }
        $query = Input::trimStrings($_GET);
        $productSku = isset($query['product_sku']) ? (int) $query['product_sku'] : 0;
        $sku = trim((string) ($query['sku'] ?? ''));
        if ($productSku <= 0 && $sku === '') {
            return null;
        }
        $quantity = isset($query['quantity']) ? max(1, (int) $query['quantity']) : 1;
        $variationId = isset($query['variation_id']) ? (int) $query['variation_id'] : 0;

        return [
            'product_sku' => $productSku > 0 ? $productSku : null,
            'sku' => $sku,
            'quantity' => $quantity,
            'destination' => $query['destination'] ?? null,
            'reason' => $query['reason'] ?? null,
            'variation_id' => $variationId > 0 ? $variationId : null,
        ];
    }
}
