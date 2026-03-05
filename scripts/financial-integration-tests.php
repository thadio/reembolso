#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\BudgetService;
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
    $year = (int) date('Y');
    $scenarioPrefix = 'qa_it_7_3_';
    $testUserId = resolveTestUserId($db);

    cleanupQaScenarios($db, $scenarioPrefix);
    cleanupFixtureData($db);

    $dashboardBefore = dashboardSummary($app);
    $budgetBefore = budgetSummary($app, $year);

    applyFixtureFile($db, $fixturePath);

    $dashboardAfter = dashboardSummary($app);
    $budgetAfter = budgetSummary($app, $year);

    $dashboardDelta = buildDelta($dashboardBefore, $dashboardAfter, dashboardIntegerMetrics());
    $budgetDelta = buildDelta($budgetBefore, $budgetAfter, budgetIntegerMetrics());

    $budgetService = new BudgetService(new BudgetRepository($db), $app->audit(), $app->events());
    $simulationName = $scenarioPrefix . date('YmdHis') . '_' . (string) random_int(100, 999);

    $simulation = $budgetService->simulate(
        year: $year,
        input: [
            'organ_id' => 990001,
            'modality' => 'geral',
            'movement_type' => 'entrada',
            'entry_date' => sprintf('%04d-10-15', $year),
            'quantity' => 2,
            'avg_monthly_cost' => '1500.00',
            'scenario_name' => $simulationName,
            'notes' => 'qa integration phase 7.3',
        ],
        userId: $testUserId,
        ip: '127.0.0.1',
        userAgent: 'financial-integration-tests'
    );

    $simulationData = is_array($simulation['simulation'] ?? null) ? $simulation['simulation'] : [];
    $scenarioId = findScenarioIdByName($db, $simulationName);
    $scenarioItemsCount = $scenarioId > 0 ? countScenarioItems($db, $scenarioId) : 0;

    $assertions = [];

    foreach (expectedDashboardDelta() as $metric => $expectedValue) {
        $actual = $dashboardDelta[$metric] ?? 0;
        $assertions[] = buildAssertion(
            'dashboard.delta.' . $metric,
            $expectedValue,
            $actual,
            isFloatMetric($metric)
        );
    }

    foreach (expectedBudgetDelta() as $metric => $expectedValue) {
        $actual = $budgetDelta[$metric] ?? 0;
        $assertions[] = buildAssertion(
            'budget.delta.' . $metric,
            $expectedValue,
            $actual,
            isFloatMetric($metric)
        );
    }

    $assertions[] = buildAssertion('simulation.ok', true, (bool) ($simulation['ok'] ?? false), false);
    $assertions[] = buildAssertion('simulation.months_remaining', 3, (int) ($simulationData['months_remaining'] ?? 0), false);
    $assertions[] = buildAssertion('simulation.cost_current_year_per_person', 4500.00, (float) ($simulationData['cost_current_year_per_person'] ?? 0.0), true);
    $assertions[] = buildAssertion('simulation.cost_current_year', 9000.00, (float) ($simulationData['cost_current_year'] ?? 0.0), true);
    $assertions[] = buildAssertion('simulation.cost_next_year', 39900.00, (float) ($simulationData['cost_next_year'] ?? 0.0), true);

    $matrix = is_array($simulationData['scenario_matrix'] ?? null) ? $simulationData['scenario_matrix'] : [];
    $assertions[] = buildAssertion('simulation.matrix_items', 3, count($matrix), false);
    $assertions[] = buildAssertion('simulation.scenario_persisted', true, $scenarioId > 0, false);
    $assertions[] = buildAssertion('simulation.scenario_items_persisted', 3, $scenarioItemsCount, false);

    $failed = array_values(array_filter($assertions, static fn (array $item): bool => (bool) ($item['pass'] ?? false) === false));

    $payload = [
        'status' => $failed === [] ? 'ok' : 'failed',
        'fixture_file' => $fixturePath,
        'cleanup_after' => (bool) $options['cleanup_after'],
        'year' => $year,
        'test_user_id' => $testUserId,
        'dashboard_delta' => selectKeys($dashboardDelta, array_keys(expectedDashboardDelta())),
        'budget_delta' => selectKeys($budgetDelta, array_keys(expectedBudgetDelta())),
        'simulation' => [
            'ok' => (bool) ($simulation['ok'] ?? false),
            'scenario_name' => $simulationName,
            'scenario_id' => $scenarioId,
            'scenario_items_count' => $scenarioItemsCount,
            'months_remaining' => (int) ($simulationData['months_remaining'] ?? 0),
            'cost_current_year_per_person' => round((float) ($simulationData['cost_current_year_per_person'] ?? 0), 2),
            'cost_current_year' => round((float) ($simulationData['cost_current_year'] ?? 0), 2),
            'cost_next_year' => round((float) ($simulationData['cost_next_year'] ?? 0), 2),
        ],
        'totals' => [
            'assertions_total' => count($assertions),
            'assertions_failed' => count($failed),
        ],
        'assertions' => $assertions,
    ];

    if ((bool) $options['cleanup_after'] === true) {
        cleanupQaScenarios($db, $scenarioPrefix);
        cleanupFixtureData($db);
    }

    output((string) $options['output'], $payload);
    exit($failed === [] ? 0 : 2);
}

