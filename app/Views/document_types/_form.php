<?php

declare(strict_types=1);

$type = is_array($type ?? null) ? $type : [];
$old = static fn (string $key, mixed $default = '') => old($key, $type[$key] ?? $default);
$activeValue = (string) $old('is_active', '1');
$isActive = in_array(mb_strtolower($activeValue), ['1', 'true', 'on', 'yes', 'sim'], true);
?>
<div class="card">
  <form method="post" action="<?= e($action ?? '#') ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ((int) ($type['id'] ?? 0))) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="document_type_name">Nome *</label>
      <input
        id="document_type_name"
        name="name"
        type="text"
        minlength="3"
        maxlength="120"
        required
        value="<?= e((string) $old('name', '')) ?>"
        placeholder="Ex.: Oficio ao orgao"
      >
    </div>

    <div class="field field-wide">
      <label for="document_type_description">Descricao</label>
      <textarea id="document_type_description" name="description" rows="3" maxlength="255"><?= e((string) $old('description', '')) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="document_type_is_active">Status</label>
      <select id="document_type_is_active" name="is_active">
        <option value="1" <?= $isActive ? 'selected' : '' ?>>Ativo</option>
        <option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativo</option>
      </select>
    </div>

    <div class="form-actions field-wide">
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
      <a class="btn btn-outline" href="<?= e(url('/document-types')) ?>">Cancelar</a>
    </div>
  </form>
</div>
