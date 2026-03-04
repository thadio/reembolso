<?php
/**
 * Post-Backfill Corrections
 * Fixes findings #1, #2, #3 (automatic) and #6 (registry backfill for orphaned sales).
 * 
 * Run with: php cli/post_backfill_fix.php
 * Add --dry-run to see what would be changed without applying.
 * Add --apply to actually execute the changes.
 */
require __DIR__ . '/../bootstrap.php';

[$pdo] = bootstrapPdo();
if (!$pdo) { echo "❌ DB connection failed.\n"; exit(1); }

$dryRun = !in_array('--apply', $argv);
if ($dryRun) {
    echo "🔍 DRY RUN — no changes will be made. Use --apply to execute.\n\n";
} else {
    echo "⚡ APPLYING CHANGES to production database.\n\n";
}

$totalFixed = 0;
$log = [];

// ═══════════════════════════════════════════════════════════════
// FIX #1: origin_type 'lote_produtos' → 'manual' where no intake/consignment link
// ═══════════════════════════════════════════════════════════════
echo "=== FIX #1: origin_type ===\n";
$count1 = $pdo->query("
    SELECT COUNT(*) FROM consignment_product_registry
    WHERE origin_type = 'lote_produtos' AND intake_id IS NULL AND consignment_id IS NULL
")->fetchColumn();
echo "  Affected rows: {$count1}\n";

if ($count1 > 0 && !$dryRun) {
    $pdo->exec("
        UPDATE consignment_product_registry
        SET origin_type = 'manual'
        WHERE origin_type = 'lote_produtos' AND intake_id IS NULL AND consignment_id IS NULL
    ");
    echo "  ✅ Updated {$count1} rows: origin_type = 'manual'\n";
    $totalFixed += $count1;
    $log[] = "#1: {$count1} registry rows origin_type lote_produtos → manual";
} elseif ($count1 > 0) {
    echo "  → Would update {$count1} rows to origin_type='manual'\n";
} else {
    echo "  ✅ No rows to fix (already clean).\n";
}

// ═══════════════════════════════════════════════════════════════
// FIX #2: commission_formula_version 'v1' → 'v1_backfill' for backfilled sales
// ═══════════════════════════════════════════════════════════════
echo "\n=== FIX #2: commission_formula_version ===\n";
// Only tag sales that have NO payout_id (never reconciled organically) 
// AND commission_formula_version = 'v1' — these are definitely from the backfill.
// We also check: no consignment_payouts records exist yet, so all sales are from backfill.
$count2 = $pdo->query("
    SELECT COUNT(*) FROM consignment_sales
    WHERE commission_formula_version = 'v1'
")->fetchColumn();
$organicCount = $pdo->query("SELECT COUNT(*) FROM consignment_payouts")->fetchColumn();
echo "  Sales with v1: {$count2}\n";
echo "  Organic payouts exist: " . ($organicCount > 0 ? "YES ($organicCount)" : "NO (all sales are from backfill)") . "\n";

if ($count2 > 0 && $organicCount == 0 && !$dryRun) {
    $pdo->exec("
        UPDATE consignment_sales
        SET commission_formula_version = 'v1_backfill'
        WHERE commission_formula_version = 'v1'
    ");
    echo "  ✅ Updated {$count2} rows: commission_formula_version = 'v1_backfill'\n";
    $totalFixed += $count2;
    $log[] = "#2: {$count2} sales commission_formula_version v1 → v1_backfill";
} elseif ($count2 > 0 && $organicCount > 0) {
    // Be more careful: only tag those without payout_id
    $countSafe = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales
        WHERE commission_formula_version = 'v1' AND payout_id IS NULL
    ")->fetchColumn();
    echo "  ⚠️ Organic payouts exist. Would only update {$countSafe} sales (those without payout_id).\n";
    if (!$dryRun && $countSafe > 0) {
        $pdo->exec("
            UPDATE consignment_sales
            SET commission_formula_version = 'v1_backfill'
            WHERE commission_formula_version = 'v1' AND payout_id IS NULL
        ");
        echo "  ✅ Updated {$countSafe} rows\n";
        $totalFixed += $countSafe;
        $log[] = "#2: {$countSafe} sales commission_formula_version v1 → v1_backfill (safe mode)";
    }
} elseif ($count2 > 0) {
    echo "  → Would update {$count2} rows to 'v1_backfill'\n";
} else {
    echo "  ✅ No rows to fix.\n";
}

// ═══════════════════════════════════════════════════════════════
// FIX #3: reversal_notes cleanup — move backfill reconciliation notes out of reversal_notes
// ═══════════════════════════════════════════════════════════════
echo "\n=== FIX #3: reversal_notes cleanup ===\n";
$count3 = $pdo->query("
    SELECT COUNT(*) FROM consignment_sales
    WHERE reversal_notes LIKE '%[BACKFILL] Conciliado%' AND sale_status = 'ativa'
")->fetchColumn();
echo "  Affected rows: {$count3}\n";

if ($count3 > 0 && !$dryRun) {
    // The reversal_notes contain the reconciliation note which should not be there.
    // These are ativa/pago sales — not reversals. Clear reversal_notes.
    $pdo->exec("
        UPDATE consignment_sales
        SET reversal_notes = NULL
        WHERE reversal_notes LIKE '%[BACKFILL] Conciliado%' AND sale_status = 'ativa'
    ");
    echo "  ✅ Cleared reversal_notes on {$count3} ativa sales.\n";
    // Note: the reconciliation info is already tracked in the ledger movement event_notes
    // and through the paid_at timestamp on the sale, so no data is lost.
    $totalFixed += $count3;
    $log[] = "#3: {$count3} sales reversal_notes cleared (backfill conciliation text in wrong column)";
} elseif ($count3 > 0) {
    echo "  → Would clear reversal_notes on {$count3} ativa sales\n";
} else {
    echo "  ✅ No rows to fix.\n";
}

// ═══════════════════════════════════════════════════════════════
// FIX #6: Create registry entries for 260 source=compra products with legitimate consignment sales
// ═══════════════════════════════════════════════════════════════
echo "\n=== FIX #6a: Registry backfill for source=compra products with consignment sales ===\n";

// These 260 products have legitimate consignment sales (from consignacao-scope vouchers)
// but no registry entry because Phase 1 only imported source='consignacao' products.
// We need to create registry entries for them so the consignment module can track them.
$orphanedCompra = $pdo->query("
    SELECT DISTINCT cs.product_id, cs.supplier_pessoa_id,
           p.name AS product_name, p.price, p.source, p.status AS product_status,
           MIN(cs.sold_at) AS first_sold
    FROM consignment_sales cs
    LEFT JOIN consignment_product_registry r ON r.product_id = cs.product_id
    JOIN products p ON p.sku = cs.product_id
    WHERE r.id IS NULL AND p.source = 'compra'
    GROUP BY cs.product_id, cs.supplier_pessoa_id
    ORDER BY cs.product_id
")->fetchAll(PDO::FETCH_ASSOC);

echo "  Distinct product/supplier pairs without registry: " . count($orphanedCompra) . "\n";

if (count($orphanedCompra) > 0 && !$dryRun) {
    $insertStmt = $pdo->prepare("
        INSERT INTO consignment_product_registry
            (product_id, supplier_pessoa_id, consignment_status, origin_type, received_at, created_at, updated_at)
        VALUES (?, ?, ?, 'manual', ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE id = id
    ");

    $inserted = 0;
    foreach ($orphanedCompra as $row) {
        $productId = (int) $row['product_id'];
        $supplierId = (int) $row['supplier_pessoa_id'];
        $receivedAt = $row['first_sold'] ?? date('Y-m-d H:i:s');

        // Determine consignment_status based on existing sales
        $hasPago = $pdo->prepare("SELECT COUNT(*) FROM consignment_sales WHERE product_id = ? AND payout_status = 'pago' AND sale_status = 'ativa'");
        $hasPago->execute([$productId]);
        $isPago = (int) $hasPago->fetchColumn() > 0;

        $hasAtiva = $pdo->prepare("SELECT COUNT(*) FROM consignment_sales WHERE product_id = ? AND sale_status = 'ativa'");
        $hasAtiva->execute([$productId]);
        $isAtiva = (int) $hasAtiva->fetchColumn() > 0;

        if ($isPago) {
            $status = 'vendido_pago';
        } elseif ($isAtiva) {
            $status = 'vendido_pendente';
        } else {
            $status = 'em_estoque'; // fallback
        }

        $insertStmt->execute([$productId, $supplierId, $status, $receivedAt]);
        $inserted++;
    }

    echo "  ✅ Created {$inserted} registry entries for source=compra products.\n";
    $totalFixed += $inserted;
    $log[] = "#6a: {$inserted} registry entries created for source=compra products with consignment sales";
} elseif (count($orphanedCompra) > 0) {
    echo "  → Would create " . count($orphanedCompra) . " registry entries\n";
} else {
    echo "  ✅ No orphaned source=compra products.\n";
}

// ═══════════════════════════════════════════════════════════════
// FIX #6b: Handle 83 sales with non-existent products
// These products were deleted/archived from the products table but still have ledger movements.
// We do NOT delete sales — they are legitimate historical records.
// We mark them with a note and set sale_status to 'revertida' if they're still 'ativa'.
// ═══════════════════════════════════════════════════════════════
echo "\n=== FIX #6b: Sales for non-existent products ===\n";
$orphanedNoProduct = $pdo->query("
    SELECT cs.id, cs.product_id, cs.sale_status, cs.payout_status, cs.credit_amount, cs.supplier_pessoa_id
    FROM consignment_sales cs
    LEFT JOIN consignment_product_registry r ON r.product_id = cs.product_id
    LEFT JOIN products p ON p.sku = cs.product_id
    WHERE r.id IS NULL AND p.sku IS NULL
    ORDER BY cs.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "  Sales referencing deleted products: " . count($orphanedNoProduct) . "\n";

// Breakdown
$breakdown = [];
foreach ($orphanedNoProduct as $row) {
    $key = $row['sale_status'] . '/' . $row['payout_status'];
    $breakdown[$key] = ($breakdown[$key] ?? 0) + 1;
}
foreach ($breakdown as $k => $v) echo "    {$k}: {$v}\n";

// For these, we don't need registry entries (product doesn't exist).
// Sales already flagged as 'revertida' are fine.
// ativa/pendente ones: these are sales for products that no longer exist.
// The ledger movement already credited the supplier — we should NOT reverse that.
// Best approach: leave them as-is but flag with a note for future audits.
$ativaOrphans = array_filter($orphanedNoProduct, fn($r) => $r['sale_status'] === 'ativa');
echo "  Ativa sales for deleted products: " . count($ativaOrphans) . "\n";

if (count($ativaOrphans) > 0 && !$dryRun) {
    // We DON'T change sale_status — these were real sales that generated real credit.
    // We just mark them so the system knows the product no longer exists.
    $ids = array_column($ativaOrphans, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Check if they already have a note
    $alreadyNoted = $pdo->prepare("
        SELECT COUNT(*) FROM consignment_sales
        WHERE id IN ({$placeholders}) AND reversal_notes LIKE '%produto_inexistente%'
    ");
    $alreadyNoted->execute($ids);
    $notedCount = (int) $alreadyNoted->fetchColumn();

    if ($notedCount < count($ids)) {
        $pdo->prepare("
            UPDATE consignment_sales
            SET reversal_notes = CASE
                WHEN reversal_notes IS NULL OR reversal_notes = '' THEN '[AUDIT] produto_inexistente — produto removido da tabela products'
                ELSE CONCAT(reversal_notes, ' | [AUDIT] produto_inexistente')
            END
            WHERE id IN ({$placeholders})
            AND (reversal_notes IS NULL OR reversal_notes NOT LIKE '%produto_inexistente%')
        ")->execute($ids);
        $flagged = count($ids) - $notedCount;
        echo "  ✅ Flagged {$flagged} ativa sales with [AUDIT] produto_inexistente note.\n";
        $totalFixed += $flagged;
        $log[] = "#6b: {$flagged} ativa sales flagged as produto_inexistente (product deleted from DB)";
    } else {
        echo "  ✅ All already flagged.\n";
    }
} elseif (count($ativaOrphans) > 0) {
    echo "  → Would flag " . count($ativaOrphans) . " ativa sales with audit note\n";
} else {
    echo "  ✅ No ativa orphans to flag.\n";
}

// ═══════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════
echo "\n" . str_repeat('═', 60) . "\n";
if ($dryRun) {
    echo "DRY RUN complete. No changes were made.\n";
    echo "Run with --apply to execute the fixes.\n";
} else {
    echo "APPLIED. Total rows affected: {$totalFixed}\n";
    echo "Changes log:\n";
    foreach ($log as $l) echo "  • {$l}\n";
}
echo str_repeat('═', 60) . "\n";
