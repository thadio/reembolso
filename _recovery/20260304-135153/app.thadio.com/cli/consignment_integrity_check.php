<?php
/**
 * Consignment Integrity Check — Runs all consistency checks and outputs report.
 *
 * Usage: php cli/consignment_integrity_check.php [--json] [--verbose]
 */

require __DIR__ . '/../bootstrap.php';

use App\Services\ConsignmentIntegrityService;
use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\ConsignmentPayoutRepository;
use App\Repositories\ConsignmentPayoutItemRepository;
use App\Repositories\ConsignmentPeriodLockRepository;
use App\Support\SchemaBootstrapper;

$jsonOutput = in_array('--json', $argv, true);
$verbose = in_array('--verbose', $argv, true);

[$pdo, $err] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, "Erro de conexão: $err\n");
    exit(1);
}

// Ensure consignment tables exist (DDL only)
SchemaBootstrapper::enable();
new ConsignmentProductRegistryRepository($pdo);
new ConsignmentSaleRepository($pdo);
new ConsignmentPayoutRepository($pdo);
new ConsignmentPayoutItemRepository($pdo);
new ConsignmentPeriodLockRepository($pdo);
SchemaBootstrapper::disable();

$service = new ConsignmentIntegrityService($pdo);
$checks = $service->runAllChecks();

if ($jsonOutput) {
    echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$totalIssues = 0;
foreach ($checks as $check) {
    $label = $check['label'] ?? $check['description'] ?? $check['check'] ?? '?';
    $count = (int) ($check['count'] ?? count($check['issues'] ?? []));
    $totalIssues += $count;

    $icon = $count === 0 ? '✅' : '⚠️ ';
    echo "{$icon} {$label} — {$count} item(ns)\n";

    if ($verbose && !empty($check['issues'])) {
        foreach ($check['issues'] as $issue) {
            if (is_array($issue)) {
                $parts = [];
                foreach ($issue as $k => $v) {
                    $parts[] = "{$k}={$v}";
                }
                echo "    " . implode(' | ', $parts) . "\n";
            } else {
                echo "    {$issue}\n";
            }
        }
    }
}

echo "\n";
if ($totalIssues === 0) {
    echo "Resultado: Todas as verificações passaram. ✅\n";
} else {
    echo "Resultado: {$totalIssues} inconsistência(s) encontrada(s). ⚠️\n";
}

exit($totalIssues > 0 ? 1 : 0);
