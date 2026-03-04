#!/usr/bin/env php
<?php

declare(strict_types=1);

const DEFAULT_WINDOW_HOURS = 24;
const DEFAULT_TOP_MESSAGES = 8;
const DEFAULT_HEALTH_TIMEOUT = 20;
const DEFAULT_KPI_MAX_AGE_MINUTES = 360;
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

    $checks = [];
    if ((bool) $options['skip_health'] === true) {
        $checks[] = [
            'name' => 'health_endpoint',
            'status' => 'ok',
            'message' => 'checagem ignorada via --skip-health',
            'skipped' => true,
        ];
    } else {
        $checks[] = runHealthCheck(
            $basePath,
            (string) $options['health_url'],
            (int) $options['health_timeout']
        );
    }

    $checks[] = runLogSeverityCheck(
        $basePath,
        (int) $options['window_hours'],
        (string) $options['bucket'],
        (int) $options['top_messages'],
        $options['error_threshold']
    );

    $checks[] = runErrorReviewCheck(
        $basePath,
        (int) $options['window_hours'],
        (int) $options['top_messages'],
        $options['recurring_threshold']
    );

    $kpiSnapshotDir = resolvePath(
        (string) $options['kpi_snapshot_dir'],
        $basePath . '/storage/ops/kpi_snapshots',
        $basePath
    );
    $checks[] = inspectKpiSnapshotFreshness(
        $kpiSnapshotDir,
        (int) $options['kpi_max_age_minutes']
    );

    $counts = countStatuses($checks);
    $overallStatus = deriveOverallStatus($counts);

    $report = [
        'generated_at' => date(DATE_ATOM),
        'status' => $overallStatus,
        'checks' => $checks,
        'totals' => [
            'checks_total' => count($checks),
            'ok' => $counts['ok'],
            'warn' => $counts['warn'],
            'fail' => $counts['fail'],
        ],
        'config' => [
            'window_hours' => (int) $options['window_hours'],
            'bucket' => (string) $options['bucket'],
            'top_messages' => (int) $options['top_messages'],
            'error_threshold' => $options['error_threshold'],
            'recurring_threshold' => $options['recurring_threshold'],
            'kpi_snapshot_dir' => $kpiSnapshotDir,
            'kpi_max_age_minutes' => (int) $options['kpi_max_age_minutes'],
            'skip_health' => (bool) $options['skip_health'],
        ],
    ];

    if ((bool) $options['write_snapshot'] === true) {
        $snapshotDir = resolvePath(
            (string) $options['snapshot_dir'],
            $basePath . '/storage/ops/health-panel',
            $basePath
        );
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
        printTable($report);
    }

    exit($overallStatus === 'fail' ? 2 : 0);
}

/**
 * @param array<int, string> $argv
 * @return array{
 *   health_url: string,
 *   health_timeout: int,
 *   skip_health: bool,
 *   window_hours: int,
 *   bucket: string,
 *   top_messages: int,
 *   error_threshold: int|null,
 *   recurring_threshold: int|null,
 *   kpi_snapshot_dir: string,
 *   kpi_max_age_minutes: int,
 *   output: string,
 *   write_snapshot: bool,
 *   snapshot_dir: string,
 *   retention_days: int,
 *   help: bool
 * }
 */
