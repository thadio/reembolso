<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class UserAdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(
        string $query,
        string $status,
        int $roleId,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        $sortMap = [
            'name' => 'u.name',
            'email' => 'u.email',
            'is_active' => 'u.is_active',
            'last_login_at' => 'u.last_login_at',
            'created_at' => 'u.created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'u.created_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE u.deleted_at IS NULL';
        $params = [];

        if ($query !== '') {
            $where .= ' AND (u.name LIKE :q_name OR u.email LIKE :q_email OR u.cpf LIKE :q_cpf)';
            $search = '%' . $query . '%';
            $params['q_name'] = $search;
            $params['q_email'] = $search;
            $params['q_cpf'] = $search;
        }

        if ($status === 'active') {
            $where .= ' AND u.is_active = 1';
        } elseif ($status === 'inactive') {
            $where .= ' AND u.is_active = 0';
        }

        if ($roleId > 0) {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM user_roles ur_filter
                WHERE ur_filter.user_id = u.id AND ur_filter.role_id = :role_id
            )';
            $params['role_id'] = $roleId;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM users u {$where}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                u.id,
                u.name,
                u.email,
                u.cpf,
                u.is_active,
                u.last_login_at,
                u.password_expires_at,
                u.created_at,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names_csv
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            {$where}
            GROUP BY u.id, u.name, u.email, u.cpf, u.is_active, u.last_login_at, u.password_expires_at, u.created_at
            ORDER BY {$sortColumn} {$direction}, u.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($listSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];

        foreach ($rows as $row) {
            $row['role_names'] = $this->splitCsv($row['role_names_csv'] ?? null);
            unset($row['role_names_csv']);
            $items[] = $row;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                u.id,
                u.name,
                u.email,
                u.cpf,
                u.is_active,
                u.last_login_at,
                u.password_changed_at,
                u.password_expires_at,
                u.created_at,
                u.updated_at
             FROM users u
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        $user['role_ids'] = $this->roleIdsByUserId($id);
        $user['role_names'] = $this->roleNamesByUserId($id);
        $user['permission_names'] = $this->permissionNamesByUserId($id);

        return $user;
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => mb_strtolower(trim($email))];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (
                name,
                email,
                password_hash,
                cpf,
                is_active,
                password_changed_at,
                password_expires_at,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :email,
                :password_hash,
                :cpf,
                :is_active,
                :password_changed_at,
                :password_expires_at,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'cpf' => $data['cpf'],
            'is_active' => $data['is_active'],
            'password_changed_at' => $data['password_changed_at'],
            'password_expires_at' => $data['password_expires_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET
                name = :name,
                email = :email,
                cpf = :cpf,
                is_active = :is_active,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'cpf' => $data['cpf'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET is_active = :is_active, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET deleted_at = NOW(), updated_at = NOW(), is_active = 0
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    /** @param array<int, int> $roleIds */
    public function replaceUserRoles(int $userId, array $roleIds): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $deleteStmt->execute(['user_id' => $userId]);

        if ($roleIds === []) {
            return;
        }

        $insertStmt = $this->db->prepare(
            'INSERT INTO user_roles (user_id, role_id, created_at)
             VALUES (:user_id, :role_id, NOW())'
        );

        foreach ($roleIds as $roleId) {
            $insertStmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listRoles(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description
             FROM roles
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findRoleById(int $roleId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description
             FROM roles
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $roleId]);
        $role = $stmt->fetch();

        return $role === false ? null : $role;
    }

    /** @return array<int, array<string, mixed>> */
    public function listPermissions(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description
             FROM permissions
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<int, int>> */
    public function rolePermissionMap(): array
    {
        $stmt = $this->db->query(
            'SELECT role_id, permission_id
             FROM role_permissions
             ORDER BY role_id ASC, permission_id ASC'
        );

        $map = [];

        foreach ($stmt->fetchAll() as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            $permissionId = (int) ($row['permission_id'] ?? 0);

            if ($roleId <= 0 || $permissionId <= 0) {
                continue;
            }

            if (!isset($map[$roleId])) {
                $map[$roleId] = [];
            }

            $map[$roleId][] = $permissionId;
        }

        return $map;
    }

    /** @return array<int, int> */
    public function rolePermissionIds(int $roleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT permission_id
             FROM role_permissions
             WHERE role_id = :role_id
             ORDER BY permission_id ASC'
        );
        $stmt->execute(['role_id' => $roleId]);

        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) ($row['permission_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param array<int, int> $permissionIds */
    public function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
        $deleteStmt->execute(['role_id' => $roleId]);

        if ($permissionIds === []) {
            return;
        }

        $insertStmt = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, created_at)
             VALUES (:role_id, :permission_id, NOW())'
        );

        foreach ($permissionIds as $permissionId) {
            $insertStmt->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /** @param array<int, int> $candidateIds
     *  @return array<int, int>
     */
    public function validRoleIds(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE id IN ({$placeholders}) ORDER BY id ASC");
        foreach ($candidateIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param array<int, int> $candidateIds
     *  @return array<int, int>
     */
    public function validPermissionIds(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmt = $this->db->prepare("SELECT id FROM permissions WHERE id IN ({$placeholders}) ORDER BY id ASC");
        foreach ($candidateIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function findPasswordHashById(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT password_hash
             FROM users
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $hash = (string) ($row['password_hash'] ?? '');

        return $hash === '' ? null : $hash;
    }

    public function updatePasswordHash(int $userId, string $passwordHash, ?string $passwordExpiresAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET
                password_hash = :password_hash,
                password_changed_at = NOW(),
                password_expires_at = :password_expires_at,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
            'password_expires_at' => $passwordExpiresAt,
        ]);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function runInTransaction(callable $callback): mixed
    {
        if ($this->db->inTransaction()) {
            return $callback();
        }

        $this->db->beginTransaction();

        try {
            $result = $callback();
            $this->db->commit();

            return $result;
        } catch (Throwable $throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $throwable;
        }
    }

    /** @return array<int, int> */
    private function roleIdsByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ur.role_id
             FROM user_roles ur
             WHERE ur.user_id = :user_id
             ORDER BY ur.role_id ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $ids = [];

        foreach ($stmt->fetchAll() as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            if ($roleId > 0) {
                $ids[] = $roleId;
            }
        }

        return $ids;
    }

    /** @return array<int, string> */
    private function roleNamesByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.name
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $items = [];

        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $items[] = $name;
            }
        }

        return $items;
    }

    /** @return array<int, string> */
    private function permissionNamesByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT p.name
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
             ORDER BY p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $items = [];

        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $items[] = $name;
            }
        }

        return $items;
    }

    /** @return array<int, string> */
    private function splitCsv(mixed $csv): array
    {
        $raw = trim((string) $csv);
        if ($raw === '') {
            return [];
        }

        $parts = array_map(static fn (string $part): string => trim($part), explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
