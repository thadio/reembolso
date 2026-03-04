<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalProducts */
/** @var int $totalPages */
/** @var array $filters */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var array $brands */
/** @var array $categories */
/** @var array $statusOptions */
/** @var callable $esc */
?>
<?php
  $page = $page ?? 1;
  $perPage = $perPage ?? 50;
  $totalProducts = $totalProducts ?? 0;
  $totalPages = $totalPages ?? 1;
  $filters = $filters ?? [];
  $sortKey = $sortKey ?? 'created_at';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $brands = $brands ?? [];
  $categories = $categories ?? [];
  $statusOptions = $statusOptions ?? [];
  $canCreate = userCan('products.create');
  $canEdit = userCan('products.edit');
  $canDelete = userCan('products.delete');
  $columnFilterParams = [];
  foreach ($filters as $key => $value) {
    if (strpos((string) $key, 'filter_') !== 0) {
      continue;
    }
    if (is_array($value)) {
      $value = implode(',', array_values(array_filter(array_map('strval', $value), static fn ($v) => $v !== '')));
    }
    $value = trim((string) $value);
    if ($value === '') {
      continue;
    }
    $columnFilterParams[(string) $key] = $value;
  }
  $rangeStart = $totalProducts > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalProducts > 0 ? min($totalProducts, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage, $filters, $sortKey, $sortDir, $columnFilterParams): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage];
    if (!empty($filters['status'])) {
      $query['status'] = $filters['status'];
    }
    if (!empty($filters['search'])) {
        $query['q'] = $filters['search'];
        $query['search'] = $filters['search'];
    }
    if (!empty($filters['brand_id'])) {
        $query['brand_id'] = $filters['brand_id'];
    }
    if (!empty($filters['category_id'])) {
        $query['category_id'] = $filters['category_id'];
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
    return 'produto-list.php?' . http_build_query($query);
  };
?>
<style>
  .products-table .col-thumb { width: 52px; text-align: center; padding: 4px; }
  .products-table .col-thumb img { display: block; margin: 0 auto; }
