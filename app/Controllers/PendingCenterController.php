<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\PendingCenterRepository;
use App\Services\PendingCenterService;

final class PendingCenterController extends Controller
{
    public function index(Request $request): void
    {
        $authUserId = (int) ($this->app->auth()->id() ?? 0);

        $filters = [
            'q' => (string) $request->input('q', ''),
            'pending_type' => (string) $request->input('pending_type', ''),
            'status' => (string) $request->input('status', ''),
            'severity' => (string) $request->input('severity', ''),
            'queue_scope' => (string) $request->input('queue_scope', 'all'),
            'responsible_id' => max(0, (int) $request->input('responsible_id', '0')),
            'sort' => (string) $request->input('sort', 'updated_at'),
            'dir' => (string) $request->input('dir', 'desc'),
            'queue_user_id' => $authUserId,
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(10, min(60, (int) $request->input('per_page', '20')));

        $result = $this->service()->panel(
            filters: $filters,
            page: $page,
            perPage: $perPage,
            userId: $authUserId,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->view('people/pending', [
            'title' => 'Central de pendencias',
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
            'typeOptions' => $this->service()->typeOptions(),
            'statusOptions' => $this->service()->statusOptions(),
            'severityOptions' => $this->service()->severityOptions(),
            'responsibleOptions' => $this->service()->responsibleOptions(350),
            'authUserId' => $authUserId,
            'canManage' => $this->app->auth()->hasPermission('people.manage'),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $pendingId = (int) $request->input('pending_id', '0');
        $status = (string) $request->input('status', 'aberta');

        $result = $this->service()->updateStatus(
            pendingId: $pendingId,
            status: $status,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/people/pending');
        }

        flash('success', $result['message']);
        $this->redirect('/people/pending');
    }

    private function service(): PendingCenterService
    {
        return new PendingCenterService(
            new PendingCenterRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
