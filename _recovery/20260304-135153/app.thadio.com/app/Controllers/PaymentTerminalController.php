<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\PaymentTerminalRepository;
use App\Services\PaymentTerminalService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class PaymentTerminalController
{
    private PaymentTerminalRepository $repository;
    private PaymentTerminalService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new PaymentTerminalRepository($pdo);
        $this->service = new PaymentTerminalService();
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
                Auth::requirePermission('payment_terminals.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Maquininha excluida.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir maquininha: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('payment_terminals/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'typeOptions' => PaymentTerminalService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Maquininhas e sistemas',
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
            Auth::requirePermission('payment_terminals.edit', $this->repository->getPdo());
            $editing = true;
            $terminal = $this->repository->find((int) $_GET['id']);
            if ($terminal) {
                $formData = $this->terminalToForm($terminal);
            } else {
                $errors[] = 'Maquininha não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'payment_terminals.edit' : 'payment_terminals.create', $this->repository->getPdo());
            [$terminal, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($terminal->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($terminal);
                    $success = $editing ? 'Maquininha atualizada com sucesso.' : 'Maquininha salva com sucesso.';
                    $formData = $this->terminalToForm($terminal);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('payment_terminals/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'typeOptions' => PaymentTerminalService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar maquininha' : 'Nova maquininha',
        ]);
    }

    private function terminalToForm($terminal): array
    {
        return [
            'id' => $terminal->id ?? '',
            'name' => $terminal->name ?? '',
            'type' => $terminal->type ?? 'both',
            'description' => $terminal->description ?? '',
            'status' => $terminal->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'type' => 'both',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
