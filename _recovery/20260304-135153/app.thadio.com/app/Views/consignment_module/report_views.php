<?php
/** @var array $views */
/** @var array $suppliers */
/** @var array $fieldMetadata */
/** @var array $fieldsByCategory */
/** @var array $systemPresets */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Modelos de Relatório</h1>
    <div class="subtitle">Crie, edite e gerencie modelos (views) de relatório de consignação. Cada modelo define quais campos ficam visíveis, a ordenação e o nível de detalhamento.</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn ghost" href="consignacao-relatorio-dinamico.php">← Relatório Dinâmico</a>
    <button type="button" class="btn primary" onclick="document.getElementById('createModal').showModal()">+ Novo Modelo</button>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>

<!-- ═══ LISTA DE MODELOS ═══ -->
<?php if (empty($views)): ?>
  <div class="alert info" style="margin-top:20px;">Nenhum modelo de relatório cadastrado. Os modelos do sistema serão criados automaticamente ao gerar o primeiro relatório.</div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;margin-top:20px;">
    <?php foreach ($views as $v): ?>
      <?php
        $fields = $v['fields_config'] ?? [];
        $fieldCount = count($fields);
        $detailLabel = match($v['detail_level'] ?? 'both') {
          'summary' => 'Apenas Resumo',
          'items' => 'Apenas Detalhe',
          default => 'Resumo + Detalhe',
        };
      ?>
      <div style="background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:20px;position:relative;">
        <!-- Badges -->
        <div style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
          <?php if (!empty($v['is_system'])): ?>
            <span class="badge info" style="font-size:10px;">Sistema</span>
          <?php endif; ?>
          <?php if (!empty($v['is_default'])): ?>
            <span class="badge success" style="font-size:10px;">Padrão</span>
          <?php endif; ?>
          <?php if (!empty($v['default_for_supplier_id'])): ?>
            <span class="badge secondary" style="font-size:10px;">Padrão para #<?php echo (int)$v['default_for_supplier_id']; ?></span>
          <?php endif; ?>
          <span class="badge secondary" style="font-size:10px;"><?php echo $esc($detailLabel); ?></span>
        </div>

        <h3 style="margin:0 0 4px;font-size:16px;"><?php echo $esc($v['name']); ?></h3>
        <?php if (!empty($v['description'])): ?>
          <div style="font-size:13px;color:var(--muted);margin-bottom:8px;"><?php echo $esc($v['description']); ?></div>
        <?php endif; ?>

        <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
          <?php echo $fieldCount; ?> campo(s)
          <?php if (!empty($v['group_by'])): ?> · Agrupado por <?php echo $esc($fieldMetadata[$v['group_by']]['label'] ?? $v['group_by']); ?><?php endif; ?>
        </div>

        <!-- Field chips -->
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">
          <?php foreach (array_slice($fields, 0, 8) as $fk): ?>
            <span style="background:#e5e7eb;padding:2px 8px;border-radius:6px;font-size:11px;"><?php echo $esc($fieldMetadata[$fk]['label'] ?? $fk); ?></span>
          <?php endforeach; ?>
          <?php if ($fieldCount > 8): ?>
            <span style="color:var(--muted);font-size:11px;">+<?php echo $fieldCount - 8; ?> mais</span>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <a class="btn ghost" style="font-size:12px;padding:4px 10px;" href="consignacao-relatorio-dinamico.php?view_id=<?php echo (int)$v['id']; ?>">Usar</a>

          <?php if (empty($v['is_system'])): ?>
            <button type="button" class="btn ghost" style="font-size:12px;padding:4px 10px;"
              onclick="openEditModal(<?php echo $esc(json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Editar</button>
          <?php endif; ?>

          <form method="post" style="display:inline;" onsubmit="return confirm('Clonar este modelo?')">
            <input type="hidden" name="action" value="clone">
            <input type="hidden" name="source_id" value="<?php echo (int)$v['id']; ?>">
            <input type="hidden" name="new_name" value="<?php echo $esc($v['name'] . ' (cópia)'); ?>">
            <button type="submit" class="btn ghost" style="font-size:12px;padding:4px 10px;">Clonar</button>
          </form>

          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="set_default">
            <input type="hidden" name="view_id" value="<?php echo (int)$v['id']; ?>">
            <button type="submit" class="btn ghost" style="font-size:12px;padding:4px 10px;" <?php echo !empty($v['is_default']) ? 'disabled' : ''; ?>>
              <?php echo !empty($v['is_default']) ? '✓ Padrão' : 'Definir padrão'; ?>
            </button>
          </form>

          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="export_view">
            <input type="hidden" name="view_id" value="<?php echo (int)$v['id']; ?>">
            <button type="submit" class="btn ghost" style="font-size:12px;padding:4px 10px;">Exportar JSON</button>
          </form>

          <?php if (empty($v['is_system'])): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Excluir este modelo?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="view_id" value="<?php echo (int)$v['id']; ?>">
              <button type="submit" class="btn ghost" style="font-size:12px;padding:4px 10px;color:#dc2626;">Excluir</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ═══ CREATE MODAL ═══ -->
