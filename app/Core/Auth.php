<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\SecuritySettingsRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use PDO;
use RuntimeException;

final class Auth
{
    private const SESSION_KEY = 'auth_user';

    private UserRepository $users;

    private RateLimiter $rateLimiter;

    private SecuritySettingsRepository $securitySettings;

    public function __construct(private PDO $db, private Config $config, private AuditService $audit)
    {
        $this->users = new UserRepository($db);
        $this->rateLimiter = new RateLimiter($db);
        $this->securitySettings = new SecuritySettingsRepository($db);
    }

    public function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $user = Session::get(self::SESSION_KEY);

        return is_array($user) ? $user : null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return (int) ($user['id'] ?? 0) ?: null;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();
        if ($user === null || !isset($user['roles']) || !is_array($user['roles'])) {
            return false;
        }

        return in_array($role, $user['roles'], true);
    }

    public function hasPermission(string $permission): bool
    {
        $user = $this->user();
        if ($user === null || !isset($user['permissions']) || !is_array($user['permissions'])) {
            return false;
        }

        return in_array($permission, $user['permissions'], true);
    }

    public function passwordExpired(): bool
    {
        $user = $this->user();

        return $user !== null && (int) ($user['password_expired'] ?? 0) === 1;
    }

    public function refresh(): void
    {
        $userId = $this->id();
        if ($userId === null) {
            return;
        }

        $loadedUser = $this->users->findWithPermissionsById($userId);
        if ($loadedUser === null) {
            return;
        }

        Session::set(self::SESSION_KEY, $loadedUser);
    }

    public function attempt(string $email, string $password, string $ip, string $userAgent): bool
    {
        $policy = $this->loginPolicy();
        $maxAttempts = max(3, min(20, (int) ($policy['login_max_attempts'] ?? 5)));
        $windowSeconds = max(60, min(86400, (int) ($policy['login_window_seconds'] ?? 900)));
        $lockoutSeconds = max(60, min(86400, (int) ($policy['login_lockout_seconds'] ?? 900)));

        $throttleKey = sha1(mb_strtolower(trim($email)) . '|' . $ip);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, $maxAttempts, $windowSeconds)) {
            $remaining = $this->rateLimiter->lockoutRemainingSeconds($throttleKey);
            if ($remaining > 0) {
                throw new RuntimeException(
                    sprintf('Muitas tentativas. Tente novamente em %d segundo(s).', $remaining)
                );
            }

            throw new RuntimeException('Muitas tentativas. Aguarde alguns minutos para tentar novamente.');
        }

        $user = $this->users->findActiveByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->rateLimiter->hit($throttleKey, $maxAttempts, $windowSeconds, $lockoutSeconds);
            $this->audit->log(
                entity: 'auth',
                entityId: null,
                action: 'login.failed',
                beforeData: null,
                afterData: ['email' => mb_strtolower(trim($email))],
                metadata: null,
                userId: null,
                ip: $ip,
                userAgent: $userAgent
            );

            return false;
        }

        $this->rateLimiter->clear($throttleKey);
        $this->users->touchLastLogin((int) $user['id']);

        $loadedUser = $this->users->findWithPermissionsById((int) $user['id']);
        if ($loadedUser === null) {
            return false;
        }

        Session::regenerate();
        Session::set(self::SESSION_KEY, $loadedUser);

        $this->audit->log(
            entity: 'auth',
            entityId: (int) $loadedUser['id'],
            action: 'login.success',
            beforeData: null,
            afterData: ['email' => $loadedUser['email']],
            metadata: [
                'password_expired' => (int) ($loadedUser['password_expired'] ?? 0),
            ],
            userId: (int) $loadedUser['id'],
            ip: $ip,
            userAgent: $userAgent
        );

        return true;
    }

    public function logout(string $ip, string $userAgent): void
    {
        $userId = $this->id();

        if ($userId !== null) {
            $this->audit->log(
                entity: 'auth',
                entityId: $userId,
                action: 'logout',
                beforeData: null,
                afterData: null,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );
        }

        Session::remove(self::SESSION_KEY);
        Session::regenerate();
    }

    /** @return array<string, int> */
    private function loginPolicy(): array
    {
        $defaults = [
            'login_max_attempts' => max(3, (int) $this->config->get('security_policy.login_max_attempts', 5)),
            'login_window_seconds' => max(60, (int) $this->config->get('security_policy.login_window_seconds', 900)),
            'login_lockout_seconds' => max(60, (int) $this->config->get('security_policy.login_lockout_seconds', 900)),
        ];

        $stored = $this->securitySettings->defaultSettings();
        if ($stored === null) {
            return $defaults;
        }

        return [
            'login_max_attempts' => max(3, (int) ($stored['login_max_attempts'] ?? $defaults['login_max_attempts'])),
            'login_window_seconds' => max(60, (int) ($stored['login_window_seconds'] ?? $defaults['login_window_seconds'])),
            'login_lockout_seconds' => max(60, (int) ($stored['login_lockout_seconds'] ?? $defaults['login_lockout_seconds'])),
        ];
    }
}
