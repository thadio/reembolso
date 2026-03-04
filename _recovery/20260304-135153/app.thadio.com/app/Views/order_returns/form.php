<?php
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $formData */
/** @var int $orderId */
/** @var array $orderItems */
/** @var array $returnItems */
/** @var array $alreadyReturned */
/** @var array $availableMap */
/** @var array|null $orderData */
/** @var array|null $customer */
/** @var array $statusOptions */
/** @var array $returnMethodOptions */
/** @var array $refundMethodOptions */
/** @var array $refundStatusOptions */
/** @var callable $esc */
?>
<?php
  $editing = $editing ?? false;
  $orderId = (int) ($orderId ?? 0);
  $orderItems = $orderItems ?? [];
  $returnItems = $returnItems ?? [];
  $alreadyReturned = $alreadyReturned ?? [];
  $availableMap = $availableMap ?? [];
  $statusOptions = $statusOptions ?? [];
  $returnMethodOptions = $returnMethodOptions ?? [];
  $refundMethodOptions = $refundMethodOptions ?? [];
  $refundStatusOptions = $refundStatusOptions ?? [];

  $currentQuantities = [];
  foreach ($returnItems as $item) {
      $lineId = isset($item['order_item_id']) ? (int) $item['order_item_id'] : 0;
      if ($lineId > 0) {
          $currentQuantities[$lineId] = (int) ($item['quantity'] ?? 0);
      }
  }

  $orderStatus = '-';
  if ($orderData) {
      $orderStatus = isset($orderData['status']) ? (string) $orderData['status'] : '-';
      if (strpos($orderStatus, 'wc-') === 0) {
          $orderStatus = substr($orderStatus, 3);
      }
      $orderStatus = $orderStatus === '' ? '-' : $orderStatus;
  }

  $customerName = $customer['name'] ?? '-';
  $customerEmail = $customer['email'] ?? '';
?>

