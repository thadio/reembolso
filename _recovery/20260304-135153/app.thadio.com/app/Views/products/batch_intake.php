<?php
/** @var array $errors */
/** @var array $warnings */
/** @var string $success */
/** @var array $vendorOptions */
/** @var array|null $selectedVendor */
/** @var float|string|null $defaultCommission */
/** @var float|string|null $defaultCost */
/** @var string|null $selectedSource */
/** @var int $consignmentId */
/** @var array|null $consignment */
/** @var array|null $consignmentTotals */
/** @var array $submittedRows */
/** @var array $createdItems */
/** @var array $lotOptions */
/** @var int|null $selectedLotId */
/** @var array|null $selectedLot */
/** @var string $lotSuccess */
/** @var string $skuContextKey */
/** @var callable $esc */

$rows = $submittedRows;
$commissionValue = $defaultCommission !== null && $defaultCommission !== '' ? \App\Support\Input::parseNumber($defaultCommission) : null;
$defaultCostValue = $defaultCost !== null && $defaultCost !== '' ? \App\Support\Input::parseNumber($defaultCost) : null;
$lotOptions = $lotOptions ?? [];
$selectedLotId = $selectedLotId ?? null;
$selectedLot = $selectedLot ?? null;
$currentSource = $selectedSource ?: 'consignacao';
if (empty($rows)) {
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        $rows[] = [
            'name' => '',
            'price' => '',
            'percentualConsignacao' => $commissionValue,
            'cost' => $defaultCostValue,
        ];
    }
}

function fmtPercentBadge(?float $value): string {
    if ($value === null) {
        return '—';
    }
    return number_format($value, 2, ',', '.') . '%';
}
?>

<style>
  .batch-hero {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
  }
  .batch-hero .subtitle { margin: 0; }
  .batch-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  }
  .lock-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: linear-gradient(120deg, rgba(63,124,255,0.12), rgba(0,198,174,0.12));
    font-weight: 700;
    border: 1px solid #dbeafe;
  }
  .batch-shell {
    border: 1px solid #eef2f7;
    border-radius: 16px;
    padding: 14px;
    background: #f8fafc;
    display: grid;
    gap: 12px;
  }
  .lines-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .line-row {
    display: grid;
    grid-template-columns: 64px 120px 1.1fr 0.9fr 180px 60px;
    gap: 8px;
    align-items: center;
    padding: 8px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #fff;
  }
  .line-row .muted {
    color: var(--muted);
    font-weight: 700;
  }
  .line-row input[type="text"],
  .line-row input[type="number"] {
    width: 100%;
  }
  .line-row .chip {
    padding: 8px 10px;
    border-radius: 10px;
    background: #ecf4ff;
    border: 1px solid #dbeafe;
    color: #1d4ed8;
    font-weight: 700;
    font-size: 12px;
    text-align: center;
  }
  .line-row .sku-chip {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #0f172a;
  }
  .line-row button {
    width: 100%;
  }
  .lines-stack {
    display: grid;
    gap: 8px;
  }
  .help-text {
    color: var(--muted);
    font-size: 13px;
  }
  .toggle-edit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    font-weight: 700;
    color: var(--ink);
  }
  .toggle-edit input { width: 18px; height: 18px; }
  .hidden { display: none !important; }
  @media (max-width: 900px) {
    .line-row {
      grid-template-columns: 52px 1fr 1fr;
      grid-template-areas:
        "idx sku sku"
        "name name name"
        "price price commission"
        "remove remove remove";
    }
    .line-row .line-idx { grid-area: idx; }
    .line-row .line-sku { grid-area: sku; }
    .line-row .line-name { grid-area: name; }
    .line-row .line-price { grid-area: price; }
    .line-row .line-commission { grid-area: commission; }
    .line-row .line-remove { grid-area: remove; }
  }
</style>

