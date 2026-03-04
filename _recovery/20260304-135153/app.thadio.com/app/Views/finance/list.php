<?php
/** @var array $rows */
/** @var array $filters */
/** @var array $summary */
/** @var array $overdue */
/** @var array $salesSummary */
/** @var array $categoryOptions */
/** @var array $vendorOptions */
/** @var array $bankAccountOptions */
/** @var array $paymentTerminalOptions */
/** @var array $paymentMethodOptions */
/** @var array|null $statementSummary */
/** @var string $searchQuery */
/** @var array<string, string> $columnFilters */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, int> $perPageOptions */
/** @var int $totalRows */
/** @var int $totalPages */
/** @var string $sortKey */
/** @var string $sortDir */
/** @var array $errors */
/** @var string $success */
/** @var array $typeOptions */
/** @var array $statusOptions */
/** @var callable $esc */
?>
<?php
  $canCreate = userCan('finance_entries.create');
  $canEdit = userCan('finance_entries.edit');
  $canDelete = userCan('finance_entries.delete');
  $filters = $filters ?? [];
  $typeOptions = $typeOptions ?? [];
  $statusOptions = $statusOptions ?? [];
  $summary = $summary ?? [];
  $overdue = $overdue ?? [];
  $bankAccountOptions = $bankAccountOptions ?? [];
  $paymentTerminalOptions = $paymentTerminalOptions ?? [];
  $paymentMethodOptions = $paymentMethodOptions ?? [];
  $statementSummary = $statementSummary ?? null;
  $searchQuery = $searchQuery ?? (string) ($filters['search'] ?? '');
  $columnFilters = $columnFilters ?? [];
  $page = max(1, (int) ($page ?? 1));
  $perPage = max(1, (int) ($perPage ?? 100));
  $perPageOptions = $perPageOptions ?? [50, 100, 200];
  $totalRows = max(0, (int) ($totalRows ?? 0));
  $totalPages = max(1, (int) ($totalPages ?? 1));
  $sortKey = $sortKey ?? 'due';
  $sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $rangeStart = $totalRows > 0 ? (($page - 1) * $perPage + 1) : 0;
  $rangeEnd = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

  $buildBaseQuery = function () use ($filters, $searchQuery, $perPage, $sortKey, $sortDir, $columnFilters): array {
      $query = ['page' => 1, 'per_page' => $perPage];
      $numericParams = ['category_id', 'bank_account_id', 'payment_terminal_id', 'payment_method_id'];
      foreach (['type', 'status', 'category_id', 'supplier_pessoa_id', 'bank_account_id', 'payment_terminal_id', 'payment_method_id', 'due_from', 'due_to', 'paid_from', 'paid_to'] as $param) {
          $value = $filters[$param] ?? '';
          if (in_array($param, $numericParams, true)) {
              if ((int) $value <= 0) {
                  continue;
              }
              $query[$param] = (int) $value;
              continue;
          }
          if ((string) $value === '') {
              continue;
          }
          $query[$param] = (string) $value;
      }
      if ($searchQuery !== '') {
          $query['q'] = $searchQuery;
          $query['search'] = $searchQuery;
      }
      if ($sortKey !== '') {
          $query['sort_key'] = $sortKey;
          $query['sort'] = $sortKey;
      }
      if ($sortDir !== '') {
          $query['sort_dir'] = strtolower($sortDir);
          $query['dir'] = strtolower($sortDir);
      }
      foreach ($columnFilters as $param => $value) {
          if ((string) $value === '') {
              continue;
          }
          $query[$param] = $value;
      }
      return $query;
  };

  $buildPageLink = function (int $targetPage) use ($buildBaseQuery): string {
      $query = $buildBaseQuery();
      $query['page'] = $targetPage;
      return 'financeiro-list.php?' . http_build_query($query);
  };
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Financeiro</h1>
    <div class="subtitle">Controle contas a pagar/receber e pagamentos.</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($canCreate): ?>
      <a class="btn primary" href="financeiro-cadastro.php">Novo lançamento</a>
    <?php endif; ?>
    <a class="btn ghost" href="financeiro-categoria-list.php">Categorias</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));margin:16px 0;">
  <div class="order-summary-card">
    <div class="order-summary-title">A receber</div>
    <div class="order-summary-value">R$ <?php echo number_format((float) ($summary['receber']['open_total'] ?? 0), 2, ',', '.'); ?></div>
    <div class="order-summary-meta">
      Recebido: R$ <?php echo number_format((float) ($summary['receber']['paid_total'] ?? 0), 2, ',', '.'); ?> ·
      Abertos: <?php echo (int) ($summary['receber']['open_count'] ?? 0); ?>
    </div>
  </div>
  <div class="order-summary-card">
    <div class="order-summary-title">A pagar</div>
    <div class="order-summary-value">R$ <?php echo number_format((float) ($summary['pagar']['open_total'] ?? 0), 2, ',', '.'); ?></div>
    <div class="order-summary-meta">
      Pago: R$ <?php echo number_format((float) ($summary['pagar']['paid_total'] ?? 0), 2, ',', '.'); ?> ·
      Abertos: <?php echo (int) ($summary['pagar']['open_count'] ?? 0); ?>
    </div>
  </div>
  <div class="order-summary-card">
    <div class="order-summary-title">Em atraso</div>
    <div class="order-summary-value">R$ <?php echo number_format((float) ($overdue['pagar']['total_amount'] ?? 0), 2, ',', '.'); ?></div>
    <div class="order-summary-meta">
      A pagar: <?php echo (int) ($overdue['pagar']['total_entries'] ?? 0); ?> lanç. ·
      A receber: R$ <?php echo number_format((float) ($overdue['receber']['total_amount'] ?? 0), 2, ',', '.'); ?>
      (<?php echo (int) ($overdue['receber']['total_entries'] ?? 0); ?> lanç.)
    </div>
  </div>
  <div class="order-summary-card">
    <div class="order-summary-title">Vendas</div>
    <?php if (!empty($salesSummary['connected'])): ?>
      <div class="order-summary-value">R$ <?php echo number_format((float) ($salesSummary['paid_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="order-summary-meta">
        Recebidas: <?php echo (int) ($salesSummary['paid_orders'] ?? 0); ?> ·
        A receber: R$ <?php echo number_format((float) ($salesSummary['pending_total'] ?? 0), 2, ',', '.'); ?>
      </div>
    <?php else: ?>
      <div class="order-summary-value">—</div>
      <div class="order-summary-meta">Sem conexao com dados.</div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($statementSummary)): ?>
  <div class="order-summary-card" style="margin:16px 0;">
    <div class="order-summary-title">Extrato filtrado</div>
    <div class="order-summary-meta" style="margin-bottom:8px;">
      <?php echo $esc((string) ($statementSummary['scope'] ?? '')); ?>
    </div>
    <div class="order-summary-value">Saldo: R$ <?php echo number_format((float) ($statementSummary['balance'] ?? 0), 2, ',', '.'); ?></div>
    <div class="order-summary-meta">
      Créditos: R$ <?php echo number_format((float) ($statementSummary['credits'] ?? 0), 2, ',', '.'); ?> ·
      Débitos: R$ <?php echo number_format((float) ($statementSummary['debits'] ?? 0), 2, ',', '.'); ?> ·
      Movimentos pagos: <?php echo (int) ($statementSummary['movement_count'] ?? 0); ?>
    </div>
    <div class="order-summary-meta" style="margin-top:4px;">
      A receber pendente: R$ <?php echo number_format((float) ($statementSummary['pending_receivable'] ?? 0), 2, ',', '.'); ?> ·
      A pagar pendente: R$ <?php echo number_format((float) ($statementSummary['pending_payable'] ?? 0), 2, ',', '.'); ?>
    </div>
  </div>
<?php endif; ?>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
  <input type="hidden" name="page" value="1">
  <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
  <?php if ($searchQuery !== ''): ?>
    <input type="hidden" name="q" value="<?php echo $esc($searchQuery); ?>">
    <input type="hidden" name="search" value="<?php echo $esc($searchQuery); ?>">
  <?php endif; ?>
  <?php if ($sortKey !== ''): ?>
    <input type="hidden" name="sort_key" value="<?php echo $esc($sortKey); ?>">
    <input type="hidden" name="sort" value="<?php echo $esc($sortKey); ?>">
  <?php endif; ?>
  <?php if ($sortDir !== ''): ?>
    <input type="hidden" name="sort_dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
    <input type="hidden" name="dir" value="<?php echo $esc(strtolower($sortDir)); ?>">
  <?php endif; ?>
  <?php foreach ($columnFilters as $param => $value): ?>
    <input type="hidden" name="<?php echo $esc($param); ?>" value="<?php echo $esc($value); ?>">
  <?php endforeach; ?>
  <select name="type" aria-label="Filtrar tipo">
    <option value="">Todos os tipos</option>
    <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
      <option value="<?php echo $esc($typeKey); ?>" <?php echo ($filters['type'] ?? '') === $typeKey ? 'selected' : ''; ?>>
        <?php echo $esc($typeLabel); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="status" aria-label="Filtrar status">
    <option value="">Todos os status</option>
    <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
      <option value="<?php echo $esc($statusKey); ?>" <?php echo ($filters['status'] ?? '') === $statusKey ? 'selected' : ''; ?>>
        <?php echo $esc($statusLabel); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="category_id" aria-label="Filtrar categoria">
    <option value="">Todas as categorias</option>
    <?php foreach ($categoryOptions as $category): ?>
      <?php $cid = (int) ($category['id'] ?? 0); ?>
      <option value="<?php echo $cid; ?>" <?php echo (int) ($filters['category_id'] ?? 0) === $cid ? 'selected' : ''; ?>>
        <?php echo $esc($category['name'] ?? ''); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input
    type="search"
    name="supplier_pessoa_id"
    list="finance-supplier-suggestions"
    aria-label="Filtrar fornecedor"
    placeholder="Digite nome, código ou ID"
    value="<?php echo $esc((string) ($filters['supplier_pessoa_id'] ?? '')); ?>"
    style="min-width:280px;">
  <datalist id="finance-supplier-suggestions">
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
  <select name="bank_account_id" aria-label="Filtrar conta">
    <option value="">Todas as contas</option>
    <?php foreach ($bankAccountOptions as $account): ?>
      <?php
        $accountId = (int) ($account['id'] ?? 0);
        $bankName = trim((string) ($account['bank_name'] ?? 'Conta'));
        $accountLabel = trim((string) ($account['label'] ?? ''));
        $fullLabel = $bankName . ($accountLabel !== '' ? ' · ' . $accountLabel : '');
      ?>
      <option value="<?php echo $accountId; ?>" <?php echo (int) ($filters['bank_account_id'] ?? 0) === $accountId ? 'selected' : ''; ?>>
        <?php echo $esc($fullLabel); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="payment_terminal_id" aria-label="Filtrar maquininha">
    <option value="">Todas as maquininhas</option>
    <?php foreach ($paymentTerminalOptions as $terminal): ?>
      <?php $terminalId = (int) ($terminal['id'] ?? 0); ?>
      <option value="<?php echo $terminalId; ?>" <?php echo (int) ($filters['payment_terminal_id'] ?? 0) === $terminalId ? 'selected' : ''; ?>>
        <?php echo $esc((string) ($terminal['name'] ?? ('Maquininha #' . $terminalId))); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="payment_method_id" aria-label="Filtrar meio de recebimento">
    <option value="">Todos os meios</option>
    <?php foreach ($paymentMethodOptions as $method): ?>
      <?php $methodId = (int) ($method['id'] ?? 0); ?>
      <option value="<?php echo $methodId; ?>" <?php echo (int) ($filters['payment_method_id'] ?? 0) === $methodId ? 'selected' : ''; ?>>
        <?php echo $esc((string) ($method['name'] ?? ('Meio #' . $methodId))); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="due_from" value="<?php echo $esc((string) ($filters['due_from'] ?? '')); ?>" aria-label="Vencimento de">
  <input type="date" name="due_to" value="<?php echo $esc((string) ($filters['due_to'] ?? '')); ?>" aria-label="Vencimento até">
  <input type="date" name="paid_from" value="<?php echo $esc((string) ($filters['paid_from'] ?? '')); ?>" aria-label="Pagamento de">
  <input type="date" name="paid_to" value="<?php echo $esc((string) ($filters['paid_to'] ?? '')); ?>" aria-label="Pagamento até">
  <button class="btn ghost" type="submit">Filtrar</button>
  <a class="btn ghost" href="financeiro-list.php">Limpar</a>
</form>

<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em lançamentos" value="<?php echo $esc($searchQuery); ?>">
    <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
  </div>
  <form method="get" id="perPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php $baseQuery = $buildBaseQuery(); ?>
    <?php foreach ($baseQuery as $param => $value): ?>
      <?php if ($param === 'per_page'): ?>
        <?php continue; ?>
      <?php endif; ?>
      <input type="hidden" name="<?php echo $esc((string) $param); ?>" value="<?php echo $esc((string) $value); ?>">
    <?php endforeach; ?>
    <label for="perPage" style="font-size:13px;color:var(--muted);">Itens por pagina</label>
    <select id="perPage" name="per_page">
      <?php foreach ($perPageOptions as $option): ?>
        <option value="<?php echo (int) $option; ?>" <?php echo $perPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $rangeStart; ?>-<?php echo $rangeEnd; ?> de <?php echo $totalRows; ?></span>
  </form>
</div>

<div style="overflow:auto;">
  <table data-table="interactive" data-filter-mode="server">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="type" aria-sort="none">Tipo</th>
        <th data-sort-key="description" aria-sort="none">Descrição</th>
        <th data-sort-key="category" aria-sort="none">Categoria</th>
        <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
        <th data-sort-key="amount" aria-sort="none">Valor</th>
        <th data-sort-key="due" aria-sort="none">Vencimento</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th data-sort-key="paid" aria-sort="none">Pago</th>
        <th data-sort-key="payment" aria-sort="none">Pagamento</th>
        <th data-sort-key="origin" aria-sort="none">Origem</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" data-query-param="filter_id" value="<?php echo $esc($columnFilters['filter_id'] ?? ''); ?>" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="type" data-query-param="filter_type" value="<?php echo $esc($columnFilters['filter_type'] ?? ''); ?>" placeholder="Filtrar tipo" aria-label="Filtrar tipo"></th>
        <th><input type="search" data-filter-col="description" data-query-param="filter_description" value="<?php echo $esc($columnFilters['filter_description'] ?? ''); ?>" placeholder="Filtrar descricao" aria-label="Filtrar descricao"></th>
        <th><input type="search" data-filter-col="category" data-query-param="filter_category" value="<?php echo $esc($columnFilters['filter_category'] ?? ''); ?>" placeholder="Filtrar categoria" aria-label="Filtrar categoria"></th>
        <th><input type="search" data-filter-col="supplier" data-query-param="filter_supplier" value="<?php echo $esc($columnFilters['filter_supplier'] ?? ''); ?>" placeholder="Filtrar fornecedor" aria-label="Filtrar fornecedor"></th>
        <th><input type="search" data-filter-col="amount" data-query-param="filter_amount" value="<?php echo $esc($columnFilters['filter_amount'] ?? ''); ?>" placeholder="Filtrar valor" aria-label="Filtrar valor"></th>
        <th><input type="search" data-filter-col="due" data-query-param="filter_due" value="<?php echo $esc($columnFilters['filter_due'] ?? ''); ?>" placeholder="Filtrar vencimento" aria-label="Filtrar vencimento"></th>
        <th><input type="search" data-filter-col="status" data-query-param="filter_status" value="<?php echo $esc($columnFilters['filter_status'] ?? ''); ?>" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th><input type="search" data-filter-col="paid" data-query-param="filter_paid" value="<?php echo $esc($columnFilters['filter_paid'] ?? ''); ?>" placeholder="Filtrar pago" aria-label="Filtrar pago"></th>
        <th><input type="search" data-filter-col="payment" data-query-param="filter_payment" value="<?php echo $esc($columnFilters['filter_payment'] ?? ''); ?>" placeholder="Filtrar pagamento" aria-label="Filtrar pagamento"></th>
        <th><input type="search" data-filter-col="origin" data-query-param="filter_origin" value="<?php echo $esc($columnFilters['filter_origin'] ?? ''); ?>" placeholder="Filtrar origem" aria-label="Filtrar origem"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="12">Nenhum lançamento encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $typeKey = strtolower((string) ($row['type'] ?? ''));
            $typeLabel = $typeOptions[$typeKey] ?? $typeKey;
            $statusKey = strtolower((string) ($row['status'] ?? ''));
            $statusLabel = $statusOptions[$statusKey] ?? $statusKey;
            $dueDate = (string) ($row['due_date'] ?? '');
            $paidAt = (string) ($row['paid_at'] ?? '');
            $isOverdue = $dueDate !== ''
                && in_array($statusKey, ['pendente', 'parcial'], true)
                && strtotime($dueDate) < strtotime(date('Y-m-d'));
            if ($isOverdue) {
                $statusLabel .= ' · atrasado';
            }
            $amount = (float) ($row['amount'] ?? 0);
            $paidAmount = $row['paid_amount'] !== null ? (float) $row['paid_amount'] : null;
            $paymentParts = [];
            if (!empty($row['payment_method_name'])) {
                $paymentParts[] = $row['payment_method_name'];
            }
            $bankLabel = '';
            if (!empty($row['bank_account_label'])) {
                $bankLabel = (string) $row['bank_account_label'];
                if (!empty($row['bank_name'])) {
                    $bankLabel = $row['bank_name'] . ' · ' . $bankLabel;
                }
                $paymentParts[] = $bankLabel;
            }
            if (!empty($row['payment_terminal_name'])) {
                $paymentParts[] = $row['payment_terminal_name'];
            }
            $paymentLabel = $paymentParts ? implode(' · ', $paymentParts) : '-';

            $originParts = [];
            if (!empty($row['order_id'])) {
                $originParts[] = 'Pedido #' . (int) $row['order_id'];
            }
            if (!empty($row['lot_id'])) {
                $lotLabel = $row['lot_name'] ?? ('Lote #' . (int) $row['lot_id']);
                $originParts[] = $lotLabel;
            }
            $originLabel = $originParts ? implode(' · ', $originParts) : '-';

            $rowLink = $canEdit ? 'financeiro-cadastro.php?id=' . (int) $row['id'] : '';
          ?>
          <tr<?php echo $rowLink !== '' ? ' data-row-href="' . $esc($rowLink) . '"' : ''; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($typeLabel); ?>"><?php echo $esc($typeLabel); ?></td>
            <td data-value="<?php echo $esc($row['description'] ?? ''); ?>"><?php echo $esc($row['description'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($row['category_name'] ?? ''); ?>"><?php echo $esc($row['category_name'] ?? '-'); ?></td>
            <td data-value="<?php echo $esc($row['supplier_name'] ?? ''); ?>"><?php echo $esc($row['supplier_name'] ?? '-'); ?></td>
            <td data-value="<?php echo $amount; ?>">R$ <?php echo number_format($amount, 2, ',', '.'); ?></td>
            <td data-value="<?php echo $esc($dueDate); ?>">
              <?php echo $dueDate !== '' ? $esc(date('d/m/Y', strtotime($dueDate))) : '-'; ?>
            </td>
            <td data-value="<?php echo $esc($statusLabel); ?>">
              <span class="pill"><?php echo $esc($statusLabel); ?></span>
            </td>
            <td data-value="<?php echo $paidAmount !== null ? $paidAmount : ''; ?>">
              <?php if ($paidAmount !== null): ?>
                R$ <?php echo number_format($paidAmount, 2, ',', '.'); ?>
                <?php if ($paidAt !== ''): ?>
                  <div style="color:var(--muted);font-size:12px;">
                    <?php echo $esc(date('d/m/Y', strtotime($paidAt))); ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td data-value="<?php echo $esc($paymentLabel); ?>"><?php echo $esc($paymentLabel); ?></td>
            <td data-value="<?php echo $esc($originLabel); ?>"><?php echo $esc($originLabel); ?></td>
            <td class="col-actions">
              <div class="actions">
                <?php if ($canEdit): ?>
                  <?php if ($statusKey !== 'pago'): ?>
                    <form method="post" onsubmit="return confirm('Marcar este lançamento como pago?');" style="margin:0;">
                      <input type="hidden" name="mark_paid_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn success" type="submit" aria-label="Marcar como pago" title="Marcar como pago">
                        <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" onsubmit="return confirm('Marcar este lançamento como pendente?');" style="margin:0;">
                      <input type="hidden" name="mark_unpaid_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="icon-btn" type="submit" aria-label="Marcar como pendente" title="Marcar como pendente">
                        <svg aria-hidden="true"><use href="#icon-x"></use></svg>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <form method="post" onsubmit="return confirm('Excluir este lançamento?');" style="margin:0;">
                    <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                      <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if (!$canEdit && !$canDelete): ?>
                  <span class="muted">Sem ações</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
  <span style="color:var(--muted);font-size:13px;">Pagina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($page > 1): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink(1)); ?>">Primeira</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page - 1)); ?>">Anterior</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($page + 1)); ?>">Proxima</a>
      <a class="btn ghost" href="<?php echo $esc($buildPageLink($totalPages)); ?>">Ultima</a>
    <?php else: ?>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Proxima</span>
      <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Ultima</span>
    <?php endif; ?>
  </div>
</div>

<script>
  (function () {
    const perPage = document.getElementById('perPage');
    const form = document.getElementById('perPageForm');
    if (perPage && form) {
      perPage.addEventListener('change', () => form.submit());
    }
  })();
</script>
