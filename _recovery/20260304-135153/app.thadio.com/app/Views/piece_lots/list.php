<?php
/** @var array $errors */
/** @var string $success */
/** @var array $rows */
/** @var array $filters */
/** @var string $searchQuery */
/** @var array $columnFilters */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalLots */
/** @var int $totalPages */
/** @var array $vendorOptions */
/** @var array $vendorNames */
/** @var array $openMap */
/** @var array $lotStats */
/** @var array|null $selectedLot */
/** @var string $selectedSupplierName */
/** @var array $lotProducts */
/** @var callable $esc */

use App\Support\Image;

$statusFilter = $filters['status'] ?? '';
$supplierFilter = trim((string) ($filters['supplier'] ?? ''));
$searchQuery = trim((string) ($searchQuery ?? ''));
$columnFilters = is_array($columnFilters ?? null) ? $columnFilters : [];
$sortKey = trim((string) ($sortKey ?? 'opened_at'));
$sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$page = $page ?? 1;
$perPage = $perPage ?? 200;
$perPageOptions = $perPageOptions ?? [100, 200, 500];
$totalLots = $totalLots ?? 0;
$totalPages = $totalPages ?? 1;
$selectedLotId = isset($selectedLot['id']) ? (int) $selectedLot['id'] : 0;
$rangeStart = $totalLots > 0 ? (($page - 1) * $perPage + 1) : 0;
$rangeEnd = $totalLots > 0 ? min($totalLots, $page * $perPage) : 0;
$baseQuery = [];
if ($supplierFilter !== '') {
  $baseQuery['supplier'] = $supplierFilter;
}
if ($statusFilter !== '') {
  $baseQuery['status'] = $statusFilter;
}
if ($searchQuery !== '') {
  $baseQuery['q'] = $searchQuery;
  $baseQuery['search'] = $searchQuery;
}
if ($perPage) {
  $baseQuery['per_page'] = $perPage;
}
if ($sortKey !== '') {
  $baseQuery['sort_key'] = $sortKey;
  $baseQuery['sort'] = $sortKey;
}
if ($sortDir !== '') {
  $baseQuery['sort_dir'] = strtolower($sortDir);
  $baseQuery['dir'] = strtolower($sortDir);
}
foreach ($columnFilters as $param => $value) {
  $value = trim((string) $value);
  if ($value === '') {
    continue;
  }
  $baseQuery[$param] = $value;
}
if ($page > 1) {
  $baseQuery['page'] = $page;
}
$baseQueryString = http_build_query($baseQuery);
$buildLink = function (int $targetPage) use ($baseQuery, $selectedLotId): string {
  $query = $baseQuery;
  $query['page'] = max(1, $targetPage);
  if ($selectedLotId > 0) {
    $query['lot_id'] = $selectedLotId;
  }
  return 'lote-produtos-gestao.php?' . http_build_query($query);
};
$formatMoney = static function ($value): string {
  if ($value === null || $value === '') {
    return '-';
  }
  return 'R$ ' . number_format((float) $value, 2, ',', '.');
};
$formatPercent = static function ($value): string {
  if ($value === null || $value === '') {
    return '-';
  }
  return number_format((float) $value, 2, ',', '.') . '%';
};
$formatPriceRange = static function ($min, $max) use ($formatMoney): string {
  if ($min === null && $max === null) {
    return '-';
  }
  if ($min !== null && $max !== null && (string) $min !== (string) $max) {
    return $formatMoney($min) . ' - ' . $formatMoney($max);
  }
  return $formatMoney($min ?? $max);
};
$statusLabels = [
  'aberto' => 'Aberto',
  'fechado' => 'Fechado',
  'lixeira' => 'Lixeira',
];
?>
<div class="page-head">
  <div>
    <h1>Lotes de produtos</h1>
    <div class="subtitle">Abra, consulte e encerre lotes vinculados a um único fornecedor.</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn ghost" href="lote-produtos.php">Receber lote</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="card" style="margin:12px 0;display:grid;gap:12px;">
  <h2 style="margin:0;font-size:16px;">Abrir novo lote</h2>
  <form method="post" action="lote-produtos-gestao.php" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));align-items:flex-end;">
    <input type="hidden" name="action" value="create">
    <div class="field">
      <label for="lot_supplier">Fornecedor *</label>
      <input id="lot_supplier" name="supplier_pessoa_id" list="lot_supplier_options" required placeholder="ID da pessoa fornecedora" value="<?php echo $esc($supplierFilter); ?>">
      <datalist id="lot_supplier_options">
        <?php foreach ($vendorOptions as $vendor): ?>
          <option value="<?php echo (int) $vendor['id']; ?>"><?php echo $esc(($vendor['full_name'] ?? '') . ' — Pessoa ' . (int) $vendor['id']); ?></option>
        <?php endforeach; ?>
      </datalist>
      <small class="help-text">Cada lote pertence a apenas um fornecedor.</small>
    </div>
    <div class="field" style="grid-column: span 2;">
      <label for="lot_notes">Observações do lote</label>
      <input id="lot_notes" name="notes" type="text" maxlength="200" placeholder="Ex.: caixas com produtos delicados">
    </div>
    <div class="field">
      <button class="primary" type="submit" style="width:100%;">Abrir lote agora</button>
    </div>
  </form>