<?php if ($success): ?>
  <div class="alert success" style="margin-top:12px;"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error" style="margin-top:12px;"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
  <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
  <input type="hidden" name="pessoa_id" value="<?php echo $esc((string) ($formData['pessoa_id'] ?? '')); ?>">
  <input type="hidden" name="customer_name" value="<?php echo $esc((string) ($formData['customer_name'] ?? $customerName)); ?>">
  <input type="hidden" name="customer_email" value="<?php echo $esc((string) ($formData['customer_email'] ?? $customerEmail)); ?>">
  <input type="hidden" name="refund_status" id="refund_status_locked" value="<?php echo $esc((string) ($formData['refund_status'] ?? 'done')); ?>" hidden>

  <div class="card-grid">
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
            <?php foreach ($returnMethodOptions as $key => $label): ?>
              <option value="<?php echo $esc($key); ?>"<?php echo (($formData['return_method'] ?? '') === $key) ? ' selected' : ''; ?>>
                <?php echo $esc($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="status">Status da devolução *</label>
          <select id="status" name="status" required>
            <?php foreach ($statusOptions as $key => $label): ?>
              <option value="<?php echo $esc($key); ?>"<?php echo (($formData['status'] ?? '') === $key) ? ' selected' : ''; ?>>
                <?php echo $esc($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="help-text">Status "Devolução recebida" ou "Produto não entregue" já ajusta disponibilidade.</div>
        </div>
        <div class="field" data-return-tracking>
          <label for="tracking_code">Código de rastreamento</label>
          <input id="tracking_code" name="tracking_code" type="text" maxlength="160" value="<?php echo $esc((string) ($formData['tracking_code'] ?? '')); ?>" placeholder="Opcional">
        </div>
        <div class="field" data-return-expected>
          <label for="expected_at">Previsão de recebimento</label>
          <input id="expected_at" name="expected_at" type="date" value="<?php echo $esc((string) ($formData['expected_at'] ?? '')); ?>">
        </div>
        <div class="field">
          <label for="received_at">Recebido em</label>
          <input id="received_at" name="received_at" type="datetime-local" value="<?php echo $esc((string) ($formData['received_at'] ?? '')); ?>">
        </div>
        <div class="field full-width" data-return-notes-toggle>
          <button type="button" class="btn ghost" data-return-notes-button>Adicionar observações</button>
        </div>
        <div class="field full-width" data-return-notes hidden>
          <label for="notes">Observações</label>
          <textarea id="notes" name="notes" rows="3" maxlength="500" placeholder="Motivo da devolução, detalhes de logística."><?php echo $esc((string) ($formData['notes'] ?? '')); ?></textarea>
        </div>
      </div>
    </section>

    <section class="card">
      <header>
        <div>
          <h2>Reembolso / crédito</h2>
        </div>
      </header>
      <div class="field-grid">
        <div class="field">
          <label for="refund_method">Forma de reembolso</label>
          <select id="refund_method" name="refund_method" required>
            <?php foreach ($refundMethodOptions as $key => $label): ?>
              <option value="<?php echo $esc($key); ?>"<?php echo (($formData['refund_method'] ?? '') === $key) ? ' selected' : ''; ?>>
                <?php echo $esc($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="refund_status">Status do reembolso</label>
          <select id="refund_status" name="refund_status" required>
            <?php foreach ($refundStatusOptions as $key => $label): ?>
              <option value="<?php echo $esc($key); ?>"<?php echo (($formData['refund_status'] ?? '') === $key) ? ' selected' : ''; ?>>
                <?php echo $esc($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="refund_amount">Valor a reembolsar *</label>
          <input id="refund_amount" name="refund_amount" type="text" inputmode="decimal" data-number-br step="0.01" min="0" value="<?php echo $esc((string) ($formData['refund_amount'] ?? '')); ?>" placeholder="0,00">
        </div>
      </div>
    </section>
  </div>

  <section class="card">
    <header>
      <div>
        <h2>Itens do pedido</h2>
        <div class="subtitle">Selecione os produtos devolvidos e a quantidade.</div>
      </div>
    </header>
    <div style="overflow:auto;">
      <table data-table="interactive">
        <thead>
          <tr>
            <th data-sort-key="name" aria-sort="none">Produto</th>
            <th data-sort-key="sku" aria-sort="none">SKU</th>
            <th data-sort-key="sold" aria-sort="none">Vendido</th>
            <th data-sort-key="returned" aria-sort="none">Já devolvido</th>
            <th data-sort-key="available" aria-sort="none">Disponível</th>
            <th data-sort-key="quantity" aria-sort="none">Qtd a devolver</th>
            <th data-sort-key="price" aria-sort="none" class="col-total">Preço un.</th>
            <th data-sort-key="total" aria-sort="none" class="col-total">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orderItems)): ?>
            <tr class="no-results"><td colspan="8">Itens do pedido não encontrados.</td></tr>
          <?php else: ?>
            <?php foreach ($orderItems as $lineId => $item): ?>
              <?php
                $soldQty = (int) ($item['quantity'] ?? 0);
                $returnedQty = $alreadyReturned[$lineId] ?? 0;
                $availableQty = $availableMap[$lineId] ?? $soldQty;
                $currentQty = $currentQuantities[$lineId] ?? '';
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
                $totalValue = $currentQty !== '' && $currentQty !== null && $currentQty !== 0
                  ? $unitPrice * (int) $currentQty
                  : 0.0;
                $name = (string) ($item['name'] ?? '');
                $sku = (string) ($item['sku'] ?? '');
              ?>
              <tr>
                <td data-value="<?php echo $esc($name); ?>"><?php echo $esc($name); ?></td>
                <td data-value="<?php echo $esc($sku); ?>"><?php echo $esc($sku); ?></td>
                <td data-value="<?php echo $soldQty; ?>"><?php echo $soldQty; ?></td>
                <td data-value="<?php echo $returnedQty; ?>"><?php echo $returnedQty; ?></td>
                <td data-value="<?php echo $availableQty; ?>"><?php echo $availableQty; ?></td>
                <td>
                  <input
                    type="number"
                    name="items[<?php echo $lineId; ?>][quantity]"
                    min="0"
                    max="<?php echo $availableQty; ?>"
                    value="<?php echo $esc((string) $currentQty); ?>"
                    style="width:100px;"
                    aria-label="Quantidade para <?php echo $esc($name); ?>"
                  >
                  <input type="hidden" name="items[<?php echo $lineId; ?>][unit_price]" value="<?php echo number_format($unitPrice, 2, '.', ''); ?>">
                </td>
                <td class="col-total" data-value="<?php echo number_format($unitPrice, 2, ',', '.'); ?>">
                  R$ <?php echo number_format($unitPrice, 2, ',', '.'); ?>
                </td>
                <td class="col-total" data-value="<?php echo number_format($totalValue, 2, ',', '.'); ?>">
                  <?php echo $totalValue > 0 ? 'R$ ' . number_format($totalValue, 2, ',', '.') : '-'; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div style="display:flex;justify-content:flex-end;gap:8px;">
    <a class="btn ghost" href="pedido-devolucao-list.php">Cancelar</a>
    <button class="btn primary" type="submit"><?php echo $editing ? 'Atualizar devolução' : 'Salvar devolução'; ?></button>
  </div>
</form>

<?php
  // Cancel form placed outside the main edit form to avoid nested forms.
  $canCancelReturn = userCan('order_returns.edit');
  $restockedAt = $formData['restocked_at'] ?? '';
  $refundStatus = $formData['refund_status'] ?? '';
  $refundMethod = $formData['refund_method'] ?? '';
  $isCancelled = (($formData['status'] ?? '') === 'cancelled');
  $canRowCancel = $editing && $canCancelReturn && !$isCancelled && empty($restockedAt) && !($refundStatus === 'done' && $refundMethod !== 'none');
?>

<?php if ($canRowCancel): ?>
  <form method="post" action="pedido-devolucao-cancel.php" onsubmit="return confirm('Cancelar esta devolução?');" style="margin-top:12px;">
    <input type="hidden" name="return_id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
    <button class="btn ghost" type="submit">Cancelar devolução</button>
  </form>
<?php endif; ?>

<script>
  (() => {
    const statusSelect = document.getElementById('status');
    const trackingField = document.querySelector('[data-return-tracking]');
    const expectedField = document.querySelector('[data-return-expected]');
    const notesToggleWrap = document.querySelector('[data-return-notes-toggle]');
    const notesToggleButton = document.querySelector('[data-return-notes-button]');
    const notesField = document.querySelector('[data-return-notes]');
    const notesInput = document.getElementById('notes');
    const refundMethodSelect = document.getElementById('refund_method');
    const refundStatusSelect = document.getElementById('refund_status');
    const refundStatusLockedInput = document.getElementById('refund_status_locked');

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

    if (notesInput && notesInput.value.trim() !== '') {
      toggleNotes(true);
    }

    if (notesToggleButton) {
      notesToggleButton.addEventListener('click', () => toggleNotes(true));
    }

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

    if (refundMethodSelect) {
      refundMethodSelect.addEventListener('change', toggleRefundStatusLock);
      toggleRefundStatusLock();
    }

    if (statusSelect) {
      statusSelect.addEventListener('change', toggleLogistics);
      toggleLogistics();
    }
  })();
</script>
