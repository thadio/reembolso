<?php

namespace App\Controllers;

use App\Core\View;
use App\Repositories\ProfileRepository;
use App\Services\ProfileService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Permissions;
use PDO;
use PDOException;

class ProfileController
{
    private ProfileRepository $profiles;
    private ProfileService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->profiles = new ProfileRepository($pdo);
        $this->service = new ProfileService();
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
                Auth::requirePermission('profiles.delete', $this->profiles->getPdo());
                $this->profiles->delete((int) $_POST['delete_id']);
                $success = 'Perfil excluído.';
            } catch (PDOException|\RuntimeException $e) {
                $errors[] = 'Erro ao excluir perfil: ' . $e->getMessage();
            }
        }

        $rows = $this->profiles->all();

        View::render('profiles/list', [
            'rows' => $rows,
            'modules' => Permissions::catalog(),
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Perfis de acesso',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $formData = $this->emptyForm();
        $modules = Permissions::catalog();

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('profiles.edit', $this->profiles->getPdo());
            $editing = true;
            $profile = $this->profiles->find((int) $_GET['id']);
            if ($profile) {
                $formData = $this->profileToForm($profile);
            } else {
                $errors[] = 'Perfil não encontrado.';
                $editing = false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::requirePermission(isset($_POST['id']) && $_POST['id'] !== '' ? 'profiles.edit' : 'profiles.create', $this->profiles->getPdo());
            [$profile, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($profile->id ?? false);
            if (empty($errors)) {
                try {
                    $this->profiles->save($profile);
                    $success = $editing ? 'Perfil atualizado com sucesso.' : 'Perfil salvo com sucesso.';
                    $formData = $this->profileToForm($profile);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Nome do perfil já existe. Use um valor único.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        View::render('profiles/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'modules' => $modules,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar perfil' : 'Novo perfil',
        ]);
    }

    private function profileToForm($profile): array
    {
        return [
            'id' => $profile->id ?? '',
            'name' => $profile->name ?? '',
            'description' => $profile->description ?? '',
            'status' => $profile->status ?? 'ativo',
            'permissions' => $profile->permissions ?? [],
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'name' => '',
            'description' => '',
            'status' => 'ativo',
            'permissions' => [],
        ];
    }
}
