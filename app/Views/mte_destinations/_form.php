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
      <label for="name">Nome da lotação *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($destination['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="code">Código/Sigla</label>
      <input id="code" name="code" type="text" value="<?= e(old('code', (string) ($destination['code'] ?? ''))) ?>" maxlength="60">
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
