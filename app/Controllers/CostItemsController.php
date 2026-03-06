<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CostItemCatalogRepository;
use App\Services\CostItemCatalogService;

final class CostItemsController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $linkage = (string) $request->input('linkage', '');
        $reimbursable = (string) $request->input('reimbursable', '');
        $periodicity = (string) $request->input('periodicity', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        if (!in_array($linkage, ['', '309', '510'], true)) {
            $linkage = '';
        }

        if (!in_array($reimbursable, ['', 'reimbursable', 'non_reimbursable'], true)) {
            $reimbursable = '';
        }

        if (!in_array($periodicity, ['', 'mensal', 'anual', 'unico'], true)) {
            $periodicity = '';
        }

        $result = $this->service()->paginate(
            $query,
            $linkage,
            $reimbursable,
            $periodicity,
            $sort,
            $dir,
            $page,
            $perPage
        );

        $this->view('cost_items/index', [
            'title' => 'Itens de Custo',
            'items' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'linkage' => $linkage,
                'reimbursable' => $reimbursable,
                'periodicity' => $periodicity,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('cost_item.manage'),
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursableOptions' => $this->service()->reimbursableOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('cost_items/create', [
            'title' => 'Novo Item de Custo',
            'item' => $this->emptyItem(),
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursableOptions' => $this->service()->reimbursableOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
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
            $this->redirect('/cost-items/create');
        }

        flash('success', 'Item de custo cadastrado com sucesso.');
        $this->redirect('/cost-items/show?id=' . (int) ($result['id'] ?? 0));
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Item de custo invalido.');
            $this->redirect('/cost-items');
        }

        $item = $this->service()->find($id);
        if ($item === null) {
            flash('error', 'Item de custo nao encontrado.');
            $this->redirect('/cost-items');
        }

        $this->view('cost_items/show', [
            'title' => 'Detalhe do Item de Custo',
            'item' => $item,
            'canManage' => $this->app->auth()->hasPermission('cost_item.manage'),
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursableOptions' => $this->service()->reimbursableOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Item de custo invalido.');
            $this->redirect('/cost-items');
        }

        $item = $this->service()->find($id);
        if ($item === null) {
            flash('error', 'Item de custo nao encontrado.');
            $this->redirect('/cost-items');
        }

        $this->view('cost_items/edit', [
            'title' => 'Editar Item de Custo',
            'item' => $item,
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursableOptions' => $this->service()->reimbursableOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Item de custo invalido.');
            $this->redirect('/cost-items');
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
            $this->redirect('/cost-items/edit?id=' . $id);
        }

        flash('success', 'Item de custo atualizado com sucesso.');
        $this->redirect('/cost-items/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Item de custo invalido.');
            $this->redirect('/cost-items');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Item de custo nao encontrado ou ja removido.');
            $this->redirect('/cost-items');
        }

        flash('success', 'Item de custo removido com sucesso.');
        $this->redirect('/cost-items');
    }

    /** @return array<string, mixed> */
    private function emptyItem(): array
    {
        return [
            'name' => '',
            'linkage_code' => 309,
            'is_reimbursable' => 1,
            'payment_periodicity' => 'mensal',
        ];
    }

    private function service(): CostItemCatalogService
    {
        return new CostItemCatalogService(
            new CostItemCatalogRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
