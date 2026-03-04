<?php
/**
 * Consignment Backfill — Populates consignment module tables from legacy data.
 *
 * Usage: php cli/consignment_backfill.php [--dry-run] [--phase=N] [--verbose]
 *
 * PHASES:
 * 1. Populate consignment_product_registry for products with source='consignacao'
 * 2. Populate consignment_sales from voucher credit entries (ledger)
 * 3. Derive consignment_status for each product (heuristic)
 * 4. Attempt to reconcile legacy payouts (FIFO best-effort)
 * 5. Backfill scope='consignacao' on voucher accounts
 * 6. Sync products.consignment_status from registry
 *
 * WARNING: This script WRITES data. Use --dry-run first.
 */

require __DIR__ . '/../bootstrap.php';

use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\ConsignmentPayoutRepository;
use App\Repositories\ConsignmentPayoutItemRepository;
use App\Repositories\ConsignmentPeriodLockRepository;
use App\Repositories\ProductRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Support\SchemaBootstrapper;

// ── CLI args ────────────────────────────────────────────────
$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$onlyPhase = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--phase=')) {
        $onlyPhase = (int) substr($arg, 8);
    }
}

[$pdo, $err] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, "Erro de conexão: $err\n");
    exit(1);
}

// Ensure NEW consignment tables exist (DDL only — lightweight CREATE IF NOT EXISTS)
SchemaBootstrapper::enable();
$registry = new ConsignmentProductRegistryRepository($pdo);
$sales    = new ConsignmentSaleRepository($pdo);
new ConsignmentPayoutRepository($pdo);
new ConsignmentPayoutItemRepository($pdo);
new ConsignmentPeriodLockRepository($pdo);
SchemaBootstrapper::disable();

// Existing-table repos — do NOT run ensureTable/ensureColumn (heavy ALTER TABLEs on live DB)
$products = new ProductRepository($pdo);
$vouchers = new VoucherAccountRepository($pdo);
$entries  = new VoucherCreditEntryRepository($pdo);

$startTime = microtime(true);

$log = function (string $msg) use ($startTime) {
    $elapsed = number_format(microtime(true) - $startTime, 1);
    echo "[{$elapsed}s] $msg\n";
    if (ob_get_level()) ob_flush();
    flush();
};
$prefix = $dryRun ? '[DRY-RUN] ' : '';

/**
 * Bulk CASE/WHEN UPDATE in chunks — one roundtrip per chunk instead of one per row.
 */
function bulkCaseUpdate(PDO $pdo, string $table, string $setCol, string $whereCol, array $map, int $chunkSize, callable $log, string $prefix, bool $dryRun, string $extraSet = ''): int {
    if (empty($map)) return 0;
    $total = 0;
    $chunks = array_chunk($map, $chunkSize, true);
    $chunkNum = 0;
    $numChunks = count($chunks);
    foreach ($chunks as $chunk) {
        $chunkNum++;
        $cases = '';
        $ids = [];
        foreach ($chunk as $id => $val) {
            $escapedVal = $pdo->quote($val);
            $cases .= " WHEN {$pdo->quote($id)} THEN {$escapedVal}";
            $ids[] = $pdo->quote($id);
        }
        $idList = implode(',', $ids);
        $extraSetClause = $extraSet ? ", $extraSet" : '';
        $sql = "UPDATE {$table} SET {$setCol} = CASE {$whereCol}{$cases} END{$extraSetClause} WHERE {$whereCol} IN ({$idList})";

        if (!$dryRun) {
            $affected = $pdo->exec($sql);
            $total += $affected;
        } else {
            $total += count($chunk);
        }
        $log("{$prefix}    Chunk {$chunkNum}/{$numChunks}: " . count($chunk) . " rows");
    }
    return $total;
}

