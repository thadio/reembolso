<?php

declare(strict_types=1);

namespace App\Services;

final class ReportPdfBuilder
{
    /** @param array<int, string> $lines */
    public function build(array $lines): string
    {
        $preparedLines = $this->prepareLines($lines);
        if ($preparedLines === []) {
            $preparedLines = ['Relatorio sem conteudo.'];
        }

        $linesPerPage = 52;
        $pageChunks = array_chunk($preparedLines, $linesPerPage);

        $pageStreams = [];
        foreach ($pageChunks as $chunk) {
            $pageStreams[] = $this->buildPageStream($chunk);
        }

        $pageCount = count($pageStreams);
        $fontObjectId = 3 + ($pageCount * 2);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        foreach ($pageStreams as $index => $stream) {
            $pageObjectId = 3 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = $pageObjectId . ' 0 R';

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                . '/Resources << /Font << /F1 ' . $fontObjectId . ' 0 R >> >> '
                . '/Contents ' . $contentObjectId . ' 0 R >>';

            $objects[$contentObjectId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $maxObjectId = max(array_keys($objects));

        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxObjectId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefStart . "\n%%EOF";

        return $pdf;
    }

    /** @param array<int, string> $lines
     *  @return array<int, string>
     */
    private function prepareLines(array $lines): array
    {
        $prepared = [];

        foreach ($lines as $line) {
            $asciiLine = $this->toAscii((string) $line);
            $normalized = trim(preg_replace('/\s+/', ' ', $asciiLine) ?? '');

            if ($normalized === '') {
                $prepared[] = '';
                continue;
            }

            $wrapped = wordwrap($normalized, 102, "\n", true);
            foreach (explode("\n", $wrapped) as $segment) {
                $prepared[] = $segment;
            }
        }

        return $prepared;
    }

    /** @param array<int, string> $lines */
    private function buildPageStream(array $lines): string
    {
        $stream = "BT\n/F1 10 Tf\n14 TL\n40 800 Td\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $stream .= "T*\n";
            }

            $text = $line === '' ? ' ' : $line;
            $stream .= '(' . $this->escapePdfText($text) . ") Tj\n";
        }

        $stream .= 'ET';

        return $stream;
    }

    private function toAscii(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted)) {
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? $value;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text
        );
    }
}
