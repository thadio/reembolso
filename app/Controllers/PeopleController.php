<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CostItemCatalogRepository;
use App\Repositories\CostPlanRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\LgpdRepository;
use App\Repositories\PersonAuditRepository;
use App\Repositories\PipelineRepository;
use App\Repositories\PeopleRepository;
use App\Repositories\ProcessAdminTimelineRepository;
use App\Repositories\ProcessCommentRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\ReimbursementRepository;
use App\Repositories\SecuritySettingsRepository;
use App\Services\CostPlanService;
use App\Services\DocumentService;
use App\Services\LgpdService;
use App\Services\PersonAuditService;
use App\Services\PersonDossierExportService;
use App\Services\PipelineService;
use App\Services\PeopleService;
use App\Services\ProcessAdminTimelineService;
use App\Services\ProcessCommentService;
use App\Services\ReconciliationService;
use App\Services\ReimbursementService;
use App\Services\ReportPdfBuilder;
use App\Services\SecuritySettingsService;

final class PeopleController extends Controller
{
    public function index(Request $request): void
    {
        $previewId = max(0, (int) $request->input('preview_id', '0'));
        $movementBucket = mb_strtolower((string) $request->input('movement_bucket', ''));
        if (!in_array($movementBucket, ['entrando', 'saindo'], true)) {
            $movementBucket = '';
        }

        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'organ_id' => max(0, (int) $request->input('organ_id', '0')),
            'modality_id' => max(0, (int) $request->input('modality_id', '0')),
            'movement_bucket' => $movementBucket,
            'tag' => (string) $request->input('tag', ''),
            'queue_scope' => (string) $request->input('queue_scope', 'all'),
            'priority' => (string) $request->input('priority', ''),
            'responsible_id' => max(0, (int) $request->input('responsible_id', '0')),
            'sort' => (string) $request->input('sort', 'name'),
            'dir' => (string) $request->input('dir', 'asc'),
        ];