</div>

<div class="card" style="margin:12px 0;display:grid;gap:10px;">
  <h2 style="margin:0;font-size:16px;">Filtros</h2>
  <form method="get" action="lote-produtos-gestao.php" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
    <input type="hidden" name="page" value="1">
    <?php if ($perPage): ?>
      <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
    <?php endif; ?>
    <?php if ($selectedLotId > 0): ?>
      <input type="hidden" name="lot_id" value="<?php echo (int) $selectedLotId; ?>">
    <?php endif; ?>
    <?php if ($sortKey !== ''): ?>
      <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
      <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
    <?php endif; ?>
    <?php if ($sortDir !== ''): ?>
      <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
    <?php endif; ?>
    <?php foreach ($columnFilters as $param => $value): ?>
      <input type="hidden" name="<?php echo $esc((string) $param); ?>" value="<?php echo $esc((string) $value); ?>">
    <?php endforeach; ?>
    <div class="field">
      <label for="filter_q">Busca</label>
      <input id="filter_q" name="q" type="search" placeholder="ID, lote, fornecedor, status" value="<?php echo $esc($searchQuery); ?>">
    </div>
    <div class="field">
      <label for="filter_supplier">Fornecedor</label>
      <input id="filter_supplier" name="supplier" type="text" list="lot_supplier_options" placeholder="Pessoa ID ou deixe em branco" value="<?php echo $esc($supplierFilter); ?>">
    </div>
    <div class="field">
      <label for="filter_status">Status</label>
      <select id="filter_status" name="status">
        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Todos</option>
        <option value="aberto" <?php echo $statusFilter === 'aberto' ? 'selected' : ''; ?>>Abertos</option>
        <option value="fechado" <?php echo $statusFilter === 'fechado' ? 'selected' : ''; ?>>Fechados</option>
        <option value="lixeira" <?php echo $statusFilter === 'lixeira' ? 'selected' : ''; ?>>Lixeira</option>
      </select>
    </div>
    <div class="field" style="display:flex;gap:8px;align-items:center;">
      <button class="ghost" type="submit">Filtrar</button>
      <a class="btn ghost" href="lote-produtos-gestao.php">Limpar</a>
    </div>
  </form>
</div>

