<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ConsignmentRepository;
use App\Repositories\ConsignmentItemRepository;
use App\Services\ConsignmentService;

use PDO;
use Exception;

/**
 * ConsignmentController
 * 
 * Controller para gestão de consignações.
 */
class ConsignmentController
{
    private PDO $pdo;
    private ConsignmentRepository $repository;
    private ConsignmentItemRepository $itemRepository;
    private ConsignmentService $service;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->repository = new ConsignmentRepository($pdo);
        $this->itemRepository = new ConsignmentItemRepository($pdo);
        $this->service = new ConsignmentService($pdo);
    }

    /**
     * Exibe listagem de consignações com filtros, paginação e ordenação
     */
    public function index(): void
    {
        $errors = [];
        $success = '';
        if (isset($_SESSION['flash_success'])) {
            $success = trim((string) $_SESSION['flash_success']);
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $flashError = trim((string) $_SESSION['flash_error']);
            if ($flashError !== '') {
                $errors[] = $flashError;
            }
            unset($_SESSION['flash_error']);
        }

        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $supplierFilter = isset($_GET['supplier_pessoa_id']) ? trim((string) $_GET['supplier_pessoa_id']) : '';
        $dateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
        $dateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';

        $searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($searchQuery === '') {
            $searchQuery = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $rawLimit = isset($_GET['per_page']) ? (int) $_GET['per_page'] : (isset($_GET['limit']) ? (int) $_GET['limit'] : 50);
        $perPageOptions = [25, 50, 100, 200];
        $limit = ($rawLimit >= 1 && $rawLimit <= 500) ? $rawLimit : 50;

        $sortKey = isset($_GET['sort_key']) ? trim((string) $_GET['sort_key']) : '';
        if ($sortKey === '') {
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'received_at';
        }
        $sortDir = isset($_GET['sort_dir']) ? strtolower(trim((string) $_GET['sort_dir'])) : '';
        if ($sortDir === '') {
            $sortDir = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'desc';
        }
        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

        $columnFilters = [];
        foreach (['id', 'received_at', 'supplier_name', 'status', 'items_count', 'total_value', 'notes'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            $value = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';
            if ($value !== '') {
                $columnFilters[$param] = $value;
            }
        }

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($supplierFilter !== '') {
            $filters['supplier_pessoa_id'] = $supplierFilter;
        }
        if ($dateFrom !== '') {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $filters['date_to'] = $dateTo;
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        foreach ($columnFilters as $key => $value) {
            $filters[$key] = $value;
        }

        $total = $this->repository->count($filters);
        $totalPages = max(1, (int) ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        $consignments = $this->repository->list($filters, $limit, $offset, $sortKey, $sortDir);

        // Dados para dropdowns de filtro
        $statuses = [
            'aberta' => 'Aberta',
            'fechada' => 'Fechada',
            'pendente' => 'Pendente',
            'liquidada' => 'Liquidada',
        ];

        // Buscar fornecedores para dropdown
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.id, p.full_name AS nome, p.full_name
            FROM pessoas p
            INNER JOIN pessoas_papeis pp ON p.id = pp.pessoa_id
            WHERE pp.role = 'fornecedor'
            ORDER BY p.full_name
        ");
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('consignments/products-list', [
            'consignments' => $consignments,
            'errors' => $errors,
            'success' => $success,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $limit,
            'perPageOptions' => $perPageOptions,
            'filters' => $filters,
            'statusFilter' => $statusFilter,
            'supplierFilter' => $supplierFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'statuses' => $statuses,
            'suppliers' => $suppliers,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
        ], [
            'title' => 'Consignações',
        ]);
    }

    /**
     * Exibe formulário de criação
     */
    public function create(): void
    {
        $this->renderForm();
    }

    /**
     * Processa criação de nova consignação
     */
    public function store(): void
    {
        try {
            $this->pdo->beginTransaction();

            // Validar dados
            $validated = $this->service->validate($_POST);

            // Separar items do resto dos dados
            $items = $validated['items'] ?? [];
            unset($validated['items']);

            // Salvar consignação
            $consignmentId = $this->repository->save($validated);

            // Salvar itens (se houver)
            if (!empty($items)) {
                foreach ($items as $item) {
                    $item['consignment_id'] = $consignmentId;
                    $this->itemRepository->save($item);
                }
            }

            $this->pdo->commit();

            $_SESSION['flash_success'] = "Consignação #{$consignmentId} criada com sucesso!";
            header('Location: /consignacao-produto-list.php');
            exit;

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header('Location: /consignacao-produto-cadastro.php');
            exit;
        }
    }

    /**
     * Exibe detalhes de uma consignação
     */
    public function show(int $id): void
    {
        $consignment = $this->repository->find($id);

        if (!$consignment) {
            $_SESSION['flash_error'] = "Consignação não encontrada.";
            header('Location: /consignacao-produto-list.php');
            exit;
        }

        // Buscar itens da consignação
        $items = $this->repository->listItems($id);

        View::render('consignments/show', [
            'consignment' => $consignment,
            'items' => $items,
        ], [
            'title' => 'Consignação #' . str_pad((string) $consignment['id'], 5, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Exibe formulário de edição
     */
    public function edit(int $id): void
    {
        $consignment = $this->repository->find($id);

        if (!$consignment) {
            $_SESSION['flash_error'] = "Consignação não encontrada.";
            header('Location: /consignacao-produto-list.php');
            exit;
        }

        // Verificar se pode editar
        try {
            $this->service->canEdit($id);
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /consignacao-produto-list.php');
            exit;
        }

        // Buscar itens
        $consignment['items'] = $this->repository->listItems($id);

        $this->renderForm($consignment);
    }

    /**
     * Processa atualização de consignação
     */
    public function update(int $id): void
    {
        try {
            // Verificar se existe
            $existing = $this->repository->find($id);
            if (!$existing) {
                throw new Exception("Consignação não encontrada.");
            }

            // Verificar se pode editar
            $this->service->canEdit($id);

            $this->pdo->beginTransaction();

            // Validar dados
            $_POST['id'] = $id;
            $validated = $this->service->validate($_POST);

            // Separar items
            $items = $validated['items'] ?? [];
            unset($validated['items']);

            // Salvar consignação
            $this->repository->save($validated);

            // Atualizar itens: deletar existentes e recriar
            if (isset($items)) {
                // Deletar itens existentes
                $stmt = $this->pdo->prepare("DELETE FROM consignment_items WHERE consignment_id = :id");
                $stmt->execute([':id' => $id]);

                // Inserir novos itens
                foreach ($items as $item) {
                    $item['consignment_id'] = $id;
                    $this->itemRepository->save($item);
                }
            }

            $this->pdo->commit();

            $_SESSION['flash_success'] = "Consignação atualizada com sucesso!";
            header('Location: /consignacao-produto-list.php');
            exit;

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $_SESSION['old_input'] = $_POST;
            header("Location: /consignacao-produto-cadastro.php?id={$id}");
            exit;
        }
    }

    /**
     * Exclui uma consignação
     */
    public function destroy(int $id): void
    {
        try {
            $existing = $this->repository->find($id);
            if (!$existing) {
                throw new Exception("Consignação não encontrada.");
            }

            // Verificar se pode excluir (mesma regra de edição)
            $this->service->canEdit($id);

            $this->pdo->beginTransaction();

            // Deletar itens
            $stmt = $this->pdo->prepare("DELETE FROM consignment_items WHERE consignment_id = :id");
            $stmt->execute([':id' => $id]);

            // Deletar consignação
            $stmt = $this->pdo->prepare("DELETE FROM consignments WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();

            $_SESSION['flash_success'] = "Consignação excluída com sucesso.";

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /consignacao-produto-list.php');
        exit;
    }

    /**
     * Fecha uma consignação
     */
    public function close(int $id): void
    {
        try {
            $existing = $this->repository->find($id);
            if (!$existing) {
                throw new Exception("Consignação não encontrada.");
            }

            // Verificar se pode fechar
            $this->service->canClose($id);

            // Fechar
            $this->repository->close($id);

            $_SESSION['flash_success'] = "Consignação #{$id} fechada com sucesso!";

        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /consignacao-produto-list.php');
        exit;
    }

    /**
     * Renderiza formulário (create ou edit)
     */
    private function renderForm(?array $consignment = null): void
    {
        // Recuperar input antigo em caso de erro
        $oldInput = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);

        // Combinar consignment existente com input antigo (input antigo tem precedência)
        $data = array_merge($consignment ?? [], $oldInput);

        // Dados para dropdowns
        $statuses = [
            'aberta' => 'Aberta',
            'fechada' => 'Fechada',
            'pendente' => 'Pendente',
            'liquidada' => 'Liquidada',
        ];

        // Buscar fornecedores
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.id, p.full_name AS nome, p.full_name
            FROM pessoas p
            INNER JOIN pessoas_papeis pp ON p.id = pp.pessoa_id
            WHERE pp.role = 'fornecedor'
            ORDER BY p.full_name
        ");
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar produtos disponíveis (modelo unificado)
        $stmt = $this->pdo->query("
            SELECT 
                p.sku AS id,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.internal_code')),
                    CAST(p.sku AS CHAR)
                ) AS internal_code,
                p.sku,
                p.name AS product_name,
                p.cost AS acquisition_cost
            FROM products p
            WHERE p.status = 'disponivel'
              AND p.quantity > 0
            ORDER BY internal_code
        ");
        $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('consignments/form', [
            'consignment' => $data,
            'isEdit' => isset($consignment['id']),
            'statuses' => $statuses,
            'suppliers' => $suppliers,
            'inventoryItems' => $inventoryItems,
        ]);
    }
}
