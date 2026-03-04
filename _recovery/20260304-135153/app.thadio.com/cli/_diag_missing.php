<?php
/**
 * Diagnose missing data in consignment_sales.
 * READ-ONLY — no writes.
 */
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();
if (!$pdo) { echo "DB fail\n"; exit(1); }

echo "=== CONSIGNMENT_SALES MISSING DATA DIAGNOSIS ===\n\n";

// 1. Overall field completeness
echo "── 1. Field completeness ──\n";
$fields = [
    'product_id'     => "product_id IS NULL OR product_id = 0",
    'order_id'       => "order_id IS NULL OR order_id = 0",
    'order_item_id'  => "order_item_id IS NULL OR order_item_id = 0",
    'sold_at'        => "sold_at IS NULL",
    'credit_amount'  => "credit_amount IS NULL OR credit_amount = 0",
    'percent_applied'=> "percent_applied IS NULL OR percent_applied = 0",
    'supplier_pessoa_id' => "supplier_pessoa_id IS NULL OR supplier_pessoa_id = 0",
    'ledger_credit_movement_id' => "ledger_credit_movement_id IS NULL OR ledger_credit_movement_id = 0",
];
$total = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales")->fetchColumn();
echo "  Total sales: {$total}\n\n";
foreach ($fields as $field => $cond) {
    $missing = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE {$cond}")->fetchColumn();
    $pct = $total > 0 ? round($missing / $total * 100, 1) : 0;
    $status = $missing === 0 ? '✅' : '⚠️';
    echo "  {$status} {$field}: {$missing} missing ({$pct}%)\n";
}

// 2. sold_at: where can we get it?
echo "\n── 2. sold_at missing — trace sources ──\n";
$noSoldAt = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE sold_at IS NULL")->fetchColumn();
echo "  Sales with NULL sold_at: {$noSoldAt}\n";