<div class="card" style="margin:12px 0;" data-table-scope>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
    <h2 style="margin:0;font-size:16px;">Lotes</h2>
    <div class="subtitle">Inclua produtos de cadastro individual ou recebimento em lote.</div>
  </div>
  <div class="table-tools" style="margin-bottom:8px;">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em lotes" value="<?php echo $esc($searchQuery); ?>">
      <span style="color:var(--muted);font-size:13px;">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalLots; ?></span>
    </div>
    <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="page" value="1">
      <?php if ($supplierFilter !== ''): ?>
        <input type="hidden" name="supplier" value="<?php echo $esc($supplierFilter); ?>">
      <?php endif; ?>
      <?php if ($statusFilter !== ''): ?>
        <input type="hidden" name="status" value="<?php echo $esc($statusFilter); ?>">
      <?php endif; ?>
      <?php if ($searchQuery !== ''): ?>
        <input type="hidden" name="q" value="<?php echo $esc($searchQuery); ?>">
        <input type="hidden" name="search" value="<?php echo $esc($searchQuery); ?>">
      <?php endif; ?>
      <?php if ($sortKey !== ''): ?>
        <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
        <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
      <?php endif; ?>
      <?php if ($sortDir !== ''): ?>
        <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
        <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <?php endif; ?>
      <?php foreach ($columnFilters as $param => $value): ?>
        <input type="hidden" name="<?php echo $esc((string) $param); ?>" value="<?php echo $esc((string) $value); ?>">
      <?php endforeach; ?>
      <?php if ($selectedLotId > 0): ?>
        <input type="hidden" name="lot_id" value="<?php echo (int) $selectedLotId; ?>">
      <?php endif; ?>
      <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
      <select id="perPage" name="per_page">
        <?php foreach ($perPageOptions as $option): ?>
          <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-wrapper">
    <table data-table="interactive" data-filter-mode="server">
      <thead>
        <tr>
          <th data-sort-key="id" aria-sort="<?php echo $sortKey === 'id' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">ID</th>
          <th data-sort-key="name" aria-sort="<?php echo $sortKey === 'name' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Nome do lote</th>
          <th data-sort-key="supplier" aria-sort="<?php echo $sortKey === 'supplier' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Fornecedor</th>
          <th data-sort-key="status" aria-sort="<?php echo $sortKey === 'status' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Status</th>
          <th data-sort-key="opened_at" aria-sort="<?php echo $sortKey === 'opened_at' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Aberto em</th>
          <th data-sort-key="closed_at" aria-sort="<?php echo $sortKey === 'closed_at' ? ($sortDir === 'ASC' ? 'ascending' : 'descending') : 'none'; ?>">Fechado em</th>
          <th>Qtd total</th>
          <th>Vendidas</th>
          <th>Devolvidas</th>
          <th>Disponíveis</th>
          <th>Custo do lote</th>
          <th>Receita potencial</th>
          <th class="col-actions">Ações</th>
        </tr>
        <tr>
          <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc((string) ($columnFilters['filter_id'] ?? '')); ?>" placeholder="#" aria-label="Filtrar ID"></th>
          <th><input type="search" data-filter-col="name" data-query-param="filter_name" value="<?php echo $esc((string) ($columnFilters['filter_name'] ?? '')); ?>" placeholder="Nome" aria-label="Filtrar lote"></th>
          <th><input type="search" data-filter-col="supplier" data-query-param="filter_supplier" value="<?php echo $esc((string) ($columnFilters['filter_supplier'] ?? '')); ?>" placeholder="Fornecedor" aria-label="Filtrar fornecedor"></th>
          <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc((string) ($columnFilters['filter_status'] ?? '')); ?>" placeholder="Status" aria-label="Filtrar status"></th>
          <th><input type="search" data-filter-col="opened_at" data-query-param="filter_opened_at" value="<?php echo $esc((string) ($columnFilters['filter_opened_at'] ?? '')); ?>" placeholder="Data" aria-label="Filtrar abertura"></th>
          <th><input type="search" data-filter-col="closed_at" data-query-param="filter_closed_at" value="<?php echo $esc((string) ($columnFilters['filter_closed_at'] ?? '')); ?>" placeholder="Data" aria-label="Filtrar fechamento"></th>
          <th></th>
          <th></th>
          <th></th>
          <th></th>
          <th></th>
          <th></th>
          <th class="col-actions"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="13" style="text-align:center;">Nenhum lote encontrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $supplierPessoaId = (int) ($row['supplier_pessoa_id'] ?? 0);
              $supplierName = $vendorNames[$supplierPessoaId] ?? ('Fornecedor ' . $supplierPessoaId);
              $rowStatus = (string) ($row['status'] ?? '');
              $isOpen = $rowStatus === 'aberto';
              $isTrashed = $rowStatus === 'lixeira';
              $statusLabel = $statusLabels[$rowStatus] ?? '—';
              $stats = $lotStats[(int) ($row['id'] ?? 0)] ?? [
                'total' => 0,
                'sold' => 0,
                'returned' => 0,
                'available' => 0,
                'lot_cost' => 0.0,
                'potential_revenue' => 0.0,
              ];
              $totalPieces = (int) ($stats['total'] ?? 0);
              $soldPieces = (int) ($stats['sold'] ?? 0);
              $returnedPieces = (int) ($stats['returned'] ?? 0);
              $availablePieces = (int) ($stats['available'] ?? 0);
              $lotCost = (float) ($stats['lot_cost'] ?? 0.0);
              $potentialRevenue = (float) ($stats['potential_revenue'] ?? 0.0);
              $detailQuery = $baseQuery;
              $detailQuery['lot_id'] = (int) $row['id'];
              $detailHref = 'lote-produtos-gestao.php' . (!empty($detailQuery) ? '?' . http_build_query($detailQuery) : '');
            ?>
            <tr>
              <td data-value="<?php echo (int) $row['id']; ?>"><a href="<?php echo $esc($detailHref); ?>">#<?php echo (int) $row['id']; ?></a></td>
              <td><a href="<?php echo $esc($detailHref); ?>"><?php echo $esc((string) $row['name']); ?></a></td>
              <td data-value="<?php echo $esc((string) $supplierName); ?>">
                <?php echo $esc($supplierName); ?> — Pessoa <?php echo $supplierPessoaId; ?>
              </td>
              <td data-value="<?php echo $esc($rowStatus); ?>"><?php echo $statusLabel; ?></td>
              <?php
                $openedTs = isset($row['opened_at']) ? strtotime((string) $row['opened_at']) : false;
                $closedTs = isset($row['closed_at']) ? strtotime((string) $row['closed_at']) : false;
              ?>
              <td data-value="<?php echo $esc((string) ($row['opened_at'] ?? '')); ?>">
                <?php echo $openedTs ? $esc(date('d/m/Y H:i', $openedTs)) : '—'; ?>
              </td>
              <td data-value="<?php echo $esc((string) ($row['closed_at'] ?? '')); ?>">
                <?php echo $closedTs ? $esc(date('d/m/Y H:i', $closedTs)) : '—'; ?>
              </td>
              <td data-value="<?php echo $totalPieces; ?>"><?php echo number_format($totalPieces, 0, ',', '.'); ?></td>
              <td data-value="<?php echo $soldPieces; ?>"><?php echo number_format($soldPieces, 0, ',', '.'); ?></td>
              <td data-value="<?php echo $returnedPieces; ?>"><?php echo number_format($returnedPieces, 0, ',', '.'); ?></td>
              <td data-value="<?php echo $availablePieces; ?>"><?php echo number_format($availablePieces, 0, ',', '.'); ?></td>
              <td data-value="<?php echo $lotCost; ?>"><?php echo $formatMoney($lotCost); ?></td>
              <td data-value="<?php echo $potentialRevenue; ?>"><?php echo $formatMoney($potentialRevenue); ?></td>
              <td class="col-actions">
                <div class="actions">
                  <?php if (!$isTrashed): ?>
                    <form method="post" action="lote-produtos-gestao.php" style="margin:0;">
                      <input type="hidden" name="lot_id" value="<?php echo (int) $row['id']; ?>">
                      <?php if ($isOpen): ?>
                        <input type="hidden" name="action" value="close">
                        <button type="submit" class="icon-btn success" aria-label="Encerrar lote" title="Encerrar lote">
                          <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                        </button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="reopen">
                        <button type="submit" class="icon-btn" aria-label="Reabrir lote" title="Reabrir lote">
                          <svg aria-hidden="true"><use href="#icon-restore"></use></svg>
                        </button>
                      <?php endif; ?>
                    </form>
                    <form method="post" action="lote-produtos-gestao.php" onsubmit="return confirm('Enviar este lote para a lixeira?');" style="margin:0;">
                      <input type="hidden" name="lot_id" value="<?php echo (int) $row['id']; ?>">
                      <input type="hidden" name="action" value="trash">
                      <button type="submit" class="icon-btn danger" aria-label="Enviar para lixeira" title="Enviar para lixeira">
                        <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                      </button>
                    </form>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildLink($page + 1)); ?>">Próxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildLink($totalPages)); ?>">Última</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
    <?php endif; ?>
  </div>
