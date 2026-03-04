<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CostPlanRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\PersonAuditRepository;
use App\Repositories\PipelineRepository;
use App\Repositories\PeopleRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\ReimbursementRepository;
use App\Services\CostPlanService;
use App\Services\DocumentService;
use App\Services\PersonAuditService;
use App\Services\PipelineService;
use App\Services\PeopleService;
use App\Services\ReconciliationService;
use App\Services\ReimbursementService;

final class PeopleController extends Controller
{
    public function index(Request $request): void
    {
        $previewId = max(0, (int) $request->input('preview_id', '0'));
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'organ_id' => max(0, (int) $request->input('organ_id', '0')),
            'modality_id' => max(0, (int) $request->input('modality_id', '0')),
            'tag' => (string) $request->input('tag', ''),
            'sort' => (string) $request->input('sort', 'name'),
            'dir' => (string) $request->input('dir', 'asc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);
        $previewPerson = null;

        foreach ($result['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $previewId) {
                $previewPerson = $item;
                break;
            }
        }

        if ($previewPerson === null && $result['items'] !== []) {
            $previewPerson = $result['items'][0];
        }

        $this->view('people/index', [
            'title' => 'Pessoas',
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
            'canViewCpfFull' => $this->app->auth()->hasPermission('people.cpf.full'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('people/create', [
            'title' => 'Nova Pessoa',
            'person' => $this->emptyPerson(),
            'statuses' => $this->service()->statuses(),
            'organs' => $this->service()->activeOrgans(),
            'modalities' => $this->service()->activeModalities(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

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
            userAgent: $request->userAgent()
        );

        flash('success', 'Pessoa cadastrada com sucesso.');
        $this->redirect('/people/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        $timelinePage = max(1, (int) $request->input('timeline_page', '1'));
        $documentsPage = max(1, (int) $request->input('documents_page', '1'));
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
        $pipeline = $this->pipelineService()->profileData($id, $timelinePage, 8);
        $documents = $this->documentService()->profileData($id, $documentsPage, 8);
        $costs = $this->costService()->profileData($id);
        $conciliation = $this->conciliationService()->profileData($id, 8);
        $reimbursements = $this->reimbursementService()->profileData($id, 80);
        $canViewAudit = $this->app->auth()->hasPermission('audit.view');

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
            'conciliation' => $conciliation,
            'reimbursements' => $reimbursements,
            'audit' => $audit,
            'canManage' => $this->app->auth()->hasPermission('people.manage'),
            'canViewCpfFull' => $this->app->auth()->hasPermission('people.cpf.full'),
            'canViewAudit' => $canViewAudit,
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

        $this->view('people/edit', [
            'title' => 'Editar Pessoa',
            'person' => $person,
            'statuses' => $this->service()->statuses(),
            'organs' => $this->service()->activeOrgans(),
            'modalities' => $this->service()->activeModalities(),
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
            userAgent: $request->userAgent()
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
        if ($id <= 0) {
            flash('error', 'Pessoa inválida.');
            $this->redirect('/people');
        }

        $result = $this->pipelineService()->advance(
            personId: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', $result['message']);
            $this->redirect('/people/show?id=' . $id);
        }

        flash('success', $result['message']);
        $this->redirect('/people/show?id=' . $id);
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

        $result = $this->pipelineService()->addManualEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            input: $request->all(),
            files: $_FILES,
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

    public function rectifyTimelineEvent(Request $request): void
    {
        $personId = (int) $request->input('person_id', '0');
        $sourceEventId = (int) $request->input('source_event_id', '0');
        $note = (string) $request->input('rectification_note', '');

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

        $result = $this->pipelineService()->rectifyEvent(
            personId: $personId,
            assignmentId: $assignmentId,
            sourceEventId: $sourceEventId,
            note: $note,
            files: $_FILES,
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

    public function downloadTimelineAttachment(Request $request): void
    {
        $attachmentId = (int) $request->input('id', '0');
        $personId = (int) $request->input('person_id', '0');

        if ($attachmentId <= 0 || $personId <= 0) {
            flash('error', 'Anexo inválido.');
            $this->redirect('/people');
        }

        $file = $this->pipelineService()->attachmentForDownload($attachmentId, $personId);
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

        $result = $this->documentService()->uploadDocuments(
            personId: $personId,
            input: $request->all(),
            files: $_FILES,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            $messages = array_merge($result['errors'], $result['warnings']);
            flash('error', implode(' ', $messages));
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
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
            userAgent: $request->userAgent()
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
            $this->redirect('/people/show?id=' . $personId);
        }

        flash('success', $result['message']);
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }

        $this->redirect('/people/show?id=' . $personId);
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

        $result = $this->costService()->addItem(
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

    /** @return array<string, mixed> */
    private function emptyPerson(): array
    {
        return [
            'organ_id' => '',
            'desired_modality_id' => '',
            'name' => '',
            'cpf' => '',
            'birth_date' => '',
            'email' => '',
            'phone' => '',
            'status' => 'interessado',
            'sei_process_number' => '',
            'mte_destination' => '',
            'tags' => '',
            'notes' => '',
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
            $this->app->config()
        );
    }

    private function documentService(): DocumentService
    {
        return new DocumentService(
            new DocumentRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config()
        );
    }

    private function costService(): CostPlanService
    {
        return new CostPlanService(
            new CostPlanRepository($this->app->db()),
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
}
