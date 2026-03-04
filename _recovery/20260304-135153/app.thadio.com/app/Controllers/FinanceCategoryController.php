<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\FinanceCategoryRepository;
use App\Services\FinanceCategoryService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class FinanceCategoryController
{
    private FinanceCategoryRepository $repository;
    private FinanceCategoryService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new FinanceCategoryRepository($pdo);
        $this->service = new FinanceCategoryService();
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
                Auth::requirePermission('finance_categories.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Categoria excluída.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir categoria: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('finance_categories/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'typeOptions' => FinanceCategoryService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Categorias financeiras',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('finance_categories.edit', $this->repository->getPdo());
            $editing = true;
            $category = $this->repository->find((int) $_GET['id']);
            if ($category) {
                $formData = $this->categoryToForm($category);
            } else {
                $errors[] = 'Categoria não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'finance_categories.edit' : 'finance_categories.create', $this->repository->getPdo());
            [$category, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($category->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($category);
                    $success = $editing ? 'Categoria atualizada com sucesso.' : 'Categoria salva com sucesso.';
                    $formData = $this->categoryToForm($category);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome da categoria já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('finance_categories/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'typeOptions' => FinanceCategoryService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar categoria financeira' : 'Nova categoria financeira',
        ]);
    }

    private function categoryToForm($category): array
    {
        return [
            'id' => $category->id ?? '',
            'name' => $category->name ?? '',
            'type' => $category->type ?? 'ambos',
            'description' => $category->description ?? '',
            'status' => $category->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'type' => 'ambos',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
