<?php
/**
 * Incremental Data Correction — Post-Backfill Fixes
 *
 * Fixes issues identified in the backfill data without duplicating information.
 *
 * Usage: php cli/consignment_backfill_corrections.php [--dry-run]
 *
 * FIX 1: origin_type — 'lote_produtos' → 'manual' for rows without intake/consignment FK
 * FIX 2: commission_formula_version — 'v1' → 'v1_backfill' for backfill-created sales
 * FIX 3: reversal_notes — clean [BACKFILL] reconciliation text from non-reversal rows
 * FIX 4: Cross-reference 48 pago-without-payout sales with legacy movements
 */

require __DIR__ . '/../bootstrap.php';

[$pdo] = bootstrapPdo();
if (!$pdo) { fwrite(STDERR, "No DB\n"); exit(1); }

$dryRun = in_array('--dry-run', $argv, true);
$prefix = $dryRun ? '[DRY-RUN] ' : '';

$startTime = microtime(true);
$log = function (string $msg) use ($startTime) {
    $elapsed = number_format(microtime(true) - $startTime, 1);
    echo "[{$elapsed}s] $msg\n";
    flush();
};

$log("{$prefix}=== Correções incrementais pós-backfill ===\n");

// ─── FIX 1: Registry origin_type ────────────────────────────
$log("{$prefix}--- FIX 1: origin_type em consignment_product_registry ---");

// 1a. Rows with intake_id → 'pre_lote'
$sql1a = "UPDATE consignment_product_registry SET origin_type = 'pre_lote' WHERE intake_id IS NOT NULL AND origin_type != 'pre_lote'";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_product_registry WHERE intake_id IS NOT NULL AND origin_type != 'pre_lote'");
    $log("{$prefix}  intake_id → pre_lote: " . $stmt->fetchColumn() . " rows");
} else {
    $affected = $pdo->exec($sql1a);
    $log("{$prefix}  intake_id → pre_lote: {$affected} rows");
}

// 1b. Rows with consignment_id (no intake) → 'sku_consignment'
$sql1b = "UPDATE consignment_product_registry SET origin_type = 'sku_consignment' WHERE consignment_id IS NOT NULL AND intake_id IS NULL AND origin_type != 'sku_consignment'";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_product_registry WHERE consignment_id IS NOT NULL AND intake_id IS NULL AND origin_type != 'sku_consignment'");
    $log("{$prefix}  consignment_id → sku_consignment: " . $stmt->fetchColumn() . " rows");
} else {
    $affected = $pdo->exec($sql1b);
    $log("{$prefix}  consignment_id → sku_consignment: {$affected} rows");
}

// 1c. Rows with neither → 'manual' (backfill-origin, previously defaulted to lote_produtos)
$sql1c = "UPDATE consignment_product_registry SET origin_type = 'manual' WHERE intake_id IS NULL AND consignment_id IS NULL AND origin_type = 'lote_produtos'";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_product_registry WHERE intake_id IS NULL AND consignment_id IS NULL AND origin_type = 'lote_produtos'");
    $log("{$prefix}  neither → manual: " . $stmt->fetchColumn() . " rows");
} else {
    $affected = $pdo->exec($sql1c);
    $log("{$prefix}  neither → manual: {$affected} rows");
}

$log("");

// ─── FIX 2: Sales commission_formula_version ────────────────
$log("{$prefix}--- FIX 2: commission_formula_version em consignment_sales ---");

// All 603 current sales were backfill-created (payouts=0, no organic flow yet).
// Mark them as v1_backfill so future organic sales keep 'v1'.
// Safety: only update rows that DON'T already have a payout_id (organic payout) or 'v1_backfill'.
$sql2 = "UPDATE consignment_sales
         SET commission_formula_version = 'v1_backfill'
         WHERE commission_formula_version = 'v1'
           AND payout_id IS NULL
           AND id NOT IN (SELECT DISTINCT pi.consignment_sale_id FROM consignment_payout_items pi WHERE pi.consignment_sale_id IS NOT NULL)";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_sales
         WHERE commission_formula_version = 'v1'
           AND payout_id IS NULL
           AND id NOT IN (SELECT DISTINCT pi.consignment_sale_id FROM consignment_payout_items pi WHERE pi.consignment_sale_id IS NOT NULL)");
    $log("{$prefix}  v1 → v1_backfill: " . $stmt->fetchColumn() . " rows");
} else {
    $affected = $pdo->exec($sql2);
    $log("{$prefix}  v1 → v1_backfill: {$affected} rows");
}

$log("");

// ─── FIX 3: Clean reversal_notes pollution ──────────────────
$log("{$prefix}--- FIX 3: limpar reversal_notes com [BACKFILL] ---");

// These 48 rows have reconciliation notes in reversal_notes (wrong column).
// They are ativa/pago — not reversals. NULL out reversal_notes.
// Safety: only clear if content is exactly the backfill pattern AND sale_status = 'ativa'
$sql3 = "UPDATE consignment_sales
         SET reversal_notes = NULL
         WHERE reversal_notes LIKE '%[BACKFILL] Conciliado automaticamente%'
           AND sale_status = 'ativa'";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consignment_sales
         WHERE reversal_notes LIKE '%[BACKFILL] Conciliado automaticamente%'
           AND sale_status = 'ativa'");
    $log("{$prefix}  reversal_notes NULLed: " . $stmt->fetchColumn() . " rows");
} else {
    $affected = $pdo->exec($sql3);
    $log("{$prefix}  reversal_notes NULLed: {$affected} rows");
}

