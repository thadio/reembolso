<?php
/** @var array $report */
/** @var object|null $supplierPerson */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $dateField */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;
$statusLabels = ConsignmentModuleController::consignmentStatusLabels();
$s = $report['summary'] ?? [];
$supplierName = $supplierPerson->fullName ?? $supplierPerson->full_name ?? 'Fornecedora';
?>

<div class="print-actions">
  <button class="btn primary" onclick="window.print()">Imprimir</button>
  <button class="btn ghost" onclick="window.close()">Fechar</button>
</div>

<div style="text-align:center;margin-bottom:20px;">
  <h2 style="margin:0;">RELATÓRIO DE CONSIGNAÇÃO</h2>
  <div style="font-size:16px;margin-top:6px;"><?php echo $esc($supplierName); ?></div>
  <?php if ($dateFrom || $dateTo): ?>
    <div style="color:#6b7280;margin-top:4px;">
      Período: <?php echo $dateFrom ? date('d/m/Y', strtotime($dateFrom)) : 'início'; ?>
      — <?php echo $dateTo ? date('d/m/Y', strtotime($dateTo)) : 'atual'; ?>
    </div>
  <?php endif; ?>
</div>

<div class="doc-meta">
  <div><strong>Em estoque</strong><?php echo $s['in_stock_count'] ?? 0; ?> peça(s) — R$ <?php echo number_format((float)($s['in_stock_value'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Vendido (pendente)</strong><?php echo $s['sold_pending_count'] ?? 0; ?> — R$ <?php echo number_format((float)($s['sold_pending_credit'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Vendido (pago)</strong><?php echo $s['sold_paid_count'] ?? 0; ?> — R$ <?php echo number_format((float)($s['sold_paid_credit'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Devolvido</strong><?php echo $s['returned_count'] ?? 0; ?></div>
  <div><strong>Doado</strong><?php echo $s['donated_count'] ?? 0; ?></div>
  <div><strong>Aging médio</strong><?php echo $s['avg_aging_days'] ?? 0; ?> dias</div>
</div>

<div class="doc-section" style="margin-top:24px;">
  <h3>Detalhe por peça</h3>
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="border-bottom:2px solid #e5e7eb;">
        <th style="text-align:left;padding:6px;">SKU</th>
        <th style="text-align:left;padding:6px;">Produto</th>
        <th style="text-align:left;padding:6px;">Recebido</th>
        <th style="text-align:left;padding:6px;">Status</th>
        <th style="text-align:left;padding:6px;">Venda</th>
        <th style="text-align:right;padding:6px;">Preço</th>
        <th style="text-align:right;padding:6px;">%</th>
        <th style="text-align:right;padding:6px;">Comissão</th>
        <th style="text-align:left;padding:6px;">Pago em</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $totalCommission = 0;
      foreach (($report['items'] ?? []) as $item):
        $commission = (float)($item['credit_amount'] ?? 0);
        $totalCommission += $commission;
        $st = $item['consignment_status'] ?? '';
        $stInfo = $statusLabels[$st] ?? ['label' => $st];
      ?>
        <tr style="border-bottom:1px solid #f3f4f6;">
          <td style="padding:4px 6px;"><?php echo $esc($item['sku'] ?? ''); ?></td>
          <td style="padding:4px 6px;"><?php echo $esc($item['product_name'] ?? ''); ?></td>
          <td style="padding:4px 6px;"><?php echo !empty($item['received_at']) ? date('d/m/Y', strtotime($item['received_at'])) : '-'; ?></td>
          <td style="padding:4px 6px;"><?php echo $esc($stInfo['label']); ?></td>
          <td style="padding:4px 6px;"><?php echo !empty($item['order_id']) ? '#'.(int)$item['order_id'].' '.(!empty($item['sold_at']) ? date('d/m/Y', strtotime($item['sold_at'])) : '') : '-'; ?></td>
          <td style="padding:4px 6px;text-align:right;">R$ <?php echo number_format((float)($item['price'] ?? 0), 2, ',', '.'); ?></td>
          <td style="padding:4px 6px;text-align:right;"><?php echo number_format((float)($item['percent_applied'] ?? 0), 0); ?>%</td>
          <td style="padding:4px 6px;text-align:right;">R$ <?php echo number_format($commission, 2, ',', '.'); ?></td>
          <td style="padding:4px 6px;"><?php echo !empty($item['paid_at']) ? date('d/m/Y', strtotime($item['paid_at'])) : '-'; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="border-top:2px solid #374151;font-weight:700;">
        <td colspan="7" style="padding:8px 6px;text-align:right;">Total comissão:</td>
        <td style="padding:8px 6px;text-align:right;">R$ <?php echo number_format($totalCommission, 2, ',', '.'); ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div style="margin-top:32px;font-size:12px;color:#9ca3af;text-align:center;">
  Relatório gerado em <?php echo date('d/m/Y H:i'); ?>
</div>
