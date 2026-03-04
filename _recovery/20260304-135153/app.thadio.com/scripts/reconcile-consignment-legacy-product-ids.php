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
        echo "  php scripts/reconcile-consignment-legacy-product-ids.php [--supplier=ID] [--apply]\n";
        echo "\n";
        echo "Defaults to dry-run. Use --apply to persist changes.\n";
        exit(0);
    }
}

/**
 * Build IN clause placeholders and bind map.
 *
 * @param string $prefix
 * @param int[] $values
 * @param array<string, mixed> $params
 */
function buildInClause(string $prefix, array $values, array &$params): string
{
    $placeholders = [];
    foreach (array_values($values) as $i => $value) {
        $key = ':' . $prefix . $i;
        $placeholders[] = $key;
        $params[$key] = (int) $value;
    }
    return implode(',', $placeholders);
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

$params = [];
$supplierSql = '';
if ($supplierFilter > 0) {
    $supplierSql = ' AND cs.supplier_pessoa_id = :supplier_filter';
    $params[':supplier_filter'] = $supplierFilter;
}

$candidateSql = "SELECT
                    cs.id AS sale_id,
                    cs.supplier_pessoa_id,
                    COALESCE(ps.full_name, '') AS supplier_name,
                    cs.product_id AS legacy_product_id,
                    oi.product_sku AS real_product_sku,
                    cs.order_id,
                    cs.order_item_id,
                    cs.sale_status,
                    cs.payout_status,
                    cs.payout_id,
                    cs.credit_amount,
                    cs.sold_at
                 FROM consignment_sales cs
                 INNER JOIN order_items oi ON oi.id = cs.order_item_id
                 LEFT JOIN pessoas ps ON ps.id = cs.supplier_pessoa_id
                 WHERE oi.product_sku IS NOT NULL
                   AND oi.product_sku > 0
                   AND cs.product_id <> oi.product_sku
                   AND NOT EXISTS (
                       SELECT 1
                       FROM consignment_product_registry r
                       WHERE r.product_id = cs.product_id
                         AND (
                             r.supplier_pessoa_id = cs.supplier_pessoa_id
                             OR r.consignment_supplier_original_id = cs.supplier_pessoa_id
                         )
                   )
                   AND EXISTS (
                       SELECT 1
                       FROM consignment_product_registry r2
                       WHERE r2.product_id = oi.product_sku
                         AND (
                             r2.supplier_pessoa_id = cs.supplier_pessoa_id
                             OR r2.consignment_supplier_original_id = cs.supplier_pessoa_id
                         )
                   )
                   {$supplierSql}
                 ORDER BY cs.supplier_pessoa_id ASC, cs.id ASC";

$candidateStmt = $pdo->prepare($candidateSql);
$candidateStmt->execute($params);
$candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);

$candidateCount = count($candidates);
if ($candidateCount === 0) {
    echo "MODE=" . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
    echo "CANDIDATE_SALES=0" . PHP_EOL;
    echo "STATUS=NO_CHANGES" . PHP_EOL;
    exit(0);
}

$saleIds = array_values(array_unique(array_map(
    static fn(array $row): int => (int) ($row['sale_id'] ?? 0),
    $candidates
)));
$saleIds = array_values(array_filter($saleIds));

$legacyProductIds = [];
$realProductSkus = [];
$newSkuBySaleId = [];
foreach ($candidates as $row) {
    $legacyProductIds[] = (int) ($row['legacy_product_id'] ?? 0);
    $realProductSkus[] = (int) ($row['real_product_sku'] ?? 0);
    $saleId = (int) ($row['sale_id'] ?? 0);
    if ($saleId > 0) {
        $newSkuBySaleId[$saleId] = (int) ($row['real_product_sku'] ?? 0);
    }
}
$legacyProductIds = array_values(array_unique(array_filter($legacyProductIds)));
$realProductSkus = array_values(array_unique(array_filter($realProductSkus)));

$supplierStats = [];
foreach ($candidates as $row) {
    $sid = (int) ($row['supplier_pessoa_id'] ?? 0);
    if (!isset($supplierStats[$sid])) {
        $supplierStats[$sid] = [
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
            'sales' => 0,
            'legacy_products' => [],
            'real_skus' => [],
        ];
    }
    $supplierStats[$sid]['sales']++;
    $legacy = (int) ($row['legacy_product_id'] ?? 0);
    $real = (int) ($row['real_product_sku'] ?? 0);
    if ($legacy > 0) {
        $supplierStats[$sid]['legacy_products'][$legacy] = true;
    }
    if ($real > 0) {
        $supplierStats[$sid]['real_skus'][$real] = true;
    }
}

$saleInParams = [];
$saleIn = buildInClause('sid_', $saleIds, $saleInParams);

