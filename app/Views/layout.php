<?php

declare(strict_types=1);

$isAuthenticated = isset($authUser) && is_array($authUser);
$path = (string) ($currentPath ?? '');
$authPermissions = is_array($authUser['permissions'] ?? null) ? $authUser['permissions'] : [];
$canViewDashboard = in_array('dashboard.view', $authPermissions, true);
$canViewUsers = in_array('users.view', $authPermissions, true);
$canManageUsers = in_array('users.manage', $authPermissions, true);
$canViewSecurity = in_array('security.view', $authPermissions, true);
$canManageSecurity = in_array('security.manage', $authPermissions, true);
$canViewOpsPanel = $canViewSecurity;
$canViewLgpd = in_array('lgpd.view', $authPermissions, true);
$canManageLgpd = in_array('lgpd.manage', $authPermissions, true);
$canViewBudget = in_array('budget.view', $authPermissions, true);
$canManageBudget = in_array('budget.manage', $authPermissions, true);
$canViewPeople = in_array('people.view', $authPermissions, true);
$canManagePeople = in_array('people.manage', $authPermissions, true);
$canViewMteDestinations = in_array('mte_destinations.view', $authPermissions, true);
$canManageMteDestinations = in_array('mte_destinations.manage', $authPermissions, true);
$canViewDocumentTypes = in_array('document_type.view', $authPermissions, true);
$canManageDocumentTypes = in_array('document_type.manage', $authPermissions, true);
$canViewCostItems = in_array('cost_item.view', $authPermissions, true);
$canManageCostItems = in_array('cost_item.manage', $authPermissions, true);
$canViewOrgans = in_array('organs.view', $authPermissions, true);
$canManageOrgans = in_array('organs.manage', $authPermissions, true);
$canViewCdos = in_array('cdo.view', $authPermissions, true);
$canManageCdos = in_array('cdo.manage', $authPermissions, true);
$canViewInvoices = in_array('invoice.view', $authPermissions, true);
$canManageInvoices = in_array('invoice.manage', $authPermissions, true);
$canViewCostMirrors = in_array('cost_mirror.view', $authPermissions, true);
$canManageCostMirrors = in_array('cost_mirror.manage', $authPermissions, true);
$canViewOfficeTemplates = in_array('office_template.view', $authPermissions, true);
$canManageOfficeTemplates = in_array('office_template.manage', $authPermissions, true);
$canViewProcessMeta = in_array('process_meta.view', $authPermissions, true);
$canManageProcessMeta = in_array('process_meta.manage', $authPermissions, true);
$canViewSla = in_array('sla.view', $authPermissions, true);
$canManageSla = in_array('sla.manage', $authPermissions, true);
$canViewReports = in_array('report.view', $authPermissions, true);
$canUseGlobalSearch = $canViewPeople || $canViewOrgans || $canViewProcessMeta;
$movementBucketFilter = mb_strtolower(trim((string) ($_GET['movement_bucket'] ?? '')));
if (!in_array($movementBucketFilter, ['entrando', 'saindo'], true)) {
    $movementBucketFilter = '';
}

