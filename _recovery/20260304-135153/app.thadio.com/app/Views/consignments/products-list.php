<?php
/** @var array $consignments */
/** @var array $errors */
/** @var string $success */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var string $statusFilter */
/** @var string $supplierFilter */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $searchQuery */
/** @var array $columnFilters */
/** @var array $statuses */
/** @var array $suppliers */
/** @var string $sortKey */
/** @var string $sortDir */
?>
<?php
  $rows = is_array($consignments ?? null) ? $consignments : [];
  $errors = is_array($errors ?? null) ? $errors : [];
  $success = (string) ($success ?? '');
  $total = isset($total) ? (int) $total : count($rows);
  $page = isset($page) ? max(1, (int) $page) : 1;
  $totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
  $perPage = isset($perPage) ? (int) $perPage : 50;
  $perPageOptions = is_array($perPageOptions ?? null) ? $perPageOptions : [25, 50, 100, 200];
  $statusFilter = trim((string) ($statusFilter ?? ''));
  $supplierFilter = trim((string) ($supplierFilter ?? ''));
  $dateFrom = trim((string) ($dateFrom ?? ''));
  $dateTo = trim((string) ($dateTo ?? ''));
  $searchQuery = trim((string) ($searchQuery ?? ''));
  $columnFilters = is_array($columnFilters ?? null) ? $columnFilters : [];
  $statuses = is_array($statuses ?? null) ? $statuses : [];
  $suppliers = is_array($suppliers ?? null) ? $suppliers : [];
  $sortKey = trim((string) ($sortKey ?? 'received_at'));
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

  $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  $rangeStart = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $total > 0 ? min($total, $page * $perPage) : 0;
  $canCreate = userCan('consignments.create');
  $canEdit = userCan('consignments.edit');
  $canDelete = userCan('consignments.delete');
  $canClose = userCan('consignments.close');

  $baseQuery = [
    'per_page' => $perPage,
  ];
  if ($statusFilter !== '') {
    $baseQuery['status'] = $statusFilter;
  }
  if ($supplierFilter !== '') {
    $baseQuery['supplier_pessoa_id'] = $supplierFilter;
  }
  if ($dateFrom !== '') {
    $baseQuery['date_from'] = $dateFrom;
  }
  if ($dateTo !== '') {
    $baseQuery['date_to'] = $dateTo;
  }
  if ($searchQuery !== '') {
    $baseQuery['q'] = $searchQuery;
    $baseQuery['search'] = $searchQuery;
  }
  if ($sortKey !== '') {
    $baseQuery['sort_key'] = $sortKey;
    $baseQuery['sort'] = $sortKey;
  }
  if ($sortDir !== '') {
    $baseQuery['sort_dir'] = strtolower($sortDir);
    $baseQuery['dir'] = strtolower($sortDir);
  }
  foreach ($columnFilters as $param => $value) {
    if (trim((string) $value) === '') {
      continue;
    }
    $baseQuery[$param] = $value;
  }

  $buildLink = function (int $targetPage) use ($baseQuery): string {
    $query = $baseQuery;
    $query['page'] = max(1, $targetPage);
    return 'consignacao-produto-list.php?' . http_build_query($query);
  };

  $formatDate = static function (?string $value): string {
    if (!$value) {
      return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return $value;
    }
    return date('d/m/Y H:i', $ts);
  };

  $formatMoney = static function ($value): string {
    if ($value === null || $value === '') {
      return '-';
    }
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Consignações de produtos</h1>
    <div class="subtitle">Busca global no backend com paginação consistente.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="consignacao-produto-cadastro.php?action=create">Nova consignação</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="card" style="margin:12px 0;display:grid;gap:10px;">
  <h2 style="margin:0;font-size:16px;">Filtros</h2>
  <form method="get" action="consignacao-produto-list.php" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;">
    <input type="hidden" name="page" value="1">
    <?php if ($sortKey !== ''): ?>
      <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
      <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
    <?php endif; ?>
    <?php if ($sortDir !== ''): ?>
      <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
    <?php endif; ?>
    <?php foreach ($columnFilters as $param => $value): ?>
      <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc((string) $value); ?>">
    <?php endforeach; ?>

    <div class="field">
      <label for="consignment_q">Busca</label>
      <input id="consignment_q" type="search" name="q" value="<?php echo $esc($searchQuery); ?>" placeholder="ID, fornecedor, status, observação">
    </div>
    <div class="field">
      <label for="consignment_status">Status</label>
      <select id="consignment_status" name="status">
        <option value="">Todos</option>
        <?php foreach ($statuses as $statusValue => $statusLabel): ?>
          <option value="<?php echo $esc((string) $statusValue); ?>" <?php echo $statusFilter === (string) $statusValue ? 'selected' : ''; ?>>
            <?php echo $esc((string) $statusLabel); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="consignment_supplier">Fornecedor</label>
      <select id="consignment_supplier" name="supplier_pessoa_id">
        <option value="">Todos</option>
        <?php foreach ($suppliers as $supplier): ?>
          <?php
            $supplierId = (string) ((int) ($supplier['id'] ?? 0));
            $supplierName = (string) ($supplier['full_name'] ?? ($supplier['nome'] ?? ('Pessoa ' . $supplierId)));
          ?>
          <option value="<?php echo $esc($supplierId); ?>" <?php echo $supplierFilter === $supplierId ? 'selected' : ''; ?>>
            <?php echo $esc($supplierName . ' (#' . $supplierId . ')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="consignment_date_from">Recebido de</label>
      <input id="consignment_date_from" type="date" name="date_from" value="<?php echo $esc($dateFrom); ?>">
    </div>
    <div class="field">
      <label for="consignment_date_to">Recebido até</label>
      <input id="consignment_date_to" type="date" name="date_to" value="<?php echo $esc($dateTo); ?>">
    </div>
    <div class="field" style="display:flex;gap:8px;align-items:center;">
      <button class="ghost" type="submit">Aplicar</button>
      <a class="btn ghost" href="consignacao-produto-list.php">Limpar</a>
    </div>
  </form>
</div>

<div data-table-scope>
  <div class="table-tools">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em consignações" value="<?php echo $esc($searchQuery); ?>">
      <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
    </div>
    <form method="get" id="consignmentPerPageForm" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="page" value="1">
      <?php foreach ($baseQuery as $param => $value): ?>
        <?php if ($param === 'per_page' || $param === 'page'): ?>
          <?php continue; ?>
        <?php endif; ?>
        <input type="hidden" name="<?php echo $esc((string) $param); ?>" value="<?php echo $esc((string) $value); ?>">
      <?php endforeach; ?>
      <label for="consignment_per_page" style="font-size:13px;color:var(--muted);">Itens por página</label>
      <select id="consignment_per_page" name="per_page">
        <?php foreach ($perPageOptions as $option): ?>
          <option value="<?php echo (int) $option; ?>" <?php echo (int) $option === $perPage ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $total; ?></span>
    </form>
  </div>

  <div style="overflow:auto;">
    <table data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="<?php echo $sortKey === 'id' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">ID</th>
        <th data-sort-key="received_at" aria-sort="<?php echo $sortKey === 'received_at' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Recebido em</th>
        <th data-sort-key="supplier_name" aria-sort="<?php echo $sortKey === 'supplier_name' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Fornecedor</th>
        <th data-sort-key="status" aria-sort="<?php echo $sortKey === 'status' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Status</th>
        <th data-sort-key="items_count" aria-sort="<?php echo $sortKey === 'items_count' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Itens</th>
        <th data-sort-key="total_value" aria-sort="<?php echo $sortKey === 'total_value' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Valor estimado</th>
        <th data-sort-key="notes" aria-sort="<?php echo $sortKey === 'notes' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Observações</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc((string) ($columnFilters['filter_id'] ?? '')); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="received_at" data-query-param="filter_received_at" value="<?php echo $esc((string) ($columnFilters['filter_received_at'] ?? '')); ?>" placeholder="Filtrar data" aria-label="Filtrar data"></th>
        <th><input type="search" data-filter-col="supplier_name" data-query-param="filter_supplier_name" value="<?php echo $esc((string) ($columnFilters['filter_supplier_name'] ?? '')); ?>" placeholder="Filtrar fornecedor" aria-label="Filtrar fornecedor"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc((string) ($columnFilters['filter_status'] ?? '')); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="items_count" data-query-param="filter_items_count" value="<?php echo $esc((string) ($columnFilters['filter_items_count'] ?? '')); ?>" placeholder="Filtrar itens" aria-label="Filtrar itens"></th>
        <th><input type="search" data-filter-col="total_value" data-query-param="filter_total_value" value="<?php echo $esc((string) ($columnFilters['filter_total_value'] ?? '')); ?>" placeholder="Filtrar valor" aria-label="Filtrar valor"></th>
        <th><input type="search" data-filter-col="notes" data-query-param="filter_notes" value="<?php echo $esc((string) ($columnFilters['filter_notes'] ?? '')); ?>" placeholder="Filtrar observações" aria-label="Filtrar observações"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="8">Nenhuma consignação encontrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $rowId = (int) ($row['id'] ?? 0);
            $statusValue = (string) ($row['status'] ?? '');
            $statusLabel = $statuses[$statusValue] ?? ($statusValue !== '' ? ucfirst($statusValue) : '-');
            $rowNotes = trim((string) ($row['notes'] ?? ''));
            $notesDisplay = $rowNotes !== '' ? $rowNotes : '-';
            if (strlen($notesDisplay) > 80) {
              $notesDisplay = substr($notesDisplay, 0, 77) . '...';
            }
            $rowLink = $canEdit ? 'consignacao-produto-cadastro.php?action=edit&id=' . $rowId : '';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo $rowId; ?>">#<?php echo $rowId; ?></td>
            <td data-value="<?php echo $esc((string) ($row['received_at'] ?? '')); ?>"><?php echo $esc($formatDate($row['received_at'] ?? null)); ?></td>
            <td data-value="<?php echo $esc((string) ($row['supplier_name'] ?? '')); ?>">
              <?php echo $esc((string) ($row['supplier_name'] ?? 'Sem fornecedor')); ?>
            </td>
            <td data-value="<?php echo $esc($statusValue); ?>"><?php echo $esc($statusLabel); ?></td>
            <td data-value="<?php echo (int) ($row['items_count'] ?? 0); ?>"><?php echo number_format((int) ($row['items_count'] ?? 0), 0, ',', '.'); ?></td>
            <td data-value="<?php echo $esc((string) ($row['total_value'] ?? '0')); ?>"><?php echo $esc($formatMoney($row['total_value'] ?? 0)); ?></td>
            <td data-value="<?php echo $esc($rowNotes); ?>"><?php echo $esc($notesDisplay); ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($canEdit): ?>
                  <a class="icon-btn neutral" href="consignacao-produto-cadastro.php?action=edit&id=<?php echo $rowId; ?>" aria-label="Editar" title="Editar">
                    <svg aria-hidden="true"><use href="#icon-edit"></use></svg>
                  </a>
                <?php endif; ?>
                <a class="icon-btn neutral" href="consignacao-produto-cadastro.php?action=show&id=<?php echo $rowId; ?>" aria-label="Visualizar" title="Visualizar">
                  <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                </a>
                <?php if ($canClose && $statusValue === 'aberta'): ?>
                  <form method="post" action="consignacao-produto-cadastro.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?php echo $rowId; ?>">
                    <input type="hidden" name="action" value="close">
                    <button class="icon-btn success" type="submit" aria-label="Fechar consignação" title="Fechar consignação">
                      <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <form method="post" action="consignacao-produto-cadastro.php" onsubmit="return confirm('Excluir esta consignação?');" style="margin:0;">
                    <input type="hidden" name="id" value="<?php echo $rowId; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
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

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
    <?php endif; ?>
  </div>
</div>

<script>
  (function() {
    const perPage = document.getElementById('consignment_per_page');
    const form = document.getElementById('consignmentPerPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>
