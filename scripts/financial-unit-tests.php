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

    $dashboardService = new DashboardService(
        new DashboardRepository($app->db()),
        $app->config()
    );

    $budgetService = new BudgetService(
        new BudgetRepository($app->db()),
        $app->audit(),
        $app->events()
    );

    $assertions = runAssertions($dashboardService, $budgetService);
    $failed = array_values(array_filter($assertions, static fn (array $item): bool => (bool) ($item['pass'] ?? false) === false));

    $payload = [
        'status' => $failed === [] ? 'ok' : 'failed',
        'totals' => [
            'assertions_total' => count($assertions),
            'assertions_failed' => count($failed),
        ],
        'assertions' => $assertions,
    ];

    output((string) $options['output'], $payload);
    exit($failed === [] ? 0 : 2);
}

/**
 * @param array<int, string> $argv
 * @return array{output: string, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'output' => 'table',
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--output':
                $value = strtolower(readOptionValue($argv, $i, '--output'));
                if (!in_array($value, ['table', 'json'], true)) {
                    fail('--output deve ser table ou json.');
                }
                $options['output'] = $value;
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
 * @return array<int, array{test: string, expected: mixed, actual: mixed, pass: bool}>
 */
function runAssertions(DashboardService $dashboardService, BudgetService $budgetService): array
{
    $assertions = [];

    $normalizedSummary = invokePrivate($dashboardService, 'normalizeSummary', [[
        'total_people' => 10,
        'active_people' => 12,
        'total_organs' => 3,
        'people_with_documents' => 15,
        'people_with_active_cost_plan' => 8,
        'timeline_last_30_days' => 4,
        'audit_last_30_days' => 2,
        'expected_reimbursement_current_month' => 450,
        'actual_reimbursement_posted_current_month' => 500,
        'actual_reimbursement_paid_current_month' => 200,
        'total_cdos' => 2,
        'open_cdos' => 5,
        'cdo_total_amount' => 1000,
        'cdo_allocated_amount' => 1250,
    ]]);

    if (!is_array($normalizedSummary)) {
        fail('normalizeSummary retornou payload invalido para testes unitarios.');
    }

    $assertions[] = assertExact('dashboard.active_people_clamped', 10, (int) ($normalizedSummary['active_people'] ?? -1));
    $assertions[] = assertExact('dashboard.in_progress_people', 0, (int) ($normalizedSummary['in_progress_people'] ?? -1));
    $assertions[] = assertExact('dashboard.people_without_documents', 0, (int) ($normalizedSummary['people_without_documents'] ?? -1));
    $assertions[] = assertExact('dashboard.people_without_cost_plan', 2, (int) ($normalizedSummary['people_without_cost_plan'] ?? -1));
    $assertions[] = assertFloat('dashboard.documents_coverage_percent', 100.0, (float) ($normalizedSummary['documents_coverage_percent'] ?? -1), 0.001);
    $assertions[] = assertFloat('dashboard.cost_plan_coverage_percent', 80.0, (float) ($normalizedSummary['cost_plan_coverage_percent'] ?? -1), 0.001);
    $assertions[] = assertFloat('dashboard.reconciliation_deviation_posted_current', 50.0, (float) ($normalizedSummary['reconciliation_deviation_posted_current'] ?? -1), 0.001);
    $assertions[] = assertFloat('dashboard.reconciliation_deviation_paid_current', -250.0, (float) ($normalizedSummary['reconciliation_deviation_paid_current'] ?? -1), 0.001);
    $assertions[] = assertExact('dashboard.open_cdos_clamped', 2, (int) ($normalizedSummary['open_cdos'] ?? -1));
    $assertions[] = assertFloat('dashboard.cdo_available_non_negative', 0.0, (float) ($normalizedSummary['cdo_available_amount'] ?? -1), 0.001);

    $recommendationDeviation = invokePrivate($dashboardService, 'recommendation', [[
        'total_people' => 5,
        'people_without_documents' => 2,
        'people_without_cost_plan' => 1,
        'in_progress_people' => 2,
        'reconciliation_deviation_posted_current' => -10.0,
        'total_cdos' => 0,
        'open_cdos' => 0,
        'cdo_available_amount' => 0.0,
    ]]);

    if (!is_array($recommendationDeviation)) {
        fail('recommendation retornou payload invalido para cenario de desvio.');
    }

    $assertions[] = assertExact('dashboard.recommendation_deviation_path', '/people', (string) ($recommendationDeviation['path'] ?? ''));
    $assertions[] = assertExact('dashboard.recommendation_deviation_label', 'Revisar pessoas', (string) ($recommendationDeviation['label'] ?? ''));

    $recommendationEmpty = invokePrivate($dashboardService, 'recommendation', [[
        'total_people' => 0,
        'people_without_documents' => 0,
        'people_without_cost_plan' => 0,
        'in_progress_people' => 0,
        'reconciliation_deviation_posted_current' => 0.0,
        'total_cdos' => 0,
        'open_cdos' => 0,
        'cdo_available_amount' => 0.0,
    ]]);

    if (!is_array($recommendationEmpty)) {
        fail('recommendation retornou payload invalido para cenario vazio.');
    }

    $assertions[] = assertExact('dashboard.recommendation_empty_path', '/people/create', (string) ($recommendationEmpty['path'] ?? ''));

    $assertions[] = assertExact('budget.months_remaining_jan', 12, (int) invokePrivate($budgetService, 'monthsRemainingInYear', ['2026-01-15', 2026]));
    $assertions[] = assertExact('budget.months_remaining_dec', 1, (int) invokePrivate($budgetService, 'monthsRemainingInYear', ['2026-12-01', 2026]));
    $assertions[] = assertExact('budget.months_remaining_year_mismatch', 0, (int) invokePrivate($budgetService, 'monthsRemainingInYear', ['2025-12-01', 2026]));
    $assertions[] = assertExact('budget.months_remaining_invalid_date', 0, (int) invokePrivate($budgetService, 'monthsRemainingInYear', ['data-invalida', 2026]));

    $assertions[] = assertFloat('budget.normalize_variation_min_bound', -95.0, (float) invokePrivate($budgetService, 'normalizeVariation', [-200.0]), 0.001);
    $assertions[] = assertFloat('budget.normalize_variation_max_bound', 500.0, (float) invokePrivate($budgetService, 'normalizeVariation', [600.0]), 0.001);
    $assertions[] = assertFloat('budget.normalize_variation_rounding', 10.13, (float) invokePrivate($budgetService, 'normalizeVariation', [10.126]), 0.001);

    $assertions[] = assertExact('budget.risk_level_high', 'alto', (string) invokePrivate($budgetService, 'riskLevel', [1000.0, -0.01]));
    $assertions[] = assertExact('budget.risk_level_medium_threshold', 'medio', (string) invokePrivate($budgetService, 'riskLevel', [1000.0, 100.0]));
    $assertions[] = assertExact('budget.risk_level_low', 'baixo', (string) invokePrivate($budgetService, 'riskLevel', [1000.0, 101.0]));

    $assertions[] = assertFloat('budget.parse_money_ptbr', 1234.56, (float) invokePrivate($budgetService, 'parseMoneyNullable', ['1.234,56']), 0.001);
    $assertions[] = assertFloat('budget.parse_money_decimal_comma', 10.5, (float) invokePrivate($budgetService, 'parseMoneyNullable', ['10,5']), 0.001);
    $assertions[] = assertExact('budget.parse_money_invalid', null, invokePrivate($budgetService, 'parseMoneyNullable', ['abc']));

    return $assertions;
}

