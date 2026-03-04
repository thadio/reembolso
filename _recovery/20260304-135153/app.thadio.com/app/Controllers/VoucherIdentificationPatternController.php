<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\VoucherIdentificationPatternRepository;
use App\Services\VoucherIdentificationPatternService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class VoucherIdentificationPatternController
{
    private VoucherIdentificationPatternRepository $repository;
    private VoucherIdentificationPatternService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new VoucherIdentificationPatternRepository($pdo);
        $this->service = new VoucherIdentificationPatternService();
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
                Auth::requirePermission('voucher_identification_patterns.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Padrão de identificação excluído.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir padrão: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('voucher_identification_patterns/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Padrões de identificação de cupom/crédito',
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
            Auth::requirePermission('voucher_identification_patterns.edit', $this->repository->getPdo());
            $editing = true;
            $pattern = $this->repository->find((int) $_GET['id']);
            if ($pattern) {
                $formData = $this->patternToForm($pattern);
            } else {
                $errors[] = 'Padrão não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'voucher_identification_patterns.edit' : 'voucher_identification_patterns.create', $this->repository->getPdo());
            [$pattern, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($pattern->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($pattern);
                    $success = $editing ? 'Padrão atualizado com sucesso.' : 'Padrão salvo com sucesso.';
                    $formData = $this->patternToForm($pattern);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Identificação já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('voucher_identification_patterns/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar padrão de identificação' : 'Novo padrão de identificação',
        ]);
    }

    private function patternToForm($pattern): array
    {
        return [
            'id' => $pattern->id ?? '',
            'label' => $pattern->label ?? '',
            'description' => $pattern->description ?? '',
            'status' => $pattern->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'label' => '',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