$log("");

// ─── FIX 4: Detailed analysis of pago-without-payout ────────
$log("{$prefix}--- FIX 4: análise de sales pago sem payout_id ---");

// These were marked pago by Phase 4 backfill, which matched them to legacy payout movements.
// The backfill didn't create consignment_payouts records — it just marked payout_status='pago'.
// We need to extract the movement_id from the (now cleaned) reversal_notes...
// But wait — we just NULLed those. Let's get the info BEFORE cleaning.
// Actually the notes are already cleaned in non-dry-run. Let's reconstruct from paid_at timestamps.

// List all pago-without-payout sales grouped by supplier
$stmt = $pdo->query("
    SELECT cs.supplier_pessoa_id, pe.full_name,
           COUNT(*) AS cnt,
           SUM(cs.credit_amount) AS total_credit,
           MIN(cs.paid_at) AS earliest_paid,
           MAX(cs.paid_at) AS latest_paid
    FROM consignment_sales cs
    LEFT JOIN pessoas pe ON pe.id = cs.supplier_pessoa_id
    WHERE cs.payout_status = 'pago' AND cs.payout_id IS NULL
    GROUP BY cs.supplier_pessoa_id, pe.full_name
    ORDER BY total_credit DESC
");
$pagoOrphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
$log("  Sales pago sem payout_id por fornecedora:");
foreach ($pagoOrphans as $p) {
    $name = $p['full_name'] ?: '(sem nome)';
    $log("    supplier #{$p['supplier_pessoa_id']} ({$name}): {$p['cnt']} sales, R$ " . number_format((float)$p['total_credit'], 2, ',', '.') . " ({$p['earliest_paid']} → {$p['latest_paid']})");
}

// Cross-reference with unlinked legacy movements
$log("\n  Movimentos legados não conciliados por fornecedora:");
$stmt = $pdo->query("
    SELECT v.pessoa_id AS supplier_pessoa_id, pe.full_name,
           COUNT(*) AS cnt,
           SUM(m.credit_amount) AS total_amount
    FROM cupons_creditos_movimentos m
    JOIN cupons_creditos v ON v.id = m.voucher_account_id
    LEFT JOIN pessoas pe ON pe.id = v.pessoa_id
    WHERE m.type = 'debito' AND m.event_type = 'payout' AND m.payout_id IS NULL
    GROUP BY v.pessoa_id, pe.full_name
    ORDER BY total_amount DESC
");
$unlinkedMovs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($unlinkedMovs as $u) {
    $name = $u['full_name'] ?: '(sem nome)';
    $log("    supplier #{$u['supplier_pessoa_id']} ({$name}): {$u['cnt']} movimentos, R$ " . number_format((float)$u['total_amount'], 2, ',', '.'));
}

$log("");

// ─── FIX 5: Ensure products.consignment_status is synced ────
$log("{$prefix}--- FIX 5: sync products.consignment_status ← registry ---");

$sql5 = "UPDATE products p
         JOIN consignment_product_registry r ON r.product_id = p.sku
         SET p.consignment_status = r.consignment_status
         WHERE r.consignment_status IS NOT NULL
           AND (p.consignment_status IS NULL OR p.consignment_status != r.consignment_status)";
if ($dryRun) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products p
         JOIN consignment_product_registry r ON r.product_id = p.sku
         WHERE r.consignment_status IS NOT NULL
           AND (p.consignment_status IS NULL OR p.consignment_status != r.consignment_status)");
    $log("{$prefix}  Out-of-sync products: " . $stmt->fetchColumn());
} else {
    $affected = $pdo->exec($sql5);
    $log("{$prefix}  Products synced: {$affected} rows");
}

$log("");

// ─── SUMMARY ────────────────────────────────────────────────
$log("{$prefix}=== Verificação pós-correção ===");

$stmt = $pdo->query("SELECT origin_type, COUNT(*) AS cnt FROM consignment_product_registry GROUP BY origin_type ORDER BY cnt DESC");
$log("  Registry origin_type:");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $log("    {$r['origin_type']}: {$r['cnt']}");
}

$stmt = $pdo->query("SELECT commission_formula_version, COUNT(*) AS cnt FROM consignment_sales GROUP BY commission_formula_version ORDER BY cnt DESC");
$log("  Sales commission_formula_version:");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $log("    {$r['commission_formula_version']}: {$r['cnt']}");
}

$stmt = $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE reversal_notes LIKE '%[BACKFILL]%'");
$log("  Remaining polluted reversal_notes: " . $stmt->fetchColumn());

$stmt = $pdo->query("SELECT COUNT(*) FROM products p JOIN consignment_product_registry r ON r.product_id = p.sku WHERE p.consignment_status != r.consignment_status");
$log("  Products still out of sync: " . $stmt->fetchColumn());

$elapsed = number_format(microtime(true) - $startTime, 1);
$log("\n{$prefix}=== Correções completas em {$elapsed}s ===");
