<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\BrandService;
use App\Repositories\CatalogBrandRepository;
use App\Support\Auth;
use App\Support\Html;
use App\Support\CatalogLookup;
use PDO;

class BrandController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private CatalogBrandRepository $catalogBrands;
    private BrandService $service;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->catalogBrands = new CatalogBrandRepository($pdo);
        $this->service = new BrandService();
    }

    public function index(): void
    {
        $this->indexV2();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLegacyBrandRows(): array
    {
        if (!$this->pdo) {
            return [];
        }

        try {
            $catalogRows = $this->catalogBrands->list([], 'name', 'ASC');
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marcas para listagem legado: ' . $e->getMessage());
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

    public function quickCreate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => 'Método não permitido.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        Auth::requirePermission('brands.create', $this->pdo);

        $rows = $this->loadLegacyBrandRows();

        [$payload, $validationErrors, $resolvedSlug] = $this->readBrandPayload($_POST, 0, $rows);
        if (!empty($validationErrors)) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => implode(' ', $validationErrors),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $brandId = $this->catalogBrands->save($payload);
            $created = $this->catalogBrands->find($brandId);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Erro ao criar marca: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $brandId = (int) ($created['id'] ?? $created['term_id'] ?? 0);
        $brandName = (string) ($created['name'] ?? ($payload['name'] ?? ''));
        $brandSlug = (string) ($created['slug'] ?? ($payload['slug'] ?? $resolvedSlug));

        if ($brandId <= 0) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Marca criada, mas não foi possível localizar o ID.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'brand' => [
                'id' => $brandId,
                'name' => $brandName,
                'slug' => $brandSlug,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: string}
     */
    private function readBrandPayload(array $input, int $editingId, array $rows): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        if ($slug === '' && $name !== '') {
            $slug = $this->slugify($name);
        }

        if ($slug === '' && $name !== '') {
            $errors[] = 'Slug inválido. Ajuste manualmente.';
        } elseif ($slug !== '' && $this->slugExists($slug, $editingId, $rows)) {
            $errors[] = 'Slug já existe. Ajuste manualmente.';
        }

        $payload = [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : null,
            'description' => $description,
        ];
        $payload = array_filter($payload, static fn($value) => $value !== null);

        return [$payload, $errors, $slug];
    }

    private function emptyForm(array $form = []): array
    {
        $defaults = [
            'id' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
        ];

        return array_merge($defaults, $form);
    }

    private function brandToForm(array $brand): array
    {
        return [
            'id' => (string) ($brand['term_id'] ?? $brand['id'] ?? ''),
            'name' => (string) ($brand['name'] ?? ''),
            'slug' => (string) ($brand['slug'] ?? ''),
            'description' => (string) ($brand['description'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function slugExists(string $slug, int $editingId, array $rows): bool
    {
        $needle = strtolower($slug);
        foreach ($rows as $row) {
            $rowSlug = strtolower((string) ($row['slug'] ?? ''));
            if ($rowSlug === '') {
                continue;
            }
            $rowId = (int) ($row['term_id'] ?? 0);
            if ($editingId > 0 && $rowId === $editingId) {
                continue;
            }
            if ($rowSlug === $needle) {
                return true;
            }
        }

        return false;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
            if (is_string($converted)) {
                $value = $converted;
            }
        } elseif (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted)) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return strtolower($value);
    }

    // ========================================================================
    // NEW RESTFUL METHODS - BLOCO 1, Prompt 1.7 ✅
    // ========================================================================

    /**
     * List brands using internal catalog (catalog_brands).
     */
    public function indexV2(): void
    {
        Auth::requirePermission('brands.view', $this->pdo);

        $errors = [];
        $success = '';

        // Handle POST actions (delete/archive)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                $brandId = (int) $_POST['delete_id'];
                if ($brandId > 0) {
                    try {
                        Auth::requirePermission('brands.delete', $this->pdo);
                        // Soft delete: set status='inativa'
                        $this->catalogBrands->updateStatus($brandId, 'inativa');
                        $_SESSION['flash_success'] = 'Marca arquivada com sucesso.';
                        header('Location: marca-list.php');
                        exit;
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao arquivar marca: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Marca inválida para exclusão.';
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
        if (isset($_GET['search']) && trim((string) $_GET['search']) !== '') {
            $filters['search'] = trim((string) $_GET['search']);
        }
        foreach (['id', 'name', 'slug', 'status', 'product_count'] as $columnKey) {
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
            $totalBrands = $this->catalogBrands->count($filters);
            $totalPages = $totalBrands > 0 ? (int) ceil($totalBrands / $perPage) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rows = $this->catalogBrands->list($filters, $sortKey, $sortDir, $perPage, $offset);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar lista de marcas: ' . $e->getMessage());
            $errors[] = 'Erro ao carregar marcas.';
            $rows = [];
            $totalBrands = 0;
            $totalPages = 1;
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

        View::render('brands/list-catalog', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalBrands' => $totalBrands,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'statusOptions' => $statusOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Marcas',
        ]);
    }

    /**
     * Show form for creating a new brand.
     */
    public function create(): void
    {
        Auth::requirePermission('brands.create', $this->pdo);

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

        $statusOptions = CatalogLookup::taxonomyStatuses();

        View::render('brands/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'statusOptions' => $statusOptions,
            'editing' => false,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Nova Marca',
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function storeV2(): void
    {
        Auth::requirePermission('brands.create', $this->pdo);

        [$validated, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header('Location: marca-cadastro.php');
            exit;
        }

        try {
            $brandId = $this->catalogBrands->save($validated);
            $_SESSION['flash_success'] = 'Marca criada com sucesso.';
            header('Location: marca-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao salvar marca: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao salvar marca: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header('Location: marca-cadastro.php');
            exit;
        }
    }

    /**
     * Show brand details.
     */
    public function show(int $id): void
    {
        Auth::requirePermission('brands.view', $this->pdo);

        try {
            $brand = $this->catalogBrands->find($id);
            if (!$brand) {
                header('HTTP/1.1 404 Not Found');
                echo '<h1>404 - Marca não encontrada</h1>';
                exit;
            }

            View::render('brands/show', [
                'brand' => $brand,
                'statusLabel' => CatalogLookup::getTaxonomyStatusLabel($brand['status'] ?? ''),
                'esc' => [Html::class, 'esc'],
            ], [
                'title' => 'Detalhes da Marca',
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao carregar marca: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>Erro ao carregar marca</h1>';
            exit;
        }
    }

    /**
     * Show form for editing a brand.
     */
    public function edit(int $id): void
    {
        Auth::requirePermission('brands.edit', $this->pdo);

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
                $brand = $this->catalogBrands->find($id);
                if (!$brand) {
                    header('HTTP/1.1 404 Not Found');
                    echo '<h1>404 - Marca não encontrada</h1>';
                    exit;
                }
                $formData = $brand;
            } catch (\Throwable $e) {
                error_log('Erro ao carregar marca: ' . $e->getMessage());
                $errors[] = 'Erro ao carregar marca.';
            }
        }

        $statusOptions = CatalogLookup::taxonomyStatuses();

        View::render('brands/form-catalog', [
            'errors' => $errors,
            'formData' => $formData,
            'statusOptions' => $statusOptions,
            'editing' => true,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Editar Marca',
        ]);
    }

    /**
     * Update an existing brand.
     */
    public function update(int $id): void
    {
        Auth::requirePermission('brands.edit', $this->pdo);

        $_POST['id'] = $id;
        [$validated, $errors] = $this->service->validate($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['old_input'] = $_POST;
            header("Location: marca-cadastro.php?id={$id}");
            exit;
        }

        try {
            $this->catalogBrands->save($validated);
            $_SESSION['flash_success'] = 'Marca atualizada com sucesso.';
            header('Location: marca-list.php');
            exit;
        } catch (\Throwable $e) {
            error_log('Erro ao atualizar marca: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao atualizar marca: ' . $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header("Location: marca-cadastro.php?id={$id}");
            exit;
        }
    }

    /**
     * Soft delete (deactivate) a brand.
     */
    public function destroy(int $id): void
    {
        Auth::requirePermission('brands.delete', $this->pdo);

        try {
            // Soft delete: set status='inativa'
            $this->catalogBrands->updateStatus($id, 'inativa');
            $_SESSION['flash_success'] = 'Marca arquivada com sucesso.';
        } catch (\Throwable $e) {
            error_log('Erro ao arquivar marca: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Erro ao arquivar marca: ' . $e->getMessage();
        }

        header('Location: marca-list.php');
        exit;
    }
}