        $listTitle = match ($movementBucket) {
            'entrando' => 'Pessoas entrando',
            'saindo' => 'Pessoas saindo',
            default => 'Pessoas',
        };
        $listSubtitle = match ($movementBucket) {
            'entrando' => 'Movimentos de entrada por Cessão ou Composição de Força de Trabalho.',
            'saindo' => 'Movimentos de saída por Cessão, Requisição ou Composição de Força de Trabalho.',
            default => 'Filtros por status, modalidade, órgão, tipo de movimento e tags.',
        };

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));
        $authUserId = (int) ($this->app->auth()->id() ?? 0);

        if (mb_strtolower((string) ($filters['queue_scope'] ?? 'all')) === 'mine' && $authUserId > 0) {
            $filters['assigned_user_id'] = $authUserId;
            $filters['responsible_id'] = $authUserId;
        }

        $result = $this->service()->paginate($filters, $page, $perPage);
        $previewPerson = null;
        $canViewCpfFull = $this->app->auth()->hasPermission('people.cpf.full');

        foreach ($result['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $previewId) {
                $previewPerson = $item;
                break;
            }
        }

        if ($previewPerson === null && $result['items'] !== []) {
            $previewPerson = $result['items'][0];
        }

        if ($canViewCpfFull && $result['items'] !== []) {
            $personIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $result['items']
            )));

            $this->lgpdService()->registerSensitiveAccess(
                entity: 'person',
                entityId: $previewPerson !== null ? (int) ($previewPerson['id'] ?? 0) : null,
                action: 'cpf_view_list',
                sensitivity: 'cpf',
                subjectPersonId: $previewPerson !== null ? (int) ($previewPerson['id'] ?? 0) : null,
                subjectLabel: 'Listagem de pessoas',
                contextPath: '/people',
                metadata: [
                    'visible_people_count' => count($personIds),
                    'person_ids' => array_slice($personIds, 0, 100),
                ],
                userId: (int) ($this->app->auth()->id() ?? 0),
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        $this->view('people/index', [
            'title' => $listTitle,
            'listTitle' => $listTitle,
            'listSubtitle' => $listSubtitle,
            'people' => $result['items'],
            'filters' => $filters,
            'previewPerson' => $previewPerson,
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'statuses' => $this->service()->statuses(),
            'organs' => $this->service()->activeOrgans(),
            'modalities' => $this->service()->activeModalities(),
            'canManage' => $this->app->auth()->hasPermission('people.manage'),
            'canViewCpfFull' => $canViewCpfFull,
            'queuePriorities' => $this->pipelineService()->queuePriorities(),
            'queueUsers' => $this->pipelineService()->queueUsers(300),
            'authUserId' => $authUserId,
        ]);
    }

    public function create(Request $request): void
    {
        $assignmentFlows = $this->pipelineService()->activeFlows();
        $organs = $this->service()->activeOrgans();
        $person = $this->emptyPerson();
        if ($assignmentFlows !== [] && trim((string) ($person['assignment_flow_id'] ?? '')) === '') {
            $person['assignment_flow_id'] = (string) ((int) ($assignmentFlows[0]['id'] ?? 0));
        }

        $this->view('people/create', [
            'title' => 'Nova Pessoa',
            'person' => $person,
            'assignment' => null,
            'statuses' => $this->service()->statuses(),
            'organs' => $organs,
            'mteOrganId' => $this->resolveMteOrganId($organs),
            'modalities' => $this->service()->activeModalities(),
            'mteDestinations' => $this->service()->activeMteDestinations(),
            'assignmentFlows' => $assignmentFlows,
            'movementDirectionOptions' => $this->pipelineService()->movementDirectionOptions(),
            'financialNatureOptions' => $this->pipelineService()->financialNatureOptions(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $movementValidation = $this->pipelineService()->validateMovementContext(
            $this->movementContextPayload($input)
        );
        if (!$movementValidation['ok']) {
            flash('error', implode(' ', $movementValidation['errors']));
            $this->redirect('/people/create');
        }

        $scheduleValidation = $this->pipelineService()->validateScheduleContext($input);
        if (!$scheduleValidation['ok']) {
            flash('error', implode(' ', $scheduleValidation['errors']));
            $this->redirect('/people/create');
        }

        $movementContext = is_array($movementValidation['context'] ?? null) ? $movementValidation['context'] : [];
        $scheduleContext = is_array($scheduleValidation['context'] ?? null) ? $scheduleValidation['context'] : [];
        $movementReasonValidation = $this->validateMovementReason(
            modalityId: max(0, (int) ($input['desired_modality_id'] ?? 0)),
            movementDirection: (string) ($movementContext['movement_direction'] ?? 'entrada_mte')
        );
        if (!$movementReasonValidation['ok']) {
            flash('error', (string) ($movementReasonValidation['message'] ?? 'Motivo de movimentacao invalido.'));
            $this->redirect('/people/create');
        }

        $result = $this->service()->create(
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/create');
        }

        $this->pipelineService()->ensureAssignment(
            personId: (int) $result['id'],
            modalityId: isset($result['data']['desired_modality_id']) ? (int) $result['data']['desired_modality_id'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            movementContext: $movementContext,
            scheduleContext: $scheduleContext
        );

        flash('success', 'Pessoa cadastrada com sucesso.');
        $this->redirect('/people/show?id=' . (int) $result['id']);
    }

    public function importCsv(Request $request): void
    {
        $validateOnly = (string) $request->input('validate_only', '0') === '1';
        $file = is_array($_FILES['csv_file'] ?? null) ? $_FILES['csv_file'] : null;

        $result = $this->service()->importCsv(
            file: $file,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            validateOnly: $validateOnly
        );

        if (!$result['ok']) {
            $errors = $result['errors'] ?? [];
            if (count($errors) > 8) {
                $extra = count($errors) - 8;
                $errors = array_slice($errors, 0, 8);
                $errors[] = sprintf('... e mais %d erro(s).', $extra);
            }

            flash('error', implode(' ', $errors));
            $this->redirect('/people');
        }

        if (!$validateOnly) {
            foreach ($result['created_people'] as $person) {
                $this->pipelineService()->ensureAssignment(
                    personId: (int) ($person['id'] ?? 0),
                    modalityId: isset($person['desired_modality_id']) ? (int) $person['desired_modality_id'] : null,
                    userId: (int) ($this->app->auth()->id() ?? 0),
                    ip: $request->ip(),
                    userAgent: $request->userAgent()
                );
            }
        }

        flash('success', (string) ($result['message'] ?? 'Importacao concluida.'));
        $this->redirect('/people');
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        $timelinePage = max(1, (int) $request->input('timeline_page', '1'));
        $documentsPage = max(1, (int) $request->input('documents_page', '1'));
        $adminTimelinePage = max(1, (int) $request->input('admin_timeline_page', '1'));
        $auditPage = max(1, (int) $request->input('audit_page', '1'));
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($id);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $this->pipelineService()->ensureAssignment(
            personId: $id,
            modalityId: isset($person['desired_modality_id']) ? (int) $person['desired_modality_id'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $person = $this->service()->find($id) ?? $person;
        $pipeline = $this->pipelineService()->profileData(
            personId: $id,
            timelinePage: $timelinePage,
            timelinePerPage: 8,
            actorUserId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );
        $canViewAudit = $this->app->auth()->hasPermission('audit.view');
        $canViewCpfFull = $this->app->auth()->hasPermission('people.cpf.full');
        $canViewSensitiveDocuments = $this->app->auth()->hasPermission('people.documents.sensitive');
        $documentContextEventId = max(0, (int) $request->input('document_context_event_id', '0'));
        $documentContext = $this->pipelineService()->documentTypeContext(
            $id,
            $documentContextEventId > 0 ? $documentContextEventId : null
        );
        $documents = $this->documentService()->profileData($id, $documentsPage, 8, $canViewSensitiveDocuments);
        $documents['context'] = $documentContext;
        $costs = $this->costService()->profileData($id);
        $costItemCatalog = $this->costService()->catalogOptions();
        $conciliation = $this->conciliationService()->profileData($id, 8);
        $reimbursements = $this->reimbursementService()->profileData($id, 80);
        $processComments = $this->processCommentService()->profileData($id, 80);
        $adminTimelineFilters = [
            'q' => (string) $request->input('admin_timeline_q', ''),
            'source' => (string) $request->input('admin_timeline_source', ''),
            'status_group' => (string) $request->input('admin_timeline_status_group', ''),
        ];
        $adminTimeline = $this->processAdminTimelineService()->profileData(
            personId: $id,
            filters: $adminTimelineFilters,
            page: $adminTimelinePage,
            perPage: 14
        );

        if ($canViewCpfFull && trim((string) ($person['cpf'] ?? '')) !== '') {
            $this->lgpdService()->registerSensitiveAccess(
                entity: 'person',
                entityId: $id,
                action: 'cpf_view_profile',
                sensitivity: 'cpf',
                subjectPersonId: $id,
                subjectLabel: (string) ($person['name'] ?? ''),
                contextPath: '/people/show',
                metadata: [
                    'status' => (string) ($person['status'] ?? ''),
                ],
                userId: (int) ($this->app->auth()->id() ?? 0),
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        $auditFilters = [
            'entity' => (string) $request->input('audit_entity', ''),
            'action' => (string) $request->input('audit_action', ''),
            'q' => (string) $request->input('audit_q', ''),
            'from_date' => (string) $request->input('audit_from', ''),
            'to_date' => (string) $request->input('audit_to', ''),
        ];

        $audit = [
            'items' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'per_page' => 10,
                'pages' => 1,
            ],
            'filters' => [
                'entity' => '',
                'action' => '',
                'q' => '',
                'from_date' => '',
                'to_date' => '',
            ],
            'options' => [
                'entities' => [],
                'actions' => [],
            ],
        ];

        if ($canViewAudit) {
            $audit = $this->auditTrailService()->profileData($id, $auditFilters, $auditPage, 10);
        }

        $this->view('people/show', [
            'title' => 'Perfil 360',
            'person' => $person,
            'pipeline' => $pipeline,
            'documents' => $documents,
            'costs' => $costs,
            'costItemCatalog' => $costItemCatalog,
            'conciliation' => $conciliation,
            'reimbursements' => $reimbursements,
            'processComments' => $processComments,
            'adminTimeline' => $adminTimeline,
            'audit' => $audit,
            'canManage' => $this->app->auth()->hasPermission('people.manage'),
            'canManageCostItems' => $this->app->auth()->hasPermission('cost_item.manage'),
            'canViewCpfFull' => $canViewCpfFull,
            'canViewAudit' => $canViewAudit,
            'canViewSensitiveDocuments' => $canViewSensitiveDocuments,
            'processCommentStatusOptions' => $this->processCommentService()->statusOptions(),
            'adminTimelineNoteStatusOptions' => $this->processAdminTimelineService()->noteStatusOptions(),
            'adminTimelineNoteSeverityOptions' => $this->processAdminTimelineService()->noteSeverityOptions(),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($id);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $organs = $this->service()->activeOrgans();

        $this->view('people/edit', [
            'title' => 'Editar Pessoa',
            'person' => $person,
            'assignment' => $this->pipelineService()->profileData($id, 1, 1)['assignment'] ?? null,
            'statuses' => $this->service()->statuses(),
            'organs' => $organs,
            'mteOrganId' => $this->resolveMteOrganId($organs),
            'modalities' => $this->service()->activeModalities(),
            'mteDestinations' => $this->service()->activeMteDestinations(),
            'assignmentFlows' => $this->pipelineService()->activeFlows(),
            'movementDirectionOptions' => $this->pipelineService()->movementDirectionOptions(),
            'financialNatureOptions' => $this->pipelineService()->financialNatureOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $input = $request->all();
        Session::flashInput($input);

        $movementValidation = $this->pipelineService()->validateMovementContext(
            $this->movementContextPayload($input)
        );
        if (!$movementValidation['ok']) {
            flash('error', implode(' ', $movementValidation['errors']));
            $this->redirect('/people/edit?id=' . $id);
        }

        $scheduleValidation = $this->pipelineService()->validateScheduleContext($input);
        if (!$scheduleValidation['ok']) {
            flash('error', implode(' ', $scheduleValidation['errors']));
            $this->redirect('/people/edit?id=' . $id);
        }

        $movementContext = is_array($movementValidation['context'] ?? null) ? $movementValidation['context'] : [];
        $scheduleContext = is_array($scheduleValidation['context'] ?? null) ? $scheduleValidation['context'] : [];
        $movementReasonValidation = $this->validateMovementReason(
            modalityId: max(0, (int) ($input['desired_modality_id'] ?? 0)),
            movementDirection: (string) ($movementContext['movement_direction'] ?? 'entrada_mte')
        );
        if (!$movementReasonValidation['ok']) {
            flash('error', (string) ($movementReasonValidation['message'] ?? 'Motivo de movimentacao invalido.'));
            $this->redirect('/people/edit?id=' . $id);
        }

        $result = $this->service()->update(
            $id,
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/edit?id=' . $id);
        }

        $this->pipelineService()->ensureAssignment(
            personId: $id,
            modalityId: isset($result['data']['desired_modality_id']) ? (int) $result['data']['desired_modality_id'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            movementContext: $movementContext,
            scheduleContext: $scheduleContext
        );

        flash('success', 'Pessoa atualizada com sucesso.');
        $this->redirect('/people/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Pessoa não encontrada ou já removida.');
            $this->redirect('/people');
        }

        flash('success', 'Pessoa removida com sucesso.');
        $this->redirect('/people');
    }

    public function advancePipeline(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        $transitionId = max(0, (int) $request->input('transition_id', '0'));
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $result = $this->pipelineService()->advance(
            personId: $id,
            transitionId: $transitionId > 0 ? $transitionId : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', $result['message']);
            $this->redirect('/people/show?id=' . $id . '&tab=timeline');
        }

        flash('success', $result['message']);
        $this->redirect('/people/show?id=' . $id . '&tab=timeline&focus=history');
    }

    public function updatePipelineQueue(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $assignmentId = (int) $request->input('assignment_id', '0');
        $assignedUserId = max(0, (int) $request->input('assigned_user_id', '0'));
        $priorityLevel = (string) $request->input('priority_level', 'normal');

        if ($personId <= 0 || $assignmentId <= 0) {
            flash('error', 'Dados invalidos para atualizar a fila.');
            $this->redirect('/people');
        }

        $result = $this->pipelineService()->updateQueue(
            personId: $personId,
            assignmentId: $assignmentId,
            assignedUserId: $assignedUserId > 0 ? $assignedUserId : null,
            priorityLevel: $priorityLevel,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
        }

        flash('success', $result['message']);
        $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
    }

    public function updatePipelineChecklist(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $assignmentId = (int) $request->input('assignment_id', '0');
        $itemId = (int) $request->input('checklist_item_id', '0');
        $isDone = (int) $request->input('is_done', '0') === 1;

        if ($personId <= 0 || $assignmentId <= 0 || $itemId <= 0) {
            flash('error', 'Dados invalidos para atualizar checklist.');
            $this->redirect('/people');
        }

        $result = $this->pipelineService()->updateChecklistItem(
            personId: $personId,
            assignmentId: $assignmentId,
            itemId: $itemId,
            isDone: $isDone,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
        }

        flash('success', $result['message']);
        $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
    }

    public function storeTimelineEvent(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para registro de evento.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $pipeline = $this->pipelineService()->profileData($personId, 1, 1);
        $assignment = $pipeline['assignment'] ?? null;
        $assignmentId = $assignment !== null ? (int) ($assignment['id'] ?? 0) : null;
        if ($assignmentId !== null && $assignmentId <= 0) {
            $assignmentId = null;
        }

        $input = $request->all();
        $result = $this->pipelineService()->addManualEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            input: $input,
            files: $_FILES,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
        }

        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        $advanceAttempt = $this->advanceAfterTimelineEvidence($personId, $input, $request);

        $successMessage = (string) ($result['message'] ?? 'Evento manual registrado com sucesso.');
        if ($advanceAttempt['requested'] && $advanceAttempt['ok']) {
            $successMessage .= ' Etapa encerrada com sucesso.';
        }

        if ($advanceAttempt['requested'] && !$advanceAttempt['ok']) {
            $advanceMessage = trim((string) ($advanceAttempt['message'] ?? ''));
            if ($advanceMessage === '') {
                $advanceMessage = 'Escolha a transicao de destino e tente novamente.';
            }

            $warnings[] = 'Evidencia salva, mas nao foi possivel encerrar etapa: ' . $advanceMessage;
        }

        flash('success', $successMessage);
        if ($warnings !== []) {
            flash('error', implode(' ', array_values(array_unique($warnings))));
        }

        $focus = $advanceAttempt['requested'] && $advanceAttempt['ok'] ? '&focus=pipeline' : '';
        $this->redirect('/people/show?id=' . $personId . '&tab=timeline' . $focus);
    }

    public function rectifyTimelineEvent(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $sourceEventId = (int) $request->input('source_event_id', '0');
        $note = (string) $request->input('rectification_note', '');
        $evidenceLinks = (string) $request->input('evidence_links', '');

        if ($personId <= 0 || $sourceEventId <= 0) {
            flash('error', 'Dados inválidos para retificação.');
            $this->redirect('/people');
        }

        $pipeline = $this->pipelineService()->profileData($personId, 1, 1);
        $assignment = $pipeline['assignment'] ?? null;
        $assignmentId = $assignment !== null ? (int) ($assignment['id'] ?? 0) : null;
        if ($assignmentId !== null && $assignmentId <= 0) {
            $assignmentId = null;
        }

        $input = $request->all();
        $result = $this->pipelineService()->rectifyEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            sourceEventId: $sourceEventId,
            note: $note,
            linksInput: $evidenceLinks,
            files: $_FILES,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=timeline');
        }

        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        $advanceAttempt = $this->advanceAfterTimelineEvidence($personId, $input, $request);

        $successMessage = (string) ($result['message'] ?? 'Retificacao registrada com sucesso.');
        if ($advanceAttempt['requested'] && $advanceAttempt['ok']) {
            $successMessage .= ' Etapa encerrada com sucesso.';
        }

        if ($advanceAttempt['requested'] && !$advanceAttempt['ok']) {
            $advanceMessage = trim((string) ($advanceAttempt['message'] ?? ''));
            if ($advanceMessage === '') {
                $advanceMessage = 'Escolha a transicao de destino e tente novamente.';
            }

            $warnings[] = 'Retificacao salva, mas nao foi possivel encerrar etapa: ' . $advanceMessage;
        }

        flash('success', $successMessage);
        if ($warnings !== []) {
            flash('error', implode(' ', array_values(array_unique($warnings))));
        }

        $focus = $advanceAttempt['requested'] && $advanceAttempt['ok'] ? '&focus=pipeline' : '';
        $this->redirect('/people/show?id=' . $personId . '&tab=timeline' . $focus);
    }

    public function downloadTimelineAttachment(Request $request): void
    {
        $attachmentId = (int) $request->input('id', '0');
        $personId = (int) $request->input('person_id', '0');

        if ($attachmentId <= 0 || $personId <= 0) {
            flash('error', 'Anexo inválido.');
            $this->redirect('/people');
        }

        $file = $this->pipelineService()->attachmentForDownload(
            attachmentId: $attachmentId,
            personId: $personId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );
        if ($file === null) {
            flash('error', 'Anexo não encontrado ou acesso não autorizado.');
            $this->redirect('/people/show?id=' . $personId);
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) filesize($file['path']));
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
        readfile($file['path']);
        exit;
    }

    public function timelinePrint(Request $request): void
    {
        $personId = (int) $request->input('id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $timeline = $this->pipelineService()->fullTimeline($personId, 400);

        if ($this->app->auth()->hasPermission('people.cpf.full') && trim((string) ($person['cpf'] ?? '')) !== '') {
            $this->lgpdService()->registerSensitiveAccess(
                entity: 'person',
                entityId: $personId,
                action: 'cpf_view_timeline_print',
                sensitivity: 'cpf',
                subjectPersonId: $personId,
                subjectLabel: (string) ($person['name'] ?? ''),
                contextPath: '/people/timeline/print',
                metadata: [
                    'timeline_items' => count($timeline),
                ],
                userId: (int) ($this->app->auth()->id() ?? 0),
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        $this->app->view()->render('people/timeline_print', [
            'title' => 'Timeline',
            'person' => $person,
            'timeline' => $timeline,
            'authUser' => $this->app->auth()->user(),
            'currentPath' => '/people/timeline/print',
        ], 'print_layout');
    }

    public function storeDocument(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para upload de documento.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $input = $request->all();
        $contextEventId = max(0, (int) ($input['context_event_id'] ?? 0));
        $documentsTabUrl = '/people/show?id=' . $personId . '&tab=documents';
        if ($contextEventId > 0) {
            $documentsTabUrl .= '&document_context_event_id=' . $contextEventId;
        }
        $requestedDocumentTypeId = max(0, (int) ($input['document_type_id'] ?? 0));
        $documentTypeResolution = $this->pipelineService()->resolveDocumentTypeForUpload(
            personId: $personId,
            contextEventId: $contextEventId > 0 ? $contextEventId : null,
            requestedDocumentTypeId: $requestedDocumentTypeId > 0 ? $requestedDocumentTypeId : null
        );
        $resolvedDocumentTypeId = max(0, (int) ($documentTypeResolution['resolved_document_type_id'] ?? 0));
        if ($resolvedDocumentTypeId > 0) {
            $input['document_type_id'] = (string) $resolvedDocumentTypeId;
        }

        $result = $this->documentService()->uploadDocuments(
            personId: $personId,
            input: $input,
            files: $_FILES,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            canAssignSensitiveDocuments: $this->app->auth()->hasPermission('people.documents.sensitive')
        );

        if (!$result['ok']) {
            $messages = array_merge($result['errors'], $result['warnings']);
            flash('error', implode(' ', $messages));
            $this->redirect($documentsTabUrl);
        }

        $contextWarning = trim((string) ($documentTypeResolution['warning'] ?? ''));
        $successMessage = (string) ($result['message'] ?? 'Documentos enviados com sucesso.');
        if ($contextWarning !== '') {
            $successMessage .= ' ' . $contextWarning;
        }
        flash('success', $successMessage);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect($documentsTabUrl);
    }

    public function downloadDocument(Request $request): void
    {
        $documentId = (int) $request->input('id', '0');
        $personId = (int) $request->input('person_id', '0');

        if ($documentId <= 0 || $personId <= 0) {
            flash('error', 'Documento inválido.');
            $this->redirect('/people');
        }

        $file = $this->documentService()->documentForDownload(
            documentId: $documentId,
            personId: $personId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            canAccessSensitiveDocuments: $this->app->auth()->hasPermission('people.documents.sensitive')
        );
        if ($file === null) {
            flash('error', 'Documento não encontrado ou acesso não autorizado.');
            $this->redirect('/people/show?id=' . $personId);
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) filesize($file['path']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
        readfile($file['path']);
        exit;
    }

    public function storeDocumentVersion(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $documentId = (int) $request->input('document_id', '0');

        if ($personId <= 0 || $documentId <= 0) {
            flash('error', 'Dados inválidos para versionamento do documento.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $result = $this->documentService()->createDocumentVersion(
            personId: $personId,
            documentId: $documentId,
            files: $_FILES,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            canAccessSensitiveDocuments: $this->app->auth()->hasPermission('people.documents.sensitive')
        );

        if (!$result['ok']) {
            $messages = array_merge($result['errors'], $result['warnings']);
            flash('error', implode(' ', $messages));
            $this->redirect('/people/show?id=' . $personId . '&tab=documents');
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId . '&tab=documents');
    }

    public function downloadDocumentVersion(Request $request): void
    {
        $versionId = (int) $request->input('version_id', '0');
        $documentId = (int) $request->input('document_id', '0');
        $personId = (int) $request->input('person_id', '0');

        if ($versionId <= 0 || $documentId <= 0 || $personId <= 0) {
            flash('error', 'Versão de documento inválida.');
            $this->redirect('/people');
        }

        $file = $this->documentService()->documentVersionForDownload(
            versionId: $versionId,
            documentId: $documentId,
            personId: $personId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            canAccessSensitiveDocuments: $this->app->auth()->hasPermission('people.documents.sensitive')
        );
        if ($file === null) {
            flash('error', 'Versão do documento não encontrada ou acesso não autorizado.');
            $this->redirect('/people/show?id=' . $personId);
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) filesize($file['path']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
        readfile($file['path']);
        exit;
    }

    public function createCostVersion(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para versionamento de custos.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $result = $this->costService()->createVersion(
            personId: $personId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=costs');
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId . '&tab=costs');
    }

    public function storeCostItem(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para custos.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $result = $this->costService()->saveTable(
            personId: $personId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId . '&tab=costs');
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId . '&tab=costs');
    }

    public function storeReimbursement(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para lançamento financeiro.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $result = $this->reimbursementService()->createEntry(
            personId: $personId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function markReimbursementPaid(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $entryId = (int) $request->input('entry_id', '0');

        if ($personId <= 0 || $entryId <= 0) {
            flash('error', 'Dados inválidos para baixa de lançamento.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $result = $this->reimbursementService()->markAsPaid(
            personId: $personId,
            entryId: $entryId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function storeProcessComment(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa invalida para comentario interno.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $defaultAssignmentId = max(0, (int) $request->input('assignment_id', '0'));

        $result = $this->processCommentService()->create(
            personId: $personId,
            defaultAssignmentId: $defaultAssignmentId > 0 ? $defaultAssignmentId : null,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function updateProcessComment(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $commentId = (int) $request->input('comment_id', '0');
        if ($personId <= 0 || $commentId <= 0) {
            flash('error', 'Dados invalidos para atualizar comentario interno.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $result = $this->processCommentService()->update(
            personId: $personId,
            commentId: $commentId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function deleteProcessComment(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $commentId = (int) $request->input('comment_id', '0');
        if ($personId <= 0 || $commentId <= 0) {
            flash('error', 'Dados invalidos para excluir comentario interno.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $result = $this->processCommentService()->delete(
            personId: $personId,
            commentId: $commentId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function storeProcessAdminTimelineNote(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa invalida para timeline administrativa.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $defaultAssignmentId = max(0, (int) $request->input('assignment_id', '0'));

        $result = $this->processAdminTimelineService()->createManualNote(
            personId: $personId,
            defaultAssignmentId: $defaultAssignmentId > 0 ? $defaultAssignmentId : null,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function updateProcessAdminTimelineNote(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $noteId = (int) $request->input('note_id', '0');
        if ($personId <= 0 || $noteId <= 0) {
            flash('error', 'Dados invalidos para atualizar nota administrativa.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $result = $this->processAdminTimelineService()->updateManualNote(
            personId: $personId,
            noteId: $noteId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function deleteProcessAdminTimelineNote(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $noteId = (int) $request->input('note_id', '0');
        if ($personId <= 0 || $noteId <= 0) {
            flash('error', 'Dados invalidos para excluir nota administrativa.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $result = $this->processAdminTimelineService()->deleteManualNote(
            personId: $personId,
            noteId: $noteId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
    }

    public function exportAudit(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa inválida para exportação de auditoria.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa não encontrada.');
            $this->redirect('/people');
        }

        $filters = [
            'entity' => (string) $request->input('audit_entity', ''),
            'action' => (string) $request->input('audit_action', ''),
            'q' => (string) $request->input('audit_q', ''),
            'from_date' => (string) $request->input('audit_from', ''),
            'to_date' => (string) $request->input('audit_to', ''),
        ];

        $export = $this->auditTrailService()->exportRows($personId, $filters, 2000);
        $rows = $export['rows'];

        $fileName = sprintf('auditoria-pessoa-%d-%s.csv', $personId, date('Ymd_His'));

        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'data_hora',
            'entidade',
            'entidade_id',
            'acao',
            'usuario',
            'ip',
            'user_agent',
            'before_data',
            'after_data',
            'metadata',
        ]);

        $normalizePayload = static function (mixed $payload): string {
            if (!is_string($payload) || trim($payload) === '') {
                return '';
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                return $payload;
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : $payload;
        };

        foreach ($rows as $row) {
            fputcsv($output, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['entity'] ?? ''),
                isset($row['entity_id']) ? (string) $row['entity_id'] : '',
                (string) ($row['action'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                (string) ($row['ip'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $normalizePayload($row['before_data'] ?? null),
                $normalizePayload($row['after_data'] ?? null),
                $normalizePayload($row['metadata'] ?? null),
            ]);
        }

        fclose($output);
        exit;
    }

    public function exportDossier(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        if ($personId <= 0) {
            flash('error', 'Pessoa invalida para exportacao de dossie.');
            $this->redirect('/people');
        }

        $person = $this->service()->find($personId);
        if ($person === null) {
            flash('error', 'Pessoa nao encontrada.');
            $this->redirect('/people');
        }

        $export = $this->dossierService()->exportZip(
            personId: $personId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            canViewSensitiveDocuments: $this->app->auth()->hasPermission('people.documents.sensitive'),
            canViewAuditTrail: $this->app->auth()->hasPermission('audit.view'),
            canViewCpfFull: $this->app->auth()->hasPermission('people.cpf.full')
        );

        if (($export['ok'] ?? false) !== true) {
            $errors = is_array($export['errors'] ?? null) ? $export['errors'] : ['Falha ao gerar dossie ZIP.'];
            flash('error', implode(' ', $errors));
            $this->redirect('/people/show?id=' . $personId);
        }

        $path = (string) ($export['path'] ?? '');
        $fileName = (string) ($export['file_name'] ?? ('dossie-pessoa-' . $personId . '.zip'));

        if ($path === '' || !is_file($path)) {
            flash('error', 'Arquivo ZIP de dossie nao encontrado para download.');
            $this->redirect('/people/show?id=' . $personId);
        }

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . (string) filesize($path));
        }

        readfile($path);
        @unlink($path);
        exit;
    }

    /** @return array<string, mixed> */
    private function emptyPerson(): array
    {
        return [
            'organ_id' => '',
            'desired_modality_id' => '',
            'assignment_flow_id' => '',
            'movement_direction' => '',
            'financial_nature' => '',
            'origin_mte_destination_id' => '',
            'destination_mte_destination_id' => '',
            'target_start_date' => '',
            'requested_end_date' => '',
            'name' => '',
            'cpf' => '',
            'matricula_siape' => '',
            'birth_date' => '',
            'email' => '',
            'phone' => '',
            'status' => 'interessado',
            'sei_process_number' => '',
            'tags' => '',
            'notes' => '',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function validateMovementReason(int $modalityId, string $movementDirection): array
    {
        if ($modalityId <= 0) {
            return [
                'ok' => false,
                'message' => 'Selecione o motivo da movimentacao da pessoa.',
            ];
        }

        $modality = $this->findActiveModalityById($modalityId);
        if ($modality === null) {
            return [
                'ok' => false,
                'message' => 'Motivo da movimentacao invalido.',
            ];
        }

        $reasonType = $this->movementReasonType((string) ($modality['name'] ?? ''));
        if ($reasonType === null) {
            return [
                'ok' => false,
                'message' => 'Use os motivos Cessao, Requisicao ou Composicao de Forca de Trabalho.',
            ];
        }

        $direction = $movementDirection === 'saida_mte' ? 'saida_mte' : 'entrada_mte';
        $allowedByDirection = $direction === 'saida_mte'
            ? ['cessao', 'requisicao', 'cft']
            : ['cessao', 'cft'];

        if (!in_array($reasonType, $allowedByDirection, true)) {
            $directionLabel = $direction === 'saida_mte' ? 'saida' : 'entrada';
            $allowedLabel = $direction === 'saida_mte'
                ? 'Cessao, Requisicao ou Composicao de Forca de Trabalho'
                : 'Cessao ou Composicao de Forca de Trabalho';

            return [
                'ok' => false,
                'message' => sprintf('Para movimento de %s, selecione motivo: %s.', $directionLabel, $allowedLabel),
            ];
        }

        return [
            'ok' => true,
            'message' => '',
        ];
    }

    /** @return array<string, mixed>|null */
    private function findActiveModalityById(int $modalityId): ?array
    {
        if ($modalityId <= 0) {
            return null;
        }

        foreach ($this->service()->activeModalities() as $modality) {
            if ((int) ($modality['id'] ?? 0) === $modalityId) {
                return $modality;
            }
        }

        return null;
    }

    private function movementReasonType(string $modalityName): ?string
    {
        $normalized = $this->normalizeLookup($modalityName);
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'cess')) {
            return 'cessao';
        }

        if (str_contains($normalized, 'requis')) {
            return 'requisicao';
        }

        if (str_contains($normalized, 'forca') || str_contains($normalized, 'cft')) {
            return 'cft';
        }

        return null;
    }

    private function normalizeLookup(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        return strtr($normalized, [
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
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function movementContextPayload(array $input): array
    {
        return [
            'movement_direction' => (string) ($input['movement_direction'] ?? ''),
            'financial_nature' => (string) ($input['financial_nature'] ?? ''),
            'counterparty_organ_id' => max(0, (int) ($input['organ_id'] ?? 0)),
            'origin_mte_destination_id' => max(0, (int) ($input['origin_mte_destination_id'] ?? 0)),
            'destination_mte_destination_id' => max(0, (int) ($input['destination_mte_destination_id'] ?? 0)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $organs
     */
    private function resolveMteOrganId(array $organs): ?int
    {
        foreach ($organs as $organ) {
            $id = (int) ($organ['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $acronym = $this->normalizeLookup((string) ($organ['acronym'] ?? ''));
            if ($acronym === 'mte') {
                return $id;
            }
        }

        foreach ($organs as $organ) {
            $id = (int) ($organ['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = $this->normalizeLookup((string) ($organ['name'] ?? ''));
            if (
                $name === 'ministerio do trabalho e emprego'
                || (str_contains($name, 'ministerio') && str_contains($name, 'trabalho') && str_contains($name, 'emprego'))
            ) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{requested: bool, ok: bool, message: string}
     */
    private function advanceAfterTimelineEvidence(int $personId, array $input, Request $request): array
    {
        $shouldAdvance = (int) ($input['close_step'] ?? 0) === 1;
        if (!$shouldAdvance) {
            return [
                'requested' => false,
                'ok' => false,
                'message' => '',
            ];
        }

        $transitionId = max(0, (int) ($input['close_transition_id'] ?? 0));

        $result = $this->pipelineService()->advance(
            personId: $personId,
            transitionId: $transitionId > 0 ? $transitionId : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'requested' => true,
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => trim((string) ($result['message'] ?? '')),
        ];
    }

    private function service(): PeopleService
    {
        return new PeopleService(
            new PeopleRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function pipelineService(): PipelineService
    {
        return new PipelineService(
            new PipelineRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config(),
            $this->lgpdService(),
            $this->securityService()
        );
    }

    private function documentService(): DocumentService
    {
        return new DocumentService(
            new DocumentRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config(),
            $this->lgpdService(),
            $this->securityService()
        );
    }

    private function costService(): CostPlanService
    {
        return new CostPlanService(
            new CostPlanRepository($this->app->db()),
            new CostItemCatalogRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function reimbursementService(): ReimbursementService
    {
        return new ReimbursementService(
            new ReimbursementRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function processCommentService(): ProcessCommentService
    {
        return new ProcessCommentService(
            new ProcessCommentRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function processAdminTimelineService(): ProcessAdminTimelineService
    {
        return new ProcessAdminTimelineService(
            new ProcessAdminTimelineRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function conciliationService(): ReconciliationService
    {
        return new ReconciliationService(
            new ReconciliationRepository($this->app->db())
        );
    }

    private function auditTrailService(): PersonAuditService
    {
        return new PersonAuditService(
            new PersonAuditRepository($this->app->db())
        );
    }

    private function dossierService(): PersonDossierExportService
    {
        return new PersonDossierExportService(
            $this->service(),
            $this->pipelineService(),
            $this->documentService(),
            $this->processCommentService(),
            $this->processAdminTimelineService(),
            $this->reimbursementService(),
            $this->auditTrailService(),
            $this->app->audit(),
            $this->app->events(),
            new ReportPdfBuilder(),
            $this->app->config()
        );
    }

    private function lgpdService(): LgpdService
    {
        return new LgpdService(
            new LgpdRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function securityService(): SecuritySettingsService
    {
        return new SecuritySettingsService(
            new SecuritySettingsRepository($this->app->db()),
            $this->app->config(),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