</style>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Produtos</h1>
    <div class="subtitle">Catálogo interno de produtos</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="produto-cadastro.php">Novo produto</a>
  <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em produtos" value="<?php echo $esc($filters['search'] ?? ''); ?>">
    <select name="filter_status" data-select-filter data-param="status" aria-label="Filtrar por status">
      <option value="">Todos os status</option>
      <?php foreach ($statusOptions as $opt): ?>
        <option value="<?php echo $esc($opt['value']); ?>" <?php echo ($filters['status'] ?? '') === $opt['value'] ? 'selected' : ''; ?>>
          <?php echo $esc($opt['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="filter_brand" data-select-filter data-param="brand_id" aria-label="Filtrar por marca">
      <option value="">Todas as marcas</option>
      <?php foreach ($brands as $brand): ?>
        <option value="<?php echo (int)$brand['id']; ?>" <?php echo ($filters['brand_id'] ?? 0) === (int)$brand['id'] ? 'selected' : ''; ?>>
          <?php echo $esc($brand['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="filter_category" data-select-filter data-param="category_id" aria-label="Filtrar por categoria">
      <option value="">Todas as categorias</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?php echo (int)$category['id']; ?>" <?php echo ($filters['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : ''; ?>>
          <?php echo $esc($category['name']); ?>
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
    <?php if (!empty($filters['brand_id'])): ?>
      <input type="hidden" name="brand_id" value="<?php echo (int)$filters['brand_id']; ?>">
    <?php endif; ?>
    <?php if (!empty($filters['category_id'])): ?>
      <input type="hidden" name="category_id" value="<?php echo (int)$filters['category_id']; ?>">
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
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalProducts; ?></span>
  </form>
</div>

<div class="table-scroll" data-table-scroll>
  <div class="table-scroll-top" aria-hidden="true">
    <div class="table-scroll-top-inner"></div>
  </div>
  <div class="table-scroll-body">
    <table class="products-table" data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th class="col-thumb">Foto</th>
        <th class="col-sku" data-sort-key="sku" aria-sort="none">SKU</th>
        <th class="col-name" data-sort-key="name" aria-sort="none">Nome</th>
        <th class="col-brand" data-sort-key="brand_id" aria-sort="none">Marca</th>
        <th class="col-category" data-sort-key="category_id" aria-sort="none">Categoria</th>
        <th class="col-price" data-sort-key="price" aria-sort="none">Preço</th>
        <th class="col-qty" data-sort-key="quantity" aria-sort="none">Qtd</th>
        <th class="col-source" data-sort-key="source" aria-sort="none">Tipo</th>
        <th class="col-supplier" data-sort-key="supplier_pessoa_id" aria-sort="none">Fornecedor</th>
        <th class="col-status" data-sort-key="status" aria-sort="none">Status</th>
        <th class="col-visibility" data-sort-key="visibility" aria-sort="none">Visibilidade</th>
        <th class="col-actions">Ação</th>
      </tr>
      <tr class="filters-row">
        <th class="col-thumb"></th>
        <th class="col-sku"><input type="search" data-filter-col="sku" data-query-param="filter_sku" value="<?php echo $esc($columnFilterParams['filter_sku'] ?? ''); ?>" placeholder="Filtrar SKU" aria-label="Filtrar SKU"></th>
        <th class="col-name"><input type="search" data-filter-col="name" data-query-param="filter_name" value="<?php echo $esc($columnFilterParams['filter_name'] ?? ''); ?>" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th class="col-brand"><input type="search" data-filter-col="brand" data-query-param="filter_brand" value="<?php echo $esc($columnFilterParams['filter_brand'] ?? ''); ?>" placeholder="Filtrar marca" aria-label="Filtrar marca"></th>
        <th class="col-category"><input type="search" data-filter-col="category" data-query-param="filter_category" value="<?php echo $esc($columnFilterParams['filter_category'] ?? ''); ?>" placeholder="Filtrar categoria" aria-label="Filtrar categoria"></th>
        <th class="col-price"><input type="search" data-filter-col="price" data-query-param="filter_price" value="<?php echo $esc($columnFilterParams['filter_price'] ?? ''); ?>" placeholder="Filtrar preço" aria-label="Filtrar preço"></th>
        <th class="col-qty"><input type="search" data-filter-col="quantity" data-query-param="filter_quantity" value="<?php echo $esc($columnFilterParams['filter_quantity'] ?? ''); ?>" placeholder="Filtrar qtd" aria-label="Filtrar quantidade"></th>
        <th class="col-source"><input type="search" data-filter-col="source" data-query-param="filter_source" value="<?php echo $esc($columnFilterParams['filter_source'] ?? ''); ?>" placeholder="Filtrar tipo" aria-label="Filtrar tipo de fornecimento"></th>
        <th class="col-supplier"><input type="search" data-filter-col="supplier" data-query-param="filter_supplier" value="<?php echo $esc($columnFilterParams['filter_supplier'] ?? ''); ?>" placeholder="Filtrar fornecedor" aria-label="Filtrar fornecedor"></th>
        <th class="col-status"><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilterParams['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th class="col-visibility"><input type="search" data-filter-col="visibility" data-query-param="filter_visibility" value="<?php echo $esc($columnFilterParams['filter_visibility'] ?? ''); ?>" placeholder="Filtrar visibilidade" aria-label="Filtrar visibilidade"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="12">Nenhum produto encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $rowLink = $canEdit ? 'produto-cadastro.php?id=' . (int) $row['id'] : '';
            $statusLabel = \App\Support\CatalogLookup::getProductStatusLabel($row['status'] ?? '');
            $visibilityLabel = \App\Support\CatalogLookup::getVisibilityLabel($row['visibility'] ?? '');
            $sourceLabel = \App\Support\CatalogLookup::getSourceLabel($row['source'] ?? '');
            $supplierPessoaId = (int) ($row['supplier_pessoa_id'] ?? 0);
            $supplierName = $row['supplier_name'] ?? '';
            $imageSrc = (string) ($row['image_src'] ?? '');
            $thumbSrc = $imageSrc !== '' ? image_url($imageSrc, 'thumb', 60) : '';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td class="col-thumb">
              <?php if ($thumbSrc !== ''): ?>
                <img src="<?php echo $esc($thumbSrc); ?>" alt="<?php echo $esc($row['name']); ?>" width="44" height="44" loading="lazy" style="object-fit:cover;border-radius:4px;">
              <?php else: ?>
                <span style="display:inline-block;width:44px;height:44px;background:var(--surface,#f0f0f0);border-radius:4px;text-align:center;line-height:44px;color:var(--muted);font-size:11px;">—</span>
              <?php endif; ?>
            </td>
            <td class="col-sku" data-value="<?php echo $esc($row['sku'] ?? ''); ?>"><?php echo $esc($row['sku'] ?? ''); ?></td>
            <td class="col-name" data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td class="col-brand" data-value="<?php echo $esc($row['brand_name'] ?? ''); ?>">
              <?php echo $row['brand_name'] ? $esc($row['brand_name']) : '<span style="color:var(--muted);">—</span>'; ?>
            </td>
            <td class="col-category" data-value="<?php echo $esc($row['category_name'] ?? ''); ?>">
              <?php echo $row['category_name'] ? $esc($row['category_name']) : '<span style="color:var(--muted);">—</span>'; ?>
            </td>
            <td class="col-price" data-value="<?php echo $esc((string) ($row['price'] ?? '')); ?>">
              <?php if ($row['price'] !== null && $row['price'] !== ''): ?>
                R$ <?php echo number_format((float) $row['price'], 2, ',', '.'); ?>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td class="col-qty" data-value="<?php echo (int) ($row['quantity'] ?? 0); ?>">
              <?php echo (int) ($row['quantity'] ?? 0); ?>
            </td>
            <td class="col-source" data-value="<?php echo $esc($row['source'] ?? ''); ?>">
              <?php echo ($row['source'] ?? '') !== '' ? $esc($sourceLabel) : '<span style="color:var(--muted);">—</span>'; ?>
            </td>
            <td class="col-supplier" data-value="<?php echo $esc($supplierName); ?>">
              <?php if ($supplierPessoaId > 0): ?>
                <?php echo $esc($supplierName ?: '—'); ?>
                <small style="color:var(--muted);">(#<?php echo $supplierPessoaId; ?>)</small>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td class="col-status" data-value="<?php echo $esc((string) $row['status']); ?>"><?php echo $esc($statusLabel); ?></td>
            <td class="col-visibility" data-value="<?php echo $esc((string) ($row['visibility'] ?? '')); ?>"><?php echo $esc($visibilityLabel); ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($canDelete): ?>
                  <form method="post" onsubmit="return confirm('Arquivar este produto?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Arquivar" title="Arquivar">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if (!$canEdit && !$canDelete): ?>
                  <span style="color:var(--muted);">somente leitura</span>
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