$renderMenuIcon = static function (string $icon): string {
    switch ($icon) {
        case 'dashboard':
            $body = '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect>'
                . '<rect x="14" y="3" width="7" height="4" rx="1.5"></rect>'
                . '<rect x="14" y="10" width="7" height="11" rx="1.5"></rect>'
                . '<rect x="3" y="13" width="7" height="8" rx="1.5"></rect>';
            break;
        case 'people':
            $body = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>'
                . '<circle cx="9" cy="7" r="4"></circle>'
                . '<path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>'
                . '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>';
            break;
        case 'inbound':
            $body = '<path d="M12 3v14"></path>'
                . '<path d="M7 12l5 5 5-5"></path>'
                . '<rect x="4" y="18" width="16" height="3" rx="1.2"></rect>';
            break;
        case 'outbound':
            $body = '<path d="M12 21V7"></path>'
                . '<path d="M7 12l5-5 5 5"></path>'
                . '<rect x="4" y="3" width="16" height="3" rx="1.2"></rect>';
            break;
        case 'workflow':
            $body = '<circle cx="5" cy="6" r="2"></circle>'
                . '<circle cx="19" cy="6" r="2"></circle>'
                . '<circle cx="12" cy="18" r="2"></circle>'
                . '<path d="M7 6h10"></path>'
                . '<path d="M12 16V8"></path>';
            break;
        case 'users_admin':
            $body = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>'
                . '<circle cx="9" cy="7" r="4"></circle>'
                . '<path d="M20 8l2 2-4 4-2-2z"></path>'
                . '<path d="M18 12l-1 3 3-1"></path>';
            break;
        case 'lgpd':
            $body = '<path d="M12 3l8 4v5c0 5-3.4 9.3-8 10-4.6-.7-8-5-8-10V7z"></path>'
                . '<path d="M9 12l2 2 4-4"></path>';
            break;
        case 'security':
            $body = '<path d="M12 2l8 4v6c0 5-3.4 9.3-8 10-4.6-.7-8-5-8-10V6z"></path>'
                . '<circle cx="12" cy="13" r="2"></circle>'
                . '<path d="M12 15v3"></path>';
            break;
        case 'ops':
            $body = '<path d="M3 12h3l2-4 3 8 2-4h6"></path>'
                . '<rect x="2.5" y="4" width="19" height="16" rx="2"></rect>';
            break;
        case 'budget':
            $body = '<path d="M3 20h18"></path>'
                . '<path d="M6 16l4-5 4 3 4-6"></path>'
                . '<circle cx="6" cy="16" r="1.5"></circle>'
                . '<circle cx="10" cy="11" r="1.5"></circle>'
                . '<circle cx="14" cy="14" r="1.5"></circle>'
                . '<circle cx="18" cy="8" r="1.5"></circle>';
            break;
        case 'organs':
            $body = '<rect x="3" y="3" width="18" height="18" rx="2"></rect>'
                . '<path d="M9 21V8h6v13"></path>'
                . '<path d="M9 12h6"></path>';
            break;
        case 'mte_destination':
            $body = '<path d="M12 22s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11z"></path>'
                . '<circle cx="12" cy="11" r="2.5"></circle>';
            break;
        case 'document_type':
            $body = '<path d="M6 2h9l5 5v15H6z"></path>'
                . '<path d="M15 2v5h5"></path>'
                . '<path d="M9 12h8"></path>'
                . '<path d="M9 16h8"></path>'
                . '<path d="M9 20h5"></path>';
            break;
        case 'cost_item':
            $body = '<path d="M4 7h16"></path>'
                . '<path d="M4 12h16"></path>'
                . '<path d="M4 17h16"></path>'
                . '<circle cx="8" cy="7" r="1.5"></circle>'
                . '<circle cx="16" cy="12" r="1.5"></circle>'
                . '<circle cx="10" cy="17" r="1.5"></circle>';
            break;
        case 'cdo':
            $body = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>'
                . '<path d="M14 2v6h6"></path>'
                . '<path d="M8 13h8"></path>'
                . '<path d="M8 17h8"></path>';
            break;
        case 'invoice':
            $body = '<path d="M4 4h16v16l-2-1.5L16 20l-2-1.5L12 20l-2-1.5L8 20l-2-1.5L4 20z"></path>'
                . '<path d="M8 8h8"></path>'
                . '<path d="M8 12h8"></path>';
            break;
        case 'cost_mirror':
            $body = '<path d="M3 5h18v14H3z"></path>'
                . '<path d="M7 9h10"></path>'
                . '<path d="M7 13h6"></path>'
                . '<circle cx="17" cy="13" r="1.5"></circle>';
            break;
        case 'office_template':
            $body = '<path d="M6 2h9l5 5v15H6z"></path>'
                . '<path d="M15 2v5h5"></path>'
                . '<path d="M9 12h8"></path>'
                . '<path d="M9 16h8"></path>';
            break;
        case 'process_meta':
            $body = '<path d="M6 3h9l5 5v13H6z"></path>'
                . '<path d="M15 3v5h5"></path>'
                . '<path d="M9 13h6"></path>'
                . '<path d="M9 17h4"></path>'
                . '<circle cx="17" cy="17" r="3"></circle>';
            break;
        case 'sla':
            $body = '<path d="M12 3a9 9 0 1 0 9 9"></path>'
                . '<path d="M12 7v5l3 2"></path>'
                . '<path d="M17 3h4v4"></path>';
            break;
        case 'report':
            $body = '<path d="M4 4h16v16H4z"></path>'
                . '<path d="M8 8h8"></path>'
                . '<path d="M8 12h8"></path>'
                . '<path d="M8 16h5"></path>';
            break;
        case 'search':
            $body = '<circle cx="11" cy="11" r="7"></circle>'
                . '<path d="M20 20l-4-4"></path>';
            break;
        case 'plus':
            $body = '<circle cx="12" cy="12" r="9"></circle>'
                . '<path d="M12 8v8"></path>'
                . '<path d="M8 12h8"></path>';
            break;
        case 'key':
            $body = '<circle cx="8" cy="15" r="3"></circle>'
                . '<path d="M11 15h10"></path>'
                . '<path d="M17 15v-2"></path>'
                . '<path d="M20 15v-2"></path>';
            break;
        default:
            $body = '<circle cx="12" cy="12" r="9"></circle>';
            break;
    }

    return '<svg viewBox="0 0 24 24" role="presentation" focusable="false">' . $body . '</svg>';
};

