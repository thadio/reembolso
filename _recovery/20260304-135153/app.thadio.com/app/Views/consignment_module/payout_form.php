<?php
/** @var array|null $payout */
/** @var array $existingItems */
/** @var array $pendingSales */
/** @var array $pendingSupplierCandidates */
/** @var int $selectedSupplier */
/** @var array $suppliers */
/** @var object|null $supplierPerson */
/** @var array $activeBankAccounts */
/** @var string $formRoute */
/** @var bool $isDedicatedSupplierPage */
/** @var bool $isEditingConfirmed */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */

$isEditing = !empty($payout);
$isEditingConfirmed = !empty($isEditingConfirmed);
$formRoute = isset($formRoute) && is_string($formRoute) && trim($formRoute) !== ''
    ? trim($formRoute)
    : 'consignacao-pagamento-cadastro.php';
$isDedicatedSupplierPage = !empty($isDedicatedSupplierPage);
$supplierSelectionRoute = 'consignacao-pagamento-cadastro.php';
$supplierDetailRoute = 'consignacao-pagamento-fornecedor.php';
$payoutId = $isEditing ? (int) $payout['id'] : 0;
$soldFromValue = (string) ($_GET['sold_from'] ?? $_POST['sold_from'] ?? '');
$soldToValue = (string) ($_GET['sold_to'] ?? $_POST['sold_to'] ?? '');
$postedSaleIds = array_values(array_unique(array_filter(
    array_map('intval', (array) ($_POST['sale_ids'] ?? [])),
    static fn (int $id): bool => $id > 0
)));

// Source of truth for PIX key: pessoa cadastro.
$supplierPixKey = '';
if ($supplierPerson && isset($supplierPerson->pixKey)) {
    $supplierPixKey = (string) $supplierPerson->pixKey;
} elseif ($supplierPerson && isset($supplierPerson->pix_key)) {
    $supplierPixKey = (string) $supplierPerson->pix_key;
}
$supplierPixKey = trim($supplierPixKey);
$hasSupplierPixKey = $supplierPixKey !== '';

$payoutDateValue = (string) ($_POST['payout_date'] ?? ($payout['payout_date'] ?? date('Y-m-d')));
$methodValue = (string) ($_POST['method'] ?? ($payout['method'] ?? 'pix'));
$originBankAccountValue = (int) ($_POST['origin_bank_account_id'] ?? ($payout['origin_bank_account_id'] ?? 0));
$pixKeyValue = (string) ($_POST['pix_key'] ?? ($hasSupplierPixKey ? $supplierPixKey : ($payout['pix_key'] ?? '')));
$referenceValue = (string) ($_POST['reference'] ?? ($payout['reference'] ?? ''));
$notesValue = (string) ($_POST['notes'] ?? ($payout['notes'] ?? ''));
$previewQuery = ['action' => 'preview'];
if ($payoutId > 0) {
    $previewQuery['id'] = $payoutId;
}
if ($isEditingConfirmed) {
    $previewQuery['edit_confirmed'] = '1';
}
$previewActionUrl = 'consignacao-pagamento-cadastro.php?' . http_build_query($previewQuery);
$batchExportActionUrl = 'consignacao-pagamento-cadastro.php?action=preview_batch_export';
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1><?php
      if ($isEditingConfirmed) {
          echo 'Editar PIX Confirmado #' . $payoutId;
      } elseif ($isEditing) {
          echo 'Editar Pagamento #' . $payoutId;
      } else {
          echo $isDedicatedSupplierPage ? 'Novo Pagamento da Fornecedora' : 'Novo Pagamento de Consignação';
      }
    ?></h1>
    <div class="subtitle">
      <?php if ($isEditingConfirmed): ?>
        Reprocessamento de PIX: ajuste SKUs vinculados e mantenha o payout consistente no sistema.
      <?php else: ?>
        Selecione fornecedora, itens e confirme o pagamento.
      <?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;">
    <?php if ($isDedicatedSupplierPage): ?>
      <a class="btn ghost" href="<?php echo $esc($supplierSelectionRoute); ?>">← Selecionar fornecedora</a>
    <?php endif; ?>
    <a class="btn ghost" href="consignacao-pagamento-list.php">← Lista de Pagamentos</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>
