<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrganRepository;

final class OrganService
{
    public function __construct(
        private OrganRepository $organs,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->organs->paginate($query, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->organs->findById($id);
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

        if ($validation['data']['cnpj'] !== null && $this->organs->cnpjExists((string) $validation['data']['cnpj'])) {
            return [
                'ok' => false,
                'errors' => ['Já existe um órgão com este CNPJ.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->organs->create($validation['data']);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.created',
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
        $before = $this->organs->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Órgão não encontrado.'],
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

        if ($validation['data']['cnpj'] !== null && $this->organs->cnpjExists((string) $validation['data']['cnpj'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe um órgão com este CNPJ.'],
                'data' => $validation['data'],
            ];
        }

        $this->organs->update($id, $validation['data']);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.updated',
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
        $before = $this->organs->findById($id);
        if ($before === null) {
            return false;
        }

        $this->organs->softDelete($id);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.deleted',
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
        $acronym = $this->clean($input['acronym'] ?? null);
        $cnpj = $this->normalizeCnpj($this->clean($input['cnpj'] ?? null));
        $contactName = $this->clean($input['contact_name'] ?? null);
        $contactEmail = $this->clean($input['contact_email'] ?? null);
        $contactPhone = $this->clean($input['contact_phone'] ?? null);
        $addressLine = $this->clean($input['address_line'] ?? null);
        $city = $this->clean($input['city'] ?? null);
        $state = $this->clean($input['state'] ?? null);
        $zipCode = $this->clean($input['zip_code'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do órgão é obrigatório e deve ter ao menos 3 caracteres.';
        }

        if ($contactEmail !== null && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail de contato inválido.';
        }

        if ($cnpj !== null && mb_strlen($cnpj) !== 18) {
            $errors[] = 'CNPJ inválido. Informe 14 dígitos.';
        }

        if ($state !== null) {
            $state = mb_strtoupper($state);
            if (mb_strlen($state) !== 2) {
                $errors[] = 'UF deve conter exatamente 2 caracteres.';
            }
        }

        $data = [
            'name' => $name,
            'acronym' => $acronym === null ? null : mb_strtoupper($acronym),
            'cnpj' => $cnpj,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'address_line' => $addressLine,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zipCode,
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

    private function normalizeCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cnpj);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (mb_strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2)
            . '.' . substr($digits, 2, 3)
            . '.' . substr($digits, 5, 3)
            . '/' . substr($digits, 8, 4)
            . '-' . substr($digits, 12, 2);
    }
}
