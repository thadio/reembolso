<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array $filters */
/** @var array $suppliers */
/** @var array $statusOptions */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */

$canEditState = userCan('consignment_module.edit_product_state');
$canAdminOverride = userCan('consignment_module.admin_override');
$totalPages = $perPage > 0 ? max(1, ceil($total / $perPage)) : 1;
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Produtos Consignados</h1>
    <div class="subtitle"><?php echo $total; ?> produto(s) encontrado(s).</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php
$buildProductsLink = function (int $targetPage) use ($filters, $perPage): string {
    $qs = array_merge($filters, ['page' => $targetPage, 'per_page' => $perPage]);
    return 'consignacao-produtos.php?' . http_build_query($qs);
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
    <select data-select-filter data-param="consignment_status" aria-label="Filtrar por status">
      <option value="">Todos os status</option>
      <?php foreach ($statusOptions as $key => $opt): ?>
        <option value="<?php echo $key; ?>" <?php echo ($filters['consignment_status'] ?? '') === $key ? 'selected' : ''; ?>>
          <?php echo $esc($opt['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" data-select-filter data-param="date_from" value="<?php echo $esc($filters['date_from'] ?? ''); ?>" title="Recebido de" aria-label="Recebido de">
    <input type="date" name="date_to" data-select-filter data-param="date_to" value="<?php echo $esc($filters['date_to'] ?? ''); ?>" title="Recebido até" aria-label="Recebido até">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageFormProducts" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="page" value="1">
    <?php foreach ($filters as $fk => $fv): ?>
      <?php if ($fk !== 'page' && $fk !== 'per_page' && $fv !== ''): ?>
        <input type="hidden" name="<?php echo $esc($fk); ?>" value="<?php echo $esc((string) $fv); ?>">
      <?php endif; ?>
    <?php endforeach; ?>
    <label for="perPageProducts" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPageProducts" name="per_page">
      <?php foreach ([20, 50, 100] as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $total; ?></span>
  </form>
</div>

<!-- Bulk action form -->
<form method="post" action="consignacao-produtos.php?<?php echo http_build_query($filters); ?>" id="bulkForm">
  <?php if ($canEditState || $canAdminOverride): ?>
    <div style="display:flex;gap:10px;align-items:center;margin:12px 0;flex-wrap:wrap;">
      <select name="bulk_action">
        <option value="">Ação em lote...</option>
        <?php if ($canEditState): ?>
          <option value="devolver_fornecedor">Devolver à fornecedora</option>
          <option value="doar">Marcar para doação</option>
          <option value="descartar">Marcar como descarte/perda</option>
        <?php endif; ?>
        <?php if ($canAdminOverride): ?>
          <option value="reativar_consignado">Reativar como consignado (admin)</option>
        <?php endif; ?>
      </select>
      <input type="text" name="justification" placeholder="Justificativa (obrigatória para admin)">
      <button type="submit" class="btn warning" onclick="return confirm('Confirma a ação em lote para os itens selecionados?');">Executar</button>
    </div>
  <?php endif; ?>

  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true">
      <div class="table-scroll-top-inner"></div>
    </div>
    <div class="table-scroll-body">
      <table data-table="interactive" data-filter-mode="server">
        <thead>
          <tr>
            <th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
            <th data-sort-key="sku" aria-sort="none">SKU</th>
            <th data-sort-key="product_name" aria-sort="none">Produto</th>
            <th data-sort-key="supplier_name" aria-sort="none">Fornecedora</th>
            <th data-sort-key="received_at" aria-sort="none">Recebido em</th>
            <th data-sort-key="aging" aria-sort="none">Dias na loja</th>
            <th data-sort-key="consignment_status" aria-sort="none">Status</th>
            <th data-sort-key="price" aria-sort="none">Preço</th>
            <th data-sort-key="percentual" aria-sort="none">% Comissão</th>
            <th data-sort-key="commission" aria-sort="none">Comissão (R$)</th>
          </tr>
          <tr class="filters-row">
            <th></th>
            <th><input type="search" data-filter-col="sku" data-query-param="filter_sku" value="<?php echo $esc($filters['filter_sku'] ?? ''); ?>" placeholder="Filtrar SKU" aria-label="Filtrar SKU"></th>
            <th><input type="search" data-filter-col="product_name" data-query-param="filter_product_name" value="<?php echo $esc($filters['filter_product_name'] ?? ''); ?>" placeholder="Filtrar produto" aria-label="Filtrar produto"></th>
            <th><input type="search" data-filter-col="supplier_name" data-query-param="filter_supplier_name" value="<?php echo $esc($filters['filter_supplier_name'] ?? ''); ?>" placeholder="Filtrar fornecedora" aria-label="Filtrar fornecedora"></th>
            <th></th>
            <th></th>
            <th><input type="search" data-filter-col="consignment_status" data-query-param="filter_consignment_status" value="<?php echo $esc($filters['filter_consignment_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
            <th></th>
            <th></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="no-results"><td colspan="10">Nenhum produto encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row):
              $productId = (int) ($row['product_id'] ?? 0);
              $sku = $esc($row['sku'] ?? '');
              $name = $esc($row['product_name'] ?? $row['name'] ?? '');
              $supplierName = $esc($row['supplier_name'] ?? '(sem nome)');
              $receivedAt = $row['received_at'] ?? '';
              $status = $row['consignment_status'] ?? '';
              $statusInfo = $statusOptions[$status] ?? ['label' => $status, 'badge' => 'secondary'];
              $price = (float) ($row['price'] ?? 0);
              $percent = (float) ($row['consignment_percent_snapshot'] ?? $row['percentual_consignacao'] ?? 0);
              $commission = $price > 0 && $percent > 0 ? $price * $percent / 100 : 0;
              $aging = $receivedAt ? (int) ((time() - strtotime($receivedAt)) / 86400) : 0;
            ?>
              <tr>
                <td><input type="checkbox" name="product_ids[]" value="<?php echo $productId; ?>"></td>
                <td data-col="sku" data-value="<?php echo $sku; ?>"><?php echo $sku; ?></td>
                <td data-col="product_name" data-value="<?php echo $name; ?>"><?php echo $name; ?></td>
                <td data-col="supplier_name" data-value="<?php echo $supplierName; ?>"><?php echo $supplierName; ?></td>
                <td data-col="received_at"><?php echo $receivedAt ? date('d/m/Y', strtotime($receivedAt)) : '<span style="color:var(--muted);">—</span>'; ?></td>
                <td data-col="aging"><?php echo $aging; ?>d</td>
                <td data-col="consignment_status" data-value="<?php echo $esc($status); ?>"><span class="badge <?php echo $statusInfo['badge']; ?>"><?php echo $esc($statusInfo['label']); ?></span></td>
                <td data-col="price" data-value="<?php echo $price; ?>">R$ <?php echo number_format($price, 2, ',', '.'); ?></td>
                <td data-col="percentual" data-value="<?php echo $percent; ?>"><?php echo $percent > 0 ? number_format($percent, 0) . '%' : '<span style="color:var(--muted);">—</span>'; ?></td>
                <td data-col="commission" data-value="<?php echo $commission; ?>">R$ <?php echo number_format($commission, 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<!-- Pagination -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildProductsLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildProductsLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildProductsLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildProductsLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleAll(master) {
  document.querySelectorAll('#bulkForm input[name="product_ids[]"]').forEach(function(cb) {
    cb.checked = master.checked;
  });
}
(function() {
  var perPage = document.getElementById('perPageProducts');
  var form = document.getElementById('perPageFormProducts');
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
