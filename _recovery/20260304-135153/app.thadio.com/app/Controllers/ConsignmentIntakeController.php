<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ConsignmentIntakeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\VendorRepository;
use App\Services\ConsignmentIntakeService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class ConsignmentIntakeController
{
    private ConsignmentIntakeRepository $repository;
    private VendorRepository $vendors;
    private ProductRepository $products;
    private ConsignmentIntakeService $service;
    private ?PDO $pdo;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new ConsignmentIntakeRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->service = new ConsignmentIntakeService();
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
            try {
                Auth::requirePermission('consignments.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Recebimento excluido.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir recebimento: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->listWithTotals();

        View::render('consignments/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Recebimentos de consignação',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $returnErrors = [];
        $returnSuccess = '';
        $editing = false;

        $formData = $this->emptyForm();
        $items = [];
        $returns = [];
        $returnForm = $this->emptyReturnForm();
        $categoryQuantities = [];
        $returnQuantities = [];
        $returnAvailable = [];
        $batchVendorId = null;
        $linkedProducts = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $vendors = $this->vendors->all();
        $categoryOptions = [];
        [$categoryOptions, $categoryErrors] = $this->loadCategories();
        $errors = array_merge($errors, $categoryErrors);

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('consignments.edit', $this->repository->getPdo());
            $editing = true;
            $intake = $this->repository->find((int) $_GET['id']);
            if ($intake) {
                $formData = $this->intakeToForm($intake);
                $items = $this->repository->listItems((int) $intake->id);
                $returns = $this->repository->listReturns((int) $intake->id);
                $categoryQuantities = $this->buildQuantityMapFromItems($items);
                $categoryOptions = $this->mergeMissingCategories($categoryOptions, $items);
            } else {
                $errors[] = 'Recebimento não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'save') {
                $editing = isset($_POST['id']) && $_POST['id'] !== '';
                Auth::requirePermission($editing ? 'consignments.edit' : 'consignments.create', $this->repository->getPdo());

                [$intake, $parsedItems, $validationErrors] = $this->service->validate($_POST);
                $errors = array_merge($errors, $validationErrors);
                $categoryQuantities = $this->buildQuantityMap($_POST['category_id'] ?? [], $_POST['category_qty'] ?? []);
                $items = $parsedItems;

                if (empty($errors)) {
                    try {
                        $this->repository->save($intake, $parsedItems);
                        $success = $editing ? 'Recebimento atualizado com sucesso.' : 'Recebimento salvo com sucesso.';
                        $editing = true;
                        $formData = $this->intakeToForm($intake);
                        $items = $this->repository->listItems((int) $intake->id);
                        $returns = $this->repository->listReturns((int) $intake->id);
                        $categoryQuantities = $this->buildQuantityMapFromItems($items);
                    } catch (PDOException $e) {
                        $errors[] = 'Erro ao salvar recebimento: ' . $e->getMessage();
                    }
                } else {
                    $formData = array_merge($this->emptyForm(), $_POST);
                }
            } elseif ($action === 'add_return') {
                $editing = isset($_POST['id']) && $_POST['id'] !== '';
                $intakeId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                Auth::requirePermission('consignments.edit', $this->repository->getPdo());

                [$returnData, $parsedItems, $validationErrors] = $this->service->validateReturn($_POST);
                $returnErrors = array_merge($returnErrors, $validationErrors);
                $returnQuantities = $this->buildQuantityMap($_POST['return_category_id'] ?? [], $_POST['return_category_qty'] ?? []);
                $returnForm = array_merge($this->emptyReturnForm(), [
                    'return_date' => (string) ($_POST['return_date'] ?? $this->emptyReturnForm()['return_date']),
                    'return_notes' => (string) ($_POST['return_notes'] ?? ''),
                ]);

                if ($intakeId <= 0) {
                    $returnErrors[] = 'Recebimento inválido para devolução.';
                }

                $intake = $intakeId > 0 ? $this->repository->find($intakeId) : null;
                if ($intake) {
                    $formData = $this->intakeToForm($intake);
                    if (!isset($returnData['pessoa_id'])) {
                        $returnData['pessoa_id'] = $intake->personId ?? null;
                    }
                }

                if (empty($returnErrors) && $intakeId > 0) {
                    $receivedTotals = $this->repository->getItemTotalsByCategory($intakeId);
                    $returnedTotals = $this->repository->getReturnTotalsByCategory($intakeId);

                    foreach ($parsedItems as $item) {
                        $categoryId = $item['category_id'];
                        $receivedQty = $receivedTotals[$categoryId] ?? 0;
                        $returnedQty = $returnedTotals[$categoryId] ?? 0;
                        $available = $receivedQty - $returnedQty;
                        $categoryLabel = $categoryOptions[$categoryId] ?? ('Categoria #' . $categoryId);

                        if ($receivedQty <= 0) {
                            $returnErrors[] = 'Categoria não existe no recebimento: ' . $categoryLabel . '.';
                            continue;
                        }
                        if ($item['quantity'] > $available) {
                            $returnErrors[] = 'Quantidade para ' . $categoryLabel . ' excede saldo disponível.';
                        }
                    }
                }

                if (empty($returnErrors) && $intakeId > 0) {
                    try {
                        $this->repository->addReturn($intakeId, $returnData, $parsedItems);
                        $returnSuccess = 'Devolução registrada com sucesso.';
                        $returnForm = $this->emptyReturnForm();
                        $returnQuantities = [];
                    } catch (PDOException $e) {
                        $returnErrors[] = 'Erro ao registrar devolução: ' . $e->getMessage();
                    }
                }

                if ($intakeId > 0) {
                    $items = $this->repository->listItems($intakeId);
                    $returns = $this->repository->listReturns($intakeId);
                    $categoryQuantities = $this->buildQuantityMapFromItems($items);
                    $categoryOptions = $this->mergeMissingCategories($categoryOptions, $items);
                }
            }
        }

        if ($editing && isset($formData['id']) && $formData['id'] !== '') {
            $intakeId = (int) $formData['id'];
            $items = $items ?: $this->repository->listItems($intakeId);
            $returns = $returns ?: $this->repository->listReturns($intakeId);
            if (empty($categoryQuantities)) {
                $categoryQuantities = $this->buildQuantityMapFromItems($items);
            }
            $categoryOptions = $this->mergeMissingCategories($categoryOptions, $items);
            $batchVendorId = $this->resolveVendorCode($vendors, (int) ($formData['pessoa_id'] ?? 0));
            $linkedProducts = $this->loadLinkedProducts($intakeId);
        }

        if (!empty($items)) {
            $categoryOptions = $this->mergeMissingCategories($categoryOptions, $items);
        }

        if ($editing && isset($formData['id']) && $formData['id'] !== '') {
            $returnAvailable = $this->buildReturnAvailable((int) $formData['id'], $categoryOptions);
        }

        $totals = $this->calculateTotals($items, $returns);

        View::render('consignments/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'items' => $items,
            'vendors' => $vendors,
            'categoryOptions' => $categoryOptions,
            'returns' => $returns,
            'returnForm' => $returnForm,
            'returnErrors' => $returnErrors,
            'returnSuccess' => $returnSuccess,
            'categoryQuantities' => $categoryQuantities,
            'returnQuantities' => $returnQuantities,
            'returnAvailable' => $returnAvailable,
            'batchVendorId' => $batchVendorId,
            'linkedProducts' => $linkedProducts,
            'totals' => $totals,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar recebimento' : 'Novo recebimento',
        ]);
    }

    public function receiptTerm(): void
    {
        $errors = [];
        $intakeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('consignments.view', $this->repository->getPdo());

        $intake = $intakeId > 0 ? $this->repository->findWithVendor($intakeId) : null;
        if (!$intake) {
            $errors[] = 'Recebimento não encontrado.';
        }

        $items = $intake ? $this->repository->listItems($intakeId) : [];
        $returns = $intake ? $this->repository->listReturns($intakeId) : [];
        $returnedTotals = $intake ? $this->repository->getReturnTotalsByCategory($intakeId) : [];

        [$categoryOptions, $categoryErrors] = $this->loadCategories();
        $errors = array_merge($errors, $categoryErrors);
        $categoryOptions = $this->mergeMissingCategories($categoryOptions, $items);

        $rows = [];
        $totalReceived = 0;
        $totalReturned = 0;

        foreach ($items as $item) {
            $categoryId = (int) ($item['category_id'] ?? 0);
            $receivedQty = (int) ($item['quantity'] ?? 0);
            $returnedQty = (int) ($returnedTotals[$categoryId] ?? 0);
            $rows[] = [
                'category' => $categoryOptions[$categoryId] ?? ('Categoria #' . $categoryId),
                'received' => $receivedQty,
                'returned' => $returnedQty,
                'remaining' => max(0, $receivedQty - $returnedQty),
            ];
            $totalReceived += $receivedQty;
            $totalReturned += $returnedQty;
        }

        $totals = [
            'received' => $totalReceived,
            'returned' => $totalReturned,
            'remaining' => max(0, $totalReceived - $totalReturned),
        ];

        View::render('consignments/receipt_print', [
            'errors' => $errors,
            'intake' => $intake,
            'rows' => $rows,
            'totals' => $totals,
            'returns' => $returns,
            'esc' => [Html::class, 'esc'],
        ], [
            'layout' => __DIR__ . '/../Views/print-layout.php',
            'title' => 'Termo de recebimento',
        ]);
    }

    public function returnTerm(): void
    {
        $errors = [];
        $returnId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('consignments.view', $this->repository->getPdo());

        $return = $returnId > 0 ? $this->repository->findReturnWithIntake($returnId) : null;
        if (!$return) {
            $errors[] = 'Devolução não encontrada.';
        }

        $items = $return ? $this->repository->listReturnItems($returnId) : [];
        $receiptItems = $return ? $this->repository->listItems((int) $return['intake_id']) : [];
        $receivedTotals = $return ? $this->repository->getItemTotalsByCategory((int) $return['intake_id']) : [];
        $returnedTotals = $return ? $this->repository->getReturnTotalsByCategory((int) $return['intake_id']) : [];

        [$categoryOptions, $categoryErrors] = $this->loadCategories();
        $errors = array_merge($errors, $categoryErrors);
        $categoryOptions = $this->mergeMissingCategories($categoryOptions, $receiptItems ?: $items);

        $rows = [];
        $totalReturned = 0;

        foreach ($items as $item) {
            $categoryId = (int) ($item['category_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $rows[] = [
                'category' => $categoryOptions[$categoryId] ?? ('Categoria #' . $categoryId),
                'quantity' => $qty,
            ];
            $totalReturned += $qty;
        }

        $consolidatedRows = [];
        $totalReceived = 0;
        $totalReturnedAll = 0;

        foreach ($receivedTotals as $categoryId => $receivedQty) {
            $returnedQty = $returnedTotals[$categoryId] ?? 0;
            $consolidatedRows[] = [
                'category' => $categoryOptions[$categoryId] ?? ('Categoria #' . $categoryId),
                'received' => $receivedQty,
                'returned' => $returnedQty,
                'remaining' => max(0, $receivedQty - $returnedQty),
            ];
            $totalReceived += $receivedQty;
            $totalReturnedAll += $returnedQty;
        }

        $consolidatedTotals = [
            'received' => $totalReceived,
            'returned' => $totalReturnedAll,
            'remaining' => max(0, $totalReceived - $totalReturnedAll),
        ];

        View::render('consignments/return_print', [
            'errors' => $errors,
            'return' => $return,
            'rows' => $rows,
            'totalReturned' => $totalReturned,
            'consolidatedRows' => $consolidatedRows,
            'consolidatedTotals' => $consolidatedTotals,
            'esc' => [Html::class, 'esc'],
        ], [
            'layout' => __DIR__ . '/../Views/print-layout.php',
            'title' => 'Termo de devolução',
        ]);
    }

    private function intakeToForm($intake): array
    {
        return [
            'id' => $intake->id ?? '',
            'pessoa_id' => $intake->personId ?? 0,
            'received_at' => $intake->receivedAt ?? date('Y-m-d'),
            'notes' => $intake->notes ?? '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'pessoa_id' => '',
            'received_at' => date('Y-m-d'),
            'notes' => '',
        ];
    }

    private function emptyReturnForm(): array
    {
        return [
            'return_date' => date('Y-m-d'),
            'return_notes' => '',
        ];
    }

    /**
     * @param array<int, mixed> $categoryIds
     * @param array<int, mixed> $quantities
     * @return array<int, array{category_id: int, quantity: string}>
     */
    private function buildItemDrafts(array $categoryIds, array $quantities): array
    {
        $rows = [];
        $count = max(count($categoryIds), count($quantities));

        for ($i = 0; $i < $count; $i++) {
            $rawCategory = $categoryIds[$i] ?? '';
            $rawQty = $quantities[$i] ?? '';

            if ($rawCategory === '' && $rawQty === '') {
                continue;
            }

            $rows[] = [
                'category_id' => (int) $rawCategory,
                'quantity' => $rawQty === '' ? '' : (string) $rawQty,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $categoryIds
     * @param array<int, mixed> $quantities
     * @return array<int, string>
     */
    private function buildQuantityMap(array $categoryIds, array $quantities): array
    {
        $map = [];
        $count = max(count($categoryIds), count($quantities));

        for ($i = 0; $i < $count; $i++) {
            $categoryId = (int) ($categoryIds[$i] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $rawQty = $quantities[$i] ?? '';
            $map[$categoryId] = $rawQty === '' ? '' : (string) $rawQty;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function buildQuantityMapFromItems(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $categoryId = (int) ($item['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $map[$categoryId] = (string) ($item['quantity'] ?? '');
        }

        return $map;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function loadCategories(): array
    {
        $options = [];
        $errors = [];

        if (!$this->pdo) {
            return [$options, $errors];
        }

        $sources = [
            [
                'name' => 'catalog_categories',
                'sql' => "SELECT id, name
                          FROM catalog_categories
                          WHERE status = 'ativa'
                          ORDER BY position ASC, name ASC",
            ],
            [
                'name' => 'colecoes',
                'sql' => "SELECT id, name FROM colecoes ORDER BY name ASC",
            ],
            [
                'name' => 'product_categories',
                'sql' => "SELECT id, name
                          FROM product_categories
                          WHERE status = 'ativo'
                          ORDER BY name ASC",
            ],
        ];

        foreach ($sources as $source) {
            try {
                $stmt = $this->pdo->query($source['sql']);
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (!$rows) {
                    continue;
                }

                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($id <= 0 || $name === '') {
                        continue;
                    }
                    $options[$id] = $name;
                }

                if (!empty($options)) {
                    break;
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    'Falha ao carregar categorias de %s: %s',
                    $source['name'],
                    $e->getMessage()
                ));
            }
        }

        return [$options, $errors];
    }

    /**
     * @param array<int, string> $options
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function mergeMissingCategories(array $options, array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['category_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        foreach (array_keys($ids) as $id) {
            if (!isset($options[$id])) {
                $options[$id] = 'Categoria #' . $id . ' (removida)';
            }
        }

        return $options;
    }

    /**
     * @param array<int, string> $categoryOptions
     * @return array<int, int>
     */
    private function buildReturnAvailable(int $intakeId, array $categoryOptions): array
    {
        $receivedTotals = $this->repository->getItemTotalsByCategory($intakeId);
        $returnedTotals = $this->repository->getReturnTotalsByCategory($intakeId);

        $available = [];
        foreach ($categoryOptions as $categoryId => $name) {
            $received = $receivedTotals[$categoryId] ?? 0;
            $returned = $returnedTotals[$categoryId] ?? 0;
            $available[$categoryId] = max(0, $received - $returned);
        }

        return $available;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLinkedProducts(int $intakeId): array
    {
        $links = $this->repository->listLinkedProducts($intakeId);
        if (empty($links)) {
            return [];
        }

        $ids = array_map(static function (array $row): int {
            return (int) ($row['product_id'] ?? 0);
        }, $links);
        $ids = array_values(array_filter($ids));
        if (empty($ids)) {
            return [];
        }

        $rows = $this->products->findByIds($ids);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['ID'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }

        $result = [];
        foreach ($links as $link) {
            $id = (int) ($link['product_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $product = $map[$id] ?? null;
            $result[] = [
                'product_id' => $id,
                'created_at' => $link['created_at'] ?? null,
                'sku' => $product['sku'] ?? '',
                'name' => $product['post_title'] ?? ('Produto #' . $id),
                'status' => $product['status'] ?? '',
                'price' => $product['regular_price'] ?? $product['price'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $returns
     * @return array{received: int, returned: int, remaining: int}
     */
    private function calculateTotals(array $items, array $returns): array
    {
        $received = 0;
        foreach ($items as $item) {
            $received += (int) ($item['quantity'] ?? 0);
        }

        $returned = 0;
        foreach ($returns as $return) {
            $returned += (int) ($return['total_returned'] ?? 0);
        }

        return [
            'received' => $received,
            'returned' => $returned,
            'remaining' => max(0, $received - $returned),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $vendors
     */
    private function resolveVendorCode(array $vendors, int $vendorId): ?int
    {
        if ($vendorId <= 0) {
            return null;
        }

        foreach ($vendors as $vendor) {
            if ((int) ($vendor['id'] ?? 0) === $vendorId) {
                $code = (int) ($vendor['id_vendor'] ?? 0);
                return $code > 0 ? $code : null;
            }
        }

        return null;
    }
}
