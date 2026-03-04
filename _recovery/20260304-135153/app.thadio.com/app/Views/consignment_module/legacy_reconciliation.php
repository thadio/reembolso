<?php
/** @var array $legacyPayouts */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalMovements */
/** @var int $totalPages */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<?php
  $legacyPayouts = $legacyPayouts ?? [];
  $page = $page ?? 1;
  $perPage = $perPage ?? 50;
  $perPageOptions = $perPageOptions ?? [25, 50, 100, 200];
  $totalMovements = $totalMovements ?? count($legacyPayouts);
  $totalPages = $totalPages ?? 1;
  $success = $success ?? '';
  $rangeStart = $totalMovements > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalMovements > 0 ? min($totalMovements, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage): string {
    $query = ['page' => $targetPage, 'per_page' => $perPage];
    return 'consignacao-inconsistencias.php?action=legacy&' . http_build_query($query);
  };
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Reconciliação de Pagamentos Legados</h1>
    <div class="subtitle">Vincule movimentações de pagamento anteriores ao módulo de consignação.</div>
  </div>
  <a class="btn ghost" href="consignacao-inconsistencias.php">← Inconsistências</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>

<p style="font-size:13px;color:#6b7280;margin:16px 0;">
  Abaixo estão as movimentações de débito (pagamento a fornecedora) que não possuem <code>payout_id</code> vinculado.
  Para cada movimentação, é possível criar um pagamento retroativo vinculando às vendas correspondentes.
</p>

<div class="table-tools" style="margin:10px 0;">
  <span style="color:var(--muted);font-size:13px;">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalMovements; ?></span>
  <form method="get" id="legacyPerPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="hidden" name="action" value="legacy">
    <input type="hidden" name="page" value="1">
    <label for="legacyPerPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
    <select id="legacyPerPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($legacyPayouts)): ?>
  <div class="alert success">Todas as movimentações já estão vinculadas. Nada a reconciliar.</div>
