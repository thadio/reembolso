<?php

declare(strict_types=1);

$isAuthenticated = isset($authUser) && is_array($authUser);
$path = (string) ($currentPath ?? '');
$authPermissions = is_array($authUser['permissions'] ?? null) ? $authUser['permissions'] : [];
$canViewDashboard = in_array('dashboard.view', $authPermissions, true);
$canViewUsers = in_array('users.view', $authPermissions, true);
$canManageUsers = in_array('users.manage', $authPermissions, true);
$canViewSecurity = in_array('security.view', $authPermissions, true);
$canViewOpsPanel = $canViewSecurity;
$canViewLgpd = in_array('lgpd.view', $authPermissions, true);
$canViewBudget = in_array('budget.view', $authPermissions, true);
$canViewPeople = in_array('people.view', $authPermissions, true);
$canManagePeople = in_array('people.manage', $authPermissions, true);
$canViewMteDestinations = in_array('mte_destinations.view', $authPermissions, true);
$canViewDocumentTypes = in_array('document_type.view', $authPermissions, true);
$canManageDocumentTypes = in_array('document_type.manage', $authPermissions, true);
$canViewCostItems = in_array('cost_item.view', $authPermissions, true);
$canManageCostItems = in_array('cost_item.manage', $authPermissions, true);
$canViewOrgans = in_array('organs.view', $authPermissions, true);
$canViewCdos = in_array('cdo.view', $authPermissions, true);
$canViewInvoices = in_array('invoice.view', $authPermissions, true);
$canViewCostMirrors = in_array('cost_mirror.view', $authPermissions, true);
$canViewOfficeTemplates = in_array('office_template.view', $authPermissions, true);
$canViewProcessMeta = in_array('process_meta.view', $authPermissions, true);
$canViewSla = in_array('sla.view', $authPermissions, true);
$canManageSla = in_array('sla.manage', $authPermissions, true);
$canViewReports = in_array('report.view', $authPermissions, true);
$canViewBulkImports = in_array('bulk_import.view', $authPermissions, true);
$canUseGlobalSearch = $canViewPeople;
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
        case 'import_batch':
            $body = '<rect x="3" y="4" width="18" height="16" rx="2"></rect>'
                . '<path d="M12 8v8"></path>'
                . '<path d="M8 12l4 4 4-4"></path>'
                . '<path d="M7 4v-1"></path>'
                . '<path d="M12 4v-1"></path>'
                . '<path d="M17 4v-1"></path>';
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

$menuSections = [];

$panelMenuItems = [];
if ($canViewDashboard) {
    $panelMenuItems[] = [
        'label' => 'Dashboard',
        'href' => '/dashboard',
        'icon' => 'dashboard',
        'active' => $path === '/dashboard' || $path === '/' || $path === '/dashboard2',
    ];
}
if ($canUseGlobalSearch) {
    $panelMenuItems[] = [
        'label' => 'Busca global',
        'href' => '/global-search',
        'icon' => 'search',
        'active' => str_starts_with($path, '/global-search'),
    ];
}
if ($canViewBudget) {
    $panelMenuItems[] = [
        'label' => 'Orçamento',
        'href' => '/budget',
        'icon' => 'budget',
        'active' => str_starts_with($path, '/budget'),
    ];
}
if ($canViewReports) {
    $panelMenuItems[] = [
        'label' => 'Relatórios',
        'href' => '/reports',
        'icon' => 'report',
        'active' => str_starts_with($path, '/reports'),
    ];
}
if ($panelMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Painel',
        'icon' => 'dashboard',
        'items' => $panelMenuItems,
    ];
}

$peopleMenuItems = [];
if ($canViewPeople) {
    $isPeopleModule = str_starts_with($path, '/people');
    $isPeopleList = $path === '/people';
    $isPeoplePending = str_starts_with($path, '/people/pending');

    $peopleMenuItems[] = [
        'label' => 'Todas as pessoas',
        'href' => '/people',
        'icon' => 'people',
        'active' => $isPeopleModule && !$isPeoplePending && (!$isPeopleList || $movementBucketFilter === ''),
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
    $peopleMenuItems[] = [
        'label' => 'Central de pendências',
        'href' => '/people/pending',
        'icon' => 'workflow',
        'active' => $isPeoplePending,
    ];
}
if ($canViewOrgans) {
    $peopleMenuItems[] = [
        'label' => 'Órgãos',
        'href' => '/organs',
        'icon' => 'organs',
        'active' => str_starts_with($path, '/organs'),
    ];
}
if ($canViewMteDestinations) {
    $peopleMenuItems[] = [
        'label' => 'UORG MTE',
        'href' => '/mte-destinations',
        'icon' => 'mte_destination',
        'active' => str_starts_with($path, '/mte-destinations'),
    ];
}
if ($peopleMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Pessoas e estrutura',
        'icon' => 'people',
        'items' => $peopleMenuItems,
    ];
}

$documentMenuItems = [];
if ($canViewDocumentTypes || $canManageDocumentTypes) {
    $documentMenuItems[] = [
        'label' => 'Tipos de documento',
        'href' => '/document-types',
        'icon' => 'document_type',
        'active' => str_starts_with($path, '/document-types'),
    ];
}
if ($canViewCostItems || $canManageCostItems) {
    $documentMenuItems[] = [
        'label' => 'Itens de custo',
        'href' => '/cost-items',
        'icon' => 'cost_item',
        'active' => str_starts_with($path, '/cost-items'),
    ];
}
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
        'active' => str_starts_with($path, '/invoices') && !str_starts_with($path, '/invoices/payment-batches'),
    ];
    $documentMenuItems[] = [
        'label' => 'Lotes de pagamento',
        'href' => '/invoices/payment-batches',
        'icon' => 'invoice',
        'active' => str_starts_with($path, '/invoices/payment-batches'),
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
        'label' => 'Ofícios',
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
if ($documentMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Documentos e custos',
        'icon' => 'cdo',
        'items' => $documentMenuItems,
    ];
}

