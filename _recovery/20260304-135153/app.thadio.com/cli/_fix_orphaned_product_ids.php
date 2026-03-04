<?php
/**
 * Fix: Corrige 82 vendas consignadas cujo product_id contém o WooCommerce ID
 * antigo em vez do Retrato SKU atual.
 *
 * Tabelas afetadas:
 *   1. consignment_sales.product_id  (WC ID → Retrato SKU)
 *   2. cupons_creditos_movimentos.product_id  (idem, via ledger_credit_movement_id)
 *
 * A fonte de verdade para o SKU correto é order_items.product_sku,
 * confirmada por metadata.wc_product_id = antigo product_id em 82/82 casos.
 *
 * USO:
 *   php cli/_fix_orphaned_product_ids.php             # dry-run
 *   php cli/_fix_orphaned_product_ids.php --apply      # aplica
 */

require __DIR__ . '/../bootstrap.php';
[$pdo, $err] = bootstrapPdo();
if (!$pdo) { fwrite(STDERR, "DB fail: $err\n"); exit(1); }

$apply = in_array('--apply', $argv, true);
$mode = $apply ? 'APPLY' : 'DRY-RUN';

echo "=== Fix: Corrigir product_id WC → Retrato SKU em consignment_sales + ledger ===\n";
echo "Modo: {$mode}\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// ── 1. Buscar as 82 vendas órfãs com o SKU correto ──────────────────────
$sql = "
SELECT s.id AS sale_id,
       s.product_id AS old_wc_id,
       oi.product_sku AS correct_sku,
       s.ledger_credit_movement_id,
       s.order_id,
       s.order_item_id,
       s.supplier_pessoa_id
FROM consignment_sales s
JOIN order_items oi ON oi.id = s.order_item_id
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
ORDER BY s.id
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
echo "Vendas a corrigir: {$total}\n\n";

if ($total === 0) {
    echo "Nada a fazer. ✅\n";
    exit(0);
}

// ── 2. Validações pré-fix ────────────────────────────────────────────────
echo "--- Validações pré-fix ---\n";
$errors = [];
foreach ($rows as $row) {
    $sku = $row['correct_sku'];
    
    // Verify correct_sku exists in products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
    $stmt->execute([$sku]);
    if ((int)$stmt->fetchColumn() === 0) {
        $errors[] = "sale#{$row['sale_id']}: correct_sku={$sku} NOT FOUND in products";
    }
    
    // Verify no duplicate would be created
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) FROM consignment_sales 
        WHERE order_id = ? AND order_item_id = ? AND product_id = ? AND id != ?
    ");
    $stmt2->execute([$row['order_id'], $row['order_item_id'], $sku, $row['sale_id']]);
    if ((int)$stmt2->fetchColumn() > 0) {
        $errors[] = "sale#{$row['sale_id']}: duplicate would be created for sku={$sku}";
    }
    
    // Verify metadata.wc_product_id matches old_wc_id
    $stmt3 = $pdo->prepare("SELECT metadata FROM products WHERE sku = ?");
    $stmt3->execute([$sku]);
    $meta = json_decode($stmt3->fetchColumn() ?: '{}', true);
    $metaWcId = (int)($meta['wc_product_id'] ?? 0);
    if ($metaWcId !== (int)$row['old_wc_id']) {
        $errors[] = "sale#{$row['sale_id']}: metadata.wc_product_id={$metaWcId} != old_wc_id={$row['old_wc_id']}";
    }
}

if (!empty($errors)) {
    echo "ERROS DE VALIDAÇÃO — ABORTANDO:\n";
    foreach ($errors as $e) echo "  ✗ {$e}\n";
    exit(1);
}
echo "Todas as validações passaram. ✅\n\n";

// ── 3. Aplicar correções ─────────────────────────────────────────────────
$stmtSale = $pdo->prepare("UPDATE consignment_sales SET product_id = ? WHERE id = ?");
$stmtLedger = $pdo->prepare("UPDATE cupons_creditos_movimentos SET product_id = ? WHERE id = ?");

$fixedSales = 0;
$fixedLedger = 0;

if ($apply) {
    $pdo->beginTransaction();
}

foreach ($rows as $row) {
    $saleId = $row['sale_id'];
    $correctSku = $row['correct_sku'];
    $oldWcId = $row['old_wc_id'];
    $ledgerId = $row['ledger_credit_movement_id'];
    
    echo "  sale#{$saleId}: product_id {$oldWcId} → {$correctSku}";
    
    if ($apply) {
        $stmtSale->execute([$correctSku, $saleId]);
        $fixedSales++;
    }
    
    if ($ledgerId > 0) {
        echo " | ledger#{$ledgerId}";
        if ($apply) {
            $stmtLedger->execute([$correctSku, $ledgerId]);
            $fixedLedger++;
        }
    }
    
    echo "\n";
}

if ($apply) {
    $pdo->commit();
}

echo "\n=== RESULTADO ===\n";
echo "  consignment_sales.product_id: {$fixedSales} atualizados\n";
echo "  cupons_creditos_movimentos.product_id: {$fixedLedger} atualizados\n";

// ── 4. Verificação pós-fix ───────────────────────────────────────────────
if ($apply) {
    echo "\n--- Verificação pós-fix ---\n";
    
    $remaining = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales s
        WHERE s.sale_status = 'ativa'
          AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
    ")->fetchColumn();
    echo "  Vendas ativas sem produto: {$remaining}";
    echo $remaining == 0 ? " ✅\n" : " ⚠️\n";
    
    $remainingRegistry = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales s
        WHERE s.sale_status = 'ativa'
          AND NOT EXISTS (SELECT 1 FROM consignment_product_registry r WHERE r.product_id = s.product_id)
    ")->fetchColumn();
    echo "  Vendas ativas sem registry: {$remainingRegistry}";
    echo $remainingRegistry == 0 ? " ✅\n" : " ⚠️\n";
    
    $orphanedLedger = $pdo->query("
        SELECT COUNT(*) FROM consignment_sales s
        JOIN cupons_creditos_movimentos m ON m.id = s.ledger_credit_movement_id
        WHERE s.sale_status = 'ativa'
          AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = m.product_id)
    ")->fetchColumn();
    echo "  Ledger com product_id órfão: {$orphanedLedger}";
    echo $orphanedLedger == 0 ? " ✅\n" : " ⚠️\n";
} else {
    echo "\nModo DRY-RUN. Nenhum dado alterado.\n";
    echo "Para aplicar: php cli/_fix_orphaned_product_ids.php --apply\n";
}

echo "\n=== FIM ===\n";
