<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProcessAdminTimelineRepository;

final class ProcessAdminTimelineService
{
    private const ALLOWED_NOTE_STATUS = ['aberto', 'concluido'];
    private const ALLOWED_NOTE_SEVERITY = ['baixa', 'media', 'alta'];
    private const ALLOWED_SOURCE_FILTER = [
        'nota_manual',
        'comentario_processo',
        'pendencia_operacional',
        'financeiro_reembolso',
        'metadado_processo',
        'timeline_operacional',
    ];
    private const ALLOWED_STATUS_GROUP = ['aberto', 'concluido'];

    public function __construct(
        private ProcessAdminTimelineRepository $timeline,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   summary: array<string, int>,
     *   items: array<int, array<string, mixed>>,
     *   pagination: array<string, int>,
     *   filters: array<string, string>,
     *   source_options: array<int, array{value: string, label: string}>,
     *   status_group_options: array<int, array{value: string, label: string}>
     * }
     */
    public function profileData(int $personId, array $filters, int $page, int $perPage): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        $rows = [];
        foreach ($this->timeline->manualNotesByPerson($personId, 200) as $row) {
            $rows[] = $this->mapManualNote($row);
        }
        foreach ($this->timeline->processCommentsByPerson($personId, 220) as $row) {
            $rows[] = $this->mapProcessComment($row);
        }
        foreach ($this->timeline->pendingItemsByPerson($personId, 220) as $row) {
            $rows[] = $this->mapPending($row);
        }
        foreach ($this->timeline->reimbursementsByPerson($personId, 220) as $row) {
            $rows[] = $this->mapReimbursement($row);
        }
        foreach ($this->timeline->processMetadataByPerson($personId, 50) as $row) {
            $rows[] = $this->mapProcessMetadata($row);
        }
        foreach ($this->timeline->operationalTimelineByPerson($personId, 260) as $row) {
            $rows[] = $this->mapOperationalTimeline($row);
        }

        $filtered = array_values(array_filter(
            $rows,
            fn (array $entry): bool => $this->matchesFilters($entry, $normalizedFilters)
        ));

        usort($filtered, function (array $left, array $right): int {
            $leftPinned = (int) ($left['is_pinned'] ?? 0);
            $rightPinned = (int) ($right['is_pinned'] ?? 0);
            if ($leftPinned !== $rightPinned) {
                return $rightPinned <=> $leftPinned;
            }

            $leftTs = strtotime((string) ($left['event_at'] ?? ''));
            $rightTs = strtotime((string) ($right['event_at'] ?? ''));
            $leftTs = $leftTs === false ? 0 : $leftTs;
            $rightTs = $rightTs === false ? 0 : $rightTs;
            if ($leftTs !== $rightTs) {
                return $rightTs <=> $leftTs;
            }

            return strcmp((string) ($right['source_id'] ?? ''), (string) ($left['source_id'] ?? ''));
        });

        $summary = $this->buildSummary($filtered);
        $pagination = $this->paginate($filtered, $page, $perPage);

