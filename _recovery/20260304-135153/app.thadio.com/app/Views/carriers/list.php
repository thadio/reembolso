<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $typeOptions */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('carriers.create');
  $canEdit = userCan('carriers.edit');
  $canDeactivate = userCan('carriers.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Transportadoras</h1>
    <div class="subtitle">Cadastro único para uso em pedidos e acompanhamento de entregas.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="transportadora-cadastro.php">Nova transportadora</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar transportadoras" aria-label="Busca geral em transportadoras">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="carrier_type" aria-sort="none">Tipo</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="site_url" aria-sort="none">Site</th>
        <th data-sort-key="tracking_url_template" aria-sort="none">Template rastreio</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="carrier_type" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="site_url" placeholder="Filtrar site" aria-label="Filtrar site"></th>
        <th><input type="search" data-filter-col="tracking_url_template" placeholder="Filtrar template" aria-label="Filtrar template"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="7">Nenhuma transportadora cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $typeKey = (string) ($row['carrier_type'] ?? 'transportadora');
            $typeLabel = $typeOptions[$typeKey] ?? $typeKey;
            $rowLink = $canEdit ? 'transportadora-cadastro.php?id=' . (int) $row['id'] : '';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc((string) ($row['name'] ?? '')); ?>"><?php echo $esc((string) ($row['name'] ?? '')); ?></td>
            <td data-value="<?php echo $esc($typeKey); ?>"><?php echo $esc($typeLabel); ?></td>
            <td data-value="<?php echo $esc((string) ($row['status'] ?? '')); ?>"><span class="pill"><?php echo $esc((string) ($row['status'] ?? '')); ?></span></td>
            <td data-value="<?php echo $esc((string) ($row['site_url'] ?? '')); ?>">
              <?php if (!empty($row['site_url'])): ?>
                <a href="<?php echo $esc((string) $row['site_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir site</a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc((string) ($row['tracking_url_template'] ?? '')); ?>">
              <code><?php echo $esc((string) ($row['tracking_url_template'] ?? '-')); ?></code>
            </td>
            <td class="col-actions">
              <?php if ($canDeactivate && ($row['status'] ?? '') === 'ativo'): ?>
                <form method="post" onsubmit="return confirm('Desativar esta transportadora?');" style="margin:0;">
                  <input type="hidden" name="deactivate_id" value="<?php echo (int) $row['id']; ?>">
                  <button class="icon-btn danger" type="submit" aria-label="Desativar" title="Desativar">
                    <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                  </button>
                </form>
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
