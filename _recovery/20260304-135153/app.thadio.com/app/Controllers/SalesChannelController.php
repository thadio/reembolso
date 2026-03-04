<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\SalesChannelRepository;
use App\Services\SalesChannelService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class SalesChannelController
{
    private SalesChannelRepository $repository;
    private SalesChannelService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new SalesChannelRepository($pdo);
        $this->service = new SalesChannelService();
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
                Auth::requirePermission('sales_channels.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Canal excluido.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir canal: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('sales_channels/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Canais de venda',
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
            Auth::requirePermission('sales_channels.edit', $this->repository->getPdo());
            $editing = true;
            $channel = $this->repository->find((int) $_GET['id']);
            if ($channel) {
                $formData = $this->channelToForm($channel);
            } else {
                $errors[] = 'Canal não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'sales_channels.edit' : 'sales_channels.create', $this->repository->getPdo());
            [$channel, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($channel->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($channel);
                    $success = $editing ? 'Canal atualizado com sucesso.' : 'Canal salvo com sucesso.';
                    $formData = $this->channelToForm($channel);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome do canal já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('sales_channels/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar canal de venda' : 'Novo canal de venda',
        ]);
    }

    private function channelToForm($channel): array
    {
        return [
            'id' => $channel->id ?? '',
            'name' => $channel->name ?? '',
            'description' => $channel->description ?? '',
            'status' => $channel->status ?? 'ativo',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'description' => '',
            'status' => 'ativo',
        ];
    }
}
