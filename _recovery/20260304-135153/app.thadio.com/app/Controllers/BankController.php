<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BankRepository;
use App\Services\BankService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class BankController
{
    private BankRepository $repository;
    private BankService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new BankRepository($pdo);
        $this->service = new BankService();
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
                Auth::requirePermission('banks.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Banco excluido.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir banco: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('banks/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Bancos',
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
            Auth::requirePermission('banks.edit', $this->repository->getPdo());
            $editing = true;
            $bank = $this->repository->find((int) $_GET['id']);
            if ($bank) {
                $formData = $this->bankToForm($bank);
            } else {
                $errors[] = 'Banco não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'banks.edit' : 'banks.create', $this->repository->getPdo());
            [$bank, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($bank->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($bank);
                    $success = $editing ? 'Banco atualizado com sucesso.' : 'Banco salvo com sucesso.';
                    $formData = $this->bankToForm($bank);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome do banco já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('banks/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar banco' : 'Novo banco',
        ]);
    }

    private function bankToForm($bank): array
    {
        return [
            'id' => $bank->id ?? '',
            'name' => $bank->name ?? '',
            'code' => $bank->code ?? '',
            'description' => $bank->description ?? '',
            'status' => $bank->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'code' => '',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
