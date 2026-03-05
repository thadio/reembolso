<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\ReportRepository;
use Throwable;
use ZipArchive;

final class ReportService
{
    private const ALLOWED_SEVERITIES = ['no_prazo', 'em_risco', 'vencido'];
    private const ALLOWED_SORTS = ['person_name', 'organ_name', 'status_order', 'days_in_status', 'sla_level', 'updated_at'];

    public function __construct(
        private ReportRepository $reports,
        private AuditService $audit,
        private EventService $events,
        private ReportPdfBuilder $pdfBuilder,
        private Config $config,
    ) {
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array<string, mixed>
     */
    public function dashboard(array $inputFilters, int $page, int $perPage): array
    {
        $filters = $this->normalizeFilters($inputFilters);

        $operationalSummary = $this->reports->operationalSummary($filters);
        $operationalBottlenecks = $this->reports->operationalBottlenecks($filters, 8);
        $operationalList = $this->reports->paginateOperationalRows($filters, $page, $perPage);
        $financial = $this->reports->financialDataset($filters);

        return [
            'filters' => $filters,
            'operational' => [
                'summary' => $operationalSummary,
                'bottlenecks' => $operationalBottlenecks,
                'items' => $operationalList['items'],
                'pagination' => [
                    'total' => $operationalList['total'],
                    'page' => $operationalList['page'],
                    'per_page' => $operationalList['per_page'],
                    'pages' => $operationalList['pages'],
                ],
            ],
            'financial' => $financial,
            'organs' => $this->reports->activeOrgans(),
            'status_options' => $this->reports->activeStatusesForSla(),
            'severity_options' => $this->severityOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{file_name: string, rows: array<int, array<int, string|int|float>>}
     */
    public function exportCsv(array $inputFilters, int $userId, string $ip, string $userAgent): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        $payload = $this->buildCsvPayload($filters);

        $this->audit->log(
            entity: 'report',
            entityId: null,
            action: 'export_csv',
            beforeData: null,
            afterData: [
                'filters' => $filters,
                'rows_exported' => $payload['operational_rows_count'],
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'report',
            type: 'report.export_csv',
            payload: [
                'year' => $filters['year'],
                'month_from' => $filters['month_from'],
                'month_to' => $filters['month_to'],
                'organ_id' => $filters['organ_id'],
                'rows_exported' => $payload['operational_rows_count'],
            ],
            entityId: null,
            userId: $userId,
        );

        return [
            'file_name' => $payload['file_name'],
            'rows' => $payload['rows'],
        ];
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{file_name: string, binary: string}
     */
    public function exportPdf(array $inputFilters, int $userId, string $ip, string $userAgent): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        $payload = $this->buildPdfPayload($filters);

        $this->audit->log(
            entity: 'report',
            entityId: null,
            action: 'export_pdf',
            beforeData: null,
            afterData: [
                'filters' => $filters,
                'rows_in_pdf' => $payload['rows_in_pdf'],
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'report',
            type: 'report.export_pdf',
            payload: [
                'year' => $filters['year'],
                'month_from' => $filters['month_from'],
                'month_to' => $filters['month_to'],
                'organ_id' => $filters['organ_id'],
                'rows_in_pdf' => $payload['rows_in_pdf'],
            ],
            entityId: null,
            userId: $userId,
        );

        return [
            'file_name' => $payload['file_name'],
            'binary' => $payload['binary'],
        ];
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{
     *   ok: bool,
     *   errors: array<int, string>,
     *   file_name: string,
     *   path: string,
     *   stats: array<string, int>
     * }
     */
    public function exportZip(array $inputFilters, int $userId, string $ip, string $userAgent): array
    {
        if (!class_exists(ZipArchive::class)) {
            return [
                'ok' => false,
                'errors' => ['Extensao ZipArchive nao disponivel no PHP do servidor.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $filters = $this->normalizeFilters($inputFilters);
        $csvPayload = $this->buildCsvPayload($filters);
        $pdfPayload = $this->buildPdfPayload($filters);

        $invoices = $this->reports->accountabilityInvoices($filters, 5000);
        $payments = $this->reports->accountabilityPayments($filters, 8000);

        $zipPath = $this->createZipTempPath();
        if ($zipPath === null) {
            return [
                'ok' => false,
                'errors' => ['Nao foi possivel criar arquivo temporario para o pacote ZIP.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $fileName = sprintf('prestacao-contas-%04d-%02d-%02d-%s.zip', (int) $filters['year'], (int) $filters['month_from'], (int) $filters['month_to'], date('Ymd_His'));

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            @unlink($zipPath);

            return [
                'ok' => false,
                'errors' => ['Falha ao abrir arquivo ZIP temporario para escrita.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $usedZipPaths = [];
        $missingFiles = 0;
        $attachedInvoiceFiles = 0;
        $attachedPaymentFiles = 0;

        try {
            $zip->addFromString('relatorio/relatorio-premium.csv', $this->csvString($csvPayload['rows']));
            $zip->addFromString('relatorio/relatorio-premium.pdf', $pdfPayload['binary']);
            $zip->addFromString('prestacao/boletos.csv', $this->csvString($this->buildInvoiceCsvRows($invoices)));
            $zip->addFromString('prestacao/pagamentos.csv', $this->csvString($this->buildPaymentCsvRows($payments)));

            foreach ($invoices as $invoice) {
                $relative = trim((string) ($invoice['pdf_storage_path'] ?? ''));
                if ($relative === '') {
                    continue;
                }

                $path = $this->resolveUploadPath($relative);
                if ($path === null) {
                    $missingFiles++;
                    continue;
                }

                $invoiceId = (int) ($invoice['id'] ?? 0);
                $invoiceNumber = $this->safeFileName((string) ($invoice['invoice_number'] ?? ''), 'boleto');
                $original = $this->safeFileName((string) ($invoice['pdf_original_name'] ?? ''), 'boleto_' . $invoiceId . '.pdf');
                $zipName = 'prestacao/anexos/boletos/' . $invoiceNumber . '_' . $original;
                $zipName = $this->uniqueZipPath($zipName, $usedZipPaths);

                if ($zip->addFile($path, $zipName)) {
                    $attachedInvoiceFiles++;
                } else {
                    $missingFiles++;
                }
            }

            foreach ($payments as $payment) {
                $relative = trim((string) ($payment['proof_storage_path'] ?? ''));
                if ($relative === '') {
                    continue;
                }

                $path = $this->resolveUploadPath($relative);
                if ($path === null) {
                    $missingFiles++;
                    continue;
                }

                $paymentId = (int) ($payment['id'] ?? 0);
                $invoiceNumber = $this->safeFileName((string) ($payment['invoice_number'] ?? ''), 'invoice');
                $original = $this->safeFileName((string) ($payment['proof_original_name'] ?? ''), 'comprovante_' . $paymentId . '.pdf');
                $zipName = 'prestacao/anexos/comprovantes/' . $invoiceNumber . '_' . $paymentId . '_' . $original;
                $zipName = $this->uniqueZipPath($zipName, $usedZipPaths);

                if ($zip->addFile($path, $zipName)) {
                    $attachedPaymentFiles++;
                } else {
                    $missingFiles++;
                }
            }

            $manifestLines = [
                'Pacote de prestacao de contas - Fase 5.3',
                'Gerado em: ' . date('Y-m-d H:i:s'),
                'Periodo: ' . $this->periodLabel($filters),
                'Orgao: ' . $this->organLabel((int) $filters['organ_id']),
                'Filtro textual: ' . (string) ($filters['q'] !== '' ? $filters['q'] : 'n/a'),
                'Status operacional: ' . (string) ($filters['status_code'] !== '' ? $filters['status_code'] : 'todos'),
                'Severidade SLA: ' . $this->severityLabel((string) ($filters['severity'] ?? '')),
                '',
                'Resumo de conteudo:',
                '- relatorio CSV: relatorio/relatorio-premium.csv',
                '- relatorio PDF: relatorio/relatorio-premium.pdf',
                '- boletos CSV: prestacao/boletos.csv',
                '- pagamentos CSV: prestacao/pagamentos.csv',
                '- anexos de boletos adicionados: ' . $attachedInvoiceFiles,
                '- anexos de comprovantes adicionados: ' . $attachedPaymentFiles,
                '- anexos ausentes/inacessiveis: ' . $missingFiles,
            ];

            $zip->addFromString('manifesto.txt', implode("\n", $manifestLines) . "\n");
            $zip->close();
        } catch (Throwable $throwable) {
            if ($zip instanceof ZipArchive) {
                $zip->close();
            }
            @unlink($zipPath);

            return [
                'ok' => false,
                'errors' => ['Falha ao montar pacote ZIP: ' . $throwable->getMessage()],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $stats = [
            'invoices' => count($invoices),
            'payments' => count($payments),
            'invoice_files_attached' => $attachedInvoiceFiles,
            'payment_files_attached' => $attachedPaymentFiles,
            'missing_files' => $missingFiles,
        ];

        $this->audit->log(
            entity: 'report',
            entityId: null,
            action: 'export_zip',
            beforeData: null,
            afterData: [
                'filters' => $filters,
                'stats' => $stats,
                'file_name' => $fileName,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'report',
            type: 'report.export_zip',
            payload: [
                'year' => $filters['year'],
                'month_from' => $filters['month_from'],
                'month_to' => $filters['month_to'],
                'organ_id' => $filters['organ_id'],
                ...$stats,
            ],
            entityId: null,
            userId: $userId,
        );

        return [
            'ok' => true,
            'errors' => [],
            'file_name' => $fileName,
            'path' => $zipPath,
            'stats' => $stats,
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function severityOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os niveis'],
            ['value' => 'no_prazo', 'label' => 'No prazo'],
            ['value' => 'em_risco', 'label' => 'Em risco'],
            ['value' => 'vencido', 'label' => 'Vencido'],
        ];
    }

    public function severityLabel(string $severity): string
    {
        return match ($severity) {
            'no_prazo' => 'No prazo',
            'em_risco' => 'Em risco',
            'vencido' => 'Vencido',
            default => 'Todos',
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{file_name: string, rows: array<int, array<int, string|int|float>>, operational_rows_count: int}
     */
    private function buildCsvPayload(array $filters): array
    {
        $operationalSummary = $this->reports->operationalSummary($filters);
        $operationalBottlenecks = $this->reports->operationalBottlenecks($filters, 20);
        $operationalRows = $this->reports->operationalRowsForExport($filters, 5000);
        $financial = $this->reports->financialDataset($filters);
        $organLabel = $this->organLabel((int) $filters['organ_id']);

        $rows = [];
        $rows[] = ['Relatorio premium - Fase 5.3'];
        $rows[] = ['Gerado em', date('Y-m-d H:i:s')];
        $rows[] = ['Periodo', (string) $filters['period_start'] . ' ate ' . (string) $filters['period_end']];
        $rows[] = ['Orgao', $organLabel];
        $rows[] = ['Filtro textual', (string) ($filters['q'] ?? '')];
        $rows[] = ['Status', (string) ($filters['status_code'] !== '' ? $filters['status_code'] : 'todos')];
        $rows[] = ['Severidade SLA', (string) $this->severityLabel((string) ($filters['severity'] ?? ''))];
        $rows[] = [];

        $rows[] = ['Resumo operacional'];
        $rows[] = ['Total monitorado', (string) (int) ($operationalSummary['total'] ?? 0)];
        $rows[] = ['No prazo', (string) (int) ($operationalSummary['no_prazo'] ?? 0)];
        $rows[] = ['Em risco', (string) (int) ($operationalSummary['em_risco'] ?? 0)];
        $rows[] = ['Vencido', (string) (int) ($operationalSummary['vencido'] ?? 0)];
        $rows[] = ['Tempo medio (dias)', number_format((float) ($operationalSummary['avg_days_in_status'] ?? 0), 2, '.', '')];
        $rows[] = [];

        $rows[] = ['Gargalos operacionais'];
        $rows[] = ['status', 'casos', 'media_dias', 'max_dias', 'em_risco', 'vencido'];
        foreach ($operationalBottlenecks as $bottleneck) {
            $rows[] = [
                (string) ($bottleneck['status_label'] ?? $bottleneck['status_code'] ?? '-'),
                (string) (int) ($bottleneck['cases_count'] ?? 0),
                number_format((float) ($bottleneck['avg_days_in_status'] ?? 0), 2, '.', ''),
                (string) (int) ($bottleneck['max_days_in_status'] ?? 0),
                (string) (int) ($bottleneck['risco_count'] ?? 0),
                (string) (int) ($bottleneck['vencido_count'] ?? 0),
            ];
        }
        $rows[] = [];

        $rows[] = ['Detalhe operacional'];
        $rows[] = ['pessoa', 'orgao', 'status', 'dias_na_etapa', 'nivel_sla', 'atualizado_em', 'sei'];
        foreach ($operationalRows as $item) {
            $rows[] = [
                (string) ($item['person_name'] ?? '-'),
                (string) ($item['organ_name'] ?? '-'),
                (string) ($item['status_label'] ?? '-'),
                (string) (int) ($item['days_in_status'] ?? 0),
                $this->severityLabel((string) ($item['sla_level'] ?? '')),
                (string) ($item['status_changed_at'] ?? ''),
                (string) ($item['sei_process_number'] ?? ''),
            ];
        }
        $rows[] = [];

        $financialSummary = is_array($financial['summary'] ?? null) ? $financial['summary'] : [];
        $financialMonths = is_array($financial['months'] ?? null) ? $financial['months'] : [];

        $rows[] = ['Resumo financeiro'];
        $rows[] = ['previsto_total', number_format((float) ($financialSummary['forecast_total'] ?? 0), 2, '.', '')];
        $rows[] = ['efetivo_total', number_format((float) ($financialSummary['effective_total'] ?? 0), 2, '.', '')];
        $rows[] = ['pago_total', number_format((float) ($financialSummary['paid_total'] ?? 0), 2, '.', '')];
        $rows[] = ['a_pagar_total', number_format((float) ($financialSummary['payable_total'] ?? 0), 2, '.', '')];
        $rows[] = ['desvio_previsto_x_efetivo', number_format((float) ($financialSummary['variance_forecast_effective'] ?? 0), 2, '.', '')];
        $rows[] = ['aderencia_percentual', number_format((float) ($financialSummary['adherence_percent'] ?? 0), 2, '.', '')];
        $rows[] = ['cobertura_pagamento_percentual', number_format((float) ($financialSummary['payment_coverage_percent'] ?? 0), 2, '.', '')];
        $rows[] = [];

        $rows[] = ['Financeiro mensal'];
        $rows[] = ['mes', 'previsto', 'efetivo', 'pago', 'a_pagar'];
        foreach ($financialMonths as $month) {
            $rows[] = [
                (string) ($month['month_label'] ?? ''),
                number_format((float) ($month['forecast_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($month['effective_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($month['paid_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($month['payable_amount'] ?? 0), 2, '.', ''),
            ];
        }

        $fileName = sprintf('relatorio-premium-%04d-%02d-%02d-%s.csv', (int) $filters['year'], (int) $filters['month_from'], (int) $filters['month_to'], date('Ymd_His'));

        return [
            'file_name' => $fileName,
            'rows' => $rows,
            'operational_rows_count' => count($operationalRows),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{file_name: string, binary: string, rows_in_pdf: int}
     */
    private function buildPdfPayload(array $filters): array
    {
        $operationalSummary = $this->reports->operationalSummary($filters);
        $operationalBottlenecks = $this->reports->operationalBottlenecks($filters, 10);
        $operationalRows = $this->reports->operationalRowsForExport($filters, 220);
        $financial = $this->reports->financialDataset($filters);
        $organLabel = $this->organLabel((int) $filters['organ_id']);

        $lines = [];
        $lines[] = 'RELATORIO PREMIUM - FASE 5.3';
        $lines[] = 'Gerado em: ' . date('d/m/Y H:i:s');
        $lines[] = 'Periodo: ' . $this->periodLabel($filters);
        $lines[] = 'Orgao: ' . $organLabel;
        $lines[] = 'Filtro textual: ' . (string) ($filters['q'] !== '' ? $filters['q'] : 'n/a');
        $lines[] = 'Status: ' . (string) ($filters['status_code'] !== '' ? $filters['status_code'] : 'todos');
        $lines[] = 'Severidade SLA: ' . $this->severityLabel((string) ($filters['severity'] ?? ''));
        $lines[] = '';

        $lines[] = '[RESUMO OPERACIONAL]';
        $lines[] = 'Total monitorado: ' . (string) (int) ($operationalSummary['total'] ?? 0);
        $lines[] = 'No prazo: ' . (string) (int) ($operationalSummary['no_prazo'] ?? 0)
            . ' | Em risco: ' . (string) (int) ($operationalSummary['em_risco'] ?? 0)
            . ' | Vencido: ' . (string) (int) ($operationalSummary['vencido'] ?? 0);
        $lines[] = 'Tempo medio em etapa (dias): ' . number_format((float) ($operationalSummary['avg_days_in_status'] ?? 0), 2, ',', '.');
        $lines[] = '';

        $lines[] = '[GARGALOS]';
        if ($operationalBottlenecks === []) {
            $lines[] = 'Sem gargalos para os filtros selecionados.';
        } else {
            foreach ($operationalBottlenecks as $index => $bottleneck) {
                $lines[] = sprintf(
                    '%d) %s - casos: %d | media dias: %s | risco: %d | vencido: %d',
                    $index + 1,
                    (string) ($bottleneck['status_label'] ?? $bottleneck['status_code'] ?? '-'),
                    (int) ($bottleneck['cases_count'] ?? 0),
                    number_format((float) ($bottleneck['avg_days_in_status'] ?? 0), 2, ',', '.'),
                    (int) ($bottleneck['risco_count'] ?? 0),
                    (int) ($bottleneck['vencido_count'] ?? 0),
                );
            }
        }
        $lines[] = '';

        $lines[] = '[FINANCEIRO]';
        $financialSummary = is_array($financial['summary'] ?? null) ? $financial['summary'] : [];
        $lines[] = 'Previsto: ' . $this->money((float) ($financialSummary['forecast_total'] ?? 0));
        $lines[] = 'Efetivo: ' . $this->money((float) ($financialSummary['effective_total'] ?? 0));
        $lines[] = 'Pago: ' . $this->money((float) ($financialSummary['paid_total'] ?? 0));
        $lines[] = 'A pagar: ' . $this->money((float) ($financialSummary['payable_total'] ?? 0));
        $lines[] = 'Desvio previsto x efetivo: ' . $this->money((float) ($financialSummary['variance_forecast_effective'] ?? 0));
        $lines[] = 'Aderencia: ' . number_format((float) ($financialSummary['adherence_percent'] ?? 0), 2, ',', '.') . '%';
        $lines[] = 'Cobertura de pagamento: ' . number_format((float) ($financialSummary['payment_coverage_percent'] ?? 0), 2, ',', '.') . '%';
        $lines[] = '';

        $lines[] = '[FINANCEIRO MENSAL]';
        $financialMonths = is_array($financial['months'] ?? null) ? $financial['months'] : [];
        foreach ($financialMonths as $month) {
            $lines[] = sprintf(
                '%s | Prev: %s | Efe: %s | Pago: %s | A pagar: %s',
                (string) ($month['month_label'] ?? ''),
                $this->money((float) ($month['forecast_amount'] ?? 0)),
                $this->money((float) ($month['effective_amount'] ?? 0)),
                $this->money((float) ($month['paid_amount'] ?? 0)),
                $this->money((float) ($month['payable_amount'] ?? 0)),
            );
        }
        $lines[] = '';

        $lines[] = '[DETALHE OPERACIONAL - AMOSTRA PARA PDF]';
        if ($operationalRows === []) {
            $lines[] = 'Sem registros operacionais para os filtros selecionados.';
        } else {
            foreach ($operationalRows as $row) {
                $lines[] = sprintf(
                    '%s | %s | %s | %dd | %s',
                    (string) ($row['person_name'] ?? '-'),
                    (string) ($row['organ_name'] ?? '-'),
                    (string) ($row['status_label'] ?? '-'),
                    (int) ($row['days_in_status'] ?? 0),
                    $this->severityLabel((string) ($row['sla_level'] ?? '')),
                );
            }
        }

        $binary = $this->pdfBuilder->build($lines);
        $fileName = sprintf('relatorio-premium-%04d-%02d-%02d-%s.pdf', (int) $filters['year'], (int) $filters['month_from'], (int) $filters['month_to'], date('Ymd_His'));

        return [
            'file_name' => $fileName,
            'binary' => $binary,
            'rows_in_pdf' => count($operationalRows),
        ];
    }

    /** @param array<int, array<string, mixed>> $invoices
     *  @return array<int, array<int, string|int|float>>
     */
    private function buildInvoiceCsvRows(array $invoices): array
    {
        $rows = [[
            'invoice_id',
            'invoice_number',
            'title',
            'organ',
            'reference_month',
            'issue_date',
            'due_date',
            'status',
            'total_amount',
            'paid_amount',
            'pending_amount',
            'has_pdf_attachment',
            'pdf_original_name',
        ]];

        foreach ($invoices as $invoice) {
            $total = (float) ($invoice['total_amount'] ?? 0);
            $paid = (float) ($invoice['paid_amount'] ?? 0);
            $pending = max($total - $paid, 0);

            $rows[] = [
                (int) ($invoice['id'] ?? 0),
                (string) ($invoice['invoice_number'] ?? ''),
                (string) ($invoice['title'] ?? ''),
                (string) ($invoice['organ_name'] ?? ''),
                (string) ($invoice['reference_month'] ?? ''),
                (string) ($invoice['issue_date'] ?? ''),
                (string) ($invoice['due_date'] ?? ''),
                (string) ($invoice['status'] ?? ''),
                number_format($total, 2, '.', ''),
                number_format($paid, 2, '.', ''),
                number_format($pending, 2, '.', ''),
                trim((string) ($invoice['pdf_storage_path'] ?? '')) !== '' ? '1' : '0',
                (string) ($invoice['pdf_original_name'] ?? ''),
            ];
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $payments
     *  @return array<int, array<int, string|int|float>>
     */
    private function buildPaymentCsvRows(array $payments): array
    {
        $rows = [[
            'payment_id',
            'invoice_id',
            'invoice_number',
            'invoice_reference_month',
            'organ',
            'payment_date',
            'amount',
            'process_reference',
            'has_proof_attachment',
            'proof_original_name',
        ]];

        foreach ($payments as $payment) {
            $rows[] = [
                (int) ($payment['id'] ?? 0),
                (int) ($payment['invoice_id'] ?? 0),
                (string) ($payment['invoice_number'] ?? ''),
                (string) ($payment['invoice_reference_month'] ?? ''),
                (string) ($payment['organ_name'] ?? ''),
                (string) ($payment['payment_date'] ?? ''),
                number_format((float) ($payment['amount'] ?? 0), 2, '.', ''),
                (string) ($payment['process_reference'] ?? ''),
                trim((string) ($payment['proof_storage_path'] ?? '')) !== '' ? '1' : '0',
                (string) ($payment['proof_original_name'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<int, string|int|float>> $rows
     */
    private function csvString(array $rows): string
    {
        $handle = fopen('php://temp', 'wb+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    private function createZipTempPath(): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'report_zip_');
        if ($tmp === false || $tmp === '') {
            return null;
        }

        return $tmp;
    }

    private function resolveUploadPath(string $relativePath): ?string
    {
        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        $relative = ltrim($relativePath, '/');
        if ($relative === '') {
            return null;
        }

        $full = $base . '/' . $relative;
        $real = realpath($full);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $prefix)) {
            return null;
        }

        return $real;
    }

    /** @param array<string, bool> $usedPaths */
    private function uniqueZipPath(string $candidate, array &$usedPaths): string
    {
        if (!isset($usedPaths[$candidate])) {
            $usedPaths[$candidate] = true;

            return $candidate;
        }

        $info = pathinfo($candidate);
        $dir = isset($info['dirname']) && $info['dirname'] !== '.' ? (string) $info['dirname'] : '';
        $name = (string) ($info['filename'] ?? 'arquivo');
        $ext = (string) ($info['extension'] ?? '');

        $counter = 2;
        while (true) {
            $next = $name . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
            $path = ($dir !== '' ? $dir . '/' : '') . $next;

            if (!isset($usedPaths[$path])) {
                $usedPaths[$path] = true;

                return $path;
            }

            $counter++;
        }
    }

    private function safeFileName(string $value, string $fallback): string
    {
        $candidate = trim($value);
        if ($candidate === '') {
            $candidate = $fallback;
        }

        $ascii = $this->toAscii($candidate);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii) ?? $ascii;
        $safe = trim($safe, '._-');

        if ($safe === '') {
            $safe = $fallback;
        }

        if (mb_strlen($safe) > 120) {
            $safe = mb_substr($safe, 0, 120);
        }

        return $safe;
    }

    private function toAscii(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && trim($converted) !== '') {
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? $value;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $input): array
    {
        $year = (int) ($input['year'] ?? date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $monthFrom = (int) ($input['month_from'] ?? 1);
        $monthTo = (int) ($input['month_to'] ?? 12);

        $monthFrom = max(1, min(12, $monthFrom));
        $monthTo = max(1, min(12, $monthTo));

        if ($monthFrom > $monthTo) {
            [$monthFrom, $monthTo] = [$monthTo, $monthFrom];
        }

        $periodStart = sprintf('%04d-%02d-01', $year, $monthFrom);
        $periodEnd = (string) date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $monthTo)));

        $severity = trim((string) ($input['severity'] ?? ''));
        if (!in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            $severity = '';
        }

        $sort = trim((string) ($input['sort'] ?? 'days_in_status'));
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'days_in_status';
        }

        $dir = mb_strtolower(trim((string) ($input['dir'] ?? 'desc')));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $organId = (int) ($input['organ_id'] ?? 0);
        if ($organId < 0) {
            $organId = 0;
        }

        return [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 160),
            'organ_id' => $organId,
            'status_code' => mb_substr(trim((string) ($input['status_code'] ?? '')), 0, 60),
            'severity' => $severity,
            'year' => $year,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /** @param array<string, mixed> $filters */
    private function periodLabel(array $filters): string
    {
        return sprintf('%02d/%04d ate %02d/%04d', (int) ($filters['month_from'] ?? 1), (int) ($filters['year'] ?? date('Y')), (int) ($filters['month_to'] ?? 12), (int) ($filters['year'] ?? date('Y')));
    }

    private function organLabel(int $organId): string
    {
        if ($organId <= 0) {
            return 'Todos os orgaos';
        }

        foreach ($this->reports->activeOrgans() as $organ) {
            if ((int) ($organ['id'] ?? 0) === $organId) {
                return (string) ($organ['name'] ?? 'Orgao #' . $organId);
            }
        }

        return 'Orgao #' . $organId;
    }
}
