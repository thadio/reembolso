<?php
/** @var array<int, array<string, mixed>> $rows */
/** @var array<int, string> $errors */
/** @var string $success */
/** @var string $statusFilter */
/** @var bool $isTrashView */
/** @var string $searchQuery */
/** @var array<string, string> $filters */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, int> $perPageOptions */
/** @var int $totalRows */
/** @var int $totalPages */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var array<string, string> $typeOptions */
/** @var callable $esc */
?>
<?php
  $statusFilter = $statusFilter ?? '';
  $isTrashView = $isTrashView ?? ($statusFilter === 'trash');
  $searchQuery = $searchQuery ?? '';
  $filters = $filters ?? [];
  $page = max(1, (int) ($page ?? 1));
  $perPage = max(1, (int) ($perPage ?? 100));
  $perPageOptions = $perPageOptions ?? [50, 100, 200];
  $totalRows = max(0, (int) ($totalRows ?? 0));
  $totalPages = max(1, (int) ($totalPages ?? 1));
  $sortKey = $sortKey ?? 'created_at';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $typeOptions = $typeOptions ?? [];

  $rangeStart = $totalRows > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

  $canView = userCan('voucher_accounts.view');
  $canCreate = userCan('voucher_accounts.create');
  $canEdit = userCan('voucher_accounts.edit');
  $canDelete = userCan('voucher_accounts.delete');
  $canRestore = userCan('voucher_accounts.restore');
  $canForceDelete = userCan('voucher_accounts.force_delete');

  $columnFilterParams = [];
  foreach ($filters as $key => $value) {
      if (strpos((string) $key, 'filter_') !== 0) {
          continue;
      }
      $value = trim((string) $value);
      if ($value === '') {
          continue;
      }
      $columnFilterParams[(string) $key] = $value;
  }

  $buildBaseQuery = function () use ($statusFilter, $searchQuery, $perPage, $sortKey, $sortDir, $columnFilterParams): array {
      $query = ['page' => 1, 'per_page' => $perPage];
      if ($statusFilter !== '') {
          $query['status'] = $statusFilter;
      }
      if ($searchQuery !== '') {
          $query['q'] = $searchQuery;
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
      return $query;
  };

  $buildTrashLink = function (bool $toTrash) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = 1;
      if ($toTrash) {
          $query['status'] = 'trash';
      } else {
          unset($query['status']);
      }
      return 'cupom-credito-list.php?' . http_build_query($query);
  };

  $buildPageLink = function (int $targetPage) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = $targetPage;
      return 'cupom-credito-list.php?' . http_build_query($query);
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Cupons e creditos</h1>
    <div class="subtitle">
      <?php echo $isTrashView ? 'Lixeira de cupons e creditos.' : 'Gerencie saldos por cliente para uso como pagamento.'; ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($canCreate): ?>
      <a class="btn primary" href="cupom-credito-cadastro.php">Novo cupom/credito</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?php echo $esc($buildTrashLink(!$isTrashView)); ?>">
      <?php echo $isTrashView ? 'Voltar aos ativos' : 'Ver lixeira'; ?>
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em cupons e creditos" value="<?php echo $esc($searchQuery); ?>">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabecalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="hidden" name="page" value="1">
    <?php if ($statusFilter !== ''): ?>
      <input type="hidden" name="status" value="<?php echo $esc($statusFilter); ?>">
    <?php endif; ?>
    <?php if ($searchQuery !== ''): ?>
      <input type="hidden" name="q" value="<?php echo $esc($searchQuery); ?>">
      <input type="hidden" name="search" value="<?php echo $esc($searchQuery); ?>">
    <?php endif; ?>
    <?php if ($sortKey !== ''): ?>
      <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
      <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
    <?php endif; ?>
    <?php if ($sortDir !== ''): ?>
      <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
    <?php endif; ?>
    <?php foreach ($columnFilterParams as $param => $value): ?>
      <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
    <?php endforeach; ?>
    <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por pagina</label>
    <select id="perPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>-<?php echo $rangeEnd; ?> de <?php echo $totalRows; ?></span>
  </form>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server"<?php echo $isTrashView ? ' data-table-trash-view="true"' : ''; ?> class="voucher-accounts-table">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="customer" aria-sort="none">Cliente</th>
        <th data-sort-key="type" aria-sort="none">Tipo</th>
        <th data-sort-key="code" aria-sort="none">Codigo</th>
        <th data-sort-key="balance" aria-sort="none">Saldo</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="description" aria-sort="none">Descricao</th>
        <th class="col-actions">Acoes</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilterParams['filter_id'] ?? ''); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="customer" data-query-param="filter_customer" value="<?php echo $esc($columnFilterParams['filter_customer'] ?? ''); ?>" placeholder="Filtrar cliente" aria-label="Filtrar cliente"></th>
        <th><input type="search" data-filter-col="type" data-query-param="filter_type" value="<?php echo $esc($columnFilterParams['filter_type'] ?? ''); ?>" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
        <th><input type="search" data-filter-col="code" data-query-param="filter_code" value="<?php echo $esc($columnFilterParams['filter_code'] ?? ''); ?>" placeholder="Filtrar codigo" aria-label="Filtrar codigo"></th>
        <th><input type="search" data-filter-col="balance" data-query-param="filter_balance" value="<?php echo $esc($columnFilterParams['filter_balance'] ?? ''); ?>" placeholder="Filtrar saldo" aria-label="Filtrar saldo"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilterParams['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="description" data-query-param="filter_description" value="<?php echo $esc($columnFilterParams['filter_description'] ?? ''); ?>" placeholder="Filtrar descricao" aria-label="Filtrar descricao"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="8">Nenhum cupom/credito cadastrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $personId = (int) ($row['pessoa_id'] ?? 0);
            $customerName = trim((string) ($row['customer_name'] ?? ''));
            $customerEmail = trim((string) ($row['customer_email'] ?? ''));
            $customerLabel = $customerName !== '' ? $customerName : ($personId > 0 ? 'Pessoa #' . $personId : 'Pessoa');
            if ($customerEmail !== '') {
                $customerLabel .= ' - ' . $customerEmail;
            }
            $type = (string) ($row['type'] ?? '');
            $typeLabel = $typeOptions[$type] ?? $type;
            $balance = number_format((float) ($row['balance'] ?? 0), 2, ',', '.');
            $rowLink = (!$isTrashView && ($canEdit || $canView)) ? 'cupom-credito-cadastro.php?id=' . (int) $row['id'] : '';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($customerLabel); ?>">
              <?php if ($rowLink !== ''): ?>
                <a href="<?php echo $esc($rowLink); ?>" style="color:inherit;text-decoration:none;">
                  <?php echo $esc($customerLabel); ?>
                </a>
              <?php else: ?>
                <?php echo $esc($customerLabel); ?>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($typeLabel); ?>"><?php echo $esc($typeLabel); ?></td>
            <td data-value="<?php echo $esc($row['code'] ?? ''); ?>"><?php echo $esc($row['code'] ?? '-'); ?></td>
            <td data-value="<?php echo $esc((string) $balance); ?>">R$ <?php echo $esc($balance); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td data-value="<?php echo $esc($row['description'] ?? ''); ?>"><?php echo $esc($row['description'] ?? ''); ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($isTrashView): ?>
                  <?php if ($canRestore): ?>
                    <form method="post" onsubmit="return confirm('Restaurar este cupom/credito?');" style="margin:0;">
                      <input type="hidden" name="restore_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn success" type="submit" aria-label="Restaurar" title="Restaurar">
                        <svg aria-hidden="true"><use href="#icon-restore"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canForceDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir definitivamente este cupom/credito?');" style="margin:0;">
                      <input type="hidden" name="force_delete_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir definitivamente" title="Excluir definitivamente">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$canRestore && !$canForceDelete): ?>
                    <span class="muted">Sem permissao</span>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Enviar este cupom/credito para a lixeira?');" style="margin:0;">
                      <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page + 1)); ?>">Proxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($totalPages)); ?>">Ultima</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Proxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Ultima</span>
    <?php endif; ?>
  </div>
</div>

<script>
  (function () {
    const perPage = document.getElementById('perPage');
    const form = document.getElementById('perPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>
