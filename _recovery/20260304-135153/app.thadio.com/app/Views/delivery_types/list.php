<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $availabilityOptions */
/** @var array $bagActionOptions */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('delivery_types.create');
  $canEdit = userCan('delivery_types.edit');
  $canDelete = userCan('delivery_types.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Tipos de entrega</h1>
    <div class="subtitle">Gerencie opcoes de entrega e valores sugeridos.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="tipo-entrega-cadastro.php">Novo tipo</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em tipos de entrega">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="base_price" aria-sort="none">Frete base</th>
        <th data-sort-key="south_price" aria-sort="none">Frete Sul</th>
        <th data-sort-key="availability" aria-sort="none">Disponibilidade</th>
        <th data-sort-key="bag_action" aria-sort="none">Sacolinha</th>
        <th data-sort-key="description" aria-sort="none">Descrição</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="base_price" placeholder="Filtrar frete base" aria-label="Filtrar frete base"></th>
        <th><input type="search" data-filter-col="south_price" placeholder="Filtrar frete Sul" aria-label="Filtrar frete Sul"></th>
        <th><input type="search" data-filter-col="availability" placeholder="Filtrar disponibilidade" aria-label="Filtrar disponibilidade"></th>
        <th><input type="search" data-filter-col="bag_action" placeholder="Filtrar sacolinha" aria-label="Filtrar sacolinha"></th>
        <th><input type="search" data-filter-col="description" placeholder="Filtrar descrição" aria-label="Filtrar descrição"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="9">Nenhum tipo cadastrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $basePrice = number_format((float) ($row['base_price'] ?? 0), 2, ',', '.');
            $southPriceValue = $row['south_price'];
            $southPrice = $southPriceValue === null || $southPriceValue === '' ? '-' : number_format((float) $southPriceValue, 2, ',', '.');
            $availabilityKey = (string) ($row['availability'] ?? 'all');
            $availabilityLabel = $availabilityOptions[$availabilityKey] ?? $availabilityKey;
            $bagActionKey = (string) ($row['bag_action'] ?? 'none');
            $bagActionLabel = $bagActionOptions[$bagActionKey] ?? $bagActionKey;
          ?>
          <?php $rowLink = $canEdit ? 'tipo-entrega-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td data-value="<?php echo $esc((string) ($row['base_price'] ?? '')); ?>">R$ <?php echo $esc($basePrice); ?></td>
            <td data-value="<?php echo $esc((string) ($row['south_price'] ?? '')); ?>"><?php echo $southPrice === '-' ? '-' : 'R$ ' . $esc($southPrice); ?></td>
            <td data-value="<?php echo $esc(strtolower($availabilityLabel)); ?>"><?php echo $esc($availabilityLabel); ?></td>
            <td data-value="<?php echo $esc(strtolower($bagActionLabel)); ?>"><?php echo $esc($bagActionLabel); ?></td>
            <td data-value="<?php echo $esc($row['description'] ?? ''); ?>"><?php echo $esc($row['description'] ?? ''); ?></td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir este tipo?');" style="margin:0;">
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