<dialog id="createModal" style="max-width:700px;width:95%;border:none;border-radius:var(--radius);box-shadow:var(--shadow);padding:0;">
  <form method="post" style="padding:24px;">
    <input type="hidden" name="action" value="create">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="margin:0;">Novo Modelo de Relatório</h2>
      <button type="button" class="btn ghost" onclick="document.getElementById('createModal').close()">✕</button>
    </div>

    <div style="display:grid;gap:12px;">
      <div>
        <label style="font-size:13px;font-weight:600;">Nome *</label>
        <input type="text" name="name" required style="width:100%;" placeholder="Ex: Relatório Mensal Fornecedora">
      </div>
      <div>
        <label style="font-size:13px;font-weight:600;">Descrição</label>
        <textarea name="description" rows="2" style="width:100%;" placeholder="Descrição opcional..."></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div>
          <label style="font-size:13px;font-weight:600;">Detalhamento</label>
          <select name="detail_level" style="width:100%;">
            <option value="both">Resumo + Detalhe</option>
            <option value="summary">Apenas Resumo</option>
            <option value="items">Apenas Detalhe</option>
          </select>
        </div>
        <div>
          <label style="font-size:13px;font-weight:600;">Ordenar por</label>
          <select name="sort_field" style="width:100%;">
            <option value="received_at">Recebido em</option>
            <option value="sold_at">Vendido em</option>
            <option value="price">Preço</option>
            <option value="credit_amount">Comissão</option>
            <option value="sku">SKU</option>
          </select>
        </div>
        <div>
          <label style="font-size:13px;font-weight:600;">Direção</label>
          <select name="sort_dir" style="width:100%;">
            <option value="DESC">Decrescente</option>
            <option value="ASC">Crescente</option>
          </select>
        </div>
      </div>
      <div>
        <label style="font-size:13px;font-weight:600;">Agrupar por</label>
        <select name="group_by" style="width:100%;">
          <option value="">Sem agrupamento</option>
          <option value="consignment_status">Status</option>
          <option value="payout_status">Status pagamento</option>
          <option value="category_name">Categoria</option>
          <option value="brand_name">Marca</option>
        </select>
      </div>

      <div>
        <label style="font-size:13px;font-weight:600;">Campos visíveis</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin-top:8px;max-height:300px;overflow-y:auto;padding:12px;background:#f9fafc;border-radius:10px;">
          <?php foreach ($fieldsByCategory as $catKey => $cat): ?>
            <div>
              <div style="font-weight:600;font-size:11px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;"><?php echo $esc($cat['label']); ?></div>
              <?php foreach ($cat['fields'] as $fk => $fmeta): ?>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;padding:2px 0;">
                  <input type="checkbox" name="fields[]" value="<?php echo $esc($fk); ?>" <?php echo !empty($fmeta['required']) ? 'checked' : ''; ?>>
                  <?php echo $esc($fmeta['label']); ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn ghost" onclick="document.getElementById('createModal').close()">Cancelar</button>
      <button type="submit" class="btn primary">Criar Modelo</button>
    </div>
  </form>
