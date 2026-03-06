<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PipelineFlowRepository;

final class PipelineFlowService
{
    private const NODE_KINDS = ['activity', 'gateway', 'final'];

    public function __construct(
        private PipelineFlowRepository $flows,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->flows->paginate($query, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $flowId): ?array
    {
        return $this->flows->findFlowById($flowId);
    }

    /**
     * @return array{
     *   flow: array<string, mixed>|null,
     *   steps: array<int, array<string, mixed>>,
     *   transitions: array<int, array<string, mixed>>,
     *   status_catalog: array<int, array<string, mixed>>,
     *   node_kind_options: array<int, array{value: string, label: string}>,
     *   document_type_catalog: array<int, array<string, mixed>>
     * }
     */
    public function detailData(int $flowId): array
    {
        $flow = $this->flows->findFlowById($flowId);
        $steps = $flow !== null ? $this->flows->flowSteps($flowId) : [];
        $stepDocumentTypeMap = $flow !== null ? $this->flows->flowStepDocumentTypesMap($flowId) : [];

        foreach ($steps as $index => $step) {
            $stepId = (int) ($step['id'] ?? 0);
            $documentTypes = $stepId > 0 && is_array($stepDocumentTypeMap[$stepId] ?? null)
                ? $stepDocumentTypeMap[$stepId]
                : [];

            $steps[$index]['expected_document_types'] = $documentTypes;
            $steps[$index]['expected_document_type_ids'] = array_values(array_map(
                static fn (array $row): int => (int) ($row['document_type_id'] ?? 0),
                $documentTypes
            ));
        }

        return [
            'flow' => $flow,
            'steps' => $steps,
            'transitions' => $flow !== null ? $this->flows->flowTransitions($flowId) : [],
            'status_catalog' => $this->flows->statusCatalog(),
            'node_kind_options' => $this->nodeKindOptions(),
            'document_type_catalog' => $this->flows->documentTypeCatalog(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validateFlowInput($input, null);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->flows->flowNameExists((string) $validation['data']['name'])) {
            return [
                'ok' => false,
                'errors' => ['Ja existe fluxo com este nome.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->flows->createFlow($validation['data']);
        if ((int) ($validation['data']['is_default'] ?? 0) === 1) {
            $this->flows->setDefaultFlow($id);
        }

        $this->audit->log(
            entity: 'assignment_flow',
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
            entity: 'assignment_flow',
            type: 'assignment_flow.created',
            payload: [
                'flow_id' => $id,
                'name' => $validation['data']['name'],
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
    public function update(int $flowId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->flows->findFlowById($flowId);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateFlowInput($input, $before);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->flows->flowNameExists((string) $validation['data']['name'], $flowId)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe fluxo com este nome.'],
                'data' => $validation['data'],
            ];
        }

        $this->flows->updateFlow($flowId, $validation['data']);
        if ((int) ($validation['data']['is_default'] ?? 0) === 1) {
            $this->flows->setDefaultFlow($flowId);
        } elseif ((int) ($before['is_default'] ?? 0) === 1) {
            $fallbackFlowId = $this->flows->fallbackFlowId($flowId);
            if ($fallbackFlowId !== null) {
                $this->flows->setDefaultFlow($fallbackFlowId);
            }
        }

        $this->audit->log(
            entity: 'assignment_flow',
            entityId: $flowId,
            action: 'update',
            beforeData: $before,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.updated',
            payload: [
                'flow_id' => $flowId,
                'name' => $validation['data']['name'],
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function updateDiagram(int $flowId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $flow = $this->flows->findFlowById($flowId);
        if ($flow === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado para atualizar o diagrama BPMN.'],
                'message' => 'Nao foi possivel salvar o diagrama BPMN.',
            ];
        }

        $diagramXml = trim((string) ($input['bpmn_xml'] ?? ''));
        if ($diagramXml === '') {
            return [
                'ok' => false,
                'errors' => ['O XML BPMN nao pode ser vazio.'],
                'message' => 'Nao foi possivel salvar o diagrama BPMN.',
            ];
        }

        if (mb_strlen($diagramXml) > 2_000_000) {
            return [
                'ok' => false,
                'errors' => ['O XML BPMN excede o limite de 2 MB.'],
                'message' => 'Nao foi possivel salvar o diagrama BPMN.',
            ];
        }

        $hasDefinitionsOpen = str_contains($diagramXml, '<bpmn:definitions')
            || str_contains($diagramXml, '<definitions');
        $hasDefinitionsClose = str_contains($diagramXml, '</bpmn:definitions>')
            || str_contains($diagramXml, '</definitions>');

        if (!$hasDefinitionsOpen || !$hasDefinitionsClose) {
            return [
                'ok' => false,
                'errors' => ['Conteudo BPMN invalido.'],
                'message' => 'Nao foi possivel salvar o diagrama BPMN.',
            ];
        }

        if (!$this->flows->updateFlowDiagramXml($flowId, $diagramXml)) {
            return [
                'ok' => false,
                'errors' => ['Falha ao persistir o XML BPMN no banco de dados.'],
                'message' => 'Nao foi possivel salvar o diagrama BPMN.',
            ];
        }

        $beforeXml = (string) ($flow['bpmn_diagram_xml'] ?? '');
        $beforeSignature = [
            'xml_sha1' => $beforeXml === '' ? null : sha1($beforeXml),
            'xml_size' => $beforeXml === '' ? 0 : mb_strlen($beforeXml),
        ];
        $afterSignature = [
            'xml_sha1' => sha1($diagramXml),
            'xml_size' => mb_strlen($diagramXml),
        ];

        $this->audit->log(
            entity: 'assignment_flow',
            entityId: $flowId,
            action: 'update_diagram',
            beforeData: $beforeSignature,
            afterData: $afterSignature,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.diagram_updated',
            payload: [
                'flow_id' => $flowId,
                'xml_sha1' => $afterSignature['xml_sha1'],
                'xml_size' => $afterSignature['xml_size'],
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => 'Diagrama BPMN atualizado com sucesso.',
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function delete(int $flowId, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->flows->findFlowById($flowId);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado.'],
            ];
        }

        $fallbackFlowId = $this->flows->fallbackFlowId($flowId);
        if ($fallbackFlowId === null) {
            return [
                'ok' => false,
                'errors' => ['Nao e possivel remover o unico fluxo ativo do sistema.'],
            ];
        }

        $this->flows->reassignPeopleFlow($flowId, $fallbackFlowId);
        $this->flows->reassignAssignmentsFlow($flowId, $fallbackFlowId);
        $this->flows->softDeleteFlow($flowId);

        if ((int) ($before['is_default'] ?? 0) === 1) {
            $this->flows->setDefaultFlow($fallbackFlowId);
        }

        $this->audit->log(
            entity: 'assignment_flow',
            entityId: $flowId,
            action: 'delete',
            beforeData: $before,
            afterData: [
                'fallback_flow_id' => $fallbackFlowId,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.deleted',
            payload: [
                'flow_id' => $flowId,
                'fallback_flow_id' => $fallbackFlowId,
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function upsertStep(int $flowId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $flow = $this->flows->findFlowById($flowId);
        if ($flow === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado para cadastro da etapa.'],
                'message' => 'Nao foi possivel atualizar a etapa.',
            ];
        }

        $statusId = max(0, (int) ($input['status_id'] ?? 0));
        $statusCode = $this->normalizeCode($this->clean($input['status_code'] ?? null));
        $statusLabel = $this->clean($input['status_label'] ?? null);
        $statusNextAction = $this->clean($input['status_next_action_label'] ?? null);
        $statusEventType = $this->clean($input['status_event_type'] ?? null);
        $statusIsActive = $this->normalizeBool($input['status_is_active'] ?? '1', true);
        $nodeKind = $this->normalizeNodeKind((string) ($input['step_node_kind'] ?? 'activity'));
        $sortOrder = max(1, (int) ($input['step_sort_order'] ?? 10));
        $isInitial = $this->normalizeBool($input['step_is_initial'] ?? '0', false);
        $stepIsActive = $this->normalizeBool($input['step_is_active'] ?? '1', true);
        $requiresEvidenceClose = $this->normalizeBool($input['step_requires_evidence_close'] ?? '0', false);
        $stepTags = $this->normalizeTagList($input['step_tags'] ?? null);
        $stepDocumentTypeIds = $this->normalizeIdList($input['step_document_type_ids'] ?? []);

        $errors = [];
        if ($nodeKind === null) {
            $errors[] = 'Tipo da etapa invalido.';
        }

        if ($stepDocumentTypeIds !== []) {
            $existingTypeIds = $this->flows->existingDocumentTypeIds($stepDocumentTypeIds);
            sort($existingTypeIds);
            $requestedTypeIds = $stepDocumentTypeIds;
            sort($requestedTypeIds);

            if ($existingTypeIds !== $requestedTypeIds) {
                $errors[] = 'Um ou mais tipos de documento esperados sao invalidos.';
            }
        }

        $status = null;
        if ($statusId > 0) {
            $status = $this->flows->statusById($statusId);
            if ($status === null) {
                $errors[] = 'Status informado nao foi encontrado.';
            }
        } else {
            if ($statusCode === null) {
                $errors[] = 'Codigo da etapa e obrigatorio.';
            }
            if ($statusLabel === null || mb_strlen($statusLabel) < 2) {
                $errors[] = 'Rotulo da etapa e obrigatorio.';
            }
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'message' => 'Nao foi possivel atualizar a etapa.',
            ];
        }

        if ($status === null) {
            $status = $statusCode !== null ? $this->flows->statusByCode($statusCode) : null;
            if ($status === null) {
                $statusId = $this->flows->createStatus([
                    'code' => $statusCode,
                    'label' => $statusLabel,
                    'sort_order' => $this->flows->nextStatusSortOrder(),
                    'next_action_label' => $statusNextAction,
                    'event_type' => $statusEventType,
                    'is_active' => $statusIsActive ? 1 : 0,
                ]);
                $status = $this->flows->statusById($statusId);
            } else {
                $statusId = (int) ($status['id'] ?? 0);
            }
        } else {
            $statusId = (int) ($status['id'] ?? 0);
        }

        if ($status === null || $statusId <= 0 || $nodeKind === null) {
            return [
                'ok' => false,
                'errors' => ['Falha ao identificar o status da etapa.'],
                'message' => 'Nao foi possivel atualizar a etapa.',
            ];
        }

        $this->flows->updateStatus($statusId, [
            'label' => $statusLabel ?? (string) ($status['label'] ?? ''),
            'next_action_label' => $statusNextAction ?? (string) ($status['next_action_label'] ?? ''),
            'event_type' => $statusEventType ?? (string) ($status['event_type'] ?? ''),
            'is_active' => $statusIsActive ? 1 : 0,
        ]);

        if ($isInitial) {
            $this->flows->clearInitialStep($flowId, $statusId);
        }

        $this->flows->upsertFlowStep(
            flowId: $flowId,
            statusId: $statusId,
            nodeKind: $nodeKind,
            sortOrder: $sortOrder,
            isInitial: $isInitial,
            isActive: $stepIsActive,
            requiresEvidenceClose: $requiresEvidenceClose,
            stepTags: $stepTags
        );

        $step = $this->flows->flowStepByStatus($flowId, $statusId);
        $flowStepId = (int) ($step['id'] ?? 0);
        if ($flowStepId > 0) {
            $this->flows->replaceFlowStepDocumentTypes($flowStepId, $stepDocumentTypeIds);
        }

        $this->ensureFlowHasInitialStep($flowId);

        $this->audit->log(
            entity: 'assignment_flow_step',
            entityId: $statusId,
            action: 'upsert',
            beforeData: null,
            afterData: [
                'flow_id' => $flowId,
                'status_id' => $statusId,
                'status_code' => (string) ($status['code'] ?? $statusCode ?? ''),
                'node_kind' => $nodeKind,
                'sort_order' => $sortOrder,
                'is_initial' => $isInitial ? 1 : 0,
                'is_active' => $stepIsActive ? 1 : 0,
                'requires_evidence_close' => $requiresEvidenceClose ? 1 : 0,
                'step_tags' => $stepTags,
                'expected_document_type_ids' => $stepDocumentTypeIds,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.step_upserted',
            payload: [
                'flow_id' => $flowId,
                'status_id' => $statusId,
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => 'Etapa atualizada com sucesso.',
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function deleteStep(int $flowId, int $statusId, int $userId, string $ip, string $userAgent): array
    {
        $flow = $this->flows->findFlowById($flowId);
        if ($flow === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado.'],
                'message' => 'Nao foi possivel remover a etapa.',
            ];
        }

        $step = $this->flows->flowStepByStatus($flowId, $statusId);
        if ($step === null) {
            return [
                'ok' => false,
                'errors' => ['Etapa nao encontrada no fluxo informado.'],
                'message' => 'Nao foi possivel remover a etapa.',
            ];
        }

        $this->flows->removeFlowStep($flowId, $statusId);
        $this->ensureFlowHasInitialStep($flowId);

        $this->audit->log(
            entity: 'assignment_flow_step',
            entityId: $statusId,
            action: 'delete',
            beforeData: $step,
            afterData: null,
            metadata: ['flow_id' => $flowId],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.step_deleted',
            payload: [
                'flow_id' => $flowId,
                'status_id' => $statusId,
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => 'Etapa removida com sucesso.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function upsertTransition(int $flowId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $flow = $this->flows->findFlowById($flowId);
        if ($flow === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado para transicao.'],
                'message' => 'Nao foi possivel atualizar a transicao.',
            ];
        }

        $transitionId = max(0, (int) ($input['transition_id'] ?? 0));
        $fromStatusId = max(0, (int) ($input['from_status_id'] ?? 0));
        $toStatusId = max(0, (int) ($input['to_status_id'] ?? 0));
        $transitionLabel = $this->clean($input['transition_label'] ?? null);
        $actionLabel = $this->clean($input['action_label'] ?? null);
        $eventType = $this->clean($input['event_type'] ?? null);
        $sortOrder = max(1, (int) ($input['sort_order'] ?? 10));
        $isActive = $this->normalizeBool($input['is_active'] ?? '1', true);

        $errors = [];
        if ($fromStatusId <= 0 || $toStatusId <= 0) {
            $errors[] = 'Origem e destino da transicao sao obrigatorios.';
        }

        if ($this->flows->flowStepByStatus($flowId, $fromStatusId) === null) {
            $errors[] = 'Etapa de origem nao pertence ao fluxo selecionado.';
        }

        if ($this->flows->flowStepByStatus($flowId, $toStatusId) === null) {
            $errors[] = 'Etapa de destino nao pertence ao fluxo selecionado.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'message' => 'Nao foi possivel atualizar a transicao.',
            ];
        }

        $payload = [
            'flow_id' => $flowId,
            'from_status_id' => $fromStatusId,
            'to_status_id' => $toStatusId,
            'transition_label' => $transitionLabel,
            'action_label' => $actionLabel,
            'event_type' => $eventType,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ];

        if ($transitionId > 0) {
            $before = $this->flows->flowTransitionById($flowId, $transitionId);
            if ($before === null) {
                return [
                    'ok' => false,
                    'errors' => ['Transicao nao encontrada para atualizacao.'],
                    'message' => 'Nao foi possivel atualizar a transicao.',
                ];
            }

            $this->flows->updateFlowTransition($transitionId, $payload);
            $entityId = $transitionId;
            $auditAction = 'update';
            $beforeData = $before;
        } else {
            $entityId = $this->flows->createFlowTransition($payload);
            $auditAction = 'create';
            $beforeData = null;
        }

        $this->audit->log(
            entity: 'assignment_flow_transition',
            entityId: $entityId,
            action: $auditAction,
            beforeData: $beforeData,
            afterData: $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.transition_upserted',
            payload: [
                'flow_id' => $flowId,
                'transition_id' => $entityId,
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => 'Transicao atualizada com sucesso.',
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, message: string}
     */
    public function deleteTransition(int $flowId, int $transitionId, int $userId, string $ip, string $userAgent): array
    {
        $flow = $this->flows->findFlowById($flowId);
        if ($flow === null) {
            return [
                'ok' => false,
                'errors' => ['Fluxo nao encontrado.'],
                'message' => 'Nao foi possivel remover a transicao.',
            ];
        }

        $before = $this->flows->flowTransitionById($flowId, $transitionId);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Transicao nao encontrada.'],
                'message' => 'Nao foi possivel remover a transicao.',
            ];
        }

        $this->flows->deleteFlowTransition($flowId, $transitionId);

        $this->audit->log(
            entity: 'assignment_flow_transition',
            entityId: $transitionId,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: ['flow_id' => $flowId],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'assignment_flow',
            type: 'assignment_flow.transition_deleted',
            payload: [
                'flow_id' => $flowId,
                'transition_id' => $transitionId,
            ],
            entityId: $flowId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'message' => 'Transicao removida com sucesso.',
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function nodeKindOptions(): array
    {
        return [
            ['value' => 'activity', 'label' => 'Atividade'],
            ['value' => 'gateway', 'label' => 'Decisao'],
            ['value' => 'final', 'label' => 'Final'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param ?array<string, mixed> $before
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateFlowInput(array $input, ?array $before): array
    {
        $name = $this->clean($input['name'] ?? null);
        $description = $this->clean($input['description'] ?? null);
        $isActive = $this->normalizeBool($input['is_active'] ?? ($before['is_active'] ?? '1'), true);
        $isDefault = $this->normalizeBool($input['is_default'] ?? ($before['is_default'] ?? '0'), false);

        $errors = [];
        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do fluxo e obrigatorio (minimo 3 caracteres).';
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive ? 1 : 0,
            'is_default' => $isDefault ? 1 : 0,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function ensureFlowHasInitialStep(int $flowId): void
    {
        $steps = $this->flows->flowSteps($flowId);
        if ($steps === []) {
            return;
        }

        $activeSteps = array_values(array_filter(
            $steps,
            static fn (array $step): bool => (int) ($step['is_active'] ?? 0) === 1
        ));
        if ($activeSteps === []) {
            return;
        }

        $hasInitial = false;
        foreach ($activeSteps as $step) {
            if ((int) ($step['is_initial'] ?? 0) === 1) {
                $hasInitial = true;
                break;
            }
        }

        if ($hasInitial) {
            return;
        }

        $first = $activeSteps[0];
        $statusId = (int) ($first['status_id'] ?? 0);
        if ($statusId <= 0) {
            return;
        }

        $this->flows->clearInitialStep($flowId, $statusId);
        $this->flows->upsertFlowStep(
            flowId: $flowId,
            statusId: $statusId,
            nodeKind: (string) ($first['node_kind'] ?? 'activity'),
            sortOrder: max(1, (int) ($first['sort_order'] ?? 10)),
            isInitial: true,
            isActive: true,
            requiresEvidenceClose: (int) ($first['requires_evidence_close'] ?? 0) === 1,
            stepTags: $this->normalizeTagList($first['step_tags'] ?? null)
        );
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

    private function normalizeCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        $normalized = is_string($normalized) ? trim($normalized, '_') : '';

        if ($normalized === '' || mb_strlen($normalized) < 2 || mb_strlen($normalized) > 60) {
            return null;
        }

        return $normalized;
    }

    private function normalizeTagList(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/[,\n;]+/', $raw);
        if (!is_array($parts)) {
            return null;
        }

        $normalized = [];
        foreach ($parts as $part) {
            $candidate = mb_strtolower(trim((string) $part));
            if ($candidate === '') {
                continue;
            }

            $candidate = preg_replace('/[^a-z0-9_\\-]+/u', '_', $candidate);
            $candidate = is_string($candidate) ? trim($candidate, '_-') : '';
            if ($candidate === '') {
                continue;
            }

            $normalized[$candidate] = true;
        }

        if ($normalized === []) {
            return null;
        }

        return implode(',', array_keys($normalized));
    }

    private function normalizeNodeKind(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if (!in_array($normalized, self::NODE_KINDS, true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): int => (int) $item,
            $items
        ), static fn (int $id): bool => $id > 0)));

        sort($ids);

        return $ids;
    }
}
