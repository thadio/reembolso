<?php
/**
 * Consignment Reindex — Reprocesses consignment_status for all products.
 *
 * Derives the correct status based on real state (sales, payouts, writeoffs)
 * and updates both consignment_product_registry and products.consignment_status.
 *
 * Usage: php cli/consignment_reindex.php [--dry-run] [--verbose]
 */

require __DIR__ . '/../bootstrap.php';

use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Support\SchemaBootstrapper;

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);

[$pdo, $err] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, "Erro de conexão: $err\n");
    exit(1);
}

SchemaBootstrapper::enable();
$registry = new ConsignmentProductRegistryRepository($pdo);
$salesRepo = new ConsignmentSaleRepository($pdo);
SchemaBootstrapper::disable();
$prefix = $dryRun ? '[DRY-RUN] ' : '';

$log = function (string $msg) use ($verbose) {
    if ($verbose || PHP_SAPI === 'cli') {
        echo $msg . "\n";
    }
};

// Fetch all consignment products
$stmt = $pdo->query("
    SELECT p.sku, p.status, p.source, p.consignment_status
    FROM products p
    WHERE p.source IN ('consignacao', 'consignacao_quitada')
      AND p.status != 'archived'
");
$products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Build writeoff map
$writeoffMap = [];
try {
    $wStmt = $pdo->query("SELECT product_id, destination FROM produto_baixas WHERE product_id IS NOT NULL");
    foreach ($wStmt->fetchAll(\PDO::FETCH_ASSOC) as $w) {
        $writeoffMap[(int) $w['product_id']] = $w['destination'];
    }
} catch (\Throwable $e) {
    // skip
}

$destToStatus = [
    'devolucao_fornecedor' => 'devolvido',
    'doacao' => 'doado',
    'nao_localizado' => 'descartado',
    'lixo' => 'descartado',
];

$updated = 0;
$unchanged = 0;

foreach ($products as $row) {
    $productId = (int) $row['sku'];
    $pStatus = (string) $row['status'];
    $source = (string) $row['source'];
    $currentCs = $row['consignment_status'];
    $newStatus = null;

    // Reclassified products
    if ($source === 'consignacao_quitada') {
        $newStatus = 'vendido_pago';
    }

    // 1. Check writeoff
    if ($newStatus === null && isset($writeoffMap[$productId])) {
        $dest = $writeoffMap[$productId];
        $newStatus = $destToStatus[$dest] ?? 'descartado';
    }

    // 2. Derive from sales
    if ($newStatus === null) {
        $activeSalesStmt = $pdo->prepare("
            SELECT id, payout_status FROM consignment_sales
            WHERE product_id = ? AND sale_status = 'ativa'
            LIMIT 1
        ");
        $activeSalesStmt->execute([$productId]);
        $activeSale = $activeSalesStmt->fetch(\PDO::FETCH_ASSOC);

        if ($activeSale) {
            $payoutStatus = (string) ($activeSale['payout_status'] ?? 'pendente');
            $newStatus = $payoutStatus === 'pago' ? 'vendido_pago' : 'vendido_pendente';
        } else {
            // No active sale
            $reversedStmt = $pdo->prepare("
                SELECT id FROM consignment_sales
                WHERE product_id = ? AND sale_status = 'revertida'
                LIMIT 1
            ");
            $reversedStmt->execute([$productId]);
            $hasReversed = (bool) $reversedStmt->fetch();

            if ($hasReversed) {
                $newStatus = in_array($pStatus, ['disponivel', 'draft', 'reservado'], true) ? 'em_estoque' : 'descartado';
            } else {
                $newStatus = in_array($pStatus, ['disponivel', 'draft', 'reservado', 'esgotado'], true) ? 'em_estoque' : 'descartado';
            }
        }
    }

    if ($newStatus !== null && $newStatus !== $currentCs) {
        if ($verbose) {
            $log("{$prefix}  Produto #{$productId}: {$currentCs} → {$newStatus}");
        }
        if (!$dryRun) {
            $registry->updateStatusByProduct($productId, $newStatus);
            $pdo->prepare("UPDATE products SET consignment_status = ? WHERE sku = ?")->execute([$newStatus, $productId]);
        }
        $updated++;
    } else {
        $unchanged++;
    }
}

$log("{$prefix}Reindex concluído: {$updated} atualizados, {$unchanged} sem alteração.");
exit(0);
