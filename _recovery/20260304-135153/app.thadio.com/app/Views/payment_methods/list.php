<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $typeOptions */
/** @var array $feeTypeOptions */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('payment_methods.create');
  $canEdit = userCan('payment_methods.edit');
  $canDelete = userCan('payment_methods.delete');
  $canViewFinance = userCan('finance_entries.view');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Metodos de pagamento</h1>
    <div class="subtitle">Configure taxas e requisitos para os meios de pagamento.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="metodo-pagamento-cadastro.php">Novo método</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em metodos">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="name" aria-sort="none">Nome</th>
        <th data-sort-key="type" aria-sort="none">Tipo</th>
        <th data-sort-key="fee" aria-sort="none">Taxa padrao</th>
        <th data-sort-key="requirements" aria-sort="none">Requisitos</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="type" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
        <th><input type="search" data-filter-col="fee" placeholder="Filtrar taxa" aria-label="Filtrar taxa"></th>
        <th><input type="search" data-filter-col="requirements" placeholder="Filtrar requisitos" aria-label="Filtrar requisitos"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="7">Nenhum método cadastrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $typeKey = $row['type'] ?? '';
            $typeLabel = $typeOptions[$typeKey] ?? $typeKey;
            $feeType = $row['fee_type'] ?? 'none';
            $feeLabel = $feeTypeOptions[$feeType] ?? $feeType;
            $feeValue = number_format((float) ($row['fee_value'] ?? 0), 2, ',', '.');
            $feeDisplay = $feeType === 'none' ? 'Sem taxa' : ($feeLabel . ': ' . $feeValue);
            $requirements = [];
            if (!empty($row['requires_bank_account'])) {
                $requirements[] = 'Banco/PIX';
            }
            if (!empty($row['requires_terminal'])) {
                $requirements[] = 'Maquininha';
            }
            $requirementsLabel = $requirements ? implode(', ', $requirements) : '-';
          ?>
          <?php $rowLink = $canEdit ? 'metodo-pagamento-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
            <td data-value="<?php echo $esc($typeLabel); ?>"><?php echo $esc($typeLabel); ?></td>
            <td data-value="<?php echo $esc($feeDisplay); ?>"><?php echo $esc($feeDisplay); ?></td>
            <td data-value="<?php echo $esc($requirementsLabel); ?>"><?php echo $esc($requirementsLabel); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td class="col-actions">
              <?php if ($canDelete || $canViewFinance): ?>
                <div class="actions">
                  <?php if ($canViewFinance): ?>
                    <a class="icon-btn" href="financeiro-list.php?payment_method_id=<?php echo (int) $row['id']; ?>" aria-label="Ver extrato" title="Ver extrato">
                      <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir este método?');" style="margin:0;">
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
