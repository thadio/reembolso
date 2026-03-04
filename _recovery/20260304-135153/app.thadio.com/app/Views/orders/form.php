<?php
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $formData */
/** @var array|null $orderSummary */
/** @var array $items */
/** @var array $itemStocks */
/** @var array $productOptions */
/** @var array $customerOptions */
/** @var string $step */
/** @var array $statusOptions */
/** @var array $paymentStatusOptions */
/** @var array $fulfillmentStatusOptions */
/** @var array $salesChannelOptions */
/** @var array $deliveryModeOptions */
/** @var array $shipmentKindOptions */
/** @var array $carrierOptions */
/** @var array $paymentMethodOptions */
/** @var array $bankAccountOptions */
/** @var array $paymentTerminalOptions */
/** @var array $voucherAccountOptions */
/** @var float $openingFeeDefault */
/** @var array|null $bagContext */
/** @var array $orderReturns */
/** @var array $orderReturnStatusOptions */
/** @var array $orderReturnRefundStatusOptions */
/** @var array|null $consignmentPreview */
/** @var bool $fullEdit */
/** @var array $originalLineItemIds */
/** @var callable $esc */
?>
<?php
  use App\Support\Image;

  $editing = $editing ?? false;
  $fullEdit = ($fullEdit ?? false) && $editing;
  $showEditForm = !$editing || $fullEdit;
  $canCreate = userCan('orders.create');
  $canEdit = userCan('orders.edit');
  $canPayment = userCan('orders.payment');
  $canFulfillment = userCan('orders.fulfillment') || userCan('orders.edit');
  $canCancel = userCan('orders.cancel');
  $orderReturns = $orderReturns ?? [];
  $orderReturnStatusOptions = $orderReturnStatusOptions ?? [];
  $orderReturnRefundStatusOptions = $orderReturnRefundStatusOptions ?? [];
  $orderReturnReturnMethodOptions = $orderReturnReturnMethodOptions ?? [];
  $orderReturnRefundMethodOptions = $orderReturnRefundMethodOptions ?? [];
  $orderReturnItems = $orderReturnItems ?? [];
  $orderReturnAlreadyReturned = $orderReturnAlreadyReturned ?? [];
  $orderReturnAvailableMap = $orderReturnAvailableMap ?? [];
  $orderReturnFormData = $orderReturnFormData ?? [];
  $orderReturnItemsInput = $orderReturnItemsInput ?? [];
  $orderReturnErrors = $orderReturnErrors ?? [];
  $orderReturnSuccess = $orderReturnSuccess ?? '';
  $consignmentPreview = $consignmentPreview ?? null;
  $originalLineItemIds = $originalLineItemIds ?? [];
  $returnShippingTotal = $orderSummary ? (float) ($orderSummary['shipping_total'] ?? 0) : 0.0;
  $returnTaxTotal = $orderSummary ? (float) ($orderSummary['total_tax'] ?? 0) : 0.0;
  $orderReturnCount = is_array($orderReturns) ? count($orderReturns) : 0;
  $canManageReturns = userCan('order_returns.view');
  $canCancelReturns = userCan('order_returns.edit');
  $itemsInput = $formData['items'] ?? [];
  if (!is_array($itemsInput)) {
      $itemsInput = [];
  }
  $paymentsInput = $formData['payments'] ?? [];
  if (!is_array($paymentsInput)) {
      $paymentsInput = [];
  }
  $customerName = trim((string) ($formData['billing_full_name'] ?? ''));
  if ($customerName === '') {
      $customerName = trim((string) ($formData['shipping_full_name'] ?? ''));
  }
  $customerEmail = trim((string) ($formData['billing_email'] ?? ''));
  if ($customerEmail === '') {
      $customerEmail = trim((string) ($formData['shipping_email'] ?? ''));
  }
  if ($customerName === '') {
      $customerId = (int) ($formData['pessoa_id'] ?? 0);
      $customerName = $customerId > 0 ? 'Cliente #' . $customerId : 'Convidado';
  }
  $salesChannelOptions = $salesChannelOptions ?? [];
  $currentSalesChannel = trim((string) ($formData['sales_channel'] ?? ''));
  $hasInvalidSalesChannel = $currentSalesChannel !== '' && !in_array($currentSalesChannel, $salesChannelOptions, true);
  $deliveryModeOptions = $deliveryModeOptions ?? [];
  $shipmentKindOptions = $shipmentKindOptions ?? [];
  $carrierOptions = $carrierOptions ?? [];
  $paymentMethodOptions = $paymentMethodOptions ?? [];
  $bankAccountOptions = $bankAccountOptions ?? [];
  $paymentTerminalOptions = $paymentTerminalOptions ?? [];
  $voucherAccountOptions = $voucherAccountOptions ?? [];
  $openingFeeDefault = isset($openingFeeDefault) ? (float) $openingFeeDefault : 35.0;
  $currentDeliveryMode = trim((string) ($formData['delivery_mode'] ?? 'shipment'));
  if (!array_key_exists($currentDeliveryMode, $deliveryModeOptions)) {
      $currentDeliveryMode = 'shipment';
  }
  $currentShipmentKind = trim((string) ($formData['shipment_kind'] ?? ''));
  if ($currentDeliveryMode === 'shipment') {
      if ($currentShipmentKind === '' || !array_key_exists($currentShipmentKind, $shipmentKindOptions)) {
          $currentShipmentKind = 'tracked';
      }
  } else {
      $currentShipmentKind = '';
  }
  $bagContext = $bagContext ?? null;

  $layoutOptions = [
      1 => 'Cartões',
      2 => 'Colunas',
      3 => 'Painel',
      4 => 'Linha',
  ];
  $defaultLayout = $editing ? 2 : 1;
  $currentLayout = isset($_GET['layout']) ? (int) $_GET['layout'] : $defaultLayout;
  if (!isset($layoutOptions[$currentLayout])) {
      $currentLayout = $defaultLayout;
  }

  $editToggleLink = '';
  if ($editing) {
      $query = $_GET;
      if ($fullEdit) {
          unset($query['edit']);
      } else {
          $query['edit'] = '1';
      }
      $editToggleLink = 'pedido-cadastro.php' . (!empty($query) ? '?' . http_build_query($query) : '');
  }
  $formAction = 'pedido-cadastro.php';
  if ($editing) {
      $query = $_GET;
      $query['id'] = (string) ($formData['id'] ?? '');
      if ($fullEdit) {
          $query['edit'] = '1';
      }
      $formAction = 'pedido-cadastro.php' . (!empty($query) ? '?' . http_build_query($query) : '');
  }

  $orderTitle = $editing ? $customerName : 'Novo pedido';
  $orderEmailLabel = $editing ? ($customerEmail !== '' ? $customerEmail : '-') : '';
  $orderIdLabel = $editing ? 'Pedido #' . (string) ($formData['id'] ?? '') : '';

  $deliveryLabel = '-';
  $salesChannelLabel = '-';
  $shippingTotalLabel = 'R$ 0,00';
  $orderTotalLabel = '';
  $orderShippingLabel = '';
  $orderDateCreated = '-';
  $orderDatePaid = '-';
  $orderDateCompleted = '-';
  $billingAddress = ['has_data' => false];
  $shippingAddress = ['has_data' => false];
  $lifecycleSnapshot = isset($formData['lifecycle_snapshot']) && is_array($formData['lifecycle_snapshot'])
    ? $formData['lifecycle_snapshot']
    : [];
  $lifecycleTotals = isset($lifecycleSnapshot['totals']) && is_array($lifecycleSnapshot['totals'])
    ? $lifecycleSnapshot['totals']
    : [];
  $lifecyclePending = isset($lifecycleSnapshot['pending']) && is_array($lifecycleSnapshot['pending'])
    ? $lifecycleSnapshot['pending']
    : [];
  $lifecycleTimeline = isset($lifecycleSnapshot['timeline']) && is_array($lifecycleSnapshot['timeline'])
    ? $lifecycleSnapshot['timeline']
    : [];
  $lifecycleOrderStatusLabel = '';
  $lifecyclePaymentStatusLabel = '';
  $lifecycleFulfillmentStatusLabel = '';
  $lifecycleDueNowLabel = 'R$ 0,00';
  $lifecyclePaidLabel = 'R$ 0,00';
  $lifecycleBalanceLabel = 'R$ 0,00';
  $lifecycleDueLaterLabel = 'R$ 0,00';
  $lifecyclePendingCount = 0;

  if ($editing) {
      $deliveryModeKey = (string) ($formData['delivery_mode'] ?? '');
      $shipmentKindKey = (string) ($formData['shipment_kind'] ?? '');
      if ($deliveryModeKey !== '' && isset($deliveryModeOptions[$deliveryModeKey])) {
          $deliveryLabel = (string) $deliveryModeOptions[$deliveryModeKey];
      }
      if ($deliveryModeKey === 'shipment' && $shipmentKindKey !== '' && isset($shipmentKindOptions[$shipmentKindKey])) {
          $deliveryLabel .= ' - ' . (string) $shipmentKindOptions[$shipmentKindKey];
      }
      $salesChannelLabel = $currentSalesChannel !== '' ? $currentSalesChannel : '-';
      $shippingTotalLabel = $formData['shipping_total'] !== '' ? 'R$ ' . number_format((float) $formData['shipping_total'], 2, ',', '.') : 'R$ 0,00';

      if ($orderSummary) {
          $orderTotalLabel = 'R$ ' . number_format((float) ($orderSummary['total'] ?? 0), 2, ',', '.');
          $orderShippingLabel = 'R$ ' . number_format((float) ($orderSummary['shipping_total'] ?? 0), 2, ',', '.');
          $orderDateCreated = $orderSummary['date_created'] ?? '-';
          $orderDatePaid = $orderSummary['date_paid'] ?? '-';
          $orderDateCompleted = $orderSummary['date_completed'] ?? '-';
      }

      $lifecycleOrderStatusLabel = (string) ($lifecycleSnapshot['labels']['order_status'] ?? ($statusOptions[$formData['status']] ?? $formData['status']));
      $lifecyclePaymentStatusLabel = (string) ($lifecycleSnapshot['labels']['payment_status'] ?? ($paymentStatusOptions[$formData['payment_status']] ?? $formData['payment_status']));
      $lifecycleFulfillmentStatusLabel = (string) ($lifecycleSnapshot['labels']['fulfillment_status'] ?? ($fulfillmentStatusOptions[$formData['fulfillment_status']] ?? $formData['fulfillment_status']));
      $lifecycleDueNowLabel = 'R$ ' . number_format((float) ($lifecycleTotals['due_now'] ?? 0), 2, ',', '.');
      $lifecyclePaidLabel = 'R$ ' . number_format((float) ($lifecycleTotals['net_paid'] ?? 0), 2, ',', '.');
      $lifecycleBalanceLabel = 'R$ ' . number_format((float) ($lifecycleTotals['balance_due_now'] ?? 0), 2, ',', '.');
      $lifecycleDueLaterLabel = 'R$ ' . number_format((float) ($lifecycleTotals['due_later'] ?? 0), 2, ',', '.');
      $lifecyclePendingCount = count($lifecyclePending);

      $addressFrom = function (string $prefix) use ($formData): array {
          $get = function (string $key) use ($formData, $prefix): string {
              return trim((string) ($formData[$prefix . $key] ?? ''));
          };
          $address1 = $get('address_1');
          $number = $get('number');
          $addressLine = trim(implode(', ', array_filter([$address1, $number])));
          $address2 = $get('address_2');
          $neighborhood = $get('neighborhood');
          $city = $get('city');
          $state = $get('state');
          $cityState = trim(implode(' / ', array_filter([$city, $state])));
          $postcode = $get('postcode');
          $location = trim(implode(' - ', array_filter([$cityState, $postcode !== '' ? 'CEP ' . $postcode : ''])));
          $country = $get('country');
          $phone = $get('phone');
          $hasData = $phone !== '' || $addressLine !== '' || $address2 !== '' || $neighborhood !== '' || $location !== '' || $country !== '';
          return [
              'phone' => $phone,
              'address_line' => $addressLine,
              'address_2' => $address2,
              'neighborhood' => $neighborhood,
              'location' => $location,
              'country' => $country,
              'has_data' => $hasData,
          ];
      };
      $billingAddress = $addressFrom('billing_');
      $shippingAddress = $addressFrom('shipping_');
  }

  $pdvChannelLabel = 'Loja Física';
  $pdvDeliveryLabel = 'Imediata em mãos';
  $orderMode = strtolower(trim((string) ($formData['order_mode'] ?? '')));
  if ($orderMode === '') {
    $isPdvLike = (strcasecmp($currentSalesChannel, $pdvChannelLabel) === 0)
      && (
          (string) ($formData['delivery_mode'] ?? '') === 'immediate_in_hand'
        )
        && ((float) ($formData['shipping_total'] ?? 0) === 0.0);
      $orderMode = $isPdvLike ? 'pdv' : 'online';
  }

  $showNewOrderButton = $success !== '' && stripos($success, 'pedido criado') !== false;
  $renderAlerts = function () use ($success, $errors, $esc, $showNewOrderButton, $canCreate): void {
      if ($success) {
        echo '<div class="alert success">' . $esc($success) . '</div>';
        if ($showNewOrderButton && $canCreate) {
          echo '<div style="margin-top:8px;"><a class="btn ghost" href="pedido-cadastro.php">Cadastrar novo pedido</a></div>';
        }
        return;
      }
      if (!empty($errors)) {
          echo '<div class="alert error">' . $esc(implode(' ', $errors)) . '</div>';
      }
  };

  $renderAddressLines = function (array $address) use ($esc): void {
      if (empty($address['has_data'])) {
          echo '<div class="order-layout-sub">Sem dados de endereço.</div>';
          return;
      }
      if (!empty($address['phone'])) {
          echo '<div class="order-layout-sub">Telefone: ' . $esc($address['phone']) . '</div>';
      }
      if (!empty($address['address_line'])) {
          echo '<div class="order-layout-value">' . $esc($address['address_line']) . '</div>';
      }
      if (!empty($address['address_2'])) {
          echo '<div class="order-layout-sub">' . $esc($address['address_2']) . '</div>';
      }
      if (!empty($address['neighborhood'])) {
          echo '<div class="order-layout-sub">' . $esc($address['neighborhood']) . '</div>';
      }
      if (!empty($address['location'])) {
          echo '<div class="order-layout-sub">' . $esc($address['location']) . '</div>';
      }
      if (!empty($address['country'])) {
          echo '<div class="order-layout-sub">' . $esc($address['country']) . '</div>';
      }
  };
?>

<?php if ($editing && $canEdit): ?>
  <div style="display:flex;justify-content:flex-end;margin:6px 0 12px;gap:8px;flex-wrap:wrap;">
    <a class="btn <?php echo $fullEdit ? 'ghost' : 'primary'; ?>" href="<?php echo $esc($editToggleLink); ?>">
      <?php echo $fullEdit ? 'Voltar ao acompanhamento' : 'Editar pedido'; ?>
    </a>
  </div>
<?php endif; ?>

