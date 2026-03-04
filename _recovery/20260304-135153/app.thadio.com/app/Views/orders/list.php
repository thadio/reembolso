<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalOrders */
/** @var int $totalPages */
/** @var string $statusFilter */
/** @var string $searchQuery */
/** @var array $columnFilters */
/** @var array $statusOptions */
/** @var array $paymentStatusOptions */
/** @var array $paymentMethodOptions */
/** @var array $fulfillmentStatusOptions */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var array $orderItems */
/** @var array $returnSummaryByOrder */
/** @var array $bagStatusByOrder */
/** @var callable $esc */
?>
<?php
  use App\Support\Image;
  use App\Services\BagService;
  use App\Services\OrderService;

  $page = $page ?? 1;
  $perPage = $perPage ?? 120;
  $perPageOptions = $perPageOptions ?? [50, 100, 120, 200];
  $totalOrders = $totalOrders ?? 0;
  $totalPages = $totalPages ?? 1;
  $statusFilter = $statusFilter ?? '';
  $searchQuery = $searchQuery ?? '';
  $columnFilters = $columnFilters ?? [];
  $statusOptions = $statusOptions ?? [];
  $paymentStatusOptions = $paymentStatusOptions ?? [];
  $paymentMethodOptions = $paymentMethodOptions ?? [];
  $fulfillmentStatusOptions = $fulfillmentStatusOptions ?? [];
  $sortKey = $sortKey ?? 'date';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $orderItems = $orderItems ?? [];
  $returnSummaryByOrder = $returnSummaryByOrder ?? [];
  $bagStatusByOrder = $bagStatusByOrder ?? [];
  $deliveryModeOptions = OrderService::deliveryModeOptions();
  $shipmentKindOptions = OrderService::shipmentKindOptions();
  $bagStatusOptions = BagService::statusOptions();
  $normalizeMethodLabel = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    $compact = strtolower(str_replace(['-', '_'], '', $value));
    if ($compact === 'pix') {
      return 'PIX';
    }
    if ($compact === 'ted' || $compact === 'doc') {
      return strtoupper($compact);
    }
    if ($compact === 'card') {
      return 'Cartão';
    }
    if (strpos($value, '_') !== false || strpos($value, '-') !== false) {
      $value = str_replace(['_', '-'], ' ', strtolower($value));
      $value = ucwords($value);
    }
    return $value;
  };
  $rangeStart = $totalOrders > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalOrders > 0 ? min($totalOrders, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage, $statusFilter, $searchQuery, $columnFilters, $sortKey, $sortDir): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage];
    if ($statusFilter !== '') {
      $query['status'] = $statusFilter;
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
    return 'pedido-list.php?' . http_build_query($query);
  };
  $buildStatusLink = function (string $targetStatus) use ($perPage, $searchQuery, $columnFilters, $sortKey, $sortDir): string {
    $query = ['page' => 1, 'per_page' => $perPage];
    if ($targetStatus !== '') {
      $query['status'] = $targetStatus;
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
    return 'pedido-list.php?' . http_build_query($query);
  };
  $canCreate = userCan('orders.create');
  $canView = userCan('orders.view');
  $canDelete = userCan('orders.delete');
  $canPayment = userCan('orders.payment') || userCan('orders.edit');
  $canFulfillment = userCan('orders.fulfillment') || userCan('orders.edit');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Pedidos</h1>
    <div class="subtitle">Acompanhamento completo dos pedidos.</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($canCreate): ?>
      <a class="btn primary" href="pedido-cadastro.php">Novo pedido</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
  <a class="btn <?php echo $statusFilter === '' ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildStatusLink('')); ?>">Todos</a>
  <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
    <a class="btn <?php echo $statusFilter === $statusKey ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildStatusLink($statusKey)); ?>">
      <?php echo $esc($statusLabel); ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em pedidos" value="<?php echo $esc($searchQuery); ?>">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="page" value="1">
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
    <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="perPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalOrders; ?></span>
  </form>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server" class="orders-table">
    <thead>
      <tr>
        <th class="col-order" data-sort-key="id" aria-sort="none">#</th>
        <th data-sort-key="customer" aria-sort="none">Cliente</th>
        <th class="col-uf" data-sort-key="state" aria-sort="none">UF</th>
        <th class="col-origin" data-sort-key="origin" aria-sort="none">Origem</th>
        <th data-sort-key="status" aria-sort="none">Pedido</th>
        <th class="col-status-flow">Status (Pagamento + Entrega + Sacolinha)</th>
        <th class="col-total" data-sort-key="total" aria-sort="none">Total</th>
        <th class="col-total">Saldo</th>
        <th data-sort-key="items" aria-sort="none">Qtd. itens</th>
        <th data-sort-key="date" aria-sort="none">Data</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th class="col-order"><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilters['filter_id'] ?? ''); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="customer" data-query-param="filter_customer" value="<?php echo $esc($columnFilters['filter_customer'] ?? ''); ?>" placeholder="Filtrar cliente" aria-label="Filtrar cliente"></th>
        <th class="col-uf"><input type="search" data-filter-col="state" data-query-param="filter_state" value="<?php echo $esc($columnFilters['filter_state'] ?? ''); ?>" placeholder="UF" aria-label="Filtrar UF"></th>
        <th class="col-origin"><input type="search" data-filter-col="origin" data-query-param="filter_origin" value="<?php echo $esc($columnFilters['filter_origin'] ?? ''); ?>" placeholder="Filtrar origem" aria-label="Filtrar origem"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilters['filter_status'] ?? ''); ?>" placeholder="Filtrar pedido" aria-label="Filtrar status do pedido"></th>
        <th class="col-status-flow">
          <div class="orders-table__status-filters">
            <label class="orders-table__status-filter-field">
              <span>Pagamento</span>
              <input type="search" data-filter-col="payment" data-query-param="filter_payment" value="<?php echo $esc($columnFilters['filter_payment'] ?? ''); ?>" placeholder="Status do pagamento" aria-label="Filtrar status de pagamento">
            </label>
            <label class="orders-table__status-filter-field">
              <span>Entrega</span>
              <input type="search" data-filter-col="fulfillment" data-query-param="filter_fulfillment" value="<?php echo $esc($columnFilters['filter_fulfillment'] ?? ''); ?>" placeholder="Status da entrega" aria-label="Filtrar status de entrega">
            </label>
          </div>
        </th>
        <th class="col-total"><input type="search" data-filter-col="total" data-query-param="filter_total" value="<?php echo $esc($columnFilters['filter_total'] ?? ''); ?>" placeholder="Filtrar total" aria-label="Filtrar total"></th>
        <th class="col-total"></th>
        <th><input type="search" data-filter-col="items" data-query-param="filter_items" value="<?php echo $esc($columnFilters['filter_items'] ?? ''); ?>" placeholder="Filtrar qtd." aria-label="Filtrar quantidade"></th>
        <th><input type="search" data-filter-col="date" data-query-param="filter_date" value="<?php echo $esc($columnFilters['filter_date'] ?? ''); ?>" placeholder="Filtrar data" aria-label="Filtrar data"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="11">Nenhum pedido encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $orderId = (int) ($row['order_id'] ?? 0);
            $itemsForOrder = $orderItems[$orderId] ?? [];
            $statusKey = (string) ($row['status'] ?? 'open');
            $statusLabel = (string) ($row['order_status_label'] ?? ($statusOptions[$statusKey] ?? $statusKey));
            $paymentStatusKey = (string) ($row['payment_status'] ?? 'none');
            $paymentStatusLabel = (string) ($row['payment_status_label'] ?? ($paymentStatusOptions[$paymentStatusKey] ?? $paymentStatusKey));
            $paymentStatusClass = in_array($paymentStatusKey, ['paid', 'partially_refunded', 'refunded'], true) ? '' : ' order-payment-status--pending';
            $paymentStatusDisplay = $paymentStatusLabel;
            $paymentMethodRaw = trim((string) ($row['payment_method_title'] ?? ($row['payment_method'] ?? '')));
            $paymentMethodLabel = $paymentMethodRaw !== '' ? $normalizeMethodLabel($paymentMethodRaw) : 'Não informado';
            $fulfillmentStatusKey = (string) ($row['fulfillment_status'] ?? 'pending');
            $fulfillmentStatusLabel = (string) ($row['fulfillment_status_label'] ?? ($fulfillmentStatusOptions[$fulfillmentStatusKey] ?? $fulfillmentStatusKey));
            $fulfillmentStatusClass = in_array($fulfillmentStatusKey, ['delivered', 'not_required', 'returned'], true) ? '' : ' order-payment-status--pending';
            $fulfillmentStatusDisplay = $fulfillmentStatusLabel;
            $deliveryModeKey = OrderService::normalizeDeliveryMode((string) ($row['delivery_mode'] ?? 'shipment'));
            $shipmentKindKey = OrderService::normalizeShipmentKind((string) ($row['shipment_kind'] ?? ''), $deliveryModeKey);
            $deliveryMethodLabel = '';
            if ($deliveryModeKey !== 'shipment') {
              $deliveryMethodLabel = (string) ($deliveryModeOptions[$deliveryModeKey] ?? $normalizeMethodLabel($deliveryModeKey));
            } elseif ($shipmentKindKey !== null && $shipmentKindKey !== '') {
              $deliveryMethodLabel = (string) ($shipmentKindOptions[$shipmentKindKey] ?? $normalizeMethodLabel($shipmentKindKey));
            } else {
              $deliveryMethodLabel = (string) ($deliveryModeOptions[$deliveryModeKey] ?? 'Entrega');
            }
            $pendingCount = (int) ($row['pending_count'] ?? 0);
            $totalValue = $row['total_sales'] ?? null;
            $totalLabel = $totalValue !== null ? 'R$ ' . number_format((float) $totalValue, 2, ',', '.') : '-';
            $dueNow = (float) ($row['due_now'] ?? 0);
            $paidTotal = (float) ($row['paid_total'] ?? 0);
            $balanceNow = (float) ($row['balance_due_now'] ?? max(0.0, $dueNow - $paidTotal));
            $dueLater = (float) ($row['due_later'] ?? 0);
            $balanceLabel = 'R$ ' . number_format($balanceNow, 2, ',', '.');
            $itemsCount = (int) ($row['items_count'] ?? 0);
            $dateRaw = (string) ($row['date_created'] ?? '');
            $dateLabel = $dateRaw !== '' ? date('d/m/Y H:i', strtotime($dateRaw)) : '-';
            $salesChannel = trim((string) ($row['sales_channel'] ?? ''));
            $originLabel = $salesChannel !== '' ? $salesChannel : '-';
            $billingName = trim((string) ($row['billing_full_name'] ?? ($row['billing_name'] ?? '')));
            if ($billingName === '') {
              $billingFirst = trim((string) ($row['billing_first_name'] ?? ''));
              $billingLast = trim((string) ($row['billing_last_name'] ?? ''));
              $billingName = trim($billingFirst . ' ' . $billingLast);
            }
            $shippingName = trim((string) ($row['shipping_full_name'] ?? ''));
            if ($shippingName === '') {
              $shippingFirst = trim((string) ($row['shipping_first_name'] ?? ''));
              $shippingLast = trim((string) ($row['shipping_last_name'] ?? ''));
              $shippingName = trim($shippingFirst . ' ' . $shippingLast);
            }
            $customerName = $billingName;
            if ($customerName === '') {
              $customerName = $shippingName;
            }
            if ($customerName === '') {
              $customerName = trim((string) ($row['customer_display_name'] ?? ''));
            }
            if ($customerName === '') {
              $customerName = trim((string) ($row['billing_email'] ?? ''));
            }
            if ($customerName === '') {
              $customerName = trim((string) ($row['shipping_email'] ?? ''));
            }
            if ($customerName === '') {
              $customerName = trim((string) ($row['customer_email'] ?? ''));
            }
            if ($customerName === '') {
              $customerName = 'Convidado';
            }
            $destinationState = trim((string) ($row['shipping_state'] ?? ''));
            if ($destinationState !== '') {
              $destinationState = strtoupper($destinationState);
            } else {
              $destinationState = '-';
            }
            $totalQty = 0;
            foreach ($itemsForOrder as $item) {
              $qty = (int) ($item['product_qty'] ?? 0);
              $totalQty += $qty > 0 ? $qty : 1;
            }
            $returnSummary = $returnSummaryByOrder[$orderId] ?? [];
            $returnedQty = (int) ($returnSummary['returned_qty'] ?? 0);
            $hasReturn = $returnedQty > 0;
            $isTotalReturn = $hasReturn && $totalQty > 0 && $returnedQty >= $totalQty;
            $refundPending = !empty($returnSummary['refund_pending']);
            $refundDone = !empty($returnSummary['refund_done']);
            $bagInfo = $bagStatusByOrder[$orderId] ?? null;
            $bagStatus = $bagInfo ? (string) ($bagInfo['status'] ?? '') : '';
            $hasBag = $bagStatus !== '';
            $bagIsDelivered = $bagStatus === 'entregue';
            $bagIsAccumulating = $bagStatus === 'aberta';
            $showBagStatus = $hasBag || $shipmentKindKey === 'bag_deferred';
            $bagStatusLabel = 'Aguardando abertura';
            $bagStatusClass = ' order-payment-status--pending';
            if ($hasBag) {
              $bagStatusLabel = (string) ($bagStatusOptions[$bagStatus] ?? ucfirst($bagStatus));
              if ($bagStatus === 'entregue' || $bagStatus === 'fechada') {
                $bagStatusClass = '';
              } elseif ($bagStatus === 'aberta' || $bagStatus === 'despachada') {
                $bagStatusClass = ' order-payment-status--bag-open';
              } elseif ($bagStatus === 'cancelada') {
                $bagStatusClass = ' order-payment-status--pending';
              } else {
                $bagStatusClass = ' order-payment-status--neutral';
              }
            }
            $bagStatusLink = $hasBag && !empty($bagInfo['bag_id'])
              ? 'sacolinha-cadastro.php?id=' . (int) $bagInfo['bag_id']
              : '';
            $flowSearch = trim(implode(' ', array_filter([
              $paymentStatusDisplay,
              $paymentMethodLabel,
              $fulfillmentStatusDisplay,
              $deliveryMethodLabel,
              $showBagStatus ? $bagStatusLabel : '',
            ])));
          ?>
          <?php $rowLink = $canView ? 'pedido-cadastro.php?id=' . $orderId : ''; ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td class="col-order" data-value="<?php echo $orderId; ?>">#<?php echo $orderId; ?></td>
            <td data-value="<?php echo $esc($customerName); ?>">
              <?php echo $esc($customerName); ?>
            </td>
            <td class="col-uf" data-value="<?php echo $esc($destinationState); ?>">
              <?php echo $esc($destinationState); ?>
            </td>
            <td class="col-origin" data-value="<?php echo $esc($originLabel); ?>">
              <?php echo $esc($originLabel); ?>
            </td>
            <td data-value="<?php echo $esc($statusLabel); ?>">
              <div><?php echo $esc($statusLabel); ?></div>
              <?php if ($pendingCount > 0): ?>
                <div class="subtitle"><?php echo $pendingCount; ?> pendência(s)</div>
              <?php endif; ?>
              <div class="order-status-icons">
                <?php if ($hasReturn): ?>
                  <?php if ($isTotalReturn): ?>
                    <span class="order-status-icon order-status-icon--return-total" title="Devolução total">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M12 5v2a5 5 0 1 1-4.58 7H5a7 7 0 1 0 7-9v2l3-3-3-3z"/>
                      </svg>
                    </span>
                  <?php else: ?>
                    <span class="order-status-icon order-status-icon--return-partial" title="Devolução parcial">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M12 5v2a5 5 0 1 1-4.58 7H5a7 7 0 1 0 7-9v2l3-3-3-3z M12 12h7v2h-7z"/>
                      </svg>
                      <span class="order-status-icon__label">Devolução parcial</span>
                    </span>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($hasReturn && ($refundPending || $refundDone)): ?>
                  <span class="order-status-icon <?php echo $refundDone ? 'order-status-icon--refund-done' : 'order-status-icon--refund-pending'; ?>" title="<?php echo $refundDone ? 'Reembolso feito' : 'Reembolso pendente'; ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path fill="currentColor" d="<?php echo $refundDone ? 'M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z' : 'M12 8v5l3 3-1.2 1.2L10 13V8h2zm0-6a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16z'; ?>"/>
                    </svg>
                  </span>
                <?php endif; ?>
                <?php if ($hasBag): ?>
                  <a class="order-status-icon order-status-icon--bag<?php echo $bagIsDelivered ? ' order-status-icon--bag-done' : ''; ?><?php echo $bagIsAccumulating ? ' order-status-icon--bag-open' : ''; ?>" href="sacolinha-cadastro.php?id=<?php echo (int) ($bagInfo['bag_id'] ?? 0); ?>" title="<?php echo $bagIsDelivered ? 'Sacolinha entregue' : ($bagIsAccumulating ? 'Sacolinha em andamento' : 'Sacolinha'); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path fill="currentColor" d="M7 7V6a5 5 0 0 1 10 0v1h2a1 1 0 0 1 1 1l-1.5 12a2 2 0 0 1-2 1.8H7.5a2 2 0 0 1-2-1.8L4 8a1 1 0 0 1 1-1h2zm2 0h6V6a3 3 0 0 0-6 0v1z"/>
                    </svg>
                    <?php if ($bagIsDelivered): ?>
                      <span class="order-status-icon__check" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                          <path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                        </svg>
                      </span>
                    <?php endif; ?>
                  </a>
                <?php endif; ?>
              </div>
            </td>
            <td class="col-status-flow" data-value="<?php echo $esc($flowSearch); ?>">
              <div class="order-unified-status">
                <div class="order-unified-status__line order-unified-status__line--payment">
                  <span class="order-unified-status__label">Pagamento</span>
                  <?php if ($canPayment): ?>
                    <button type="button" class="order-payment-status order-inline-status-btn<?php echo $paymentStatusClass; ?>"
                      data-inline-action="inline_payment"
                      data-order-id="<?php echo $orderId; ?>"
                      data-current-status="<?php echo $esc($paymentStatusKey); ?>"
                      data-current-label="<?php echo $esc($paymentStatusDisplay); ?>"
                      data-current-method="<?php echo $esc($paymentMethodRaw); ?>"
                      data-order-label="#<?php echo $orderId; ?> – <?php echo $esc($customerName); ?>"
                      title="Clique para alterar situação de pagamento"
                    ><?php echo $esc($paymentStatusDisplay); ?></button>
                  <?php else: ?>
                    <span class="order-payment-status<?php echo $paymentStatusClass; ?>"><?php echo $esc($paymentStatusDisplay); ?></span>
                  <?php endif; ?>
                  <span class="order-unified-status__method" title="<?php echo $esc($paymentMethodLabel); ?>"><?php echo $esc($paymentMethodLabel); ?></span>
                </div>
                <div class="order-unified-status__line order-unified-status__line--fulfillment">
                  <span class="order-unified-status__label">Entrega</span>
                  <?php if ($canFulfillment): ?>
                    <button type="button" class="order-payment-status order-inline-status-btn<?php echo $fulfillmentStatusClass; ?>"
                      data-inline-action="inline_fulfillment"
                      data-order-id="<?php echo $orderId; ?>"
                      data-current-status="<?php echo $esc($fulfillmentStatusKey); ?>"
                      data-current-label="<?php echo $esc($fulfillmentStatusDisplay); ?>"
                      data-order-label="#<?php echo $orderId; ?> – <?php echo $esc($customerName); ?>"
                      title="Clique para alterar situação de entrega"
                    ><?php echo $esc($fulfillmentStatusDisplay); ?></button>
                  <?php else: ?>
                    <span class="order-payment-status<?php echo $fulfillmentStatusClass; ?>"><?php echo $esc($fulfillmentStatusDisplay); ?></span>
                  <?php endif; ?>
                  <span class="order-unified-status__method" title="<?php echo $esc($deliveryMethodLabel); ?>"><?php echo $esc($deliveryMethodLabel); ?></span>
                </div>
                <?php if ($showBagStatus): ?>
                  <div class="order-unified-status__line order-unified-status__line--bag">
                    <span class="order-unified-status__label">Sacolinha</span>
                    <?php if ($bagStatusLink !== ''): ?>
                      <a class="order-payment-status<?php echo $bagStatusClass; ?> order-unified-status__bag-link" href="<?php echo $esc($bagStatusLink); ?>"><?php echo $esc($bagStatusLabel); ?></a>
                    <?php else: ?>
                      <span class="order-payment-status<?php echo $bagStatusClass; ?>"><?php echo $esc($bagStatusLabel); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <td class="col-total" data-value="<?php echo $esc($totalLabel); ?>"><?php echo $esc($totalLabel); ?></td>
            <td class="col-total" data-value="<?php echo $esc($balanceLabel); ?>">
              <?php echo $esc($balanceLabel); ?>
              <?php if ($dueLater > 0): ?>
                <div class="subtitle">+ R$ <?php echo $esc(number_format($dueLater, 2, ',', '.')); ?> futuro</div>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $itemsCount; ?>"><?php echo $itemsCount; ?></td>
            <td data-value="<?php echo $esc($dateLabel); ?>"><?php echo $esc($dateLabel); ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($canDelete): ?>
                  <form method="post" onsubmit="return confirm('Enviar este pedido para a lixeira?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo $orderId; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
              <?php if (!empty($itemsForOrder)): ?>
                <div class="order-items-preview" aria-hidden="true">
                  <div class="order-items-preview__title">Produtos do pedido #<?php echo $orderId; ?></div>
                  <div class="order-items-preview__list">
                    <?php foreach ($itemsForOrder as $item): ?>
                      <?php
                        $sku = trim((string) ($item['product_sku'] ?? ''));
                        $name = trim((string) ($item['product_name'] ?? ''));
                        $imageSrc = trim((string) ($item['image_src'] ?? ''));
                        $thumbSrc = $imageSrc !== '' ? image_url($imageSrc, 'thumb', 150) : '';
                        $qtyRaw = (int) ($item['product_qty'] ?? 0);
                        $qty = $qtyRaw > 0 ? $qtyRaw : 1;
                        $lineTotal = (float) ($item['product_net_revenue'] ?? 0);
                        $unitPrice = $qty > 0 ? $lineTotal / $qty : $lineTotal;
                        $qtyLabel = $qtyRaw > 0 ? $qtyRaw . ' un.' : '1 un.';
                        $unitLabel = 'R$ ' . number_format($unitPrice, 2, ',', '.');
                        $lineLabel = 'R$ ' . number_format($lineTotal, 2, ',', '.');
                      ?>
                      <div class="order-items-preview__item">
                        <div class="order-item-table-thumb">
                          <?php if ($imageSrc !== ''): ?>
                            <?php $displaySrc = $thumbSrc !== '' ? $thumbSrc : $imageSrc; ?>
                            <img src="<?php echo $esc($displaySrc); ?>" data-thumb-full="<?php echo $esc($imageSrc); ?>" data-thumb-size="44" alt="<?php echo $esc($name !== '' ? $name : 'Produto'); ?>" width="44" height="44">
                          <?php else: ?>
                            <span>Sem foto</span>
                          <?php endif; ?>
                        </div>
                        <div class="order-items-preview__item-main">
                          <div class="order-items-preview__item-name"><?php echo $esc($name); ?></div>
                          <div class="order-items-preview__item-meta">
                            <?php if ($sku !== ''): ?>
                              <span class="order-items-preview__chip">SKU <?php echo $esc($sku); ?></span>
                            <?php endif; ?>
                            <span class="order-items-preview__chip"><?php echo $esc($qtyLabel); ?></span>
                          </div>
                        </div>
                        <div class="order-items-preview__item-price">
                          <div class="order-items-preview__item-unit"><?php echo $esc($unitLabel); ?></div>
                          <div class="order-items-preview__item-total"><?php echo $esc($lineLabel); ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
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

