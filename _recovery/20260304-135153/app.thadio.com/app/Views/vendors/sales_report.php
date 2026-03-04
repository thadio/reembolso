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
    <h1>Relatório de venda de produtos por fornecedor</h1>
    <div class="subtitle">Vendas por produto, com descontos, acréscimos e pagamento de consignação.</div>
  </div>
  <div class="actions">
    <a class="btn ghost" href="fornecedor-list.php">Voltar aos fornecedores</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;">
  <input type="date" name="start" value="<?php echo $esc((string) $filters['start']); ?>" aria-label="Data inicial">
  <input type="date" name="end" value="<?php echo $esc((string) $filters['end']); ?>" aria-label="Data final">
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
  <a class="btn ghost" href="fornecedor-vendas-relatorio.php">Limpar</a>
</form>

<div style="margin-top:18px;">
  <h2 style="margin:0 0 8px;">Resumo por fornecedor</h2>
  <div style="overflow:auto;">
    <table>
      <thead>
        <tr>
          <th>Fornecedor</th>
          <th>Código</th>
          <th>Itens</th>
          <th>Unidades</th>
          <th>Receita líquida</th>
          <th>Descontos</th>
          <th>Acréscimos</th>
          <th>Custo total</th>
          <th>Pagto consignação</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($summaryRows)): ?>
          <tr class="no-results"><td colspan="9">Nenhum dado encontrado.</td></tr>
        <?php else: ?>
          <?php foreach ($summaryRows as $row): ?>
            <tr>
              <td><?php echo $esc($row['supplier_name']); ?></td>
              <td><?php echo (int) $row['supplier_id']; ?></td>
              <td><?php echo (int) $row['item_count']; ?></td>
              <td><?php echo (int) $row['unit_count']; ?></td>
              <td data-value="<?php echo $row['net_total']; ?>">R$ <?php echo number_format($row['net_total'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $row['discount_total']; ?>">R$ <?php echo number_format($row['discount_total'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $row['additions_total']; ?>">R$ <?php echo number_format($row['additions_total'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $row['cost_total']; ?>">R$ <?php echo number_format($row['cost_total'], 2, ',', '.'); ?></td>
              <td data-value="<?php echo $row['consign_total']; ?>">R$ <?php echo number_format($row['consign_total'], 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td><strong>Total geral</strong></td>
            <td>—</td>
            <td><strong><?php echo (int) $totals['item_count']; ?></strong></td>
            <td><strong><?php echo (int) $totals['unit_count']; ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['net_total'], 2, ',', '.'); ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['discount_total'], 2, ',', '.'); ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['additions_total'], 2, ',', '.'); ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['cost_total'], 2, ',', '.'); ?></strong></td>
            <td><strong>R$ <?php echo number_format($totals['consign_total'], 2, ',', '.'); ?></strong></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:6px;color:var(--muted);font-size:13px;">
    Acréscimos são rateios de frete e impostos do pedido.
  </div>
</div>

<div style="margin-top:20px;">
  <div class="table-tools">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em vendas" value="">
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
            <th data-sort-key="order_id" aria-sort="none">Pedido</th>
            <th data-sort-key="order_date" aria-sort="none">Data</th>
            <th data-sort-key="order_status" aria-sort="none">Status</th>
            <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
            <th data-sort-key="source" aria-sort="none">Origem</th>
            <th data-sort-key="sku" aria-sort="none">SKU</th>
            <th data-sort-key="product" aria-sort="none">Produto</th>
            <th data-sort-key="quantity" aria-sort="none">Qtde</th>
            <th data-sort-key="unit_net" aria-sort="none">Valor unit.</th>
            <th data-sort-key="net_total" aria-sort="none">Valor total</th>
            <th data-sort-key="discount" aria-sort="none">Desconto</th>
            <th data-sort-key="additions" aria-sort="none">Acréscimos</th>
            <th data-sort-key="cost_unit" aria-sort="none">Custo unit.</th>
            <th data-sort-key="cost_total" aria-sort="none">Custo total</th>
            <th data-sort-key="consign_percent" aria-sort="none">% Consignação</th>
            <th data-sort-key="consign_payment" aria-sort="none">Pagto consignação</th>
          </tr>
          <tr class="filters-row">
            <th><input type="search" data-filter-col="order_id" placeholder="Pedido" aria-label="Filtrar pedido"></th>
            <th><input type="search" data-filter-col="order_date" placeholder="Data" aria-label="Filtrar data"></th>
            <th><input type="search" data-filter-col="order_status" placeholder="Status" aria-label="Filtrar status"></th>
            <th><input type="search" data-filter-col="supplier" placeholder="Fornecedor" aria-label="Filtrar fornecedor"></th>
            <th><input type="search" data-filter-col="source" placeholder="Origem" aria-label="Filtrar origem"></th>
            <th><input type="search" data-filter-col="sku" placeholder="SKU" aria-label="Filtrar SKU"></th>
            <th><input type="search" data-filter-col="product" placeholder="Produto" aria-label="Filtrar produto"></th>
            <th><input type="search" data-filter-col="quantity" placeholder="Qtde" aria-label="Filtrar quantidade"></th>
            <th><input type="search" data-filter-col="unit_net" placeholder="Valor unit." aria-label="Filtrar valor unitário"></th>
            <th><input type="search" data-filter-col="net_total" placeholder="Valor total" aria-label="Filtrar valor total"></th>
            <th><input type="search" data-filter-col="discount" placeholder="Desconto" aria-label="Filtrar desconto"></th>
            <th><input type="search" data-filter-col="additions" placeholder="Acréscimos" aria-label="Filtrar acréscimos"></th>
            <th><input type="search" data-filter-col="cost_unit" placeholder="Custo unit." aria-label="Filtrar custo unitário"></th>
            <th><input type="search" data-filter-col="cost_total" placeholder="Custo total" aria-label="Filtrar custo total"></th>
            <th><input type="search" data-filter-col="consign_percent" placeholder="% Cons." aria-label="Filtrar consignação"></th>
            <th><input type="search" data-filter-col="consign_payment" placeholder="Pagto" aria-label="Filtrar pagamento"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="no-results"><td colspan="16">Nenhuma venda encontrada.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $sourceLabel = $sourceLabels[$row['source']] ?? ($row['source'] !== '' ? $row['source'] : '—');
                $statusLabel = $row['order_status'] !== '' ? $row['order_status'] : '—';
              ?>
              <tr>
                <td data-value="<?php echo (int) $row['order_id']; ?>"><?php echo (int) $row['order_id']; ?></td>
                <td data-value="<?php echo $esc($row['order_date']); ?>"><?php echo $esc($row['order_date']); ?></td>
                <td data-value="<?php echo $esc($statusLabel); ?>"><?php echo $esc($statusLabel); ?></td>
                <td data-value="<?php echo $esc($row['supplier_name']); ?>"><?php echo $esc($row['supplier_name']); ?></td>
                <td data-value="<?php echo $esc($row['source']); ?>"><?php echo $esc($sourceLabel); ?></td>
                <td data-value="<?php echo $esc($row['sku']); ?>"><?php echo $esc($row['sku']); ?></td>
                <td data-value="<?php echo $esc($row['product_name']); ?>"><?php echo $esc($row['product_name']); ?></td>
                <td data-value="<?php echo (int) $row['quantity']; ?>"><?php echo (int) $row['quantity']; ?></td>
                <td data-value="<?php echo $row['unit_net'] !== null ? $row['unit_net'] : ''; ?>">
                  <?php if ($row['unit_net'] !== null): ?>
                    R$ <?php echo number_format((float) $row['unit_net'], 2, ',', '.'); ?>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $row['net_total']; ?>">
                  R$ <?php echo number_format((float) $row['net_total'], 2, ',', '.'); ?>
                </td>
                <td data-value="<?php echo $row['discount']; ?>">
                  R$ <?php echo number_format((float) $row['discount'], 2, ',', '.'); ?>
                </td>
                <td data-value="<?php echo $row['additions']; ?>">
                  R$ <?php echo number_format((float) $row['additions'], 2, ',', '.'); ?>
                </td>
                <td data-value="<?php echo $row['cost_unit'] !== null ? $row['cost_unit'] : ''; ?>">
                  <?php if ($row['cost_unit'] !== null): ?>
                    R$ <?php echo number_format((float) $row['cost_unit'], 2, ',', '.'); ?>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $row['cost_total'] !== null ? $row['cost_total'] : ''; ?>">
                  <?php if ($row['cost_total'] !== null): ?>
                    R$ <?php echo number_format((float) $row['cost_total'], 2, ',', '.'); ?>
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $row['consign_percent'] !== null ? $row['consign_percent'] : ''; ?>">
                  <?php if ($row['consign_percent'] !== null): ?>
                    <?php echo number_format((float) $row['consign_percent'], 2, ',', '.'); ?>%
                  <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td data-value="<?php echo $row['consign_payment'] !== null ? $row['consign_payment'] : ''; ?>">
                  <?php if ($row['consign_payment'] !== null): ?>
                    R$ <?php echo number_format((float) $row['consign_payment'], 2, ',', '.'); ?>
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
