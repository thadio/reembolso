<?php

declare(strict_types=1);

$destination = $destination ?? [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($destination['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="name">Nome da unidade *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($destination['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="code">Código UORG</label>
      <input id="code" name="code" type="text" value="<?= e(old('code', (string) ($destination['code'] ?? ''))) ?>" maxlength="60">
    </div>

    <div class="field">
      <label for="acronym">Sigla</label>
      <input id="acronym" name="acronym" type="text" value="<?= e(old('acronym', (string) ($destination['acronym'] ?? ''))) ?>" maxlength="60">
    </div>

    <div class="field">
      <label for="uf">UF</label>
      <input id="uf" name="uf" type="text" value="<?= e(old('uf', (string) ($destination['uf'] ?? ''))) ?>" maxlength="2">
    </div>

    <div class="field">
      <label for="upag_code">Código UPAG</label>
      <input id="upag_code" name="upag_code" type="text" value="<?= e(old('upag_code', (string) ($destination['upag_code'] ?? ''))) ?>" maxlength="20">
    </div>

    <div class="field">
      <label for="parent_uorg_code">UORG vinculação</label>
      <input id="parent_uorg_code" name="parent_uorg_code" type="text" value="<?= e(old('parent_uorg_code', (string) ($destination['parent_uorg_code'] ?? ''))) ?>" maxlength="20">
    </div>

    <div class="field field-wide">
      <label for="notes">Observações</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($destination['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/mte-destinations')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
