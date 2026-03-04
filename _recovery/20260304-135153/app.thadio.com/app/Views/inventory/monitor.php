<?php
/** @var array $errors */
/** @var string $success */
/** @var array|null $openInventory */
/** @var array $inventories */
/** @var int $page */
/** @var int $perPage */
/** @var array $perPageOptions */
/** @var int $totalInventories */
/** @var int $totalPages */
/** @var array|null $selectedInventory */
/** @var array|null $summary */
/** @var array $items */
/** @var array $pending */
/** @var string $pendingLabel */
/** @var array $logs */
/** @var array|null $metrics */
/** @var array $priceMap */
/** @var array $availabilityChanges */
/** @var callable $formatDate */
/** @var callable $esc */
?>
<?php
  $page = $page ?? 1;
  $perPage = $perPage ?? 80;
  $perPageOptions = $perPageOptions ?? [40, 80, 120, 200];
  $totalInventories = $totalInventories ?? 0;
  $totalPages = $totalPages ?? 1;
  $selectedInventoryId = isset($selectedInventory['id']) ? (int) $selectedInventory['id'] : 0;
  $rangeStart = $totalInventories > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalInventories > 0 ? min($totalInventories, $page * $perPage) : 0;
  $buildLink = function (int $targetPage) use ($perPage, $selectedInventoryId): string {
      $query = ['page' => $targetPage, 'per_page' => $perPage];
      if ($selectedInventoryId > 0) {
          $query['inventory'] = $selectedInventoryId;
      }
      return 'disponibilidade-conferencia-acompanhamento.php?' . http_build_query($query);
  };
  $formatMoney = function ($value): string {
      $value = (float) ($value ?? 0);
      return 'R$ ' . number_format($value, 2, ',', '.');
  };
  $actionLabels = [
      'manual_update' => 'Ajuste manual',
      'scan_update' => 'Ajuste por leitura',
      'bulk_zero' => 'Zerado em massa',
  ];
  $availabilityLabel = static function ($raw): string {
      $value = strtolower(trim((string) $raw));
      $labels = [
          'instock' => 'Disponível',
          'disponivel' => 'Disponível',
          'reservado' => 'Reservado',
          'outofstock' => 'Indisponível',
          'esgotado' => 'Esgotado',
          'indisponivel' => 'Indisponível',
          'baixado' => 'Baixado',
          'archived' => 'Arquivado',
          'draft' => 'Rascunho',
      ];
      return $labels[$value] ?? ($value !== '' ? ucfirst($value) : '—');
  };
