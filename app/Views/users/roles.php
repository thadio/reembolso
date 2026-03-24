<?php

declare(strict_types=1);

$roles = $roles ?? [];
$permissions = $permissions ?? [];
$rolePermissionMap = $rolePermissionMap ?? [];
$modulePermissions = array_values(array_filter($permissions, static function ($permission): bool {
    $name = is_array($permission) ? (string) ($permission['name'] ?? '') : '';

    return str_starts_with($name, 'module.');
}));
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Vincule permissoes por papel para refletir os acessos dos usuarios.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/users')) ?>">Voltar aos usuarios</a>
    </div>
  </div>
</div>

<?php if ($modulePermissions !== []): ?>
  <div class="card">
    <h3>Gates de modulo (camada guardachuva)</h3>
    <p class="muted">Essas permissoes controlam a exibicao dos modulos na home inicial.</p>
    <div class="chips-row">
      <?php foreach ($modulePermissions as $modulePermission): ?>
        <span class="badge badge-info"><?= e((string) ($modulePermission['name'] ?? 'module.unknown.access')) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php foreach ($roles as $role): ?>
  <?php
    $roleId = (int) ($role['id'] ?? 0);
    $selectedPermissions = $rolePermissionMap[$roleId] ?? [];
  ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3><?= e((string) ($role['name'] ?? 'papel')) ?></h3>
        <p class="muted"><?= e((string) ($role['description'] ?? 'Sem descricao')) ?></p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/users/roles/update')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="role_id" value="<?= e((string) $roleId) ?>">

      <div class="field field-wide">
        <label>Permissoes vinculadas</label>
        <div class="permission-list">
          <?php foreach ($permissions as $permission): ?>
            <?php
              $permissionId = (int) ($permission['id'] ?? 0);
              $permissionName = (string) ($permission['name'] ?? 'permissao');
              $isModuleGate = str_starts_with($permissionName, 'module.');
            ?>
            <label class="role-option">
              <input
                type="checkbox"
                name="permission_ids[]"
                value="<?= e((string) $permissionId) ?>"
                <?= in_array($permissionId, $selectedPermissions, true) ? 'checked' : '' ?>
              >
              <span>
                <strong><?= e($permissionName) ?></strong>
                <?php if ($isModuleGate): ?>
                  <span class="badge badge-warning">Gate de modulo</span>
                <?php endif; ?>
                <span class="muted role-description"><?= e((string) ($permission['description'] ?? '')) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Salvar permissoes do papel</button>
      </div>
    </form>
  </div>
<?php endforeach; ?>
