<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserAdminRepository;
use Throwable;

final class UserAdminService
{
    public function __construct(
        private UserAdminRepository $users,
        private AuditService $audit,
        private EventService $events,
        private SecuritySettingsService $security
    ) {
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
        return $this->users->paginate($query, $status, $roleId, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->users->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function roles(): array
    {
        return $this->users->listRoles();
    }

    /** @return array<int, array<string, mixed>> */
    public function permissions(): array
    {
        return $this->users->listPermissions();
    }

    public function passwordRulesSummary(): string
    {
        return $this->security->passwordRulesSummary();
    }

    /**
     * @return array{roles: array<int, array<string, mixed>>, permissions: array<int, array<string, mixed>>, role_permission_map: array<int, array<int, int>>}
     */
    public function rolePermissionMatrix(): array
    {
        return [
            'roles' => $this->users->listRoles(),
            'permissions' => $this->users->listPermissions(),
            'role_permission_map' => $this->users->rolePermissionMap(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $actorUserId, string $ip, string $userAgent): array
    {
        $validation = $this->validateCreate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->users->emailExists((string) $validation['data']['email'])) {
            return [
                'ok' => false,
                'errors' => ['Ja existe conta com este e-mail.'],
                'data' => $validation['data'],
            ];
        }

        $passwordHash = password_hash((string) $validation['password'], PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            return [
                'ok' => false,
                'errors' => ['Nao foi possivel gerar hash de senha.'],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['password_hash'] = $passwordHash;
        $payload['password_changed_at'] = date('Y-m-d H:i:s');
        $payload['password_expires_at'] = $this->security->passwordExpiresAtFromNow();

        try {
            $id = $this->users->runInTransaction(function () use ($payload, $validation): int {
                $userId = $this->users->create($payload);
                $this->users->replaceUserRoles($userId, $validation['role_ids']);

                return $userId;
            });
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'errors' => ['Falha ao criar usuario.'],
                'data' => $validation['data'],
            ];
        }

        $after = $this->users->findById($id);

        $this->audit->log(
            entity: 'user',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $after,
            metadata: null,
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user',
            type: 'user.created',
            payload: [
                'email' => (string) ($after['email'] ?? $validation['data']['email']),
                'is_active' => (int) ($after['is_active'] ?? $validation['data']['is_active']),
            ],
            entityId: $id,
            userId: $actorUserId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, int $actorUserId, string $ip, string $userAgent): array
    {
        $before = $this->users->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Usuario nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateUpdate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->users->emailExists((string) $validation['data']['email'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe conta com este e-mail.'],
                'data' => $validation['data'],
            ];
        }

        if ($actorUserId === $id && (int) $validation['data']['is_active'] !== 1) {
            return [
                'ok' => false,
                'errors' => ['Voce nao pode desativar a propria conta.'],
                'data' => $validation['data'],
            ];
        }

        try {
            $this->users->runInTransaction(function () use ($id, $validation): void {
                $this->users->update($id, $validation['data']);
                $this->users->replaceUserRoles($id, $validation['role_ids']);
            });
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'errors' => ['Falha ao atualizar usuario.'],
                'data' => $validation['data'],
            ];
        }

        $after = $this->users->findById($id);

        $this->audit->log(
            entity: 'user',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $after,
            metadata: null,
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user',
            type: 'user.updated',
            payload: ['email' => (string) ($after['email'] ?? $before['email'] ?? '')],
            entityId: $id,
            userId: $actorUserId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    /** @return array{ok: bool, message: string} */
    public function delete(int $id, int $actorUserId, string $ip, string $userAgent): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Usuario invalido.'];
        }

        if ($id === $actorUserId) {
            return ['ok' => false, 'message' => 'Voce nao pode remover a propria conta.'];
        }

        $before = $this->users->findById($id);
        if ($before === null) {
            return ['ok' => false, 'message' => 'Usuario nao encontrado ou ja removido.'];
        }

        $this->users->softDelete($id);

        $this->audit->log(
            entity: 'user',
            entityId: $id,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: null,
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user',
            type: 'user.deleted',
            payload: ['email' => (string) ($before['email'] ?? '')],
            entityId: $id,
            userId: $actorUserId
        );

        return ['ok' => true, 'message' => 'Usuario removido com sucesso.'];
    }

    /** @return array{ok: bool, message: string} */
    public function toggleActive(int $id, int $actorUserId, string $ip, string $userAgent): array
    {
        $before = $this->users->findById($id);
        if ($before === null) {
            return ['ok' => false, 'message' => 'Usuario nao encontrado.'];
        }

        $current = (int) ($before['is_active'] ?? 0) === 1;
        $next = !$current;

        if ($actorUserId === $id && !$next) {
            return ['ok' => false, 'message' => 'Voce nao pode desativar a propria conta.'];
        }

        $this->users->setActive($id, $next);
        $after = $this->users->findById($id);

        $this->audit->log(
            entity: 'user',
            entityId: $id,
            action: 'status',
            beforeData: $before,
            afterData: $after,
            metadata: ['is_active' => $next ? 1 : 0],
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user',
            type: 'user.status_changed',
            payload: ['is_active' => $next ? 1 : 0],
            entityId: $id,
            userId: $actorUserId
        );

        return [
            'ok' => true,
            'message' => $next ? 'Conta ativada com sucesso.' : 'Conta desativada com sucesso.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function updateRolePermissions(array $input, int $actorUserId, string $ip, string $userAgent): array
    {
        $roleId = (int) ($input['role_id'] ?? 0);
        if ($roleId <= 0) {
            return ['ok' => false, 'errors' => ['Papel invalido.']];
        }

        $role = $this->users->findRoleById($roleId);
        if ($role === null) {
            return ['ok' => false, 'errors' => ['Papel nao encontrado.']];
        }

        $permissionIds = $this->normalizeIntArray($input['permission_ids'] ?? []);
        $validPermissionIds = $this->users->validPermissionIds($permissionIds);

        if (count($validPermissionIds) !== count($permissionIds)) {
            return ['ok' => false, 'errors' => ['Lista de permissoes contem item invalido.']];
        }

        sort($validPermissionIds);
        $beforeIds = $this->users->rolePermissionIds($roleId);

        try {
            $this->users->runInTransaction(function () use ($roleId, $validPermissionIds): void {
                $this->users->replaceRolePermissions($roleId, $validPermissionIds);
            });
        } catch (Throwable $throwable) {
            return ['ok' => false, 'errors' => ['Falha ao atualizar permissoes do papel.']];
        }

        $afterIds = $this->users->rolePermissionIds($roleId);

        $this->audit->log(
            entity: 'role',
            entityId: $roleId,
            action: 'update_permissions',
            beforeData: ['permission_ids' => $beforeIds],
            afterData: ['permission_ids' => $afterIds],
            metadata: ['role' => $role['name'] ?? ''],
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'role',
            type: 'role.permissions_updated',
            payload: [
                'role' => (string) ($role['name'] ?? ''),
                'permission_count' => count($afterIds),
            ],
            entityId: $roleId,
            userId: $actorUserId
        );

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function changeOwnPassword(int $userId, array $input, string $ip, string $userAgent): array
    {
        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmation = (string) ($input['new_password_confirmation'] ?? '');

        if ($currentPassword === '') {
            return ['ok' => false, 'errors' => ['Informe a senha atual.']];
        }

        $hash = $this->users->findPasswordHashById($userId);
        if ($hash === null || !password_verify($currentPassword, $hash)) {
            return ['ok' => false, 'errors' => ['Senha atual invalida.']];
        }

        $passwordErrors = $this->validatePassword($newPassword, $confirmation);
        if ($passwordErrors !== []) {
            return ['ok' => false, 'errors' => $passwordErrors];
        }

        if (password_verify($newPassword, $hash)) {
            return ['ok' => false, 'errors' => ['A nova senha deve ser diferente da senha atual.']];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($newHash) || $newHash === '') {
            return ['ok' => false, 'errors' => ['Nao foi possivel atualizar a senha.']];
        }

        $this->users->updatePasswordHash($userId, $newHash, $this->security->passwordExpiresAtFromNow());

        $this->audit->log(
            entity: 'user_password',
            entityId: $userId,
            action: 'change_own',
            beforeData: null,
            afterData: null,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user_password',
            type: 'user.password_changed',
            payload: null,
            entityId: $userId,
            userId: $userId
        );

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function adminResetPassword(
        int $targetUserId,
        array $input,
        int $actorUserId,
        string $ip,
        string $userAgent
    ): array {
        $target = $this->users->findById($targetUserId);
        if ($target === null) {
            return ['ok' => false, 'errors' => ['Usuario nao encontrado para reset.']];
        }

        $newPassword = (string) ($input['reset_password'] ?? '');
        $confirmation = (string) ($input['reset_password_confirmation'] ?? '');

        $passwordErrors = $this->validatePassword($newPassword, $confirmation);
        if ($passwordErrors !== []) {
            return ['ok' => false, 'errors' => $passwordErrors];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($newHash) || $newHash === '') {
            return ['ok' => false, 'errors' => ['Nao foi possivel redefinir a senha.']];
        }

        $this->users->updatePasswordHash($targetUserId, $newHash, $this->security->passwordExpiresAtFromNow());

        $this->audit->log(
            entity: 'user_password',
            entityId: $targetUserId,
            action: 'admin_reset',
            beforeData: null,
            afterData: null,
            metadata: ['target_email' => $target['email'] ?? ''],
            userId: $actorUserId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'user_password',
            type: 'user.password_reset',
            payload: ['target_user_id' => $targetUserId],
            entityId: $targetUserId,
            userId: $actorUserId
        );

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>, role_ids: array<int, int>, password: string}
     */
    private function validateCreate(array $input): array
    {
        $base = $this->validateBase($input);
        $errors = $base['errors'];

        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');
        $errors = array_merge($errors, $this->validatePassword($password, $confirmation));

        return [
            'errors' => $errors,
            'data' => $base['data'],
            'role_ids' => $base['role_ids'],
            'password' => $password,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>, role_ids: array<int, int>}
     */
    private function validateUpdate(array $input): array
    {
        return $this->validateBase($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>, role_ids: array<int, int>}
     */
    private function validateBase(array $input): array
    {
        $name = $this->clean($input['name'] ?? null);
        $email = mb_strtolower((string) $this->clean($input['email'] ?? null));
        $cpf = $this->normalizeCpf($this->clean($input['cpf'] ?? null));
        $isActive = $this->toBool($input['is_active'] ?? null);

        $roleIds = $this->normalizeIntArray($input['role_ids'] ?? []);
        $validRoleIds = $this->users->validRoleIds($roleIds);

        $errors = [];

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do usuario e obrigatorio e deve ter ao menos 3 caracteres.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail valido.';
        }

        if ($cpf !== null && mb_strlen($cpf) !== 14) {
            $errors[] = 'CPF invalido. Informe 11 digitos ou deixe em branco.';
        }

        if ($roleIds === []) {
            $errors[] = 'Selecione ao menos um papel para o usuario.';
        } elseif (count($validRoleIds) !== count($roleIds)) {
            $errors[] = 'Papel selecionado invalido.';
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'cpf' => $cpf,
            'is_active' => $isActive ? 1 : 0,
        ];

        sort($validRoleIds);

        return [
            'errors' => $errors,
            'data' => $data,
            'role_ids' => $validRoleIds,
        ];
    }

    /** @return array<int, string> */
    private function validatePassword(string $password, string $confirmation): array
    {
        $policy = $this->security->passwordPolicy();
        $minLength = max(8, min(64, (int) ($policy['password_min_length'] ?? 8)));
        $maxLength = max($minLength, min(256, (int) ($policy['password_max_length'] ?? 128)));
        $requireUpper = (int) ($policy['password_require_upper'] ?? 0) === 1;
        $requireLower = (int) ($policy['password_require_lower'] ?? 0) === 1;
        $requireNumber = (int) ($policy['password_require_number'] ?? 1) === 1;
        $requireSymbol = (int) ($policy['password_require_symbol'] ?? 0) === 1;

        $errors = [];

        if ($password === '') {
            $errors[] = 'Senha e obrigatoria.';

            return $errors;
        }

        if (mb_strlen($password) < $minLength) {
            $errors[] = sprintf('Senha deve ter ao menos %d caracteres.', $minLength);
        }

        if (mb_strlen($password) > $maxLength) {
            $errors[] = sprintf('Senha deve ter no maximo %d caracteres.', $maxLength);
        }

        if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Senha deve conter ao menos uma letra maiuscula.';
        }

        if ($requireLower && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Senha deve conter ao menos uma letra minuscula.';
        }

        if ($requireNumber && !preg_match('/\d/', $password)) {
            $errors[] = 'Senha deve conter ao menos um numero.';
        }

        if ($requireSymbol && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Senha deve conter ao menos um simbolo.';
        }

        if ($password !== $confirmation) {
            $errors[] = 'Confirmacao de senha nao confere.';
        }

        return $errors;
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeCpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (mb_strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3)
            . '.' . substr($digits, 3, 3)
            . '.' . substr($digits, 6, 3)
            . '-' . substr($digits, 9, 2);
    }

    /** @return array<int, int> */
    private function normalizeIntArray(mixed $input): array
    {
        $values = is_array($input) ? $input : [$input];
        $ids = [];

        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
