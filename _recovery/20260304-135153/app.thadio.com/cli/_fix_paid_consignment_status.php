<?php
/**
 * Fix: Atualiza consignment_status dos 23 produtos cujas vendas foram pagas
 * mas o status ficou como 'em_estoque' porque o product_id antigo (WC) não
 * apontava para o produto correto.
 *
 * USO:
 *   php cli/_fix_paid_consignment_status.php            # dry-run
 *   php cli/_fix_paid_consignment_status.php --apply     # aplica
 */

require __DIR__ . '/../bootstrap.php';
[$pdo, $err] = bootstrapPdo();
if (!$pdo) { fwrite(STDERR, "DB fail: $err\n"); exit(1); }

$apply = in_array('--apply', $argv, true);
$mode = $apply ? 'APPLY' : 'DRY-RUN';

echo "=== Fix: consignment_status em_estoque → vendido_pago para vendas pagas ===\n";
echo "Modo: {$mode}\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

$sql = "
SELECT s.id AS sale_id, s.product_id, s.payout_status, s.payout_id,
       p.sku, p.name, p.consignment_status
FROM consignment_sales s
JOIN products p ON p.sku = s.product_id
WHERE s.payout_status = 'pago'
  AND s.sale_status = 'ativa'
  AND (p.consignment_status IS NULL OR p.consignment_status != 'vendido_pago')
ORDER BY s.id
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
echo "Produtos a corrigir: {$total}\n\n";

if ($total === 0) {
    echo "Nada a fazer. ✅\n";
    exit(0);
}

// Also update registry
$stmtProduct = $pdo->prepare("UPDATE products SET consignment_status = 'vendido_pago' WHERE sku = ? AND consignment_status != 'vendido_pago'");
$stmtRegistry = $pdo->prepare("UPDATE consignment_product_registry SET consignment_status = 'vendido_pago', status_changed_at = NOW() WHERE product_id = ? AND consignment_status != 'vendido_pago'");

$fixedProducts = 0;
$fixedRegistry = 0;

if ($apply) {
    $pdo->beginTransaction();
}

foreach ($rows as $row) {
    echo "  sku={$row['sku']} [{$row['consignment_status']}] → vendido_pago | sale#{$row['sale_id']} | {$row['name']}\n";
    
    if ($apply) {
        $stmtProduct->execute([$row['sku']]);
        $fixedProducts += $stmtProduct->rowCount();
        
        $stmtRegistry->execute([$row['sku']]);
        $fixedRegistry += $stmtRegistry->rowCount();
    }
}

if ($apply) {
    $pdo->commit();
}

echo "\n=== RESULTADO ===\n";
echo "  products.consignment_status: {$fixedProducts} atualizados\n";
echo "  registry.consignment_status: {$fixedRegistry} atualizados\n";

if ($apply) {
    echo "\n--- Verificação pós-fix ---\n";
    $remaining = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales s
        JOIN products p ON p.sku = s.product_id
        WHERE s.payout_status = 'pago' AND s.sale_status = 'ativa'
          AND (p.consignment_status IS NULL OR p.consignment_status != 'vendido_pago')
    ")->fetchColumn();
    echo "  Vendas pagas com produto não vendido_pago: {$remaining}";
    echo $remaining == 0 ? " ✅\n" : " ⚠️\n";
} else {
    echo "\nModo DRY-RUN. Nenhum dado alterado.\n";
    echo "Para aplicar: php cli/_fix_paid_consignment_status.php --apply\n";
}

echo "\n=== FIM ===\n";
