<?php
require __DIR__ . '/../bootstrap.php';
[$pdo] = bootstrapPdo();

$sql = "SELECT s.id, s.product_id, s.payout_status, p.consignment_status, p.name
FROM consignment_sales s
JOIN products p ON p.sku = s.product_id
WHERE s.payout_status = 'pago' AND s.sale_status = 'ativa'
  AND (p.consignment_status IS NULL OR p.consignment_status != 'vendido_pago')
ORDER BY s.id";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo count($rows) . " sales paid but product not vendido_pago:\n\n";

$byStatus = [];
foreach ($rows as $r) {
    $cs = $r['consignment_status'] ?? '(null)';
    $byStatus[$cs][] = $r;
    echo "  sale#{$r['id']}: sku={$r['product_id']} consignment_status={$cs} | " . substr($r['name'], 0, 50) . "\n";
}

echo "\nBy consignment_status:\n";
foreach ($byStatus as $status => $group) {
    echo "  {$status}: " . count($group) . "\n";
}

// Check: were these among the 82 we just fixed?
$fixedIds = [5,20,28,29,31,32,34,40,41,48,49,50,59,61,67,71,91,102,103,114,122,124,129,130,132,139,153,154,158,167,175,183,191,201,204,205,209,217,230,231,236,241,243,259,272,282,296,300,302,304,322,340,373,381,424,440,442,456,459,463,464,465,491,508,534,540,546,550,555,556,557,559,573,575,577,584,592,593,595,601,602,603];
$fromFix = 0;
$notFromFix = 0;
foreach ($rows as $r) {
    if (in_array((int)$r['id'], $fixedIds, true)) {
        $fromFix++;
    } else {
        $notFromFix++;
    }
}
echo "\nFrom the 82 just fixed: {$fromFix}\n";
echo "Pre-existing (not from fix): {$notFromFix}\n";
