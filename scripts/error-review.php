#!/usr/bin/env php
<?php

declare(strict_types=1);

const DEFAULT_WINDOW_HOURS = 24;
const DEFAULT_TOP = 10;

main($argv);

/**
 * @param array<int, string> $argv
 */
function main(array $argv): void
{
    if (PHP_SAPI !== 'cli') {
        fail('script disponivel apenas em CLI.');
    }

    $basePath = dirname(__DIR__);
    $options = parseOptions($argv);

    if ($options['help'] === true) {
        printUsage();
        exit(0);
    }

    $defaultLogFile = $basePath . '/storage/logs/app.log';
    $logFile = resolvePath((string) $options['log_file'], $defaultLogFile, $basePath);
    if (!is_file($logFile)) {
        fail(sprintf('arquivo de log nao encontrado: %s', $logFile));
    }

    if (!is_readable($logFile)) {
        fail(sprintf('arquivo de log sem permissao de leitura: %s', $logFile));
    }

    $windowHours = (int) $options['window_hours'];
    $top = (int) $options['top'];
    $levels = normalizeLevels($options['levels']);
    $cutoff = time() - ($windowHours * 3600);

    $analysis = analyzeLogFile($logFile, $cutoff, $levels);
    $report = buildReport($analysis, $logFile, $windowHours, $top, $levels, $cutoff);

    $output = (string) $options['output'];
    if ($output === 'json') {
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            fail('falha ao serializar resultado json.');
        }

        fwrite(STDOUT, $json . PHP_EOL);
    } else {
        printTableReport($report);
    }

    $reportFile = trim((string) $options['report_file']);
    if ($reportFile !== '') {
        $absoluteReportFile = resolvePath($reportFile, $basePath . '/storage/ops/error-review.md', $basePath);
        writeReportFile($absoluteReportFile, $report);
    }

    $failThreshold = $options['fail_threshold'];
    if (is_int($failThreshold) && (int) ($report['totals']['recurring_error_entries'] ?? 0) >= $failThreshold) {
        exit(2);
    }
}

/**
 * @param array<int, string> $argv
 * @return array{
 *   log_file: string,
 *   window_hours: int,
 *   top: int,
 *   output: string,
 *   levels: array<int, string>,
 *   report_file: string,
 *   fail_threshold: int|null,
 *   help: bool
 * }
 */
function parseOptions(array $argv): array
{
    $options = [
        'log_file' => '',
        'window_hours' => DEFAULT_WINDOW_HOURS,
        'top' => DEFAULT_TOP,
        'output' => 'table',
        'levels' => [],
        'report_file' => '',
        'fail_threshold' => null,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--log-file':
                $options['log_file'] = readOptionValue($argv, $i, '--log-file');
                break;
            case '--window-hours':
                $options['window_hours'] = parseIntOption(
                    readOptionValue($argv, $i, '--window-hours'),
                    '--window-hours',
                    1,
                    24 * 365
                );
                break;
            case '--top':
                $options['top'] = parseIntOption(
                    readOptionValue($argv, $i, '--top'),
                    '--top',
                    1,
                    200
                );
                break;
            case '--output':
                $output = strtolower(readOptionValue($argv, $i, '--output'));
                if (!in_array($output, ['table', 'json'], true)) {
                    fail('--output deve ser table ou json.');
                }

                $options['output'] = $output;
                break;
            case '--levels':
                $options['levels'] = parseLevelsOption(readOptionValue($argv, $i, '--levels'));
                break;
            case '--report-file':
                $options['report_file'] = readOptionValue($argv, $i, '--report-file');
                break;
            case '--fail-threshold':
                $options['fail_threshold'] = parseIntOption(
                    readOptionValue($argv, $i, '--fail-threshold'),
                    '--fail-threshold',
                    1,
                    1000000
                );
                break;
            case '--help':
            case '-h':
                $options['help'] = true;
                break;
            default:
                fail(sprintf('opcao desconhecida: %s', $arg));
        }
    }

    return $options;
}

