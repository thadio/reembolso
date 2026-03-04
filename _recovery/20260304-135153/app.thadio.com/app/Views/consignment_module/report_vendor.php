<?php
/** @var array|null $report */
/** @var int $supplierFilter */
/** @var object|null $supplierPerson */
/** @var array $suppliers */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $dateField */
/** @var array $reportFilters */
/** @var array|null $periodBadge */
/** @var array $errors */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;
$statusLabels = ConsignmentModuleController::consignmentStatusLabels();
$reportFilters = $reportFilters ?? [];

$baseQuery = [
  'supplier_pessoa_id' => $supplierFilter,
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'date_field' => $dateField,
];
if (($reportFilters['consignment_status'] ?? '') !== '') {
  $baseQuery['consignment_status'] = (string) $reportFilters['consignment_status'];
}
if (($reportFilters['payout_status'] ?? '') !== '') {
  $baseQuery['payout_status'] = (string) $reportFilters['payout_status'];
}
if (($reportFilters['search'] ?? '') !== '') {
  $baseQuery['q'] = (string) $reportFilters['search'];
}
$printQuery = $baseQuery;
$printQuery['print'] = 1;
$csvQuery = $baseQuery;
$csvQuery['format'] = 'csv';
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Relatório por Fornecedora</h1>
    <div class="subtitle">Relatório detalhado por fornecedora com resumo e itens.</div>
    <?php if (!empty($periodBadge)): ?>
      <div style="margin-top:6px;">
        <span class="badge <?php echo $esc((string) ($periodBadge['badge'] ?? 'secondary')); ?>">
          <?php echo $esc((string) ($periodBadge['label'] ?? '')); ?> (<?php echo $esc((string) ($periodBadge['year_month'] ?? '')); ?>)
        </span>
      </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
    <?php if ($report && $supplierFilter > 0): ?>
      <a class="btn ghost" href="consignacao-relatorio-fornecedora.php?<?php echo $esc(http_build_query($printQuery)); ?>" target="_blank">Versão para impressão</a>
      <a class="btn ghost" href="consignacao-relatorio-fornecedora.php?<?php echo $esc(http_build_query($csvQuery)); ?>">Exportar CSV</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <form method="get" action="consignacao-relatorio-fornecedora.php" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <select name="supplier_pessoa_id" aria-label="Fornecedora">
        <option value="">Selecione a fornecedora...</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?php echo (int)$s['supplier_pessoa_id']; ?>" <?php echo $supplierFilter === (int)$s['supplier_pessoa_id'] ? 'selected' : ''; ?>>
            <?php echo $esc($s['full_name'] ?? '(sem nome)'); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?php echo $esc($dateFrom); ?>" title="Data de" aria-label="Data de">
      <input type="date" name="date_to" value="<?php echo $esc($dateTo); ?>" title="Data até" aria-label="Data até">
      <select name="date_field" aria-label="Filtrar por campo de data">
        <option value="sold_at" <?php echo $dateField === 'sold_at' ? 'selected' : ''; ?>>Data de venda</option>
        <option value="received_at" <?php echo $dateField === 'received_at' ? 'selected' : ''; ?>>Data de recebimento</option>
        <option value="paid_at" <?php echo $dateField === 'paid_at' ? 'selected' : ''; ?>>Data de pagamento</option>
      </select>
      <select name="consignment_status" aria-label="Filtrar por status da peça">
        <option value="">Todos os status</option>
        <?php foreach ($statusLabels as $statusKey => $statusInfo): ?>
          <option value="<?php echo $esc((string) $statusKey); ?>" <?php echo (($reportFilters['consignment_status'] ?? '') === $statusKey) ? 'selected' : ''; ?>>
            <?php echo $esc((string) ($statusInfo['label'] ?? $statusKey)); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="payout_status" aria-label="Filtrar por pagamento">
        <option value="">Todos os pagamentos</option>
        <option value="pendente" <?php echo (($reportFilters['payout_status'] ?? '') === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
        <option value="pago" <?php echo (($reportFilters['payout_status'] ?? '') === 'pago') ? 'selected' : ''; ?>>Pago</option>
      </select>
      <input type="search" name="q" value="<?php echo $esc((string) ($reportFilters['search'] ?? '')); ?>" placeholder="Buscar SKU, produto, pedido..." aria-label="Busca geral">
      <button type="submit" class="btn primary">Gerar relatório</button>
    </form>
  </div>
</div>

<?php if ($report): ?>
  <?php $s = $report['summary']; ?>
  <h3 style="margin:20px 0 12px;">
    <?php echo $esc($supplierPerson->fullName ?? $supplierPerson->full_name ?? 'Fornecedora'); ?>
    — Resumo
  </h3>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0;">
    <div style="background:#f3f4f6;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Em estoque</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['in_stock_count']; ?></div>
      <div style="font-size:13px;color:#6b7280;">R$ <?php echo number_format($s['in_stock_value'], 2, ',', '.'); ?></div>
    </div>
    <div style="background:#fef3c7;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Vendido — Pendente pgto</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['sold_pending_count']; ?></div>
      <div style="font-size:13px;color:#92400e;">R$ <?php echo number_format($s['sold_pending_credit'], 2, ',', '.'); ?></div>
    </div>
    <div style="background:#d1fae5;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Vendido — Pago</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['sold_paid_count']; ?></div>
      <div style="font-size:13px;color:#065f46;">R$ <?php echo number_format($s['sold_paid_credit'], 2, ',', '.'); ?></div>
    </div>
    <div style="background:#f3f4f6;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Devolvido</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['returned_count']; ?></div>
    </div>
    <div style="background:#f3f4f6;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Doado</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['donated_count']; ?></div>
    </div>
    <div style="background:#f3f4f6;border-radius:8px;padding:16px;">
      <div style="font-size:12px;color:#6b7280;text-transform:uppercase;">Aging médio (estoque)</div>
      <div style="font-size:22px;font-weight:700;"><?php echo $s['avg_aging_days']; ?>d</div>
    </div>
  </div>

  <!-- Detail items -->
  <h3 style="margin:24px 0 12px;">Detalhe por peça</h3>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
        <thead>
          <tr>
            <th data-sort-key="sku" aria-sort="none">SKU</th>
            <th data-sort-key="product_name" aria-sort="none">Produto</th>
            <th data-sort-key="received_at" aria-sort="none">Recebido em</th>
            <th data-sort-key="consignment_status" aria-sort="none">Status</th>
            <th data-sort-key="order_id" aria-sort="none">Venda #</th>
            <th data-sort-key="sold_at" aria-sort="none">Vendido em</th>
            <th data-sort-key="price" aria-sort="none">Preço venda</th>
            <th data-sort-key="percent_applied" aria-sort="none">%</th>
            <th data-sort-key="credit_amount" aria-sort="none">Comissão</th>
            <th data-sort-key="payout_status" aria-sort="none">Pgto</th>
            <th data-sort-key="paid_at" aria-sort="none">Pago em</th>
          </tr>
          <tr>
            <th><input type="search" data-filter-col="sku" placeholder="Filtrar SKU" aria-label="Filtrar SKU"></th>
            <th><input type="search" data-filter-col="product_name" placeholder="Filtrar produto" aria-label="Filtrar produto"></th>
            <th><input type="search" data-filter-col="received_at" placeholder="Filtrar recebido" aria-label="Filtrar recebido"></th>
            <th><input type="search" data-filter-col="consignment_status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
            <th><input type="search" data-filter-col="order_id" placeholder="Filtrar venda" aria-label="Filtrar venda"></th>
            <th><input type="search" data-filter-col="sold_at" placeholder="Filtrar vendido" aria-label="Filtrar vendido"></th>
            <th><input type="search" data-filter-col="price" placeholder="Filtrar preço" aria-label="Filtrar preço"></th>
            <th><input type="search" data-filter-col="percent_applied" placeholder="Filtrar %" aria-label="Filtrar percentual"></th>
            <th><input type="search" data-filter-col="credit_amount" placeholder="Filtrar comissão" aria-label="Filtrar comissão"></th>
            <th><input type="search" data-filter-col="payout_status" placeholder="Filtrar pgto" aria-label="Filtrar pagamento"></th>
            <th><input type="search" data-filter-col="paid_at" placeholder="Filtrar pago em" aria-label="Filtrar pago em"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($report['items'])): ?>
            <tr class="no-results"><td colspan="11">Nenhum item.</td></tr>
          <?php else: ?>
            <?php foreach ($report['items'] as $item):
              $st = $item['consignment_status'] ?? '';
              $stInfo = $statusLabels[$st] ?? ['label' => $st, 'badge' => 'secondary'];
              $statusLabel = trim((string) ($stInfo['label'] ?? $st));
              if ($statusLabel === '') {
                $statusLabel = '—';
              }
              $payoutStatus = (string) ($item['payout_status'] ?? '');
              $payoutLabel = $payoutStatus === 'pago' ? 'Pago' : ($payoutStatus === 'pendente' ? 'Pendente' : '—');
              $receivedRaw = (string) ($item['received_at'] ?? '');
              $soldRaw = (string) ($item['sold_at'] ?? '');
              $paidRaw = (string) ($item['paid_at'] ?? '');
              $priceValue = (float) ($item['price'] ?? 0);
              $percentValue = (float) ($item['percent_applied'] ?? 0);
              $creditValue = (float) ($item['credit_amount'] ?? 0);
            ?>
              <tr>
                <td data-col="sku" data-value="<?php echo $esc((string) ($item['sku'] ?? '')); ?>"><?php echo $esc($item['sku'] ?? ''); ?></td>
                <td data-col="product_name" data-value="<?php echo $esc((string) ($item['product_name'] ?? '')); ?>"><?php echo $esc($item['product_name'] ?? ''); ?></td>
                <td data-col="received_at" data-value="<?php echo $esc($receivedRaw); ?>"><?php echo $receivedRaw !== '' ? date('d/m/Y', strtotime($receivedRaw)) : '<span style="color:var(--muted);">—</span>'; ?></td>
                <td data-col="consignment_status" data-value="<?php echo $esc((string) $statusLabel); ?>"><span class="badge <?php echo $stInfo['badge']; ?>"><?php echo $esc($statusLabel); ?></span></td>
                <td data-col="order_id" data-value="<?php echo $esc((string) ($item['order_id'] ?? '')); ?>"><?php echo !empty($item['order_id']) ? '#'.(int)$item['order_id'] : '<span style="color:var(--muted);">—</span>'; ?></td>
                <td data-col="sold_at" data-value="<?php echo $esc($soldRaw); ?>"><?php echo $soldRaw !== '' ? date('d/m/Y', strtotime($soldRaw)) : '<span style="color:var(--muted);">—</span>'; ?></td>
                <td data-col="price" data-value="<?php echo $esc(number_format($priceValue, 2, '.', '')); ?>">R$ <?php echo number_format($priceValue, 2, ',', '.'); ?></td>
                <td data-col="percent_applied" data-value="<?php echo $esc(number_format($percentValue, 2, '.', '')); ?>"><?php echo number_format($percentValue, 0); ?>%</td>
                <td data-col="credit_amount" data-value="<?php echo $esc(number_format($creditValue, 2, '.', '')); ?>">R$ <?php echo number_format($creditValue, 2, ',', '.'); ?></td>
                <td data-col="payout_status" data-value="<?php echo $esc($payoutLabel); ?>"><?php echo $payoutStatus === 'pago' ? '<span class="badge success">Pago</span>' : ($payoutStatus === 'pendente' ? '<span class="badge warning">Pendente</span>' : '<span style="color:var(--muted);">—</span>'); ?></td>
                <td data-col="paid_at" data-value="<?php echo $esc($paidRaw); ?>"><?php echo $paidRaw !== '' ? date('d/m/Y', strtotime($paidRaw)) : '<span style="color:var(--muted);">—</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php elseif ($supplierFilter === 0): ?>
  <div class="alert info" style="margin-top:20px;">Selecione uma fornecedora para gerar o relatório.</div>
<?php endif; ?>
