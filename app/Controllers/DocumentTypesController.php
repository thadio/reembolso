<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\DocumentTypeRepository;
use App\Services\DocumentTypeService;

final class DocumentTypesController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $status = (string) $request->input('status', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        if (!in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        $result = $this->service()->paginate($query, $status, $sort, $dir, $page, $perPage);

        $this->view('document_types/index', [
            'title' => 'Tipos de Documento',
            'types' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'status' => $status,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('document_type.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('document_types/create', [
            'title' => 'Novo Tipo de Documento',
            'type' => $this->emptyType(),
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
            $this->redirect('/document-types/create');
        }

        flash('success', 'Tipo de documento cadastrado com sucesso.');
        $this->redirect('/document-types/show?id=' . (int) ($result['id'] ?? 0));
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de documento invalido.');
            $this->redirect('/document-types');
        }

        $type = $this->service()->find($id);
        if ($type === null) {
            flash('error', 'Tipo de documento nao encontrado.');
            $this->redirect('/document-types');
        }

        $this->view('document_types/show', [
            'title' => 'Detalhe do Tipo de Documento',
            'type' => $type,
            'canManage' => $this->app->auth()->hasPermission('document_type.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de documento invalido.');
            $this->redirect('/document-types');
        }

        $type = $this->service()->find($id);
        if ($type === null) {
            flash('error', 'Tipo de documento nao encontrado.');
            $this->redirect('/document-types');
        }

        $this->view('document_types/edit', [
            'title' => 'Editar Tipo de Documento',
            'type' => $type,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de documento invalido.');
            $this->redirect('/document-types');
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
            $this->redirect('/document-types/edit?id=' . $id);
        }

        flash('success', 'Tipo de documento atualizado com sucesso.');
        $this->redirect('/document-types/show?id=' . $id);
    }

    public function toggleActive(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de documento invalido.');
            $this->redirect('/document-types');
        }

        $isActive = (string) $request->input('is_active', '0') === '1';
        $result = $this->service()->toggleActive(
            id: $id,
            isActive: $isActive,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/document-types/show?id=' . $id);
        }

        flash('success', $result['message']);
        $this->redirect('/document-types/show?id=' . $id);
    }

    /** @return array<string, mixed> */
    private function emptyType(): array
    {
        return [
            'name' => '',
            'description' => '',
            'is_active' => 1,
        ];
    }

    private function service(): DocumentTypeService
    {
        return new DocumentTypeService(
            new DocumentTypeRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