/**
 * @param array<int, string> $argv
 */
function readOptionValue(array $argv, int &$index, string $option): string
{
    $valueIndex = $index + 1;
    if (!isset($argv[$valueIndex])) {
        fail(sprintf('valor ausente para %s', $option));
    }

    $index = $valueIndex;
    $value = trim($argv[$valueIndex]);
    if ($value === '') {
        fail(sprintf('valor invalido para %s', $option));
    }

    return $value;
}

function parseIntOption(string $value, string $option, int $min, int $max): int
{
    if (!preg_match('/^\d+$/', $value)) {
        fail(sprintf('%s deve ser inteiro.', $option));
    }

    $intValue = (int) $value;
    if ($intValue < $min || $intValue > $max) {
        fail(sprintf('%s deve estar entre %d e %d.', $option, $min, $max));
    }

    return $intValue;
}

/**
 * @return array<int, string>
 */
function parseLevelsOption(string $value): array
{
    $parts = array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== '');
    if ($parts === []) {
        return [];
    }

    $levels = [];
    foreach ($parts as $part) {
        $upper = strtoupper($part);
        if ($upper === 'WARN') {
            $upper = 'WARNING';
        }

        if (!in_array($upper, ['INFO', 'WARNING', 'ERROR'], true)) {
            fail(sprintf('nivel invalido em --levels: %s', $part));
        }

        $levels[] = $upper;
    }

    return array_values(array_unique($levels));
}

/**
 * @param array<int, string> $levels
 * @return array<int, string>
 */
function normalizeLevels(array $levels): array
{
    if ($levels === []) {
        return [];
    }

    $clean = [];
    foreach ($levels as $level) {
        $upper = strtoupper(trim($level));
        if ($upper === '') {
            continue;
        }

        if ($upper === 'WARN') {
            $upper = 'WARNING';
        }

        if (in_array($upper, ['INFO', 'WARNING', 'ERROR'], true)) {
            $clean[] = $upper;
        }
    }

    return array_values(array_unique($clean));
}

function resolvePath(string $path, string $defaultPath, string $basePath): string
{
    $value = trim($path);
    if ($value === '') {
        return $defaultPath;
    }

    if (str_starts_with($value, '/')) {
        return $value;
    }

    return $basePath . '/' . ltrim($value, '/');
}

/**
 * @param array<int, string> $levelFilter
 * @return array{
 *   totals: array<string, int>,
 *   groups: array<int, array<string, mixed>>
 * }
 */
