#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Logger;
use App\Repositories\DashboardRepository;
use App\Services\DashboardService;

const SNAPSHOT_PREFIX = 'kpi_snapshot_';
const DEFAULT_TIMELINE_LIMIT = 8;
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

    $app = require $basePath . '/bootstrap.php';
    if (!$app instanceof App) {
        fail('falha ao inicializar a aplicacao.');
    }

    $outputDirInput = (string) ($options['output_dir'] ?? '');
    if ($outputDirInput === '') {
        $outputDirInput = envOrDefault('OPS_KPI_SNAPSHOT_DIR', 'storage/ops/kpi_snapshots');
    }

    $retentionDays = $options['retention_days'];
    if (!is_int($retentionDays)) {
        $retentionDays = numericEnvOrDefault('OPS_KPI_SNAPSHOT_RETENTION_DAYS', DEFAULT_RETENTION_DAYS);
    }

    $outputDir = resolveOutputDir($outputDirInput, $basePath);
    $timelineLimit = (int) $options['timeline_limit'];
    $dryRun = (bool) $options['dry_run'];

    $dashboard = new DashboardService(new DashboardRepository($app->db()));
    $overview = $dashboard->overview($timelineLimit);

    $snapshot = buildSnapshotPayload($overview, $app);
    $targetFile = buildSnapshotPath($outputDir);

    if ($dryRun) {
        $removed = cleanupSnapshots($outputDir, $retentionDays, true);
        outputResult([
            'status' => 'dry-run',
            'captured_at' => $snapshot['captured_at'],
            'output_dir' => $outputDir,
            'snapshot_file' => $targetFile,
            'retention_days' => $retentionDays,
            'removed_files' => $removed,
        ]);

        return;
    }

    ensureDirectory($outputDir);
    writeSnapshot($targetFile, $snapshot);
    $removed = cleanupSnapshots($outputDir, $retentionDays, false);

    Logger::info('kpi.snapshot.generated', [
        'snapshot_file' => $targetFile,
        'retention_days' => $retentionDays,
        'removed_files' => count($removed),
    ]);

    outputResult([
        'status' => 'ok',
        'captured_at' => $snapshot['captured_at'],
        'output_dir' => $outputDir,
        'snapshot_file' => $targetFile,
        'retention_days' => $retentionDays,
        'removed_files' => $removed,
    ]);
}

/**
 * @param array<string, mixed> $overview
 * @return array<string, mixed>
 */
function buildSnapshotPayload(array $overview, App $app): array
{
    $summary = is_array($overview['summary'] ?? null) ? $overview['summary'] : [];
    $expectedCurrent = (float) ($summary['expected_reimbursement_current_month'] ?? 0.0);
    $postedCurrent = (float) ($summary['actual_reimbursement_posted_current_month'] ?? 0.0);
    $paidCurrent = (float) ($summary['actual_reimbursement_paid_current_month'] ?? 0.0);

    return [
        'captured_at' => date(DATE_ATOM),
        'timezone' => date_default_timezone_get(),
        'app' => [
            'name' => (string) $app->config()->get('app.name', 'Reembolso'),
            'env' => (string) $app->config()->get('app.env', 'production'),
        ],
        'summary' => $summary,
        'status_distribution' => is_array($overview['status_distribution'] ?? null) ? $overview['status_distribution'] : [],
        'recommendation' => is_array($overview['recommendation'] ?? null) ? $overview['recommendation'] : [],
        'projections' => [
            'method' => 'annualizacao_linear_mes_atual_x_12',
            'annualized_expected_reimbursement' => round($expectedCurrent * 12, 2),
            'annualized_posted_reimbursement' => round($postedCurrent * 12, 2),
            'annualized_paid_reimbursement' => round($paidCurrent * 12, 2),
        ],
    ];
}

