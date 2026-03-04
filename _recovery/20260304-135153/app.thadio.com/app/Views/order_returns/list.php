<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalReturns */
/** @var int $totalPages */
/** @var string $statusFilter */
/** @var string $refundFilter */
/** @var int $orderFilter */
/** @var string $searchQuery */
/** @var array $columnFilters */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var array $statusOptions */
/** @var array $refundStatusOptions */
/** @var callable $esc */
?>
<?php
  $page = $page ?? 1;
  $perPage = $perPage ?? 120;
  $perPageOptions = $perPageOptions ?? [50, 100, 120, 200];
  $totalReturns = $totalReturns ?? 0;
  $totalPages = $totalPages ?? 1;
  $statusFilter = $statusFilter ?? '';
  $refundFilter = $refundFilter ?? '';
  $orderFilter = $orderFilter ?? 0;
  $searchQuery = $searchQuery ?? '';
  $columnFilters = $columnFilters ?? [];
  $sortKey = $sortKey ?? 'id';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $statusOptions = $statusOptions ?? [];
  $refundStatusOptions = $refundStatusOptions ?? [];
  $canCreate = userCan('order_returns.create');
  $canView = userCan('order_returns.view');
  $canCancel = userCan('order_returns.edit');
  $rangeStart = $totalReturns > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalReturns > 0 ? min($totalReturns, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage, $statusFilter, $refundFilter, $orderFilter, $searchQuery, $columnFilters, $sortKey, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage];
    if ($statusFilter !== '') {
      $query['status'] = $statusFilter;
    }
    if ($refundFilter !== '') {
      $query['refund_status'] = $refundFilter;
    }
    if ($orderFilter > 0) {
      $query['order_id'] = $orderFilter;
    }
    if ($searchQuery !== '') {
      $query['q'] = $searchQuery;
    }
    if ($sortKey !== '') {
      $query['sort_key'] = $sortKey;
      $query['sort'] = $sortKey;
    }
    if ($sortDir !== '') {
      $query['sort_dir'] = strtolower($sortDir);
      $query['dir'] = strtolower($sortDir);
    }
    foreach ($columnFilters as $param => $value) {
      if ($value === '') {
        continue;
      }
      $query[$param] = $value;
    }
    return 'pedido-devolucao-list.php?' . http_build_query($query);
  };
  $buildStatusLink = function (string $targetStatus) use ($perPage, $refundFilter, $orderFilter, $searchQuery, $columnFilters, $sortKey, $sortDir): string {
    $query = ['page' => 1, 'per_page' => $perPage];
    if ($targetStatus !== '') {
      $query['status'] = $targetStatus;
    }
    if ($refundFilter !== '') {
      $query['refund_status'] = $refundFilter;
    }
    if ($orderFilter > 0) {
      $query['order_id'] = $orderFilter;
    }
    if ($searchQuery !== '') {
      $query['q'] = $searchQuery;
    }
    if ($sortKey !== '') {
      $query['sort_key'] = $sortKey;
      $query['sort'] = $sortKey;
    }
    if ($sortDir !== '') {
      $query['sort_dir'] = strtolower($sortDir);
      $query['dir'] = strtolower($sortDir);
    }
    foreach ($columnFilters as $param => $value) {
      if ($value === '') {
        continue;
      }
      $query[$param] = $value;
    }
    return 'pedido-devolucao-list.php?' . http_build_query($query);
  };
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Devoluções</h1>
    <div class="subtitle">Controle de devoluções, reembolso e retorno à disponibilidade.</div>
  </div>
  <?php if ($canCreate): ?>
    <a class="btn primary" href="pedido-devolucao-cadastro.php">Registrar devolução</a>
  <?php endif; ?>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
  <a class="btn <?php echo $statusFilter === '' ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildStatusLink('')); ?>">Todas</a>
  <?php foreach ($statusOptions as $key => $label): ?>
    <a class="btn <?php echo $statusFilter === $key ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildStatusLink($key)); ?>">
      <?php echo $esc($label); ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em devoluções" value="<?php echo $esc($searchQuery); ?>">
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="page" value="1">
      <?php if ($perPage): ?>
        <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
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
        <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
      <?php endforeach; ?>
      <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--muted);">
        <span>Reembolso</span>
        <select name="refund_status">
          <option value="">Todos</option>
          <?php foreach ($refundStatusOptions as $key => $label): ?>
            <option value="<?php echo $esc($key); ?>"<?php echo $refundFilter === $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="font-size:13px;color:var(--muted);">
        Pedido #
        <input type="number" name="order_id" value="<?php echo (int) $orderFilter; ?>" placeholder="ID" style="width:120px;">
      </label>
      <button class="btn ghost" type="submit">Filtrar</button>
    </form>
    <form method="get" id="perPageForm" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="page" value="1">
      <?php if ($statusFilter !== ''): ?>
        <input type="hidden" name="status" value="<?php echo $esc($statusFilter); ?>">
      <?php endif; ?>
      <?php if ($refundFilter !== ''): ?>
        <input type="hidden" name="refund_status" value="<?php echo $esc($refundFilter); ?>">
      <?php endif; ?>
      <?php if ($orderFilter > 0): ?>
        <input type="hidden" name="order_id" value="<?php echo (int) $orderFilter; ?>">
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
        <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
      <?php endforeach; ?>
      <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
      <select id="perPage" name="per_page">
        <?php foreach ($perPageOptions as $option): ?>
          <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalReturns; ?></span>
    </form>
  </div>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">Devolução</th>
        <th data-sort-key="order" aria-sort="none">Pedido</th>
        <th data-sort-key="customer" aria-sort="none">Cliente</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="refund" aria-sort="none">Reembolso</th>
        <th data-sort-key="amount" aria-sort="none" class="col-total">Valor</th>
        <th data-sort-key="qty" aria-sort="none">Qtd itens</th>
        <th data-sort-key="date" aria-sort="none">Atualizado</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilters['filter_id'] ?? ''); ?>" placeholder="#" aria-label="Filtrar devolução"></th>
        <th><input type="search" data-filter-col="order" data-query-param="filter_order" value="<?php echo $esc($columnFilters['filter_order'] ?? ''); ?>" placeholder="#" aria-label="Filtrar pedido"></th>
        <th><input type="search" data-filter-col="customer" data-query-param="filter_customer" value="<?php echo $esc($columnFilters['filter_customer'] ?? ''); ?>" placeholder="Cliente" aria-label="Filtrar cliente"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilters['filter_status'] ?? ''); ?>" placeholder="Status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="refund" data-query-param="filter_refund" value="<?php echo $esc($columnFilters['filter_refund'] ?? ''); ?>" placeholder="Reembolso" aria-label="Filtrar reembolso"></th>
        <th class="col-total"><input type="search" data-filter-col="amount" data-query-param="filter_amount" value="<?php echo $esc($columnFilters['filter_amount'] ?? ''); ?>" placeholder="Valor" aria-label="Filtrar valor"></th>
        <th><input type="search" data-filter-col="qty" data-query-param="filter_qty" value="<?php echo $esc($columnFilters['filter_qty'] ?? ''); ?>" placeholder="Qtd" aria-label="Filtrar quantidade"></th>
        <th><input type="search" data-filter-col="date" data-query-param="filter_date" value="<?php echo $esc($columnFilters['filter_date'] ?? ''); ?>" placeholder="Data" aria-label="Filtrar data"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="9">Nenhuma devolução registrada.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $id = (int) ($row['id'] ?? 0);
            $orderId = (int) ($row['order_id'] ?? 0);
            $customerName = trim((string) ($row['customer_name'] ?? ''));
            $customerEmail = trim((string) ($row['customer_email'] ?? ''));
            if ($customerName === '' && $customerEmail !== '') {
              $customerName = $customerEmail;
            }
            $showCustomerEmail = $customerEmail !== '' && strcasecmp($customerName, $customerEmail) !== 0;
            if ($customerName === '') {
              $customerName = '-';
            }
            $statusKey = (string) ($row['status'] ?? '');
            $statusLabel = $statusOptions[$statusKey] ?? $statusKey;
            $refundStatusKey = (string) ($row['refund_status'] ?? '');
            $refundStatusLabel = $refundStatusOptions[$refundStatusKey] ?? $refundStatusKey;
            $refundMethod = (string) ($row['refund_method'] ?? '');
            $refundLabel = trim($refundStatusLabel . ' - ' . $refundMethod);
            $amount = isset($row['refund_amount']) ? (float) $row['refund_amount'] : 0.0;
            $amountLabel = 'R$ ' . number_format($amount, 2, ',', '.');
            $itemsCount = isset($row['items_count']) ? (int) $row['items_count'] : 0;
            $totalQty = isset($row['total_quantity']) ? (int) $row['total_quantity'] : 0;
            $qtyLabel = $itemsCount > 0 ? $itemsCount . ' it./ ' . $totalQty . ' un.' : '0';
            $updatedAt = $row['updated_at'] ?? $row['created_at'] ?? null;
            $updatedLabel = $updatedAt ? date('d/m/Y H:i', strtotime($updatedAt)) : '-';
            $rowLink = $canView ? 'pedido-devolucao-cadastro.php?id=' . $id : '';
            $restockedAt = $row['restocked_at'] ?? null;
            $canRowCancel = $canCancel
              && $statusKey !== 'cancelled'
              && empty($restockedAt)
              && !($refundStatusKey === 'done' && $refundMethod !== 'none');
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo $id; ?>">#<?php echo $id; ?></td>
            <td data-value="<?php echo $orderId; ?>">
              <?php if ($orderId > 0): ?>
                <a class="link" href="pedido-cadastro.php?id=<?php echo $orderId; ?>">Pedido #<?php echo $orderId; ?></a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($customerName); ?>">
              <div><?php echo $esc($customerName); ?></div>
              <?php if ($showCustomerEmail): ?>
                <div class="muted"><?php echo $esc($customerEmail); ?></div>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($statusLabel); ?>"><?php echo $esc($statusLabel); ?></td>
            <td data-value="<?php echo $esc($refundLabel); ?>">
              <div><?php echo $esc($refundStatusLabel); ?></div>
              <?php if ($refundMethod !== ''): ?>
                <div class="muted"><?php echo $esc($refundMethod); ?></div>
              <?php endif; ?>
            </td>
            <td class="col-total" data-value="<?php echo $esc($amountLabel); ?>"><?php echo $esc($amountLabel); ?></td>
            <td data-value="<?php echo $totalQty; ?>"><?php echo $esc($qtyLabel); ?></td>
            <td data-value="<?php echo $esc($updatedLabel); ?>"><?php echo $esc($updatedLabel); ?></td>
            <td class="col-actions">
              <?php if (!$canView): ?>
                <span class="muted">Sem acesso</span>
              <?php elseif ($canRowCancel): ?>
                <form method="post" action="pedido-devolucao-list.php" onsubmit="return confirm('Cancelar esta devolução?');">
                  <input type="hidden" name="cancel_id" value="<?php echo $id; ?>">
                  <button class="btn ghost" type="submit">Cancelar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
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
