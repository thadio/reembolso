<?php
/**
 * Verify: Can all 82 orphaned sales be fixed by mapping 
 * consignment_sales.product_id (WooCommerce ID) to products.sku (Retrato SKU)
 * using metadata->wc_product_id?
 */
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();

echo "=== Verificação: Mapeamento WC ID → Retrato SKU para 82 vendas órfãs ===\n\n";

// Get all orphaned sales with their oi.product_sku (which we know exists in products)
$sql = "
SELECT s.id AS sale_id, s.product_id AS wc_id, oi.product_sku AS correct_sku,
       p.sku AS verified_sku, p.name AS product_name,
       p.source, p.consignment_status, p.supplier_pessoa_id AS product_supplier,
       s.supplier_pessoa_id AS sale_supplier,
       s.payout_status
FROM consignment_sales s
JOIN order_items oi ON oi.id = s.order_item_id
JOIN products p ON p.sku = oi.product_sku
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products pp WHERE pp.sku = s.product_id)
ORDER BY s.id
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
echo "Total sales to fix: {$total}\n\n";

// Verify that metadata.wc_product_id matches the old product_id
$metaMatch = 0;
$metaMismatch = 0;
$noMeta = 0;
$supplierMatch = 0;
$supplierMismatch = 0;

foreach ($rows as $row) {
    // Check metadata
    $stmt = $pdo->prepare("SELECT metadata FROM products WHERE sku = ?");
    $stmt->execute([$row['correct_sku']]);
    $meta = json_decode($stmt->fetchColumn() ?: '{}', true);
    $wcId = (int)($meta['wc_product_id'] ?? 0);
    
    if ($wcId === (int)$row['wc_id']) {
        $metaMatch++;
    } elseif ($wcId > 0) {
        $metaMismatch++;
        echo "  META MISMATCH sale#{$row['sale_id']}: wc_id={$row['wc_id']} vs meta.wc_product_id={$wcId} (sku={$row['correct_sku']})\n";
    } else {
        $noMeta++;
        echo "  NO META sale#{$row['sale_id']}: wc_id={$row['wc_id']} sku={$row['correct_sku']} (no wc_product_id in metadata)\n";
    }
    
    // Check supplier consistency
    if ((int)$row['product_supplier'] === (int)$row['sale_supplier']) {
        $supplierMatch++;
    } else {
        $supplierMismatch++;
    }
}

echo "\n=== RESULTADO DA VERIFICAÇÃO ===\n";
echo "  metadata.wc_product_id = s.product_id: {$metaMatch}/{$total}\n";
echo "  metadata mismatch:                     {$metaMismatch}/{$total}\n";
echo "  no metadata wc_product_id:             {$noMeta}/{$total}\n";
echo "  supplier match (sale vs product):       {$supplierMatch}/{$total}\n";
echo "  supplier mismatch:                      {$supplierMismatch}/{$total}\n";

// Also check registry: will the correct_sku have a registry entry?
$hasRegistry = 0;
foreach ($rows as $row) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consignment_product_registry WHERE product_id = ?");
    $stmt->execute([$row['correct_sku']]);
    if ($stmt->fetchColumn() > 0) $hasRegistry++;
}
echo "  correct_sku has registry entry:         {$hasRegistry}/{$total}\n";

// Check for duplicates: would updating product_id create duplicate (order_id, order_item_id, product_id)?
echo "\n=== VERIFICAÇÃO DE DUPLICATAS ===\n";
$dupes = 0;
foreach ($rows as $row) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM consignment_sales 
        WHERE order_id = ? AND order_item_id = ? AND product_id = ? AND id != ?
    ");
    $stmt->execute([$row['sale_id'], $row['sale_id'], $row['correct_sku'], $row['sale_id']]);
    // Actually check by the sale's order_id/order_item_id
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) FROM consignment_sales 
        WHERE order_id = (SELECT order_id FROM consignment_sales WHERE id = ?)
          AND order_item_id = (SELECT order_item_id FROM consignment_sales WHERE id = ?)
          AND product_id = ?
          AND id != ?
    ");
    $stmt2->execute([$row['sale_id'], $row['sale_id'], $row['correct_sku'], $row['sale_id']]);
    if ((int)$stmt2->fetchColumn() > 0) {
        $dupes++;
        echo "  DUPLICATE: sale#{$row['sale_id']} would conflict with existing sale for sku={$row['correct_sku']}\n";
    }
}
echo "  Potential duplicates: {$dupes}/{$total}\n";

// Generate the UPDATE statements (dry run)
echo "\n=== DRY RUN: SQL UPDATES ===\n";
echo "-- UPDATE consignment_sales SET product_id = <correct_sku> WHERE id = <sale_id>\n";
$updateCount = 0;
foreach ($rows as $row) {
    echo "UPDATE consignment_sales SET product_id = {$row['correct_sku']} WHERE id = {$row['sale_id']}; -- was wc_id={$row['wc_id']}\n";
    $updateCount++;
}
echo "\n-- Total: {$updateCount} updates\n";

echo "\n=== FIM ===\n";