$bulkImportMenuItems = [];
if ($canViewBulkImports) {
    $bulkImportMenuItems[] = [
        'label' => 'Central de importacoes',
        'href' => '/bulk-imports',
        'icon' => 'import_batch',
        'active' => str_starts_with($path, '/bulk-imports'),
    ];
}
if ($bulkImportMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Importacoes em lote',
        'icon' => 'import_batch',
        'items' => $bulkImportMenuItems,
    ];
}

$flowMenuItems = [];
if ($canManagePeople) {
    $flowMenuItems[] = [
        'label' => 'Fluxos BPMN',
        'href' => '/pipeline-flows',
        'icon' => 'workflow',
        'active' => str_starts_with($path, '/pipeline-flows'),
    ];
}
if ($canViewSla) {
    $flowMenuItems[] = [
        'label' => 'SLA',
        'href' => '/sla-alerts',
        'icon' => 'sla',
        'active' => str_starts_with($path, '/sla-alerts') && $path !== '/sla-alerts/rules',
    ];
}
if ($canManageSla) {
    $flowMenuItems[] = [
        'label' => 'Regras SLA',
        'href' => '/sla-alerts/rules',
        'icon' => 'sla',
        'active' => $path === '/sla-alerts/rules',
    ];
}
if ($flowMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Fluxos e controle',
        'icon' => 'workflow',
        'items' => $flowMenuItems,
    ];
}

$systemMenuItems = [];
if ($canViewUsers) {
    $systemMenuItems[] = [
        'label' => 'Usuários',
        'href' => '/users',
        'icon' => 'users_admin',
        'active' => str_starts_with($path, '/users') && $path !== '/users/password' && !str_starts_with($path, '/users/roles'),
    ];
}
if ($canManageUsers) {
    $systemMenuItems[] = [
        'label' => 'Papéis e permissões',
        'href' => '/users/roles',
        'icon' => 'key',
        'active' => str_starts_with($path, '/users/roles'),
    ];
}
if ($canViewSecurity) {
    $systemMenuItems[] = [
        'label' => 'Segurança',
        'href' => '/security',
        'icon' => 'security',
        'active' => str_starts_with($path, '/security'),
    ];
    $systemMenuItems[] = [
        'label' => 'Dependências',
        'href' => '/integrity/dependencies',
        'icon' => 'security',
        'active' => str_starts_with($path, '/integrity/dependencies'),
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
if ($isAuthenticated) {
    $systemMenuItems[] = [
        'label' => 'Trocar senha',
        'href' => '/users/password',
        'icon' => 'key',
        'active' => $path === '/users/password',
    ];
}
if ($systemMenuItems !== []) {
    $menuSections[] = [
        'title' => 'Administração',
        'icon' => 'users_admin',
        'items' => $systemMenuItems,
    ];
}

$normalizedPath = trim($path, '/');
$pathParts = $normalizedPath === '' ? [] : explode('/', $normalizedPath);
$rawModuleSlug = (string) ($pathParts[0] ?? 'dashboard');
$rawPageSlug = (string) ($pathParts[1] ?? 'index');

$sanitizeSlug = static function (string $value, string $fallback): string {
    $slug = preg_replace('/[^a-z0-9_-]+/i', '-', mb_strtolower(trim($value)));
    $slug = trim((string) $slug, '-');

    return $slug === '' ? $fallback : $slug;
};

$moduleSlug = $sanitizeSlug($rawModuleSlug, 'dashboard');
$pageSlug = $sanitizeSlug($rawPageSlug, 'index');
$bodyClasses = [
    'app-body',
    $isAuthenticated ? 'is-authenticated' : 'is-guest',
    'module-' . $moduleSlug,
    'page-' . $moduleSlug . '-' . $pageSlug,
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? $appName ?? 'Reembolso') ?></title>
  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>">
<?php if ($isAuthenticated): ?>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-dot"></span>
        <strong><?= e($appName ?? 'Reembolso') ?></strong>
      </div>
      <nav class="menu">
        <?php foreach ($menuSections as $section): ?>
          <?php
            $sectionItems = is_array($section['items'] ?? null) ? $section['items'] : [];
            $sectionIsActive = false;
            foreach ($sectionItems as $sectionItem) {
                if (($sectionItem['active'] ?? false) === true) {
                    $sectionIsActive = true;
                    break;
                }
            }
          ?>
          <section class="menu-section <?= $sectionIsActive ? 'is-active' : '' ?>">
            <p class="menu-section-title">
              <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($section['icon'] ?? 'dashboard')) ?></span>
              <span><?= e((string) ($section['title'] ?? 'Seção')) ?></span>
            </p>
            <div class="menu-section-items">
              <?php foreach ($sectionItems as $item): ?>
                <a class="menu-item menu-item-level2 <?= ($item['active'] ?? false) ? 'is-active' : '' ?>" href="<?= e(url((string) ($item['href'] ?? '/dashboard'))) ?>">
                  <span class="menu-item-content">
                    <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($item['icon'] ?? 'dashboard')) ?></span>
                    <span><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </nav>
      <form method="post" action="<?= e(url('/logout')) ?>" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary full-width">Sair</button>
      </form>
    </aside>
    <main class="main-content">
      <header class="topbar">
        <div>
          <h1 class="topbar-title"><?= e($title ?? '') ?></h1>
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
