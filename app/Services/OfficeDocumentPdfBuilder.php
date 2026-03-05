<?php

declare(strict_types=1);

namespace App\Services;

final class OfficeDocumentPdfBuilder
{
    private ReportPdfBuilder $pdfBuilder;

    public function __construct(?ReportPdfBuilder $pdfBuilder = null)
    {
        $this->pdfBuilder = $pdfBuilder ?? new ReportPdfBuilder();
    }

    /** @param array<string, mixed> $document */
    public function build(array $document): string
    {
        return $this->pdfBuilder->build($this->linesFromDocument($document));
    }

    /**
     * @param array<string, mixed> $document
     * @return array<int, string>
     */
    private function linesFromDocument(array $document): array
    {
        $documentId = (int) ($document['id'] ?? 0);
        $versionNumber = (int) ($document['version_number'] ?? 0);
        $personId = (int) ($document['person_id'] ?? 0);

        $lines = [
            'OFICIO GERADO - FASE 4.1',
            'Documento: #' . $documentId,
            'Template: ' . (string) ($document['template_name'] ?? '-')
                . ' (' . (string) ($document['template_key'] ?? '-') . ')',
            'Versao: V' . $versionNumber,
            'Pessoa: ' . (string) ($document['person_name'] ?? '-') . ' (ID ' . $personId . ')',
            'Orgao: ' . (string) ($document['organ_name'] ?? '-'),
            'Gerado em: ' . $this->dateTimeLabel((string) ($document['created_at'] ?? '')),
            'Assunto: ' . (string) ($document['rendered_subject'] ?? '-'),
            '',
            'CONTEUDO',
            '',
        ];

        $content = $this->htmlToPlainText((string) ($document['rendered_html'] ?? ''));
        if ($content === '') {
            $lines[] = 'Sem conteudo renderizado.';

            return $lines;
        }

        foreach (explode("\n", $content) as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    private function htmlToPlainText(string $html): string
    {
        $normalized = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $normalized = preg_replace('/<\s*\/\s*(p|div|li|h1|h2|h3|h4|h5|h6)\s*>/i', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*li\b[^>]*>/i', '- ', $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*p\b[^>]*>/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*div\b[^>]*>/i', '', $normalized) ?? $normalized;

        $text = strip_tags($normalized);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function dateTimeLabel(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value !== '' ? $value : '-';
        }

        return date('d/m/Y H:i:s', $timestamp);
    }
}
