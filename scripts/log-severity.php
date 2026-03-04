#!/usr/bin/env php
<?php

declare(strict_types=1);

const DEFAULT_WINDOW_HOURS = 24;
const DEFAULT_TOP_MESSAGES = 8;
const DEFAULT_RETENTION_DAYS = 30;

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
    $bucket = (string) $options['bucket'];
    $topMessages = (int) $options['top_messages'];
    $cutoff = time() - ($windowHours * 3600);

    $analysis = analyzeLogFile($logFile, $cutoff, $bucket, $topMessages);
    $report = [
        'generated_at' => date(DATE_ATOM),
        'log_file' => $logFile,
        'window_hours' => $windowHours,
        'window_from' => date(DATE_ATOM, $cutoff),
        'window_to' => date(DATE_ATOM),
        'bucket' => $bucket,
        'totals' => $analysis['totals'],
        'by_bucket' => $analysis['by_bucket'],
        'top_messages' => $analysis['top_messages'],
    ];

    $snapshotFile = null;
    if ((bool) $options['write_snapshot'] === true) {
        $snapshotDir = resolvePath((string) $options['snapshot_dir'], $basePath . '/storage/ops/log-severity', $basePath);
        ensureDirectory($snapshotDir);
        $snapshotFile = writeSnapshot($snapshotDir, $report);
        $removed = cleanupOldSnapshots($snapshotDir, (int) $options['retention_days']);
        $report['snapshot'] = [
            'file' => $snapshotFile,
            'removed_files' => $removed,
        ];
    }

    if ((string) $options['output'] === 'json') {
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            fail('falha ao serializar saida json.');
        }

        fwrite(STDOUT, $json . PHP_EOL);
    } else {
        printTableReport($report);
    }

    $failErrorCount = $options['fail_error_count'];
    if (is_int($failErrorCount) && (int) ($analysis['totals']['error'] ?? 0) >= $failErrorCount) {
        exit(2);
    }
}

/**
 * @param array<int, string> $argv
 * @return array{
 *   log_file: string,
 *   window_hours: int,
 *   bucket: string,
 *   top_messages: int,
 *   output: string,
 *   write_snapshot: bool,
 *   snapshot_dir: string,
 *   retention_days: int,
 *   fail_error_count: int|null,
 *   help: bool
 * }
 */
