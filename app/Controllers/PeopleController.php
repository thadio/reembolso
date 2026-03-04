<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\PipelineRepository;
use App\Repositories\PeopleRepository;
use App\Services\PipelineService;
use App\Services\PeopleService;

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

        $this->view('people/show', [
            'title' => 'Perfil 360',
            'person' => $person,
            'pipeline' => $pipeline,
            'canManage' => $this->app->auth()->hasPermission('people.manage'),
            'canViewCpfFull' => $this->app->auth()->hasPermission('people.cpf.full'),
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
}