if ($noSoldAt > 0) {
    // Can we get from order_items.created_at?
    $fromOI = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN order_items oi ON oi.id = cs.order_item_id
        WHERE cs.sold_at IS NULL AND oi.created_at IS NOT NULL
    ")->fetchColumn();
    echo "  → Recoverable from order_items.created_at: {$fromOI}\n";

    // Can we get from orders.created_at?
    $fromOrder = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN orders o ON o.id = cs.order_id
        WHERE cs.sold_at IS NULL AND o.created_at IS NOT NULL
    ")->fetchColumn();
    echo "  → Recoverable from orders.created_at: {$fromOrder}\n";

    // Can we get from ledger movement event_at?
    $fromLedger = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE cs.sold_at IS NULL AND m.event_at IS NOT NULL
    ")->fetchColumn();
    echo "  → Recoverable from ledger movement event_at: {$fromLedger}\n";

    // Sample
    echo "  Sample (first 10):\n";
    $r = $pdo->query("
        SELECT cs.id, cs.product_id, cs.order_id, cs.order_item_id, cs.ledger_credit_movement_id,
               oi.created_at as oi_created, o.created_at as o_created,
               m.event_at as m_event_at
        FROM consignment_sales cs
        LEFT JOIN order_items oi ON oi.id = cs.order_item_id
        LEFT JOIN orders o ON o.id = cs.order_id
        LEFT JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE cs.sold_at IS NULL
        LIMIT 10
    ");
    foreach ($r as $row) {
        echo "    sale#{$row['id']} prod={$row['product_id']} order={$row['order_id']} oi={$row['order_item_id']} mov={$row['ledger_credit_movement_id']}"
           . " | oi.created=" . ($row['oi_created'] ?? 'NULL')
           . " o.created=" . ($row['o_created'] ?? 'NULL')
           . " m.event_at=" . ($row['m_event_at'] ?? 'NULL') . "\n";
    }
}

// 3. order_id missing
echo "\n── 3. order_id missing — trace sources ──\n";
$noOrderId = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE order_id IS NULL OR order_id = 0")->fetchColumn();
echo "  Sales with NULL/0 order_id: {$noOrderId}\n";

if ($noOrderId > 0) {
    // Can we get from order_items?
    $fromOI = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN order_items oi ON oi.id = cs.order_item_id
        WHERE (cs.order_id IS NULL OR cs.order_id = 0) AND oi.order_id IS NOT NULL AND oi.order_id > 0
    ")->fetchColumn();
    echo "  → Recoverable from order_items.order_id: {$fromOI}\n";

    // Can we get from ledger movement event_id (if event_type='sale')?
    $fromLedger = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE (cs.order_id IS NULL OR cs.order_id = 0)
          AND m.event_id IS NOT NULL AND m.event_id > 0
    ")->fetchColumn();
    echo "  → Recoverable from ledger movement event_id: {$fromLedger}\n";
}

// 4. order_item_id missing
echo "\n── 4. order_item_id missing — trace sources ──\n";
$noOIId = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE order_item_id IS NULL OR order_item_id = 0")->fetchColumn();
echo "  Sales with NULL/0 order_item_id: {$noOIId}\n";

if ($noOIId > 0) {
    // Can we find by matching order_id + product_id in order_items?
    $fromMatch = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN order_items oi ON oi.order_id = cs.order_id AND oi.product_sku = cs.product_id
        WHERE (cs.order_item_id IS NULL OR cs.order_item_id = 0)
          AND cs.order_id > 0
    ")->fetchColumn();
    echo "  → Recoverable by matching order_id + product_sku: {$fromMatch}\n";

    // Check ledger movement for order_item_id
    $fromLedger = (int) $pdo->query("
        SELECT COUNT(*)
        FROM consignment_sales cs
        JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
        WHERE (cs.order_item_id IS NULL OR cs.order_item_id = 0)
          AND m.order_item_id IS NOT NULL AND m.order_item_id > 0
    ")->fetchColumn();
    echo "  → Recoverable from ledger movement order_item_id: {$fromLedger}\n";
}

// 5. Check what columns the ledger movement has
echo "\n── 5. Ledger movement columns ──\n";
$cols = $pdo->query("SHOW COLUMNS FROM cupons_creditos_movimentos")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

// 6. Check what columns order_items has (relevant ones)
echo "\n── 6. order_items relevant columns ──\n";
$cols = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    if (in_array($c['Field'], ['id','order_id','product_sku','product_name','quantity','unit_price','total_price','created_at','updated_at','status'])) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
}

// 7. Check the backfill Phase 2 to understand how sales were created
echo "\n── 7. How were sales created? Check ledger_credit_movement_id coverage ──\n";
$hasLedger = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE ledger_credit_movement_id IS NOT NULL AND ledger_credit_movement_id > 0")->fetchColumn();
$noLedger = (int) $pdo->query("SELECT COUNT(*) FROM consignment_sales WHERE ledger_credit_movement_id IS NULL OR ledger_credit_movement_id = 0")->fetchColumn();
echo "  With ledger_credit_movement_id: {$hasLedger}\n";
echo "  Without: {$noLedger}\n";

// 8. For sales WITH ledger_credit_movement_id, check how many movements have order/event data
echo "\n── 8. Ledger movements with order data ──\n";
$r = $pdo->query("
    SELECT
        SUM(m.event_id IS NOT NULL AND m.event_id > 0) as has_event_id,
        SUM(m.order_item_id IS NOT NULL AND m.order_item_id > 0) as has_order_item_id,
        SUM(m.product_id IS NOT NULL AND m.product_id > 0) as has_product_id,
        SUM(m.event_at IS NOT NULL) as has_event_at,
        COUNT(*) as total
    FROM consignment_sales cs
    JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
")->fetch(PDO::FETCH_ASSOC);
if ($r) {
    echo "  Total joined: {$r['total']}\n";
    echo "  m.event_id (order_id): {$r['has_event_id']}\n";
    echo "  m.order_item_id: {$r['has_order_item_id']}\n";
    echo "  m.product_id: {$r['has_product_id']}\n";
    echo "  m.event_at: {$r['has_event_at']}\n";
}

// 9. Sample of sales with NULL sold_at to understand patterns
echo "\n── 9. Cross-check: sales with sold_at = NULL and their ledger data ──\n";
$r = $pdo->query("
    SELECT cs.id, cs.product_id, cs.order_id, cs.order_item_id,
           cs.ledger_credit_movement_id,
           m.event_id as m_order_id, m.order_item_id as m_oi_id,
           m.event_at, m.event_type, m.product_id as m_product_id,
           m.created_at as m_created
    FROM consignment_sales cs
    LEFT JOIN cupons_creditos_movimentos m ON m.id = cs.ledger_credit_movement_id
    WHERE cs.sold_at IS NULL
    LIMIT 15
");
foreach ($r as $row) {
    echo "  sale#{$row['id']} cs.order={$row['order_id']} cs.oi={$row['order_item_id']}"
       . " | mov#{$row['ledger_credit_movement_id']}: event_id(order)={$row['m_order_id']}"
       . " oi={$row['m_oi_id']} event_at={$row['event_at']} type={$row['event_type']}"
       . " created={$row['m_created']}\n";
}

echo "\nDone.\n";
