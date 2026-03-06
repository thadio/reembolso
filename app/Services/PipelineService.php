<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\PipelineRepository;

final class PipelineService
{
    private const ALLOWED_ATTACHMENT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_ATTACHMENT_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
    private const MAX_ATTACHMENT_SIZE = 10485760; // 10MB
    private const ALLOWED_QUEUE_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    /** @var array<int, string> */
    private const ALLOWED_MOVEMENT_DIRECTIONS = ['entrada_mte', 'saida_mte'];
    /** @var array<int, string> */
    private const ALLOWED_FINANCIAL_NATURES = ['despesa_reembolso', 'receita_reembolso'];
    private const CHECKLIST_CASE_LABELS = [
        'geral' => 'Geral',
        'cessao' => 'Cessao',
        'cft' => 'Composicao de Forca de Trabalho',
        'requisicao' => 'Requisicao',
    ];

    public function __construct(
        private PipelineRepository $pipeline,
        private AuditService $audit,
        private EventService $events,
        private Config $config,
        private LgpdService $lgpd,
        private SecuritySettingsService $security
    ) {
    }

    /** @return array<string, mixed>|null */
    public function ensureAssignment(
        int $personId,
        ?int $modalityId,
        int $userId,
        string $ip,
        string $userAgent,
        ?array $movementContext = null
    ): ?array
    {
        $movementResolved = $this->resolveMovementContext($personId, is_array($movementContext) ? $movementContext : [], false);
        $movement = is_array($movementResolved['context'] ?? null) ? $movementResolved['context'] : [];

        $flowId = $this->resolvePersonFlowId($personId);
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        if ($assignment !== null) {
            $currentModalityId = isset($assignment['modality_id']) ? (int) $assignment['modality_id'] : null;
            if ($modalityId !== null && $modalityId > 0 && $currentModalityId !== $modalityId) {
                $this->pipeline->updateAssignmentModality((int) $assignment['id'], $modalityId);
                $assignment = $this->pipeline->assignmentByPersonId($personId);
            }

            if ($assignment !== null) {
                $currentDirection = (string) ($assignment['movement_direction'] ?? 'entrada_mte');
                $currentNature = (string) ($assignment['financial_nature'] ?? 'despesa_reembolso');
                $currentCounterparty = isset($assignment['counterparty_organ_id']) ? (int) ($assignment['counterparty_organ_id'] ?? 0) : 0;
                $currentOrigin = isset($assignment['origin_mte_destination_id']) ? (int) ($assignment['origin_mte_destination_id'] ?? 0) : 0;
                $currentDestination = isset($assignment['destination_mte_destination_id']) ? (int) ($assignment['destination_mte_destination_id'] ?? 0) : 0;

                if (
                    $currentDirection !== (string) ($movement['movement_direction'] ?? 'entrada_mte')
                    || $currentNature !== (string) ($movement['financial_nature'] ?? 'despesa_reembolso')
                    || $currentCounterparty !== (int) ($movement['counterparty_organ_id'] ?? 0)
                    || $currentOrigin !== (int) ($movement['origin_mte_destination_id'] ?? 0)
                    || $currentDestination !== (int) ($movement['destination_mte_destination_id'] ?? 0)
                ) {
                    $this->pipeline->updateAssignmentMovementContext(
                        assignmentId: (int) $assignment['id'],
                        movementDirection: (string) ($movement['movement_direction'] ?? 'entrada_mte'),
                        financialNature: (string) ($movement['financial_nature'] ?? 'despesa_reembolso'),
                        counterpartyOrganId: isset($movement['counterparty_organ_id']) ? (int) $movement['counterparty_organ_id'] : null,
                        originMteDestinationId: isset($movement['origin_mte_destination_id']) ? (int) $movement['origin_mte_destination_id'] : null,
                        destinationMteDestinationId: isset($movement['destination_mte_destination_id']) ? (int) $movement['destination_mte_destination_id'] : null
                    );
                    $assignment = $this->pipeline->assignmentByPersonId($personId);
                }
            }

            $assignmentFlowId = (int) ($assignment['flow_id'] ?? 0);
            if ($flowId > 0 && $assignmentFlowId !== $flowId) {
                $this->pipeline->updateAssignmentFlow((int) $assignment['id'], $flowId);
                $assignment = $this->pipeline->assignmentByPersonId($personId);
            }

            if ($assignment !== null) {
                $this->ensureChecklistForAssignment($assignment, $userId > 0 ? $userId : null, $ip, $userAgent);
            }

            return $assignment;
        }

        $initial = $this->pipeline->initialStatus($flowId > 0 ? $flowId : null);
        if ($initial === null) {
            return null;
        }

        $assignmentId = $this->pipeline->createAssignment(
            personId: $personId,
            flowId: $flowId > 0 ? $flowId : null,
            modalityId: ($modalityId !== null && $modalityId > 0) ? $modalityId : null,
            statusId: (int) $initial['id'],
            assignedUserId: $userId > 0 ? $userId : null,
            priorityLevel: 'normal',
            movementDirection: (string) ($movement['movement_direction'] ?? 'entrada_mte'),
            financialNature: (string) ($movement['financial_nature'] ?? 'despesa_reembolso'),
            counterpartyOrganId: isset($movement['counterparty_organ_id']) ? (int) $movement['counterparty_organ_id'] : null,
            originMteDestinationId: isset($movement['origin_mte_destination_id']) ? (int) $movement['origin_mte_destination_id'] : null,
            destinationMteDestinationId: isset($movement['destination_mte_destination_id']) ? (int) $movement['destination_mte_destination_id'] : null,
            movementCode: $this->generateMovementCode($personId)
        );

        $this->pipeline->updatePersonStatus($personId, (string) $initial['code']);

        $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            eventType: 'pipeline.started',
            title: 'Pipeline iniciado',
            description: 'Status inicial definido: ' . (string) $initial['label'],
            createdBy: $userId,
            eventDate: date('Y-m-d H:i:s'),
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
                'flow_id' => $flowId > 0 ? $flowId : null,
                'modality_id' => $modalityId,
                'movement_direction' => $movement['movement_direction'] ?? 'entrada_mte',
                'financial_nature' => $movement['financial_nature'] ?? 'despesa_reembolso',
                'counterparty_organ_id' => $movement['counterparty_organ_id'] ?? null,
                'origin_mte_destination_id' => $movement['origin_mte_destination_id'] ?? null,
                'destination_mte_destination_id' => $movement['destination_mte_destination_id'] ?? null,
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
                'flow_id' => $flowId > 0 ? $flowId : null,
                'status_code' => $initial['code'],
                'status_label' => $initial['label'],
                'movement_direction' => $movement['movement_direction'] ?? 'entrada_mte',
                'financial_nature' => $movement['financial_nature'] ?? 'despesa_reembolso',
            ],
            entityId: $personId,
            userId: $userId
        );
        $createdAssignment = $this->pipeline->assignmentByPersonId($personId);
        if ($createdAssignment !== null) {
            $this->ensureChecklistForAssignment($createdAssignment, $userId > 0 ? $userId : null, $ip, $userAgent);
        }

        return $createdAssignment;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, context: array<string, mixed>}
     */
    public function validateMovementContext(array $input): array
    {
        $resolved = $this->resolveMovementContext(0, $input, true);

        return [
            'ok' => ($resolved['errors'] ?? []) === [],
            'errors' => $resolved['errors'] ?? [],
            'context' => $resolved['context'] ?? [],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function movementDirectionOptions(): array
    {
        return [
            ['value' => 'entrada_mte', 'label' => 'Recebimento no MTE (MTE paga reembolso)'],
            ['value' => 'saida_mte', 'label' => 'Cessao para fora do MTE (MTE recebe reembolso)'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function financialNatureOptions(): array
    {
        return [
            ['value' => 'despesa_reembolso', 'label' => 'Despesa de reembolso (a pagar)'],
            ['value' => 'receita_reembolso', 'label' => 'Receita de reembolso (a receber)'],
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   assignment?: array<string, mixed>,
     *   next_status?: array<string, mixed>|null,
     *   available_transitions?: array<int, array<string, mixed>>
     * }
     */
    public function advance(int $personId, ?int $transitionId, int $userId, string $ip, string $userAgent): array
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

        $flowId = (int) ($assignment['flow_id'] ?? 0);
        if ($flowId <= 0) {
            $flowId = $this->resolvePersonFlowId($personId);
            if ($flowId > 0) {
                $this->pipeline->updateAssignmentFlow((int) $assignment['id'], $flowId);
                $assignment = $this->pipeline->assignmentByPersonId($personId) ?? $assignment;
            }
        }

        if ($flowId <= 0) {
            return [
                'ok' => false,
                'message' => 'Fluxo nao configurado para esta pessoa.',
                'assignment' => $assignment,
                'next_status' => null,
                'available_transitions' => [],
            ];
        }

        $currentStatusId = (int) ($assignment['current_status_id'] ?? 0);
        $currentCode = (string) ($assignment['current_status_code'] ?? '');
        $currentLabel = (string) ($assignment['current_status_label'] ?? '');
        $availableTransitions = $this->pipeline->transitionsFromStatus($flowId, $currentStatusId);

        if ($availableTransitions === []) {
            return [
                'ok' => false,
                'message' => 'Pessoa ja esta em uma etapa final deste fluxo.',
                'assignment' => $assignment,
                'next_status' => null,
                'available_transitions' => [],
            ];
        }

        $selectedTransition = null;
        if ($transitionId !== null && $transitionId > 0) {
            $candidate = $this->pipeline->transitionById($flowId, $transitionId);
            if ($candidate === null || (int) ($candidate['from_status_id'] ?? 0) !== $currentStatusId) {
                return [
                    'ok' => false,
                    'message' => 'Transicao selecionada nao e valida para a etapa atual.',
                    'assignment' => $assignment,
                    'next_status' => null,
                    'available_transitions' => $availableTransitions,
                ];
            }

            $selectedTransition = $candidate;
        } elseif (count($availableTransitions) === 1) {
            $selectedTransition = $availableTransitions[0];
        } else {
            return [
                'ok' => false,
                'message' => 'Escolha a proxima transicao para continuar o fluxo.',
                'assignment' => $assignment,
                'next_status' => null,
                'available_transitions' => $availableTransitions,
            ];
        }

        $next = [
            'id' => (int) ($selectedTransition['to_status_id'] ?? 0),
            'code' => (string) ($selectedTransition['to_code'] ?? ''),
            'label' => (string) ($selectedTransition['to_label'] ?? ''),
            'event_type' => (string) ($selectedTransition['event_type'] ?? ''),
            'next_action_label' => (string) ($selectedTransition['action_label'] ?? ''),
        ];

        if ((int) ($next['id'] ?? 0) <= 0 || (string) ($next['code'] ?? '') === '') {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel identificar a etapa de destino da transicao.',
                'assignment' => $assignment,
                'next_status' => null,
                'available_transitions' => $availableTransitions,
            ];
        }

        $isFinalNode = (string) ($selectedTransition['to_node_kind'] ?? '') === 'final';
        $effectiveStartDate = ((string) $next['code'] === 'ativo' || $isFinalNode) ? date('Y-m-d') : null;

        $this->pipeline->updateAssignmentStatus(
            assignmentId: (int) $assignment['id'],
            statusId: (int) $next['id'],
            effectiveStartDate: $effectiveStartDate
        );

        $this->pipeline->updatePersonStatus($personId, (string) $next['code']);

        $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: (int) $assignment['id'],
            eventType: (string) ($next['event_type'] !== '' ? $next['event_type'] : 'pipeline.status_changed'),
            title: 'Status alterado para ' . (string) $next['label'],
            description: sprintf(
                'Transicao do fluxo: %s -> %s%s',
                $currentLabel,
                (string) $next['label'],
                trim((string) ($selectedTransition['transition_label'] ?? '')) !== ''
                    ? ' (' . (string) $selectedTransition['transition_label'] . ')'
                    : ''
            ),
            createdBy: $userId,
            eventDate: date('Y-m-d H:i:s'),
            metadata: [
                'flow_id' => $flowId,
                'transition_id' => (int) ($selectedTransition['id'] ?? 0),
                'transition_label' => $selectedTransition['transition_label'] ?? null,
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
            metadata: [
                'flow_id' => $flowId,
                'transition_id' => (int) ($selectedTransition['id'] ?? 0),
                'transition_label' => $selectedTransition['transition_label'] ?? null,
            ],
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
                'flow_id' => $flowId,
                'transition_id' => (int) ($selectedTransition['id'] ?? 0),
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
            'available_transitions' => $this->pipeline->transitionsFromStatus($flowId, (int) ($next['id'] ?? 0)),
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>, assignment?: array<string, mixed>}
     */
    public function updateQueue(
        int $personId,
        int $assignmentId,
        ?int $assignedUserId,
        string $priorityLevel,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        if ($assignment === null) {
            return [
                'ok' => false,
                'message' => 'Pipeline nao inicializado para esta pessoa.',
                'errors' => ['Pipeline nao inicializado para esta pessoa.'],
            ];
        }

        if ((int) ($assignment['id'] ?? 0) !== $assignmentId) {
            return [
                'ok' => false,
                'message' => 'Movimentacao invalida para atualizacao da fila.',
                'errors' => ['Movimentacao invalida para atualizacao da fila.'],
            ];
        }

        $normalizedPriority = $this->normalizeQueuePriority($priorityLevel);
        if ($normalizedPriority === null) {
            return [
                'ok' => false,
                'message' => 'Prioridade invalida.',
                'errors' => ['Prioridade invalida.'],
            ];
        }

        if ($assignedUserId !== null && $assignedUserId > 0 && !$this->pipeline->userExists($assignedUserId)) {
            return [
                'ok' => false,
                'message' => 'Responsavel informado nao foi encontrado.',
                'errors' => ['Responsavel informado nao foi encontrado.'],
            ];
        }

        $normalizedAssignedUserId = ($assignedUserId !== null && $assignedUserId > 0) ? $assignedUserId : null;
        $currentAssignedUserId = isset($assignment['assigned_user_id']) ? (int) $assignment['assigned_user_id'] : 0;
        $currentPriority = (string) ($assignment['priority_level'] ?? 'normal');

        if ($currentAssignedUserId === (int) ($normalizedAssignedUserId ?? 0) && $currentPriority === $normalizedPriority) {
            return [
                'ok' => true,
                'message' => 'Fila mantida sem alteracoes.',
                'errors' => [],
                'assignment' => $assignment,
            ];
        }

        $updated = $this->pipeline->updateAssignmentQueue($assignmentId, $normalizedAssignedUserId, $normalizedPriority);
        if (!$updated) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar a fila.',
                'errors' => ['Nao foi possivel atualizar a fila.'],
            ];
        }

        $after = $this->pipeline->assignmentByPersonId($personId) ?? $assignment;

        $this->audit->log(
            entity: 'assignment',
            entityId: $assignmentId,
            action: 'queue.update',
            beforeData: [
                'assigned_user_id' => $assignment['assigned_user_id'] ?? null,
                'priority_level' => $assignment['priority_level'] ?? 'normal',
            ],
            afterData: [
                'assigned_user_id' => $after['assigned_user_id'] ?? null,
                'priority_level' => $after['priority_level'] ?? 'normal',
            ],
            metadata: [
                'person_id' => $personId,
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'pipeline.queue_updated',
            payload: [
                'assignment_id' => $assignmentId,
                'assigned_user_id' => $after['assigned_user_id'] ?? null,
                'priority_level' => $after['priority_level'] ?? 'normal',
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Fila atualizada com sucesso.',
            'errors' => [],
            'assignment' => $after,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   errors: array<int, string>,
     *   checklist?: array{
     *     case_type: string,
     *     case_type_label: string,
     *     items: array<int, array<string, mixed>>,
     *     summary: array{total: int, completed: int, required_total: int, required_completed: int, percent: int}
     *   }
     * }
     */
    public function updateChecklistItem(
        int $personId,
        int $assignmentId,
        int $itemId,
        bool $isDone,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        if ($assignment === null) {
            return [
                'ok' => false,
                'message' => 'Pipeline nao inicializado para esta pessoa.',
                'errors' => ['Pipeline nao inicializado para esta pessoa.'],
            ];
        }

        if ((int) ($assignment['id'] ?? 0) !== $assignmentId) {
            return [
                'ok' => false,
                'message' => 'Movimentacao invalida para checklist.',
                'errors' => ['Movimentacao invalida para checklist.'],
            ];
        }

        $checklist = $this->ensureChecklistForAssignment($assignment, $userId > 0 ? $userId : null, $ip, $userAgent);
        $item = $this->pipeline->checklistItemById($assignmentId, $itemId);
        if ($item === null) {
            return [
                'ok' => false,
                'message' => 'Item de checklist nao encontrado.',
                'errors' => ['Item de checklist nao encontrado.'],
                'checklist' => $checklist,
            ];
        }

        $currentDone = (int) ($item['is_done'] ?? 0) === 1;
        if ($currentDone === $isDone) {
            return [
                'ok' => true,
                'message' => 'Checklist mantido sem alteracoes.',
                'errors' => [],
                'checklist' => $checklist,
            ];
        }

        $updated = $this->pipeline->updateChecklistItemStatus(
            assignmentId: $assignmentId,
            itemId: $itemId,
            isDone: $isDone,
            doneBy: $isDone && $userId > 0 ? $userId : null
        );

        if (!$updated) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar o checklist.',
                'errors' => ['Nao foi possivel atualizar o checklist.'],
                'checklist' => $checklist,
            ];
        }

        $afterItem = $this->pipeline->checklistItemById($assignmentId, $itemId) ?? $item;

        $this->audit->log(
            entity: 'assignment_checklist_item',
            entityId: $itemId,
            action: 'status.update',
            beforeData: [
                'item_code' => (string) ($item['item_code'] ?? ''),
                'item_label' => (string) ($item['item_label'] ?? ''),
                'is_done' => (int) ($item['is_done'] ?? 0),
                'done_by' => $item['done_by'] ?? null,
            ],
            afterData: [
                'item_code' => (string) ($afterItem['item_code'] ?? ''),
                'item_label' => (string) ($afterItem['item_label'] ?? ''),
                'is_done' => (int) ($afterItem['is_done'] ?? 0),
                'done_by' => $afterItem['done_by'] ?? null,
            ],
            metadata: [
                'person_id' => $personId,
                'assignment_id' => $assignmentId,
                'case_type' => (string) ($afterItem['case_type'] ?? ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'pipeline.checklist_item_updated',
            payload: [
                'assignment_id' => $assignmentId,
                'item_id' => $itemId,
                'item_code' => (string) ($afterItem['item_code'] ?? ''),
                'is_done' => (int) ($afterItem['is_done'] ?? 0),
            ],
            entityId: $personId,
            userId: $userId
        );

        $refreshedAssignment = $this->pipeline->assignmentByPersonId($personId) ?? $assignment;
        $refreshedChecklist = $this->ensureChecklistForAssignment($refreshedAssignment, null, null, null);

        return [
            'ok' => true,
            'message' => $isDone
                ? 'Item do checklist marcado como concluido.'
                : 'Item do checklist marcado como pendente.',
            'errors' => [],
            'checklist' => $refreshedChecklist,
        ];
    }

    /**
     * @return array{ok: bool, message: string, warnings: array<int, string>, errors: array<int, string>}
     */
    public function addManualEvent(
        int $personId,
        ?int $assignmentId,
        array $input,
        array $files,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $eventType = trim((string) ($input['event_type'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $eventDateInput = trim((string) ($input['event_date'] ?? ''));

        $errors = [];

        $types = $this->pipeline->activeTimelineEventTypes();
        $validTypes = array_map(static fn (array $row): string => (string) $row['name'], $types);

        if ($eventType === '' || !in_array($eventType, $validTypes, true)) {
            $errors[] = 'Tipo de evento inválido.';
        }

        if ($title === '' || mb_strlen($title) < 3) {
            $errors[] = 'Título do evento é obrigatório (mínimo 3 caracteres).';
        }

        $eventDate = $this->normalizeDateTime($eventDateInput);
        if ($eventDate === null) {
            $errors[] = 'Data do evento inválida.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível registrar evento manual.',
                'warnings' => [],
                'errors' => $errors,
            ];
        }

        $assignmentContext = $this->pipeline->assignmentByPersonId($personId);
        $effectiveAssignmentId = $assignmentId;
        if (($effectiveAssignmentId === null || $effectiveAssignmentId <= 0) && $assignmentContext !== null) {
            $resolvedAssignmentId = (int) ($assignmentContext['id'] ?? 0);
            $effectiveAssignmentId = $resolvedAssignmentId > 0 ? $resolvedAssignmentId : null;
        }

        $eventMetadata = ['manual' => true];
        if ($assignmentContext !== null) {
            $flowId = (int) ($assignmentContext['flow_id'] ?? 0);
            $flowName = trim((string) ($assignmentContext['flow_name'] ?? ''));
            $statusCode = trim((string) ($assignmentContext['current_status_code'] ?? ''));
            $statusLabel = trim((string) ($assignmentContext['current_status_label'] ?? ''));

            if ($flowId > 0) {
                $eventMetadata['flow_id'] = $flowId;
            }
            if ($flowName !== '') {
                $eventMetadata['flow_name'] = $flowName;
            }
            if ($statusCode !== '') {
                $eventMetadata['pipeline_status_code'] = $statusCode;
            }
            if ($statusLabel !== '') {
                $eventMetadata['pipeline_status_label'] = $statusLabel;
            }
        }

        $eventId = $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: $effectiveAssignmentId,
            eventType: $eventType,
            title: $title,
            description: $description === '' ? null : $description,
            createdBy: $userId,
            eventDate: $eventDate,
            metadata: $eventMetadata
        );

        $warnings = $this->storeAttachments($personId, $eventId, $files, $userId);

        $this->audit->log(
            entity: 'timeline_event',
            entityId: $eventId,
            action: 'create',
            beforeData: null,
            afterData: [
                'event_type' => $eventType,
                'title' => $title,
            ],
            metadata: $eventMetadata,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'timeline.manual_event',
            payload: [
                'event_type' => $eventType,
                'title' => $title,
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Evento manual registrado com sucesso.',
            'warnings' => $warnings,
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, message: string, warnings: array<int, string>, errors: array<int, string>}
     */
    public function rectifyEvent(
        int $personId,
        ?int $assignmentId,
        int $sourceEventId,
        string $note,
        array $files,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $source = $this->pipeline->findTimelineEventById($sourceEventId, $personId);
        if ($source === null) {
            return [
                'ok' => false,
                'message' => 'Evento original não encontrado para retificação.',
                'warnings' => [],
                'errors' => ['Evento original inválido.'],
            ];
        }

        $trimmedNote = trim($note);
        if ($trimmedNote === '') {
            return [
                'ok' => false,
                'message' => 'Informe a justificativa da retificação.',
                'warnings' => [],
                'errors' => ['Justificativa de retificação é obrigatória.'],
            ];
        }

        $title = 'Retificação: ' . (string) ($source['title'] ?? 'Evento');
        $description = $trimmedNote;
        $assignmentContext = $this->pipeline->assignmentByPersonId($personId);
        $effectiveAssignmentId = $assignmentId;
        if (($effectiveAssignmentId === null || $effectiveAssignmentId <= 0) && $assignmentContext !== null) {
            $resolvedAssignmentId = (int) ($assignmentContext['id'] ?? 0);
            $effectiveAssignmentId = $resolvedAssignmentId > 0 ? $resolvedAssignmentId : null;
        }

        $rectificationMetadata = [
            'rectifies_event_id' => $sourceEventId,
            'source_event_type' => (string) ($source['event_type'] ?? ''),
        ];
        if ($assignmentContext !== null) {
            $flowId = (int) ($assignmentContext['flow_id'] ?? 0);
            $flowName = trim((string) ($assignmentContext['flow_name'] ?? ''));
            $statusCode = trim((string) ($assignmentContext['current_status_code'] ?? ''));
            $statusLabel = trim((string) ($assignmentContext['current_status_label'] ?? ''));

            if ($flowId > 0) {
                $rectificationMetadata['flow_id'] = $flowId;
            }
            if ($flowName !== '') {
                $rectificationMetadata['flow_name'] = $flowName;
            }
            if ($statusCode !== '') {
                $rectificationMetadata['pipeline_status_code'] = $statusCode;
            }
            if ($statusLabel !== '') {
                $rectificationMetadata['pipeline_status_label'] = $statusLabel;
            }
        }

        $eventId = $this->pipeline->insertTimelineEvent(
            personId: $personId,
            assignmentId: $effectiveAssignmentId,
            eventType: 'retificacao',
            title: $title,
            description: $description,
            createdBy: $userId,
            eventDate: date('Y-m-d H:i:s'),
            metadata: $rectificationMetadata
        );

        $warnings = $this->storeAttachments($personId, $eventId, $files, $userId);

        $this->audit->log(
            entity: 'timeline_event',
            entityId: $eventId,
            action: 'rectify',
            beforeData: [
                'source_event_id' => $sourceEventId,
                'source_title' => $source['title'] ?? null,
            ],
            afterData: [
                'title' => $title,
                'description' => $description,
            ],
            metadata: $rectificationMetadata,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'timeline.rectified',
            payload: [
                'source_event_id' => $sourceEventId,
                'new_event_id' => $eventId,
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Retificação registrada com sucesso.',
            'warnings' => $warnings,
            'errors' => [],
        ];
    }

    /**
     * @return array{
     *   assignment: array<string, mixed>|null,
     *   flow: array<string, mixed>|null,
     *   statuses: array<int, array<string, mixed>>,
     *   next_status: array<string, mixed>|null,
     *   available_transitions: array<int, array<string, mixed>>,
     *   timeline: array<int, array<string, mixed>>,
     *   timeline_pagination: array<string, int>,
     *   event_types: array<int, array<string, mixed>>,
     *   queue_priorities: array<int, array{value: string, label: string}>,
     *   queue_users: array<int, array<string, mixed>>,
     *   checklist: array{
     *     case_type: string,
     *     case_type_label: string,
     *     items: array<int, array<string, mixed>>,
     *     summary: array{total: int, completed: int, required_total: int, required_completed: int, percent: int}
     *   }
     * }
     */
    public function profileData(
        int $personId,
        int $timelinePage = 1,
        int $timelinePerPage = 10,
        ?int $actorUserId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array
    {
        $assignment = $this->pipeline->assignmentByPersonId($personId);
        $flow = null;
        $statuses = [];
        $availableTransitions = [];
        $checklist = $this->emptyChecklist();

        $nextStatus = null;
        if ($assignment !== null) {
            $flowId = (int) ($assignment['flow_id'] ?? 0);
            if ($flowId <= 0) {
                $flowId = $this->resolvePersonFlowId($personId);
                if ($flowId > 0) {
                    $this->pipeline->updateAssignmentFlow((int) ($assignment['id'] ?? 0), $flowId);
                    $assignment = $this->pipeline->assignmentByPersonId($personId) ?? $assignment;
                }
            }

            $flow = $flowId > 0 ? $this->pipeline->activeFlowById($flowId) : null;
            $statuses = $flowId > 0 ? $this->pipeline->statusesForFlow($flowId) : [];
            if ($statuses === []) {
                $statuses = $this->pipeline->allStatuses();
            }
            $availableTransitions = $flowId > 0
                ? $this->pipeline->transitionsFromStatus($flowId, (int) ($assignment['current_status_id'] ?? 0))
                : [];

            if (count($availableTransitions) === 1) {
                $transition = $availableTransitions[0];
                $nextStatus = [
                    'id' => (int) ($transition['to_status_id'] ?? 0),
                    'code' => (string) ($transition['to_code'] ?? ''),
                    'label' => (string) ($transition['to_label'] ?? ''),
                    'next_action_label' => (string) ($transition['action_label'] ?? ''),
                    'event_type' => (string) ($transition['event_type'] ?? ''),
                    'transition_id' => (int) ($transition['id'] ?? 0),
                    'transition_label' => (string) ($transition['transition_label'] ?? ''),
                ];
            }

            $checklist = $this->ensureChecklistForAssignment($assignment, $actorUserId, $ip, $userAgent);
        } else {
            $defaultFlow = $this->pipeline->defaultFlow();
            if ($defaultFlow !== null) {
                $flow = $defaultFlow;
                $statuses = $this->pipeline->statusesForFlow((int) ($defaultFlow['id'] ?? 0));
            } else {
                $statuses = $this->pipeline->allStatuses();
            }
        }

        $timelinePageResult = $this->pipeline->timelinePaginateByPerson($personId, $timelinePage, $timelinePerPage);

        return [
            'assignment' => $assignment,
            'flow' => $flow,
            'statuses' => $statuses,
            'next_status' => $nextStatus,
            'available_transitions' => $availableTransitions,
            'timeline' => $timelinePageResult['items'],
            'timeline_pagination' => [
                'total' => $timelinePageResult['total'],
                'page' => $timelinePageResult['page'],
                'per_page' => $timelinePageResult['per_page'],
                'pages' => $timelinePageResult['pages'],
            ],
            'event_types' => $this->pipeline->activeTimelineEventTypes(),
            'queue_priorities' => $this->queuePriorities(),
            'queue_users' => $this->queueUsers(),
            'checklist' => $checklist,
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function queuePriorities(): array
    {
        return [
            ['value' => 'low', 'label' => 'Baixa'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'high', 'label' => 'Alta'],
            ['value' => 'urgent', 'label' => 'Urgente'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function queueUsers(int $limit = 300): array
    {
        return $this->pipeline->activeAssignableUsers($limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeFlows(): array
    {
        return $this->pipeline->activeFlows();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   context: array<string, mixed>,
     *   errors: array<int, string>
     * }
     */
    private function resolveMovementContext(int $personId, array $input, bool $strict): array
    {
        $defaults = $personId > 0 ? ($this->pipeline->personMovementDefaults($personId) ?? []) : [];

        $rawDirection = mb_strtolower(trim((string) ($input['movement_direction'] ?? '')));
        $direction = in_array($rawDirection, self::ALLOWED_MOVEMENT_DIRECTIONS, true)
            ? $rawDirection
            : 'entrada_mte';

        $rawNature = mb_strtolower(trim((string) ($input['financial_nature'] ?? '')));
        $expectedNature = $this->financialNatureFromDirection($direction);
        $financialNature = in_array($rawNature, self::ALLOWED_FINANCIAL_NATURES, true)
            ? $rawNature
            : $expectedNature;

        $counterpartyOrganId = max(0, (int) ($input['counterparty_organ_id'] ?? ($defaults['organ_id'] ?? 0)));
        $originMteDestinationId = max(0, (int) ($input['origin_mte_destination_id'] ?? $input['mte_origin_destination_id'] ?? 0));
        $destinationMteDestinationId = max(0, (int) ($input['destination_mte_destination_id'] ?? $input['mte_destination_id'] ?? 0));

        $errors = [];

        if ($counterpartyOrganId > 0 && !$this->pipeline->organExists($counterpartyOrganId)) {
            $errors[] = 'Orgao de contraparte invalido para o movimento.';
        }

        if ($originMteDestinationId > 0 && !$this->pipeline->mteDestinationExistsById($originMteDestinationId)) {
            $errors[] = 'Lotacao de origem MTE invalida.';
        }

        if ($destinationMteDestinationId > 0 && !$this->pipeline->mteDestinationExistsById($destinationMteDestinationId)) {
            $errors[] = 'Lotacao de destino MTE invalida.';
        }

        if ($strict) {
            if ($counterpartyOrganId <= 0) {
                $errors[] = 'Orgao de contraparte e obrigatorio para abrir o movimento.';
            }

            if ($direction === 'entrada_mte' && $destinationMteDestinationId <= 0) {
                $errors[] = 'Lotacao de destino no MTE e obrigatoria para movimento de entrada.';
            }

            if ($direction === 'saida_mte' && $originMteDestinationId <= 0) {
                $errors[] = 'Lotacao de origem no MTE e obrigatoria para movimento de saida.';
            }

            if ($financialNature !== $expectedNature) {
                $errors[] = 'Natureza financeira invalida para a direcao selecionada.';
            }
        }

        if ($direction === 'entrada_mte') {
            $originMteDestinationId = 0;
            $financialNature = 'despesa_reembolso';
        } else {
            $destinationMteDestinationId = 0;
            $financialNature = 'receita_reembolso';
        }

        return [
            'context' => [
                'movement_direction' => $direction,
                'financial_nature' => $financialNature,
                'counterparty_organ_id' => $counterpartyOrganId > 0 ? $counterpartyOrganId : null,
                'origin_mte_destination_id' => $originMteDestinationId > 0 ? $originMteDestinationId : null,
                'destination_mte_destination_id' => $destinationMteDestinationId > 0 ? $destinationMteDestinationId : null,
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function financialNatureFromDirection(string $direction): string
    {
        return $direction === 'saida_mte' ? 'receita_reembolso' : 'despesa_reembolso';
    }

    private function generateMovementCode(int $personId): string
    {
        try {
            $suffix = strtoupper(bin2hex(random_bytes(3)));
        } catch (\Throwable) {
            $suffix = strtoupper((string) mt_rand(100000, 999999));
        }

        return sprintf('MOV-%d-%s-%s', $personId, date('YmdHis'), $suffix);
    }

    /**
     * @return array{
     *   case_type: string,
     *   case_type_label: string,
     *   items: array<int, array<string, mixed>>,
     *   summary: array{total: int, completed: int, required_total: int, required_completed: int, percent: int}
     * }
     */
    private function emptyChecklist(): array
    {
        return [
            'case_type' => 'geral',
            'case_type_label' => $this->checklistCaseTypeLabel('geral'),
            'items' => [],
            'summary' => [
                'total' => 0,
                'completed' => 0,
                'required_total' => 0,
                'required_completed' => 0,
                'percent' => 0,
            ],
        ];
    }

    /**
     * @return array{
     *   case_type: string,
     *   case_type_label: string,
     *   items: array<int, array<string, mixed>>,
     *   summary: array{total: int, completed: int, required_total: int, required_completed: int, percent: int}
     * }
     */
    private function ensureChecklistForAssignment(
        array $assignment,
        ?int $actorUserId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $assignmentId = (int) ($assignment['id'] ?? 0);
        if ($assignmentId <= 0) {
            return $this->emptyChecklist();
        }

        $caseType = $this->checklistCaseTypeFromAssignment($assignment);
        $caseLabel = $this->checklistCaseTypeLabel($caseType);

        try {
            $templates = $this->pipeline->activeChecklistTemplatesForCaseType($caseType);
            $existingItems = $this->pipeline->checklistItemsByAssignment($assignmentId, $caseType);
            $existingKeys = [];
            foreach ($existingItems as $existingItem) {
                $existingKeys[] = mb_strtolower(
                    trim((string) ($existingItem['case_type'] ?? 'geral')) . '|' . trim((string) ($existingItem['item_code'] ?? ''))
                );
            }

            $generatedItems = [];
            foreach ($templates as $template) {
                $templateCaseType = mb_strtolower(trim((string) ($template['case_type'] ?? 'geral')));
                if ($templateCaseType === '') {
                    $templateCaseType = 'geral';
                }

                $code = trim((string) ($template['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $key = mb_strtolower($templateCaseType . '|' . $code);
                if (!in_array($key, $existingKeys, true)) {
                    $generatedItems[] = $templateCaseType . ':' . $code;
                }

                $this->pipeline->upsertChecklistItemFromTemplate(
                    assignmentId: $assignmentId,
                    templateId: (int) ($template['id'] ?? 0),
                    caseType: $templateCaseType,
                    code: $code,
                    label: (string) ($template['label'] ?? $code),
                    description: ($template['description'] ?? null) !== null
                        ? (string) $template['description']
                        : null,
                    isRequired: (int) ($template['is_required'] ?? 1) === 1 ? 1 : 0
                );
            }

            $items = $this->pipeline->checklistItemsByAssignment($assignmentId, $caseType);
            $summary = $this->checklistSummary($items);

            if ($generatedItems !== [] && $actorUserId !== null && $actorUserId > 0 && $ip !== null && $userAgent !== null) {
                $this->audit->log(
                    entity: 'assignment_checklist',
                    entityId: $assignmentId,
                    action: 'auto.generate',
                    beforeData: null,
                    afterData: [
                        'case_type' => $caseType,
                        'generated_items' => $generatedItems,
                    ],
                    metadata: [
                        'person_id' => (int) ($assignment['person_id'] ?? 0),
                    ],
                    userId: $actorUserId,
                    ip: $ip,
                    userAgent: $userAgent
                );

                $this->events->recordEvent(
                    entity: 'person',
                    type: 'pipeline.checklist_generated',
                    payload: [
                        'assignment_id' => $assignmentId,
                        'case_type' => $caseType,
                        'generated_count' => count($generatedItems),
                    ],
                    entityId: (int) ($assignment['person_id'] ?? 0),
                    userId: $actorUserId
                );
            }

            return [
                'case_type' => $caseType,
                'case_type_label' => $caseLabel,
                'items' => $items,
                'summary' => $summary,
            ];
        } catch (\Throwable) {
            return $this->emptyChecklist();
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function checklistSummary(array $items): array
    {
        $total = count($items);
        $completed = 0;
        $requiredTotal = 0;
        $requiredCompleted = 0;

        foreach ($items as $item) {
            $isRequired = (int) ($item['is_required'] ?? 1) === 1;
            $isDone = (int) ($item['is_done'] ?? 0) === 1;

            if ($isDone) {
                $completed++;
            }

            if ($isRequired) {
                $requiredTotal++;
                if ($isDone) {
                    $requiredCompleted++;
                }
            }
        }

        $baseTotal = $requiredTotal > 0 ? $requiredTotal : $total;
        $baseCompleted = $requiredTotal > 0 ? $requiredCompleted : $completed;
        $percent = $baseTotal > 0 ? (int) round(($baseCompleted / $baseTotal) * 100) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'percent' => max(0, min(100, $percent)),
        ];
    }

    /** @param array<string, mixed> $assignment */
    private function checklistCaseTypeFromAssignment(array $assignment): string
    {
        $raw = mb_strtolower(trim((string) ($assignment['modality_name'] ?? '')));
        if ($raw === '') {
            return 'geral';
        }

        $normalized = strtr($raw, [
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

        if (str_contains($normalized, 'cess')) {
            return 'cessao';
        }

        if (str_contains($normalized, 'forca') || str_contains($normalized, 'cft')) {
            return 'cft';
        }

        if (str_contains($normalized, 'requis')) {
            return 'requisicao';
        }

        return 'geral';
    }

    private function checklistCaseTypeLabel(string $caseType): string
    {
        return self::CHECKLIST_CASE_LABELS[$caseType] ?? self::CHECKLIST_CASE_LABELS['geral'];
    }

    /** @return array<int, array<string, mixed>> */
    public function fullTimeline(int $personId, int $limit = 300): array
    {
        return $this->pipeline->fullTimelineByPerson($personId, $limit);
    }

    /** @return array{path: string, original_name: string, mime_type: string}|null */
    public function attachmentForDownload(int $attachmentId, int $personId, int $userId, string $ip, string $userAgent): ?array
    {
        $attachment = $this->pipeline->findAttachmentById($attachmentId);
        if ($attachment === null) {
            return null;
        }

        if ((int) ($attachment['event_person_id'] ?? 0) !== $personId) {
            return null;
        }

        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $relative = ltrim((string) ($attachment['storage_path'] ?? ''), '/');
        $path = $base . '/' . $relative;

        if (!is_file($path)) {
            return null;
        }

        $this->audit->log(
            entity: 'timeline_attachment',
            entityId: (int) ($attachment['id'] ?? 0),
            action: 'download',
            beforeData: null,
            afterData: [
                'person_id' => $personId,
                'timeline_event_id' => (int) ($attachment['timeline_event_id'] ?? 0),
                'original_name' => (string) ($attachment['original_name'] ?? ''),
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'timeline.attachment_downloaded',
            payload: [
                'attachment_id' => (int) ($attachment['id'] ?? 0),
                'timeline_event_id' => (int) ($attachment['timeline_event_id'] ?? 0),
            ],
            entityId: $personId,
            userId: $userId
        );

        $this->lgpd->registerSensitiveAccess(
            entity: 'timeline_attachment',
            entityId: (int) ($attachment['id'] ?? 0),
            action: 'timeline_attachment_download',
            sensitivity: 'attachment',
            subjectPersonId: $personId,
            subjectLabel: (string) ($attachment['original_name'] ?? ''),
            contextPath: '/people/timeline/attachment',
            metadata: [
                'timeline_event_id' => (int) ($attachment['timeline_event_id'] ?? 0),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        return [
            'path' => $path,
            'original_name' => (string) ($attachment['original_name'] ?? 'anexo'),
            'mime_type' => (string) ($attachment['mime_type'] ?? 'application/octet-stream'),
        ];
    }

    /** @return array<int, string> */
    private function storeAttachments(int $personId, int $eventId, array $files, int $userId): array
    {
        $warnings = [];
        $normalizedFiles = $this->normalizeFilesArray($files['attachments'] ?? null);

        if ($normalizedFiles === []) {
            return $warnings;
        }

        $baseUploads = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($baseUploads === '') {
            $warnings[] = 'Diretório de uploads não configurado.';

            return $warnings;
        }

        $subDir = sprintf('timeline/%d/%s', $personId, date('Y/m'));
        $targetDir = $baseUploads . '/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $warnings[] = 'Não foi possível preparar o diretório de anexos.';

            return $warnings;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $maxBytes = $this->maxAttachmentBytes();
        $maxMb = max(1, (int) ceil($maxBytes / 1048576));

        foreach ($normalizedFiles as $file) {
            $name = (string) ($file['name'] ?? '');
            $tmpName = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $warnings[] = 'Falha ao processar anexo: ' . $name;
                continue;
            }

            if (!UploadSecurityService::isSafeOriginalName($name)) {
                $warnings[] = 'Nome de arquivo invalido: ' . $name;
                continue;
            }

            if (!UploadSecurityService::isNativeUploadedFile($tmpName)) {
                $warnings[] = 'Upload invalido ou nao confiavel: ' . $name;
                continue;
            }

            if ($size <= 0 || $size > $maxBytes) {
                $warnings[] = sprintf('Arquivo fora do limite permitido (%dMB): %s', $maxMb, $name);
                continue;
            }

            $ext = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                $warnings[] = 'Extensão não permitida: ' . $name;
                continue;
            }

            $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
            if (!in_array($mime, self::ALLOWED_ATTACHMENT_MIME, true)) {
                $warnings[] = 'Tipo de arquivo não permitido: ' . $name;
                continue;
            }

            if (!UploadSecurityService::matchesKnownSignature($tmpName, $mime)) {
                $warnings[] = 'Assinatura binaria invalida para o tipo informado: ' . $name;
                continue;
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                $warnings[] = 'Não foi possível salvar anexo: ' . $name;
                continue;
            }

            $relativePath = $subDir . '/' . $storedName;

            $this->pipeline->createAttachment(
                eventId: $eventId,
                personId: $personId,
                originalName: $name,
                storedName: $storedName,
                mimeType: $mime,
                fileSize: $size,
                storagePath: $relativePath,
                uploadedBy: $userId
            );
        }

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return $warnings;
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function normalizeFilesArray(mixed $raw): array
    {
        if (!is_array($raw) || !isset($raw['name'])) {
            return [];
        }

        if (!is_array($raw['name'])) {
            return [[
                'name' => (string) ($raw['name'] ?? ''),
                'type' => (string) ($raw['type'] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
                'error' => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'] ?? 0),
            ]];
        }

        $files = [];
        foreach ($raw['name'] as $index => $name) {
            $files[] = [
                'name' => (string) $name,
                'type' => (string) ($raw['type'][$index] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
                'error' => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    private function normalizeDateTime(string $input): ?string
    {
        if ($input === '') {
            return date('Y-m-d H:i:s');
        }

        $normalized = str_replace('T', ' ', $input);
        if (mb_strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        $time = strtotime($normalized);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    private function maxAttachmentBytes(): int
    {
        $globalLimit = max(1048576, $this->security->uploadMaxBytes());

        return min(self::MAX_ATTACHMENT_SIZE, $globalLimit);
    }

    private function resolvePersonFlowId(int $personId): int
    {
        $personFlow = $this->pipeline->personFlow($personId);
        $personFlowId = (int) ($personFlow['assignment_flow_id'] ?? 0);
        $isPersonFlowActive = (int) ($personFlow['flow_is_active'] ?? 0) === 1;

        if ($personFlowId > 0 && $isPersonFlowActive) {
            return $personFlowId;
        }

        $defaultFlow = $this->pipeline->defaultFlow();
        $defaultFlowId = $defaultFlow !== null ? (int) ($defaultFlow['id'] ?? 0) : 0;

        if ($defaultFlowId > 0) {
            $this->pipeline->updatePersonFlow($personId, $defaultFlowId);
        }

        return $defaultFlowId;
    }

    private function normalizeQueuePriority(string $priority): ?string
    {
        $normalized = mb_strtolower(trim($priority));

        if (!in_array($normalized, self::ALLOWED_QUEUE_PRIORITIES, true)) {
            return null;
        }

        return $normalized;
    }
}
