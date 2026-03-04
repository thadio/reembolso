<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array|null $orderPreview */
/** @var float|null $lotCost */
/** @var array $typeOptions */
/** @var array $statusOptions */
/** @var array $categoryOptions */
/** @var array $vendorOptions */
/** @var array $lotOptions */
/** @var array $orderOptions */
/** @var array $paymentMethodOptions */
/** @var array $bankAccountOptions */
/** @var array $paymentTerminalOptions */
/** @var array $recurrenceOptions */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Lançamento Financeiro</h1>
      <div class="subtitle">Registre contas a pagar e receber.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Novo lançamento'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <?php if (!empty($orderPreview)): ?>
    <div class="order-summary-card" style="margin:14px 0;">
      <div class="order-summary-title">Pedido vinculado</div>
      <div class="order-summary-value">#<?php echo (int) ($orderPreview['order_id'] ?? 0); ?></div>
      <div class="order-summary-meta">
        <?php if (!empty($orderPreview['customer'])): ?>
          <?php echo $esc($orderPreview['customer']); ?> ·
        <?php endif; ?>
        R$ <?php echo number_format((float) ($orderPreview['total_sales'] ?? 0), 2, ',', '.'); ?> ·
        <?php echo $esc((string) ($orderPreview['payment_status'] ?? '')); ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" action="financeiro-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="type">Tipo *</label>
        <select id="type" name="type" required>
          <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
            <option value="<?php echo $esc($typeKey); ?>" <?php echo $formData['type'] === $typeKey ? 'selected' : ''; ?>>
              <?php echo $esc($typeLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="description">Descrição *</label>
        <input type="text" id="description" name="description" required maxlength="255" value="<?php echo $esc($formData['description']); ?>" placeholder="Ex: Conta de energia, venda #123">
      </div>
      <div class="field">
        <label for="amount">Valor *</label>
        <input type="text" id="amount" name="amount" required value="<?php echo $esc($formData['amount']); ?>" placeholder="0,00">
        <?php if ($lotCost !== null): ?>
          <div class="muted" style="font-size:12px;">Custo estimado do lote: R$ <?php echo number_format($lotCost, 2, ',', '.'); ?></div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="due_date">Data de vencimento</label>
        <input type="date" id="due_date" name="due_date" value="<?php echo $esc($formData['due_date']); ?>">
      </div>
      <div class="field" style="grid-column:1 / -1; padding:12px 14px; border:1px solid var(--border-color,#e0e0e0); border-radius:8px; margin-top:6px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:8px; font-weight:600;">
            <input type="checkbox" id="recurrence_enabled" name="recurrence_enabled" value="1" <?php echo ($formData['recurrence_enabled'] ?? '') === '1' ? 'checked' : ''; ?> <?php echo $editing ? 'disabled' : ''; ?>>
            Criar lançamentos recorrentes
          </label>
          <span class="muted" style="font-size:12px;">Gerará múltiplos vencimentos a partir da data informada.</span>
        </div>
        <div class="grid" style="margin-top:10px;">
          <div class="field">
            <label for="recurrence_frequency">Frequência</label>
            <select id="recurrence_frequency" name="recurrence_frequency" <?php echo $editing ? 'disabled' : ''; ?>>
              <?php foreach ($recurrenceOptions as $freqKey => $freqLabel): ?>
                <option value="<?php echo $esc($freqKey); ?>" <?php echo (string) ($formData['recurrence_frequency'] ?? '') === (string) $freqKey ? 'selected' : ''; ?>>
                  <?php echo $esc($freqLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="recurrence_count">Quantidade de repetições</label>
            <input type="number" id="recurrence_count" name="recurrence_count" min="2" max="60" step="1" value="<?php echo $esc((string) ($formData['recurrence_count'] ?? 3)); ?>" <?php echo $editing ? 'disabled' : ''; ?>>
            <div class="muted" style="font-size:12px;">Inclui o primeiro lançamento. Mínimo 2.</div>
          </div>
        </div>
        <?php if ($editing): ?>
          <div class="muted" style="font-size:12px; margin-top:6px;">Recorrência disponível apenas na criação de um novo lançamento.</div>
        <?php endif; ?>
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
        <label for="paid_at">Data do pagamento</label>
        <input type="date" id="paid_at" name="paid_at" value="<?php echo $esc($formData['paid_at']); ?>">
      </div>
      <div class="field">
        <label for="paid_amount">Valor pago</label>
        <input type="text" id="paid_amount" name="paid_amount" value="<?php echo $esc($formData['paid_amount']); ?>" placeholder="0,00">
      </div>
      <div class="field">
        <label for="category_id">Categoria</label>
        <select id="category_id" name="category_id">
          <option value="" <?php echo $formData['category_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($categoryOptions as $category): ?>
            <?php $categoryId = (string) ($category['id'] ?? ''); ?>
            <option value="<?php echo $esc($categoryId); ?>" <?php echo (string) $formData['category_id'] === $categoryId ? 'selected' : ''; ?>>
              <?php echo $esc((string) ($category['name'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="supplier_pessoa_id">Fornecedor</label>
        <input
          type="search"
          id="supplier_pessoa_id"
          name="supplier_pessoa_id"
          list="finance-form-supplier-options"
          value="<?php echo $esc((string) ($formData['supplier_pessoa_id'] ?? '')); ?>"
          placeholder="Digite nome, código ou ID">
        <datalist id="finance-form-supplier-options">
          <?php foreach ($vendorOptions as $vendor): ?>
            <?php
              $vendorPessoaId = (string) ((int) ($vendor['id'] ?? 0));
              $vendorCode = (string) ((int) ($vendor['id_vendor'] ?? 0));
              $vendorName = trim((string) ($vendor['full_name'] ?? ''));
            ?>
            <?php if ($vendorName !== ''): ?>
              <option value="<?php echo $esc($vendorName); ?>" label="<?php echo $esc('Pessoa ' . $vendorPessoaId . ($vendorCode !== '0' ? ' · Cód. ' . $vendorCode : '')); ?>"></option>
            <?php endif; ?>
            <?php if ($vendorPessoaId !== '0'): ?>
              <option value="<?php echo $esc($vendorPessoaId); ?>" label="<?php echo $esc($vendorName !== '' ? $vendorName : 'Fornecedor'); ?>"></option>
            <?php endif; ?>
            <?php if ($vendorCode !== '0'): ?>
              <option value="<?php echo $esc($vendorCode); ?>" label="<?php echo $esc(($vendorName !== '' ? $vendorName : 'Fornecedor') . ' · Pessoa ' . $vendorPessoaId); ?>"></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="field">
        <label for="lot_id">Lote de produtos</label>
        <select id="lot_id" name="lot_id">
          <option value="" <?php echo $formData['lot_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($lotOptions as $lot): ?>
            <?php $lotId = (string) ($lot['id'] ?? ''); ?>
            <?php $lotLabel = $lot['name'] ?? ('Lote #' . $lotId); ?>
            <option value="<?php echo $esc($lotId); ?>" <?php echo (string) $formData['lot_id'] === $lotId ? 'selected' : ''; ?>>
              <?php echo $esc($lotLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="order_id">Pedido vinculado</label>
        <input type="text" id="order_id" name="order_id" list="finance-order-options" value="<?php echo $esc($formData['order_id']); ?>" placeholder="#123">
        <?php if (!empty($orderOptions)): ?>
          <datalist id="finance-order-options">
            <?php foreach ($orderOptions as $order): ?>
              <option value="<?php echo (int) ($order['id'] ?? 0); ?>"><?php echo $esc((string) ($order['label'] ?? '')); ?></option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="payment_method_id">Método de pagamento</label>
        <select id="payment_method_id" name="payment_method_id">
          <option value="" <?php echo $formData['payment_method_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($paymentMethodOptions as $method): ?>
            <?php $methodId = (string) ($method['id'] ?? ''); ?>
            <option value="<?php echo $esc($methodId); ?>" <?php echo (string) $formData['payment_method_id'] === $methodId ? 'selected' : ''; ?>>
              <?php echo $esc((string) ($method['name'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="bank_account_id">Conta bancária</label>
        <select id="bank_account_id" name="bank_account_id">
          <option value="" <?php echo $formData['bank_account_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($bankAccountOptions as $account): ?>
            <?php
              $accountId = (string) ($account['id'] ?? '');
              $bankLabel = $account['bank_name'] ?? '';
              $accountLabel = $account['label'] ?? '';
              $label = trim($bankLabel . ' ' . $accountLabel);
            ?>
            <option value="<?php echo $esc($accountId); ?>" <?php echo (string) $formData['bank_account_id'] === $accountId ? 'selected' : ''; ?>>
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="payment_terminal_id">Maquininha/sistema</label>
        <select id="payment_terminal_id" name="payment_terminal_id">
          <option value="" <?php echo $formData['payment_terminal_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($paymentTerminalOptions as $terminal): ?>
            <?php $terminalId = (string) ($terminal['id'] ?? ''); ?>
            <option value="<?php echo $esc($terminalId); ?>" <?php echo (string) $formData['payment_terminal_id'] === $terminalId ? 'selected' : ''; ?>>
              <?php echo $esc((string) ($terminal['name'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="3" maxlength="500" placeholder="Opcional"><?php echo $esc($formData['notes']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar lançamento</button>
    </div>
  </form>
</div>
