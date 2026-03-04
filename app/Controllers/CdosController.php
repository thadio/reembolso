<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CdoRepository;
use App\Services\CdoService;

final class CdosController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'sort' => (string) $request->input('sort', 'period_start'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);

        $this->view('cdos/index', [
            'title' => 'CDOs',
            'cdos' => $result['items'],
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
            'canManage' => $this->app->auth()->hasPermission('cdo.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('cdos/create', [
            'title' => 'Novo CDO',
            'cdo' => $this->emptyCdo(),
            'statusOptions' => $this->service()->statusOptions(),
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
            $this->redirect('/cdos/create');
        }

        flash('success', 'CDO cadastrado com sucesso.');
        $this->redirect('/cdos/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'CDO invalido.');
            $this->redirect('/cdos');
        }

        $cdo = $this->service()->find($id);
        if ($cdo === null) {
            flash('error', 'CDO nao encontrado.');
            $this->redirect('/cdos');
        }

        $canManage = $this->app->auth()->hasPermission('cdo.manage');

        $this->view('cdos/show', [
            'title' => 'Detalhe do CDO',
            'cdo' => $cdo,
            'links' => $this->service()->links($id),
            'availablePeople' => $canManage ? $this->service()->availablePeople($id, 500) : [],
            'canManage' => $canManage,
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'CDO invalido.');
            $this->redirect('/cdos');
        }

        $cdo = $this->service()->find($id);
        if ($cdo === null) {
            flash('error', 'CDO nao encontrado.');
            $this->redirect('/cdos');
        }

        $this->view('cdos/edit', [
            'title' => 'Editar CDO',
            'cdo' => $cdo,
            'statusOptions' => $this->service()->statusOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'CDO invalido.');
            $this->redirect('/cdos');
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
            $this->redirect('/cdos/edit?id=' . $id);
        }

        flash('success', 'CDO atualizado com sucesso.');
        $this->redirect('/cdos/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'CDO invalido.');
            $this->redirect('/cdos');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'CDO nao encontrado ou ja removido.');
            $this->redirect('/cdos');
        }

        flash('success', 'CDO removido com sucesso.');
        $this->redirect('/cdos');
    }

    public function linkPerson(Request $request): void
    {
        $cdoId = (int) $request->input('cdo_id', '0');
        if ($cdoId <= 0) {
            flash('error', 'CDO invalido para vinculo.');
            $this->redirect('/cdos');
        }

        $result = $this->service()->linkPerson(
            cdoId: $cdoId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cdos/show?id=' . $cdoId);
        }

        flash('success', $result['message']);
        $this->redirect('/cdos/show?id=' . $cdoId);
    }

    public function unlinkPerson(Request $request): void
    {
        $cdoId = (int) $request->input('cdo_id', '0');
        $linkId = (int) $request->input('link_id', '0');

        if ($cdoId <= 0 || $linkId <= 0) {
            flash('error', 'Dados invalidos para remocao de vinculo.');
            $this->redirect('/cdos');
        }

        $result = $this->service()->unlinkPerson(
            cdoId: $cdoId,
            linkId: $linkId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cdos/show?id=' . $cdoId);
        }

        flash('success', $result['message']);
        $this->redirect('/cdos/show?id=' . $cdoId);
    }

    /** @return array<string, mixed> */
    private function emptyCdo(): array
    {
        return [
            'number' => '',
            'ug_code' => '',
            'action_code' => '',
            'period_start' => '',
            'period_end' => '',
            'total_amount' => '',
            'status' => 'aberto',
            'notes' => '',
        ];
    }

    private function service(): CdoService
    {
        return new CdoService(
            new CdoRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
