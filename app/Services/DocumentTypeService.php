<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentTypeRepository;

final class DocumentTypeService
{
    public function __construct(
        private DocumentTypeRepository $types,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $status, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->types->paginate($query, $status, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->types->findById($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validate($input, null);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->types->nameExists((string) $validation['data']['name'])) {
            return [
                'ok' => false,
                'errors' => ['Ja existe tipo de documento com este nome.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->types->create($validation['data']);

        $this->audit->log(
            entity: 'document_type',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'document_type',
            type: 'document_type.created',
            payload: [
                'name' => $validation['data']['name'],
                'is_active' => (int) ($validation['data']['is_active'] ?? 0),
            ],
            entityId: $id,
            userId: $userId
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
    public function update(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->types->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Tipo de documento nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validate($input, $before);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->types->nameExists((string) $validation['data']['name'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe tipo de documento com este nome.'],
                'data' => $validation['data'],
            ];
        }

        $this->types->update($id, $validation['data']);

        $this->audit->log(
            entity: 'document_type',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'document_type',
            type: 'document_type.updated',
            payload: [
                'name' => $validation['data']['name'],
                'is_active' => (int) ($validation['data']['is_active'] ?? 0),
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function toggleActive(int $id, bool $isActive, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->types->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Tipo de documento nao encontrado.'],
                'message' => 'Nao foi possivel atualizar status do tipo de documento.',
            ];
        }

        if ((int) ($before['is_active'] ?? 0) === ($isActive ? 1 : 0)) {
            return [
                'ok' => true,
                'errors' => [],
                'message' => 'Status do tipo de documento ja estava atualizado.',
            ];
        }

        $this->types->setActive($id, $isActive);
        $after = $this->types->findById($id) ?? $before;

        $this->audit->log(
            entity: 'document_type',
            entityId: $id,
            action: 'status.update',
            beforeData: $before,
            afterData: $after,
            metadata: ['is_active' => $isActive ? 1 : 0],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'document_type',
            type: 'document_type.status_updated',
            payload: [
                'is_active' => $isActive ? 1 : 0,
                'name' => (string) ($after['name'] ?? ''),
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => $isActive
                ? 'Tipo de documento ativado com sucesso.'
                : 'Tipo de documento inativado com sucesso.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param ?array<string, mixed> $before
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validate(array $input, ?array $before): array
    {
        $name = $this->clean($input['name'] ?? ($before['name'] ?? null));
        $description = $this->clean($input['description'] ?? ($before['description'] ?? null));
        $isActive = $this->normalizeBool($input['is_active'] ?? ($before['is_active'] ?? '1'), true);

        $errors = [];
        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do tipo de documento e obrigatorio (minimo 3 caracteres).';
        }
        if ($description !== null && mb_strlen($description) > 255) {
            $errors[] = 'Descricao deve ter no maximo 255 caracteres.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive ? 1 : 0,
            ],
        ];
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