$primaryMenuItems = [];
if ($canViewDashboard) {
    $primaryMenuItems[] = [
        'label' => 'Dashboard',
        'href' => '/dashboard',
        'icon' => 'dashboard',
        'active' => $path === '/dashboard' || $path === '/' || $path === '/dashboard2',
    ];
}
if ($canUseGlobalSearch) {
    $primaryMenuItems[] = [
        'label' => 'Busca global',
        'href' => '/global-search',
        'icon' => 'search',
        'active' => str_starts_with($path, '/global-search'),
    ];
}
if ($canViewBudget) {
    $primaryMenuItems[] = [
        'label' => 'Orçamento',
        'href' => '/budget',
        'icon' => 'budget',
        'active' => str_starts_with($path, '/budget'),
    ];
}
if ($canViewMteDestinations) {
    $primaryMenuItems[] = [
        'label' => 'Lotações MTE',
        'href' => '/mte-destinations',
        'icon' => 'mte_destination',
        'active' => str_starts_with($path, '/mte-destinations'),
    ];
}
if ($canViewDocumentTypes || $canManageDocumentTypes) {
    $primaryMenuItems[] = [
        'label' => 'Tipos de documento',
        'href' => '/document-types',
        'icon' => 'document_type',
        'active' => str_starts_with($path, '/document-types'),
    ];
}
if ($canViewCostItems || $canManageCostItems) {
    $primaryMenuItems[] = [
        'label' => 'Itens de custo',
        'href' => '/cost-items',
        'icon' => 'cost_item',
        'active' => str_starts_with($path, '/cost-items'),
    ];
}
if ($canViewOrgans) {
    $primaryMenuItems[] = [
        'label' => 'Órgãos',
        'href' => '/organs',
        'icon' => 'organs',
        'active' => str_starts_with($path, '/organs'),
    ];
}
if ($canManagePeople) {
    $primaryMenuItems[] = [
        'label' => 'Fluxos BPMN',
        'href' => '/pipeline-flows',
        'icon' => 'workflow',
        'active' => str_starts_with($path, '/pipeline-flows'),
    ];
}
if ($canViewSla) {
    $primaryMenuItems[] = [
        'label' => 'SLA',
        'href' => '/sla-alerts',
        'icon' => 'sla',
        'active' => str_starts_with($path, '/sla-alerts'),
    ];
}
if ($canViewReports) {
    $primaryMenuItems[] = [
        'label' => 'Relatorios',
        'href' => '/reports',
        'icon' => 'report',
        'active' => str_starts_with($path, '/reports'),
    ];
}

$peopleMenuItems = [];
if ($canViewPeople) {
    $isPeopleModule = str_starts_with($path, '/people');
    $isPeopleList = $path === '/people';

    $peopleMenuItems[] = [
        'label' => 'Todas as pessoas',
        'href' => '/people',
        'icon' => 'people',
        'active' => $isPeopleModule && (!$isPeopleList || $movementBucketFilter === ''),
    ];
    $peopleMenuItems[] = [
        'label' => 'Pessoas entrando',
        'href' => '/people?movement_bucket=entrando',
        'icon' => 'inbound',
        'active' => $isPeopleList && $movementBucketFilter === 'entrando',
    ];
    $peopleMenuItems[] = [
        'label' => 'Pessoas saindo',
        'href' => '/people?movement_bucket=saindo',
        'icon' => 'outbound',
        'active' => $isPeopleList && $movementBucketFilter === 'saindo',
    ];
}

