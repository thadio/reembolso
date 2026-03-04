<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var array $filters */
/** @var array $deliveryStatusOptions */
/** @var array $deliveryModeOptions */
/** @var array $shipmentKindOptions */
/** @var array $carrierOptions */
/** @var callable $esc */
?>
<?php
  $canEditDelivery = userCan('orders.fulfillment') || userCan('orders.edit');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Acompanhamento de Entregas</h1>
    <div class="subtitle">Monitoramento por rastreio e atualização operacional da logística.</div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="get" class="grid" style="margin:12px 0;">
  <div class="field">
    <label for="period_from">Período (de)</label>
    <input id="period_from" type="date" name="period_from" value="<?php echo $esc((string) ($filters['period_from'] ?? '')); ?>">
  </div>
  <div class="field">
    <label for="period_to">Período (até)</label>
    <input id="period_to" type="date" name="period_to" value="<?php echo $esc((string) ($filters['period_to'] ?? '')); ?>">
  </div>
  <div class="field">
    <label for="delivery_status">Status entrega</label>
    <select id="delivery_status" name="delivery_status">
      <option value="">Todos</option>
      <?php foreach ($deliveryStatusOptions as $statusKey => $statusLabel): ?>
        <option value="<?php echo $esc((string) $statusKey); ?>" <?php echo (string) ($filters['delivery_status'] ?? '') === (string) $statusKey ? 'selected' : ''; ?>>
          <?php echo $esc((string) $statusLabel); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="delivery_mode">Entrega</label>
    <select id="delivery_mode" name="delivery_mode">
      <option value="">Todos</option>
      <?php foreach ($deliveryModeOptions as $modeKey => $modeLabel): ?>
        <option value="<?php echo $esc((string) $modeKey); ?>" <?php echo (string) ($filters['delivery_mode'] ?? '') === (string) $modeKey ? 'selected' : ''; ?>>
          <?php echo $esc((string) $modeLabel); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="shipment_kind">Tipo envio</label>
    <select id="shipment_kind" name="shipment_kind">
      <option value="">Todos</option>
      <?php foreach ($shipmentKindOptions as $kindKey => $kindLabel): ?>
        <option value="<?php echo $esc((string) $kindKey); ?>" <?php echo (string) ($filters['shipment_kind'] ?? '') === (string) $kindKey ? 'selected' : ''; ?>>
          <?php echo $esc((string) $kindLabel); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="carrier_id">Transportadora</label>
    <select id="carrier_id" name="carrier_id">
      <option value="">Todas</option>
      <?php foreach ($carrierOptions as $carrier): ?>
        <?php $carrierId = (string) ($carrier['id'] ?? ''); ?>
        <option value="<?php echo $esc($carrierId); ?>" <?php echo (string) ($filters['carrier_id'] ?? '') === $carrierId ? 'selected' : ''; ?>>
          <?php echo $esc((string) ($carrier['name'] ?? '')); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="tracking_code">Rastreio</label>
    <input id="tracking_code" type="text" name="tracking_code" value="<?php echo $esc((string) ($filters['tracking_code'] ?? '')); ?>" placeholder="Código de rastreio">
  </div>
  <div class="field">
    <label for="customer">Cliente</label>
    <input id="customer" type="text" name="customer" value="<?php echo $esc((string) ($filters['customer'] ?? '')); ?>" placeholder="Nome da cliente">
  </div>
  <div class="field">
    <label for="order_id">Pedido</label>
    <input id="order_id" type="number" name="order_id" value="<?php echo $esc((string) ($filters['order_id'] ?? '')); ?>" placeholder="ID">
  </div>
  <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
    <button class="primary" type="submit">Filtrar</button>
    <a class="btn ghost" href="entrega-acompanhamento.php">Limpar</a>
  </div>