</dialog>

<!-- ═══ EDIT MODAL ═══ -->
<dialog id="editModal" style="max-width:700px;width:95%;border:none;border-radius:var(--radius);box-shadow:var(--shadow);padding:0;">
  <form method="post" id="editForm" style="padding:24px;">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="view_id" id="editViewId">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="margin:0;">Editar Modelo</h2>
      <button type="button" class="btn ghost" onclick="document.getElementById('editModal').close()">✕</button>
    </div>

    <div style="display:grid;gap:12px;">
      <div>
        <label style="font-size:13px;font-weight:600;">Nome *</label>
        <input type="text" name="name" id="editName" required style="width:100%;">
      </div>
      <div>
        <label style="font-size:13px;font-weight:600;">Descrição</label>
        <textarea name="description" id="editDescription" rows="2" style="width:100%;"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div>
          <label style="font-size:13px;font-weight:600;">Detalhamento</label>
          <select name="detail_level" id="editDetailLevel" style="width:100%;">
            <option value="both">Resumo + Detalhe</option>
            <option value="summary">Apenas Resumo</option>
            <option value="items">Apenas Detalhe</option>
          </select>
        </div>
        <div>
          <label style="font-size:13px;font-weight:600;">Ordenar por</label>
          <select name="sort_field" id="editSortField" style="width:100%;">
            <option value="received_at">Recebido em</option>
            <option value="sold_at">Vendido em</option>
            <option value="price">Preço</option>
            <option value="credit_amount">Comissão</option>
            <option value="sku">SKU</option>
          </select>
        </div>
        <div>
          <label style="font-size:13px;font-weight:600;">Direção</label>
          <select name="sort_dir" id="editSortDir" style="width:100%;">
            <option value="DESC">Decrescente</option>
            <option value="ASC">Crescente</option>
          </select>
        </div>
      </div>
      <div>
        <label style="font-size:13px;font-weight:600;">Agrupar por</label>
        <select name="group_by" id="editGroupBy" style="width:100%;">
          <option value="">Sem agrupamento</option>
          <option value="consignment_status">Status</option>
          <option value="payout_status">Status pagamento</option>
          <option value="category_name">Categoria</option>
          <option value="brand_name">Marca</option>
        </select>
      </div>

      <div>
        <label style="font-size:13px;font-weight:600;">Campos visíveis</label>
        <div id="editFieldsContainer" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin-top:8px;max-height:300px;overflow-y:auto;padding:12px;background:#f9fafc;border-radius:10px;">
          <?php foreach ($fieldsByCategory as $catKey => $cat): ?>
            <div>
              <div style="font-weight:600;font-size:11px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;"><?php echo $esc($cat['label']); ?></div>
              <?php foreach ($cat['fields'] as $fk => $fmeta): ?>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;padding:2px 0;">
                  <input type="checkbox" name="fields[]" value="<?php echo $esc($fk); ?>" class="edit-field-cb" data-field="<?php echo $esc($fk); ?>">
                  <?php echo $esc($fmeta['label']); ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn ghost" onclick="document.getElementById('editModal').close()">Cancelar</button>
      <button type="submit" class="btn primary">Salvar Alterações</button>
    </div>
  </form>
</dialog>

<script>
function openEditModal(viewData) {
  document.getElementById('editViewId').value = viewData.id || '';
  document.getElementById('editName').value = viewData.name || '';
  document.getElementById('editDescription').value = viewData.description || '';
  document.getElementById('editDetailLevel').value = viewData.detail_level || 'both';
  document.getElementById('editGroupBy').value = viewData.group_by || '';

  const sortConfig = viewData.sort_config || {};
  document.getElementById('editSortField').value = sortConfig.field || 'received_at';
  document.getElementById('editSortDir').value = sortConfig.direction || 'DESC';

  // Fields
  const fields = viewData.fields_config || [];
  document.querySelectorAll('.edit-field-cb').forEach(cb => {
    cb.checked = fields.includes(cb.dataset.field);
  });

  document.getElementById('editModal').showModal();
}
</script>