<?php if ($currentLayout === 1): ?>
  <div class="order-hero">
    <div class="order-hero__title">
      <h1 class="order-hero__h1"><?php echo $editing ? $esc($customerName) : 'Novo pedido'; ?></h1>
      <?php if ($editing): ?>
        <div class="subtitle"><?php echo $customerEmail !== '' ? $esc($customerEmail) : '-'; ?></div>
        <div class="subtitle">Pedido #<?php echo $esc((string) $formData['id']); ?></div>
      <?php endif; ?>
    </div>
    <div class="order-hero__actions"></div>
  </div>

  <?php $renderAlerts(); ?>

  <?php if ($editing && $orderSummary): ?>
    <div class="grid" style="margin:12px 0;">
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Total</strong>
        <div style="font-size:20px;margin-top:6px;">R$ <?php echo $esc(number_format((float) ($orderSummary['total'] ?? 0), 2, ',', '.')); ?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:6px;">Frete: R$ <?php echo $esc(number_format((float) ($orderSummary['shipping_total'] ?? 0), 2, ',', '.')); ?></div>
      </div>
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Datas</strong>
        <div style="margin-top:6px;font-size:13px;color:var(--muted);">Criado: <?php echo $esc($orderSummary['date_created'] ?? '-'); ?></div>
        <div style="margin-top:6px;font-size:13px;color:var(--muted);">Pago: <?php echo $esc($orderSummary['date_paid'] ?? '-'); ?></div>
        <div style="margin-top:6px;font-size:13px;color:var(--muted);">Concluído: <?php echo $esc($orderSummary['date_completed'] ?? '-'); ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($editing): ?>
    <div class="grid" style="margin:12px 0;">
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Canal de venda</strong>
        <div style="margin-top:6px;"><?php echo $esc($salesChannelLabel); ?></div>
      </div>
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Entrega</strong>
        <div style="margin-top:6px;"><?php echo $esc($deliveryLabel); ?></div>
      </div>
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Frete (R$)</strong>
        <div style="margin-top:6px;"><?php echo $esc($shippingTotalLabel); ?></div>
      </div>
    </div>
    <div class="grid" style="margin:12px 0;">
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Endereço da cliente</strong>
        <?php if ($billingAddress['has_data']): ?>
          <?php if ($billingAddress['phone'] !== ''): ?>
            <div style="margin-top:6px;font-size:13px;color:var(--muted);">Telefone: <?php echo $esc($billingAddress['phone']); ?></div>
          <?php endif; ?>
          <?php if ($billingAddress['address_line'] !== ''): ?>
            <div style="margin-top:6px;"><?php echo $esc($billingAddress['address_line']); ?></div>
          <?php endif; ?>
          <?php if ($billingAddress['address_2'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($billingAddress['address_2']); ?></div>
          <?php endif; ?>
          <?php if ($billingAddress['neighborhood'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($billingAddress['neighborhood']); ?></div>
          <?php endif; ?>
          <?php if ($billingAddress['location'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($billingAddress['location']); ?></div>
          <?php endif; ?>
          <?php if ($billingAddress['country'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($billingAddress['country']); ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div style="margin-top:6px;font-size:13px;color:var(--muted);">Sem dados de endereço.</div>
        <?php endif; ?>
      </div>
      <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
        <strong>Endereço de envio</strong>
        <?php if ($shippingAddress['has_data']): ?>
          <?php if ($shippingAddress['phone'] !== ''): ?>
            <div style="margin-top:6px;font-size:13px;color:var(--muted);">Telefone: <?php echo $esc($shippingAddress['phone']); ?></div>
          <?php endif; ?>
          <?php if ($shippingAddress['address_line'] !== ''): ?>
            <div style="margin-top:6px;"><?php echo $esc($shippingAddress['address_line']); ?></div>
          <?php endif; ?>
          <?php if ($shippingAddress['address_2'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($shippingAddress['address_2']); ?></div>
          <?php endif; ?>
          <?php if ($shippingAddress['neighborhood'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($shippingAddress['neighborhood']); ?></div>
          <?php endif; ?>
          <?php if ($shippingAddress['location'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($shippingAddress['location']); ?></div>
          <?php endif; ?>
          <?php if ($shippingAddress['country'] !== ''): ?>
            <div style="margin-top:4px;"><?php echo $esc($shippingAddress['country']); ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div style="margin-top:6px;font-size:13px;color:var(--muted);">Sem dados de endereço.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php elseif ($currentLayout === 2): ?>
  <div class="order-layout-header order-layout-header--with-switch">
    <div class="order-layout-header__main">
      <h1 class="order-layout-title"><?php echo $esc($orderTitle); ?></h1>
      <?php if ($editing): ?>
        <div class="order-layout-meta"><?php echo $esc($orderEmailLabel); ?></div>
        <div class="order-layout-meta"><?php echo $esc($orderIdLabel); ?></div>
      <?php endif; ?>
    </div>
    <div class="order-layout-header__side">
      <?php if ($editing): ?>
        <div class="order-layout-chips">
          <span class="order-layout-chip">Canal: <?php echo $esc($salesChannelLabel); ?></span>
          <span class="order-layout-chip">Entrega: <?php echo $esc($deliveryLabel); ?></span>
          <span class="order-layout-chip">Frete: <?php echo $esc($shippingTotalLabel); ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php $renderAlerts(); ?>

  <?php if ($editing && $orderSummary): ?>
    <div class="order-layout-metrics" style="margin:8px 0;">
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Total</div>
        <div class="order-layout-value"><?php echo $esc($orderTotalLabel); ?></div>
        <div class="order-layout-sub">Frete <?php echo $esc($orderShippingLabel); ?></div>
      </div>
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Datas</div>
        <div class="order-layout-sub">Criado: <?php echo $esc($orderDateCreated); ?></div>
        <div class="order-layout-sub">Pago: <?php echo $esc($orderDatePaid); ?></div>
        <div class="order-layout-sub">Concluído: <?php echo $esc($orderDateCompleted); ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($editing): ?>
    <div class="order-layout-grid" style="margin:8px 0;">
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Endereço da cliente</div>
        <?php $renderAddressLines($billingAddress); ?>
      </div>
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Endereço de envio</div>
        <?php $renderAddressLines($shippingAddress); ?>
      </div>
    </div>
  <?php endif; ?>
<?php elseif ($currentLayout === 3): ?>
  <div class="order-layout-header order-layout-header--with-switch">
    <div class="order-layout-header__main">
      <h1 class="order-layout-title"><?php echo $esc($orderTitle); ?></h1>
      <?php if ($editing): ?>
        <div class="order-layout-meta"><?php echo $esc($orderEmailLabel); ?></div>
        <div class="order-layout-meta"><?php echo $esc($orderIdLabel); ?></div>
      <?php endif; ?>
    </div>
    <div class="order-layout-header__side">
      <?php if ($editing): ?>
        <div class="order-layout-chips"></div>
      <?php endif; ?>
    </div>
  </div>

  <?php $renderAlerts(); ?>

  <?php if ($editing): ?>
    <div class="order-layout-panel" style="margin:8px 0;">
      <div>
        <div class="order-layout-grid">
          <div class="order-layout-card order-layout-card--tight">
            <div class="order-layout-label">Endereço da cliente</div>
            <?php $renderAddressLines($billingAddress); ?>
          </div>
          <div class="order-layout-card order-layout-card--tight">
            <div class="order-layout-label">Endereço de envio</div>
            <?php $renderAddressLines($shippingAddress); ?>
          </div>
        </div>
        <div class="order-layout-grid" style="margin-top:8px;">
          <div class="order-layout-card order-layout-card--tight">
            <div class="order-layout-label">Canal de venda</div>
            <div class="order-layout-value"><?php echo $esc($salesChannelLabel); ?></div>
          </div>
          <div class="order-layout-card order-layout-card--tight">
            <div class="order-layout-label">Entrega</div>
            <div class="order-layout-value"><?php echo $esc($deliveryLabel); ?></div>
          </div>
          <div class="order-layout-card order-layout-card--tight">
            <div class="order-layout-label">Frete</div>
            <div class="order-layout-value"><?php echo $esc($shippingTotalLabel); ?></div>
          </div>
        </div>
      </div>
      <div>
        <?php if ($orderSummary): ?>
          <div class="order-layout-card" style="margin-bottom:8px;">
            <div class="order-layout-label">Total</div>
            <div class="order-layout-value"><?php echo $esc($orderTotalLabel); ?></div>
            <div class="order-layout-sub">Frete <?php echo $esc($orderShippingLabel); ?></div>
          </div>
          <div class="order-layout-card">
            <div class="order-layout-label">Datas</div>
            <div class="order-layout-sub">Criado: <?php echo $esc($orderDateCreated); ?></div>
            <div class="order-layout-sub">Pago: <?php echo $esc($orderDatePaid); ?></div>
            <div class="order-layout-sub">Concluído: <?php echo $esc($orderDateCompleted); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="order-layout-header order-layout-header--with-switch">
    <div class="order-layout-header__main">
      <h1 class="order-layout-title"><?php echo $esc($orderTitle); ?></h1>
      <?php if ($editing): ?>
        <div class="order-layout-meta"><?php echo $esc($orderEmailLabel); ?></div>
        <div class="order-layout-meta"><?php echo $esc($orderIdLabel); ?></div>
      <?php endif; ?>
    </div>
    <div class="order-layout-header__side"></div>
  </div>

  <?php $renderAlerts(); ?>

  <?php if ($editing): ?>
    <div class="order-layout-grid" style="margin:8px 0;">
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Resumo</div>
        <div class="order-layout-list">
          <?php if ($orderSummary): ?>
            <div class="order-layout-list-item">
              <span>Total</span>
              <strong><?php echo $esc($orderTotalLabel); ?></strong>
            </div>
            <div class="order-layout-list-item">
              <span>Frete</span>
              <strong><?php echo $esc($orderShippingLabel); ?></strong>
            </div>
            <div class="order-layout-list-item">
              <span>Criado</span>
              <strong><?php echo $esc($orderDateCreated); ?></strong>
            </div>
            <div class="order-layout-list-item">
              <span>Pago</span>
              <strong><?php echo $esc($orderDatePaid); ?></strong>
            </div>
            <div class="order-layout-list-item">
              <span>Concluído</span>
              <strong><?php echo $esc($orderDateCompleted); ?></strong>
            </div>
          <?php endif; ?>
          <div class="order-layout-list-item">
            <span>Canal</span>
            <strong><?php echo $esc($salesChannelLabel); ?></strong>
          </div>
          <div class="order-layout-list-item">
            <span>Entrega</span>
            <strong><?php echo $esc($deliveryLabel); ?></strong>
          </div>
          <div class="order-layout-list-item">
            <span>Frete (pedido)</span>
            <strong><?php echo $esc($shippingTotalLabel); ?></strong>
          </div>
        </div>
      </div>
    </div>
    <div class="order-layout-grid" style="margin:8px 0;">
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Endereço da cliente</div>
        <?php $renderAddressLines($billingAddress); ?>
      </div>
      <div class="order-layout-card order-layout-card--tight">
        <div class="order-layout-label">Endereço de envio</div>
        <?php $renderAddressLines($shippingAddress); ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($editing && $canManageReturns): ?>
  <div style="margin:12px 0;padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div>
        <strong>Devoluções</strong>
        <div class="subtitle">Produtos devolvidos, créditos e reembolsos.</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn ghost" type="button" data-return-open>
          <?php echo $orderReturnCount > 0 ? 'Nova devolução' : 'Registrar devolução'; ?>
        </button>
      </div>
    </div>
    <?php if ($orderReturnSuccess): ?>
      <div class="alert success" style="margin-top:10px;"><?php echo $esc($orderReturnSuccess); ?></div>
    <?php endif; ?>
    <?php if (!empty($orderReturnErrors)): ?>
      <div class="alert error" style="margin-top:10px;"><?php echo $esc(implode(' ', $orderReturnErrors)); ?></div>
    <?php endif; ?>
    <?php if (empty($orderReturns)): ?>
      <div class="muted" style="margin-top:8px;">Nenhuma devolução registrada para este pedido.</div>
    <?php else: ?>
      <div class="order-layout-list" style="margin-top:10px;">
        <?php foreach ($orderReturns as $return): ?>
          <?php
            $returnId = (int) ($return['id'] ?? 0);
            $statusKey = (string) ($return['status'] ?? '');
            $statusLabel = $orderReturnStatusOptions[$statusKey] ?? $statusKey;
            $refundStatusKey = (string) ($return['refund_status'] ?? '');
            $refundStatusLabel = $orderReturnRefundStatusOptions[$refundStatusKey] ?? $refundStatusKey;
            $refundMethod = (string) ($return['refund_method'] ?? '');
            $amount = isset($return['refund_amount']) ? (float) $return['refund_amount'] : 0.0;
            $amountLabel = 'R$ ' . number_format($amount, 2, ',', '.');
            $updatedAt = $return['updated_at'] ?? $return['created_at'] ?? null;
            $updatedLabel = $updatedAt ? date('d/m/Y H:i', strtotime($updatedAt)) : '-';
            $restockedAt = $return['restocked_at'] ?? null;
            $canRowCancel = $canCancelReturns
              && $statusKey !== 'cancelled'
              && empty($restockedAt)
              && !($refundStatusKey === 'done' && $refundMethod !== 'none');
          ?>
          <div class="order-layout-list-item">
            <div>
              <span>#<?php echo $returnId; ?> • <?php echo $esc($statusLabel); ?></span>
              <div class="order-layout-sub">Reembolso: <?php echo $esc($refundStatusLabel); ?> <?php echo $refundMethod !== '' ? '(' . $esc($refundMethod) . ')' : ''; ?> • <?php echo $esc($amountLabel); ?></div>
              <div class="order-layout-sub">Atualizado: <?php echo $esc($updatedLabel); ?></div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <a class="link" href="pedido-devolucao-cadastro.php?id=<?php echo $returnId; ?>">Abrir</a>
              <?php if ($canRowCancel): ?>
                <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) ($formData['id'] ?? '')); ?>" onsubmit="return confirm('Cancelar esta devolução?');">
                  <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
                  <input type="hidden" name="action" value="order_return_cancel">
                  <input type="hidden" name="return_id" value="<?php echo $returnId; ?>">
                  <button class="btn ghost" type="submit">Cancelar</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($editing && $canManageReturns): ?>
  <div class="modal-backdrop" data-return-modal-backdrop hidden></div>
  <div class="modal" data-return-modal role="dialog" aria-modal="true" hidden>
    <div class="modal-card" style="max-width:980px;">
      <div class="modal-header">
        <div></div>
        <button type="button" class="icon-button" data-return-close aria-label="Fechar janela">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4z"/>
          </svg>
        </button>
      </div>

      <?php if (!empty($orderReturnErrors)): ?>
        <div class="alert error" style="margin-top:12px;"><?php echo $esc(implode(' ', $orderReturnErrors)); ?></div>
      <?php endif; ?>

      <form
        method="post"
        action="pedido-cadastro.php?id=<?php echo $esc((string) ($formData['id'] ?? '')); ?>"
        novalidate
        data-shipping-total="<?php echo $esc(number_format($returnShippingTotal, 2, '.', '')); ?>"
        data-tax-total="<?php echo $esc(number_format($returnTaxTotal, 2, '.', '')); ?>"
      >
        <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
        <input type="hidden" name="action" value="order_return_save">
        <input type="hidden" name="order_id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
        <input type="hidden" name="pessoa_id" value="<?php echo $esc((string) ($orderReturnFormData['pessoa_id'] ?? '')); ?>">
        <input type="hidden" name="customer_name" value="<?php echo $esc((string) ($orderReturnFormData['customer_name'] ?? '')); ?>">
        <input type="hidden" name="customer_email" value="<?php echo $esc((string) ($orderReturnFormData['customer_email'] ?? '')); ?>">
        <input type="hidden" name="refund_extra_shipping" id="return_refund_extra_shipping" value="0">
        <input type="hidden" name="refund_extra_tax" id="return_refund_extra_tax" value="0">
        <input type="hidden" name="refund_status" id="return_refund_status_locked" value="<?php echo $esc((string) ($orderReturnFormData['refund_status'] ?? 'done')); ?>" hidden>

        <div class="card-grid" style="margin-top:12px;">
          <section class="card">
            <header>
              <div>
                <h2>Devolução</h2>
              </div>
            </header>
            <div class="field-grid">
              <div class="field">
                <label for="return_method">Forma de devolução</label>
                <select id="return_method" name="return_method" required>
                  <?php foreach ($orderReturnReturnMethodOptions as $key => $label): ?>
                    <option value="<?php echo $esc($key); ?>"<?php echo (($orderReturnFormData['return_method'] ?? '') === $key) ? ' selected' : ''; ?>>
                      <?php echo $esc($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="return_status">Status da devolução</label>
                <select id="return_status" name="status" required>
                  <?php foreach ($orderReturnStatusOptions as $key => $label): ?>
                    <option value="<?php echo $esc($key); ?>"<?php echo (($orderReturnFormData['status'] ?? '') === $key) ? ' selected' : ''; ?>>
                      <?php echo $esc($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field" data-return-tracking>
                <label for="return_tracking_code">Código de rastreamento</label>
                <input id="return_tracking_code" name="tracking_code" type="text" maxlength="160" value="<?php echo $esc((string) ($orderReturnFormData['tracking_code'] ?? '')); ?>" placeholder="Opcional">
              </div>
              <div class="field" data-return-expected>
                <label for="return_expected_at">Previsão de recebimento</label>
                <input id="return_expected_at" name="expected_at" type="date" value="<?php echo $esc((string) ($orderReturnFormData['expected_at'] ?? '')); ?>">
              </div>
              <div class="field">
                <label for="return_received_at">Recebido em</label>
                <input id="return_received_at" name="received_at" type="datetime-local" value="<?php echo $esc((string) ($orderReturnFormData['received_at'] ?? '')); ?>">
              </div>
              <div class="field full-width" data-return-notes-toggle>
                <button type="button" class="btn ghost" data-return-notes-button>Adicionar observações</button>
              </div>
              <div class="field full-width" data-return-notes hidden>
                <label for="return_notes">Observações</label>
                <textarea id="return_notes" name="notes" rows="3" maxlength="500" placeholder="Motivo da devolução, detalhes de logística."><?php echo $esc((string) ($orderReturnFormData['notes'] ?? '')); ?></textarea>
              </div>
            </div>
          </section>

          <section class="card">
            <header>
              <div>
                <h2>Reembolso / crédito</h2>
              </div>
            </header>
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
              <div class="field" style="min-width:180px;flex:1;">
                <label for="return_refund_method">Reembolso</label>
                <select id="return_refund_method" name="refund_method" required>
                  <?php foreach ($orderReturnRefundMethodOptions as $key => $label): ?>
                    <option value="<?php echo $esc($key); ?>"<?php echo (($orderReturnFormData['refund_method'] ?? '') === $key) ? ' selected' : ''; ?>>
                      <?php echo $esc($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field" style="min-width:160px;">
                <label for="return_refund_status">Status</label>
                <select id="return_refund_status" name="refund_status" required>
                  <?php foreach ($orderReturnRefundStatusOptions as $key => $label): ?>
                    <option value="<?php echo $esc($key); ?>"<?php echo (($orderReturnFormData['refund_status'] ?? '') === $key) ? ' selected' : ''; ?>>
                      <?php echo $esc($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field" style="min-width:160px;">
                <label for="return_refund_amount">Valor</label>
                <input id="return_refund_amount" name="refund_amount" type="text" inputmode="decimal" data-number-br step="0.01" min="0" value="<?php echo $esc((string) ($orderReturnFormData['refund_amount'] ?? '')); ?>" placeholder="0,00">
              </div>
              <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="icon-button" data-return-extra-toggle data-extra-type="shipping" aria-label="Adicionar frete" title="Adicionar frete">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M3 6h13v8H3V6zm14 3h3l2 3v2h-5V9zm-1-5H2a1 1 0 0 0-1 1v10h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-4.5l-2.7-4A1 1 0 0 0 20 6h-3V4zM6 17a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm12 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                  </svg>
                </button>
                <span class="subtitle">Frete R$ <?php echo $esc(number_format($returnShippingTotal, 2, ',', '.')); ?></span>
              </div>
              <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="icon-button" data-return-extra-toggle data-extra-type="tax" aria-label="Adicionar taxas" title="Adicionar taxas">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M3 4h18v2H3V4zm2 4h14v12H5V8zm3 2v2h2v-2H8zm0 4v2h2v-2H8zm4-4v2h2v-2h-2zm0 4v2h2v-2h-2zm4-4v2h2v-2h-2zm0 4v2h2v-2h-2z"/>
                  </svg>
                </button>
                <span class="subtitle">Taxas R$ <?php echo $esc(number_format($returnTaxTotal, 2, ',', '.')); ?></span>
              </div>
            </div>
          </section>
        </div>

        <section class="card" style="margin-top:12px;">
          <header>
            <div>
              <h2>Itens do pedido</h2>
              <div class="subtitle">Marque os produtos devolvidos e a quantidade.</div>
            </div>
          </header>
          <?php if (empty($orderReturnItems)): ?>
            <div class="muted">Itens do pedido não encontrados.</div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
              <?php foreach ($orderReturnItems as $lineId => $item): ?>
                <?php
                  $soldQty = (int) ($item['quantity'] ?? 0);
                  $returnedQty = $orderReturnAlreadyReturned[$lineId] ?? 0;
                  $availableQty = $orderReturnAvailableMap[$lineId] ?? $soldQty;
                  $currentQty = $orderReturnItemsInput[$lineId]['quantity'] ?? '';
                  $currentQty = $currentQty === '' ? '' : (int) $currentQty;
                  $isSelected = $currentQty !== '' && (int) $currentQty > 0;
                  $name = (string) ($item['name'] ?? '');
                  $sku = (string) ($item['sku'] ?? '');
                  $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
                  $disableItem = $availableQty <= 0;
                ?>
                <div style="border:1px solid var(--line);border-radius:12px;padding:10px;background:#fff;">
                  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div style="min-width:160px;flex:1;">
                      <div style="font-weight:600;"><?php echo $esc($name); ?></div>
                      <?php if ($sku !== ''): ?>
                        <div class="subtitle">SKU <?php echo $esc($sku); ?></div>
                      <?php endif; ?>
                      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;font-size:12px;color:var(--muted);">
                        <span>Vendido <?php echo $soldQty; ?></span>
                        <span>Devolvido <?php echo $returnedQty; ?></span>
                        <span>Disponível <?php echo $availableQty; ?></span>
                      </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" data-return-item-toggle data-line-id="<?php echo $lineId; ?>" <?php echo $isSelected ? 'checked' : ''; ?> <?php echo $disableItem ? 'disabled' : ''; ?>>
                        <span>Selecionar</span>
                      </label>
                      <input
                        type="number"
                        name="items[<?php echo $lineId; ?>][quantity]"
                        min="0"
                        max="<?php echo $availableQty; ?>"
                        value="<?php echo $esc((string) $currentQty); ?>"
                        data-return-qty
                        data-line-id="<?php echo $lineId; ?>"
                        style="width:80px;"
                        aria-label="Quantidade para <?php echo $esc($name); ?>"
                        <?php echo $disableItem ? 'disabled' : ''; ?>
                      >
                      <input type="hidden" name="items[<?php echo $lineId; ?>][unit_price]" value="<?php echo number_format($unitPrice, 2, '.', ''); ?>">
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <div class="footer" style="gap:8px;justify-content:flex-end;">
          <button class="btn ghost" type="button" data-return-close>Cancelar</button>
          <?php
            $returnId = $orderReturnFormData['id'] ?? '';
            $restockedAt = $orderReturnFormData['restocked_at'] ?? null;
            $refundStatus = $orderReturnFormData['refund_status'] ?? '';
            $refundMethod = $orderReturnFormData['refund_method'] ?? '';
            $isCancelled = (($orderReturnFormData['status'] ?? '') === 'cancelled');
            $canModalCancel = $canCancelReturns && $returnId && !$isCancelled && empty($restockedAt) && !($refundStatus === 'done' && $refundMethod !== 'none');
          ?>
          <?php if ($canModalCancel): ?>
            <button class="btn danger" type="button" id="return-cancel-button" data-return-id="<?php echo $esc((string) $returnId); ?>">Cancelar devolução</button>
          <?php endif; ?>
          <button class="btn primary" type="submit">Salvar devolução</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($editing && $bagContext): ?>
  <?php
    $bagAction = $bagContext['action'] ?? 'none';
    if ($bagAction !== 'none') {
        $openBag = $bagContext['open_bag'] ?? null;
        $bagTotals = $bagContext['bag_totals'] ?? ['items_qty' => 0, 'items_total' => 0.0, 'items_weight' => 0.0];
        $openingFee = $bagContext['opening_fee'] ?? 0.0;
        $bagOpenedAt = $openBag && $openBag->openedAt ? date('d/m/Y', strtotime($openBag->openedAt)) : '-';
        $bagExpectedClose = $openBag && $openBag->expectedCloseAt ? date('d/m/Y', strtotime($openBag->expectedCloseAt)) : '-';
        $bagFeePaid = $openBag ? (!empty($openBag->openingFeePaid) ? 'Pago' : 'Pendente') : 'Pendente';
        $bagFeeValue = $openBag ? (float) ($openBag->openingFeeValue ?? 0) : (float) $openingFee;
    }
  ?>
  <?php if ($bagAction !== 'none'): ?>
    <div style="margin:12px 0;padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
          <strong>Sacolinha</strong>
          <div style="font-size:13px;color:var(--muted);margin-top:4px;">
            <?php if ($bagAction === 'open_bag'): ?>
              Entrega configurada para abrir sacolinha.
            <?php elseif ($bagAction === 'add_to_bag'): ?>
              Entrega configurada para adicionar na sacolinha.
            <?php else: ?>
              Sem ação automática de sacolinha.
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn ghost" href="sacolinha-cliente.php?pessoa_id=<?php echo (int) ($bagContext['pessoa_id'] ?? 0); ?>">Histórico da cliente</a>
          <?php if ($openBag): ?>
            <a class="btn ghost" href="sacolinha-cadastro.php?id=<?php echo (int) $openBag->id; ?>">Ver sacolinha #<?php echo (int) $openBag->id; ?></a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($bagAction === 'open_bag' && $openBag): ?>
        <div class="alert error" style="margin-top:12px;">
          Cliente já possui sacolinha aberta (#<?php echo (int) $openBag->id; ?>). Use "Adicionar a sacolinha".
        </div>
      <?php elseif ($bagAction === 'add_to_bag' && !$openBag): ?>
        <div class="alert error" style="margin-top:12px;">
          Não há sacolinha aberta para esta cliente. Abra uma nova (taxa R$ <?php echo $esc(number_format($openingFee, 2, ',', '.')); ?>).
        </div>
      <?php endif; ?>
      <?php if ($bagAction !== 'none' && $openBag && ($bagContext['order_items_count'] ?? 0) > 0 && empty($bagContext['order_in_bag'])): ?>
        <div class="alert muted" style="margin-top:12px;">
          Itens deste pedido ainda não foram adicionados à sacolinha #<?php echo (int) $openBag->id; ?>.
          Use "Adicionar itens a sacolinha".
        </div>
      <?php endif; ?>

      <div class="grid" style="margin-top:12px;">
        <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
          <div style="font-size:12px;color:var(--muted);">Sacolinha aberta</div>
          <div style="margin-top:6px;font-size:14px;">
            <?php if ($openBag): ?>
              #<?php echo (int) $openBag->id; ?> • <?php echo $esc($bagOpenedAt); ?> → <?php echo $esc($bagExpectedClose); ?>
            <?php else: ?>
              Nenhuma sacolinha aberta.
            <?php endif; ?>
          </div>
        </div>
        <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
          <div style="font-size:12px;color:var(--muted);">Itens</div>
          <div style="margin-top:6px;font-size:14px;"><?php echo (int) ($bagTotals['items_qty'] ?? 0); ?> itens</div>
          <div style="font-size:12px;color:var(--muted);">Total R$ <?php echo $esc(number_format((float) ($bagTotals['items_total'] ?? 0), 2, ',', '.')); ?></div>
          <div style="font-size:12px;color:var(--muted);">Peso <?php echo $esc(number_format((float) ($bagTotals['items_weight'] ?? 0), 3, ',', '.')); ?> kg</div>
        </div>
        <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
          <div style="font-size:12px;color:var(--muted);">Taxa de abertura</div>
          <div style="margin-top:6px;font-size:14px;"><?php echo $esc($bagFeePaid); ?></div>
          <div style="font-size:12px;color:var(--muted);">R$ <?php echo $esc(number_format($bagFeeValue, 2, ',', '.')); ?></div>
        </div>
      </div>

      <div class="footer" style="margin-top:10px;gap:8px;flex-wrap:wrap;">
        <?php if ($bagAction === 'open_bag' && !$openBag): ?>
          <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin:0;">
            <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
            <input type="hidden" name="action" value="bag_open">
            <button class="primary" type="submit">Abrir sacolinha e adicionar itens</button>
          </form>
        <?php elseif ($bagAction === 'add_to_bag' && $openBag): ?>
          <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin:0;">
            <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
            <input type="hidden" name="action" value="bag_add">
            <button class="primary" type="submit">Adicionar itens a sacolinha #<?php echo (int) $openBag->id; ?></button>
          </form>
        <?php elseif ($bagAction === 'add_to_bag' && !$openBag): ?>
          <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin:0;">
            <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
            <input type="hidden" name="action" value="bag_open">
            <button class="primary" type="submit">Abrir sacolinha e adicionar itens</button>
          </form>
        <?php elseif ($bagAction === 'open_bag' && $openBag): ?>
          <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin:0;">
            <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
            <input type="hidden" name="action" value="bag_add">
            <button class="primary" type="submit">Adicionar itens a sacolinha #<?php echo (int) $openBag->id; ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($showEditForm): ?>
  <div class="order-layout-switcher" data-layout-switcher-wrap>
    <label for="layoutSwitcher">Layout</label>
    <select id="layoutSwitcher" aria-label="Selecionar layout do pedido">
      <option value="layout-1">Baseline</option>
      <option value="layout-2">Wizard</option>
      <option value="layout-3">Cards</option>
      <option value="layout-4">Painel</option>
      <option value="layout-5">Compacto</option>
    </select>
  </div>

  <form id="orderForm" method="post" action="<?php echo $esc($formAction); ?>">
    <div id="orderLayoutRoot" class="layout layout-1 order-create-layout" data-order-layout-root>
      <div class="order-create-shell" data-order-create-shell>
        <main class="order-create-main" data-order-main>
          <section class="order-section order-section--customer is-open" data-order-section data-section-key="customer">
            <header class="order-section__header">
              <div>
                <div class="flow-step-label">1. Cliente</div>
                <h2 class="order-section__title">Cliente</h2>
              </div>
              <div class="order-section__header-actions">
                <span class="order-step-status" data-step-status="customer">Incompleto</span>
                <button type="button" class="btn ghost order-section__toggle" data-section-toggle aria-expanded="true">Recolher</button>
              </div>
            </header>
            <div class="order-section__body" data-section-body>
              <?php
                $selectedCustomerId = (int) ($formData['pessoa_id'] ?? 0);
                $selectedCustomer = null;
                if ($selectedCustomerId > 0) {
                  foreach ($customerOptions as $customer) {
                    if ((int) ($customer['id'] ?? 0) === $selectedCustomerId) {
                      $selectedCustomer = $customer;
                      break;
                    }
                  }
                }

                $selectedCustomerLabel = 'Informe um ID de cliente.';
                $hasSelectedCustomer = false;
                if ($selectedCustomer) {
                  $hasSelectedCustomer = true;
                  $name = trim((string) ($selectedCustomer['shipping_full_name'] ?? $selectedCustomer['full_name'] ?? 'Cliente'));
                  $email = trim((string) ($selectedCustomer['email'] ?? ''));

                  $shippingLine1 = trim((string) ($selectedCustomer['shipping_address_1'] ?? $selectedCustomer['street'] ?? ''));
                  $shippingNumber = trim((string) ($selectedCustomer['shipping_number'] ?? $selectedCustomer['number'] ?? ''));
                  $shippingLine2 = trim((string) ($selectedCustomer['shipping_address_2'] ?? $selectedCustomer['street2'] ?? ''));
                  $shippingNeighborhood = trim((string) ($selectedCustomer['shipping_neighborhood'] ?? $selectedCustomer['neighborhood'] ?? ''));
                  $shippingCity = trim((string) ($selectedCustomer['shipping_city'] ?? $selectedCustomer['city'] ?? ''));
                  $shippingState = trim((string) ($selectedCustomer['shipping_state'] ?? $selectedCustomer['state'] ?? ''));
                  $shippingPostcode = trim((string) ($selectedCustomer['shipping_postcode'] ?? $selectedCustomer['zip'] ?? ''));
                  $shippingCountry = trim((string) ($selectedCustomer['shipping_country'] ?? $selectedCustomer['country'] ?? ''));

                  $addressParts = [];
                  if ($shippingLine1 !== '') {
                    $addressParts[] = $shippingNumber !== '' ? ($shippingLine1 . ', ' . $shippingNumber) : $shippingLine1;
                  }
                  if ($shippingLine2 !== '') {
                    $addressParts[] = $shippingLine2;
                  }
                  if ($shippingNeighborhood !== '') {
                    $addressParts[] = $shippingNeighborhood;
                  }
                  $cityState = trim(implode(' - ', array_filter([$shippingCity, $shippingState])));
                  if ($cityState !== '') {
                    $addressParts[] = $cityState;
                  }
                  $zipCountry = trim(implode(' · ', array_filter([$shippingPostcode, $shippingCountry])));
                  if ($zipCountry !== '') {
                    $addressParts[] = $zipCountry;
                  }

                  $parts = array_filter([
                    $name,
                    $email,
                    implode("\n", $addressParts),
                  ], static fn($v) => (string) $v !== '');

                  if (!empty($parts)) {
                    $selectedCustomerLabel = implode("\n", $parts);
                  }
                } elseif ($selectedCustomerId > 0) {
                  $selectedCustomerLabel = 'Cliente nao encontrado.';
                }
              ?>

              <div class="grid order-top-grid">
                <div class="field">
                  <div class="customer-picker autocomplete-picker" data-customer-picker>
                    <div class="customer-input-row">
                      <input type="text" id="pessoa_id" name="pessoa_id" list="customerOptions" placeholder="Digite para buscar" aria-label="Cliente (ID pessoa)" value="<?php echo $esc((string) ($formData['pessoa_id'] ?? '')); ?>">
                      <button type="button" class="btn ghost customer-new-btn" data-new-customer>Novo cliente</button>
                    </div>
                    <small id="customerSelectedLabel" data-fallback-label="<?php echo $esc($selectedCustomerLabel); ?>" style="color:var(--muted);display:flex;gap:6px;align-items:flex-start;margin-top:6px;white-space:pre-line;line-height:1.35;">
                      <span data-customer-label-text><?php echo $esc($selectedCustomerLabel); ?></span>
                      <button type="button" class="icon-button" data-customer-edit aria-label="Editar dados do cliente" title="Editar dados do cliente" style="margin-top:-2px;" <?php echo $hasSelectedCustomer ? '' : 'hidden'; ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <path fill="currentColor" d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25zm14.71-11.46a1 1 0 0 1 1.41 0l1.34 1.34a1 1 0 0 1 0 1.41l-1.13 1.13-2.75-2.75 1.13-1.13z"/>
                        </svg>
                      </button>
                    </small>
                    <div id="customerSuggestions" class="customer-suggestions autocomplete-suggestions" hidden></div>
                  </div>
                  <datalist id="customerOptions">
                    <?php foreach ($customerOptions as $customer):
                      $cName  = trim((string) ($customer['shipping_full_name'] ?? $customer['full_name'] ?? $customer['name'] ?? ''));
                      $cEmail = trim((string) ($customer['email'] ?? ''));
                      $cState = trim((string) ($customer['shipping_state'] ?? $customer['state'] ?? ''));
                      // Skip name when it equals email (data-quality: email stored as full_name)
                      if (strtolower($cName) === strtolower($cEmail)) { $cName = ''; }
                      $cParts = ['#' . (int) $customer['id']];
                      if ($cName !== '') $cParts[] = $cName;
                      if ($cEmail !== '') $cParts[] = $cEmail;
                      if ($cState !== '') $cParts[] = $cState;
                      $cLabel = $esc(implode(' - ', $cParts));
                    ?>
                      <option value="<?php echo (int) $customer['id']; ?>" label="<?php echo $cLabel; ?>">
                        <?php echo $cLabel; ?>
                      </option>
                    <?php endforeach; ?>
                  </datalist>
                </div>
              </div>
            </div>
          </section>

          <?php if ($fullEdit): ?>
            <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
            <input type="hidden" name="action" value="update_full">
            <input type="hidden" name="original_line_items" value="<?php echo $esc(json_encode(array_values($originalLineItemIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
            <input type="hidden" name="line_id" value="<?php echo $esc((string) ($formData['line_id'] ?? '')); ?>">
          <?php endif; ?>
          <input type="hidden" name="status" value="<?php echo $esc($fullEdit ? ($formData['status'] ?? 'open') : 'open'); ?>">
          <input type="hidden" id="billing_full_name" name="billing_full_name" value="<?php echo $esc($formData['billing_full_name'] ?? ''); ?>">
          <input type="hidden" id="billing_email" name="billing_email" value="<?php echo $esc($formData['billing_email'] ?? ''); ?>">
          <input type="hidden" id="billing_phone" name="billing_phone" value="<?php echo $esc($formData['billing_phone'] ?? ''); ?>">
          <input type="hidden" id="billing_address_1" name="billing_address_1" value="<?php echo $esc($formData['billing_address_1'] ?? ''); ?>">
          <input type="hidden" id="billing_address_2" name="billing_address_2" value="<?php echo $esc($formData['billing_address_2'] ?? ''); ?>">
          <input type="hidden" id="billing_number" name="billing_number" value="<?php echo $esc($formData['billing_number'] ?? ''); ?>">
          <input type="hidden" id="billing_neighborhood" name="billing_neighborhood" value="<?php echo $esc($formData['billing_neighborhood'] ?? ''); ?>">
          <input type="hidden" id="billing_city" name="billing_city" value="<?php echo $esc($formData['billing_city'] ?? ''); ?>">
          <input type="hidden" id="billing_state" name="billing_state" value="<?php echo $esc($formData['billing_state'] ?? ''); ?>">
          <input type="hidden" id="billing_postcode" name="billing_postcode" value="<?php echo $esc($formData['billing_postcode'] ?? ''); ?>">
          <input type="hidden" id="billing_country" name="billing_country" value="<?php echo $esc($formData['billing_country'] ?? ''); ?>">
          <input type="hidden" id="shipping_full_name" name="shipping_full_name" value="<?php echo $esc($formData['shipping_full_name'] ?? ''); ?>">
          <input type="hidden" id="shipping_phone" name="shipping_phone" value="<?php echo $esc($formData['shipping_phone'] ?? ''); ?>">
          <input type="hidden" id="shipping_address_1" name="shipping_address_1" value="<?php echo $esc($formData['shipping_address_1'] ?? ''); ?>">
          <input type="hidden" id="shipping_address_2" name="shipping_address_2" value="<?php echo $esc($formData['shipping_address_2'] ?? ''); ?>">
          <input type="hidden" id="shipping_number" name="shipping_number" value="<?php echo $esc($formData['shipping_number'] ?? ''); ?>">
          <input type="hidden" id="shipping_neighborhood" name="shipping_neighborhood" value="<?php echo $esc($formData['shipping_neighborhood'] ?? ''); ?>">
          <input type="hidden" id="shipping_city" name="shipping_city" value="<?php echo $esc($formData['shipping_city'] ?? ''); ?>">
          <input type="hidden" id="shipping_state" name="shipping_state" value="<?php echo $esc($formData['shipping_state'] ?? ''); ?>">
          <input type="hidden" id="shipping_postcode" name="shipping_postcode" value="<?php echo $esc($formData['shipping_postcode'] ?? ''); ?>">
          <input type="hidden" id="shipping_country" name="shipping_country" value="<?php echo $esc($formData['shipping_country'] ?? ''); ?>">

          <section class="order-section order-section--items is-open" data-order-section data-section-key="items">
            <header class="order-section__header">
              <div>
                <div class="flow-step-label">2. Itens do pedido</div>
                <h2 class="order-section__title">Itens</h2>
              </div>
              <div class="order-section__header-actions">
                <span class="order-step-status" data-step-status="items">Incompleto</span>
                <button type="button" class="btn ghost order-section__toggle" data-section-toggle aria-expanded="true">Recolher</button>
              </div>
            </header>
            <div class="order-section__body" data-section-body>
              <datalist id="productSkuOptions">
                <?php foreach ($productOptions as $product): ?>
                  <?php
                    $name = trim((string) ($product['post_title'] ?? ''));
                    $sku = trim((string) ($product['sku'] ?? ''));
                  ?>
                  <?php if ($sku !== ''): ?>
                    <option value="<?php echo $esc($sku); ?>" label="<?php echo $esc($name); ?>"></option>
                  <?php endif; ?>
                  <?php if (!empty($product['variations']) && is_array($product['variations'])): ?>
                    <?php foreach ($product['variations'] as $variation): ?>
                      <?php
                        $variationSku = trim((string) ($variation['sku'] ?? ''));
                        if ($variationSku === '') {
                            continue;
                        }
                        $variationName = trim((string) ($variation['name'] ?? $variation['post_title'] ?? ''));
                        $variationLabelParts = array_filter([$name, $variationName]);
                        $variationLabel = $variationLabelParts ? implode(' - ', $variationLabelParts) : $name;
                      ?>
                      <option value="<?php echo $esc($variationSku); ?>" label="<?php echo $esc($variationLabel); ?>"></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              </datalist>
              <datalist id="productNameOptions">
                <?php foreach ($productOptions as $product): ?>
                  <?php
                    $name = trim((string) ($product['post_title'] ?? ''));
                    $sku = trim((string) ($product['sku'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                  ?>
                  <option value="<?php echo $esc($name); ?>" label="<?php echo $esc($sku); ?>"></option>
                <?php endforeach; ?>
              </datalist>

              <div class="order-item-picker">
                <div class="grid order-item-picker__grid">
                  <div class="field" data-order-picker-sku>
                    <label>SKU/Produto</label>
                    <div class="autocomplete-picker">
                      <input type="text" id="orderItemSku" list="productSkuOptions" data-product-sku>
                      <div class="autocomplete-suggestions" data-sku-suggestions hidden></div>
                    </div>
                  </div>
                  <div class="field" data-order-picker-name>
                    <label>Produto</label>
                    <div class="autocomplete-picker">
                      <input type="text" id="orderItemName" list="productNameOptions" data-product-name>
                      <div class="autocomplete-suggestions" data-name-suggestions hidden></div>
                    </div>
                  </div>
                  <div class="field" data-variation-field hidden>
                    <label>Variação</label>
                    <select id="orderItemVariation" data-variation-select data-variation-value="">
                      <option value="">Sem variação</option>
                    </select>
                  </div>
                  <div class="field" data-order-picker-qty>
                    <label>Quantidade</label>
                    <input type="number" min="1" id="orderItemQty" value="1">
                  </div>
                  <div class="field" data-order-picker-price>
                    <label>Preço unitário (R$)</label>
                    <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="orderItemPrice">
                  </div>
                </div>
                <div class="order-item-picker__actions">
                  <div class="order-item-helper" id="orderItemHelper">Selecione um SKU ou produto.</div>
                  <button class="btn primary" type="button" id="addOrderItem">Adicionar</button>
                </div>
              </div>

              <div id="orderItemsList" class="order-items-list">
                <div class="order-items-empty" data-items-empty>Nenhum item adicionado.</div>
              </div>
            </div>
          </section>

          <input type="hidden" name="payment_status" value="none">
          <input type="hidden" name="fulfillment_status" value="pending">
          <input type="hidden" name="order_mode" id="order_mode" value="<?php echo $esc($orderMode); ?>">
          <input type="hidden" id="bag_id_create" name="bag_id" value="<?php echo $esc((string) ($formData['bag_id'] ?? '')); ?>">

          <section class="order-section order-section--logistics is-open" data-order-section data-section-key="logistics">
            <header class="order-section__header">
              <div>
                <div class="flow-step-label">3. Entrega e frete</div>
                <h2 class="order-section__title">Logística</h2>
              </div>
              <div class="order-section__header-actions">
                <span class="order-step-status" data-step-status="logistics">Incompleto</span>
                <button type="button" class="btn ghost order-section__toggle" data-section-toggle aria-expanded="true">Recolher</button>
              </div>
            </header>
            <div class="order-section__body" data-section-body>
              <div class="order-mode-mobile" data-mobile-order-mode>
                <div class="order-mode-toggle" role="group" aria-label="Tipo de criação do pedido (mobile)">
                  <button type="button" class="btn ghost" data-order-mode="pdv">PDV</button>
                  <button type="button" class="btn ghost" data-order-mode="online">ONLINE</button>
                </div>
                <div class="order-mode-summary-label" data-mobile-pdv-summary hidden></div>
              </div>

              <div class="order-mode-desktop">
                <div class="order-mode-toggle" role="group" aria-label="Tipo de criação do pedido">
                  <button type="button" class="btn ghost" data-order-mode="pdv">PDV</button>
                  <button type="button" class="btn ghost" data-order-mode="online">ONLINE</button>
                </div>
                <div id="pdvSummary" class="order-mode-pdv-summary" hidden>Canal e logística configurados para operação de balcão.</div>
              </div>

              <div class="grid" style="margin-top:12px;" data-mobile-online-fields data-online-fields>
                <div class="field">
                  <label for="sales_channel">Canal de venda</label>
                  <select id="sales_channel" name="sales_channel">
                    <option value="" <?php echo $currentSalesChannel === '' ? 'selected' : ''; ?>>Selecione</option>
                    <?php if ($hasInvalidSalesChannel): ?>
                      <option value="<?php echo $esc($currentSalesChannel); ?>" selected>Canal inválido: <?php echo $esc($currentSalesChannel); ?></option>
                    <?php endif; ?>
                    <?php foreach ($salesChannelOptions as $option): ?>
                      <option value="<?php echo $esc((string) $option); ?>" <?php echo $currentSalesChannel === (string) $option ? 'selected' : ''; ?>>
                        <?php echo $esc((string) $option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="delivery_mode_select">Entrega</label>
                  <select id="delivery_mode_select">
                    <?php foreach ($deliveryModeOptions as $modeKey => $modeLabel): ?>
                      <option value="<?php echo $esc((string) $modeKey); ?>" <?php echo $currentDeliveryMode === (string) $modeKey ? 'selected' : ''; ?>>
                        <?php echo $esc((string) $modeLabel); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field" data-create-shipment-kind-field <?php echo $currentDeliveryMode === 'shipment' ? '' : 'hidden'; ?>>
                  <label for="shipment_kind_select">Tipo de envio</label>
                  <select id="shipment_kind_select">
                    <option value="">Selecione</option>
                    <?php foreach ($shipmentKindOptions as $kindKey => $kindLabel): ?>
                      <option value="<?php echo $esc((string) $kindKey); ?>" <?php echo $currentShipmentKind === (string) $kindKey ? 'selected' : ''; ?>>
                        <?php echo $esc((string) $kindLabel); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="shipping_total">Frete (R$)</label>
                  <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="shipping_total" name="shipping_total" value="<?php echo $esc($formData['shipping_total']); ?>">
                </div>
                <div class="field" data-create-carrier-field hidden>
                  <label for="carrier_id_create">Transportadora</label>
                  <select id="carrier_id_create" name="carrier_id">
                    <option value="">Selecione</option>
                    <?php foreach ($carrierOptions as $carrier): ?>
                      <?php $carrierId = (string) ($carrier['id'] ?? ''); ?>
                      <option value="<?php echo $esc($carrierId); ?>" <?php echo (string) ($formData['carrier_id'] ?? '') === $carrierId ? 'selected' : ''; ?>>
                        <?php echo $esc((string) ($carrier['name'] ?? '')); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="subtitle" style="margin-top:6px;"><a href="transportadora-cadastro.php">Cadastrar transportadora</a></div>
                </div>
                <div class="field" data-create-tracking-field hidden>
                  <label for="tracking_code_create">Código de rastreio</label>
                  <input type="text" id="tracking_code_create" name="tracking_code" value="<?php echo $esc((string) ($formData['tracking_code'] ?? '')); ?>">
                </div>
                <div class="field" data-create-estimated-field hidden>
                  <label for="estimated_delivery_at_create">Previsão de entrega</label>
                  <input type="text" id="estimated_delivery_at_create" name="estimated_delivery_at" value="<?php echo $esc((string) ($formData['estimated_delivery_at'] ?? '')); ?>">
                </div>
                <div class="field" data-open-bag-fee-pay-now-field hidden style="grid-column:1 / -1;display:none;">
                  <label class="open-bag-fee-line" for="open_bag_fee_pay_now">
                    <input type="checkbox" id="open_bag_fee_pay_now" value="1">
                    <span id="openBagFeePayNowLabel">Pagar taxa de abertura agora</span>
                    <span id="openBagFeePayNowHint" class="open-bag-fee-line__hint">Se preferir, deixe desmarcado para cobrar depois.</span>
                  </label>
                </div>
                <div class="field" data-opening-fee-deferred-field hidden style="grid-column:1 / -1;display:none;">
                  <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="opening_fee_deferred" value="1" <?php echo !empty($formData['opening_fee_deferred']) ? 'checked' : ''; ?>>
                    <span>Cobrar frete de abertura depois</span>
                  </label>
                </div>
              </div>
              <div id="bagCreateSummary" class="order-bag-inline-summary subtitle" hidden></div>
              <div id="deliveryNote" class="subtitle" style="margin-top:6px;color:var(--muted);" hidden></div>
            </div>
          </section>

          <section class="order-section order-section--payments is-open" data-order-section data-section-key="payments">
            <header class="order-section__header">
              <div>
                <div class="flow-step-label">4. Pagamentos</div>
                <h2 class="order-section__title">Pagamentos</h2>
              </div>
              <div class="order-section__header-actions">
                <span class="order-step-status" data-step-status="payments">Incompleto</span>
                <button type="button" class="btn ghost order-section__toggle" data-section-toggle aria-expanded="true">Recolher</button>
              </div>
            </header>
            <div class="order-section__body" data-section-body>
              <div class="order-payment-picker">
                <div class="grid order-payment-picker__grid">
                  <div class="field">
                    <label for="orderPaymentMethod">Método</label>
                    <select id="orderPaymentMethod">
                      <option value="">Selecione</option>
                      <?php foreach ($paymentMethodOptions as $method): ?>
                        <?php $methodId = (string) ($method['id'] ?? ''); ?>
                        <option value="<?php echo $esc($methodId); ?>">
                          <?php echo $esc((string) ($method['name'] ?? '')); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field" data-payment-voucher-field hidden>
                    <label for="orderPaymentVoucher">Cupom/crédito</label>
                    <select id="orderPaymentVoucher">
                      <option value="">Selecione</option>
                    </select>
                    <div id="orderPaymentVoucherMeta" class="subtitle" style="margin-top:6px;color:var(--muted);" hidden></div>
                  </div>
                  <div class="field">
                    <label for="orderPaymentAmount">Valor (R$)</label>
                    <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="orderPaymentAmount">
                  </div>
                  <div class="field">
                    <label for="orderPaymentFee">Taxa (R$)</label>
                    <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="orderPaymentFee">
                  </div>
                  <div class="field" data-payment-bank-field hidden>
                    <label for="orderPaymentBank">Banco/PIX</label>
                    <select id="orderPaymentBank">
                      <option value="">Selecione</option>
                      <?php foreach ($bankAccountOptions as $account): ?>
                        <?php
                          $accountId = (string) ($account['id'] ?? '');
                          $bankName = trim((string) ($account['bank_name'] ?? ''));
                          $accountLabel = trim((string) ($account['label'] ?? ''));
                          if ($bankName !== '' && $accountLabel !== '') {
                              $accountLabel = $bankName . ' - ' . $accountLabel;
                          } elseif ($bankName !== '') {
                              $accountLabel = $bankName;
                          }
                        ?>
                        <option value="<?php echo $esc($accountId); ?>">
                          <?php echo $esc($accountLabel !== '' ? $accountLabel : ('Conta #' . $accountId)); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field" data-payment-terminal-field hidden>
                    <label for="orderPaymentTerminal">Maquininha</label>
                    <select id="orderPaymentTerminal">
                      <option value="">Selecione</option>
                      <?php foreach ($paymentTerminalOptions as $terminal): ?>
                        <?php $terminalId = (string) ($terminal['id'] ?? ''); ?>
                        <option value="<?php echo $esc($terminalId); ?>">
                          <?php echo $esc((string) ($terminal['name'] ?? '')); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="order-payment-picker__actions">
                  <div class="order-payment-helper" id="orderPaymentHelper">Selecione um método para adicionar.</div>
                  <button class="btn primary" type="button" id="addOrderPayment">Adicionar pagamento</button>
                </div>
                <div id="paymentPixKey" class="subtitle" style="margin-top:6px;color:var(--muted);" hidden></div>
              </div>
              <div id="orderPaymentsList" class="order-payments-list">
                <div class="order-payments-empty" data-payments-empty>Nenhum pagamento adicionado.</div>
              </div>
            </div>
          </section>

          <section class="order-section order-section--review is-open" data-order-section data-section-key="review" data-section-locked>
            <header class="order-section__header">
              <div>
                <div class="flow-step-label">5. Revisão final</div>
                <h2 class="order-section__title">Totais e revisão</h2>
              </div>
              <div class="order-section__header-actions">
                <span class="order-step-status" data-step-status="review">Revisar</span>
              </div>
            </header>
            <div class="order-section__body" data-section-body>
              <div id="orderTotalSummary" class="order-summary-card order-summary-card--header order-summary-card--inline">
                <div id="orderTotalValue" class="order-summary-value">R$ 0,00</div>
                <div id="orderTotalPieces" class="order-summary-meta">0 itens</div>
                <div id="orderTotalShipping" class="order-summary-meta">Frete: R$ 0,00</div>
                <div id="orderTotalPaid" class="order-summary-meta">Pago: R$ 0,00</div>
                <div id="orderTotalFees" class="order-summary-meta">Taxas: R$ 0,00</div>
                <div id="orderTotalRemaining" class="order-summary-meta">Falta: R$ 0,00</div>
              </div>
            </div>
          </section>

          <input type="hidden" id="delivery_mode" name="delivery_mode" value="<?php echo $esc((string) ($formData['delivery_mode'] ?? 'shipment')); ?>">
          <input type="hidden" id="shipment_kind" name="shipment_kind" value="<?php echo $esc((string) ($formData['shipment_kind'] ?? '')); ?>">
        </main>

        <aside class="order-layout-sidebar" data-order-layout-sidebar>
          <button type="button" class="btn ghost order-layout-sidebar__trigger" data-sidebar-trigger>Resumo</button>
          <div class="order-layout-sidebar__panel" data-sidebar-panel>
            <div class="order-layout-sidebar__header">
              <h3>Resumo financeiro</h3>
              <span class="pill" id="orderPanelState">Pedido incompleto</span>
            </div>
            <div class="order-layout-sidebar__list">
              <div class="order-layout-sidebar__row"><span>Total</span><strong id="orderPanelTotal">R$ 0,00</strong></div>
              <div class="order-layout-sidebar__row"><span>Itens</span><strong id="orderPanelItems">0 itens</strong></div>
              <div class="order-layout-sidebar__row"><span>Pago</span><strong id="orderPanelPaid">R$ 0,00</strong></div>
              <div class="order-layout-sidebar__row order-layout-sidebar__row--alert"><span>Falta</span><strong id="orderPanelRemaining">R$ 0,00</strong></div>
            </div>
            <div class="order-layout-sidebar__alerts" id="orderPanelAlerts">Adicione cliente, item e pagamento para concluir.</div>
          </div>
        </aside>
      </div>

      <div class="order-submit-bar" data-order-submit-bar>
        <div class="order-submit-bar__secondary">
          <button type="button" class="btn ghost" data-sidebar-trigger>Resumo</button>
        </div>
        <button class="primary" type="submit"><?php echo $esc($editing ? 'Salvar edições' : 'Criar pedido'); ?></button>
      </div>
    </div>
  </form>

  <div class="modal-backdrop" data-modal-backdrop hidden></div>
  <div class="modal" data-address-modal role="dialog" aria-modal="true" hidden>
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 data-modal-title style="margin:0;">Editar dados</h3>
          <div class="subtitle" data-modal-subtitle style="margin:4px 0 0;">Atualiza o cadastro do cliente.</div>
        </div>
        <button type="button" class="icon-button" data-modal-close aria-label="Fechar janela">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4z"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-error" data-modal-error hidden></div>
        <div class="grid">
          <div class="field">
            <label for="address_full_name">Nome completo</label>
            <input type="text" id="address_full_name" autocomplete="name">
          </div>
          <div class="field" data-modal-email-field>
            <label for="address_email">E-mail</label>
            <input type="email" id="address_email" autocomplete="email">
          </div>
          <div class="field">
            <label for="address_phone">Telefone</label>
            <input type="text" id="address_phone" autocomplete="tel">
          </div>
          <div class="field">
            <label for="address_postcode">CEP</label>
            <input type="text" id="address_postcode" autocomplete="postal-code">
          </div>
          <div class="field">
            <label for="address_address_1">Endereço</label>
            <input type="text" id="address_address_1" autocomplete="address-line1">
          </div>
          <div class="field">
            <label for="address_number">Número</label>
            <input type="text" id="address_number">
          </div>
          <div class="field">
            <label for="address_address_2">Complemento</label>
            <input type="text" id="address_address_2" placeholder="nº, cs, lt, apt, bl" autocomplete="address-line2">
          </div>
          <div class="field">
            <label for="address_neighborhood">Bairro</label>
            <input type="text" id="address_neighborhood">
          </div>
          <div class="field">
            <label for="address_city">Cidade</label>
            <input type="text" id="address_city" autocomplete="address-level2">
          </div>
          <div class="field">
            <label for="address_state">UF</label>
            <input type="text" id="address_state" autocomplete="address-level1">
          </div>
          <div class="field">
            <label for="address_country">País</label>
            <input type="text" id="address_country" autocomplete="country">
          </div>
        </div>
      </div>
      <div class="footer">
        <button type="button" class="btn ghost" data-modal-cancel>Cancelar</button>
        <button type="button" class="primary" data-modal-save>Salvar</button>
      </div>
    </div>
  </div>
  <div class="modal-backdrop" data-customer-backdrop hidden></div>
  <div class="modal" data-customer-modal role="dialog" aria-modal="true" hidden>
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 style="margin:0;">Novo cliente</h3>
          <div class="subtitle" style="margin:4px 0 0;">Cadastro rápido para usar neste pedido.</div>
        </div>
        <button type="button" class="icon-button" data-customer-close aria-label="Fechar janela">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4z"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-error" data-customer-error hidden></div>
        <div class="grid">
          <div class="field">
            <label for="new_customer_full_name">Nome completo</label>
            <input type="text" id="new_customer_full_name" autocomplete="name">
          </div>
          <div class="field">
            <label for="new_customer_email">E-mail</label>
            <input type="email" id="new_customer_email" autocomplete="email">
          </div>
          <div class="field">
            <label for="new_customer_phone">Telefone</label>
            <input type="text" id="new_customer_phone" autocomplete="tel">
          </div>
          <div class="field">
            <label for="new_customer_cpf">CPF/CNPJ</label>
            <input type="text" id="new_customer_cpf">
          </div>
          <div class="field">
            <label for="new_customer_zip">CEP</label>
            <input type="text" id="new_customer_zip" autocomplete="postal-code">
          </div>
          <div class="field">
            <label for="new_customer_street">Endereço</label>
            <input type="text" id="new_customer_street" autocomplete="address-line1">
          </div>
          <div class="field">
            <label for="new_customer_number">Número</label>
            <input type="text" id="new_customer_number">
          </div>
          <div class="field">
            <label for="new_customer_street2">Complemento</label>
            <input type="text" id="new_customer_street2" placeholder="nº, cs, lt, apt, bl" autocomplete="address-line2">
          </div>
          <div class="field">
            <label for="new_customer_neighborhood">Bairro</label>
            <input type="text" id="new_customer_neighborhood">
          </div>
          <div class="field">
            <label for="new_customer_city">Cidade</label>
            <input type="text" id="new_customer_city" autocomplete="address-level2">
          </div>
          <div class="field">
            <label for="new_customer_state">UF</label>
            <input type="text" id="new_customer_state" autocomplete="address-level1">
          </div>
          <div class="field">
            <label for="new_customer_country">País</label>
            <input type="text" id="new_customer_country" value="BR" autocomplete="country">
          </div>
        </div>
      </div>
      <div class="footer">
        <button type="button" class="btn ghost" data-customer-cancel>Cancelar</button>
        <button type="button" class="primary" data-customer-save>Salvar cliente</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($editing): ?>
  <div style="margin-top:20px;">
    <h2 style="margin-bottom:10px;">Itens do pedido</h2>
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Imagem</th>
            <th>Produto</th>
            <th>Quantidade</th>
            <th>Total</th>
            <th>Disponível</th>
            <?php if ($canManageReturns): ?>
              <th>Devolução</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="<?php echo $canManageReturns ? 6 : 5; ?>">Nenhum item encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <?php
                $productSku = (int) ($item['product_sku'] ?? 0);
                $lineId = (int) ($item['id'] ?? 0);
                $stockInfo = $itemStocks[$productSku] ?? null;
                $stockLabel = '-';
                if ($stockInfo) {
                    $stockQty = $stockInfo['quantity'] ?? '';
                    $stockStatus = $stockInfo['availability_status'] ?? '';
                    $stockLabel = trim((string) $stockQty);
                    if ($stockStatus !== '') {
                        $statusLabel = $stockStatus === 'instock' ? 'disponível' : 'indisponível';
                        $stockLabel .= ' (' . $statusLabel . ')';
                    }
                }
                $itemImage = '';
                if (isset($item['image']) && is_array($item['image'])) {
                    $itemImage = (string) ($item['image']['src'] ?? '');
                }
                if ($itemImage === '' && !empty($item['image_src'])) {
                    $itemImage = (string) $item['image_src'];
                }
                if ($itemImage === '' && $stockInfo) {
                    $itemImage = (string) ($stockInfo['image_src'] ?? '');
                }
                $productImage = $itemImage;
                $productThumb = $productImage !== '' ? image_url($productImage, 'thumb', 150) : '';
              ?>
              <tr>
                <td>
                  <div class="order-item-table-thumb">
                    <?php if ($productImage): ?>
                      <?php $displayThumb = $productThumb !== '' ? $productThumb : $productImage; ?>
                      <img src="<?php echo $esc($displayThumb); ?>" data-thumb-full="<?php echo $esc($productImage); ?>" data-thumb-size="44" alt="<?php echo $esc($item['name'] ?? 'Produto'); ?>" width="44" height="44">
                    <?php else: ?>
                      <span>Sem imagem</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php echo $esc($item['name'] ?? 'Produto'); ?>
                  <?php if ($productSku): ?>
                    <div style="font-size:12px;color:var(--muted);">SKU #<?php echo $productSku; ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo (int) ($item['quantity'] ?? 0); ?></td>
                <td>R$ <?php echo $esc(number_format((float) ($item['total'] ?? 0), 2, ',', '.')); ?></td>
                <td><?php echo $esc($stockLabel); ?></td>
                <?php if ($canManageReturns): ?>
                  <td>
                    <button type="button" class="icon-button" data-return-open data-line-id="<?php echo $lineId; ?>" aria-label="Registrar devolução deste item">
                      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 5V2L7 7l5 5V9c3.31 0 6 2.69 6 6a6 6 0 0 1-6 6H6v-2h6a4 4 0 0 0 0-8z"/>
                      </svg>
                    </button>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
    $canLifecycleUpdate = $canEdit || $canPayment || $canFulfillment;
    $paidEntriesCount = 0;
    if (!empty($paymentsInput)) {
      foreach ($paymentsInput as $paymentEntry) {
        if (!is_array($paymentEntry)) {
          continue;
        }
        $paidFlag = $paymentEntry['paid'] ?? null;
        $isPaidEntry = !in_array(strtolower(trim((string) $paidFlag)), ['0', 'false', 'off', 'nao', 'não'], true);
        if ($isPaidEntry) {
          $paidEntriesCount++;
        }
      }
    }
  ?>

  <div style="margin-top:20px;padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <span class="pill">Pedido: <?php echo $esc($lifecycleOrderStatusLabel); ?></span>
      <span class="pill">Pagamento: <?php echo $esc($lifecyclePaymentStatusLabel); ?></span>
      <span class="pill">Entrega: <?php echo $esc($lifecycleFulfillmentStatusLabel); ?></span>
      <span class="pill"><?php echo $lifecyclePendingCount; ?> pendência(s)</span>
    </div>

    <div class="grid" style="margin-top:12px;">
      <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
        <div class="subtitle">Devido agora</div>
        <div style="font-weight:700;"><?php echo $esc($lifecycleDueNowLabel); ?></div>
      </div>
      <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
        <div class="subtitle">Pago</div>
        <div style="font-weight:700;"><?php echo $esc($lifecyclePaidLabel); ?></div>
      </div>
      <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
        <div class="subtitle">Saldo</div>
        <div style="font-weight:700;"><?php echo $esc($lifecycleBalanceLabel); ?></div>
      </div>
      <div style="padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
        <div class="subtitle">Cobrancas futuras</div>
        <div style="font-weight:700;"><?php echo $esc($lifecycleDueLaterLabel); ?></div>
      </div>
    </div>

    <?php if (!empty($lifecyclePending)): ?>
      <div style="margin-top:10px;padding:10px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
        <strong>Pendências</strong>
        <ul style="margin:8px 0 0 18px;">
          <?php foreach ($lifecyclePending as $pendingItem): ?>
            <li><?php echo $esc((string) ($pendingItem['label'] ?? ($pendingItem['code'] ?? 'Pendência'))); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);">
      <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center;">
        <strong>Ações</strong>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn ghost" href="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>&edit=1#orderPaymentMethod">Registrar pagamento</a>
          <a class="btn ghost" href="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>&edit=1">Editar itens/cobrancas</a>
          <?php if ($canCancel && ($formData['status'] ?? '') !== 'cancelled'): ?>
            <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" onsubmit="return confirm('Cancelar este pedido?');" style="margin:0;">
              <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn ghost" type="submit">Cancelar pedido</button>
            </form>
          <?php endif; ?>
          <?php if ($canEdit && ($formData['status'] ?? '') === 'cancelled'): ?>
            <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin:0;">
              <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="status" value="open">
              <button class="btn ghost" type="submit">Reabrir pedido</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <form id="delivery-lifecycle-form" method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin-top:10px;">
        <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
        <input type="hidden" name="action" value="status">
        <div class="grid">
          <div class="field">
            <label for="sales_channel_lifecycle">Canal de venda</label>
            <select id="sales_channel_lifecycle" name="sales_channel" <?php echo $canEdit ? '' : 'disabled'; ?>>
              <option value="" <?php echo $currentSalesChannel === '' ? 'selected' : ''; ?>>Selecione</option>
              <?php if ($hasInvalidSalesChannel): ?>
                <option value="<?php echo $esc($currentSalesChannel); ?>" selected>Canal inválido: <?php echo $esc($currentSalesChannel); ?></option>
              <?php endif; ?>
              <?php foreach ($salesChannelOptions as $option): ?>
                <option value="<?php echo $esc((string) $option); ?>" <?php echo $currentSalesChannel === (string) $option ? 'selected' : ''; ?>>
                  <?php echo $esc((string) $option); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="fulfillment_status_lifecycle">Status de entrega</label>
            <select id="fulfillment_status_lifecycle" name="fulfillment_status" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
              <?php foreach ($fulfillmentStatusOptions as $fulfillmentKey => $fulfillmentLabel): ?>
                <option value="<?php echo $esc($fulfillmentKey); ?>" <?php echo $formData['fulfillment_status'] === $fulfillmentKey ? 'selected' : ''; ?>>
                  <?php echo $esc($fulfillmentLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="delivery_mode_lifecycle">Entrega</label>
            <select id="delivery_mode_lifecycle" name="delivery_mode" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
              <?php foreach ($deliveryModeOptions as $modeKey => $modeLabel): ?>
                <option value="<?php echo $esc((string) $modeKey); ?>" <?php echo (string) ($formData['delivery_mode'] ?? 'shipment') === (string) $modeKey ? 'selected' : ''; ?>>
                  <?php echo $esc((string) $modeLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" data-lifecycle-shipment-kind-field>
            <label for="shipment_kind_lifecycle">Tipo de envio</label>
            <select id="shipment_kind_lifecycle" name="shipment_kind" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
              <option value="">Selecione</option>
              <?php foreach ($shipmentKindOptions as $kindKey => $kindLabel): ?>
                <option value="<?php echo $esc((string) $kindKey); ?>" <?php echo (string) ($formData['shipment_kind'] ?? '') === (string) $kindKey ? 'selected' : ''; ?>>
                  <?php echo $esc((string) $kindLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" data-lifecycle-bag-field hidden>
            <label for="bag_id_lifecycle">Sacolinha</label>
            <input type="number" min="1" id="bag_id_lifecycle" name="bag_id" value="<?php echo $esc((string) ($formData['bag_id'] ?? '')); ?>" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
          </div>
          <div class="field" data-lifecycle-carrier-field>
            <label for="carrier_id_lifecycle">Transportadora</label>
            <select id="carrier_id_lifecycle" name="carrier_id" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
              <option value="">Selecione</option>
              <?php foreach ($carrierOptions as $carrier): ?>
                <?php $carrierId = (string) ($carrier['id'] ?? ''); ?>
                <option value="<?php echo $esc($carrierId); ?>" <?php echo (string) ($formData['carrier_id'] ?? '') === $carrierId ? 'selected' : ''; ?>>
                  <?php echo $esc((string) ($carrier['name'] ?? '')); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($canFulfillment): ?>
              <div class="subtitle" style="margin-top:6px;"><a href="transportadora-cadastro.php">Cadastrar transportadora</a></div>
            <?php endif; ?>
          </div>
          <div class="field" data-lifecycle-tracking-field>
            <label for="tracking_code_lifecycle">Código de rastreio</label>
            <input type="text" id="tracking_code_lifecycle" name="tracking_code" value="<?php echo $esc($formData['tracking_code']); ?>" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
          </div>
          <div class="field" data-lifecycle-estimated-field>
            <label for="estimated_delivery_at_lifecycle">Previsão de entrega</label>
            <input type="text" id="estimated_delivery_at_lifecycle" name="estimated_delivery_at" value="<?php echo $esc((string) ($formData['estimated_delivery_at'] ?? $formData['shipping_eta'] ?? '')); ?>" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
          </div>
          <div class="field">
            <label for="shipped_at_lifecycle">Data de envio</label>
            <input type="text" id="shipped_at_lifecycle" name="shipped_at" value="<?php echo $esc((string) ($formData['shipped_at'] ?? '')); ?>" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
          </div>
          <div class="field">
            <label for="delivered_at_lifecycle">Data de entrega</label>
            <input type="text" id="delivered_at_lifecycle" name="delivered_at" value="<?php echo $esc((string) ($formData['delivered_at'] ?? '')); ?>" <?php echo $canFulfillment ? '' : 'disabled'; ?>>
          </div>
          <div class="field" style="grid-column:1 / -1;">
            <label for="logistics_notes_lifecycle">Observações logística</label>
            <textarea id="logistics_notes_lifecycle" name="logistics_notes" rows="2" <?php echo $canFulfillment ? '' : 'disabled'; ?>><?php echo $esc((string) ($formData['logistics_notes'] ?? '')); ?></textarea>
          </div>
          <input type="hidden" name="shipping_eta" value="<?php echo $esc((string) ($formData['shipping_eta'] ?? '')); ?>">
        </div>
        <div class="footer">
          <?php if ($canLifecycleUpdate): ?>
            <button class="primary" type="submit">Atualizar entrega/logística</button>
          <?php else: ?>
            <span class="pill">Sem permissão para atualização</span>
          <?php endif; ?>
        </div>
      </form>
      <script>
        (() => {
          const deliveryMode = document.getElementById('delivery_mode_lifecycle');
          const shipmentKind = document.getElementById('shipment_kind_lifecycle');
          const lifecycleForm = document.getElementById('delivery-lifecycle-form');
          const status = document.getElementById('fulfillment_status_lifecycle');
          const shipmentKindField = document.querySelector('[data-lifecycle-shipment-kind-field]');
          const bagField = document.querySelector('[data-lifecycle-bag-field]');
          const carrierField = document.querySelector('[data-lifecycle-carrier-field]');
          const trackingField = document.querySelector('[data-lifecycle-tracking-field]');
          const estimatedField = document.querySelector('[data-lifecycle-estimated-field]');
          const bagInput = document.getElementById('bag_id_lifecycle');
          const carrierInput = document.getElementById('carrier_id_lifecycle');
          const trackingInput = document.getElementById('tracking_code_lifecycle');
          const shippingEta = lifecycleForm ? lifecycleForm.querySelector('input[name="shipping_eta"]') : null;
          const estimatedInput = document.getElementById('estimated_delivery_at_lifecycle');
          if (!deliveryMode) return;

          const modeLabels = <?php echo json_encode($deliveryModeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          const kindLabels = <?php echo json_encode($shipmentKindOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          const apply = () => {
            const mode = deliveryMode.value || 'shipment';
            const isShipment = mode === 'shipment';
            const kind = shipmentKind ? shipmentKind.value : '';
            const isTracked = isShipment && kind === 'tracked';
            const isLocal = isShipment && kind === 'local_courier';
            const isBagDeferred = isShipment && kind === 'bag_deferred';
            const showCarrier = isShipment && (isTracked || isLocal);
            const showTracking = isShipment && (isTracked || isLocal);
            const showEstimated = isShipment && !isBagDeferred;
            const showBag = isBagDeferred;

            if (shipmentKindField) shipmentKindField.hidden = !isShipment;
            if (bagField) bagField.hidden = !showBag;
            if (carrierField) carrierField.hidden = !showCarrier;
            if (trackingField) trackingField.hidden = !showTracking;
            if (estimatedField) estimatedField.hidden = !showEstimated;

            if (shipmentKind && !isShipment) {
              shipmentKind.value = '';
            } else if (shipmentKind && shipmentKind.value === '') {
              shipmentKind.value = 'tracked';
            }

            if (shippingEta && estimatedInput) {
              shippingEta.value = estimatedInput.value || '';
            }

            if (mode === 'immediate_in_hand' && status) {
              status.value = 'delivered';
              if (shipmentKind) shipmentKind.value = '';
              if (bagInput) bagInput.value = '';
              if (carrierInput) carrierInput.value = '';
              if (trackingInput) trackingInput.value = '';
              if (estimatedInput) estimatedInput.value = '';
            }
            if (mode === 'store_pickup') {
              if (shipmentKind) shipmentKind.value = '';
              if (bagInput) bagInput.value = '';
              if (carrierInput) carrierInput.value = '';
              if (trackingInput) trackingInput.value = '';
            }
            if (isBagDeferred) {
              if (carrierInput) carrierInput.value = '';
              if (trackingInput) trackingInput.value = '';
              if (estimatedInput) estimatedInput.value = '';
            }

            if (bagInput) bagInput.required = isBagDeferred;
            if (carrierInput) carrierInput.required = isTracked;
            if (trackingInput) trackingInput.required = isTracked;
          };

          deliveryMode.addEventListener('change', apply);
          if (shipmentKind) {
            shipmentKind.addEventListener('change', apply);
          }
          if (estimatedInput && shippingEta) {
            estimatedInput.addEventListener('input', () => {
              shippingEta.value = estimatedInput.value || '';
            });
          }
          apply();
        })();
      </script>
    </div>

    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);">
      <form id="payment-lifecycle-form" method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin-top:10px;">
        <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
        <input type="hidden" name="action" value="payment">
        <div class="grid">
          <div class="field">
            <label for="payment_status_lifecycle">Situação de pagamento</label>
            <select id="payment_status_lifecycle" name="payment_status" <?php echo ($canPayment || $canEdit) ? '' : 'disabled'; ?>>
              <?php foreach ($paymentStatusOptions as $psKey => $psLabel): ?>
                <option value="<?php echo $esc($psKey); ?>" <?php echo ($formData['payment_status'] ?? 'none') === $psKey ? 'selected' : ''; ?>>
                  <?php echo $esc($psLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="payment_method_title_lifecycle">Método de pagamento</label>
            <select id="payment_method_title_lifecycle" name="payment_method_title" <?php echo ($canPayment || $canEdit) ? '' : 'disabled'; ?>>
              <option value="">Selecione</option>
              <?php foreach ($paymentMethodOptions as $method): ?>
                <?php $methodName = (string) ($method['name'] ?? ''); ?>
                <option value="<?php echo $esc($methodName); ?>" <?php echo (string) ($formData['payment_method_title'] ?? '') === $methodName ? 'selected' : ''; ?>>
                  <?php echo $esc($methodName); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="subtitle" id="payment_method_lifecycle_hint">Obrigatório quando situação for "Pago".</div>
          </div>
        </div>
        <div class="footer">
          <?php if ($canPayment || $canEdit): ?>
            <button class="primary" type="submit">Atualizar pagamento</button>
          <?php else: ?>
            <span class="pill">Sem permissão para atualização</span>
          <?php endif; ?>
        </div>
      </form>
      <script>
        (function() {
          const form = document.getElementById('payment-lifecycle-form');
          const statusSelect = document.getElementById('payment_status_lifecycle');
          const methodSelect = document.getElementById('payment_method_title_lifecycle');
          const hint = document.getElementById('payment_method_lifecycle_hint');
          if (!form || !statusSelect || !methodSelect) return;

          const applyPaymentMethodRule = () => {
            const mustInformMethod = statusSelect.value === 'paid';
            methodSelect.required = mustInformMethod;
            if (mustInformMethod && !methodSelect.value) {
              methodSelect.setCustomValidity('Selecione o método de pagamento/recebimento para marcar como Pago.');
              if (hint) {
                hint.textContent = 'Obrigatório para confirmar o status "Pago".';
              }
              return;
            }

            methodSelect.setCustomValidity('');
            if (hint) {
              hint.textContent = 'Obrigatório quando situação for "Pago".';
            }
          };

          statusSelect.addEventListener('change', applyPaymentMethodRule);
          methodSelect.addEventListener('change', applyPaymentMethodRule);
          form.addEventListener('submit', (event) => {
            applyPaymentMethodRule();
            if (!form.checkValidity()) {
              event.preventDefault();
              form.reportValidity();
            }
          });

          applyPaymentMethodRule();
        })();
      </script>
    </div>

    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);">
      <strong>Linha do tempo</strong>
      <?php if (empty($lifecycleTimeline)): ?>
        <div class="subtitle" style="margin-top:6px;">Sem eventos detalhados para este pedido.</div>
      <?php else: ?>
        <div class="order-layout-list" style="margin-top:8px;">
          <?php foreach ($lifecycleTimeline as $event): ?>
            <?php
              $eventAt = trim((string) ($event['at'] ?? ''));
              $eventDateLabel = '-';
              if ($eventAt !== '') {
                  $parsed = strtotime($eventAt);
                  if ($parsed !== false) {
                      $eventDateLabel = date('d/m/Y H:i', $parsed);
                  } else {
                      $eventDateLabel = $eventAt;
                  }
              }
            ?>
            <div class="order-layout-list-item">
              <span><?php echo $esc((string) ($event['label'] ?? 'Evento')); ?></span>
              <strong><?php echo $esc($eventDateLabel); ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($editing && $canEdit): ?>
    <div style="margin-top:20px;padding:14px;border:1px dashed var(--line);border-radius:14px;background:#fff;">
      <h2 style="margin-top:0;">Consignação</h2>
      <div class="subtitle">Prévia obrigatória antes de gravar créditos/abatimentos.</div>
      <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" style="margin-top:10px;">
        <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
        <input type="hidden" name="action" value="consignment_preview">
        <button class="btn ghost" type="submit">Reprocessar consignação</button>
      </form>

      <?php if (is_array($consignmentPreview)): ?>
        <div class="alert muted" style="margin-top:12px;">
          <strong>Prévia</strong>
          <?php if (!empty($consignmentPreview['messages'])): ?>
            <ul style="margin:6px 0 0 18px;">
              <?php foreach ($consignmentPreview['messages'] as $message): ?>
                <li><?php echo $esc($message); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div>Nenhuma alteração detectada.</div>
          <?php endif; ?>
        </div>

        <?php if (!empty($consignmentPreview['errors'])): ?>
          <div class="alert error">
            <?php echo $esc(implode(' ', $consignmentPreview['errors'])); ?>
          </div>
        <?php endif; ?>

        <form method="post" action="pedido-cadastro.php?id=<?php echo $esc((string) $formData['id']); ?>" onsubmit="return confirm('Aplicar reprocessamento de consignação?');" style="margin-top:10px;">
          <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
          <input type="hidden" name="action" value="consignment_apply">
          <input type="hidden" name="consignment_confirm" value="1">
          <button class="btn primary" type="submit">Aplicar reprocessamento</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php if ($editing && $canManageReturns): ?>
  <script>
    (() => {
      const modal = document.querySelector('[data-return-modal]');
      const backdrop = document.querySelector('[data-return-modal-backdrop]');
      const openButtons = document.querySelectorAll('[data-return-open]');
      const closeButtons = document.querySelectorAll('[data-return-close]');
      const statusSelect = document.getElementById('return_status');
      const trackingField = modal ? modal.querySelector('[data-return-tracking]') : null;
      const expectedField = modal ? modal.querySelector('[data-return-expected]') : null;
      const notesToggleWrap = modal ? modal.querySelector('[data-return-notes-toggle]') : null;
      const notesToggleButton = modal ? modal.querySelector('[data-return-notes-button]') : null;
      const notesField = modal ? modal.querySelector('[data-return-notes]') : null;
      const notesInput = document.getElementById('return_notes');
      const refundAmountInput = document.getElementById('return_refund_amount');
      const refundMethodSelect = document.getElementById('return_refund_method');
      const refundStatusSelect = document.getElementById('return_refund_status');
      const refundStatusLockedInput = document.getElementById('return_refund_status_locked');
      const refundExtraShippingInput = document.getElementById('return_refund_extra_shipping');
      const refundExtraTaxInput = document.getElementById('return_refund_extra_tax');
      const extraButtons = modal ? modal.querySelectorAll('[data-return-extra-toggle]') : [];
      const itemToggles = modal ? modal.querySelectorAll('[data-return-item-toggle]') : [];
      const qtyInputs = modal ? modal.querySelectorAll('[data-return-qty]') : [];
      const returnForm = modal ? modal.querySelector('form') : null;
      const shippingTotal = returnForm ? parseFloat(returnForm.dataset.shippingTotal || '0') : 0;
      const taxTotal = returnForm ? parseFloat(returnForm.dataset.taxTotal || '0') : 0;
      const numberUtils = window.RetratoNumber || {};
      const parseNumber = (value) => {
        if (typeof numberUtils.parse === 'function') {
          const parsed = numberUtils.parse(value);
          return Number.isFinite(parsed) ? parsed : 0;
        }
        const cleaned = String(value ?? '').trim().replace(/[^0-9,.\-+]/g, '');
        const parsed = parseFloat(cleaned.replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : 0;
      };

      const toggleLogistics = () => {
        if (!statusSelect || !trackingField || !expectedField) return;
        const hide = statusSelect.value === 'received';
        trackingField.hidden = hide;
        expectedField.hidden = hide;
      };

      const toggleNotes = (forceOpen) => {
        if (!notesField || !notesToggleWrap || !notesToggleButton) return;
        const shouldOpen = forceOpen !== undefined ? forceOpen : notesField.hidden;
        notesField.hidden = !shouldOpen;
        notesToggleWrap.hidden = shouldOpen;
        if (shouldOpen && notesInput) {
          notesInput.focus();
        }
      };

      const openModal = (lineId) => {
        if (!modal || !backdrop) return;
        modal.hidden = false;
        backdrop.hidden = false;
        toggleLogistics();
        if (notesInput && notesInput.value.trim() !== '') {
          toggleNotes(true);
        }
        toggleRefundStatusLock();
        if (lineId) {
          const toggle = modal.querySelector(`[data-return-item-toggle][data-line-id="${lineId}"]`);
          const qtyInput = modal.querySelector(`[data-return-qty][data-line-id="${lineId}"]`);
          if (toggle && !toggle.disabled) {
            toggle.checked = true;
          }
          if (qtyInput && !qtyInput.disabled) {
            const currentValue = parseInt(qtyInput.value || '0', 10);
            qtyInput.value = currentValue > 0 ? currentValue : 1;
          }
        }
        if (refundAmountInput && refundAmountInput.value !== '') {
          refundAmountInput.dataset.userEdited = 'true';
          updateRefundTotals(true);
        } else {
          updateRefundTotals(false);
        }
      };

      const closeModal = () => {
        if (!modal || !backdrop) return;
        modal.hidden = true;
        backdrop.hidden = true;
      };

      const toggleRefundStatusLock = () => {
        if (!refundMethodSelect || !refundStatusSelect || !refundStatusLockedInput) return;
        const lock = refundMethodSelect.value === 'voucher';
        if (lock) {
          refundStatusSelect.value = 'done';
          refundStatusSelect.disabled = true;
          refundStatusLockedInput.value = 'done';
          refundStatusLockedInput.hidden = false;
        } else {
          refundStatusSelect.disabled = false;
          refundStatusLockedInput.hidden = true;
        }
      };

      const formatMoney = (value) => {
        const safeValue = Number.isFinite(value) ? value : 0;
        if (typeof numberUtils.format === 'function') {
          return numberUtils.format(safeValue, 2);
        }
        return (Math.round(safeValue * 100) / 100).toFixed(2).replace('.', ',');
      };

      const selectedExtrasTotal = () => {
        let extra = 0;
        if (refundExtraShippingInput) {
          extra += parseNumber(refundExtraShippingInput.value || '0');
        }
        if (refundExtraTaxInput) {
          extra += parseNumber(refundExtraTaxInput.value || '0');
        }
        return extra;
      };

      const computeItemsTotal = () => {
        if (!qtyInputs || qtyInputs.length === 0) return 0;
        let total = 0;
        qtyInputs.forEach((input) => {
          const lineId = input.dataset.lineId;
          if (!lineId) return;
          const qty = parseInt(input.value || '0', 10);
          if (qty <= 0) return;
          const unitInput = modal.querySelector(`input[name="items[${lineId}][unit_price]"]`);
          const unitPrice = unitInput ? parseNumber(unitInput.value || '0') : 0;
          total += qty * unitPrice;
        });
        return total;
      };

      // Cancel return button (inside modal) handler
      const cancelBtn = modal ? modal.querySelector('#return-cancel-button') : null;
      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
          if (!confirm('Cancelar esta devolução?')) return;
          const returnId = cancelBtn.dataset.returnId;
          if (!returnId) return;
          // create and submit a small POST form to the cancel endpoint
          const f = document.createElement('form');
          f.method = 'post';
          f.action = 'pedido-devolucao-cancel.php';
          const i = document.createElement('input');
          i.type = 'hidden';
          i.name = 'return_id';
          i.value = returnId;
          f.appendChild(i);
          document.body.appendChild(f);
          f.submit();
        });
      }

      const updateRefundTotals = (respectUserInput = true) => {
        if (!refundAmountInput) return;
        const itemsTotal = computeItemsTotal();
        const extras = selectedExtrasTotal();
        const maxRefund = itemsTotal + extras;
        refundAmountInput.max = formatMoney(maxRefund);
        const userEdited = refundAmountInput.dataset.userEdited === 'true';
        if (!userEdited || !respectUserInput) {
          refundAmountInput.value = formatMoney(itemsTotal + extras);
          refundAmountInput.dataset.userEdited = 'false';
          return;
        }
        const current = parseNumber(refundAmountInput.value || '0');
        if (current > maxRefund) {
          refundAmountInput.value = formatMoney(maxRefund);
        }
      };

      openButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const lineId = button.dataset.lineId ? parseInt(button.dataset.lineId, 10) : null;
          openModal(lineId);
        });
      });

      closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
      });

      if (backdrop) {
        backdrop.addEventListener('click', closeModal);
      }

      if (statusSelect) {
        statusSelect.addEventListener('change', toggleLogistics);
        toggleLogistics();
      }

      if (notesToggleButton) {
        notesToggleButton.addEventListener('click', () => toggleNotes(true));
      }

      if (refundMethodSelect) {
        refundMethodSelect.addEventListener('change', toggleRefundStatusLock);
        toggleRefundStatusLock();
      }

      if (refundAmountInput) {
        refundAmountInput.addEventListener('input', () => {
          refundAmountInput.dataset.userEdited = 'true';
          updateRefundTotals(true);
        });
      }

      extraButtons.forEach((button) => {
        const type = button.dataset.extraType;
        if (type === 'shipping' && shippingTotal <= 0) {
          button.disabled = true;
        }
        if (type === 'tax' && taxTotal <= 0) {
          button.disabled = true;
        }
        button.addEventListener('click', () => {
          const isActive = button.dataset.active === 'true';
          if (type === 'shipping' && refundExtraShippingInput) {
            refundExtraShippingInput.value = isActive ? formatMoney(0) : formatMoney(shippingTotal);
          }
          if (type === 'tax' && refundExtraTaxInput) {
            refundExtraTaxInput.value = isActive ? formatMoney(0) : formatMoney(taxTotal);
          }
          button.dataset.active = isActive ? 'false' : 'true';
          button.classList.toggle('active', !isActive);
          updateRefundTotals(true);
        });
      });

      itemToggles.forEach((toggle) => {
        toggle.addEventListener('change', () => {
          const lineId = toggle.dataset.lineId;
          if (!lineId) return;
          const qtyInput = modal.querySelector(`[data-return-qty][data-line-id="${lineId}"]`);
          if (!qtyInput || qtyInput.disabled) return;
          if (toggle.checked) {
            const currentValue = parseInt(qtyInput.value || '0', 10);
            qtyInput.value = currentValue > 0 ? currentValue : 1;
          } else {
            qtyInput.value = 0;
          }
          updateRefundTotals(true);
        });
      });

      qtyInputs.forEach((input) => {
        input.addEventListener('input', () => {
          const lineId = input.dataset.lineId;
          if (!lineId) return;
          const toggle = modal.querySelector(`[data-return-item-toggle][data-line-id="${lineId}"]`);
          if (!toggle) return;
          const value = parseInt(input.value || '0', 10);
          toggle.checked = value > 0;
          updateRefundTotals(true);
        });
      });

      const shouldOpenOnLoad = <?php echo !empty($orderReturnErrors) ? 'true' : 'false'; ?>;
      if (shouldOpenOnLoad) {
        openModal();
      }
    })();
  </script>
<?php endif; ?>

<?php if ($showEditForm): ?>
  <script>
    const isFullEdit = <?php echo $fullEdit ? 'true' : 'false'; ?>;
    const customerOptions = <?php echo json_encode(array_column($customerOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const customerInput = document.getElementById('pessoa_id');
    const customerPicker = document.querySelector('[data-customer-picker]');
    const customerSuggestions = document.getElementById('customerSuggestions');
    const customerOptionsList = document.getElementById('customerOptions');
  const customerSelectedLabel = document.getElementById('customerSelectedLabel');
    const customerLabelText = document.querySelector('[data-customer-label-text]');
    const customerEditButton = document.querySelector('[data-customer-edit]');
    const newCustomerButton = document.querySelector('[data-new-customer]');
    const salesChannelSelect = document.getElementById('sales_channel');
    const deliveryModeOptions = <?php echo json_encode($deliveryModeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const shipmentKindOptions = <?php echo json_encode($shipmentKindOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const deliveryModeSelect = document.getElementById('delivery_mode_select');
    const shipmentKindSelect = document.getElementById('shipment_kind_select');
    const deliveryModeInput = document.getElementById('delivery_mode');
    const shipmentKindInput = document.getElementById('shipment_kind');
    const shippingTotalInput = document.getElementById('shipping_total');
    const carrierCreateField = document.querySelector('[data-create-carrier-field]');
    const trackingCreateField = document.querySelector('[data-create-tracking-field]');
    const estimatedCreateField = document.querySelector('[data-create-estimated-field]');
    const estimatedCreateInput = document.getElementById('estimated_delivery_at_create');
    const shipmentKindField = document.querySelector('[data-create-shipment-kind-field]');
    const bagCreateField = document.querySelector('[data-create-bag-field]');
    const bagIdCreateInput = document.getElementById('bag_id_create');
    const bagCreateHint = document.getElementById('bagCreateHint');
    const bagCreateSummary = document.getElementById('bagCreateSummary');
    const openBagFeePayNowField = document.querySelector('[data-open-bag-fee-pay-now-field]');
    const openBagFeePayNowInput = document.getElementById('open_bag_fee_pay_now');
    const openBagFeePayNowLabel = document.getElementById('openBagFeePayNowLabel');
    const openBagFeePayNowHint = document.getElementById('openBagFeePayNowHint');
    const carrierCreateInput = document.getElementById('carrier_id_create');
    const trackingCreateInput = document.getElementById('tracking_code_create');
    const deliveryNote = document.getElementById('deliveryNote');
  const orderModeInput = document.getElementById('order_mode');
  const orderModeButtons = document.querySelectorAll('[data-order-mode]');
  const onlineFields = document.querySelector('[data-online-fields]');
  const pdvSummary = document.getElementById('pdvSummary');
  const pdvChannelLabel = <?php echo json_encode($pdvChannelLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const pdvDeliveryLabel = <?php echo json_encode($pdvDeliveryLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const mobileOrderMode = document.querySelector('[data-mobile-order-mode]');
  const mobileOnlineFields = document.querySelector('[data-mobile-online-fields]');
  const mobilePdvSummary = document.querySelector('[data-mobile-pdv-summary]');
  const isMobileViewport = window.matchMedia('(max-width: 768px)');
  const isNarrowWizardViewport = window.matchMedia('(max-width: 720px)');
  const layoutRoot = document.getElementById('orderLayoutRoot');
  const layoutSwitcher = document.getElementById('layoutSwitcher');
  const orderSections = Array.from(document.querySelectorAll('[data-order-section]'));
  const stepStatusNodes = Array.from(document.querySelectorAll('[data-step-status]'));
  const sidebarTriggers = Array.from(document.querySelectorAll('[data-sidebar-trigger]'));
  const layoutSidebarPanel = document.querySelector('[data-sidebar-panel]');
  const layoutNames = ['layout-1', 'layout-2', 'layout-3', 'layout-4', 'layout-5'];
  const layoutStorageKey = 'order.create.layout';
  const sectionStateStorageKey = 'order.create.section.state';
  let currentLayout = 'layout-1';
  let mobileOrderModeSelected = false;
    const openingFeeDeferredField = document.querySelector('[data-opening-fee-deferred-field]');
    const openingFeeDeferredInput = document.querySelector('input[name="opening_fee_deferred"]');
    const openingFeeFromForm = <?php echo json_encode((float) ($formData['opening_fee_value'] ?? 0), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const openingFeeDefault = <?php echo json_encode((float) $openingFeeDefault, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let applyOrderDefaults = () => {};
    let ensuringOpenBagPersonId = 0;
    let ensureOpenBagFailedPersonId = 0;

    if (openingFeeDeferredField) {
      openingFeeDeferredField.hidden = true;
      openingFeeDeferredField.style.display = 'none';
    }
    if (openBagFeePayNowField) {
      openBagFeePayNowField.hidden = true;
      openBagFeePayNowField.style.display = 'none';
    }
    if (isFullEdit) {
      if (salesChannelSelect) {
        salesChannelSelect.dataset.userEdited = 'true';
      }
      if (deliveryModeSelect) {
        deliveryModeSelect.dataset.userEdited = 'true';
      }
      if (shipmentKindSelect) {
        shipmentKindSelect.dataset.userEdited = 'true';
      }
      if (shippingTotalInput) {
        shippingTotalInput.dataset.userEdited = 'true';
      }
    }

    const setValue = (id, value) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.value = value === null || value === undefined ? '' : value;
    };

    const setChecked = (name, checked) => {
      const el = document.querySelector(`input[name="${name}"]`);
      if (!el) return;
      el.checked = checked;
    };

    let pendingShippingAfterBilling = false;

    if (customerEditButton) {
      customerEditButton.addEventListener('click', () => {
        if (typeof openAddressModal === 'function') {
          pendingShippingAfterBilling = true;
          openAddressModal('billing');
        }
      });
    }

    const isPdvMode = () => orderModeInput && orderModeInput.value === 'pdv';
  let initialOrderMode = orderModeInput ? orderModeInput.value : 'online';

    const normalizeSearch = (value) => {
      let normalized = String(value || '').toLowerCase().trim();
      if (typeof normalized.normalize === 'function') {
        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }
      normalized = normalized.replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
      return normalized;
    };

    const normalizeName = (value) => normalizeSearch(value);

    const isNameSimilar = (a, b) => {
      const left = normalizeName(a);
      const right = normalizeName(b);
      if (!left || !right) return true;
      return left === right || left.includes(right) || right.includes(left);
    };

    const buildSearchTokens = (value) => {
      const normalized = normalizeSearch(value);
      return normalized ? normalized.split(' ') : [];
    };

    const matchesSearchTokens = (tokens, searchValue) => {
      if (!tokens.length) return true;
      return tokens.every((token) => searchValue.includes(token));
    };

    const normalizeLabel = (value) => {
      let normalized = String(value || '').toLowerCase().trim();
      if (!normalized) return '';
      if (typeof normalized.normalize === 'function') {
        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }
      return normalized.replace(/\s+/g, ' ').trim();
    };

    const cachedOnlineState = {
      salesChannel: salesChannelSelect ? salesChannelSelect.value : '',
      deliveryMode: deliveryModeSelect ? deliveryModeSelect.value : '',
      shipmentKind: shipmentKindSelect ? shipmentKindSelect.value : '',
      shippingTotal: shippingTotalInput ? shippingTotalInput.value : '',
    };

    const updateMobileOrderModeUI = (mode) => {
      if (!mobileOnlineFields && !mobilePdvSummary) return;
      const isMobile = isMobileViewport && isMobileViewport.matches;
      if (!isMobile) {
        if (mobileOnlineFields) {
          mobileOnlineFields.hidden = false;
          mobileOnlineFields.classList.remove('order-mode--hidden');
        }
        if (mobilePdvSummary) {
          mobilePdvSummary.hidden = true;
          mobilePdvSummary.textContent = '';
        }
        return;
      }

      const normalized = mode === 'pdv' ? 'pdv' : 'online';
      const showOnline = mobileOrderModeSelected && normalized === 'online';
      const showPdv = mobileOrderModeSelected && normalized === 'pdv';

      if (mobileOnlineFields) {
        mobileOnlineFields.hidden = !showOnline;
        mobileOnlineFields.classList.toggle('order-mode--hidden', !showOnline);
      }

      if (mobilePdvSummary) {
        if (showPdv) {
          mobilePdvSummary.hidden = false;
          mobilePdvSummary.textContent = `Canal de vendas: ${pdvChannelLabel} • Entrega: ${pdvDeliveryLabel} • Frete: R$ 0,00`;
        } else {
          mobilePdvSummary.hidden = true;
          mobilePdvSummary.textContent = '';
        }
      }
    };

    if (isMobileViewport) {
      isMobileViewport.addEventListener('change', () => {
        const currentMode = orderModeInput ? orderModeInput.value : 'online';
        updateMobileOrderModeUI(currentMode);
      });
    }

    const clearNode = (node) => {
      if (!node) return;
      while (node.firstChild) {
        node.removeChild(node.firstChild);
      }
    };

    const filterSuggestions = (items, tokens, includeAllWhenEmpty = true) => {
      if (!tokens.length) {
        return includeAllWhenEmpty ? items : [];
      }
      return items.filter((item) => matchesSearchTokens(tokens, item.search));
    };

    const buildCustomerSelectedLabel = (customer) => {
      if (!customer) return null;

      const name = (customer.shipping_full_name || customer.full_name || customer.name || 'Cliente').trim();
      const email = (customer.email || '').trim();

      const shippingLine1 = (customer.shipping_address_1 || customer.street || '').trim();
      const shippingNumber = (customer.shipping_number || customer.number || '').trim();
      const shippingLine2 = (customer.shipping_address_2 || customer.street2 || '').trim();
      const shippingNeighborhood = (customer.shipping_neighborhood || customer.neighborhood || '').trim();
      const shippingCity = (customer.shipping_city || customer.city || '').trim();
      const shippingState = (customer.shipping_state || customer.state || '').trim();
      const shippingPostcode = (customer.shipping_postcode || customer.zip || '').trim();
      const shippingCountry = (customer.shipping_country || customer.country || '').trim();

      const addressParts = [];
      if (shippingLine1) {
        addressParts.push(shippingNumber ? `${shippingLine1}, ${shippingNumber}` : shippingLine1);
      }
      if (shippingLine2) {
        addressParts.push(shippingLine2);
      }
      if (shippingNeighborhood) {
        addressParts.push(shippingNeighborhood);
      }
      const cityState = [shippingCity, shippingState].filter(Boolean).join(' - ');
      if (cityState) {
        addressParts.push(cityState);
      }
      const zipCountry = [shippingPostcode, shippingCountry].filter(Boolean).join(' · ');
      if (zipCountry) {
        addressParts.push(zipCountry);
      }

      const parts = [];
      if (name) parts.push(name);
      if (email) parts.push(email);
      if (addressParts.length) parts.push(addressParts.join('\n'));

      return parts.length ? parts.join('\n') : null;
    };

    const updateCustomerSelectedLabel = () => {
      if (!customerSelectedLabel || !customerInput) return;
      const raw = String(customerInput.value || '').trim();
      if (!raw) {
        if (customerLabelText) customerLabelText.textContent = 'Informe um ID de cliente.';
        if (customerEditButton) customerEditButton.hidden = true;
        return;
      }
      const id = parseInt(raw, 10);
      const customer = Number.isFinite(id) ? customerOptions[id] : null;
      if (customer) {
        const label = buildCustomerSelectedLabel(customer);
        if (customerLabelText) {
          customerLabelText.textContent = label || (customerSelectedLabel.dataset.fallbackLabel || 'Cliente nao encontrado.');
        }
        if (customerEditButton) customerEditButton.hidden = false;
        return;
      }
      const fallback = customerSelectedLabel.dataset.fallbackLabel || '';
      if (customerLabelText) customerLabelText.textContent = fallback !== '' ? fallback : 'Cliente nao encontrado.';
      if (customerEditButton) customerEditButton.hidden = true;
    };

    const getSelectedCustomer = () => {
      if (!customerInput) return null;
      const key = customerInput.value.trim();
      if (!key || !Object.prototype.hasOwnProperty.call(customerOptions, key)) {
        return null;
      }
      return customerOptions[key];
    };

    const addressSummaryNodes = {
      billing: document.querySelector('[data-summary="billing"]'),
      shipping: document.querySelector('[data-summary="shipping"]'),
    };
    const shippingSameCheckbox = document.querySelector('input[name="shipping_same_as_billing"]');
    const shippingSameLabel = document.querySelector('[data-shipping-same-label]');
    const shippingSameHint = document.querySelector('[data-shipping-same-hint]');
    const billingTitleName = document.getElementById('billingTitleName');

    const readValue = (id) => {
      const el = document.getElementById(id);
      return el ? String(el.value || '').trim() : '';
    };

    const buildSummaryLine = (text, className) => {
      const line = document.createElement('div');
      line.className = className ? `address-summary__line ${className}` : 'address-summary__line';
      line.textContent = text;
      return line;
    };

    const renderAddressSummary = (section) => {
      const summary = addressSummaryNodes[section];
      if (!summary) return;
      clearNode(summary);

      const values = {
        full_name: readValue(`${section}_full_name`),
        email: section === 'billing' ? readValue('billing_email') : '',
        phone: readValue(`${section}_phone`),
        address_1: readValue(`${section}_address_1`),
        address_2: readValue(`${section}_address_2`),
        number: readValue(`${section}_number`),
        neighborhood: readValue(`${section}_neighborhood`),
        city: readValue(`${section}_city`),
        state: readValue(`${section}_state`),
        postcode: readValue(`${section}_postcode`),
        country: readValue(`${section}_country`),
      };

      const lines = [];
      if (values.full_name && section !== 'billing') {
        lines.push(buildSummaryLine(values.full_name, 'address-summary__name'));
      }
      if (values.email) lines.push(buildSummaryLine(values.email));
      if (values.phone) lines.push(buildSummaryLine(values.phone));

      const addressLine = [values.address_1, values.number].filter(Boolean).join(', ');
      if (addressLine) lines.push(buildSummaryLine(addressLine));
      if (values.address_2) lines.push(buildSummaryLine(values.address_2));
      if (values.neighborhood) lines.push(buildSummaryLine(values.neighborhood));

      const cityParts = [values.city, values.state].filter(Boolean);
      const cityLine = cityParts.join(' / ');
      const postcodeLine = values.postcode ? `CEP ${values.postcode}` : '';
      if (cityLine || postcodeLine) {
        const combined = [cityLine, postcodeLine].filter(Boolean).join(' - ');
        lines.push(buildSummaryLine(combined));
      }

      if (values.country) lines.push(buildSummaryLine(values.country));

      if (!lines.length) {
        const empty = document.createElement('div');
        empty.className = 'address-summary__empty';
        empty.textContent = section === 'billing' ? 'Sem dados de cobranca.' : 'Sem dados de envio.';
        summary.appendChild(empty);
        return;
      }

      lines.forEach((line) => summary.appendChild(line));
    };

    const updateAddressSummaries = () => {
      if (billingTitleName) {
        const billingName = readValue('billing_full_name') || readValue('shipping_full_name');
        billingTitleName.textContent = billingName || 'Endereço de envio';
      }
      renderAddressSummary('shipping');
      updateDeliveryPricing(false);
      applyOrderDefaults();
    };

    const updateShippingSameCopy = () => {
      if (!shippingSameCheckbox) return;
      const enabled = shippingSameCheckbox.checked;
      if (shippingSameLabel) {
        shippingSameLabel.textContent = enabled
          ? 'Usando os mesmos dados da cobranca'
          : 'Usar os mesmos dados da cobranca';
      }
      if (shippingSameHint) {
        shippingSameHint.textContent = enabled
          ? 'Ativo: alteracoes na cobranca atualizam o endereco de envio automaticamente.'
          : 'Copia nome, telefone e endereco da cobranca para o envio.';
      }
    };

    const syncShippingWithBilling = () => {
      if (!shippingSameCheckbox || !shippingSameCheckbox.checked) {
        return;
      }
      setValue('shipping_full_name', readValue('billing_full_name'));
      setValue('shipping_phone', readValue('billing_phone'));
      setValue('shipping_address_1', readValue('billing_address_1'));
      setValue('shipping_address_2', readValue('billing_address_2'));
      setValue('shipping_number', readValue('billing_number'));
      setValue('shipping_neighborhood', readValue('billing_neighborhood'));
      setValue('shipping_city', readValue('billing_city'));
      setValue('shipping_state', readValue('billing_state'));
      setValue('shipping_postcode', readValue('billing_postcode'));
      setValue('shipping_country', readValue('billing_country'));
      renderAddressSummary('shipping');
      updateDeliveryPricing(false);
    };

    const fillFromCustomer = () => {
      if (!customerInput) return;
      const key = customerInput.value.trim();
      if (!key || !Object.prototype.hasOwnProperty.call(customerOptions, key)) {
        return;
      }

      const customer = customerOptions[key];
      const billingFullName = customer.full_name || customer.name || '';
      const billingEmail = customer.email || '';
      const billingPhone = customer.phone || '';
      const billingAddress1 = customer.street || '';
      const billingAddress2 = customer.street2 || '';
      const billingNumber = customer.number || '';
      const billingNeighborhood = customer.neighborhood || '';
      const billingCity = customer.city || '';
      const billingState = customer.state || '';
      const billingPostcode = customer.zip || '';
      const billingCountry = customer.country || '';

      setValue('billing_full_name', billingFullName);
      setValue('billing_email', billingEmail);
      setValue('billing_phone', billingPhone);
      setValue('billing_address_1', billingAddress1);
      setValue('billing_address_2', billingAddress2);
      setValue('billing_number', billingNumber);
      setValue('billing_neighborhood', billingNeighborhood);
      setValue('billing_city', billingCity);
      setValue('billing_state', billingState);
      setValue('billing_postcode', billingPostcode);
      setValue('billing_country', billingCountry);

      const shippingFullName = customer.shipping_full_name || billingFullName;
      const shippingAddress1 = customer.shipping_address_1 || billingAddress1;
      const shippingAddress2 = customer.shipping_address_2 || billingAddress2;
      const shippingNumber = customer.shipping_number || billingNumber;
      const shippingNeighborhood = customer.shipping_neighborhood || billingNeighborhood;
      const shippingCity = customer.shipping_city || billingCity;
      const shippingState = customer.shipping_state || billingState;
      const shippingPostcode = customer.shipping_postcode || billingPostcode;
      const shippingCountry = customer.shipping_country || billingCountry;
      const hasShippingData = Boolean(
        customer.shipping_full_name ||
          customer.shipping_address_1 ||
          customer.shipping_address_2 ||
          customer.shipping_number ||
          customer.shipping_neighborhood ||
          customer.shipping_city ||
          customer.shipping_state ||
          customer.shipping_postcode ||
          customer.shipping_country
      );

      setValue('shipping_full_name', shippingFullName);
      setValue('shipping_phone', billingPhone);
      setValue('shipping_address_1', shippingAddress1);
      setValue('shipping_address_2', shippingAddress2);
      setValue('shipping_number', shippingNumber);
      setValue('shipping_neighborhood', shippingNeighborhood);
      setValue('shipping_city', shippingCity);
      setValue('shipping_state', shippingState);
      setValue('shipping_postcode', shippingPostcode);
      setValue('shipping_country', shippingCountry);
      setChecked('shipping_same_as_billing', !hasShippingData);
      updateShippingSameCopy();
      updateAddressSummaries();
    };

    const resolveCustomerName = (customer) => {
      if (!customer) return '';
      const name = String(
        customer.shipping_full_name
          || customer.full_name
          || customer.name
          || ''
      ).trim();
      return name;
    };

    const resolveCustomerState = (customer) => {
      if (!customer) return '';
      return String(customer.shipping_state || customer.state || '').trim();
    };

    const resolveCustomerEmail = (customer) => {
      if (!customer) return '';
      return String(customer.email || customer.shipping_email || '').trim();
    };

    const resolveCustomerPhone = (customer) => {
      if (!customer) return '';
      return String(customer.phone || customer.shipping_phone || '').trim();
    };

    const buildCustomerEntry = (id, customer) => {
      const resolvedId = String((customer && customer.id) ? customer.id : id).trim();
      const name = resolveCustomerName(customer);
      const email = resolveCustomerEmail(customer);
      const phone = resolveCustomerPhone(customer);
      const phoneDigits = phone.replace(/\D/g, '');
      const state = resolveCustomerState(customer);
      return {
        id: resolvedId,
        name,
        email,
        phone,
        state,
        search: normalizeSearch(`${resolvedId} ${name} ${email} ${phone} ${phoneDigits} ${state}`),
      };
    };

    let customerList = Object.entries(customerOptions || {}).map(([id, customer]) => buildCustomerEntry(id, customer))
      .filter((customer) => customer.id !== '');

    const supportsDatalist = () => {
      const input = document.createElement('input');
      return 'list' in input && !!window.HTMLDataListElement;
    };

    const isIOSDevice = /iPad|iPhone|iPod/.test(navigator.userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const hasCoarsePointer = typeof window.matchMedia === 'function'
      && window.matchMedia('(pointer: coarse)').matches;
    const isTouchDevice = (navigator.maxTouchPoints || 0) > 0 || hasCoarsePointer;
    const useCustomSuggestions = !supportsDatalist() || isIOSDevice || isTouchDevice;
    const useCustomProductSuggestions = true;
    const useCustomCustomerSuggestions = true;

    const closeAllSuggestions = () => {
      document.querySelectorAll('.autocomplete-suggestions').forEach((list) => {
        list.hidden = true;
      });
      document.querySelectorAll('.autocomplete-input').forEach((input) => {
        input.setAttribute('aria-expanded', 'false');
      });
    };

    const bindSuggestionSelect = (button, onSelect) => {
      let handled = false;
      let touchStart = null;

      const trigger = () => {
        if (handled) return;
        handled = true;
        onSelect();
        setTimeout(() => {
          handled = false;
        }, 0);
      };

      button.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch') return;
        touchStart = { x: event.clientX, y: event.clientY };
      });

      button.addEventListener('pointerup', (event) => {
        if (event.pointerType !== 'touch') return;
        if (!touchStart) return;
        const moved = Math.abs(event.clientX - touchStart.x) > 8
          || Math.abs(event.clientY - touchStart.y) > 8;
        touchStart = null;
        if (moved) return;
        event.preventDefault();
        trigger();
      });

      button.addEventListener('click', (event) => {
        if (handled) {
          event.preventDefault();
          return;
        }
        trigger();
      });
    };

    const buildCustomerParts = (customer) => {
      const name = resolveCustomerName(customer);
      const email = resolveCustomerEmail(customer);
      const state = resolveCustomerState(customer);
      const parts = [`#${customer.id}`];
      if (name) parts.push(name);
      if (email) parts.push(email);
      if (state) parts.push(state);
      return parts;
    };

    const buildCustomerLabel = (customer) => buildCustomerParts(customer).join(' - ');

    const buildCustomerOptionLabel = (customer) => buildCustomerParts(customer).join(' - ');

    const upsertCustomerOptionNode = (customer) => {
      if (!customerOptionsList) return;
      const id = String(customer.id || '').trim();
      if (!id) return;
      const label = buildCustomerOptionLabel(customer);
      let option = customerOptionsList.querySelector(`option[value="${id}"]`);
      if (!option) {
        option = document.createElement('option');
        option.value = id;
        customerOptionsList.appendChild(option);
      }
      option.label = label;
      option.textContent = label;
    };

    const upsertCustomerList = (customer) => {
      const entry = buildCustomerEntry(customer.id || '', customer);
      if (!entry.id) return;
      const index = customerList.findIndex((item) => item.id === entry.id);
      if (index >= 0) {
        customerList[index] = entry;
      } else {
        customerList.push(entry);
      }
    };

    const applyCustomerUpdate = (customer, selectCustomerId = false) => {
      if (!customer) return;
      const id = String(customer.id || '').trim();
      if (!id) return;
      customerOptions[id] = customer;
      upsertCustomerList(customer);
      upsertCustomerOptionNode(customer);
      if (selectCustomerId && customerInput) {
        customerInput.value = id;
        fillFromCustomer();
        customerInput.dispatchEvent(new Event('input', { bubbles: true }));
        if (useCustomCustomerSuggestions) {
          hideCustomerSuggestions();
        }
      }
    };

    const hideCustomerSuggestions = () => {
      if (!useCustomCustomerSuggestions) return;
      customerSuggestions.hidden = true;
      customerInput.setAttribute('aria-expanded', 'false');
    };

    const selectCustomer = (customerId) => {
      if (!customerInput) return;
      customerInput.value = customerId;
      fillFromCustomer();
      customerInput.dispatchEvent(new Event('input', { bubbles: true }));
      hideCustomerSuggestions();
    };

    const renderCustomerSuggestions = (value) => {
      if (!useCustomCustomerSuggestions) return;
      closeAllSuggestions();
      clearNode(customerSuggestions);
      const tokens = buildSearchTokens(value);
      const filtered = filterSuggestions(customerList, tokens, true);
      if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'customer-suggestion customer-suggestion--empty autocomplete-suggestion autocomplete-suggestion--empty';
        empty.textContent = 'Nenhum cliente encontrado.';
        customerSuggestions.appendChild(empty);
      } else {
        const fragment = document.createDocumentFragment();
        filtered.forEach((c) => {
          // Build label inline: #id - nome - email - estado
          // c is a buildCustomerEntry result with .id .name .email .state
          const labelParts = ['#' + c.id];
          if (c.name) labelParts.push(c.name);
          if (c.email && c.email !== c.name) labelParts.push(c.email);
          if (c.state) labelParts.push(c.state);
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'customer-suggestion autocomplete-suggestion';
          button.textContent = labelParts.join(' - ');
          bindSuggestionSelect(button, () => selectCustomer(c.id));
          fragment.appendChild(button);
        });
        customerSuggestions.appendChild(fragment);
      }
      customerSuggestions.hidden = false;
      customerInput.setAttribute('aria-expanded', 'true');
    };

    if (customerInput) {
      customerInput.addEventListener('change', () => {
        ensureOpenBagFailedPersonId = 0;
        fillFromCustomer();
        updateCustomerSelectedLabel();
        if (useCustomCustomerSuggestions) {
          hideCustomerSuggestions();
        }
        syncLayoutState();
      });
      customerInput.addEventListener('input', () => {
        ensureOpenBagFailedPersonId = 0;
        fillFromCustomer();
        updateCustomerSelectedLabel();
        if (useCustomCustomerSuggestions) {
          renderCustomerSuggestions(customerInput.value);
        }
        syncLayoutState();
      });
      updateCustomerSelectedLabel();
    }

    if (shippingSameCheckbox) {
      shippingSameCheckbox.addEventListener('change', () => {
        updateShippingSameCopy();
        if (shippingSameCheckbox.checked) {
          syncShippingWithBilling();
        }
      });
    }

    if (useCustomCustomerSuggestions) {
      customerInput.removeAttribute('list');
      customerInput.classList.add('autocomplete-input');
      customerInput.setAttribute('autocomplete', 'off');
      customerInput.setAttribute('aria-autocomplete', 'list');
      customerInput.setAttribute('aria-expanded', 'false');
      customerInput.addEventListener('focus', () => renderCustomerSuggestions(customerInput.value));
      customerInput.addEventListener('click', () => renderCustomerSuggestions(customerInput.value));
      customerInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          hideCustomerSuggestions();
        }
      });
    }

    if (useCustomSuggestions || useCustomProductSuggestions) {
      document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-picker')) {
          closeAllSuggestions();
        }
      });
    }

    const addressModal = document.querySelector('[data-address-modal]');
    const addressModalBackdrop = document.querySelector('[data-modal-backdrop]');
    const addressModalTitle = document.querySelector('[data-modal-title]');
    const addressModalSubtitle = document.querySelector('[data-modal-subtitle]');
    const addressModalError = document.querySelector('[data-modal-error]');
    const addressModalSave = document.querySelector('[data-modal-save]');
    const addressModalCancel = document.querySelector('[data-modal-cancel]');
    const addressModalClose = document.querySelector('[data-modal-close]');
    const addressModalEmailField = document.querySelector('[data-modal-email-field]');

    const addressModalFields = {
      fullName: document.getElementById('address_full_name'),
      email: document.getElementById('address_email'),
      phone: document.getElementById('address_phone'),
      address1: document.getElementById('address_address_1'),
      number: document.getElementById('address_number'),
      address2: document.getElementById('address_address_2'),
      neighborhood: document.getElementById('address_neighborhood'),
      city: document.getElementById('address_city'),
      state: document.getElementById('address_state'),
      postcode: document.getElementById('address_postcode'),
      country: document.getElementById('address_country'),
    };

    let activeAddressSection = '';

    const openAddressModal = (section) => {
      if (!addressModal || !addressModalBackdrop) return;
      activeAddressSection = section;
      if (addressModalTitle) {
        addressModalTitle.textContent = section === 'billing'
          ? 'Editar dados de cobranca'
          : 'Editar dados de envio';
      }
      if (addressModalSubtitle) {
        addressModalSubtitle.textContent = customerInput && customerInput.value.trim() !== ''
          ? 'Atualiza o cadastro do cliente.'
          : 'Preenche os dados usados neste pedido.';
      }
      if (addressModalEmailField) {
        addressModalEmailField.hidden = section !== 'billing';
      }
      if (addressModalError) {
        addressModalError.hidden = true;
        addressModalError.textContent = '';
      }

      if (addressModalFields.fullName) {
        addressModalFields.fullName.value = readValue(`${section}_full_name`);
      }
      if (addressModalFields.email) {
        addressModalFields.email.value = section === 'billing' ? readValue('billing_email') : '';
      }
      if (addressModalFields.phone) {
        addressModalFields.phone.value = readValue(`${section}_phone`);
      }
      if (addressModalFields.address1) {
        addressModalFields.address1.value = readValue(`${section}_address_1`);
      }
      if (addressModalFields.number) {
        addressModalFields.number.value = readValue(`${section}_number`);
      }
      if (addressModalFields.address2) {
        addressModalFields.address2.value = readValue(`${section}_address_2`);
      }
      if (addressModalFields.neighborhood) {
        addressModalFields.neighborhood.value = readValue(`${section}_neighborhood`);
      }
      if (addressModalFields.city) {
        addressModalFields.city.value = readValue(`${section}_city`);
      }
      if (addressModalFields.state) {
        addressModalFields.state.value = readValue(`${section}_state`);
      }
      if (addressModalFields.postcode) {
        addressModalFields.postcode.value = readValue(`${section}_postcode`);
      }
      if (addressModalFields.country) {
        addressModalFields.country.value = readValue(`${section}_country`);
      }

      addressModal.hidden = false;
      addressModalBackdrop.hidden = false;
      document.body.classList.add('modal-open');
    };

    const closeAddressModal = () => {
      if (!addressModal || !addressModalBackdrop) return;
      addressModal.hidden = true;
      addressModalBackdrop.hidden = true;
      document.body.classList.remove('modal-open');
      activeAddressSection = '';
    };

    const collectAddressModalValues = () => ({
      full_name: addressModalFields.fullName ? addressModalFields.fullName.value.trim() : '',
      email: addressModalFields.email ? addressModalFields.email.value.trim() : '',
      phone: addressModalFields.phone ? addressModalFields.phone.value.trim() : '',
      address_1: addressModalFields.address1 ? addressModalFields.address1.value.trim() : '',
      address_2: addressModalFields.address2 ? addressModalFields.address2.value.trim() : '',
      number: addressModalFields.number ? addressModalFields.number.value.trim() : '',
      neighborhood: addressModalFields.neighborhood ? addressModalFields.neighborhood.value.trim() : '',
      city: addressModalFields.city ? addressModalFields.city.value.trim() : '',
      state: addressModalFields.state ? addressModalFields.state.value.trim() : '',
      postcode: addressModalFields.postcode ? addressModalFields.postcode.value.trim() : '',
      country: addressModalFields.country ? addressModalFields.country.value.trim() : '',
    });

    const applyAddressValues = (section, values) => {
      setValue(`${section}_full_name`, values.full_name);
      if (section === 'billing') {
        setValue('billing_email', values.email);
      }
      setValue(`${section}_phone`, values.phone);
      setValue(`${section}_address_1`, values.address_1);
      setValue(`${section}_address_2`, values.address_2);
      setValue(`${section}_number`, values.number);
      setValue(`${section}_neighborhood`, values.neighborhood);
      setValue(`${section}_city`, values.city);
      setValue(`${section}_state`, values.state);
      setValue(`${section}_postcode`, values.postcode);
      setValue(`${section}_country`, values.country);
    };

    const saveAddressModal = async () => {
      if (!activeAddressSection) return;
      const values = collectAddressModalValues();
      const customerId = customerInput ? customerInput.value.trim() : '';

      if (activeAddressSection === 'shipping' && shippingSameCheckbox) {
        shippingSameCheckbox.checked = false;
        updateShippingSameCopy();
      }

      if (!customerId) {
        applyAddressValues(activeAddressSection, values);
        if (activeAddressSection === 'billing') {
          syncShippingWithBilling();
        }
        updateAddressSummaries();
        if (activeAddressSection === 'billing' && pendingShippingAfterBilling) {
          pendingShippingAfterBilling = false;
          openAddressModal('shipping');
        } else {
          pendingShippingAfterBilling = false;
          closeAddressModal();
        }
        return;
      }

      if (activeAddressSection === 'shipping') {
        const customer = getSelectedCustomer();
        const customerName = customer ? String(customer.full_name || customer.name || customer.shipping_full_name || '').trim() : '';
        if (values.full_name && customerName && !isNameSimilar(values.full_name, customerName)) {
          const confirmUpdate = window.confirm('O nome do envio é diferente do cadastro do cliente selecionado. Deseja atualizar o cadastro do cliente mesmo assim?');
          if (!confirmUpdate) {
            applyAddressValues(activeAddressSection, values);
            updateAddressSummaries();
            closeAddressModal();
            return;
          }
        }
      }

      if (!addressModalSave) return;
      addressModalSave.disabled = true;
      const originalLabel = addressModalSave.textContent;
      addressModalSave.textContent = 'Salvando...';

      try {
        const payload = new FormData();
        payload.set('action', 'update_customer_address');
        payload.set('section', activeAddressSection);
        payload.set('pessoa_id', customerId);
        payload.set('full_name', values.full_name);
        if (activeAddressSection === 'billing') {
          payload.set('email', values.email);
        }
        payload.set('phone', values.phone);
        payload.set('address_1', values.address_1);
        payload.set('address_2', values.address_2);
        payload.set('number', values.number);
        payload.set('neighborhood', values.neighborhood);
        payload.set('city', values.city);
        payload.set('state', values.state);
        payload.set('postcode', values.postcode);
        payload.set('country', values.country);

        const response = await fetch('pedido-cadastro.php', {
          method: 'POST',
          body: payload,
          headers: { 'Accept': 'application/json' },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || data.ok !== true) {
          throw new Error((data && data.message) ? data.message : 'Erro ao atualizar cliente.');
        }

        if (data.customer) {
          applyCustomerUpdate(data.customer, true);
          if (activeAddressSection === 'shipping') {
            setValue('shipping_phone', values.phone);
            updateAddressSummaries();
          }
        } else {
          applyAddressValues(activeAddressSection, values);
          updateAddressSummaries();
        }
        if (activeAddressSection === 'billing' && pendingShippingAfterBilling) {
          pendingShippingAfterBilling = false;
          openAddressModal('shipping');
        } else {
          pendingShippingAfterBilling = false;
          closeAddressModal();
        }
      } catch (error) {
        if (addressModalError) {
          addressModalError.textContent = error instanceof Error ? error.message : 'Erro ao atualizar cliente.';
          addressModalError.hidden = false;
        }
      } finally {
        addressModalSave.disabled = false;
        addressModalSave.textContent = originalLabel || 'Salvar';
      }
    };

    document.querySelectorAll('[data-edit-address]').forEach((button) => {
      button.addEventListener('click', () => {
        const section = button.getAttribute('data-edit-address');
        if (section) {
          openAddressModal(section);
        }
      });
    });

    if (addressModalBackdrop) {
      addressModalBackdrop.addEventListener('click', closeAddressModal);
    }
    if (addressModalCancel) {
      addressModalCancel.addEventListener('click', closeAddressModal);
    }
    if (addressModalClose) {
      addressModalClose.addEventListener('click', closeAddressModal);
    }
    if (addressModalSave) {
      addressModalSave.addEventListener('click', saveAddressModal);
    }

    const customerModal = document.querySelector('[data-customer-modal]');
    const customerModalBackdrop = document.querySelector('[data-customer-backdrop]');
    const customerModalError = document.querySelector('[data-customer-error]');
    const customerModalSave = document.querySelector('[data-customer-save]');
    const customerModalCancel = document.querySelector('[data-customer-cancel]');
    const customerModalClose = document.querySelector('[data-customer-close]');

    const customerModalFields = {
      fullName: document.getElementById('new_customer_full_name'),
      email: document.getElementById('new_customer_email'),
      phone: document.getElementById('new_customer_phone'),
      cpfCnpj: document.getElementById('new_customer_cpf'),
      street: document.getElementById('new_customer_street'),
      street2: document.getElementById('new_customer_street2'),
      number: document.getElementById('new_customer_number'),
      neighborhood: document.getElementById('new_customer_neighborhood'),
      city: document.getElementById('new_customer_city'),
      state: document.getElementById('new_customer_state'),
      zip: document.getElementById('new_customer_zip'),
      country: document.getElementById('new_customer_country'),
    };

    const openCustomerModal = () => {
      if (!customerModal || !customerModalBackdrop) return;
      if (customerModalError) {
        customerModalError.textContent = '';
        customerModalError.hidden = true;
      }
      Object.values(customerModalFields).forEach((field) => {
        if (!field) return;
        if (field === customerModalFields.country) {
          field.value = field.value || 'BR';
        } else {
          field.value = '';
        }
      });
      closeAllSuggestions();
      customerModal.hidden = false;
      customerModalBackdrop.hidden = false;
      document.body.classList.add('modal-open');
    };

    const closeCustomerModal = () => {
      if (!customerModal || !customerModalBackdrop) return;
      customerModal.hidden = true;
      customerModalBackdrop.hidden = true;
      document.body.classList.remove('modal-open');
    };

    const collectCustomerModalValues = () => ({
      fullName: customerModalFields.fullName ? customerModalFields.fullName.value.trim() : '',
      email: customerModalFields.email ? customerModalFields.email.value.trim() : '',
      phone: customerModalFields.phone ? customerModalFields.phone.value.trim() : '',
      cpfCnpj: customerModalFields.cpfCnpj ? customerModalFields.cpfCnpj.value.trim() : '',
      street: customerModalFields.street ? customerModalFields.street.value.trim() : '',
      street2: customerModalFields.street2 ? customerModalFields.street2.value.trim() : '',
      number: customerModalFields.number ? customerModalFields.number.value.trim() : '',
      neighborhood: customerModalFields.neighborhood ? customerModalFields.neighborhood.value.trim() : '',
      city: customerModalFields.city ? customerModalFields.city.value.trim() : '',
      state: customerModalFields.state ? customerModalFields.state.value.trim().toUpperCase() : '',
      zip: customerModalFields.zip ? customerModalFields.zip.value.trim() : '',
      country: customerModalFields.country ? customerModalFields.country.value.trim().toUpperCase() : '',
    });

    const saveCustomerModal = async () => {
      if (!customerModalSave) return;
      const values = collectCustomerModalValues();
      if (!values.fullName || !values.email) {
        if (customerModalError) {
          customerModalError.textContent = 'Informe nome completo e e-mail.';
          customerModalError.hidden = false;
        }
        return;
      }

      customerModalSave.disabled = true;
      const originalLabel = customerModalSave.textContent;
      customerModalSave.textContent = 'Salvando...';

      try {
        const payload = new FormData();
        payload.set('action', 'create_customer');
        payload.set('fullName', values.fullName);
        payload.set('email', values.email);
        payload.set('phone', values.phone);
        payload.set('cpfCnpj', values.cpfCnpj);
        payload.set('street', values.street);
        payload.set('street2', values.street2);
        payload.set('number', values.number);
        payload.set('neighborhood', values.neighborhood);
        payload.set('city', values.city);
        payload.set('state', values.state);
        payload.set('zip', values.zip);
        const normalizedCountry = (values.country === 'BRASIL' || values.country === 'BRAZIL')
          ? 'BR'
          : values.country;
        payload.set('country', normalizedCountry || 'BR');
        payload.set('status', 'ativo');

        const response = await fetch('pedido-cadastro.php', {
          method: 'POST',
          body: payload,
          headers: { 'Accept': 'application/json' },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || data.ok !== true) {
          throw new Error((data && data.message) ? data.message : 'Erro ao criar cliente.');
        }

        if (data.customer) {
          applyCustomerUpdate(data.customer, true);
        }
        closeCustomerModal();
      } catch (error) {
        if (customerModalError) {
          customerModalError.textContent = error instanceof Error ? error.message : 'Erro ao criar cliente.';
          customerModalError.hidden = false;
        }
      } finally {
        customerModalSave.disabled = false;
        customerModalSave.textContent = originalLabel || 'Salvar cliente';
      }
    };

    if (newCustomerButton) {
      newCustomerButton.addEventListener('click', openCustomerModal);
    }
    if (customerModalBackdrop) {
      customerModalBackdrop.addEventListener('click', closeCustomerModal);
    }
    if (customerModalCancel) {
      customerModalCancel.addEventListener('click', closeCustomerModal);
    }
    if (customerModalClose) {
      customerModalClose.addEventListener('click', closeCustomerModal);
    }
    if (customerModalSave) {
      customerModalSave.addEventListener('click', saveCustomerModal);
    }

    const productOptions = <?php echo json_encode(array_column($productOptions, null, 'ID'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const initialOrderItems = <?php echo json_encode(array_values($itemsInput), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const paymentMethodOptions = <?php echo json_encode(array_column($paymentMethodOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const bankAccountOptions = <?php echo json_encode(array_column($bankAccountOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const paymentTerminalOptions = <?php echo json_encode(array_column($paymentTerminalOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const voucherAccountOptions = <?php echo json_encode(array_column($voucherAccountOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const initialPaymentEntries = <?php echo json_encode(array_values($paymentsInput), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const orderTotalValue = document.getElementById('orderTotalValue');
  const orderTotalPieces = document.getElementById('orderTotalPieces');
  const orderTotalShipping = document.getElementById('orderTotalShipping');
  const orderTotalPaid = document.getElementById('orderTotalPaid');
  const orderTotalFees = document.getElementById('orderTotalFees');
  const orderTotalRemaining = document.getElementById('orderTotalRemaining');
  const orderTotalSummary = document.getElementById('orderTotalSummary');
  const orderPanelState = document.getElementById('orderPanelState');
  const orderPanelTotal = document.getElementById('orderPanelTotal');
  const orderPanelItems = document.getElementById('orderPanelItems');
  const orderPanelPaid = document.getElementById('orderPanelPaid');
  const orderPanelRemaining = document.getElementById('orderPanelRemaining');
  const orderPanelAlerts = document.getElementById('orderPanelAlerts');
  const orderItemsList = document.getElementById('orderItemsList');
    const orderItemSkuInput = document.getElementById('orderItemSku');
    const orderItemNameInput = document.getElementById('orderItemName');
    const orderItemVariationField = document.querySelector('.order-item-picker [data-variation-field]');
    const orderItemVariationSelect = document.getElementById('orderItemVariation');
    const orderItemQtyInput = document.getElementById('orderItemQty');
    const orderItemPriceInput = document.getElementById('orderItemPrice');
    const orderItemHelper = document.getElementById('orderItemHelper');
    const addOrderItemButton = document.getElementById('addOrderItem');
    const pickerSkuSuggestions = document.querySelector('.order-item-picker [data-sku-suggestions]');
    const pickerNameSuggestions = document.querySelector('.order-item-picker [data-name-suggestions]');
    const orderPaymentsList = document.getElementById('orderPaymentsList');
    const orderPaymentMethodSelect = document.getElementById('orderPaymentMethod');
    const orderPaymentAmountInput = document.getElementById('orderPaymentAmount');
    const orderPaymentFeeInput = document.getElementById('orderPaymentFee');
    const orderPaymentHelper = document.getElementById('orderPaymentHelper');
    const addOrderPaymentButton = document.getElementById('addOrderPayment');
    const paymentBankField = document.querySelector('[data-payment-bank-field]');
    const paymentBankSelect = document.getElementById('orderPaymentBank');
    const paymentTerminalField = document.querySelector('[data-payment-terminal-field]');
    const paymentTerminalSelect = document.getElementById('orderPaymentTerminal');
    const paymentVoucherField = document.querySelector('[data-payment-voucher-field]');
    const paymentVoucherSelect = document.getElementById('orderPaymentVoucher');
    const paymentVoucherMeta = document.getElementById('orderPaymentVoucherMeta');
    const paymentPixKey = document.getElementById('paymentPixKey');

    if (useCustomProductSuggestions) {
      if (orderItemSkuInput) {
        orderItemSkuInput.removeAttribute('list');
      }
      if (orderItemNameInput) {
        orderItemNameInput.removeAttribute('list');
      }
    }

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    const numberUtils = window.RetratoNumber || {};

    const toNumber = (value) => {
      if (typeof numberUtils.parse === 'function') {
        const parsed = numberUtils.parse(value);
        return Number.isFinite(parsed) ? parsed : 0;
      }
      const safeValue = value === null || value === undefined ? '' : value;
      const parsed = parseFloat(String(safeValue).replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const parseOptionalNumber = (value) => {
      if (value === null || value === undefined || value === '') {
        return '';
      }
      const parsed = toNumber(value);
      return Number.isFinite(parsed) ? parsed : '';
    };
    const parseNumberMaybe = (value) => {
      if (value === null || value === undefined || value === '') {
        return NaN;
      }
      if (typeof numberUtils.parse === 'function') {
        const parsed = numberUtils.parse(value);
        return Number.isFinite(parsed) ? parsed : NaN;
      }
      const parsed = parseFloat(String(value).replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : NaN;
    };

    const parseOptionalBoolean = (value, defaultValue = true) => {
      if (value === null || value === undefined || value === '') {
        return defaultValue;
      }
      if (typeof value === 'boolean') {
        return value;
      }
      const normalized = String(value).toLowerCase();
      if (['1', 'true', 'on', 'yes'].includes(normalized)) {
        return true;
      }
      if (['0', 'false', 'off', 'no'].includes(normalized)) {
        return false;
      }
      return defaultValue;
    };

    const formatMoney = (value) => {
      const safeValue = Number.isFinite(value) ? value : 0;
      return moneyFormatter.format(safeValue);
    };
    const formatNumber = (value, decimals = 2) => {
      const safeValue = Number.isFinite(value) ? value : 0;
      if (typeof numberUtils.format === 'function') {
        return numberUtils.format(safeValue, decimals);
      }
      return safeValue.toFixed(decimals).replace('.', ',');
    };
    const formatOptionalNumber = (value, decimals = 2) => {
      if (!Number.isFinite(value)) return '';
      return formatNumber(value, decimals);
    };

    const formatPieces = (qty) => {
      if (qty === 1) return '1 item';
      return `${qty} itens`;
    };

    const normalizeState = (value) => {
      const raw = String(value || '').trim().toUpperCase();
      if (!raw) return '';
      let cleaned = raw;
      if (typeof cleaned.normalize === 'function') {
        cleaned = cleaned.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }
      cleaned = cleaned.replace(/[^A-Z]/g, ' ').replace(/\s+/g, ' ').trim();
      const map = {
        DF: 'DF',
        'DISTRITO FEDERAL': 'DF',
        PR: 'PR',
        PARANA: 'PR',
        SC: 'SC',
        'SANTA CATARINA': 'SC',
        RS: 'RS',
        'RIO GRANDE DO SUL': 'RS',
      };
      return map[cleaned] || cleaned;
    };

    const resolveOrderState = () => {
      const shippingState = readValue('shipping_state');
      if (shippingState) return normalizeState(shippingState);
      const billingState = readValue('billing_state');
      return normalizeState(billingState);
    };

    const resolveSelectedCustomer = () => getSelectedCustomer();

    const resolveSelectedCustomerHasOpenBag = () => {
      if (resolveSelectedCustomerOpenBag()) {
        return true;
      }
      const customer = resolveSelectedCustomer();
      return Boolean(customer && (customer.has_open_bag || customer.hasOpenBag));
    };

    const resolveSelectedCustomerOpenBag = () => {
      const customer = resolveSelectedCustomer();
      if (!customer) {
        return null;
      }
      const bag = customer.open_bag || customer.openBag || null;
      if (!bag || typeof bag !== 'object') {
        return null;
      }
      const bagId = parseInt(String(bag.id || 0), 10);
      if (!Number.isFinite(bagId) || bagId <= 0) {
        return null;
      }
      return {
        id: bagId,
        opened_at: String(bag.opened_at || bag.openedAt || '').trim(),
        expected_close_at: String(bag.expected_close_at || bag.expectedCloseAt || '').trim(),
        items_qty: toNumber(bag.items_qty ?? bag.itemsQty ?? 0),
        items_total: toNumber(bag.items_total ?? bag.itemsTotal ?? 0),
        items_weight: toNumber(bag.items_weight ?? bag.itemsWeight ?? 0),
        opening_fee_value: toNumber(bag.opening_fee_value ?? bag.openingFeeValue ?? 0),
        opening_fee_paid: Boolean(bag.opening_fee_paid ?? bag.openingFeePaid ?? false),
      };
    };

    const formatBagDate = (value) => {
      const raw = String(value || '').trim();
      if (!raw) {
        return '-';
      }
      const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
      const parsed = new Date(normalized);
      if (Number.isNaN(parsed.getTime())) {
        return raw;
      }
      return parsed.toLocaleString('pt-BR');
    };

    const resolveBagOpeningFeeValue = (bag = null) => {
      const candidate = bag && Number.isFinite(bag.opening_fee_value) ? bag.opening_fee_value : NaN;
      if (Number.isFinite(candidate) && candidate > 0) {
        return candidate;
      }
      const formValue = Number.isFinite(openingFeeFromForm) ? openingFeeFromForm : NaN;
      if (Number.isFinite(formValue) && formValue > 0) {
        return formValue;
      }
      return Number.isFinite(openingFeeDefault) && openingFeeDefault > 0 ? openingFeeDefault : 35;
    };

    const renderCreateBagSummary = (isBagDeferred, bagAction) => {
      const openBag = resolveSelectedCustomerOpenBag();
      if (!bagCreateSummary) {
        return openBag;
      }

      if (!isBagDeferred) {
        bagCreateSummary.hidden = true;
        bagCreateSummary.textContent = '';
        return openBag;
      }

      if (openBag) {
        const openingFeeStatus = openBag.opening_fee_paid ? 'paga' : 'pendente';
        bagCreateSummary.textContent = [
          `Sacolinha #${openBag.id}`,
          `Aberta em: ${formatBagDate(openBag.opened_at)}`,
          `Fechamento previsto: ${formatBagDate(openBag.expected_close_at)}`,
          `Pecas acumuladas: ${Math.round(openBag.items_qty)}`,
          `Peso acumulado: ${formatNumber(openBag.items_weight, 3)} kg`,
          `Taxa de abertura: ${openingFeeStatus} (${formatMoney(resolveBagOpeningFeeValue(openBag))})`,
        ].join(' • ');
      } else {
        const openingFee = resolveBagOpeningFeeValue(null);
        const selectedCustomer = resolveSelectedCustomer();
        const hasCustomer = Boolean(selectedCustomer && (selectedCustomer.id || selectedCustomer.user_id));
        bagCreateSummary.textContent = hasCustomer
          ? `Sacolinha sera aberta automaticamente ao salvar. Taxa de abertura prevista: ${formatMoney(openingFee)}`
          : `Selecione a cliente para abrir sacolinha. Taxa de abertura prevista: ${formatMoney(openingFee)}`;
      }

      bagCreateSummary.hidden = false;
      return openBag;
    };

    const syncOpenBagFeePayNow = (isBagDeferred, bagAction, openBag) => {
      const hasOpenBagContext = Boolean(
        isBagDeferred
          && bagAction === 'add_to_bag'
          && openBag
      );
      const hasPendingOpenFee = Boolean(
        isBagDeferred
          && bagAction === 'add_to_bag'
          && openBag
          && !openBag.opening_fee_paid
      );
      const hasPaidOpenFee = Boolean(
        hasOpenBagContext
          && openBag
          && openBag.opening_fee_paid
      );

      if (openBagFeePayNowField) {
        openBagFeePayNowField.hidden = !hasOpenBagContext;
        openBagFeePayNowField.style.display = hasOpenBagContext ? '' : 'none';
        openBagFeePayNowField.classList.toggle('is-paid', hasPaidOpenFee);
      }
      if (openBagFeePayNowInput) {
        if (!hasOpenBagContext) {
          openBagFeePayNowInput.checked = false;
          openBagFeePayNowInput.disabled = false;
          openBagFeePayNowInput.dataset.userToggled = 'false';
        } else if (hasPaidOpenFee) {
          openBagFeePayNowInput.checked = true;
          openBagFeePayNowInput.disabled = true;
          openBagFeePayNowInput.dataset.userToggled = 'false';
        } else {
          openBagFeePayNowInput.disabled = false;
          if (openBagFeePayNowInput.dataset.userToggled !== 'true') {
            openBagFeePayNowInput.checked = true;
          }
        }
      }
      if (openBagFeePayNowLabel) {
        const value = resolveBagOpeningFeeValue(openBag);
        openBagFeePayNowLabel.textContent = hasPaidOpenFee
          ? `Taxa de abertura ja paga (${formatMoney(value)})`
          : `Pagar taxa de abertura agora (${formatMoney(value)})`;
      }
      if (openBagFeePayNowHint) {
        if (hasPaidOpenFee) {
          openBagFeePayNowHint.textContent = 'Nao e necessario cobrar novamente.';
        } else {
          openBagFeePayNowHint.textContent = 'Se preferir, deixe desmarcado para cobrar depois.';
        }
      }
    };

    const ensureOpenBagIfNeeded = async (mode, shipmentKind, bagAction, openBag) => {
      const shouldEnsure = mode === 'shipment' && shipmentKind === 'bag_deferred' && bagAction === 'open_bag';
      if (!shouldEnsure) {
        ensureOpenBagFailedPersonId = 0;
        return;
      }

      if (openBag && openBag.id) {
        ensureOpenBagFailedPersonId = 0;
        return;
      }

      const customerIdValue = customerInput ? customerInput.value.trim() : '';
      const customerId = parseInt(customerIdValue, 10);
      if (!Number.isFinite(customerId) || customerId <= 0) {
        return;
      }

      if (ensureOpenBagFailedPersonId === customerId) {
        return;
      }
      if (ensuringOpenBagPersonId === customerId) {
        return;
      }

      ensuringOpenBagPersonId = customerId;
      if (bagCreateHint) {
        bagCreateHint.textContent = 'Abrindo sacolinha automaticamente...';
        bagCreateHint.style.color = 'var(--muted)';
        bagCreateHint.hidden = false;
      }

      try {
        const payload = new FormData();
        payload.set('action', 'ensure_open_bag');
        payload.set('pessoa_id', String(customerId));

        const response = await fetch('pedido-cadastro.php', {
          method: 'POST',
          body: payload,
          headers: { 'Accept': 'application/json' },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || data.ok !== true) {
          throw new Error((data && data.message) ? data.message : 'Erro ao abrir sacolinha automaticamente.');
        }

        if (data.customer) {
          applyCustomerUpdate(data.customer, false);
        } else if (data.bag && customerOptions[String(customerId)]) {
          const customer = customerOptions[String(customerId)];
          customer.has_open_bag = true;
          customer.open_bag = data.bag;
          applyCustomerUpdate(customer, false);
        }

        ensureOpenBagFailedPersonId = 0;
        if (bagCreateHint && data.message) {
          bagCreateHint.textContent = String(data.message);
          bagCreateHint.style.color = 'var(--muted)';
          bagCreateHint.hidden = false;
        }
        updateDeliveryPricing(true);
      } catch (error) {
        ensureOpenBagFailedPersonId = customerId;
        if (bagCreateHint) {
          bagCreateHint.textContent = error instanceof Error ? error.message : 'Erro ao abrir sacolinha automaticamente.';
          bagCreateHint.style.color = '#b91c1c';
          bagCreateHint.hidden = false;
        }
      } finally {
        ensuringOpenBagPersonId = 0;
      }
    };

    const findSalesChannelValue = (targetLabel) => {
      if (!salesChannelSelect) return '';
      const target = normalizeLabel(targetLabel);
      const options = Array.from(salesChannelSelect.options || []);
      for (const option of options) {
        if (!option.value) continue;
        const optionLabel = normalizeLabel(option.textContent || option.label || option.value);
        const optionValue = normalizeLabel(option.value);
        if (optionLabel === target || optionValue === target || optionLabel.includes(target)) {
          return option.value;
        }
      }
      return '';
    };

    const isPhysicalStoreChannel = () => {
      if (!salesChannelSelect) return false;
      const selected = salesChannelSelect.options
        ? salesChannelSelect.options[salesChannelSelect.selectedIndex]
        : null;
      const label = selected ? (selected.textContent || selected.label || selected.value) : salesChannelSelect.value;
      const normalized = normalizeLabel(label);
      const normalizedValue = normalizeLabel(salesChannelSelect.value);
      return normalized.includes('loja fisica') || normalizedValue.includes('loja fisica');
    };

    const syncDeliveryInputs = (forcedMode = '', forcedKind = '') => {
      let mode = forcedMode || (deliveryModeSelect && deliveryModeSelect.value ? deliveryModeSelect.value : 'shipment');
      let kind = forcedKind || (shipmentKindSelect && shipmentKindSelect.value ? shipmentKindSelect.value : 'tracked');
      if (mode !== 'shipment') {
        kind = '';
      } else if (!Object.prototype.hasOwnProperty.call(shipmentKindOptions, kind)) {
        kind = 'tracked';
      }

      if (deliveryModeSelect && deliveryModeSelect.value !== mode) {
        deliveryModeSelect.value = mode;
      }
      if (shipmentKindSelect) {
        if (mode !== 'shipment') {
          shipmentKindSelect.value = '';
        } else if (shipmentKindSelect.value !== kind && Object.prototype.hasOwnProperty.call(shipmentKindOptions, kind)) {
          shipmentKindSelect.value = kind;
        }
      }
      if (deliveryModeInput) {
        deliveryModeInput.value = mode;
      }
      if (shipmentKindInput) {
        shipmentKindInput.value = mode === 'shipment' ? (kind || 'tracked') : '';
      }
    };

    const resolveBagAction = (mode, shipmentKind) => {
      if (mode !== 'shipment' || shipmentKind !== 'bag_deferred') {
        return '';
      }
      return resolveSelectedCustomerOpenBag() ? 'add_to_bag' : 'open_bag';
    };

    applyOrderDefaults = () => {
      if (isFullEdit || isPdvMode()) {
        return;
      }
      const stateCode = resolveOrderState();
      const isDf = stateCode === 'DF';
      const hasOpenBag = resolveSelectedCustomerHasOpenBag();
      const targetChannel = isDf ? 'Loja Física' : 'Instagram';
      const targetMode = isDf ? 'immediate_in_hand' : 'shipment';
      const targetKind = isDf ? '' : (hasOpenBag ? 'bag_deferred' : 'tracked');

      if (salesChannelSelect && salesChannelSelect.dataset.userEdited !== 'true') {
        const channelValue = findSalesChannelValue(targetChannel);
        if (channelValue) {
          salesChannelSelect.value = channelValue;
        }
      }

      if (deliveryModeSelect && deliveryModeSelect.dataset.userEdited !== 'true') {
        deliveryModeSelect.value = targetMode;
      }
      if (shipmentKindSelect && shipmentKindSelect.dataset.userEdited !== 'true') {
        shipmentKindSelect.value = targetKind;
      }
      updateDeliveryPricing(true);
    };

    const normalizeSku = (value) => {
      const base = normalizeSearch(value);
      return base.replace(/^sku\s*/i, '').trim();
    };

    const resolveProductLabel = (product) => {
      if (!product) return '';
      const name = String(product.post_title || '').trim();
      const sku = String(product.sku || '').trim();
      if (sku && name) return `SKU ${sku} - ${name}`;
      if (name) return name;
      if (sku) return `SKU ${sku}`;
      return 'Produto selecionado';
    };

    const buildSkuLabel = (sku, name, variationLabel) => {
      const parts = [];
      if (sku) {
        parts.push(`SKU ${sku}`);
      }
      if (name) {
        parts.push(name);
      }
      if (variationLabel) {
        parts.push(variationLabel);
      }
      if (parts.length) {
        return parts.join(' - ');
      }
      return 'SKU';
    };

    const buildNameLabel = (name, sku) => {
      if (name && sku) return `${name} (SKU ${sku})`;
      if (name) return name;
      if (sku) return `SKU ${sku}`;
      return 'Produto';
    };

    const buildVariationLabel = (variation) => {
      if (!variation) return 'Variação';
      const name = String(variation.name || variation.post_title || '').trim();
      const sku = String(variation.sku || '').trim();
      const id = String(variation.id || variation.ID || '').trim();
      if (name && sku) return `${name} (SKU ${sku})`;
      if (name) return name;
      if (sku) return `SKU ${sku}`;
      if (id) return `Variação #${id}`;
      return 'Variação';
    };

    const resolvePriceFromCandidates = (candidates, fallback = null) => {
      for (let i = 0; i < candidates.length; i += 1) {
        const parsed = parseNumberMaybe(candidates[i]);
        if (Number.isFinite(parsed)) {
          return parsed;
        }
      }
      return fallback;
    };

    const hasPriceFromCandidates = (candidates) => {
      for (let i = 0; i < candidates.length; i += 1) {
        const parsed = parseNumberMaybe(candidates[i]);
        if (Number.isFinite(parsed)) {
          return true;
        }
      }
      return false;
    };

    const getProductPriceCandidates = (product) => {
      if (!product) return [];
      return [
        product.sale_price,
        product.preco_venda,
        product.price,
        product.regular_price,
        product.display_price,
        product.min_price,
        product.max_price,
      ];
    };

    const getVariationPriceCandidates = (variation) => {
      if (!variation) return [];
      return [
        variation.sale_price,
        variation.preco_venda,
        variation.price,
        variation.regular_price,
        variation.display_price,
        variation.min_price,
        variation.max_price,
      ];
    };

    const resolveProductPrice = (product) => {
      if (!product) return 0;
      return resolvePriceFromCandidates(getProductPriceCandidates(product), 0);
    };

    const resolveVariationPrice = (variation) => {
      if (!variation) return null;
      return resolvePriceFromCandidates(getVariationPriceCandidates(variation), null);
    };

    const hasConfiguredProductPrice = (product) => {
      return hasPriceFromCandidates(getProductPriceCandidates(product));
    };

    const hasConfiguredVariationPrice = (variation) => {
      return hasPriceFromCandidates(getVariationPriceCandidates(variation));
    };

    const addLookupValue = (lookup, key, id) => {
      const normalized = normalizeSearch(key);
      if (normalized === '') {
        return;
      }
      if (!lookup[normalized]) {
        lookup[normalized] = [];
      }
      lookup[normalized].push(String(id));
    };

    const addSkuMatch = (lookup, suggestions, sku, productId, variationId, name, variationLabel) => {
      const normalized = normalizeSearch(sku);
      if (normalized === '') {
        return;
      }
      if (!lookup[normalized]) {
        lookup[normalized] = [];
      }
      lookup[normalized].push({
        productId: String(productId),
        variationId: variationId ? String(variationId) : '',
        sku,
      });
      suggestions.push({
        id: String(productId),
        variationId: variationId ? String(variationId) : '',
        value: sku,
        label: buildSkuLabel(sku, name, variationLabel),
        search: normalizeSearch(`${sku} ${name} ${variationLabel || ''}`),
      });
    };

    const skuLookup = {};
    const productNameLookup = {};
    const skuSuggestions = [];
    const productNameSuggestions = [];
    Object.entries(productOptions).forEach(([id, product]) => {
      const sku = String(product && product.sku ? product.sku : '').trim();
      const name = String(product && product.post_title ? product.post_title : '').trim();
      const normalizedId = String(id);
      if (sku !== '') {
        addSkuMatch(skuLookup, skuSuggestions, sku, normalizedId, '', name, '');
      }
      if (name !== '') {
        addLookupValue(productNameLookup, name, id);
        productNameSuggestions.push({
          id: normalizedId,
          value: name,
          label: buildNameLabel(name, sku),
          search: normalizeSearch(`${name} ${sku}`),
        });
      }
      const variations = product && Array.isArray(product.variations) ? product.variations : [];
      variations.forEach((variation) => {
        const variationId = String(variation && (variation.id || variation.ID) ? (variation.id || variation.ID) : '').trim();
        const variationSku = String(variation && variation.sku ? variation.sku : '').trim();
        if (!variationId || variationSku === '') {
          return;
        }
        const variationLabel = buildVariationLabel(variation);
        addSkuMatch(skuLookup, skuSuggestions, variationSku, normalizedId, variationId, name, variationLabel);
      });
    });

    const matchLookup = (value, lookup, allowSkuPrefix, suggestions = null) => {
      const normalized = normalizeSearch(value);
      let ids = lookup[normalized] || null;
      if ((!ids || ids.length === 0) && allowSkuPrefix) {
        const normalizedSku = normalizeSku(value);
        if (normalizedSku && normalizedSku !== normalized) {
          ids = lookup[normalizedSku] || null;
        }
      }
      if (ids && ids.length === 1) {
        return { id: ids[0], state: 'exact' };
      }
      if (ids && ids.length > 1) {
        return { id: '', state: 'multiple' };
      }
      if (suggestions) {
        const tokens = buildSearchTokens(value);
        const matches = filterSuggestions(suggestions, tokens, false);
        if (matches.length) {
          const uniqueIds = new Set(matches.map((item) => String(item.id)));
          if (uniqueIds.size === 1) {
            return { id: Array.from(uniqueIds)[0], state: 'approx' };
          }
          if (uniqueIds.size > 1) {
            return { id: '', state: 'multiple' };
          }
        }
      }
      return { id: '', state: 'none' };
    };

    const matchSku = (value) => {
      const normalized = normalizeSearch(value);
      let matches = skuLookup[normalized] || null;
      if ((!matches || matches.length === 0)) {
        const normalizedSku = normalizeSku(value);
        if (normalizedSku && normalizedSku !== normalized) {
          matches = skuLookup[normalizedSku] || null;
        }
      }
      if (matches && matches.length === 1) {
        return {
          productId: matches[0].productId,
          variationId: matches[0].variationId,
          sku: matches[0].sku,
          state: 'exact',
        };
      }
      if (matches && matches.length > 1) {
        return { productId: '', variationId: '', sku: '', state: 'multiple' };
      }
      return { productId: '', variationId: '', sku: '', state: 'none' };
    };

    const setupAutocomplete = (input, suggestions, items, onSelect, emptyMessage, enabled = useCustomSuggestions) => {
      if (!enabled || !input || !suggestions) return null;
      const hideSuggestions = () => {
        suggestions.hidden = true;
        input.setAttribute('aria-expanded', 'false');
      };
      const renderSuggestions = (value) => {
        closeAllSuggestions();
        clearNode(suggestions);
        const tokens = buildSearchTokens(value);
        const filtered = filterSuggestions(items, tokens, true);
        if (filtered.length === 0) {
          const empty = document.createElement('div');
          empty.className = 'autocomplete-suggestion autocomplete-suggestion--empty';
          empty.textContent = emptyMessage;
          suggestions.appendChild(empty);
        } else {
          const fragment = document.createDocumentFragment();
          filtered.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-suggestion';
            button.textContent = item.label;
            bindSuggestionSelect(button, () => {
              onSelect(item);
              hideSuggestions();
            });
            fragment.appendChild(button);
          });
          suggestions.appendChild(fragment);
        }
        suggestions.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      };

      input.classList.add('autocomplete-input');
      input.setAttribute('autocomplete', 'off');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');
      input.addEventListener('focus', () => renderSuggestions(input.value));
      input.addEventListener('click', () => renderSuggestions(input.value));
      input.addEventListener('input', () => renderSuggestions(input.value));
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          hideSuggestions();
        }
      });

      return {
        hide: hideSuggestions,
        render: renderSuggestions,
      };
    };

    const applyAdjustment = (price, type, value) => {
      const base = Number.isFinite(price) ? price : 0;
      const adj = Math.max(0, Number.isFinite(value) ? value : 0);
      let total = base;
      switch (type) {
        case 'discount_percent':
          total = base - base * (adj / 100);
          break;
        case 'discount_value':
          total = base - adj;
          break;
        case 'increase_percent':
          total = base + base * (adj / 100);
          break;
        case 'increase_value':
          total = base + adj;
          break;
        default:
          total = base;
      }
      if (!Number.isFinite(total)) {
        total = base;
      }
      return Math.max(0, total);
    };

    const adjustmentLabels = {
      discount_percent: 'Desconto (%)',
      discount_value: 'Desconto (R$)',
      increase_percent: 'Acrescimo (%)',
      increase_value: 'Acrescimo (R$)',
    };

    const describeAdjustment = (type, value) => {
      if (!type) return '';
      const label = adjustmentLabels[type] || 'Ajuste';
      if (type.includes('percent')) {
        return `${label} ${value}%`;
      }
      return `${label} ${formatMoney(value)}`;
    };

    const orderItems = [];
    const orderPayments = [];
    let pickerProductSku = '';
    let syncLayoutState = () => {};

    const parseJsonStorage = (raw, fallback) => {
      if (!raw) return fallback;
      try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : fallback;
      } catch (error) {
        return fallback;
      }
    };

    const readLayoutFromStorage = () => {
      try {
        const stored = localStorage.getItem(layoutStorageKey);
        return stored || '';
      } catch (error) {
        return '';
      }
    };

    const saveLayoutToStorage = (layoutName) => {
      try {
        localStorage.setItem(layoutStorageKey, layoutName);
      } catch (error) {
        // noop
      }
    };

    const readSectionState = () => {
      try {
        return parseJsonStorage(localStorage.getItem(sectionStateStorageKey), {});
      } catch (error) {
        return {};
      }
    };

    const writeSectionState = (state) => {
      try {
        localStorage.setItem(sectionStateStorageKey, JSON.stringify(state));
      } catch (error) {
        // noop
      }
    };

    const layoutSectionState = readSectionState();

    const normalizeLayoutName = (value) => {
      const raw = String(value || '').trim();
      return layoutNames.includes(raw) ? raw : 'layout-1';
    };

    const getSectionNode = (key) => {
      return orderSections.find((section) => section.dataset.sectionKey === key) || null;
    };

    const syncSectionToggleLabel = (section) => {
      if (!section) return;
      const button = section.querySelector('[data-section-toggle]');
      if (!button) return;
      const expanded = !section.classList.contains('is-collapsed');
      button.textContent = expanded ? 'Recolher' : 'Expandir';
      button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const setSectionCollapsed = (key, collapsed, persist = true) => {
      const section = getSectionNode(key);
      if (!section) return;
      const isLocked = section.hasAttribute('data-section-locked');
      const shouldCollapse = Boolean(collapsed) && !isLocked;
      section.classList.toggle('is-collapsed', shouldCollapse);
      section.classList.toggle('is-open', !shouldCollapse);
      syncSectionToggleLabel(section);
      if (!persist) return;
      const layoutState = layoutSectionState[currentLayout] && typeof layoutSectionState[currentLayout] === 'object'
        ? layoutSectionState[currentLayout]
        : {};
      layoutState[key] = shouldCollapse;
      layoutSectionState[currentLayout] = layoutState;
      writeSectionState(layoutSectionState);
    };

    const collapseOtherSectionsOnMobile = (keepKey) => {
      if (currentLayout !== 'layout-2' || !isNarrowWizardViewport.matches) return;
      orderSections.forEach((section) => {
        const key = section.dataset.sectionKey || '';
        if (!key || key === keepKey || section.hasAttribute('data-section-locked')) return;
        setSectionCollapsed(key, true);
      });
    };

    const applySectionStateForLayout = () => {
      const layoutState = layoutSectionState[currentLayout] && typeof layoutSectionState[currentLayout] === 'object'
        ? layoutSectionState[currentLayout]
        : {};

      orderSections.forEach((section) => {
        const key = section.dataset.sectionKey || '';
        if (!key) return;
        if (section.hasAttribute('data-section-locked')) {
          section.classList.remove('is-collapsed');
          section.classList.add('is-open');
          syncSectionToggleLabel(section);
          return;
        }

        let collapsed = Boolean(layoutState[key]);
        if (currentLayout === 'layout-5' && key === 'logistics' && layoutState[key] === undefined) {
          collapsed = true;
        }
        if (currentLayout === 'layout-2' && key === 'customer') {
          collapsed = false;
        }
        if (currentLayout !== 'layout-3' && currentLayout !== 'layout-2' && currentLayout !== 'layout-5') {
          collapsed = false;
        }
        setSectionCollapsed(key, collapsed, false);
      });

      if (currentLayout === 'layout-2' && isNarrowWizardViewport.matches) {
        const firstOpen = orderSections.find((section) => !section.classList.contains('is-collapsed') && !section.hasAttribute('data-section-locked'));
        if (firstOpen) {
          const firstOpenKey = firstOpen.dataset.sectionKey || '';
          collapseOtherSectionsOnMobile(firstOpenKey);
        }
      }
    };

    const setLayoutMode = (layoutName, persist = true) => {
      const normalized = normalizeLayoutName(layoutName);
      currentLayout = normalized;
      document.body.dataset.layout = normalized;
      if (layoutRoot) {
        layoutNames.forEach((name) => layoutRoot.classList.remove(name));
        layoutRoot.classList.add(normalized);
        if (normalized !== 'layout-4') {
          layoutRoot.classList.remove('is-sidebar-open');
        }
      }
      if (layoutSwitcher && layoutSwitcher.value !== normalized) {
        layoutSwitcher.value = normalized;
      }
      if (persist) {
        saveLayoutToStorage(normalized);
      }
      applySectionStateForLayout();
      syncLayoutState();
    };

    const toggleSidebar = () => {
      if (currentLayout !== 'layout-4' || !layoutRoot) {
        const reviewSection = getSectionNode('review');
        if (reviewSection) {
          reviewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        return;
      }
      layoutRoot.classList.toggle('is-sidebar-open');
    };

    orderSections.forEach((section) => {
      const key = section.dataset.sectionKey || '';
      if (!key || section.hasAttribute('data-section-locked')) return;
      const toggleButton = section.querySelector('[data-section-toggle]');
      const header = section.querySelector('.order-section__header');

      const toggle = () => {
        const nextCollapsed = !section.classList.contains('is-collapsed');
        setSectionCollapsed(key, nextCollapsed);
        if (!nextCollapsed) {
          collapseOtherSectionsOnMobile(key);
        }
      };

      if (toggleButton) {
        toggleButton.addEventListener('click', (event) => {
          event.stopPropagation();
          toggle();
        });
      }

      if (header) {
        header.addEventListener('click', (event) => {
          if (event.target && event.target.closest('[data-section-toggle]')) return;
          if (!['layout-2', 'layout-3', 'layout-5'].includes(currentLayout)) return;
          toggle();
        });
      }
    });

    sidebarTriggers.forEach((button) => {
      button.addEventListener('click', toggleSidebar);
    });

    if (layoutSidebarPanel) {
      document.addEventListener('click', (event) => {
        if (currentLayout !== 'layout-4' || !layoutRoot) return;
        if (!layoutRoot.classList.contains('is-sidebar-open')) return;
        const target = event.target;
        if (layoutSidebarPanel.contains(target)) return;
        if (sidebarTriggers.some((button) => button.contains(target))) return;
        layoutRoot.classList.remove('is-sidebar-open');
      });
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || currentLayout !== 'layout-4' || !layoutRoot) return;
        layoutRoot.classList.remove('is-sidebar-open');
      });
    }

    if (layoutSwitcher) {
      layoutSwitcher.addEventListener('change', () => {
        setLayoutMode(layoutSwitcher.value, true);
      });
    }

    if (isNarrowWizardViewport) {
      isNarrowWizardViewport.addEventListener('change', () => {
        applySectionStateForLayout();
      });
    }

    const matchesOrderItem = (item, productSku, variationId) => {
      const itemSku = String(item.product_sku || '');
      return itemSku === String(productSku || '')
        && String(item.variation_id || '') === String(variationId || '');
    };

    const sumOrderItemQuantities = (productSku, variationId, excludeIndex = -1) => {
      return orderItems.reduce((sum, item, index) => {
        if (index === excludeIndex) return sum;
        if (!matchesOrderItem(item, productSku, variationId)) return sum;
        const itemQty = parseInt(item.quantity, 10) || 1;
        return sum + Math.max(1, itemQty);
      }, 0);
    };

    const setPickerHelper = (message, isError) => {
      if (!orderItemHelper) return;
      orderItemHelper.textContent = message;
      if (isError) {
        orderItemHelper.classList.add('order-item-helper--error');
      } else {
        orderItemHelper.classList.remove('order-item-helper--error');
      }
    };

    const pulseNode = (node, className = 'is-feedback') => {
      if (!node) return;
      node.classList.remove(className);
      window.requestAnimationFrame(() => {
        node.classList.add(className);
        window.setTimeout(() => {
          node.classList.remove(className);
        }, 520);
      });
    };

    const updatePickerVariationField = (productId, preferredValue) => {
      if (!orderItemVariationField || !orderItemVariationSelect) return '';
      const resolvedId = productId ? String(productId) : '';
      const product = resolvedId && Object.prototype.hasOwnProperty.call(productOptions, resolvedId)
        ? productOptions[resolvedId]
        : null;
      const variations = product && Array.isArray(product.variations) ? product.variations : [];

      clearNode(orderItemVariationSelect);
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Sem variação';
      orderItemVariationSelect.appendChild(placeholder);

      if (!variations.length) {
        orderItemVariationField.hidden = true;
        orderItemVariationSelect.value = '';
        return '';
      }

      orderItemVariationField.hidden = false;
      variations.forEach((variation) => {
        const id = String(variation && (variation.id || variation.ID) ? (variation.id || variation.ID) : '').trim();
        if (!id) return;
        const option = document.createElement('option');
        option.value = id;
        option.textContent = buildVariationLabel(variation);
        orderItemVariationSelect.appendChild(option);
      });

      if (preferredValue && Array.from(orderItemVariationSelect.options).some((opt) => opt.value === preferredValue)) {
        orderItemVariationSelect.value = preferredValue;
        return preferredValue;
      }

      if (variations.length === 1) {
        const onlyId = String(variations[0].id || variations[0].ID || '').trim();
        if (onlyId) {
          orderItemVariationSelect.value = onlyId;
          return onlyId;
        }
      }

      orderItemVariationSelect.value = '';
      return '';
    };

    const setPickerPrice = (price, force) => {
      if (!orderItemPriceInput) return;
      const shouldUpdate = force || orderItemPriceInput.value === '' || orderItemPriceInput.dataset.userEdited !== 'true';
      if (shouldUpdate) {
        orderItemPriceInput.value = formatOptionalNumber(price, 2);
        orderItemPriceInput.dataset.userEdited = 'false';
      }
    };

    const applyPickerProduct = (productId, forcePrice, preferredVariationId = '', preferredSku = '') => {
      if (!productId || !Object.prototype.hasOwnProperty.call(productOptions, productId)) {
        return;
      }
      const product = productOptions[productId];
      pickerProductSku = String(productId);

      const sku = String(product && product.sku ? product.sku : '').trim();
      const name = String(product && product.post_title ? product.post_title : '').trim();
      const resolvedSku = preferredSku || sku;
      if (orderItemSkuInput && resolvedSku && orderItemSkuInput.value.trim() !== resolvedSku) {
        orderItemSkuInput.value = resolvedSku;
      }
      if (orderItemNameInput && name && orderItemNameInput.value.trim() !== name) {
        orderItemNameInput.value = name;
      }

      const previousVariation = orderItemVariationSelect ? orderItemVariationSelect.value : '';
      const variationId = updatePickerVariationField(productId, preferredVariationId || previousVariation);
      const variation = variationId ? (product.variations || []).find((item) => String(item.id || item.ID) === variationId) : null;
      const variationPrice = resolveVariationPrice(variation);
      const basePrice = variationPrice !== null ? variationPrice : resolveProductPrice(product);
      setPickerPrice(basePrice, forcePrice);
      const hasConfiguredPrice = hasConfiguredVariationPrice(variation) || hasConfiguredProductPrice(product);
      if (!hasConfiguredPrice) {
        setPickerHelper('Produto sem preco de venda cadastrado. Informe o preco unitario.', true);
      } else {
        setPickerHelper(resolveProductLabel(product), false);
      }
    };

    const clearPickerSelection = () => {
      pickerProductSku = '';
      if (orderItemSkuInput) orderItemSkuInput.value = '';
      if (orderItemNameInput) orderItemNameInput.value = '';
      if (orderItemPriceInput) {
        orderItemPriceInput.value = '';
        orderItemPriceInput.dataset.userEdited = 'false';
      }
      if (orderItemQtyInput) orderItemQtyInput.value = '1';
      updatePickerVariationField('', '');
      setPickerHelper('Selecione um SKU ou produto.', false);
    };

    const mobileAutoAddIfReady = () => {
      if (!isMobileViewport || !isMobileViewport.matches) return;
      if (!pickerProductSku) return;
      if (orderItemVariationField && orderItemVariationSelect && !orderItemVariationField.hidden) {
        if (!orderItemVariationSelect.value) return;
      }
      addOrderItem();
    };

    const handlePickerSkuInput = (finalize = false) => {
      if (!orderItemSkuInput) return;
      const skuValue = orderItemSkuInput.value.trim();
      if (!skuValue) {
        if (!orderItemNameInput || orderItemNameInput.value.trim() === '') {
          clearPickerSelection();
        }
        return;
      }
      const match = matchSku(skuValue);
      if (match.productId) {
        const shouldForcePrice = pickerProductSku !== match.productId;
        applyPickerProduct(match.productId, shouldForcePrice, match.variationId, match.sku || skuValue);
        mobileAutoAddIfReady();
        return;
      }
      pickerProductSku = '';
      updatePickerVariationField('', '');
      if (match.state === 'multiple') {
        setPickerHelper('Mais de um SKU encontrado. Refine o valor.', true);
      } else if (finalize) {
        setPickerHelper('SKU não encontrado.', true);
      } else {
        setPickerHelper('Digite um SKU válido.', false);
      }
    };

    const handlePickerNameInput = (finalize = false) => {
      if (!orderItemNameInput) return;
      const nameValue = orderItemNameInput.value.trim();
      if (!nameValue) {
        if (!orderItemSkuInput || orderItemSkuInput.value.trim() === '') {
          clearPickerSelection();
        }
        return;
      }
      const match = matchLookup(nameValue, productNameLookup, false, productNameSuggestions);
      if (match.id) {
        const shouldForcePrice = pickerProductSku !== match.id;
        applyPickerProduct(match.id, shouldForcePrice);
        mobileAutoAddIfReady();
        return;
      }
      pickerProductSku = '';
      updatePickerVariationField('', '');
      if (match.state === 'multiple') {
        setPickerHelper('Mais de um produto com esse nome. Refine o valor.', true);
      } else if (finalize) {
        setPickerHelper('Produto não encontrado.', true);
      } else {
        setPickerHelper('Digite um produto válido.', false);
      }
    };

    if (orderItemSkuInput) {
      orderItemSkuInput.addEventListener('input', () => handlePickerSkuInput(false));
      orderItemSkuInput.addEventListener('change', () => handlePickerSkuInput(true));
    }
    if (orderItemNameInput) {
      orderItemNameInput.addEventListener('input', () => handlePickerNameInput(false));
      orderItemNameInput.addEventListener('change', () => handlePickerNameInput(true));
    }
    if (orderItemPriceInput) {
      orderItemPriceInput.addEventListener('input', () => {
        orderItemPriceInput.dataset.userEdited = 'true';
      });
    }
    if (orderItemVariationSelect) {
      orderItemVariationSelect.addEventListener('change', () => {
        if (!pickerProductSku) return;
        const product = productOptions[pickerProductSku];
        if (!product) return;
        const variationId = orderItemVariationSelect.value;
        const variation = variationId ? (product.variations || []).find((item) => String(item.id || item.ID) === variationId) : null;
        const variationPrice = resolveVariationPrice(variation);
        const basePrice = variationPrice !== null ? variationPrice : resolveProductPrice(product);
        setPickerPrice(basePrice, false);
        const hasConfiguredPrice = hasConfiguredVariationPrice(variation) || hasConfiguredProductPrice(product);
        if (!hasConfiguredPrice) {
          setPickerHelper('Produto sem preco de venda cadastrado. Informe o preco unitario.', true);
        } else {
          setPickerHelper(resolveProductLabel(product), false);
        }
        mobileAutoAddIfReady();
      });
    }

    setupAutocomplete(
      orderItemSkuInput,
      pickerSkuSuggestions,
      skuSuggestions,
      (item) => {
        if (!orderItemSkuInput) return;
        orderItemSkuInput.value = item.value;
        handlePickerSkuInput(true);
      },
      'Nenhum SKU encontrado.',
      useCustomProductSuggestions
    );

    setupAutocomplete(
      orderItemNameInput,
      pickerNameSuggestions,
      productNameSuggestions,
      (item) => {
        if (!orderItemNameInput) return;
        orderItemNameInput.value = item.value;
        handlePickerNameInput(true);
      },
      'Nenhum produto encontrado.',
      useCustomProductSuggestions
    );

    const applyCreateDeliveryFields = (mode, kind, bagAction, openBag) => {
      const isShipment = mode === 'shipment';
      const isTracked = isShipment && kind === 'tracked';
      const isLocal = isShipment && kind === 'local_courier';
      const isBagDeferred = isShipment && kind === 'bag_deferred';
      const showEstimated = isShipment && !isBagDeferred;

      if (shipmentKindField) {
        shipmentKindField.hidden = !isShipment;
      }
      if (carrierCreateField) {
        carrierCreateField.hidden = !(isTracked || isLocal);
      }
      if (trackingCreateField) {
        trackingCreateField.hidden = !(isTracked || isLocal);
      }
      if (estimatedCreateField) {
        estimatedCreateField.hidden = !showEstimated;
      }
      if (estimatedCreateInput && !showEstimated) {
        estimatedCreateInput.value = '';
      }
      if (bagCreateField) {
        bagCreateField.hidden = !isBagDeferred;
      }
      if (carrierCreateInput) {
        carrierCreateInput.required = isTracked;
        if (!isTracked && !isLocal) {
          carrierCreateInput.value = '';
        }
      }
      if (trackingCreateInput) {
        trackingCreateInput.required = false;
        if (!isTracked && !isLocal) {
          trackingCreateInput.value = '';
        }
      }
      if (bagIdCreateInput) {
        bagIdCreateInput.required = false;
        bagIdCreateInput.readOnly = true;
        if (!isBagDeferred) {
          bagIdCreateInput.value = '';
          bagIdCreateInput.placeholder = 'ID da sacolinha';
        } else if (openBag && openBag.id) {
          bagIdCreateInput.value = String(openBag.id);
          bagIdCreateInput.placeholder = 'Sacolinha aberta da cliente';
        } else {
          bagIdCreateInput.value = '';
          bagIdCreateInput.placeholder = 'Sera aberta automaticamente ao salvar';
        }
      }
      if (bagCreateHint) {
        bagCreateHint.textContent = '';
        bagCreateHint.hidden = true;
      }
      renderCreateBagSummary(isBagDeferred, bagAction);
      syncOpenBagFeePayNow(isBagDeferred, bagAction, openBag);
    };

    const updateDeliveryPricing = (forcePrice = false) => {
      let mode = deliveryModeSelect && deliveryModeSelect.value ? deliveryModeSelect.value : 'shipment';
      let shipmentKind = shipmentKindSelect && shipmentKindSelect.value ? shipmentKindSelect.value : 'tracked';

      if (isPdvMode()) {
        mode = 'immediate_in_hand';
        shipmentKind = '';
      }

      syncDeliveryInputs(mode, shipmentKind);
      const stateCode = resolveOrderState();
      let note = '';
      let noteIsWarning = false;
      const bagAction = resolveBagAction(mode, mode === 'shipment' ? shipmentKind : '');
      const openBag = resolveSelectedCustomerOpenBag();
      const isOpenBag = bagAction === 'open_bag';
      const hasPendingOpenFee = Boolean(
        bagAction === 'add_to_bag'
          && openBag
          && !openBag.opening_fee_paid
      );
      const hasPaidOpenFee = Boolean(
        bagAction === 'add_to_bag'
          && openBag
          && openBag.opening_fee_paid
      );
      const payingOpenFeeNow = Boolean(
        hasPendingOpenFee
          && openBagFeePayNowInput
          && openBagFeePayNowInput.checked
      );
      const isDeferred = Boolean(isOpenBag && openingFeeDeferredInput && openingFeeDeferredInput.checked);
      const openingFeeValue = resolveBagOpeningFeeValue(openBag);

      applyCreateDeliveryFields(mode, shipmentKind, bagAction, openBag);
      ensureOpenBagIfNeeded(mode, shipmentKind, bagAction, openBag);

      if (shippingTotalInput) {
        const shouldForceZero = isDeferred
          || (hasPendingOpenFee && !payingOpenFeeNow)
          || hasPaidOpenFee
          || isPdvMode()
          || mode === 'immediate_in_hand'
          || mode === 'store_pickup';
        if (shouldForceZero) {
          shippingTotalInput.value = formatNumber(0, 2);
          shippingTotalInput.dataset.userEdited = 'true';
        } else if (
          mode === 'shipment'
          && shipmentKind === 'bag_deferred'
          && ((isOpenBag && hasPendingOpenFee) || payingOpenFeeNow)
          && openingFeeValue > 0
        ) {
          const shouldApplyFee = payingOpenFeeNow || shippingTotalInput.dataset.userEdited !== 'true';
          if (shouldApplyFee) {
            shippingTotalInput.value = formatNumber(openingFeeValue, 2);
            if (payingOpenFeeNow) {
              shippingTotalInput.dataset.userEdited = 'true';
            }
          }
        } else if (forcePrice && shippingTotalInput.dataset.userEdited !== 'true' && String(shippingTotalInput.value || '').trim() === '') {
          shippingTotalInput.value = formatNumber(0, 2);
          shippingTotalInput.dataset.userEdited = 'false';
        }
      }

      if (mode === 'immediate_in_hand') {
        note = 'Entrega em mãos conclui automaticamente o status e não usa logística.';
      } else if (mode === 'store_pickup') {
        note = 'Retirada na loja não usa transportadora nem rastreio.';
      } else if (shipmentKind === 'tracked') {
        note = 'Envio rastreável exige transportadora; código de rastreio é opcional.';
      } else if (shipmentKind === 'local_courier') {
        note = 'Entrega local permite transportadora e rastreio opcionais.';
      } else if (shipmentKind === 'bag_deferred') {
        note = '';
        if (!openBag && bagAction === 'open_bag') {
          note = 'Sacolinha sera aberta automaticamente ao salvar o pedido.';
        }
        if (isDeferred) {
          note += (note ? ' ' : '') + 'Frete de abertura sera cobrado depois.';
        }
      }

      if (mode === 'shipment' && shipmentKind === 'tracked' && stateCode === '') {
        noteIsWarning = false;
      }

      if (openingFeeDeferredField) {
        openingFeeDeferredField.hidden = !isOpenBag;
        openingFeeDeferredField.style.display = isOpenBag ? '' : 'none';
        if (!isOpenBag && openingFeeDeferredInput) {
          openingFeeDeferredInput.checked = false;
        }
      }

      if (deliveryNote) {
        if (note) {
          deliveryNote.textContent = note;
          deliveryNote.style.color = noteIsWarning ? '#b91c1c' : 'var(--muted)';
          deliveryNote.hidden = false;
        } else {
          deliveryNote.textContent = '';
          deliveryNote.hidden = true;
        }
      }

      updateOrderTotal();
    };

    let updatePaymentSummary = () => {};

    const calculateOrderTotals = () => {
      let totalPieces = 0;
      const total = orderItems.reduce((sum, item) => {
        const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
        totalPieces += qty;
        const price = toNumber(item.price);
        const adjValue = toNumber(item.adjust_value);
        const finalUnit = applyAdjustment(price, item.adjust_type || '', adjValue);
        return sum + finalUnit * qty;
      }, 0);
      const shippingCost = shippingTotalInput ? toNumber(shippingTotalInput.value) : 0;
      const finalTotal = total + shippingCost;
      return { finalTotal, totalPieces, shippingCost };
    };

    const updateOrderTotal = () => {
      const totals = calculateOrderTotals();
      if (orderTotalValue) {
        orderTotalValue.textContent = formatMoney(totals.finalTotal);
      }
      if (orderTotalPieces) {
        orderTotalPieces.textContent = formatPieces(totals.totalPieces);
      }
      if (orderTotalShipping) {
        orderTotalShipping.textContent = `Frete: ${formatMoney(totals.shippingCost)}`;
      }
      updatePaymentSummary();
    };

    const setOrderMode = (mode, skipRestore = false) => {
      const normalized = mode === 'pdv' ? 'pdv' : 'online';
      if (orderModeInput) {
        orderModeInput.value = normalized;
      }
      if (orderModeButtons && orderModeButtons.length) {
        orderModeButtons.forEach((button) => {
          const isActive = (button.dataset.orderMode || 'online') === normalized;
          button.classList.toggle('primary', isActive);
          button.classList.toggle('ghost', !isActive);
        });
      }

      if (normalized === 'pdv') {
        if (salesChannelSelect) {
          cachedOnlineState.salesChannel = salesChannelSelect.value;
          const pdvChannelValue = findSalesChannelValue(pdvChannelLabel) || salesChannelSelect.value;
          salesChannelSelect.value = pdvChannelValue;
          salesChannelSelect.dataset.userEdited = 'true';
        }
        if (deliveryModeSelect) {
          cachedOnlineState.deliveryMode = deliveryModeSelect.value;
          deliveryModeSelect.value = 'immediate_in_hand';
          deliveryModeSelect.dataset.userEdited = 'true';
        }
        if (shipmentKindSelect) {
          cachedOnlineState.shipmentKind = shipmentKindSelect.value;
          shipmentKindSelect.value = '';
          shipmentKindSelect.dataset.userEdited = 'true';
        }
        if (shippingTotalInput) {
          cachedOnlineState.shippingTotal = shippingTotalInput.value;
          shippingTotalInput.value = formatNumber(0, 2);
          shippingTotalInput.dataset.userEdited = 'true';
        }
        if (onlineFields) {
          onlineFields.hidden = true;
          onlineFields.classList.add('order-mode--hidden');
        }
        if (pdvSummary) {
          pdvSummary.hidden = false;
        }
        if (deliveryNote) {
          deliveryNote.hidden = true;
        }
        updateMobileOrderModeUI(normalized);
        updateDeliveryPricing(true);
        return;
      }

      if (onlineFields) {
        onlineFields.hidden = false;
        onlineFields.classList.remove('order-mode--hidden');
      }
      if (pdvSummary) {
        pdvSummary.hidden = true;
      }
      if (!skipRestore) {
        if (salesChannelSelect) {
          salesChannelSelect.value = cachedOnlineState.salesChannel || '';
        }
        if (deliveryModeSelect) {
          deliveryModeSelect.value = cachedOnlineState.deliveryMode || 'shipment';
        }
        if (shipmentKindSelect) {
          shipmentKindSelect.value = cachedOnlineState.shipmentKind || (deliveryModeSelect && deliveryModeSelect.value === 'shipment' ? 'tracked' : '');
        }
        if (shippingTotalInput) {
          shippingTotalInput.value = cachedOnlineState.shippingTotal || '';
          shippingTotalInput.dataset.userEdited = cachedOnlineState.shippingTotal ? 'true' : 'false';
        }
      }
      updateMobileOrderModeUI(normalized);
      updateDeliveryPricing(true);
    };

    if (salesChannelSelect) {
      salesChannelSelect.addEventListener('change', () => {
        salesChannelSelect.dataset.userEdited = 'true';
        if (!isPhysicalStoreChannel()) return;
        if (deliveryModeSelect) {
          deliveryModeSelect.value = 'immediate_in_hand';
          deliveryModeSelect.dataset.userEdited = 'true';
        }
        if (shipmentKindSelect) {
          shipmentKindSelect.value = '';
          shipmentKindSelect.dataset.userEdited = 'true';
        }
        if (shippingTotalInput) {
          shippingTotalInput.dataset.userEdited = 'false';
        }
        updateDeliveryPricing(true);
      });
    }

    if (deliveryModeSelect) {
      deliveryModeSelect.addEventListener('change', () => {
        deliveryModeSelect.dataset.userEdited = 'true';
        const mode = deliveryModeSelect.value || 'shipment';
        if (shipmentKindSelect) {
          if (mode !== 'shipment') {
            shipmentKindSelect.value = '';
          } else if (!shipmentKindSelect.value) {
            shipmentKindSelect.value = 'tracked';
          }
        }
        if (shippingTotalInput) {
          shippingTotalInput.dataset.userEdited = 'false';
        }
        updateDeliveryPricing(true);
      });
    }

    if (shipmentKindSelect) {
      shipmentKindSelect.addEventListener('change', () => {
        shipmentKindSelect.dataset.userEdited = 'true';
        if (shippingTotalInput) {
          shippingTotalInput.dataset.userEdited = 'false';
        }
        updateDeliveryPricing(true);
      });
    }

    if (shippingTotalInput) {
      shippingTotalInput.addEventListener('input', () => {
        shippingTotalInput.dataset.userEdited = 'true';
        updateOrderTotal();
      });
    }

    if (orderModeButtons && orderModeButtons.length) {
      orderModeButtons.forEach((button) => {
        button.addEventListener('click', () => {
          if (isMobileViewport && isMobileViewport.matches) {
            mobileOrderModeSelected = true;
          }
          const mode = button.dataset.orderMode || 'online';
          setOrderMode(mode);
        });
      });
    }

    if (openingFeeDeferredInput) {
      openingFeeDeferredInput.addEventListener('change', () => {
        if (shippingTotalInput) {
          if (openingFeeDeferredInput.checked) {
            shippingTotalInput.value = formatNumber(0, 2);
            shippingTotalInput.dataset.userEdited = 'true';
          } else {
            shippingTotalInput.dataset.userEdited = 'false';
          }
        }
        updateDeliveryPricing(true);
      });
    }

    if (openBagFeePayNowInput) {
      openBagFeePayNowInput.addEventListener('change', () => {
        openBagFeePayNowInput.dataset.userToggled = 'true';
        const openBag = resolveSelectedCustomerOpenBag();
        const openingFeeValue = resolveBagOpeningFeeValue(openBag);
        if (shippingTotalInput) {
          if (openBagFeePayNowInput.checked) {
            shippingTotalInput.value = formatNumber(openingFeeValue, 2);
          } else {
            shippingTotalInput.value = formatNumber(0, 2);
          }
          shippingTotalInput.dataset.userEdited = 'true';
        }
        updateDeliveryPricing(true);
      });
    }

    const appendHiddenInput = (wrapper, name, value) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value === null || value === undefined ? '' : String(value);
      wrapper.appendChild(input);
    };

    const findVariation = (product, variationId) => {
      if (!product || !variationId) return null;
      const variations = Array.isArray(product.variations) ? product.variations : [];
      return variations.find((variation) => String(variation.id || variation.ID) === String(variationId)) || null;
    };

    const parseStockQuantity = (value) => {
      if (value === null || value === undefined || value === '') {
        return null;
      }
      const parsed = parseInt(String(value), 10);
      return Number.isFinite(parsed) ? parsed : null;
    };

    const resolveStockSource = (product, variationId) => {
      if (!product) return null;
      if (variationId) {
        const variation = findVariation(product, variationId);
        if (variation) {
          return variation;
        }
      }
      return product;
    };

    const resolveStockQuantity = (product, variationId) => {
      const source = resolveStockSource(product, variationId);
      if (!source) return null;
      const qtySource = source.quantity ?? null;
      return parseStockQuantity(qtySource);
    };

    const resolveStockStatus = (product, variationId) => {
      const source = resolveStockSource(product, variationId);
      if (!source) return '';
      const raw = String(source.availability_status || '').toLowerCase().trim();
      if (raw === 'instock' || raw === 'outofstock') {
        return raw;
      }
      const qty = resolveStockQuantity(product, variationId);
      const unifiedStatus = String(source.status_unified || source.status || '').toLowerCase().trim();
      return unifiedStatus === 'disponivel' && (qty === null || qty > 0) ? 'instock' : 'outofstock';
    };

    const resolveAvailableStock = (productSku, variationId, excludeIndex = -1) => {
      if (!productSku || !Object.prototype.hasOwnProperty.call(productOptions, productSku)) {
        return null;
      }
      const product = productOptions[productSku];
      const stockStatus = resolveStockStatus(product, variationId);
      if (stockStatus === 'outofstock') {
        return 0;
      }
      const stockQty = resolveStockQuantity(product, variationId);
      if (stockQty === null) {
        return null;
      }
      const used = sumOrderItemQuantities(productSku, variationId, excludeIndex);
      return Math.max(0, stockQty - used);
    };

    const thumbSize = 150;
    const buildThumbUrl = (src, size = thumbSize) => {
      const raw = String(src || '').trim();
      if (!raw) return '';
      let base = raw;
      let suffix = '';
      const queryIndex = raw.indexOf('?');
      const hashIndex = raw.indexOf('#');
      const cutIndex = Math.min(
        queryIndex === -1 ? raw.length : queryIndex,
        hashIndex === -1 ? raw.length : hashIndex
      );
      if (cutIndex < raw.length) {
        base = raw.slice(0, cutIndex);
        suffix = raw.slice(cutIndex);
      }
      const extMatch = base.match(/\.[^./]+$/);
      if (!extMatch) return raw;
      const ext = extMatch[0];
      let stem = base.slice(0, -ext.length);
      if (!stem) return raw;
      if (/-\d+x\d+$/.test(stem)) {
        stem = stem.replace(/-\d+x\d+$/, `-${size}x${size}`);
      } else {
        stem += `-${size}x${size}`;
      }
      return `${stem}${ext}${suffix}`;
    };

    const resolveItemImage = (product, variation) => {
      const variationSrc = variation && variation.image_src ? String(variation.image_src).trim() : '';
      if (variationSrc) {
        return variationSrc;
      }
      const productSrc = product && product.image_src ? String(product.image_src).trim() : '';
      return productSrc;
    };

    const renderOrderItems = () => {
      if (!orderItemsList) return;
      clearNode(orderItemsList);
      if (!orderItems.length) {
        const empty = document.createElement('div');
        empty.className = 'order-items-empty';
        empty.textContent = 'Nenhum item adicionado.';
        orderItemsList.appendChild(empty);
        updateOrderTotal();
        return;
      }

      orderItems.forEach((item, index) => {
        const itemProductSku = String(item.product_sku || '').trim();
        const product = itemProductSku && productOptions[itemProductSku] ? productOptions[itemProductSku] : null;
        const variation = product ? findVariation(product, item.variation_id) : null;
        const productLabel = product ? resolveProductLabel(product) : (item.product_label || 'Produto');
        const variationLabel = variation ? buildVariationLabel(variation) : (item.variation_id ? `Variação #${item.variation_id}` : '');
        const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
        const priceValue = toNumber(item.price);
        const adjustValue = toNumber(item.adjust_value);
        const finalUnit = applyAdjustment(priceValue, item.adjust_type || '', adjustValue);
        const lineTotal = finalUnit * qty;

        const card = document.createElement('div');
        card.className = 'order-item-card';

        const thumb = document.createElement('div');
        thumb.className = 'order-item-thumb';
        const imageSrc = resolveItemImage(product, variation);
        if (imageSrc) {
          const thumbHelper = window.RetratoThumbnail || null;
          let img;
          if (thumbHelper && typeof thumbHelper.createElement === 'function') {
            img = thumbHelper.createElement({
              fullSrc: imageSrc,
              size: thumbSize,
              alt: productLabel,
            });
          } else {
            img = document.createElement('img');
            const thumbSrc = buildThumbUrl(imageSrc, thumbSize);
            img.src = thumbSrc || imageSrc;
            img.alt = productLabel || 'Produto';
          }
          thumb.appendChild(img);
        } else {
          const placeholder = document.createElement('span');
          placeholder.textContent = 'Sem imagem';
          thumb.appendChild(placeholder);
        }

        const main = document.createElement('div');
        main.className = 'order-item-main';
        const title = document.createElement('div');
        title.className = 'order-item-title';
        title.textContent = productLabel;
        const meta = document.createElement('div');
        meta.className = 'order-item-meta';
        const metaParts = [];
        if (variationLabel) metaParts.push(variationLabel);
        metaParts.push(`Qtd ${qty}`);
        metaParts.push(`${formatMoney(priceValue)} un`);
        const adjustmentLabel = describeAdjustment(item.adjust_type || '', adjustValue);
        if (adjustmentLabel) {
          metaParts.push(adjustmentLabel);
        }
        meta.textContent = metaParts.join(' | ');
        main.appendChild(title);
        main.appendChild(meta);

        const total = document.createElement('div');
        total.className = 'order-item-total';
        total.textContent = formatMoney(lineTotal);

        const actions = document.createElement('div');
        actions.className = 'order-item-actions';

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'order-item-action';
        editButton.title = 'Editar item';
        editButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75 1.13-1.13z"/></svg>';
        editButton.addEventListener('click', () => {
          item._editing = !item._editing;
          if (item._editing) {
            item._showAdjust = false;
          }
          renderOrderItems();
        });

        const adjustButton = document.createElement('button');
        adjustButton.type = 'button';
        adjustButton.className = 'order-item-action';
        adjustButton.title = 'Ajuste';
        adjustButton.textContent = '$';
        adjustButton.addEventListener('click', () => {
          item._showAdjust = !item._showAdjust;
          if (item._showAdjust) {
            item._editing = false;
          }
          renderOrderItems();
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'order-item-action';
        removeButton.title = 'Excluir item';
        removeButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>';
        removeButton.addEventListener('click', () => {
          orderItems.splice(index, 1);
          renderOrderItems();
        });

        actions.appendChild(editButton);
        actions.appendChild(adjustButton);
        actions.appendChild(removeButton);

        card.appendChild(thumb);
        card.appendChild(main);
        card.appendChild(total);
        card.appendChild(actions);

        if (item._editing) {
          const editor = document.createElement('div');
          editor.className = 'order-item-panel';
          editor.innerHTML = `
            <div class="order-item-panel__grid">
              <div class="field">
                <label>Quantidade</label>
                <input type="number" min="1" data-edit-qty>
              </div>
              <div class="field">
                <label>Preço unitário (R$)</label>
                <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" data-edit-price>
              </div>
              <div class="field" data-edit-variation-field>
                <label>Variação</label>
                <select data-edit-variation></select>
              </div>
            </div>
            <div class="order-item-panel__actions">
              <button type="button" class="btn ghost" data-edit-cancel>Cancelar</button>
              <button type="button" class="primary" data-edit-save>Salvar</button>
            </div>
          `;
          const qtyInput = editor.querySelector('[data-edit-qty]');
          const priceInput = editor.querySelector('[data-edit-price]');
          const variationField = editor.querySelector('[data-edit-variation-field]');
          const variationSelect = editor.querySelector('[data-edit-variation]');
          if (qtyInput) qtyInput.value = String(qty);
          if (priceInput) priceInput.value = priceValue ? formatNumber(priceValue, 2) : '';

          if (variationSelect && variationField) {
            clearNode(variationSelect);
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Sem variação';
            variationSelect.appendChild(placeholder);
            const variations = product && Array.isArray(product.variations) ? product.variations : [];
            if (!variations.length) {
              variationField.hidden = true;
            } else {
              variationField.hidden = false;
              variations.forEach((variationItem) => {
                const variationId = String(variationItem.id || variationItem.ID || '');
                if (!variationId) return;
                const option = document.createElement('option');
                option.value = variationId;
                option.textContent = buildVariationLabel(variationItem);
                variationSelect.appendChild(option);
              });
              const savedVariation = item.variation_id ? String(item.variation_id) : '';
              if (savedVariation) {
                variationSelect.value = savedVariation;
              } else if (variations.length === 1) {
                const onlyVariationId = String(variations[0].id || variations[0].ID || '');
                if (onlyVariationId) {
                  variationSelect.value = onlyVariationId;
                }
              } else {
                variationSelect.value = '';
              }
            }
          }

          editor.querySelector('[data-edit-cancel]').addEventListener('click', () => {
            item._editing = false;
            renderOrderItems();
          });
          editor.querySelector('[data-edit-save]').addEventListener('click', () => {
            let nextQty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : qty;
            const nextPrice = priceInput ? parseOptionalNumber(priceInput.value) : item.price;
            const nextVariation = variationSelect && !variationField.hidden ? variationSelect.value : '';
            const availableStock = resolveAvailableStock(itemProductSku, nextVariation, index);
            if (availableStock !== null) {
              if (availableStock <= 0) {
                window.alert('Disponibilidade máxima já reservada para este item. Ajuste as quantidades antes de salvar.');
                return;
              }
              if (nextQty > availableStock) {
                nextQty = availableStock;
                if (qtyInput) {
                  qtyInput.value = String(availableStock);
                }
                window.alert(`Quantidade ajustada para a disponibilidade atual (${availableStock}).`);
              }
            }
            item.quantity = Math.max(1, nextQty);
            item.price = nextPrice;
            item.variation_id = nextVariation;
            item._editing = false;
            renderOrderItems();
          });

          card.appendChild(editor);
        }

        if (item._showAdjust) {
          const adjustPanel = document.createElement('div');
          adjustPanel.className = 'order-item-panel order-item-panel--adjust';
          adjustPanel.innerHTML = `
            <div class="order-item-panel__grid">
              <div class="field">
                <label>Ajuste</label>
                <select data-adjust-type>
                  <option value="">Sem ajuste</option>
                  <option value="discount_percent">Desconto (%)</option>
                  <option value="discount_value">Desconto (R$)</option>
                  <option value="increase_percent">Acrescimo (%)</option>
                  <option value="increase_value">Acrescimo (R$)</option>
                </select>
              </div>
              <div class="field">
                <label>Valor do ajuste</label>
                <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" data-adjust-value>
              </div>
            </div>
            <div class="order-item-panel__actions">
              <button type="button" class="btn ghost" data-adjust-clear>Limpar</button>
              <button type="button" class="primary" data-adjust-save>Aplicar</button>
            </div>
          `;
          const adjustTypeInput = adjustPanel.querySelector('[data-adjust-type]');
          const adjustValueInput = adjustPanel.querySelector('[data-adjust-value]');
          if (adjustTypeInput) {
            adjustTypeInput.value = item.adjust_type || '';
          }
          if (adjustValueInput) {
            adjustValueInput.value = item.adjust_value !== '' ? formatNumber(toNumber(item.adjust_value), 2) : '';
          }
          adjustPanel.querySelector('[data-adjust-clear]').addEventListener('click', () => {
            item.adjust_type = '';
            item.adjust_value = '';
            item._showAdjust = false;
            renderOrderItems();
          });
          adjustPanel.querySelector('[data-adjust-save]').addEventListener('click', () => {
            item.adjust_type = adjustTypeInput ? adjustTypeInput.value : '';
            item.adjust_value = adjustValueInput ? parseOptionalNumber(adjustValueInput.value) : '';
            item._showAdjust = false;
            renderOrderItems();
          });
          card.appendChild(adjustPanel);
        }

        appendHiddenInput(card, `items[${index}][line_id]`, item.line_id || '');
        const submitSku = item.product_sku || '';
        appendHiddenInput(card, `items[${index}][product_sku]`, submitSku);
        appendHiddenInput(card, `items[${index}][variation_id]`, item.variation_id || '');
        appendHiddenInput(card, `items[${index}][quantity]`, qty);
        appendHiddenInput(card, `items[${index}][price]`, item.price);
        appendHiddenInput(card, `items[${index}][adjust_type]`, item.adjust_type || '');
        appendHiddenInput(card, `items[${index}][adjust_value]`, item.adjust_value);

        orderItemsList.appendChild(card);
      });

      updateOrderTotal();
    };

    const resolvePaymentMethod = (methodId) => {
      if (!methodId) return null;
      const key = String(methodId);
      return paymentMethodOptions && paymentMethodOptions[key] ? paymentMethodOptions[key] : null;
    };

    const resolveBankAccount = (accountId) => {
      if (!accountId) return null;
      const key = String(accountId);
      return bankAccountOptions && bankAccountOptions[key] ? bankAccountOptions[key] : null;
    };

    const resolvePaymentTerminal = (terminalId) => {
      if (!terminalId) return null;
      const key = String(terminalId);
      return paymentTerminalOptions && paymentTerminalOptions[key] ? paymentTerminalOptions[key] : null;
    };

    const resolveVoucherAccount = (accountId) => {
      if (!accountId) return null;
      const key = String(accountId);
      return voucherAccountOptions && voucherAccountOptions[key] ? voucherAccountOptions[key] : null;
    };

    const formatBankAccountLabel = (account) => {
      if (!account) return '';
      const bankName = String(account.bank_name || '').trim();
      const label = String(account.label || '').trim();
      if (bankName && label) return `${bankName} - ${label}`;
      if (label) return label;
      if (bankName) return bankName;
      return account.id ? `Conta #${account.id}` : 'Conta';
    };

    const formatPixKey = (account) => {
      if (!account) return '';
      const key = String(account.pix_key || '').trim();
      if (!key) return '';
      const type = String(account.pix_key_type || '').trim();
      return type ? `${type.toUpperCase()}: ${key}` : key;
    };

    const getSelectedCustomerId = () => {
      if (!customerInput) return 0;
      const parsed = parseInt(String(customerInput.value || '').trim(), 10);
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const resolveSelectedCustomerIds = () => {
      const customerId = getSelectedCustomerId();
      const customer = resolveSelectedCustomer();
      const rawUserId = customer ? parseInt(String(customer.user_id || ''), 10) : 0;
      const userId = Number.isFinite(rawUserId) ? rawUserId : 0;
      return {
        customerId,
        userId,
        hasCustomer: customerId > 0 || userId > 0,
      };
    };

    const formatVoucherAccountLabel = (account) => {
      if (!account) return '';
      const label = String(account.label || '').trim();
      const code = String(account.code || '').trim();
      if (label && code) return `${label} (${code})`;
      if (label) return label;
      if (code) return code;
      return account.id ? `Cupom/crédito #${account.id}` : 'Cupom/crédito';
    };

    const updateVoucherMeta = () => {
      if (!paymentVoucherMeta) return;
      const accountId = paymentVoucherSelect ? paymentVoucherSelect.value : '';
      const account = resolveVoucherAccount(accountId);
      if (!account) {
        paymentVoucherMeta.textContent = '';
        paymentVoucherMeta.hidden = true;
        return;
      }
      const balance = toNumber(account.balance);
      paymentVoucherMeta.textContent = `Saldo disponivel: ${formatMoney(balance)}`;
      paymentVoucherMeta.hidden = false;
    };

    const rebuildVoucherAccountOptions = (selectedId = '') => {
      if (!paymentVoucherSelect) return;
      clearNode(paymentVoucherSelect);
      const { customerId, userId, hasCustomer } = resolveSelectedCustomerIds();
      const placeholder = document.createElement('option');
      placeholder.value = '';

      const matchIds = [customerId, userId].filter((value) => value > 0);
      const accounts = Object.values(voucherAccountOptions || {})
        .filter((account) => matchIds.includes(Number(account.pessoa_id || 0)));

      if (!hasCustomer) {
        placeholder.textContent = 'Informe a cliente';
        paymentVoucherSelect.appendChild(placeholder);
        updateVoucherMeta();
        return;
      }

      if (!accounts.length) {
        placeholder.textContent = 'Nenhum cupom/crédito';
        paymentVoucherSelect.appendChild(placeholder);
        updateVoucherMeta();
        return;
      }

      placeholder.textContent = 'Selecione';
      paymentVoucherSelect.appendChild(placeholder);
      accounts.forEach((account) => {
        const option = document.createElement('option');
        option.value = String(account.id || '');
        option.textContent = `${formatVoucherAccountLabel(account)} - Saldo ${formatMoney(toNumber(account.balance))}`;
        paymentVoucherSelect.appendChild(option);
      });

      if (selectedId && Array.from(paymentVoucherSelect.options).some((opt) => opt.value === String(selectedId))) {
        paymentVoucherSelect.value = String(selectedId);
      }

      updateVoucherMeta();
    };

    const isPixMethod = (method) => {
      if (!method) return false;
      const type = String(method.type || '').trim().toLowerCase();
      if (type === 'pix') return true;
      const name = normalizeLabel(method.name || '');
      return name === 'pix' || name.includes('pix');
    };

    const isCardMethod = (method) => {
      if (!method) return false;
      const type = String(method.type || '').trim().toLowerCase();
      if (type === 'debit_card' || type === 'credit_card') return true;
      const name = normalizeLabel(method.name || '');
      return name.includes('cartao de debito') || name.includes('cartao de credito');
    };

    const isVoucherMethod = (method) => {
      if (!method) return false;
      const type = String(method.type || '').trim().toLowerCase();
      if (type !== '') {
        return type === 'voucher';
      }
      const name = normalizeLabel(method.name || '');
      if (!name) return false;
      if (name.includes('cupom')) return true;
      return name.includes('credito') && !name.includes('cartao');
    };

    const methodShowsBank = (method) => isPixMethod(method);
    const methodRequiresBank = (method) => isPixMethod(method);
    const methodShowsTerminal = (method) => isCardMethod(method) || isPixMethod(method);
    const methodRequiresTerminal = (method) => isCardMethod(method);
    const methodShowsVoucher = (method) => isVoucherMethod(method);
    const methodRequiresVoucher = (method) => isVoucherMethod(method);

    const findPaymentMethodIdByType = (targetType) => {
      const normalized = String(targetType || '').trim().toLowerCase();
      const methods = Object.values(paymentMethodOptions || {});
      for (const method of methods) {
        const methodType = String(method.type || '').trim().toLowerCase();
        if (normalized && methodType === normalized) {
          return String(method.id || '');
        }
        if (normalized === 'pix' && normalizeLabel(method.name || '') === 'pix') {
          return String(method.id || '');
        }
      }
      return '';
    };

    const findBankAccountIdByName = (targetName) => {
      const target = normalizeLabel(targetName);
      if (!target) return '';
      const accounts = Object.values(bankAccountOptions || {});
      for (const account of accounts) {
        const bankName = normalizeLabel(account.bank_name || '');
        const label = normalizeLabel(account.label || '');
        const pixKey = normalizeLabel(account.pix_key || '');
        if (bankName.includes(target) || label.includes(target) || pixKey.includes(target)) {
          return String(account.id || '');
        }
      }
      return '';
    };

    const applyDefaultPaymentPicker = () => {
      if (!orderPaymentMethodSelect) return;
      const isMobile = isMobileViewport && isMobileViewport.matches;
      if (!orderPaymentMethodSelect.value || isMobile) {
        const pixMethodId = findPaymentMethodIdByType('pix');
        if (pixMethodId) {
          orderPaymentMethodSelect.value = pixMethodId;
        }
      }
      updatePaymentPickerState();
      const method = resolvePaymentMethod(orderPaymentMethodSelect.value);
      if (method && isPixMethod(method) && paymentBankSelect && (!paymentBankSelect.value || isMobile)) {
        const targetBankId = findBankAccountIdByName('itau') || paymentBankSelect.value;
        if (targetBankId) {
          paymentBankSelect.value = targetBankId;
          updatePaymentPixKey();
        }
      }
    };

    const resolveFeeValue = (method) => {
      if (!method) return 0;
      return toNumber(method.fee_value);
    };

    const computePaymentFee = (method, amount) => {
      if (!method) return 0;
      const base = Math.max(0, Number.isFinite(amount) ? amount : 0);
      const feeValue = resolveFeeValue(method);
      const feeType = String(method.fee_type || 'none');
      if (feeType === 'percent') {
        return base * (feeValue / 100);
      }
      if (feeType === 'fixed') {
        return feeValue;
      }
      return 0;
    };

    const isTerminalCompatible = (terminal, methodType) => {
      if (!terminal) return false;
      const terminalType = String(terminal.type || '').trim();
      if (!methodType) return true;
      if (methodType === 'debit_card') {
        return ['debit', 'both', 'link', 'other'].includes(terminalType);
      }
      if (methodType === 'credit_card') {
        return ['credit', 'both', 'link', 'other'].includes(terminalType);
      }
      return true;
    };

    const rebuildTerminalOptions = (select, methodType, selectedId = '') => {
      if (!select) return;
      clearNode(select);
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Selecione';
      select.appendChild(placeholder);

      const terminals = Object.values(paymentTerminalOptions || {});
      let filtered = terminals.filter((terminal) => isTerminalCompatible(terminal, methodType));
      if (!filtered.length) {
        filtered = terminals;
      }
      filtered.forEach((terminal) => {
        const option = document.createElement('option');
        option.value = String(terminal.id || '');
        option.textContent = String(terminal.name || '');
        select.appendChild(option);
      });
      if (selectedId && Array.from(select.options).some((opt) => opt.value === String(selectedId))) {
        select.value = String(selectedId);
      }
    };

    const setPaymentHelper = (message, isError = false) => {
      if (!orderPaymentHelper) return;
      orderPaymentHelper.textContent = message;
      if (isError) {
        orderPaymentHelper.classList.add('order-payment-helper--error');
      } else {
        orderPaymentHelper.classList.remove('order-payment-helper--error');
      }
    };

    const updatePaymentPixKey = () => {
      if (!paymentPixKey) return;
      const accountId = paymentBankSelect ? paymentBankSelect.value : '';
      const account = resolveBankAccount(accountId);
      const pixLabel = formatPixKey(account);
      if (pixLabel) {
        paymentPixKey.textContent = `Chave PIX: ${pixLabel}`;
        paymentPixKey.hidden = false;
      } else {
        paymentPixKey.textContent = '';
        paymentPixKey.hidden = true;
      }
    };

    const updatePaymentPickerVisibility = (method) => {
      const showsBank = methodShowsBank(method);
      const showsTerminal = methodShowsTerminal(method);
      const showsVoucher = methodShowsVoucher(method);
      if (paymentBankField) {
        paymentBankField.hidden = !showsBank;
      }
      if (paymentBankSelect) {
        if (!showsBank) {
          paymentBankSelect.value = '';
        }
      }
      if (paymentTerminalField) {
        paymentTerminalField.hidden = !showsTerminal;
      }
      if (paymentTerminalSelect) {
        if (!showsTerminal) {
          paymentTerminalSelect.value = '';
        }
      }
      if (paymentVoucherField) {
        paymentVoucherField.hidden = !showsVoucher;
      }
      if (paymentVoucherSelect) {
        if (!showsVoucher) {
          paymentVoucherSelect.value = '';
        }
      }
      if (showsTerminal) {
        rebuildTerminalOptions(paymentTerminalSelect, method ? String(method.type || '') : '', paymentTerminalSelect ? paymentTerminalSelect.value : '');
      }
      if (showsVoucher) {
        rebuildVoucherAccountOptions(paymentVoucherSelect ? paymentVoucherSelect.value : '');
      } else if (paymentVoucherMeta) {
        paymentVoucherMeta.textContent = '';
        paymentVoucherMeta.hidden = true;
      }
      updatePaymentPixKey();
    };

    const sumPaidPayments = () => {
      return orderPayments.reduce((sum, entry) => {
        return entry && entry.paid === false ? sum : sum + toNumber(entry.amount);
      }, 0);
    };

    const sumPaidFees = () => {
      return orderPayments.reduce((sum, entry) => {
        return entry && entry.paid === false ? sum : sum + toNumber(entry.fee);
      }, 0);
    };

    const resolveLogisticsCompletion = () => {
      const mode = deliveryModeInput ? String(deliveryModeInput.value || '').trim() : '';
      const kind = shipmentKindInput ? String(shipmentKindInput.value || '').trim() : '';
      const carrier = carrierCreateInput ? String(carrierCreateInput.value || '').trim() : '';

      if (!mode) return { complete: false, partial: false };
      if (mode === 'immediate_in_hand' || mode === 'store_pickup') {
        return { complete: true, partial: true };
      }
      if (mode !== 'shipment') {
        return { complete: true, partial: true };
      }
      if (!kind) return { complete: false, partial: true };
      if (kind === 'tracked') {
        const done = carrier !== '';
        return { complete: done, partial: true };
      }
      if (kind === 'local_courier') {
        return { complete: true, partial: true };
      }
      if (kind === 'bag_deferred') {
        return { complete: true, partial: true };
      }
      return { complete: true, partial: true };
    };

    const setStepVisualState = (key, state, label) => {
      const node = stepStatusNodes.find((item) => item.dataset.stepStatus === key);
      if (!node) return;
      node.textContent = label;
      node.classList.remove('is-complete', 'is-partial', 'is-incomplete');
      node.classList.add(state === 'complete' ? 'is-complete' : (state === 'partial' ? 'is-partial' : 'is-incomplete'));
      const section = getSectionNode(key);
      if (section) {
        section.classList.remove('is-complete', 'is-partial', 'is-incomplete');
        section.classList.add(state === 'complete' ? 'is-complete' : (state === 'partial' ? 'is-partial' : 'is-incomplete'));
      }
    };

    const maybeAdvanceWizard = (stepState) => {
      if (currentLayout !== 'layout-2') return;
      const sequence = ['customer', 'items', 'logistics', 'payments', 'review'];
      for (let i = 0; i < sequence.length - 1; i += 1) {
        const currentKey = sequence[i];
        const nextKey = sequence[i + 1];
        if (stepState[currentKey] !== 'complete') {
          break;
        }
        setSectionCollapsed(nextKey, false);
        if (isNarrowWizardViewport.matches) {
          collapseOtherSectionsOnMobile(nextKey);
        }
      }
    };

    syncLayoutState = () => {
      const totals = calculateOrderTotals();
      const customerId = customerInput ? parseInt(String(customerInput.value || '').trim(), 10) : 0;
      const hasCustomer = Number.isFinite(customerId) && customerId > 0;
      const hasItems = orderItems.length > 0 && totals.totalPieces > 0;
      const logistics = resolveLogisticsCompletion();
      const paidTotal = sumPaidPayments();
      const remaining = totals.finalTotal - paidTotal;
      const hasPayment = orderPayments.length > 0;

      const stepState = {
        customer: hasCustomer ? 'complete' : 'incomplete',
        items: hasItems ? 'complete' : 'incomplete',
        logistics: logistics.complete ? 'complete' : (logistics.partial ? 'partial' : 'incomplete'),
        payments: !hasPayment
          ? 'incomplete'
          : (remaining <= 0.00001 ? 'complete' : 'partial'),
        review: remaining <= 0.00001 && hasItems && hasCustomer ? 'complete' : (hasItems ? 'partial' : 'incomplete'),
      };

      setStepVisualState('customer', stepState.customer, hasCustomer ? 'Concluido' : 'Incompleto');
      setStepVisualState('items', stepState.items, hasItems ? 'Concluido' : 'Incompleto');
      setStepVisualState('logistics', stepState.logistics, logistics.complete ? 'Concluido' : 'Pendente');
      if (stepState.payments === 'complete') {
        setStepVisualState('payments', stepState.payments, 'Pago');
      } else if (stepState.payments === 'partial') {
        setStepVisualState('payments', stepState.payments, 'Parcial');
      } else {
        setStepVisualState('payments', stepState.payments, 'Incompleto');
      }
      if (stepState.review === 'complete') {
        setStepVisualState('review', stepState.review, 'Pronto');
      } else if (stepState.review === 'partial') {
        setStepVisualState('review', stepState.review, 'Revisar');
      } else {
        setStepVisualState('review', stepState.review, 'Pendente');
      }

      maybeAdvanceWizard(stepState);

      const remainingAbs = Math.abs(remaining);
      const remainingLabel = formatMoney(remainingAbs);
      const paymentStateLabel = !hasPayment
        ? 'Pedido incompleto'
        : (remaining <= 0.00001 ? 'Pago integral' : 'Pago parcial');
      const alertMessages = [];
      if (!hasCustomer) alertMessages.push('Defina a cliente.');
      if (!hasItems) alertMessages.push('Inclua ao menos um item.');
      if (!logistics.complete) alertMessages.push('Revise logística e frete.');
      if (!hasPayment) alertMessages.push('Inclua pagamento.');
      if (hasPayment && remaining > 0.00001) alertMessages.push(`Falta ${formatMoney(remaining)} para concluir.`);

      if (orderPanelState) {
        orderPanelState.textContent = paymentStateLabel;
      }
      if (orderPanelTotal) {
        orderPanelTotal.textContent = formatMoney(totals.finalTotal + sumPaidFees());
      }
      if (orderPanelItems) {
        orderPanelItems.textContent = formatPieces(totals.totalPieces);
      }
      if (orderPanelPaid) {
        orderPanelPaid.textContent = formatMoney(paidTotal);
      }
      if (orderPanelRemaining) {
        orderPanelRemaining.textContent = remainingLabel;
        const panelRemainingRow = orderPanelRemaining.closest('.order-layout-sidebar__row');
        if (panelRemainingRow) {
          panelRemainingRow.classList.toggle('order-layout-sidebar__row--alert', remaining > 0.00001);
        }
      }
      if (orderPanelAlerts) {
        orderPanelAlerts.textContent = alertMessages.length ? alertMessages.join(' ') : 'Todos os pontos essenciais foram preenchidos.';
      }

      if (orderTotalRemaining) {
        orderTotalRemaining.classList.remove('is-alert', 'is-ok');
        orderTotalRemaining.classList.add(remaining > 0.00001 ? 'is-alert' : 'is-ok');
      }
      if (orderTotalSummary) {
        orderTotalSummary.classList.remove('is-state-incomplete', 'is-state-partial', 'is-state-paid');
        if (!hasPayment) {
          orderTotalSummary.classList.add('is-state-incomplete');
        } else if (remaining > 0.00001) {
          orderTotalSummary.classList.add('is-state-partial');
        } else {
          orderTotalSummary.classList.add('is-state-paid');
        }
      }
    };

    const suggestPaymentAmount = () => {
      if (!orderPaymentAmountInput) return;
      if (orderPaymentAmountInput.dataset.userEdited === 'true') return;
      const totals = calculateOrderTotals();
      const paidTotal = sumPaidPayments();
      const remaining = Math.max(0, totals.finalTotal - paidTotal);
      orderPaymentAmountInput.value = remaining > 0 ? formatNumber(remaining, 2) : '';
      orderPaymentAmountInput.dataset.userEdited = 'false';
    };

    const updatePaymentFeeInput = (method) => {
      if (!orderPaymentFeeInput) return;
      if (orderPaymentFeeInput.dataset.userEdited === 'true') return;
      const amount = orderPaymentAmountInput ? toNumber(orderPaymentAmountInput.value) : 0;
      const fee = computePaymentFee(method, amount);
      orderPaymentFeeInput.value = fee > 0 ? formatNumber(fee, 2) : '';
      orderPaymentFeeInput.dataset.userEdited = 'false';
    };

    const updatePaymentPickerState = () => {
      const methodId = orderPaymentMethodSelect ? orderPaymentMethodSelect.value : '';
      const method = resolvePaymentMethod(methodId);
      updatePaymentPickerVisibility(method);
      suggestPaymentAmount();
      updatePaymentFeeInput(method);
    };

    updatePaymentSummary = () => {
      const totals = calculateOrderTotals();
      const paidTotal = sumPaidPayments();
      const feeTotal = sumPaidFees();
      const remaining = totals.finalTotal - paidTotal;
      const absRemaining = Math.abs(remaining);
      const remainingLabel = remaining >= 0
        ? `Falta: ${formatMoney(absRemaining)}`
        : `Excedente: ${formatMoney(absRemaining)}`;
      if (orderTotalValue) {
        orderTotalValue.textContent = formatMoney(totals.finalTotal + feeTotal);
      }
      if (orderTotalPaid) {
        orderTotalPaid.textContent = `Pago: ${formatMoney(paidTotal)}`;
      }
      if (orderTotalFees) {
        orderTotalFees.textContent = `Taxas: ${formatMoney(feeTotal)}`;
      }
      if (orderTotalRemaining) {
        orderTotalRemaining.textContent = remainingLabel;
      }
      suggestPaymentAmount();
      const methodId = orderPaymentMethodSelect ? orderPaymentMethodSelect.value : '';
      updatePaymentFeeInput(resolvePaymentMethod(methodId));
      syncLayoutState();
    };

    const renderOrderPayments = () => {
      if (!orderPaymentsList) return;
      clearNode(orderPaymentsList);
      if (!orderPayments.length) {
        const empty = document.createElement('div');
        empty.className = 'order-payments-empty';
        empty.textContent = 'Nenhum pagamento adicionado.';
        orderPaymentsList.appendChild(empty);
        updatePaymentSummary();
        return;
      }

      orderPayments.forEach((entry, index) => {
        const card = document.createElement('div');
        card.className = 'order-payment-card';

        const main = document.createElement('div');
        main.className = 'order-payment-main';
        const titleRow = document.createElement('div');
        titleRow.className = 'order-payment-title-row';
        const title = document.createElement('div');
        title.className = 'order-payment-title';
        title.textContent = entry.method_name || 'Método';
        const status = document.createElement('span');
        status.className = 'order-payment-status';
        status.textContent = entry.paid === false ? 'Aguardando pagamento' : 'Pago';
        if (entry.paid === false) {
          status.classList.add('order-payment-status--pending');
        }
        titleRow.appendChild(title);
        titleRow.appendChild(status);
        const meta = document.createElement('div');
        meta.className = 'order-payment-meta';
        const metaParts = [];
        if (entry.voucher_account_label) {
          metaParts.push(`Cupom: ${entry.voucher_account_label}`);
        }
        if (entry.bank_account_label) {
          metaParts.push(`Banco: ${entry.bank_account_label}`);
        }
        if (entry.terminal_label) {
          metaParts.push(`Maquininha: ${entry.terminal_label}`);
        }
        if (toNumber(entry.fee) > 0) {
          metaParts.push(`Taxa: ${formatMoney(toNumber(entry.fee))}`);
        }
        if (metaParts.length) {
          meta.textContent = metaParts.join(' | ');
        } else {
          meta.textContent = 'Sem detalhes adicionais.';
        }
        main.appendChild(titleRow);
        main.appendChild(meta);

        const amount = document.createElement('div');
        amount.className = 'order-payment-amount';
        amount.textContent = formatMoney(toNumber(entry.amount) + toNumber(entry.fee));

        const actions = document.createElement('div');
        actions.className = 'order-payment-actions';

        const paidButton = document.createElement('button');
        paidButton.type = 'button';
        paidButton.className = 'order-item-action order-payment-action';
        if (entry.paid === false) {
          paidButton.classList.add('order-payment-action--pending');
          paidButton.title = 'Marcar como pago';
          paidButton.setAttribute('aria-label', 'Marcar pagamento como pago');
          paidButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 8v5l3 3-1.2 1.2L10 13V8h2zm0-6a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16z"/></svg>';
        } else {
          paidButton.classList.add('order-payment-action--paid');
          paidButton.title = 'Marcar como nao pago';
          paidButton.setAttribute('aria-label', 'Marcar pagamento como nao pago');
          paidButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m9 16.2-3.5-3.5L4 14.2 9 19l11-11-1.5-1.4z"/></svg>';
        }
        paidButton.addEventListener('click', () => {
          entry.paid = entry.paid === false;
          renderOrderPayments();
        });

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'order-item-action';
        editButton.title = 'Editar pagamento';
        editButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75 1.13-1.13z"/></svg>';
        editButton.addEventListener('click', () => {
          entry._editing = !entry._editing;
          renderOrderPayments();
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'order-item-action';
        removeButton.title = 'Excluir pagamento';
        removeButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/></svg>';
        removeButton.addEventListener('click', () => {
          orderPayments.splice(index, 1);
          renderOrderPayments();
        });

        actions.appendChild(paidButton);
        actions.appendChild(editButton);
        actions.appendChild(removeButton);

        card.appendChild(main);
        card.appendChild(amount);
        card.appendChild(actions);

        if (entry._editing) {
          const editor = document.createElement('div');
          editor.className = 'order-payment-panel';
          editor.innerHTML = `
            <div class="order-payment-panel__grid">
              <div class="field">
                <label>Valor (R$)</label>
                <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" data-edit-amount>
              </div>
              <div class="field">
                <label>Taxa (R$)</label>
                <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" data-edit-fee>
              </div>
              <div class="field" data-edit-bank-field>
                <label>Banco/PIX</label>
                <select data-edit-bank></select>
              </div>
              <div class="field" data-edit-terminal-field>
                <label>Maquininha</label>
                <select data-edit-terminal></select>
              </div>
            </div>
            <div class="order-payment-panel__actions">
              <button type="button" class="btn ghost" data-edit-cancel>Cancelar</button>
              <button type="button" class="primary" data-edit-save>Salvar</button>
            </div>
          `;
          const amountInput = editor.querySelector('[data-edit-amount]');
          const feeInput = editor.querySelector('[data-edit-fee]');
          const bankField = editor.querySelector('[data-edit-bank-field]');
          const bankSelect = editor.querySelector('[data-edit-bank]');
          const terminalField = editor.querySelector('[data-edit-terminal-field]');
          const terminalSelect = editor.querySelector('[data-edit-terminal]');
          const method = resolvePaymentMethod(entry.method_id);

          const entryAmount = toNumber(entry.amount);
          const entryFee = toNumber(entry.fee);
          if (amountInput) amountInput.value = entryAmount ? formatNumber(entryAmount, 2) : '';
          if (feeInput) feeInput.value = entryFee ? formatNumber(entryFee, 2) : '';

          if (bankField && bankSelect) {
            if (methodShowsBank(method)) {
              bankField.hidden = false;
              clearNode(bankSelect);
              const placeholder = document.createElement('option');
              placeholder.value = '';
              placeholder.textContent = 'Selecione';
              bankSelect.appendChild(placeholder);
              Object.values(bankAccountOptions || {}).forEach((account) => {
                const option = document.createElement('option');
                option.value = String(account.id || '');
                option.textContent = formatBankAccountLabel(account);
                bankSelect.appendChild(option);
              });
              if (entry.bank_account_id) {
                bankSelect.value = String(entry.bank_account_id);
              }
            } else {
              bankField.hidden = true;
            }
          }

          if (terminalField && terminalSelect) {
            if (methodShowsTerminal(method)) {
              terminalField.hidden = false;
              rebuildTerminalOptions(terminalSelect, method ? String(method.type || '') : '', entry.terminal_id ? String(entry.terminal_id) : '');
              if (entry.terminal_id) {
                terminalSelect.value = String(entry.terminal_id);
              }
            } else {
              terminalField.hidden = true;
            }
          }

          editor.querySelector('[data-edit-cancel]').addEventListener('click', () => {
            entry._editing = false;
            renderOrderPayments();
          });
          editor.querySelector('[data-edit-save]').addEventListener('click', () => {
            const nextAmount = amountInput ? parseOptionalNumber(amountInput.value) : entry.amount;
            const nextFee = feeInput ? parseOptionalNumber(feeInput.value) : entry.fee;
            if (nextAmount !== '') {
              entry.amount = nextAmount;
            }
            if (nextFee !== '') {
              entry.fee = nextFee;
            } else {
              entry.fee = 0;
            }
            if (bankSelect && !bankField.hidden) {
              const accountId = bankSelect.value;
              const account = resolveBankAccount(accountId);
              entry.bank_account_id = account ? account.id : '';
              entry.bank_account_label = account ? formatBankAccountLabel(account) : '';
            }
            if (terminalSelect && !terminalField.hidden) {
              const terminalId = terminalSelect.value;
              const terminal = resolvePaymentTerminal(terminalId);
              entry.terminal_id = terminal ? terminal.id : '';
              entry.terminal_label = terminal ? terminal.name : '';
            }
            entry._editing = false;
            renderOrderPayments();
          });

          card.appendChild(editor);
        }

        appendHiddenInput(card, `payments[${index}][method_id]`, entry.method_id);
        appendHiddenInput(card, `payments[${index}][amount]`, entry.amount);
        appendHiddenInput(card, `payments[${index}][fee]`, entry.fee);
        appendHiddenInput(card, `payments[${index}][paid]`, entry.paid === false ? '0' : '1');
        appendHiddenInput(card, `payments[${index}][bank_account_id]`, entry.bank_account_id || '');
        appendHiddenInput(card, `payments[${index}][terminal_id]`, entry.terminal_id || '');
        appendHiddenInput(card, `payments[${index}][voucher_account_id]`, entry.voucher_account_id || '');

        orderPaymentsList.appendChild(card);
      });

      updatePaymentSummary();
    };

    const resetPaymentPicker = () => {
      if (orderPaymentMethodSelect) orderPaymentMethodSelect.value = '';
      if (orderPaymentAmountInput) {
        orderPaymentAmountInput.value = '';
        orderPaymentAmountInput.dataset.userEdited = 'false';
      }
      if (orderPaymentFeeInput) {
        orderPaymentFeeInput.value = '';
        orderPaymentFeeInput.dataset.userEdited = 'false';
      }
      if (paymentBankSelect) paymentBankSelect.value = '';
      if (paymentTerminalSelect) paymentTerminalSelect.value = '';
      if (paymentVoucherSelect) paymentVoucherSelect.value = '';
      if (paymentVoucherMeta) {
        paymentVoucherMeta.textContent = '';
        paymentVoucherMeta.hidden = true;
      }
      updatePaymentPickerVisibility(null);
      updatePaymentPixKey();
      setPaymentHelper('Selecione um método para adicionar.', false);
      updatePaymentSummary();
    };

    const addOrderPayment = () => {
      const methodId = orderPaymentMethodSelect ? orderPaymentMethodSelect.value : '';
      const method = resolvePaymentMethod(methodId);
      if (!method) {
        setPaymentHelper('Selecione um método válido.', true);
        return;
      }
      let amount = orderPaymentAmountInput ? parseOptionalNumber(orderPaymentAmountInput.value) : '';
      if (amount === '') {
        const totals = calculateOrderTotals();
        const paidTotal = sumPaidPayments();
        amount = Math.max(0, totals.finalTotal - paidTotal);
      }
      if (!Number.isFinite(amount) || amount <= 0) {
        setPaymentHelper('Informe um valor válido.', true);
        return;
      }
      let fee = orderPaymentFeeInput ? parseOptionalNumber(orderPaymentFeeInput.value) : '';
      if (fee === '') {
        fee = computePaymentFee(method, amount);
      }
      if (fee < 0) {
        setPaymentHelper('Taxa inválida.', true);
        return;
      }

      const needsBank = methodRequiresBank(method);
      const needsTerminal = methodRequiresTerminal(method);
      const needsVoucher = methodRequiresVoucher(method);
      const accountId = paymentBankSelect ? paymentBankSelect.value : '';
      const terminalId = paymentTerminalSelect ? paymentTerminalSelect.value : '';
      const voucherId = paymentVoucherSelect ? paymentVoucherSelect.value : '';
      const account = accountId ? resolveBankAccount(accountId) : null;
      const terminal = terminalId ? resolvePaymentTerminal(terminalId) : null;
      const voucherAccount = voucherId ? resolveVoucherAccount(voucherId) : null;
      const { customerId, userId, hasCustomer } = resolveSelectedCustomerIds();
      const matchIds = [customerId, userId].filter((value) => value > 0);

      if (needsBank && !account) {
        setPaymentHelper('Selecione a conta bancaria.', true);
        return;
      }
      if (needsTerminal && !terminal) {
        setPaymentHelper('Selecione a maquininha.', true);
        return;
      }
      if (needsVoucher) {
        if (!hasCustomer) {
          setPaymentHelper('Informe a cliente para usar cupom/crédito.', true);
          return;
        }
        if (!voucherAccount) {
          setPaymentHelper('Selecione o cupom/crédito.', true);
          return;
        }
        if (!matchIds.includes(Number(voucherAccount.pessoa_id || 0))) {
          setPaymentHelper('Cupom/crédito nao pertence a esta cliente.', true);
          return;
        }
        const balance = toNumber(voucherAccount.balance);
        const used = orderPayments.reduce((sum, entry) => {
          return String(entry.voucher_account_id || '') === String(voucherAccount.id || '') ? sum + toNumber(entry.amount) : sum;
        }, 0);
        if ((used + amount - balance) > 0.00001) {
          setPaymentHelper('Saldo insuficiente no cupom/crédito.', true);
          return;
        }
      }

      orderPayments.push({
        method_id: methodId,
        method_name: String(method.name || ''),
        method_type: String(method.type || ''),
        amount: amount,
        fee: fee,
        paid: true,
        bank_account_id: account ? account.id : '',
        bank_account_label: account ? formatBankAccountLabel(account) : '',
        terminal_id: terminal ? terminal.id : '',
        terminal_label: terminal ? terminal.name : '',
        voucher_account_id: voucherAccount ? voucherAccount.id : '',
        voucher_account_label: voucherAccount ? formatVoucherAccountLabel(voucherAccount) : '',
        voucher_account_code: voucherAccount ? String(voucherAccount.code || '') : '',
      });

      renderOrderPayments();
      pulseNode(orderPaymentsList);
      pulseNode(orderTotalSummary);
      setPaymentHelper('Pagamento adicionado.', false);
      resetPaymentPicker();
    };

    const addOrderItem = () => {
      if (!pickerProductSku) {
        setPickerHelper('Selecione um produto válido.', true);
        return;
      }
      const product = productOptions[pickerProductSku];
      if (!product) {
        setPickerHelper('Produto não encontrado.', true);
        return;
      }

      let qty = orderItemQtyInput ? parseInt(orderItemQtyInput.value, 10) || 1 : 1;
      const variationId = orderItemVariationSelect && orderItemVariationField && !orderItemVariationField.hidden
        ? orderItemVariationSelect.value
        : '';
      const variation = variationId ? findVariation(product, variationId) : null;
      let price = orderItemPriceInput ? parseOptionalNumber(orderItemPriceInput.value) : '';
      if (price === '') {
        const variationPrice = resolveVariationPrice(variation);
        price = variationPrice !== null ? variationPrice : resolveProductPrice(product);
      }

      const availableStock = resolveAvailableStock(pickerProductSku, variationId);
      let adjustedQty = false;
      if (availableStock !== null) {
        if (availableStock <= 0) {
          setPickerHelper('Disponibilidade máxima já reservada para este item.', true);
          return;
        }
        if (qty > availableStock) {
          qty = availableStock;
          adjustedQty = true;
          if (orderItemQtyInput) {
            orderItemQtyInput.value = String(availableStock);
          }
        }
      }

      const existingIndex = orderItems.findIndex((item) => matchesOrderItem(item, pickerProductSku, variationId));
      if (existingIndex >= 0) {
        const existingItem = orderItems[existingIndex];
        const existingQty = Math.max(1, parseInt(existingItem.quantity, 10) || 1);
        existingItem.quantity = existingQty + Math.max(1, qty);
        if (orderItemPriceInput && orderItemPriceInput.dataset.userEdited === 'true' && price !== '') {
          existingItem.price = price;
        } else if (existingItem.price === '' || existingItem.price === null) {
          existingItem.price = price;
        }
        renderOrderItems();
        clearPickerSelection();
        const message = adjustedQty
          ? `Quantidade adicionada ao item existente. Ajustamos para ${availableStock} (disponibilidade atual).`
          : 'Quantidade adicionada ao item existente.';
        setPickerHelper(message, false);
        pulseNode(orderItemsList);
        pulseNode(orderTotalSummary);
        return;
      }

      orderItems.push({
        line_id: '',
        product_sku: pickerProductSku,
        variation_id: variationId,
        quantity: Math.max(1, qty),
        price: price,
        adjust_type: '',
        adjust_value: '',
      });
      renderOrderItems();
      clearPickerSelection();
      const message = adjustedQty
        ? `Item adicionado. Ajustamos a quantidade para ${availableStock} (disponibilidade atual).`
        : 'Item adicionado.';
      setPickerHelper(message, false);
      pulseNode(orderItemsList);
      pulseNode(orderTotalSummary);
    };

    if (addOrderItemButton) {
      addOrderItemButton.addEventListener('click', addOrderItem);
    }

    if (orderPaymentMethodSelect) {
      orderPaymentMethodSelect.addEventListener('change', () => {
        if (orderPaymentFeeInput) {
          orderPaymentFeeInput.dataset.userEdited = 'false';
        }
        if (orderPaymentAmountInput) {
          orderPaymentAmountInput.dataset.userEdited = 'false';
        }
        updatePaymentPickerState();
      });
    }
    if (orderPaymentAmountInput) {
      orderPaymentAmountInput.addEventListener('input', () => {
        orderPaymentAmountInput.dataset.userEdited = 'true';
        updatePaymentFeeInput(resolvePaymentMethod(orderPaymentMethodSelect ? orderPaymentMethodSelect.value : ''));
        updatePaymentSummary();
      });
    }
    if (orderPaymentFeeInput) {
      orderPaymentFeeInput.addEventListener('input', () => {
        orderPaymentFeeInput.dataset.userEdited = 'true';
      });
    }
    if (paymentBankSelect) {
      paymentBankSelect.addEventListener('change', updatePaymentPixKey);
    }
    if (paymentVoucherSelect) {
      paymentVoucherSelect.addEventListener('change', updateVoucherMeta);
    }
    if (addOrderPaymentButton) {
      addOrderPaymentButton.addEventListener('click', addOrderPayment);
    }
    if (customerInput) {
      const onCustomerInputChanged = () => {
        const method = resolvePaymentMethod(orderPaymentMethodSelect ? orderPaymentMethodSelect.value : '');
        if (methodShowsVoucher(method)) {
          rebuildVoucherAccountOptions(paymentVoucherSelect ? paymentVoucherSelect.value : '');
        }
        syncLayoutState();
      };
      customerInput.addEventListener('input', onCustomerInputChanged);
      customerInput.addEventListener('change', onCustomerInputChanged);
    }

    document.addEventListener('keydown', (event) => {
      if (currentLayout !== 'layout-5' || (isMobileViewport && isMobileViewport.matches)) return;
      const target = event.target;
      const tagName = target && target.tagName ? String(target.tagName).toLowerCase() : '';
      const isTextArea = tagName === 'textarea';

      if (event.ctrlKey && !event.metaKey && (event.key === 'p' || event.key === 'P')) {
        event.preventDefault();
        addOrderPayment();
        return;
      }

      if (event.key !== 'Enter' || event.ctrlKey || event.metaKey || event.shiftKey || isTextArea) {
        return;
      }

      const targetId = target && target.id ? String(target.id) : '';
      const isItemInput = ['orderItemSku', 'orderItemName', 'orderItemQty', 'orderItemPrice', 'orderItemVariation'].includes(targetId);
      if (!isItemInput) return;
      event.preventDefault();
      addOrderItem();
    });

    const normalizeItemInput = (raw) => {
      if (!raw || typeof raw !== 'object') return null;
      const lineId = String(raw.line_id || raw.lineId || raw.id || '').trim();
      let productSku = String(raw.product_sku || raw.productSku || '').trim();
      let variationId = String(raw.variation_id || raw.variationId || '').trim();
      if (!productSku) {
        const skuMatch = raw.product_sku ? matchSku(String(raw.product_sku)) : null;
        if (skuMatch && skuMatch.productId) {
          productSku = skuMatch.productId;
          if (!variationId && skuMatch.variationId) {
            variationId = skuMatch.variationId;
          }
        }
      }
      if (!productSku) {
        const nameMatch = raw.product_name
          ? matchLookup(String(raw.product_name), productNameLookup, false, productNameSuggestions)
          : null;
        if (nameMatch && nameMatch.id) {
          productSku = nameMatch.id;
        }
      }
      if (!productSku) return null;

      const qty = parseInt(raw.quantity ?? 1, 10) || 1;
      const productLabel = raw.product_name
        ? String(raw.product_name)
        : (raw.product_sku ? `SKU ${raw.product_sku}` : '');

      return {
        line_id: lineId,
        product_sku: productSku,
        variation_id: variationId,
        quantity: Math.max(1, qty),
        price: parseOptionalNumber(raw.price),
        adjust_type: String(raw.adjust_type || ''),
        adjust_value: parseOptionalNumber(raw.adjust_value),
        product_label: productLabel,
      };
    };

    if (Array.isArray(initialOrderItems)) {
      initialOrderItems.forEach((raw) => {
        const item = normalizeItemInput(raw);
        if (item) {
          orderItems.push(item);
        }
      });
    }

    renderOrderItems();
    clearPickerSelection();

    const normalizePaymentInput = (raw) => {
      if (!raw || typeof raw !== 'object') return null;
      const methodId = String(raw.method_id || raw.methodId || '').trim();
      if (!methodId) return null;
      const method = resolvePaymentMethod(methodId);
      const amount = parseOptionalNumber(raw.amount ?? raw.value ?? '');
      if (amount === '') return null;
      const fee = parseOptionalNumber(raw.fee ?? '');
      const paid = parseOptionalBoolean(raw.paid ?? raw.is_paid ?? raw.set_paid ?? raw.paid_status, true);
      const accountId = String(raw.bank_account_id || raw.bankAccountId || '').trim();
      const terminalId = String(raw.terminal_id || raw.terminalId || '').trim();
      const voucherId = String(raw.voucher_account_id || raw.voucherAccountId || '').trim();
      const account = accountId ? resolveBankAccount(accountId) : null;
      const terminal = terminalId ? resolvePaymentTerminal(terminalId) : null;
      const voucherAccount = voucherId ? resolveVoucherAccount(voucherId) : null;

      return {
        method_id: methodId,
        method_name: method ? String(method.name || '') : String(raw.method_name || raw.methodName || ''),
        method_type: method ? String(method.type || '') : String(raw.method_type || raw.methodType || ''),
        amount: amount,
        fee: fee === '' ? 0 : fee,
        paid: paid,
        bank_account_id: account ? account.id : accountId,
        bank_account_label: account ? formatBankAccountLabel(account) : String(raw.bank_account_label || raw.bankAccountLabel || ''),
        terminal_id: terminal ? terminal.id : terminalId,
        terminal_label: terminal ? terminal.name : String(raw.terminal_label || raw.terminalLabel || ''),
        voucher_account_id: voucherAccount ? voucherAccount.id : voucherId,
        voucher_account_label: voucherAccount ? formatVoucherAccountLabel(voucherAccount) : String(raw.voucher_account_label || raw.voucherAccountLabel || ''),
        voucher_account_code: voucherAccount ? String(voucherAccount.code || '') : String(raw.voucher_account_code || raw.voucherAccountCode || ''),
      };
    };

    if (Array.isArray(initialPaymentEntries)) {
      initialPaymentEntries.forEach((raw) => {
        const entry = normalizePaymentInput(raw);
        if (entry) {
          orderPayments.push(entry);
        }
      });
    }

    if (orderPaymentAmountInput) {
      orderPaymentAmountInput.dataset.userEdited = 'false';
    }
    if (orderPaymentFeeInput) {
      orderPaymentFeeInput.dataset.userEdited = 'false';
    }

    updatePaymentPickerVisibility(null);
    applyDefaultPaymentPicker();
    renderOrderPayments();

    const initialLayout = normalizeLayoutName(readLayoutFromStorage() || (layoutSwitcher ? layoutSwitcher.value : 'layout-1'));
    setLayoutMode(initialLayout, false);

    let resolvedInitialOrderMode = initialOrderMode || 'online';
    if (isMobileViewport && isMobileViewport.matches) {
      resolvedInitialOrderMode = 'pdv';
      mobileOrderModeSelected = true;
    }
    setOrderMode(resolvedInitialOrderMode, true);

    const initCepLookup = () => {
      if (!window.setupCepLookup) return;
      window.setupCepLookup({
        zip: '#address_postcode',
        street: '#address_address_1',
        address2: '#address_address_2',
        neighborhood: '#address_neighborhood',
        city: '#address_city',
        state: '#address_state',
        country: '#address_country',
        countryDefault: 'BR',
      });
      window.setupCepLookup({
        zip: '#new_customer_zip',
        street: '#new_customer_street',
        address2: '#new_customer_street2',
        neighborhood: '#new_customer_neighborhood',
        city: '#new_customer_city',
        state: '#new_customer_state',
        country: '#new_customer_country',
        countryDefault: 'BR',
      });
    };

    if (document.readyState === 'loading') {
      window.addEventListener('DOMContentLoaded', initCepLookup);
    } else {
      initCepLookup();
    }

    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
      orderForm.addEventListener('submit', (event) => {
        syncDeliveryInputs();
        const customer = getSelectedCustomer();
        if (!customer) return;
        const shippingName = readValue('shipping_full_name');
        const customerName = String(customer.full_name || customer.name || customer.shipping_full_name || '').trim();
        if (shippingName && customerName && !isNameSimilar(shippingName, customerName)) {
          const proceed = window.confirm('O nome do envio é diferente do cliente selecionado. Deseja continuar assim mesmo?');
          if (!proceed) {
            event.preventDefault();
            return;
          }
        }
      });
    }

    updateAddressSummaries();
    updateShippingSameCopy();
    if (shippingSameCheckbox && shippingSameCheckbox.checked) {
      syncShippingWithBilling();
    }
  </script>
<?php endif; ?>
