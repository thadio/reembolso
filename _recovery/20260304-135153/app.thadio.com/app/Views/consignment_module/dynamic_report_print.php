<?php
/** @var array $report */
/** @var object|null $supplierPerson */
/** @var array $selectedFields */
/** @var array $fieldMetadata */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $dateField */
/** @var string $detailLevel */
/** @var string $printSummaryMode */
/** @var array $statusLabels */
/** @var callable $esc */

$summaryHistorical = $report['summary'] ?? [];
$summaryFiltered = $report['summary_filtered'] ?? [];
$items = $report['items'] ?? [];
$groups = $report['grouped_items'] ?? ($report['groups'] ?? []);
$supplierName = '';
if ($supplierPerson) {
    $supplierName = is_array($supplierPerson)
        ? ($supplierPerson['full_name'] ?? $supplierPerson['fullName'] ?? 'Fornecedora')
        : ($supplierPerson->fullName ?? $supplierPerson->full_name ?? 'Fornecedora');
}

$dateFieldLabels = [
    'received_at' => 'Data de recebimento',
    'sold_at'     => 'Data de venda',
    'paid_at'     => 'Data de pagamento',
];
$dateFieldLabel = $dateFieldLabels[$dateField ?? 'received_at'] ?? 'Data';
$printSummaryMode = in_array(($printSummaryMode ?? 'both'), ['both', 'historical', 'filtered', 'none'], true)
    ? (string) $printSummaryMode
    : 'both';

function formatPrintCell(string $key, $value, array $meta, array $statusLabels, callable $esc): string {
    if ($value === null || $value === '') return '-';
    $type = $meta['type'] ?? 'text';
    return match ($type) {
        'currency' => 'R$ ' . number_format((float)$value, 2, ',', '.'),
        'percent'  => number_format((float)$value, 1, ',', '.') . '%',
        'date'     => date('d/m/Y', strtotime($value)),
        'datetime' => date('d/m/Y H:i', strtotime($value)),
        'integer'  => number_format((int)$value, 0, ',', '.'),
        'boolean'  => $value ? 'Sim' : 'Não',
        'status'   => $esc($statusLabels[$value]['label'] ?? $value),
        'image'    => '[img]',
        'link'     => '#' . (int)$value,
        default    => $esc((string)$value),
    };
}
?>

<div class="print-actions">
  <button class="btn primary" onclick="window.print()">Imprimir</button>
  <button class="btn ghost" onclick="window.close()">Fechar</button>
</div>

<div style="text-align:center;margin-bottom:20px;">
  <h2 style="margin:0;">RELATÓRIO DINÂMICO DE CONSIGNAÇÃO</h2>
  <?php if ($supplierName): ?>
    <div style="font-size:16px;margin-top:6px;"><?php echo $esc($supplierName); ?></div>
  <?php endif; ?>
  <?php if ($dateFrom || $dateTo): ?>
    <div style="color:#6b7280;margin-top:4px;">
      <?php echo $esc($dateFieldLabel); ?>:
      <?php echo $dateFrom ? date('d/m/Y', strtotime($dateFrom)) : 'início'; ?>
      — <?php echo $dateTo ? date('d/m/Y', strtotime($dateTo)) : 'atual'; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ═══ KPI SUMMARY ═══ -->
