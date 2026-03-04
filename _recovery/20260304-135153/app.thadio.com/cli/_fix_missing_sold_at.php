<?php
/**
 * Fix missing sold_at in consignment_sales (and sync to ledger movements).
 *
 * Source: orders.ordered_at  (verified: 100 % match with existing sold_at)
 *
 * Usage:
 *   php cli/_fix_missing_sold_at.php              # dry-run
 *   php cli/_fix_missing_sold_at.php --apply      # execute
 */

require __DIR__ . '/../bootstrap.php';
[$pdo, $err] = bootstrapPdo();
if ($err) { echo "DB error: $err\n"; exit(1); }

$apply = in_array('--apply', $argv);
echo $apply ? "=== APPLY MODE ===\n\n" : "=== DRY-RUN MODE ===\n\n";

// ── 1. Fix consignment_sales.sold_at ────────────────────────────
echo "── 1. consignment_sales.sold_at ──\n";

$countBefore = $pdo->query(
    "SELECT COUNT(*) FROM consignment_sales WHERE sold_at IS NULL"
)->fetchColumn();
echo "  NULL sold_at before: $countBefore\n";

if ($countBefore == 0) {
    echo "  Nothing to fix.\n\n";
} else {
    $sql1 = "
        UPDATE consignment_sales cs
        JOIN orders o ON o.id = cs.order_id
        SET cs.sold_at = o.ordered_at
        WHERE cs.sold_at IS NULL
          AND o.ordered_at IS NOT NULL
    ";

    if ($apply) {
        $affected = $pdo->exec($sql1);
        echo "  Updated: $affected rows\n";
    } else {
        // Preview
        $preview = $pdo->query("
            SELECT cs.id, cs.order_id, o.ordered_at
            FROM consignment_sales cs
            JOIN orders o ON o.id = cs.order_id
            WHERE cs.sold_at IS NULL AND o.ordered_at IS NOT NULL
            ORDER BY o.ordered_at
            LIMIT 15
        ");
        echo "  Preview (first 15):\n";
        foreach ($preview as $r) {
            echo "    sale#{$r['id']}  order={$r['order_id']}  → sold_at={$r['ordered_at']}\n";
        }
        echo "  Would update: $countBefore rows\n";
    }
    echo "\n";
}

// ── 2. Sync ledger movements sold_at ────────────────────────────
echo "── 2. cupons_creditos_movimentos.sold_at (sync from orders.ordered_at) ──\n";

$countLedger = $pdo->query("
    SELECT COUNT(*)
    FROM consignment_sales cs
    JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
    JOIN orders o ON o.id = cs.order_id
    WHERE m.sold_at IS NULL
      AND o.ordered_at IS NOT NULL
")->fetchColumn();
echo "  Ledger movements with NULL sold_at (linked to sales): $countLedger\n";

if ($countLedger == 0) {
    echo "  Nothing to fix.\n\n";
} else {
    $sql2 = "
        UPDATE cupons_creditos_movimentos m
        JOIN consignment_sales cs ON cs.ledger_credit_movement_id = m.id
        JOIN orders o ON o.id = cs.order_id
        SET m.sold_at = o.ordered_at
        WHERE m.sold_at IS NULL
          AND o.ordered_at IS NOT NULL
    ";

    if ($apply) {
        $affected = $pdo->exec($sql2);
        echo "  Updated: $affected rows\n";
    } else {
        echo "  Would update: $countLedger rows\n";
    }
    echo "\n";
}

// ── 3. Also sync ledger event_at where NULL ─────────────────────
echo "── 3. cupons_creditos_movimentos.event_at (sync from orders.ordered_at) ──\n";

$countEvt = $pdo->query("
    SELECT COUNT(*)
    FROM consignment_sales cs
    JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
    JOIN orders o ON o.id = cs.order_id
    WHERE m.event_at IS NULL
      AND o.ordered_at IS NOT NULL
")->fetchColumn();
echo "  Ledger movements with NULL event_at (linked to sales): $countEvt\n";

if ($countEvt == 0) {
    echo "  Nothing to fix.\n\n";
} else {
    $sql3 = "
        UPDATE cupons_creditos_movimentos m
        JOIN consignment_sales cs ON cs.ledger_credit_movement_id = m.id
        JOIN orders o ON o.id = cs.order_id
        SET m.event_at = o.ordered_at
        WHERE m.event_at IS NULL
          AND o.ordered_at IS NOT NULL
    ";

    if ($apply) {
        $affected = $pdo->exec($sql3);
        echo "  Updated: $affected rows\n";
    } else {
        echo "  Would update: $countEvt rows\n";
    }
    echo "\n";
}

// ── 4. Also sync ledger event_id (order_id) where 0/NULL ───────
echo "── 4. cupons_creditos_movimentos.event_id (set to order_id where missing) ──\n";

$countEid = $pdo->query("
    SELECT COUNT(*)
    FROM consignment_sales cs
    JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
    WHERE (m.event_id IS NULL OR m.event_id = 0)
      AND cs.order_id > 0
")->fetchColumn();
echo "  Ledger movements with NULL/0 event_id: $countEid\n";

if ($countEid == 0) {
    echo "  Nothing to fix.\n\n";
} else {
    $sql4 = "
        UPDATE cupons_creditos_movimentos m
        JOIN consignment_sales cs ON cs.ledger_credit_movement_id = m.id
        SET m.event_id = cs.order_id
        WHERE (m.event_id IS NULL OR m.event_id = 0)
          AND cs.order_id > 0
    ";

    if ($apply) {
        $affected = $pdo->exec($sql4);
        echo "  Updated: $affected rows\n";
    } else {
        echo "  Would update: $countEid rows\n";
    }
    echo "\n";
}

// ── 5. Verification ─────────────────────────────────────────────
if ($apply) {
    echo "── 5. Post-fix verification ──\n";
    $remaining = $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE sold_at IS NULL")->fetchColumn();
    echo "  consignment_sales with NULL sold_at: $remaining\n";

    $remainLedger = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE m.sold_at IS NULL
    ")->fetchColumn();
    echo "  Linked ledger movements with NULL sold_at: $remainLedger\n";

    $remainEvt = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE m.event_at IS NULL
    ")->fetchColumn();
    echo "  Linked ledger movements with NULL event_at: $remainEvt\n";

    $remainEid = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE m.event_id IS NULL OR m.event_id = 0
    ")->fetchColumn();
    echo "  Linked ledger movements with NULL/0 event_id: $remainEid\n";
}

echo "\nDone.\n";
