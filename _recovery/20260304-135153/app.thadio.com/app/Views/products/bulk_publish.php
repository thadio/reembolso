<?php
/** @var array $filters */
/** @var array $selectedIds */
/** @var array $categories */
/** @var array $tags */
/** @var array $previewRows */
/** @var int $totalMatched */
/** @var array $errors */
/** @var string $success */
/** @var string $actionStatus */
/** @var int $applyLimit */
/** @var callable $esc */
?>
<?php
  $selectedCategories = array_map('strval', $filters['category_ids'] ?? []);
  $selectedTags = array_map('strval', $filters['tag_ids'] ?? []);
  $selectedIds = $selectedIds ?? [];
  $statusFilter = (string) ($filters['status'] ?? ($filters['statuses'][0] ?? ''));
  $availabilityFilter = (string) ($filters['availability'] ?? '');
  $dateFrom = $filters['date_from'] ?? '';
  $dateTo = $filters['date_to'] ?? '';
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Publicação em lote</h1>
    <div class="subtitle">Ajuste status de produtos em lote com filtro por disponibilidade de venda.</div>
  </div>
</div>

<?php if (!empty($success)): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="get" action="produto-publicacao-lote.php" class="card" style="margin-top:12px;padding:16px;">
  <h2 style="margin:0 0 12px;font-size:16px;">Filtros</h2>
  <div class="grid" style="grid-template-columns:repeat(3,minmax(180px,1fr));gap:12px;">
    <div class="field">
      <label for="date_from">Data inicial (cadastro)</label>
      <input id="date_from" name="date_from" type="date" value="<?php echo $esc($dateFrom); ?>">
    </div>
    <div class="field">
      <label for="date_to">Data final (cadastro)</label>
      <input id="date_to" name="date_to" type="date" value="<?php echo $esc($dateTo); ?>">
    </div>
    <div class="field">
      <label for="status">Status atual</label>
      <select id="status" name="status">
        <option value="">Todos</option>
        <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
        <option value="disponivel" <?php echo $statusFilter === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
        <option value="reservado" <?php echo $statusFilter === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
        <option value="esgotado" <?php echo $statusFilter === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
        <option value="baixado" <?php echo $statusFilter === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
        <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>Arquivado</option>
      </select>
    </div>
    <div class="field">
      <label for="availability">Disponibilidade para venda</label>
      <select id="availability" name="availability">
        <option value="">Todos</option>
        <option value="available" <?php echo $availabilityFilter === 'available' ? 'selected' : ''; ?>>Disponível</option>
        <option value="unavailable" <?php echo $availabilityFilter === 'unavailable' ? 'selected' : ''; ?>>Indisponível</option>
      </select>
    </div>
  </div>

  <div class="grid" style="margin-top:14px;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px;">
    <div class="field">
      <label>Categorias</label>
      <div style="max-height:180px;overflow:auto;border:1px solid #eef2f7;border-radius:8px;padding:8px;background:#fff;">
        <?php if (empty($categories)): ?>
          <div class="muted">Nenhuma categoria disponível.</div>
        <?php else: ?>
          <?php foreach ($categories as $category): ?>
            <?php
              $catId = (string) ($category['term_id'] ?? '');
              $checked = in_array($catId, $selectedCategories, true);
            ?>
            <label style="display:flex;gap:8px;align-items:center;margin:4px 0;">
              <input type="checkbox" name="category_ids[]" value="<?php echo $esc($catId); ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <span><?php echo $esc($category['name'] ?? ''); ?></span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="field">
      <label>Tags</label>
      <div style="max-height:180px;overflow:auto;border:1px solid #eef2f7;border-radius:8px;padding:8px;background:#fff;">
        <?php if (empty($tags)): ?>
          <div class="muted">Nenhuma tag disponível.</div>
        <?php else: ?>
          <?php foreach ($tags as $tag): ?>
            <?php
              $tagId = (string) ($tag['term_id'] ?? '');
              $checked = in_array($tagId, $selectedTags, true);
            ?>
            <label style="display:flex;gap:8px;align-items:center;margin:4px 0;">
              <input type="checkbox" name="tag_ids[]" value="<?php echo $esc($tagId); ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <span><?php echo $esc($tag['name'] ?? ''); ?></span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="footer" style="justify-content:flex-start;gap:10px;">
    <button class="primary" type="submit">Filtrar</button>
    <a class="btn ghost" href="produto-publicacao-lote.php">Limpar</a>
  </div>
</form>

