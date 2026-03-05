<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\LgpdRepository;
use App\Services\LgpdService;

final class LgpdController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'action' => (string) $request->input('action', ''),
            'sensitivity' => (string) $request->input('sensitivity', ''),
            'user_id' => max(0, (int) $request->input('user_id', '0')),
            'from_date' => (string) $request->input('from_date', ''),
            'to_date' => (string) $request->input('to_date', ''),
            'sort' => (string) $request->input('sort', 'created_at'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(10, min(100, (int) $request->input('per_page', '20')));

        $result = $this->service()->dashboard($filters, $page, $perPage);

        $this->view('lgpd/index', [
            'title' => 'LGPD avancado',
            'filters' => [
                ...$result['filters'],
                'per_page' => $perPage,
            ],
            'logs' => $result['logs']['items'],
            'pagination' => [
                'total' => $result['logs']['total'],
                'page' => $result['logs']['page'],
                'per_page' => $result['logs']['per_page'],
                'pages' => $result['logs']['pages'],
            ],
            'summary' => $result['summary'],
            'actions' => $result['actions'],
            'sensitivities' => $result['sensitivities'],
            'users' => $result['users'],
            'policies' => $result['policies'],
            'runs' => $result['runs'],
            'latestRun' => $result['latest_run'],
            'sensitivityLabel' => [$this->service(), 'sensitivityLabel'],
            'canManage' => $this->app->auth()->hasPermission('lgpd.manage'),
        ]);
    }

    public function exportAccessCsv(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'action' => (string) $request->input('action', ''),
            'sensitivity' => (string) $request->input('sensitivity', ''),
            'user_id' => max(0, (int) $request->input('user_id', '0')),
            'from_date' => (string) $request->input('from_date', ''),
            'to_date' => (string) $request->input('to_date', ''),
            'sort' => (string) $request->input('sort', 'created_at'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $export = $this->service()->exportAccessCsv(
            inputFilters: $filters,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $export['file_name'] . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");

        foreach ($export['rows'] as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }

        fclose($output);
        exit;
    }

    public function upsertPolicy(Request $request): void
    {
        $result = $this->service()->upsertPolicy(
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/lgpd');
        }

        flash('success', 'Politica LGPD atualizada com sucesso.');
        $this->redirect('/lgpd');
    }

    public function runRetention(Request $request): void
    {
        $mode = (string) $request->input('mode', 'preview');
        $apply = $mode === 'apply';

        $result = $this->service()->executeRetention(
            apply: $apply,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/lgpd');
        }

        $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
        $summary = sprintf(
            ' Candidatos: logs sensiveis %d, audit %d, pessoas %d, usuarios %d. Aplicados: logs sensiveis %d, audit %d, pessoas %d, usuarios %d.',
            (int) ($stats['sensitive_access_candidates'] ?? 0),
            (int) ($stats['audit_log_candidates'] ?? 0),
            (int) ($stats['people_candidates'] ?? 0),
            (int) ($stats['users_candidates'] ?? 0),
            (int) ($stats['sensitive_access_purged'] ?? 0),
            (int) ($stats['audit_log_purged'] ?? 0),
            (int) ($stats['people_anonymized'] ?? 0),
            (int) ($stats['users_anonymized'] ?? 0),
        );

        flash('success', (string) ($result['message'] ?? 'Rotina LGPD executada.') . $summary);
        $this->redirect('/lgpd');
    }

    private function service(): LgpdService
    {
        return new LgpdService(
            new LgpdRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
