<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array $filters */
/** @var array $suppliers */
/** @var array $importSummary */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;

$payoutStatusLabels = ConsignmentModuleController::payoutStatusLabels();
$methodLabels = ConsignmentModuleController::payoutMethodLabels();
$canCreate = userCan('consignment_module.create_payout');
$totalPages = $perPage > 0 ? max(1, ceil($total / $perPage)) : 1;
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Pagamentos de Consignação</h1>
    <div class="subtitle"><?php echo $total; ?> pagamento(s) encontrado(s).</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
    <?php if ($canCreate): ?>
      <a class="btn primary" href="consignacao-pagamento-cadastro.php">Novo Pagamento</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>
<?php
$importSummary = is_array($importSummary ?? null) ? $importSummary : [];
$unmatchedImports = (int) ($importSummary['unmatched'] ?? 0);
?>
<?php if ($unmatchedImports > 0): ?>
  <div class="alert warning">
    <?php echo $unmatchedImports; ?> lançamento(s) PIX ficaram sem conciliação automática e permanecem para revisão manual.
    <?php if (userCan('consignment_module.admin_override')): ?>
      <a href="consignacao-inconsistencias.php?action=backfill_review" style="margin-left:8px;">Abrir revisão manual</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$buildPayoutLink = function (int $targetPage) use ($filters, $perPage): string {
    $qs = array_merge($filters, ['page' => $targetPage, 'per_page' => $perPage]);
    return 'consignacao-pagamento-list.php?' . http_build_query($qs);
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
    <select data-select-filter data-param="status" aria-label="Filtrar por status">
      <option value="">Todos os status</option>
      <?php foreach ($payoutStatusLabels as $key => $opt): ?>
        <option value="<?php echo $key; ?>" <?php echo ($filters['status'] ?? '') === $key ? 'selected' : ''; ?>>
          <?php echo $esc($opt['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="date" data-select-filter data-param="date_from" value="<?php echo $esc($filters['date_from'] ?? ''); ?>" title="Data de" aria-label="Data de">
    <input type="date" data-select-filter data-param="date_to" value="<?php echo $esc($filters['date_to'] ?? ''); ?>" title="Data até" aria-label="Data até">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageFormPayouts" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="page" value="1">
    <?php foreach ($filters as $fk => $fv): ?>
      <?php if ($fk !== 'page' && $fk !== 'per_page' && $fv !== ''): ?>
        <input type="hidden" name="<?php echo $esc($fk); ?>" value="<?php echo $esc((string) $fv); ?>">
      <?php endif; ?>
    <?php endforeach; ?>
    <label for="perPagePayouts" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPagePayouts" name="per_page">
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
          <th data-sort-key="id" aria-sort="none">ID</th>
          <th data-sort-key="payout_date" aria-sort="none">Data</th>
          <th data-sort-key="supplier_name" aria-sort="none">Fornecedora</th>
          <th data-sort-key="method" aria-sort="none">Método</th>
          <th data-sort-key="total_amount" aria-sort="none">Valor total</th>
          <th data-sort-key="items_count" aria-sort="none">Itens</th>
          <th data-sort-key="status" aria-sort="none">Status</th>
          <th data-sort-key="reference" aria-sort="none">Referência</th>
          <th class="col-actions">Ações</th>
        </tr>
        <tr class="filters-row">
          <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($filters['filter_id'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar ID"></th>
          <th></th>
          <th><input type="search" data-filter-col="supplier_name" data-query-param="filter_supplier_name" value="<?php echo $esc($filters['filter_supplier_name'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar fornecedora"></th>
          <th><input type="search" data-filter-col="method" data-query-param="filter_method" value="<?php echo $esc($filters['filter_method'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar método"></th>
          <th><input type="search" data-filter-col="total_amount" data-query-param="filter_total_amount" value="<?php echo $esc($filters['filter_total_amount'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar valor"></th>
          <th></th>
          <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($filters['filter_status'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar status"></th>
          <th><input type="search" data-filter-col="reference" data-query-param="filter_reference" value="<?php echo $esc($filters['filter_reference'] ?? ''); ?>" placeholder="Filtrar" aria-label="Filtrar referência"></th>
          <th class="col-actions"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="no-results"><td colspan="9">Nenhum pagamento encontrado.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $id = (int)($row['id'] ?? 0);
            $status = $row['status'] ?? '';
            $statusInfo = $payoutStatusLabels[$status] ?? ['label' => $status, 'badge' => 'secondary'];
            $method = $methodLabels[$row['method'] ?? ''] ?? ($row['method'] ?? '');
          ?>
            <tr data-row-href="consignacao-pagamento-list.php?id=<?php echo $id; ?>&amp;action=show">
              <td data-col="id" data-value="<?php echo $id; ?>"><?php echo $id; ?></td>
              <td data-col="payout_date"><?php echo !empty($row['payout_date']) ? date('d/m/Y', strtotime($row['payout_date'])) : '<span style="color:var(--muted);">—</span>'; ?></td>
              <td data-col="supplier_name" data-value="<?php echo $esc($row['supplier_name'] ?? ''); ?>"><?php echo $esc($row['supplier_name'] ?? '(sem nome)'); ?></td>
              <td data-col="method" data-value="<?php echo $esc($method); ?>"><?php echo $esc($method); ?></td>
              <td data-col="total_amount" data-value="<?php echo (float)($row['total_amount'] ?? 0); ?>">R$ <?php echo number_format((float)($row['total_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td data-col="items_count"><?php echo (int)($row['items_count'] ?? 0); ?></td>
              <td data-col="status" data-value="<?php echo $esc($status); ?>"><span class="badge <?php echo $statusInfo['badge']; ?>"><?php echo $esc($statusInfo['label']); ?></span></td>
              <td data-col="reference" data-value="<?php echo $esc($row['reference'] ?? ''); ?>"><?php echo $esc(mb_strimwidth($row['reference'] ?? '', 0, 40, '...')); ?></td>
              <td class="col-actions">
                <div class="actions">
                  <a href="consignacao-pagamento-list.php?id=<?php echo $id; ?>&action=show" class="icon-btn" title="Ver" aria-label="Ver">
                    <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                  </a>
                  <?php if ($status === 'confirmado'): ?>
                    <a href="consignacao-pagamento-cadastro.php?id=<?php echo $id; ?>&action=receipt" class="icon-btn" title="Recibo" aria-label="Recibo">
                      <svg aria-hidden="true"><use href="#icon-file"></use></svg>
                    </a>
                  <?php endif; ?>
                  <?php if ($status === 'rascunho' && $canCreate): ?>
                    <a href="consignacao-pagamento-cadastro.php?id=<?php echo $id; ?>" class="icon-btn" title="Editar" aria-label="Editar">
                      <svg aria-hidden="true"><use href="#icon-edit"></use></svg>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
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
      <a class="btn ghost" href="<?php echo $esc($buildPayoutLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildPayoutLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPayoutLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildPayoutLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
  var perPage = document.getElementById('perPagePayouts');
  var form = document.getElementById('perPageFormPayouts');
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
