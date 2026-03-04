<?php
/** @var array $rows */
/** @var array $modules */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('profiles.create');
  $canEdit = userCan('profiles.edit');
  $canDelete = userCan('profiles.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Perfis de acesso</h1>
    <div class="subtitle">Defina permissões por módulo e vincule usuários.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="perfil-cadastro.php">Novo perfil</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em perfis">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" class="profiles-table">
    <thead>
      <tr>
        <th class="col-id" data-sort-key="id" aria-sort="none">ID</th>
        <th class="col-name" data-sort-key="name" aria-sort="none">Nome</th>
        <th class="col-status" data-sort-key="status" aria-sort="none">Status</th>
        <th class="col-permissions" data-sort-key="permissions" aria-sort="none">Permissões</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th class="col-id"><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th class="col-name"><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th class="col-status"><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th class="col-permissions"><input type="search" data-filter-col="permissions" placeholder="Filtrar permissão" aria-label="Filtrar permissão"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="5">Nenhum perfil cadastrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $permissionsArray = json_decode($row['permissions'] ?? '[]', true);
            $permissionsArray = is_array($permissionsArray) ? $permissionsArray : [];
            $summary = [];
            $permissionsItems = [];
            foreach ($permissionsArray as $moduleKey => $actions) {
              $label = $modules[$moduleKey]['label'] ?? $moduleKey;
              $actionLabels = [];
              foreach ($actions as $action) {
                $actionLabels[] = $modules[$moduleKey]['actions'][$action] ?? $action;
              }
              $actionsText = $actionLabels ? implode(', ', $actionLabels) : 'Sem ações';
              $summary[] = $label . ': ' . $actionsText;
              $permissionsItems[] = [
                'label' => $label,
                'actions' => $actionsText,
              ];
            }
            $permissionsText = $summary ? implode(' • ', $summary) : 'Sem permissões';
          ?>
          <?php $rowLink = $canEdit ? 'perfil-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td class="col-id" data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td class="col-name" data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td class="col-status" data-value="<?php echo $esc($row['status']); ?>">
              <span class="pill"><?php echo $esc($row['status']); ?></span>
            </td>
            <td class="col-permissions" data-value="<?php echo $esc(strtolower($permissionsText)); ?>">
              <?php if ($permissionsItems): ?>
                <?php
                  $summaryItems = array_slice($permissionsItems, 0, 2);
                  $summaryLabels = array_map(static fn($item) => $item['label'], $summaryItems);
                  $remaining = count($permissionsItems) - count($summaryItems);
                  $summaryText = $summaryLabels ? implode(', ', $summaryLabels) : 'Ver permissões';
                  if ($remaining > 0) {
                    $summaryText .= ' +' . $remaining;
                  }
                ?>
                <details class="permissions-details">
                  <summary>
                    <span class="permissions-summary"><?php echo $esc($summaryText); ?></span>
                    <span class="permissions-summary-meta">
                      <span class="permissions-summary-count"><?php echo (int) count($permissionsItems); ?> módulos</span>
                      <span class="permissions-toggle permissions-toggle--closed">Expandir</span>
                      <span class="permissions-toggle permissions-toggle--open">Recolher</span>
                    </span>
                  </summary>
                  <ul class="permissions-list">
                    <?php foreach ($permissionsItems as $item): ?>
                      <li class="permissions-item">
                        <span class="permissions-label"><?php echo $esc($item['label']); ?></span>
                        <span class="permissions-actions"><?php echo $esc($item['actions']); ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php else: ?>
                <span class="muted">Sem permissões</span>
              <?php endif; ?>
            </td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir este perfil?');" style="margin:0;">
                      <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
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
