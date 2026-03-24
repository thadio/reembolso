<?php

declare(strict_types=1);

$modules = is_array($modules ?? null) ? $modules : [];
$availableCount = count($modules);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Inicio de modulos</h2>
      <p class="muted">Selecione um modulo para continuar.</p>
    </div>
    <span class="home-modules-counter"><?= e((string) $availableCount) ?> modulo(s) disponivel(is)</span>
  </div>
</div>

<?php if ($modules === []): ?>
  <div class="card home-modules-empty">
    <h3>Nenhum modulo disponivel</h3>
    <p class="muted">Seu usuario foi autenticado, mas ainda nao possui acessos de modulo.</p>
    <p class="muted">Solicite ao administrador a revisao de papeis e permissoes.</p>
  </div>
<?php else: ?>
  <section class="home-modules-grid" aria-label="Modulos disponiveis">
    <?php foreach ($modules as $module): ?>
      <?php
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($module['slug'] ?? 'modulo'));
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'modulo';
        }

        $name = (string) ($module['name'] ?? 'Modulo');
        $description = (string) ($module['description'] ?? '');
        $entryPath = (string) ($module['entry_path'] ?? '/home');
        $ctaLabel = (string) ($module['cta_label'] ?? 'Abrir modulo');
        $entryType = (string) ($module['entry_type'] ?? 'internal_route');
        $accessMode = (string) ($module['access_mode'] ?? 'module_gate');
        $isLegacyFallback = $accessMode === 'legacy_fallback';
        $isExternalModule = $entryType === 'external_url';
      ?>
      <article class="card home-module-card home-module-<?= e($slug) ?>">
        <div class="home-module-head">
          <div class="home-module-meta">
            <p class="home-module-kicker">Modulo</p>
            <?php if ($isExternalModule): ?>
              <span class="badge badge-info">Integracao externa</span>
            <?php endif; ?>
            <?php if ($isLegacyFallback): ?>
              <span class="badge badge-warning">Acesso legado (transicao)</span>
            <?php endif; ?>
          </div>
          <h3><?= e($name) ?></h3>
        </div>
        <p class="muted"><?= e($description) ?></p>
        <div class="actions-inline">
          <a class="btn btn-primary" href="<?= e(url($entryPath)) ?>"><?= e($ctaLabel) ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
