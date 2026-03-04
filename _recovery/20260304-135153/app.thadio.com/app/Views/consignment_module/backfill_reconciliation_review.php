<?php
/** @var array $categories */
/** @var array $voucherDivergences */
/** @var array $summary */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */

$categories = $categories ?? ['A' => [], 'C' => [], 'B' => [], 'D' => []];
$voucherDivergences = $voucherDivergences ?? [];
$summary = $summary ?? [];
$success = $success ?? '';
$errors = $errors ?? [];
$esc = is_callable($esc ?? null)
    ? $esc
    : static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$fmtDate = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '(sem data)';
?>

<style>
  .recon-card { margin:12px 0; padding:16px 18px; border-radius:10px; border:1px solid var(--line); background:var(--panel); }
  .recon-card.cat-a { border-left:4px solid #22c55e; }
  .recon-card.cat-c { border-left:4px solid #22c55e; }
  .recon-card.cat-b { border-left:4px solid #3b82f6; }
  .recon-card.cat-d { border-left:4px solid #ef4444; }
  .recon-card.cat-v { border-left:4px solid #f59e0b; }
  .recon-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; }
  .recon-header h3 { margin:0; font-size:15px; }
  .recon-meta { font-size:13px; color:var(--muted); margin-top:4px; }
  .recon-meta strong { color:var(--ink); }
  .recon-badge { display:inline-block; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
  .recon-badge.green { background:#dcfce7; color:#166534; }
  .recon-badge.blue { background:#dbeafe; color:#1e40af; }
  .recon-badge.red { background:#fee2e2; color:#991b1b; }
  .recon-badge.yellow { background:#fef3c7; color:#92400e; }
  .recon-badge.gray { background:#f3f4f6; color:#4b5563; }
  .recon-sales-table { width:100%; font-size:12px; border-collapse:collapse; margin-top:8px; }
  .recon-sales-table th { text-align:left; padding:4px 6px; border-bottom:1px solid var(--line); font-weight:600; color:var(--muted); font-size:11px; text-transform:uppercase; }
  .recon-sales-table td { padding:4px 6px; border-bottom:1px solid #f3f4f6; }
  .recon-sales-table tr.matched { background:#f0fdf4; }
  .recon-sales-table tr.remaining { background:#fffbeb; }
  .recon-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:12px; }
  .recon-actions .btn { font-size:13px; padding:6px 14px; }
  .summary-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px; margin:16px 0; }
  .summary-card { padding:14px; border-radius:10px; text-align:center; border:1px solid var(--line); }
  .summary-card .num { font-size:28px; font-weight:700; }
  .summary-card .lbl { font-size:12px; color:var(--muted); margin-top:2px; }
  .cat-section { margin:28px 0 16px; }
  .cat-section h2 { font-size:17px; display:flex; align-items:center; gap:8px; margin-bottom:4px; }
  .cat-section .cat-desc { font-size:13px; color:var(--muted); margin-bottom:12px; }
  .notes-input { width:100%; max-width:400px; padding:4px 8px; font-size:12px; border:1px solid var(--line); border-radius:6px; }
  .diag-box { margin-top:8px; padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; font-size:13px; }
  .diag-box.info { background:#eff6ff; border-color:#bfdbfe; }
  .bulk-bar { position:sticky; top:0; z-index:10; background:var(--panel); padding:12px 16px; border-radius:10px; border:1px solid var(--line); margin:12px 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
  details summary { cursor:pointer; font-size:13px; color:var(--accent); font-weight:500; }
  details summary:hover { text-decoration:underline; }
  .mov-notes { font-size:12px; color:#9ca3af; margin-top:2px; font-style:italic; }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Revisão de Reconciliação do Backfill</h1>
    <div class="subtitle">Analise e decida sobre cada pagamento legado não vinculado.</div>
  </div>
  <a class="btn ghost" href="consignacao-inconsistencias.php">← Inconsistências</a>
</div>

<?php if (!empty($errors)): ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert error"><?= $esc($err) ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($success !== ''): ?>
  <div class="alert success"><?= $esc($success) ?></div>
<?php endif; ?>

<!-- SUMMARY -->
<div class="summary-grid">
  <div class="summary-card" style="background:#f0fdf4;">
    <div class="num" style="color:#16a34a;"><?= $summary['cat_a'] + $summary['cat_c'] ?></div>
    <div class="lbl">Cat A+C — Já reconciliados</div>
  </div>
  <div class="summary-card" style="background:#eff6ff;">
    <div class="num" style="color:#2563eb;"><?= $summary['cat_b'] ?></div>
    <div class="lbl">Cat B — Subset match exato</div>
  </div>
  <div class="summary-card" style="background:#fef2f2;">
    <div class="num" style="color:#dc2626;"><?= $summary['cat_d'] ?></div>
    <div class="lbl">Cat D — Irreconciliáveis</div>
  </div>
  <div class="summary-card" style="background:#fffbeb;">
    <div class="num" style="color:#d97706;"><?= $summary['voucher_div'] ?></div>
    <div class="lbl">Vouchers — Saldo divergente</div>
  </div>
  <div class="summary-card">
    <div class="num"><?= $summary['total'] ?></div>
    <div class="lbl">Total movimentos</div>
  </div>
</div>

<!-- BULK ACTIONS BAR -->
<div class="bulk-bar">
  <strong style="font-size:14px;">Ações em lote:</strong>
  <form method="post" action="consignacao-inconsistencias.php?action=backfill_action" id="bulkLinkForm"
        onsubmit="return confirm('Vincular TODOS os <?= $summary['cat_a'] + $summary['cat_c'] ?> movimentos da Cat A+C como já reconciliados?');">
    <input type="hidden" name="reconciliation_action" value="bulk_link_ac">
    <button type="submit" class="btn success" <?= ($summary['cat_a'] + $summary['cat_c']) === 0 ? 'disabled' : '' ?>>
      ✅ Vincular todos A+C (<?= $summary['cat_a'] + $summary['cat_c'] ?>)
    </button>
  </form>
  <span style="font-size:12px;color:var(--muted);">Use os botões individuais abaixo para decidir caso a caso.</span>
</div>


<?php /* ============================================================
        CATEGORY A — Already reconciled
        ============================================================ */ ?>
<div class="cat-section">
  <h2>✅ Categoria A — Já Reconciliados pelo Phase 4 <span class="recon-badge green"><?= $summary['cat_a'] ?></span></h2>
  <div class="cat-desc">
    O backfill Phase 4 já marcou as vendas como <code>pago</code> via FIFO. A soma das vendas pago = valor do payout.
    Ação sugerida: vincular o movimento como já reconciliado.
  </div>

  <?php if (empty($categories['A'])): ?>
    <div class="alert success">Nenhum movimento nesta categoria.</div>
  <?php else: ?>
    <?php foreach ($categories['A'] as $mov): ?>
      <div class="recon-card cat-a">
        <div class="recon-header">
          <div>
            <h3>mov#<?= $mov['mov_id'] ?> — <?= $esc($mov['supplier_name']) ?></h3>
            <div class="recon-meta">
              Payout: <strong><?= $fmt($mov['credit_amount']) ?></strong>
              | Data: <?= $fmtDate($mov['event_at']) ?>
              | Vendas pago: <strong><?= count($mov['pago_sales']) ?></strong> = <?= $fmt($mov['pago_total']) ?>
              <?php if (!empty($mov['pending_sales'])): ?>
                | Pendentes: <?= count($mov['pending_sales']) ?> = <?= $fmt($mov['pending_total']) ?>
              <?php endif; ?>
            </div>
            <?php
              $rawNotes = trim(str_replace("\n", ' ', $mov['event_notes'] ?? ''));
              $rawNotes = preg_replace('/\[BACKFILL\][^|]*/', '', $rawNotes);
              $rawNotes = trim($rawNotes);
              if ($rawNotes): ?>
              <div class="mov-notes">Notas: <?= $esc($rawNotes) ?></div>
            <?php endif; ?>
          </div>
          <span class="recon-badge green">✅ Match exato</span>
        </div>

        <details>
          <summary>Ver <?= count($mov['pago_sales']) ?> vendas pago</summary>
          <table class="recon-sales-table">
            <thead><tr><th>Sale ID</th><th>SKU</th><th>Produto</th><th>Valor</th><th>Vendido em</th></tr></thead>
            <tbody>
              <?php foreach ($mov['pago_sales'] as $s): ?>
                <tr class="matched">
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>

        <div class="recon-actions">
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action">
            <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
            <input type="hidden" name="reconciliation_action" value="link_existing">
            <button type="submit" class="btn success">✅ Vincular como reconciliado</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<?php /* ============================================================
        CATEGORY C — Match pago with remaining pending
        ============================================================ */ ?>
<div class="cat-section">
  <h2>✅ Categoria C — Match Pago + Pendentes Restantes <span class="recon-badge green"><?= $summary['cat_c'] ?></span></h2>
  <div class="cat-desc">
    Vendas pago somam exatamente o valor do payout. Existem vendas pendentes extras não relacionadas a este pagamento.
  </div>

  <?php if (empty($categories['C'])): ?>
    <div class="alert success">Nenhum movimento nesta categoria.</div>
  <?php else: ?>
    <?php foreach ($categories['C'] as $mov): ?>
      <div class="recon-card cat-c">
        <div class="recon-header">
          <div>
            <h3>mov#<?= $mov['mov_id'] ?> — <?= $esc($mov['supplier_name']) ?></h3>
            <div class="recon-meta">
              Payout: <strong><?= $fmt($mov['credit_amount']) ?></strong>
              | Data: <?= $fmtDate($mov['event_at']) ?>
              | Vendas pago: <strong><?= count($mov['pago_sales']) ?></strong> = <?= $fmt($mov['pago_total']) ?>
              | Pendentes restantes: <?= count($mov['pending_sales']) ?> = <?= $fmt($mov['pending_total']) ?>
            </div>
            <?php
              $rawNotes = trim(str_replace("\n", ' ', $mov['event_notes'] ?? ''));
              $rawNotes = preg_replace('/\[BACKFILL\][^|]*/', '', $rawNotes);
              $rawNotes = trim($rawNotes);
              if ($rawNotes): ?>
              <div class="mov-notes">Notas: <?= $esc($rawNotes) ?></div>
            <?php endif; ?>
          </div>
          <span class="recon-badge green">✅ Match exato</span>
        </div>

        <details>
          <summary>Ver <?= count($mov['pago_sales']) ?> vendas pago + <?= count($mov['pending_sales']) ?> pendentes</summary>
          <table class="recon-sales-table">
            <thead><tr><th>Sale ID</th><th>SKU</th><th>Produto</th><th>Valor</th><th>Status</th><th>Vendido em</th></tr></thead>
            <tbody>
              <?php foreach ($mov['pago_sales'] as $s): ?>
                <tr class="matched">
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><span class="recon-badge green">pago</span></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($mov['pending_sales'] as $s): ?>
                <tr class="remaining">
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><span class="recon-badge yellow">pendente</span></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>

        <div class="recon-actions">
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action">
            <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
            <input type="hidden" name="reconciliation_action" value="link_existing">
            <button type="submit" class="btn success">✅ Vincular como reconciliado</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<?php /* ============================================================
        CATEGORY B — Subset-sum exact match
        ============================================================ */ ?>
<div class="cat-section">
  <h2>🔵 Categoria B — Subset-Sum Match Exato <span class="recon-badge blue"><?= $summary['cat_b'] ?></span></h2>
  <div class="cat-desc">
    Foi encontrado um subconjunto de vendas pendentes cuja soma é <strong>exatamente</strong> igual ao valor do payout.
    Ação sugerida: aprovar a reconciliação — as vendas serão marcadas como <code>pago</code>.
  </div>

  <?php if (empty($categories['B'])): ?>
    <div class="alert success">Nenhum movimento nesta categoria.</div>
  <?php else: ?>
    <?php foreach ($categories['B'] as $mov):
      $matchedIds = array_map(fn($s) => (int) $s['id'], $mov['matched_sales']);
      $matchedTotal = array_sum(array_map(fn($s) => (float) $s['credit_amount'], $mov['matched_sales']));
      $remainingSales = $mov['remaining_sales'] ?? [];
      $remainingTotal = array_sum(array_map(fn($s) => (float) $s['credit_amount'], $remainingSales));
    ?>
      <div class="recon-card cat-b">
        <div class="recon-header">
          <div>
            <h3>mov#<?= $mov['mov_id'] ?> — <?= $esc($mov['supplier_name']) ?></h3>
            <div class="recon-meta">
              Payout: <strong><?= $fmt($mov['credit_amount']) ?></strong>
              | Data: <?= $fmtDate($mov['event_at']) ?>
              | Match: <strong><?= count($mov['matched_sales']) ?></strong> vendas = <?= $fmt($matchedTotal) ?>
              <?php if (!empty($remainingSales)): ?>
                | Sobram pendentes: <?= count($remainingSales) ?> = <?= $fmt($remainingTotal) ?>
              <?php endif; ?>
            </div>
            <?php
              $rawNotes = trim(str_replace("\n", ' ', $mov['event_notes'] ?? ''));
              $rawNotes = preg_replace('/\[BACKFILL\][^|]*/', '', $rawNotes);
              $rawNotes = trim($rawNotes);
              if ($rawNotes): ?>
              <div class="mov-notes">Notas: <?= $esc($rawNotes) ?></div>
            <?php endif; ?>
          </div>
          <span class="recon-badge blue">🎯 Subset exato</span>
        </div>

        <details>
          <summary>Ver <?= count($mov['matched_sales']) ?> vendas matched<?= !empty($remainingSales) ? ' + ' . count($remainingSales) . ' pendentes restantes' : '' ?></summary>
          <table class="recon-sales-table">
            <thead><tr><th></th><th>Sale ID</th><th>SKU</th><th>Produto</th><th>Valor</th><th>Vendido em</th></tr></thead>
            <tbody>
              <?php foreach ($mov['matched_sales'] as $s): ?>
                <tr class="matched">
                  <td>✅</td>
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($remainingSales as $s): ?>
                <tr class="remaining">
                  <td>⏸️</td>
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>

        <div class="recon-actions">
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action"
                onsubmit="return confirm('Aprovar reconciliação de mov#<?= $mov['mov_id'] ?>?\n\n<?= count($mov['matched_sales']) ?> vendas serão marcadas como pago.');">
            <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
            <input type="hidden" name="reconciliation_action" value="approve_subset">
            <?php foreach ($matchedIds as $sid): ?>
              <input type="hidden" name="sale_ids[]" value="<?= $sid ?>">
            <?php endforeach; ?>
            <input type="hidden" name="notes" value="Subset-sum exato: <?= count($mov['matched_sales']) ?> vendas">
            <button type="submit" class="btn primary">🎯 Aprovar reconciliação</button>
          </form>
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action">
            <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
            <input type="hidden" name="reconciliation_action" value="skip">
            <input type="text" name="notes" class="notes-input" placeholder="Motivo (opcional)...">
            <button type="submit" class="btn ghost">⏭️ Pular</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<?php /* ============================================================
        CATEGORY D — Cannot auto-reconcile
        ============================================================ */ ?>
<div class="cat-section">
  <h2>❌ Categoria D — Irreconciliáveis Automaticamente <span class="recon-badge red"><?= $summary['cat_d'] ?></span></h2>
  <div class="cat-desc">
    Nenhum subconjunto de vendas soma exatamente ao valor do payout. Requer decisão manual.
  </div>

  <?php if (empty($categories['D'])): ?>
    <div class="alert success">Nenhum movimento nesta categoria. 🎉</div>
  <?php else: ?>
    <?php foreach ($categories['D'] as $mov):
      $diag = $mov['diagnostic'] ?? [];
    ?>
      <div class="recon-card cat-d">
        <div class="recon-header">
          <div>
            <h3>mov#<?= $mov['mov_id'] ?> — <?= $esc($mov['supplier_name']) ?></h3>
            <div class="recon-meta">
              Payout: <strong><?= $fmt($mov['credit_amount']) ?></strong>
              | Data: <?= $fmtDate($mov['event_at']) ?>
              | Pendentes: <?= count($mov['pending_sales']) ?> = <?= $fmt($mov['pending_total']) ?>
              <?php if ($mov['pago_total'] > 0): ?>
                | Pago: <?= count($mov['pago_sales']) ?> = <?= $fmt($mov['pago_total']) ?>
              <?php endif; ?>
              | Saldo voucher: <?= $fmt($mov['voucher_balance']) ?>
            </div>
            <?php
              $rawNotes = trim(str_replace("\n", ' ', $mov['event_notes'] ?? ''));
              $rawNotes = preg_replace('/\[BACKFILL\][^|]*/', '', $rawNotes);
              $rawNotes = trim($rawNotes);
              if ($rawNotes): ?>
              <div class="mov-notes">Notas: <?= $esc($rawNotes) ?></div>
            <?php endif; ?>
          </div>
          <span class="recon-badge red">❌ Sem match</span>
        </div>

        <!-- Diagnostic -->
        <?php if (!empty($diag)): ?>
          <div class="diag-box <?= ($diag['type'] ?? '') === 'already_reconciled' ? 'info' : '' ?>">
            <strong>Diagnóstico:</strong> <?= $esc($diag['message'] ?? '') ?>
            <?php if (!empty($diag['fifo_sum']) && ($diag['type'] ?? '') !== 'already_reconciled'): ?>
              <br>FIFO: <?= $diag['fifo_count'] ?> vendas = <?= $fmt($diag['fifo_sum']) ?> (gap <?= $fmt($diag['fifo_gap']) ?>)
            <?php endif; ?>
            <?php if (!empty($diag['near_miss_diff']) && $diag['near_miss_diff'] <= 1.00): ?>
              <br>⚡ <strong>Near-miss:</strong> <?= $fmt($diag['near_miss_sum']) ?> (diferença de apenas <?= $fmt($diag['near_miss_diff']) ?>)
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <details>
          <summary>Ver todas as vendas (<?= count($mov['pending_sales']) ?> pendentes<?= !empty($mov['pago_sales']) ? ' + ' . count($mov['pago_sales']) . ' pago' : '' ?>)</summary>
          <table class="recon-sales-table">
            <thead><tr><th>Sale ID</th><th>SKU</th><th>Produto</th><th>Valor</th><th>Status</th><th>Vendido em</th></tr></thead>
            <tbody>
              <?php foreach ($mov['pago_sales'] as $s): ?>
                <tr class="matched">
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><span class="recon-badge green">pago</span></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($mov['pending_sales'] as $s): ?>
                <tr>
                  <td>#<?= $s['id'] ?></td>
                  <td><?= $s['product_id'] ?></td>
                  <td><?= $esc($s['product_name'] ?? '—') ?></td>
                  <td><?= $fmt($s['credit_amount']) ?></td>
                  <td><span class="recon-badge yellow">pendente</span></td>
                  <td><?= $fmtDate($s['sold_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>

        <div class="recon-actions">
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action"
                onsubmit="return confirm('Marcar mov#<?= $mov['mov_id'] ?> como irreconciliável?');">
            <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
            <input type="hidden" name="reconciliation_action" value="skip">
            <input type="text" name="notes" class="notes-input" placeholder="Motivo / observação...">
            <button type="submit" class="btn warning">⏭️ Marcar como irreconciliável</button>
          </form>
          <?php if (!empty($diag['near_miss_diff']) && $diag['near_miss_diff'] <= 1.00 && !empty($diag['near_miss_sale_ids'])): ?>
            <form method="post" action="consignacao-inconsistencias.php?action=backfill_action"
                  onsubmit="return confirm('Aprovar near-miss de mov#<?= $mov['mov_id'] ?>?\nDiferença: <?= $fmt($diag['near_miss_diff']) ?>');">
              <input type="hidden" name="movement_id" value="<?= $mov['mov_id'] ?>">
              <input type="hidden" name="reconciliation_action" value="approve_near_miss">
              <input type="hidden" name="notes" value="Near-miss diff <?= $fmt($diag['near_miss_diff']) ?>">
              <?php foreach ($diag['near_miss_sale_ids'] as $sid): ?>
                <input type="hidden" name="sale_ids[]" value="<?= $sid ?>">
              <?php endforeach; ?>
              <button type="submit" class="btn ghost">
                ⚡ Aprovar near-miss (<?= count($diag['near_miss_sale_ids']) ?> vendas, diff <?= $fmt($diag['near_miss_diff']) ?>)
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<?php /* ============================================================
        VOUCHER BALANCE DIVERGENCES
        ============================================================ */ ?>
<div class="cat-section">
  <h2>💰 Divergências de Saldo de Voucher <span class="recon-badge yellow"><?= $summary['voucher_div'] ?></span></h2>
  <div class="cat-desc">
    O saldo armazenado em <code>cupons_creditos.balance</code> não confere com a soma dos movimentos.
    Ação sugerida: corrigir o saldo para o valor calculado.
  </div>

  <?php if (empty($voucherDivergences)): ?>
    <div class="alert success">Nenhuma divergência de saldo encontrada. 🎉</div>
  <?php else: ?>
    <?php foreach ($voucherDivergences as $v):
      $storedBalance = (float) $v['stored_balance'];
      $calcBalance = (float) $v['calc_balance'];
      $diff = $storedBalance - $calcBalance;
    ?>
      <div class="recon-card cat-v">
        <div class="recon-header">
          <div>
            <h3>Voucher #<?= $v['id'] ?> — <?= $esc($v['customer_name']) ?></h3>
            <div class="recon-meta">
              Saldo armazenado: <strong style="color:#dc2626;"><?= $fmt($storedBalance) ?></strong>
              | Saldo calculado: <strong style="color:#16a34a;"><?= $fmt($calcBalance) ?></strong>
              | Diferença: <strong style="color:#d97706;"><?= $fmt($diff) ?></strong>
            </div>
          </div>
          <span class="recon-badge yellow">⚠️ Divergência <?= $fmt(abs($diff)) ?></span>
        </div>

        <details>
          <summary>Ver <?= count($v['movements']) ?> movimentos</summary>
          <table class="recon-sales-table">
            <thead><tr><th>Mov ID</th><th>Tipo</th><th>Evento</th><th>Valor</th><th>Produto</th><th>Data</th></tr></thead>
            <tbody>
              <?php
                $running = 0;
                foreach ($v['movements'] as $m):
                  if ($m['type'] === 'credito') { $running += (float) $m['credit_amount']; $sign = '+'; $color = '#16a34a'; }
                  else { $running -= (float) $m['credit_amount']; $sign = '-'; $color = '#dc2626'; }
              ?>
                <tr>
                  <td>#<?= $m['id'] ?></td>
                  <td><?= $m['type'] ?></td>
                  <td><?= $m['event_type'] ?></td>
                  <td style="color:<?= $color ?>;font-weight:600;"><?= $sign ?><?= $fmt($m['credit_amount']) ?></td>
                  <td><?= $esc(mb_substr($m['product_name'] ?? '', 0, 30)) ?> <?= $m['sku'] ? '(SKU ' . $m['sku'] . ')' : '' ?></td>
                  <td><?= $fmtDate($m['event_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <tr style="border-top:2px solid var(--line);font-weight:700;">
                <td colspan="3">Saldo calculado</td>
                <td style="color:#16a34a;"><?= $fmt($running) ?></td>
                <td colspan="2">vs armazenado: <span style="color:#dc2626;"><?= $fmt($storedBalance) ?></span></td>
              </tr>
            </tbody>
          </table>
        </details>

        <div class="recon-actions">
          <form method="post" action="consignacao-inconsistencias.php?action=backfill_action"
                onsubmit="return confirm('Corrigir saldo do Voucher #<?= $v['id'] ?> de <?= $fmt($storedBalance) ?> para <?= $fmt($calcBalance) ?>?');">
            <input type="hidden" name="reconciliation_action" value="fix_voucher_balance">
            <input type="hidden" name="movement_id" value="0">
            <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
            <input type="hidden" name="correct_balance" value="<?= number_format($calcBalance, 2, '.', '') ?>">
            <button type="submit" class="btn warning">🔧 Corrigir saldo para <?= $fmt($calcBalance) ?></button>
          </form>
          <span style="font-size:12px;color:var(--muted);">
            Diff: <?= $fmt(abs($diff)) ?> (<?= $diff > 0 ? 'armazenado está maior' : 'armazenado está menor' ?>)
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


<!-- LEGEND -->
<div style="margin:28px 0;padding:16px;background:#f9fafb;border-radius:10px;border:1px solid var(--line);font-size:13px;">
  <strong>Legenda de ações:</strong>
  <ul style="margin:8px 0 0;padding-left:20px;line-height:1.8;">
    <li><strong>✅ Vincular como reconciliado</strong> — Marca o movimento como já tratado (vendas já estão pago). Não altera dados de vendas.</li>
    <li><strong>🎯 Aprovar reconciliação</strong> — Cria um payout retroativo e marca as vendas selecionadas como <code>pago</code>.</li>
    <li><strong>⏭️ Marcar como irreconciliável</strong> — Adiciona nota e remove da lista de pendências.</li>
    <li><strong>🔧 Corrigir saldo</strong> — Atualiza <code>cupons_creditos.balance</code> para o valor calculado pelos movimentos.</li>
  </ul>
</div>