<?php if ($isEditingConfirmed): ?>
  <div class="alert warning" style="margin-top:10px;">
    Você está editando um PIX já confirmado. Ao salvar, o sistema irá estornar e reaplicar vínculos, saldos e status automaticamente.
  </div>
<?php endif; ?>

<?php if (!$isDedicatedSupplierPage): ?>
  <div style="margin:20px 0 16px;">
    <h3 style="margin:0 0 6px;">Fornecedoras com comissão pendente</h3>
    <div class="subtitle">Vendas consignadas de pedidos já pagos que ainda não foram liquidadas. Clique na linha para carregar os itens.</div>
  </div>

  <?php if (empty($pendingSupplierCandidates)): ?>
    <div class="alert info">Nenhuma fornecedora com comissão pendente para o período informado.</div>
  <?php else: ?>
    <form method="post" action="<?php echo $esc($batchExportActionUrl); ?>" id="batchPreviewExportForm">
      <input type="hidden" name="sold_from" value="<?php echo $esc($soldFromValue); ?>">
      <input type="hidden" name="sold_to" value="<?php echo $esc($soldToValue); ?>">

      <div style="margin-bottom:10px;padding:12px;border:1px solid #d1d5db;border-radius:10px;background:#f8fafc;">
        <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
          <div>
            <label>Lote</label>
            <select name="batch_scope" id="batchScope" onchange="toggleBatchSupplierSelectionState()">
              <option value="all">Todas as fornecedoras pendentes</option>
              <option value="selected">Somente fornecedoras marcadas</option>
            </select>
          </div>
          <div>
            <label>Data prevista</label>
            <input type="date" name="batch_payout_date" value="<?php echo $esc(date('Y-m-d')); ?>">
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="btn ghost" onclick="setAllBatchSuppliers(true)">Marcar todas</button>
            <button type="button" class="btn ghost" onclick="setAllBatchSuppliers(false)">Limpar seleção</button>
            <button type="submit" class="btn primary">Exportar espelhos em lote (ZIP)</button>
          </div>
        </div>
        <details style="margin-top:8px;">
          <summary style="cursor:pointer;font-size:13px;color:var(--muted);">Combo de fornecedoras com checkmarks</summary>
          <div style="margin-top:8px;max-height:180px;overflow:auto;padding-right:4px;">
            <?php foreach ($pendingSupplierCandidates as $candidate): ?>
              <?php $comboSupplierId = (int) ($candidate['supplier_pessoa_id'] ?? 0); ?>
              <?php if ($comboSupplierId <= 0) continue; ?>
              <label style="display:flex;align-items:center;gap:8px;padding:4px 0;">
                <input
                  type="checkbox"
                  class="batch-supplier-cb"
                  value="<?php echo $comboSupplierId; ?>"
                  data-supplier-id="<?php echo $comboSupplierId; ?>"
                  onchange="syncBatchSupplierCheckboxes(this)">
                <span>
                  <?php echo $esc($candidate['supplier_name'] ?? ('Fornecedor #' . $comboSupplierId)); ?>
                  · <?php echo (int) ($candidate['pending_count'] ?? 0); ?> item(ns)
                  · R$ <?php echo number_format((float) ($candidate['pending_commission_amount'] ?? 0), 2, ',', '.'); ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
        <div class="subtitle" style="margin-top:8px;">
          O ZIP inclui 1 espelho por fornecedora usando todos os itens pendentes do período filtrado.
          No modo "Todas", a seleção por checkmark é ignorada.
        </div>
      </div>
      <div class="table-scroll" data-table-scroll style="margin-bottom:16px;">
        <div class="table-scroll-top" aria-hidden="true">
          <div class="table-scroll-top-inner"></div>
        </div>
        <div class="table-scroll-body">
          <table data-table="interactive">
            <thead>
              <tr>
                <th style="width:44px;text-align:center;" title="Marcar fornecedora para exportação em lote no modo selecionado.">
                  <input type="checkbox" onclick="setAllBatchSuppliers(this.checked)">
                </th>
                <th title="Pessoa fornecedora que tem vendas consignadas pendentes de pagamento da comissão." style="cursor:help;">Fornecedora</th>
                <th title="Situação atual dos itens em estoque da fornecedora: quantidade, receita potencial e comissão potencial." style="cursor:help;">Em estoque</th>
                <th title="Vendas com pagamento da comissão ainda pendente: quantidade, receita potencial e comissão potencial." style="cursor:help;">Vendido (pendente)</th>
                <th title="Vendas já pagas para a fornecedora: quantidade, receita efetiva e comissão já paga." style="cursor:help;">Vendido (pago)</th>
                <th title="Itens devolvidos para a fornecedora: quantidade, receita potencial e comissão potencial." style="cursor:help;">Devolvido</th>
                <th title="Itens doados: quantidade, receita potencial e comissão potencial." style="cursor:help;">Doado</th>
                <th title="Quantidade de pedidos já pagos que possuem ao menos um item consignado ainda pendente de comissão." style="cursor:help;">Pedidos pagos</th>
                <th title="Quantidade total de itens de venda pendentes (linhas em consignment_sales). Um pedido pode ter vários itens." style="cursor:help;">Itens</th>
                <th title="Quantidade de produtos distintos pendentes. Difere de Itens: um mesmo produto pode aparecer em mais de um item/venda." style="cursor:help;">Produtos</th>
                <th title="Soma da comissão a pagar para a fornecedora nas vendas pendentes." style="cursor:help;">Comissão pendente</th>
                <th title="Soma da receita líquida que serviu de base para calcular as comissões pendentes." style="cursor:help;">Receita base</th>
                <th title="Data mais antiga entre as vendas pendentes da fornecedora." style="cursor:help;">1ª venda pendente</th>
                <th title="Data mais recente entre as vendas pendentes da fornecedora." style="cursor:help;">Última venda pendente</th>
                <th title="Tempo decorrido desde a venda pendente mais antiga desta fornecedora." style="cursor:help;">Aguardando</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingSupplierCandidates as $candidate): ?>
                <?php
                  $supplierId = (int) ($candidate['supplier_pessoa_id'] ?? 0);
                  if ($supplierId <= 0) {
                      continue;
                  }

                  $query = ['supplier_pessoa_id' => $supplierId];
                  if ($payoutId > 0) {
                      $query['id'] = $payoutId;
                  }
                  if ($soldFromValue !== '') {
                      $query['sold_from'] = $soldFromValue;
                  }
                  if ($soldToValue !== '') {
                      $query['sold_to'] = $soldToValue;
                  }
                  $rowHref = $supplierDetailRoute . '?' . http_build_query($query);

                  $oldestSoldAt = (string) ($candidate['oldest_sold_at'] ?? '');
                  $latestSoldAt = (string) ($candidate['latest_sold_at'] ?? '');
                  $waitingLabel = '-';
                  if ($oldestSoldAt !== '') {
                      $oldestTs = strtotime($oldestSoldAt);
                      if ($oldestTs !== false) {
                          $daysWaiting = max(0, (int) floor((time() - $oldestTs) / 86400));
                          $waitingLabel = $daysWaiting . ' dia(s)';
                      }
                  }
                  $isSelected = $selectedSupplier === $supplierId;
                ?>
                <tr data-row-href="<?php echo $esc($rowHref); ?>"<?php echo $isSelected ? ' style="background:#ecfdf5;"' : ''; ?>>
                  <td style="text-align:center;">
                    <input
                      type="checkbox"
                      class="batch-supplier-cb"
                      name="batch_supplier_ids[]"
                      value="<?php echo $supplierId; ?>"
                      data-supplier-id="<?php echo $supplierId; ?>"
                      onchange="syncBatchSupplierCheckboxes(this)">
                  </td>
                  <td>
                    <?php echo $esc($candidate['supplier_name'] ?? '(sem nome)'); ?>
                    <?php if ($isSelected): ?>
                      <span class="badge success" style="margin-left:6px;">selecionada</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-size:12px;line-height:1.35;">
                      <div><strong>Qtd:</strong> <?php echo (int) ($candidate['in_stock_count'] ?? 0); ?></div>
                      <div><strong>Rec. pot.:</strong> R$ <?php echo number_format((float) ($candidate['in_stock_revenue_potential'] ?? 0), 2, ',', '.'); ?></div>
                      <div><strong>Com. pot.:</strong> R$ <?php echo number_format((float) ($candidate['in_stock_commission_potential'] ?? 0), 2, ',', '.'); ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;line-height:1.35;">
                      <div><strong>Qtd:</strong> <?php echo (int) ($candidate['sold_pending_count'] ?? 0); ?></div>
                      <div><strong>Rec. pot.:</strong> R$ <?php echo number_format((float) ($candidate['sold_pending_revenue_potential'] ?? 0), 2, ',', '.'); ?></div>
                      <div><strong>Com. pot.:</strong> R$ <?php echo number_format((float) ($candidate['sold_pending_commission_potential'] ?? 0), 2, ',', '.'); ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;line-height:1.35;">
                      <div><strong>Qtd:</strong> <?php echo (int) ($candidate['sold_paid_count'] ?? 0); ?></div>
                      <div><strong>Rec. ef.:</strong> R$ <?php echo number_format((float) ($candidate['sold_paid_revenue_effective'] ?? 0), 2, ',', '.'); ?></div>
                      <div><strong>Com. paga:</strong> R$ <?php echo number_format((float) ($candidate['sold_paid_commission_paid'] ?? 0), 2, ',', '.'); ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;line-height:1.35;">
                      <div><strong>Qtd:</strong> <?php echo (int) ($candidate['returned_count'] ?? 0); ?></div>
                      <div><strong>Rec. pot.:</strong> R$ <?php echo number_format((float) ($candidate['returned_revenue_potential'] ?? 0), 2, ',', '.'); ?></div>
                      <div><strong>Com. pot.:</strong> R$ <?php echo number_format((float) ($candidate['returned_commission_potential'] ?? 0), 2, ',', '.'); ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;line-height:1.35;">
                      <div><strong>Qtd:</strong> <?php echo (int) ($candidate['donated_count'] ?? 0); ?></div>
                      <div><strong>Rec. pot.:</strong> R$ <?php echo number_format((float) ($candidate['donated_revenue_potential'] ?? 0), 2, ',', '.'); ?></div>
                      <div><strong>Com. pot.:</strong> R$ <?php echo number_format((float) ($candidate['donated_commission_potential'] ?? 0), 2, ',', '.'); ?></div>
                    </div>
                  </td>
                  <td><?php echo (int) ($candidate['pending_orders_count'] ?? 0); ?></td>
                  <td><?php echo (int) ($candidate['pending_count'] ?? 0); ?></td>
                  <td><?php echo (int) ($candidate['pending_products_count'] ?? 0); ?></td>
                  <td>R$ <?php echo number_format((float) ($candidate['pending_commission_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td>R$ <?php echo number_format((float) ($candidate['pending_net_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td><?php echo $oldestSoldAt !== '' ? date('d/m/Y', strtotime($oldestSoldAt)) : '-'; ?></td>
                  <td><?php echo $latestSoldAt !== '' ? date('d/m/Y', strtotime($latestSoldAt)) : '-'; ?></td>
                  <td><?php echo $esc($waitingLabel); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>

<!-- Step 1: Select supplier -->
<div class="table-tools">
<form method="get" action="<?php echo $esc($formRoute); ?>">
  <?php if ($payoutId > 0): ?>
    <input type="hidden" name="id" value="<?php echo $payoutId; ?>">
    <?php if ($isEditingConfirmed): ?>
      <input type="hidden" name="edit_confirmed" value="1">
    <?php endif; ?>
  <?php endif; ?>
  <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <div>
      <label>Fornecedora</label>
      <select name="supplier_pessoa_id" onchange="this.form.submit()">
        <option value="">Selecione...</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?php echo (int)$s['supplier_pessoa_id']; ?>" <?php echo $selectedSupplier === (int)$s['supplier_pessoa_id'] ? 'selected' : ''; ?>>
            <?php echo $esc($s['full_name'] ?? '(sem nome)'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Vendido de</label>
      <input type="date" name="sold_from" value="<?php echo $esc($soldFromValue); ?>">
    </div>
    <div>
      <label>Vendido até</label>
      <input type="date" name="sold_to" value="<?php echo $esc($soldToValue); ?>">
    </div>
    <div>
      <button type="submit" class="btn ghost">Carregar vendas</button>
    </div>
  </div>
</form>
</div>

<?php if ($selectedSupplier > 0): ?>
  <!-- Step 2: Select sales + payment form -->
  <form method="post" action="<?php echo $esc($formRoute . ($payoutId > 0 ? '?id=' . $payoutId : '')); ?>" id="payoutForm">
    <input type="hidden" name="supplier_pessoa_id" value="<?php echo $selectedSupplier; ?>">
    <input type="hidden" name="sold_from" value="<?php echo $esc($soldFromValue); ?>">
    <input type="hidden" name="sold_to" value="<?php echo $esc($soldToValue); ?>">
    <?php if ($isEditingConfirmed): ?>
      <input type="hidden" name="allow_confirmed_edit" value="1">
    <?php endif; ?>

    <h3 style="margin:20px 0 12px;">
      <?php echo $isEditingConfirmed ? 'Vendas elegíveis para este PIX' : 'Vendas pendentes de pagamento'; ?>
    </h3>

    <?php if (empty($pendingSales)): ?>
      <div class="alert info">
        <?php if ($isEditingConfirmed): ?>
          Nenhuma venda elegível encontrada para ajuste neste payout.
        <?php else: ?>
          Nenhuma venda pendente encontrada para esta fornecedora no período selecionado.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="margin-bottom:12px;">
        <button type="button" class="btn ghost" onclick="toggleAllSales(true)">Selecionar todos</button>
        <button type="button" class="btn ghost" onclick="toggleAllSales(false)">Desmarcar todos</button>
        <span id="selectionSummary" style="margin-left:12px;font-size:14px;color:var(--muted);"></span>
      </div>

      <div class="table-scroll" data-table-scroll>
        <div class="table-scroll-top"></div>
        <div class="table-scroll-body" style="max-height:400px;">
        <table data-table="interactive">
          <thead>
            <tr>
              <th style="width:40px;cursor:help;" title="Marca/desmarca os itens para entrar neste pagamento."><input type="checkbox" onclick="toggleAllSales(this.checked)"></th>
              <th title="Número do pedido de origem do item consignado." style="cursor:help;">Pedido</th>
              <th title="Código SKU do produto vendido." style="cursor:help;">SKU</th>
              <th title="Nome do produto vendido em consignação." style="cursor:help;">Produto</th>
              <th title="Data em que a venda foi registrada para comissão." style="cursor:help;">Vendido em</th>
              <th title="Valor líquido da venda usado como base de cálculo da comissão (já considerando descontos aplicáveis)." style="cursor:help;">Receita líq.</th>
              <th title="Percentual de comissão aplicado sobre a receita líquida deste item." style="cursor:help;">%</th>
              <th title="Valor de comissão a pagar para este item de venda." style="cursor:help;">Comissão</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $existingItemSaleIds = array_map(function($i) { return (int)($i['consignment_sale_id'] ?? 0); }, $existingItems);
            foreach ($pendingSales as $sale):
              $saleId = (int) $sale['id'];
              $checked = (in_array($saleId, $existingItemSaleIds, true) || in_array($saleId, $postedSaleIds, true)) ? 'checked' : '';
            ?>
              <tr>
                <td><input type="checkbox" name="sale_ids[]" value="<?php echo $saleId; ?>" class="sale-cb" <?php echo $checked; ?>
                     data-amount="<?php echo (float)($sale['credit_amount'] ?? 0); ?>" onchange="updateSummary()"></td>
                <td><a href="pedido-cadastro.php?id=<?php echo (int)$sale['order_id']; ?>" target="_blank">#<?php echo (int)$sale['order_id']; ?></a></td>
                <td><?php echo $esc($sale['sku'] ?? ''); ?></td>
                <td><?php echo $esc($sale['product_name'] ?? ''); ?></td>
                <td><?php echo !empty($sale['sold_at']) ? date('d/m/Y', strtotime($sale['sold_at'])) : '-'; ?></td>
                <td>R$ <?php echo number_format((float)($sale['net_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo number_format((float)($sale['percent_applied'] ?? 0), 0); ?>%</td>
                <td>R$ <?php echo number_format((float)($sale['credit_amount'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    <?php endif; ?>

    <h3 style="margin:24px 0 12px;">Dados do pagamento</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
      <div>
        <label>Data do pagamento</label>
        <input type="date" name="payout_date" value="<?php echo $esc($payoutDateValue); ?>" required>
      </div>
      <div>
        <label>Método</label>
        <select name="method">
          <option value="pix" <?php echo $methodValue === 'pix' ? 'selected' : ''; ?>>PIX</option>
          <option value="transferencia" <?php echo $methodValue === 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
          <option value="dinheiro" <?php echo $methodValue === 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
          <option value="outro" <?php echo $methodValue === 'outro' ? 'selected' : ''; ?>>Outro</option>
        </select>
      </div>
      <div>
        <label>Conta de origem</label>
        <select name="origin_bank_account_id">
          <option value="">Selecione...</option>
          <?php foreach ($activeBankAccounts as $ba): ?>
            <option value="<?php echo (int)($ba['id'] ?? 0); ?>" <?php echo $originBankAccountValue === (int)($ba['id'] ?? 0) ? 'selected' : ''; ?>>
              <?php echo $esc(($ba['bank_name'] ?? '') . ' - ' . ($ba['account_number'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Chave PIX</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" name="pix_key" value="<?php echo $esc($pixKeyValue); ?>" placeholder="CPF, e-mail, telefone..." style="flex:1;">
          <button type="submit" name="submit_action" value="save_supplier_pix" class="btn ghost" formnovalidate title="Salvar chave PIX no cadastro da pessoa">+</button>
        </div>
        <?php if (!$hasSupplierPixKey): ?>
          <div class="subtitle" style="margin-top:6px;">Esta fornecedora não tem chave PIX no cadastro. Preencha e clique em + para salvar.</div>
        <?php else: ?>
          <div class="subtitle" style="margin-top:6px;">Chave PIX carregada do cadastro da pessoa.</div>
        <?php endif; ?>
      </div>
      <div>
        <label>Referência / TXID</label>
        <input type="text" name="reference" value="<?php echo $esc($referenceValue); ?>" placeholder="ID da transação">
      </div>
    </div>
    <div style="margin-top:12px;">
      <label>Observações</label>
      <textarea name="notes" rows="3" style="width:100%;"><?php echo $esc($notesValue); ?></textarea>
    </div>

    <!-- Summary + Submit -->
    <div style="margin:24px 0;padding:16px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div>
        <strong>Resumo:</strong>
        <span id="summaryCount">0</span> item(ns) selecionado(s) —
        <strong>Total: R$ <span id="summaryTotal">0,00</span></strong>
      </div>
      <div style="display:flex;gap:8px;">
        <button
          type="submit"
          name="submit_action"
          value="preview"
          class="btn ghost"
          formaction="<?php echo $esc($previewActionUrl); ?>"
          formtarget="_blank"
          formnovalidate
          title="Abrir demonstrativo prévio dos itens selecionados">
          Gerar demonstrativo prévio
        </button>
        <?php if ($isEditingConfirmed): ?>
          <button type="submit" name="submit_action" value="edit_confirmed" class="btn primary"
                  onclick="return confirm('Salvar ajustes deste PIX confirmado? O sistema irá reprocessar vínculos, saldos e status.');">
            Salvar ajustes do PIX
          </button>
        <?php else: ?>
          <button type="submit" name="submit_action" value="draft" class="btn ghost">Salvar rascunho</button>
          <?php if (userCan('consignment_module.confirm_payout')): ?>
            <button type="submit" name="submit_action" value="confirm" class="btn primary"
                    onclick="return confirm('Confirma o pagamento? Esta ação irá debitar o saldo da fornecedora e não pode ser desfeita facilmente.');">
              Confirmar pagamento
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </form>
<?php endif; ?>

<script>
function toggleAllSales(checked) {
  document.querySelectorAll('.sale-cb').forEach(function(cb) { cb.checked = checked; });
  updateSummary();
}

function updateSummary() {
  var total = 0, count = 0;
  document.querySelectorAll('.sale-cb:checked').forEach(function(cb) {
    count++;
    total += parseFloat(cb.dataset.amount || 0);
  });
  var el = document.getElementById('summaryCount');
  if (el) el.textContent = count;
  var el2 = document.getElementById('summaryTotal');
  if (el2) el2.textContent = total.toFixed(2).replace('.', ',');
  var sel = document.getElementById('selectionSummary');
  if (sel) sel.textContent = count + ' selecionado(s) — R$ ' + total.toFixed(2).replace('.', ',');
}

function getBatchSupplierCheckboxes() {
  return Array.from(document.querySelectorAll('#batchPreviewExportForm .batch-supplier-cb'));
}

function syncBatchSupplierCheckboxes(source) {
  if (!source || !source.dataset || !source.dataset.supplierId) return;
  var supplierId = source.dataset.supplierId;
  getBatchSupplierCheckboxes().forEach(function(cb) {
    if (cb.dataset && cb.dataset.supplierId === supplierId && cb !== source) {
      cb.checked = source.checked;
    }
  });
}

function setAllBatchSuppliers(checked) {
  getBatchSupplierCheckboxes().forEach(function(cb) {
    cb.checked = checked;
  });
}

function toggleBatchSupplierSelectionState() {
  var scopeEl = document.getElementById('batchScope');
  var scope = scopeEl ? scopeEl.value : 'all';
  var disabled = scope !== 'selected';
  getBatchSupplierCheckboxes().forEach(function(cb) {
    cb.disabled = disabled;
  });
}

function validateBatchPreviewExport(event) {
  var scopeEl = document.getElementById('batchScope');
  var scope = scopeEl ? scopeEl.value : 'all';
  if (scope !== 'selected') return;

  var hasSelection = getBatchSupplierCheckboxes().some(function(cb) {
    return cb.checked;
  });
  if (!hasSelection) {
    event.preventDefault();
    alert('Marque ao menos uma fornecedora para exportar no modo selecionado.');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  updateSummary();
  toggleBatchSupplierSelectionState();

  var batchForm = document.getElementById('batchPreviewExportForm');
  if (batchForm) {
    batchForm.addEventListener('submit', validateBatchPreviewExport);
  }
});
</script>
