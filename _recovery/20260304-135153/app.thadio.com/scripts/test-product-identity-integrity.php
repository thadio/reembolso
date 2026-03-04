#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

[$pdo, $connectionError] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, 'ERR:' . ($connectionError ?? 'database connection failed') . PHP_EOL);
    exit(1);
}

/**
 * @return int
 */
function scalarInt(PDO $pdo, string $sql): int
{
    $value = $pdo->query($sql)->fetchColumn();
    return (int) ($value ?: 0);
}

/**
 * @return bool
 */
function hasForeignKey(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :constraint
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
         LIMIT 1"
    );
    $stmt->execute([
        ':table' => $table,
        ':constraint' => $constraint,
    ]);
    return (bool) $stmt->fetchColumn();
}

$checks = [
    'products.null_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM products WHERE sku IS NULL OR sku = 0"),
    'products.duplicate_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM (SELECT sku, COUNT(*) c FROM products GROUP BY sku HAVING c > 1) x"),
    'orphan.consignment_registry.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM consignment_product_registry r WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = r.product_id)"),
    'orphan.consignment_sales.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM consignment_sales s WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)"),
    'orphan.consignment_payout_items.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM consignment_payout_items pi WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = pi.product_id)"),
    'orphan.cupons_creditos_movimentos.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM cupons_creditos_movimentos m WHERE m.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = m.product_id)"),
    'orphan.produto_baixas.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM produto_baixas b WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = b.product_id)"),
    'orphan.consignacao_recebimento_produtos.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM consignacao_recebimento_produtos crp WHERE crp.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = crp.product_id)"),
    'orphan.consignment_items.product_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM consignment_items ci WHERE ci.product_sku IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = ci.product_sku)"),
    'orphan.order_return_items.product_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM order_return_items ri WHERE ri.product_sku IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = ri.product_sku)"),
    'orphan.sacolinha_itens.product_id' => scalarInt($pdo, "SELECT COUNT(*) FROM sacolinha_itens si WHERE si.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = si.product_id)"),
    'orphan.order_items.product_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM order_items oi WHERE oi.product_sku IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = oi.product_sku)"),
    'orphan.inventory_movements.product_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM inventory_movements im WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = im.product_sku)"),
    'mismatch.consignment_sales_vs_order_items' => scalarInt($pdo, "SELECT COUNT(*) FROM consignment_sales s JOIN order_items oi ON oi.id = s.order_item_id WHERE oi.product_sku IS NOT NULL AND s.product_id <> oi.product_sku"),
    'mismatch.inventario_itens.product_id_vs_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM inventario_itens i WHERE i.product_id IS NOT NULL AND NULLIF(TRIM(CAST(i.sku AS CHAR)), '') REGEXP '^[0-9]+$' AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(i.sku AS UNSIGNED)) AND i.product_id <> CAST(i.sku AS UNSIGNED)"),
    'mismatch.inventario_logs.product_id_vs_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM inventario_logs i WHERE i.product_id IS NOT NULL AND NULLIF(TRIM(CAST(i.sku AS CHAR)), '') REGEXP '^[0-9]+$' AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(i.sku AS UNSIGNED)) AND i.product_id <> CAST(i.sku AS UNSIGNED)"),
    'mismatch.inventario_pendentes.product_id_vs_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM inventario_pendentes i WHERE i.product_id IS NOT NULL AND NULLIF(TRIM(CAST(i.sku AS CHAR)), '') REGEXP '^[0-9]+$' AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(i.sku AS UNSIGNED)) AND i.product_id <> CAST(i.sku AS UNSIGNED)"),
    'mismatch.inventario_scans.product_id_vs_sku' => scalarInt($pdo, "SELECT COUNT(*) FROM inventario_scans i WHERE i.product_id IS NOT NULL AND NULLIF(TRIM(CAST(i.sku AS CHAR)), '') REGEXP '^[0-9]+$' AND EXISTS (SELECT 1 FROM products p WHERE p.sku = CAST(i.sku AS UNSIGNED)) AND i.product_id <> CAST(i.sku AS UNSIGNED)"),
];

$fkChecks = [
    'fk.consignment_product_registry.product_id' => hasForeignKey($pdo, 'consignment_product_registry', 'fk_consign_registry_product_sku'),
    'fk.consignment_sales.product_id' => hasForeignKey($pdo, 'consignment_sales', 'fk_consign_sales_product_sku'),
    'fk.consignment_payout_items.product_id' => hasForeignKey($pdo, 'consignment_payout_items', 'fk_consign_payout_item_product_sku'),
    'fk.cupons_creditos_movimentos.product_id' => hasForeignKey($pdo, 'cupons_creditos_movimentos', 'fk_cupons_mov_product_sku'),
    'fk.produto_baixas.product_id' => hasForeignKey($pdo, 'produto_baixas', 'fk_produto_baixas_product_sku'),
    'fk.inventario_itens.product_id' => hasForeignKey($pdo, 'inventario_itens', 'fk_inventario_itens_product_sku'),
    'fk.inventario_scans.product_id' => hasForeignKey($pdo, 'inventario_scans', 'fk_inventario_scans_product_sku'),
    'fk.inventario_logs.product_id' => hasForeignKey($pdo, 'inventario_logs', 'fk_inventario_logs_product_sku'),
    'fk.inventario_pendentes.product_id' => hasForeignKey($pdo, 'inventario_pendentes', 'fk_inventario_pendentes_product_sku'),
    'fk.consignacao_recebimento_produtos.product_id' => hasForeignKey($pdo, 'consignacao_recebimento_produtos', 'fk_consig_receb_prod_product_sku'),
    'fk.consignment_items.product_sku' => hasForeignKey($pdo, 'consignment_items', 'fk_consign_items_product_sku'),
    'fk.order_return_items.product_sku' => hasForeignKey($pdo, 'order_return_items', 'fk_order_return_items_product_sku'),
    'fk.sacolinha_itens.product_id' => hasForeignKey($pdo, 'sacolinha_itens', 'fk_sacolinha_itens_product_sku'),
];

$errors = 0;
echo "== METRIC CHECKS ==\n";
foreach ($checks as $label => $value) {
    $ok = $value === 0;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . '=' . $value . PHP_EOL;
    if (!$ok) {
        $errors++;
    }
}

echo "\n== FK CHECKS ==\n";
foreach ($fkChecks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $errors++;
    }
}

echo "\nSUMMARY.errors=" . $errors . PHP_EOL;
exit($errors === 0 ? 0 : 1);
