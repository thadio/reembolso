<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $items */
/** @var array $shipments */
/** @var array $carrierOptions */
/** @var array $statusOptions */
/** @var callable $esc */

use App\Support\Image;

  $items = $items ?? [];
  $shipments = $shipments ?? [];
  $carrierOptions = $carrierOptions ?? [];
  $carrierNameMap = [];
  foreach ($carrierOptions as $carrierOption) {
      $carrierId = (int) ($carrierOption['id'] ?? 0);
      if ($carrierId > 0) {
          $carrierNameMap[$carrierId] = (string) ($carrierOption['name'] ?? ('#' . $carrierId));
      }
  }
  $totalPieces = 0;
  $totalValue = 0.0;
  foreach ($items as $item) {
      $qty = (int) ($item['quantity'] ?? 0);
      $totalPieces += $qty;
      $totalValue += (float) ($item['total_price'] ?? 0);
  }
  $latestShipment = !empty($shipments) && is_array($shipments[0]) ? $shipments[0] : null;
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1><?php echo $editing ? 'Detalhe da sacolinha' : 'Abrir sacolinha'; ?></h1>
      <div class="subtitle">Controle de sacolinhas e taxa de abertura.</div>
    </div>
    <?php if ($editing): ?>
      <span class="pill">Sacolinha #<?php echo $esc((string) $formData['id']); ?></span>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="sacolinha-cadastro.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="pessoa_id">Cliente (ID pessoa) *</label>
        <input type="number" min="1" id="pessoa_id" name="pessoa_id" required value="<?php echo $esc($formData['pessoa_id']); ?>">
      </div>
      <div class="field">
        <label for="customer_name">Nome da cliente</label>
        <input type="text" id="customer_name" name="customer_name" value="<?php echo $esc($formData['customer_name']); ?>">
      </div>
      <div class="field">
        <label for="customer_email">E-mail da cliente</label>
        <input type="email" id="customer_email" name="customer_email" value="<?php echo $esc($formData['customer_email']); ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
            <option value="<?php echo $esc($statusKey); ?>" <?php echo $formData['status'] === $statusKey ? 'selected' : ''; ?>>
              <?php echo $esc($statusLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="opened_at">Data de abertura</label>
        <input type="datetime-local" id="opened_at" name="opened_at" value="<?php echo $esc($formData['opened_at']); ?>">
      </div>
      <div class="field">
        <label for="expected_close_at">Data prevista de fechamento</label>
        <input type="datetime-local" id="expected_close_at" name="expected_close_at" value="<?php echo $esc($formData['expected_close_at']); ?>">
      </div>
      <div class="field">
        <label for="closed_at">Data de fechamento</label>
        <input type="datetime-local" id="closed_at" name="closed_at" value="<?php echo $esc($formData['closed_at']); ?>">
      </div>
      <div class="field">
        <label for="opening_fee_value">Taxa de abertura (R$)</label>
  <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="opening_fee_value" name="opening_fee_value" value="<?php echo $esc($formData['opening_fee_value']); ?>">
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="opening_fee_paid" value="1" <?php echo !empty($formData['opening_fee_paid']) ? 'checked' : ''; ?>>
          <span>Taxa paga</span>
        </label>
      </div>
      <div class="field">
        <label for="opening_fee_paid_at">Data do pagamento</label>
        <input type="datetime-local" id="opening_fee_paid_at" name="opening_fee_paid_at" value="<?php echo $esc($formData['opening_fee_paid_at']); ?>">
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="notes">Observacoes</label>
        <textarea id="notes" name="notes" rows="2" maxlength="255" placeholder="Opcional"><?php echo $esc($formData['notes']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar sacolinha</button>
    </div>
  </form>

  <?php if ($editing): ?>
    <div style="margin-top:20px;padding:14px;border:1px dashed var(--line);border-radius:12px;background:#fff;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <h2 style="margin:0;">Remessa da sacolinha</h2>
        <span class="pill"><?php echo $latestShipment ? ('Última remessa #' . (int) ($latestShipment['id'] ?? 0)) : 'Sem remessa'; ?></span>
      </div>
      <?php if ($latestShipment): ?>
        <div style="margin-top:8px;font-size:13px;color:var(--muted);">
          Status: <strong><?php echo $esc((string) ($latestShipment['status'] ?? '')); ?></strong>
          <?php if (!empty($latestShipment['tracking_code'])): ?>
            • Rastreio: <code><?php echo $esc((string) $latestShipment['tracking_code']); ?></code>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (($formData['status'] ?? '') !== 'entregue' && ($formData['status'] ?? '') !== 'cancelada'): ?>
        <form method="post" action="sacolinha-cadastro.php" style="margin-top:12px;">
          <input type="hidden" name="id" value="<?php echo (int) ($formData['id'] ?? 0); ?>">
          <input type="hidden" name="action" value="dispatch">
          <div class="grid">
            <div class="field">
              <label for="shipment_carrier_id">Transportadora *</label>
              <select id="shipment_carrier_id" name="shipment_carrier_id" required>
                <option value="">Selecione</option>
                <?php foreach ($carrierOptions as $carrier): ?>
                  <?php $carrierId = (string) ($carrier['id'] ?? ''); ?>
                  <option value="<?php echo $esc($carrierId); ?>" <?php echo $latestShipment && (string) ($latestShipment['carrier_id'] ?? '') === $carrierId ? 'selected' : ''; ?>>
                    <?php echo $esc((string) ($carrier['name'] ?? '')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="shipment_tracking_code">Código de rastreio *</label>
              <input type="text" id="shipment_tracking_code" name="shipment_tracking_code" required value="<?php echo $esc((string) ($latestShipment['tracking_code'] ?? '')); ?>">
            </div>
            <div class="field">
              <label for="shipment_estimated_delivery_at">Previsão de entrega</label>
              <input type="datetime-local" id="shipment_estimated_delivery_at" name="shipment_estimated_delivery_at" value="<?php echo $esc(!empty($latestShipment['estimated_delivery_at']) ? date('Y-m-d\TH:i', strtotime((string) $latestShipment['estimated_delivery_at'])) : ''); ?>">
            </div>
            <div class="field">
              <label for="shipment_shipped_at">Data de despacho</label>
              <input type="datetime-local" id="shipment_shipped_at" name="shipment_shipped_at" value="<?php echo $esc(date('Y-m-d\TH:i')); ?>">
            </div>
            <div class="field" style="grid-column:1 / -1;">
              <label for="shipment_notes">Observações da remessa</label>
              <textarea id="shipment_notes" name="shipment_notes" rows="2"><?php echo $esc((string) ($latestShipment['notes'] ?? '')); ?></textarea>
            </div>
          </div>
          <div class="footer">
            <button class="primary" type="submit">Despachar sacolinha</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($latestShipment && (string) ($latestShipment['status'] ?? '') !== 'delivered' && ($formData['status'] ?? '') !== 'cancelada'): ?>
        <form method="post" action="sacolinha-cadastro.php" style="margin-top:8px;">
          <input type="hidden" name="id" value="<?php echo (int) ($formData['id'] ?? 0); ?>">
          <input type="hidden" name="action" value="deliver">
          <div class="grid">
            <div class="field">
              <label for="shipment_delivered_at">Data de entrega</label>
              <input type="datetime-local" id="shipment_delivered_at" name="shipment_delivered_at" value="<?php echo $esc(date('Y-m-d\TH:i')); ?>">
            </div>
          </div>
          <div class="footer">
            <button class="btn ghost" type="submit">Marcar sacolinha como entregue</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <h2 style="margin:0;">Itens da sacolinha</h2>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <span class="pill"><?php echo $totalPieces; ?> itens</span>
          <span class="pill">Total R$ <?php echo $esc(number_format($totalValue, 2, ',', '.')); ?></span>
        </div>
      </div>
      <div style="overflow:auto;margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Foto</th>
              <th>Produto</th>
              <th>Quantidade</th>
              <th>Total</th>
              <th>Data compra</th>
              <th>Pedido</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr><td colspan="6">Nenhum item cadastrado.</td></tr>
            <?php else: ?>
              <?php foreach ($items as $item): ?>
                <?php
                  $image = trim((string) ($item['image_url'] ?? ''));
                  $name = $item['name'] ?? 'Produto';
                  $description = trim((string) ($item['description'] ?? ''));
                  $orderId = (int) ($item['order_id'] ?? 0);
                  $purchaseDate = $item['purchased_at'] ? date('d/m/Y', strtotime($item['purchased_at'])) : '-';
                  $thumb = $image !== '' ? image_url($image, 'thumb', 110) : '';
                  $displayImage = $thumb !== '' ? $thumb : $image;
                ?>
                <tr>
                  <td>
                    <div class="order-item-table-thumb">
                      <?php if ($image): ?>
                        <img src="<?php echo $esc($displayImage); ?>" data-thumb-full="<?php echo $esc($image); ?>" data-thumb-size="110" alt="<?php echo $esc($name); ?>">
                      <?php else: ?>
                        <span>Sem foto</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <?php echo $esc($name); ?>
                    <?php if ($description !== ''): ?>
                      <div style="font-size:12px;color:var(--muted);"><?php echo $esc($description); ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo (int) ($item['quantity'] ?? 0); ?></td>
                  <td>R$ <?php echo $esc(number_format((float) ($item['total_price'] ?? 0), 2, ',', '.')); ?></td>
                  <td><?php echo $esc($purchaseDate); ?></td>
                  <td><?php echo $orderId > 0 ? '#' . $orderId : '-'; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px;">
        <a class="btn ghost" href="sacolinha-cliente.php?pessoa_id=<?php echo (int) ($formData['pessoa_id'] ?? 0); ?>">Ver histórico da cliente</a>
      </div>
    </div>

    <div style="margin-top:20px;">
      <h2 style="margin:0 0 10px;">Histórico de remessas</h2>
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Status</th>
              <th>Transportadora</th>
              <th>Rastreio</th>
              <th>Despacho</th>
              <th>Entrega</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($shipments)): ?>
              <tr><td colspan="6">Sem remessas registradas.</td></tr>
            <?php else: ?>
              <?php foreach ($shipments as $shipment): ?>
                <?php
                  $shipmentCarrierId = (int) ($shipment['carrier_id'] ?? 0);
                  $shipmentCarrierLabel = $shipmentCarrierId > 0
                    ? ($carrierNameMap[$shipmentCarrierId] ?? ('#' . $shipmentCarrierId))
                    : '-';
                ?>
                <tr>
                  <td>#<?php echo (int) ($shipment['id'] ?? 0); ?></td>
                  <td><span class="pill"><?php echo $esc((string) ($shipment['status'] ?? '')); ?></span></td>
                  <td><?php echo $esc($shipmentCarrierLabel); ?></td>
                  <td><?php echo !empty($shipment['tracking_code']) ? ('<code>' . $esc((string) $shipment['tracking_code']) . '</code>') : '-'; ?></td>
                  <td><?php echo $esc(!empty($shipment['shipped_at']) ? date('d/m/Y H:i', strtotime((string) $shipment['shipped_at'])) : '-'); ?></td>
                  <td><?php echo $esc(!empty($shipment['delivered_at']) ? date('d/m/Y H:i', strtotime((string) $shipment['delivered_at'])) : '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
