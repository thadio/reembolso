<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;
use App\Services\AuditService;
use PDO;
use RuntimeException;

final class Auth
{
    private const SESSION_KEY = 'auth_user';

    private UserRepository $users;

    private RateLimiter $rateLimiter;

    public function __construct(private PDO $db, private Config $config, private AuditService $audit)
    {
        $this->users = new UserRepository($db);
        $this->rateLimiter = new RateLimiter($db);
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

    public function attempt(string $email, string $password, string $ip, string $userAgent): bool
    {
        $throttleKey = sha1(mb_strtolower(trim($email)) . '|' . $ip);

        $maxAttempts = (int) $this->config->get('rate_limit.login_max_attempts', 5);
        $decay = (int) $this->config->get('rate_limit.login_decay_seconds', 900);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, $maxAttempts, $decay)) {
            throw new RuntimeException('Muitas tentativas. Aguarde alguns minutos para tentar novamente.');
        }

        $user = $this->users->findActiveByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->rateLimiter->hit($throttleKey);
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
            metadata: null,
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
}
