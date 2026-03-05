<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\SlaAlertRepository;
use App\Services\SlaAlertService;

final class SlaAlertsController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status_code' => (string) $request->input('status_code', ''),
            'severity' => (string) $request->input('severity', ''),
            'control_status' => (string) $request->input('control_status', ''),
            'sort' => (string) $request->input('sort', 'status_order'),
            'dir' => (string) $request->input('dir', 'asc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->panel($filters, $page, $perPage);

        $this->view('sla_alerts/index', [
            'title' => 'SLA e pendencias',
            'items' => $result['items'],
            'summary' => $result['summary'],
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
            'severityOptions' => $this->service()->severityOptions(),
            'controlStatusOptions' => $this->service()->controlStatusOptions(),
            'controlOwnerOptions' => $this->service()->controlOwnerOptions(350),
            'severityLabel' => [$this->service(), 'severityLabel'],
            'recentLogs' => $this->service()->recentLogs(12),
            'canManage' => $this->app->auth()->hasPermission('sla.manage'),
        ]);
    }

    public function rules(Request $request): void
    {
        $this->view('sla_alerts/rules', [
            'title' => 'Regras de SLA',
            'rows' => $this->service()->rulesGrid(),
        ]);
    }

    public function upsertRule(Request $request): void
    {
        $result = $this->service()->saveRule(
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/sla-alerts/rules');
        }

        flash('success', 'Regra de SLA salva com sucesso.');
        $this->redirect('/sla-alerts/rules');
    }

    public function dispatchEmail(Request $request): void
    {
        $severity = (string) $request->input('severity', 'all');

        $result = $this->service()->dispatchNotifications(
            severity: $severity,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (($result['attempted'] ?? 0) <= 0) {
            flash('error', 'Nenhum alerta elegivel para envio de email.');
            $this->redirect('/sla-alerts');
        }

        if (($result['sent'] ?? 0) <= 0) {
            flash('error', (string) ($result['message'] ?? 'Disparo executado sem envios efetivos.'));
            $this->redirect('/sla-alerts');
        }

        flash('success', (string) ($result['message'] ?? 'Disparo executado.'));
        $this->redirect('/sla-alerts');
    }

    public function updateControl(Request $request): void
    {
        $returnTo = (string) $request->input('return_to', '/sla-alerts');
        if ($returnTo !== '/sla-alerts' && !str_starts_with($returnTo, '/sla-alerts?')) {
            $returnTo = '/sla-alerts';
        }

        $result = $this->service()->updateCaseControl(
            assignmentId: (int) $request->input('assignment_id', '0'),
            controlStatus: (string) $request->input('control_status', 'aberto'),
            ownerUserId: max(0, (int) $request->input('owner_user_id', '0')),
            note: (string) $request->input('note', ''),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect($returnTo);
        }

        flash('success', (string) ($result['message'] ?? 'Controle de atraso atualizado.'));
        $this->redirect($returnTo);
    }

    private function service(): SlaAlertService
    {
        return new SlaAlertService(
            new SlaAlertRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config()
        );
    }
}
