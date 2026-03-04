#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

[$pdo, $connectionError] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, 'ERR:' . ($connectionError ?? 'database connection failed') . PHP_EOL);
    exit(1);
}

$apply = false;
$supplierFilter = 0;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
        continue;
    }
    if (str_starts_with($arg, '--supplier=')) {
        $supplierFilter = (int) substr($arg, strlen('--supplier='));
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage:\n";
        echo "  php scripts/fix-consignment-dependent-product-id-consistency.php [--supplier=ID] [--apply]\n";
        echo "\n";
        echo "Defaults to dry-run. Use --apply to persist changes.\n";
        exit(0);
    }
}

/**
 * Write rows to CSV.
 *
 * @param string[] $header
 * @param array<int, array<string, mixed>> $rows
 */
function writeCsv(string $filePath, array $header, array $rows): void
{
    $fh = fopen($filePath, 'w');
    if ($fh === false) {
        throw new RuntimeException('Unable to open CSV for writing: ' . $filePath);
    }
    fputcsv($fh, $header, ',', '"', '\\');
    foreach ($rows as $row) {
        $line = [];
        foreach ($header as $key) {
            $line[] = $row[$key] ?? null;
        }
        fputcsv($fh, $line, ',', '"', '\\');
    }
    fclose($fh);
}

$supplierSql = '';
$params = [];
if ($supplierFilter > 0) {
    $supplierSql = ' AND cs.supplier_pessoa_id = :supplier_filter';
    $params[':supplier_filter'] = $supplierFilter;
}

$payoutSql = "SELECT
                cpi.id,
                cpi.payout_id,
                cpi.consignment_sale_id,
                cpi.product_id AS payout_product_id,
                cpi.order_id,
                cpi.order_item_id,
                cpi.amount,
                cpi.percent_applied,
                cpi.created_at,
                cs.supplier_pessoa_id,
                cs.product_id AS sale_product_id,
                cs.sale_status,
                cs.payout_status
              FROM consignment_payout_items cpi
              INNER JOIN consignment_sales cs ON cs.id = cpi.consignment_sale_id
              WHERE cpi.product_id <> cs.product_id
              {$supplierSql}
              ORDER BY cpi.id ASC";
$payoutStmt = $pdo->prepare($payoutSql);
$payoutStmt->execute($params);
$payoutRows = $payoutStmt->fetchAll(PDO::FETCH_ASSOC);

$cupomSql = "SELECT
               ccm.id,
               ccm.voucher_account_id,
               ccm.vendor_pessoa_id,
               ccm.order_id,
               ccm.order_item_id,
               ccm.product_id AS cupom_product_id,
               ccm.sku,
               ccm.product_name,
               ccm.type,
               ccm.event_type,
               ccm.event_id,
               ccm.event_label,
               ccm.event_at,
               ccm.payout_id,
               ccm.credit_amount,
               ccm.created_at,
               cs.id AS sale_id,
               cs.product_id AS sale_product_id,
               cs.supplier_pessoa_id,
               cs.sale_status
             FROM cupons_creditos_movimentos ccm
             INNER JOIN consignment_sales cs
                     ON cs.order_id = ccm.order_id
                    AND cs.order_item_id = ccm.order_item_id
             WHERE ccm.product_id <> cs.product_id
               AND (ccm.vendor_pessoa_id IS NULL OR ccm.vendor_pessoa_id = cs.supplier_pessoa_id)
               {$supplierSql}
             ORDER BY ccm.id ASC";
$cupomStmt = $pdo->prepare($cupomSql);
$cupomStmt->execute($params);
$cupomRows = $cupomStmt->fetchAll(PDO::FETCH_ASSOC);

$payoutCount = count($payoutRows);
$cupomCount = count($cupomRows);

echo "MODE=" . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
if ($supplierFilter > 0) {
    echo "SUPPLIER_FILTER={$supplierFilter}" . PHP_EOL;
}
echo "PAYOUT_MISMATCH_COUNT={$payoutCount}" . PHP_EOL;
echo "CUPOM_MISMATCH_COUNT={$cupomCount}" . PHP_EOL;

if ($payoutCount === 0 && $cupomCount === 0) {
    echo "STATUS=NO_CHANGES" . PHP_EOL;
    exit(0);
}

$payoutBySupplier = [];
foreach ($payoutRows as $row) {
    $sid = (int) ($row['supplier_pessoa_id'] ?? 0);
    $payoutBySupplier[$sid] = ($payoutBySupplier[$sid] ?? 0) + 1;
}
$cupomBySupplier = [];
foreach ($cupomRows as $row) {
    $sid = (int) ($row['supplier_pessoa_id'] ?? 0);
    $cupomBySupplier[$sid] = ($cupomBySupplier[$sid] ?? 0) + 1;
}