<?php if ($canPayment || $canFulfillment): ?>
<!-- Inline status update modal -->
<div id="inlineStatusModal" class="inline-status-modal" hidden>
  <div class="inline-status-modal__backdrop" data-inline-modal-close></div>
  <div class="inline-status-modal__dialog">
    <div class="inline-status-modal__header">
      <strong id="inlineStatusModalTitle">Alterar status</strong>
      <button type="button" class="inline-status-modal__close" data-inline-modal-close aria-label="Fechar">&times;</button>
    </div>
    <form id="inlineStatusForm" method="post" action="pedido-list.php">
      <input type="hidden" name="inline_action" id="inlineStatusAction" value="">
      <input type="hidden" name="inline_order_id" id="inlineStatusOrderId" value="">
      <input type="hidden" name="_page" value="<?php echo (int) $page; ?>">
      <input type="hidden" name="_per_page" value="<?php echo (int) $perPage; ?>">
      <input type="hidden" name="_status" value="<?php echo $esc($statusFilter); ?>">
      <input type="hidden" name="_q" value="<?php echo $esc($searchQuery); ?>">
      <input type="hidden" name="_sort_key" value="<?php echo $esc($sortKey); ?>">
      <input type="hidden" name="_sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
      <div class="inline-status-modal__body">
        <div class="inline-status-modal__order-label" id="inlineStatusOrderLabel"></div>
        <div class="inline-status-modal__current">
          Status atual: <strong id="inlineStatusCurrentLabel"></strong>
        </div>
        <div class="field" id="inlinePaymentStatusField">
          <label for="inlinePaymentStatus">Novo status de pagamento</label>
          <select id="inlinePaymentStatus" name="payment_status">
            <?php foreach ($paymentStatusOptions as $psKey => $psLabel): ?>
              <option value="<?php echo $esc($psKey); ?>"><?php echo $esc($psLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="inlinePaymentMethodField">
          <label for="inlinePaymentMethod">Método de pagamento/recebimento</label>
          <select id="inlinePaymentMethod" name="payment_method_title">
            <option value="">Selecione</option>
            <?php foreach ($paymentMethodOptions as $method): ?>
              <?php $methodName = trim((string) ($method['name'] ?? '')); ?>
              <?php if ($methodName === '') { continue; } ?>
              <option value="<?php echo $esc($methodName); ?>"><?php echo $esc($methodName); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="inline-status-modal__hint" id="inlinePaymentMethodHint">Obrigatório quando status for "Pago".</div>
        </div>
        <div class="field" id="inlineFulfillmentStatusField">
          <label for="inlineFulfillmentStatus">Novo status de entrega</label>
          <select id="inlineFulfillmentStatus" name="fulfillment_status">
            <?php foreach ($fulfillmentStatusOptions as $fsKey => $fsLabel): ?>
              <option value="<?php echo $esc($fsKey); ?>"><?php echo $esc($fsLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="inline-status-modal__footer">
        <button type="button" class="btn ghost" data-inline-modal-close>Cancelar</button>
        <button type="submit" class="btn primary" id="inlineStatusConfirmBtn">Confirmar alteração</button>
      </div>
    </form>
  </div>
</div>

<style>
  .order-inline-status-btn {
    cursor: pointer;
    border: 1px dashed rgba(148, 163, 184, 0.45);
    padding: 2px 8px;
    border-radius: 8px;
    font: inherit;
    font-size: 12px;
    line-height: 1.4;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .order-inline-status-btn:hover {
    border-color: #64748b;
    box-shadow: 0 0 0 1px rgba(100, 116, 139, 0.16);
  }
  .inline-status-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .inline-status-modal[hidden] {
    display: none !important;
  }
  .inline-status-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.35);
  }
  .inline-status-modal__dialog {
    position: relative;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    width: 100%;
    max-width: 420px;
    margin: 16px;
    overflow: hidden;
  }
  .inline-status-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--line, #e5e7eb);
  }
  .inline-status-modal__header strong {
    font-size: 16px;
  }
  .inline-status-modal__close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: var(--muted, #888);
    padding: 0 4px;
    line-height: 1;
  }
  .inline-status-modal__close:hover {
    color: var(--text, #111);
  }
  .inline-status-modal__body {
    padding: 16px 20px;
  }
  .inline-status-modal__order-label {
    font-weight: 600;
    margin-bottom: 8px;
  }
  .inline-status-modal__current {
    color: var(--muted, #666);
    font-size: 13px;
    margin-bottom: 14px;
  }
  .inline-status-modal__hint {
    margin-top: 6px;
    color: var(--muted, #666);
    font-size: 12px;
  }
  .inline-status-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px 16px;
    border-top: 1px solid var(--line, #e5e7eb);
  }
</style>

<script>
(function() {
  const modal = document.getElementById('inlineStatusModal');
  const form = document.getElementById('inlineStatusForm');
  const actionInput = document.getElementById('inlineStatusAction');
  const orderIdInput = document.getElementById('inlineStatusOrderId');
  const titleEl = document.getElementById('inlineStatusModalTitle');
  const orderLabelEl = document.getElementById('inlineStatusOrderLabel');
  const currentLabelEl = document.getElementById('inlineStatusCurrentLabel');
  const paymentField = document.getElementById('inlinePaymentStatusField');
  const paymentMethodField = document.getElementById('inlinePaymentMethodField');
  const fulfillmentField = document.getElementById('inlineFulfillmentStatusField');
  const paymentSelect = document.getElementById('inlinePaymentStatus');
  const paymentMethodSelect = document.getElementById('inlinePaymentMethod');
  const paymentMethodHint = document.getElementById('inlinePaymentMethodHint');
  const fulfillmentSelect = document.getElementById('inlineFulfillmentStatus');
  const confirmBtn = document.getElementById('inlineStatusConfirmBtn');

  if (!modal || !form) return;

  function applyInlinePaymentMethodRule() {
    if (!paymentSelect || !paymentMethodSelect) return true;
    const mustInformMethod = paymentSelect.value === 'paid';
    paymentMethodSelect.required = mustInformMethod;
    if (mustInformMethod && !paymentMethodSelect.value) {
      paymentMethodSelect.setCustomValidity('Selecione o método de pagamento/recebimento para marcar como Pago.');
      if (paymentMethodHint) {
        paymentMethodHint.textContent = 'Selecione o método para confirmar o status "Pago".';
      }
      return false;
    }

    paymentMethodSelect.setCustomValidity('');
    if (paymentMethodHint) {
      paymentMethodHint.textContent = 'Obrigatório quando status for "Pago".';
    }
    return true;
  }

  if (paymentSelect) {
    paymentSelect.addEventListener('change', applyInlinePaymentMethodRule);
  }
  if (paymentMethodSelect) {
    paymentMethodSelect.addEventListener('change', applyInlinePaymentMethodRule);
  }

  function openModal(btn) {
    const action = btn.dataset.inlineAction;
    const orderId = btn.dataset.orderId;
    const currentStatus = btn.dataset.currentStatus;
    const currentLabel = btn.dataset.currentLabel;
    const currentMethod = btn.dataset.currentMethod || '';
    const orderLabel = btn.dataset.orderLabel || ('#' + orderId);

    actionInput.value = action;
    orderIdInput.value = orderId;
    orderLabelEl.textContent = 'Pedido ' + orderLabel;
    currentLabelEl.textContent = currentLabel;

    if (action === 'inline_payment') {
      titleEl.textContent = 'Alterar pagamento';
      paymentField.hidden = false;
      paymentMethodField.hidden = false;
      fulfillmentField.hidden = true;
      paymentSelect.value = currentStatus;
      paymentSelect.name = 'payment_status';
      if (paymentMethodSelect) {
        paymentMethodSelect.value = currentMethod;
        paymentMethodSelect.name = 'payment_method_title';
      }
      fulfillmentSelect.name = '';
      confirmBtn.textContent = 'Confirmar pagamento';
      applyInlinePaymentMethodRule();
    } else {
      titleEl.textContent = 'Alterar entrega';
      paymentField.hidden = true;
      paymentMethodField.hidden = true;
      fulfillmentField.hidden = false;
      fulfillmentSelect.value = currentStatus;
      fulfillmentSelect.name = 'fulfillment_status';
      paymentSelect.name = '';
      if (paymentMethodSelect) {
        paymentMethodSelect.name = '';
        paymentMethodSelect.required = false;
        paymentMethodSelect.setCustomValidity('');
      }
      confirmBtn.textContent = 'Confirmar entrega';
    }

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    const activeSelect = action === 'inline_payment' ? paymentSelect : fulfillmentSelect;
    setTimeout(() => activeSelect.focus(), 60);
  }

  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  // Delegate click on all inline status buttons
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-inline-action]');
    if (btn) {
      e.preventDefault();
      e.stopPropagation();
      openModal(btn);
      return;
    }
    const closeBtn = e.target.closest('[data-inline-modal-close]');
    if (closeBtn) {
      e.preventDefault();
      closeModal();
    }
  });

  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });

  // Confirm form submit
  form.addEventListener('submit', function(e) {
    const action = actionInput.value;
    const orderId = orderIdInput.value;
    if (action === 'inline_payment' && !applyInlinePaymentMethodRule()) {
      e.preventDefault();
      if (paymentMethodSelect) {
        paymentMethodSelect.reportValidity();
      }
      return;
    }
    const select = action === 'inline_payment' ? paymentSelect : fulfillmentSelect;
    const newLabel = select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent.trim() : select.value;
    const orderLabel = orderLabelEl.textContent || ('#' + orderId);
    const typeLabel = action === 'inline_payment' ? 'pagamento' : 'entrega';
    let confirmMessage = 'Confirmar alteração de ' + typeLabel + ' para "' + newLabel + '" no ' + orderLabel + '?';
    if (action === 'inline_payment' && paymentSelect && paymentSelect.value === 'paid' && paymentMethodSelect) {
      confirmMessage += '\nMétodo selecionado: ' + paymentMethodSelect.value + '.';
    }
    if (!confirm(confirmMessage)) {
      e.preventDefault();
    }
  });
})();
</script>
<?php endif; ?>
