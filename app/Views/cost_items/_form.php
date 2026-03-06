<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$reimbursableOptions = is_array($reimbursableOptions ?? null) ? $reimbursableOptions : [];
$periodicityOptions = is_array($periodicityOptions ?? null) ? $periodicityOptions : [];

$old = static fn (string $key, mixed $default = '') => old($key, $item[$key] ?? $default);
?>
<div class="card">
  <form method="post" action="<?= e($action ?? '#') ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ((int) ($item['id'] ?? 0))) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="cost_item_name">Nome do item de custo *</label>
      <input
        id="cost_item_name"
        name="name"
        type="text"
        minlength="3"
        maxlength="190"
        required
        value="<?= e((string) $old('name', '')) ?>"
        placeholder="Ex.: Auxilio transporte"
      >
    </div>

    <div class="field">
      <label for="cost_item_linkage_code">Vinculo *</label>
      <select id="cost_item_linkage_code" name="linkage_code" required>
        <?php $selectedLinkage = (string) $old('linkage_code', '309'); ?>
        <?php foreach ($linkageOptions as $option): ?>
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedLinkage === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_reimbursable">Parcela *</label>
      <select id="cost_item_reimbursable" name="is_reimbursable" required>
        <?php $selectedReimbursable = (string) $old('is_reimbursable', '1'); ?>
        <?php foreach ($reimbursableOptions as $option): ?>
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedReimbursable === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_periodicity">Periodicidade *</label>
      <select id="cost_item_periodicity" name="payment_periodicity" required>
        <?php $selectedPeriodicity = (string) $old('payment_periodicity', 'mensal'); ?>
        <?php foreach ($periodicityOptions as $option): ?>
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedPeriodicity === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-actions field-wide">
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
      <a class="btn btn-outline" href="<?= e(url('/cost-items')) ?>">Cancelar</a>
    </div>
  </form>
</div>