<?php
$showSummary = $detailLevel !== 'items' && $printSummaryMode !== 'none';
$showHistorical = $showSummary && in_array($printSummaryMode, ['both', 'historical'], true) && !empty($summaryHistorical);
$showFiltered = $showSummary && in_array($printSummaryMode, ['both', 'filtered'], true) && !empty($summaryFiltered);
$renderPrintSummary = static function (string $title, array $s) use ($esc): void {
?>
<h3 style="margin:14px 0 8px;"><?php echo $esc($title); ?></h3>
<div class="doc-meta" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:20px;">
  <div><strong>Total peças</strong> <?php echo (int)($s['total_received'] ?? $s['total_pieces'] ?? 0); ?></div>
  <div><strong>Valor total estoque</strong> R$ <?php echo number_format((float)($s['in_stock_value'] ?? $s['total_stock_value'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Em estoque</strong> <?php echo (int)($s['in_stock_count'] ?? 0); ?> — R$ <?php echo number_format((float)($s['in_stock_value'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Vendido (pendente)</strong> <?php echo (int)($s['sold_pending_count'] ?? 0); ?> — R$ <?php echo number_format((float)($s['total_pending'] ?? $s['sold_pending_credit'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Vendido (pago)</strong> <?php echo (int)($s['sold_paid_count'] ?? 0); ?> — R$ <?php echo number_format((float)($s['total_paid'] ?? $s['sold_paid_credit'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Devolvido</strong> <?php echo (int)($s['returned_count'] ?? 0); ?></div>
  <div><strong>Doado</strong> <?php echo (int)($s['donated_count'] ?? 0); ?></div>
  <div><strong>Descartado</strong> <?php echo (int)($s['discarded_count'] ?? 0); ?></div>
  <div><strong>Aging médio</strong> <?php echo number_format((float)($s['avg_aging_days'] ?? 0), 1, ',', '.'); ?> dias</div>
  <div><strong>Total vendas brutas</strong> R$ <?php echo number_format((float)($s['gross_sold_total'] ?? $s['total_sales_gross'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Total comissão</strong> R$ <?php echo number_format((float)($s['total_commission'] ?? 0), 2, ',', '.'); ?></div>
  <div><strong>Comissão pendente</strong> R$ <?php echo number_format((float)($s['total_pending'] ?? $s['pending_commission'] ?? 0), 2, ',', '.'); ?></div>
</div>
<?php
};
?>
<?php if ($showHistorical): ?>
  <?php $renderPrintSummary('Resumo Executivo Histórico Completo', $summaryHistorical); ?>
<?php endif; ?>
<?php if ($showFiltered): ?>
  <?php $renderPrintSummary('Resumo Executivo filtrado (conforme filtros)', $summaryFiltered); ?>
<?php endif; ?>

<!-- ═══ DETAIL TABLE ═══ -->
<?php if ($detailLevel !== 'summary' && !empty($selectedFields)):
  $visibleMeta = [];
  foreach ($selectedFields as $fk) {
      if (isset($fieldMetadata[$fk])) $visibleMeta[$fk] = $fieldMetadata[$fk];
  }
  $hasCurrencyTotals = false;
  foreach ($visibleMeta as $m) { if (($m['type'] ?? '') === 'currency') { $hasCurrencyTotals = true; break; } }

  // Render a table block
  $renderTable = function(array $rows, string $groupLabel = '') use ($visibleMeta, $selectedFields, $statusLabels, $esc, $hasCurrencyTotals) {
    if ($groupLabel): ?>
      <h3 style="margin:18px 0 6px;font-size:14px;border-bottom:1px solid #e5e7eb;padding-bottom:4px;"><?php echo $esc($groupLabel); ?> (<?php echo count($rows); ?>)</h3>
    <?php endif; ?>
    <table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:12px;">
      <thead>
        <tr style="border-bottom:2px solid #d1d5db;">
          <?php foreach ($visibleMeta as $fk => $meta): ?>
            <th style="text-align:<?php echo in_array($meta['type'] ?? '', ['currency', 'percent', 'integer']) ? 'right' : 'left'; ?>;padding:4px 5px;white-space:nowrap;font-size:10px;">
              <?php echo $esc($meta['label']); ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $item): ?>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <?php foreach ($visibleMeta as $fk => $meta): ?>
              <td style="padding:3px 5px;text-align:<?php echo in_array($meta['type'] ?? '', ['currency', 'percent', 'integer']) ? 'right' : 'left'; ?>;">
                <?php echo formatPrintCell($fk, $item[$fk] ?? null, $meta, $statusLabels, $esc); ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($hasCurrencyTotals): ?>
      <tfoot>
        <tr style="border-top:2px solid #374151;font-weight:700;">
          <?php foreach ($visibleMeta as $fk => $meta): ?>
            <td style="padding:6px 5px;text-align:right;">
              <?php if (($meta['type'] ?? '') === 'currency') {
                  $sum = array_sum(array_column($rows, $fk));
                  echo 'R$ ' . number_format($sum, 2, ',', '.');
              } ?>
            </td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  <?php };

  if (!empty($groups)):
    foreach ($groups as $group):
      if (is_array($group) && array_key_exists('items', $group)) {
          $label = (string) ($group['label'] ?? '');
          $rows = is_array($group['items']) ? $group['items'] : [];
          $renderTable($rows, $label);
          continue;
      }
      if (is_array($group)) {
          $renderTable($group);
      }
    endforeach;
  else:
    $renderTable($items);
  endif;
endif; ?>

<div style="margin-top:32px;font-size:11px;color:#9ca3af;text-align:center;">
  Relatório gerado em <?php echo date('d/m/Y H:i'); ?>
  <?php if (count($selectedFields ?? [])): ?> · <?php echo count($selectedFields); ?> campo(s) selecionado(s)<?php endif; ?>
</div>
