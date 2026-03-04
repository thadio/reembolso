<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('holidays.create');
  $canEdit = userCan('holidays.edit');
  $canDelete = userCan('holidays.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Calendário comemorativo</h1>
    <div class="subtitle">Base de datas comemorativas brasileiras e mundiais.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="data-comemorativa-cadastro.php">Nova data</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em datas comemorativas">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="date" aria-sort="none">Data</th>
        <th data-sort-key="year" aria-sort="none">Ano</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="scope" aria-sort="none">Escopo</th>
        <th data-sort-key="category" aria-sort="none">Categoria</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="source" aria-sort="none">Fonte</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="date" placeholder="Filtrar data" aria-label="Filtrar data"></th>
        <th><input type="search" data-filter-col="year" placeholder="Filtrar ano" aria-label="Filtrar ano"></th>
        <th><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="scope" placeholder="Filtrar escopo" aria-label="Filtrar escopo"></th>
        <th><input type="search" data-filter-col="category" placeholder="Filtrar categoria" aria-label="Filtrar categoria"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="source" placeholder="Filtrar fonte" aria-label="Filtrar fonte"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="9">Nenhuma data comemorativa cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $rowLink = $canEdit ? 'data-comemorativa-cadastro.php?id=' . (int) $row['id'] : '';
            $day = (int) ($row['day'] ?? 0);
            $month = (int) ($row['month'] ?? 0);
            $year = $row['year'] ?? null;
            $dateLabel = sprintf('%02d/%02d', $day, $month);
            $dateSort = sprintf('%02d-%02d', $month, $day);
            $yearLabel = $year ? (string) $year : 'Recorrente';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($dateSort); ?>"><?php echo $esc($dateLabel); ?></td>
            <td data-value="<?php echo $esc((string) ($year ?? 0)); ?>"><?php echo $esc($yearLabel); ?></td>
            <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td data-value="<?php echo $esc($row['scope'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['scope'] ?? ''); ?></span>
            </td>
            <td data-value="<?php echo $esc($row['category'] ?? ''); ?>"><?php echo $esc($row['category'] ?? '-'); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td data-value="<?php echo $esc($row['source'] ?? ''); ?>"><?php echo $esc($row['source'] ?? '-'); ?></td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <form method="post" onsubmit="return confirm('Excluir esta data comemorativa?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
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
