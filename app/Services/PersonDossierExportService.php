<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Throwable;
use ZipArchive;

final class PersonDossierExportService
{
    public function __construct(
        private PeopleService $people,
        private PipelineService $pipeline,
        private DocumentService $documents,
        private ProcessCommentService $processComments,
        private ProcessAdminTimelineService $adminTimeline,
        private ReimbursementService $reimbursements,
        private PersonAuditService $personAudit,
        private AuditService $audit,
        private EventService $events,
        private ReportPdfBuilder $pdfBuilder,
        private Config $config,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   errors: array<int, string>,
     *   file_name: string,
     *   path: string,
     *   stats: array<string, int>
     * }
     */
    public function exportZip(
        int $personId,
        int $userId,
        string $ip,
        string $userAgent,
        bool $canViewSensitiveDocuments,
        bool $canViewAuditTrail,
        bool $canViewCpfFull
    ): array {
        if (!class_exists(ZipArchive::class)) {
            return [
                'ok' => false,
                'errors' => ['Extensao ZipArchive nao disponivel no PHP do servidor.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $person = $this->people->find($personId);
        if ($person === null) {
            return [
                'ok' => false,
                'errors' => ['Pessoa nao encontrada para exportacao do dossie.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $timeline = $this->pipeline->fullTimeline($personId, 2000);
        $documentsData = $this->documents->profileData($personId, 1, 2500, $canViewSensitiveDocuments);
        $documentItems = is_array($documentsData['items'] ?? null) ? $documentsData['items'] : [];

        $processCommentsData = $this->processComments->profileData($personId, 200);
        $commentItems = is_array($processCommentsData['items'] ?? null) ? $processCommentsData['items'] : [];

        $adminTimelineData = $this->collectAdminTimelineEntries($personId);
        $adminTimelineItems = $adminTimelineData['items'];
        $adminTimelineSummary = $adminTimelineData['summary'];

        $reimbursementsData = $this->reimbursements->profileData($personId, 200);
        $reimbursementItems = is_array($reimbursementsData['items'] ?? null) ? $reimbursementsData['items'] : [];
        $reimbursementSummary = is_array($reimbursementsData['summary'] ?? null) ? $reimbursementsData['summary'] : [];

        $auditRows = [];
        if ($canViewAuditTrail) {
            $auditExport = $this->personAudit->exportRows($personId, [
                'entity' => '',
                'action' => '',
                'q' => '',
                'from_date' => '',
                'to_date' => '',
            ], 5000);
            $auditRows = is_array($auditExport['rows'] ?? null) ? $auditExport['rows'] : [];
        }

        $zipPath = $this->createZipTempPath();
        if ($zipPath === null) {
            return [
                'ok' => false,
                'errors' => ['Nao foi possivel criar arquivo temporario para o dossie ZIP.'],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $fileName = sprintf('dossie-pessoa-%d-%s.zip', $personId, date('Ymd_His'));

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

        $attachedDocumentFiles = 0;
        $attachedTimelineFiles = 0;
        $missingFiles = 0;
        $usedZipPaths = [];

        try {
            $zip->addFromString('dossie/pessoa.csv', $this->csvString($this->buildPersonCsvRows($person, $canViewCpfFull)));
            $zip->addFromString('dossie/timeline_operacional.csv', $this->csvString($this->buildOperationalTimelineCsvRows($timeline)));
            $zip->addFromString('dossie/documentos.csv', $this->csvString($this->buildDocumentsCsvRows($documentItems)));
            $zip->addFromString('dossie/comentarios_internos.csv', $this->csvString($this->buildProcessCommentsCsvRows($commentItems)));
            $zip->addFromString('dossie/timeline_administrativa.csv', $this->csvString($this->buildAdminTimelineCsvRows($adminTimelineItems)));
            $zip->addFromString('dossie/financeiro_reembolso.csv', $this->csvString($this->buildReimbursementsCsvRows($reimbursementItems)));
            $zip->addFromString('dossie/resumo_dossie.pdf', $this->buildSummaryPdf(
                person: $person,
                timeline: $timeline,
                documents: $documentItems,
                processComments: $commentItems,
                adminTimelineSummary: $adminTimelineSummary,
                reimbursementsSummary: $reimbursementSummary,
                auditRowsCount: count($auditRows),
                canViewAuditTrail: $canViewAuditTrail,
                canViewSensitiveDocuments: $canViewSensitiveDocuments,
            ));

            if ($canViewAuditTrail) {
                $zip->addFromString('trilha/auditoria.csv', $this->csvString($this->buildAuditCsvRows($auditRows)));
            } else {
                $zip->addFromString(
                    'trilha/auditoria.txt',
                    "Trilha de auditoria nao incluida para este usuario (permissao audit.view ausente).\n"
                );
            }

            foreach ($documentItems as $document) {
                $relativePath = trim((string) ($document['storage_path'] ?? ''));
                if ($relativePath === '') {
                    continue;
                }

                $absolutePath = $this->resolveUploadPath($relativePath);
                if ($absolutePath === null) {
                    $missingFiles++;
                    continue;
                }

                $docId = (int) ($document['id'] ?? 0);
                $originalName = $this->safeFileName((string) ($document['original_name'] ?? ''), 'documento_' . $docId);
                $zipName = 'anexos/documentos/' . $docId . '_' . $originalName;
                $zipName = $this->uniqueZipPath($zipName, $usedZipPaths);

                if ($zip->addFile($absolutePath, $zipName)) {
                    $attachedDocumentFiles++;
                } else {
                    $missingFiles++;
                }
            }

            foreach ($timeline as $event) {
                $eventId = (int) ($event['id'] ?? 0);
                $attachments = is_array($event['attachments'] ?? null) ? $event['attachments'] : [];

                foreach ($attachments as $attachment) {
                    $relativePath = trim((string) ($attachment['storage_path'] ?? ''));
                    if ($relativePath === '') {
                        continue;
                    }

                    $absolutePath = $this->resolveUploadPath($relativePath);
                    if ($absolutePath === null) {
                        $missingFiles++;
                        continue;
                    }

                    $attachmentId = (int) ($attachment['id'] ?? 0);
                    $originalName = $this->safeFileName(
                        (string) ($attachment['original_name'] ?? ''),
                        'timeline_' . $eventId . '_' . $attachmentId
                    );
                    $zipName = 'anexos/timeline/' . $eventId . '_' . $attachmentId . '_' . $originalName;
                    $zipName = $this->uniqueZipPath($zipName, $usedZipPaths);

                    if ($zip->addFile($absolutePath, $zipName)) {
                        $attachedTimelineFiles++;
                    } else {
                        $missingFiles++;
                    }
                }
            }

            $manifestLines = [
                'Dossie completo de processo/pessoa',
                'Gerado em: ' . date('Y-m-d H:i:s'),
                'Pessoa ID: ' . $personId,
                'Pessoa: ' . (string) ($person['name'] ?? ''),
                'Orgao: ' . (string) ($person['organ_name'] ?? ''),
                'SEI: ' . (string) ($person['sei_process_number'] ?? ''),
                '',
                'Arquivos do dossie:',
                '- dossie/pessoa.csv',
                '- dossie/timeline_operacional.csv',
                '- dossie/documentos.csv',
                '- dossie/comentarios_internos.csv',
                '- dossie/timeline_administrativa.csv',
                '- dossie/financeiro_reembolso.csv',
                '- dossie/resumo_dossie.pdf',
                $canViewAuditTrail ? '- trilha/auditoria.csv' : '- trilha/auditoria.txt',
                '',
                'Contagens:',
                '- eventos timeline operacional: ' . count($timeline),
                '- documentos listados: ' . count($documentItems),
                '- comentarios internos: ' . count($commentItems),
                '- entradas timeline administrativa: ' . count($adminTimelineItems),
                '- lancamentos financeiros: ' . count($reimbursementItems),
                '- trilha de auditoria: ' . count($auditRows),
                '- anexos documentos adicionados: ' . $attachedDocumentFiles,
                '- anexos timeline adicionados: ' . $attachedTimelineFiles,
                '- anexos ausentes/inacessiveis: ' . $missingFiles,
                '',
                'Observacao: exportacao respeita as permissoes do usuario logado.',
            ];

            $zip->addFromString('manifesto_dossie.txt', implode("\n", $manifestLines) . "\n");
            $zip->close();
        } catch (Throwable $throwable) {
            if ($zip instanceof ZipArchive) {
                $zip->close();
            }
            @unlink($zipPath);

            return [
                'ok' => false,
                'errors' => ['Falha ao montar dossie ZIP: ' . $throwable->getMessage()],
                'file_name' => '',
                'path' => '',
                'stats' => [],
            ];
        }

        $stats = [
            'timeline_events' => count($timeline),
            'documents' => count($documentItems),
            'process_comments' => count($commentItems),
            'admin_timeline_entries' => count($adminTimelineItems),
            'reimbursements' => count($reimbursementItems),
            'audit_rows' => count($auditRows),
            'document_files_attached' => $attachedDocumentFiles,
            'timeline_files_attached' => $attachedTimelineFiles,
            'missing_files' => $missingFiles,
        ];

        $this->audit->log(
            entity: 'person',
            entityId: $personId,
            action: 'export_dossier_zip',
            beforeData: null,
            afterData: [
                'file_name' => $fileName,
                'stats' => $stats,
                'can_view_sensitive_documents' => $canViewSensitiveDocuments ? 1 : 0,
                'can_view_audit_trail' => $canViewAuditTrail ? 1 : 0,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'person.dossier_exported',
            payload: [
                'person_id' => $personId,
                ...$stats,
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'file_name' => $fileName,
            'path' => $zipPath,
            'stats' => $stats,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    private function collectAdminTimelineEntries(int $personId): array
    {
        $filters = [
            'q' => '',
            'source' => '',
            'status_group' => '',
        ];

        $firstPage = $this->adminTimeline->profileData($personId, $filters, 1, 80);
        $items = is_array($firstPage['items'] ?? null) ? $firstPage['items'] : [];
        $summary = is_array($firstPage['summary'] ?? null) ? $firstPage['summary'] : [];

        $pagination = is_array($firstPage['pagination'] ?? null) ? $firstPage['pagination'] : [];
        $pages = max(1, (int) ($pagination['pages'] ?? 1));

        for ($page = 2; $page <= $pages; $page++) {
            $batch = $this->adminTimeline->profileData($personId, $filters, $page, 80);
            $batchItems = is_array($batch['items'] ?? null) ? $batch['items'] : [];
            if ($batchItems !== []) {
                $items = [...$items, ...$batchItems];
            }
        }

        return [
            'items' => $items,
            'summary' => [
                'total' => (int) ($summary['total'] ?? count($items)),
                'open_count' => (int) ($summary['open_count'] ?? 0),
                'closed_count' => (int) ($summary['closed_count'] ?? 0),
                'manual_count' => (int) ($summary['manual_count'] ?? 0),
                'automated_count' => (int) ($summary['automated_count'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $person
     * @return array<int, array<int, string|int|float>>
     */
    private function buildPersonCsvRows(array $person, bool $canViewCpfFull): array
    {
        $cpf = (string) ($person['cpf'] ?? '');

        return [[
            'id',
            'nome',
            'cpf',
            'status',
            'orgao',
            'modalidade',
            'sei',
            'lotacao_mte',
            'email',
            'telefone',
            'tags',
            'observacoes',
            'criado_em',
            'atualizado_em',
        ], [
            (int) ($person['id'] ?? 0),
            (string) ($person['name'] ?? ''),
            $canViewCpfFull ? $cpf : (string) \mask_cpf($cpf),
            (string) ($person['status'] ?? ''),
            (string) ($person['organ_name'] ?? ''),
            (string) ($person['modality_name'] ?? ''),
            (string) ($person['sei_process_number'] ?? ''),
            (string) ($person['mte_destination'] ?? ''),
            (string) ($person['email'] ?? ''),
            (string) ($person['phone'] ?? ''),
            (string) ($person['tags'] ?? ''),
            (string) ($person['notes'] ?? ''),
            (string) ($person['created_at'] ?? ''),
            (string) ($person['updated_at'] ?? ''),
        ]];
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @return array<int, array<int, string|int|float>>
     */
    private function buildOperationalTimelineCsvRows(array $timeline): array
    {
        $rows = [[
            'timeline_id',
            'assignment_id',
            'tipo_evento',
            'titulo',
            'descricao',
            'data_evento',
            'ator',
            'criado_em',
            'anexos_total',
        ]];

        foreach ($timeline as $event) {
            $attachments = is_array($event['attachments'] ?? null) ? $event['attachments'] : [];
            $rows[] = [
                (int) ($event['id'] ?? 0),
                isset($event['assignment_id']) ? (int) $event['assignment_id'] : '',
                (string) ($event['event_type'] ?? ''),
                (string) ($event['title'] ?? ''),
                (string) ($event['description'] ?? ''),
                (string) ($event['event_date'] ?? ''),
                (string) ($event['created_by_name'] ?? 'Sistema'),
                (string) ($event['created_at'] ?? ''),
                count($attachments),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, string|int|float>>
     */
    private function buildDocumentsCsvRows(array $items): array
    {
        $rows = [[
            'document_id',
            'tipo_documento',
            'titulo',
            'referencia_sei',
            'data_documento',
            'sensibilidade',
            'tags',
            'observacoes',
            'arquivo_original',
            'mime_type',
            'tamanho_bytes',
            'enviado_por',
            'criado_em',
        ]];

        foreach ($items as $item) {
            $rows[] = [
                (int) ($item['id'] ?? 0),
                (string) ($item['document_type_name'] ?? ''),
                (string) ($item['title'] ?? ''),
                (string) ($item['reference_sei'] ?? ''),
                (string) ($item['document_date'] ?? ''),
                (string) ($item['sensitivity_level'] ?? ''),
                (string) ($item['tags'] ?? ''),
                (string) ($item['notes'] ?? ''),
                (string) ($item['original_name'] ?? ''),
                (string) ($item['mime_type'] ?? ''),
                (int) ($item['file_size'] ?? 0),
                (string) ($item['uploaded_by_name'] ?? ''),
                (string) ($item['created_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, string|int|float>>
     */
    private function buildProcessCommentsCsvRows(array $items): array
    {
        $rows = [[
            'comment_id',
            'assignment_id',
            'status',
            'fixado',
            'comentario',
            'criado_por',
            'atualizado_por',
            'criado_em',
            'atualizado_em',
        ]];

        foreach ($items as $item) {
            $rows[] = [
                (int) ($item['id'] ?? 0),
                isset($item['assignment_id']) ? (int) $item['assignment_id'] : '',
                (string) ($item['status'] ?? ''),
                (int) ($item['is_pinned'] ?? 0) === 1 ? '1' : '0',
                (string) ($item['comment_text'] ?? ''),
                (string) ($item['created_by_name'] ?? 'Sistema'),
                (string) ($item['updated_by_name'] ?? ''),
                (string) ($item['created_at'] ?? ''),
                (string) ($item['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, string|int|float>>
     */
    private function buildAdminTimelineCsvRows(array $items): array
    {
        $rows = [[
            'entry_id',
            'origem',
            'source_id',
            'assignment_id',
            'titulo',
            'descricao',
            'status',
            'status_grupo',
            'severidade',
            'fixado',
            'evento_em',
            'ator',
            'manual',
        ]];

        foreach ($items as $item) {
            $rows[] = [
                (string) ($item['entry_id'] ?? ''),
                (string) ($item['source_label'] ?? ''),
                (int) ($item['source_id'] ?? 0),
                isset($item['assignment_id']) ? (int) $item['assignment_id'] : '',
                (string) ($item['title'] ?? ''),
                (string) ($item['description'] ?? ''),
                (string) ($item['status_label'] ?? ''),
                (string) ($item['status_group'] ?? ''),
                (string) ($item['severity_label'] ?? ''),
                (int) ($item['is_pinned'] ?? 0) === 1 ? '1' : '0',
                (string) ($item['event_at'] ?? ''),
                (string) ($item['actor_name'] ?? 'Sistema'),
                (int) ($item['is_manual'] ?? 0) === 1 ? '1' : '0',
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, string|int|float>>
     */
    private function buildReimbursementsCsvRows(array $items): array
    {
        $rows = [[
            'entry_id',
            'assignment_id',
            'tipo',
            'status',
            'titulo',
            'valor',
            'competencia',
            'vencimento',
            'pago_em',
            'observacoes',
            'criado_por',
            'criado_em',
            'atualizado_em',
        ]];

        foreach ($items as $item) {
            $rows[] = [
                (int) ($item['id'] ?? 0),
                isset($item['assignment_id']) ? (int) $item['assignment_id'] : '',
                (string) ($item['entry_type'] ?? ''),
                (string) ($item['status'] ?? ''),
                (string) ($item['title'] ?? ''),
                number_format((float) ($item['amount'] ?? 0), 2, '.', ''),
                (string) ($item['reference_month'] ?? ''),
                (string) ($item['due_date'] ?? ''),
                (string) ($item['paid_at'] ?? ''),
                (string) ($item['notes'] ?? ''),
                (string) ($item['created_by_name'] ?? 'Sistema'),
                (string) ($item['created_at'] ?? ''),
                (string) ($item['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<int, string|int|float>>
     */
    private function buildAuditCsvRows(array $rows): array
    {
        $payload = [[
            'audit_id',
            'data_hora',
            'entidade',
            'entidade_id',
            'acao',
            'usuario',
            'ip',
            'user_agent',
            'before_data',
            'after_data',
            'metadata',
        ]];

        foreach ($rows as $row) {
            $payload[] = [
                (int) ($row['id'] ?? 0),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['entity'] ?? ''),
                isset($row['entity_id']) ? (string) $row['entity_id'] : '',
                (string) ($row['action'] ?? ''),
                (string) ($row['user_name'] ?? 'Sistema'),
                (string) ($row['ip'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $this->normalizeAuditPayload($row['before_data'] ?? null),
                $this->normalizeAuditPayload($row['after_data'] ?? null),
                $this->normalizeAuditPayload($row['metadata'] ?? null),
            ];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $person
     * @param array<int, array<string, mixed>> $timeline
     * @param array<int, array<string, mixed>> $documents
     * @param array<int, array<string, mixed>> $processComments
     * @param array<string, int> $adminTimelineSummary
     * @param array<string, int|float> $reimbursementsSummary
     */
    private function buildSummaryPdf(
        array $person,
        array $timeline,
        array $documents,
        array $processComments,
        array $adminTimelineSummary,
        array $reimbursementsSummary,
        int $auditRowsCount,
        bool $canViewAuditTrail,
        bool $canViewSensitiveDocuments
    ): string {
        $lines = [];
        $lines[] = 'DOSSIE COMPLETO - PROCESSO/PESSOA';
        $lines[] = 'Gerado em: ' . date('d/m/Y H:i:s');
        $lines[] = 'Pessoa: ' . (string) ($person['name'] ?? '');
        $lines[] = 'Pessoa ID: ' . (string) ((int) ($person['id'] ?? 0));
        $lines[] = 'Orgao: ' . (string) ($person['organ_name'] ?? '-');
        $lines[] = 'Status atual: ' . (string) ($person['status'] ?? '-');
        $lines[] = 'Processo SEI: ' . (string) ($person['sei_process_number'] ?? '-');
        $lines[] = '';

        $lines[] = '[RESUMO]';
        $lines[] = 'Timeline operacional: ' . count($timeline) . ' evento(s)';
        $lines[] = 'Documentos do dossie: ' . count($documents) . ' documento(s)';
        $lines[] = 'Comentarios internos: ' . count($processComments) . ' comentario(s)';
        $lines[] = 'Timeline administrativa: ' . (string) ((int) ($adminTimelineSummary['total'] ?? 0)) . ' entrada(s)';
        $lines[] = 'Reembolsos (lancamentos): ' . (string) ((int) ($reimbursementsSummary['total_entries'] ?? 0));
        $lines[] = 'Reembolsos pendentes: ' . (string) ((int) ($reimbursementsSummary['pending_count'] ?? 0));
        $lines[] = 'Reembolsos pagos: ' . (string) ((int) ($reimbursementsSummary['paid_count'] ?? 0));
        $lines[] = 'Trilha de auditoria: ' . $auditRowsCount . ' registro(s)';
        $lines[] = '';

        $lines[] = '[PERMISSOES APLICADAS]';
        $lines[] = 'Documentos sensiveis incluidos: ' . ($canViewSensitiveDocuments ? 'sim' : 'nao');
        $lines[] = 'Trilha de auditoria incluida: ' . ($canViewAuditTrail ? 'sim' : 'nao');
        $lines[] = '';

        $lines[] = '[FINANCEIRO]';
        $lines[] = 'Total pendente: ' . $this->money((float) ($reimbursementsSummary['pending_total'] ?? 0));
        $lines[] = 'Total pago: ' . $this->money((float) ($reimbursementsSummary['paid_total'] ?? 0));
        $lines[] = 'Total vencido: ' . $this->money((float) ($reimbursementsSummary['overdue_total'] ?? 0));
        $lines[] = '';

        $lines[] = 'Este PDF sintetiza o pacote ZIP de dossie. Os detalhes completos estao nos CSVs e anexos.';

        return $this->pdfBuilder->build($lines);
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
        $tmp = tempnam(sys_get_temp_dir(), 'dossie_zip_');
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

    private function safeFileName(string $input, string $fallback): string
    {
        $value = trim($input);
        if ($value === '') {
            $value = $fallback;
        }

        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
        $value = trim((string) $value, '._-');
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, 160);
    }

    private function normalizeAuditPayload(mixed $payload): string
    {
        if (!is_string($payload) || trim($payload) === '') {
            return '';
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return $payload;
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : $payload;
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
