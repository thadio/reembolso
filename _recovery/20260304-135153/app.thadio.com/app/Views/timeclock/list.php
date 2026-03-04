<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $currentUser */
/** @var bool $canApprove */
/** @var bool $canReport */
/** @var array $allowedTypes */
/** @var array|null $lastEntry */
/** @var array $filters */
/** @var array $userOptions */
/** @var string $searchQuery */
/** @var array<string, string> $columnFilters */
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
  $canCreate = userCan('timeclock.create');
  $filters = $filters ?? [];
  $entryAllowed = in_array('entrada', $allowedTypes, true);
  $exitAllowed = in_array('saida', $allowedTypes, true);
  $nextType = $allowedTypes[0] ?? ($entryAllowed ? 'entrada' : ($exitAllowed ? 'saida' : 'entrada'));
  $nextLabel = $nextType === 'saida' ? 'Saída' : 'Entrada';
  $isEntryNext = $nextType === 'entrada';
  $isExitNext = $nextType === 'saida';
  $entryClass = $isEntryNext ? 'btn primary' : 'btn ghost';
  $exitClass = $isExitNext ? 'btn primary' : 'btn ghost';
  $entryDisabled = (!$entryAllowed || !$isEntryNext) ? 'disabled' : '';
  $exitDisabled = (!$exitAllowed || !$isExitNext) ? 'disabled' : '';
  $lastLabel = 'Nenhum registro ainda.';
  if (!empty($lastEntry)) {
    $lastType = $lastEntry['tipo'] ?? '';
    $lastLabel = ucfirst((string) $lastType) . ' em ' . $esc((string) ($lastEntry['registrado_em'] ?? ''));
    if (!empty($lastEntry['status'])) {
      $lastLabel .= ' • ' . $esc((string) $lastEntry['status']);
    }
  }
  $searchQuery = $searchQuery ?? '';
  $columnFilters = $columnFilters ?? [];
  $page = max(1, (int) ($page ?? 1));
  $perPage = max(1, (int) ($perPage ?? 100));
  $perPageOptions = $perPageOptions ?? [50, 100, 200];
  $totalRows = max(0, (int) ($totalRows ?? 0));
  $totalPages = max(1, (int) ($totalPages ?? 1));
  $sortKey = $sortKey ?? 'registrado_em';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $rangeStart = $totalRows > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;
  $buildBaseQuery = function () use ($filters, $searchQuery, $columnFilters, $perPage, $sortKey, $sortDir): array {
      $query = ['page' => 1, 'per_page' => $perPage];
      if (($filters['status'] ?? '') !== '') {
          $query['status'] = (string) $filters['status'];
      }
      if (($filters['start'] ?? '') !== '') {
          $query['start'] = (string) $filters['start'];
      }
      if (($filters['end'] ?? '') !== '') {
          $query['end'] = (string) $filters['end'];
      }
      if ((int) ($filters['user_id'] ?? 0) > 0) {
          $query['user_id'] = (int) $filters['user_id'];
      }
      if ($searchQuery !== '') {
          $query['q'] = $searchQuery;
          $query['search'] = $searchQuery;
      }
      if ($sortKey !== '') {
          $query['sort_key'] = $sortKey;
          $query['sort'] = $sortKey;
      }
      if ($sortDir !== '') {
          $query['sort_dir'] = strtolower($sortDir);
          $query['dir'] = strtolower($sortDir);
      }
      foreach ($columnFilters as $param => $value) {
          if ((string) $value === '') {
              continue;
          }
          $query[$param] = $value;
      }
      return $query;
  };
  $buildPageLink = function (int $targetPage) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = $targetPage;
      return 'ponto-list.php?' . http_build_query($query);
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Gestão de ponto</h1>
    <div class="subtitle">Registre entradas/saídas, aprove e acompanhe relatórios.</div>
  </div>
  <div class="actions">
    <?php if ($canReport): ?>
      <a class="btn primary" href="ponto-relatorio.php">Relatórios</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($canCreate): ?>
  <div style="margin:16px 0;padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div>
        <strong>Registrar ponto</strong>
        <div class="subtitle" style="margin:4px 0 0;">Último registro: <?php echo $lastLabel; ?></div>
      </div>
      <span class="pill">Próximo: <?php echo $esc($nextLabel); ?></span>
    </div>
    <form method="post" style="margin-top:12px;">
      <div class="grid" style="grid-template-columns: minmax(220px, 1fr);">
        <div class="field">
          <label for="observacao">Observação (opcional)</label>
          <input type="text" id="observacao" name="observacao" maxlength="255" placeholder="Ex.: intervalo, ajuste" />
        </div>
      </div>
      <div class="actions" style="margin-top:12px;">
        <button class="<?php echo $entryClass; ?>" type="submit" name="register_type" value="entrada" <?php echo $entryDisabled; ?>>Registrar entrada</button>
        <button class="<?php echo $exitClass; ?>" type="submit" name="register_type" value="saida" <?php echo $exitDisabled; ?>>Registrar saída</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;">
  <input type="hidden" name="page" value="1">
  <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
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
  <?php foreach ($columnFilters as $param => $value): ?>
    <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
  <?php endforeach; ?>
  <select name="status" aria-label="Filtrar status">
    <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>Todos os status</option>
    <option value="pendente" <?php echo $filters['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
    <option value="aprovado" <?php echo $filters['status'] === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
    <option value="rejeitado" <?php echo $filters['status'] === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
  </select>
  <input type="date" name="start" value="<?php echo $esc((string) $filters['start']); ?>" aria-label="Data inicial">
  <input type="date" name="end" value="<?php echo $esc((string) $filters['end']); ?>" aria-label="Data final">
  <?php if ($canApprove || $canReport): ?>
    <select name="user_id" aria-label="Filtrar usuário">
      <option value="">Todos os usuários</option>
      <?php foreach ($userOptions as $user): ?>
        <option value="<?php echo (int) $user['id']; ?>" <?php echo (int) $filters['user_id'] === (int) $user['id'] ? 'selected' : ''; ?>>
          <?php echo $esc($user['full_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button class="btn ghost" type="submit">Filtrar</button>
  <a class="btn ghost" href="ponto-list.php">Limpar</a>
</form>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em pontos" value="<?php echo $esc($searchQuery); ?>">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php $baseQuery = $buildBaseQuery(); ?>
    <?php foreach ($baseQuery as $param => $value): ?>
      <?php if ($param === 'per_page'): ?>
        <?php continue; ?>
      <?php endif; ?>
      <input type="hidden" name="<?php echo $esc((string) $param); ?>" value="<?php echo $esc((string) $value); ?>">
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
  <table data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th data-sort-key="registrado_em" aria-sort="none">Data/Hora</th>
        <?php if ($canApprove || $canReport): ?>
          <th data-sort-key="full_name" aria-sort="none">Usuário</th>
        <?php endif; ?>
        <th data-sort-key="tipo" aria-sort="none">Tipo</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="aprovado_por_nome" aria-sort="none">Aprovador</th>
        <th data-sort-key="observacao" aria-sort="none">Observação</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="registrado_em" data-query-param="filter_registrado_em" value="<?php echo $esc($columnFilters['filter_registrado_em'] ?? ''); ?>" placeholder="Filtrar data" aria-label="Filtrar data"></th>
        <?php if ($canApprove || $canReport): ?>
          <th><input type="search" data-filter-col="full_name" data-query-param="filter_full_name" value="<?php echo $esc($columnFilters['filter_full_name'] ?? ''); ?>" placeholder="Filtrar usuario" aria-label="Filtrar usuario"></th>
        <?php endif; ?>
        <th><input type="search" data-filter-col="tipo" data-query-param="filter_tipo" value="<?php echo $esc($columnFilters['filter_tipo'] ?? ''); ?>" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilters['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="aprovado_por_nome" data-query-param="filter_aprovado_por_nome" value="<?php echo $esc($columnFilters['filter_aprovado_por_nome'] ?? ''); ?>" placeholder="Filtrar aprovador" aria-label="Filtrar aprovador"></th>
        <th><input type="search" data-filter-col="observacao" data-query-param="filter_observacao" value="<?php echo $esc($columnFilters['filter_observacao'] ?? ''); ?>" placeholder="Filtrar observacao" aria-label="Filtrar observacao"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="<?php echo ($canApprove || $canReport) ? '7' : '6'; ?>">Nenhum registro encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td data-value="<?php echo $esc((string) ($row['registrado_em'] ?? '')); ?>">
              <?php echo $esc((string) ($row['registrado_em'] ?? '')); ?>
            </td>
            <?php if ($canApprove || $canReport): ?>
              <td data-value="<?php echo $esc((string) ($row['full_name'] ?? '')); ?>"><?php echo $esc((string) ($row['full_name'] ?? '')); ?></td>
            <?php endif; ?>
            <td data-value="<?php echo $esc((string) ($row['tipo'] ?? '')); ?>">
              <span class="pill"><?php echo $esc(ucfirst((string) ($row['tipo'] ?? ''))); ?></span>
            </td>
            <td data-value="<?php echo $esc((string) ($row['status'] ?? '')); ?>">
              <span class="pill"><?php echo $esc((string) ($row['status'] ?? '')); ?></span>
            </td>
            <td data-value="<?php echo $esc((string) ($row['aprovado_por_nome'] ?? '')); ?>">
              <?php echo $esc((string) ($row['aprovado_por_nome'] ?? '-')); ?>
            </td>
            <td data-value="<?php echo $esc((string) ($row['observacao'] ?? '')); ?>">
              <?php echo $esc((string) ($row['observacao'] ?? '-')); ?>
            </td>
            <td class="col-actions">
              <?php if ($canApprove && ($row['status'] ?? '') === 'pendente'): ?>
                <div class="actions">
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="approve_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn success" type="submit" aria-label="Aprovar" title="Aprovar">
                      <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                    </button>
                  </form>
                  <form method="post" onsubmit="return confirm('Rejeitar este registro?');" style="margin:0;">
                    <input type="hidden" name="reject_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Rejeitar" title="Rejeitar">
                      <svg aria-hidden="true"><use href="#icon-x"></use></svg>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span class="muted">Sem ações</span>
              <?php endif; ?>
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