/**
 * @param array<int, string> $argv
 * @return array{fixture_file: string, output: string, cleanup_after: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'fixture_file' => '',
        'output' => 'table',
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

function resolveTestUserId(PDO $db): int
{
    $stmt = $db->query(
        'SELECT id
         FROM users
         WHERE deleted_at IS NULL
           AND is_active = 1
         ORDER BY id ASC
         LIMIT 1'
    );
    $row = $stmt->fetch();
    $userId = (int) ($row['id'] ?? 0);

    if ($userId <= 0) {
        fail('nenhum usuario ativo encontrado para executar teste de simulacao.');
    }

    return $userId;
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
        deleteFixtureByIds($db, 'timeline_events', $ids['timeline_events']);
        deleteFixtureByIds($db, 'reimbursement_entries', $ids['reimbursement_entries']);
        deleteFixtureByIds($db, 'cdo_people', $ids['cdo_people']);
        deleteFixtureByIds($db, 'cdos', $ids['cdos']);
        deleteFixtureByIds($db, 'cost_plan_items', $ids['cost_plan_items']);
        deleteFixtureByIds($db, 'cost_plans', $ids['cost_plans']);
        deleteFixtureByIds($db, 'people', $ids['people']);
        deleteFixtureByIds($db, 'organs', $ids['organs']);
        $db->commit();
    } catch (Throwable $throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        fail('falha no cleanup da fixture: ' . $throwable->getMessage());
    }
}

/**
 * @param array<int, int> $ids
 */
function deleteFixtureByIds(PDO $db, string $table, array $ids): void
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
    } catch (Throwable $throwable) {
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

    return normalizeNumericMap(is_array($summary) ? $summary : []);
}

/**
 * @return array<string, int|float>
 */
