<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array $filters */
/** @var array $suppliers */
/** @var array $errors */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;

$saleStatusLabels = ConsignmentModuleController::saleStatusLabels();
$canCreatePayout = userCan('consignment_module.create_payout');
$totalPages = $perPage > 0 ? max(1, ceil($total / $perPage)) : 1;
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Vendas Consignadas</h1>
    <div class="subtitle"><?php echo $total; ?> venda(s) encontrada(s).</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
    <?php if ($canCreatePayout): ?>
      <a class="btn primary" href="consignacao-pagamento-cadastro.php">Novo Pagamento</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php
$buildSalesLink = function (int $targetPage) use ($filters, $perPage): string {
    $qs = array_merge($filters, ['page' => $targetPage, 'per_page' => $perPage]);
    return 'consignacao-vendas.php?' . http_build_query($qs);
};
$rangeStart = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
$rangeEnd = min($page * $perPage, $total);
?>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral" value="<?php echo $esc($filters['search'] ?? ''); ?>">
    <select data-select-filter data-param="supplier_pessoa_id" aria-label="Filtrar por fornecedora">
      <option value="">Todas as fornecedoras</option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?php echo (int)$s['supplier_pessoa_id']; ?>" <?php echo (int)($filters['supplier_pessoa_id'] ?? 0) === (int)$s['supplier_pessoa_id'] ? 'selected' : ''; ?>>
          <?php echo $esc($s['full_name'] ?? '(sem nome)'); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select data-select-filter data-param="sale_status" aria-label="Filtrar por status venda">
      <option value="">Todos os status</option>
      <option value="ativa" <?php echo ($filters['sale_status'] ?? '') === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
      <option value="revertida" <?php echo ($filters['sale_status'] ?? '') === 'revertida' ? 'selected' : ''; ?>>Revertida</option>
    </select>
    <select data-select-filter data-param="payout_status" aria-label="Filtrar por pagamento">
      <option value="">Todos os pagamentos</option>
      <option value="pendente" <?php echo ($filters['payout_status'] ?? '') === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
      <option value="pago" <?php echo ($filters['payout_status'] ?? '') === 'pago' ? 'selected' : ''; ?>>Pago</option>
    </select>
    <input type="date" data-select-filter data-param="sold_from" value="<?php echo $esc($filters['sold_from'] ?? ''); ?>" title="Vendido de" aria-label="Vendido de">
    <input type="date" data-select-filter data-param="sold_to" value="<?php echo $esc($filters['sold_to'] ?? ''); ?>" title="Vendido até" aria-label="Vendido até">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageFormSales" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="page" value="1">
    <?php foreach ($filters as $fk => $fv): ?>
      <?php if ($fk !== 'page' && $fk !== 'per_page' && $fv !== ''): ?>
        <input type="hidden" name="<?php echo $esc($fk); ?>" value="<?php echo $esc((string) $fv); ?>">
      <?php endif; ?>
    <?php endforeach; ?>
    <label for="perPageSales" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPageSales" name="per_page">
      <?php foreach ([20, 50, 100] as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $total; ?></span>
  </form>
</div>

<div class="table-scroll" data-table-scroll>
  <div class="table-scroll-top" aria-hidden="true">
    <div class="table-scroll-top-inner"></div>
  </div>
  <div class="table-scroll-body">
    <table data-table="interactive" data-filter-mode="server">
      <thead>
        <tr>
          <th data-sort-key="order_id" aria-sort="none">Pedido #</th>
          <th data-sort-key="sku" aria-sort="none">SKU</th>
          <th data-sort-key="product_name" aria-sort="none">Produto</th>
          <th data-sort-key="supplier_name" aria-sort="none">Fornecedora</th>
          <th data-sort-key="sold_at" aria-sort="none">Data venda</th>
          <th data-sort-key="net_amount" aria-sort="none">Receita líq.</th>
          <th data-sort-key="percent_applied" aria-sort="none">%</th>
          <th data-sort-key="credit_amount" aria-sort="none">Comissão</th>
          <th data-sort-key="sale_status" aria-sort="none">Status venda</th>
          <th data-sort-key="payout_status" aria-sort="none">Pagamento</th>
          <th data-sort-key="payout_id" aria-sort="none">Payout #</th>
          <th data-sort-key="paid_at" aria-sort="none">Pago em</th>
        </tr>
        <tr class="filters-row">
          <th><input type="search" data-filter-col="order_id" data-query-param="filter_order_id" value="<?php echo $esc($filters['filter_order_id'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar pedido"></th>
          <th><input type="search" data-filter-col="sku" data-query-param="filter_sku" value="<?php echo $esc($filters['filter_sku'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar SKU"></th>
          <th><input type="search" data-filter-col="product_name" data-query-param="filter_product_name" value="<?php echo $esc($filters['filter_product_name'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar produto"></th>
          <th><input type="search" data-filter-col="supplier_name" data-query-param="filter_supplier_name" value="<?php echo $esc($filters['filter_supplier_name'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar fornecedora"></th>
          <th></th>
          <th></th>
          <th></th>
          <th></th>
          <th><input type="search" data-filter-col="sale_status" data-query-param="filter_sale_status" value="<?php echo $esc($filters['filter_sale_status'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar status"></th>
          <th><input type="search" data-filter-col="payout_status" data-query-param="filter_payout_status" value="<?php echo $esc($filters['filter_payout_status'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar pgto"></th>
          <th></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="no-results"><td colspan="12">Nenhuma venda consignada encontrada.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $orderId = (int) ($row['order_id'] ?? 0);
            $saleStatus = $row['sale_status'] ?? '';
            $payoutStatus = $row['payout_status'] ?? '';
            $saleInfo = $saleStatusLabels[$saleStatus] ?? ['label' => $saleStatus, 'badge' => 'secondary'];
          ?>
            <tr>
              <td data-col="order_id" data-value="<?php echo $orderId; ?>"><a href="pedido-cadastro.php?id=<?php echo $orderId; ?>">#<?php echo $orderId; ?></a></td>
              <td data-col="sku" data-value="<?php echo $esc($row['sku'] ?? ''); ?>"><?php echo $esc($row['sku'] ?? ''); ?></td>
              <td data-col="product_name" data-value="<?php echo $esc($row['product_name'] ?? ''); ?>"><?php echo $esc($row['product_name'] ?? ''); ?></td>
              <td data-col="supplier_name" data-value="<?php echo $esc($row['supplier_name'] ?? ''); ?>"><?php echo $esc($row['supplier_name'] ?? '(sem nome)'); ?></td>
              <td data-col="sold_at"><?php echo !empty($row['sold_at']) ? date('d/m/Y', strtotime($row['sold_at'])) : '<span style="color:var(--muted);">—</span>'; ?></td>
              <td data-col="net_amount" data-value="<?php echo (float)($row['net_amount'] ?? 0); ?>">R$ <?php echo number_format((float)($row['net_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td data-col="percent_applied"><?php echo number_format((float)($row['percent_applied'] ?? 0), 0); ?>%</td>
              <td data-col="credit_amount" data-value="<?php echo (float)($row['credit_amount'] ?? 0); ?>">R$ <?php echo number_format((float)($row['credit_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td data-col="sale_status" data-value="<?php echo $esc($saleStatus); ?>"><span class="badge <?php echo $saleInfo['badge']; ?>"><?php echo $esc($saleInfo['label']); ?></span></td>
              <td data-col="payout_status" data-value="<?php echo $esc($payoutStatus); ?>">
                <span class="badge <?php echo $payoutStatus === 'pago' ? 'success' : 'warning'; ?>">
                  <?php echo $payoutStatus === 'pago' ? 'Pago' : 'Pendente'; ?>
                </span>
              </td>
              <td><?php
                $payoutId = (int)($row['payout_id'] ?? 0);
                echo $payoutId > 0 ? '<a href="consignacao-pagamento-list.php?id='.$payoutId.'&action=show">#'.$payoutId.'</a>' : '<span style="color:var(--muted);">—</span>';
              ?></td>
              <td><?php echo !empty($row['paid_at']) ? date('d/m/Y', strtotime($row['paid_at'])) : '<span style="color:var(--muted);">—</span>'; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildSalesLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildSalesLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildSalesLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildSalesLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
  var perPage = document.getElementById('perPageSales');
  var form = document.getElementById('perPageFormSales');
  if (perPage && form) { perPage.addEventListener('change', function() { form.submit(); }); }

  var filterSelects = Array.from(document.querySelectorAll('[data-select-filter]'));
  filterSelects.forEach(function(el) {
    el.addEventListener('change', function() {
      var param = el.dataset.param;
      if (!param) return;
      var url = new URL(window.location.href);
      url.searchParams.set('page', '1');
      if (el.value) { url.searchParams.set(param, el.value); } else { url.searchParams.delete(param); }
      window.location.assign(url.toString());
    });
  });
})();
</script>