/**
 * @param array<int, string> $argv
 * @return array{output_dir: string, retention_days: int|null, timeline_limit: int, dry_run: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $outputDir = '';
    $retentionDays = null;
    $timelineLimit = DEFAULT_TIMELINE_LIMIT;
    $dryRun = false;
    $help = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--output-dir':
                $outputDir = readOptionValue($argv, $i, '--output-dir');
                break;
            case '--retention-days':
                $retentionDays = parseIntOption(
                    readOptionValue($argv, $i, '--retention-days'),
                    '--retention-days',
                    0,
                    3650
                );
                break;
            case '--timeline-limit':
                $timelineLimit = parseIntOption(
                    readOptionValue($argv, $i, '--timeline-limit'),
                    '--timeline-limit',
                    1,
                    50
                );
                break;
            case '--dry-run':
                $dryRun = true;
                break;
            case '--help':
            case '-h':
                $help = true;
                break;
            default:
                fail(sprintf('opcao desconhecida: %s', $arg));
        }
    }

    return [
        'output_dir' => trim($outputDir),
        'retention_days' => $retentionDays,
        'timeline_limit' => $timelineLimit,
        'dry_run' => $dryRun,
        'help' => $help,
    ];
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

function envOrDefault(string $key, string $default): string
{
    $value = getenv($key);
    if (!is_string($value)) {
        return $default;
    }

    $value = trim($value);

    return $value !== '' ? $value : $default;
}

function numericEnvOrDefault(string $key, int $default): int
{
    $value = getenv($key);
    if (!is_string($value)) {
        return $default;
    }

    $value = trim($value);
    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        return $default;
    }

    return (int) $value;
}

function resolveOutputDir(string $outputDir, string $basePath): string
{
    if ($outputDir === '') {
        return $basePath . '/storage/ops/kpi_snapshots';
    }

    if (str_starts_with($outputDir, '/')) {
        return rtrim($outputDir, '/');
    }

    return rtrim($basePath . '/' . ltrim($outputDir, '/'), '/');
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

function buildSnapshotPath(string $outputDir): string
{
    $timestamp = date('Ymd_His');
    $base = rtrim($outputDir, '/');
    $candidate = $base . '/' . SNAPSHOT_PREFIX . $timestamp . '.json';

    $counter = 1;
    while (is_file($candidate)) {
        $candidate = sprintf('%s/%s%s_%02d.json', $base, SNAPSHOT_PREFIX, $timestamp, $counter);
        $counter++;
    }

    return $candidate;
}

/**
 * @param array<string, mixed> $snapshot
 */
function writeSnapshot(string $path, array $snapshot): void
{
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        fail('falha ao serializar snapshot.');
    }

    $tempPath = $path . '.tmp';
    $bytes = file_put_contents($tempPath, $json . PHP_EOL, LOCK_EX);
    if ($bytes === false) {
        fail(sprintf('falha ao gravar arquivo temporario: %s', $tempPath));
    }

    if (!rename($tempPath, $path)) {
        @unlink($tempPath);
        fail(sprintf('falha ao mover arquivo temporario para destino: %s', $path));
    }
}

/**
 * @return array<int, string>
 */
function cleanupSnapshots(string $directory, int $retentionDays, bool $dryRun): array
{
    if ($retentionDays <= 0 || !is_dir($directory)) {
        return [];
    }

    $cutoff = time() - ($retentionDays * 86400);
    $pattern = rtrim($directory, '/') . '/' . SNAPSHOT_PREFIX . '*.json';
    $files = glob($pattern);
    if (!is_array($files)) {
        return [];
    }

    $removed = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $mtime = filemtime($file);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }

        if ($dryRun || @unlink($file)) {
            $removed[] = basename($file);
        }
    }

    sort($removed);

    return $removed;
}

/**
 * @param array<string, mixed> $payload
 */
function outputResult(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        fail('falha ao serializar resultado final.');
    }

    fwrite(STDOUT, $json . PHP_EOL);
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/kpi-snapshot.php [options]

Options:
  --output-dir <path>      Diretorio de snapshots (default: OPS_KPI_SNAPSHOT_DIR ou storage/ops/kpi_snapshots)
  --retention-days <n>     Remove snapshots mais antigos que n dias (default: OPS_KPI_SNAPSHOT_RETENTION_DAYS ou 30)
  --timeline-limit <n>     Quantidade de itens da timeline no calculo do dashboard (default: 8)
  --dry-run                Simula geracao e limpeza sem gravar arquivos
  --help                   Mostra esta ajuda
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[kpi-snapshot][error] ' . $message . PHP_EOL);
    exit(1);
}