function analyzeLogFile(string $logFile, int $cutoff, array $levelFilter): array
{
    $handle = fopen($logFile, 'rb');
    if ($handle === false) {
        fail(sprintf('falha ao abrir log: %s', $logFile));
    }

    $totals = [
        'entries_total' => 0,
        'entries_in_window' => 0,
        'info' => 0,
        'warning' => 0,
        'error' => 0,
        'other' => 0,
    ];
    $groups = [];

    try {
        while (($line = fgets($handle)) !== false) {
            $parsed = parseLogLine($line);
            if ($parsed === null) {
                continue;
            }

            $totals['entries_total']++;
            if ($parsed['timestamp_unix'] < $cutoff) {
                continue;
            }

            $level = (string) $parsed['level'];
            if ($levelFilter !== [] && !in_array($level, $levelFilter, true)) {
                continue;
            }

            $totals['entries_in_window']++;
            if ($level === 'INFO') {
                $totals['info']++;
            } elseif ($level === 'WARNING') {
                $totals['warning']++;
            } elseif ($level === 'ERROR') {
                $totals['error']++;
            } else {
                $totals['other']++;
            }

            $signature = buildSignature($parsed);
            if (!isset($groups[$signature])) {
                $groups[$signature] = [
                    'signature' => $signature,
                    'level' => $level,
                    'message' => (string) $parsed['message'],
                    'file' => (string) ($parsed['context']['file'] ?? ''),
                    'line' => (string) ($parsed['context']['line'] ?? ''),
                    'count' => 0,
                    'first_seen' => (string) $parsed['timestamp'],
                    'last_seen' => (string) $parsed['timestamp'],
                    'sample_context' => $parsed['context'],
                ];
            }

            $groups[$signature]['count']++;

            if (strtotime((string) $parsed['timestamp']) < strtotime((string) $groups[$signature]['first_seen'])) {
                $groups[$signature]['first_seen'] = (string) $parsed['timestamp'];
            }

            if (strtotime((string) $parsed['timestamp']) > strtotime((string) $groups[$signature]['last_seen'])) {
                $groups[$signature]['last_seen'] = (string) $parsed['timestamp'];
                $groups[$signature]['sample_context'] = $parsed['context'];
            }
        }
    } finally {
        fclose($handle);
    }

    usort($groups, static function (array $a, array $b): int {
        $countDiff = (int) $b['count'] <=> (int) $a['count'];
        if ($countDiff !== 0) {
            return $countDiff;
        }

        return strcmp((string) $b['last_seen'], (string) $a['last_seen']);
    });

    return [
        'totals' => $totals,
        'groups' => $groups,
    ];
}

/**
 * @return array{timestamp: string, timestamp_unix: int, level: string, message: string, context: array<string, mixed>}|null
 */
function parseLogLine(string $line): ?array
{
    $trimmed = trim($line);
    if ($trimmed === '') {
        return null;
    }

    if (!preg_match('/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<level>[A-Z]+):\s*(?<body>.*)$/', $trimmed, $matches)) {
        return null;
    }

    $timestamp = (string) $matches['timestamp'];
    $timestampUnix = strtotime($timestamp);
    if ($timestampUnix === false) {
        return null;
    }

    $level = strtoupper((string) $matches['level']);
    $body = trim((string) $matches['body']);

    $message = $body;
    $context = [];
    $candidatePos = strrpos($body, ' {');
    if ($candidatePos !== false) {
        $contextCandidate = trim(substr($body, $candidatePos + 1));
        $decoded = json_decode($contextCandidate, true);
        if (is_array($decoded)) {
            $message = trim(substr($body, 0, $candidatePos));
            $context = $decoded;
        }
    }

    if ($message === '') {
        $message = '(sem mensagem)';
    }

    return [
        'timestamp' => $timestamp,
        'timestamp_unix' => $timestampUnix,
        'level' => $level,
        'message' => $message,
        'context' => is_array($context) ? $context : [],
    ];
}

/**
 * @param array{level: string, message: string, context: array<string, mixed>} $entry
 */
function buildSignature(array $entry): string
{
    $parts = [
        strtoupper((string) ($entry['level'] ?? 'OTHER')),
        trim((string) ($entry['message'] ?? '')),
    ];

    $context = $entry['context'] ?? [];
    if (isset($context['file']) && is_string($context['file']) && trim($context['file']) !== '') {
        $parts[] = trim($context['file']);
    }

    if (isset($context['line']) && is_scalar($context['line']) && trim((string) $context['line']) !== '') {
        $parts[] = 'line=' . trim((string) $context['line']);
    }

    return implode(' | ', $parts);
}

/**
 * @param array{totals: array<string, int>, groups: array<int, array<string, mixed>>} $analysis
 * @param array<int, string> $levels
 * @return array<string, mixed>
 */
