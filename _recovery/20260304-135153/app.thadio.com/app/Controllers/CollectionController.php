<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\CategoryService;
use App\Repositories\CatalogCategoryRepository;
use App\Support\Auth;
use App\Support\Html;
use App\Support\CatalogLookup;
use PDO;

class CollectionController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private CatalogCategoryRepository $catalogCategories;
    private CategoryService $service;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->catalogCategories = new CatalogCategoryRepository($pdo);
        $this->service = new CategoryService();
    }

    public function index(): void
    {
        $this->indexV2();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLegacyCollectionRows(): array
    {
        if (!$this->pdo) {
            return [];
        }

        try {
            $catalogRows = $this->catalogCategories->list([], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar categorias para listagem legado: ' . $e->getMessage());
            return [];
        }

        $rows = [];
        foreach ($catalogRows as $row) {
            $rows[] = [
                'term_id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'count' => (int) ($row['product_count'] ?? 0),
            ];
        }

        return $rows;
    }

    public function form(): void
    {
        $editingId = isset($_GET['id']) ? (int) $_GET['id'] : ((isset($_POST['id']) && $_POST['id'] !== '') ? (int) $_POST['id'] : 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($editingId > 0) {
                $this->update($editingId);
                return;
            }
            $this->storeV2();
            return;
        }

        if ($editingId > 0) {
            $this->edit($editingId);
            return;
        }

        $this->create();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function readCategoryPayload(array $input, int $editingId, array $rows): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $parent = isset($input['parent']) ? (int) $input['parent'] : 0;

        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        if ($editingId > 0 && $parent === $editingId) {
            $errors[] = 'Categoria não pode ser pai dela mesma.';
        }

        if (!empty($rows)) {
            $validIds = [];
            foreach ($rows as $row) {
                if (isset($row['term_id'])) {
                    $validIds[] = (int) $row['term_id'];
                }
            }
            if ($parent > 0 && !in_array($parent, $validIds, true)) {
                $errors[] = 'Categoria pai inválida.';
            }
        }

        $payload = [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : null,
            'description' => $description,
            'parent' => $parent,
        ];
        $payload = array_filter($payload, static fn($value) => $value !== null);

        return [$payload, $errors];
    }

    private function emptyForm(array $form = []): array
    {
        $defaults = [
            'id' => '',
            'name' => '',
            'slug' => '',
            'parent' => '0',
            'description' => '',
        ];

        return array_merge($defaults, $form);
    }

    private function categoryToForm(array $category): array
    {
        return [
            'id' => (string) ($category['term_id'] ?? $category['id'] ?? ''),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'parent' => (string) ($category['parent'] ?? 0),
            'description' => (string) ($category['description'] ?? ''),
        ];
    }

    // ========================================================================
    // NEW RESTFUL METHODS - BLOCO 1, Prompt 1.8 ✅
    // ========================================================================

    /**
     * List categories using internal catalog (catalog_categories).
     */
    public function indexV2(): void
    {
        Auth::requirePermission('collections.view', $this->pdo);

        $errors = [];
        $success = '';

        // Handle POST actions (delete/archive)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                $categoryId = (int) $_POST['delete_id'];
                if ($categoryId > 0) {
                    try {
                        Auth::requirePermission('collections.delete', $this->pdo);
                        // Soft delete: set status='inativa'
                        $this->catalogCategories->updateStatus($categoryId, 'inativa');
                        $_SESSION['flash_success'] = 'Categoria arquivada com sucesso.';
                        header('Location: colecao-list.php');
                        exit;
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao arquivar categoria: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Categoria inválida para exclusão.';
                }
            }
        }

        // Read filters and pagination
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
        $perPageOptions = [25, 50, 100];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 50;
        }

        // Build filters
        $filters = [];
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = trim((string) $_GET['status']);
        }
        if (isset($_GET['parent_id']) && $_GET['parent_id'] !== '') {
            $filters['parent_id'] = (int) $_GET['parent_id'];
        }
        if (isset($_GET['search']) && trim((string) $_GET['search']) !== '') {
            $filters['search'] = trim((string) $_GET['search']);
        }
        foreach (['id', 'name', 'slug', 'parent_name', 'status', 'product_count'] as $columnKey) {
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

        // Sorting
        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : '';
        if ($sortKey === '') {
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'name';
        }
        $sortDirRaw = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : '';
        if ($sortDirRaw === '') {
            $sortDirRaw = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'asc';
        }
        $sortDir = $sortDirRaw === 'desc' ? 'DESC' : 'ASC';

        // Get data
        try {
            $totalCategories = $this->catalogCategories->count($filters);
            $totalPages = $totalCategories > 0 ? (int) ceil($totalCategories / $perPage) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rows = $this->catalogCategories->list($filters, $sortKey, $sortDir, $perPage, $offset);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar lista de categorias: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar categorias.';
            $rows = [];
            $totalCategories = 0;
            $totalPages = 1;
        }

        // Get parent categories for filter
        $parentCategories = [];
        try {
            $parentCategories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar categorias pai: ' . $e->getMessage());
        }

        // Get status options
        $statusOptions = [];
        foreach (CatalogLookup::taxonomyStatuses() as $value => $label) {
            $statusOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        // Flash messages
        if (isset($_SESSION['flash_success'])) {
            $success = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        View::render('collections/list-catalog', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalCategories' => $totalCategories,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'parentCategories' => $parentCategories,
            'statusOptions' => $statusOptions,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Categorias',
        ]);
    }

    /**
     * Show form for creating a new category.
     */
    public function create(): void
    {
        Auth::requirePermission('collections.create', $this->pdo);

        $errors = [];
        $formData = [];

        // Flash messages
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        // Old input
        if (isset($_SESSION['old_input'])) {
            $formData = $_SESSION['old_input'];
            unset($_SESSION['old_input']);
        }

        // Get parent categories for dropdown
        $parentCategories = [];
        try {
            $parentCategories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar categorias: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar categorias pai.';
        }

        $statusOptions = CatalogLookup::taxonomyStatuses();

        View::render('collections/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'categories' => $parentCategories,
            'statusOptions' => $statusOptions,
            'editing' => false,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Nova Categoria',
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function storeV2(): void
    {
        Auth::requirePermission('collections.create', $this->pdo);

        [$validated, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header('Location: colecao-cadastro.php');
            exit;
        }

        try {
            $categoryId = $this->catalogCategories->save($validated);
            $_SESSION['flash_success'] = 'Categoria criada com sucesso.';
            header('Location: colecao-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao salvar categoria: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar categoria: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header('Location: colecao-cadastro.php');
            exit;
        }
    }

    /**
     * Show category details.
     */
    public function show(int $id): void
    {
        Auth::requirePermission('collections.view', $this->pdo);

        try {
            $category = $this->catalogCategories->find($id);
            if (!$category) {
                header('HTTP/1.1 404 Not Found');
                echo '<h1>404 - Categoria não encontrada</h1>';
                exit;
            }

            // Get parent category name if exists
            $parentName = null;
            if (!empty($category['parent_id'])) {
                $parent = $this->catalogCategories->find($category['parent_id']);
                if ($parent) {
                    $parentName = $parent['name'];
                }
            }

            View::render('collections/show', [
                'category' => $category,
                'parentName' => $parentName,
                'statusLabel' => CatalogLookup::getTaxonomyStatusLabel($category['status'] ?? ''),
                'esc' => [Html::class, 'esc'],
            ], [
                'title' => 'Detalhes da Categoria',
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar categoria: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>Erro ao carregar categoria</h1>';
            exit;
        }
    }

    /**
     * Show form for editing a category.
     */
    public function edit(int $id): void
    {
        Auth::requirePermission('collections.edit', $this->pdo);

        $errors = [];
        $formData = [];

        // Flash messages
        if (isset($_SESSION['flash_error'])) {
            $errors[] = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        // Old input (priority)
        if (isset($_SESSION['old_input'])) {
            $formData = $_SESSION['old_input'];
            unset($_SESSION['old_input']);
        } else {
            // Load from database
            try {
                $category = $this->catalogCategories->find($id);
                if (!$category) {
                    header('HTTP/1.1 404 Not Found');
                    echo '<h1>404 - Categoria não encontrada</h1>';
                    exit;
                }
                $formData = $category;
            } catch (\Throwable $e) {
                error_log('Erro ao carregar categoria: ' . $e->getMessage());
                $errors[] = 'Erro ao carregar categoria.';
            }
        }

        // Get parent categories for dropdown (exclude current category)
        $parentCategories = [];
        try {
            $allCategories = $this->catalogCategories->list(['status' => 'ativa'], 'name', 'ASC');
            foreach ($allCategories as $cat) {
                if ((int) $cat['id'] !== $id) {
                    $parentCategories[] = $cat;
                }
            }
        } catch (\Throwable $e) {
            error_log('Erro ao carregar categorias: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar categorias pai.';
        }

        $statusOptions = CatalogLookup::taxonomyStatuses();

        View::render('collections/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'categories' => $parentCategories,
            'statusOptions' => $statusOptions,
            'editing' => true,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Editar Categoria',
        ]);
    }

    /**
     * Update an existing category.
     */
    public function update(int $id): void
    {
        Auth::requirePermission('collections.edit', $this->pdo);

        $_POST['id'] = $id;
        [$validated, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header("Location: colecao-cadastro.php?id={$id}");
            exit;
        }

        try {
            $this->catalogCategories->save($validated);
            $_SESSION['flash_success'] = 'Categoria atualizada com sucesso.';
            header('Location: colecao-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao atualizar categoria: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar categoria: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header("Location: colecao-cadastro.php?id={$id}");
            exit;
        }
    }

    /**
     * Soft delete (deactivate) a category.
     */
    public function destroy(int $id): void
    {
        Auth::requirePermission('collections.delete', $this->pdo);

        try {
            // Soft delete: set status='inativa'
            $this->catalogCategories->updateStatus($id, 'inativa');
            $_SESSION['flash_success'] = 'Categoria arquivada com sucesso.';
        } catch (\Throwable $e) {
            error_log('Erro ao arquivar categoria: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao arquivar categoria: ' . $e->getMessage();
        }

        header('Location: colecao-list.php');
        exit;
    }
}
