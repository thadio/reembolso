<?php
/** @var array<int, array<string, mixed>> $rows */
/** @var array<int, string> $errors */
/** @var string $success */
/** @var string $statusFilter */
/** @var string $roleFilter */
/** @var array<string, string> $roleOptions */
/** @var bool $isTrashView */
/** @var string $searchQuery */
/** @var array<string, mixed> $filters */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, int> $perPageOptions */
/** @var int $totalRows */
/** @var int $totalPages */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var callable $esc */
?>
<?php
  $statusFilter = $statusFilter ?? '';
  $roleFilter = $roleFilter ?? '';
  $isTrashView = $isTrashView ?? ($statusFilter === 'trash');
  $searchQuery = $searchQuery ?? '';
  $filters = $filters ?? [];
  $page = max(1, (int) ($page ?? 1));
  $perPage = max(1, (int) ($perPage ?? 100));
  $perPageOptions = $perPageOptions ?? [50, 100, 200];
  $totalRows = max(0, (int) ($totalRows ?? 0));
  $totalPages = max(1, (int) ($totalPages ?? 1));
  $sortKey = $sortKey ?? 'full_name';
  $sortDir = strtoupper((string) ($sortDir ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
  $rangeStart = $totalRows > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

  $canCreate = userCan('people.create') || userCan('customers.create') || userCan('vendors.create');
  $canEdit = userCan('people.edit') || userCan('customers.edit') || userCan('vendors.edit');
  $canDelete = userCan('people.delete') || userCan('customers.delete') || userCan('vendors.delete');
  $canRestore = userCan('people.restore') || userCan('customers.restore') || userCan('vendors.restore');
  $canForceDelete = userCan('people.force_delete') || userCan('customers.force_delete') || userCan('vendors.force_delete');
  $roleOptions = $roleOptions ?? [];
  $roleLinks = [
    '' => 'Todas',
    'cliente' => 'Clientes',
    'fornecedor' => 'Fornecedores',
    'usuario_retratoapp' => 'Usuários app',
  ];

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

  $buildBaseQuery = function () use ($statusFilter, $roleFilter, $searchQuery, $perPage, $sortKey, $sortDir, $columnFilterParams): array {
      $query = ['page' => 1, 'per_page' => $perPage];
      if ($statusFilter !== '') {
          $query['status'] = $statusFilter;
      }
      if ($roleFilter !== '') {
          $query['role'] = $roleFilter;
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

  $buildListLink = function (string $role) use ($buildBaseQuery, $isTrashView): string {
      $query = $buildBaseQuery();
      $query['page'] = 1;
      if ($isTrashView) {
          $query['status'] = 'trash';
      } else {
          unset($query['status']);
      }
      if ($role !== '') {
          $query['role'] = $role;
      } else {
          unset($query['role']);
      }
      return 'pessoa-list.php?' . http_build_query($query);
  };

  $trashLink = function (bool $toTrash) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = 1;
      if ($toTrash) {
          $query['status'] = 'trash';
      } else {
          unset($query['status']);
      }
      return 'pessoa-list.php?' . http_build_query($query);
  };

  $buildPageLink = function (int $targetPage) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = $targetPage;
      return 'pessoa-list.php?' . http_build_query($query);
  };
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Pessoas</h1>
    <div class="subtitle">
      <?php echo $isTrashView ? 'Lixeira do sistema.' : 'Cadastros unificados. Pessoas é a fonte da verdade.'; ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($canCreate): ?>
      <a class="btn primary" href="pessoa-cadastro.php<?php echo $roleFilter !== '' ? '?role=' . $esc($roleFilter) : ''; ?>">Nova pessoa</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?php echo $esc($trashLink(!$isTrashView)); ?>">
      <?php echo $isTrashView ? 'Voltar aos ativos' : 'Ver lixeira'; ?>
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 16px;">
  <?php foreach ($roleLinks as $role => $label): ?>
    <a class="btn <?php echo $roleFilter === $role ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildListLink($role)); ?>">
      <?php echo $esc($label); ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em pessoas" value="<?php echo $esc($searchQuery); ?>">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($statusFilter !== ''): ?>
      <input type="hidden" name="status" value="<?php echo $esc($statusFilter); ?>">
    <?php endif; ?>
    <?php if ($roleFilter !== ''): ?>
      <input type="hidden" name="role" value="<?php echo $esc($roleFilter); ?>">
    <?php endif; ?>
    <?php if ($searchQuery !== ''): ?>
      <input type="hidden" name="q" value="<?php echo $esc($searchQuery); ?>">
    <?php endif; ?>
    <input type="hidden" name="page" value="1">
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
    <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalRows; ?></span>
  </form>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server"<?php echo $isTrashView ? ' data-table-trash-view="true"' : ''; ?> class="people-table">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="full_name" aria-sort="none">Nome</th>
        <th data-sort-key="email" aria-sort="none">E-mail</th>
        <th data-sort-key="phone" aria-sort="none">Telefone</th>
        <th data-sort-key="roles" aria-sort="none">Papéis</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="city_state" aria-sort="none">Cidade/UF</th>
        <th data-sort-key="vendor" aria-sort="none">Fornecedor</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilterParams['filter_id'] ?? ''); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="full_name" data-query-param="filter_full_name" value="<?php echo $esc($columnFilterParams['filter_full_name'] ?? ''); ?>" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="email" data-query-param="filter_email" value="<?php echo $esc($columnFilterParams['filter_email'] ?? ''); ?>" placeholder="Filtrar e-mail" aria-label="Filtrar e-mail"></th>
        <th><input type="search" data-filter-col="phone" data-query-param="filter_phone" value="<?php echo $esc($columnFilterParams['filter_phone'] ?? ''); ?>" placeholder="Filtrar telefone" aria-label="Filtrar telefone"></th>
        <th><input type="search" data-filter-col="roles" data-query-param="filter_roles" value="<?php echo $esc($columnFilterParams['filter_roles'] ?? ''); ?>" placeholder="Filtrar papéis" aria-label="Filtrar papéis"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilterParams['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="city_state" data-query-param="filter_city_state" value="<?php echo $esc($columnFilterParams['filter_city_state'] ?? ''); ?>" placeholder="Filtrar cidade/UF" aria-label="Filtrar cidade/UF"></th>
        <th><input type="search" data-filter-col="vendor" data-query-param="filter_vendor" value="<?php echo $esc($columnFilterParams['filter_vendor'] ?? ''); ?>" placeholder="Filtrar fornecedor" aria-label="Filtrar fornecedor"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="9">Nenhuma pessoa cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $rowId = (int) ($row['id'] ?? 0);
            $rowLink = (!$isTrashView && $canEdit) ? 'pessoa-cadastro.php?id=' . $rowId : '';
            $roles = $row['roles'] ?? [];
            $rolesLabel = implode(', ', array_map(function ($role) use ($roleOptions) {
                return $roleOptions[$role] ?? $role;
            }, $roles));
            $vendorCode = $row['vendor_code'] ?? '';
            $cityState = trim(($row['city'] ?? '') . '/' . ($row['state'] ?? ''), '/');
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo $rowId; ?>">#<?php echo $rowId; ?></td>
            <td data-value="<?php echo $esc($row['full_name'] ?? ''); ?>"><?php echo $esc($row['full_name'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($row['email'] ?? ''); ?>"><?php echo $esc($row['email'] ?? ''); ?><?php if (!empty($row['email2'])): ?><br><small style="opacity:.6"><?php echo $esc($row['email2']); ?></small><?php endif; ?></td>
            <td data-value="<?php echo $esc($row['phone'] ?? ''); ?>"><?php echo $esc($row['phone'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($rolesLabel); ?>">
              <?php if (!empty($roles)): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <?php foreach ($roles as $role): ?>
                    <span class="pill small"><?php echo $esc($roleOptions[$role] ?? $role); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>"><?php echo $esc($row['status'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($cityState); ?>"><?php echo $esc($cityState); ?></td>
            <td data-value="<?php echo $esc((string) $vendorCode); ?>"><?php echo $vendorCode !== '' ? $esc((string) $vendorCode) : '-'; ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($isTrashView): ?>
                  <?php if ($canRestore): ?>
                    <form method="post" onsubmit="return confirm('Restaurar esta pessoa?');" style="margin:0;">
                      <input type="hidden" name="restore_id" value="<?php echo $rowId; ?>">
                      <button class="icon-btn success" type="submit" aria-label="Restaurar" title="Restaurar">
                        <svg aria-hidden="true"><use href="#icon-restore"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canForceDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir definitivamente esta pessoa?');" style="margin:0;">
                      <input type="hidden" name="force_delete_id" value="<?php echo $rowId; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir definitivamente" title="Excluir definitivamente">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$canRestore && !$canForceDelete): ?>
                    <span class="muted">Sem permissão</span>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Enviar esta pessoa para a lixeira?');" style="margin:0;">
                      <input type="hidden" name="delete_id" value="<?php echo $rowId; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!$canEdit && !$canDelete && !$isTrashView): ?>
                  <span class="muted">somente leitura</span>
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
  <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
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
