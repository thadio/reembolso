<?php

namespace App\Support;

use App\Models\User;
use App\Models\Profile;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;

use App\Support\Permissions;
use PDO;

class Auth
{
    private const SESSION_KEY = 'auth_user';
    private static bool $sessionStarted = false;

    public static function start(): void
    {
        if (self::$sessionStarted) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::$sessionStarted = true;
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function userId(): ?int
    {
        $user = self::user();
        if (!is_array($user)) {
            return null;
        }

        $id = isset($user['id']) ? (int) $user['id'] : 0;
        return $id > 0 ? $id : null;
    }

    public static function requireLogin(?PDO $pdo = null): void
    {
        if (self::check($pdo)) {
            return;
        }

        // FIX: Requisições AJAX não devem receber redirect 302 para
        // login.php — o fetch() seguiria o redirect silenciosamente e
        // tentaria parsear o HTML como JSON, causando erro.
        if (self::isJsonRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => false,
                'message' => 'Sessão expirada. Faça login novamente.',
            ]);
            exit;
        }

        $redirect = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit;
    }

    public static function attempt(?PDO $pdo, string $email, string $password): array
    {
        self::start();

        if (!$pdo) {
            return [false, 'Sem conexão com o banco.'];
        }

        $repo = new UserRepository($pdo);
        $user = $repo->findByEmail($email);

        if (!$user) {
            return [false, 'Usuário não encontrado.'];
        }

        if ($user->status !== 'ativo') {
            if ($user->status === 'pendente') {
                return [false, 'Cadastro pendente de validação. Verifique seu e-mail.'];
            }
            return [false, 'Usuário inativo.'];
        }

        if (!self::validatePassword($user, $password)) {
            return [false, 'Senha incorreta.'];
        }

        $profileRepo = new ProfileRepository($pdo);
        $profileData = null;
        $permissions = [];

        $profile = $user->profileId ? $profileRepo->find($user->profileId) : null;
        if (!$profile && $user->role) {
            $profile = $profileRepo->findByName($user->role);
            if ($profile) {
                $user->profileId = $profile->id;
                $user->role = $profile->name;
                try {
                    $repo->save($user);
                } catch (\Throwable $e) {
                    // Não bloqueia login se atualização do vínculo falhar.
                }
            }
        }
        if (!$profile && $user->role === 'admin') {
            $profile = $profileRepo->findByName('Administrador');
            if ($profile) {
                $user->profileId = $profile->id;
                $user->role = $profile->name;
                try {
                    $repo->save($user);
                } catch (\Throwable $e) {
                    // Não bloqueia login se atualização do vínculo falhar.
                }
            }
        }

        if ($profile) {
            if ($profile->status !== 'ativo') {
                return [false, 'Perfil de acesso inativo ou inexistente.'];
            }
            $profileData = $profile;
            $permissions = $profile->permissions;
            $user->role = $profile->name;
        } else {
            return [false, 'Usuário sem perfil de acesso associado.'];
        }

        self::refreshSession($user, $profileData, Permissions::upgradeLegacy($permissions));
        self::syncPersonFromUser($user, $pdo);

        return [true, null];
    }

    public static function logout(): void
    {
        self::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function check(?PDO $pdo = null): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        if (!$pdo) {
            return true;
        }

        $repo = new UserRepository($pdo);
        $dbUser = $repo->find((int) $user['id']);

        if (!$dbUser || $dbUser->status !== 'ativo') {
            self::logout();
            return false;
        }

        $profileRepo = new ProfileRepository($pdo);
        $profileData = null;
        $permissions = [];

        $profile = $dbUser->profileId ? $profileRepo->find($dbUser->profileId) : null;
        if (!$profile && $dbUser->role) {
            $profile = $profileRepo->findByName($dbUser->role);
            if ($profile) {
                $dbUser->profileId = $profile->id;
                $dbUser->role = $profile->name;
                try {
                    $repo->save($dbUser);
                } catch (\Throwable $e) {
                    // segue mesmo sem atualizar o vínculo.
                }
            }
        }
        if (!$profile && $dbUser->role === 'admin') {
            $profile = $profileRepo->findByName('Administrador');
            if ($profile) {
                $dbUser->profileId = $profile->id;
                $dbUser->role = $profile->name;
                try {
                    $repo->save($dbUser);
                } catch (\Throwable $e) {
                    // segue mesmo sem atualizar o vínculo.
                }
            }
        }

        if ($profile) {
            if ($profile->status !== 'ativo') {
                self::logout();
                return false;
            }
            $profileData = $profile;
            $permissions = $profile->permissions;
            $dbUser->role = $profile->name;
        } else {
            self::logout();
            return false;
        }

        self::refreshSession($dbUser, $profileData, Permissions::upgradeLegacy($permissions));

        return true;
    }

    private static function validatePassword(User $user, string $password): bool
    {
        $hash = $user->passwordHash ?? '';
        if ($hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function can(string $permission): bool
    {
        self::start();
        $user = self::user();
        if (!$user) {
            return false;
        }
        [$module, $action] = self::splitPermission($permission);
        $permissions = $user['permissions'] ?? [];
        if (!is_array($permissions)) {
            $decoded = json_decode((string) $permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }
        $permissions = Permissions::upgradeLegacy($permissions);
        return Permissions::has($permissions, $module, $action);
    }

    public static function requirePermission(string $permission, ?PDO $pdo = null): void
    {
        self::requireLogin($pdo);
        if (self::can($permission)) {
            return;
        }
        http_response_code(403);

        // FIX: Quando a requisição é AJAX (XMLHttpRequest ou Accept: json),
        // responde JSON para que o fetch() do frontend consiga parsear a
        // resposta em vez de gerar SyntaxError silencioso.
        if (self::isJsonRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => false,
                'message' => 'Acesso negado ao recurso solicitado.',
            ]);
            exit;
        }

        echo 'Acesso negado ao recurso solicitado.';
        exit;
    }

    /**
     * Detecta se a requisição corrente espera resposta JSON.
     */
    private static function isJsonRequest(): bool
    {
        // Header padrão enviado por fetch() / XMLHttpRequest quando
        // configurado para esperar JSON.
        $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Fallback: header X-Requested-With (usado por jQuery, axios, etc.)
        $xhr = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($xhr === 'xmlhttprequest') {
            return true;
        }

        // Heurística: Content-Type do request é JSON (POST com body JSON).
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    private static function refreshSession(User $user, ?Profile $profile, array $permissions): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'id' => $user->id,
            'name' => $user->fullName,
            'email' => $user->email,
            'role' => $profile ? $profile->name : $user->role,
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'status' => $profile->status,
            ] : null,
            'permissions' => $permissions,
        ];
    }

    private static function splitPermission(string $permission): array
    {
        $normalized = strtolower(trim($permission));

        if (str_starts_with($normalized, 'catalog.brands.')) {
            $action = substr($normalized, strlen('catalog.brands.'));
            return ['brands', $action !== '' ? $action : 'view'];
        }

        if (str_starts_with($normalized, 'catalog.categories.')) {
            $action = substr($normalized, strlen('catalog.categories.'));
            return ['collections', $action !== '' ? $action : 'view'];
        }

        $parts = explode('.', $normalized, 2);
        $module = $parts[0];
        $action = $parts[1] ?? 'view';
        return [$module, $action];
    }

    private static function syncPersonFromUser(User $user, ?PDO $pdo): void
    {
        if (!$pdo || !$user || trim((string) ($user->email ?? '')) === '') {
            return;
        }

        // Vincula pessoa existente por e-mail e garante papel.
        try {
            $people = new PersonRepository($pdo);
            $roles = new PersonRoleRepository($pdo);
            $existing = $people->findByEmail((string) $user->email);
            if ($existing && $existing->id) {
                $roles->assign($existing->id, 'usuario_retratoapp', 'retratoapp');
            }
        } catch (\Throwable $fallbackError) {
            error_log('Erro ao sincronizar pessoa (login usuario): ' . $fallbackError->getMessage());
        }
    }
}
