<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogCategoryRepository;
use PDO;

/**
 * Compat layer for legacy catalog category routes.
 *
 * Canonical flow is handled by CollectionController.
 */
class CatalogCategoriaController
{
    private CollectionController $delegate;
    private CatalogCategoryRepository $categoryRepo;

    public function __construct(PDO $pdo, ?string $connectionError = null)
    {
        $this->delegate = new CollectionController($pdo, $connectionError);
        $this->categoryRepo = new CatalogCategoryRepository($pdo);
    }

    public function index(array $filters = []): void
    {
        $this->delegate->index();
    }

    public function form(?int $id = null): void
    {
        if ($id !== null && $id > 0) {
            $_GET['id'] = (string) $id;
        }
        $this->delegate->form();
    }

    public function store(array $data): void
    {
        $_POST = $data;
        $this->delegate->storeV2();
    }

    public function update(int $id, array $data): void
    {
        $_POST = $data;
        $this->delegate->update($id);
    }

    public function delete(int $id): void
    {
        $this->delegate->destroy($id);
    }

    public function toggleStatus(int $id): void
    {
        $category = $this->categoryRepo->find($id);
        if (!$category) {
            $_SESSION['flash_error'] = 'Categoria não encontrada.';
            header('Location: colecao-list.php');
            exit;
        }

        $current = (string) ($category['status'] ?? 'inativa');
        $target = $current === 'ativa' ? 'inativa' : 'ativa';
        $this->categoryRepo->updateStatus($id, $target);

        $_SESSION['flash_success'] = 'Status atualizado com sucesso.';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'colecao-list.php';
        header('Location: ' . $referer);
        exit;
    }
}
