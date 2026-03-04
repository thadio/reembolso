<?php
/** @var string $mode */
/** @var array<int, array<string, mixed>> $rows */
/** @var array<int, array<string, mixed>> $summaryRows */
/** @var array<string, int|float> $totals */
/** @var array<string, string> $filters */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, int> $perPageOptions */
/** @var int $totalRows */
/** @var int $totalPages */
/** @var array<string, string> $statusOptions */
/** @var array<string, string> $orderStatusLabels */
/** @var array<string, string> $paymentStatusLabels */
/** @var array<int, array<string, mixed>> $customerOptions */
/** @var array<int, string> $errors */
/** @var callable $esc */
?>
<?php
  $mode = $mode === 'products' ? 'products' : 'orders';
  $filters = $filters ?? [];
  $statusOptions = $statusOptions ?? [];
  $orderStatusLabels = $orderStatusLabels ?? [];
  $paymentStatusLabels = $paymentStatusLabels ?? [];
  $customerOptions = $customerOptions ?? [];
  $page = max(1, (int) ($page ?? 1));
  $perPage = max(1, (int) ($perPage ?? 100));
  $perPageOptions = $perPageOptions ?? [50, 100, 200];
  $totalRows = max(0, (int) ($totalRows ?? 0));
  $totalPages = max(1, (int) ($totalPages ?? 1));
  $rangeStart = $totalRows > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

  $buildModeLink = function (string $targetMode) use ($filters, $perPage): string {
      $query = ['mode' => $targetMode, 'page' => 1, 'per_page' => $perPage];
      foreach (['customer', 'order_status', 'start', 'end', 'q'] as $filterKey) {
          $value = trim((string) ($filters[$filterKey] ?? ''));
          if ($value !== '') {
              $query[$filterKey] = $value;
          }
      }
      return 'cliente-compras.php?' . http_build_query($query);
  };

  $buildPageLink = function (int $targetPage) use ($mode, $filters, $perPage): string {
      $query = ['mode' => $mode, 'page' => $targetPage, 'per_page' => $perPage];
      foreach (['customer', 'order_status', 'start', 'end', 'q'] as $filterKey) {
          $value = trim((string) ($filters[$filterKey] ?? ''));
          if ($value !== '') {
              $query[$filterKey] = $value;
          }
      }
      return 'cliente-compras.php?' . http_build_query($query);
  };

  $clearLink = 'cliente-compras.php?mode=' . $mode . '&per_page=' . $perPage;
  $formatDate = static function (?string $value): string {
      $raw = trim((string) $value);
      if ($raw === '') {
          return '—';
      }
      $timestamp = strtotime($raw);
      if ($timestamp === false) {
          return $raw;
      }
      return date('d/m/Y H:i', $timestamp);
  };
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Compras por clientes</h1>
    <div class="subtitle">Consulte pedidos por cliente ou os produtos já adquiridos.</div>
  </div>
  <div class="actions">
    <a class="btn ghost" href="pessoa-list.php?role=cliente">Voltar aos clientes</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
  <a class="btn <?php echo $mode === 'orders' ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildModeLink('orders')); ?>">Pedidos por cliente</a>
  <a class="btn <?php echo $mode === 'products' ? 'primary' : 'ghost'; ?>" href="<?php echo $esc($buildModeLink('products')); ?>">Produtos adquiridos</a>
