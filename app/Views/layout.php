<?php

declare(strict_types=1);

$isAuthenticated = isset($authUser) && is_array($authUser);
$path = (string) ($currentPath ?? '');
$authPermissions = is_array($authUser['permissions'] ?? null) ? $authUser['permissions'] : [];
$canViewDashboard = in_array('dashboard.view', $authPermissions, true);
$canViewPeople = in_array('people.view', $authPermissions, true);
$canManagePeople = in_array('people.manage', $authPermissions, true);
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
        case 'organs':
            $body = '<rect x="3" y="3" width="18" height="18" rx="2"></rect>'
                . '<path d="M9 21V8h6v13"></path>'
                . '<path d="M9 12h6"></path>';
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
        case 'plus':
            $body = '<circle cx="12" cy="12" r="9"></circle>'
                . '<path d="M12 8v8"></path>'
                . '<path d="M8 12h8"></path>';
            break;
        default:
            $body = '<circle cx="12" cy="12" r="9"></circle>';
            break;
    }

    return '<svg viewBox="0 0 24 24" role="presentation" focusable="false">' . $body . '</svg>';
};

$mainMenuItems = [];
if ($canViewDashboard) {
    $mainMenuItems[] = [
        'label' => 'Dashboard',
        'href' => '/dashboard',
        'icon' => 'dashboard',
        'active' => $path === '/dashboard' || $path === '/',
    ];
}
if ($canViewPeople) {
    $mainMenuItems[] = [
        'label' => 'Pessoas',
        'href' => '/people',
        'icon' => 'people',
        'active' => str_starts_with($path, '/people'),
    ];
}
if ($canViewOrgans) {
    $mainMenuItems[] = [
        'label' => 'Órgãos',
        'href' => '/organs',
        'icon' => 'organs',
        'active' => str_starts_with($path, '/organs'),
    ];
}
if ($canViewCdos) {
    $mainMenuItems[] = [
        'label' => 'CDOs',
        'href' => '/cdos',
        'icon' => 'cdo',
        'active' => str_starts_with($path, '/cdos'),
    ];
}
if ($canViewInvoices) {
    $mainMenuItems[] = [
        'label' => 'Boletos',
        'href' => '/invoices',
        'icon' => 'invoice',
        'active' => str_starts_with($path, '/invoices'),
    ];
}
if ($canViewCostMirrors) {
    $mainMenuItems[] = [
        'label' => 'Espelhos',
        'href' => '/cost-mirrors',
        'icon' => 'cost_mirror',
        'active' => str_starts_with($path, '/cost-mirrors'),
    ];
}
if ($canViewOfficeTemplates) {
    $mainMenuItems[] = [
        'label' => 'Oficios',
        'href' => '/office-templates',
        'icon' => 'office_template',
        'active' => str_starts_with($path, '/office-templates') || str_starts_with($path, '/office-documents'),
    ];
}
if ($canViewProcessMeta) {
    $mainMenuItems[] = [
        'label' => 'Processo formal',
        'href' => '/process-meta',
        'icon' => 'process_meta',
        'active' => str_starts_with($path, '/process-meta'),
    ];
}

$quickMenuItems = [];
if ($canManagePeople) {
    $quickMenuItems[] = [
        'label' => 'Nova pessoa',
        'href' => '/people/create',
        'icon' => 'plus',
        'active' => $path === '/people/create',
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
        <?php foreach ($mainMenuItems as $item): ?>
          <a class="menu-item <?= ($item['active'] ?? false) ? 'is-active' : '' ?>" href="<?= e(url((string) ($item['href'] ?? '/dashboard'))) ?>">
            <span class="menu-item-content">
              <span class="menu-icon" aria-hidden="true"><?= $renderMenuIcon((string) ($item['icon'] ?? 'dashboard')) ?></span>
              <span><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
            </span>
          </a>
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
