<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogBrandRepository;
use PDO;

/**
 * Compat layer for legacy catalog brand routes.
 *
 * Canonical flow is handled by BrandController.
 */
class CatalogMarcaController
{
    private BrandController $delegate;
    private CatalogBrandRepository $brandRepo;

    public function __construct(PDO $pdo, ?string $connectionError = null)
    {
        $this->delegate = new BrandController($pdo, $connectionError);
        $this->brandRepo = new CatalogBrandRepository($pdo);
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
        $brand = $this->brandRepo->find($id);
        if (!$brand) {
            $_SESSION['flash_error'] = 'Marca não encontrada.';
            header('Location: marca-list.php');
            exit;
        }

        $current = (string) ($brand['status'] ?? 'inativa');
        $target = $current === 'ativa' ? 'inativa' : 'ativa';
        $this->brandRepo->updateStatus($id, $target);

        $_SESSION['flash_success'] = 'Status atualizado com sucesso.';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'marca-list.php';
        header('Location: ' . $referer);
        exit;
    }
}