<?php else: ?>
  <?php foreach ($legacyPayouts as $mov):
    $movId = (int)($mov['id'] ?? 0);
    $supplierName = $mov['supplier_name'] ?? '(sem nome)';
    $amount = (float)($mov['amount'] ?? 0);
    $pendingSales = $mov['pending_sales'] ?? [];
    $missingRequiredCount = 0;
    foreach ($pendingSales as $pendingSale) {
      $rowSku = trim((string) ($pendingSale['sku'] ?? ''));
      $rowProductName = trim((string) ($pendingSale['product_name'] ?? ''));
      if ($rowSku === '' || $rowProductName === '') {
        $missingRequiredCount++;
      }
    }
  ?>
    <div style="margin:16px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
        <div>
          <div style="font-weight:700;font-size:15px;">Movimentação #<?php echo $movId; ?></div>
          <div style="font-size:13px;color:#6b7280;margin-top:4px;">
            Fornecedora: <strong><?php echo $esc($supplierName); ?></strong>
            | Valor: <strong>R$ <?php echo number_format($amount, 2, ',', '.'); ?></strong>
            | Data: <?php echo !empty($mov['event_at']) ? date('d/m/Y', strtotime($mov['event_at'])) : (!empty($mov['created_at']) ? date('d/m/Y', strtotime($mov['created_at'])) : '-'); ?>
          </div>
          <?php if (!empty($mov['event_notes']) || !empty($mov['description'])): ?>
            <div style="font-size:12px;color:#9ca3af;margin-top:2px;"><?php echo $esc((string) ($mov['event_notes'] ?? $mov['description'] ?? '')); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($pendingSales)): ?>
        <div class="alert info" style="margin:12px 0 0;">Nenhuma venda elegível encontrada para esta fornecedora. A reconciliação manual pode ser necessária.</div>
      <?php else: ?>
        <?php if ($missingRequiredCount > 0): ?>
          <div class="alert warning" style="margin:12px 0 0;">
            Há <?php echo (int) $missingRequiredCount; ?> venda(s) com SKU/produto incompletos. Esses itens ficam bloqueados até correção dos dados.
          </div>
        <?php endif; ?>
        <form method="post" action="consignacao-inconsistencias.php?action=legacy_reconciliation_confirm" style="margin-top:12px;">
          <input type="hidden" name="movement_id" value="<?php echo $movId; ?>">
          <input type="hidden" name="supplier_pessoa_id" value="<?php echo (int)($mov['supplier_pessoa_id'] ?? 0); ?>">

          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
              <tr style="border-bottom:1px solid #e5e7eb;">
                <th style="width:30px;padding:6px;"><input type="checkbox" data-mov-amount="<?php echo $amount; ?>" onclick="toggleLegacy(this, <?php echo $movId; ?>)"></th>
                <th style="text-align:left;padding:6px;">SKU</th>
                <th style="text-align:left;padding:6px;">Produto</th>
                <th style="text-align:left;padding:6px;">Pedido</th>
                <th style="text-align:left;padding:6px;">Vendido em</th>
                <th style="text-align:left;padding:6px;">Tipo</th>
                <th style="text-align:right;padding:6px;">Comissão</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingSales as $sale):
                $sku = trim((string) ($sale['sku'] ?? ''));
                $productName = trim((string) ($sale['product_name'] ?? ''));
                $rowSelectable = ($sku !== '' && $productName !== '');
                $isPaidReturnException = (int) ($sale['is_paid_return_exception'] ?? 0) === 1;
              ?>
                <tr style="border-bottom:1px solid #f3f4f6;<?php echo !$rowSelectable ? 'background:#fff7ed;' : ($isPaidReturnException ? 'background:#eff6ff;' : ''); ?>">
                  <td style="padding:4px 6px;">
                    <input
                      type="checkbox"
                      name="sale_ids[]"
                      value="<?php echo (int)($sale['id'] ?? 0); ?>"
                      class="legacy-cb-<?php echo $movId; ?>"
                      data-amount="<?php echo (float)($sale['amount'] ?? 0); ?>"
                      <?php echo $rowSelectable ? '' : 'disabled'; ?>
                      <?php echo $rowSelectable ? '' : 'title="SKU e produto são obrigatórios para conciliação."'; ?>
                    >
                  </td>
                  <td style="padding:4px 6px;"><?php echo $esc($sku !== '' ? $sku : '—'); ?></td>
                  <td style="padding:4px 6px;">
                    <?php echo $esc($productName !== '' ? $productName : '—'); ?>
                      <?php if (!$rowSelectable): ?>
                        <div style="font-size:11px;color:#b45309;">Preenchimento obrigatório de SKU/produto.</div>
                      <?php endif; ?>
                      <?php if ($isPaidReturnException): ?>
                        <div style="font-size:11px;color:#1d4ed8;">Exceção pós-pagamento: venda revertida que deve ser vinculada ao payout legado.</div>
                      <?php endif; ?>
                    </td>
                  <td style="padding:4px 6px;">#<?php echo (int)($sale['order_id'] ?? 0); ?></td>
                  <td style="padding:4px 6px;"><?php echo !empty($sale['sold_at']) ? date('d/m/Y', strtotime($sale['sold_at'])) : '-'; ?></td>
                  <td style="padding:4px 6px;">
                    <?php if ($isPaidReturnException): ?>
                      <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:11px;font-weight:600;">Exceção pago+devolvido</span>
                    <?php else: ?>
                      <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:600;">Pendente</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:4px 6px;text-align:right;">R$ <?php echo number_format((float)($sale['amount'] ?? 0), 2, ',', '.'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <?php
                $totalPendingSalesAmount = 0;
                $selectableCount = 0;
                foreach ($pendingSales as $ps) {
                  $psSku = trim((string) ($ps['sku'] ?? ''));
                  $psName = trim((string) ($ps['product_name'] ?? ''));
                  $totalPendingSalesAmount += (float)($ps['amount'] ?? 0);
                  if ($psSku !== '' && $psName !== '') $selectableCount++;
                }
              ?>
              <tr style="border-top:2px solid #e5e7eb;font-weight:600;">
                <td colspan="6" style="padding:6px;text-align:right;font-size:12px;color:#6b7280;">
                  Total de <?php echo count($pendingSales); ?> vendas elegíveis (<?php echo $selectableCount; ?> selecionáveis):
                </td>
                <td style="padding:6px;text-align:right;font-size:13px;">R$ <?php echo number_format($totalPendingSalesAmount, 2, ',', '.'); ?></td>
              </tr>
            </tfoot>
          </table>

          <!-- Live selection summary bar -->
          <div id="legacy-summary-<?php echo $movId; ?>" style="margin-top:12px;padding:10px 14px;border-radius:6px;background:#f0f4ff;border:1px solid #c7d2fe;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:13px;transition:background .2s,border-color .2s;">
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
              <span>
                <strong id="legacy-count-<?php echo $movId; ?>">0</strong> venda(s) selecionada(s)
              </span>
              <span>
                Soma selecionada: <strong id="legacy-sum-<?php echo $movId; ?>" style="font-size:14px;">R$ 0,00</strong>
              </span>
              <span style="color:#6b7280;">
                Valor da movimentação: <strong>R$ <?php echo number_format($amount, 2, ',', '.'); ?></strong>
              </span>
            </div>
            <div id="legacy-diff-<?php echo $movId; ?>" style="font-weight:600;font-size:13px;"></div>
          </div>

          <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
            <button
              type="submit"
              class="btn primary"
              id="legacy-submit-<?php echo $movId; ?>"
              disabled
              style="opacity:0.5;cursor:not-allowed;"
              onclick="return legacyConfirmSubmit(<?php echo $movId; ?>, <?php echo $amount; ?>);"
            >
              Criar pagamento retroativo
            </button>
            <span id="legacy-hint-<?php echo $movId; ?>" style="font-size:12px;color:#9ca3af;">Selecione ao menos uma venda para prosseguir.</span>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <script>
  function formatBRL(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function updateLegacySummary(movId, movAmount) {
    var cbs = document.querySelectorAll('.legacy-cb-' + movId + ':checked');
    var allCbs = document.querySelectorAll('.legacy-cb-' + movId + ':not(:disabled)');
    var count = 0;
    var sum = 0;

    cbs.forEach(function(cb) {
      count++;
      sum += parseFloat(cb.getAttribute('data-amount') || '0');
    });

    var countEl = document.getElementById('legacy-count-' + movId);
    var sumEl = document.getElementById('legacy-sum-' + movId);

    // Highlight selected rows
    allCbs.forEach(function(cb) {
      var row = cb.closest('tr');
      if (row) {
        if (cb.checked) {
          row.style.background = '#f0fdf4';
        } else {
          row.style.background = '';
        }
      }
    });
    var diffEl = document.getElementById('legacy-diff-' + movId);
    var summaryEl = document.getElementById('legacy-summary-' + movId);
    var submitBtn = document.getElementById('legacy-submit-' + movId);
    var hintEl = document.getElementById('legacy-hint-' + movId);

    if (countEl) countEl.textContent = count;
    if (sumEl) sumEl.textContent = formatBRL(sum);

    var diff = sum - movAmount;
    var absDiff = Math.abs(diff);

    if (count === 0) {
      if (diffEl) diffEl.textContent = '';
      if (summaryEl) {
        summaryEl.style.background = '#f0f4ff';
        summaryEl.style.borderColor = '#c7d2fe';
      }
      if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.5'; submitBtn.style.cursor = 'not-allowed'; }
      if (hintEl) { hintEl.textContent = 'Selecione ao menos uma venda para prosseguir.'; hintEl.style.color = '#9ca3af'; }
    } else if (absDiff < 0.01) {
      if (diffEl) { diffEl.textContent = '✓ Valores coincidem'; diffEl.style.color = '#059669'; }
      if (summaryEl) { summaryEl.style.background = '#ecfdf5'; summaryEl.style.borderColor = '#6ee7b7'; }
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; submitBtn.style.cursor = 'pointer'; }
      if (hintEl) { hintEl.textContent = ''; hintEl.style.color = '#059669'; }
    } else if (diff > 0) {
      if (diffEl) { diffEl.textContent = '⚠ Soma excede em ' + formatBRL(absDiff); diffEl.style.color = '#d97706'; }
      if (summaryEl) { summaryEl.style.background = '#fffbeb'; summaryEl.style.borderColor = '#fcd34d'; }
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; submitBtn.style.cursor = 'pointer'; }
      if (hintEl) { hintEl.textContent = 'Soma selecionada diferente do valor da movimentação.'; hintEl.style.color = '#d97706'; }
    } else {
      if (diffEl) { diffEl.textContent = 'Faltam ' + formatBRL(absDiff) + ' para atingir o valor'; diffEl.style.color = '#2563eb'; }
      if (summaryEl) { summaryEl.style.background = '#eff6ff'; summaryEl.style.borderColor = '#93c5fd'; }
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; submitBtn.style.cursor = 'pointer'; }
      if (hintEl) { hintEl.textContent = 'Soma selecionada diferente do valor da movimentação.'; hintEl.style.color = '#2563eb'; }
    }

    // Update master checkbox state
    var master = document.querySelector('[onclick*="toggleLegacy"][onclick*="' + movId + '"]');
    if (master && allCbs.length > 0) {
      master.checked = (cbs.length === allCbs.length);
      master.indeterminate = (cbs.length > 0 && cbs.length < allCbs.length);
    }
  }

  function toggleLegacy(master, movId) {
    var cbs = document.querySelectorAll('.legacy-cb-' + movId);
    cbs.forEach(function(cb) {
      if (!cb.disabled) {
        cb.checked = master.checked;
      }
    });
    var movAmount = parseFloat(master.getAttribute('data-mov-amount') || '0');
    updateLegacySummary(movId, movAmount);
  }

  function legacyConfirmSubmit(movId, movAmount) {
    var cbs = document.querySelectorAll('.legacy-cb-' + movId + ':checked');
    var count = 0;
    var sum = 0;
    cbs.forEach(function(cb) {
      count++;
      sum += parseFloat(cb.getAttribute('data-amount') || '0');
    });
    if (count === 0) return false;

    var diff = sum - movAmount;
    var msg = 'Criar pagamento retroativo?\n\n'
      + 'Vendas selecionadas: ' + count + '\n'
      + 'Soma selecionada: ' + formatBRL(sum) + '\n'
      + 'Valor da movimentação: ' + formatBRL(movAmount) + '\n';

    if (Math.abs(diff) >= 0.01) {
      msg += '\n⚠ ATENÇÃO: Os valores NÃO coincidem (diferença de ' + formatBRL(Math.abs(diff)) + ').\n';
    } else {
      msg += '\n✓ Os valores coincidem.\n';
    }

    msg += '\nDeseja continuar?';
    return confirm(msg);
  }

  // Attach listeners to all checkboxes on page load
  (function () {
    <?php foreach ($legacyPayouts as $mov2):
      $movId2 = (int)($mov2['id'] ?? 0);
      $amount2 = (float)($mov2['amount'] ?? 0);
      if (!empty($mov2['pending_sales'])):
    ?>
    (function (movId, movAmount) {
      var cbs = document.querySelectorAll('.legacy-cb-' + movId);
      cbs.forEach(function(cb) {
        cb.addEventListener('change', function() {
          updateLegacySummary(movId, movAmount);
        });
      });
      updateLegacySummary(movId, movAmount);
    })(<?php echo $movId2; ?>, <?php echo $amount2; ?>);
    <?php endif; endforeach; ?>
  })();
  </script>

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
  (function () {
    var perPage = document.getElementById('legacyPerPage');
    var form = document.getElementById('legacyPerPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', function () { form.submit(); });
    }
  })();
  </script>
<?php endif; ?>
