<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PipelineRepository;

final class PipelineService
{
    public function __construct(
        private PipelineRepository $pipeline,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /** @return array<string, mixed>|null */
    public function ensureAssignment(int $personId, ?int $modalityId, int $userId, string $ip, string $userAgent): ?array
    {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        if ($assignment !== null) {
            $currentModalityId = isset($assignment['modality_id']) ? (int) $assignment['modality_id'] : null;
            if ($modalityId !== null && $modalityId > 0 && $currentModalityId !== $modalityId) {
                $this->pipeline->updateAssignmentModality((int) $assignment['id'], $modalityId);
                $assignment = $this->pipeline->assignmentByPersonId($personId);
            }

            return $assignment;
        }

        $initial = $this->pipeline->initialStatus();
        if ($initial === null) {
            return null;
        }

        $assignmentId = $this->pipeline->createAssignment(
            personId: $personId,
            modalityId: ($modalityId !== null && $modalityId > 0) ? $modalityId : null,
            statusId: (int) $initial['id']
        );

        $this->pipeline->updatePersonStatus($personId, (string) $initial['code']);

        $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            eventType: 'pipeline.started',
            title: 'Pipeline iniciado',
            description: 'Status inicial definido: ' . (string) $initial['label'],
            createdBy: $userId,
            metadata: [
                'status_code' => $initial['code'],
                'status_label' => $initial['label'],
            ]
        );

        $this->audit->log(
            entity: 'assignment',
            entityId: $assignmentId,
            action: 'create',
            beforeData: null,
            afterData: [
                'person_id' => $personId,
                'modality_id' => $modalityId,
                'status_code' => $initial['code'],
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'pipeline.started',
            payload: [
                'person_id' => $personId,
                'status_code' => $initial['code'],
                'status_label' => $initial['label'],
            ],
            entityId: $personId,
            userId: $userId
        );

        return $this->pipeline->assignmentByPersonId($personId);
    }

    /**
     * @return array{ok: bool, message: string, assignment?: array<string, mixed>, next_status?: array<string, mixed>|null}
     */
    public function advance(int $personId, int $userId, string $ip, string $userAgent): array
    {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        if ($assignment === null) {
            $assignment = $this->ensureAssignment($personId, null, $userId, $ip, $userAgent);
            if ($assignment === null) {
                return [
                    'ok' => false,
                    'message' => 'Pipeline não configurado. Verifique os status cadastrados.',
                ];
            }
        }

        $currentOrder = (int) ($assignment['current_status_order'] ?? 0);
        $currentCode = (string) ($assignment['current_status_code'] ?? '');
        $currentLabel = (string) ($assignment['current_status_label'] ?? '');
        $next = $this->pipeline->nextStatus($currentOrder);

        if ($next === null) {
            return [
                'ok' => false,
                'message' => 'Pessoa já está no status final do pipeline.',
                'assignment' => $assignment,
                'next_status' => null,
            ];
        }

        $effectiveStartDate = ((string) $next['code'] === 'ativo') ? date('Y-m-d') : null;

        $this->pipeline->updateAssignmentStatus(
            assignmentId: (int) $assignment['id'],
            statusId: (int) $next['id'],
            effectiveStartDate: $effectiveStartDate
        );

        $this->pipeline->updatePersonStatus($personId, (string) $next['code']);

        $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: (int) $assignment['id'],
            eventType: (string) ($next['event_type'] ?? 'pipeline.status_changed'),
            title: 'Status alterado para ' . (string) $next['label'],
            description: sprintf('Transição do pipeline: %s -> %s', $currentLabel, (string) $next['label']),
            createdBy: $userId,
            metadata: [
                'from_code' => $currentCode,
                'from_label' => $currentLabel,
                'to_code' => $next['code'],
                'to_label' => $next['label'],
            ]
        );

        $this->audit->log(
            entity: 'assignment',
            entityId: (int) $assignment['id'],
            action: 'status.advance',
            beforeData: [
                'status_code' => $currentCode,
                'status_label' => $currentLabel,
            ],
            afterData: [
                'status_code' => $next['code'],
                'status_label' => $next['label'],
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'pipeline.status_changed',
            payload: [
                'from' => $currentCode,
                'to' => $next['code'],
                'label' => $next['label'],
            ],
            entityId: $personId,
            userId: $userId
        );

        $updatedAssignment = $this->pipeline->assignmentByPersonId($personId);

        return [
            'ok' => true,
            'message' => 'Status atualizado para ' . (string) $next['label'] . '.',
            'assignment' => $updatedAssignment,
            'next_status' => $next,
        ];
    }

    /**
     * @return array{assignment: array<string, mixed>|null, statuses: array<int, array<string, mixed>>, next_status: array<string, mixed>|null, timeline: array<int, array<string, mixed>>}
     */
    public function profileData(int $personId): array
    {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        $statuses = $this->pipeline->allStatuses();

        $nextStatus = null;
        if ($assignment !== null) {
            $nextStatus = $this->pipeline->nextStatus((int) ($assignment['current_status_order'] ?? 0));
        }

        $timeline = $this->pipeline->timelineByPerson($personId, 40);

        return [
            'assignment' => $assignment,
            'statuses' => $statuses,
            'next_status' => $nextStatus,
            'timeline' => $timeline,
        ];
    }
}
