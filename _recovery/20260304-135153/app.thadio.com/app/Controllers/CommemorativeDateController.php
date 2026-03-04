<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CommemorativeDateRepository;
use App\Services\CommemorativeDateService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class CommemorativeDateController
{
    private CommemorativeDateRepository $repository;
    private CommemorativeDateService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new CommemorativeDateRepository($pdo);
        $this->service = new CommemorativeDateService();
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
                Auth::requirePermission('holidays.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Data comemorativa excluída.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('commemorative_dates/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Datas comemorativas',
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
            Auth::requirePermission('holidays.edit', $this->repository->getPdo());
            $editing = true;
            $item = $this->repository->find((int) $_GET['id']);
            if ($item) {
                $formData = $this->itemToForm($item);
            } else {
                $errors[] = 'Data comemorativa não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'holidays.edit' : 'holidays.create', $this->repository->getPdo());
            [$item, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($item->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($item);
                    $success = $editing ? 'Data comemorativa atualizada.' : 'Data comemorativa salva.';
                    $formData = $this->itemToForm($item);
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('commemorative_dates/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar data comemorativa' : 'Nova data comemorativa',
        ]);
    }

    private function itemToForm($item): array
    {
        return [
            'id' => $item->id ?? '',
            'name' => $item->name ?? '',
            'day' => $item->day ?? '',
            'month' => $item->month ?? '',
            'year' => $item->year ?? '',
            'scope' => $item->scope ?? 'brasil',
            'category' => $item->category ?? '',
            'description' => $item->description ?? '',
            'source' => $item->source ?? '',
            'status' => $item->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'day' => '',
            'month' => '',
            'year' => '',
            'scope' => 'brasil',
            'category' => '',
            'description' => '',
            'source' => '',
            'status' => 'ativo',
        ];
    }
}