function parseOptions(array $argv): array
{
    $options = [
        'health_url' => '',
        'health_timeout' => DEFAULT_HEALTH_TIMEOUT,
        'skip_health' => false,
        'window_hours' => DEFAULT_WINDOW_HOURS,
        'bucket' => 'hour',
        'top_messages' => DEFAULT_TOP_MESSAGES,
        'error_threshold' => null,
        'recurring_threshold' => null,
        'kpi_snapshot_dir' => '',
        'kpi_max_age_minutes' => DEFAULT_KPI_MAX_AGE_MINUTES,
        'output' => 'table',
        'write_snapshot' => false,
        'snapshot_dir' => '',
        'retention_days' => DEFAULT_RETENTION_DAYS,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--health-url':
                $options['health_url'] = readOptionValue($argv, $i, '--health-url');
                break;
            case '--health-timeout':
                $options['health_timeout'] = parseIntOption(
                    readOptionValue($argv, $i, '--health-timeout'),
                    '--health-timeout',
                    1,
                    120
                );
                break;
            case '--skip-health':
                $options['skip_health'] = true;
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
            case '--error-threshold':
                $options['error_threshold'] = parseIntOption(
                    readOptionValue($argv, $i, '--error-threshold'),
                    '--error-threshold',
                    1,
                    1000000
                );
                break;
            case '--recurring-threshold':
                $options['recurring_threshold'] = parseIntOption(
                    readOptionValue($argv, $i, '--recurring-threshold'),
                    '--recurring-threshold',
                    1,
                    1000000
                );
                break;
            case '--kpi-snapshot-dir':
                $options['kpi_snapshot_dir'] = readOptionValue($argv, $i, '--kpi-snapshot-dir');
                break;
            case '--kpi-max-age-minutes':
                $options['kpi_max_age_minutes'] = parseIntOption(
                    readOptionValue($argv, $i, '--kpi-max-age-minutes'),
                    '--kpi-max-age-minutes',
                    1,
                    60 * 24 * 365
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
 * @return array<string, mixed>
 */
function runHealthCheck(string $basePath, string $healthUrl, int $timeout): array
{
    $script = $basePath . '/scripts/healthcheck.sh';
    if (!is_file($script)) {
        return [
            'name' => 'health_endpoint',
            'status' => 'fail',
            'message' => 'script healthcheck nao encontrado',
            'script' => $script,
            'exit_code' => 127,
        ];
    }

    $args = [$script, '--timeout', (string) $timeout];
    if (trim($healthUrl) !== '') {
        $args[] = '--url';
        $args[] = trim($healthUrl);
    }

    $startedAt = microtime(true);
    ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = runCommand($args);
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

    return [
        'name' => 'health_endpoint',
        'status' => $exitCode === 0 ? 'ok' : 'fail',
        'message' => $exitCode === 0
            ? 'endpoint de health respondeu com status ok'
            : 'healthcheck retornou falha',
        'exit_code' => $exitCode,
        'elapsed_ms' => $elapsedMs,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
        'timeout_seconds' => $timeout,
        'url' => trim($healthUrl),
    ];
}

/**
 * @return array<string, mixed>
 */
function runLogSeverityCheck(
    string $basePath,
    int $windowHours,
    string $bucket,
    int $topMessages,
    ?int $errorThreshold
): array {
    $script = $basePath . '/scripts/log-severity.php';
    if (!is_file($script)) {
        return [
            'name' => 'log_severity',
            'status' => 'fail',
            'message' => 'script log-severity nao encontrado',
            'script' => $script,
            'exit_code' => 127,
        ];
    }

    $args = [
        'php',
        $script,
        '--output',
        'json',
        '--window-hours',
        (string) $windowHours,
        '--bucket',
        $bucket,
        '--top-messages',
        (string) $topMessages,
    ];

    $startedAt = microtime(true);
    ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = runCommand($args);
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
    $decoded = decodeJsonLine($stdout);

    if (!is_array($decoded)) {
        return [
            'name' => 'log_severity',
            'status' => 'fail',
            'message' => 'saida JSON invalida de log-severity',
            'exit_code' => $exitCode,
            'elapsed_ms' => $elapsedMs,
            'stderr' => trim($stderr),
        ];
    }

    $errorCount = (int) ($decoded['totals']['error'] ?? 0);
    $warningCount = (int) ($decoded['totals']['warning'] ?? 0);
    $entries = (int) ($decoded['totals']['entries_in_window'] ?? 0);

    $status = 'ok';
    $message = 'severidade de logs dentro do esperado';

    if ($exitCode !== 0) {
        $status = 'fail';
        $message = 'script log-severity retornou falha';
    } elseif (is_int($errorThreshold) && $errorCount >= $errorThreshold) {
        $status = 'fail';
        $message = sprintf(
            'erros na janela (%d) atingiram o limite configurado (%d)',
            $errorCount,
            $errorThreshold
        );
    } elseif ($errorCount > 0 || $warningCount > 0) {
        $status = 'warn';
        $message = sprintf('janela possui warning/error (warning=%d, error=%d)', $warningCount, $errorCount);
    }

    return [
        'name' => 'log_severity',
        'status' => $status,
        'message' => $message,
        'exit_code' => $exitCode,
        'elapsed_ms' => $elapsedMs,
        'stderr' => trim($stderr),
        'metrics' => [
            'entries_in_window' => $entries,
            'warning' => $warningCount,
            'error' => $errorCount,
            'error_threshold' => $errorThreshold,
            'window_hours' => (int) ($decoded['window_hours'] ?? $windowHours),
            'window_from' => (string) ($decoded['window_from'] ?? ''),
            'window_to' => (string) ($decoded['window_to'] ?? ''),
            'bucket' => (string) ($decoded['bucket'] ?? $bucket),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function runErrorReviewCheck(
    string $basePath,
    int $windowHours,
    int $topMessages,
    ?int $recurringThreshold
): array {
    $script = $basePath . '/scripts/error-review.php';
    if (!is_file($script)) {
        return [
            'name' => 'error_review',
            'status' => 'fail',
            'message' => 'script error-review nao encontrado',
            'script' => $script,
            'exit_code' => 127,
        ];
    }

    $args = [
        'php',
        $script,
        '--output',
        'json',
        '--window-hours',
        (string) $windowHours,
        '--top',
        (string) $topMessages,
    ];

    $startedAt = microtime(true);
    ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = runCommand($args);
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
    $decoded = decodeJsonLine($stdout);

    if (!is_array($decoded)) {
        return [
            'name' => 'error_review',
            'status' => 'fail',
            'message' => 'saida JSON invalida de error-review',
            'exit_code' => $exitCode,
            'elapsed_ms' => $elapsedMs,
            'stderr' => trim($stderr),
        ];
    }

    $recurringErrorEntries = (int) ($decoded['totals']['recurring_error_entries'] ?? 0);
    $recurringErrorGroups = (int) ($decoded['totals']['recurring_error_groups'] ?? 0);
    $recurringGroups = (int) ($decoded['totals']['recurring_groups'] ?? 0);

    $status = 'ok';
    $message = 'nao foram detectados erros recorrentes na janela';

    if ($exitCode !== 0) {
        $status = 'fail';
        $message = 'script error-review retornou falha';
    } elseif (is_int($recurringThreshold) && $recurringErrorEntries >= $recurringThreshold) {
        $status = 'fail';
        $message = sprintf(
            'recorrencia de erro (%d) atingiu o limite (%d)',
            $recurringErrorEntries,
            $recurringThreshold
        );
    } elseif ($recurringErrorEntries > 0 || $recurringErrorGroups > 0) {
        $status = 'warn';
        $message = sprintf(
            'erros recorrentes detectados (grupos=%d, entradas=%d)',
            $recurringErrorGroups,
            $recurringErrorEntries
        );
    } elseif ($recurringGroups > 0) {
        $status = 'warn';
        $message = sprintf('ha recorrencias nao criticas (%d grupos)', $recurringGroups);
    }

    return [
        'name' => 'error_review',
        'status' => $status,
        'message' => $message,
        'exit_code' => $exitCode,
        'elapsed_ms' => $elapsedMs,
        'stderr' => trim($stderr),
        'metrics' => [
            'recurring_groups' => $recurringGroups,
            'recurring_error_groups' => $recurringErrorGroups,
            'recurring_error_entries' => $recurringErrorEntries,
            'recurring_threshold' => $recurringThreshold,
            'window_hours' => (int) ($decoded['window_hours'] ?? $windowHours),
            'window_from' => (string) ($decoded['window_from'] ?? ''),
            'window_to' => (string) ($decoded['window_to'] ?? ''),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function inspectKpiSnapshotFreshness(string $snapshotDir, int $maxAgeMinutes): array
{
    if (!is_dir($snapshotDir)) {
        return [
            'name' => 'kpi_snapshot_freshness',
            'status' => 'warn',
            'message' => 'diretorio de snapshots de KPI nao encontrado',
            'metrics' => [
                'snapshot_dir' => $snapshotDir,
                'max_age_minutes' => $maxAgeMinutes,
            ],
        ];
    }

    $pattern = rtrim($snapshotDir, '/') . '/kpi_snapshot_*.json';
    $files = glob($pattern);
    if (!is_array($files) || $files === []) {
        return [
            'name' => 'kpi_snapshot_freshness',
            'status' => 'warn',
            'message' => 'nenhum snapshot de KPI encontrado',
            'metrics' => [
                'snapshot_dir' => $snapshotDir,
                'max_age_minutes' => $maxAgeMinutes,
            ],
        ];
    }

    $latestFile = '';
    $latestMtime = 0;
    foreach ($files as $file) {
        $mtime = filemtime($file);
        if (!is_int($mtime)) {
            continue;
        }

        if ($mtime > $latestMtime) {
            $latestMtime = $mtime;
            $latestFile = $file;
        }
    }

    if ($latestFile === '' || $latestMtime <= 0) {
        return [
            'name' => 'kpi_snapshot_freshness',
            'status' => 'warn',
            'message' => 'nao foi possivel determinar snapshot mais recente',
            'metrics' => [
                'snapshot_dir' => $snapshotDir,
                'max_age_minutes' => $maxAgeMinutes,
            ],
        ];
    }

    $ageMinutes = (int) floor(max(0, time() - $latestMtime) / 60);
    $status = $ageMinutes <= $maxAgeMinutes ? 'ok' : 'fail';
    $message = $status === 'ok'
        ? 'snapshot de KPI esta atualizado'
        : sprintf('snapshot de KPI esta desatualizado (%d min)', $ageMinutes);

    return [
        'name' => 'kpi_snapshot_freshness',
        'status' => $status,
        'message' => $message,
        'metrics' => [
            'snapshot_file' => $latestFile,
            'snapshot_mtime' => date(DATE_ATOM, $latestMtime),
            'snapshot_age_minutes' => $ageMinutes,
            'max_age_minutes' => $maxAgeMinutes,
            'snapshot_dir' => $snapshotDir,
        ],
    ];
}

/**
 * @param array<int, array<string, mixed>> $checks
 * @return array{ok: int, warn: int, fail: int}
 */
function countStatuses(array $checks): array
{
    $counts = [
        'ok' => 0,
        'warn' => 0,
        'fail' => 0,
    ];

    foreach ($checks as $check) {
        $status = strtolower((string) ($check['status'] ?? 'warn'));
        if (!array_key_exists($status, $counts)) {
            $status = 'warn';
        }

        $counts[$status]++;
    }

    return $counts;
}

/**
 * @param array{ok: int, warn: int, fail: int} $counts
 */
function deriveOverallStatus(array $counts): string
{
    if ((int) ($counts['fail'] ?? 0) > 0) {
        return 'fail';
    }

    if ((int) ($counts['warn'] ?? 0) > 0) {
        return 'warn';
    }

    return 'ok';
}

/**
 * @param array<int, string> $cmdParts
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runCommand(array $cmdParts): array
{
    $escaped = array_map(static fn (string $part): string => escapeshellarg($part), $cmdParts);
    $command = implode(' ', $escaped);

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => 'falha ao iniciar processo',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => is_int($exitCode) ? $exitCode : 1,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @return array<string, mixed>|null
 */
function decodeJsonLine(string $stdout): ?array
{
    $text = trim($stdout);
    if ($text === '') {
        return null;
    }

    $lines = preg_split('/\R/', $text);
    if (!is_array($lines) || $lines === []) {
        return null;
    }

    $candidate = trim((string) end($lines));
    if ($candidate === '') {
        return null;
    }

    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $report
 */
function printTable(array $report): void
{
    fwrite(STDOUT, '[ops-health-panel] generated_at: ' . (string) ($report['generated_at'] ?? '') . PHP_EOL);
    fwrite(
        STDOUT,
        sprintf(
            "[ops-health-panel] status=%s checks=%d ok=%d warn=%d fail=%d\n",
            (string) ($report['status'] ?? 'unknown'),
            (int) ($report['totals']['checks_total'] ?? 0),
            (int) ($report['totals']['ok'] ?? 0),
            (int) ($report['totals']['warn'] ?? 0),
            (int) ($report['totals']['fail'] ?? 0)
        )
    );

    fwrite(STDOUT, str_repeat('-', 108) . PHP_EOL);
    fwrite(STDOUT, sprintf("%-24s %-8s %-10s %s\n", 'check', 'status', 'elapsed_ms', 'message'));
    fwrite(STDOUT, str_repeat('-', 108) . PHP_EOL);

    $checks = $report['checks'] ?? [];
    if (!is_array($checks)) {
        $checks = [];
    }

    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }

        $name = (string) ($check['name'] ?? 'unknown');
        $status = strtoupper((string) ($check['status'] ?? 'warn'));
        $elapsed = isset($check['elapsed_ms']) ? (string) (int) $check['elapsed_ms'] : '-';
        $message = (string) ($check['message'] ?? '');

        fwrite(STDOUT, sprintf("%-24s %-8s %-10s %s\n", $name, $status, $elapsed, $message));
    }

    fwrite(STDOUT, str_repeat('-', 108) . PHP_EOL);
}

/**
 * @param array<string, mixed> $payload
 */
function writeSnapshot(string $directory, array $payload): string
{
    $timestamp = date('Ymd_His');
    $file = rtrim($directory, '/') . '/ops_health_panel_' . $timestamp . '.json';
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        fail('falha ao serializar snapshot JSON.');
    }

    if (file_put_contents($file, $encoded . PHP_EOL) === false) {
        fail(sprintf('falha ao gravar snapshot: %s', $file));
    }

    return $file;
}

/**
 * @return array<int, string>
 */
function cleanupOldSnapshots(string $directory, int $retentionDays): array
{
    if ($retentionDays <= 0) {
        return [];
    }

    $threshold = time() - ($retentionDays * 86400);
    $pattern = rtrim($directory, '/') . '/ops_health_panel_*.json';
    $files = glob($pattern);
    if (!is_array($files) || $files === []) {
        return [];
    }

    $removed = [];
    foreach ($files as $file) {
        $mtime = filemtime($file);
        if (!is_int($mtime) || $mtime >= $threshold) {
            continue;
        }

        if (@unlink($file)) {
            $removed[] = $file;
        }
    }

    return $removed;
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

function printUsage(): void
{
    $usage = <<<TXT
Uso: ./scripts/ops-health-panel.php [opcoes]

Painel tecnico de saude operacional (fase 7.4), agregando:
- health endpoint;
- severidade de logs;
- recorrencia de erros;
- frescor de snapshot de KPI.

Opcoes:
  --health-url <url>              URL de health-check (opcional)
  --health-timeout <n>            Timeout do health-check em segundos (padrao: 20)
  --skip-health                   Nao executa health-check HTTP
  --window-hours <n>              Janela de analise para logs (padrao: 24)
  --bucket <hour|day>             Granularidade da serie de severidade (padrao: hour)
  --top-messages <n>              Top recorrencias/mensagens (padrao: 8)
  --error-threshold <n>           Limite de erros para marcar falha
  --recurring-threshold <n>       Limite de recorrencia de erro para falha
  --kpi-snapshot-dir <path>       Diretorio de snapshots KPI (padrao: storage/ops/kpi_snapshots)
  --kpi-max-age-minutes <n>       Idade maxima permitida do ultimo snapshot KPI (padrao: 360)
  --output <table|json>           Formato de saida (padrao: table)
  --write-snapshot                Grava snapshot do painel (JSON)
  --snapshot-dir <path>           Diretorio dos snapshots do painel (padrao: storage/ops/health-panel)
  --retention-days <n>            Retencao dos snapshots do painel (padrao: 30)
  --help, -h                      Exibe esta ajuda

Codigos de saida:
  0 - painel sem falhas criticas (ok/warn)
  2 - ao menos um check em falha
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, '[ops-health-panel][error] ' . $message . PHP_EOL);
    exit(1);
}
