<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $statusOptions */
/** @var array $filters */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalBrands */
/** @var int $totalPages */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var callable $esc */
?>
<?php
  $rows = $rows ?? [];
  $errors = $errors ?? [];
  $success = $success ?? '';
  $statusOptions = $statusOptions ?? [];
  $filters = $filters ?? [];
  $page = $page ?? 1;
  $perPage = $perPage ?? 50;
  $perPageOptions = $perPageOptions ?? [25, 50, 100];
  $totalBrands = $totalBrands ?? 0;
  $totalPages = $totalPages ?? 1;
  $sortKey = $sortKey ?? 'name';
  $sortDir = strtoupper((string) ($sortDir ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
  $canCreate = userCan('brands.create');
  $canEdit = userCan('brands.edit');
  $canDelete = userCan('brands.delete');
  $columnFilterParams = [];
  foreach (($filters ?? []) as $key => $value) {
    if (strpos((string) $key, 'filter_') !== 0) {
      continue;
    }
    $value = trim((string) $value);
    if ($value === '') {
      continue;
    }
    $columnFilterParams[(string) $key] = $value;
  }
  $rangeStart = $totalBrands > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalBrands > 0 ? min($totalBrands, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage, $filters, $sortKey, $sortDir, $columnFilterParams): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage];
    if (!empty($filters['status'])) {
      $query['status'] = $filters['status'];
    }
    if (!empty($filters['search'])) {
      $query['q'] = $filters['search'];
      $query['search'] = $filters['search'];
    }
    if ($sortKey !== '') {
      $query['sort_key'] = $sortKey;
      $query['sort'] = $sortKey;
    }
    if ($sortDir !== '') {
      $query['sort_dir'] = strtolower($sortDir);
      $query['dir'] = strtolower($sortDir);
    }
    foreach ($columnFilterParams as $param => $value) {
      $query[$param] = $value;
    }
    return 'marca-list.php?' . http_build_query($query);
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Marcas</h1>
    <div class="subtitle">Catálogo interno de marcas</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="marca-cadastro.php">Nova marca</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em marcas" value="<?php echo $esc($filters['search'] ?? ''); ?>">
    <select name="filter_status" data-select-filter data-param="status" aria-label="Filtrar por status">
      <option value="">Todos os status</option>
      <?php foreach ($statusOptions as $opt): ?>
        <option value="<?php echo $esc($opt['value']); ?>" <?php echo ($filters['status'] ?? '') === $opt['value'] ? 'selected' : ''; ?>>
          <?php echo $esc($opt['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="page" value="1">
    <?php if (!empty($filters['status'])): ?>
      <input type="hidden" name="status" value="<?php echo $esc($filters['status']); ?>">
    <?php endif; ?>
    <?php if (!empty($filters['search'])): ?>
      <input type="hidden" name="q" value="<?php echo $esc($filters['search']); ?>">
      <input type="hidden" name="search" value="<?php echo $esc($filters['search']); ?>">
    <?php endif; ?>
    <?php if (!empty($sortKey)): ?>
      <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
      <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
    <?php endif; ?>
    <?php if (!empty($sortDir)): ?>
      <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
    <?php endif; ?>
    <?php foreach ($columnFilterParams as $param => $value): ?>
      <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
    <?php endforeach; ?>
    <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalBrands; ?></span>
  </form>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="slug" aria-sort="none">Slug</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="product_count" aria-sort="none">Produtos</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilterParams['filter_id'] ?? ''); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="name" data-query-param="filter_name" value="<?php echo $esc($columnFilterParams['filter_name'] ?? ''); ?>" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="slug" data-query-param="filter_slug" value="<?php echo $esc($columnFilterParams['filter_slug'] ?? ''); ?>" placeholder="Filtrar slug" aria-label="Filtrar slug"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilterParams['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="product_count" data-query-param="filter_product_count" value="<?php echo $esc($columnFilterParams['filter_product_count'] ?? ''); ?>" placeholder="Filtrar qtd" aria-label="Filtrar quantidade"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="6">Nenhuma marca encontrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php 
            $rowLink = $canEdit ? 'marca-cadastro.php?id=' . (int) $row['id'] : '';
            $statusLabel = \App\Support\CatalogLookup::getTaxonomyStatusLabel($row['status'] ?? 'ativa');
            $productCount = (int)($row['product_count'] ?? 0);
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo $esc((string) $row['id']); ?>"><?php echo $esc((string) $row['id']); ?></td>
            <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td data-value="<?php echo $esc($row['slug'] ?? ''); ?>"><?php echo $esc($row['slug'] ?? '—'); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? 'ativa'); ?>"><?php echo $esc($statusLabel); ?></td>
            <td data-value="<?php echo $esc((string) $productCount); ?>"><?php echo $esc((string) $productCount); ?></td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <form method="post" onsubmit="return confirm('Arquivar esta marca?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Arquivar" title="Arquivar">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
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
    const perPage = document.getElementById('perPage');
    const form = document.getElementById('perPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }

    const filterSelects = Array.from(document.querySelectorAll('[data-select-filter]'));
    filterSelects.forEach((select) => {
      select.addEventListener('change', () => {
        const param = select.dataset.param;
        if (!param) {
          return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('page', '1');
        if (select.value) {
          url.searchParams.set(param, select.value);
        } else {
          url.searchParams.delete(param);
        }
        window.location.assign(url.toString());
      });
    });
  })();
</script>
