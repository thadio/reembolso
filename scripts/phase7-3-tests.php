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

    if ((bool) $options['skip_unit'] === false) {
        $checks[] = runCheck(
            'financial_unit',
            $basePath . '/scripts/financial-unit-tests.php',
            ['--output', 'json']
        );
    }

    if ((bool) $options['skip_integration'] === false) {
        $checks[] = runCheck(
            'financial_integration',
            $basePath . '/scripts/financial-integration-tests.php',
            ['--output', 'json', '--cleanup-after']
        );
    }

    if ((bool) $options['skip_qa'] === false) {
        $checks[] = runCheck(
            'qa_regression',
            $basePath . '/scripts/qa-regression.php',
            ['--output', 'json', '--cleanup-after']
        );
    }

    $failedChecks = array_values(array_filter($checks, static fn (array $check): bool => (bool) ($check['ok'] ?? false) === false));

    $report = [
        'generated_at' => date(DATE_ATOM),
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
 * @return array{skip_unit: bool, skip_integration: bool, skip_qa: bool, output: string, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'skip_unit' => false,
        'skip_integration' => false,
        'skip_qa' => false,
        'output' => 'table',
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--skip-unit':
                $options['skip_unit'] = true;
                break;
            case '--skip-integration':
                $options['skip_integration'] = true;
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
            'stderr' => 'nao foi possivel iniciar processo',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    if ($stdout === false) {
        $stdout = '';
    }
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    if ($stderr === false) {
        $stderr = '';
    }
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => is_int($exitCode) ? $exitCode : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/** @return array<string, mixed> */
function decodeJsonLine(string $stdout): array
{
    $trimmed = trim($stdout);
    if ($trimmed === '') {
        return ['raw' => ''];
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return ['raw' => $trimmed];
}

/**
 * @param array<string, mixed> $report
 */
function printTable(array $report): void
{
    fwrite(STDOUT, '[phase7-3-tests] status: ' . (string) ($report['status'] ?? 'unknown') . PHP_EOL);

    $totals = $report['totals'] ?? [];
    if (is_array($totals)) {
        fwrite(
            STDOUT,
            sprintf(
                "[phase7-3-tests] checks: total=%d failed=%d\n",
                (int) ($totals['checks_total'] ?? 0),
                (int) ($totals['checks_failed'] ?? 0)
            )
        );
    }

    $checks = $report['checks'] ?? [];
    if (!is_array($checks)) {
        return;
    }

    fwrite(STDOUT, "[phase7-3-tests] details:\n");
    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }

        fwrite(
            STDOUT,
            sprintf(
                " - [%s] %s exit=%d\n",
                ((bool) ($check['ok'] ?? false)) ? 'ok' : 'fail',
                (string) ($check['name'] ?? ''),
                (int) ($check['exit_code'] ?? 1)
            )
        );

        $stderr = trim((string) ($check['stderr'] ?? ''));
        if ($stderr !== '') {
            fwrite(STDOUT, sprintf("   stderr: %s\n", $stderr));
        }
    }
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/phase7-3-tests.php [options]

Options:
  --skip-unit             Nao executa financial-unit-tests
  --skip-integration      Nao executa financial-integration-tests
  --skip-qa               Nao executa qa-regression
  --output <table|json>   Formato de saida (default: table)
  --help                  Mostra esta ajuda

Exit codes:
  0 = todos os checks passaram
  2 = pelo menos um check falhou
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[phase7-3-tests][error] ' . $message . PHP_EOL);
    exit(1);
}
