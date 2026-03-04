<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<?php
  $rows = is_array($rows ?? null)
    ? $rows
    : (is_array($consignments ?? null) ? $consignments : []);
  $errors = is_array($errors ?? null) ? $errors : [];
  $success = (string) ($success ?? '');
  $esc = is_callable($esc ?? null)
    ? $esc
    : static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

  $canCreate = userCan('consignments.create');
  $canEdit = userCan('consignments.edit');
  $canDelete = userCan('consignments.delete');

  $formatDate = function (?string $value): string {
    if (!$value) {
      return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return $value;
    }
    return date('d/m/Y', $ts);
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Recebimentos de consignação</h1>
    <div class="subtitle">Registre pré-lotes com quantidade por categoria e fornecedor.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="consignacao-recebimento-cadastro.php">Novo recebimento</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em recebimentos">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">Pre-lote</th>
        <th data-sort-key="received_at" aria-sort="none">Recebimento</th>
        <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
        <th data-sort-key="total_received" aria-sort="none">Qtd recebida</th>
        <th data-sort-key="total_returned" aria-sort="none">Qtd devolvida</th>
        <th data-sort-key="returns_count" aria-sort="none">Devolucoes</th>
        <th data-sort-key="notes" aria-sort="none">Observacoes</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="#" aria-label="Filtrar pré-lote"></th>
        <th><input type="search" data-filter-col="received_at" placeholder="Data" aria-label="Filtrar data"></th>
        <th><input type="search" data-filter-col="supplier" placeholder="Fornecedor" aria-label="Filtrar fornecedor"></th>
        <th><input type="search" data-filter-col="total_received" placeholder="Qtd" aria-label="Filtrar quantidade recebida"></th>
        <th><input type="search" data-filter-col="total_returned" placeholder="Qtd" aria-label="Filtrar quantidade devolvida"></th>
        <th><input type="search" data-filter-col="returns_count" placeholder="Qtd" aria-label="Filtrar devolucoes"></th>
        <th><input type="search" data-filter-col="notes" placeholder="Observacoes" aria-label="Filtrar observacoes"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="8">Nenhum recebimento registrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $notes = (string) ($row['notes'] ?? '');
            $notesDisplay = $notes;
            if (strlen($notes) > 80) {
              $notesDisplay = substr($notes, 0, 77) . '...';
            }
          ?>
          <?php $rowLink = $canEdit ? 'consignacao-recebimento-cadastro.php?id=' . (int) $row['id'] : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc((string) $row['received_at']); ?>"><?php echo $esc($formatDate($row['received_at'] ?? null)); ?></td>
            <td data-value="<?php echo $esc((string) ($row['supplier_name'] ?? '')); ?>"><?php echo $esc((string) ($row['supplier_name'] ?? 'Sem fornecedor')); ?></td>
            <td data-value="<?php echo (int) ($row['total_received'] ?? 0); ?>"><?php echo number_format((int) ($row['total_received'] ?? 0), 0, ',', '.'); ?></td>
            <td data-value="<?php echo (int) ($row['total_returned'] ?? 0); ?>"><?php echo number_format((int) ($row['total_returned'] ?? 0), 0, ',', '.'); ?></td>
            <td data-value="<?php echo (int) ($row['returns_count'] ?? 0); ?>"><?php echo (int) ($row['returns_count'] ?? 0); ?></td>
            <td data-value="<?php echo $esc($notes); ?>"><?php echo $esc($notesDisplay); ?></td>
            <td class="col-actions">
              <div class="actions">
                <a class="icon-btn neutral" href="consignacao-recebimento-termo.php?id=<?php echo (int) $row['id']; ?>" target="_blank" rel="noopener" aria-label="Termo" title="Abrir termo">
                  <svg aria-hidden="true"><use href="#icon-file"></use></svg>
                </a>
                <?php if ($canDelete): ?>
                  <form method="post" onsubmit="return confirm('Excluir este recebimento?');" style="margin:0;">
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
