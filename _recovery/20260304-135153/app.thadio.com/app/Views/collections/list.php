<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $skuCounts */
/** @var int $skuTotal */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('collections.create');
  $canEdit = userCan('collections.edit');
  $canDelete = userCan('collections.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Categorias</h1>
    <div class="subtitle">Leitura direta do app com gerenciamento interno.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="colecao-cadastro.php">Nova categoria</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if (!empty($skuCounts)): ?>
  <?php
  $statusLabels = [
      'publish' => 'Publicado',
      'draft' => 'Rascunho',
      'pending' => 'Pendente',
      'private' => 'Privado',
      'trash' => 'Lixeira',
  ];
  $formatCount = static fn (int $value): string => number_format($value, 0, ',', '.');
  ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 10px;">
    <span class="pill">Total SKUs (produtos + variações): <?php echo $esc($formatCount($skuTotal)); ?></span>
    <?php foreach ($skuCounts as $status => $count): ?>
      <?php $label = $statusLabels[$status] ?? $status; ?>
      <span class="pill"><?php echo $esc($label . ': ' . $formatCount((int) $count)); ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em categorias">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="term_id" aria-sort="none">ID</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="slug" aria-sort="none">Slug</th>
        <th data-sort-key="count" aria-sort="none">Produtos</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="term_id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="slug" placeholder="Filtrar slug" aria-label="Filtrar slug"></th>
        <th><input type="search" data-filter-col="count" placeholder="Filtrar qtd" aria-label="Filtrar quantidade"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="5">Nenhuma categoria encontrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $rowLink = $canEdit ? 'colecao-cadastro.php?id=' . (int) $row['term_id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo $esc((string) $row['term_id']); ?>"><?php echo $esc((string) $row['term_id']); ?></td>
            <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td data-value="<?php echo $esc($row['slug']); ?>"><?php echo $esc($row['slug']); ?></td>
            <td data-value="<?php echo $esc((string) ($row['count'] ?? 0)); ?>"><?php echo $esc((string) ($row['count'] ?? 0)); ?></td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir esta categoria?');" style="margin:0;">
                      <input type="hidden" name="delete_id" value="<?php echo (int) $row['term_id']; ?>">
                      <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
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
