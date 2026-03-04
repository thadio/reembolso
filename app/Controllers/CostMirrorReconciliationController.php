<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\CostMirrorReconciliationRepository;
use App\Services\CostMirrorReconciliationService;

final class CostMirrorReconciliationController extends Controller
{
    public function show(Request $request): void
    {
        $mirrorId = (int) $request->input('id', '0');
        if ($mirrorId <= 0) {
            flash('error', 'Espelho invalido para conciliacao.');
            $this->redirect('/cost-mirrors');
        }

        $data = $this->reconciliationService()->detailData($mirrorId);
        if ($data['mirror'] === null) {
            flash('error', 'Espelho nao encontrado para conciliacao.');
            $this->redirect('/cost-mirrors');
        }

        $this->view('cost_mirrors/reconciliation', [
            'title' => 'Conciliacao avancada',
            'mirror' => $data['mirror'],
            'review' => $data['review'],
            'divergences' => $data['divergences'],
            'summary' => $data['summary'],
            'canManage' => $this->app->auth()->hasPermission('cost_mirror.manage'),
            'isLocked' => $this->reconciliationService()->isMirrorLocked($mirrorId),
        ]);
    }

    public function run(Request $request): void
    {
        $mirrorId = (int) $request->input('cost_mirror_id', '0');
        if ($mirrorId <= 0) {
            flash('error', 'Espelho invalido para conciliacao.');
            $this->redirect('/cost-mirrors');
        }

        $result = $this->reconciliationService()->run(
            mirrorId: $mirrorId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
    }

    public function justify(Request $request): void
    {
        $mirrorId = (int) $request->input('cost_mirror_id', '0');
        $divergenceId = (int) $request->input('divergence_id', '0');
        $justification = (string) $request->input('justification_text', '');

        if ($mirrorId <= 0 || $divergenceId <= 0) {
            flash('error', 'Dados invalidos para justificativa.');
            $this->redirect('/cost-mirrors');
        }

        $result = $this->reconciliationService()->justify(
            mirrorId: $mirrorId,
            divergenceId: $divergenceId,
            justification: $justification,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
    }

    public function approve(Request $request): void
    {
        $mirrorId = (int) $request->input('cost_mirror_id', '0');
        $notes = (string) $request->input('approval_notes', '');

        if ($mirrorId <= 0) {
            flash('error', 'Espelho invalido para aprovacao da conciliacao.');
            $this->redirect('/cost-mirrors');
        }

        $result = $this->reconciliationService()->approve(
            mirrorId: $mirrorId,
            notes: $notes,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
        }

        flash('success', $result['message']);
        $this->redirect('/cost-mirrors/reconciliation/show?id=' . $mirrorId);
    }

    private function reconciliationService(): CostMirrorReconciliationService
    {
        return new CostMirrorReconciliationService(
            new CostMirrorReconciliationRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
