<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var string $statusFilter */
/** @var array $statusOptions */
/** @var int $openCount */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('bags.create');
  $canEdit = userCan('bags.edit');
  $canDelete = userCan('bags.delete');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Sacolinhas</h1>
    <div class="subtitle">Acompanhe sacolinhas abertas e histórico por cliente.</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <span class="pill">Abertas: <?php echo (int) $openCount; ?></span>
    <?php if ($canCreate): ?>
      <a class="btn primary" href="sacolinha-cadastro.php">Abrir sacolinha</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em sacolinhas">
    <select onchange="window.location='sacolinha-list.php?status=' + encodeURIComponent(this.value)">
      <option value="">Status: todos</option>
      <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
        <option value="<?php echo $esc($statusKey); ?>" <?php echo $statusFilter === $statusKey ? 'selected' : ''; ?>>
          <?php echo $esc($statusLabel); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">Número</th>
        <th data-sort-key="customer" aria-sort="none">Cliente</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="items_qty" aria-sort="none">Itens</th>
        <th data-sort-key="items_total" aria-sort="none">Total</th>
        <th data-sort-key="opened_at" aria-sort="none">Abertura</th>
        <th data-sort-key="expected_close_at" aria-sort="none">Fechamento previsto</th>
        <th data-sort-key="opening_fee_paid" aria-sort="none">Taxa</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="#"></th>
        <th><input type="search" data-filter-col="customer" placeholder="Cliente"></th>
        <th><input type="search" data-filter-col="status" placeholder="Status"></th>
        <th><input type="search" data-filter-col="items_qty" placeholder="Itens"></th>
        <th><input type="search" data-filter-col="items_total" placeholder="Total"></th>
        <th><input type="search" data-filter-col="opened_at" placeholder="Abertura"></th>
        <th><input type="search" data-filter-col="expected_close_at" placeholder="Fechamento"></th>
        <th><input type="search" data-filter-col="opening_fee_paid" placeholder="Taxa"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="9">Nenhuma sacolinha cadastrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $customerName = trim((string) ($row['customer_name'] ?? ''));
            if ($customerName === '') {
                $customerName = 'Cliente #' . (int) ($row['pessoa_id'] ?? 0);
            }
            $itemsQty = (int) ($row['items_qty'] ?? 0);
            $itemsTotal = number_format((float) ($row['items_total'] ?? 0), 2, ',', '.');
            $openedAt = $row['opened_at'] ? date('d/m/Y', strtotime($row['opened_at'])) : '-';
            $expectedClose = $row['expected_close_at'] ? date('d/m/Y', strtotime($row['expected_close_at'])) : '-';
            $feeValue = number_format((float) ($row['opening_fee_value'] ?? 0), 2, ',', '.');
            $feePaid = !empty($row['opening_fee_paid']) ? 'Pago' : 'Pendente';
            $statusLabel = $statusOptions[$row['status'] ?? ''] ?? ($row['status'] ?? '');
          ?>
          <?php $rowLink = $canEdit ? 'sacolinha-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc(strtolower($customerName)); ?>">
              <?php echo $esc($customerName); ?>
              <div style="font-size:12px;color:var(--muted);">ID <?php echo (int) ($row['pessoa_id'] ?? 0); ?></div>
            </td>
            <td data-value="<?php echo $esc($row['status'] ?? ''); ?>">
              <span class="pill"><?php echo $esc($statusLabel); ?></span>
            </td>
            <td data-value="<?php echo $itemsQty; ?>"><?php echo $itemsQty; ?></td>
            <td data-value="<?php echo $esc((string) ($row['items_total'] ?? '')); ?>">R$ <?php echo $esc($itemsTotal); ?></td>
            <td data-value="<?php echo $esc((string) ($row['opened_at'] ?? '')); ?>"><?php echo $esc($openedAt); ?></td>
            <td data-value="<?php echo $esc((string) ($row['expected_close_at'] ?? '')); ?>"><?php echo $esc($expectedClose); ?></td>
            <td data-value="<?php echo $esc($feePaid); ?>">
              <div><?php echo $esc($feePaid); ?></div>
              <div style="font-size:12px;color:var(--muted);">R$ <?php echo $esc($feeValue); ?></div>
            </td>
            <td class="col-actions">
              <div class="actions">
                <a class="icon-btn neutral" href="sacolinha-cliente.php?pessoa_id=<?php echo (int) ($row['pessoa_id'] ?? 0); ?>" aria-label="Histórico" title="Histórico">
                  <svg aria-hidden="true"><use href="#icon-clock"></use></svg>
                </a>
                <?php if ($canDelete): ?>
                  <form method="post" onsubmit="return confirm('Excluir esta sacolinha?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
