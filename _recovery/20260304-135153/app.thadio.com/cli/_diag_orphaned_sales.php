<?php
/**
 * Diagnóstico: 82 vendas ativas sem produto correspondente em products.
 * 
 * Cruza consignment_sales com order_items, consignment_product_registry,
 * e cupons_creditos_movimentos para recuperar info dos produtos deletados.
 */
require __DIR__ . '/../bootstrap.php';
[$pdo, $err] = bootstrapPdo();
if (!$pdo) { fwrite(STDERR, "DB fail: $err\n"); exit(1); }

echo "=== Vendas ativas sem produto em products ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Get all 82 orphaned sales with cross-referenced data
$sql = "
SELECT 
    s.id AS sale_id,
    s.product_id,
    s.order_id,
    s.order_item_id,
    s.supplier_pessoa_id,
    s.sale_status,
    s.payout_status,
    s.sold_at,
    s.net_amount,
    s.credit_amount,
    s.percent_applied,
    s.payout_id,
    s.ledger_credit_movement_id,
    -- order_items info
    oi.product_sku AS oi_product_sku,
    oi.product_name AS oi_product_name,
    oi.quantity AS oi_quantity,
    oi.price AS oi_price,
    oi.total AS oi_total,
    -- order info
    o.status AS order_status,
    o.payment_status,
    o.ordered_at,
    o.pessoa_id AS order_pessoa_id,
    -- registry info
    r.id AS registry_id,
    r.consignment_status AS registry_status,
    r.supplier_pessoa_id AS registry_supplier_id,
    r.received_at AS registry_received_at,
    -- supplier name
    pe.full_name AS supplier_name,
    -- ledger movement
    m.credit_amount AS ledger_amount,
    m.event_at AS ledger_event_at,
    m.event_type AS ledger_event_type
FROM consignment_sales s
LEFT JOIN order_items oi ON oi.id = s.order_item_id
LEFT JOIN orders o ON o.id = s.order_id
LEFT JOIN consignment_product_registry r ON r.product_id = s.product_id
LEFT JOIN pessoas pe ON pe.id = s.supplier_pessoa_id
LEFT JOIN cupons_creditos_movimentos m ON m.id = s.ledger_credit_movement_id
WHERE s.sale_status = 'ativa'
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
ORDER BY s.supplier_pessoa_id, s.sold_at
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
echo "Total de vendas ativas sem produto: {$total}\n\n";

// 2. Group by supplier
$bySupplier = [];
foreach ($rows as $row) {
    $sid = $row['supplier_pessoa_id'] ?? 0;
    $bySupplier[$sid][] = $row;
}

echo "=== RESUMO POR FORNECEDORA ===\n";
echo str_repeat('-', 120) . "\n";
printf("%-30s | %5s | %12s | %12s | %10s | %10s\n",
    'Fornecedora', 'Qtd', 'Net Total', 'Credit Total', 'Pendente', 'Pago');
echo str_repeat('-', 120) . "\n";

foreach ($bySupplier as $sid => $sales) {
    $name = $sales[0]['supplier_name'] ?? '(sem nome)';
    $count = count($sales);
    $netTotal = array_sum(array_column($sales, 'net_amount'));
    $creditTotal = array_sum(array_column($sales, 'credit_amount'));
    $pendente = count(array_filter($sales, fn($s) => $s['payout_status'] === 'pendente'));
    $pago = count(array_filter($sales, fn($s) => $s['payout_status'] === 'pago'));
    printf("%-30s | %5d | R$ %9.2f | R$ %9.2f | %10d | %10d\n",
        mb_substr($name, 0, 30), $count, $netTotal, $creditTotal, $pendente, $pago);
}
echo str_repeat('-', 120) . "\n\n";

// 3. Check data availability for recovery
echo "=== DISPONIBILIDADE DE DADOS ===\n";
$hasOi = 0; $hasRegistry = 0; $hasLedger = 0; $hasOrderItemName = 0;
foreach ($rows as $row) {
    if (!empty($row['oi_product_sku'])) $hasOi++;
    if (!empty($row['registry_id'])) $hasRegistry++;
    if (!empty($row['ledger_credit_movement_id'])) $hasLedger++;
    if (!empty($row['oi_product_name'])) $hasOrderItemName++;
}
echo "  Com order_item.product_sku:    {$hasOi}/{$total}\n";
echo "  Com order_item.product_name:   {$hasOrderItemName}/{$total}\n";
echo "  Com registry:                  {$hasRegistry}/{$total}\n";
echo "  Com ledger movement:           {$hasLedger}/{$total}\n\n";

