<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\PeopleRepository;
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

        flash('success', 'Pessoa cadastrada com sucesso.');
        $this->redirect('/people/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
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

        $this->view('people/show', [
            'title' => 'Perfil 360',
            'person' => $person,
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
}
