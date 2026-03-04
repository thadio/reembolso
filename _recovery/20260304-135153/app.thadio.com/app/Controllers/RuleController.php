<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\RuleRepository;
use App\Repositories\RuleVersionRepository;
use App\Services\RuleService;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class RuleController
{
    private RuleRepository $repository;
    private RuleService $service;
    private ?string $connectionError;
    private RuleVersionRepository $versionRepository;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new RuleRepository($pdo);
        $this->versionRepository = new RuleVersionRepository($pdo);
        $this->service = new RuleService();
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
                Auth::requirePermission('rules.delete', $this->repository->getPdo());
                $this->repository->delete((int) $_POST['delete_id']);
                $success = 'Regra excluida.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir regra: ' . $e->getMessage();
            }
        }

        $rows = $this->repository->all();

        View::render('rules/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Regras',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();
        $formData['observations'] = '';
        $versions = [];
        $selectedVersion = null;
        $viewingVersion = false;
        $observationRequired = false;
        $selectedVersionId = null;

        if (isset($_GET['version_id']) && is_numeric($_GET['version_id'])) {
            $selectedVersionId = (int) $_GET['version_id'];
        }

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('rules.edit', $this->repository->getPdo());
            $editing = true;
            $rule = $this->repository->find((int) $_GET['id']);
            if ($rule) {
                $formData = $this->ruleToForm($rule);
                $versions = $this->versionRepository->allForRule((int) $_GET['id']);
                $observationRequired = count($versions) > 0;
                if ($selectedVersionId) {
                    $selectedVersion = $this->versionRepository->find($selectedVersionId);
                    if (!$selectedVersion || $selectedVersion->ruleId !== $rule->id) {
                        $selectedVersion = null;
                    } else {
                        $viewingVersion = true;
                    }
                }
            } else {
                $errors[] = 'Regra não encontrada.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'rules.edit' : 'rules.create', $this->repository->getPdo());
            $observationValue = trim((string) ($_POST['observations'] ?? ''));
            [$rule, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($rule->id ?? false);

            if ($rule->id) {
                $versions = $this->versionRepository->allForRule($rule->id);
                if (count($versions) > 0) {
                    $observationRequired = true;
                }
            }

            if ($observationRequired && $observationValue === '') {
                $errors[] = 'Descreva brevemente as alterações em relação à versão anterior.';
            }

            if (empty($errors)) {
                try {
                    $this->repository->save($rule);
                    $this->versionRepository->saveVersion($rule, $observationValue !== '' ? $observationValue : null, currentUser());
                    $success = $editing ? 'Regra atualizada com sucesso.' : 'Regra salva com sucesso.';
                    $formData = $this->ruleToForm($rule);
                    $formData['observations'] = '';
                    $versions = $this->versionRepository->allForRule($rule->id);
                    $observationRequired = count($versions) > 0;
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Título da regra já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                    $formData = array_merge($this->emptyForm(), $_POST);
                    $formData['observations'] = $observationValue;
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
                $formData['observations'] = $observationValue;
            }
        }

        View::render('rules/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'versions' => $versions,
            'selectedVersion' => $selectedVersion,
            'viewingVersion' => $viewingVersion,
            'observationRequired' => $observationRequired,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar regra' : 'Nova regra',
        ]);
    }

    private function ruleToForm($rule): array
    {
        return [
            'id' => $rule->id ?? '',
            'title' => $rule->title ?? '',
            'content' => $rule->content ?? '',
            'status' => $rule->status ?? 'ativo',
            'observations' => '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'title' => '',
            'content' => '',
            'status' => 'ativo',
            'observations' => '',
        ];
    }
}
