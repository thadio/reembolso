<?php

declare(strict_types=1);

$oldInput = \App\Core\Session::getFlash('_old', []);
$user = $user ?? [];
$roles = $roles ?? [];

$selectedRoles = [];
if (isset($oldInput['role_ids']) && is_array($oldInput['role_ids'])) {
    foreach ($oldInput['role_ids'] as $roleId) {
        $id = (int) $roleId;
        if ($id > 0) {
            $selectedRoles[] = $id;
        }
    }
} elseif (isset($user['role_ids']) && is_array($user['role_ids'])) {
    foreach ($user['role_ids'] as $roleId) {
        $id = (int) $roleId;
        if ($id > 0) {
            $selectedRoles[] = $id;
        }
    }
}

$selectedRoles = array_values(array_unique($selectedRoles));
$isSelf = ($isSelf ?? false) === true;
$isEdit = ($isEdit ?? false) === true;

$currentActive = (int) ($user['is_active'] ?? 1) === 1 ? '1' : '0';
$activeValue = old('is_active', $currentActive);
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= e((string) ($user['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field">
      <label for="name">Nome *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($user['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="email">E-mail *</label>
      <input id="email" name="email" type="email" value="<?= e(old('email', (string) ($user['email'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="cpf">CPF</label>
      <input id="cpf" name="cpf" type="text" value="<?= e(old('cpf', (string) ($user['cpf'] ?? ''))) ?>" placeholder="000.000.000-00">
    </div>

    <div class="field">
      <label for="is_active">Status da conta</label>
      <?php if ($isSelf): ?>
        <input type="hidden" name="is_active" value="<?= e($currentActive) ?>">
        <input type="text" value="<?= e($currentActive === '1' ? 'Ativa (sua conta)' : 'Inativa (sua conta)') ?>" disabled>
      <?php else: ?>
        <select id="is_active" name="is_active">
          <option value="1" <?= $activeValue === '1' ? 'selected' : '' ?>>Ativa</option>
          <option value="0" <?= $activeValue === '0' ? 'selected' : '' ?>>Inativa</option>
        </select>
      <?php endif; ?>
    </div>

    <?php if (!$isEdit): ?>
      <div class="field">
        <label for="password">Senha inicial *</label>
        <input id="password" name="password" type="password" required>
      </div>

      <div class="field">
        <label for="password_confirmation">Confirmacao de senha *</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>
      </div>
    <?php endif; ?>

    <div class="field field-wide">
      <label>Papeis *</label>
      <div class="roles-grid">
        <?php foreach ($roles as $role): ?>
          <?php $roleId = (int) ($role['id'] ?? 0); ?>
          <label class="role-option">
            <input
              type="checkbox"
              name="role_ids[]"
              value="<?= e((string) $roleId) ?>"
              <?= in_array($roleId, $selectedRoles, true) ? 'checked' : '' ?>
            >
            <span>
              <strong><?= e((string) ($role['name'] ?? 'papel')) ?></strong>
              <span class="muted role-description"><?= e((string) ($role['description'] ?? '')) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/users')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
