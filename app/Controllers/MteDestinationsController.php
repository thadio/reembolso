<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\MteDestinationRepository;
use App\Services\MteDestinationService;

final class MteDestinationsController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($query, $sort, $dir, $page, $perPage);

        $this->view('mte_destinations/index', [
            'title' => 'Lotações MTE',
            'destinations' => $result['items'],
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
            'canManage' => $this->app->auth()->hasPermission('mte_destinations.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('mte_destinations/create', [
            'title' => 'Nova Lotação MTE',
            'destination' => $this->emptyDestination(),
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
            $this->redirect('/mte-destinations/create');
        }

        flash('success', 'Lotação cadastrada com sucesso.');
        $this->redirect('/mte-destinations/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Lotação inválida.');
            $this->redirect('/mte-destinations');
        }

        $destination = $this->service()->find($id);
        if ($destination === null) {
            flash('error', 'Lotação não encontrada.');
            $this->redirect('/mte-destinations');
        }

        $this->view('mte_destinations/show', [
            'title' => 'Detalhe da Lotação MTE',
            'destination' => $destination,
            'canManage' => $this->app->auth()->hasPermission('mte_destinations.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Lotação inválida.');
            $this->redirect('/mte-destinations');
        }

        $destination = $this->service()->find($id);
        if ($destination === null) {
            flash('error', 'Lotação não encontrada.');
            $this->redirect('/mte-destinations');
        }

        $this->view('mte_destinations/edit', [
            'title' => 'Editar Lotação MTE',
            'destination' => $destination,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Lotação inválida.');
            $this->redirect('/mte-destinations');
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
            $this->redirect('/mte-destinations/edit?id=' . $id);
        }

        flash('success', 'Lotação atualizada com sucesso.');
        $this->redirect('/mte-destinations/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Lotação inválida.');
            $this->redirect('/mte-destinations');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Lotação não encontrada ou já removida.');
            $this->redirect('/mte-destinations');
        }

        flash('success', 'Lotação removida com sucesso.');
        $this->redirect('/mte-destinations');
    }

    /** @return array<string, mixed> */
    private function emptyDestination(): array
    {
        return [
            'name' => '',
            'code' => '',
            'notes' => '',
        ];
    }

    private function service(): MteDestinationService
    {
        return new MteDestinationService(
            new MteDestinationRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
