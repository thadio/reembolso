<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CostMirrorRepository;
use App\Services\CostMirrorService;

final class CostMirrorsController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'organ_id' => max(0, (int) $request->input('organ_id', '0')),
            'person_id' => max(0, (int) $request->input('person_id', '0')),
            'reference_month' => (string) $request->input('reference_month', ''),
            'sort' => (string) $request->input('sort', 'reference_month'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);

        $this->view('cost_mirrors/index', [
            'title' => 'Espelhos de custo',
            'mirrors' => $result['items'],
            'filters' => [
                ...$filters,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
            'people' => $this->service()->activePeople($filters['organ_id'], 800),
            'canManage' => $this->app->auth()->hasPermission('cost_mirror.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $filterOrganId = max(0, (int) $request->input('filter_organ_id', old('filter_organ_id', '0')));
        $referenceMonth = (string) $request->input('reference_month', old('reference_month', date('Y-m')));

        $this->view('cost_mirrors/create', [
            'title' => 'Novo espelho de custo',
            'mirror' => $this->emptyMirror($referenceMonth),
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
            'people' => $this->service()->activePeople($filterOrganId, 1200),
            'invoices' => $this->service()->activeInvoices($filterOrganId, $referenceMonth, 600),
            'filterOrganId' => $filterOrganId,
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->create(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/create');
        }

        flash('success', 'Espelho de custo cadastrado com sucesso.');
        $this->redirect('/cost-mirrors/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Espelho invalido.');
            $this->redirect('/cost-mirrors');
        }

        $mirror = $this->service()->find($id);
        if ($mirror === null) {
            flash('error', 'Espelho nao encontrado.');
            $this->redirect('/cost-mirrors');
        }

        $organId = max(0, (int) ($mirror['organ_id'] ?? 0));
        $referenceMonth = substr((string) ($mirror['reference_month'] ?? ''), 0, 7);

        $this->view('cost_mirrors/show', [
            'title' => 'Detalhe do espelho',
            'mirror' => $mirror,
            'items' => $this->service()->items($id),
            'statusOptions' => $this->service()->statusOptions(),
            'invoices' => $this->service()->activeInvoices($organId, $referenceMonth, 600),
            'canManage' => $this->app->auth()->hasPermission('cost_mirror.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Espelho invalido.');
            $this->redirect('/cost-mirrors');
        }

        $mirror = $this->service()->find($id);
        if ($mirror === null) {
            flash('error', 'Espelho nao encontrado.');
            $this->redirect('/cost-mirrors');
        }

        $filterOrganId = max(
            0,
            (int) $request->input('filter_organ_id', old('filter_organ_id', (string) ((int) ($mirror['organ_id'] ?? 0))))
        );
        $referenceMonth = (string) $request->input('reference_month', old('reference_month', substr((string) ($mirror['reference_month'] ?? date('Y-m-01')), 0, 7)));

        $this->view('cost_mirrors/edit', [
            'title' => 'Editar espelho de custo',
            'mirror' => $mirror,
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
            'people' => $this->service()->activePeople($filterOrganId, 1200),
            'invoices' => $this->service()->activeInvoices($filterOrganId, $referenceMonth, 600),
            'filterOrganId' => $filterOrganId,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Espelho invalido.');
            $this->redirect('/cost-mirrors');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->update(
            id: $id,
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/edit?id=' . $id);
        }

        flash('success', 'Espelho atualizado com sucesso.');
        $this->redirect('/cost-mirrors/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Espelho invalido.');
            $this->redirect('/cost-mirrors');
        }

        $deleted = $this->service()->delete(
            id: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Espelho nao encontrado ou ja removido.');
            $this->redirect('/cost-mirrors');
        }

        flash('success', 'Espelho removido com sucesso.');
        $this->redirect('/cost-mirrors');
    }

    public function storeItem(Request $request): void
    {
        $mirrorId = (int) $request->input('mirror_id', '0');
        if ($mirrorId <= 0) {
            flash('error', 'Espelho invalido para item.');
            $this->redirect('/cost-mirrors');
        }

        $result = $this->service()->addItem(
            mirrorId: $mirrorId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
    }

    public function importItems(Request $request): void
    {
        $mirrorId = (int) $request->input('mirror_id', '0');
        if ($mirrorId <= 0) {
            flash('error', 'Espelho invalido para importacao CSV.');
            $this->redirect('/cost-mirrors');
        }

        $file = is_array($_FILES['items_csv'] ?? null) ? $_FILES['items_csv'] : null;

        $result = $this->service()->importCsv(
            mirrorId: $mirrorId,
            file: $file,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
    }

    public function destroyItem(Request $request): void
    {
        $mirrorId = (int) $request->input('mirror_id', '0');
        $itemId = (int) $request->input('item_id', '0');

        if ($mirrorId <= 0 || $itemId <= 0) {
            flash('error', 'Dados invalidos para remocao de item.');
            $this->redirect('/cost-mirrors');
        }

        $result = $this->service()->removeItem(
            mirrorId: $mirrorId,
            itemId: $itemId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/show?id=' . $mirrorId);
    }

    /** @return array<string, mixed> */
    private function emptyMirror(string $referenceMonth = ''): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $referenceMonth) === 1 ? $referenceMonth : date('Y-m');

        return [
            'person_id' => '',
            'invoice_id' => '',
            'reference_month' => $month,
            'title' => '',
            'status' => 'aberto',
            'notes' => '',
        ];
    }

    private function service(): CostMirrorService
    {
        return new CostMirrorService(
            new CostMirrorRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
