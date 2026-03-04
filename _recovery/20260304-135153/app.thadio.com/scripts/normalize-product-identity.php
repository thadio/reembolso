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
$help = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        $help = true;
    }
}

if ($help) {
    echo "Usage:\n";
    echo "  php scripts/normalize-product-identity.php [--apply]\n";
    echo "\n";
    echo "Default mode is dry-run (no writes).\n";
    echo "Use --apply to persist updates in a single transaction.\n";
    exit(0);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param string[] $headers
 */
function writeCsv(string $filePath, array $headers, array $rows): void
{
    $fh = fopen($filePath, 'w');
    if ($fh === false) {
        throw new RuntimeException('Unable to write CSV: ' . $filePath);
    }
    fputcsv($fh, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = $row[$header] ?? null;
        }
        fputcsv($fh, $line, ',', '"', '\\');
    }
    fclose($fh);
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRows(PDO $pdo, string $sql): array
{
    $stmt = $pdo->query($sql);
    if (!$stmt) {
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param callable(array<string, mixed>):bool $updater
 */
function applyRows(array $rows, callable $updater): int
{
    $affected = 0;
    foreach ($rows as $row) {
        if ($updater($row)) {
            $affected++;
        }
    }
    return $affected;
}

/**
 * @param int[] $ids
 * @param array<string, mixed> $params
 */
function buildInClause(string $prefix, array $ids, array &$params): string
{
    $placeholders = [];
    foreach (array_values($ids) as $i => $id) {
        $key = ':' . $prefix . $i;
        $placeholders[] = $key;
        $params[$key] = (int) $id;
    }
    return implode(',', $placeholders);
}

$salesFixRows = fetchRows(
    $pdo,
    "SELECT
        s.id,
        s.product_id AS old_product_id,
        oi.product_sku AS new_product_id,
        s.order_id,
        s.order_item_id,
        s.supplier_pessoa_id
     FROM consignment_sales s
     INNER JOIN order_items oi ON oi.id = s.order_item_id
     INNER JOIN products pnew ON pnew.sku = oi.product_sku
     WHERE oi.product_sku IS NOT NULL
       AND oi.product_sku > 0
       AND s.product_id <> oi.product_sku
       AND LOWER(TRIM(COALESCE(oi.product_name, ''))) = LOWER(TRIM(COALESCE(pnew.name, '')))
       AND NOT EXISTS (
            SELECT 1
            FROM consignment_sales x
            WHERE x.order_id = s.order_id
              AND x.order_item_id = s.order_item_id
              AND x.product_id = oi.product_sku
              AND x.id <> s.id
       )
     ORDER BY s.id ASC"
);

$payoutFixRows = fetchRows(
    $pdo,
    "SELECT
        pi.id,
        pi.product_id AS old_product_id,
        cs.product_id AS new_product_id,
        pi.consignment_sale_id
     FROM consignment_payout_items pi
     INNER JOIN consignment_sales cs ON cs.id = pi.consignment_sale_id
     WHERE pi.product_id <> cs.product_id
     ORDER BY pi.id ASC"
);

$ledgerFromSaleRows = fetchRows(
    $pdo,
    "SELECT
        m.id,
        m.product_id AS old_product_id,
        s.product_id AS new_product_id,
        m.sku AS old_sku,
        s.id AS sale_id
     FROM cupons_creditos_movimentos m
     INNER JOIN consignment_sales s ON s.ledger_credit_movement_id = m.id
     WHERE m.product_id <> s.product_id
        OR NULLIF(TRIM(CAST(m.sku AS CHAR)), '') IS NULL
        OR CAST(m.sku AS UNSIGNED) <> s.product_id
     ORDER BY m.id ASC"
);

/**
 * @return array<int, array<string, mixed>>
 */
function buildTableSkuFixRows(PDO $pdo, string $table): array
{
    $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
    if ($safeTable === null || $safeTable === '') {
        return [];
    }

    return fetchRows(
        $pdo,
        "SELECT
            t.id,
            t.product_id AS old_product_id,
            CAST(t.sku AS UNSIGNED) AS new_product_id,
            t.sku AS raw_sku
         FROM {$safeTable} t
         WHERE t.product_id IS NOT NULL
           AND NULLIF(TRIM(CAST(t.sku AS CHAR)), '') REGEXP '^[0-9]+$'
           AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(t.sku AS UNSIGNED))
           AND t.product_id <> CAST(t.sku AS UNSIGNED)
         ORDER BY t.id ASC"
    );
}

$tableFixTargets = [
    'cupons_creditos_movimentos',
    'inventario_itens',
    'inventario_logs',
    'inventario_pendentes',
    'inventario_scans',
    'produto_baixas',
    'sacolinha_itens',
];

$tableFixRows = [];
foreach ($tableFixTargets as $table) {
    $tableFixRows[$table] = buildTableSkuFixRows($pdo, $table);
}

$mode = $apply ? 'apply' : 'dry-run';
echo 'MODE=' . $mode . PHP_EOL;
echo 'CANDIDATE.consignment_sales=' . count($salesFixRows) . PHP_EOL;
echo 'CANDIDATE.consignment_payout_items=' . count($payoutFixRows) . PHP_EOL;
echo 'CANDIDATE.ledger_from_sale=' . count($ledgerFromSaleRows) . PHP_EOL;
foreach ($tableFixRows as $table => $rows) {
    echo 'CANDIDATE.' . $table . '=' . count($rows) . PHP_EOL;
}

if (!$apply) {
    echo 'STATUS=DRY_RUN_ONLY' . PHP_EOL;
    exit(0);
}

$reportDir = __DIR__ . '/../_ignore/reports';
if (!is_dir($reportDir) && !mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
    fwrite(STDERR, 'ERR:failed_to_create_report_dir=' . $reportDir . PHP_EOL);
    exit(1);
}

$stamp = date('Ymd-His');
writeCsv(
    $reportDir . '/product-identity-fix-consignment-sales-' . $stamp . '.csv',
    ['id', 'old_product_id', 'new_product_id', 'order_id', 'order_item_id', 'supplier_pessoa_id'],
    $salesFixRows
);
writeCsv(
    $reportDir . '/product-identity-fix-consignment-payout-items-' . $stamp . '.csv',
    ['id', 'old_product_id', 'new_product_id', 'consignment_sale_id'],
    $payoutFixRows
);
writeCsv(
    $reportDir . '/product-identity-fix-ledger-from-sale-' . $stamp . '.csv',
    ['id', 'old_product_id', 'new_product_id', 'old_sku', 'sale_id'],
    $ledgerFromSaleRows
);
foreach ($tableFixRows as $table => $rows) {
    writeCsv(
        $reportDir . '/product-identity-fix-' . $table . '-' . $stamp . '.csv',
        ['id', 'old_product_id', 'new_product_id', 'raw_sku'],
        $rows
    );
}

$updateSale = $pdo->prepare(
    "UPDATE consignment_sales
     SET product_id = :new_product_id,
         updated_at = NOW()
     WHERE id = :id
       AND product_id = :old_product_id"
);
$updatePayout = $pdo->prepare(
    "UPDATE consignment_payout_items
     SET product_id = :new_product_id
     WHERE id = :id
       AND product_id = :old_product_id"
);
$updateLedgerFromSale = $pdo->prepare(
    "UPDATE cupons_creditos_movimentos
     SET product_id = :new_product_id,
         sku = CAST(:new_product_id AS CHAR)
     WHERE id = :id
       AND (
           product_id = :old_product_id
           OR product_id IS NULL
       )"
);

$updatedSales = 0;
$updatedPayoutItems = 0;
$updatedLedgerFromSale = 0;
$updatedGeneric = [];
foreach ($tableFixTargets as $table) {
    $updatedGeneric[$table] = 0;
}

$pdo->beginTransaction();
try {
    $updatedSales = applyRows(
        $salesFixRows,
        static function (array $row) use ($updateSale): bool {
            $updateSale->execute([
                ':id' => (int) $row['id'],
                ':old_product_id' => (int) $row['old_product_id'],
                ':new_product_id' => (int) $row['new_product_id'],
            ]);
            return $updateSale->rowCount() > 0;
        }
    );

    $updatedPayoutItems = applyRows(
        $payoutFixRows,
        static function (array $row) use ($updatePayout): bool {
            $updatePayout->execute([
                ':id' => (int) $row['id'],
                ':old_product_id' => (int) $row['old_product_id'],
                ':new_product_id' => (int) $row['new_product_id'],
            ]);
            return $updatePayout->rowCount() > 0;
        }
    );

    $updatedLedgerFromSale = applyRows(
        $ledgerFromSaleRows,
        static function (array $row) use ($updateLedgerFromSale): bool {
            $oldProductId = $row['old_product_id'] !== null ? (int) $row['old_product_id'] : 0;
            $updateLedgerFromSale->execute([
                ':id' => (int) $row['id'],
                ':old_product_id' => $oldProductId,
                ':new_product_id' => (int) $row['new_product_id'],
            ]);
            return $updateLedgerFromSale->rowCount() > 0;
        }
    );

    // inventario_itens possui UNIQUE (inventario_id, product_id). Faz remapeamento em 2 fases
    // para evitar colisões transitórias quando há troca cruzada de IDs entre linhas.
    $inventarioRows = $tableFixRows['inventario_itens'] ?? [];
    if (!empty($inventarioRows)) {
        $ids = [];
        foreach ($inventarioRows as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
        if (!empty($ids)) {
            $params = [];
            $in = buildInClause('inv_id_', $ids, $params);
            $tempOffset = 2000000000;

            $stmtPhase1 = $pdo->prepare(
                "UPDATE inventario_itens
                 SET product_id = product_id + {$tempOffset}
                 WHERE id IN ({$in})"
            );
            $stmtPhase1->execute($params);

            $stmtPhase2 = $pdo->prepare(
                "UPDATE inventario_itens
                 SET product_id = CAST(sku AS UNSIGNED)
                 WHERE id IN ({$in})
                   AND NULLIF(TRIM(CAST(sku AS CHAR)), '') REGEXP '^[0-9]+$'
                   AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(sku AS UNSIGNED))"
            );
            $stmtPhase2->execute($params);
            $updatedGeneric['inventario_itens'] = $stmtPhase2->rowCount();
        }
    }

    foreach ($tableFixTargets as $table) {
        if ($table === 'inventario_itens') {
            continue;
        }
        $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
        if ($safeTable === null || $safeTable === '') {
            continue;
        }
        $sql = "UPDATE {$safeTable} t
                SET t.product_id = CAST(t.sku AS UNSIGNED)
                WHERE t.product_id IS NOT NULL
                  AND NULLIF(TRIM(CAST(t.sku AS CHAR)), '') REGEXP '^[0-9]+$'
                  AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(t.sku AS UNSIGNED))
                  AND t.product_id <> CAST(t.sku AS UNSIGNED)";
        $affected = $pdo->exec($sql);
        $updatedGeneric[$table] = $affected === false ? 0 : (int) $affected;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERR:rollback_due_to_exception=' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'UPDATED.consignment_sales=' . $updatedSales . PHP_EOL;
echo 'UPDATED.consignment_payout_items=' . $updatedPayoutItems . PHP_EOL;
echo 'UPDATED.ledger_from_sale=' . $updatedLedgerFromSale . PHP_EOL;
foreach ($updatedGeneric as $table => $count) {
    echo 'UPDATED.' . $table . '=' . $count . PHP_EOL;
}

// Post-checks
$postChecks = [
    'orphan.consignment_sales' => "SELECT COUNT(*) FROM consignment_sales s WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku=s.product_id)",
    'orphan.consignment_payout_items' => "SELECT COUNT(*) FROM consignment_payout_items pi WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku=pi.product_id)",
    'orphan.consignment_product_registry' => "SELECT COUNT(*) FROM consignment_product_registry r WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku=r.product_id)",
    'orphan.cupons_creditos_movimentos' => "SELECT COUNT(*) FROM cupons_creditos_movimentos m WHERE m.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku=m.product_id)",
    'mismatch.consignment_sales_vs_order_items' => "SELECT COUNT(*) FROM consignment_sales s JOIN order_items oi ON oi.id=s.order_item_id WHERE oi.product_sku IS NOT NULL AND s.product_id<>oi.product_sku",
];

foreach ($postChecks as $label => $sql) {
    $value = (int) ($pdo->query($sql)->fetchColumn() ?: 0);
    echo 'CHECK.' . $label . '=' . $value . PHP_EOL;
}

echo 'REPORT_DIR=' . $reportDir . PHP_EOL;
