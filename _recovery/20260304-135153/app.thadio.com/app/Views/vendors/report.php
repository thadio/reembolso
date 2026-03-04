<?php
/** @var array $rows */
/** @var array $summaryRows */
/** @var array $totals */
/** @var array $filters */
/** @var array $vendorOptions */
/** @var array $errors */
/** @var callable $esc */
?>
<?php
  $sourceLabels = [
    'consignacao' => 'Consignação',
    'compra' => 'Compra',
    'doacao' => 'Doação',
  ];
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Relatório de produtos por fornecedor</h1>
    <div class="subtitle">Visão consolidada de produtos, disponibilidade e valores por fornecedor.</div>
  </div>
  <div class="actions">
    <a class="btn ghost" href="fornecedor-list.php">Voltar aos fornecedores</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;">
  <input
    type="search"
    name="supplier"
    list="supplier-suggestions"
    aria-label="Filtrar fornecedor"
    placeholder="Digite nome ou código do fornecedor"
    value="<?php echo $esc((string) ($filters['supplier'] ?? '')); ?>"
    style="min-width:320px;"
  >
  <datalist id="supplier-suggestions">
    <?php foreach ($vendorOptions as $vendor): ?>
      <?php
        $vendorCode = (string) ((int) ($vendor['id_vendor'] ?? 0));
        $vendorName = trim((string) ($vendor['full_name'] ?? ''));
      ?>
      <?php if ($vendorName !== ''): ?>
        <option value="<?php echo $esc($vendorName); ?>" label="<?php echo $esc('Código ' . $vendorCode); ?>"></option>
      <?php endif; ?>
      <?php if ($vendorCode !== '0'): ?>
        <option value="<?php echo $esc($vendorCode); ?>" label="<?php echo $esc($vendorName !== '' ? $vendorName : 'Fornecedor'); ?>"></option>
      <?php endif; ?>
    <?php endforeach; ?>
  </datalist>
  <select name="source" aria-label="Filtrar origem">
    <option value="">Todas as origens</option>
    <?php foreach ($sourceLabels as $sourceKey => $label): ?>
      <option value="<?php echo $esc($sourceKey); ?>" <?php echo $filters['source'] === $sourceKey ? 'selected' : ''; ?>>
        <?php echo $esc($label); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn ghost" type="submit">Filtrar</button>
  <a class="btn ghost" href="fornecedor-relatorio.php">Limpar</a>
</form>