<form method="post" action="lote-produtos.php" id="batchForm" class="batch-shell">
  <div class="batch-hero">
    <h1>Recepção de lote de produtos</h1>
    <div class="subtitle">Lance vários produtos de uma só vez, escolhendo a origem (consignação, compra/garimpo ou doação) com fornecedor único por lote.</div>
    <?php if (!empty($consignmentId) && $consignment): ?>
      <div class="alert" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
          Pré-lote #<?php echo (int) $consignmentId; ?>
          — <?php echo $esc((string) ($consignment['supplier_name'] ?? 'Sem fornecedor')); ?>
          <?php if ($consignmentTotals): ?>
            | Recebido: <?php echo number_format((int) ($consignmentTotals['received'] ?? 0), 0, ',', '.'); ?>
            | Devolvido: <?php echo number_format((int) ($consignmentTotals['returned'] ?? 0), 0, ',', '.'); ?>
            | Saldo: <?php echo number_format((int) ($consignmentTotals['remaining'] ?? 0), 0, ',', '.'); ?>
          <?php endif; ?>
        </div>
        <a class="btn ghost" href="consignacao-recebimento-cadastro.php?id=<?php echo (int) $consignmentId; ?>">Ver pré-lote</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>
  <?php if (!empty($warnings)): ?>
    <div class="alert error" style="font-size:16px;font-weight:700;padding:16px;">
      <?php echo $esc(implode(' ', $warnings)); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($lotSuccess)): ?>
    <div class="alert success"><?php echo $esc($lotSuccess); ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <input type="hidden" name="source" id="sourceHidden" value="<?php echo $esc($currentSource); ?>">
  <input type="hidden" name="sku_context_key" id="skuContextKey" value="<?php echo $esc($skuContextKey ?? ''); ?>">
  <input type="hidden" name="vendorId" id="vendorId" value="<?php echo $selectedVendor ? (int) $selectedVendor['id'] : ''; ?>">
  <input type="hidden" id="defaultCommission" value="<?php echo $commissionValue !== null ? $esc($commissionValue) : ''; ?>">
  <input type="hidden" id="defaultCost" name="defaultCost" value="<?php echo $defaultCostValue !== null ? $esc($defaultCostValue) : ''; ?>">
  <input type="hidden" id="closeLot" name="close_lot" value="0">
  <?php if (!empty($consignmentId)): ?>
    <input type="hidden" name="consignment_id" value="<?php echo (int) $consignmentId; ?>">
  <?php endif; ?>

  <div class="batch-grid">
    <div class="field">
      <label for="sourceSelect">Fonte do lote</label>
      <select id="sourceSelect" <?php echo !empty($consignmentId) ? 'disabled' : ''; ?>>
        <option value="consignacao" <?php echo $currentSource === 'consignacao' ? 'selected' : ''; ?>>Consignação</option>
        <option value="compra" <?php echo $currentSource === 'compra' ? 'selected' : ''; ?>>Compra/Garimpo</option>
        <option value="doacao" <?php echo $currentSource === 'doacao' ? 'selected' : ''; ?>>Doação</option>
      </select>
      <div class="help-text">
        <?php echo !empty($consignmentId) ? 'Pré-lote de consignação: fonte travada.' : 'Escolha a origem para aplicar regra financeira do lote.'; ?>
      </div>
    </div>
    <div class="field">
      <label for="vendorSelect">Fornecedor do lote (travado)</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <input
          id="vendorSelect"
          type="text"
          list="vendorOptions"
          required
          placeholder="Digite para buscar"
          value="<?php echo $selectedVendor ? (int) $selectedVendor['id'] : ''; ?>"
          <?php echo $selectedVendor ? 'data-locked="1"' : ''; ?>
          style="flex:1;"
        >
        <datalist id="vendorOptions">
          <?php foreach ($vendorOptions as $vendor): ?>
            <?php
              $commission = isset($vendor['commission_rate']) && $vendor['commission_rate'] !== null ? (float) $vendor['commission_rate'] : '';
            ?>
            <option
              value="<?php echo (int) $vendor['id']; ?>"
              data-commission="<?php echo $esc($commission); ?>"
              label="<?php echo $esc($vendor['full_name']); ?> — Pessoa <?php echo (int) $vendor['id']; ?>"
            >
              <?php echo $esc($vendor['full_name']); ?> — Pessoa <?php echo (int) $vendor['id']; ?>
            </option>
          <?php endforeach; ?>
        </datalist>
        <button type="button" class="ghost" id="unlockVendor" title="Trocar fornecedor">Trocar</button>
      </div>
      <div class="help-text">Um único fornecedor para todo o lote; travamos para evitar produtos com fornecedor diferente.</div>
    </div>
    <div class="field">
      <label for="lotSelect">Lote de produtos</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <select id="lotSelect" name="lot_id" style="flex:1;" <?php echo $selectedVendor ? '' : 'disabled'; ?>>
          <option value="">Selecione um lote</option>
          <?php foreach ($lotOptions as $lot): ?>
            <?php
              $lotId = (int) ($lot['id'] ?? 0);
              $lotSupplier = (int) ($lot['supplier_pessoa_id'] ?? 0);
              $lotStatus = (string) ($lot['status'] ?? '');
              $isSelected = $selectedLotId && $lotId === (int) $selectedLotId;
            ?>
            <option
              value="<?php echo $lotId; ?>"
              data-supplier="<?php echo $lotSupplier; ?>"
              data-opened="<?php echo $esc((string) ($lot['opened_at'] ?? '')); ?>"
              data-status="<?php echo $esc($lotStatus); ?>"
              <?php echo $isSelected ? 'selected' : ''; ?>
            >
              <?php echo $esc($lot['name'] ?? ('Lote #' . $lotId)); ?> — Fornecedor pessoa <?php echo $lotSupplier; ?> <?php echo $lotStatus !== '' ? ('(' . $esc($lotStatus) . ')') : ''; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="ghost" id="openLotBtn" name="lot_action" value="create_lot" <?php echo $selectedVendor ? '' : 'disabled'; ?> title="Abrir novo lote para o fornecedor">+ Abrir lote</button>
        <button type="submit" class="ghost" id="reopenLotBtn" name="lot_action" value="reopen_lot" disabled title="Reabrir lote selecionado">Reabrir lote</button>
      </div>
      <div class="help-text" id="lotHint">
        <?php if ($selectedLot): ?>
          <?php
            $selectedLabel = $selectedLot['name'] ?? ('Lote #' . (int) $selectedLot['id']);
            $selectedStatus = $selectedLot['status'] ?? '';
            $selectedHint = 'Lote selecionado: ' . $selectedLabel;
            if ($selectedStatus !== '') {
                $selectedHint .= ' (' . $selectedStatus . ')';
            }
          ?>
          <?php echo $esc($selectedHint); ?>
        <?php else: ?>
          <?php echo $selectedVendor ? 'Selecione o último lote aberto ou crie um novo.' : 'Escolha o fornecedor para carregar os lotes abertos.'; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="field">
      <label id="financialLabel">% Consignação padrão (fornecedor)</label>
      <div id="commissionWrap" class="lock-pill" data-default-commission><?php echo fmtPercentBadge($commissionValue); ?></div>
      <div id="costWrap" class="field hidden" style="margin:8px 0 0;">
  <input type="text" inputmode="decimal" data-number-br id="defaultCostInput" placeholder="Custo padrão (R$)" step="0.01" min="0" value="<?php echo $defaultCostValue !== null ? $esc($defaultCostValue) : ''; ?>">
      </div>
      <div id="donationNote" class="help-text hidden">Doação: custo fixo R$0,00 e sem consignação.</div>
      <div class="help-text" id="financialHint">Puxado do cadastro do fornecedor e replicado para cada linha.</div>
      <label class="toggle-edit">
        <input type="checkbox" id="toggleFinancialEdit">
        <span id="toggleFinancialText">Editar % consignação por produto</span>
      </label>
    </div>
  </div>

  <div class="section" style="margin-top:4px;">
    <div class="lines-head">
      <div>
        <h2 style="margin:0;font-size:16px;">Produtos do lote</h2>
        <div class="help-text">Digite nome e preço, um produto por linha. É possível remover ou adicionar rapidamente.</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="ghost" id="addLine">+ Adicionar linha</button>
        <button type="submit" class="primary">Salvar lote</button>
      </div>
    </div>

    <div class="lines-stack" id="linesStack">
      <?php foreach ($rows as $index => $row): ?>
        <?php
          $rowCommission = isset($row['percentualConsignacao']) && $row['percentualConsignacao'] !== ''
            ? \App\Support\Input::parseNumber($row['percentualConsignacao'])
            : $commissionValue;
        ?>
        <div class="line-row" data-index="<?php echo (int) $index; ?>">
          <div class="line-idx muted">#<?php echo $index + 1; ?></div>
          <div class="line-sku">
            <?php
              $reservedSku = trim((string) ($row['reservedSku'] ?? ''));
              $reservationId = (int) ($row['skuReservationId'] ?? 0);
              $skuLabel = $reservedSku !== '' ? ('SKU ' . $reservedSku) : 'SKU —';
            ?>
            <span class="chip sku-chip"><?php echo $esc($skuLabel); ?></span>
            <input type="hidden" name="items[<?php echo (int) $index; ?>][skuReservationId]" value="<?php echo $reservationId ?: ''; ?>">
            <input type="hidden" name="items[<?php echo (int) $index; ?>][reservedSku]" value="<?php echo $esc($reservedSku); ?>">
          </div>
          <div class="line-name">
            <input type="text" name="items[<?php echo (int) $index; ?>][name]" placeholder="Nome do produto" maxlength="120" value="<?php echo $esc($row['name'] ?? ''); ?>">
          </div>
        <div class="line-price">
          <input type="text" inputmode="decimal" data-number-br name="items[<?php echo (int) $index; ?>][price]" placeholder="Preço de venda (R$)" step="0.01" min="0" value="<?php echo $esc($row['price'] ?? ''); ?>">
        </div>
        <div class="line-commission" style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;">
          <span class="chip commission-chip"><?php echo fmtPercentBadge($rowCommission); ?> consignação fornecedor</span>
          <input type="text" inputmode="decimal" data-number-br class="commission-editor" step="0.01" min="0" max="100" value="<?php echo $esc($rowCommission !== null ? $rowCommission : ''); ?>" disabled>
          <input type="hidden" class="commission-input" name="items[<?php echo (int) $index; ?>][percentualConsignacao]" value="<?php echo $esc($rowCommission !== null ? $rowCommission : ''); ?>">
          <span class="chip cost-chip hidden"></span>
          <input type="text" inputmode="decimal" data-number-br class="cost-editor hidden" step="0.01" min="0" value="<?php echo $esc($row['cost'] ?? ''); ?>">
          <input type="hidden" class="cost-input" name="items[<?php echo (int) $index; ?>][cost]" value="<?php echo $esc($row['cost'] ?? ''); ?>">
        </div>
        <div class="line-remove">
          <button type="button" class="ghost remove-line">Remover</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($createdItems)): ?>
    <div class="section" style="margin-top:6px;">
      <h3 style="margin:0 0 6px;">Produtos inseridos</h3>
      <ul style="margin:0;padding-left:18px;color:var(--muted);font-weight:600;">
        <?php foreach ($createdItems as $item): ?>
          <li>
            <?php echo $esc($item['sku']); ?> — <?php echo $esc($item['name']); ?>
            (R$ <?php echo number_format((float) $item['price'], 2, ',', '.'); ?>)
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</form>

