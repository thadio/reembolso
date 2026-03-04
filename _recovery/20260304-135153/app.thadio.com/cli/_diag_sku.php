<?php
/**
 * Diagnose why SKU is empty in payout form for a supplier.
 */
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();
if (!$pdo) { echo "DB fail\n"; exit(1); }

$sid = 2000000350; // Elenara

// 1. Raw data from consignment_sales
echo "=== 1. Raw consignment_sales data (first 10 pending for supplier {$sid}) ===\n";
$stmt = $pdo->prepare("
    SELECT cs.id, cs.product_id, cs.order_item_id, cs.order_id
    FROM consignment_sales cs
    WHERE cs.supplier_pessoa_id = ? AND cs.sale_status = 'ativa' AND cs.payout_status = 'pendente'
    ORDER BY cs.sold_at ASC LIMIT 10
");
$stmt->execute([$sid]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($sales as $s) {
    echo "  sale#{$s['id']}: product_id={$s['product_id']} order_item_id={$s['order_item_id']} order_id={$s['order_id']}\n";
}

// 2. Check if product_id matches products.sku
echo "\n=== 2. Products match check ===\n";
foreach ($sales as $s) {
    $pid = $s['product_id'];
    $oiid = $s['order_item_id'];
    
    // Direct match
    $p = $pdo->prepare("SELECT sku, name FROM products WHERE sku = ? LIMIT 1");
    $p->execute([$pid]);
    $prod = $p->fetch(PDO::FETCH_ASSOC);
    
    // order_items fallback
    $oi = null;
    if ($oiid > 0) {
        $o = $pdo->prepare("SELECT id, product_sku, product_name FROM order_items WHERE id = ? LIMIT 1");
        $o->execute([$oiid]);
        $oi = $o->fetch(PDO::FETCH_ASSOC);
    }
    
    // p2 fallback
    $p2 = null;
    if ($oi && !empty($oi['product_sku'])) {
        $p2q = $pdo->prepare("SELECT sku, name FROM products WHERE sku = ? LIMIT 1");
        $p2q->execute([$oi['product_sku']]);
        $p2 = $p2q->fetch(PDO::FETCH_ASSOC);
    }
    
    echo "  sale#{$s['id']}: product_id={$pid}";
    echo " | p.sku=" . ($prod ? $prod['sku'] : 'NULL');
    echo " | oi.product_sku=" . ($oi ? $oi['product_sku'] : 'NULL(no oi)');
    echo " | p2.sku=" . ($p2 ? $p2['sku'] : 'NULL');
    echo " | oi.product_name=" . ($oi ? substr($oi['product_name'], 0, 30) : 'NULL');
    echo "\n";
}

// 3. Run the ACTUAL query from listPendingBySupplier and check output
echo "\n=== 3. Actual query output (listPendingBySupplier equivalent) ===\n";
$stmt = $pdo->prepare("
    SELECT s.*,
           COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', s.product_id)) AS product_name,
           COALESCE(
               NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
               NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
               NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
               TRIM(CAST(s.product_id AS CHAR))
           ) AS sku
    FROM consignment_sales s
    LEFT JOIN order_items oi ON oi.id = s.order_item_id
    LEFT JOIN products p  ON p.sku  = s.product_id
    LEFT JOIN products p2 ON p2.sku = oi.product_sku
    WHERE s.supplier_pessoa_id = ? AND s.sale_status = 'ativa' AND s.payout_status = 'pendente'
    ORDER BY s.sold_at ASC LIMIT 10
");
$stmt->execute([$sid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  sale#{$r['id']}: sku=[" . ($r['sku'] ?? 'NULL') . "] product_name=[" . substr($r['product_name'] ?? 'NULL', 0, 40) . "] product_id={$r['product_id']}\n";
}

// 4. Check if the view file actually reads from 'sku'
echo "\n=== 4. Check column keys in result ===\n";
if (!empty($rows)) {
    echo "  Keys in first row: " . implode(', ', array_keys($rows[0])) . "\n";
    echo "  Has 'sku' key: " . (array_key_exists('sku', $rows[0]) ? 'YES' : 'NO') . "\n";
    echo "  Has 'product_sku' key: " . (array_key_exists('product_sku', $rows[0]) ? 'YES' : 'NO') . "\n";
}

echo "\nDone.\n";