$payoutRowsAll = [];
$payoutRows = [];
$payoutCount = 0;
if ($saleIn !== '') {
    $payoutSql = "SELECT
                    cpi.id,
                    cpi.payout_id,
                    cpi.consignment_sale_id,
                    cs.id AS sale_id,
                    cpi.product_id,
                    cpi.order_id,
                    cpi.order_item_id,
                    cpi.amount,
                    cpi.created_at
                  FROM consignment_payout_items cpi
                  INNER JOIN consignment_sales cs ON cs.id = cpi.consignment_sale_id
                  WHERE cs.id IN ({$saleIn})
                  ORDER BY cpi.id ASC";
    $payoutStmt = $pdo->prepare($payoutSql);
    $payoutStmt->execute($saleInParams);
    $payoutRowsAll = $payoutStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($payoutRowsAll as $row) {
        $saleId = (int) ($row['sale_id'] ?? 0);
        $targetSku = (int) ($newSkuBySaleId[$saleId] ?? 0);
        if ($targetSku > 0 && (int) ($row['product_id'] ?? 0) !== $targetSku) {
            $payoutRows[] = $row;
        }
    }
    $payoutCount = count($payoutRows);
}

$cupomRowsAll = [];
$cupomRows = [];
$cupomCount = 0;
if ($saleIn !== '') {
    $cupomSql = "SELECT
                    ccm.id,
                    ccm.voucher_account_id,
                    ccm.vendor_pessoa_id,
                    ccm.order_id,
                    ccm.order_item_id,
                    ccm.product_id,
                    ccm.sku,
                    ccm.event_type,
                    ccm.event_id,
                    ccm.payout_id,
                    ccm.credit_amount,
                    ccm.event_at,
                    ccm.created_at,
                    cs.id AS sale_id
                 FROM cupons_creditos_movimentos ccm
                 INNER JOIN consignment_sales cs
                         ON cs.order_id = ccm.order_id
                        AND cs.order_item_id = ccm.order_item_id
                 WHERE cs.id IN ({$saleIn})
                   AND (ccm.vendor_pessoa_id IS NULL OR ccm.vendor_pessoa_id = cs.supplier_pessoa_id)
                 ORDER BY ccm.id ASC";
    $cupomStmt = $pdo->prepare($cupomSql);
    $cupomStmt->execute($saleInParams);
    $cupomRowsAll = $cupomStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cupomRowsAll as $row) {
        $saleId = (int) ($row['sale_id'] ?? 0);
        $targetSku = (int) ($newSkuBySaleId[$saleId] ?? 0);
        if ($targetSku > 0 && (int) ($row['product_id'] ?? 0) !== $targetSku) {
            $cupomRows[] = $row;
        }
    }
    $cupomCount = count($cupomRows);
}

