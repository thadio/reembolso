<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $customerOptions */
/** @var array $identificationOptions */
/** @var array $typeOptions */
/** @var array $voucherStatement */
/** @var array|null $vendorInfo */
/** @var array $bankAccountOptions */
/** @var callable $esc */
?>
<?php
  $identificationOptions = $identificationOptions ?? [];
  $vendorInfo = $vendorInfo ?? null;
  $bankAccountOptions = $bankAccountOptions ?? [];
  $voucherStatement = $voucherStatement ?? ['entries' => [], 'opening_balance' => null, 'current_balance' => null, 'total_usage' => 0.0, 'error' => null];
  $formatMoney = static function ($value): string {
    $value = (float) $value;
    $prefix = $value < 0 ? '-R$ ' : 'R$ ';
    return $prefix . number_format(abs($value), 2, ',', '.');
  };
  $formatBalance = static function ($value): string {
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
  };
  $formatDate = static function ($value): string {
    if (!$value) {
      return '-';
    }
    $timestamp = strtotime((string) $value);
    if (!$timestamp) {
      return (string) $value;
    }
    return date('d/m/Y H:i', $timestamp);
  };
  $selectedCustomerId = (int) ($formData['pessoa_id'] ?? 0);
  $selectedCustomerName = trim((string) ($formData['customer_name'] ?? ''));
  $selectedCustomerEmail = trim((string) ($formData['customer_email'] ?? ''));
  if ($selectedCustomerName === '' && $selectedCustomerId > 0) {
    foreach ($customerOptions as $customer) {
      if ((int) ($customer['id'] ?? 0) === $selectedCustomerId) {
        $selectedCustomerName = trim((string) ($customer['name'] ?? ''));
        $selectedCustomerEmail = trim((string) ($customer['email'] ?? ''));
        break;
      }
    }
  }
  $canEdit = userCan('voucher_accounts.edit');
  $readOnly = $editing && !$canEdit;
  $readOnlyAttr = $readOnly ? ' disabled' : '';
  $defaultPeriodStart = date('Y-m-01', strtotime('first day of last month'));
  $defaultPeriodEnd = date('Y-m-t', strtotime('last day of last month'));
  $currentBalance = isset($voucherStatement['current_balance']) ? (float) $voucherStatement['current_balance'] : (float) ($formData['balance'] ?? 0);
  $payoutDefaultAmount = $currentBalance > 0 ? number_format($currentBalance, 2, '.', '') : '';
  $payoutAmountValue = isset($_POST['payout_action']) ? (string) ($_POST['payout_amount'] ?? '') : $payoutDefaultAmount;
  $payoutPixKeyValue = isset($_POST['payout_action']) ? (string) ($_POST['pix_key'] ?? '') : (string) ($vendorInfo['pix_key'] ?? '');
  $payoutPaidAtValue = isset($_POST['payout_action']) ? (string) ($_POST['payout_paid_at'] ?? '') : date('Y-m-d\TH:i');
  $payoutNotesValue = isset($_POST['payout_action']) ? (string) ($_POST['payout_notes'] ?? '') : '';
  $defaultPayoutBankAccount = '';
  if (!isset($_POST['payout_action']) && !empty($bankAccountOptions)) {
    $defaultPayoutBankAccount = (string) ((int) ($bankAccountOptions[0]['id'] ?? 0));
  }
  $payoutBankAccountValue = isset($_POST['payout_action']) ? (string) ($_POST['payout_bank_account_id'] ?? '') : $defaultPayoutBankAccount;
  $canPayout = $editing && (string) ($formData['type'] ?? '') === 'credito' && userCan('voucher_accounts.payout');
  $payoutDisabled = $currentBalance <= 0.0 || !$vendorInfo || empty($bankAccountOptions);
  $customerHint = 'Informe cliente.';
  if ($selectedCustomerName !== '' || $selectedCustomerEmail !== '') {
    $customerHint = 'Selecionado: ' . ($selectedCustomerName !== '' ? $selectedCustomerName : 'Cliente');
    if ($selectedCustomerEmail !== '') {
      $customerHint .= ' - ' . $selectedCustomerEmail;
    }
  } elseif ($selectedCustomerId > 0) {
    $customerHint = 'Cliente nao encontrado.';
  }

  // Detecção de divergência: label/description contêm nome diferente do dono atual
  $divergenceWarnings = [];
  if ($editing && $selectedCustomerName !== '') {
    $ownerFirstName = mb_strtolower(explode(' ', $selectedCustomerName)[0] ?? '');
    $currentLabel = mb_strtolower(trim((string) ($formData['label'] ?? '')));
    $currentDesc = mb_strtolower(trim((string) ($formData['description'] ?? '')));
    if ($ownerFirstName !== '' && $currentLabel !== '' && mb_strpos($currentLabel, $ownerFirstName) === false) {
      // Label contém texto que pode referenciar outra pessoa
      $divergenceWarnings[] = 'A identificacao "' . $formData['label'] . '" pode conter referencia a outra pessoa. O dono atual deste cupom e ' . $selectedCustomerName . '.';
    }
    if ($ownerFirstName !== '' && $currentDesc !== '' && mb_strpos($currentDesc, $ownerFirstName) === false) {
      $divergenceWarnings[] = 'A descricao pode conter referencia a outra pessoa. O dono atual deste cupom e ' . $selectedCustomerName . '.';
    }
  }
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Cupom/Credito</h1>
      <div class="subtitle">Cada cupom ou credito e uma conta corrente vinculada a uma cliente.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Novo cupom/credito'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <?php if (!empty($divergenceWarnings)): ?>
    <div class="alert error" style="border-left:4px solid #c00;">
      <strong>⚠ Alerta de integridade:</strong>
      <?php foreach ($divergenceWarnings as $dw): ?>
        <div><?php echo $esc($dw); ?></div>
      <?php endforeach; ?>
      <div style="margin-top:6px;font-size:13px;color:var(--muted);">Revise a identificacao e descricao para garantir que correspondam ao dono real (pessoa_id) deste cupom.</div>
    </div>
  <?php endif; ?>

  <form method="post" action="cupom-credito-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <input type="hidden" name="pessoa_id" value="<?php echo $esc((string) ($formData['pessoa_id'] ?? '')); ?>">
    <div class="grid">
      <div class="field">
        <label for="pessoa_id">Pessoa (ID) *</label>
        <input type="text" id="pessoa_id" name="pessoa_id" list="customerOptions" required value="<?php echo $esc((string) ($formData['pessoa_id'] ?? '')); ?>" placeholder="Digite para buscar"<?php echo $readOnlyAttr; ?>>
        <small id="customerHint" data-fallback-label="<?php echo $esc($customerHint); ?>" style="color:var(--muted);display:block;margin-top:6px;">
          <?php echo $esc($customerHint); ?>
        </small>
        <datalist id="customerOptions">
          <?php foreach ($customerOptions as $customer): ?>
            <option value="<?php echo (int) $customer['id']; ?>" label="<?php echo $esc($customer['name'] . ' - ' . $customer['email']); ?>">
              <?php echo $esc($customer['name'] . ' - ' . $customer['email']); ?>
            </option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="field">
        <label for="label">Identificacao *</label>
        <select id="label" name="label" required<?php echo $readOnlyAttr; ?>>
          <option value="" disabled <?php echo $formData['label'] === '' ? 'selected' : ''; ?>>Selecione um padrao</option>
          <?php foreach ($identificationOptions as $option): ?>
            <?php
              $optionLabel = (string) ($option['label'] ?? '');
              $optionStatus = (string) ($option['status'] ?? 'ativo');
              $optionText = $optionStatus === 'ativo' ? $optionLabel : $optionLabel . ' (inativo)';
            ?>
            <option value="<?php echo $esc($optionLabel); ?>" <?php echo $formData['label'] === $optionLabel ? 'selected' : ''; ?>>
              <?php echo $esc($optionText); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (userCan('voucher_identification_patterns.view')): ?>
          <small style="color:var(--muted);display:block;margin-top:6px;">
            <a href="cupom-credito-identificacao-list.php">Gerenciar padroes de identificacao</a>
          </small>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="type">Tipo *</label>
        <select id="type" name="type" required<?php echo $readOnlyAttr; ?>>
          <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
            <option value="<?php echo $esc($typeKey); ?>" <?php echo $formData['type'] === $typeKey ? 'selected' : ''; ?>>
              <?php echo $esc($typeLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="code">Codigo do cupom</label>
        <input type="text" id="code" name="code" maxlength="80" value="<?php echo $esc($formData['code']); ?>" placeholder="Opcional para credito"<?php echo $readOnlyAttr; ?>>
      </div>
      <div class="field">
        <label for="balance">Saldo (R$)</label>
  <input type="text" inputmode="decimal" data-number-br step="0.01" id="balance" name="balance" value="<?php echo $esc($formData['balance']); ?>"<?php echo $readOnlyAttr; ?>>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status"<?php echo $readOnlyAttr; ?>>
          <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="description">Descricao</label>
        <textarea id="description" name="description" rows="2" maxlength="255" placeholder="Opcional"<?php echo $readOnlyAttr; ?>><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <?php if (!$readOnly): ?>
      <div class="footer">
        <button class="ghost" type="reset">Limpar</button>
        <button class="primary" type="submit">Salvar cupom/credito</button>
      </div>
    <?php else: ?>
      <div class="alert muted">Sem permissao para editar este cupom/credito.</div>
    <?php endif; ?>
  </form>

  <?php if ($editing): ?>
    <?php
      $isConsignacaoScope = (string) ($formData['scope'] ?? '') === 'consignacao';
    ?>
    <?php if ($isConsignacaoScope): ?>
      <div class="alert warning" style="margin-top:28px;border-left:4px solid #f59e0b;">
        <strong>⚠ Conta de consignação</strong><br>
        Esta conta possui scope <code>consignacao</code>. O pagamento PIX manual não está disponível nesta tela.
        Use o <a href="consignacao-pagamento-cadastro.php">módulo de pagamentos de consignação</a> para registrar pagamentos a fornecedoras.
      </div>
    <?php elseif ($canPayout): ?>
      <div class="card" style="margin-top:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
          <div>
            <h2>Pagamento PIX ao fornecedor</h2>
            <div class="subtitle">Converta o saldo de comissao em pagamento via PIX e registre o debito no extrato.</div>
          </div>
          <span class="pill">Saldo disponivel: <?php echo $esc($formatBalance($currentBalance)); ?></span>
        </div>

        <?php if (!$vendorInfo): ?>
          <div class="alert error" style="margin-top:12px;">Pessoa vinculada ao cupom/credito nao encontrada.</div>
        <?php elseif (empty($bankAccountOptions)): ?>
          <div class="alert error" style="margin-top:12px;">Cadastre uma conta bancária ativa para selecionar a origem do PIX.</div>
        <?php else: ?>
          <div style="margin-top:12px; color: var(--muted);">
            <strong>Fornecedor:</strong> <?php echo $esc((string) ($vendorInfo['name'] ?? '')); ?>
            <?php if (!empty($vendorInfo['email'])): ?> | <strong>Email:</strong> <?php echo $esc((string) $vendorInfo['email']); ?><?php endif; ?>
            <?php if (!empty($vendorInfo['pix_key'])): ?> | <strong>PIX cadastrado:</strong> <?php echo $esc((string) $vendorInfo['pix_key']); ?><?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="cupom-credito-cadastro.php" style="margin-top:14px;">
          <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
          <input type="hidden" name="payout_action" value="1">
          <div class="grid">
            <div class="field">
              <label for="payout_amount">Valor do pagamento (R$)</label>
              <input type="text" inputmode="decimal" data-number-br step="0.01" id="payout_amount" name="payout_amount" required value="<?php echo $esc($payoutAmountValue); ?>" <?php echo $payoutDisabled ? 'disabled' : ''; ?> placeholder="Ex.: 150,00">
              <small style="color:var(--muted);display:block;margin-top:6px;">Nao pode ultrapassar o saldo atual.</small>
            </div>
            <div class="field">
              <label for="pix_key">Chave PIX do fornecedor</label>
              <input type="text" id="pix_key" name="pix_key" maxlength="180" value="<?php echo $esc($payoutPixKeyValue); ?>" <?php echo $payoutDisabled ? 'disabled' : ''; ?> placeholder="Email, telefone ou chave aleatoria">
              <small style="color:var(--muted);display:block;margin-top:6px;">Usaremos a chave informada acima (ou a chave PIX cadastrada na pessoa).</small>
            </div>
            <div class="field">
              <label for="payout_bank_account_id">Conta de origem do PIX *</label>
              <select id="payout_bank_account_id" name="payout_bank_account_id" required <?php echo $payoutDisabled ? 'disabled' : ''; ?>>
                <option value="">Selecione</option>
                <?php foreach ($bankAccountOptions as $bankAccount): ?>
                  <?php
                    $bankId = (int) ($bankAccount['id'] ?? 0);
                    $bankName = trim((string) ($bankAccount['bank_name'] ?? 'Conta'));
                    $label = trim((string) ($bankAccount['label'] ?? ''));
                    $bankLabel = $bankName . ($label !== '' ? ' · ' . $label : '');
                  ?>
                  <option value="<?php echo $bankId; ?>" <?php echo (string) $payoutBankAccountValue === (string) $bankId ? 'selected' : ''; ?>>
                    <?php echo $esc($bankLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small style="color:var(--muted);display:block;margin-top:6px;">Conta corrente que será debitada no pagamento.</small>
            </div>
            <div class="field">
              <label for="payout_paid_at">Data e hora do pagamento</label>
              <input type="datetime-local" id="payout_paid_at" name="payout_paid_at" value="<?php echo $esc($payoutPaidAtValue); ?>" <?php echo $payoutDisabled ? 'disabled' : ''; ?>>
            </div>
            <div class="field" style="grid-column:1 / -1;">
              <label for="payout_notes">Observacoes (opcional)</label>
              <input type="text" id="payout_notes" name="payout_notes" maxlength="160" value="<?php echo $esc($payoutNotesValue); ?>" <?php echo $payoutDisabled ? 'disabled' : ''; ?> placeholder="Comprovante ou referencia do pagamento">
            </div>
          </div>
          <div class="footer">
            <button class="primary" type="submit" <?php echo $payoutDisabled ? 'disabled' : ''; ?>>Registrar pagamento PIX</button>
            <?php if ($payoutDisabled): ?>
              <span style="color:var(--muted);">Preencha saldo e dados de pagamento para habilitar.</span>
            <?php endif; ?>
          </div>
        </form>
      </div>
    <?php endif; /* canPayout / consignacaoScope */ ?>

    <div style="margin-top:32px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div>
          <h2>Extrato do cupom/credito</h2>
          <div class="subtitle">Lancamentos de criacao, uso e reversoes de venda.</div>
        </div>
        <?php if (isset($voucherStatement['current_balance'])): ?>
          <span class="pill">Saldo final: <?php echo $esc($formatBalance((float) $voucherStatement['current_balance'])); ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($voucherStatement['error'])): ?>
        <div class="alert error"><?php echo $esc((string) $voucherStatement['error']); ?></div>
      <?php endif; ?>

      <div style="margin-top:14px;">
        <form method="get" action="cupom-credito-extrato.php" target="_blank" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
          <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
          <div class="field" style="min-width:180px;">
            <label for="statement_start">Periodo inicial</label>
            <input type="date" id="statement_start" name="start" value="<?php echo $esc($defaultPeriodStart); ?>">
          </div>
          <div class="field" style="min-width:180px;">
            <label for="statement_end">Periodo final</label>
            <input type="date" id="statement_end" name="end" value="<?php echo $esc($defaultPeriodEnd); ?>">
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <button class="btn ghost" type="submit">Gerar PDF</button>
          </div>
        </form>
        <small style="color:var(--muted);display:block;margin-top:6px;">
          Selecione um periodo para gerar o extrato em PDF. Deixe em branco para incluir tudo.
        </small>
      </div>

      <div class="table-tools" style="margin-top:14px;">
        <input type="search" data-filter-global placeholder="Buscar no extrato" aria-label="Buscar no extrato">
        <span style="color:var(--muted);font-size:13px;">Clique nos cabecalhos para ordenar</span>
      </div>

      <div style="overflow:auto;">
        <table data-table="interactive">
          <thead>
            <tr>
              <th data-sort-key="date" aria-sort="none">Data</th>
              <th data-sort-key="type" aria-sort="none">Tipo</th>
              <th data-sort-key="description" aria-sort="none">Descricao</th>
              <th data-sort-key="amount" aria-sort="none">Valor</th>
              <th data-sort-key="balance" aria-sort="none">Saldo</th>
            </tr>
            <tr class="filters-row">
              <th><input type="search" data-filter-col="date" placeholder="Filtrar data" aria-label="Filtrar data"></th>
              <th><input type="search" data-filter-col="type" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
              <th><input type="search" data-filter-col="description" placeholder="Filtrar descricao" aria-label="Filtrar descricao"></th>
              <th><input type="search" data-filter-col="amount" placeholder="Filtrar valor" aria-label="Filtrar valor"></th>
              <th><input type="search" data-filter-col="balance" placeholder="Filtrar saldo" aria-label="Filtrar saldo"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($voucherStatement['entries'])): ?>
              <tr class="no-results"><td colspan="5">Nenhum lancamento registrado.</td></tr>
            <?php else: ?>
              <?php foreach ($voucherStatement['entries'] as $entry): ?>
                <?php
                  $entryType = (string) ($entry['type'] ?? '');
                  $entryLabel = $entryType === 'debito' ? 'Debito' : 'Credito';
                  $entryAmount = (float) ($entry['amount'] ?? 0);
                  $entryBalance = (float) ($entry['balance'] ?? 0);
                  $entryDesc = (string) ($entry['description'] ?? '');
                  $entryOrderId = isset($entry['order_id']) ? (int) $entry['order_id'] : 0;
                  $entryDate = $entry['date'] ?? null;
                  $orderLink = $entryOrderId > 0 ? 'pedido-cadastro.php?id=' . $entryOrderId : '';
                ?>
                <tr>
                  <td data-value="<?php echo $esc((string) $entryDate); ?>"><?php echo $esc($formatDate($entryDate)); ?></td>
                  <td data-value="<?php echo $esc($entryLabel); ?>"><?php echo $esc($entryLabel); ?></td>
                  <td data-value="<?php echo $esc($entryDesc); ?>">
                    <?php if ($orderLink): ?>
                      <a href="<?php echo $esc($orderLink); ?>"><?php echo $esc($entryDesc); ?></a>
                    <?php else: ?>
                      <?php echo $esc($entryDesc); ?>
                    <?php endif; ?>
                  </td>
                  <td data-value="<?php echo $esc((string) $entryAmount); ?>"><?php echo $esc($formatMoney($entryAmount)); ?></td>
                  <td data-value="<?php echo $esc((string) $entryBalance); ?>"><?php echo $esc($formatBalance($entryBalance)); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  (function() {
    const customerOptions = <?php echo json_encode(array_column($customerOptions, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const input = document.getElementById('pessoa_id');
    const hint = document.getElementById('customerHint');
    if (!input || !hint) return;

    const render = () => {
      const raw = String(input.value || '').trim();
      if (!raw) {
        hint.textContent = 'Informe um cliente.';
        return;
      }
      const id = parseInt(raw, 10);
      const customer = Number.isFinite(id) ? customerOptions[id] : null;
      if (customer) {
        let label = customer.name ? customer.name : 'Cliente';
        if (customer.email) {
          label += ' - ' + customer.email;
        }
        hint.textContent = 'Selecionado: ' + label;
        return;
      }
      const fallback = hint.dataset.fallbackLabel || '';
      hint.textContent = fallback !== '' ? fallback : 'Cliente nao encontrado.';
    };

    input.addEventListener('input', render);
    input.addEventListener('change', render);
    render();
  })();
</script>