</div>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;">
  <input type="hidden" name="mode" value="<?php echo $esc($mode); ?>">
  <input type="hidden" name="page" value="1">
  <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
  <input type="date" name="start" value="<?php echo $esc((string) ($filters['start'] ?? '')); ?>" aria-label="Data inicial">
  <input type="date" name="end" value="<?php echo $esc((string) ($filters['end'] ?? '')); ?>" aria-label="Data final">
  <select name="order_status" aria-label="Filtrar status do pedido">
    <option value="">Todos os status</option>
    <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
      <option value="<?php echo $esc($statusKey); ?>" <?php echo (($filters['order_status'] ?? '') === $statusKey) ? 'selected' : ''; ?>>
        <?php echo $esc($statusLabel); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input
    type="search"
    name="customer"
    list="customer-suggestions"
    aria-label="Filtrar cliente"
    placeholder="Nome, e-mail ou ID da cliente"
    value="<?php echo $esc((string) ($filters['customer'] ?? '')); ?>"
    style="min-width:280px;"
  >
  <input
    type="search"
    name="q"
    aria-label="Busca geral"
    placeholder="Busca geral (cliente, pedido, SKU, produto, valor...)"
    value="<?php echo $esc((string) ($filters['q'] ?? '')); ?>"
    style="min-width:320px;"
  >
  <datalist id="customer-suggestions">
    <?php foreach ($customerOptions as $customer): ?>
      <?php
        $customerId = (int) ($customer['id'] ?? 0);
        $customerName = trim((string) ($customer['full_name'] ?? ''));
      ?>
      <?php if ($customerName !== ''): ?>
        <option value="<?php echo $esc($customerName); ?>" label="<?php echo $esc('ID ' . $customerId); ?>"></option>
      <?php endif; ?>
      <?php if ($customerId > 0): ?>
        <option value="<?php echo $customerId; ?>" label="<?php echo $esc($customerName !== '' ? $customerName : 'Cliente'); ?>"></option>
      <?php endif; ?>
    <?php endforeach; ?>
  </datalist>
  <button class="btn ghost" type="submit">Filtrar</button>
  <a class="btn ghost" href="<?php echo $esc($clearLink); ?>">Limpar</a>
</form>

