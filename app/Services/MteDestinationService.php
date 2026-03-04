<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MteDestinationRepository;

final class MteDestinationService
{
    public function __construct(
        private MteDestinationRepository $destinations,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->destinations->paginate($query, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->destinations->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeList(): array
    {
        return $this->destinations->activeList();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->destinations->nameExists((string) $validation['data']['name'])) {
            return [
                'ok' => false,
                'errors' => ['Já existe lotação com este nome.'],
                'data' => $validation['data'],
            ];
        }

        if (
            $validation['data']['code'] !== null
            && $this->destinations->codeExists((string) $validation['data']['code'])
        ) {
            return [
                'ok' => false,
                'errors' => ['Já existe lotação com este código.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->destinations->create($validation['data']);

        $this->audit->log(
            entity: 'mte_destination',
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
            entity: 'mte_destination',
            type: 'mte_destination.created',
            payload: ['name' => $validation['data']['name']],
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
        $before = $this->destinations->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Lotação não encontrada.'],
                'data' => [],
            ];
        }

        $validation = $this->validate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->destinations->nameExists((string) $validation['data']['name'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe lotação com este nome.'],
                'data' => $validation['data'],
            ];
        }

        if (
            $validation['data']['code'] !== null
            && $this->destinations->codeExists((string) $validation['data']['code'], $id)
        ) {
            return [
                'ok' => false,
                'errors' => ['Já existe lotação com este código.'],
                'data' => $validation['data'],
            ];
        }

        $this->destinations->update($id, $validation['data']);

        $this->audit->log(
            entity: 'mte_destination',
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
            entity: 'mte_destination',
            type: 'mte_destination.updated',
            payload: ['name' => $validation['data']['name']],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    public function delete(int $id, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->destinations->findById($id);
        if ($before === null) {
            return false;
        }

        $this->destinations->softDelete($id);

        $this->audit->log(
            entity: 'mte_destination',
            entityId: $id,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'mte_destination',
            type: 'mte_destination.deleted',
            payload: ['name' => $before['name']],
            entityId: $id,
            userId: $userId
        );

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validate(array $input): array
    {
        $name = $this->clean($input['name'] ?? null);
        $code = $this->clean($input['code'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome da lotação é obrigatório e deve ter ao menos 3 caracteres.';
        }

        if ($code !== null && mb_strlen($code) > 60) {
            $errors[] = 'Código da lotação deve ter no máximo 60 caracteres.';
        }

        $data = [
            'name' => $name,
            'code' => $code === null ? null : mb_strtoupper($code),
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
