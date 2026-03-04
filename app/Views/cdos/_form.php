<?php

declare(strict_types=1);

$cdo = $cdo ?? [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($cdo['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field">
      <label for="number">Numero do CDO *</label>
      <input id="number" name="number" type="text" value="<?= e(old('number', (string) ($cdo['number'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="status">Status *</label>
      <select id="status" name="status" required>
        <?php $selectedStatus = old('status', (string) ($cdo['status'] ?? 'aberto')); ?>
        <?php foreach ($statusOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
          ?>
          <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="ug_code">UG</label>
      <input id="ug_code" name="ug_code" type="text" value="<?= e(old('ug_code', (string) ($cdo['ug_code'] ?? ''))) ?>" maxlength="30">
    </div>

    <div class="field">
      <label for="action_code">Acao orcamentaria</label>
      <input id="action_code" name="action_code" type="text" value="<?= e(old('action_code', (string) ($cdo['action_code'] ?? ''))) ?>" maxlength="30">
    </div>

    <div class="field">
      <label for="period_start">Periodo inicial *</label>
      <input id="period_start" name="period_start" type="date" value="<?= e(old('period_start', (string) ($cdo['period_start'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="period_end">Periodo final *</label>
      <input id="period_end" name="period_end" type="date" value="<?= e(old('period_end', (string) ($cdo['period_end'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="total_amount">Valor total (R$) *</label>
      <input id="total_amount" name="total_amount" type="text" value="<?= e(old('total_amount', (string) ($cdo['total_amount'] ?? ''))) ?>" placeholder="0,00" required>
    </div>

    <div class="field field-wide">
      <label for="notes">Observacoes</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($cdo['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/cdos')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
