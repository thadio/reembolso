<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CarrierRepository;
use App\Services\CarrierService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class CarrierController
{
    private CarrierRepository $repository;
    private CarrierService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new CarrierRepository($pdo);
        $this->service = new CarrierService();
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_id'])) {
            try {
                Auth::requirePermission('carriers.delete', $this->repository->getPdo());
                $this->repository->deactivate((int) $_POST['deactivate_id']);
                $success = 'Transportadora desativada.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao desativar transportadora: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('carriers/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'typeOptions' => CarrierService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Transportadoras',
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
            Auth::requirePermission('carriers.edit', $this->repository->getPdo());
            $editing = true;
            $carrier = $this->repository->find((int) $_GET['id']);
            if ($carrier) {
                $formData = $this->carrierToForm($carrier);
            } else {
                $editing = false;
                $errors[] = 'Transportadora não encontrada.';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'carriers.edit' : 'carriers.create', $this->repository->getPdo());
            [$carrier, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($carrier->id ?? false);

            if (empty($errors)) {
                try {
                    $this->repository->save($carrier);
                    $success = $editing
                        ? 'Transportadora atualizada com sucesso.'
                        : 'Transportadora cadastrada com sucesso.';
                    $formData = $this->carrierToForm($carrier);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome já cadastrado. Use um nome único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                    $formData = array_merge($this->emptyForm(), $_POST);
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('carriers/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'typeOptions' => CarrierService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar transportadora' : 'Nova transportadora',
        ]);
    }

    private function carrierToForm($carrier): array
    {
        return [
            'id' => $carrier->id ?? '',
            'name' => $carrier->name ?? '',
            'carrier_type' => $carrier->carrierType ?? 'transportadora',
            'site_url' => $carrier->siteUrl ?? '',
            'tracking_url_template' => $carrier->trackingUrlTemplate ?? '',
            'status' => $carrier->status ?? 'ativo',
            'notes' => $carrier->notes ?? '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'carrier_type' => 'transportadora',
            'site_url' => '',
            'tracking_url_template' => '',
            'status' => 'ativo',
            'notes' => '',
        ];
    }
}
