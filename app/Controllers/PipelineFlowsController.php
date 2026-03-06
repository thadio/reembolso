<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\PipelineFlowRepository;
use App\Services\PipelineFlowService;

final class PipelineFlowsController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($query, $sort, $dir, $page, $perPage);

        $this->view('pipeline_flows/index', [
            'title' => 'Fluxos BPMN',
            'flows' => $result['items'],
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
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('pipeline_flows/create', [
            'title' => 'Novo Fluxo BPMN',
            'flow' => $this->emptyFlow(),
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
            $this->redirect('/pipeline-flows/create');
        }

        flash('success', 'Fluxo cadastrado com sucesso.');
        $this->redirect('/pipeline-flows/show?id=' . (int) ($result['id'] ?? 0));
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Fluxo invalido.');
            $this->redirect('/pipeline-flows');
        }

        $detail = $this->service()->detailData($id);
        if ($detail['flow'] === null) {
            flash('error', 'Fluxo nao encontrado.');
            $this->redirect('/pipeline-flows');
        }

        $this->view('pipeline_flows/show', [
            'title' => 'Detalhe do Fluxo BPMN',
            'flow' => $detail['flow'],
            'steps' => $detail['steps'],
            'transitions' => $detail['transitions'],
            'statusCatalog' => $detail['status_catalog'],
            'nodeKindOptions' => $detail['node_kind_options'],
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Fluxo invalido.');
            $this->redirect('/pipeline-flows');
        }

        $flow = $this->service()->find($id);
        if ($flow === null) {
            flash('error', 'Fluxo nao encontrado.');
            $this->redirect('/pipeline-flows');
        }

        $this->view('pipeline_flows/edit', [
            'title' => 'Editar Fluxo BPMN',
            'flow' => $flow,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Fluxo invalido.');
            $this->redirect('/pipeline-flows');
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
            $this->redirect('/pipeline-flows/edit?id=' . $id);
        }

        flash('success', 'Fluxo atualizado com sucesso.');
        $this->redirect('/pipeline-flows/show?id=' . $id);
    }

    public function updateDiagram(Request $request): void
    {
        $flowId = (int) $request->input('flow_id', '0');
        if ($flowId <= 0) {
            flash('error', 'Fluxo invalido.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->updateDiagram(
            $flowId,
            $request->all(),
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $flowId);
        }

        flash('success', $result['message']);
        $this->redirect('/pipeline-flows/show?id=' . $flowId);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Fluxo invalido.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $id);
        }

        flash('success', 'Fluxo removido com sucesso.');
        $this->redirect('/pipeline-flows');
    }

    public function upsertStep(Request $request): void
    {
        $flowId = (int) $request->input('flow_id', '0');
        if ($flowId <= 0) {
            flash('error', 'Fluxo invalido para etapa.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->upsertStep(
            $flowId,
            $request->all(),
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $flowId);
        }

        flash('success', $result['message']);
        $this->redirect('/pipeline-flows/show?id=' . $flowId);
    }

    public function deleteStep(Request $request): void
    {
        $flowId = (int) $request->input('flow_id', '0');
        $statusId = (int) $request->input('status_id', '0');
        if ($flowId <= 0 || $statusId <= 0) {
            flash('error', 'Dados invalidos para remover etapa.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->deleteStep(
            $flowId,
            $statusId,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $flowId);
        }

        flash('success', $result['message']);
        $this->redirect('/pipeline-flows/show?id=' . $flowId);
    }

    public function upsertTransition(Request $request): void
    {
        $flowId = (int) $request->input('flow_id', '0');
        if ($flowId <= 0) {
            flash('error', 'Fluxo invalido para transicao.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->upsertTransition(
            $flowId,
            $request->all(),
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $flowId);
        }

        flash('success', $result['message']);
        $this->redirect('/pipeline-flows/show?id=' . $flowId);
    }

    public function deleteTransition(Request $request): void
    {
        $flowId = (int) $request->input('flow_id', '0');
        $transitionId = (int) $request->input('transition_id', '0');
        if ($flowId <= 0 || $transitionId <= 0) {
            flash('error', 'Dados invalidos para remover transicao.');
            $this->redirect('/pipeline-flows');
        }

        $result = $this->service()->deleteTransition(
            $flowId,
            $transitionId,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/pipeline-flows/show?id=' . $flowId);
        }

        flash('success', $result['message']);
        $this->redirect('/pipeline-flows/show?id=' . $flowId);
    }

    /** @return array<string, mixed> */
    private function emptyFlow(): array
    {
        return [
            'name' => '',
            'description' => '',
            'is_active' => 1,
            'is_default' => 0,
        ];
    }

    private function service(): PipelineFlowService
    {
        return new PipelineFlowService(
            new PipelineFlowRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
