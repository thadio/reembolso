<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, cpf, is_active
             FROM users
             WHERE email = :email AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);

        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        return $user;
    }

    /** @return array<string, mixed>|null */
    public function findWithPermissionsById(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.name, u.email, u.cpf
             FROM users u
             WHERE u.id = :id AND u.deleted_at IS NULL AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $rolesStmt = $this->db->prepare(
            'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id'
        );
        $rolesStmt->execute(['user_id' => $userId]);
        $roles = array_map(static fn (array $role): string => (string) $role['name'], $rolesStmt->fetchAll());

        $permissionsStmt = $this->db->prepare(
            'SELECT DISTINCT p.name
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id'
        );
        $permissionsStmt->execute(['user_id' => $userId]);
        $permissions = array_map(static fn (array $permission): string => (string) $permission['name'], $permissionsStmt->fetchAll());

        $user['roles'] = $roles;
        $user['permissions'] = $permissions;

        return $user;
    }

    public function touchLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }
}
