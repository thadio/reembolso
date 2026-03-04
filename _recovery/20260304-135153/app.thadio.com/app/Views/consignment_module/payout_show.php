<?php
/** @var array|null $payout */
/** @var array $items */
/** @var object|null $supplierPerson */
/** @var string $success */
/** @var array $errors */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;

$payoutStatusLabels = ConsignmentModuleController::payoutStatusLabels();
$methodLabels = ConsignmentModuleController::payoutMethodLabels();
$canConfirm = userCan('consignment_module.confirm_payout');
$canCancel = userCan('consignment_module.cancel_payout');
$canEditConfirmed = userCan('consignment_module.confirm_payout');
$payoutId = (int) ($payout['id'] ?? 0);
$status = $payout['status'] ?? '';
$statusInfo = $payoutStatusLabels[$status] ?? ['label' => $status, 'badge' => 'secondary'];
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Pagamento #<?php echo $payoutId; ?></h1>
    <div class="subtitle">Detalhes do pagamento de consignação.</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn ghost" href="consignacao-pagamento-list.php">← Lista</a>
    <?php if ($status === 'confirmado'): ?>
      <?php if ($canEditConfirmed): ?>
        <a class="btn ghost" href="consignacao-pagamento-cadastro.php?id=<?php echo $payoutId; ?>&edit_confirmed=1">Editar PIX</a>
      <?php endif; ?>
      <a class="btn ghost" href="consignacao-pagamento-cadastro.php?id=<?php echo $payoutId; ?>&action=receipt" target="_blank">Imprimir recibo</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($success)): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php if ($payout): ?>
<div class="doc-meta" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:20px 0;">
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Status</strong>
    <span class="badge <?php echo $statusInfo['badge']; ?>"><?php echo $esc($statusInfo['label']); ?></span>
  </div>
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Fornecedora</strong>
    <?php echo $esc($supplierPerson->fullName ?? $supplierPerson->full_name ?? '(sem nome)'); ?>
  </div>
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Data</strong>
    <?php echo !empty($payout['payout_date']) ? date('d/m/Y', strtotime($payout['payout_date'])) : '-'; ?>
  </div>
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Método</strong>
    <?php echo $esc($methodLabels[$payout['method'] ?? ''] ?? ($payout['method'] ?? '')); ?>
  </div>
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Valor total</strong>
    <span style="font-size:20px;font-weight:700;">R$ <?php echo number_format((float)($payout['total_amount'] ?? 0), 2, ',', '.'); ?></span>
  </div>
  <div style="padding:12px 14px;border:1px solid #dfe3eb;border-radius:12px;background:#f9fafc;">
    <strong style="display:block;font-size:12px;text-transform:uppercase;color:#6b7280;">Itens</strong>
    <?php echo (int)($payout['items_count'] ?? 0); ?>
  </div>
</div>

<?php if (!empty($payout['pix_key'])): ?>
<div style="margin-bottom:12px;"><strong>Chave PIX:</strong> <?php echo $esc($payout['pix_key']); ?></div>
<?php endif; ?>
<?php if (!empty($payout['reference'])): ?>
<div style="margin-bottom:12px;"><strong>Referência:</strong> <?php echo $esc($payout['reference']); ?></div>
<?php endif; ?>
<?php if (!empty($payout['notes'])): ?>
<div style="margin-bottom:12px;"><strong>Observações:</strong> <?php echo nl2br($esc($payout['notes'])); ?></div>
<?php endif; ?>

<!-- Actions -->
<?php if ($status === 'rascunho' && $canConfirm): ?>
  <form method="post" action="consignacao-pagamento-cadastro.php?action=confirm" style="display:inline-block;margin:12px 4px 12px 0;">
    <input type="hidden" name="payout_id" value="<?php echo $payoutId; ?>">
    <button type="submit" class="btn primary" onclick="return confirm('Confirma o pagamento? Isto irá debitar o saldo da fornecedora.');">
      Confirmar pagamento
    </button>
  </form>
<?php endif; ?>

<?php if ($status === 'confirmado' && $canCancel): ?>
  <form method="post" action="consignacao-pagamento-cadastro.php?action=cancel" style="display:inline-block;margin:12px 0;"
        onsubmit="var r=prompt('Motivo do cancelamento:'); if(!r){return false;} this.querySelector('[name=cancelation_reason]').value=r;">
    <input type="hidden" name="payout_id" value="<?php echo $payoutId; ?>">
    <input type="hidden" name="cancelation_reason" value="">
    <button type="submit" class="btn danger">Cancelar pagamento</button>
  </form>
<?php endif; ?>

<!-- Items table -->
<h3 style="margin:24px 0 12px;">Itens do pagamento</h3>
<div class="table-scroll" data-table-scroll>
  <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
  <div class="table-scroll-body">
    <table data-table="interactive">
      <thead>
        <tr>
          <th>Pedido</th>
          <th>Produto</th>
          <th>SKU</th>
          <th>%</th>
          <th>Valor comissão</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr class="no-results"><td colspan="5">Nenhum item.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><a href="pedido-cadastro.php?id=<?php echo (int)($item['order_id'] ?? 0); ?>">#<?php echo (int)($item['order_id'] ?? 0); ?></a></td>
              <td><?php echo $esc($item['product_name'] ?? ''); ?></td>
              <td><?php echo $esc($item['sku'] ?? ''); ?></td>
              <td><?php echo number_format((float)($item['percent_applied'] ?? 0), 0); ?>%</td>
              <td>R$ <?php echo number_format((float)($item['amount'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($payout['canceled_at'])): ?>
  <div class="alert error" style="margin-top:16px;">
    <strong>Cancelado em:</strong> <?php echo date('d/m/Y H:i', strtotime($payout['canceled_at'])); ?>
    <?php if (!empty($payout['cancelation_reason'])): ?>
      <br><strong>Motivo:</strong> <?php echo $esc($payout['cancelation_reason']); ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php endif; ?>
