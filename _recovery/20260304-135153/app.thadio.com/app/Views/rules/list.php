<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('rules.create');
  $canEdit = userCan('rules.edit');
  $canDelete = userCan('rules.delete');
  $normalizeText = static function (string $value): string {
    $normalized = preg_replace('/\s+/', ' ', trim($value));
    return $normalized ?? '';
  };
  $excerptText = static function (string $value, int $limit = 160) use ($normalizeText): string {
    $normalized = $normalizeText($value);
    if ($normalized === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($normalized) > $limit) {
            return mb_substr($normalized, 0, $limit) . '...';
        }
        return $normalized;
    }
    if (strlen($normalized) > $limit) {
        return substr($normalized, 0, $limit) . '...';
    }
    return $normalized;
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Regras</h1>
    <div class="subtitle">Cadastre regras, orientacoes e comunicados usados no negocio.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="regra-cadastro.php">Nova regra</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em regras">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="title" aria-sort="none">Título</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="content" aria-sort="none">Resumo</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="title" placeholder="Filtrar título" aria-label="Filtrar título"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="content" placeholder="Filtrar resumo" aria-label="Filtrar resumo"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="5">Nenhuma regra cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $contentRaw = (string) ($row['content'] ?? '');
            $contentNormalized = $normalizeText($contentRaw);
            $excerpt = $excerptText($contentRaw);
          ?>
          <?php $rowLink = $canEdit ? 'regra-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($row['title'] ?? ''); ?>"><?php echo $esc($row['title'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td data-value="<?php echo $esc($contentNormalized); ?>"><?php echo $esc($excerpt); ?></td>
            <td class="col-actions">
              <?php if ($canDelete): ?>
                <div class="actions">
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir esta regra?');" style="margin:0;">
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
