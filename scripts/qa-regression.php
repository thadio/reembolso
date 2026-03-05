#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\DashboardRepository;
use App\Services\DashboardService;

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
        fail('falha ao inicializar aplicacao.');
    }

    $fixturePath = resolvePath((string) $options['fixture_file'], $basePath . '/tests/fixtures/qa_regression_dataset.sql', $basePath);
    if (!is_file($fixturePath)) {
        fail(sprintf('fixture nao encontrada: %s', $fixturePath));
    }

    $db = $app->db();

    if ((bool) $options['cleanup_only'] === true) {
        cleanupFixtureData($db);
        output((string) $options['output'], [
            'status' => 'cleanup_only',
            'fixture_file' => $fixturePath,
            'cleaned_ids' => fixtureIds(),
        ]);
        exit(0);
    }

    cleanupFixtureData($db);

    $baseline = dashboardSummary($app);
    applyFixtureFile($db, $fixturePath);
    $after = dashboardSummary($app);
    $delta = buildDelta($baseline, $after);
    $assertions = validateDelta($delta);
    $passed = !in_array(false, array_column($assertions, 'pass'), true);

    if ((bool) $options['cleanup_after'] === true) {
        cleanupFixtureData($db);
    }

    $payload = [
        'status' => $passed ? 'ok' : 'failed',
        'fixture_file' => $fixturePath,
        'cleanup_after' => (bool) $options['cleanup_after'],
        'baseline' => selectSummaryKeys($baseline),
        'after' => selectSummaryKeys($after),
        'delta' => selectSummaryKeys($delta),
        'assertions' => $assertions,
    ];

    output((string) $options['output'], $payload);

    exit($passed ? 0 : 2);
}

/**
 * @param array<int, string> $argv
 * @return array{fixture_file: string, output: string, cleanup_only: bool, cleanup_after: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'fixture_file' => '',
        'output' => 'table',
        'cleanup_only' => false,
        'cleanup_after' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        switch ($arg) {
            case '--fixture-file':
                $options['fixture_file'] = readOptionValue($argv, $i, '--fixture-file');
                break;
            case '--output':
                $output = strtolower(readOptionValue($argv, $i, '--output'));
                if (!in_array($output, ['table', 'json'], true)) {
                    fail('--output deve ser table ou json.');
                }
                $options['output'] = $output;
                break;
            case '--cleanup-only':
                $options['cleanup_only'] = true;
                break;
            case '--cleanup-after':
                $options['cleanup_after'] = true;
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
 * @return array<string, array<int, int>>
 */
function fixtureIds(): array
{
    return [
        'organs' => [990001],
        'people' => [990101, 990102],
        'cost_plans' => [990201],
        'cost_plan_items' => [990301, 990302, 990303],
        'reimbursement_entries' => [990401, 990402, 990403],
        'cdos' => [990501],
        'cdo_people' => [990601],
        'timeline_events' => [990701],
    ];
}

function cleanupFixtureData(PDO $db): void
{
    $ids = fixtureIds();

    $db->beginTransaction();
    try {
        deleteByIds($db, 'timeline_events', $ids['timeline_events']);
        deleteByIds($db, 'reimbursement_entries', $ids['reimbursement_entries']);
        deleteByIds($db, 'cdo_people', $ids['cdo_people']);
        deleteByIds($db, 'cdos', $ids['cdos']);
        deleteByIds($db, 'cost_plan_items', $ids['cost_plan_items']);
        deleteByIds($db, 'cost_plans', $ids['cost_plans']);
        deleteByIds($db, 'people', $ids['people']);
        deleteByIds($db, 'organs', $ids['organs']);
        $db->commit();
    } catch (\Throwable $throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        fail('falha no cleanup da fixture: ' . $throwable->getMessage());
    }
}

/**
 * @param array<int, int> $ids
 */
