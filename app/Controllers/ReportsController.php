<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\ReportRepository;
use App\Services\ReportPdfBuilder;
use App\Services\ReportService;

final class ReportsController extends Controller
{
    public function index(Request $request): void
    {
        $service = $this->service();
        $filters = $this->filtersFromRequest($request);

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(10, min(100, (int) $request->input('per_page', '20')));

        $result = $service->dashboard($filters, $page, $perPage);

        $this->view('reports/index', [
            'title' => 'Relatorios premium',
            'filters' => [
                ...$result['filters'],
                'per_page' => $perPage,
            ],
            'operational' => $result['operational'],
            'financial' => $result['financial'],
            'organs' => $result['organs'],
            'statusOptions' => $result['status_options'],
            'severityOptions' => $result['severity_options'],
            'severityLabel' => [$service, 'severityLabel'],
        ]);
    }

    public function exportCsv(Request $request): void
    {
        $service = $this->service();
        $filters = $this->filtersFromRequest($request);

        $export = $service->exportCsv(
            inputFilters: $filters,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
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

    public function exportPdf(Request $request): void
    {
        $service = $this->service();
        $filters = $this->filtersFromRequest($request);

        $export = $service->exportPdf(
            inputFilters: $filters,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $export['file_name'] . '"');
        header('Content-Length: ' . (string) strlen($export['binary']));

        echo $export['binary'];
        exit;
    }

    public function exportZip(Request $request): void
    {
        $service = $this->service();
        $filters = $this->filtersFromRequest($request);

        $export = $service->exportZip(
            inputFilters: $filters,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (($export['ok'] ?? false) !== true) {
            $errors = is_array($export['errors'] ?? null) ? $export['errors'] : ['Falha ao gerar pacote ZIP.'];
            flash('error', implode(' ', $errors));
            $this->redirect('/reports?' . http_build_query($filters));
        }

        $path = (string) ($export['path'] ?? '');
        $fileName = (string) ($export['file_name'] ?? 'prestacao-contas.zip');

        if ($path === '' || !is_file($path)) {
            flash('error', 'Arquivo ZIP nao encontrado para download.');
            $this->redirect('/reports?' . http_build_query($filters));
        }

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . (string) filesize($path));
        }

        readfile($path);
        @unlink($path);
        exit;
    }

    public function exportAuditZip(Request $request): void
    {
        $service = $this->service();
        $filters = $this->filtersFromRequest($request);

        $export = $service->exportAuditZip(
            inputFilters: $filters,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (($export['ok'] ?? false) !== true) {
            $errors = is_array($export['errors'] ?? null) ? $export['errors'] : ['Falha ao gerar pacote de auditoria.'];
            flash('error', implode(' ', $errors));
            $this->redirect('/reports?' . http_build_query($filters));
        }

        $path = (string) ($export['path'] ?? '');
        $fileName = (string) ($export['file_name'] ?? 'auditoria-cgu-tcu.zip');

        if ($path === '' || !is_file($path)) {
            flash('error', 'Arquivo ZIP de auditoria nao encontrado para download.');
            $this->redirect('/reports?' . http_build_query($filters));
        }

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . (string) filesize($path));
        }

        readfile($path);
        @unlink($path);
        exit;
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => (string) $request->input('q', ''),
            'organ_id' => (string) $request->input('organ_id', '0'),
            'status_code' => (string) $request->input('status_code', ''),
            'severity' => (string) $request->input('severity', ''),
            'year' => (string) $request->input('year', (string) date('Y')),
            'month_from' => (string) $request->input('month_from', '1'),
            'month_to' => (string) $request->input('month_to', '12'),
            'sort' => (string) $request->input('sort', 'days_in_status'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];
    }

    private function service(): ReportService
    {
        return new ReportService(
            new ReportRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            new ReportPdfBuilder(),
            $this->app->config(),
        );
    }
}