</div>

<script>
  (function() {
    const perPage = document.getElementById('perPage');
    const form = document.getElementById('perPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>

<?php if (!empty($selectedLot)): ?>
  <?php
    $selectedSupplierCode = (int) ($selectedLot['supplier_pessoa_id'] ?? 0);
    $detailStatusKey = (string) ($selectedLot['status'] ?? '');
    $detailStatus = $statusLabels[$detailStatusKey] ?? '—';
    $detailOpenedTs = isset($selectedLot['opened_at']) ? strtotime((string) $selectedLot['opened_at']) : false;
    $detailClosedTs = isset($selectedLot['closed_at']) ? strtotime((string) $selectedLot['closed_at']) : false;
    $closeDetailHref = 'lote-produtos-gestao.php' . ($baseQueryString !== '' ? '?' . $baseQueryString : '');
  ?>
  <div class="card" style="margin:12px 0;display:grid;gap:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0;font-size:16px;">Detalhamento do lote #<?php echo (int) ($selectedLot['id'] ?? 0); ?></h2>
        <div class="subtitle">Fornecedor <?php echo $esc($selectedSupplierName); ?> — Pessoa <?php echo $selectedSupplierCode; ?></div>
      </div>
      <a class="btn ghost" href="<?php echo $esc($closeDetailHref); ?>">Fechar detalhe</a>
    </div>
    <div style="display:grid;gap:6px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
      <div>
        <strong>Status</strong>
        <div><?php echo $detailStatus; ?></div>
      </div>
      <div>
        <strong>Aberto em</strong>
        <div><?php echo $detailOpenedTs ? $esc(date('d/m/Y H:i', $detailOpenedTs)) : '—'; ?></div>
      </div>
      <div>
        <strong>Fechado em</strong>
        <div><?php echo $detailClosedTs ? $esc(date('d/m/Y H:i', $detailClosedTs)) : '—'; ?></div>
      </div>
      <div>
        <strong>Observações</strong>
        <div><?php echo isset($selectedLot['notes']) && $selectedLot['notes'] !== '' ? $esc((string) $selectedLot['notes']) : '—'; ?></div>
      </div>
    </div>
    <div>
      <h3 style="margin:0 0 6px;font-size:14px;">Produtos do lote (<?php echo number_format(count($lotProducts), 0, ',', '.'); ?>)</h3>
      <div class="table-wrapper" data-table-scope>
        <table data-table="interactive">
          <thead>
            <tr>
              <th>Foto</th>
              <th data-sort-key="sku" aria-sort="none">SKU</th>
              <th data-sort-key="name" aria-sort="none">Nome</th>
              <th data-sort-key="price" aria-sort="none">Preço</th>
              <th data-sort-key="stock" aria-sort="none">Disponível</th>
              <th data-sort-key="source" aria-sort="none">Origem</th>
              <th data-sort-key="cost" aria-sort="none">Custo</th>
              <th data-sort-key="consign" aria-sort="none">% Consignação</th>
            </tr>
            <tr>
              <th></th>
              <th><input type="search" data-filter-col="sku" placeholder="SKU" aria-label="Filtrar SKU"></th>
              <th><input type="search" data-filter-col="name" placeholder="Nome" aria-label="Filtrar nome"></th>
              <th></th>
              <th></th>
              <th><input type="search" data-filter-col="source" placeholder="Origem" aria-label="Filtrar origem"></th>
              <th></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lotProducts)): ?>
              <tr>
                <td colspan="8" style="text-align:center;">Nenhum produto vinculado a este lote.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($lotProducts as $product): ?>
                <?php
                  $priceLabel = $formatPriceRange($product['price_min'], $product['price_max']);
                  $priceValue = $product['price_min'] ?? $product['price_max'] ?? '';
                  $stockStatusRaw = strtolower((string) ($product['availability_status'] ?? ''));
                  $stockLabel = match ($stockStatusRaw) {
                    'disponivel' => 'Disponível',
                    'reservado' => 'Reservado',
                    'esgotado' => 'Esgotado',
                    'indisponivel' => 'Indisponível',
                    default => '—',
                  };
                  $quantity = $product['quantity'] ?? null;
                  if ($quantity !== null && $quantity !== '') {
                    $stockLabel .= ' (' . (int) $quantity . ')';
                  }
                  $imageSrc = trim((string) ($product['image_src'] ?? ''));
                  $thumbSrc = $imageSrc !== '' ? image_url($imageSrc, 'thumb', 110) : '';
                  $displayImage = $thumbSrc !== '' ? $thumbSrc : $imageSrc;
                ?>
                <?php $productSku = (int) ($product['product_sku'] ?? ($product['product_id'] ?? 0)); ?>
                <?php $productHref = 'produto-cadastro.php?id=' . $productSku; ?>
                <tr>
                  <td>
                    <div class="order-item-table-thumb">
                      <?php if ($imageSrc !== ''): ?>
                        <img src="<?php echo $esc($displayImage); ?>" data-thumb-full="<?php echo $esc($imageSrc); ?>" data-thumb-size="110" alt="<?php echo $esc($product['name'] ?? 'Produto'); ?>">
                      <?php else: ?>
                        <span>Sem foto</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td data-value="<?php echo $esc((string) $product['sku']); ?>"><?php echo $esc((string) $product['sku']); ?></td>
                  <td data-value="<?php echo $esc((string) $product['name']); ?>">
                    <a href="<?php echo $esc($productHref); ?>">
                      <?php echo $product['name'] !== '' ? $esc((string) $product['name']) : ('Produto #' . $productSku); ?>
                    </a>
                  </td>
                  <td data-value="<?php echo $esc((string) $priceValue); ?>"><?php echo $priceLabel; ?></td>
                  <td data-value="<?php echo $esc((string) $stockStatusRaw); ?>"><?php echo $esc($stockLabel); ?></td>
                  <td data-value="<?php echo $esc((string) $product['source']); ?>"><?php echo $esc((string) $product['source']); ?></td>
                  <td data-value="<?php echo $esc((string) ($product['cost'] ?? '')); ?>"><?php echo $formatMoney($product['cost']); ?></td>
                  <td data-value="<?php echo $esc((string) ($product['percentual_consignacao'] ?? '')); ?>"><?php echo $formatPercent($product['percentual_consignacao']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