function parseOptions(array $argv): array
{
    $options = [
        'log_file' => '',
        'window_hours' => DEFAULT_WINDOW_HOURS,
        'bucket' => 'hour',
        'top_messages' => DEFAULT_TOP_MESSAGES,
        'output' => 'table',
        'write_snapshot' => false,
        'snapshot_dir' => '',
        'retention_days' => DEFAULT_RETENTION_DAYS,
        'fail_error_count' => null,
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
            case '--bucket':
                $bucket = strtolower(readOptionValue($argv, $i, '--bucket'));
                if (!in_array($bucket, ['hour', 'day'], true)) {
                    fail('--bucket deve ser hour ou day.');
                }
                $options['bucket'] = $bucket;
                break;
            case '--top-messages':
                $options['top_messages'] = parseIntOption(
                    readOptionValue($argv, $i, '--top-messages'),
                    '--top-messages',
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
            case '--write-snapshot':
                $options['write_snapshot'] = true;
                break;
            case '--snapshot-dir':
                $options['snapshot_dir'] = readOptionValue($argv, $i, '--snapshot-dir');
                break;
            case '--retention-days':
                $options['retention_days'] = parseIntOption(
                    readOptionValue($argv, $i, '--retention-days'),
                    '--retention-days',
                    0,
                    3650
                );
                break;
            case '--fail-error-count':
                $options['fail_error_count'] = parseIntOption(
                    readOptionValue($argv, $i, '--fail-error-count'),
                    '--fail-error-count',
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
 * @return array{
 *   totals: array<string, int>,
 *   by_bucket: array<int, array<string, mixed>>,
 *   top_messages: array<int, array<string, mixed>>
 * }
 */
function analyzeLogFile(string $logFile, int $cutoff, string $bucket, int $topMessages): array
{
    $handle = fopen($logFile, 'rb');
    if ($handle === false) {
        fail(sprintf('falha ao abrir log: %s', $logFile));
    }

    $totals = [
        'entries_in_window' => 0,
        'info' => 0,
        'warning' => 0,
        'error' => 0,
        'other' => 0,
    ];
    $bucketCounts = [];
    $messageCounts = [];

    try {
        while (($line = fgets($handle)) !== false) {
            $parsed = parseLogLine($line);
            if ($parsed === null) {
                continue;
            }

            if ($parsed['timestamp_unix'] < $cutoff) {
                continue;
            }

            $level = normalizeLevel((string) $parsed['level']);
            $bucketKey = formatBucket($parsed['timestamp_unix'], $bucket);

            $totals['entries_in_window']++;
            if (!array_key_exists(strtolower($level), $totals)) {
                $totals['other']++;
            } else {
                $totals[strtolower($level)]++;
            }

            if (!isset($bucketCounts[$bucketKey])) {
                $bucketCounts[$bucketKey] = [
                    'bucket' => $bucketKey,
                    'total' => 0,
                    'info' => 0,
                    'warning' => 0,
                    'error' => 0,
                    'other' => 0,
                ];
            }

            $bucketCounts[$bucketKey]['total']++;
            if (array_key_exists(strtolower($level), $bucketCounts[$bucketKey])) {
                $bucketCounts[$bucketKey][strtolower($level)]++;
            } else {
                $bucketCounts[$bucketKey]['other']++;
            }

            $signature = $level . ' | ' . (string) $parsed['message'];
            if (!isset($messageCounts[$signature])) {
                $messageCounts[$signature] = [
                    'level' => $level,
                    'message' => (string) $parsed['message'],
                    'count' => 0,
                    'first_seen' => (string) $parsed['timestamp'],
                    'last_seen' => (string) $parsed['timestamp'],
                ];
            }

            $messageCounts[$signature]['count']++;
            if (strcmp((string) $parsed['timestamp'], (string) $messageCounts[$signature]['first_seen']) < 0) {
                $messageCounts[$signature]['first_seen'] = (string) $parsed['timestamp'];
            }
            if (strcmp((string) $parsed['timestamp'], (string) $messageCounts[$signature]['last_seen']) > 0) {
                $messageCounts[$signature]['last_seen'] = (string) $parsed['timestamp'];
            }
        }
    } finally {
        fclose($handle);
    }

    ksort($bucketCounts);
    $bucketRows = array_values($bucketCounts);

    $topMessagesRows = array_values($messageCounts);
    usort($topMessagesRows, static function (array $a, array $b): int {
        $countDiff = (int) $b['count'] <=> (int) $a['count'];
        if ($countDiff !== 0) {
            return $countDiff;
        }

        return strcmp((string) $b['last_seen'], (string) $a['last_seen']);
    });
    $topMessagesRows = array_slice($topMessagesRows, 0, $topMessages);

    return [
        'totals' => $totals,
        'by_bucket' => $bucketRows,
        'top_messages' => $topMessagesRows,
    ];
}

/**
 * @return array{timestamp: string, timestamp_unix: int, level: string, message: string}|null
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

    $body = trim((string) $matches['body']);
    $message = extractMessage($body);

    return [
        'timestamp' => $timestamp,
        'timestamp_unix' => $timestampUnix,
        'level' => (string) $matches['level'],
        'message' => $message,
    ];
}

function extractMessage(string $body): string
{
    $message = $body;
    $candidatePos = strrpos($body, ' {');
    if ($candidatePos !== false) {
        $contextCandidate = trim(substr($body, $candidatePos + 1));
        $decoded = json_decode($contextCandidate, true);
        if (is_array($decoded)) {
            $message = trim(substr($body, 0, $candidatePos));
        }
    }

    return $message !== '' ? $message : '(sem mensagem)';
}

function normalizeLevel(string $level): string
{
    $upper = strtoupper(trim($level));
    if ($upper === 'WARN') {
        return 'WARNING';
    }
    if (in_array($upper, ['INFO', 'WARNING', 'ERROR'], true)) {
        return $upper;
    }

    return 'OTHER';
}

function formatBucket(int $timestampUnix, string $bucket): string
{
    if ($bucket === 'day') {
        return date('Y-m-d', $timestampUnix);
    }

    return date('Y-m-d H:00', $timestampUnix);
}

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail(sprintf('nao foi possivel criar diretorio: %s', $directory));
    }
}

/**
 * @param array<string, mixed> $report
 */
function writeSnapshot(string $snapshotDir, array $report): string
{
    $timestamp = date('Ymd_His');
    $target = rtrim($snapshotDir, '/') . '/log_severity_' . $timestamp . '.json';

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        fail('falha ao serializar snapshot.');
    }

    $bytes = file_put_contents($target, $json . PHP_EOL, LOCK_EX);
    if ($bytes === false) {
        fail(sprintf('falha ao gravar snapshot: %s', $target));
    }

    return $target;
}

/**
 * @return array<int, string>
 */
function cleanupOldSnapshots(string $snapshotDir, int $retentionDays): array
{
    if ($retentionDays <= 0) {
        return [];
    }

    $pattern = rtrim($snapshotDir, '/') . '/log_severity_*.json';
    $files = glob($pattern);
    if (!is_array($files) || $files === []) {
        return [];
    }

    $cutoff = time() - ($retentionDays * 86400);
    $removed = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $mtime = filemtime($file);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }

        if (@unlink($file)) {
            $removed[] = basename($file);
        }
    }

    sort($removed);

    return $removed;
}

/**
 * @param array<string, mixed> $report
 */
function printTableReport(array $report): void
{
    $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
    $byBucket = is_array($report['by_bucket'] ?? null) ? $report['by_bucket'] : [];
    $topMessages = is_array($report['top_messages'] ?? null) ? $report['top_messages'] : [];

    fwrite(STDOUT, '[log-severity] generated_at: ' . (string) ($report['generated_at'] ?? '') . PHP_EOL);
    fwrite(STDOUT, '[log-severity] log_file: ' . (string) ($report['log_file'] ?? '') . PHP_EOL);
    fwrite(
        STDOUT,
        sprintf(
            "[log-severity] window: %sh (%s -> %s), bucket=%s\n",
            (string) ($report['window_hours'] ?? ''),
            (string) ($report['window_from'] ?? ''),
            (string) ($report['window_to'] ?? ''),
            (string) ($report['bucket'] ?? '')
        )
    );
    fwrite(
        STDOUT,
        sprintf(
            "[log-severity] totals: total=%d error=%d warning=%d info=%d other=%d\n",
            (int) ($totals['entries_in_window'] ?? 0),
            (int) ($totals['error'] ?? 0),
            (int) ($totals['warning'] ?? 0),
            (int) ($totals['info'] ?? 0),
            (int) ($totals['other'] ?? 0)
        )
    );

    if (isset($report['snapshot']) && is_array($report['snapshot'])) {
        fwrite(STDOUT, '[log-severity] snapshot: ' . (string) ($report['snapshot']['file'] ?? '') . PHP_EOL);
    }

    if ($byBucket !== []) {
        fwrite(STDOUT, "[log-severity] by_bucket:\n");
        foreach ($byBucket as $row) {
            if (!is_array($row)) {
                continue;
            }

            fwrite(
                STDOUT,
                sprintf(
                    " - %s total=%d error=%d warning=%d info=%d other=%d\n",
                    (string) ($row['bucket'] ?? ''),
                    (int) ($row['total'] ?? 0),
                    (int) ($row['error'] ?? 0),
                    (int) ($row['warning'] ?? 0),
                    (int) ($row['info'] ?? 0),
                    (int) ($row['other'] ?? 0)
                )
            );
        }
    }

    if ($topMessages !== []) {
        fwrite(STDOUT, "[log-severity] top_messages:\n");
        $position = 1;
        foreach ($topMessages as $row) {
            if (!is_array($row)) {
                continue;
            }

            fwrite(
                STDOUT,
                sprintf(
                    "%02d. [%s] x%d | %s | last=%s\n",
                    $position,
                    (string) ($row['level'] ?? 'OTHER'),
                    (int) ($row['count'] ?? 0),
                    (string) ($row['message'] ?? '(sem mensagem)'),
                    (string) ($row['last_seen'] ?? '')
                )
            );
            $position++;
        }
    }
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/log-severity.php [options]

Options:
  --log-file <path>        Arquivo de log (default: storage/logs/app.log)
  --window-hours <n>       Janela de analise em horas (default: 24)
  --bucket <hour|day>      Granularidade da serie temporal (default: hour)
  --top-messages <n>       Quantidade maxima de mensagens no ranking (default: 8)
  --output <table|json>    Formato de saida no stdout (default: table)
  --write-snapshot         Grava snapshot JSON em storage/ops/log-severity
  --snapshot-dir <path>    Diretorio de snapshots (default: storage/ops/log-severity)
  --retention-days <n>     Retencao dos snapshots antigos (default: 30)
  --fail-error-count <n>   Sai com codigo 2 se total de ERROR na janela >= n
  --help                   Mostra esta ajuda
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[log-severity][error] ' . $message . PHP_EOL);
    exit(1);
}
