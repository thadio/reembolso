<?php
/** @var array $receipt */
/** @var object|null $supplierPerson */
/** @var string|null $documentMode */
/** @var array<int, string>|null $errors */
/** @var bool|null $hidePrintActions */
/** @var callable $esc */

$payout = $receipt['payout'] ?? [];
$items = $receipt['items'] ?? [];
$documentMode = trim((string) ($documentMode ?? 'receipt'));
$isPreview = $documentMode === 'preview';
$errors = isset($errors) && is_array($errors) ? $errors : [];
$hidePrintActions = !empty($hidePrintActions);
$payoutId = (int) ($payout['id'] ?? 0);
$supplierName = $supplierPerson->fullName ?? $supplierPerson->full_name ?? '(sem nome)';
$headerTitle = $isPreview
    ? 'DEMONSTRATIVO PRÉVIO DE PAGAMENTO — CONSIGNAÇÃO'
    : 'RECIBO DE PAGAMENTO — CONSIGNAÇÃO';
$subTitle = $isPreview
    ? ($payoutId > 0 ? ('Rascunho #' . $payoutId) : 'Itens selecionados para pagamento')
    : ('Pagamento #' . $payoutId);
$dateLabel = $isPreview ? 'Data prevista' : 'Data do pagamento';
$methodLabel = $isPreview ? 'Método previsto' : 'Método';
$referenceLabel = $isPreview ? 'Referência prevista' : 'Referência';
$totalLabel = $isPreview ? 'Valor previsto' : 'Valor total';
$itemsTitle = $isPreview ? 'Itens selecionados para pagamento' : 'Itens pagos';
$tableTotalLabel = $isPreview ? 'Total previsto:' : 'Total pago:';
?>

<?php if (!$hidePrintActions): ?>
<div class="print-actions" style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:24px;">
  <button class="btn primary" onclick="window.print()">Imprimir</button>
  <button class="btn ghost" onclick="window.close()">Fechar</button>
</div>
<?php endif; ?>

<div style="text-align:center;margin-bottom:24px;">
  <h2 style="margin:0;"><?php echo $headerTitle; ?></h2>
  <div style="color:#6b7280;margin-top:4px;"><?php echo $esc($subTitle); ?></div>
</div>

<?php if (!empty($errors)): ?>
<div style="margin:0 0 16px;padding:10px 12px;border:1px solid #ef4444;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:14px;">
  <?php echo $esc(implode(' | ', $errors)); ?>
</div>
<?php endif; ?>

<div class="doc-meta">
  <div>
    <strong>Fornecedora</strong>
    <?php echo $esc($supplierName); ?>
  </div>
  <div>
    <strong><?php echo $esc($dateLabel); ?></strong>
    <?php echo !empty($payout['payout_date']) ? date('d/m/Y', strtotime($payout['payout_date'])) : '-'; ?>
  </div>
  <div>
    <strong><?php echo $esc($methodLabel); ?></strong>
    <?php echo $esc(ucfirst($payout['method'] ?? 'pix')); ?>
  </div>
  <?php if (!empty($payout['pix_key'])): ?>
  <div>
    <strong>Chave PIX</strong>
    <?php echo $esc($payout['pix_key']); ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($payout['reference'])): ?>
  <div>
    <strong><?php echo $esc($referenceLabel); ?></strong>
    <?php echo $esc($payout['reference']); ?>
  </div>
  <?php endif; ?>
  <div>
    <strong><?php echo $esc($totalLabel); ?></strong>
    <span style="font-size:18px;font-weight:700;">R$ <?php echo number_format((float)($payout['total_amount'] ?? 0), 2, ',', '.'); ?></span>
  </div>
</div>

<div class="doc-section" style="margin-top:24px;">
  <h3><?php echo $esc($itemsTitle); ?></h3>
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="border-bottom:2px solid #e5e7eb;">
        <th style="text-align:left;padding:8px 6px;">SKU</th>
        <th style="text-align:left;padding:8px 6px;">Produto</th>
        <th style="text-align:left;padding:8px 6px;">Pedido</th>
        <th style="text-align:right;padding:8px 6px;">Comissão</th>
      </tr>
    </thead>
    <tbody>
      <?php $grandTotal = 0; ?>
      <?php if (empty($items)): ?>
        <tr style="border-bottom:1px solid #f3f4f6;">
          <td colspan="4" style="padding:8px 6px;color:#6b7280;">Nenhum item selecionado.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($items as $item):
          $amount = (float)($item['amount'] ?? 0);
          $grandTotal += $amount;
        ?>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:6px;"><?php echo $esc($item['sku'] ?? ''); ?></td>
            <td style="padding:6px;"><?php echo $esc($item['product_name'] ?? ''); ?></td>
            <td style="padding:6px;">#<?php echo (int)($item['order_id'] ?? 0); ?></td>
            <td style="padding:6px;text-align:right;">R$ <?php echo number_format($amount, 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr style="border-top:2px solid #374151;font-weight:700;">
        <td colspan="3" style="padding:8px 6px;text-align:right;"><?php echo $esc($tableTotalLabel); ?></td>
        <td style="padding:8px 6px;text-align:right;font-size:16px;">R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php if (!empty($payout['notes'])): ?>
<div class="doc-section" style="margin-top:16px;">
  <h3>Observações</h3>
  <p><?php echo nl2br($esc($payout['notes'])); ?></p>
</div>
<?php endif; ?>

<?php if ($isPreview): ?>
<?php
  $generatedAt = (string) ($receipt['preview_generated_at'] ?? date('Y-m-d H:i:s'));
  $generatedTs = strtotime($generatedAt);
?>
<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">
  Prévia gerada em <?php echo $generatedTs !== false ? date('d/m/Y H:i', $generatedTs) : $esc($generatedAt); ?>
  <?php if (!empty($receipt['hash'])): ?>
    | Hash de conferência: <?php echo $esc($receipt['hash']); ?>
  <?php endif; ?>
  | Documento sem efeito financeiro.
</div>
<?php elseif (!empty($payout['confirmed_at'])): ?>
<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">
  Confirmado em <?php echo date('d/m/Y H:i', strtotime($payout['confirmed_at'])); ?>
  <?php if (!empty($receipt['hash'])): ?>
    | Hash: <?php echo $esc($receipt['hash']); ?>
  <?php endif; ?>
</div>
<?php endif; ?>