<?php if ($mode === 'products'): ?>
  <div style="margin-top:18px;">
    <h2 style="margin:0 0 8px;">Resumo por cliente</h2>
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Linhas de produtos</th>
            <th>Quantidade</th>
            <th>Valor total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($summaryRows)): ?>
            <tr class="no-results"><td colspan="4">Nenhum dado encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($summaryRows as $summary): ?>
              <tr>
                <td><?php echo $esc((string) ($summary['customer_name'] ?? 'Cliente não identificado')); ?></td>
                <td><?php echo (int) ($summary['product_lines'] ?? 0); ?></td>
                <td><?php echo (int) ($summary['quantity_total'] ?? 0); ?></td>
                <td>R$ <?php echo number_format((float) ($summary['amount_total'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td><strong>Total geral</strong></td>
              <td><strong><?php echo (int) ($totals['product_lines'] ?? 0); ?></strong></td>
              <td><strong><?php echo (int) ($totals['quantity_total'] ?? 0); ?></strong></td>
              <td><strong>R$ <?php echo number_format((float) ($totals['amount_total'] ?? 0), 2, ',', '.'); ?></strong></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div style="margin-top:18px;">
    <h2 style="margin:0 0 8px;">Resumo por cliente</h2>
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Pedidos</th>
            <th>Itens</th>
            <th>Valor total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($summaryRows)): ?>
            <tr class="no-results"><td colspan="4">Nenhum dado encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($summaryRows as $summary): ?>
              <tr>
                <td><?php echo $esc((string) ($summary['customer_name'] ?? 'Cliente não identificado')); ?></td>
                <td><?php echo (int) ($summary['orders'] ?? 0); ?></td>
                <td><?php echo (int) ($summary['items'] ?? 0); ?></td>
                <td>R$ <?php echo number_format((float) ($summary['amount_total'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td><strong>Total geral</strong></td>
              <td><strong><?php echo (int) ($totals['orders'] ?? 0); ?></strong></td>
              <td><strong><?php echo (int) ($totals['items'] ?? 0); ?></strong></td>
              <td><strong>R$ <?php echo number_format((float) ($totals['amount_total'] ?? 0), 2, ',', '.'); ?></strong></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div style="margin-top:20px;">
  <div class="table-tools">
    <span style="color:var(--muted);font-size:13px;">Mostrando <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> de <?php echo $totalRows; ?></span>
    <form method="get" id="purchasePerPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="mode" value="<?php echo $esc($mode); ?>">
      <input type="hidden" name="page" value="1">
      <?php foreach (['customer', 'order_status', 'start', 'end', 'q'] as $filterKey): ?>
        <?php $value = trim((string) ($filters[$filterKey] ?? '')); ?>
        <?php if ($value !== ''): ?>
          <input type="hidden" name="<?php echo $esc($filterKey); ?>" value="<?php echo $esc($value); ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <label for="purchasePerPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
      <select id="purchasePerPage" name="per_page">
        <?php foreach ($perPageOptions as $option): ?>
          <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div style="overflow:auto;">
    <?php if ($mode === 'products'): ?>
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>SKU</th>
            <th>Produto</th>
            <th>Pedidos</th>
            <th>Quantidade</th>
            <th>Valor total</th>
            <th>Última compra</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="no-results"><td colspan="7">Nenhum produto adquirido encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $personId = (int) ($row['pessoa_id'] ?? 0);
                $customerName = trim((string) ($row['customer_name'] ?? 'Cliente não identificado'));
                $productSku = trim((string) ($row['product_sku_display'] ?? ''));
                $productName = trim((string) ($row['product_name'] ?? 'Produto sem nome'));
                $ordersCount = (int) ($row['orders_count'] ?? 0);
                $quantityTotal = (int) ($row['quantity_total'] ?? 0);
                $amountTotal = (float) ($row['amount_total'] ?? 0);
                $lastOrder = (string) ($row['last_order_at'] ?? '');
              ?>
              <tr>
                <td>
                  <?php if ($personId > 0): ?>
                    <a class="link" href="pessoa-cadastro.php?id=<?php echo $personId; ?>&role=cliente"><?php echo $esc($customerName); ?></a>
                  <?php else: ?>
                    <?php echo $esc($customerName); ?>
                  <?php endif; ?>
                </td>
                <td><?php echo $esc($productSku !== '' ? $productSku : '—'); ?></td>
                <td><?php echo $esc($productName); ?></td>
                <td><?php echo $ordersCount; ?></td>
                <td><?php echo $quantityTotal; ?></td>
                <td>R$ <?php echo number_format($amountTotal, 2, ',', '.'); ?></td>
                <td><?php echo $esc($formatDate($lastOrder)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Pedido</th>
            <th>Data</th>
            <th>Status pedido</th>
            <th>Status pagamento</th>
            <th>Itens</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr class="no-results"><td colspan="7">Nenhum pedido encontrado para os filtros aplicados.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $personId = (int) ($row['pessoa_id'] ?? 0);
                $customerName = trim((string) ($row['customer_name'] ?? 'Cliente não identificado'));
                $orderId = (int) ($row['order_id'] ?? 0);
                $orderStatus = (string) ($row['status'] ?? '');
                $paymentStatus = (string) ($row['payment_status'] ?? '');
                $itemsCount = (int) ($row['items_count'] ?? 0);
                $orderTotal = (float) ($row['order_total'] ?? 0);
                $orderDate = (string) ($row['order_date'] ?? '');
                $orderStatusLabel = $orderStatusLabels[$orderStatus] ?? ($orderStatus !== '' ? $orderStatus : '—');
                $paymentStatusLabel = $paymentStatusLabels[$paymentStatus] ?? ($paymentStatus !== '' ? $paymentStatus : '—');
              ?>
              <tr>
                <td>
                  <?php if ($personId > 0): ?>
                    <a class="link" href="pessoa-cadastro.php?id=<?php echo $personId; ?>&role=cliente"><?php echo $esc($customerName); ?></a>
                  <?php else: ?>
                    <?php echo $esc($customerName); ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($orderId > 0): ?>
                    <a class="link" href="pedido-cadastro.php?id=<?php echo $orderId; ?>">#<?php echo $orderId; ?></a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?php echo $esc($formatDate($orderDate)); ?></td>
                <td><?php echo $esc($orderStatusLabel); ?></td>
                <td><?php echo $esc($paymentStatusLabel); ?></td>
                <td><?php echo $itemsCount; ?></td>
                <td>R$ <?php echo number_format($orderTotal, 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
    <span style="color:var(--muted);font-size:13px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
    <div style="display:flex;gap:8px;align-items:center;">
      <?php if ($page > 1): ?>
        <a class="btn ghost" href="<?php echo $esc($buildPageLink(1)); ?>">Primeira</a>
        <a class="btn ghost" href="<?php echo $esc($buildPageLink($page - 1)); ?>">Anterior</a>
      <?php else: ?>
        <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
        <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
      <?php endif; ?>

      <?php if ($page < $totalPages): ?>
        <a class="btn ghost" href="<?php echo $esc($buildPageLink($page + 1)); ?>">Próxima</a>
        <a class="btn ghost" href="<?php echo $esc($buildPageLink($totalPages)); ?>">Última</a>
      <?php else: ?>
        <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
        <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function () {
    const perPage = document.getElementById('purchasePerPage');
    const form = document.getElementById('purchasePerPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>