// ── PHASE 1: Populate consignment_product_registry ──────────
if ($onlyPhase === null || $onlyPhase === 1) {
    $log("{$prefix}=== FASE 1: consignment_product_registry ===");

    // 1a. Fetch all consignment products (one query)
    $stmt = $pdo->query("
        SELECT p.sku, p.name, p.supplier_pessoa_id, p.percentual_consignacao,
               p.source, p.status, p.created_at
        FROM products p
        WHERE p.source = 'consignacao'
          AND p.status != 'archived'
    ");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $log("{$prefix}  Produtos consignados encontrados: " . count($rows));

    // 1b. Pre-load existing registry product_ids (one query — avoids N+1)
    $existingSet = [];
    $exStmt = $pdo->query("SELECT product_id FROM consignment_product_registry");
    foreach ($exStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $existingSet[(int) $r['product_id']] = true;
    }
    $log("{$prefix}  Registros já existentes no registry: " . count($existingSet));

    // 1c. Pre-load intake map: product_id => {intake_id, received_at} (one query)
    $intakeMap = [];
    try {
        $iStmt = $pdo->query("
            SELECT crp.product_id, crp.intake_id, cr.created_at AS received_at
            FROM consignacao_recebimento_produtos crp
            JOIN consignacao_recebimentos cr ON cr.id = crp.intake_id
        ");
        foreach ($iStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $intakeMap[(int) $r['product_id']] = $r;
        }
        $log("{$prefix}  Mapeamento intake carregado: " . count($intakeMap) . " registros");
    } catch (\Throwable $e) {
        $log("{$prefix}  [AVISO] consignacao_recebimento_produtos: " . $e->getMessage());
    }

    // 1d. Pre-load consignment_items map: product_sku => consignment_id (one query)
    $ciMap = [];
    try {
        $ciStmt = $pdo->query("SELECT ci.product_sku, ci.consignment_id FROM consignment_items ci");
        foreach ($ciStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $ciMap[(int) $r['product_sku']] = (int) $r['consignment_id'];
        }
        $log("{$prefix}  Mapeamento consignment_items carregado: " . count($ciMap) . " registros");
    } catch (\Throwable $e) {
        $log("{$prefix}  [AVISO] consignment_items: " . $e->getMessage());
    }

    // 1e. Loop using pre-loaded maps (no per-row DB queries)
    $created = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        $productId = (int) $row['sku'];

        if (isset($existingSet[$productId])) {
            $skipped++;
            continue;
        }

        $intakeId = null;
        $consignmentId = null;
        $receivedAt = $row['created_at'];

        if (isset($intakeMap[$productId])) {
            $intakeId = (int) $intakeMap[$productId]['intake_id'];
            $receivedAt = $intakeMap[$productId]['received_at'] ?: $receivedAt;
        }
        if (isset($ciMap[$productId])) {
            $consignmentId = $ciMap[$productId];
        }

        // Determine origin_type based on intake/consignment presence
        $originType = 'manual'; // default for backfill
        if ($intakeId !== null) {
            $originType = 'pre_lote';
        } elseif ($consignmentId !== null) {
            $originType = 'sku_consignment';
        }

        $data = [
            'product_id' => $productId,
            'supplier_pessoa_id' => (int) ($row['supplier_pessoa_id'] ?? 0) ?: null,
            'consignment_supplier_original_id' => (int) ($row['supplier_pessoa_id'] ?? 0) ?: null,
            'origin_type' => $originType,
            'consignment_percent_snapshot' => $row['percentual_consignacao'] !== null ? (float) $row['percentual_consignacao'] : null,
            'intake_id' => $intakeId,
            'consignment_id' => $consignmentId,
            'received_at' => $receivedAt ? substr($receivedAt, 0, 10) : date('Y-m-d'),
            'consignment_status' => 'em_estoque', // Overridden in Phase 3
            'notes' => '[BACKFILL] Auto-populated from products table.',
        ];

        if (!$dryRun) {
            try {
                $registry->create($data);
            } catch (\Throwable $e) {
                $log("{$prefix}  [ERRO] Produto #{$productId}: " . $e->getMessage());
                continue;
            }
        }
        $created++;

        if ($created % 500 === 0) {
            $log("{$prefix}    Progresso: {$created} criados...");
        }
    }

    $log("{$prefix}  Fase 1 concluída: {$created} criados, {$skipped} já existiam.");
}

// ── PHASE 2: Populate consignment_sales from ledger ─────────
if ($onlyPhase === null || $onlyPhase === 2) {
    $log("{$prefix}=== FASE 2: consignment_sales ===");

    // 2a. Fetch all credit movements for sales (one query)
    $stmt = $pdo->query("
        SELECT m.id, m.voucher_account_id, m.vendor_pessoa_id, m.order_id,
               m.order_item_id, m.product_id, m.sku, m.product_name,
               m.quantity, m.unit_price, m.line_total, m.percent,
               m.credit_amount, m.sold_at, m.buyer_name, m.event_at
        FROM cupons_creditos_movimentos m
        WHERE m.type = 'credito'
          AND m.event_type = 'sale'
          AND m.order_id > 0
    ");
    $creditRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $log("{$prefix}  Movimentos de crédito encontrados: " . count($creditRows));

    // 2b. Build debit map for reversals (one query)
    $debitStmt = $pdo->query("
        SELECT m.order_id, m.order_item_id, m.event_type, m.event_at
        FROM cupons_creditos_movimentos m
        WHERE m.type = 'debito'
          AND m.order_id > 0
    ");
    $debitMap = [];
    foreach ($debitStmt->fetchAll(\PDO::FETCH_ASSOC) as $d) {
        $key = (int) $d['order_id'] . '-' . (int) $d['order_item_id'];
        $debitMap[$key] = $d;
    }
    $log("{$prefix}  Débitos encontrados: " . count($debitMap));

    // 2c. Pre-load existing sales keys (one query — avoids N+1)
    $existingSalesSet = [];
    $exStmt = $pdo->query("SELECT order_id, order_item_id, product_id FROM consignment_sales");
    foreach ($exStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $k = (int) $r['order_id'] . '-' . (int) $r['order_item_id'] . '-' . (int) $r['product_id'];
        $existingSalesSet[$k] = true;
    }
    $log("{$prefix}  Sales já existentes: " . count($existingSalesSet));

    // 2d. Loop using pre-loaded maps
    $created = 0;
    $skipped = 0;
    foreach ($creditRows as $row) {
        $orderId = (int) $row['order_id'];
        $orderItemId = (int) $row['order_item_id'];
        $productId = (int) $row['product_id'];

        if ($orderId <= 0 || $orderItemId <= 0) {
            $skipped++;
            continue;
        }

        $k = $orderId . '-' . $orderItemId . '-' . $productId;
        if (isset($existingSalesSet[$k])) {
            $skipped++;
            continue;
        }

        $key = $orderId . '-' . $orderItemId;
        $isReversed = isset($debitMap[$key]);

        $grossAmount = (float) ($row['line_total'] ?? 0);
        $netAmount = (float) ($row['line_total'] ?? 0);
        $discountAmount = max(0, $grossAmount - $netAmount);

        $data = [
            'product_id' => $productId > 0 ? $productId : null,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'supplier_pessoa_id' => (int) $row['vendor_pessoa_id'] ?: null,
            'ledger_credit_movement_id' => (int) $row['id'],
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'net_amount' => $netAmount,
            'percent_applied' => $row['percent'] !== null ? (float) $row['percent'] : 0,
            'credit_amount' => (float) ($row['credit_amount'] ?? 0),
            'commission_formula_version' => 'v1_backfill',
            'sold_at' => $row['sold_at'] ?: $row['event_at'],
            'sale_status' => $isReversed ? 'revertida' : 'ativa',
            'payout_status' => 'pendente',
        ];

        if ($isReversed) {
            $debit = $debitMap[$key];
            $data['reversed_at'] = $debit['event_at'];
            $data['reversal_event_type'] = $debit['event_type'];
        }

        if (!$dryRun) {
            try {
                $sales->create($data);
            } catch (\Throwable $e) {
                $log("{$prefix}  [ERRO] Sale order={$orderId} item={$orderItemId}: " . $e->getMessage());
                continue;
            }
        }
        $created++;

        if ($created % 100 === 0) {
            $log("{$prefix}    Progresso: {$created} sales criadas...");
        }
    }

    $log("{$prefix}  Fase 2 concluída: {$created} criados, {$skipped} já existiam ou inválidos.");
}

// ── PHASE 3: Derive consignment_status ──────────────────────
if ($onlyPhase === null || $onlyPhase === 3) {
    $log("{$prefix}=== FASE 3: derivar consignment_status ===");

    // 3a. Fetch all consignment products
    $stmt = $pdo->query("
        SELECT p.sku, p.status, p.source
        FROM products p
        WHERE p.source = 'consignacao'
          AND p.status != 'archived'
    ");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $log("{$prefix}  Produtos a processar: " . count($rows));

    // 3b. Build writeoff map (one query)
    $writeoffMap = [];
    try {
        $wStmt = $pdo->query("SELECT product_id, destination FROM produto_baixas WHERE product_id IS NOT NULL");
        foreach ($wStmt->fetchAll(\PDO::FETCH_ASSOC) as $w) {
            $writeoffMap[(int) $w['product_id']] = $w['destination'];
        }
    } catch (\Throwable $e) {
        $log("{$prefix}  [AVISO] produto_baixas: " . $e->getMessage());
    }
    $log("{$prefix}  Writeoffs carregados: " . count($writeoffMap));

    // 3c. Build sales map: product_id => {active, reversed, pago} (one query)
    $salesMap = [];
    $salesStmt = $pdo->query("
        SELECT product_id, sale_status, payout_status
        FROM consignment_sales
        WHERE product_id IS NOT NULL AND product_id > 0
    ");
    foreach ($salesStmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
        $pid = (int) $s['product_id'];
        if (!isset($salesMap[$pid])) {
            $salesMap[$pid] = ['active' => false, 'reversed' => false, 'pago' => false];
        }
        if ($s['sale_status'] === 'ativa') {
            $salesMap[$pid]['active'] = true;
        }
        if ($s['sale_status'] === 'revertida') {
            $salesMap[$pid]['reversed'] = true;
        }
        if ($s['payout_status'] === 'pago') {
            $salesMap[$pid]['pago'] = true;
        }
    }
    $log("{$prefix}  Sales map carregado: " . count($salesMap) . " produtos");

    $destToStatus = [
        'devolucao_fornecedor' => 'devolvido',
        'doacao' => 'doado',
        'nao_localizado' => 'descartado',
        'lixo' => 'descartado',
    ];

    // 3d. Derive status using maps only (no per-row queries)
    $updated = 0;
    $batchUpdates = [];
    foreach ($rows as $row) {
        $productId = (int) $row['sku'];
        $pStatus = (string) $row['status'];
        $status = null;

        // 1. Check writeoff
        if (isset($writeoffMap[$productId])) {
            $dest = $writeoffMap[$productId];
            $status = $destToStatus[$dest] ?? 'descartado';
        }

        // 2. No writeoff — derive from sales
        if ($status === null) {
            $info = $salesMap[$productId] ?? ['active' => false, 'reversed' => false, 'pago' => false];

            if (!$info['active']) {
                if ($info['reversed']) {
                    $status = in_array($pStatus, ['disponivel', 'draft', 'reservado'], true) ? 'em_estoque' : 'descartado';
                } else {
                    $status = in_array($pStatus, ['disponivel', 'draft', 'reservado', 'esgotado'], true) ? 'em_estoque' : 'descartado';
                }
            } else {
                $status = 'vendido_pendente';
            }
        }

        if ($status !== null) {
            $batchUpdates[$productId] = $status;
            $updated++;
        }
    }

    $log("{$prefix}  Status derivados: {$updated} produtos");

    // 3e. Bulk UPDATE consignment_product_registry via CASE/WHEN (chunks of 500)
    $log("{$prefix}  Atualizando consignment_product_registry (bulk)...");
    $regAffected = bulkCaseUpdate(
        $pdo, 'consignment_product_registry', 'consignment_status', 'product_id',
        $batchUpdates, 500, $log, $prefix, $dryRun,
        'status_changed_at = NOW()'
    );
    $log("{$prefix}  Registry: {$regAffected} rows atualizados.");

    // 3f. Bulk UPDATE products via CASE/WHEN (chunks of 500)
    $log("{$prefix}  Atualizando products.consignment_status (bulk)...");
    $prodAffected = bulkCaseUpdate(
        $pdo, 'products', 'consignment_status', 'sku',
        $batchUpdates, 500, $log, $prefix, $dryRun
    );
    $log("{$prefix}  Products: {$prodAffected} rows atualizados.");

    $log("{$prefix}  Fase 3 concluída.");
}

// ── PHASE 4: Reconcile legacy payouts (FIFO) ───────────────
if ($onlyPhase === null || $onlyPhase === 4) {
    $log("{$prefix}=== FASE 4: conciliar payouts legados ===");

    // 4a. Find all consignment vouchers (one query)
    $vStmt = $pdo->query("
        SELECT id, pessoa_id FROM cupons_creditos
        WHERE (scope = 'consignacao' OR label LIKE 'Crédito consignação%')
          AND type = 'credito'
    ");
    $voucherRows = $vStmt->fetchAll(\PDO::FETCH_ASSOC);
    $log("{$prefix}  Vouchers de consignação: " . count($voucherRows));

    // 4b. Pre-load all legacy payout movements (one query)
    $lpStmt = $pdo->query("
        SELECT id, voucher_account_id, credit_amount, event_at
        FROM cupons_creditos_movimentos
        WHERE type = 'debito'
          AND event_type = 'payout'
          AND payout_id IS NULL
        ORDER BY event_at ASC
    ");
    $allLegacyPayouts = [];
    foreach ($lpStmt->fetchAll(\PDO::FETCH_ASSOC) as $lp) {
        $vid = (int) $lp['voucher_account_id'];
        $allLegacyPayouts[$vid][] = $lp;
    }
    $log("{$prefix}  Payouts legados carregados: " . array_sum(array_map('count', $allLegacyPayouts)));

    // 4c. Pre-load all pending active sales by supplier (one query)
    $psStmt = $pdo->query("
        SELECT id, supplier_pessoa_id, credit_amount as amount, product_id
        FROM consignment_sales
        WHERE sale_status = 'ativa' AND payout_status = 'pendente'
        ORDER BY sold_at ASC, id ASC
    ");
    $pendingSalesBySupplier = [];
    foreach ($psStmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
        $sid = (int) $s['supplier_pessoa_id'];
        $pendingSalesBySupplier[$sid][] = $s;
    }
    $log("{$prefix}  Vendas pendentes por fornecedora: " . count($pendingSalesBySupplier) . " fornecedoras");

    $reconciledSales = 0;
    $unreconciledPayouts = 0;
    $voucherIdx = 0;
    $totalVouchers = count($voucherRows);

    // Collect all bulk updates for final execution
    $salePayoutUpdates = [];    // sale_id => [paid_at]
    $regProdStatusUpdates = []; // product_id => 'vendido_pago'
    $unreconciledNotesList = [];    // movement_id => note string

    // 4d. Reconcile per voucher (no per-row queries in inner loop)
    foreach ($voucherRows as $vRow) {
        $voucherIdx++;
        $voucherId = (int) $vRow['id'];
        $supplierPessoaId = (int) $vRow['pessoa_id'];

        $legacyPayouts = $allLegacyPayouts[$voucherId] ?? [];
        if (empty($legacyPayouts)) {
            continue;
        }

        $pendingSales = $pendingSalesBySupplier[$supplierPessoaId] ?? [];
        $salesIdx = 0;

        foreach ($legacyPayouts as $lp) {
            $payoutAmount = (float) $lp['credit_amount'];
            $payoutDate = $lp['event_at'];
            $movementId = (int) $lp['id'];
            $consumed = 0.0;
            $matchedIds = [];

            while ($salesIdx < count($pendingSales) && $consumed < $payoutAmount - 0.01) {
                $sale = $pendingSales[$salesIdx];
                $saleAmount = (float) $sale['amount'];
                $consumed += $saleAmount;
                $matchedIds[] = $sale;
                $salesIdx++;
            }

            if (!empty($matchedIds) && abs($consumed - $payoutAmount) < 0.02) {
                foreach ($matchedIds as $matched) {
                    $salePayoutUpdates[(int) $matched['id']] = [
                        'paid_at' => $payoutDate,
                    ];
                    $pid = (int) $matched['product_id'];
                    if ($pid > 0) {
                        $regProdStatusUpdates[$pid] = 'vendido_pago';
                    }
                }
                $reconciledSales += count($matchedIds);
                $log("{$prefix}    Payout mov#{$movementId} R$ " . number_format($payoutAmount, 2, '.', '') . ": " . count($matchedIds) . " sales conciliadas");
            } else {
                $unreconciledNotesList[$movementId] = "\n[BACKFILL] Payout legado não conciliado - R$ " . number_format($payoutAmount, 2, '.', '');
                $unreconciledPayouts++;
                $log("{$prefix}    Payout mov#{$movementId} R$ " . number_format($payoutAmount, 2, '.', '') . ": NÃO conciliado");
            }
        }

        if ($voucherIdx % 20 === 0) {
            $log("{$prefix}    Voucher {$voucherIdx}/{$totalVouchers} processado...");
        }
    }

    $log("{$prefix}  Reconciliação calculada: {$reconciledSales} sales, {$unreconciledPayouts} não conciliados.");

    // 4e. Execute bulk sale payout updates
    if (!$dryRun && !empty($salePayoutUpdates)) {
        $log("{$prefix}  Gravando sale payout updates (" . count($salePayoutUpdates) . ")...");
        $saleUpd = $pdo->prepare("UPDATE consignment_sales SET payout_status = 'pago', paid_at = ? WHERE id = ?");
        $idx = 0;
        foreach ($salePayoutUpdates as $saleId => $info) {
            $saleUpd->execute([$info['paid_at'], $saleId]);
            $idx++;
            if ($idx % 50 === 0) $log("{$prefix}    Sale updates: {$idx}/" . count($salePayoutUpdates));
        }
        $log("{$prefix}    Sale updates completo: {$idx}");
    }

    // 4f. Bulk UPDATE registry + products for vendido_pago
    if (!$dryRun && !empty($regProdStatusUpdates)) {
        $log("{$prefix}  Atualizando registry/products para vendido_pago (" . count($regProdStatusUpdates) . ")...");
        bulkCaseUpdate($pdo, 'consignment_product_registry', 'consignment_status', 'product_id',
            $regProdStatusUpdates, 500, $log, $prefix, $dryRun, 'status_changed_at = NOW()');
        bulkCaseUpdate($pdo, 'products', 'consignment_status', 'sku',
            $regProdStatusUpdates, 500, $log, $prefix, $dryRun);
        $log("{$prefix}    Registry/products atualizado.");
    }

    // 4g. Bulk update unreconciled notes
    if (!$dryRun && !empty($unreconciledNotesList)) {
        $log("{$prefix}  Gravando notas de payouts não conciliados (" . count($unreconciledNotesList) . ")...");
        $noteUpd = $pdo->prepare("UPDATE cupons_creditos_movimentos SET event_notes = CONCAT(COALESCE(event_notes, ''), ?) WHERE id = ?");
        $idx = 0;
        foreach ($unreconciledNotesList as $movId => $note) {
            $noteUpd->execute([$note, $movId]);
            $idx++;
        }
        $log("{$prefix}    Notas gravadas: {$idx}");
    }

    $log("{$prefix}  Fase 4 concluída: {$reconciledSales} vendas conciliadas, {$unreconciledPayouts} payouts não conciliados.");
}

// ── PHASE 5: Backfill scope on voucher accounts ─────────────
if ($onlyPhase === null || $onlyPhase === 5) {
    $log("{$prefix}=== FASE 5: backfill scope em cupons_creditos ===");

    $sql = "
        UPDATE cupons_creditos
        SET scope = 'consignacao'
        WHERE type = 'credito'
          AND label LIKE 'Crédito consignação%'
          AND (scope IS NULL OR scope = '')
    ";

    if ($dryRun) {
        $countStmt = $pdo->query("
            SELECT COUNT(*) FROM cupons_creditos
            WHERE type = 'credito'
              AND label LIKE 'Crédito consignação%'
              AND (scope IS NULL OR scope = '')
        ");
        $count = (int) $countStmt->fetchColumn();
        $log("{$prefix}  Seriam atualizados: {$count} vouchers.");
    } else {
        $affected = $pdo->exec($sql);
        $log("{$prefix}  Fase 5 concluída: {$affected} vouchers atualizados com scope='consignacao'.");
    }
}

// ── PHASE 6: Sync products.consignment_status from registry ─
if ($onlyPhase === null || $onlyPhase === 6) {
    $log("{$prefix}=== FASE 6: sync products.consignment_status ===");

    $sql = "
        UPDATE products p
        JOIN consignment_product_registry r ON r.product_id = p.sku
        SET p.consignment_status = r.consignment_status
        WHERE r.consignment_status IS NOT NULL
          AND (p.consignment_status IS NULL OR p.consignment_status != r.consignment_status)
    ";

    if ($dryRun) {
        $countStmt = $pdo->query("
            SELECT COUNT(*) FROM products p
            JOIN consignment_product_registry r ON r.product_id = p.sku
            WHERE r.consignment_status IS NOT NULL
              AND (p.consignment_status IS NULL OR p.consignment_status != r.consignment_status)
        ");
        $count = (int) $countStmt->fetchColumn();
        $log("{$prefix}  Seriam sincronizados: {$count} produtos.");
    } else {
        $affected = $pdo->exec($sql);
        $log("{$prefix}  Fase 6 concluída: {$affected} produtos sincronizados.");
    }
}

$log("{$prefix}=== Backfill completo. ===");
$elapsed = number_format(microtime(true) - $startTime, 1);
$log("Tempo total: {$elapsed}s");
exit(0);
