#!/usr/bin/env php
<?php

declare(strict_types=1);

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

    if ((bool) $options['skip_qa'] === false) {
        $qaArgs = [
            '--output', 'json',
            '--cleanup-after',
        ];
        $checks[] = runCheck('qa_regression', $basePath . '/scripts/qa-regression.php', $qaArgs);
    }

    $logSeverityArgs = [
        '--output', 'json',
        '--window-hours', (string) $options['window_hours'],
        '--bucket', (string) $options['bucket'],
        '--top-messages', (string) $options['top_messages'],
    ];
    if ((bool) $options['write_snapshots'] === true) {
        $logSeverityArgs[] = '--write-snapshot';
        $logSeverityArgs[] = '--retention-days';
        $logSeverityArgs[] = (string) $options['retention_days'];
    }
    if (is_int($options['error_threshold'])) {
        $logSeverityArgs[] = '--fail-error-count';
        $logSeverityArgs[] = (string) $options['error_threshold'];
    }
    $checks[] = runCheck('log_severity', $basePath . '/scripts/log-severity.php', $logSeverityArgs);

    $errorReviewArgs = [
        '--output', 'json',
        '--window-hours', (string) $options['window_hours'],
        '--top', (string) $options['top_messages'],
    ];
    if (is_int($options['recurring_threshold'])) {
        $errorReviewArgs[] = '--fail-threshold';
        $errorReviewArgs[] = (string) $options['recurring_threshold'];
    }
    $checks[] = runCheck('error_review', $basePath . '/scripts/error-review.php', $errorReviewArgs);

    $failedChecks = array_values(array_filter($checks, static fn (array $check): bool => (bool) ($check['ok'] ?? false) === false));

    $report = [
        'generated_at' => date(DATE_ATOM),
        'window_hours' => (int) $options['window_hours'],
        'bucket' => (string) $options['bucket'],
        'checks' => $checks,
        'totals' => [
            'checks_total' => count($checks),
            'checks_failed' => count($failedChecks),
        ],
        'status' => $failedChecks === [] ? 'ok' : 'failed',
    ];

    if ((string) $options['output'] === 'json') {
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            fail('falha ao serializar saida json.');
        }

        fwrite(STDOUT, $json . PHP_EOL);
    } else {
        printTable($report);
    }

    exit($failedChecks === [] ? 0 : 2);
}

/**
 * @param array<int, string> $argv
 * @return array{
 *   window_hours: int,
 *   bucket: string,
 *   top_messages: int,
 *   error_threshold: int|null,
 *   recurring_threshold: int|null,
 *   write_snapshots: bool,
 *   retention_days: int,
 *   skip_qa: bool,
 *   output: string,
 *   help: bool
 * }
 */
function parseOptions(array $argv): array
{
    $options = [
        'window_hours' => 24,
        'bucket' => 'hour',
        'top_messages' => 8,
        'error_threshold' => null,
        'recurring_threshold' => null,
        'write_snapshots' => false,
        'retention_days' => 30,
        'skip_qa' => false,
        'output' => 'table',
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
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
            case '--write-snapshots':
                $options['write_snapshots'] = true;
                break;
            case '--retention-days':
                $options['retention_days'] = parseIntOption(
                    readOptionValue($argv, $i, '--retention-days'),
                    '--retention-days',
                    0,
                    3650
                );
                break;
            case '--skip-qa':
                $options['skip_qa'] = true;
                break;
            case '--output':
                $output = strtolower(readOptionValue($argv, $i, '--output'));
                if (!in_array($output, ['table', 'json'], true)) {
                    fail('--output deve ser table ou json.');
                }
                $options['output'] = $output;
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
 * @param array<int, string> $args
 * @return array<string, mixed>
 */
function runCheck(string $name, string $scriptPath, array $args): array
{
    if (!is_file($scriptPath)) {
        return [
            'name' => $name,
            'ok' => false,
            'exit_code' => 127,
            'error' => 'script nao encontrado: ' . $scriptPath,
        ];
    }

    $cmdParts = ['php', $scriptPath];
    foreach ($args as $arg) {
        $cmdParts[] = $arg;
    }

    ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = runCommand($cmdParts);
    $decoded = decodeJsonLine($stdout);

    return [
        'name' => $name,
        'ok' => $exitCode === 0,
        'exit_code' => $exitCode,
        'output' => $decoded,
        'stderr' => trim($stderr),
    ];
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
    fwrite(STDOUT, '[ops-quality-gate] generated_at: ' . (string) ($report['generated_at'] ?? '') . PHP_EOL);
    fwrite(
        STDOUT,
        sprintf(
            "[ops-quality-gate] status=%s checks=%d failed=%d window=%sh bucket=%s\n",
            (string) ($report['status'] ?? 'unknown'),
            (int) ($report['totals']['checks_total'] ?? 0),
            (int) ($report['totals']['checks_failed'] ?? 0),
            (int) ($report['window_hours'] ?? 0),
            (string) ($report['bucket'] ?? '')
        )
    );

    $checks = is_array($report['checks'] ?? null) ? $report['checks'] : [];
    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }

        fwrite(
            STDOUT,
            sprintf(
                " - [%s] %s (exit=%d)\n",
                ((bool) ($check['ok'] ?? false)) ? 'ok' : 'fail',
                (string) ($check['name'] ?? 'check'),
                (int) ($check['exit_code'] ?? 1)
            )
        );
    }
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/ops-quality-gate.php [options]

Options:
  --window-hours <n>        Janela (horas) para checks de logs (default: 24)
  --bucket <hour|day>       Bucket da serie de severidade (default: hour)
  --top-messages <n>        Top mensagens para checks de logs (default: 8)
  --error-threshold <n>     Falha se total de ERROR no log-severity >= n
  --recurring-threshold <n> Falha se recorrencia de erro no error-review >= n
  --write-snapshots         Grava snapshots no log-severity
  --retention-days <n>      Retencao dos snapshots (default: 30)
  --skip-qa                 Nao executa qa-regression
  --output <table|json>     Formato de saida (default: table)
  --help                    Mostra esta ajuda

Exit codes:
  0 = todos os checks passaram
  2 = algum check falhou
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[ops-quality-gate][error] ' . $message . PHP_EOL);
    exit(1);
}
