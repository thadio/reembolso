<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PendingCenterRepository;

final class PendingCenterService
{
    private const ALLOWED_TYPES = ['documento', 'divergencia', 'retorno'];
    private const ALLOWED_STATUS = ['aberta', 'resolvida'];
    private const ALLOWED_SEVERITY = ['baixa', 'media', 'alta'];

    public function __construct(
        private PendingCenterRepository $pending,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   pages: int,
     *   summary: array<string, int>
     * }
     */
    public function panel(
        array $filters,
        int $page,
        int $perPage,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $this->syncAutoPendencies($userId, $ip, $userAgent);

        $normalizedFilters = $this->normalizeFilters($filters);
        $list = $this->pending->paginate($normalizedFilters, $page, $perPage);
        $summary = $this->pending->summary($normalizedFilters);

        return [
            ...$list,
            'summary' => $summary,
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function typeOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os tipos'],
            ['value' => 'documento', 'label' => 'Documentos'],
            ['value' => 'divergencia', 'label' => 'Divergencias'],
            ['value' => 'retorno', 'label' => 'Retornos'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os status'],
            ['value' => 'aberta', 'label' => 'Aberta'],
            ['value' => 'resolvida', 'label' => 'Resolvida'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function severityOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todas as severidades'],
            ['value' => 'alta', 'label' => 'Alta'],
            ['value' => 'media', 'label' => 'Media'],
            ['value' => 'baixa', 'label' => 'Baixa'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function responsibleOptions(int $limit = 300): array
    {
        return $this->pending->activeAssignableUsers($limit);
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>, item?: array<string, mixed>} */
    public function updateStatus(
        int $pendingId,
        string $status,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($pendingId <= 0) {
            return [
                'ok' => false,
                'message' => 'Pendencia invalida.',
                'errors' => ['Pendencia invalida.'],
            ];
        }

        $normalizedStatus = mb_strtolower(trim($status));
        if (!in_array($normalizedStatus, self::ALLOWED_STATUS, true)) {
            return [
                'ok' => false,
                'message' => 'Status invalido para pendencia.',
                'errors' => ['Status invalido para pendencia.'],
            ];
        }

        $before = $this->pending->findById($pendingId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Pendencia nao encontrada.',
                'errors' => ['Pendencia nao encontrada.'],
            ];
        }

        if ((string) ($before['status'] ?? '') === $normalizedStatus) {
            return [
                'ok' => true,
                'message' => 'Pendencia mantida sem alteracoes.',
                'errors' => [],
                'item' => $before,
            ];
        }

        $updated = $this->pending->updateStatus(
            id: $pendingId,
            status: $normalizedStatus,
            resolvedBy: $normalizedStatus === 'resolvida' && $userId > 0 ? $userId : null
        );

        if (!$updated) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar a pendencia.',
                'errors' => ['Nao foi possivel atualizar a pendencia.'],
            ];
        }

        $after = $this->pending->findById($pendingId) ?? $before;

        $this->audit->log(
            entity: 'analyst_pending_item',
            entityId: $pendingId,
            action: 'status.update',
            beforeData: [
                'status' => $before['status'] ?? null,
                'resolved_by' => $before['resolved_by'] ?? null,
                'resolved_at' => $before['resolved_at'] ?? null,
            ],
            afterData: [
                'status' => $after['status'] ?? null,
                'resolved_by' => $after['resolved_by'] ?? null,
                'resolved_at' => $after['resolved_at'] ?? null,
            ],
            metadata: [
                'person_id' => (int) ($after['person_id'] ?? 0),
                'pending_type' => (string) ($after['pending_type'] ?? ''),
                'source_key' => (string) ($after['source_key'] ?? ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'pending.status_updated',
            payload: [
                'pending_id' => $pendingId,
                'pending_type' => (string) ($after['pending_type'] ?? ''),
                'status' => (string) ($after['status'] ?? ''),
            ],
            entityId: (int) ($after['person_id'] ?? 0),
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => $normalizedStatus === 'resolvida'
                ? 'Pendencia marcada como resolvida.'
                : 'Pendencia reaberta com sucesso.',
            'errors' => [],
            'item' => $after,
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $pendingType = mb_strtolower(trim((string) ($filters['pending_type'] ?? '')));
        if (!in_array($pendingType, self::ALLOWED_TYPES, true)) {
            $pendingType = '';
        }

        $status = mb_strtolower(trim((string) ($filters['status'] ?? '')));
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            $status = '';
        }

        $severity = mb_strtolower(trim((string) ($filters['severity'] ?? '')));
        if (!in_array($severity, self::ALLOWED_SEVERITY, true)) {
            $severity = '';
        }

        $queueScope = mb_strtolower(trim((string) ($filters['queue_scope'] ?? 'all')));
        if (!in_array($queueScope, ['all', 'mine', 'unassigned'], true)) {
            $queueScope = 'all';
        }

        $sort = (string) ($filters['sort'] ?? 'updated_at');
        if (!in_array($sort, ['updated_at', 'created_at', 'due_date', 'person_name', 'pending_type', 'status', 'severity', 'responsible'], true)) {
            $sort = 'updated_at';
        }

        $dir = mb_strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'pending_type' => $pendingType,
            'status' => $status,
            'severity' => $severity,
            'queue_scope' => $queueScope,
            'queue_user_id' => max(0, (int) ($filters['queue_user_id'] ?? 0)),
            'responsible_id' => max(0, (int) ($filters['responsible_id'] ?? 0)),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    private function syncAutoPendencies(int $userId, string $ip, string $userAgent): void
    {
        $assignments = $this->pending->activeAssignmentsForSync(800);
        if ($assignments === []) {
            $this->autoResolveStale([], $userId, $ip, $userAgent);
            return;
        }

        $personIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['person_id'] ?? 0),
            $assignments
        )));
        $personIds = array_values(array_filter($personIds, static fn (int $id): bool => $id > 0));

        $documentRows = $this->pending->documentTypesByPersonIds($personIds);
        $documentsByPerson = [];
        foreach ($documentRows as $documentRow) {
            $personId = (int) ($documentRow['person_id'] ?? 0);
            if ($personId <= 0) {
                continue;
            }

            $normalizedType = $this->normalizeDocumentType((string) ($documentRow['document_type_name'] ?? ''));
            if ($normalizedType === '') {
                continue;
            }

            if (!isset($documentsByPerson[$personId]) || !is_array($documentsByPerson[$personId])) {
                $documentsByPerson[$personId] = [];
            }
            $documentsByPerson[$personId][$normalizedType] = true;
        }

        $divergenceRows = $this->pending->openRequiredDivergencesByPersonIds($personIds);
        $divergencesByPerson = [];
        foreach ($divergenceRows as $divergenceRow) {
            $personId = (int) ($divergenceRow['person_id'] ?? 0);
            if ($personId <= 0) {
                continue;
            }

            if (!isset($divergencesByPerson[$personId]) || !is_array($divergencesByPerson[$personId])) {
                $divergencesByPerson[$personId] = [];
            }

            $divergencesByPerson[$personId][] = $divergenceRow;
        }

        $activeSourceHashes = [];

        foreach ($assignments as $assignment) {
            $personId = (int) ($assignment['person_id'] ?? 0);
            $assignmentId = (int) ($assignment['assignment_id'] ?? 0);
            if ($personId <= 0 || $assignmentId <= 0) {
                continue;
            }

            $statusCode = mb_strtolower(trim((string) ($assignment['status_code'] ?? '')));
            $statusLabel = (string) ($assignment['status_label'] ?? $statusCode);
            $statusOrder = (int) ($assignment['status_order'] ?? 0);
            $updatedAt = (string) ($assignment['assignment_updated_at'] ?? '');
            $assignedUserId = (int) ($assignment['assigned_user_id'] ?? 0);
            $assignee = $assignedUserId > 0 ? $assignedUserId : null;

            $personDocumentFlags = is_array($documentsByPerson[$personId] ?? null)
                ? $documentsByPerson[$personId]
                : [];

            $candidates = [];

            if ($statusOrder >= 4 && !$this->hasDocument($personDocumentFlags, 'oficio_orgao')) {
                $candidates[] = $this->pendingCandidate(
                    personId: $personId,
                    assignmentId: $assignmentId,
                    pendingType: 'documento',
                    sourceKey: 'doc:oficio_orgao',
                    title: 'Oficio ao orgao pendente no dossie',
                    description: 'Status atual exige evidencia de envio de oficio ao orgao de origem.',
                    severity: 'alta',
                    dueDate: null,
                    assignedUserId: $assignee,
                    metadata: [
                        'required_document' => 'Oficio ao orgao',
                        'status_code' => $statusCode,
                    ],
                    createdBy: $userId > 0 ? $userId : null
                );
            }

            if ($statusOrder >= 5 && !$this->hasDocument($personDocumentFlags, 'resposta_orgao')) {
                $candidates[] = $this->pendingCandidate(
                    personId: $personId,
                    assignmentId: $assignmentId,
                    pendingType: 'documento',
                    sourceKey: 'doc:resposta_orgao',
                    title: 'Resposta do orgao pendente no dossie',
                    description: 'Custos recebidos exigem anexo de resposta formal do orgao.',
                    severity: 'alta',
                    dueDate: null,
                    assignedUserId: $assignee,
                    metadata: [
                        'required_document' => 'Resposta do orgao',
                        'status_code' => $statusCode,
                    ],
                    createdBy: $userId > 0 ? $userId : null
                );
            }

            if ($statusOrder >= 8 && !$this->hasDocument($personDocumentFlags, 'publicacao_dou')) {
                $candidates[] = $this->pendingCandidate(
                    personId: $personId,
                    assignmentId: $assignmentId,
                    pendingType: 'documento',
                    sourceKey: 'doc:publicacao_dou',
                    title: 'Publicacao DOU pendente no dossie',
                    description: 'Etapas avancadas exigem comprovacao da publicacao oficial.',
                    severity: 'media',
                    dueDate: null,
                    assignedUserId: $assignee,
                    metadata: [
                        'required_document' => 'Publicacao DOU',
                        'status_code' => $statusCode,
                    ],
                    createdBy: $userId > 0 ? $userId : null
                );
            }

            $personDivergences = is_array($divergencesByPerson[$personId] ?? null)
                ? $divergencesByPerson[$personId]
                : [];

            foreach ($personDivergences as $divergence) {
                $divergenceId = (int) ($divergence['id'] ?? 0);
                if ($divergenceId <= 0) {
                    continue;
                }

                $severity = $this->normalizeDivergenceSeverity((string) ($divergence['severity'] ?? 'media'));
                $difference = (float) ($divergence['difference_amount'] ?? 0);

                $candidates[] = $this->pendingCandidate(
                    personId: $personId,
                    assignmentId: $assignmentId,
                    pendingType: 'divergencia',
                    sourceKey: 'divergencia:' . $divergenceId,
                    title: 'Divergencia financeira sem justificativa',
                    description: sprintf('Divergencia #%d com diferenca de R$ %s requer justificativa.', $divergenceId, number_format($difference, 2, ',', '.')),
                    severity: $severity,
                    dueDate: null,
                    assignedUserId: $assignee,
                    metadata: [
                        'divergence_id' => $divergenceId,
                        'cost_mirror_id' => (int) ($divergence['cost_mirror_id'] ?? 0),
                    ],
                    createdBy: $userId > 0 ? $userId : null
                );
            }

            if (in_array($statusCode, ['oficio_orgao', 'mgi'], true)) {
                $days = $this->daysSince($updatedAt);
                if ($days >= 7) {
                    $candidates[] = $this->pendingCandidate(
                        personId: $personId,
                        assignmentId: $assignmentId,
                        pendingType: 'retorno',
                        sourceKey: 'retorno:' . $statusCode,
                        title: 'Retorno externo pendente',
                        description: sprintf('Caso em "%s" ha %d dia(s) sem avancar para o proximo retorno.', $statusLabel, $days),
                        severity: $days >= 15 ? 'alta' : 'media',
                        dueDate: $this->dueDateFrom($updatedAt, 7),
                        assignedUserId: $assignee,
                        metadata: [
                            'status_code' => $statusCode,
                            'status_label' => $statusLabel,
                            'days_in_status' => $days,
                        ],
                        createdBy: $userId > 0 ? $userId : null
                    );
                }
            }

            foreach ($candidates as $candidate) {
                $sourceHash = $this->sourceHash(
                    (int) $candidate['person_id'],
                    (string) $candidate['pending_type'],
                    (string) $candidate['source_key']
                );
                $activeSourceHashes[] = $sourceHash;

                $before = $this->pending->findBySource(
                    personId: (int) $candidate['person_id'],
                    pendingType: (string) $candidate['pending_type'],
                    sourceKey: (string) $candidate['source_key']
                );

                if ($before === null) {
                    $createdId = $this->pending->create($candidate);
                    $after = $this->pending->findById($createdId);
                    if ($after !== null) {
                        $this->audit->log(
                            entity: 'analyst_pending_item',
                            entityId: $createdId,
                            action: 'create',
                            beforeData: null,
                            afterData: $after,
                            metadata: [
                                'sync' => true,
                            ],
                            userId: $userId,
                            ip: $ip,
                            userAgent: $userAgent
                        );

                        $this->events->recordEvent(
                            entity: 'person',
                            type: 'pending.created',
                            payload: [
                                'pending_id' => $createdId,
                                'pending_type' => (string) ($after['pending_type'] ?? ''),
                                'source_key' => (string) ($after['source_key'] ?? ''),
                            ],
                            entityId: (int) ($after['person_id'] ?? 0),
                            userId: $userId
                        );
                    }
                    continue;
                }

                if ($this->shouldSyncExisting($before, $candidate)) {
                    $this->pending->syncOpen((int) $before['id'], $candidate);
                    $after = $this->pending->findById((int) $before['id']) ?? $before;

                    $this->audit->log(
                        entity: 'analyst_pending_item',
                        entityId: (int) ($before['id'] ?? 0),
                        action: 'sync.update',
                        beforeData: [
                            'title' => $before['title'] ?? null,
                            'description' => $before['description'] ?? null,
                            'severity' => $before['severity'] ?? null,
                            'status' => $before['status'] ?? null,
                            'due_date' => $before['due_date'] ?? null,
                        ],
                        afterData: [
                            'title' => $after['title'] ?? null,
                            'description' => $after['description'] ?? null,
                            'severity' => $after['severity'] ?? null,
                            'status' => $after['status'] ?? null,
                            'due_date' => $after['due_date'] ?? null,
                        ],
                        metadata: [
                            'sync' => true,
                            'source_key' => $before['source_key'] ?? null,
                        ],
                        userId: $userId,
                        ip: $ip,
                        userAgent: $userAgent
                    );
                }
            }
        }

        $activeSourceHashes = array_values(array_unique($activeSourceHashes));
        $this->autoResolveStale($activeSourceHashes, $userId, $ip, $userAgent);
    }

    /** @param array<int, string> $activeSourceHashes */
    private function autoResolveStale(array $activeSourceHashes, int $userId, string $ip, string $userAgent): void
    {
        $stale = $this->pending->openItemsNotInSourceHashes($activeSourceHashes, 3000);

        foreach ($stale as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $before = $item;
            $this->pending->updateStatus(
                id: $itemId,
                status: 'resolvida',
                resolvedBy: $userId > 0 ? $userId : null
            );
            $after = $this->pending->findById($itemId) ?? $before;

            $this->audit->log(
                entity: 'analyst_pending_item',
                entityId: $itemId,
                action: 'auto.resolve',
                beforeData: [
                    'status' => $before['status'] ?? null,
                    'resolved_by' => $before['resolved_by'] ?? null,
                ],
                afterData: [
                    'status' => $after['status'] ?? null,
                    'resolved_by' => $after['resolved_by'] ?? null,
                ],
                metadata: [
                    'sync' => true,
                    'source_key' => $before['source_key'] ?? null,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'pending.auto_resolved',
                payload: [
                    'pending_id' => $itemId,
                    'pending_type' => (string) ($after['pending_type'] ?? ''),
                ],
                entityId: (int) ($after['person_id'] ?? 0),
                userId: $userId
            );
        }
    }

    /** @param array<string, mixed> $existing
     *  @param array<string, mixed> $candidate
     */
    private function shouldSyncExisting(array $existing, array $candidate): bool
    {
        $existingStatus = (string) ($existing['status'] ?? 'aberta');
        $existingDueDate = (string) ($existing['due_date'] ?? '');
        $candidateDueDate = (string) ($candidate['due_date'] ?? '');

        return (string) ($existing['title'] ?? '') !== (string) ($candidate['title'] ?? '')
            || (string) ($existing['description'] ?? '') !== (string) ($candidate['description'] ?? '')
            || (string) ($existing['severity'] ?? '') !== (string) ($candidate['severity'] ?? '')
            || $existingStatus !== 'aberta'
            || $existingDueDate !== $candidateDueDate
            || (int) ($existing['assignment_id'] ?? 0) !== (int) ($candidate['assignment_id'] ?? 0)
            || (int) ($existing['assigned_user_id'] ?? 0) !== (int) ($candidate['assigned_user_id'] ?? 0);
    }

    /** @param array<string, bool> $personDocumentFlags */
    private function hasDocument(array $personDocumentFlags, string $requiredType): bool
    {
        return isset($personDocumentFlags[$requiredType]) && $personDocumentFlags[$requiredType] === true;
    }

    /** @return array<string, mixed> */
    private function pendingCandidate(
        int $personId,
        int $assignmentId,
        string $pendingType,
        string $sourceKey,
        string $title,
        string $description,
        string $severity,
        ?string $dueDate,
        ?int $assignedUserId,
        array $metadata,
        ?int $createdBy
    ): array {
        return [
            'person_id' => $personId,
            'assignment_id' => $assignmentId,
            'pending_type' => $pendingType,
            'source_key' => mb_substr($sourceKey, 0, 140),
            'title' => mb_substr($title, 0, 190),
            'description' => mb_substr($description, 0, 255),
            'severity' => $severity,
            'status' => 'aberta',
            'due_date' => $dueDate,
            'assigned_user_id' => $assignedUserId,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resolved_at' => null,
            'resolved_by' => null,
            'created_by' => $createdBy,
        ];
    }

    private function normalizeDocumentType(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = strtr($normalized, [
            'ã' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'õ' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        if (str_contains($normalized, 'oficio')) {
            return 'oficio_orgao';
        }

        if (str_contains($normalized, 'resposta')) {
            return 'resposta_orgao';
        }

        if (str_contains($normalized, 'publicacao') && str_contains($normalized, 'dou')) {
            return 'publicacao_dou';
        }

        return $normalized;
    }

    private function normalizeDivergenceSeverity(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            'alta' => 'alta',
            'baixa' => 'baixa',
            default => 'media',
        };
    }

    private function daysSince(string $dateTime): int
    {
        if (trim($dateTime) === '') {
            return 0;
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 0;
        }

        return max(0, (int) floor((time() - $timestamp) / 86400));
    }

    private function dueDateFrom(string $baseDateTime, int $days): ?string
    {
        if (trim($baseDateTime) === '') {
            return null;
        }

        $timestamp = strtotime($baseDateTime);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp + (max(0, $days) * 86400));
    }

    private function sourceHash(int $personId, string $pendingType, string $sourceKey): string
    {
        return $personId . '|' . $pendingType . '|' . $sourceKey;
    }
}