<form method="post" action="produto-publicacao-lote.php">
  <div class="card" style="margin-top:16px;padding:16px;">
    <h2 style="margin:0 0 12px;font-size:16px;">Resumo</h2>
    <div class="muted">Total encontrado: <strong><?php echo (int) $totalMatched; ?></strong></div>

    <?php if (!empty($previewRows)): ?>
      <div style="overflow:auto;margin-top:12px;">
        <table data-table="interactive" data-select-table="bulk">
          <thead>
            <tr>
              <th>
                <input type="checkbox" id="selectAllRows" aria-label="Selecionar todos">
              </th>
              <th>Nome</th>
              <th>Status</th>
              <th>Data</th>
              <th>SKU</th>
              <th>Disponibilidade</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($previewRows as $row): ?>
              <?php
                $rowId = (int) ($row['id'] ?? ($row['ID'] ?? 0));
                $statusValue = (string) ($row['status_unified'] ?? ($row['status'] ?? 'draft'));
                $statusLabel = \App\Support\CatalogLookup::getProductStatusLabel($statusValue);
                $quantity = (int) ($row['quantity'] ?? 0);
                $isAvailable = $statusValue === 'disponivel' && $quantity > 0;
                $availabilityLabel = $isAvailable ? 'Disponível' : 'Indisponível';
                $createdAt = (string) ($row['created_at'] ?? ($row['post_date'] ?? ''));
              ?>
              <tr>
                <td>
                  <input
                    type="checkbox"
                    name="selected_ids[]"
                    value="<?php echo $rowId; ?>"
                    <?php echo in_array($rowId, $selectedIds, true) ? 'checked' : ''; ?>
                  >
                </td>
                <td><?php echo $esc((string) ($row['name'] ?? ($row['post_title'] ?? ''))); ?></td>
                <td><?php echo $esc($statusLabel); ?></td>
                <td><?php echo $esc($createdAt); ?></td>
                <td><?php echo $esc((string) ($row['sku'] ?? '')); ?></td>
                <td><?php echo $esc($availabilityLabel); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="muted" style="margin-top:8px;">Selecione itens específicos ou deixe em branco para aplicar em todos os filtrados.</div>
    <?php else: ?>
      <div class="muted" style="margin-top:8px;">Nenhum produto encontrado com os filtros atuais.</div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:16px;padding:16px;">
    <h2 style="margin:0 0 12px;font-size:16px;">Ação em lote</h2>
  <input type="hidden" name="bulk_action" value="apply">
  <input type="hidden" name="status" value="<?php echo $esc($statusFilter); ?>">
  <input type="hidden" name="availability" value="<?php echo $esc($availabilityFilter); ?>">
  <input type="hidden" name="date_from" value="<?php echo $esc($dateFrom); ?>">
  <input type="hidden" name="date_to" value="<?php echo $esc($dateTo); ?>">
  <?php foreach ($selectedCategories as $catId): ?>
    <input type="hidden" name="category_ids[]" value="<?php echo $esc($catId); ?>">
  <?php endforeach; ?>
  <?php foreach ($selectedTags as $tagId): ?>
    <input type="hidden" name="tag_ids[]" value="<?php echo $esc($tagId); ?>">
  <?php endforeach; ?>

    <div class="grid" style="grid-template-columns:repeat(3,minmax(180px,1fr));gap:12px;">
      <div class="field">
        <label for="action_status">Novo status</label>
        <select id="action_status" name="action_status">
          <option value="disponivel" <?php echo $actionStatus === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
          <option value="reservado" <?php echo $actionStatus === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
          <option value="esgotado" <?php echo $actionStatus === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
          <option value="draft" <?php echo $actionStatus === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
          <option value="baixado" <?php echo $actionStatus === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
          <option value="archived" <?php echo $actionStatus === 'archived' ? 'selected' : ''; ?>>Arquivado</option>
        </select>
      </div>
      <div class="field">
        <label for="apply_limit">Limite por execução</label>
        <input id="apply_limit" name="apply_limit" type="number" min="0" value="<?php echo (int) $applyLimit; ?>">
        <small class="muted">0 = todos os produtos filtrados</small>
      </div>
    </div>

    <div class="footer" style="justify-content:flex-start;gap:10px;">
      <button class="primary" type="submit" onclick="return confirm('Aplicar ação em lote nos produtos filtrados?');">Aplicar em lote</button>
    </div>
  </div>
</form>

<script>
  (function() {
    const selectAll = document.getElementById('selectAllRows');
    if (!selectAll) return;
    const table = document.querySelector('[data-select-table="bulk"]');
    if (!table) return;
    const checkboxes = Array.from(table.querySelectorAll('tbody input[type="checkbox"]'));
    const syncSelectAll = () => {
      const allChecked = checkboxes.length > 0 && checkboxes.every((cb) => cb.checked);
      selectAll.checked = allChecked;
      selectAll.indeterminate = !allChecked && checkboxes.some((cb) => cb.checked);
    };
    selectAll.addEventListener('change', () => {
      checkboxes.forEach((cb) => { cb.checked = selectAll.checked; });
    });
    checkboxes.forEach((cb) => cb.addEventListener('change', syncSelectAll));
    syncSelectAll();
  })();
</script>
