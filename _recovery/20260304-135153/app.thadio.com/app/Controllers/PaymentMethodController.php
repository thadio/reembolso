<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\PaymentMethodRepository;
use App\Services\PaymentMethodService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class PaymentMethodController
{
    private PaymentMethodRepository $repository;
    private PaymentMethodService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new PaymentMethodRepository($pdo);
        $this->service = new PaymentMethodService();
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
                Auth::requirePermission('payment_methods.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Método de pagamento excluído.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir método: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('payment_methods/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'typeOptions' => PaymentMethodService::typeOptions(),
            'feeTypeOptions' => PaymentMethodService::feeTypeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Metodos de pagamento',
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
            Auth::requirePermission('payment_methods.edit', $this->repository->getPdo());
            $editing = true;
            $method = $this->repository->find((int) $_GET['id']);
            if ($method) {
                $formData = $this->methodToForm($method);
            } else {
                $errors[] = 'Método não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'payment_methods.edit' : 'payment_methods.create', $this->repository->getPdo());
            [$method, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($method->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($method);
                    $success = $editing ? 'Método atualizado com sucesso.' : 'Método salvo com sucesso.';
                    $formData = $this->methodToForm($method);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome do método já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('payment_methods/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'typeOptions' => PaymentMethodService::typeOptions(),
            'feeTypeOptions' => PaymentMethodService::feeTypeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar método de pagamento' : 'Novo método de pagamento',
        ]);
    }

    private function methodToForm($method): array
    {
        return [
            'id' => $method->id ?? '',
            'name' => $method->name ?? '',
            'type' => $method->type ?? 'cash',
            'description' => $method->description ?? '',
            'status' => $method->status ?? 'ativo',
            'fee_type' => $method->feeType ?? 'none',
            'fee_value' => isset($method->feeValue) ? number_format((float) $method->feeValue, 2, '.', '') : '0.00',
            'requires_bank_account' => !empty($method->requiresBankAccount) ? '1' : '',
            'requires_terminal' => !empty($method->requiresTerminal) ? '1' : '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'type' => 'cash',
            'description' => '',
            'status' => 'ativo',
            'fee_type' => 'none',
            'fee_value' => '0.00',
            'requires_bank_account' => '',
            'requires_terminal' => '',
        ];
    }
}