function budgetSummary(App $app, int $year): array
{
    $service = new BudgetService(new BudgetRepository($app->db()), $app->audit(), $app->events());
    $dashboard = $service->dashboard($year);
    $summary = $dashboard['summary'] ?? [];

    return normalizeNumericMap(is_array($summary) ? $summary : []);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, int|float>
 */
function normalizeNumericMap(array $input): array
{
    $normalized = [];
    foreach ($input as $key => $value) {
        if (is_int($value) || is_float($value)) {
            $normalized[(string) $key] = $value;
            continue;
        }

        if (is_numeric((string) $value)) {
            $text = (string) $value;
            if (str_contains($text, '.')) {
                $normalized[(string) $key] = (float) $text;
            } else {
                $normalized[(string) $key] = (int) $text;
            }
        }
    }

    return $normalized;
}

/**
 * @param array<string, int|float> $before
 * @param array<string, int|float> $after
 * @param array<int, string> $integerMetrics
 * @return array<string, int|float>
 */
function buildDelta(array $before, array $after, array $integerMetrics): array
{
    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    $delta = [];

    foreach ($keys as $key) {
        $afterValue = (float) ($after[$key] ?? 0.0);
        $beforeValue = (float) ($before[$key] ?? 0.0);
        $value = $afterValue - $beforeValue;

        $delta[$key] = in_array($key, $integerMetrics, true)
            ? (int) round($value)
            : round($value, 2);
    }

    return $delta;
}

/** @return array<int, string> */
function dashboardIntegerMetrics(): array
{
    return [
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
    ];
}

/** @return array<int, string> */
function budgetIntegerMetrics(): array
{
    return ['year'];
}

/**
 * @return array<string, int|float>
 */
function expectedDashboardDelta(): array
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

/**
 * @return array<string, int|float>
 */
function expectedBudgetDelta(): array
{
    return [
        'executed_amount' => 200.00,
        'committed_amount' => 300.00,
        'paid_reimbursements_amount' => 200.00,
        'committed_reimbursements_amount' => 300.00,
        'projected_monthly_base' => 200.00,
    ];
}

function isFloatMetric(string $metric): bool
{
    $floatMetrics = array_merge(
        [
            'expected_reimbursement_current_month',
            'actual_reimbursement_posted_current_month',
            'actual_reimbursement_paid_current_month',
            'reconciliation_deviation_posted_current',
            'reconciliation_deviation_paid_current',
            'cdo_total_amount',
            'cdo_allocated_amount',
            'cdo_available_amount',
        ],
        array_keys(expectedBudgetDelta())
    );

    return in_array($metric, $floatMetrics, true);
}

/**
 * @return array{test: string, expected: int|float|bool, actual: int|float|bool, pass: bool}
 */
function buildAssertion(string $test, int|float|bool $expected, int|float|bool $actual, bool $isFloat): array
{
    $pass = $isFloat
        ? abs((float) $actual - (float) $expected) < 0.001
        : $actual === $expected;

    return [
        'test' => $test,
        'expected' => $expected,
        'actual' => $actual,
        'pass' => $pass,
    ];
}

/**
 * @param array<string, int|float> $source
 * @param array<int, string> $keys
 * @return array<string, int|float>
 */
function selectKeys(array $source, array $keys): array
{
    $selected = [];
    foreach ($keys as $key) {
        $selected[$key] = $source[$key] ?? 0;
    }

    return $selected;
}

function findScenarioIdByName(PDO $db, string $name): int
{
    $stmt = $db->prepare(
        'SELECT id
         FROM hiring_scenarios
         WHERE scenario_name = :scenario_name
           AND deleted_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['scenario_name' => $name]);

    return (int) ($stmt->fetch()['id'] ?? 0);
}

function countScenarioItems(PDO $db, int $scenarioId): int
{
    if ($scenarioId <= 0) {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total
         FROM hiring_scenario_items
         WHERE hiring_scenario_id = :scenario_id'
    );
    $stmt->execute(['scenario_id' => $scenarioId]);

    return (int) ($stmt->fetch()['total'] ?? 0);
}

function cleanupQaScenarios(PDO $db, string $prefix): void
{
    $stmt = $db->prepare(
        'SELECT id
         FROM hiring_scenarios
         WHERE scenario_name LIKE :prefix
           AND deleted_at IS NULL'
    );
    $stmt->execute(['prefix' => $prefix . '%']);

    $ids = array_map(
        static fn (array $row): int => (int) ($row['id'] ?? 0),
        array_values(array_filter($stmt->fetchAll(), static fn (array $row): bool => (int) ($row['id'] ?? 0) > 0))
    );

    if ($ids === []) {
        return;
    }

    $db->beginTransaction();
    try {
        deleteByIds($db, 'hiring_scenario_items', $ids, 'hiring_scenario_id');
        deleteByIds($db, 'audit_log', $ids, 'entity_id', "entity = 'hiring_scenario'");
        deleteByIds($db, 'hiring_scenarios', $ids, 'id');
        $db->commit();
    } catch (Throwable $throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        fail('falha ao limpar cenarios de integracao: ' . $throwable->getMessage());
    }
}

/**
 * @param array<int, int> $ids
 */
function deleteByIds(PDO $db, string $table, array $ids, string $column = 'id', string $extraWhere = ''): void
{
    if ($ids === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = sprintf('DELETE FROM %s WHERE %s IN (%s)', $table, $column, $placeholders);

    if ($extraWhere !== '') {
        $sql .= ' AND ' . $extraWhere;
    }

    $stmt = $db->prepare($sql);
    foreach (array_values($ids) as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
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
    fwrite(STDOUT, '[financial-integration-tests] status: ' . (string) ($payload['status'] ?? 'unknown') . PHP_EOL);
    fwrite(STDOUT, '[financial-integration-tests] fixture: ' . (string) ($payload['fixture_file'] ?? '') . PHP_EOL);

    $dashboardDelta = $payload['dashboard_delta'] ?? [];
    if (is_array($dashboardDelta)) {
        fwrite(STDOUT, "[financial-integration-tests] dashboard delta:\n");
        foreach ($dashboardDelta as $metric => $value) {
            fwrite(STDOUT, sprintf(" - %s: %s\n", (string) $metric, printableValue($value)));
        }
    }

    $budgetDelta = $payload['budget_delta'] ?? [];
    if (is_array($budgetDelta)) {
        fwrite(STDOUT, "[financial-integration-tests] budget delta:\n");
        foreach ($budgetDelta as $metric => $value) {
            fwrite(STDOUT, sprintf(" - %s: %s\n", (string) $metric, printableValue($value)));
        }
    }

    $simulation = $payload['simulation'] ?? [];
    if (is_array($simulation)) {
        fwrite(
            STDOUT,
            sprintf(
                "[financial-integration-tests] simulation: ok=%s scenario_id=%d items=%d\n",
                ((bool) ($simulation['ok'] ?? false)) ? 'true' : 'false',
                (int) ($simulation['scenario_id'] ?? 0),
                (int) ($simulation['scenario_items_count'] ?? 0)
            )
        );
    }

    $totals = $payload['totals'] ?? [];
    if (is_array($totals)) {
        fwrite(
            STDOUT,
            sprintf(
                "[financial-integration-tests] assertions: total=%d failed=%d\n",
                (int) ($totals['assertions_total'] ?? 0),
                (int) ($totals['assertions_failed'] ?? 0)
            )
        );
    }

    $assertions = $payload['assertions'] ?? [];
    if (!is_array($assertions)) {
        return;
    }

    fwrite(STDOUT, "[financial-integration-tests] assertions:\n");
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
                (string) ($assertion['test'] ?? ''),
                printableValue($assertion['expected'] ?? null),
                printableValue($assertion['actual'] ?? null)
            )
        );
    }
}

function printableValue(mixed $value): string
{
    if (is_float($value)) {
        return number_format($value, 2, '.', '');
    }

    if (is_int($value)) {
        return (string) $value;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    return (string) $value;
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/financial-integration-tests.php [options]

Options:
  --fixture-file <path>    Caminho da fixture SQL (default: tests/fixtures/qa_regression_dataset.sql)
  --output <table|json>    Formato de saida (default: table)
  --cleanup-after          Remove dados de fixture e cenarios QA ao final
  --help                   Mostra esta ajuda

Exit codes:
  0 = validacao OK
  2 = validacao falhou
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[financial-integration-tests][error] ' . $message . PHP_EOL);
    exit(1);
}
