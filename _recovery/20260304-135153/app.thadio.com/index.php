<?php

require __DIR__ . '/bootstrap.php';

use App\Repositories\BankAccountRepository;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\OrderReturnRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\DashboardLayoutRepository;
use App\Repositories\PaymentTerminalRepository;
use App\Repositories\TimeEntryRepository;
use App\Repositories\UserRepository;
use App\Services\DashboardService;
use App\Services\ProductImageService;
use App\Services\FinanceEntryService;
use App\Services\OrderService;
use App\Services\OrderReturnService;
use App\Services\VoucherAccountService;
use App\Models\TimeEntry;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'dashboard.view');
$dashboardSuccess = '';
$dashboardErrors = [];
$user = currentUser() ?? [];
$paymentMethodRepo = $pdo ? new PaymentMethodRepository($pdo) : null;
$paymentMethods = $paymentMethodRepo ? $paymentMethodRepo->active() : [];
$bankAccountRepo = $pdo ? new BankAccountRepository($pdo) : null;
$bankAccounts = $bankAccountRepo ? $bankAccountRepo->active() : [];
$paymentTerminalRepo = $pdo ? new PaymentTerminalRepository($pdo) : null;
$paymentTerminals = $paymentTerminalRepo ? $paymentTerminalRepo->active() : [];
$userRepo = $pdo ? new UserRepository($pdo) : null;
$canEditFinanceEntries = userCan('finance_entries.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_done_id'])) {
  requirePermission($pdo, 'order_returns.refund');
  $returnId = (int) $_POST['refund_done_id'];
  if ($returnId <= 0) {
    $dashboardErrors[] = 'Ressarcimento inválido para concluir.';
  } elseif (!$pdo) {
    $dashboardErrors[] = 'Sem conexão com o banco para concluir ressarcimento.';
  } else {
    $returns = new OrderReturnRepository($pdo);
    $returnRow = $returns->find($returnId);
    if (!$returnRow) {
      $dashboardErrors[] = 'Devolução não encontrada para concluir ressarcimento.';
    } elseif (($returnRow['status'] ?? '') === 'cancelled') {
      $dashboardErrors[] = 'Devolução cancelada não pode ter ressarcimento concluído.';
    } elseif (($returnRow['refund_status'] ?? '') === 'done') {
      $dashboardErrors[] = 'Ressarcimento já está marcado como feito.';
    } else {
      $voucherId = isset($returnRow['voucher_account_id']) ? (int) $returnRow['voucher_account_id'] : null;
      $returns->updateRefundStatus($returnId, 'done', $voucherId ?: null);
      $dashboardSuccess = 'Ressarcimento marcado como feito.';
    }
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeclock_type'])) {
  requirePermission($pdo, 'timeclock.create');
  if (!$pdo) {
    $dashboardErrors[] = 'Sem conexão com o banco para registrar ponto.';
  } elseif (!$userId = (int) ($user['id'] ?? 0)) {
    $dashboardErrors[] = 'Usuário não encontrado para registrar ponto.';
  } else {
    $type = strtolower(trim((string) $_POST['timeclock_type']));
    if (!in_array($type, ['entrada', 'saida'], true)) {
      $dashboardErrors[] = 'Tipo de ponto inválido.';
    } else {
      $entries = new TimeEntryRepository($pdo);
      $lastEntry = $entries->lastForUser($userId);
      $allowedTypes = [];
      if (!$lastEntry || ($lastEntry['status'] ?? '') === 'rejeitado') {
        $allowedTypes = ['entrada'];
      } elseif (($lastEntry['tipo'] ?? '') === 'entrada') {
        $allowedTypes = ['saida'];
      } else {
        $allowedTypes = ['entrada'];
      }
      if (!in_array($type, $allowedTypes, true)) {
        $dashboardErrors[] = 'Sequência inválida. Registre o próximo ponto sugerido.';
      } else {
        $entry = new TimeEntry();
        $entry->userId = $userId;
        $entry->type = $type;
        $entry->recordedAt = date('Y-m-d H:i:s');
        $entry->status = 'pendente';
        $entries->create($entry);
        $dashboardSuccess = 'Ponto registrado com sucesso.';
      }
    }
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_payable_mark_paid'])) {
  requirePermission($pdo, 'finance_entries.edit');
  if (!$pdo) {
    $dashboardErrors[] = 'Sem conexão com o banco para registrar pagamento.';
  } else {
    $entryId = isset($_POST['dashboard_payable_mark_paid']) ? (int) $_POST['dashboard_payable_mark_paid'] : 0;
    if ($entryId <= 0) {
      $dashboardErrors[] = 'Despesa inválida para marcar como paga.';
    } else {
      $entryRepo = new FinanceEntryRepository($pdo);
      $entry = $entryRepo->find($entryId);
      if (!$entry) {
        $dashboardErrors[] = 'Despesa não encontrada.';
      } else {
        $selectedMethodId = isset($_POST['payment_method_id']) ? (int) $_POST['payment_method_id'] : 0;
        $selectedMethodId = $selectedMethodId > 0 ? $selectedMethodId : null;
        $selectedBankAccountId = isset($_POST['bank_account_id']) ? (int) $_POST['bank_account_id'] : 0;
        $selectedBankAccountId = $selectedBankAccountId > 0 ? $selectedBankAccountId : null;
        $selectedTerminalId = isset($_POST['payment_terminal_id']) ? (int) $_POST['payment_terminal_id'] : 0;
        $selectedTerminalId = $selectedTerminalId > 0 ? $selectedTerminalId : null;

        $methodMap = [];
        foreach ($paymentMethods as $method) {
          $methodId = (int) ($method['id'] ?? 0);
          if ($methodId > 0) {
            $methodMap[$methodId] = $method;
          }
        }

        $effectiveMethodId = $selectedMethodId ?? $entry->paymentMethodId;
        $effectiveBankId = $selectedBankAccountId ?? $entry->bankAccountId;
        $effectiveTerminalId = $selectedTerminalId ?? $entry->paymentTerminalId;
        $methodRow = $effectiveMethodId !== null ? ($methodMap[$effectiveMethodId] ?? null) : null;

        $hasPayableErrors = false;
        if ($selectedMethodId !== null && $methodRow === null) {
          $dashboardErrors[] = 'Método de pagamento inválido.';
          $hasPayableErrors = true;
        }
        if ($methodRow) {
          if (!empty($methodRow['requires_bank_account']) && !$effectiveBankId) {
            $dashboardErrors[] = 'Método exige conta bancária. Escolha uma conta válida.';
            $hasPayableErrors = true;
          }
          if (!empty($methodRow['requires_terminal']) && !$effectiveTerminalId) {
            $dashboardErrors[] = 'Método exige maquininha. Escolha uma maquininha válida.';
            $hasPayableErrors = true;
          }
        }

        if (!$hasPayableErrors) {
          if ($selectedMethodId !== null) {
            $entry->paymentMethodId = $selectedMethodId;
          }
          if ($selectedBankAccountId !== null) {
            $entry->bankAccountId = $selectedBankAccountId;
          }
          if ($selectedTerminalId !== null) {
            $entry->paymentTerminalId = $selectedTerminalId;
          }
          $entry->status = 'pago';
          $entry->paidAt = date('Y-m-d H:i:s');
          $entry->paidAmount = $entry->amount;
          $entryRepo->save($entry);
          $dashboardSuccess = 'Despesa marcada como paga.';
        }
      }
    }
  }
}
$service = new DashboardService($pdo);
$imageUploader = new ProductImageService();
$productImageInfo = $imageUploader->info();
$productImageAccept = htmlspecialchars((string) ($productImageInfo['accept'] ?? ''), ENT_QUOTES, 'UTF-8');
$insights = $service->insights();
$customerEngagement = $insights['customerEngagement'] ?? null;
$profileName = $user['profile']['name'] ?? ($user['role'] ?? 'Usuário');
$isAdminProfile = in_array($profileName, ['Administrador', 'Administrador de Sistema'], true);
$isManagerProfile = in_array($profileName, ['Gestor'], true);
$adminLayoutOwner = (int) (getenv('ADMIN_INITIAL_LAYOUT_USER_ID') ?: 0);
$managerLayoutOwnerId = 0;
$managerLayoutEmail = trim((string) (getenv('GESTOR_LAYOUT_EMAIL') ?: 'gestor.teste.sistemico@retratoapp.com'));
if ($isManagerProfile && $userRepo && $managerLayoutEmail !== '') {
  $templateManager = $userRepo->findByEmail($managerLayoutEmail);
  $managerLayoutOwnerId = $templateManager && $templateManager->id ? (int) $templateManager->id : 0;
}
$dashboardCopy = [
  'Administrador' => [
    'title' => '',
    'subtitle' => '',
  ],
  'Gestor' => [
    'title' => 'Visão de Gestão',
    'subtitle' => 'Indicadores chave para decidir rápido e priorizar ações do time.',
  ],
  'Editor' => [
    'title' => 'Visão de Catálogo',
    'subtitle' => 'Acompanhe disponibilidade, coleções e pontos de atenção do catálogo.',
  ],
  'Colaborador' => [
    'title' => 'Visão Operacional',
    'subtitle' => 'Resumo rápido do que precisa de atenção na rotina.',
  ],
];
$dashboardMeta = $dashboardCopy[$profileName] ?? [
  'title' => 'Visão Geral',
  'subtitle' => 'Painel com os indicadores mais relevantes para o seu perfil.',
];
$dashboardTitle = trim((string) ($dashboardMeta['title'] ?? ''));
$dashboardSubtitle = trim((string) ($dashboardMeta['subtitle'] ?? ''));

$dashboardActions = [
  'widget_overview_highlights',
  'widget_stock_value',
  'widget_margin',
  'widget_active_products',
  'widget_active_customers',
  'widget_suppliers_dependency',
  'widget_inventory_attention',
  'widget_collections_strength',
  'widget_inventory_missing_photos',
  'widget_inventory_missing_costs',
  'widget_inventory_unpublished',
  'widget_inventory_old_consigned',
  'widget_timeclock',
  'widget_calendar',
  'widget_ops_bags',
  'widget_ops_consignments',
  'widget_ops_deliveries',
  'widget_ops_refunds',
  'widget_ops_credits',
  'widget_ops_consign_vouchers',
  'widget_finance_payables',
  'widget_sales_performance',
  'quick_links',
];
$hasDashboardConfig = false;
foreach ($dashboardActions as $action) {
  if (userCan('dashboard.' . $action)) {
    $hasDashboardConfig = true;
    break;
  }
}
$defaultDashboardAccess = !$hasDashboardConfig;

$canWidgetOverviewHighlights = $defaultDashboardAccess || userCan('dashboard.widget_overview_highlights');
$canWidgetStockValue = $defaultDashboardAccess || userCan('dashboard.widget_stock_value');
$canWidgetMargin = $defaultDashboardAccess || userCan('dashboard.widget_margin');
$canWidgetActiveProducts = $defaultDashboardAccess || userCan('dashboard.widget_active_products');
$canWidgetActiveCustomers = $defaultDashboardAccess || userCan('dashboard.widget_active_customers');
$showStatsGrid = $canWidgetStockValue || $canWidgetMargin || $canWidgetActiveProducts || $canWidgetActiveCustomers;
$canWidgetSalesPerformance = $defaultDashboardAccess || userCan('dashboard.widget_sales_performance');
$canWidgetSuppliersDependency = $defaultDashboardAccess || userCan('dashboard.widget_suppliers_dependency');
$canWidgetInventoryAttention = $defaultDashboardAccess || userCan('dashboard.widget_inventory_attention');
$canWidgetCollectionsStrength = $defaultDashboardAccess || userCan('dashboard.widget_collections_strength');

$canWidgetMissingPhotos = ($defaultDashboardAccess || userCan('dashboard.widget_inventory_missing_photos')) && userCan('products.view');
$canWidgetUnpublished = ($defaultDashboardAccess || userCan('dashboard.widget_inventory_unpublished')) && userCan('products.view');
$canWidgetMissingCost = ($defaultDashboardAccess || userCan('dashboard.widget_inventory_missing_costs')) && userCan('products.view');
$canWidgetOldConsigned = ($defaultDashboardAccess || userCan('dashboard.widget_inventory_old_consigned')) && userCan('products.view');
$canEditProductCost = userCan('products.edit');
$canBulkPublish = userCan('products.bulk_publish');

$dashboardLayoutRepo = $pdo ? new DashboardLayoutRepository($pdo) : null;
$canCustomizeLayout = userCan('dashboard.layout_customize');
$layoutColumns = 3;
$layoutItems = [];
if ($dashboardLayoutRepo && ($userId = (int) ($user['id'] ?? 0)) > 0) {
  $savedLayout = $dashboardLayoutRepo->findByUserId($userId);
  if (!$savedLayout && $isAdminProfile && $adminLayoutOwner > 0 && $adminLayoutOwner !== $userId) {
    $savedLayout = $dashboardLayoutRepo->findByUserId($adminLayoutOwner);
  }
  if (!$savedLayout && $isManagerProfile && $managerLayoutOwnerId > 0 && $managerLayoutOwnerId !== $userId) {
    $savedLayout = $dashboardLayoutRepo->findByUserId($managerLayoutOwnerId);
  }
  if (is_array($savedLayout)) {
    $layoutColumns = max(1, min(6, (int) ($savedLayout['columns'] ?? $layoutColumns)));
    $layoutItems = is_array($savedLayout['items'] ?? null) ? array_values($savedLayout['items']) : [];
  }
}
$layoutState = ['columns' => $layoutColumns, 'items' => $layoutItems];
$defaultWidgetSpans = [
  'widget_sales_performance' => $layoutState['columns'],
  'widget_stock_value' => 1,
  'widget_margin' => 1,
  'widget_active_products' => 1,
  'widget_active_customers' => 1,
  'widget_suppliers_dependency' => 1,
  'widget_inventory_attention' => 1,
  'widget_collections_strength' => 1,
  'widget_inventory_missing_photos' => 1,
  'widget_inventory_missing_costs' => 1,
  'widget_inventory_old_consigned' => 1,
  'widget_inventory_unpublished' => 1,
  'widget_ops_consign_vouchers' => 1,
  'widget_finance_payables' => 2,
  'widget_timeclock' => 1,
  'widget_calendar' => 1,
  'widget_ops_bags' => 1,
  'widget_ops_consignments' => 1,
  'widget_ops_deliveries' => 1,
  'widget_ops_refunds' => 1,
  'widget_ops_credits' => 1,
  'quick_links' => 1,
];

$canTimeclockView = userCan('timeclock.view');
$canTimeclockCreate = userCan('timeclock.create');
$canTimeclockWidget = ($defaultDashboardAccess || userCan('dashboard.widget_timeclock')) && ($canTimeclockView || $canTimeclockCreate);

$canCalendarBase = userCan('holidays.view');
$canCalendarWidget = ($defaultDashboardAccess || userCan('dashboard.widget_calendar')) && $canCalendarBase;
$canConsignVouchersWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_consign_vouchers')) && userCan('voucher_accounts.view');
$canPayablesWidget = ($defaultDashboardAccess || userCan('dashboard.widget_finance_payables')) && userCan('finance_entries.view');

$canBagsWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_bags')) && userCan('bags.view');
$canConsignmentsWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_consignments')) && userCan('consignments.view');
$canConsignmentEdit = userCan('consignments.edit');
$canDeliveriesWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_deliveries')) && userCan('orders.view');
$canRefundsWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_refunds')) && userCan('order_returns.view');
$canRefundUpdate = userCan('order_returns.refund');
$canCreditsWidget = ($defaultDashboardAccess || userCan('dashboard.widget_ops_credits')) && userCan('voucher_accounts.view');
$canBatchIntake = userCan('products.batch_intake');
$canQuickLinks = $defaultDashboardAccess || userCan('dashboard.quick_links');
$hasOpsWidgets = $canBagsWidget || $canConsignmentsWidget || $canDeliveriesWidget || $canRefundsWidget || $canCreditsWidget;
$showInsightsGrid = $canWidgetSuppliersDependency || $canWidgetInventoryAttention || $canWidgetCollectionsStrength;
$showWidgetGrid = $canWidgetMissingPhotos || $canWidgetUnpublished || $canWidgetMissingCost || $canWidgetOldConsigned || $canConsignVouchersWidget || $canPayablesWidget || $canTimeclockWidget || $canCalendarWidget;
$hasLayoutCards = $showStatsGrid || $canWidgetSalesPerformance || $showInsightsGrid || $showWidgetGrid || $hasOpsWidgets || $canQuickLinks;

$productActivity = $insights['productActivity'] ?? [
  'availability' => ['total' => 0, 'statuses' => []],
  'periods' => [],
  'months' => [],
];
$productActivityAvailability = $productActivity['availability'] ?? ['total' => 0, 'statuses' => []];
$productActivityPeriods = array_values($productActivity['periods'] ?? []);
$productActivityMonths = array_values($productActivity['months'] ?? []);
$productActivityChartMonths = $productActivityMonths;
$chartMax = 0;
foreach ($productActivityChartMonths as $month) {
  foreach (['registered', 'sold', 'writeOff'] as $key) {
    $chartMax = max($chartMax, (int) ($month[$key] ?? 0));
  }
}
$chartMax = max($chartMax, 1);
$chartWidth = 320;
$chartHeight = 120;
$seriesPoints = [
  'registered' => [],
  'sold' => [],
  'writeOff' => [],
];
$monthCount = count($productActivityChartMonths);
$spacing = $monthCount > 1 ? $chartWidth / ($monthCount - 1) : $chartWidth;
$seriesKeys = array_keys($seriesPoints);
foreach ($productActivityChartMonths as $index => $month) {
  $x = $monthCount > 1 ? ($spacing * $index) : 0;
  foreach ($seriesKeys as $key) {
    $value = max(0, (int) ($month[$key] ?? 0));
    $normalized = $value / $chartMax;
    $y = $chartHeight - ($normalized * $chartHeight);
    $seriesPoints[$key][] = sprintf('%.2f,%.2f', (float) $x, (float) $y);
  }
}

$topVendor = $insights['topVendors'][0] ?? null;
$stockValue = (float) ($insights['inventory']['potentialValue'] ?? 0);
$topVendorShare = $topVendor && $stockValue > 0 ? ($topVendor['potential_value'] / $stockValue) : 0;

function fmtNumber($value): string {
  return number_format((float) $value, 0, ',', '.');
}

function fmtMoney($value, int $decimals = 0): string {
  return 'R$ ' . number_format((float) $value, $decimals, ',', '.');
}

function fmtPercent(?float $value): string {
  if ($value === null) {
    return '—';
  }
  return number_format($value * 100, 1, ',', '.') . '%';
}

function fmtShortDate(?string $value): string {
  if (!$value) {
    return '—';
  }
  try {
    $date = new \DateTimeImmutable($value);
  } catch (\Throwable $e) {
    return '—';
  }
  return $date->format('d/m/Y');
}

function fmtDuration(int $seconds): string {
  $seconds = max(0, $seconds);
  $hours = intdiv($seconds, 3600);
  $minutes = intdiv($seconds % 3600, 60);
  $remaining = $seconds % 60;
  return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
}

function fmtProductStatus(string $status): string {
  $status = strtolower(trim($status));
  $statusMap = [
    'publish' => 'disponivel',
    'active' => 'disponivel',
    'pending' => 'draft',
    'private' => 'archived',
    'future' => 'draft',
    'trash' => 'archived',
  ];
  $normalized = $statusMap[$status] ?? $status;
  $map = [
    'draft' => 'Rascunho',
    'disponivel' => 'Disponível',
    'reservado' => 'Reservado',
    'esgotado' => 'Esgotado',
    'baixado' => 'Baixado',
    'archived' => 'Arquivado',
  ];
  return $map[$normalized] ?? ($normalized !== '' ? ucfirst($normalized) : '—');
}

function fmtFinanceStatus(string $status): string {
  $map = FinanceEntryService::STATUS_OPTIONS;
  return $map[$status] ?? ucfirst($status);
}

function sensitiveSpan(string $value): string {
  return '<span class="sensitive" data-sensitive>' . $value . '</span>';
}

function timeclockAllowedTypes(?array $lastEntry): array {
  if (!$lastEntry || ($lastEntry['status'] ?? '') === 'rejeitado') {
    return ['entrada'];
  }

  if (($lastEntry['tipo'] ?? '') === 'entrada') {
    return ['saida'];
  }

  return ['entrada'];
}

function resolveOrderCustomerName(array $row): string {
  $billingFirst = trim((string) ($row['billing_first_name'] ?? ''));
  $billingLast = trim((string) ($row['billing_last_name'] ?? ''));
  $billingName = trim($billingFirst . ' ' . $billingLast);
  $shippingFirst = trim((string) ($row['shipping_first_name'] ?? ''));
  $shippingLast = trim((string) ($row['shipping_last_name'] ?? ''));
  $shippingName = trim($shippingFirst . ' ' . $shippingLast);
  $customerName = $billingName;
  if ($customerName === '') {
    $customerName = $shippingName;
  }
  if ($customerName === '') {
    $firstName = trim((string) ($row['customer_first_name'] ?? ''));
    $lastName = trim((string) ($row['customer_last_name'] ?? ''));
    $customerName = trim($firstName . ' ' . $lastName);
  }
  if ($customerName === '') {
    $customerName = trim((string) ($row['customer_display_name'] ?? ''));
  }
  if ($customerName === '') {
    $customerName = trim((string) ($row['billing_email'] ?? ''));
  }
  if ($customerName === '') {
    $customerName = trim((string) ($row['shipping_email'] ?? ''));
  }
  if ($customerName === '') {
    $customerName = trim((string) ($row['customer_email'] ?? ''));
  }
  return $customerName !== '' ? $customerName : 'Convidado';
}

function resolveBagCustomerName(array $row): string {
  $name = trim((string) ($row['customer_name'] ?? ''));
  if ($name === '') {
    $name = trim((string) ($row['customer_email'] ?? ''));
  }
  if ($name === '') {
    $personId = (int) ($row['pessoa_id'] ?? 0);
    $name = $personId > 0 ? 'Cliente #' . $personId : 'Cliente';
  }
  return $name;
}

function resolveVoucherCustomerLabel(array $row): string {
  $personId = (int) ($row['pessoa_id'] ?? 0);
  $customerName = trim((string) ($row['person_name'] ?? ''));
  $customerEmail = trim((string) ($row['person_email'] ?? ''));
  $label = $customerName !== '' ? $customerName : ($personId > 0 ? 'Cliente #' . $personId : 'Cliente');
  if ($customerEmail !== '') {
    $label .= ' - ' . $customerEmail;
  }
  return $label;
}

$openBags = $insights['openBags'] ?? [];
$pendingConsignments = $insights['pendingConsignments'] ?? [];
$pendingDeliveries = $insights['pendingDeliveries'] ?? [];
$pendingRefunds = $insights['pendingRefunds'] ?? [];
$creditBalances = $insights['creditBalances'] ?? [];
$consignmentVoucherTotals = $insights['consignmentVoucherTotals'] ?? [];
$consignmentVoucherTotalAmount = 0.0;
if (!empty($consignmentVoucherTotals)) {
  foreach ($consignmentVoucherTotals as $row) {
    $consignmentVoucherTotalAmount += (float) ($row['total_credit'] ?? 0);
  }
}
$productsWithoutPhotos = $insights['productsWithoutPhotos'] ?? [];
$productsWithPhotosUnpublished = $insights['productsWithPhotosUnpublished'] ?? [];
$productsMissingCost = $insights['productsMissingCost'] ?? [];
$oldConsignedProducts = $insights['oldConsignedProducts'] ?? [];
$payables = $insights['payables'] ?? [];
$payablesRecent = [];
$payablesProjected = [];
$payablesTotalRecent = 0.0;
$payablesTotalProjected = 0.0;
$todayDate = date('Y-m-d');
foreach ($payables as $row) {
  $dueRaw = $row['due_date'] ?? '';
  if ($dueRaw === '') {
    continue;
  }
  $amount = (float) ($row['amount'] ?? 0);
  if ($dueRaw <= $todayDate) {
    $payablesRecent[] = $row;
    $payablesTotalRecent += $amount;
  } else {
    $payablesProjected[] = $row;
    $payablesTotalProjected += $amount;
  }
}
$commemorativeWeek = $insights['commemorativeWeek']['days'] ?? [];

$orderStatusLabels = OrderService::statusOptions();
$fulfillmentStatusLabels = OrderService::fulfillmentStatusOptions();
$refundMethodLabels = OrderReturnService::refundMethodOptions();
$voucherTypeLabels = VoucherAccountService::typeOptions();

$timeclockLastEntry = null;
$timeclockAllowedTypes = [];
$timeclockNextType = '';
$timeclockOpenSeconds = null;
$timeclockOpenSince = null;
$timeclockTodayOpenSeconds = null;

if ($pdo && $canTimeclockWidget && !empty($user['id'])) {
  $timeEntries = new TimeEntryRepository($pdo);
  $userId = (int) $user['id'];
  $timeclockLastEntry = $timeEntries->lastForUser($userId);
  $timeclockAllowedTypes = timeclockAllowedTypes($timeclockLastEntry);
  $timeclockNextType = $timeclockAllowedTypes[0] ?? '';

  // Calcular tempo aberto do dia e o período corrente em aberto
  $todayStart = date('Y-m-d 00:00:00');
  $todayEnd = date('Y-m-d 23:59:59');
  $entriesToday = $timeEntries->list([
    'user_id' => $userId,
    'start' => $todayStart,
    'end' => $todayEnd,
  ]);
  $entriesToday = array_values(array_filter($entriesToday, static function ($row) {
    return ($row['status'] ?? '') !== 'rejeitado';
  }));
  usort($entriesToday, static function ($a, $b) {
    return strcmp((string) ($a['registrado_em'] ?? ''), (string) ($b['registrado_em'] ?? ''));
  });

  $todayTotal = 0;
  $openStart = null;
  foreach ($entriesToday as $entry) {
    $ts = strtotime((string) ($entry['registrado_em'] ?? ''));
    if (!$ts) {
      continue;
    }
    if (($entry['tipo'] ?? '') === 'entrada') {
      $openStart = $ts;
    } elseif (($entry['tipo'] ?? '') === 'saida' && $openStart !== null) {
      $todayTotal += max(0, $ts - $openStart);
      $openStart = null;
    }
  }
  if ($openStart !== null) {
    $timeclockOpenSince = $openStart;
    $currentOpen = max(0, time() - $openStart);
    $timeclockOpenSeconds = $currentOpen;
    $todayTotal += $currentOpen;
  }
  $timeclockTodayOpenSeconds = $todayTotal;
}
if ($canTimeclockCreate && $timeclockNextType === '') {
  $timeclockNextType = 'entrada';
}

$assetVersion = static function (string $relativePath): string {
  $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
  $mtime = is_file($fullPath) ? filemtime($fullPath) : null;
  return $mtime ? (string) $mtime : '1';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Início — Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=<?php echo $assetVersion('assets/app.css'); ?>">
  <script src="assets/nav.js?v=<?php echo $assetVersion('assets/nav.js'); ?>" defer></script>
  <script src="assets/table.js?v=<?php echo $assetVersion('assets/table.js'); ?>" defer></script>
  <script src="assets/dashboard-layout.js?v=<?php echo $assetVersion('assets/dashboard-layout.js'); ?>" defer></script>
  <style>
    :root {
      --grid-row-height: 260px;
    }
    .dashboard-hero {
      display: grid;
      gap: 12px;
      margin-bottom: 0;
    }
    .dashboard-layout {
      display: flex;
      flex-direction: column;
      gap: 24px;
      width: 100%;
    }
    @media (max-width: 780px) {
      .dashboard-layout { gap: 18px; }
      .dashboard-section { gap: 12px; }
    }
    .dashboard-section {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .dashboard-section--hero {
      padding: 0;
    }
    .dashboard-section--insights,
    .dashboard-section--widgets,
    .dashboard-section--operations,
    .dashboard-section--quick-links,
    .dashboard-section--sales {
      position: relative;
    }
    .dashboard-layout-grid {
      display: grid;
      grid-template-columns: repeat(var(--dashboard-columns, 3), minmax(260px, 1fr));
      gap: 18px;
      align-items: stretch;
      grid-auto-rows: var(--grid-row-height, 260px);
    }
    .dashboard-layout-heading {
      grid-column: 1 / -1;
      margin-bottom: 0;
      font-size: 16px;
      font-weight: 700;
    }
    [data-layout-item] {
      position: relative;
      min-height: 0;
      grid-column: span var(--widget-span, 1);
      grid-row: span var(--widget-rows, 1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .layout-editor-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      padding-bottom: 6px;
    }
    .layout-columns-control {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      font-size: 14px;
    }
    .layout-row-height-control {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      font-size: 14px;
      margin-left: auto;
    }
    .layout-row-height-control select {
      border: 1px solid #d2d6dc;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 14px;
      background: #fff;
    }
    .layout-controls {
      position: absolute;
      top: 10px;
      right: 10px;
      display: none;
      gap: 6px;
      z-index: 3;
    }
    .dashboard-layout-grid[data-editing='true'] .layout-controls {
      display: flex;
    }
    .layout-control {
      border-radius: 10px;
      border: 1px solid #d2d6dc;
      background: #fff;
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
    }
    .dashboard-layout-grid[data-editing='true'] [data-layout-item].is-dragging {
      opacity: 0.8;
      box-shadow: 0 12px 26px rgba(15, 23, 42, 0.25);
    }
    .insight-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .insight-pill {
      background: #f8fafc;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #0f172a;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      align-items: stretch;
      grid-auto-rows: minmax(220px, auto);
      margin-bottom: 0;
    }
    .stat-card {
      border: 1px solid #eef2f7;
      border-radius: 16px;
      padding: 14px;
      background: linear-gradient(135deg, rgba(63,124,255,0.05), rgba(0,198,174,0.08));
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
      display: grid;
      gap: 6px;
    }
    .stat-label { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-value { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
    .stat-hint { color: #0f172a; font-weight: 600; font-size: 13px; }
    .sales-widget { margin-bottom: 18px; }
    .sales-metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 12px;
      margin: 10px 0;
    }
    .sales-metric-card {
      border: 1px solid #eef2f7;
      border-radius: 14px;
      padding: 12px;
      background: #fff;
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
      display: grid;
      gap: 6px;
    }
    .sales-metric-title {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #475569;
    }
    .sales-metric-value {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: #0f172a;
    }
    .sales-metric-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: #0f172a;
    }
    .sales-monthly-table th, .sales-monthly-table td {
      padding: 6px 4px;
      font-size: 13px;
      white-space: normal;
      word-break: break-word;
    }
    .sales-monthly-table th { font-size: 11px; }
    .numbers-toggle {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid #dbeafe;
      background: #eff6ff;
      color: #1d4ed8;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.12s ease;
    }
    .numbers-toggle:hover { box-shadow: 0 10px 20px rgba(29, 78, 216, 0.15); }
    .numbers-toggle svg { width: 16px; height: 16px; }
    .numbers-toggle.is-active {
      background: #1d4ed8;
      color: #fff;
      border-color: #1d4ed8;
      box-shadow: 0 10px 22px rgba(29, 78, 216, 0.2);
    }
    .sensitive.is-masked { color: #94a3b8; letter-spacing: 0.08em; }
    .insights-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 18px;
      align-items: stretch;
      grid-auto-rows: var(--grid-row-height, 260px);
    }
    .block {
      border: 1px solid #eef2f7;
      border-radius: 18px;
      padding: 18px;
      background: #fff;
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.14);
      display: flex;
      flex-direction: column;
      gap: 14px;
      min-height: calc(var(--grid-row-height, 240px) * var(--widget-rows, 1));
      height: 100%;
      overflow: auto;
    }
    .block h2 { margin: 0 0 8px; font-size: 17px; letter-spacing: -0.01em; }
    .block .muted { color: var(--muted); font-size: 13px; }
    .chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .muted { color: var(--muted); }
    .chip {
      border: 1px solid #d8e1ff;
      background: #eff4ff;
      color: #1d4ed8;
      padding: 7px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.12s ease;
    }
    .chip.is-active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; box-shadow: 0 10px 20px rgba(29, 78, 216, 0.18); }
    .bar-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin: 6px 0; }
    .bar-label { font-weight: 700; font-size: 13px; }
    .bar-track { background: #f1f5f9; border-radius: 999px; height: 10px; position: relative; overflow: hidden; }
    .bar-fill { position: absolute; left: 0; top: 0; bottom: 0; background: linear-gradient(135deg, var(--accent), var(--accent-2)); border-radius: 999px; }
    .bar-value { font-weight: 700; color: #0f172a; font-size: 13px; }
    .product-activity-card {
      display: grid;
      gap: 10px;
    }
    .status-list {
      background: #f8fafc;
      border-radius: 12px;
      border: 1px solid #e2e8f7;
      padding: 10px;
    }
    .status-list-title {
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #475569;
      margin-bottom: 6px;
    }
    .status-list-body {
      display: grid;
      gap: 6px;
    }
    .status-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
    }
    .activity-periods {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 12px;
    }
    .activity-period {
      border: 1px solid #eef2f7;
      border-radius: 12px;
      padding: 10px;
      background: #fff;
    }
    .activity-period-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #475569;
      margin-bottom: 6px;
    }
    .activity-period-grid {
      display: grid;
      gap: 6px;
    }
    .activity-period-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: #0f172a;
    }
    .activity-monthly {
      display: grid;
      gap: 8px;
    }
    .activity-monthly-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .activity-chart-legend {
      display: flex;
      gap: 12px;
      font-size: 12px;
      color: #475569;
      flex-wrap: wrap;
    }
    .activity-chart-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 5px;
    }
    .activity-chart-dot--registered { background: linear-gradient(135deg, #3f7cff, #1d4ed8); }
    .activity-chart-dot--sold { background: linear-gradient(135deg, #10b981, #059669); }
    .activity-chart-dot--writeoff { background: linear-gradient(135deg, #fb923c, #f97316); }
    .activity-chart-wrapper {
      border: 1px solid #eef2f7;
      border-radius: 12px;
      padding: 10px;
      background: #fff;
    }
    .activity-chart-svg {
      width: 100%;
      height: 160px;
    }
    .activity-line {
      fill: none;
      stroke-width: 2;
      stroke-linejoin: round;
      stroke-linecap: round;
    }
    .activity-line--registered { stroke: #3f7cff; }
    .activity-line--sold { stroke: #10b981; }
    .activity-line--writeoff { stroke: #f97316; }
    .activity-monthly-table {
      max-height: 200px;
    }
    .activity-monthly-table td, .activity-monthly-table th {
      font-size: 12px;
    }
    .leaderboard { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .leaderboard li {
      border: 1px solid #eef2f7;
      border-radius: 12px;
      padding: 10px 12px;
      background: #f8fafc;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }
    .leaderboard .meta { color: var(--muted); font-size: 12px; }
    .mini-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      table-layout: auto;
    }
    .mini-table th,
    .mini-table td {
      padding: 8px 4px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      white-space: normal;
      word-break: break-word;
    }
    .mini-table th { color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; font-size: 12px; }
    .mini-table .btn { padding: 4px 8px; font-size: 12px; border-radius: 8px; }
    .widgets-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
      align-items: stretch;
      grid-auto-rows: var(--grid-row-height, 260px);
    }
    .widget-scroll {
      max-height: calc((var(--grid-row-height, 260px) * var(--widget-rows, 1)) - 80px);
      overflow-y: auto;
      overflow-x: hidden;
      flex: 1 1 auto;
      min-height: 100px;
    }
    .old-consigned-list { display: grid; gap: 10px; }
    .old-consigned-item {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 10px;
      border: 1px solid #e5e7f5;
      border-radius: 14px;
      padding: 10px;
      background: #fff;
      text-decoration: none;
      color: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .old-consigned-item:hover {
      border-color: #cbd5f5;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    }
    .old-consigned-photo {
      width: 64px;
      height: 64px;
      border-radius: 12px;
      overflow: hidden;
      background: #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .old-consigned-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 12px;
      display: block;
    }
    .old-consigned-photo span {
      font-size: 11px;
      color: var(--muted);
      text-align: center;
      padding: 0 4px;
    }
    .old-consigned-info {
      display: grid;
      gap: 4px;
    }
    .old-consigned-name {
      font-weight: 600;
      font-size: 15px;
    }
    .old-consigned-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      font-size: 13px;
      color: #475569;
      align-items: center;
    }
    .old-consigned-stock {
      text-align: right;
      font-weight: 700;
      font-size: 13px;
      color: #0f172a;
      display: grid;
      gap: 2px;
    }
    .old-consigned-stock small {
      font-size: 11px;
      font-weight: 500;
      color: #64748b;
    }
    .old-consigned-item--yellow {
      background: #fef3c7;
      border-color: #fde68a;
    }
    .old-consigned-item--orange {
      background: #fff7ed;
      border-color: #fb923c;
    }
    .cta-row { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 14px; }
    .cta-card {
      border: 1px dashed #cbd5e1;
      border-radius: 14px;
      padding: 12px;
      background: #f8fafc;
      font-weight: 600;
      color: #0f172a;
    }
    .quick-links {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      align-items: stretch;
    }
    .quick-links a { text-decoration: none; }
    .card {
      border: 1px solid #eef2f7;
      border-radius: 16px;
      padding: 16px;
      background: linear-gradient(135deg, rgba(63,124,255,0.04), rgba(0,198,174,0.06));
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
      text-decoration: none;
      color: inherit;
      transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
      display: flex;
      flex-direction: column;
      gap: 10px;
      min-height: 160px;
      justify-content: space-between;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
      border-color: #dbeafe;
    }
    .card-title { font-weight: 700; margin: 0 0 6px; font-size: 15px; }
    .card-desc { color: var(--muted); font-size: 13px; line-height: 1.4; }
    .widget-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 18px;
      align-items: stretch;
      grid-auto-rows: var(--grid-row-height, 260px);
    }
    .payables-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 14px;
    }
    .payables-summary-value {
      font-size: 24px;
      font-weight: 700;
    }
    .payables-columns {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 12px;
      margin-top: 8px;
    }
    .payables-column {
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      padding: 12px;
      background: #f8fafc;
      min-height: 220px;
    }
    .payables-column-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 8px;
      margin-bottom: 10px;
    }
    .payables-column-header h3 {
      margin: 0;
      font-size: 15px;
    }
    .payables-column-header .muted {
      font-size: 12px;
      line-height: 1.2;
    }
    .payables-column .col-actions {
      min-width: 80px;
      text-align: right;
    }
    .widget-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 8px;
    }
    .widget-title { margin: 0; font-size: 16px; }
    .widget-meta { color: var(--muted); font-size: 13px; }
    .widget-camera-status {
      font-size: 12px;
      color: #047857;
      min-height: 1.2em;
      margin-top: 6px;
    }
    .widget-camera-status--error {
      color: #b91c1c;
    }
    .widget-camera-status--info {
      color: #0ea5e9;
    }
    .sales-metric-link {
      border: none;
      background: none;
      color: var(--accent);
      font: inherit;
      cursor: pointer;
      padding: 0;
      text-decoration: underline;
      font-weight: 600;
    }
    .margin-breakdown {
      margin-top: 12px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .margin-breakdown__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      font-size: 14px;
      font-weight: 600;
    }
    .margin-breakdown__close {
      border: 1px solid transparent;
      background: none;
      color: var(--muted);
      font-size: 14px;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 8px;
      transition: background 0.2s ease;
    }
    .margin-breakdown__close:hover {
      background: #eef2f7;
    }
    .margin-breakdown table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    .margin-breakdown th,
    .margin-breakdown td {
      padding: 6px 4px;
      border-bottom: 1px solid #dfe4ef;
      text-align: left;
    }
    .margin-breakdown tfoot td {
      font-weight: 700;
    }
    .product-activity-toggle {
      margin-top: 10px;
    }
    .product-activity-toggle button {
      border: 1px solid #dbeafe;
      background: #eff6ff;
      color: #1d4ed8;
      border-radius: 999px;
      padding: 6px 12px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
    }
    .product-activity-toggle button:hover {
      background: #dbeafe;
    }
    .timeclock-card { display: grid; gap: 12px; }
    .timeclock-clock { font-size: 32px; font-weight: 700; letter-spacing: -0.02em; }
    .timeclock-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .timeclock-open {
      border-radius: 12px;
      padding: 8px 10px;
      background: #ecfeff;
      border: 1px solid #a5f3fc;
      color: #0f172a;
      font-weight: 600;
      font-size: 13px;
    }
    .timeclock-open.is-closed {
      background: #f1f5f9;
      border-color: #e2e8f0;
      color: var(--muted);
    }
    .timeclock-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .week-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 10px;
      margin-top: 10px;
    }
    .week-day {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px;
      background: #f8fafc;
      display: grid;
      gap: 8px;
    }
    .week-day-title { font-weight: 700; font-size: 13px; }
    .week-day-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .week-item { display: flex; gap: 6px; align-items: flex-start; font-size: 12px; }
    .week-item span { line-height: 1.2; }
    .week-dot { width: 8px; height: 8px; border-radius: 999px; margin-top: 4px; background: #94a3b8; flex-shrink: 0; }
    .week-dot[data-scope=\"brasil\"] { background: #16a34a; }
    .week-dot[data-scope=\"mundial\"] { background: #2563eb; }
    .week-dot[data-scope=\"regional\"] { background: #f97316; }
    .week-dot[data-scope=\"setorial\"] { background: #a855f7; }
    .week-dot[data-scope=\"local\"] { background: #0f766e; }
    .week-empty { color: var(--muted); font-size: 12px; }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
      border: 1px solid transparent;
    }
    .status-pill--paid { background: #ecfdf3; color: #166534; border-color: #bbf7d0; }
    .status-pill--open { background: #fef9c3; color: #854d0e; border-color: #fde68a; }
    .status-pill--overdue { background: #fef2f2; color: #b91c1c; border-color: #fecdd3; }
    .payables-modal-form label {
      display: grid;
      gap: 4px;
      font-size: 13px;
      color: var(--muted);
    }
    .payables-modal-form select {
      border-radius: 10px;
      border: 1px solid #d2d6dc;
      padding: 8px 10px;
      background: #fff;
      font-size: 14px;
    }
    .payables-modal-form label.is-required span::after {
      content: ' *';
      color: #ef4444;
    }
  </style>
</head>
<body>
  <button class="nav-toggle nav-toggle--outside" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mainNav">
    <span></span>
    <span></span>
    <span></span>
  </button>
  <div class="nav-backdrop" data-nav-backdrop aria-hidden="true"></div>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="panel dashboard-layout">
      <section class="dashboard-section dashboard-section--hero">
        <div class="dashboard-hero">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
          <div>
          <?php if ($dashboardTitle !== ''): ?>
            <h1><?php echo htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
          <?php endif; ?>
          <?php if ($dashboardSubtitle !== ''): ?>
            <div class="subtitle"><?php echo htmlspecialchars($dashboardSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          </div>
          <button class="numbers-toggle" type="button" data-numbers-toggle aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6-10-6-10-6z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <span data-toggle-label>Mostrar números</span>
          </button>
        </div>
        <?php if ($connectionError): ?>
          <div class="alert error"><?php echo htmlspecialchars($connectionError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($dashboardSuccess): ?>
          <div class="alert success"><?php echo htmlspecialchars($dashboardSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($dashboardErrors)): ?>
          <div class="alert error"><?php echo htmlspecialchars(implode(' ', $dashboardErrors), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      </section>

      <?php if ($hasLayoutCards) { ?>
        <section class="dashboard-section dashboard-section--layout">
          <div class="layout-editor-bar">
            <?php if ($canCustomizeLayout): ?>
              <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <button class="btn ghost" type="button" data-layout-edit-toggle>Editar layout</button>
                <div class="layout-columns-control">
                  <button class="icon-button" type="button" data-layout-column-decrease aria-label="Reduzir colunas">−</button>
                  <span data-layout-column-label><?php echo $layoutState['columns']; ?> colunas</span>
                  <button class="icon-button" type="button" data-layout-column-increase aria-label="Aumentar colunas">+</button>
                </div>
              </div>
            <?php endif; ?>
            <div class="layout-row-height-control">
              <label for="dashboard-row-height">Altura dos widgets</label>
              <select id="dashboard-row-height" data-row-height-select>
                <option value="200">Compacta</option>
                <option value="260" selected>Padrão</option>
                <option value="320">Ampla</option>
              </select>
            </div>
          </div>
          <div class="dashboard-layout-grid" data-dashboard-layout-root data-layout-columns="<?php echo $layoutState['columns']; ?>" style="--dashboard-columns: <?php echo $layoutState['columns']; ?>;">
          <?php if ($showStatsGrid): ?>
            <?php $inventory = $insights['inventory'] ?? []; ?>
            <?php if ($canWidgetStockValue): ?>
              <?php
                $unitsBySource = $inventory['unitsBySource'] ?? [];
                $totalUnits = (int) ($inventory['totalUnits'] ?? 0);
                $consignUnits = (int) ($unitsBySource['consignacao'] ?? 0);
                $purchaseUnits = (int) ($unitsBySource['compra'] ?? 0);
                $donationUnits = (int) ($unitsBySource['doacao'] ?? 0);
                $investedPurchases = (float) ($inventory['investedPurchases'] ?? 0);
                $futureConsignmentExpense = (float) ($inventory['futureConsignmentExpense'] ?? 0);
                $totalCommitted = $investedPurchases + $futureConsignmentExpense;
              ?>
              <div class="stat-card" data-layout-item data-layout-id="widget_stock_value" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_stock_value']; ?>">
                <div class="stat-label">Valor potencial dos produtos disponíveis</div>
                <div class="stat-value sensitive" data-sensitive><?php echo fmtMoney($stockValue); ?></div>
                <div class="stat-hint">Valor total comprometido (compra + consignação): <?php echo sensitiveSpan(fmtMoney($totalCommitted)); ?></div>
                <div class="stat-hint">Produtos: <?php echo sensitiveSpan(fmtNumber($totalUnits)); ?> (Consignados: <?php echo sensitiveSpan(fmtNumber($consignUnits)); ?> — Adquiridos: <?php echo sensitiveSpan(fmtNumber($purchaseUnits)); ?> — Doados: <?php echo sensitiveSpan(fmtNumber($donationUnits)); ?>)</div>
                <div class="stat-hint">Investido em compras: <?php echo sensitiveSpan(fmtMoney($investedPurchases)); ?></div>
                <div class="stat-hint">Despesa futura de consignações: <?php echo sensitiveSpan(fmtMoney($futureConsignmentExpense)); ?></div>
              </div>
            <?php endif; ?>
            <?php if ($canWidgetMargin): ?>
              <div class="stat-card" data-layout-item data-layout-id="widget_margin" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_margin']; ?>">
                <div class="stat-label">Margem média</div>
                <div class="stat-value sensitive" data-sensitive><?php echo fmtPercent($inventory['avgMargin'] ?? null); ?></div>
                <div class="stat-hint">Consignadas: <?php echo sensitiveSpan(fmtPercent($inventory['avgMarginConsigned'] ?? null)); ?></div>
                <div class="stat-hint">Adquiridas: <?php echo sensitiveSpan(fmtPercent($inventory['avgMarginPurchased'] ?? null)); ?></div>
                <div class="stat-hint">Considera produtos com preço e custo preenchidos.</div>
              </div>
            <?php endif; ?>
            <?php if ($canWidgetActiveProducts): ?>
              <div class="stat-card product-activity-card" data-layout-item data-layout-id="widget_active_products" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_active_products']; ?>">
                <div class="stat-label">Produtos ativos</div>
                <div class="stat-value sensitive" data-sensitive><?php echo fmtNumber($productActivityAvailability['total'] ?? 0); ?></div>
                <div class="stat-hint">Produtos com disponibilidade de venda (> 0 unidades).</div>
                <?php if (!empty($productActivityAvailability['statuses'])): ?>
                  <div class="status-list">
                    <div class="status-list-title">Distribuição por status</div>
                    <div class="status-list-body">
                      <?php foreach ($productActivityAvailability['statuses'] as $statusRow): ?>
                        <div class="status-row">
                          <span><?php echo htmlspecialchars(fmtProductStatus($statusRow['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                          <strong><?php echo sensitiveSpan(fmtNumber($statusRow['total'] ?? 0)); ?></strong>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
                <?php if (!empty($productActivityPeriods)): ?>
                  <div class="activity-periods">
                    <?php foreach ($productActivityPeriods as $period): ?>
                      <div class="activity-period">
                        <div class="activity-period-title"><?php echo htmlspecialchars($period['label'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="activity-period-grid">
                          <div class="activity-period-row">
                            <span>Cadastradas</span>
                            <strong><?php echo sensitiveSpan(fmtNumber($period['registered'] ?? 0)); ?></strong>
                          </div>
                          <div class="activity-period-row">
                            <span>Vendidas</span>
                            <strong><?php echo sensitiveSpan(fmtNumber($period['sold'] ?? 0)); ?></strong>
                          </div>
                          <div class="activity-period-row">
                            <span>Baixas</span>
                            <strong><?php echo sensitiveSpan(fmtNumber($period['writeOff'] ?? 0)); ?></strong>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="activity-monthly">
                  <div class="activity-monthly-head">
                    <div>
                      <strong>Últimos 12 meses</strong>
                      <div class="muted">Cadastros, vendas e baixas</div>
                    </div>
                    <div class="activity-chart-legend">
                      <span><span class="activity-chart-dot activity-chart-dot--registered"></span>Cadastradas</span>
                      <span><span class="activity-chart-dot activity-chart-dot--sold"></span>Vendidas</span>
                      <span><span class="activity-chart-dot activity-chart-dot--writeoff"></span>Baixas</span>
                    </div>
                  </div>
                  <?php if (!empty($productActivityChartMonths)): ?>
                    <div class="product-activity-toggle">
                      <button type="button" data-product-activity-trigger aria-expanded="false">Últimos 12 meses</button>
                    </div>
                    <div class="product-activity-panel" data-product-activity-panel hidden>
                      <div class="activity-chart-wrapper">
                        <svg class="activity-chart-svg" role="presentation" viewBox="0 0 <?php echo $chartWidth; ?> <?php echo $chartHeight; ?>" xmlns="http://www.w3.org/2000/svg">
                          <?php foreach (['registered' => 'activity-line--registered', 'sold' => 'activity-line--sold', 'writeOff' => 'activity-line--writeoff'] as $key => $lineClass): ?>
                            <?php $points = implode(' ', $seriesPoints[$key] ?? []); ?>
                            <?php if ($points !== ''): ?>
                              <polyline class="activity-line <?php echo htmlspecialchars($lineClass, ENT_QUOTES, 'UTF-8'); ?>" points="<?php echo htmlspecialchars($points, ENT_QUOTES, 'UTF-8'); ?>" />
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </svg>
                      </div>
                      <div class="widget-scroll activity-monthly-table">
                        <table class="mini-table">
                          <thead>
                            <tr>
                              <th>Mês</th>
                              <th>Cad.</th>
                              <th>Vend.</th>
                              <th>Baix.</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($productActivityChartMonths as $month): ?>
                              <tr>
                                <td><?php echo htmlspecialchars($month['label'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo sensitiveSpan(fmtNumber($month['registered'] ?? 0)); ?></td>
                                <td><?php echo sensitiveSpan(fmtNumber($month['sold'] ?? 0)); ?></td>
                                <td><?php echo sensitiveSpan(fmtNumber($month['writeOff'] ?? 0)); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="muted">Sem histórico recente.</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($canWidgetActiveCustomers): ?>
              <?php if (!empty($customerEngagement)): ?>
                <div class="block customer-engagement-widget" data-layout-item data-layout-id="widget_active_customers" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_active_customers']; ?>" data-customer-engagement-widget data-default-segment="<?php echo htmlspecialchars($customerEngagement['defaultSegment'] ?? 'last30', ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="widget-header">
                    <div>
                      <h2 class="widget-title">Clientes engajados</h2>
                      <div class="widget-meta">Clientes que compraram nos últimos 30 dias e segmentos relacionados.</div>
                    </div>
                  </div>
                  <div class="customer-engagement-summary">
                    <?php foreach ($customerEngagement['segments'] as $segmentKey => $segment): ?>
                      <?php $segmentValue = $customerEngagement['totals'][$segmentKey] ?? 0; ?>
                      <?php $isActiveSegment = $segmentKey === ($customerEngagement['defaultSegment'] ?? 'last30'); ?>
                      <button type="button" class="customer-engagement-summary__row<?php echo $isActiveSegment ? ' is-active' : ''; ?>" data-customer-engagement-action="<?php echo htmlspecialchars($segmentKey, ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="<?php echo $isActiveSegment ? 'true' : 'false'; ?>">
                        <div class="customer-engagement-summary__text">
                          <div class="stat-label"><?php echo htmlspecialchars($segment['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="muted"><?php echo htmlspecialchars($segment['hint'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="stat-value sensitive" data-sensitive><?php echo sensitiveSpan(fmtNumber($segmentValue)); ?></div>
                      </button>
                    <?php endforeach; ?>
                  </div>
                  <div class="customer-engagement-list">
                    <?php foreach ($customerEngagement['rows'] as $segmentKey => $rows): ?>
                      <?php $panelActive = $segmentKey === ($customerEngagement['defaultSegment'] ?? 'last30'); ?>
                      <div class="customer-engagement-list__panel" data-segment-panel="<?php echo htmlspecialchars($segmentKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $panelActive ? '' : 'hidden'; ?>>
                        <?php if (empty($rows)): ?>
                          <div class="muted">Sem clientes nesta categoria.</div>
                        <?php else: ?>
                          <div class="table-scroll">
                            <table class="mini-table" data-table="interactive">
                              <thead>
                                <tr>
                                  <th data-sort-key="customer" aria-sort="none">Cliente</th>
                                  <th data-sort-key="last_purchase" aria-sort="none">Última compra</th>
                                  <th data-sort-key="qty_last_30" aria-sort="none">Qtd (30 dias)</th>
                                  <th data-sort-key="value_last_30" aria-sort="none" class="col-total">Valor (30 dias)</th>
                                  <th data-sort-key="qty_last_365" aria-sort="none">Qtd (365 dias)</th>
                                  <th data-sort-key="value_last_365" aria-sort="none" class="col-total">Valor (365 dias)</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($rows as $row): ?>
                                  <?php $lastTimestamp = $row['last_purchase_date'] ?? $row['last_active_date'] ?? null; ?>
                                  <?php $lastSortValue = $lastTimestamp ? (string) (int) (strtotime($lastTimestamp) ?: 0) : ''; ?>
                                  <tr>
                                    <td data-value="<?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-value="<?php echo htmlspecialchars($lastSortValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo fmtShortDate($lastTimestamp); ?></td>
                                    <td data-value="<?php echo $row['qty_last_30']; ?>"><?php echo sensitiveSpan(fmtNumber($row['qty_last_30'])); ?></td>
                                    <td class="col-total" data-value="<?php echo number_format($row['value_last_30'], 2, '.', ''); ?>"><?php echo sensitiveSpan(fmtMoney($row['value_last_30'], 2)); ?></td>
                                    <td data-value="<?php echo $row['qty_last_365']; ?>"><?php echo sensitiveSpan(fmtNumber($row['qty_last_365'])); ?></td>
                                    <td class="col-total" data-value="<?php echo number_format($row['value_last_365'], 2, '.', ''); ?>"><?php echo sensitiveSpan(fmtMoney($row['value_last_365'], 2)); ?></td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="block" data-layout-item data-layout-id="widget_active_customers" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_active_customers']; ?>">
                  <div class="muted">Indicadores de clientes temporariamente indisponíveis.</div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
      <?php if ($showInsightsGrid): ?>
        <?php if ($canWidgetSuppliersDependency): ?>
          <div class="block" data-layout-item data-layout-id="widget_suppliers_dependency" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_suppliers_dependency']; ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
              <div>
                <h2>Dependência de base</h2>
                <div class="muted">Quem mais impacta receita futura (fornecedores ou regiões).</div>
              </div>
              <div class="chips" role="group" aria-label="Alternar ranking">
                <button class="chip is-active" data-lead-toggle="vendors">Fornecedores</button>
                <button class="chip" data-lead-toggle="states">Estados</button>
              </div>
            </div>
            <ul class="leaderboard" data-leaderboard></ul>
          </div>
        <?php endif; ?>

        <?php if ($canWidgetInventoryAttention): ?>
          <div class="block" data-layout-item data-layout-id="widget_inventory_attention" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_inventory_attention']; ?>">
            <h2>Itens que pedem atenção</h2>
            <div class="muted">Ordens de reposição ou revisão de preço.</div>
            <?php if (!empty($insights['lowStock'])): ?>
              <table class="mini-table">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Preço</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($insights['lowStock'] as $item): ?>
                    <?php
                      $productSku = (int) (($item['product_sku'] ?? 0) ?: ($item['product_id'] ?? 0) ?: ($item['ID'] ?? 0));
                      $productHref = $productSku > 0 ? 'produto-cadastro.php?id=' . $productSku : '';
                    ?>
                    <tr<?php echo $productHref !== '' ? ' data-row-href="' . htmlspecialchars($productHref, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                      <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo sensitiveSpan(fmtNumber($item['quantity'] ?? 0)); ?></td>
                      <td><?php echo sensitiveSpan(fmtMoney($item['price'] ?? 0, 2)); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="muted">Sem itens com disponibilidade informada.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($canWidgetCollectionsStrength): ?>
          <div class="block" data-layout-item data-layout-id="widget_collections_strength" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_collections_strength']; ?>">
            <h2>Força das coleções</h2>
            <div class="muted">Quantas coleções estão prontas para vitrine.</div>
            <div class="stats-grid" style="margin:6px 0 0;">
              <div class="stat-card" style="background:#f8fafc;box-shadow:none;">
                <div class="stat-label">Total de coleções</div>
                <div class="stat-value sensitive" data-sensitive><?php echo fmtNumber($insights['collections']['total'] ?? 0); ?></div>
                <div class="stat-hint">Ligadas a produtos: <?php echo sensitiveSpan(fmtNumber($insights['collections']['withProducts'] ?? 0)); ?></div>
              </div>
              <div class="stat-card" style="background:#f8fafc;box-shadow:none;">
                <div class="stat-label">Produtos por coleção</div>
                <div class="stat-value sensitive" data-sensitive>
                  <?php
                    $collections = (int) ($insights['collections']['total'] ?? 0);
                    $products = (int) ($insights['inventory']['totalProducts'] ?? 0);
                    $avg = $collections > 0 ? $products / $collections : 0;
                    echo number_format($avg, 1, ',', '.');
                  ?>
                </div>
                <div class="stat-hint">Use coleções para campanhas sazonais.</div>
              </div>
            </div>
            <div class="cta-row">
              <div class="cta-card">Para equilibrar risco, publique mais SKUs em coleções distintas e monitore o fornecedor dominante.</div>
              <div class="cta-card">Crie campanhas focadas nos estados com mais clientes ou onde há produtos disponíveis para envio.</div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($showWidgetGrid) { ?>
          <?php if ($canWidgetMissingPhotos): ?>
            <div class="block" data-layout-item data-layout-id="widget_inventory_missing_photos" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_inventory_missing_photos']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Disponibilidade positiva sem fotos</h2>
                  <div class="widget-meta">Produtos com disponibilidade sem imagem principal ou galeria.</div>
                </div>
                <span class="pill"><?php echo count($productsWithoutPhotos); ?></span>
              </div>
              <?php if (!empty($productsWithoutPhotos)): ?>
                <table class="mini-table">
                  <thead>
                    <tr>
                      <th>Produto</th>
                      <th>SKU</th>
                      <th>Qtd</th>
                      <th style="width:48px;text-align:center;" aria-label="Adicionar imagens">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <path fill="currentColor" d="M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6-10.5h-1.17l-1.84-2H8L6.17 6.5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-10a2 2 0 0 0-2-2zm0 12H5v-10h1.33l1.5-1.5h8.34l1.5 1.5H18v10z"/>
                        </svg>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($productsWithoutPhotos as $item): ?>
                      <?php
                        $sku = (string) ($item['sku'] ?? '');
                        $qty = $item['quantity'] ?? null;
                        $productId = (int) ($item['ID'] ?? 0);
                        $productTitle = trim((string) ($item['post_title'] ?? ''));
                        $productTitleCell = $productTitle !== '' ? $productTitle : '—';
                        $productTitleLabel = $productTitle !== '' ? $productTitle : 'produto';
                        $cameraInputId = $productId > 0 ? ('widget-missing-photos-upload-' . $productId) : '';
                      ?>
                      <tr>
                        <td><?php echo htmlspecialchars($productTitleCell, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sku !== '' ? $sku : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($qty !== null ? (string) $qty : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="width:48px;text-align:center;">
                          <?php if ($cameraInputId !== ''): ?>
                            <button
                              class="ghost"
                              type="button"
                              data-widget-camera-trigger="<?php echo htmlspecialchars($cameraInputId, ENT_QUOTES, 'UTF-8'); ?>"
                              aria-label="Adicionar fotos para <?php echo htmlspecialchars($productTitleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10m0-2a7 7 0 1 1 0 14 7 7 0 0 1 0-14zm6-1.5h-1.17l-1.84-2H8l-1.83 2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 14H5V7h1.33l1.5-1.5h8.34L17.67 7H19z"/>
                              </svg>
                            </button>
                            <input
                              id="<?php echo htmlspecialchars($cameraInputId, ENT_QUOTES, 'UTF-8'); ?>"
                              type="file"
                              accept="<?php echo $productImageAccept; ?>"
                              multiple
                              hidden
                              data-widget-camera-input
                              data-product-id="<?php echo $productId; ?>"
                            >
                            <div class="widget-camera-status" data-widget-camera-status aria-live="polite"></div>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="muted">Nenhum produto encontrado.</div>
              <?php endif; ?>
              <div class="widget-meta" style="margin-top:8px;">
                <a class="link" href="produto-list.php">Ver produtos</a>
              </div>
            </div>

            <?php endif; ?>

          <?php if ($canWidgetMissingCost): ?>
            <div class="block" data-layout-item data-layout-id="widget_inventory_missing_costs" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_inventory_missing_costs']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Compras sem custo</h2>
                  <div class="widget-meta">Produtos de origem compra com disponibilidade positiva sem custo preenchido.</div>
                </div>
                <span class="pill" data-cost-missing-count><?php echo count($productsMissingCost); ?></span>
              </div>
              <div class="widget-scroll">
                <table class="mini-table" data-cost-table <?php echo empty($productsMissingCost) ? 'hidden' : ''; ?>>
                  <thead>
                    <tr>
                      <th>Produto</th>
                      <th>SKU</th>
                      <th>Qtd</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($productsMissingCost as $item): ?>
                      <?php
                        $productId = (int) ($item['product_id'] ?? ($item['ID'] ?? 0));
                        if ($productId <= 0) {
                          continue;
                        }
                        $productName = trim((string) ($item['post_title'] ?? ''));
                        $sku = trim((string) ($item['sku'] ?? ''));
                        $availableQty = (int) ($item['quantity'] ?? 0);
                      ?>
                      <tr data-cost-row="<?php echo $productId; ?>">
                        <td><?php echo htmlspecialchars($productName !== '' ? $productName : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sku !== '' ? $sku : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-value="<?php echo $availableQty; ?>"><?php echo htmlspecialchars($availableQty > 0 ? (string) $availableQty : '0', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                          <?php if ($canEditProductCost): ?>
                            <button
                              type="button"
                              class="icon-button"
                              data-cost-edit
                              data-product-id="<?php echo $productId; ?>"
                              data-product-name="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-sku="<?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-stock="<?php echo $availableQty; ?>"
                              title="Editar custo"
                              aria-label="Editar custo"
                            >
                              <svg viewBox="0 0 24 24" width="16" height="16" role="img" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 20h4l10-10-4-4L4 16z"></path>
                                <path d="M14 6 18 10"></path>
                              </svg>
                            </button>
                          <?php else: ?>
                            <span class="muted">Sem permissão</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <div class="muted" data-cost-empty <?php echo empty($productsMissingCost) ? '' : 'hidden'; ?>>Nenhum produto de compra com disponibilidade e custo pendente.</div>
              </div>
              <div class="widget-meta" style="margin-top:8px;">
                <a class="link" href="produto-list.php">Ver produtos</a>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($canWidgetOldConsigned): ?>
            <div class="block" data-layout-item data-layout-id="widget_inventory_old_consigned" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_inventory_old_consigned']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Consignados antigos</h2>
                  <div class="widget-meta">Produtos de origem consignação com disponibilidade positiva ordenados pelo tempo em catálogo.</div>
                </div>
                <span class="pill"><?php echo count($oldConsignedProducts); ?></span>
              </div>
              <?php if (!empty($oldConsignedProducts)): ?>
                <?php
                  $now = new \DateTimeImmutable('now');
                  $thresholdYellow = $now->modify('-4 months');
                  $thresholdOrange = $now->modify('-6 months');
                ?>
                <div class="widget-scroll">
                  <div class="old-consigned-list">
                    <?php foreach ($oldConsignedProducts as $item): ?>
                      <?php
                        $productId = (int) ($item['product_id'] ?? 0);
                        $title = trim((string) ($item['title'] ?? ''));
                        $title = $title !== '' ? $title : 'Produto sem nome';
                        $sku = trim((string) ($item['sku'] ?? ''));
                        $availableQty = (int) ($item['quantity'] ?? 0);
                        $thumbUrl = trim((string) ($item['thumb_url'] ?? ''));
                        $highlightClass = '';
                        $entryMeta = 'Data de entrada desconhecida';
                        $ageLabel = '';
                        $entryRaw = trim((string) ($item['entry_date'] ?? ''));
                        if ($entryRaw !== '') {
                          try {
                            $entryDate = new \DateTimeImmutable($entryRaw);
                            $entryMeta = 'Entrou em ' . $entryDate->format('d/m/Y');
                            $interval = $entryDate->diff($now);
                            if ($interval->y > 0) {
                              $ageLabel = $interval->y . ' ano' . ($interval->y > 1 ? 's' : '');
                            } elseif ($interval->m > 0) {
                              $ageLabel = $interval->m . ' mês' . ($interval->m > 1 ? 'es' : '');
                            } else {
                              $ageLabel = $interval->d . ' dia' . ($interval->d > 1 ? 's' : '');
                            }
                            if ($ageLabel !== '') {
                              $entryMeta .= ' • Há ' . $ageLabel;
                            }
                            if ($entryDate <= $thresholdOrange) {
                              $highlightClass = 'old-consigned-item--orange';
                            } elseif ($entryDate <= $thresholdYellow) {
                              $highlightClass = 'old-consigned-item--yellow';
                            }
                          } catch (\Throwable $e) {
                            // Ignora datas inválidas.
                          }
                        }
                        $href = $productId > 0 ? 'produto-cadastro.php?id=' . $productId : 'produto-list.php';
                      ?>
                      <a class="old-consigned-item <?php echo $highlightClass; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="old-consigned-photo">
                          <?php if ($thumbUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                          <?php else: ?>
                            <span>Sem foto</span>
                          <?php endif; ?>
                        </div>
                        <div class="old-consigned-info">
                          <div class="old-consigned-name"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="old-consigned-meta">
                            <?php if ($sku !== ''): ?>
                              <span>SKU <?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($entryMeta, ENT_QUOTES, 'UTF-8'); ?></span>
                          </div>
                        </div>
                        <div class="old-consigned-stock">
                          <span><?php echo htmlspecialchars(fmtNumber($availableQty), ENT_QUOTES, 'UTF-8'); ?></span>
                          <small>Unidades</small>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="muted">Nenhum produto consignado antigo encontrado.</div>
              <?php endif; ?>
              <div class="widget-meta" style="margin-top:8px;">
                <a class="link" href="produto-list.php">Ver produtos consignados</a>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($canWidgetUnpublished): ?>
            <div class="block" data-layout-item data-layout-id="widget_inventory_unpublished" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_inventory_unpublished']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Com foto e não publicados</h2>
                  <div class="widget-meta">Produtos com disponibilidade e imagem prontos para publicar.</div>
                </div>
                <span class="pill"><?php echo count($productsWithPhotosUnpublished); ?></span>
              </div>
              <?php if (!empty($productsWithPhotosUnpublished)): ?>
                  <table class="mini-table">
                    <thead>
                      <tr>
                        <th>Foto</th>
                        <th>Produto</th>
                        <th>SKU</th>
                        <th>Status</th>
                        <th aria-label="Publicar">Ação</th>
                      </tr>
                    </thead>
                  <tbody>
                    <?php foreach ($productsWithPhotosUnpublished as $item): ?>
                      <?php
                        $productId = (int) ($item['ID'] ?? 0);
                        $productName = trim((string) ($item['post_title'] ?? ''));
                        $sku = (string) ($item['sku'] ?? '');
                        $status = (string) ($item['status_unified'] ?? ($item['status'] ?? ($item['post_status'] ?? '')));
                        $thumbUrl = trim((string) ($item['thumb_url'] ?? ''));
                        $publishLabel = $productName !== '' ? $productName : 'produto';
                      ?>
                      <tr>
                        <td style="width:56px;">
                          <?php if ($thumbUrl !== ''): ?>
                            <div style="width:48px;height:48px;border-radius:10px;overflow:hidden;background:#eef2f7;display:flex;align-items:center;justify-content:center;">
                              <img src="<?php echo htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumb" style="width:100%;height:100%;object-fit:cover;display:block;" loading="lazy">
                            </div>
                          <?php else: ?>
                            <div style="width:48px;height:48px;border-radius:10px;overflow:hidden;background:#eef2f7;display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--muted);">Sem foto</div>
                          <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($item['post_title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sku !== '' ? $sku : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="pill"><?php echo htmlspecialchars(fmtProductStatus($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td>
                          <?php if ($canBulkPublish && $productId > 0): ?>
                            <form method="post" action="produto-publicacao-lote.php" style="margin:0;display:inline-block;">
                              <input type="hidden" name="bulk_action" value="apply">
                              <input type="hidden" name="action_status" value="disponivel">
                              <input type="hidden" name="selected_ids[]" value="<?php echo $productId; ?>">
                              <button
                                type="submit"
                                class="icon-button"
                                title="Publicar <?php echo htmlspecialchars($publishLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                aria-label="Publicar <?php echo htmlspecialchars($publishLabel, ENT_QUOTES, 'UTF-8'); ?>"
                              >
                                <svg viewBox="0 0 24 24" width="16" height="16" role="img" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M15 3h6v6"></path>
                                  <path d="M10 14 21 3"></path>
                                  <path d="M5 5h5"></path>
                                  <path d="M5 21h14V9"></path>
                                </svg>
                              </button>
                            </form>
                          <?php else: ?>
                            <span class="muted">Sem permissão</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="muted">Nenhum produto encontrado.</div>
              <?php endif; ?>
              <div class="widget-meta" style="margin-top:8px;">
                <a class="link" href="produto-list.php">Ver produtos</a>
            </div>
          </div>
            <?php endif; ?>

          <?php if ($canConsignVouchersWidget): ?>
            <div class="block" data-layout-item data-layout-id="widget_ops_consign_vouchers" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_consign_vouchers']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Comissão de consignados</h2>
                  <div class="widget-meta">Total de cupons gerados por comissão de vendas consignadas, por fornecedora.</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                  <span class="pill"><?php echo count($consignmentVoucherTotals); ?> fornecedoras</span>
                  <span class="pill">Total: <?php echo sensitiveSpan(fmtMoney($consignmentVoucherTotalAmount, 2)); ?></span>
                </div>
              </div>
              <?php if (!empty($consignmentVoucherTotals)): ?>
                <div class="widget-scroll">
                  <table class="mini-table">
                    <thead>
                      <tr>
                        <th>Fornecedor</th>
                        <th>Total de cupons</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($consignmentVoucherTotals as $row): ?>
                        <?php
                          $vendorName = trim((string) ($row['supplier_name'] ?? 'Fornecedor'));
                          $totalCredit = (float) ($row['total_credit'] ?? 0);
                          $vendorId = (int) ($row['supplier_pessoa_id'] ?? 0);
                          $voucherAccountId = (int) ($row['voucher_account_id'] ?? 0);
                          $voucherHref = $voucherAccountId > 0 ? 'cupom-credito-cadastro.php?id=' . $voucherAccountId : '';
                        ?>
                        <tr<?php echo $voucherHref !== '' ? ' data-row-href="' . htmlspecialchars($voucherHref, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                          <td data-value="<?php echo $vendorId; ?>">
                            <div><?php echo htmlspecialchars($vendorName !== '' ? $vendorName : 'Fornecedor', ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($vendorId > 0): ?>
                              <div class="muted">ID <?php echo $vendorId; ?></div>
                            <?php endif; ?>
                          </td>
                          <td><?php echo sensitiveSpan(fmtMoney($totalCredit, 2)); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="muted">Nenhuma comissão de consignação encontrada.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($canPayablesWidget): ?>
            <div class="block" data-layout-item data-layout-id="widget_finance_payables" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_finance_payables']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Despesas &amp; projeções</h2>
                  <div class="widget-meta">Últimos 5 dias confirmados e próximos 30 dias projetados.</div>
                </div>
                <a class="link" href="financeiro-list.php">Abrir financeiro</a>
              </div>
              <div class="payables-summary">
                <div>
                  <div class="muted">Últimos 5 dias</div>
                  <div class="payables-summary-value sensitive" data-sensitive><?php echo fmtMoney($payablesTotalRecent, 2); ?></div>
                  <div class="muted"><?php echo count($payablesRecent); ?> lançamentos</div>
                </div>
                <div>
                  <div class="muted">Próximos 30 dias</div>
                  <div class="payables-summary-value sensitive" data-sensitive><?php echo fmtMoney($payablesTotalProjected, 2); ?></div>
                  <div class="muted"><?php echo count($payablesProjected); ?> lançamentos</div>
                </div>
              </div>
              <div class="payables-columns">
                <div class="payables-column">
                  <div class="payables-column-header">
                    <h3>Últimos 5 dias</h3>
                    <span class="muted">Base real</span>
                  </div>
                  <?php if (!empty($payablesRecent)): ?>
                    <div class="widget-scroll">
                      <table class="mini-table" data-table="interactive">
                        <thead>
                          <tr>
                            <th data-sort-key="due_date">Data</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <?php if ($canEditFinanceEntries): ?><th></th><?php endif; ?>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($payablesRecent as $row): ?>
                            <?php
                              $entryId = (int) ($row['id'] ?? 0);
                              $dueRaw = $row['due_date'] ?? null;
                              $dueLabel = $dueRaw ? date('d/m', strtotime((string) $dueRaw)) : '—';
                              $amount = (float) ($row['amount'] ?? 0);
                              $statusKey = strtolower((string) ($row['status'] ?? 'pendente'));
                              $isPaid = $statusKey === 'pago';
                              $isOverdue = !$isPaid && $dueRaw && $dueRaw < $todayDate;
                              $isToday = !$isPaid && $dueRaw === $todayDate;
                              $statusClass = $isPaid ? 'status-pill--paid' : ($isOverdue ? 'status-pill--overdue' : 'status-pill--open');
                              $statusLabel = fmtFinanceStatus($statusKey);
                              if ($isOverdue) {
                                $statusLabel .= ' (vencido)';
                              } elseif ($isToday) {
                                $statusLabel .= ' (hoje)';
                              }
                              $editLink = $canEditFinanceEntries ? 'financeiro-cadastro.php?id=' . $entryId : '';
                            ?>
                            <tr<?php echo $editLink !== '' ? ' data-row-href="' . htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                              <td data-value="<?php echo htmlspecialchars((string) $dueRaw, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                              <td>
                                <?php if ($editLink !== ''): ?>
                                  <a class="link" href="<?php echo htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php else: ?>
                                  <?php echo htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                              </td>
                              <td><?php echo sensitiveSpan(fmtMoney($amount, 2)); ?></td>
                              <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                              <?php if ($canEditFinanceEntries): ?>
                                <td class="col-actions">
                                  <?php if (!$isPaid): ?>
                                    <button class="icon-button success" type="button" aria-label="Marcar como pago" data-payable-open
                                      data-entry-id="<?php echo $entryId; ?>"
                                      data-entry-description="<?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-link="<?php echo htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-amount="<?php echo number_format($amount, 2, ',', '.'); ?>"
                                      data-entry-amount-raw="<?php echo number_format($amount, 2, '.', ''); ?>"
                                      data-entry-due="<?php echo htmlspecialchars((string) $dueRaw, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                      <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                                    </button>
                                  <?php else: ?>
                                    <span class="muted">Pago</span>
                                  <?php endif; ?>
                                </td>
                              <?php endif; ?>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="muted">Nenhuma despesa registrada neste período.</div>
                  <?php endif; ?>
                </div>
                <div class="payables-column">
                  <div class="payables-column-header">
                    <h3>Próximos 30 dias</h3>
                    <span class="muted">Projeção</span>
                  </div>
                  <?php if (!empty($payablesProjected)): ?>
                    <div class="widget-scroll">
                      <table class="mini-table" data-table="interactive">
                        <thead>
                          <tr>
                            <th data-sort-key="due_date">Data</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <?php if ($canEditFinanceEntries): ?><th></th><?php endif; ?>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($payablesProjected as $row): ?>
                            <?php
                              $entryId = (int) ($row['id'] ?? 0);
                              $dueRaw = $row['due_date'] ?? null;
                              $dueLabel = $dueRaw ? date('d/m', strtotime((string) $dueRaw)) : '—';
                              $amount = (float) ($row['amount'] ?? 0);
                              $statusKey = strtolower((string) ($row['status'] ?? 'pendente'));
                              $isPaid = $statusKey === 'pago';
                              $isOverdue = !$isPaid && $dueRaw && $dueRaw < $todayDate;
                              $isToday = !$isPaid && $dueRaw === $todayDate;
                              $statusClass = $isPaid ? 'status-pill--paid' : ($isOverdue ? 'status-pill--overdue' : 'status-pill--open');
                              $statusLabel = fmtFinanceStatus($statusKey);
                              if ($isOverdue) {
                                $statusLabel .= ' (vencido)';
                              } elseif ($isToday) {
                                $statusLabel .= ' (hoje)';
                              }
                              $editLink = $canEditFinanceEntries ? 'financeiro-cadastro.php?id=' . $entryId : '';
                            ?>
                            <tr<?php echo $editLink !== '' ? ' data-row-href="' . htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                              <td data-value="<?php echo htmlspecialchars((string) $dueRaw, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                              <td>
                                <?php if ($editLink !== ''): ?>
                                  <a class="link" href="<?php echo htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php else: ?>
                                  <?php echo htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                              </td>
                              <td><?php echo sensitiveSpan(fmtMoney($amount, 2)); ?></td>
                              <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                              <?php if ($canEditFinanceEntries): ?>
                                <td class="col-actions">
                                  <?php if (!$isPaid): ?>
                                    <button class="icon-button success" type="button" aria-label="Marcar como pago" data-payable-open
                                      data-entry-id="<?php echo $entryId; ?>"
                                      data-entry-description="<?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-link="<?php echo htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-amount="<?php echo number_format($amount, 2, ',', '.'); ?>"
                                      data-entry-amount-raw="<?php echo number_format($amount, 2, '.', ''); ?>"
                                      data-entry-due="<?php echo htmlspecialchars((string) $dueRaw, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>"
                                      data-entry-status-label="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                      <svg aria-hidden="true"><use href="#icon-check"></use></svg>
                                    </button>
                                  <?php else: ?>
                                    <span class="muted">Pago</span>
                                  <?php endif; ?>
                                </td>
                              <?php endif; ?>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="muted">Nenhuma projeção disponível.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($canTimeclockWidget): ?>
            <div class="block" data-layout-item data-layout-id="widget_timeclock" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_timeclock']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Relógio do ponto</h2>
                  <div class="widget-meta">Acompanhe e registre a próxima batida.</div>
                </div>
                <span class="pill"><?php echo $timeclockOpenSeconds !== null ? 'Ponto aberto' : 'Ponto fechado'; ?></span>
              </div>
              <?php if (!$pdo): ?>
                <div class="muted">Sem conexão com o banco para registrar ponto.</div>
              <?php else: ?>
                <?php
                  $lastType = (string) ($timeclockLastEntry['tipo'] ?? '');
                  $lastStatus = (string) ($timeclockLastEntry['status'] ?? '');
                  $lastAtRaw = $timeclockLastEntry['registrado_em'] ?? '';
                  $lastAtLabel = $lastAtRaw ? date('d/m/Y H:i', strtotime((string) $lastAtRaw)) : '';
                ?>
                <div class="timeclock-card" data-timeclock<?php echo $timeclockOpenSince ? ' data-open-since="' . (int) $timeclockOpenSince . '"' : ''; ?>>
                  <div class="timeclock-row">
                    <div>
                      <div class="timeclock-clock" data-clock-now><?php echo date('H:i:s'); ?></div>
                      <div class="widget-meta">Hora atual</div>
                    </div>
                    <div class="timeclock-open<?php echo $timeclockOpenSeconds !== null ? '' : ' is-closed'; ?>">
                      <div>Tempo em aberto</div>
                      <div data-clock-elapsed><?php echo $timeclockOpenSeconds !== null ? fmtDuration($timeclockOpenSeconds) : '—'; ?></div>
                      <div class="widget-meta" style="margin-top:2px;">
                        Hoje: <?php echo $timeclockTodayOpenSeconds !== null ? fmtDuration($timeclockTodayOpenSeconds) : '—'; ?>
                      </div>
                    </div>
                  </div>
                  <div class="widget-meta">
                    Último ponto:
                    <?php echo $lastType !== '' ? htmlspecialchars(ucfirst($lastType), ENT_QUOTES, 'UTF-8') : '—'; ?>
                    <?php if ($lastAtLabel !== ''): ?>
                      em <?php echo htmlspecialchars($lastAtLabel, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                    <?php if ($lastStatus !== ''): ?>
                      (<?php echo htmlspecialchars($lastStatus, ENT_QUOTES, 'UTF-8'); ?>)
                    <?php endif; ?>
                  </div>
                  <div class="timeclock-actions">
                    <?php if ($canTimeclockCreate): ?>
                      <form method="post" style="margin:0;">
                        <input type="hidden" name="timeclock_type" value="<?php echo htmlspecialchars($timeclockNextType, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn primary" type="submit"><?php echo $timeclockNextType === 'saida' ? 'Bater saída' : 'Bater entrada'; ?></button>
                      </form>
                    <?php endif; ?>
                    <a class="btn ghost" href="ponto-list.php">Ver registros</a>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($canCalendarWidget): ?>
            <div class="block" data-layout-item data-layout-id="widget_calendar" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_calendar']; ?>">
              <div class="widget-header">
                <div>
                  <h2 class="widget-title">Cronograma da semana</h2>
                  <div class="widget-meta">Datas comemorativas da semana (Brasil e mundo).</div>
                </div>
                <a class="link" href="data-comemorativa-list.php">Abrir calendário</a>
              </div>
              <?php if (!empty($commemorativeWeek)): ?>
                <div class="week-grid">
                  <?php foreach ($commemorativeWeek as $day): ?>
                    <div class="week-day">
                      <div class="week-day-title"><?php echo htmlspecialchars((string) ($day['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php if (!empty($day['items'])): ?>
                        <ul class="week-day-list">
                          <?php foreach ($day['items'] as $item): ?>
                            <li class="week-item">
                              <span class="week-dot" data-scope="<?php echo htmlspecialchars((string) ($item['scope'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></span>
                              <span><?php echo htmlspecialchars((string) ($item['name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <div class="week-empty">Sem datas</div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="muted">Nenhuma data cadastrada nesta semana.</div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
      <?php } ?>

      <?php if ($hasOpsWidgets): ?>
            <?php if ($canBagsWidget): ?>
              <div class="block" data-layout-item data-layout-id="widget_ops_bags" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_bags']; ?>">
                <h2>Sacolinhas abertas</h2>
                <div class="muted">Da mais antiga para a mais recente.</div>
                <?php if (!empty($openBags)): ?>
                  <div class="widget-scroll">
                    <table class="mini-table">
                      <thead>
                        <tr>
                          <th>Cliente</th>
                          <th>Abertura</th>
                          <th>Itens</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($openBags as $bag): ?>
                          <?php
                            $bagId = (int) ($bag['id'] ?? 0);
                            $customerName = resolveBagCustomerName($bag);
                            $personId = (int) ($bag['pessoa_id'] ?? 0);
                            $openedAtRaw = $bag['opened_at'] ?? null;
                            $openedAt = $openedAtRaw ? date('d/m/Y', strtotime($openedAtRaw)) : '-';
                            $expectedCloseRaw = $bag['expected_close_at'] ?? null;
                            $expectedClose = $expectedCloseRaw ? date('d/m/Y', strtotime($expectedCloseRaw)) : '-';
                            $itemsQty = (int) ($bag['items_qty'] ?? 0);
                            $itemsTotal = (float) ($bag['items_total'] ?? 0);
                            $bagHref = $bagId > 0 ? 'sacolinha-cadastro.php?id=' . $bagId : '';
                          ?>
                          <tr<?php echo $bagHref !== '' ? ' data-row-href="' . htmlspecialchars($bagHref, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                            <td data-value="<?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>">
                              <div><?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php if ($bagId > 0): ?>
                                <div class="muted">Sacolinha #<?php echo $bagId; ?></div>
                              <?php endif; ?>
                              <?php if ($personId > 0): ?>
                                <div class="muted">ID <?php echo $personId; ?></div>
                              <?php endif; ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars((string) $openedAtRaw, ENT_QUOTES, 'UTF-8'); ?>">
                              <div><?php echo htmlspecialchars($openedAt, ENT_QUOTES, 'UTF-8'); ?></div>
                              <div class="muted">Prev: <?php echo htmlspecialchars($expectedClose, ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td data-value="<?php echo $itemsQty; ?>"><?php echo $itemsQty; ?></td>
                            <td><?php echo sensitiveSpan(fmtMoney($itemsTotal, 2)); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted">Nenhuma sacolinha aberta no momento.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($canConsignmentsWidget): ?>
              <div class="block" data-layout-item data-layout-id="widget_ops_consignments" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_consignments']; ?>">
                <h2>Pré-lotes pendentes</h2>
                <div class="muted">Produtos ainda não cadastrados via lote.</div>
                <?php if (!empty($pendingConsignments)): ?>
                  <div class="widget-scroll">
                    <table class="mini-table">
                      <thead>
                        <tr>
                          <th>Pré-lote</th>
                          <th>Fornecedor</th>
                          <th>Saldo</th>
                          <th>Recebimento</th>
                          <th>Ação</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($pendingConsignments as $row): ?>
                          <?php
                            $consignmentId = (int) ($row['id'] ?? 0);
                            $supplierName = (string) ($row['supplier_name'] ?? 'Sem fornecedor');
                            $supplierCode = (int) ($row['supplier_pessoa_id'] ?? 0);
                            $receivedAtRaw = $row['received_at'] ?? null;
                            $receivedAt = $receivedAtRaw ? date('d/m/Y', strtotime($receivedAtRaw)) : '-';
                            $totalReceived = (int) ($row['total_received'] ?? 0);
                            $totalReturned = (int) ($row['total_returned'] ?? 0);
                            $remaining = max(0, $totalReceived - $totalReturned);
                            $linked = (int) ($row['total_linked'] ?? 0);
                            $pending = max(0, $remaining - $linked);
                            $detailHref = $canConsignmentEdit && $consignmentId > 0
                              ? 'consignacao-recebimento-cadastro.php?id=' . $consignmentId
                              : '';
                            $batchHref = '';
                            if ($canBatchIntake && $consignmentId > 0) {
                              $batchHref = 'lote-produtos.php?source=consignacao&consignment=' . $consignmentId;
                              if ($supplierCode > 0) {
                                $batchHref .= '&vendor=' . $supplierCode;
                              }
                            }
                          ?>
                          <tr>
                            <td data-value="<?php echo $consignmentId; ?>">
                              <?php if ($detailHref !== ''): ?>
                                <a class="link" href="<?php echo $detailHref; ?>">#<?php echo $consignmentId; ?></a>
                              <?php else: ?>
                                #<?php echo $consignmentId; ?>
                              <?php endif; ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-value="<?php echo $pending; ?>">
                              <div><?php echo sensitiveSpan(fmtNumber($pending)); ?> pendentes</div>
                              <div class="muted">Cadastradas: <?php echo sensitiveSpan(fmtNumber($linked)); ?> / <?php echo sensitiveSpan(fmtNumber($remaining)); ?></div>
                            </td>
                            <td data-value="<?php echo htmlspecialchars((string) $receivedAtRaw, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($receivedAt, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                              <?php if ($batchHref !== ''): ?>
                                <a class="btn ghost" href="<?php echo $batchHref; ?>">Cadastrar</a>
                              <?php else: ?>
                                <span class="muted">Sem permissão</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted">Nenhum pré-lote pendente de cadastro.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($canDeliveriesWidget): ?>
              <div class="block" data-layout-item data-layout-id="widget_ops_deliveries" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_deliveries']; ?>">
                <h2>Vendas pendentes de entrega</h2>
                <div class="muted">Pedidos não entregues, do mais antigo ao mais novo.</div>
                <?php if (!empty($pendingDeliveries)): ?>
                  <div class="widget-scroll">
                    <table class="mini-table">
                      <thead>
                        <tr>
                          <th>Pedido</th>
                          <th>Cliente</th>
                          <th>Entrega</th>
                          <th>Data</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($pendingDeliveries as $row): ?>
                          <?php
                            $orderId = (int) ($row['order_id'] ?? 0);
                            $customerName = resolveOrderCustomerName($row);
                            $dateRaw = (string) ($row['date_created'] ?? '');
                            $dateLabel = $dateRaw !== '' ? date('d/m/Y', strtotime($dateRaw)) : '-';
                            $fulfillmentKey = trim((string) ($row['fulfillment_status'] ?? ''));
                            if ($fulfillmentKey === '') {
                              $fulfillmentKey = 'novo';
                            }
                            $fulfillmentLabel = $fulfillmentStatusLabels[$fulfillmentKey] ?? $fulfillmentKey;
                            $statusKeyRaw = (string) ($row['status'] ?? '');
                            $statusKey = strpos($statusKeyRaw, 'wc-') === 0 ? substr($statusKeyRaw, 3) : $statusKeyRaw;
                            $statusLabel = $orderStatusLabels[$statusKey] ?? $statusKey;
                          ?>
                          <tr>
                            <td data-value="<?php echo $orderId; ?>">
                              <a class="link" href="pedido-cadastro.php?id=<?php echo $orderId; ?>">#<?php echo $orderId; ?></a>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($fulfillmentLabel, ENT_QUOTES, 'UTF-8'); ?>">
                              <div class="pill"><?php echo htmlspecialchars($fulfillmentLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php if ($statusLabel !== ''): ?>
                                <div class="muted"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php endif; ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($dateRaw, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted">Nenhuma venda pendente de entrega.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($canRefundsWidget): ?>
              <div class="block" data-layout-item data-layout-id="widget_ops_refunds" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_refunds']; ?>">
                <h2>Ressarcimentos a fazer</h2>
                <div class="muted">Devoluções com reembolso pendente.</div>
                <?php if (!empty($pendingRefunds)): ?>
                  <div class="widget-scroll">
                    <table class="mini-table">
                      <thead>
                        <tr>
                          <th>Devolução</th>
                          <th>Pedido</th>
                          <th>Cliente</th>
                          <th>Método</th>
                          <th>Valor</th>
                          <th>Ação</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($pendingRefunds as $row): ?>
                          <?php
                            $returnId = (int) ($row['id'] ?? 0);
                            $orderId = (int) ($row['order_id'] ?? 0);
                            $customerName = trim((string) ($row['customer_name'] ?? ''));
                            $customerEmail = trim((string) ($row['customer_email'] ?? ''));
                            if ($customerName === '' && $customerEmail !== '') {
                              $customerName = $customerEmail;
                            }
                            if ($customerName === '') {
                              $customerName = 'Cliente';
                            }
                            $refundMethodKey = (string) ($row['refund_method'] ?? '');
                            $refundMethodLabel = $refundMethodLabels[$refundMethodKey] ?? $refundMethodKey;
                            $refundAmount = (float) ($row['refund_amount'] ?? 0);
                          ?>
                          <tr>
                            <td data-value="<?php echo $returnId; ?>">
                              <a class="link" href="pedido-devolucao-cadastro.php?id=<?php echo $returnId; ?>">#<?php echo $returnId; ?></a>
                            </td>
                            <td data-value="<?php echo $orderId; ?>">
                              <?php if ($orderId > 0): ?>
                                <a class="link" href="pedido-cadastro.php?id=<?php echo $orderId; ?>">#<?php echo $orderId; ?></a>
                              <?php else: ?>
                                -
                              <?php endif; ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($refundMethodLabel, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($refundMethodLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td><?php echo sensitiveSpan(fmtMoney($refundAmount, 2)); ?></td>
                            <td>
                              <?php if ($canRefundUpdate): ?>
                                <form method="post" onsubmit="return confirm('Marcar ressarcimento como feito?');" style="margin:0;">
                                  <input type="hidden" name="refund_done_id" value="<?php echo $returnId; ?>">
                                  <button class="btn ghost" type="submit">Concluir</button>
                                </form>
                              <?php else: ?>
                                <span class="muted">Sem permissão</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted">Nenhum ressarcimento pendente.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($canCreditsWidget): ?>
              <div class="block" data-layout-item data-layout-id="widget_ops_credits" data-layout-default-span="<?php echo $defaultWidgetSpans['widget_ops_credits']; ?>">
                <h2>Créditos e cupons ativos</h2>
                <div class="muted">Clientes/fornecedoras com saldo disponível.</div>
                <?php if (!empty($creditBalances)): ?>
                  <div class="widget-scroll">
                    <table class="mini-table">
                      <thead>
                        <tr>
                          <th>Cliente</th>
                          <th>Tipo</th>
                          <th>Saldo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($creditBalances as $row): ?>
                          <?php
                            $voucherId = (int) ($row['id'] ?? 0);
                            $customerLabel = resolveVoucherCustomerLabel($row);
                            $typeKey = (string) ($row['type'] ?? '');
                            $typeLabel = $voucherTypeLabels[$typeKey] ?? $typeKey;
                            $balance = (float) ($row['balance'] ?? 0);
                            $meta = trim((string) ($row['code'] ?? ''));
                            if ($meta === '') {
                              $meta = trim((string) ($row['label'] ?? ''));
                            }
                          ?>
                          <tr>
                            <td data-value="<?php echo htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8'); ?>">
                              <div>
                                <a class="link" href="cupom-credito-cadastro.php?id=<?php echo $voucherId; ?>"><?php echo htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                              </div>
                              <?php if ($meta !== ''): ?>
                                <div class="muted"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php endif; ?>
                            </td>
                            <td data-value="<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td><?php echo sensitiveSpan(fmtMoney($balance, 2)); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted">Sem créditos ou cupons com saldo.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
      <?php endif; ?>

      <?php if ($canQuickLinks): ?>
        <?php
          $quickLinkCards = [];

          if (userCan('products.view')) {
            $quickLinkCards[] = [
              'href' => 'produto-list.php',
              'title' => 'Produtos e disponibilidade',
              'desc' => 'Acesse a lista principal de produtos do modelo unificado.',
            ];
          }
          if (userCan('inventory.view')) {
            $quickLinkCards[] = [
              'href' => 'disponibilidade-conferencia.php',
              'title' => 'Conferência de disponibilidade',
              'desc' => 'Abra um batimento para validar estoque físico e disponibilidade.',
            ];
          }
          if (userCan('consignments.view')) {
            $quickLinkCards[] = [
              'href' => 'consignacao-produto-list.php',
              'title' => 'Consignações',
              'desc' => 'Acompanhe recebimentos, status e fechamento de consignacao.',
            ];
          }
          if (userCan('products.batch_intake')) {
            $quickLinkCards[] = [
              'href' => 'lote-produtos.php',
              'title' => 'Entrada de lote',
              'desc' => 'Inicie um novo lote de produtos no fluxo unificado.',
            ];
          }
          if (userCan('inventory.monitor')) {
            $quickLinkCards[] = [
              'href' => 'disponibilidade-conferencia-acompanhamento.php',
              'title' => 'Conferências de disponibilidade',
              'desc' => 'Monitore contagens abertas, ajustes e consolidacao.',
            ];
          }
          if (userCan('people.view')) {
            $quickLinkCards[] = [
              'href' => 'pessoa-list.php',
              'title' => 'Pessoas unificadas',
              'desc' => 'Fonte unica para clientes, fornecedores e demais papeis.',
            ];
          }
          if (userCan('vendors.view')) {
            $quickLinkCards[] = [
              'href' => 'pessoa-list.php?role=fornecedor',
              'title' => 'Fornecedores',
              'desc' => 'Filtro rapido da base unificada para fornecedores.',
            ];
          }
          if (userCan('customers.view')) {
            $quickLinkCards[] = [
              'href' => 'pessoa-list.php?role=cliente',
              'title' => 'Clientes',
              'desc' => 'Filtro rapido da base unificada para clientes.',
            ];
          }
          if (userCan('sales_channels.view')) {
            $quickLinkCards[] = [
              'href' => 'canal-venda-list.php',
              'title' => 'Canais de venda',
              'desc' => 'Gerencie canais disponiveis para pedidos e vendas.',
            ];
          }
          if (userCan('voucher_identification_patterns.view')) {
            $quickLinkCards[] = [
              'href' => 'cupom-credito-identificacao-list.php',
              'title' => 'Padrões de cupom',
              'desc' => 'Controle os padroes de identificacao de cupons e creditos.',
            ];
          }
          if (userCan('users.create')) {
            $quickLinkCards[] = [
              'href' => 'usuario-cadastro.php',
              'title' => 'Cadastrar usuário',
              'desc' => 'Crie novos acessos com perfil e permissoes.',
            ];
          }
        ?>
        <div class="block block--quick-links quick-links" data-layout-item data-layout-id="quick_links" data-layout-default-span="<?php echo $defaultWidgetSpans['quick_links']; ?>">
          <?php if (empty($quickLinkCards)): ?>
            <div class="muted">Sem atalhos disponiveis para o perfil atual.</div>
          <?php else: ?>
            <?php foreach ($quickLinkCards as $card): ?>
              <a class="card" href="<?php echo htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-desc"><?php echo htmlspecialchars($card['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
        </section>
      <?php } ?>
    </main>
  </div>

  <div class="modal-backdrop" data-cost-modal-backdrop hidden></div>
  <div class="modal" data-cost-modal hidden>
    <div class="panel" style="max-width:420px;width:100%;position:relative;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div>
          <h2 style="margin:0;font-size:18px;">Editar custo de compra</h2>
          <div class="muted" style="margin-top:4px;font-size:14px;">Atualize o valor para remover o alerta.</div>
        </div>
        <button type="button" class="icon-button" data-cost-modal-close aria-label="Fechar">
          <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round">
            <path d="M6 6l12 12"></path>
            <path d="M18 6l-12 12"></path>
          </svg>
        </button>
      </div>
      <form data-cost-form>
        <input type="hidden" name="product_id">
        <div class="field">
          <label>Produto</label>
          <div data-cost-product-name class="muted" style="font-weight:600;">—</div>
          <div data-cost-product-sku class="muted" style="font-size:13px;"></div>
          <div data-cost-product-stock class="muted" style="font-size:13px;"></div>
        </div>
        <div class="field">
          <label for="dashboard-cost-value">Custo de compra (R$)</label>
          <input id="dashboard-cost-value" name="cost" type="text" inputmode="decimal" data-number-br data-decimals="2" step="0.01" placeholder="0,00">
        </div>
        <div class="alert error" data-cost-error hidden></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
          <button type="button" class="btn ghost" data-cost-modal-cancel>Cancelar</button>
          <button type="submit" class="btn primary" data-cost-modal-save>Salvar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" data-payables-modal-backdrop hidden></div>
  <div class="modal" role="dialog" aria-modal="true" aria-hidden="true" data-payables-modal hidden>
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h2>Registrar pagamento</h2>
          <div class="muted" data-payables-status>—</div>
        </div>
        <button class="icon-button" type="button" aria-label="Fechar" data-payables-modal-close>
          <svg aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
      </div>
      <form class="payables-modal-form" method="post" action="index.php">
        <input type="hidden" name="dashboard_payable_mark_paid" value="">
        <div class="modal-body">
          <div>
            <div class="muted">Lançamento</div>
            <strong data-payables-description>—</strong>
            <div class="muted" data-payables-due>—</div>
          </div>
          <div>
            <div class="muted">Valor</div>
            <div class="payables-summary-value" data-payables-amount>—</div>
          </div>
          <div class="grid">
            <label class="form-field">
              <span>Meio de pagamento</span>
              <select name="payment_method_id" data-payable-method>
                <option value="">Manter registro atual</option>
                <?php foreach ($paymentMethods as $method): ?>
                  <option value="<?php echo (int) $method['id']; ?>" data-requires-bank="<?php echo !empty($method['requires_bank_account']) ? '1' : '0'; ?>" data-requires-terminal="<?php echo !empty($method['requires_terminal']) ? '1' : '0'; ?>">
                    <?php echo htmlspecialchars($method['name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-field">
              <span>Conta bancária</span>
              <select name="bank_account_id" data-payable-bank>
                <option value="">Manter conta vinculada</option>
                <?php foreach ($bankAccounts as $account): ?>
                  <option value="<?php echo (int) ($account['id'] ?? 0); ?>">
                    <?php
                      $bankName = trim((string) ($account['bank_name'] ?? 'Conta'));
                      $label = trim((string) ($account['label'] ?? ''));
                      echo htmlspecialchars($bankName . ($label !== '' ? ' · ' . $label : ''), ENT_QUOTES, 'UTF-8');
                    ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-field">
              <span>Maquininha</span>
              <select name="payment_terminal_id" data-payable-terminal>
                <option value="">Manter terminal vinculado</option>
                <?php foreach ($paymentTerminals as $terminal): ?>
                  <option value="<?php echo (int) ($terminal['id'] ?? 0); ?>">
                    <?php echo htmlspecialchars($terminal['name'] ?? 'Terminal', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="muted" style="font-size:12px;">Preencha campos apenas quando precisar alterar o registro.</div>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:10px;">
          <button class="btn ghost" type="button" data-payables-modal-close>Cancelar</button>
          <button class="btn primary" type="submit">Confirmar pagamento</button>
        </div>
      </form>
    </div>
  </div>

  <script type="application/json" id="dashboard-layout-data"><?php echo json_encode($layoutState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <script type="application/json" id="dashboard-data"><?php echo json_encode($insights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <script>
    (() => {
      const select = document.querySelector('[data-row-height-select]');
      if (!select) return;
      const storageKey = 'dashboardRowHeight';
      const root = document.documentElement;
      const clampHeight = (value) => {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return 260;
        return Math.min(480, Math.max(160, parsed));
      };
      const applyHeight = (value) => {
        const clamped = clampHeight(value);
        root.style.setProperty('--grid-row-height', `${clamped}px`);
      };

      const saved = localStorage.getItem(storageKey);
      if (saved !== null) {
        select.value = saved;
        applyHeight(saved);
      } else {
        applyHeight(select.value);
      }

      select.addEventListener('change', () => {
        const value = select.value;
        localStorage.setItem(storageKey, value);
        applyHeight(value);
      });
    })();
  </script>
  <script>
    (() => {
      const dataEl = document.getElementById('dashboard-data');
      if (!dataEl) return;
      const data = JSON.parse(dataEl.textContent || '{}');
      const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
      }[ch]));
      const fmtNumber = (value) => new Intl.NumberFormat('pt-BR').format(Number(value) || 0);
      const fmtCurrency = (value) => new Intl.NumberFormat('pt-BR', {
        style: 'currency', currency: 'BRL', maximumFractionDigits: 0,
      }).format(Number(value) || 0);
      const fmtPercent = (value) => {
        const n = Number(value);
        if (Number.isNaN(n)) return '0%';
        return `${(n * 100).toFixed(1).replace('.', ',')}%`;
      };

      const toggleButton = document.querySelector('[data-numbers-toggle]');
      const toggleLabel = toggleButton?.querySelector('[data-toggle-label]');
      let numbersVisible = false;

      const maskText = (text) => {
        const raw = String(text ?? '').trim();
        if (!raw) return raw;
        const length = Math.min(Math.max(raw.length, 4), 12);
        return '•'.repeat(length);
      };

      const applySensitiveState = (visible) => {
        numbersVisible = visible;
        const sensitiveEls = Array.from(document.querySelectorAll('[data-sensitive]'));
        sensitiveEls.forEach((el) => {
          if (!el.dataset.original) {
            el.dataset.original = el.textContent || '';
          }
          el.textContent = visible ? el.dataset.original : maskText(el.dataset.original);
          el.classList.toggle('is-masked', !visible);
        });
        if (toggleButton) {
          toggleButton.classList.toggle('is-active', visible);
          toggleButton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        }
        if (toggleLabel) {
          toggleLabel.textContent = visible ? 'Ocultar números' : 'Mostrar números';
        }
      };

      if (toggleButton) {
        toggleButton.addEventListener('click', () => applySensitiveState(!numbersVisible));
      }

      // Gráfico de barras
      const barContainer = document.querySelector('[data-chart-bars]');
      const chartButtons = document.querySelectorAll('[data-chart-toggle]');
      let chartMode = 'status';

      function renderBars() {
        if (!barContainer) return;
        const list = chartMode === 'source' ? (data.sources || []) : (data.status || []);
        const max = Math.max(...list.map((item) => Number(item.total) || 0), 1);
        if (!list.length) {
          barContainer.innerHTML = '<div class="muted">Sem dados para exibir.</div>';
          return;
        }
        barContainer.innerHTML = list.map((item) => {
          const width = Math.round(((Number(item.total) || 0) / max) * 100);
          return `
            <div class="bar-row">
              <div class="bar-label">${esc(item.label || '—')}</div>
              <div class="bar-track"><div class="bar-fill" style="width:${width}%"></div></div>
              <div class="bar-value sensitive" data-sensitive>${fmtNumber(item.total)}</div>
            </div>
          `;
        }).join('');
        applySensitiveState(numbersVisible);
      }

      chartButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          chartButtons.forEach((b) => b.classList.remove('is-active'));
          btn.classList.add('is-active');
          chartMode = btn.dataset.chartToggle;
          renderBars();
        });
      });

      // Ranking
      const leaderboard = document.querySelector('[data-leaderboard]');
      const leadButtons = document.querySelectorAll('[data-lead-toggle]');
      let leadMode = 'vendors';

      function renderLeaderboard() {
        if (!leaderboard) return;
        const list = leadMode === 'states' ? (data.geo || []) : (data.topVendors || []);
        if (!list.length) {
          leaderboard.innerHTML = '<li class="muted">Sem dados suficientes.</li>';
          return;
        }
        leaderboard.innerHTML = list.map((item, idx) => {
          if (leadMode === 'states') {
            return `
              <li>
                <div>
                  <div><strong>${idx + 1}.</strong> ${esc(item.label || '—')}</div>
                  <div class="meta">Clientes</div>
                </div>
                <div class="stat-value sensitive" data-sensitive style="font-size:16px;">${fmtNumber(item.total)}</div>
              </li>
            `;
          }
          const potential = item.potential_value ?? item.potentialValue;
          const products = item.product_count ?? item.productCount;
          return `
            <li>
              <div>
                <div><strong>${idx + 1}.</strong> ${esc(item.name || '—')}</div>
                <div class="meta">Produtos: <span class="sensitive" data-sensitive>${fmtNumber(products)}</span></div>
              </div>
              <div style="text-align:right;">
                <div class="stat-value sensitive" data-sensitive style="font-size:16px;">${fmtCurrency(potential)}</div>
                <div class="meta">Potencial de receita</div>
              </div>
            </li>
          `;
        }).join('');
        applySensitiveState(numbersVisible);
      }

      leadButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          leadButtons.forEach((b) => b.classList.remove('is-active'));
          btn.classList.add('is-active');
          leadMode = btn.dataset.leadToggle;
          renderLeaderboard();
        });
      });

      renderBars();
      renderLeaderboard();
      applySensitiveState(false);
    })();
  </script>
  <script>
    (() => {
      const timeclock = document.querySelector('[data-timeclock]');
      const clockNow = document.querySelector('[data-clock-now]');
      if (!timeclock || !clockNow) return;
      const openSince = Number(timeclock.dataset.openSince || 0);
      const elapsedEl = timeclock.querySelector('[data-clock-elapsed]');
      const pad = (value) => String(value).padStart(2, '0');
      const formatNow = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
      const formatElapsed = (totalSeconds) => {
        const seconds = Math.max(0, Math.floor(totalSeconds));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;
        return `${pad(hours)}:${pad(minutes)}:${pad(remainder)}`;
      };

      const tick = () => {
        const now = new Date();
        clockNow.textContent = formatNow(now);
        if (elapsedEl && openSince) {
          elapsedEl.textContent = formatElapsed(Date.now() / 1000 - openSince);
        }
      };

      tick();
      setInterval(tick, 1000);
    })();
  </script>
  <script>
    (() => {
      const modal = document.querySelector('[data-cost-modal]');
      const backdrop = document.querySelector('[data-cost-modal-backdrop]');
      const form = modal?.querySelector('[data-cost-form]');
      if (!modal || !backdrop || !form) {
        return;
      }
      const productIdInput = form.querySelector('input[name="product_id"]');
      const costInput = form.querySelector('input[name="cost"]');
      const productNameEl = form.querySelector('[data-cost-product-name]');
      const productSkuEl = form.querySelector('[data-cost-product-sku]');
      const productStockEl = form.querySelector('[data-cost-product-stock]');
      const errorEl = form.querySelector('[data-cost-error]');
      const saveButton = form.querySelector('[data-cost-modal-save]');
      const closeTriggers = modal.querySelectorAll('[data-cost-modal-close], [data-cost-modal-cancel]');
      const table = document.querySelector('[data-cost-table]');
      const emptyMessage = document.querySelector('[data-cost-empty]');
      const badge = document.querySelector('[data-cost-missing-count]');
      if (!productIdInput || !costInput) {
        return;
      }

      const parseNumber = (value) => {
        if (window.RetratoNumber && typeof window.RetratoNumber.parse === 'function') {
          return window.RetratoNumber.parse(value);
        }
        const normalized = String(value ?? '').trim().replace(/[^\d,.-]/g, '').replace(',', '.');
        if (normalized === '' || normalized === '-' || normalized === '+') {
          return null;
        }
        const numeric = Number(normalized);
        return Number.isFinite(numeric) ? numeric : null;
      };

      const setModalVisible = (visible) => {
        modal.hidden = !visible;
        backdrop.hidden = !visible;
        if (!visible && errorEl) {
          errorEl.hidden = true;
        }
      };

      const toggleTableState = () => {
        if (!table) {
          return;
        }
        const hasRow = table.querySelector('tbody tr');
        if (hasRow) {
          table.removeAttribute('hidden');
          emptyMessage?.setAttribute('hidden', '');
        } else {
          table.setAttribute('hidden', '');
          emptyMessage?.removeAttribute('hidden');
        }
      };

      const updateBadge = (delta) => {
        if (!badge) {
          return;
        }
        const current = Number(badge.textContent) || 0;
        const next = Math.max(0, current + delta);
        badge.textContent = next;
      };

      const showError = (message) => {
        if (!errorEl) {
          return;
        }
        errorEl.textContent = message;
        errorEl.hidden = false;
      };

      const closeModal = () => {
        setModalVisible(false);
        productIdInput.value = '';
        costInput.value = '';
      };

      const openModal = (trigger) => {
        const productId = trigger?.dataset?.productId;
        if (!productId) {
          return;
        }
        productIdInput.value = productId;
        if (productNameEl) {
          productNameEl.textContent = trigger.dataset.productName || 'Produto';
        }
        if (productSkuEl) {
          const skuValue = trigger.dataset.productSku;
          productSkuEl.textContent = skuValue ? `SKU ${skuValue}` : '';
        }
        if (productStockEl) {
          const stockValue = trigger.dataset.productStock;
          productStockEl.textContent = stockValue ? `Disponível: ${stockValue}` : '';
        }
        costInput.value = '';
        if (errorEl) {
          errorEl.hidden = true;
        }
        setModalVisible(true);
        setTimeout(() => {
          costInput.focus();
        }, 0);
      };

      const removeRow = (productId) => {
        if (!table) {
          return;
        }
        const row = table.querySelector(`[data-cost-row="${productId}"]`);
        if (row) {
          row.remove();
        }
        toggleTableState();
      };

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const productId = productIdInput.value;
        const parsedCost = parseNumber(costInput.value);
        if (!productId) {
          showError('Produto inválido.');
          return;
        }
        if (parsedCost === null || parsedCost < 0) {
          showError('Informe um custo válido.');
          return;
        }
        if (saveButton) {
          saveButton.disabled = true;
        }
        try {
          const response = await fetch('produto-cost-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: Number(productId) || 0, cost: parsedCost }),
          });
          const payload = await response.json();
          if (!response.ok || payload.status !== 'ok') {
            showError(payload.message || 'Não foi possível atualizar o custo.');
            return;
          }
          removeRow(productId);
          updateBadge(-1);
          closeModal();
        } catch (error) {
          showError('Erro ao salvar o custo.');
        } finally {
          if (saveButton) {
            saveButton.disabled = false;
          }
        }
      });

      closeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', closeModal);
      });
      backdrop.addEventListener('click', closeModal);
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
          closeModal();
        }
      });
    document.querySelectorAll('[data-cost-edit]').forEach((trigger) => {
      trigger.addEventListener('click', () => openModal(trigger));
    });
  })();
</script>
  <script>
    (() => {
      const triggerSelector = '[data-widget-camera-trigger]';
  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const trigger = target.closest(triggerSelector);
    if (!trigger) {
      return;
        }
        event.preventDefault();
        const targetId = trigger.dataset.widgetCameraTrigger;
        if (!targetId) {
          return;
        }
        const input = document.getElementById(targetId);
        if (!input) {
          return;
        }
        input.value = '';
        input.click();
    });
  })();
</script>
  <script>
    (() => {
      const toggle = document.querySelector('[data-product-activity-trigger]');
      const panel = document.querySelector('[data-product-activity-panel]');
      if (!toggle || !panel) {
        return;
      }
      toggle.addEventListener('click', () => {
        const isHidden = panel.hasAttribute('hidden');
        if (isHidden) {
          panel.removeAttribute('hidden');
          toggle.setAttribute('aria-expanded', 'true');
        } else {
          panel.setAttribute('hidden', '');
          toggle.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
  <script>
    (() => {
      const setStatus = (el, message, type) => {
        if (!el) {
          return;
        }
        el.textContent = message;
        el.classList.remove('widget-camera-status--error', 'widget-camera-status--success', 'widget-camera-status--info');
        if (type) {
          el.classList.add(`widget-camera-status--${type}`);
        }
      };

      const uploadImages = async (productId, input, statusEl, triggerButton) => {
        const files = Array.from(input.files || []).filter((file) => file && file.size > 0);
        if (files.length === 0) {
          setStatus(statusEl, 'Nenhuma imagem selecionada.', 'error');
          return;
        }
        const formData = new FormData();
        formData.append('product_id', productId);
        files.forEach((file) => formData.append('images[]', file));
        triggerButton?.setAttribute('disabled', 'true');
        setStatus(statusEl, 'Enviando imagens...', 'info');
        try {
          const response = await fetch('produto-missing-photo-upload.php', {
            method: 'POST',
            body: formData,
          });
          const payload = await response.json();
          if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Não foi possível enviar as imagens.');
          }
          setStatus(statusEl, payload.message ?? 'Imagens enviadas com sucesso.', 'success');
        } catch (error) {
          setStatus(statusEl, error.message || 'Erro ao atualizar imagens.', 'error');
        } finally {
          triggerButton?.removeAttribute('disabled');
          input.value = '';
        }
      };

      document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-widget-camera-input]');
        if (!input) {
          return;
        }
        const productId = Number(input.dataset.productId || 0);
        if (productId <= 0) {
          return;
        }
        const statusEl = input.closest('td')?.querySelector('[data-widget-camera-status]');
        const triggerButton = document.querySelector(`[data-widget-camera-trigger="${input.id}"]`);
        uploadImages(productId, input, statusEl, triggerButton);
      });
    })();
  </script>
  <script>
    (() => {
      const widget = document.querySelector('.block.sales-widget');
      const panel = widget ? widget.querySelector('[data-margin-breakdown-panel]') : null;
      const script = widget ? widget.querySelector('#sales-margin-breakdown-data') : null;
      const triggers = widget ? Array.from(widget.querySelectorAll('[data-margin-total-trigger]')) : [];
      if (!panel || !script) {
        return;
      }
      const tableBody = panel.querySelector('[data-margin-breakdown-body]');
      const periodLabel = panel.querySelector('[data-margin-breakdown-period]');
      const emptyMessage = panel.querySelector('[data-margin-breakdown-empty]');
      const closeButton = panel.querySelector('[data-margin-breakdown-close]');
      const periodLabels = widget.dataset.periodLabels ? JSON.parse(widget.dataset.periodLabels) : {};
      let dataMap = {};
      try {
        dataMap = JSON.parse(script.textContent || '{}');
      } catch {
        dataMap = {};
      }

      const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
      const integerFormatter = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
      const percentFormatter = new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2 });

      const createCell = (content) => {
        const cell = document.createElement('td');
        cell.textContent = content !== null && content !== undefined && content !== '' ? content : '—';
        return cell;
      };

      const renderRows = (rows) => {
        if (!tableBody) {
          return;
        }
        tableBody.innerHTML = '';
        rows.forEach((row) => {
          const tr = document.createElement('tr');
          tr.appendChild(createCell(row.name));
          tr.appendChild(createCell(row.sku));
          tr.appendChild(createCell(integerFormatter.format(row.quantity ?? 0)));
          tr.appendChild(createCell(currencyFormatter.format(row.revenue ?? 0)));
          tr.appendChild(createCell(currencyFormatter.format(row.cost ?? 0)));
          tr.appendChild(createCell(currencyFormatter.format(row.profit ?? 0)));
          tr.appendChild(createCell(row.margin !== null && row.margin !== undefined ? percentFormatter.format(row.margin) : '—'));
          tableBody.appendChild(tr);
        });
      };

      const openPanel = (periodKey) => {
        const rows = dataMap[periodKey] || [];
        if (periodLabel) {
          periodLabel.textContent = periodLabels[periodKey] ?? periodKey;
        }
        if (rows.length === 0) {
          tableBody && (tableBody.innerHTML = '');
          emptyMessage && emptyMessage.removeAttribute('hidden');
        } else {
          renderRows(rows);
          emptyMessage && emptyMessage.setAttribute('hidden', '');
        }
        panel.removeAttribute('hidden');
      };

      triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          const periodKey = trigger.dataset.marginPeriod;
          if (!periodKey) {
            return;
          }
          openPanel(periodKey);
        });
      });

      closeButton?.addEventListener('click', () => {
        panel.setAttribute('hidden', '');
      });
    })();
  </script>
  <script>
    (() => {
      const modal = document.querySelector('[data-payables-modal]');
      const backdrop = document.querySelector('[data-payables-modal-backdrop]');
      if (!modal || !backdrop) {
        return;
      }
      const form = modal.querySelector('form');
      const entryInput = form?.querySelector('input[name="dashboard_payable_mark_paid"]');
      const descriptionEl = modal.querySelector('[data-payables-description]');
      const dueEl = modal.querySelector('[data-payables-due]');
      const amountEl = modal.querySelector('[data-payables-amount]');
      const statusEl = modal.querySelector('[data-payables-status]');
      const methodSelect = form?.querySelector('[data-payable-method]');
      const bankSelect = form?.querySelector('[data-payable-bank]');
      const terminalSelect = form?.querySelector('[data-payable-terminal]');
      const openButtons = document.querySelectorAll('[data-payable-open]');
      const closeTriggers = modal.querySelectorAll('[data-payables-modal-close]');

      const formatDate = (value) => {
        if (!value) return '—';
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
          return value;
        }
        return new Intl.DateTimeFormat('pt-BR').format(parsed);
      };

      const updateRequirements = () => {
        if (!methodSelect) {
          return;
        }
        const option = methodSelect.selectedOptions[0];
        const requiresBank = option?.dataset?.requiresBank === '1';
        const requiresTerminal = option?.dataset?.requiresTerminal === '1';
        const bankLabel = bankSelect?.closest('label');
        const terminalLabel = terminalSelect?.closest('label');
        bankLabel?.classList.toggle('is-required', requiresBank);
        terminalLabel?.classList.toggle('is-required', requiresTerminal);
      };

      const closeModal = () => {
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        backdrop.setAttribute('hidden', '');
        document.body.classList.remove('modal-open');
      };

      const openModal = (source) => {
        if (!form || !entryInput) {
          return;
        }
        entryInput.value = source.dataset.entryId || '';
        descriptionEl && (descriptionEl.textContent = source.dataset.entryDescription || '—');
        dueEl && (dueEl.textContent = formatDate(source.dataset.entryDue));
        amountEl && (amountEl.textContent = source.dataset.entryAmount || '—');
        statusEl && (statusEl.textContent = source.dataset.entryStatusLabel || '—');
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        backdrop.removeAttribute('hidden');
        document.body.classList.add('modal-open');
        methodSelect && (methodSelect.value = '');
        bankSelect && (bankSelect.value = '');
        terminalSelect && (terminalSelect.value = '');
        updateRequirements();
      };

      openButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          openModal(btn);
        });
      });
      closeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          closeModal();
        });
      });
      backdrop.addEventListener('click', closeModal);
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
          closeModal();
        }
      });
      methodSelect?.addEventListener('change', updateRequirements);
    })();
  </script>
</body>
</html>
