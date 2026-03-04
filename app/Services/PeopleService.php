<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PeopleRepository;

final class PeopleService
{
    private const ALLOWED_STATUS = [
        'interessado',
        'triagem',
        'selecionado',
        'oficio_orgao',
        'custos_recebidos',
        'cdo',
        'mgi',
        'dou',
        'ativo',
    ];

    public function __construct(
        private PeopleRepository $people,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->people->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->people->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        return $this->people->activeOrgans();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeModalities(): array
    {
        return $this->people->activeModalities();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeMteDestinations(): array
    {
        return $this->people->activeMteDestinations();
    }

    /** @return array<int, string> */
    public function statuses(): array
    {
        return self::ALLOWED_STATUS;
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

        $validation['data']['status'] = 'interessado';

        if ($this->people->cpfExists((string) $validation['data']['cpf'])) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com este CPF.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->people->create($validation['data']);

        $this->audit->log(
            entity: 'person',
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
            entity: 'person',
            type: 'person.created',
            payload: [
                'name' => $validation['data']['name'],
                'status' => $validation['data']['status'],
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
        $before = $this->people->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Pessoa não encontrada.'],
                'data' => [],
            ];
        }

        $validation = $this->validate($input, $this->clean($before['mte_destination'] ?? null));
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->people->cpfExists((string) $validation['data']['cpf'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com este CPF.'],
                'data' => $validation['data'],
            ];
        }

        $validation['data']['status'] = (string) ($before['status'] ?? 'interessado');

        $this->people->update($id, $validation['data']);

        $this->audit->log(
            entity: 'person',
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
            entity: 'person',
            type: 'person.updated',
            payload: [
                'name' => $validation['data']['name'],
                'status' => $validation['data']['status'],
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

    public function delete(int $id, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->people->findById($id);
        if ($before === null) {
            return false;
        }

        $this->people->softDelete($id);

        $this->audit->log(
            entity: 'person',
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
            entity: 'person',
            type: 'person.deleted',
            payload: ['name' => $before['name']],
            entityId: $id,
            userId: $userId
        );

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @param ?string $legacyDestination
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validate(array $input, ?string $legacyDestination): array
    {
        $organId = (int) ($input['organ_id'] ?? 0);
        $modalityId = (int) ($input['desired_modality_id'] ?? 0);
        $name = $this->clean($input['name'] ?? null);
        $cpf = $this->normalizeCpf($this->clean($input['cpf'] ?? null));
        $birthDate = $this->normalizeDate($this->clean($input['birth_date'] ?? null));
        $email = $this->clean($input['email'] ?? null);
        $phone = $this->clean($input['phone'] ?? null);
        $sei = $this->clean($input['sei_process_number'] ?? null);
        $destination = $this->clean($input['mte_destination'] ?? null);
        $tags = $this->normalizeTags($this->clean($input['tags'] ?? null));
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($organId <= 0 || !$this->people->organExists($organId)) {
            $errors[] = 'Órgão de origem é obrigatório.';
        }

        if ($modalityId > 0 && !$this->people->modalityExists($modalityId)) {
            $errors[] = 'Modalidade pretendida inválida.';
        }

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome da pessoa é obrigatório e deve ter ao menos 3 caracteres.';
        }

        if ($cpf === null || mb_strlen($cpf) !== 14) {
            $errors[] = 'CPF inválido. Informe 11 dígitos.';
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }

        if ($destination !== null && !$this->people->mteDestinationExists($destination)) {
            $isLegacyDestination = $legacyDestination !== null
                && mb_strtolower($legacyDestination) === mb_strtolower($destination);

            if (!$isLegacyDestination) {
                $errors[] = 'Lotação MTE inválida. Selecione uma lotação cadastrada.';
            }
        }

        $data = [
            'organ_id' => $organId,
            'desired_modality_id' => $modalityId > 0 ? $modalityId : null,
            'name' => $name,
            'cpf' => $cpf,
            'birth_date' => $birthDate,
            'email' => $email,
            'phone' => $phone,
            'status' => 'interessado',
            'sei_process_number' => $sei,
            'mte_destination' => $destination,
            'tags' => $tags,
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

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $time = strtotime($date);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function normalizeTags(?string $tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        $parts = array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $tags)));
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