$documentMenuItems = [];
if ($canViewCdos) {
    $documentMenuItems[] = [
        'label' => 'CDOs',
        'href' => '/cdos',
        'icon' => 'cdo',
        'active' => str_starts_with($path, '/cdos'),
    ];
}
if ($canViewInvoices) {
    $documentMenuItems[] = [
        'label' => 'Boletos',
        'href' => '/invoices',
        'icon' => 'invoice',
        'active' => str_starts_with($path, '/invoices'),
    ];
}
if ($canViewCostMirrors) {
    $documentMenuItems[] = [
        'label' => 'Espelhos',
        'href' => '/cost-mirrors',
        'icon' => 'cost_mirror',
        'active' => str_starts_with($path, '/cost-mirrors'),
    ];
}
if ($canViewOfficeTemplates) {
    $documentMenuItems[] = [
        'label' => 'Oficios',
        'href' => '/office-templates',
        'icon' => 'office_template',
        'active' => str_starts_with($path, '/office-templates') || str_starts_with($path, '/office-documents'),
    ];
}
if ($canViewProcessMeta) {
    $documentMenuItems[] = [
        'label' => 'Processo formal',
        'href' => '/process-meta',
        'icon' => 'process_meta',
        'active' => str_starts_with($path, '/process-meta'),
    ];
}

$systemMenuItems = [];
if ($canViewUsers) {
    $systemMenuItems[] = [
        'label' => 'Usuarios',
        'href' => '/users',
        'icon' => 'users_admin',
        'active' => str_starts_with($path, '/users'),
    ];
}
if ($canViewSecurity) {
    $systemMenuItems[] = [
        'label' => 'Seguranca',
        'href' => '/security',
        'icon' => 'security',
        'active' => str_starts_with($path, '/security'),
    ];
}
if ($canViewOpsPanel) {
    $systemMenuItems[] = [
        'label' => 'Observabilidade',
        'href' => '/ops/health-panel',
        'icon' => 'ops',
        'active' => str_starts_with($path, '/ops/health-panel'),
    ];
}
if ($canViewLgpd) {
    $systemMenuItems[] = [
        'label' => 'LGPD',
        'href' => '/lgpd',
        'icon' => 'lgpd',
        'active' => str_starts_with($path, '/lgpd'),
    ];
}

$menuGroups = [];
if ($peopleMenuItems !== []) {
    $menuGroups[] = ['title' => 'Pessoas', 'items' => $peopleMenuItems];
}
if ($documentMenuItems !== []) {
    $menuGroups[] = ['title' => 'Documentos', 'items' => $documentMenuItems];
}
if ($systemMenuItems !== []) {
    $menuGroups[] = ['title' => 'Sistema', 'items' => $systemMenuItems];
}

