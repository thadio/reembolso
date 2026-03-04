<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\BankAccountRepository;
use App\Repositories\BankRepository;
use App\Services\BankAccountService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class BankAccountController
{
    private BankAccountRepository $repository;
    private BankRepository $banks;
    private BankAccountService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new BankAccountRepository($pdo);
        $this->banks = new BankRepository($pdo);
        $this->service = new BankAccountService();
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
                Auth::requirePermission('bank_accounts.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Conta bancaria excluida.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir conta: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('bank_accounts/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'pixTypeOptions' => BankAccountService::pixKeyTypeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Contas bancarias',
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

        $bankOptions = $this->banks->active();
        if (empty($bankOptions)) {
            $errors[] = 'Cadastre um banco antes de criar contas.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('bank_accounts.edit', $this->repository->getPdo());
            $editing = true;
            $account = $this->repository->find((int) $_GET['id']);
            if ($account) {
                $formData = $this->accountToForm($account);
            } else {
                $errors[] = 'Conta não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'bank_accounts.edit' : 'bank_accounts.create', $this->repository->getPdo());
            [$account, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($account->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($account);
                    $success = $editing ? 'Conta atualizada com sucesso.' : 'Conta salva com sucesso.';
                    $formData = $this->accountToForm($account);
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('bank_accounts/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'bankOptions' => $bankOptions,
            'pixTypeOptions' => BankAccountService::pixKeyTypeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar conta bancaria' : 'Nova conta bancaria',
        ]);
    }

    private function accountToForm($account): array
    {
        return [
            'id' => $account->id ?? '',
            'bank_id' => $account->bankId ?? '',
            'label' => $account->label ?? '',
            'holder' => $account->holder ?? '',
            'branch' => $account->branch ?? '',
            'account_number' => $account->accountNumber ?? '',
            'pix_key' => $account->pixKey ?? '',
            'pix_key_type' => $account->pixKeyType ?? '',
            'description' => $account->description ?? '',
            'status' => $account->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'bank_id' => '',
            'label' => '',
            'holder' => '',
            'branch' => '',
            'account_number' => '',
            'pix_key' => '',
            'pix_key_type' => '',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
