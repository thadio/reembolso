<?php

declare(strict_types=1);

$isAuthenticated = isset($authUser) && is_array($authUser);
$path = (string) ($currentPath ?? '');
$authPermissions = is_array($authUser['permissions'] ?? null) ? $authUser['permissions'] : [];
$canViewCdos = in_array('cdo.view', $authPermissions, true);
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
        <a class="menu-item <?= $path === '/dashboard' || $path === '/' ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
        <?php if ($canViewCdos): ?>
          <a class="menu-item <?= str_starts_with($path, '/cdos') ? 'is-active' : '' ?>" href="<?= e(url('/cdos')) ?>">CDOs</a>
        <?php endif; ?>
        <a class="menu-item <?= str_starts_with($path, '/people') ? 'is-active' : '' ?>" href="<?= e(url('/people')) ?>">Pessoas</a>
        <a class="menu-item <?= str_starts_with($path, '/organs') ? 'is-active' : '' ?>" href="<?= e(url('/organs')) ?>">Órgãos</a>
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
