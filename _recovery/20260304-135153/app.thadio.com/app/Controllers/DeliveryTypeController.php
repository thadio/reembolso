<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\DeliveryTypeRepository;
use App\Services\DeliveryTypeService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class DeliveryTypeController
{
    private DeliveryTypeRepository $repository;
    private DeliveryTypeService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new DeliveryTypeRepository($pdo);
        $this->service = new DeliveryTypeService();
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
                Auth::requirePermission('delivery_types.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Tipo de entrega excluido.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir tipo de entrega: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('delivery_types/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'availabilityOptions' => DeliveryTypeService::availabilityOptions(),
            'bagActionOptions' => DeliveryTypeService::bagActionOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Tipos de entrega',
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
            Auth::requirePermission('delivery_types.edit', $this->repository->getPdo());
            $editing = true;
            $type = $this->repository->find((int) $_GET['id']);
            if ($type) {
                $formData = $this->typeToForm($type);
            } else {
                $errors[] = 'Tipo de entrega não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'delivery_types.edit' : 'delivery_types.create', $this->repository->getPdo());
            [$type, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($type->id ?? false);
            if (empty($errors)) {
                try {
                    $this->repository->save($type);
                    $success = $editing ? 'Tipo de entrega atualizado com sucesso.' : 'Tipo de entrega salvo com sucesso.';
                    $formData = $this->typeToForm($type);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome do tipo já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('delivery_types/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'availabilityOptions' => DeliveryTypeService::availabilityOptions(),
            'bagActionOptions' => DeliveryTypeService::bagActionOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar tipo de entrega' : 'Novo tipo de entrega',
        ]);
    }

    private function typeToForm($type): array
    {
        return [
            'id' => $type->id ?? '',
            'name' => $type->name ?? '',
            'description' => $type->description ?? '',
            'status' => $type->status ?? 'ativo',
            'base_price' => isset($type->basePrice) ? number_format((float) $type->basePrice, 2, '.', '') : '0.00',
            'south_price' => $type->southPrice !== null ? number_format((float) $type->southPrice, 2, '.', '') : '',
            'availability' => $type->availability ?? 'all',
            'bag_action' => $type->bagAction ?? 'none',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'description' => '',
            'status' => 'ativo',
            'base_price' => '0.00',
            'south_price' => '',
            'availability' => 'all',
            'bag_action' => 'none',
        ];
    }
}
