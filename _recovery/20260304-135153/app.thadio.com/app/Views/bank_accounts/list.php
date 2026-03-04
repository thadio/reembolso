<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $pixTypeOptions */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('bank_accounts.create');
  $canEdit = userCan('bank_accounts.edit');
  $canDelete = userCan('bank_accounts.delete');
  $canViewFinance = userCan('finance_entries.view');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Contas bancarias</h1>
    <div class="subtitle">Cadastre contas e chaves PIX para recebimento.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="conta-bancaria-cadastro.php">Nova conta</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em contas bancarias">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="bank" aria-sort="none">Banco</th>
        <th data-sort-key="label" aria-sort="none">Conta</th>
        <th data-sort-key="holder" aria-sort="none">Titular</th>
        <th data-sort-key="pix" aria-sort="none">PIX</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="bank" placeholder="Filtrar banco" aria-label="Filtrar banco"></th>
        <th><input type="search" data-filter-col="label" placeholder="Filtrar conta" aria-label="Filtrar conta"></th>
        <th><input type="search" data-filter-col="holder" placeholder="Filtrar titular" aria-label="Filtrar titular"></th>
        <th><input type="search" data-filter-col="pix" placeholder="Filtrar PIX" aria-label="Filtrar PIX"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="7">Nenhuma conta cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $pixKey = trim((string) ($row['pix_key'] ?? ''));
            $pixType = $row['pix_key_type'] ?? '';
            $pixTypeLabel = $pixType !== '' ? ($pixTypeOptions[$pixType] ?? $pixType) : '';
            $pixLabel = $pixKey !== '' ? trim($pixTypeLabel . ' ' . $pixKey) : '-';
            $bankName = $row['bank_name'] ?? '';
            if ($bankName === '') {
                $bankName = 'Banco #' . (int) ($row['bank_id'] ?? 0);
            }
          ?>
          <?php $rowLink = $canEdit ? 'conta-bancaria-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($bankName); ?>"><?php echo $esc($bankName); ?></td>
            <td data-value="<?php echo $esc($row['label'] ?? ''); ?>"><?php echo $esc($row['label'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($row['holder'] ?? ''); ?>"><?php echo $esc($row['holder'] ?? '-'); ?></td>
            <td data-value="<?php echo $esc($pixLabel); ?>"><?php echo $esc($pixLabel); ?></td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($row['status'] ?? ''); ?></span>
            </td>
            <td class="col-actions">
              <?php if ($canDelete || $canViewFinance): ?>
                <div class="actions">
                  <?php if ($canViewFinance): ?>
                    <a class="icon-btn" href="financeiro-list.php?bank_account_id=<?php echo (int) $row['id']; ?>" aria-label="Ver extrato" title="Ver extrato">
                      <svg aria-hidden="true"><use href="#icon-eye"></use></svg>
                    </a>
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                    <form method="post" onsubmit="return confirm('Excluir esta conta?');" style="margin:0;">
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