</form>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar na tabela" aria-label="Busca geral no acompanhamento de entregas">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">Pedido</th>
        <th data-sort-key="customer_name" aria-sort="none">Cliente</th>
        <th data-sort-key="delivery_mode" aria-sort="none">Entrega</th>
        <th data-sort-key="shipment_kind" aria-sort="none">Tipo envio</th>
        <th data-sort-key="delivery_status" aria-sort="none">Status</th>
        <th data-sort-key="carrier_name" aria-sort="none">Transportadora</th>
        <th data-sort-key="tracking_code" aria-sort="none">Rastreio</th>
        <th data-sort-key="estimated_delivery_at" aria-sort="none">Prev. entrega</th>
        <th data-sort-key="shipped_at" aria-sort="none">Data envio</th>
        <th data-sort-key="updated_at" aria-sort="none">Última atualização</th>
        <th class="col-actions">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="11">Nenhuma entrega encontrada com os filtros atuais.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $deliveryMode = (string) ($row['delivery_mode'] ?? 'shipment');
            $deliveryModeLabel = $deliveryModeOptions[$deliveryMode] ?? $deliveryMode;
            $shipmentKind = (string) ($row['shipment_kind'] ?? '');
            $shipmentKindLabel = $shipmentKind !== '' ? ($shipmentKindOptions[$shipmentKind] ?? $shipmentKind) : '-';
            $deliveryStatus = (string) ($row['delivery_status'] ?? 'pending');
            $deliveryStatusLabel = $deliveryStatusOptions[$deliveryStatus] ?? $deliveryStatus;
            $trackingCode = (string) ($row['tracking_code'] ?? '');
          ?>
          <tr>
            <td data-value="<?php echo (int) ($row['id'] ?? 0); ?>">
              <a href="pedido-cadastro.php?id=<?php echo (int) ($row['id'] ?? 0); ?>">#<?php echo (int) ($row['id'] ?? 0); ?></a>
            </td>
            <td data-value="<?php echo $esc((string) ($row['customer_name'] ?? '')); ?>"><?php echo $esc((string) ($row['customer_name'] ?? '')); ?></td>
            <td data-value="<?php echo $esc($deliveryMode); ?>"><?php echo $esc($deliveryModeLabel); ?></td>
            <td data-value="<?php echo $esc($shipmentKind); ?>">
              <?php echo $esc($shipmentKindLabel); ?>
              <?php if (!empty($row['bag_id'])): ?>
                <div style="font-size:12px;color:var(--muted);">Sacolinha #<?php echo (int) $row['bag_id']; ?></div>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($deliveryStatus); ?>"><span class="pill"><?php echo $esc($deliveryStatusLabel); ?></span></td>
            <td data-value="<?php echo $esc((string) ($row['carrier_name'] ?? '')); ?>"><?php echo $esc((string) ($row['carrier_name'] ?? '-')); ?></td>
            <td data-value="<?php echo $esc($trackingCode); ?>">
              <?php if ($trackingCode !== ''): ?>
                <code><?php echo $esc($trackingCode); ?></code>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc((string) ($row['estimated_delivery_at'] ?? '')); ?>"><?php echo $esc((string) ($row['estimated_delivery_at'] ?? '-')); ?></td>
            <td data-value="<?php echo $esc((string) ($row['shipped_at'] ?? '')); ?>"><?php echo $esc((string) ($row['shipped_at'] ?? '-')); ?></td>
            <td data-value="<?php echo $esc((string) ($row['updated_at'] ?? '')); ?>"><?php echo $esc((string) ($row['updated_at'] ?? '-')); ?></td>
            <td class="col-actions">
              <div class="actions" style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($trackingCode !== ''): ?>
                  <button type="button" class="btn ghost" data-copy-tracking="<?php echo $esc($trackingCode); ?>">Copiar rastreio</button>
                <?php endif; ?>
                <?php if (!empty($row['tracking_url'])): ?>
                  <a class="btn ghost" target="_blank" rel="noopener noreferrer" href="<?php echo $esc((string) $row['tracking_url']); ?>">Abrir rastreio</a>
                <?php endif; ?>
                <?php if ($canEditDelivery): ?>
                  <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="update_delivery">
                    <input type="hidden" name="order_id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                    <input type="hidden" name="delivery_mode" value="<?php echo $esc($deliveryMode); ?>">
                    <input type="hidden" name="shipment_kind" value="<?php echo $esc($shipmentKind); ?>">
                    <input type="hidden" name="carrier_id" value="<?php echo (int) ($row['carrier_id'] ?? 0); ?>">
                    <input type="hidden" name="bag_id" value="<?php echo (int) ($row['bag_id'] ?? 0); ?>">
                    <input type="hidden" name="tracking_code" value="<?php echo $esc($trackingCode); ?>">
                    <input type="hidden" name="estimated_delivery_at" value="<?php echo $esc((string) ($row['estimated_delivery_at'] ?? '')); ?>">
                    <select name="delivery_status" aria-label="Atualizar status entrega do pedido <?php echo (int) ($row['id'] ?? 0); ?>">
                      <?php foreach ($deliveryStatusOptions as $statusKey => $statusLabel): ?>
                        <option value="<?php echo $esc((string) $statusKey); ?>" <?php echo $deliveryStatus === (string) $statusKey ? 'selected' : ''; ?>>
                          <?php echo $esc((string) $statusLabel); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn ghost" type="submit">Atualizar status</button>
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

<script>
  (() => {
    const copyButtons = document.querySelectorAll('[data-copy-tracking]');
    copyButtons.forEach((button) => {
      button.addEventListener('click', async () => {
        const code = button.getAttribute('data-copy-tracking') || '';
        if (!code) return;
        try {
          await navigator.clipboard.writeText(code);
          button.textContent = 'Copiado';
          window.setTimeout(() => {
            button.textContent = 'Copiar rastreio';
          }, 1200);
        } catch (error) {
          window.alert('Não foi possível copiar o código de rastreio.');
        }
      });
    });
  })();
</script>
