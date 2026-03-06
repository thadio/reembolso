<?php

declare(strict_types=1);

$flow = $flow ?? [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($flow['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="name">Nome do fluxo *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($flow['name'] ?? ''))) ?>" required>
    </div>

    <div class="field field-wide">
      <label for="description">Descrição</label>
      <textarea id="description" name="description" rows="4"><?= e(old('description', (string) ($flow['description'] ?? ''))) ?></textarea>
    </div>

    <div class="field">
      <?php $isActive = old('is_active', (string) ((int) ($flow['is_active'] ?? 1))) === '1'; ?>
      <label>
        <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
        Fluxo ativo
      </label>
    </div>

    <div class="field">
      <?php $isDefault = old('is_default', (string) ((int) ($flow['is_default'] ?? 0))) === '1'; ?>
      <label>
        <input type="checkbox" name="is_default" value="1" <?= $isDefault ? 'checked' : '' ?>>
        Definir como fluxo padrão
      </label>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/pipeline-flows')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