<script>
  (function() {
    const vendorSelect = document.getElementById('vendorSelect');
    const vendorList = document.getElementById('vendorOptions');
    const vendorHidden = document.getElementById('vendorId');
    const defaultCommissionInput = document.getElementById('defaultCommission');
    const defaultCostInput = document.getElementById('defaultCost');
    const commissionBadge = document.querySelector('[data-default-commission]');
    const lotSelect = document.getElementById('lotSelect');
    const openLotBtn = document.getElementById('openLotBtn');
    const reopenLotBtn = document.getElementById('reopenLotBtn');
    const lotHint = document.getElementById('lotHint');
    const lotData = <?php echo json_encode(array_values($lotOptions), JSON_UNESCAPED_UNICODE); ?>;
    const selectedLotId = <?php echo json_encode($selectedLotId); ?>;
    const batchForm = document.getElementById('batchForm');
    const closeLotInput = document.getElementById('closeLot');
    const linesStack = document.getElementById('linesStack');
    const addLineBtn = document.getElementById('addLine');
    const unlockBtn = document.getElementById('unlockVendor');
    const toggleFinancialEdit = document.getElementById('toggleFinancialEdit');
    const toggleFinancialText = document.getElementById('toggleFinancialText');
    const sourceSelect = document.getElementById('sourceSelect');
    const sourceHidden = document.getElementById('sourceHidden');
    const financialLabel = document.getElementById('financialLabel');
    const financialHint = document.getElementById('financialHint');
    const commissionWrap = document.getElementById('commissionWrap');
    const costWrap = document.getElementById('costWrap');
    const donationNote = document.getElementById('donationNote');
    const defaultCostField = document.getElementById('defaultCostInput');
    const skuContextKeyInput = document.getElementById('skuContextKey');
    const reserveEndpoint = batchForm ? (batchForm.getAttribute('action') || window.location.pathname) : window.location.pathname;

    if (defaultCostField) {
      defaultCostField.addEventListener('input', () => {
        defaultCostInput.value = defaultCostField.value;
        if (sourceSelect.value === 'compra') {
          applyFinancialToRows('compra', { force: !toggleFinancialEdit.checked });
        }
      });
    }

    const numberUtils = window.RetratoNumber || {};
    const parseNumber = (value) => {
      if (typeof numberUtils.parse === 'function') {
        const parsed = numberUtils.parse(value);
        return Number.isFinite(parsed) ? parsed : null;
      }
      const parsed = parseFloat(String(value ?? '').replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : null;
    };
    const formatNumber = (value, decimals = 2) => {
      if (!Number.isFinite(value)) return '';
      if (typeof numberUtils.format === 'function') {
        return numberUtils.format(value, decimals);
      }
      return value.toFixed(decimals).replace('.', ',');
    };
    const formatRawNumber = (value, decimals = 2) => {
      const parsed = parseNumber(value);
      if (!Number.isFinite(parsed)) return value === undefined || value === null ? '' : String(value);
      return formatNumber(parsed, decimals);
    };

    function formatPercent(value) {
      if (!value && value !== 0) return '—';
      const numeric = parseNumber(value);
      if (!Number.isFinite(numeric)) return '—';
      return formatNumber(numeric, 2) + '%';
    }

    function formatMoney(value) {
      if (value === null || value === undefined || value === '') return 'R$ 0,00';
      const numeric = parseNumber(value);
      if (!Number.isFinite(numeric)) return 'R$ 0,00';
      return 'R$ ' + formatNumber(numeric, 2);
    }

    function describeLot(lot) {
      if (!lot) return '';
      const name = lot.name || `Lote #${lot.id || ''}`;
      const status = lot.status ? ` (${lot.status})` : '';
      const opened = lot.opened_at ? new Date(lot.opened_at) : null;
      const openedLabel = opened && !isNaN(opened.getTime())
        ? opened.toLocaleString('pt-BR')
        : (lot.opened_at || '');
      return `${name}${status}${openedLabel ? ` — ${openedLabel}` : ''}`;
    }

    function updateReopenState(lot) {
      if (!reopenLotBtn) return;
      const isClosed = lot && lot.status === 'fechado';
      reopenLotBtn.disabled = !isClosed;
    }

    function updateLotOptionsForVendor(vendorId) {
      if (!lotSelect) return;
      const vendorKey = String(vendorId || '');
      const currentValue = lotSelect.value;
      lotSelect.innerHTML = '<option value=\"\">Selecione um lote</option>';
      const available = (lotData || []).filter((lot) => String(lot.supplier_pessoa_id) === vendorKey);
      available.sort((a, b) => {
        const aDate = new Date(a.opened_at || '').getTime();
        const bDate = new Date(b.opened_at || '').getTime();
        return (bDate || 0) - (aDate || 0);
      });
      available.forEach((lot) => {
        const opt = document.createElement('option');
        opt.value = lot.id;
        opt.dataset.supplier = lot.supplier_pessoa_id;
        opt.dataset.opened = lot.opened_at || '';
        opt.dataset.status = lot.status || '';
        opt.textContent = describeLot(lot);
        lotSelect.appendChild(opt);
      });

      const findLot = (id) => available.find((lot) => String(lot.id) === String(id));
      let nextValue = '';
      if (selectedLotId && findLot(selectedLotId)) {
        nextValue = String(selectedLotId);
      } else if (currentValue && findLot(currentValue)) {
        nextValue = currentValue;
      } else if (available[0]) {
        nextValue = String(available[0].id);
      }

      lotSelect.value = nextValue;
      if (lotHint) {
        const chosen = findLot(nextValue);
        if (nextValue && chosen) {
          lotHint.textContent = `Lote selecionado: ${describeLot(chosen)}`;
        } else {
          lotHint.textContent = vendorKey ? 'Selecione o último lote aberto ou crie um novo.' : 'Escolha o fornecedor para carregar os lotes abertos.';
        }
      }
      updateReopenState(findLot(nextValue));

      lotSelect.disabled = vendorKey === '';
      if (openLotBtn) {
        openLotBtn.disabled = vendorKey === '';
      }
    }

    function applyFinancialToRows(mode, { force = false } = {}) {
      document.querySelectorAll('.line-row').forEach((row) => {
        const commissionInput = row.querySelector('.commission-input');
        const commissionEditor = row.querySelector('.commission-editor');
        const commissionChip = row.querySelector('.commission-chip');
        const costInput = row.querySelector('.cost-input');
        const costEditor = row.querySelector('.cost-editor');
        const costChip = row.querySelector('.cost-chip');

        if (mode === 'consignacao') {
          const commission = defaultCommissionInput.value || '';
          const commissionDisplay = formatRawNumber(commission, 2);
          if (force || !toggleFinancialEdit.checked || (commissionEditor && !commissionEditor.value)) {
            commissionInput.value = commission;
            if (commissionEditor) commissionEditor.value = commissionDisplay;
          }
          if (commissionChip) commissionChip.textContent = formatPercent(commission) + ' consignação fornecedor';
          if (commissionEditor) commissionEditor.disabled = !toggleFinancialEdit.checked;
          if (costEditor) costEditor.classList.add('hidden');
          if (costChip) costChip.classList.add('hidden');
          if (commissionEditor) commissionEditor.classList.remove('hidden');
          if (commissionChip) commissionChip.classList.remove('hidden');
          if (costInput) costInput.value = '';
        } else if (mode === 'compra') {
          const cost = defaultCostField.value || '';
          const costDisplay = formatRawNumber(cost, 2);
          if (force || !toggleFinancialEdit.checked || (costEditor && !costEditor.value)) {
            costInput.value = cost;
            if (costEditor) costEditor.value = costDisplay;
          }
          if (costChip) costChip.textContent = formatMoney(cost) + ' custo';
          if (costEditor) {
            costEditor.disabled = !toggleFinancialEdit.checked;
            costEditor.classList.remove('hidden');
          }
          if (costChip) costChip.classList.remove('hidden');
          if (commissionEditor) {
            commissionEditor.disabled = true;
            commissionEditor.classList.add('hidden');
          }
          if (commissionChip) commissionChip.classList.add('hidden');
          if (commissionInput) commissionInput.value = '';
        } else { // doacao
          const costValue = '0';
          if (costInput) costInput.value = costValue;
          if (costEditor) {
            costEditor.value = formatNumber(0, 2);
            costEditor.disabled = true;
            costEditor.classList.add('hidden');
          }
          if (costChip) {
            costChip.textContent = 'Doação — custo R$0,00';
            costChip.classList.remove('hidden');
          }
          if (commissionEditor) {
            commissionEditor.disabled = true;
            commissionEditor.classList.add('hidden');
          }
          if (commissionChip) commissionChip.classList.add('hidden');
          if (commissionInput) commissionInput.value = '';
        }
      });
    }

    async function reserveSku() {
      if (!skuContextKeyInput || !skuContextKeyInput.value) {
        return null;
      }
      const payload = new FormData();
      payload.append('action', 'reserve_sku');
      payload.append('sku_context_key', skuContextKeyInput.value);
      try {
        const response = await fetch(reserveEndpoint, {
          method: 'POST',
          body: payload,
          credentials: 'same-origin',
        });
        if (!response.ok) {
          return null;
        }
        const data = await response.json();
        if (data && data.ok && data.reservation) {
          return {
            id: data.reservation.id || '',
            sku: data.reservation.sku || '',
          };
        }
      } catch (e) {
        // Sem bloqueio; fallback para SKU automático no backend.
      }
      return null;
    }

    function createLine(index, reservation) {
      const commission = defaultCommissionInput.value || '';
      const cost = defaultCostField.value || '';
      const reservationId = reservation && reservation.id ? reservation.id : '';
      const reservedSku = reservation && reservation.sku ? reservation.sku : '';
      const skuLabel = reservedSku ? `SKU ${reservedSku}` : 'SKU —';
      const wrapper = document.createElement('div');
      wrapper.className = 'line-row';
      wrapper.dataset.index = index;
      wrapper.innerHTML = `
        <div class="line-idx muted">#${index + 1}</div>
        <div class="line-sku">
          <span class="chip sku-chip">${skuLabel}</span>
          <input type="hidden" name="items[${index}][skuReservationId]" value="${reservationId}">
          <input type="hidden" name="items[${index}][reservedSku]" value="${reservedSku}">
        </div>
        <div class="line-name">
          <input type="text" name="items[${index}][name]" placeholder="Nome do produto" maxlength="120">
        </div>
        <div class="line-price">
          <input type="text" inputmode="decimal" data-number-br name="items[${index}][price]" placeholder="Preço de venda (R$)" step="0.01" min="0">
        </div>
        <div class="line-commission" style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;">
          <span class="chip commission-chip">${formatPercent(commission)} consignação fornecedor</span>
          <input type="text" inputmode="decimal" data-number-br class="commission-editor" step="0.01" min="0" max="100" value="${commission}">
          <input type="hidden" class="commission-input" name="items[${index}][percentualConsignacao]" value="${commission}">
          <span class="chip cost-chip hidden">${formatMoney(cost)} custo</span>
          <input type="text" inputmode="decimal" data-number-br class="cost-editor hidden" step="0.01" min="0" value="${cost}">
          <input type="hidden" class="cost-input" name="items[${index}][cost]" value="${cost}">
        </div>
        <div class="line-remove">
          <button type="button" class="ghost remove-line">Remover</button>
        </div>
      `;
      if (window.RetratoNumber && typeof window.RetratoNumber.formatInput === 'function') {
        wrapper.querySelectorAll('[data-number-br]').forEach((input) => window.RetratoNumber.formatInput(input));
      }
      return wrapper;
    }

    function renumberLines() {
      const rows = Array.from(linesStack.querySelectorAll('.line-row'));
      rows.forEach((row, idx) => {
        row.dataset.index = idx;
        row.querySelector('.line-idx').textContent = `#${idx + 1}`;
        row.querySelectorAll('input').forEach((input) => {
          const name = input.getAttribute('name');
          if (!name) return;
          const newName = name.replace(/items\[\d+\]/, `items[${idx}]`);
          input.setAttribute('name', newName);
        });
      });
    }

    linesStack.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove-line')) {
        const row = e.target.closest('.line-row');
        if (row) {
          row.remove();
          renumberLines();
        }
      }
    });

    linesStack.addEventListener('input', (e) => {
      if (e.target.classList.contains('commission-editor')) {
        const row = e.target.closest('.line-row');
        if (!row) return;
        const hidden = row.querySelector('.commission-input');
        const chip = row.querySelector('.commission-chip');
        hidden.value = e.target.value;
        if (chip) {
          chip.textContent = formatPercent(e.target.value) + ' consignação fornecedor';
        }
      }
      if (e.target.classList.contains('cost-editor')) {
        const row = e.target.closest('.line-row');
        if (!row) return;
        const hidden = row.querySelector('.cost-input');
        const chip = row.querySelector('.cost-chip');
        hidden.value = e.target.value;
        if (chip) {
          chip.textContent = formatMoney(e.target.value) + ' custo';
        }
      }
    });

    addLineBtn.addEventListener('click', async () => {
      const nextIndex = linesStack.querySelectorAll('.line-row').length;
      addLineBtn.disabled = true;
      const reservation = await reserveSku();
      linesStack.appendChild(createLine(nextIndex, reservation));
      applyFinancialToRows(sourceSelect.value, { force: true });
      addLineBtn.disabled = false;
    });

    toggleFinancialEdit.addEventListener('change', () => {
      const editing = toggleFinancialEdit.checked;
      const rows = linesStack.querySelectorAll('.line-row');
      rows.forEach((row) => {
        const editor = row.querySelector('.commission-editor');
        const hidden = row.querySelector('.commission-input');
        const costEditor = row.querySelector('.cost-editor');
        const costHidden = row.querySelector('.cost-input');
        if (sourceSelect.value === 'consignacao') {
          if (editor && hidden) {
            if (editing) {
              editor.disabled = false;
              if (!editor.value) {
                editor.value = formatRawNumber(hidden.value || defaultCommissionInput.value || '', 2);
              }
            } else {
              const base = defaultCommissionInput.value || '';
              editor.value = formatRawNumber(base, 2);
              editor.disabled = true;
              hidden.value = base;
              const chip = row.querySelector('.commission-chip');
              if (chip) {
                chip.textContent = formatPercent(base) + ' consignação fornecedor';
              }
            }
          }
        } else if (sourceSelect.value === 'compra') {
          if (costEditor && costHidden) {
            if (editing) {
              costEditor.disabled = false;
              if (!costEditor.value) {
                costEditor.value = formatRawNumber(costHidden.value || defaultCostField.value || '', 2);
              }
            } else {
              const base = defaultCostField.value || '';
              costEditor.value = formatRawNumber(base, 2);
              costEditor.disabled = true;
              costHidden.value = base;
              const chip = row.querySelector('.cost-chip');
              if (chip) {
                chip.textContent = formatMoney(base) + ' custo';
              }
            }
          }
        }
      });
    });

    function updateFinancialUI() {
      const mode = sourceSelect.value;
      sourceHidden.value = mode;
      if (mode === 'consignacao') {
        financialLabel.textContent = '% Consignação padrão (fornecedor)';
        toggleFinancialText.textContent = 'Editar % consignação por produto';
        financialHint.textContent = 'Puxado do cadastro do fornecedor e replicado para cada linha.';
        commissionWrap.classList.remove('hidden');
        costWrap.classList.add('hidden');
        donationNote.classList.add('hidden');
        toggleFinancialEdit.disabled = false;
        applyFinancialToRows(mode, { force: true });
      } else if (mode === 'compra') {
        financialLabel.textContent = 'Custo padrão do lote (R$)';
        toggleFinancialText.textContent = 'Editar custo por produto';
        financialHint.textContent = 'Use custo padrão ou ajuste produto a produto.';
        commissionWrap.classList.add('hidden');
        costWrap.classList.remove('hidden');
        donationNote.classList.add('hidden');
        toggleFinancialEdit.disabled = false;
        applyFinancialToRows(mode, { force: true });
      } else {
        financialLabel.textContent = 'Doação';
        toggleFinancialText.textContent = 'Editar custo por produto';
        financialHint.textContent = 'Custo fixo R$0,00 e sem comissão.';
        commissionWrap.classList.add('hidden');
        costWrap.classList.add('hidden');
        donationNote.classList.remove('hidden');
        toggleFinancialEdit.checked = false;
        toggleFinancialEdit.disabled = true;
        applyFinancialToRows(mode, { force: true });
      }
    }

    function updateVendorData() {
      const option = vendorList
        ? Array.from(vendorList.options).find((opt) => opt.value === vendorSelect.value)
        : null;
      vendorHidden.value = vendorSelect.value;
      const commission = option ? option.getAttribute('data-commission') || '' : '';
      defaultCommissionInput.value = commission;
      commissionBadge.textContent = formatPercent(commission);
      applyFinancialToRows(sourceSelect.value, { force: !toggleFinancialEdit.checked });
      updateLotOptionsForVendor(vendorHidden.value);
    }

    vendorSelect.addEventListener('input', () => {
      updateVendorData();
    });

    vendorSelect.addEventListener('change', () => {
      updateVendorData();
      vendorSelect.dataset.locked = '1';
      vendorSelect.readOnly = true;
      unlockBtn.style.opacity = '1';
    });

    if (vendorSelect.dataset.locked === '1') {
      vendorSelect.readOnly = true;
      unlockBtn.style.opacity = '1';
    }

    unlockBtn.addEventListener('click', () => {
      vendorSelect.readOnly = false;
      vendorSelect.focus();
    });

    sourceSelect.addEventListener('change', () => {
      updateFinancialUI();
    });

    if (lotSelect) {
      lotSelect.addEventListener('change', () => {
        const lot = (lotData || []).find((row) => String(row.id) === String(lotSelect.value));
        updateReopenState(lot);
        if (lotHint) {
          lotHint.textContent = lot ? `Lote selecionado: ${describeLot(lot)}` : 'Selecione um lote.';
        }
      });
    }

    if (batchForm && closeLotInput) {
      batchForm.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        if (submitter && submitter.name === 'lot_action') {
          closeLotInput.value = '0';
          return;
        }
        const shouldKeepOpen = window.confirm('Deseja manter o lote aberto para inserir mais produtos?\\nOK: manter aberto\\nCancelar: encerrar lote após salvar.');
        closeLotInput.value = shouldKeepOpen ? '0' : '1';
      });
    }

    updateVendorData();
    updateFinancialUI();
    updateLotOptionsForVendor(vendorHidden.value || vendorSelect.value);
  })();
</script>
