<?php
/** @var array|null $report */
/** @var int $supplierFilter */
/** @var object|array|null $supplierPerson */
/** @var array $suppliers */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $dateField */
/** @var string $detailLevel */
/** @var string $sortField */
/** @var string $sortDir */
/** @var string $groupBy */
/** @var string $printSummaryMode */
/** @var string[] $selectedFields */
/** @var array $fieldMetadata */
/** @var array $fieldsByCategory */
/** @var array $availableViews */
/** @var int $currentViewId */
/** @var array|null $currentView */
/** @var array $statusLabels */
/** @var array $runtimeFilters */
/** @var array|null $periodBadge */
/** @var array $errors */
/** @var callable $esc */

$runtimeFilters = $runtimeFilters ?? [];

// Build base query for export links
$baseQuery = array_filter([
  'supplier_pessoa_id' => $supplierFilter,
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'date_field' => $dateField,
  'detail_level' => $detailLevel,
  'sort_field' => $sortField,
  'sort_dir' => $sortDir,
  'group_by' => $groupBy,
  'print_summary_mode' => $printSummaryMode,
  'view_id' => $currentViewId,
  'consignment_status' => $runtimeFilters['consignment_status'] ?? '',
  'payout_status' => $runtimeFilters['payout_status'] ?? '',
  'q' => $runtimeFilters['search'] ?? '',
  'only_pending_payment' => !empty($runtimeFilters['only_pending_payment']) ? '1' : '',
  'only_sold' => !empty($runtimeFilters['only_sold']) ? '1' : '',
  'aging_min_days' => $runtimeFilters['aging_min_days'] ?? '',
  'only_donation_authorized' => !empty($runtimeFilters['only_donation_authorized']) ? '1' : '',
]);
$baseQuery['fields'] = implode(',', $selectedFields);
$printQuery = $baseQuery; $printQuery['print'] = 1;
$csvQuery = $baseQuery; $csvQuery['format'] = 'csv';
$excelQuery = $baseQuery; $excelQuery['format'] = 'excel';
$jsonQuery = $baseQuery; $jsonQuery['format'] = 'json';
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Relatório Dinâmico de Consignação</h1>
    <div class="subtitle">Gere relatórios completos por fornecedora com campos configuráveis, filtros avançados e múltiplos formatos de exportação.</div>
    <?php if (!empty($periodBadge)): ?>
      <div style="margin-top:6px;">
        <span class="badge <?php echo $esc((string) ($periodBadge['badge'] ?? 'secondary')); ?>">
          <?php echo $esc((string) ($periodBadge['label'] ?? '')); ?> (<?php echo $esc((string) ($periodBadge['year_month'] ?? '')); ?>)
        </span>
      </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
    <a class="btn ghost" href="consignacao-relatorio-modelos.php">⚙ Modelos</a>
    <?php if ($report && $supplierFilter > 0): ?>
      <a class="btn ghost" href="consignacao-relatorio-dinamico.php?<?php echo $esc(http_build_query($printQuery)); ?>" target="_blank">🖨 Imprimir</a>
      <a class="btn ghost" href="consignacao-relatorio-dinamico.php?<?php echo $esc(http_build_query($csvQuery)); ?>">📄 CSV</a>
      <a class="btn ghost" href="consignacao-relatorio-dinamico.php?<?php echo $esc(http_build_query($excelQuery)); ?>">📊 Excel</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<!-- ═══ FILTROS E CONFIGURAÇÃO ═══ -->