function buildReport(array $analysis, string $logFile, int $windowHours, int $top, array $levels, int $cutoff): array
{
    $groups = $analysis['groups'];

    $recurringGroups = array_values(array_filter($groups, static fn (array $group): bool => (int) ($group['count'] ?? 0) >= 2));
    $recurringErrorGroups = array_values(array_filter(
        $recurringGroups,
        static fn (array $group): bool => strtoupper((string) ($group['level'] ?? '')) === 'ERROR'
    ));

    $recurringErrorEntries = 0;
    foreach ($recurringErrorGroups as $group) {
        $recurringErrorEntries += (int) ($group['count'] ?? 0);
    }

    $topRecurring = array_slice($recurringGroups, 0, max(1, $top));

    return [
        'generated_at' => date(DATE_ATOM),
        'log_file' => $logFile,
        'window_hours' => $windowHours,
        'window_from' => date(DATE_ATOM, $cutoff),
        'window_to' => date(DATE_ATOM),
        'filter_levels' => $levels,
        'totals' => [
            'entries_total' => (int) ($analysis['totals']['entries_total'] ?? 0),
            'entries_in_window' => (int) ($analysis['totals']['entries_in_window'] ?? 0),
            'info' => (int) ($analysis['totals']['info'] ?? 0),
            'warning' => (int) ($analysis['totals']['warning'] ?? 0),
            'error' => (int) ($analysis['totals']['error'] ?? 0),
            'other' => (int) ($analysis['totals']['other'] ?? 0),
            'distinct_groups' => count($groups),
            'recurring_groups' => count($recurringGroups),
            'recurring_error_groups' => count($recurringErrorGroups),
            'recurring_error_entries' => $recurringErrorEntries,
        ],
        'top_recurring' => $topRecurring,
    ];
}

/**
 * @param array<string, mixed> $report
 */
function printTableReport(array $report): void
{
    $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
    $topRecurring = is_array($report['top_recurring'] ?? null) ? $report['top_recurring'] : [];

    fwrite(STDOUT, '[error-review] generated_at: ' . (string) ($report['generated_at'] ?? '') . PHP_EOL);
    fwrite(STDOUT, '[error-review] log_file: ' . (string) ($report['log_file'] ?? '') . PHP_EOL);
    fwrite(
        STDOUT,
        sprintf(
            "[error-review] window: %sh (%s -> %s)\n",
            (string) ($report['window_hours'] ?? ''),
            (string) ($report['window_from'] ?? ''),
            (string) ($report['window_to'] ?? '')
        )
    );

    fwrite(
        STDOUT,
        sprintf(
            "[error-review] totals: in_window=%d error=%d warning=%d info=%d recurring_error_groups=%d recurring_error_entries=%d\n",
            (int) ($totals['entries_in_window'] ?? 0),
            (int) ($totals['error'] ?? 0),
            (int) ($totals['warning'] ?? 0),
            (int) ($totals['info'] ?? 0),
            (int) ($totals['recurring_error_groups'] ?? 0),
            (int) ($totals['recurring_error_entries'] ?? 0)
        )
    );

    if ($topRecurring === []) {
        fwrite(STDOUT, '[error-review] nenhum grupo recorrente encontrado na janela.' . PHP_EOL);
        return;
    }

    fwrite(STDOUT, "[error-review] top recorrencias:\n");
    $position = 1;
    foreach ($topRecurring as $group) {
        fwrite(
            STDOUT,
            sprintf(
                "%02d. [%s] x%d | %s | last=%s\n",
                $position,
                (string) ($group['level'] ?? 'OTHER'),
                (int) ($group['count'] ?? 0),
                (string) ($group['message'] ?? '(sem mensagem)'),
                (string) ($group['last_seen'] ?? '')
            )
        );

        $file = trim((string) ($group['file'] ?? ''));
        if ($file !== '') {
            fwrite(
                STDOUT,
                sprintf(
                    "    file=%s%s\n",
                    $file,
                    trim((string) ($group['line'] ?? '')) !== '' ? ':' . trim((string) ($group['line'] ?? '')) : ''
                )
            );
        }

        $position++;
    }
}

/**
 * @param array<string, mixed> $report
 */
