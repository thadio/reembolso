<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\OrganRepository;
use App\Services\OrganService;

final class OrgansController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($query, $sort, $dir, $page, $perPage);

        $this->view('organs/index', [
            'title' => 'Órgãos',
            'organs' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('organs.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('organs/create', [
            'title' => 'Novo Órgão',
            'organ' => $this->emptyOrgan(),
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
            $this->redirect('/organs/create');
        }

        flash('success', 'Órgão cadastrado com sucesso.');
        $this->redirect('/organs/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $organ = $this->service()->find($id);
        if ($organ === null) {
            flash('error', 'Órgão não encontrado.');
            $this->redirect('/organs');
        }

        $this->view('organs/show', [
            'title' => 'Detalhe do Órgão',
            'organ' => $organ,
            'canManage' => $this->app->auth()->hasPermission('organs.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $organ = $this->service()->find($id);
        if ($organ === null) {
            flash('error', 'Órgão não encontrado.');
            $this->redirect('/organs');
        }

        $this->view('organs/edit', [
            'title' => 'Editar Órgão',
            'organ' => $organ,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
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
            $this->redirect('/organs/edit?id=' . $id);
        }

        flash('success', 'Órgão atualizado com sucesso.');
        $this->redirect('/organs/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Órgão não encontrado ou já removido.');
            $this->redirect('/organs');
        }

        flash('success', 'Órgão removido com sucesso.');
        $this->redirect('/organs');
    }

    /** @return array<string, mixed> */
    private function emptyOrgan(): array
    {
        return [
            'name' => '',
            'acronym' => '',
            'cnpj' => '',
            'contact_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address_line' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'notes' => '',
        ];
    }

    private function service(): OrganService
    {
        return new OrganService(
            new OrganRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