<form method="get" action="consignacao-relatorio-dinamico.php" id="reportForm">
<div class="table-tools" style="margin-top:16px;">
  <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">

    <!-- Fornecedora -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">Fornecedora</label>
      <select name="supplier_pessoa_id" style="min-width:200px;">
        <option value="">Selecione...</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?php echo (int)$s['supplier_pessoa_id']; ?>" <?php echo $supplierFilter === (int)$s['supplier_pessoa_id'] ? 'selected' : ''; ?>>
            <?php echo $esc($s['full_name'] ?? '(sem nome)'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Modelo de relatório -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">Modelo</label>
      <select name="view_id" id="viewSelector" style="min-width:180px;">
        <option value="0">Personalizado</option>
        <?php foreach ($availableViews as $v): ?>
          <option value="<?php echo (int)$v['id']; ?>"
            <?php echo $currentViewId === (int)$v['id'] ? 'selected' : ''; ?>
            data-fields="<?php echo $esc(implode(',', $v['fields_config'] ?? [])); ?>"
            data-detail="<?php echo $esc($v['detail_level'] ?? 'both'); ?>"
            data-group="<?php echo $esc($v['group_by'] ?? ''); ?>"
          >
            <?php echo $esc($v['name']); ?><?php echo !empty($v['is_system']) ? ' ★' : ''; ?><?php echo !empty($v['is_default']) ? ' (padrão)' : ''; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Período -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">Período</label>
      <div style="display:flex;gap:6px;">
        <input type="date" name="date_from" value="<?php echo $esc($dateFrom); ?>" title="De" style="width:140px;">
        <input type="date" name="date_to" value="<?php echo $esc($dateTo); ?>" title="Até" style="width:140px;">
      </div>
    </div>

    <!-- Campo de data -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">Filtrar por</label>
      <select name="date_field">
        <option value="sold_at" <?php echo $dateField === 'sold_at' ? 'selected' : ''; ?>>Data de venda</option>
        <option value="received_at" <?php echo $dateField === 'received_at' ? 'selected' : ''; ?>>Data de recebimento</option>
        <option value="paid_at" <?php echo $dateField === 'paid_at' ? 'selected' : ''; ?>>Data de pagamento</option>
      </select>
    </div>

    <!-- Nível de detalhe -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">Detalhamento</label>
      <select name="detail_level" id="detailLevelSelect">
        <option value="both" <?php echo $detailLevel === 'both' ? 'selected' : ''; ?>>Resumo + Detalhe</option>
        <option value="summary" <?php echo $detailLevel === 'summary' ? 'selected' : ''; ?>>Apenas Resumo</option>
        <option value="items" <?php echo $detailLevel === 'items' ? 'selected' : ''; ?>>Apenas Detalhe</option>
      </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;">&nbsp;</label>
      <button type="submit" class="btn primary">Gerar relatório</button>
    </div>
  </div>
</div>

<!-- ═══ FILTROS AVANÇADOS ═══ -->
<details style="margin:12px 0;" <?php echo (!empty($runtimeFilters['consignment_status']) || !empty($runtimeFilters['payout_status']) || !empty($runtimeFilters['search']) || !empty($runtimeFilters['only_pending_payment']) || !empty($runtimeFilters['aging_min_days']) || (($printSummaryMode ?? 'both') !== 'both')) ? 'open' : ''; ?>>
  <summary style="cursor:pointer;font-weight:600;font-size:14px;color:var(--accent);">Filtros Avançados</summary>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;padding:12px;background:#f9fafc;border-radius:12px;">
    <select name="consignment_status">
      <option value="">Todos os status</option>
      <?php foreach ($statusLabels as $sk => $si): ?>
        <option value="<?php echo $esc($sk); ?>" <?php echo (($runtimeFilters['consignment_status'] ?? '') === $sk) ? 'selected' : ''; ?>>
          <?php echo $esc($si['label'] ?? $sk); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="payout_status">
      <option value="">Todos os pagamentos</option>
      <option value="pendente" <?php echo (($runtimeFilters['payout_status'] ?? '') === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
      <option value="pago" <?php echo (($runtimeFilters['payout_status'] ?? '') === 'pago') ? 'selected' : ''; ?>>Pago</option>
    </select>
    <input type="search" name="q" value="<?php echo $esc($runtimeFilters['search'] ?? ''); ?>" placeholder="Buscar SKU, produto, pedido..." style="min-width:200px;">
    <label style="display:flex;align-items:center;gap:4px;font-size:13px;">
      <input type="checkbox" name="only_pending_payment" value="1" <?php echo !empty($runtimeFilters['only_pending_payment']) ? 'checked' : ''; ?>>
      Apenas pendentes
    </label>
    <label style="display:flex;align-items:center;gap:4px;font-size:13px;">
      <input type="checkbox" name="only_sold" value="1" <?php echo !empty($runtimeFilters['only_sold']) ? 'checked' : ''; ?>>
      Apenas vendidas
    </label>
    <label style="display:flex;align-items:center;gap:4px;font-size:13px;">
      <input type="checkbox" name="only_donation_authorized" value="1" <?php echo !empty($runtimeFilters['only_donation_authorized']) ? 'checked' : ''; ?>>
      Doação autorizada
    </label>
    <div style="display:flex;align-items:center;gap:4px;">
      <label style="font-size:13px;">Estoque parado ≥</label>
      <input type="number" name="aging_min_days" value="<?php echo $esc((string) ($runtimeFilters['aging_min_days'] ?? '')); ?>" placeholder="dias" style="width:70px;" min="0">
      <span style="font-size:13px;">dias</span>
    </div>
    <select name="group_by" title="Agrupar por">
      <option value="">Sem agrupamento</option>
      <option value="consignment_status" <?php echo $groupBy === 'consignment_status' ? 'selected' : ''; ?>>Status</option>
      <option value="payout_status" <?php echo $groupBy === 'payout_status' ? 'selected' : ''; ?>>Status Pgto</option>
      <option value="category_name" <?php echo $groupBy === 'category_name' ? 'selected' : ''; ?>>Categoria</option>
      <option value="brand_name" <?php echo $groupBy === 'brand_name' ? 'selected' : ''; ?>>Marca</option>
    </select>
    <select name="sort_field" title="Ordenar por">
      <option value="received_at" <?php echo $sortField === 'received_at' ? 'selected' : ''; ?>>Recebido em</option>
      <option value="sold_at" <?php echo $sortField === 'sold_at' ? 'selected' : ''; ?>>Vendido em</option>
      <option value="price" <?php echo $sortField === 'price' ? 'selected' : ''; ?>>Preço</option>
      <option value="credit_amount" <?php echo $sortField === 'credit_amount' ? 'selected' : ''; ?>>Comissão</option>
      <option value="days_in_stock" <?php echo $sortField === 'days_in_stock' ? 'selected' : ''; ?>>Dias em estoque</option>
      <option value="sku" <?php echo $sortField === 'sku' ? 'selected' : ''; ?>>SKU</option>
      <option value="product_name" <?php echo $sortField === 'product_name' ? 'selected' : ''; ?>>Produto</option>
      <option value="paid_at" <?php echo $sortField === 'paid_at' ? 'selected' : ''; ?>>Pago em</option>
    </select>
    <select name="sort_dir" title="Direção">
      <option value="DESC" <?php echo $sortDir === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
      <option value="ASC" <?php echo $sortDir === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
    </select>
    <select name="print_summary_mode" title="Resumo no print">
      <option value="both" <?php echo ($printSummaryMode ?? 'both') === 'both' ? 'selected' : ''; ?>>Print: mostrar os dois resumos</option>
      <option value="historical" <?php echo ($printSummaryMode ?? 'both') === 'historical' ? 'selected' : ''; ?>>Print: só histórico completo</option>
      <option value="filtered" <?php echo ($printSummaryMode ?? 'both') === 'filtered' ? 'selected' : ''; ?>>Print: só filtrado</option>
      <option value="none" <?php echo ($printSummaryMode ?? 'both') === 'none' ? 'selected' : ''; ?>>Print: sem resumo executivo</option>
    </select>
  </div>
</details>

<!-- ═══ SELEÇÃO DE CAMPOS ═══ -->
<details style="margin:12px 0;" <?php echo $currentViewId === 0 ? 'open' : ''; ?>>
  <summary style="cursor:pointer;font-weight:600;font-size:14px;color:var(--accent);">Seleção de Campos</summary>
  <div style="margin-top:10px;padding:16px;background:#f9fafc;border-radius:12px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;" id="fieldSelector">
      <?php foreach ($fieldsByCategory as $catKey => $cat): ?>
        <div>
          <div style="font-weight:600;font-size:13px;margin-bottom:8px;color:var(--ink);text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid var(--line);padding-bottom:4px;">
            <?php echo $esc($cat['label']); ?>
          </div>
          <?php foreach ($cat['fields'] as $fk => $fmeta): ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;padding:3px 0;cursor:pointer;" class="field-option" data-field="<?php echo $esc($fk); ?>">
              <input type="checkbox" name="fields[]" value="<?php echo $esc($fk); ?>"
                <?php echo in_array($fk, $selectedFields) ? 'checked' : ''; ?>
                <?php echo !empty($fmeta['required']) ? 'disabled checked' : ''; ?>
              >
              <?php if (!empty($fmeta['required'])): ?>
                <input type="hidden" name="fields[]" value="<?php echo $esc($fk); ?>">
              <?php endif; ?>
              <span><?php echo $esc($fmeta['label']); ?></span>
              <?php if (!empty($fmeta['required'])): ?>
                <span style="font-size:10px;color:var(--accent);font-weight:600;">obrigatório</span>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;">
      <button type="button" class="btn ghost" onclick="document.querySelectorAll('#fieldSelector input[type=checkbox]:not([disabled])').forEach(c=>c.checked=true)">Selecionar todos</button>
      <button type="button" class="btn ghost" onclick="document.querySelectorAll('#fieldSelector input[type=checkbox]:not([disabled])').forEach(c=>c.checked=false)">Limpar seleção</button>
    </div>
  </div>
</details>
</form>

<?php if ($report): ?>

  <?php
  // Count for display
  $itemCount = count($report['items'] ?? []);
  ?>
  <div style="margin:16px 0 8px;font-size:13px;color:var(--muted);">
    <?php if ($supplierPerson): ?>
      <strong><?php echo $esc(is_array($supplierPerson) ? ($supplierPerson['full_name'] ?? '') : ($supplierPerson->full_name ?? $supplierPerson->fullName ?? '')); ?></strong> —
    <?php endif; ?>
    <?php echo $itemCount; ?> registro(s) encontrado(s)
    <?php if (!empty($report['generated_at'])): ?>
      · Gerado em <?php echo date('d/m/Y H:i', strtotime($report['generated_at'])); ?>
    <?php endif; ?>
  </div>

  <!-- ═══ RESUMO EXECUTIVO ═══ -->
  <?php
  $renderExecutiveSummaryCards = static function (array $s): void {
  ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin:12px 0;">
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Recebidas (total)</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['total_received'] ?? 0); ?></div>
      </div>
      <div style="background:#dbeafe;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Em estoque</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['in_stock_count'] ?? 0); ?></div>
        <div style="font-size:12px;color:#1e40af;">R$ <?php echo number_format((float) ($s['in_stock_value'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#d1fae5;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Vendidas</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['sold_count'] ?? 0); ?></div>
        <div style="font-size:12px;color:#065f46;">Bruto: R$ <?php echo number_format((float) ($s['gross_sold_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#fef3c7;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Pendentes pgto</div>
        <div style="font-size:22px;font-weight:700;color:#92400e;"><?php echo (int) ($s['sold_pending_count'] ?? 0); ?></div>
        <div style="font-size:12px;color:#92400e;">R$ <?php echo number_format((float) ($s['total_pending'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#d1fae5;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Já pagas</div>
        <div style="font-size:22px;font-weight:700;color:#065f46;"><?php echo (int) ($s['sold_paid_count'] ?? 0); ?></div>
        <div style="font-size:12px;color:#065f46;">R$ <?php echo number_format((float) ($s['total_paid'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Devolvidas</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['returned_count'] ?? 0); ?></div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Doação autorizada</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['donation_authorized_count'] ?? 0); ?></div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Doadas</div>
        <div style="font-size:22px;font-weight:700;"><?php echo (int) ($s['donated_count'] ?? 0); ?></div>
      </div>
      <div style="background:#ede9fe;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Comissão total</div>
        <div style="font-size:22px;font-weight:700;color:#6d28d9;">R$ <?php echo number_format((float) ($s['total_commission'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Ticket médio</div>
        <div style="font-size:22px;font-weight:700;">R$ <?php echo number_format((float) ($s['ticket_avg'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Aging médio (estoque)</div>
        <div style="font-size:22px;font-weight:700;"><?php echo number_format((float) ($s['avg_aging_days'] ?? 0), 1, ',', '.'); ?>d</div>
      </div>
      <div style="background:#f3f4f6;border-radius:10px;padding:14px;">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Tempo médio até venda</div>
        <div style="font-size:22px;font-weight:700;"><?php echo number_format((float) ($s['avg_days_to_sell'] ?? 0), 1, ',', '.'); ?>d</div>
      </div>
    </div>
  <?php
  };
  ?>
  <?php if (in_array($detailLevel, ['summary', 'both'], true) && (!empty($report['summary']) || !empty($report['summary_filtered']))): ?>
    <?php if (!empty($report['summary'])): ?>
      <h3 style="margin:20px 0 12px;">Resumo Executivo Histórico Completo</h3>
      <?php $renderExecutiveSummaryCards($report['summary']); ?>
    <?php endif; ?>
    <?php if (!empty($report['summary_filtered'])): ?>
      <h3 style="margin:20px 0 12px;">Resumo Executivo filtrado (conforme filtros)</h3>
      <?php $sfAudit = $report['summary_filtered']; ?>
      <?php if ((int) ($sfAudit['audit_missing_registry_items'] ?? 0) > 0): ?>
        <div class="alert info" style="margin:8px 0;">
          Auditoria: <?php echo (int) ($sfAudit['audit_filtered_total_items'] ?? 0); ?> item(ns) no detalhe, <?php echo (int) ($sfAudit['audit_registry_items'] ?? 0); ?> com registro da fornecedora e <?php echo (int) ($sfAudit['audit_missing_registry_items'] ?? 0); ?> venda(s) sem registro de consignação dessa fornecedora.
        </div>
      <?php endif; ?>
      <?php $renderExecutiveSummaryCards($report['summary_filtered']); ?>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ═══ LISTAGEM DETALHADA ═══ -->
  <?php if (in_array($detailLevel, ['items', 'both'], true) && !empty($report['items'])): ?>

    <?php if (!empty($report['grouped_items'])): ?>
      <!-- Grouped display -->
      <?php foreach ($report['grouped_items'] as $group): ?>
        <h3 style="margin:24px 0 8px;display:flex;align-items:center;gap:8px;">
          <?php
            $groupLabel = $group['label'];
            if (isset($statusLabels[$groupLabel])) {
              $groupLabel = $statusLabels[$groupLabel]['label'] ?? $groupLabel;
            }
          ?>
          <span class="badge <?php echo $statusLabels[$group['label']]['badge'] ?? 'secondary'; ?>"><?php echo $esc($groupLabel); ?></span>
          <span style="font-size:13px;color:var(--muted);">(<?php echo $group['count']; ?> itens · R$ <?php echo number_format($group['subtotal_commission'], 2, ',', '.'); ?>)</span>
        </h3>
        <?php echo renderDynamicTable($group['items'], $selectedFields, $fieldMetadata, $statusLabels, $esc); ?>
      <?php endforeach; ?>

    <?php else: ?>
      <!-- Flat display -->
      <h3 style="margin:24px 0 12px;">Detalhe por Peça</h3>
      <?php echo renderDynamicTable($report['items'], $selectedFields, $fieldMetadata, $statusLabels, $esc); ?>
    <?php endif; ?>

    <!-- ═══ TOTAIS ═══ -->
    <?php
    $totalPrice = 0; $totalCommission = 0; $totalGross = 0; $totalDiscount = 0; $totalNet = 0;
    foreach ($report['items'] as $item) {
      $totalPrice += (float) ($item['price'] ?? 0);
      $totalCommission += (float) ($item['credit_amount'] ?? 0);
      $totalGross += (float) ($item['gross_amount'] ?? 0);
      $totalDiscount += (float) ($item['discount_amount'] ?? 0);
      $totalNet += (float) ($item['net_amount'] ?? 0);
    }
    ?>
    <div style="margin:16px 0;padding:16px;background:#f3f4f6;border-radius:10px;display:flex;gap:24px;flex-wrap:wrap;font-size:14px;">
      <div><strong>Total itens:</strong> <?php echo $itemCount; ?></div>
      <?php if (in_array('price', $selectedFields)): ?>
        <div><strong>Total preço venda:</strong> R$ <?php echo number_format($totalPrice, 2, ',', '.'); ?></div>
      <?php endif; ?>
      <?php if (in_array('credit_amount', $selectedFields)): ?>
        <div><strong>Total comissão:</strong> <span style="color:#6d28d9;font-weight:700;">R$ <?php echo number_format($totalCommission, 2, ',', '.'); ?></span></div>
      <?php endif; ?>
      <?php if (in_array('net_amount', $selectedFields)): ?>
        <div><strong>Total líquido:</strong> R$ <?php echo number_format($totalNet, 2, ',', '.'); ?></div>
      <?php endif; ?>
    </div>

  <?php endif; ?>

<?php elseif ($supplierFilter > 0): ?>
  <div class="alert info" style="margin-top:20px;">Nenhum dado encontrado para os filtros selecionados.</div>
<?php else: ?>
  <div style="margin-top:40px;text-align:center;color:var(--muted);">
    <div style="font-size:48px;opacity:0.3;">📊</div>
    <div style="font-size:16px;margin-top:8px;">Selecione uma fornecedora e clique em <strong>Gerar relatório</strong> para visualizar.</div>
  </div>
<?php endif; ?>

<!-- ═══ LINK COMPARTILHÁVEL ═══ -->
<?php if ($report && $supplierFilter > 0): ?>
  <div style="margin:24px 0 12px;padding:12px;background:#f9fafc;border-radius:10px;border:1px solid var(--line);">
    <div style="font-size:12px;text-transform:uppercase;color:var(--muted);letter-spacing:0.05em;margin-bottom:6px;">Link compartilhável</div>
    <div style="display:flex;gap:8px;">
      <input type="text" id="shareableLink" value="<?php echo $esc((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/consignacao-relatorio-dinamico.php?' . http_build_query($baseQuery)); ?>" readonly style="flex:1;font-size:12px;font-family:monospace;">
      <button type="button" class="btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('shareableLink').value).then(()=>this.textContent='Copiado!').catch(()=>{})">Copiar</button>
    </div>
  </div>
<?php endif; ?>

<script>
// View selector auto-applies fields
document.getElementById('viewSelector')?.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const fields = opt.dataset.fields || '';
  const detail = opt.dataset.detail || 'both';
  const group = opt.dataset.group || '';

  if (fields) {
    const fieldKeys = fields.split(',');
    document.querySelectorAll('#fieldSelector input[type=checkbox]:not([disabled])').forEach(cb => {
      cb.checked = fieldKeys.includes(cb.value);
    });
  }
  const detailSelect = document.getElementById('detailLevelSelect');
  if (detailSelect && detail) {
    detailSelect.value = detail;
  }
  const groupSelect = document.querySelector('[name=group_by]');
  if (groupSelect) {
    groupSelect.value = group;
  }
});
</script>

<?php
/**
 * Render a dynamic table based on selected fields.
 */
function renderDynamicTable(array $items, array $fields, array $metadata, array $statusLabels, callable $esc): string {
  if (empty($items)) return '<div class="alert info">Nenhum item.</div>';

  $html = '<div class="table-scroll" data-table-scroll>';
  $html .= '<div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>';
  $html .= '<div class="table-scroll-body">';
  $html .= '<table data-table="interactive"><thead><tr>';

  foreach ($fields as $fk) {
    $label = $metadata[$fk]['label'] ?? $fk;
    $html .= '<th data-sort-key="' . htmlspecialchars($fk) . '" aria-sort="none">' . htmlspecialchars($label) . '</th>';
  }
  $html .= '</tr>';

  // Filter row
  $html .= '<tr>';
  foreach ($fields as $fk) {
    $html .= '<th><input type="search" data-filter-col="' . htmlspecialchars($fk) . '" placeholder="Filtrar" aria-label="Filtrar ' . htmlspecialchars($metadata[$fk]['label'] ?? $fk) . '" style="width:100%;"></th>';
  }
  $html .= '</tr></thead><tbody>';

  foreach ($items as $item) {
    $html .= '<tr>';
    foreach ($fields as $fk) {
      $val = $item[$fk] ?? '';
      $type = $metadata[$fk]['type'] ?? 'text';
      $html .= '<td data-col="' . htmlspecialchars($fk) . '" data-value="' . htmlspecialchars((string) $val) . '">';
      $html .= formatDynamicCell($fk, $val, $type, $item, $statusLabels, $esc);
      $html .= '</td>';
    }
    $html .= '</tr>';
  }

  $html .= '</tbody></table></div></div>';
  return $html;
}

function formatDynamicCell(string $key, $value, string $type, array $item, array $statusLabels, callable $esc): string {
  if ($value === null || $value === '') {
    return '<span style="color:var(--muted);">—</span>';
  }

  switch ($type) {
    case 'currency':
      return 'R$ ' . number_format((float) $value, 2, ',', '.');

    case 'percent':
      return number_format((float) $value, 0) . '%';

    case 'date':
      return date('d/m/Y', strtotime((string) $value));

    case 'status':
      if ($key === 'consignment_status') {
        $stInfo = $statusLabels[$value] ?? ['label' => $value, 'badge' => 'secondary'];
        return '<span class="badge ' . $stInfo['badge'] . '">' . $esc($stInfo['label'] ?? $value) . '</span>';
      }
      if ($key === 'payout_status') {
        $label = $value === 'pago' ? 'Pago' : ($value === 'pendente' ? 'Pendente' : $value);
        $badge = $value === 'pago' ? 'success' : ($value === 'pendente' ? 'warning' : 'secondary');
        return '<span class="badge ' . $badge . '">' . $esc($label) . '</span>';
      }
      return $esc((string) $value);

    case 'boolean':
      return ((int) $value) ? '<span style="color:#059669;">✓ Sim</span>' : '<span style="color:var(--muted);">Não</span>';

    case 'link':
      if ($key === 'order_id' && (int) $value > 0) {
        return '<a href="pedido-cadastro.php?id=' . (int)$value . '" style="color:var(--accent);">#' . (int)$value . '</a>';
      }
      if ($key === 'payout_id' && (int) $value > 0) {
        return '<a href="consignacao-pagamento-cadastro.php?id=' . (int)$value . '" style="color:var(--accent);">#' . (int)$value . '</a>';
      }
      return $esc((string) $value);

    case 'image':
      $source = trim((string) $value);
      if ($source !== '') {
        $thumb = function_exists('image_url') ? (string) image_url($source, 'thumb', 80) : $source;
        if ($thumb === '') {
          $thumb = $source;
        }
        return '<img src="' . $esc($thumb) . '" alt="Foto" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" loading="lazy">';
      }
      return '<span style="color:var(--muted);">—</span>';

    case 'integer':
      $intVal = (int) $value;
      // Highlight aging > 90 days
      if ($key === 'days_in_stock' && $intVal > 90) {
        return '<span style="color:#dc2626;font-weight:700;">' . $intVal . '</span>';
      }
      if ($key === 'days_in_stock' && $intVal > 60) {
        return '<span style="color:#d97706;font-weight:600;">' . $intVal . '</span>';
      }
      return (string) $intVal;

    default:
      return $esc((string) $value);
  }
}
?>