?>
<style>
  .monitor-hero { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .monitor-grid { display: grid; grid-template-columns: minmax(0, 300px) minmax(0, 1fr); gap: 16px; margin-top: 14px; }
  @media (max-width: 1100px) { .monitor-grid { grid-template-columns: 1fr; } }
  .monitor-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06); }
  .monitor-list { display: grid; gap: 8px; }
  .monitor-list a { display: block; padding: 10px 12px; border-radius: 12px; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; background: #f8fafc; }
  .monitor-list a.active { border-color: #4f46e5; background: #eef2ff; }
  .monitor-muted { color: var(--muted); font-size: 13px; }
  .monitor-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 12px; }
  .monitor-metric { border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; background: #f8fafc; }
  .monitor-metric .label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
  .monitor-metric .value { font-size: 18px; font-weight: 700; margin-top: 6px; }
  .monitor-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .monitor-table th, .monitor-table td { padding: 8px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
  .monitor-table th { text-transform: uppercase; font-size: 11px; color: var(--muted); letter-spacing: 0.04em; }
  .monitor-section { margin-top: 16px; }
  .monitor-section h3 { margin: 0 0 8px; }
</style>

<div>
  <div class="monitor-hero">
    <div>
      <h1>Acompanhamento de Batimentos</h1>
      <div class="subtitle">Acompanhe o batimento em curso e o histórico completo.</div>
    </div>
    <?php if (userCan('inventory.view')): ?>
      <a class="btn ghost" href="disponibilidade-conferencia.php">Ir para batimento</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>

  <div class="monitor-card" style="margin-top:12px;">
    <?php if ($openInventory): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
        <div>
          <strong>Batimento em curso</strong>
          <div class="monitor-muted">Batimento #<?php echo $esc((string) $openInventory['id']); ?> aberto em <?php echo $esc($formatDate((string) ($openInventory['opened_at'] ?? ''))); ?></div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span class="pill">Status: aberto</span>
          <?php if (userCan('inventory.close')): ?>
            <form method="post">
              <input type="hidden" name="inventory_id" value="<?php echo $esc((string) $openInventory['id']); ?>">
              <button class="btn primary" type="submit" name="close_inventory" value="1">Concluir batimento</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="monitor-muted">Nenhum batimento aberto no momento.</div>
    <?php endif; ?>
  </div>

  <div class="monitor-grid">
    <div class="monitor-card">
      <h2 style="margin:0 0 10px;">Histórico</h2>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
        <span class="monitor-muted">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalInventories; ?></span>
        <form method="get" id="perPageForm" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="page" value="1">
          <?php if ($selectedInventoryId > 0): ?>
            <input type="hidden" name="inventory" value="<?php echo (int) $selectedInventoryId; ?>">
          <?php endif; ?>
          <label for="perPage" class="monitor-muted">Itens por página</label>
          <select id="perPage" name="per_page">
            <?php foreach ($perPageOptions as $option): ?>
              <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="monitor-list">
        <?php if (empty($inventories)): ?>
          <div class="monitor-muted">Nenhum batimento encontrado.</div>
        <?php else: ?>
          <?php foreach ($inventories as $inv): ?>
            <?php
              $invId = (int) ($inv['id'] ?? 0);
              $isActive = $selectedInventory && (int) ($selectedInventory['id'] ?? 0) === $invId;
              $statusLabel = ($inv['status'] ?? '') === 'aberto' ? 'aberto' : 'fechado';
              $invQuery = ['inventory' => $invId, 'page' => $page, 'per_page' => $perPage];
              $invHref = 'disponibilidade-conferencia-acompanhamento.php?' . http_build_query($invQuery);
            ?>
            <a class="<?php echo $isActive ? 'active' : ''; ?>" href="<?php echo $esc($invHref); ?>">
              <div><strong>Batimento #<?php echo $invId; ?></strong> <span class="monitor-muted">(<?php echo $statusLabel; ?>)</span></div>
              <div class="monitor-muted">Abertura: <?php echo $esc($formatDate((string) ($inv['opened_at'] ?? ''))); ?></div>
              <?php if (!empty($inv['closed_at'])): ?>
                <div class="monitor-muted">Fechamento: <?php echo $esc($formatDate((string) ($inv['closed_at'] ?? ''))); ?></div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:10px;">
        <span class="monitor-muted">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
        <div style="display:flex;gap:6px;align-items:center;">
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
    </div>

    <div class="monitor-card">
      <?php if (!$selectedInventory): ?>
        <div class="monitor-muted">Selecione um batimento para ver os detalhes.</div>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
          <div>
            <h2 style="margin:0;">Detalhes do batimento #<?php echo (int) $selectedInventory['id']; ?></h2>
            <div class="monitor-muted">Status: <?php echo $esc((string) ($selectedInventory['status'] ?? '')); ?></div>
          </div>
          <div class="monitor-muted">
            Abertura: <?php echo $esc($formatDate((string) ($selectedInventory['opened_at'] ?? ''))); ?><br>
            Fechamento: <?php echo $esc($formatDate((string) ($selectedInventory['closed_at'] ?? ''))); ?>
          </div>
        </div>
        <?php if (($selectedInventory['status'] ?? '') === 'fechado' && userCan('inventory.close')): ?>
          <div class="monitor-section" style="margin-top:10px;">
            <?php if ($openInventory): ?>
              <div class="monitor-muted">Existe um batimento em aberto. Feche-o antes de reabrir.</div>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="inventory_id" value="<?php echo $esc((string) $selectedInventory['id']); ?>">
                <button class="btn ghost" type="submit" name="reopen_inventory" value="1">Reabrir batimento</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($metrics): ?>
          <div class="monitor-metrics">
            <div class="monitor-metric">
              <div class="label">Total de produtos lidos</div>
              <div class="value"><?php echo (int) $metrics['total_counted']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">SKUs lidos</div>
              <div class="value"><?php echo (int) $metrics['unique_items']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Total de leituras</div>
              <div class="value"><?php echo (int) $metrics['total_scans']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Produtos ajustados</div>
              <div class="value"><?php echo (int) $metrics['adjusted_items']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Ajustes de preço</div>
              <div class="value"><?php echo (int) $metrics['price_adjustments']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Ajustes de disponibilidade</div>
              <div class="value"><?php echo (int) $metrics['availability_adjustments']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Ajustes de nome</div>
              <div class="value"><?php echo (int) $metrics['name_adjustments']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Ajustes de status</div>
              <div class="value"><?php echo (int) $metrics['status_adjustments']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Ajustes de categorias</div>
              <div class="value"><?php echo (int) $metrics['category_adjustments']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Fotos adicionadas</div>
              <div class="value"><?php echo (int) $metrics['photos_added']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Fotos removidas</div>
              <div class="value"><?php echo (int) $metrics['photos_removed']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Valor total lido</div>
              <div class="value"><?php echo $formatMoney($metrics['total_read_value']); ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Valor médio por produto (anterior)</div>
              <div class="value"><?php echo $formatMoney($metrics['avg_value_before']); ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Valor médio por produto (novo)</div>
              <div class="value"><?php echo $formatMoney($metrics['avg_value_after']); ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Valor não encontrado</div>
              <div class="value"><?php echo $formatMoney($metrics['total_missing_value']); ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Produtos não lidos</div>
              <div class="value"><?php echo (int) $metrics['missing_items']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Total zerado (unidades)</div>
              <div class="value"><?php echo (int) $metrics['zeroed_quantity']; ?></div>
            </div>
            <div class="monitor-metric">
              <div class="label">Itens zerados</div>
              <div class="value"><?php echo (int) $metrics['zeroed_items']; ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="monitor-section">
          <h3>Produtos lidos</h3>
          <?php if (empty($items)): ?>
            <div class="monitor-muted">Nenhuma leitura registrada.</div>
          <?php else: ?>
            <table class="monitor-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Produto</th>
                  <th>Qtd</th>
                  <th>Qtd anterior</th>
                  <th>Qtd nova</th>
                  <th>Leituras</th>
                  <th>Preço</th>
                  <th>Valor</th>
                  <th>Última leitura</th>
                  <th>Usuário</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <?php
                    $productId = (int) (($item['product_sku'] ?? 0) ?: ($item['product_id'] ?? 0));
                    $price = isset($priceMap[$productId]['price']) ? (float) $priceMap[$productId]['price'] : 0.0;
                    $qty = (int) ($item['counted_quantity'] ?? 0);
                    $value = $qty * $price;
                    $availabilityBefore = $availabilityChanges[$productId]['before'] ?? null;
                    $availabilityAfter = $availabilityChanges[$productId]['after'] ?? $qty;
                    $availabilityBeforeLabel = $availabilityBefore === null ? '-' : (string) $availabilityBefore;
                    $availabilityAfterLabel = $availabilityAfter === null ? '-' : (string) $availabilityAfter;
                  ?>
                  <tr>
                    <td><?php echo $esc((string) ($item['sku'] ?? '')); ?></td>
                    <td><?php echo $esc((string) ($item['product_name'] ?? '')); ?></td>
                    <td><?php echo $qty; ?></td>
                    <td><?php echo $esc($availabilityBeforeLabel); ?></td>
                    <td><?php echo $esc($availabilityAfterLabel); ?></td>
                    <td><?php echo (int) ($item['scan_count'] ?? 0); ?></td>
                    <td><?php echo $formatMoney($price); ?></td>
                    <td><?php echo $formatMoney($value); ?></td>
                    <td><?php echo $esc($formatDate((string) ($item['last_scan_at'] ?? ''))); ?></td>
                    <td><?php echo $esc((string) ($item['last_user_name'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="monitor-section">
          <h3><?php echo $esc($pendingLabel); ?></h3>
          <?php if (empty($pending)): ?>
            <div class="monitor-muted">Nenhuma pendência encontrada.</div>
          <?php else: ?>
            <form method="post" action="disponibilidade-conferencia.php">
              <input type="hidden" name="inventory_id" value="<?php echo $esc((string) $selectedInventory['id']); ?>">
              <table class="monitor-table">
                <thead>
                  <tr>
                    <th><input type="checkbox" id="pendingSelectAll"></th>
                    <th>SKU</th>
                    <th>Produto</th>
                    <th>Qtd disponível</th>
                    <th>Disponibilidade</th>
                    <th>Preço</th>
                    <th>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pending as $row): ?>
                    <?php
                      $resolved = !empty($row['resolved_action']);
                      $checkboxDisabled = $resolved ? 'disabled' : '';
                      $productId = (int) (($row['product_sku'] ?? 0) ?: ($row['product_id'] ?? $row['id'] ?? 0));
                      $price = isset($priceMap[$productId]['price']) ? (float) $priceMap[$productId]['price'] : 0.0;
                      $qtySource = $row['quantity'] ?? null;
                      $qty = $qtySource !== null ? (int) $qtySource : 0;
                      $value = $qty * $price;
                      $name = (string) ($row['product_name'] ?? $row['name'] ?? '');
                    ?>
                    <tr>
                      <td>
                        <input type="checkbox" name="pending_ids[]" value="<?php echo $esc((string) $row['id']); ?>" <?php echo $checkboxDisabled; ?>>
                      </td>
                      <td><?php echo $esc((string) ($row['sku'] ?? '')); ?></td>
                      <td><?php echo $esc($name); ?></td>
                      <td><?php echo $qty; ?></td>
                      <td><?php echo $esc($availabilityLabel($row['availability_status'] ?? '')); ?></td>
                      <td><?php echo $formatMoney($price); ?></td>
                      <td><?php echo $formatMoney($value); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="grid" style="margin-top:12px;">
                <div class="field">
                  <label for="bulk_reason">Motivo do ajuste em massa</label>
                  <input id="bulk_reason" name="bulk_reason" type="text" maxlength="255" value="<?php echo $esc((string) ($selectedInventory['default_reason'] ?? '')); ?>">
                </div>
              </div>
              <div class="footer">
                <button class="btn primary" type="submit" name="bulk_zero" value="1">Zerar disponibilidade selecionada</button>
              </div>
            </form>
          <?php endif; ?>
        </div>

        <div class="monitor-section">
          <h3>Ajustes registrados</h3>
          <?php if (empty($logs)): ?>
            <div class="monitor-muted">Nenhum ajuste registrado.</div>
          <?php else: ?>
            <table class="monitor-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>SKU</th>
                  <th>Ação</th>
                  <th>Usuário</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <?php
                    $action = (string) ($log['action'] ?? '');
                    $label = $actionLabels[$action] ?? $action;
                  ?>
                  <tr>
                    <td><?php echo $esc($formatDate((string) ($log['created_at'] ?? ''))); ?></td>
                    <td><?php echo $esc((string) ($log['sku'] ?? '')); ?></td>
                    <td><?php echo $esc($label); ?></td>
                    <td><?php echo $esc((string) ($log['user_name'] ?? '-')); ?></td>
                    <td><?php echo $esc((string) ($log['reason'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function() {
    const perPage = document.getElementById('perPage');
    const form = document.getElementById('perPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>

<script>
  (function(){
    const selectAll = document.getElementById('pendingSelectAll');
    if (!selectAll) return;
    selectAll.addEventListener('change', function() {
      const checkboxes = Array.from(document.querySelectorAll('input[name="pending_ids[]"]:not([disabled])'));
      checkboxes.forEach(cb => cb.checked = selectAll.checked);
    });
  })();
</script>