/**
 * @param array<int, mixed> $args
 */
function invokePrivate(object $object, string $method, array $args): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

/**
 * @return array{test: string, expected: mixed, actual: mixed, pass: bool}
 */
function assertExact(string $name, mixed $expected, mixed $actual): array
{
    return [
        'test' => $name,
        'expected' => $expected,
        'actual' => $actual,
        'pass' => $expected === $actual,
    ];
}

/**
 * @return array{test: string, expected: mixed, actual: mixed, pass: bool}
 */
function assertFloat(string $name, float $expected, float $actual, float $epsilon): array
{
    return [
        'test' => $name,
        'expected' => round($expected, 4),
        'actual' => round($actual, 4),
        'pass' => abs($expected - $actual) <= $epsilon,
    ];
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
    fwrite(STDOUT, '[financial-unit-tests] status: ' . (string) ($payload['status'] ?? 'unknown') . PHP_EOL);

    $totals = $payload['totals'] ?? [];
    if (is_array($totals)) {
        fwrite(
            STDOUT,
            sprintf(
                "[financial-unit-tests] assertions: total=%d failed=%d\n",
                (int) ($totals['assertions_total'] ?? 0),
                (int) ($totals['assertions_failed'] ?? 0)
            )
        );
    }

    $assertions = $payload['assertions'] ?? [];
    if (!is_array($assertions)) {
        return;
    }

    fwrite(STDOUT, "[financial-unit-tests] details:\n");
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

    if (is_string($value)) {
        return $value;
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return '[unserializable]';
    }

    return $json;
}

function printUsage(): void
{
    $usage = <<<'TXT'
Usage: ./scripts/financial-unit-tests.php [options]

Options:
  --output <table|json>    Formato de saida (default: table)
  --help                   Mostra esta ajuda

Exit codes:
  0 = validacao OK
  2 = validacao falhou
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[financial-unit-tests][error] ' . $message . PHP_EOL);
    exit(1);
}