$quickMenuItems = [];
if ($canManagePeople) {
    $quickMenuItems[] = [
        'label' => 'Nova pessoa',
        'href' => '/people/create',
        'icon' => 'plus',
        'active' => $path === '/people/create',
    ];
    $quickMenuItems[] = [
        'label' => 'Novo fluxo BPMN',
        'href' => '/pipeline-flows/create',
        'icon' => 'plus',
        'active' => $path === '/pipeline-flows/create',
    ];
}
if ($canManageUsers) {
    $quickMenuItems[] = [
        'label' => 'Novo usuario',
        'href' => '/users/create',
        'icon' => 'plus',
        'active' => $path === '/users/create',
    ];
}
if ($canManageSecurity) {
    $quickMenuItems[] = [
        'label' => 'Seguranca',
        'href' => '/security',
        'icon' => 'plus',
        'active' => str_starts_with($path, '/security'),
    ];
}
if ($canManageLgpd) {
    $quickMenuItems[] = [
        'label' => 'LGPD',
        'href' => '/lgpd',
        'icon' => 'plus',
        'active' => str_starts_with($path, '/lgpd'),
    ];
}
if ($canManageMteDestinations) {
    $quickMenuItems[] = [
        'label' => 'Nova lotação MTE',
        'href' => '/mte-destinations/create',
        'icon' => 'plus',
        'active' => $path === '/mte-destinations/create',
    ];
}
if ($canManageBudget) {
    $quickMenuItems[] = [
        'label' => 'Simular contratação',
        'href' => '/budget',
        'icon' => 'plus',
        'active' => str_starts_with($path, '/budget'),
    ];
}
if ($canManageOrgans) {
    $quickMenuItems[] = [
        'label' => 'Novo órgão',
        'href' => '/organs/create',
        'icon' => 'plus',
        'active' => $path === '/organs/create',
    ];
}
if ($canManageCdos) {
    $quickMenuItems[] = [
        'label' => 'Novo CDO',
        'href' => '/cdos/create',
        'icon' => 'plus',
        'active' => $path === '/cdos/create',
    ];
}
if ($canManageInvoices) {
    $quickMenuItems[] = [
        'label' => 'Novo boleto',
        'href' => '/invoices/create',
        'icon' => 'plus',
        'active' => $path === '/invoices/create',
    ];
}
if ($canManageCostMirrors) {
    $quickMenuItems[] = [
        'label' => 'Novo espelho',
        'href' => '/cost-mirrors/create',
        'icon' => 'plus',
        'active' => $path === '/cost-mirrors/create',
    ];
}
if ($canManageOfficeTemplates) {
    $quickMenuItems[] = [
        'label' => 'Novo oficio',
        'href' => '/office-templates/create',
        'icon' => 'plus',
        'active' => $path === '/office-templates/create',
    ];
}
if ($canManageProcessMeta) {
    $quickMenuItems[] = [
        'label' => 'Novo processo',
        'href' => '/process-meta/create',
        'icon' => 'plus',
        'active' => $path === '/process-meta/create',
    ];
}
if ($canManageSla) {
    $quickMenuItems[] = [
        'label' => 'Regras SLA',
        'href' => '/sla-alerts/rules',
        'icon' => 'plus',
        'active' => $path === '/sla-alerts/rules',
    ];
}
if ($isAuthenticated) {
    $quickMenuItems[] = [
        'label' => 'Trocar senha',
        'href' => '/users/password',
        'icon' => 'key',
        'active' => $path === '/users/password',
    ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? $appName ?? 'Reembolso') ?></title>
  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
<?php if ($isAuthenticated): ?>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-dot"></span>
        <strong><?= e($appName ?? 'Reembolso') ?></strong>
      </div>
      <nav class="menu">
        <?php foreach ($primaryMenuItems as $item): ?>
          <a class="menu-item <?= ($item['active'] ?? false) ? 'is-active' : '' ?>" href="<?= e(url((string) ($item['href'] ?? '/dashboard'))) ?>">
            <span class="menu-item-content">
              <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($item['icon'] ?? 'dashboard')) ?></span>
              <span><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
            </span>
          </a>
        <?php endforeach; ?>

        <?php foreach ($menuGroups as $group): ?>
          <p class="menu-group-title"><?= e((string) ($group['title'] ?? 'Grupo')) ?></p>
          <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
            <a class="menu-item menu-item-sub <?= ($item['active'] ?? false) ? 'is-active' : '' ?>" href="<?= e(url((string) ($item['href'] ?? '/dashboard'))) ?>">
              <span class="menu-item-content">
                <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($item['icon'] ?? 'dashboard')) ?></span>
                <span><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <?php if ($quickMenuItems !== []): ?>
          <p class="menu-group-title">Ações rápidas</p>
          <?php foreach ($quickMenuItems as $item): ?>
            <a class="menu-item menu-item-quick <?= ($item['active'] ?? false) ? 'is-active' : '' ?>" href="<?= e(url((string) ($item['href'] ?? '/dashboard'))) ?>">
              <span class="menu-item-content">
                <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($item['icon'] ?? 'plus')) ?></span>
                <span><?= e((string) ($item['label'] ?? 'Ação')) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </nav>
      <form method="post" action="<?= e(url('/logout')) ?>" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary full-width">Sair</button>
      </form>
    </aside>
    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="topbar-title"><?= e($title ?? '') ?></p>
          <p class="topbar-subtitle">Usuário: <?= e($authUser['name'] ?? 'N/A') ?></p>
        </div>
      </header>
      <?php if (!empty($flash_success)): ?>
        <div class="toast toast-success" role="status"><?= e((string) $flash_success) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_error)): ?>
        <div class="toast toast-error" role="alert"><?= e((string) $flash_error) ?></div>
      <?php endif; ?>
      <section class="page-body">
        <?= $content ?>
      </section>
    </main>
  </div>
<?php else: ?>
  <?php if (!empty($flash_success)): ?>
    <div class="toast toast-success" role="status"><?= e((string) $flash_success) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash_error)): ?>
    <div class="toast toast-error" role="alert"><?= e((string) $flash_error) ?></div>
  <?php endif; ?>
  <?= $content ?>
<?php endif; ?>
<script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
