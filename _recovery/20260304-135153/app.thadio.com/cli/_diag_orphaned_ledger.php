<?php
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();

$sql = "
SELECT COUNT(*) AS total,
       SUM(CASE WHEN m.product_id = s.product_id THEN 1 ELSE 0 END) AS same_old,
       SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = m.product_id) THEN 1 ELSE 0 END) AS orphaned
FROM consignment_sales s
JOIN cupons_creditos_movimentos m ON m.id = s.ledger_credit_movement_id
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
";
$row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
echo "Total: {$row['total']}\n";
echo "Ledger product_id == sale product_id (old WC): {$row['same_old']}\n";
echo "Ledger product_id also orphaned: {$row['orphaned']}\n";

// Also check sku column in ledger
$sql2 = "
SELECT COUNT(*) AS total,
       SUM(CASE WHEN m.sku IS NOT NULL AND m.sku != '' AND m.sku != '0' THEN 1 ELSE 0 END) AS has_sku,
       SUM(CASE WHEN CAST(m.sku AS UNSIGNED) = oi.product_sku THEN 1 ELSE 0 END) AS sku_matches_oi
FROM consignment_sales s
JOIN cupons_creditos_movimentos m ON m.id = s.ledger_credit_movement_id
JOIN order_items oi ON oi.id = s.order_item_id
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
";
$row2 = $pdo->query($sql2)->fetch(PDO::FETCH_ASSOC);
echo "\nLedger sku column:\n";
echo "  has sku value: {$row2['has_sku']}/{$row2['total']}\n";
echo "  sku matches oi.product_sku: {$row2['sku_matches_oi']}/{$row2['total']}\n";
