<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CdoRepository;

final class CdoService
{
    private const ALLOWED_STATUSES = ['aberto', 'parcial', 'alocado', 'encerrado', 'cancelado'];

    public function __construct(
        private CdoRepository $cdos,
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
        return $this->cdos->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->cdos->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function links(int $cdoId): array
    {
        return $this->cdos->linksByCdo($cdoId);
    }

    /** @return array<int, array<string, mixed>> */
    public function availablePeople(int $cdoId, int $limit = 300): array
    {
        return $this->cdos->availablePeopleForLinking($cdoId, $limit);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return [
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'parcial', 'label' => 'Parcial'],
            ['value' => 'alocado', 'label' => 'Alocado'],
            ['value' => 'encerrado', 'label' => 'Encerrado'],
            ['value' => 'cancelado', 'label' => 'Cancelado'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validateCdoInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $number = (string) $validation['data']['number'];
        if ($this->cdos->cdoNumberExists($number)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe CDO cadastrado com este numero.'],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['created_by'] = $userId > 0 ? $userId : null;

        $id = $this->cdos->create($payload);

        $this->audit->log(
            entity: 'cdo',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cdo',
            type: 'cdo.created',
            payload: [
                'number' => $number,
                'status' => $payload['status'],
                'total_amount' => $payload['total_amount'],
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $payload,
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->cdos->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['CDO nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateCdoInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $number = (string) $validation['data']['number'];
        if ($this->cdos->cdoNumberExists($number, $id)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe CDO cadastrado com este numero.'],
                'data' => $validation['data'],
            ];
        }

        $currentAllocated = max(0.0, (float) ($before['allocated_amount'] ?? 0));
        $newTotal = (float) ($validation['data']['total_amount'] ?? 0);
        if ($newTotal + 0.009 < $currentAllocated) {
            return [
                'ok' => false,
                'errors' => ['Valor total nao pode ficar abaixo do total ja vinculado a pessoas.'],
                'data' => $validation['data'],
            ];
        }

        $this->cdos->update($id, $validation['data']);

        $after = $this->cdos->findById($id);

        $this->audit->log(
            entity: 'cdo',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $beforeTotal = (float) ($before['total_amount'] ?? 0);
        if (abs($beforeTotal - $newTotal) >= 0.01) {
            $this->events->recordEvent(
                entity: 'cdo',
                type: 'cdo.value_changed',
                payload: [
                    'number' => (string) ($before['number'] ?? ''),
                    'before_total_amount' => number_format($beforeTotal, 2, '.', ''),
                    'after_total_amount' => number_format($newTotal, 2, '.', ''),
                ],
                entityId: $id,
                userId: $userId
            );
        }

        $beforeStatus = (string) ($before['status'] ?? '');
        $afterStatus = (string) ($after['status'] ?? ($validation['data']['status'] ?? ''));
        if ($beforeStatus !== $afterStatus) {
            $this->events->recordEvent(
                entity: 'cdo',
                type: 'cdo.status_changed',
                payload: [
                    'number' => (string) ($before['number'] ?? ''),
                    'before_status' => $beforeStatus,
                    'after_status' => $afterStatus,
                ],
                entityId: $id,
                userId: $userId
            );
        }

        $this->events->recordEvent(
            entity: 'cdo',
            type: 'cdo.updated',
            payload: [
                'number' => (string) ($validation['data']['number'] ?? ''),
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
        $before = $this->cdos->findById($id);
        if ($before === null) {
            return false;
        }

        try {
            $this->cdos->beginTransaction();
            $this->cdos->softDeleteLinksByCdo($id);
            $this->cdos->softDelete($id);

            $this->audit->log(
                entity: 'cdo',
                entityId: $id,
                action: 'delete',
                beforeData: $before,
                afterData: null,
                metadata: [
                    'linked_people_count' => (int) ($before['linked_people_count'] ?? 0),
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cdo',
                type: 'cdo.deleted',
                payload: [
                    'number' => (string) ($before['number'] ?? ''),
                ],
                entityId: $id,
                userId: $userId
            );

            $this->cdos->commit();
        } catch (\Throwable $exception) {
            $this->cdos->rollBack();

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function linkPerson(int $cdoId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $cdo = $this->cdos->findById($cdoId);
        if ($cdo === null) {
            return [
                'ok' => false,
                'message' => 'CDO nao encontrado.',
                'errors' => ['CDO nao encontrado.'],
            ];
        }

        if ($this->isFinalStatus((string) ($cdo['status'] ?? ''))) {
            return [
                'ok' => false,
                'message' => 'CDO encerrado/cancelado nao permite vinculos.',
                'errors' => ['CDO encerrado/cancelado nao permite novos vinculos.'],
            ];
        }

        $personId = (int) ($input['person_id'] ?? 0);
        $amount = $this->parseMoney($input['allocated_amount'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($personId <= 0) {
            $errors[] = 'Pessoa invalida para vinculo.';
        }

        if ($amount === null || (float) $amount <= 0.0) {
            $errors[] = 'Valor de vinculo deve ser maior que zero.';
        }

        if ($personId > 0 && !$this->cdos->personExists($personId)) {
            $errors[] = 'Pessoa informada nao existe ou foi removida.';
        }

        if ($personId > 0 && $this->cdos->activeLinkExists($cdoId, $personId)) {
            $errors[] = 'Pessoa ja vinculada a este CDO.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel vincular pessoa ao CDO.',
                'errors' => $errors,
            ];
        }

        $available = max(0.0, (float) ($cdo['available_amount'] ?? 0));
        if ((float) $amount - $available > 0.009) {
            return [
                'ok' => false,
                'message' => 'Saldo insuficiente no CDO para este vinculo.',
                'errors' => ['Vinculo bloqueado: valor excede o saldo disponivel do CDO.'],
            ];
        }

        try {
            $this->cdos->beginTransaction();

            $linkId = $this->cdos->createPersonLink(
                cdoId: $cdoId,
                personId: $personId,
                allocatedAmount: $amount,
                notes: $notes,
                createdBy: $userId > 0 ? $userId : null
            );

            $after = $this->cdos->findById($cdoId) ?? $cdo;
            $this->syncAutoStatus(
                cdoId: $cdoId,
                current: (string) ($cdo['status'] ?? 'aberto'),
                allocated: (float) ($after['allocated_amount'] ?? 0),
                total: (float) ($after['total_amount'] ?? 0),
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent,
                number: (string) ($after['number'] ?? ($cdo['number'] ?? ''))
            );

            $this->audit->log(
                entity: 'cdo_person',
                entityId: $linkId,
                action: 'link',
                beforeData: null,
                afterData: [
                    'cdo_id' => $cdoId,
                    'person_id' => $personId,
                    'allocated_amount' => $amount,
                    'notes' => $notes,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cdo',
                type: 'cdo.person_linked',
                payload: [
                    'link_id' => $linkId,
                    'person_id' => $personId,
                    'allocated_amount' => $amount,
                ],
                entityId: $cdoId,
                userId: $userId
            );

            $this->cdos->commit();
        } catch (\Throwable $exception) {
            $this->cdos->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel vincular pessoa ao CDO.',
                'errors' => ['Falha ao persistir vinculo. Tente novamente.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Pessoa vinculada ao CDO com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function unlinkPerson(int $cdoId, int $linkId, int $userId, string $ip, string $userAgent): array
    {
        $cdo = $this->cdos->findById($cdoId);
        if ($cdo === null) {
            return [
                'ok' => false,
                'message' => 'CDO nao encontrado.',
                'errors' => ['CDO nao encontrado.'],
            ];
        }

        if ($this->isFinalStatus((string) ($cdo['status'] ?? ''))) {
            return [
                'ok' => false,
                'message' => 'CDO encerrado/cancelado nao permite alteracao de vinculos.',
                'errors' => ['CDO encerrado/cancelado nao permite remover vinculos.'],
            ];
        }

        $link = $this->cdos->findPersonLinkById($linkId, $cdoId);
        if ($link === null) {
            return [
                'ok' => false,
                'message' => 'Vinculo nao encontrado.',
                'errors' => ['Vinculo de pessoa nao encontrado para este CDO.'],
            ];
        }

        try {
            $this->cdos->beginTransaction();

            $this->cdos->softDeletePersonLink($linkId);

            $after = $this->cdos->findById($cdoId) ?? $cdo;
            $this->syncAutoStatus(
                cdoId: $cdoId,
                current: (string) ($cdo['status'] ?? 'aberto'),
                allocated: (float) ($after['allocated_amount'] ?? 0),
                total: (float) ($after['total_amount'] ?? 0),
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent,
                number: (string) ($after['number'] ?? ($cdo['number'] ?? ''))
            );

            $this->audit->log(
                entity: 'cdo_person',
                entityId: $linkId,
                action: 'unlink',
                beforeData: $link,
                afterData: null,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cdo',
                type: 'cdo.person_unlinked',
                payload: [
                    'link_id' => $linkId,
                    'person_id' => (int) ($link['person_id'] ?? 0),
                    'allocated_amount' => (string) ($link['allocated_amount'] ?? '0.00'),
                ],
                entityId: $cdoId,
                userId: $userId
            );

            $this->cdos->commit();
        } catch (\Throwable $exception) {
            $this->cdos->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover vinculo.',
                'errors' => ['Falha ao remover vinculo. Tente novamente.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Vinculo removido com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateCdoInput(array $input): array
    {
        $number = $this->clean($input['number'] ?? null);
        $ugCode = $this->clean($input['ug_code'] ?? null);
        $actionCode = $this->clean($input['action_code'] ?? null);
        $periodStartRaw = $this->clean($input['period_start'] ?? null);
        $periodEndRaw = $this->clean($input['period_end'] ?? null);
        $totalAmount = $this->parseMoney($input['total_amount'] ?? null);
        $status = mb_strtolower((string) ($input['status'] ?? 'aberto'));
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($number === null || mb_strlen($number) < 3) {
            $errors[] = 'Numero do CDO e obrigatorio (minimo 3 caracteres).';
        }

        $periodStart = $this->normalizeDate($periodStartRaw);
        if ($periodStart === null) {
            $errors[] = 'Periodo inicial invalido.';
        }

        $periodEnd = $this->normalizeDate($periodEndRaw);
        if ($periodEnd === null) {
            $errors[] = 'Periodo final invalido.';
        }

        if ($periodStart !== null && $periodEnd !== null && strtotime($periodEnd) < strtotime($periodStart)) {
            $errors[] = 'Periodo final deve ser igual ou posterior ao periodo inicial.';
        }

        if ($totalAmount === null || (float) $totalAmount <= 0.0) {
            $errors[] = 'Valor total do CDO deve ser maior que zero.';
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Status do CDO invalido.';
        }

        $data = [
            'number' => $number === null ? '' : mb_substr($number, 0, 80),
            'ug_code' => $ugCode === null ? null : mb_substr($ugCode, 0, 30),
            'action_code' => $actionCode === null ? null : mb_substr($actionCode, 0, 30),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_amount' => $totalAmount,
            'status' => $status,
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function syncAutoStatus(
        int $cdoId,
        string $current,
        float $allocated,
        float $total,
        int $userId,
        string $ip,
        string $userAgent,
        string $number
    ): void {
        if ($this->isFinalStatus($current)) {
            return;
        }

        $nextStatus = $this->autoStatus($allocated, $total);
        if ($nextStatus === $current) {
            return;
        }

        $this->cdos->updateStatus($cdoId, $nextStatus);

        $this->audit->log(
            entity: 'cdo',
            entityId: $cdoId,
            action: 'status.auto_update',
            beforeData: ['status' => $current],
            afterData: ['status' => $nextStatus],
            metadata: [
                'allocated_amount' => number_format($allocated, 2, '.', ''),
                'total_amount' => number_format($total, 2, '.', ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cdo',
            type: 'cdo.status_changed',
            payload: [
                'number' => $number,
                'before_status' => $current,
                'after_status' => $nextStatus,
                'reason' => 'auto_allocation',
            ],
            entityId: $cdoId,
            userId: $userId
        );
    }

    private function autoStatus(float $allocated, float $total): string
    {
        if ($allocated <= 0.009) {
            return 'aberto';
        }

        if ($allocated + 0.009 >= $total) {
            return 'alocado';
        }

        return 'parcial';
    }

    private function isFinalStatus(string $status): bool
    {
        return in_array($status, ['encerrado', 'cancelado'], true);
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseMoney(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = $raw;
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