echo "SUPPLIER_BREAKDOWN=" . count(array_unique(array_merge(array_keys($payoutBySupplier), array_keys($cupomBySupplier)))) . PHP_EOL;
foreach (array_keys($payoutBySupplier + $cupomBySupplier) as $sid) {
    echo "  {$sid}|payout=" . ($payoutBySupplier[$sid] ?? 0) . "|cupom=" . ($cupomBySupplier[$sid] ?? 0) . PHP_EOL;
}

if (!$apply) {
    echo "STATUS=DRY_RUN_ONLY" . PHP_EOL;
    exit(0);
}

$reportDir = __DIR__ . '/../_ignore/reports';
if (!is_dir($reportDir) && !mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
    throw new RuntimeException('Unable to create report directory: ' . $reportDir);
}
$stamp = date('Ymd-His');
$payoutBackupFile = $reportDir . '/fix-consistency-payout-before-' . $stamp . '.csv';
$cupomBackupFile = $reportDir . '/fix-consistency-cupom-before-' . $stamp . '.csv';

writeCsv(
    $payoutBackupFile,
    [
        'id',
        'payout_id',
        'consignment_sale_id',
        'payout_product_id',
        'sale_product_id',
        'supplier_pessoa_id',
        'sale_status',
        'payout_status',
        'order_id',
        'order_item_id',
        'amount',
        'percent_applied',
        'created_at',
    ],
    $payoutRows
);

writeCsv(
    $cupomBackupFile,
    [
        'id',
        'voucher_account_id',
        'vendor_pessoa_id',
        'sale_id',
        'supplier_pessoa_id',
        'sale_status',
        'cupom_product_id',
        'sale_product_id',
        'order_id',
        'order_item_id',
        'sku',
        'product_name',
        'type',
        'event_type',
        'event_id',
        'event_label',
        'event_at',
        'payout_id',
        'credit_amount',
        'created_at',
    ],
    $cupomRows
);

$updatedPayout = 0;
$updatedCupom = 0;

$pdo->beginTransaction();
try {
    $updatePayoutSql = "UPDATE consignment_payout_items cpi
                        INNER JOIN consignment_sales cs ON cs.id = cpi.consignment_sale_id
                        SET cpi.product_id = cs.product_id
                        WHERE cpi.product_id <> cs.product_id
                        {$supplierSql}";
    $updatePayoutStmt = $pdo->prepare($updatePayoutSql);
    $updatePayoutStmt->execute($params);
    $updatedPayout = $updatePayoutStmt->rowCount();

    $updateCupomSql = "UPDATE cupons_creditos_movimentos ccm
                       INNER JOIN consignment_sales cs
                               ON cs.order_id = ccm.order_id
                              AND cs.order_item_id = ccm.order_item_id
                       SET ccm.product_id = cs.product_id
                       WHERE ccm.product_id <> cs.product_id
                         AND (ccm.vendor_pessoa_id IS NULL OR ccm.vendor_pessoa_id = cs.supplier_pessoa_id)
                         {$supplierSql}";
    $updateCupomStmt = $pdo->prepare($updateCupomSql);
    $updateCupomStmt->execute($params);
    $updatedCupom = $updateCupomStmt->rowCount();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERR: rollback due to exception: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$verifyPayoutSql = "SELECT COUNT(*)
                    FROM consignment_payout_items cpi
                    INNER JOIN consignment_sales cs ON cs.id = cpi.consignment_sale_id
                    WHERE cpi.product_id <> cs.product_id
                    {$supplierSql}";
$verifyPayoutStmt = $pdo->prepare($verifyPayoutSql);
$verifyPayoutStmt->execute($params);
$remainingPayout = (int) $verifyPayoutStmt->fetchColumn();

$verifyCupomSql = "SELECT COUNT(*)
                   FROM cupons_creditos_movimentos ccm
                   INNER JOIN consignment_sales cs
                           ON cs.order_id = ccm.order_id
                          AND cs.order_item_id = ccm.order_item_id
                   WHERE ccm.product_id <> cs.product_id
                     AND (ccm.vendor_pessoa_id IS NULL OR ccm.vendor_pessoa_id = cs.supplier_pessoa_id)
                     {$supplierSql}";
$verifyCupomStmt = $pdo->prepare($verifyCupomSql);
$verifyCupomStmt->execute($params);
$remainingCupom = (int) $verifyCupomStmt->fetchColumn();

echo "UPDATED_PAYOUT_ITEMS={$updatedPayout}" . PHP_EOL;
echo "UPDATED_CUPOM_MOV={$updatedCupom}" . PHP_EOL;
echo "REMAINING_PAYOUT_MISMATCH={$remainingPayout}" . PHP_EOL;
echo "REMAINING_CUPOM_MISMATCH={$remainingCupom}" . PHP_EOL;
echo "BACKUP_PAYOUT={$payoutBackupFile}" . PHP_EOL;
echo "BACKUP_CUPOM={$cupomBackupFile}" . PHP_EOL;
