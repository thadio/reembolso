<?php
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();

// Check if oi.product_sku values actually exist in products
$sql = "
SELECT COUNT(*) AS total,
       SUM(CASE WHEN EXISTS (SELECT 1 FROM products p WHERE p.sku = oi.product_sku) THEN 1 ELSE 0 END) AS found_in_products
FROM consignment_sales s
JOIN order_items oi ON oi.id = s.order_item_id
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
";
$row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
echo "Total orphaned: {$row['total']}\n";
echo "oi.product_sku exists in products: {$row['found_in_products']}\n";

// Check pattern: sample of old_id vs current_sku
$sql2 = "
SELECT s.product_id AS old_id, oi.product_sku AS current_sku, p.name
FROM consignment_sales s
JOIN order_items oi ON oi.id = s.order_item_id
JOIN products p ON p.sku = oi.product_sku
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products pp WHERE pp.sku = s.product_id)
ORDER BY s.id LIMIT 10
";
$rows = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
echo "\nSample: old_id => current_sku:\n";
foreach ($rows as $r) {
    echo "  {$r['old_id']} => {$r['current_sku']}  {$r['name']}\n";
}

// Check if there is a wc_id or woo column in products
$cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
$wcCols = array_filter($cols, fn($c) => stripos($c, 'wc') !== false || stripos($c, 'woo') !== false || stripos($c, 'legacy') !== false || stripos($c, 'external') !== false || stripos($c, 'old') !== false);
echo "\nProducts columns with wc/woo/legacy/external/old: " . (empty($wcCols) ? "(none)" : implode(', ', $wcCols)) . "\n";

// Check if old product_ids appear in products.metadata JSON
$sample_old_ids = array_column($rows, 'old_id');
if (!empty($sample_old_ids)) {
    $first = $sample_old_ids[0];
    $stmt = $pdo->prepare("SELECT sku, metadata FROM products WHERE metadata LIKE ? LIMIT 3");
    $stmt->execute(['%' . $first . '%']);
    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSearch for old_id {$first} in products.metadata: " . count($found) . " matches\n";
    foreach ($found as $f) {
        echo "  sku={$f['sku']} metadata=" . substr($f['metadata'], 0, 200) . "\n";
    }
}

// Check if there's a wc_product_id in metadata of the products that DO exist
$sql3 = "
SELECT p.sku, p.metadata
FROM consignment_sales s
JOIN order_items oi ON oi.id = s.order_item_id
JOIN products p ON p.sku = oi.product_sku
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products pp WHERE pp.sku = s.product_id)
LIMIT 5
";
$rows3 = $pdo->query($sql3)->fetchAll(PDO::FETCH_ASSOC);
echo "\nMetadata of matching products (via oi.product_sku):\n";
foreach ($rows3 as $r) {
    $meta = json_decode($r['metadata'] ?? '{}', true);
    $wcId = $meta['wc_product_id'] ?? $meta['woocommerce_id'] ?? $meta['external_id'] ?? $meta['old_id'] ?? '(not found)';
    echo "  sku={$r['sku']} wc_id_in_meta={$wcId}\n";
    if (is_array($meta)) {
        $keys = array_keys($meta);
        $relevantKeys = array_filter($keys, fn($k) => stripos($k, 'id') !== false || stripos($k, 'wc') !== false || stripos($k, 'woo') !== false);
        echo "    id-related keys: " . (empty($relevantKeys) ? "(none)" : implode(', ', $relevantKeys)) . "\n";
        echo "    all keys: " . implode(', ', array_slice($keys, 0, 20)) . "\n";
    }
}

echo "\nDone.\n";