        return [
            'summary' => $summary,
            'items' => $pagination['items'],
            'pagination' => [
                'total' => $pagination['total'],
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'pages' => $pagination['pages'],
            ],
            'filters' => $normalizedFilters,
            'source_options' => $this->sourceOptions(),
            'status_group_options' => $this->statusGroupOptions(),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function noteStatusOptions(): array
    {
        return [
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'concluido', 'label' => 'Concluido'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function noteSeverityOptions(): array
    {
        return [
            ['value' => 'baixa', 'label' => 'Baixa'],
            ['value' => 'media', 'label' => 'Media'],
            ['value' => 'alta', 'label' => 'Alta'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function createManualNote(
        int $personId,
        ?int $defaultAssignmentId,
        array $input,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $assignmentId = max(0, (int) ($input['assignment_id'] ?? ($defaultAssignmentId ?? 0)));
        $title = $this->cleanText($input['title'] ?? null, 190);
        $description = $this->cleanText($input['description'] ?? null, 5000);
        $status = $this->normalizeStatus($input['status'] ?? 'aberto');
        $severity = $this->normalizeSeverity($input['severity'] ?? 'media');
        $isPinned = $this->isTruthy($input['is_pinned'] ?? null);
        $eventAtRaw = $this->cleanText($input['event_at'] ?? null, 30);
        $eventAt = $this->normalizeEventAt($eventAtRaw);

        $errors = $this->validateManualNote(
            personId: $personId,
            title: $title,
            status: $status,
            severity: $severity,
            assignmentId: $assignmentId > 0 ? $assignmentId : null,
            eventAtRaw: $eventAtRaw,
            eventAt: $eventAt
        );
        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar nota na timeline administrativa.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        try {
            $this->timeline->beginTransaction();

            $noteId = $this->timeline->createManualNote(
                personId: $personId,
                assignmentId: $assignmentId > 0 ? $assignmentId : null,
                title: $title,
                description: $description,
                status: $status,
                severity: $severity,
                isPinned: $isPinned,
                eventAt: $eventAt ?? date('Y-m-d H:i:s'),
                createdBy: $userId > 0 ? $userId : null
            );

            $this->audit->log(
                entity: 'process_admin_timeline_note',
                entityId: $noteId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'person_id' => $personId,
                    'assignment_id' => $assignmentId > 0 ? $assignmentId : null,
                    'title' => $title,
                    'status' => $status,
                    'severity' => $severity,
                    'is_pinned' => $isPinned ? 1 : 0,
                    'event_at' => $eventAt ?? date('Y-m-d H:i:s'),
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_admin_timeline.note_created',
                payload: [
                    'note_id' => $noteId,
                    'status' => $status,
                    'severity' => $severity,
                    'is_pinned' => $isPinned,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->timeline->commit();
        } catch (\Throwable $exception) {
            $this->timeline->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar nota na timeline administrativa.',
                'errors' => ['Falha ao persistir nota da timeline administrativa. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Nota da timeline administrativa registrada com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function updateManualNote(
        int $personId,
        int $noteId,
        array $input,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($noteId <= 0) {
            return [
                'ok' => false,
                'message' => 'Nota da timeline administrativa invalida.',
                'errors' => ['Nota da timeline administrativa invalida.'],
                'warnings' => [],
            ];
        }

        $before = $this->timeline->findManualNoteByIdForPerson($noteId, $personId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Nota da timeline administrativa nao encontrada.',
                'errors' => ['Nota da timeline administrativa nao encontrada para esta pessoa.'],
                'warnings' => [],
            ];
        }

        $title = $this->cleanText($input['title'] ?? ($before['title'] ?? ''), 190);
        $description = $this->cleanText($input['description'] ?? ($before['description'] ?? null), 5000);
        $status = $this->normalizeStatus($input['status'] ?? ($before['status'] ?? 'aberto'));
        $severity = $this->normalizeSeverity($input['severity'] ?? ($before['severity'] ?? 'media'));
        $isPinned = array_key_exists('is_pinned', $input)
            ? $this->isTruthy($input['is_pinned'])
            : ((int) ($before['is_pinned'] ?? 0) === 1);
        $eventAtRaw = $this->cleanText($input['event_at'] ?? ($before['event_at'] ?? null), 30);
        $eventAt = $this->normalizeEventAt($eventAtRaw);
        $assignmentId = isset($before['assignment_id']) ? (int) $before['assignment_id'] : null;

        $errors = $this->validateManualNote(
            personId: $personId,
            title: $title,
            status: $status,
            severity: $severity,
            assignmentId: $assignmentId,
            eventAtRaw: $eventAtRaw,
            eventAt: $eventAt
        );
        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar nota da timeline administrativa.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        $statusBefore = (string) ($before['status'] ?? 'aberto');
        $changed = (
            (string) ($before['title'] ?? '') !== $title
            || (string) ($before['description'] ?? '') !== (string) ($description ?? '')
            || (string) ($before['status'] ?? '') !== $status
            || (string) ($before['severity'] ?? '') !== $severity
            || ((int) ($before['is_pinned'] ?? 0) === 1) !== $isPinned
            || (string) ($before['event_at'] ?? '') !== (string) ($eventAt ?? '')
        );

        if (!$changed) {
            return [
                'ok' => true,
                'message' => 'Nota mantida sem alteracoes.',
                'errors' => [],
                'warnings' => [],
            ];
        }

        try {
            $this->timeline->beginTransaction();

            $updated = $this->timeline->updateManualNote(
                noteId: $noteId,
                title: $title,
                description: $description,
                status: $status,
                severity: $severity,
                isPinned: $isPinned,
                eventAt: $eventAt ?? date('Y-m-d H:i:s'),
                updatedBy: $userId > 0 ? $userId : null
            );

            if (!$updated) {
                throw new \RuntimeException('update_failed');
            }

            $after = $this->timeline->findManualNoteByIdForPerson($noteId, $personId) ?? $before;

            $this->audit->log(
                entity: 'process_admin_timeline_note',
                entityId: $noteId,
                action: 'update',
                beforeData: [
                    'title' => $before['title'] ?? '',
                    'description' => $before['description'] ?? '',
                    'status' => $before['status'] ?? '',
                    'severity' => $before['severity'] ?? '',
                    'is_pinned' => (int) ($before['is_pinned'] ?? 0),
                    'event_at' => $before['event_at'] ?? '',
                ],
                afterData: [
                    'title' => $after['title'] ?? $title,
                    'description' => $after['description'] ?? $description,
                    'status' => $after['status'] ?? $status,
                    'severity' => $after['severity'] ?? $severity,
                    'is_pinned' => (int) ($after['is_pinned'] ?? ($isPinned ? 1 : 0)),
                    'event_at' => $after['event_at'] ?? ($eventAt ?? ''),
                ],
                metadata: [
                    'person_id' => $personId,
                    'assignment_id' => $assignmentId,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            if ($statusBefore !== $status) {
                $this->audit->log(
                    entity: 'process_admin_timeline_note',
                    entityId: $noteId,
                    action: 'status.update',
                    beforeData: ['status' => $statusBefore],
                    afterData: ['status' => $status],
                    metadata: ['person_id' => $personId],
                    userId: $userId,
                    ip: $ip,
                    userAgent: $userAgent
                );
            }

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_admin_timeline.note_updated',
                payload: [
                    'note_id' => $noteId,
                    'status' => (string) ($after['status'] ?? $status),
                    'severity' => (string) ($after['severity'] ?? $severity),
                    'is_pinned' => (int) ($after['is_pinned'] ?? ($isPinned ? 1 : 0)) === 1,
                ],
                entityId: $personId,
                userId: $userId
            );

            if ($statusBefore !== $status) {
                $this->events->recordEvent(
                    entity: 'person',
                    type: 'process_admin_timeline.note_status_updated',
                    payload: [
                        'note_id' => $noteId,
                        'from_status' => $statusBefore,
                        'to_status' => $status,
                    ],
                    entityId: $personId,
                    userId: $userId
                );
            }

            $this->timeline->commit();
        } catch (\Throwable $exception) {
            $this->timeline->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar nota da timeline administrativa.',
                'errors' => ['Falha ao atualizar nota da timeline administrativa. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Nota da timeline administrativa atualizada com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>} */
    public function deleteManualNote(
        int $personId,
        int $noteId,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($noteId <= 0) {
            return [
                'ok' => false,
                'message' => 'Nota da timeline administrativa invalida.',
                'errors' => ['Nota da timeline administrativa invalida.'],
                'warnings' => [],
            ];
        }

        $before = $this->timeline->findManualNoteByIdForPerson($noteId, $personId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Nota da timeline administrativa nao encontrada.',
                'errors' => ['Nota da timeline administrativa nao encontrada para esta pessoa.'],
                'warnings' => [],
            ];
        }

        try {
            $this->timeline->beginTransaction();

            $deleted = $this->timeline->softDeleteManualNote(
                noteId: $noteId,
                deletedBy: $userId > 0 ? $userId : null
            );
            if (!$deleted) {
                throw new \RuntimeException('delete_failed');
            }

            $this->audit->log(
                entity: 'process_admin_timeline_note',
                entityId: $noteId,
                action: 'delete',
                beforeData: [
                    'title' => $before['title'] ?? '',
                    'status' => $before['status'] ?? '',
                    'severity' => $before['severity'] ?? '',
                    'is_pinned' => (int) ($before['is_pinned'] ?? 0),
                ],
                afterData: ['deleted' => true],
                metadata: [
                    'person_id' => $personId,
                    'assignment_id' => isset($before['assignment_id']) ? (int) $before['assignment_id'] : null,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_admin_timeline.note_deleted',
                payload: ['note_id' => $noteId],
                entityId: $personId,
                userId: $userId
            );

            $this->timeline->commit();
        } catch (\Throwable $exception) {
            $this->timeline->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel excluir nota da timeline administrativa.',
                'errors' => ['Falha ao excluir nota da timeline administrativa. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Nota da timeline administrativa removida com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /** @return array<string, string> */
    private function normalizeFilters(array $filters): array
    {
        $source = mb_strtolower(trim((string) ($filters['source'] ?? '')));
        if (!in_array($source, self::ALLOWED_SOURCE_FILTER, true)) {
            $source = '';
        }

        $statusGroup = mb_strtolower(trim((string) ($filters['status_group'] ?? '')));
        if (!in_array($statusGroup, self::ALLOWED_STATUS_GROUP, true)) {
            $statusGroup = '';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'source' => $source,
            'status_group' => $statusGroup,
        ];
    }

    /** @return array<string, int> */
    private function buildSummary(array $entries): array
    {
        $total = count($entries);
        $open = 0;
        $closed = 0;
        $manual = 0;
        $automated = 0;

        foreach ($entries as $entry) {
            $statusGroup = (string) ($entry['status_group'] ?? '');
            if ($statusGroup === 'aberto') {
                $open++;
            } else {
                $closed++;
            }

            if ((string) ($entry['source_kind'] ?? '') === 'nota_manual') {
                $manual++;
            } else {
                $automated++;
            }
        }

        return [
            'total' => $total,
            'open_count' => $open,
            'closed_count' => $closed,
            'manual_count' => $manual,
            'automated_count' => $automated,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    private function paginate(array $entries, int $page, int $perPage): array
    {
        $total = count($entries);
        $perPage = max(5, min(80, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($entries, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function sourceOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todas as origens'],
            ['value' => 'nota_manual', 'label' => 'Nota manual'],
            ['value' => 'comentario_processo', 'label' => 'Comentarios internos'],
            ['value' => 'pendencia_operacional', 'label' => 'Pendencias operacionais'],
            ['value' => 'financeiro_reembolso', 'label' => 'Financeiro de reembolso'],
            ['value' => 'metadado_processo', 'label' => 'Metadados formais'],
            ['value' => 'timeline_operacional', 'label' => 'Timeline operacional'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function statusGroupOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os status'],
            ['value' => 'aberto', 'label' => 'Abertos'],
            ['value' => 'concluido', 'label' => 'Concluidos'],
        ];
    }

    /** @param array<string, string> $filters */
    private function matchesFilters(array $entry, array $filters): bool
    {
        $sourceFilter = $filters['source'] ?? '';
        if ($sourceFilter !== '' && (string) ($entry['source_kind'] ?? '') !== $sourceFilter) {
            return false;
        }

        $statusGroup = $filters['status_group'] ?? '';
        if ($statusGroup !== '' && (string) ($entry['status_group'] ?? '') !== $statusGroup) {
            return false;
        }

        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q === '') {
            return true;
        }

        $haystack = mb_strtolower(
            implode(' ', [
                (string) ($entry['source_label'] ?? ''),
                (string) ($entry['title'] ?? ''),
                (string) ($entry['description'] ?? ''),
                (string) ($entry['actor_name'] ?? ''),
                (string) ($entry['status_label'] ?? ''),
            ])
        );

        return str_contains($haystack, $q);
    }

    /** @return array<string, mixed> */
    private function mapManualNote(array $row): array
    {
        $statusRaw = (string) ($row['status'] ?? 'aberto');
        $severity = $this->normalizeSeverity($row['severity'] ?? 'media');

        return [
            'entry_id' => 'manual:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'nota_manual',
            'source_label' => 'Nota manual',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
            'title' => (string) ($row['title'] ?? 'Nota administrativa'),
            'description' => (string) ($row['description'] ?? ''),
            'status_raw' => $statusRaw,
            'status_label' => $statusRaw === 'aberto' ? 'Aberto' : 'Concluido',
            'status_group' => $statusRaw === 'aberto' ? 'aberto' : 'concluido',
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'is_pinned' => (int) ($row['is_pinned'] ?? 0) === 1 ? 1 : 0,
            'event_at' => (string) ($row['event_at'] ?? $row['created_at'] ?? ''),
            'actor_name' => (string) (($row['updated_by_name'] ?? $row['created_by_name']) ?? 'Sistema'),
            'is_manual' => 1,
            'can_edit' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function mapProcessComment(array $row): array
    {
        $statusRaw = (string) ($row['status'] ?? 'aberto');
        $statusGroup = $statusRaw === 'aberto' ? 'aberto' : 'concluido';
        $severity = (int) ($row['is_pinned'] ?? 0) === 1 ? 'media' : 'baixa';

        return [
            'entry_id' => 'comment:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'comentario_processo',
            'source_label' => 'Comentario interno',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
            'title' => 'Comentario interno',
            'description' => (string) ($row['comment_text'] ?? ''),
            'status_raw' => $statusRaw,
            'status_label' => $statusRaw === 'aberto' ? 'Aberto' : 'Arquivado',
            'status_group' => $statusGroup,
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'is_pinned' => (int) ($row['is_pinned'] ?? 0) === 1 ? 1 : 0,
            'event_at' => (string) (($row['updated_at'] ?? $row['created_at']) ?? ''),
            'actor_name' => (string) (($row['updated_by_name'] ?? $row['created_by_name']) ?? 'Sistema'),
            'is_manual' => 0,
            'can_edit' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function mapPending(array $row): array
    {
        $statusRaw = (string) ($row['status'] ?? 'aberta');
        $severity = $this->normalizeSeverity($row['severity'] ?? 'media');
        $statusGroup = $statusRaw === 'aberta' ? 'aberto' : 'concluido';

        return [
            'entry_id' => 'pending:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'pendencia_operacional',
            'source_label' => 'Pendencia operacional',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
            'title' => (string) ($row['title'] ?? 'Pendencia operacional'),
            'description' => (string) ($row['description'] ?? ''),
            'status_raw' => $statusRaw,
            'status_label' => $statusRaw === 'aberta' ? 'Aberta' : 'Resolvida',
            'status_group' => $statusGroup,
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'is_pinned' => 0,
            'event_at' => (string) (($row['updated_at'] ?? $row['created_at']) ?? ''),
            'actor_name' => (string) (($row['resolved_by_name'] ?? $row['created_by_name']) ?? 'Sistema'),
            'is_manual' => 0,
            'can_edit' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function mapReimbursement(array $row): array
    {
        $statusRaw = (string) ($row['status'] ?? 'pendente');
        $statusGroup = $statusRaw === 'pendente' ? 'aberto' : 'concluido';
        $isOverdue = $statusRaw === 'pendente'
            && trim((string) ($row['due_date'] ?? '')) !== ''
            && strtotime((string) $row['due_date']) !== false
            && strtotime((string) $row['due_date']) < strtotime(date('Y-m-d'));

        $severity = 'baixa';
        if ($isOverdue) {
            $severity = 'alta';
        } elseif ($statusRaw === 'pendente') {
            $severity = 'media';
        }

        $amount = (string) ($row['amount'] ?? '0');
        $entryType = (string) ($row['entry_type'] ?? '');
        $title = (string) ($row['title'] ?? 'Lancamento financeiro');

        return [
            'entry_id' => 'reimbursement:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'financeiro_reembolso',
            'source_label' => 'Financeiro de reembolso',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
            'title' => $title,
            'description' => sprintf('Tipo: %s | Valor: %s', $entryType, $amount),
            'status_raw' => $statusRaw,
            'status_label' => $this->reimbursementStatusLabel($statusRaw, $isOverdue),
            'status_group' => $statusGroup,
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'is_pinned' => 0,
            'event_at' => (string) (($row['updated_at'] ?? $row['created_at']) ?? ''),
            'actor_name' => (string) ($row['created_by_name'] ?? 'Sistema'),
            'is_manual' => 0,
            'can_edit' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function mapProcessMetadata(array $row): array
    {
        $parts = [];
        if (trim((string) ($row['office_number'] ?? '')) !== '') {
            $parts[] = 'Oficio: ' . (string) $row['office_number'];
        }
        if (trim((string) ($row['office_protocol'] ?? '')) !== '') {
            $parts[] = 'Protocolo: ' . (string) $row['office_protocol'];
        }
        if (trim((string) ($row['dou_edition'] ?? '')) !== '') {
            $parts[] = 'DOU: ' . (string) $row['dou_edition'];
        }
        if (trim((string) ($row['mte_entry_date'] ?? '')) !== '') {
            $parts[] = 'Entrada MTE: ' . (string) $row['mte_entry_date'];
        }

        return [
            'entry_id' => 'metadata:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'metadado_processo',
            'source_label' => 'Metadado formal',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => null,
            'title' => 'Metadados formais atualizados',
            'description' => implode(' | ', $parts),
            'status_raw' => 'registrado',
            'status_label' => 'Registrado',
            'status_group' => 'concluido',
            'severity' => 'baixa',
            'severity_label' => $this->severityLabel('baixa'),
            'is_pinned' => 0,
            'event_at' => (string) (($row['updated_at'] ?? $row['created_at']) ?? ''),
            'actor_name' => (string) ($row['created_by_name'] ?? 'Sistema'),
            'is_manual' => 0,
            'can_edit' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function mapOperationalTimeline(array $row): array
    {
        $eventType = (string) ($row['event_type'] ?? 'timeline');
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = 'Evento de timeline: ' . str_replace('_', ' ', $eventType);
        }

        return [
            'entry_id' => 'timeline:' . (string) ((int) ($row['id'] ?? 0)),
            'source_kind' => 'timeline_operacional',
            'source_label' => 'Timeline operacional',
            'source_id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
            'title' => $title,
            'description' => (string) ($row['description'] ?? ''),
            'status_raw' => 'registrado',
            'status_label' => 'Registrado',
            'status_group' => 'concluido',
            'severity' => 'baixa',
            'severity_label' => $this->severityLabel('baixa'),
            'is_pinned' => 0,
            'event_at' => (string) (($row['event_date'] ?? $row['created_at']) ?? ''),
            'actor_name' => (string) ($row['created_by_name'] ?? 'Sistema'),
            'is_manual' => 0,
            'can_edit' => 0,
        ];
    }

    /** @return array<int, string> */
    private function validateManualNote(
        int $personId,
        string $title,
        string $status,
        string $severity,
        ?int $assignmentId,
        ?string $eventAtRaw,
        ?string $eventAt
    ): array {
        $errors = [];

        if ($personId <= 0) {
            $errors[] = 'Pessoa invalida para nota administrativa.';
        }

        if (mb_strlen($title) < 3) {
            $errors[] = 'Titulo da nota administrativa deve ter no minimo 3 caracteres.';
        }

        if (!in_array($status, self::ALLOWED_NOTE_STATUS, true)) {
            $errors[] = 'Status invalido para nota administrativa.';
        }

        if (!in_array($severity, self::ALLOWED_NOTE_SEVERITY, true)) {
            $errors[] = 'Severidade invalida para nota administrativa.';
        }

        if ($assignmentId !== null && $assignmentId > 0 && !$this->timeline->assignmentBelongsToPerson($assignmentId, $personId)) {
            $errors[] = 'Movimentacao informada nao pertence a pessoa selecionada.';
        }

        if ($eventAtRaw !== null && $eventAt === null) {
            $errors[] = 'Data/hora do evento administrativo invalida.';
        }

        return $errors;
    }

    private function cleanText(mixed $value, int $maxLen): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $maxLen);
    }

    private function normalizeStatus(mixed $value): string
    {
        $candidate = mb_strtolower(trim((string) $value));

        return in_array($candidate, self::ALLOWED_NOTE_STATUS, true) ? $candidate : 'aberto';
    }

    private function normalizeSeverity(mixed $value): string
    {
        $candidate = mb_strtolower(trim((string) $value));

        return in_array($candidate, self::ALLOWED_NOTE_SEVERITY, true) ? $candidate : 'media';
    }

    private function normalizeEventAt(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return date('Y-m-d H:i:s');
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed . ' 00:00:00';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'alta' => 'Alta',
            'baixa' => 'Baixa',
            default => 'Media',
        };
    }

    private function reimbursementStatusLabel(string $status, bool $isOverdue): string
    {
        if ($isOverdue) {
            return 'Vencido';
        }

        return match ($status) {
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            default => ucfirst($status),
        };
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