echo "MODE=" . ($apply ? 'apply' : 'dry-run') . PHP_EOL;
echo "CANDIDATE_SALES={$candidateCount}" . PHP_EOL;
echo "UNIQUE_SALES=" . count($saleIds) . PHP_EOL;
echo "UNIQUE_LEGACY_PRODUCT_IDS=" . count($legacyProductIds) . PHP_EOL;
echo "UNIQUE_REAL_SKUS=" . count($realProductSkus) . PHP_EOL;
echo "PAYOUT_ITEMS_TO_UPDATE={$payoutCount}" . PHP_EOL;
echo "CUPOM_MOV_TO_UPDATE={$cupomCount}" . PHP_EOL;
echo "SUPPLIER_BREAKDOWN=" . count($supplierStats) . PHP_EOL;
foreach ($supplierStats as $sid => $stat) {
    echo "  {$sid}|{$stat['supplier_name']}|sales={$stat['sales']}|legacy_products=" .
        count($stat['legacy_products']) . "|real_skus=" . count($stat['real_skus']) . PHP_EOL;
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

$salesBackupFile = $reportDir . '/reconcile-legacy-product-id-sales-before-' . $stamp . '.csv';
$payoutBackupFile = $reportDir . '/reconcile-legacy-product-id-payout-items-before-' . $stamp . '.csv';
$cupomBackupFile = $reportDir . '/reconcile-legacy-product-id-cupom-mov-before-' . $stamp . '.csv';
$mappingFile = $reportDir . '/reconcile-legacy-product-id-mapping-' . $stamp . '.csv';

writeCsv(
    $mappingFile,
    [
        'sale_id',
        'supplier_pessoa_id',
        'supplier_name',
        'legacy_product_id',
        'real_product_sku',
        'order_id',
        'order_item_id',
        'sale_status',
        'payout_status',
        'payout_id',
        'credit_amount',
        'sold_at',
    ],
    $candidates
);

$salesBackupSql = "SELECT
                     id,
                     supplier_pessoa_id,
                     product_id,
                     order_id,
                     order_item_id,
                     sale_status,
                     payout_status,
                     payout_id,
                     credit_amount,
                     sold_at,
                     updated_at
                   FROM consignment_sales
                   WHERE id IN ({$saleIn})
                   ORDER BY id ASC";
$salesBackupStmt = $pdo->prepare($salesBackupSql);
$salesBackupStmt->execute($saleInParams);
$salesBackupRows = $salesBackupStmt->fetchAll(PDO::FETCH_ASSOC);
writeCsv(
    $salesBackupFile,
    [
        'id',
        'supplier_pessoa_id',
        'product_id',
        'order_id',
        'order_item_id',
        'sale_status',
        'payout_status',
        'payout_id',
        'credit_amount',
        'sold_at',
        'updated_at',
    ],
    $salesBackupRows
);

writeCsv(
    $payoutBackupFile,
    [
        'id',
        'payout_id',
        'consignment_sale_id',
        'sale_id',
        'product_id',
        'order_id',
        'order_item_id',
        'amount',
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
        'order_id',
        'order_item_id',
        'product_id',
        'sku',
        'event_type',
        'event_id',
        'payout_id',
        'credit_amount',
        'event_at',
        'created_at',
        'sale_id',
    ],
    $cupomRows
);

$updatedSales = 0;
$updatedPayoutItems = 0;
$updatedCupomMov = 0;

$pdo->beginTransaction();
try {
    $updateSale = $pdo->prepare(
        "UPDATE consignment_sales
         SET product_id = :new_product_id,
             updated_at = NOW()
         WHERE id = :sale_id"
    );
    foreach ($candidates as $row) {
        $saleId = (int) ($row['sale_id'] ?? 0);
        $realSku = (int) ($row['real_product_sku'] ?? 0);
        if ($saleId <= 0 || $realSku <= 0) {
            continue;
        }
        $updateSale->execute([
            ':sale_id' => $saleId,
            ':new_product_id' => $realSku,
        ]);
        $updatedSales += $updateSale->rowCount();
    }

    if ($saleIn !== '') {
        $updatePayout = $pdo->prepare(
            "UPDATE consignment_payout_items cpi
             INNER JOIN consignment_sales cs ON cs.id = cpi.consignment_sale_id
             SET cpi.product_id = cs.product_id
             WHERE cs.id IN ({$saleIn})
               AND cpi.product_id <> cs.product_id"
        );
        $updatePayout->execute($saleInParams);
        $updatedPayoutItems = $updatePayout->rowCount();

        $updateCupom = $pdo->prepare(
            "UPDATE cupons_creditos_movimentos ccm
             INNER JOIN consignment_sales cs
                     ON cs.order_id = ccm.order_id
                    AND cs.order_item_id = ccm.order_item_id
             SET ccm.product_id = cs.product_id
             WHERE cs.id IN ({$saleIn})
               AND ccm.product_id <> cs.product_id
               AND (ccm.vendor_pessoa_id IS NULL OR ccm.vendor_pessoa_id = cs.supplier_pessoa_id)"
        );
        $updateCupom->execute($saleInParams);
        $updatedCupomMov = $updateCupom->rowCount();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERR: rollback due to exception: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$verifyParams = [];
$verifySupplierSql = '';
if ($supplierFilter > 0) {
    $verifySupplierSql = ' AND cs.supplier_pessoa_id = :supplier_filter';
    $verifyParams[':supplier_filter'] = $supplierFilter;
}
$verifySql = "SELECT COUNT(*)
              FROM consignment_sales cs
              INNER JOIN order_items oi ON oi.id = cs.order_item_id
              WHERE oi.product_sku IS NOT NULL
                AND oi.product_sku > 0
                AND cs.product_id <> oi.product_sku
                AND NOT EXISTS (
                    SELECT 1
                    FROM consignment_product_registry r
                    WHERE r.product_id = cs.product_id
                      AND (
                          r.supplier_pessoa_id = cs.supplier_pessoa_id
                          OR r.consignment_supplier_original_id = cs.supplier_pessoa_id
                      )
                )
                AND EXISTS (
                    SELECT 1
                    FROM consignment_product_registry r2
                    WHERE r2.product_id = oi.product_sku
                      AND (
                          r2.supplier_pessoa_id = cs.supplier_pessoa_id
                          OR r2.consignment_supplier_original_id = cs.supplier_pessoa_id
                      )
                )
                {$verifySupplierSql}";
$verifyStmt = $pdo->prepare($verifySql);
$verifyStmt->execute($verifyParams);
$remaining = (int) $verifyStmt->fetchColumn();

echo "UPDATED_SALES={$updatedSales}" . PHP_EOL;
echo "UPDATED_PAYOUT_ITEMS={$updatedPayoutItems}" . PHP_EOL;
echo "UPDATED_CUPOM_MOV={$updatedCupomMov}" . PHP_EOL;
echo "REMAINING_RECOVERABLE_MISMATCH={$remaining}" . PHP_EOL;
echo "BACKUP_SALES={$salesBackupFile}" . PHP_EOL;
echo "BACKUP_PAYOUT_ITEMS={$payoutBackupFile}" . PHP_EOL;
echo "BACKUP_CUPOM_MOV={$cupomBackupFile}" . PHP_EOL;
echo "BACKUP_MAPPING={$mappingFile}" . PHP_EOL;