function writeReportFile(string $path, array $report): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail(sprintf('nao foi possivel criar diretorio de relatorio: %s', $dir));
    }

    $content = toMarkdownReport($report);
    $bytes = file_put_contents($path, $content, LOCK_EX);
    if ($bytes === false) {
        fail(sprintf('falha ao gravar relatorio: %s', $path));
    }
}

/**
 * @param array<string, mixed> $report
 */
function toMarkdownReport(array $report): string
{
    $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
    $topRecurring = is_array($report['top_recurring'] ?? null) ? $report['top_recurring'] : [];

    $lines = [];
    $lines[] = '# Revisao de erros recorrentes';
    $lines[] = '';
    $lines[] = '- Gerado em: ' . (string) ($report['generated_at'] ?? '');
    $lines[] = '- Log: `' . (string) ($report['log_file'] ?? '') . '`';
    $lines[] = '- Janela: ' . (string) ($report['window_hours'] ?? 0) . 'h (' . (string) ($report['window_from'] ?? '') . ' -> ' . (string) ($report['window_to'] ?? '') . ')';
    $lines[] = '';
    $lines[] = '## Totais';
    $lines[] = '';
    $lines[] = '- Entradas na janela: ' . (string) (int) ($totals['entries_in_window'] ?? 0);
    $lines[] = '- ERROR: ' . (string) (int) ($totals['error'] ?? 0);
    $lines[] = '- WARNING: ' . (string) (int) ($totals['warning'] ?? 0);
    $lines[] = '- INFO: ' . (string) (int) ($totals['info'] ?? 0);
    $lines[] = '- Grupos recorrentes: ' . (string) (int) ($totals['recurring_groups'] ?? 0);
    $lines[] = '- Grupos recorrentes de erro: ' . (string) (int) ($totals['recurring_error_groups'] ?? 0);
    $lines[] = '- Entradas recorrentes de erro: ' . (string) (int) ($totals['recurring_error_entries'] ?? 0);
    $lines[] = '';
    $lines[] = '## Top recorrencias';
    $lines[] = '';

    if ($topRecurring === []) {
        $lines[] = '- Nenhuma recorrencia encontrada.';
        $lines[] = '';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    $position = 1;
    foreach ($topRecurring as $group) {
        $lines[] = $position . '. [' . (string) ($group['level'] ?? 'OTHER') . '] x' . (string) (int) ($group['count'] ?? 0) . ' - ' . (string) ($group['message'] ?? '(sem mensagem)');
        $lines[] = '   - first_seen: ' . (string) ($group['first_seen'] ?? '');
        $lines[] = '   - last_seen: ' . (string) ($group['last_seen'] ?? '');

        $file = trim((string) ($group['file'] ?? ''));
        if ($file !== '') {
            $lines[] = '   - file: `' . $file . '`' . (trim((string) ($group['line'] ?? '')) !== '' ? ':' . trim((string) ($group['line'] ?? '')) : '');
        }

        $context = $group['sample_context'] ?? [];
        if (is_array($context) && $context !== []) {
            $encodedContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encodedContext) && $encodedContext !== '') {
                $lines[] = '   - context: `' . $encodedContext . '`';
            }
        }

        $position++;
    }

    $lines[] = '';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/error-review.php [options]

Options:
  --log-file <path>        Arquivo de log (default: storage/logs/app.log)
  --window-hours <n>       Janela de analise em horas (default: 24)
  --top <n>                Quantidade maxima de recorrencias no resultado (default: 10)
  --levels <csv>           Filtro de niveis (INFO,WARNING,ERROR). Ex.: ERROR,WARNING
  --output <table|json>    Formato de saida no stdout (default: table)
  --report-file <path>     Salva relatorio markdown no caminho informado
  --fail-threshold <n>     Sai com codigo 2 se recurring_error_entries >= n
  --help                   Mostra esta ajuda
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[error-review][error] ' . $message . PHP_EOL);
    exit(1);
}