// 4. Check if product_id matches oi.product_sku
echo "=== CONSISTÊNCIA product_id vs order_item ===\n";
$match = 0; $mismatch = 0; $noOi = 0;
foreach ($rows as $row) {
    if (empty($row['oi_product_sku'])) {
        $noOi++;
    } elseif ((string)$row['product_id'] === (string)$row['oi_product_sku']) {
        $match++;
    } else {
        $mismatch++;
        echo "  MISMATCH sale#{$row['sale_id']}: product_id={$row['product_id']} vs oi.product_sku={$row['oi_product_sku']}\n";
    }
}
echo "  Match: {$match} | Mismatch: {$mismatch} | Sem OI: {$noOi}\n\n";

// 5. Distinct product_ids
$distinctProducts = array_unique(array_column($rows, 'product_id'));
sort($distinctProducts);
echo "=== PRODUCT IDs DISTINTOS (" . count($distinctProducts) . ") ===\n";

// Try to find them anywhere
echo "\nBuscando info dos SKUs em outras tabelas...\n\n";
foreach ($distinctProducts as $pid) {
    echo "--- SKU {$pid} ---\n";
    
    // Check order_items for name
    $stmt2 = $pdo->prepare("SELECT DISTINCT product_name FROM order_items WHERE product_sku = ? AND product_name != '' LIMIT 3");
    $stmt2->execute([$pid]);
    $names = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    if ($names) {
        echo "  order_items.product_name: " . implode(' | ', $names) . "\n";
    }
    
    // Check registry
    $stmt3 = $pdo->prepare("SELECT id, consignment_status, supplier_pessoa_id, received_at, minimum_price_snapshot FROM consignment_product_registry WHERE product_id = ?");
    $stmt3->execute([$pid]);
    $reg = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($reg) {
        echo "  registry: id={$reg['id']} status={$reg['consignment_status']} supplier={$reg['supplier_pessoa_id']} received={$reg['received_at']} min_price={$reg['minimum_price_snapshot']}\n";
    } else {
        echo "  registry: (nenhum)\n";
    }
    
    // Count sales for this product
    $stmt4 = $pdo->prepare("SELECT COUNT(*) FROM consignment_sales WHERE product_id = ? AND sale_status = 'ativa'");
    $stmt4->execute([$pid]);
    $saleCount = $stmt4->fetchColumn();
    echo "  vendas ativas: {$saleCount}\n";
    
    // Check if archived/deleted in products with different status
    $stmt5 = $pdo->prepare("SELECT sku, name, status, source, consignment_status FROM products WHERE sku = ?");
    $stmt5->execute([$pid]);
    $prod = $stmt5->fetch(PDO::FETCH_ASSOC);
    if ($prod) {
        echo "  products: ENCONTRADO! status={$prod['status']} source={$prod['source']} consignment={$prod['consignment_status']}\n";
        echo "  >>> ESTE PRODUTO EXISTE! A query NOT EXISTS pode ter falhado.\n";
    } else {
        echo "  products: NÃO EXISTE (deletado permanentemente)\n";
    }
    
    echo "\n";
}

// 6. Detailed listing
echo "=== LISTAGEM DETALHADA ===\n";
printf("%-6s | %-12s | %-8s | %-8s | %-10s | %-10s | %-10s | %-30s | %s\n",
    'SaleID', 'ProductID', 'OrderID', 'Payout', 'Net', 'Credit', 'SoldAt', 'Supplier', 'ProductName');
echo str_repeat('-', 160) . "\n";
foreach ($rows as $row) {
    $productName = $row['oi_product_name'] ?? '(desconhecido)';
    printf("%-6d | %-12s | %-8s | %-8s | R$%8.2f | R$%8.2f | %-10s | %-30s | %s\n",
        $row['sale_id'],
        $row['product_id'],
        $row['order_id'] ?? '-',
        $row['payout_status'],
        (float)$row['net_amount'],
        (float)$row['credit_amount'],
        $row['sold_at'] ? substr($row['sold_at'], 0, 10) : '-',
        mb_substr($row['supplier_name'] ?? '(sem nome)', 0, 30),
        mb_substr($productName, 0, 50)
    );
}

echo "\n=== FIM ===\n";
