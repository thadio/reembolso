<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Profile;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;

use App\Services\UserService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Mailer;
use PDO;
use PDOException;

class UserController
{
    private UserRepository $users;
    private ProfileRepository $profiles;
    private UserService $service;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->users = new UserRepository($pdo);
        $this->profiles = new ProfileRepository($pdo);
        $this->service = new UserService();
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
                Auth::requirePermission('users.delete', $this->users->getPdo());
                $this->users->delete((int) $_POST['delete_id']);
                $success = 'Usuário excluído.';
            } catch (PDOException $e) {
                $errors[] = 'Erro ao excluir usuário: ' . $e->getMessage();
            }
        }

        $rows = $this->users->list();

        View::render('users/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Usuários (MVC)',
        ]);
    }

    public function form(bool $publicOnboarding = false): void
    {
        $errors = [];
        $success = '';
        $editing = false;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $formData = $this->emptyForm();
        $nextUserId = $this->users->nextId();
        $zeroProfile = $this->ensureZeroAccessProfile();
        $profileOptions = $this->profiles->active();
        $zeroProfileId = $zeroProfile ? (string) $zeroProfile->id : '';
        $zeroProfileName = $zeroProfile ? $zeroProfile->name : 'Sem Acesso';
        if (!$formData['profileId'] && !empty($profileOptions)) {
            $formData['profileId'] = $zeroProfileId !== '' ? $zeroProfileId : (string) $profileOptions[0]['id'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            if ($publicOnboarding) {
                $errors[] = 'Edição não disponível no cadastro público.';
            } else {
                Auth::requirePermission('users.edit', $this->users->getPdo());
                $editing = true;
                $user = $this->users->find((int) $_GET['id']);
                if ($user) {
                    $formData = $this->userToForm($user);
                } else {
                    $errors[] = 'Usuário não encontrado.';
                    $editing = false;
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = !$publicOnboarding && isset($_POST['id']) && $_POST['id'] !== '';
            if (!$publicOnboarding) {
                Auth::requirePermission($editing ? 'users.edit' : 'users.create', $this->users->getPdo());
            }
            $input = $_POST;
            if ($publicOnboarding) {
                $input['id'] = '';
            }
            [$user, $errors] = $this->service->validate($input, $editing);
            if (!$editing) {
                if ($zeroProfile && $zeroProfile->id) {
                    $user->profileId = $zeroProfile->id;
                    $user->role = $zeroProfile->name;
                } else {
                    $errors[] = 'Perfil "Sem Acesso" não encontrado.';
                }
                $user->status = 'pendente';
                try {
                    $user->verificationToken = bin2hex(random_bytes(32));
                    $user->verificationExpiresAt = $this->verificationExpiry();
                    $user->verifiedAt = null;
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao gerar token de validação.';
                }
            }
            if ($user->profileId) {
                $profile = $this->profiles->find($user->profileId);
                if ($profile) {
                    $user->role = $profile->name;
                }
            }
            if (empty($errors)) {
                try {
                    $this->users->save($user);
                    $this->syncPersonFromUser($user);
                    if ($editing) {
                        $success = 'Usuário atualizado com sucesso.';
                    } else {
                        $success = $this->sendVerificationEmail($user)
                            ? 'Usuário salvo com sucesso. E-mail de validação enviado.'
                            : 'Usuário salvo, mas houve falha ao enviar o e-mail de validação.';
                    }
                    $formData = $this->userToForm($user);
                    $editing = true;
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'E-mail já existe.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        $profileOptions = $this->ensureProfileOption($profileOptions, $formData['profileId']);

        $layoutData = [
            'title' => $publicOnboarding ? 'Criar conta' : ($editing ? 'Editar usuário' : 'Novo usuário'),
        ];
        if ($publicOnboarding) {
            $layoutData['layout'] = __DIR__ . '/../Views/auth-layout.php';
        }

        View::render('users/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'nextUserId' => $nextUserId,
            'profileOptions' => $profileOptions,
            'zeroProfileName' => $zeroProfileName,
            'publicOnboarding' => $publicOnboarding,
            'esc' => [Html::class, 'esc'],
        ], $layoutData);
    }

    private function userToForm($user): array
    {
        return [
            'id' => $user->id ?? '',
            'fullName' => $user->fullName ?? '',
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? '',
            'role' => $user->role ?? 'colaborador',
            'status' => $user->status ?? 'ativo',
            'profileId' => $user->profileId ?? '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'fullName' => '',
            'email' => '',
            'phone' => '',
            'role' => 'colaborador',
            'status' => 'pendente',
            'profileId' => '',
        ];
    }

    private function ensureProfileOption(array $options, $profileId): array
    {
        if (!$profileId) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int) $option['id'] === (int) $profileId) {
                return $options;
            }
        }

        $profile = $this->profiles->find((int) $profileId);
        if ($profile) {
            $options[] = [
                'id' => $profile->id,
                'name' => $profile->name . ' (inativo)',
            ];
        }

        return $options;
    }

    private function ensureZeroAccessProfile(): ?Profile
    {
        $profile = $this->profiles->findByName('Sem Acesso');
        if ($profile) {
            return $profile;
        }

        $profile = new Profile();
        $profile->name = 'Sem Acesso';
        $profile->description = 'Usuário sem permissões liberadas.';
        $profile->status = 'ativo';
        $profile->permissions = [];

        try {
            $this->profiles->save($profile);
        } catch (\Throwable $e) {
            return null;
        }

        return $profile;
    }

    private function verificationExpiry(int $hours = 48): string
    {
        return date('Y-m-d H:i:s', time() + ($hours * 3600));
    }

    private function sendVerificationEmail($user): bool
    {
        if (!$user || !$user->verificationToken) {
            return false;
        }

        $baseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $link = $baseUrl . '/confirmar-cadastro.php?token=' . urlencode($user->verificationToken);

        $subject = 'Confirme seu cadastro';
        $html = '<p>Olá ' . Html::esc($user->fullName) . ',</p>'
            . '<p>Para liberar seu primeiro acesso, confirme o cadastro clicando no link abaixo:</p>'
            . '<p><a href="' . Html::esc($link) . '">' . Html::esc($link) . '</a></p>'
            . '<p>Se você não solicitou este acesso, ignore este e-mail.</p>';

        return Mailer::send($user->email, $subject, $html);
    }

    private function syncPersonFromUser($user): void
    {
        $pdo = $this->users->getPdo();
        if (!$pdo || !$user || trim((string) ($user->email ?? '')) === '') {
            return;
        }

        // Vincula pessoa existente pelo e-mail e garante papel.
        try {
            $people = new PersonRepository($pdo);
            $roles = new PersonRoleRepository($pdo);
            $existing = $people->findByEmail((string) $user->email);
            if ($existing && $existing->id) {
                $roles->assign($existing->id, 'usuario_retratoapp', 'retratoapp');
            }
        } catch (\Throwable $error) {
            error_log('Erro ao sincronizar pessoa (usuario): ' . $error->getMessage());
        }
    }
}