<div style="margin-top:18px;">
  <h2 style="margin:0 0 8px;">Resumo por fornecedor</h2>
  <div style="overflow:auto;">
    <table>
      <thead>
        <tr>
          <th>Fornecedor</th>
          <th>Código</th>
          <th>Produtos</th>
          <th>Disponível</th>
          <th>Valor potencial</th>
          <th>Valor investido</th>
          <th>Margem estimada</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($summaryRows)): ?>
          <tr class="no-results"><td colspan="7">Nenhum dado encontrado.</td></tr>
        <?php else: ?>
          <?php foreach ($summaryRows as $row): ?>
            <?php $margin = $row['potential_value'] - $row['invested_value']; ?>
            <tr>
              <td><?php echo $esc($row['supplier_name']); ?></td>
              <td><?php echo (int) $row['supplier_id']; ?></td>
              <td><?php echo (int) $row['product_count']; ?></td>
              <td><?php echo (int) $row['unit_count']; ?></td>
              <td data-value="<?php echo $row['potential_value']; ?>">R$ <?php echo number_format($row['potential_value'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $row['invested_value']; ?>">R$ <?php echo number_format($row['invested_value'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $margin; ?>">R$ <?php echo number_format($margin, 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td><strong>Total geral</strong></td>
            <td>—</td>
            <td><strong><?php echo (int) $totals['product_count']; ?></strong></td>
            <td><strong><?php echo (int) $totals['unit_count']; ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['potential_value'], 2, ',', '.'); ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['invested_value'], 2, ',', '.'); ?></strong></td>
            <td>
              <strong>R$ <?php echo number_format($totals['potential_value'] - $totals['invested_value'], 2, ',', '.'); ?></strong>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:6px;color:var(--muted);font-size:13px;">
    Disponibilidade total considera apenas itens com quantidade registrada.
  </div>
</div>

<div style="margin-top:20px;">
  <div class="table-tools">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em produtos" value="<?php echo $esc($filters['global'] ?? ''); ?>">
      <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
    </div>
  </div>

  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true">
      <div class="table-scroll-top-inner"></div>
    </div>
    <div class="table-scroll-body">
      <table data-table="interactive">
        <thead>
          <tr>
            <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
            <th data-sort-key="sku" aria-sort="none">SKU</th>
            <th data-sort-key="name" aria-sort="none">Produto</th>
            <th data-sort-key="source" aria-sort="none">Origem</th>
            <th data-sort-key="status" aria-sort="none">Status</th>
            <th data-sort-key="quantity" aria-sort="none">Disponível</th>
            <th data-sort-key="price" aria-sort="none">Preço</th>
            <th data-sort-key="cost" aria-sort="none">Custo</th>
            <th data-sort-key="potential" aria-sort="none">Valor potencial</th>
          </tr>
          <tr class="filters-row">
            <th><input type="search" data-filter-col="supplier" placeholder="Filtrar fornecedor" aria-label="Filtrar fornecedor"></th>
            <th><input type="search" data-filter-col="sku" placeholder="Filtrar SKU" aria-label="Filtrar SKU"></th>
            <th><input type="search" data-filter-col="name" placeholder="Filtrar produto" aria-label="Filtrar produto"></th>
            <th><input type="search" data-filter-col="source" placeholder="Filtrar origem" aria-label="Filtrar origem"></th>
            <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
            <th><input type="search" data-filter-col="quantity" placeholder="Filtrar disponibilidade" aria-label="Filtrar disponibilidade"></th>
            <th><input type="search" data-filter-col="price" placeholder="Filtrar preço" aria-label="Filtrar preço"></th>
            <th><input type="search" data-filter-col="cost" placeholder="Filtrar custo" aria-label="Filtrar custo"></th>
            <th><input type="search" data-filter-col="potential" placeholder="Filtrar valor" aria-label="Filtrar valor"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="no-results"><td colspan="9">Nenhum produto encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $sourceLabel = $sourceLabels[$row['source']] ?? ($row['source'] !== '' ? $row['source'] : '—');
                $statusLabel = $row['status'] !== '' ? $row['status'] : '—';
                $price = $row['price'];
                $cost = $row['cost'];
                $quantity = $row['quantity'];
                $potential = $row['potential_value'];
              ?>
              <tr>
                <td data-value="<?php echo $esc($row['supplier_name']); ?>"><?php echo $esc($row['supplier_name']); ?></td>
                <td data-value="<?php echo $esc($row['sku']); ?>"><?php echo $esc($row['sku']); ?></td>
                <td data-value="<?php echo $esc($row['name']); ?>"><?php echo $esc($row['name']); ?></td>
                <td data-value="<?php echo $esc($row['source']); ?>"><?php echo $esc($sourceLabel); ?></td>
                <td data-value="<?php echo $esc($statusLabel); ?>"><?php echo $esc($statusLabel); ?></td>
                <td data-value="<?php echo $quantity !== null ? (int) $quantity : ''; ?>">
                  <?php echo $quantity !== null ? (int) $quantity : '<span style="color:var(--muted);">—</span>'; ?>
                </td>
                <td data-value="<?php echo $price !== null ? $price : ''; ?>">
                  <?php if ($price !== null): ?>
                    R$ <?php echo number_format((float) $price, 2, ',', '.'); ?>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $cost !== null ? $cost : ''; ?>">
                  <?php if ($cost !== null): ?>
                    R$ <?php echo number_format((float) $cost, 2, ',', '.'); ?>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $potential !== null ? $potential : ''; ?>">
                  <?php if ($potential !== null): ?>
                    R$ <?php echo number_format((float) $potential, 2, ',', '.'); ?>
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
  </div>
</div>