function deleteByIds(PDO $db, string $table, array $ids): void
{
    if ($ids === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = sprintf('DELETE FROM %s WHERE id IN (%s)', $table, $placeholders);
    $stmt = $db->prepare($sql);
    foreach (array_values($ids) as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
}

function applyFixtureFile(PDO $db, string $fixturePath): void
{
    $raw = file_get_contents($fixturePath);
    if (!is_string($raw)) {
        fail(sprintf('nao foi possivel ler fixture: %s', $fixturePath));
    }

    $withoutComments = preg_replace('/^\s*--.*$/m', '', $raw);
    if (!is_string($withoutComments)) {
        fail('falha ao processar fixture.');
    }

    $statements = array_values(array_filter(
        array_map('trim', explode(';', $withoutComments)),
        static fn (string $stmt): bool => $stmt !== ''
    ));

    if ($statements === []) {
        fail('fixture sem statements validos.');
    }

    $db->beginTransaction();
    try {
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
        $db->commit();
    } catch (\Throwable $throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        fail('falha ao aplicar fixture: ' . $throwable->getMessage());
    }
}

/**
 * @return array<string, int|float>
 */
function dashboardSummary(App $app): array
{
    $service = new DashboardService(new DashboardRepository($app->db()), $app->config());
    $overview = $service->overview(8, false);
    $summary = $overview['summary'] ?? [];

    if (!is_array($summary)) {
        return [];
    }

    $normalized = [];
    foreach ($summary as $key => $value) {
        if (is_int($value) || is_float($value)) {
            $normalized[(string) $key] = $value;
        }
    }

    return $normalized;
}

/**
 * @param array<string, int|float> $before
 * @param array<string, int|float> $after
 * @return array<string, int|float>
 */
function buildDelta(array $before, array $after): array
{
    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    $delta = [];
    foreach ($keys as $key) {
        $a = (float) ($after[$key] ?? 0.0);
        $b = (float) ($before[$key] ?? 0.0);
        $value = $a - $b;

        $isIntMetric = in_array($key, [
            'total_people',
            'active_people',
            'in_progress_people',
            'total_organs',
            'people_with_documents',
            'people_with_active_cost_plan',
            'people_without_documents',
            'people_without_cost_plan',
            'timeline_last_30_days',
            'audit_last_30_days',
            'total_cdos',
            'open_cdos',
        ], true);

        $delta[$key] = $isIntMetric ? (int) round($value) : round($value, 2);
    }

    return $delta;
}

/**
 * @param array<string, int|float> $delta
 * @return array<int, array{metric: string, expected: int|float, actual: int|float, pass: bool}>
 */
function validateDelta(array $delta): array
{
    $expected = expectedDelta();
    $assertions = [];

    foreach ($expected as $metric => $expectedValue) {
        $actual = $delta[$metric] ?? 0;
        $pass = isFloatMetric($metric)
            ? abs((float) $actual - (float) $expectedValue) < 0.001
            : (int) round((float) $actual) === (int) round((float) $expectedValue);

        $assertions[] = [
            'metric' => $metric,
            'expected' => $expectedValue,
            'actual' => $actual,
            'pass' => $pass,
        ];
    }

    return $assertions;
}

/**
 * @return array<string, int|float>
 */
function expectedDelta(): array
{
    return [
        'total_organs' => 1,
        'total_people' => 2,
        'active_people' => 1,
        'in_progress_people' => 1,
        'people_with_active_cost_plan' => 1,
        'timeline_last_30_days' => 1,
        'expected_reimbursement_current_month' => 450.00,
        'actual_reimbursement_posted_current_month' => 500.00,
        'actual_reimbursement_paid_current_month' => 200.00,
        'reconciliation_deviation_posted_current' => 50.00,
        'reconciliation_deviation_paid_current' => -250.00,
        'total_cdos' => 1,
        'open_cdos' => 1,
        'cdo_total_amount' => 1000.00,
        'cdo_allocated_amount' => 250.00,
        'cdo_available_amount' => 750.00,
    ];
}

function isFloatMetric(string $metric): bool
{
    return in_array($metric, [
        'expected_reimbursement_current_month',
        'actual_reimbursement_posted_current_month',
        'actual_reimbursement_paid_current_month',
        'reconciliation_deviation_posted_current',
        'reconciliation_deviation_paid_current',
        'cdo_total_amount',
        'cdo_allocated_amount',
        'cdo_available_amount',
    ], true);
}

/**
 * @param array<string, int|float> $summary
 * @return array<string, int|float>
 */
function selectSummaryKeys(array $summary): array
{
    $keys = array_keys(expectedDelta());
    $selected = [];
    foreach ($keys as $key) {
        $selected[$key] = $summary[$key] ?? 0;
    }

    return $selected;
}

/**
 * @param array<string, mixed> $payload
 */
function output(string $format, array $payload): void
{
    if ($format === 'json') {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            fail('falha ao serializar json de saida.');
        }

        fwrite(STDOUT, $json . PHP_EOL);
        return;
    }

    printTable($payload);
}

/**
 * @param array<string, mixed> $payload
 */
function printTable(array $payload): void
{
    fwrite(STDOUT, '[qa-regression] status: ' . (string) ($payload['status'] ?? 'unknown') . PHP_EOL);
    fwrite(STDOUT, '[qa-regression] fixture: ' . (string) ($payload['fixture_file'] ?? '') . PHP_EOL);

    $delta = $payload['delta'] ?? [];
    if (!is_array($delta)) {
        $delta = [];
    }

    fwrite(STDOUT, "[qa-regression] delta validado:\n");
    foreach ($delta as $metric => $value) {
        fwrite(STDOUT, sprintf(" - %s: %s\n", (string) $metric, formatNumber($value)));
    }

    $assertions = $payload['assertions'] ?? [];
    if (!is_array($assertions) || $assertions === []) {
        return;
    }

    fwrite(STDOUT, "[qa-regression] assertions:\n");
    foreach ($assertions as $assertion) {
        if (!is_array($assertion)) {
            continue;
        }

        $ok = (bool) ($assertion['pass'] ?? false);
        fwrite(
            STDOUT,
            sprintf(
                " - [%s] %s expected=%s actual=%s\n",
                $ok ? 'ok' : 'fail',
                (string) ($assertion['metric'] ?? ''),
                formatNumber($assertion['expected'] ?? 0),
                formatNumber($assertion['actual'] ?? 0)
            )
        );
    }
}

function formatNumber(mixed $value): string
{
    if (is_float($value)) {
        return number_format($value, 2, '.', '');
    }

    if (is_int($value)) {
        return (string) $value;
    }

    if (is_numeric((string) $value)) {
        $text = (string) $value;
        return str_contains($text, '.') ? number_format((float) $text, 2, '.', '') : (string) (int) $text;
    }

    return (string) $value;
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/qa-regression.php [options]

Options:
  --fixture-file <path>    Caminho da fixture SQL (default: tests/fixtures/qa_regression_dataset.sql)
  --output <table|json>    Formato de saida (default: table)
  --cleanup-only           Apenas remove dados da fixture QA
  --cleanup-after          Remove dados da fixture apos validar
  --help                   Mostra esta ajuda

Exit codes:
  0 = validacao OK
  2 = validacao falhou
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[qa-regression][error] ' . $message . PHP_EOL);
    exit(1);
}
