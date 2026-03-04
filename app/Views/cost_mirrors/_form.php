<?php

declare(strict_types=1);

$mirror = is_array($mirror ?? null) ? $mirror : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$organs = is_array($organs ?? null) ? $organs : [];
$people = is_array($people ?? null) ? $people : [];
$invoices = is_array($invoices ?? null) ? $invoices : [];

$formatMonthInput = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
        return $value;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return substr($value, 0, 7);
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('Y-m', $timestamp);
};
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($mirror['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field">
      <label for="person_id">Pessoa *</label>
      <?php $selectedPersonId = (int) old('person_id', (string) ($mirror['person_id'] ?? '0')); ?>
      <select id="person_id" name="person_id" required>
        <option value="">Selecione</option>
        <?php foreach ($people as $person): ?>
          <?php $personId = (int) ($person['id'] ?? 0); ?>
          <option value="<?= e((string) $personId) ?>" <?= $selectedPersonId === $personId ? 'selected' : '' ?>>
            <?= e((string) ($person['name'] ?? '')) ?> (<?= e((string) ($person['organ_name'] ?? '-')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="reference_month">Competencia *</label>
      <?php $referenceMonth = $formatMonthInput(old('reference_month', (string) ($mirror['reference_month'] ?? ''))); ?>
      <input id="reference_month" name="reference_month" type="month" value="<?= e($referenceMonth) ?>" required>
    </div>

    <div class="field">
      <label for="invoice_id">Boleto vinculado (opcional)</label>
      <?php $selectedInvoiceId = (int) old('invoice_id', (string) ($mirror['invoice_id'] ?? '0')); ?>
      <select id="invoice_id" name="invoice_id">
        <option value="">Nao vincular</option>
        <?php foreach ($invoices as $invoice): ?>
          <?php $invoiceId = (int) ($invoice['id'] ?? 0); ?>
          <?php
            $invoiceMonth = $formatMonthInput((string) ($invoice['reference_month'] ?? ''));
            $invoiceLabel = sprintf(
                '%s - %s (%s)',
                (string) ($invoice['invoice_number'] ?? '-'),
                (string) ($invoice['organ_name'] ?? '-'),
                $invoiceMonth === '' ? '-' : $invoiceMonth
            );
          ?>
          <option value="<?= e((string) $invoiceId) ?>" <?= $selectedInvoiceId === $invoiceId ? 'selected' : '' ?>>
            <?= e($invoiceLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Somente boletos da mesma pessoa/orgao/competencia sao aceitos na validacao.</p>
    </div>

    <div class="field">
      <label for="status">Status *</label>
      <?php $selectedStatus = old('status', (string) ($mirror['status'] ?? 'aberto')); ?>
      <select id="status" name="status" required>
        <?php foreach ($statusOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
          ?>
          <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field field-wide">
      <label for="title">Titulo *</label>
      <input id="title" name="title" type="text" value="<?= e(old('title', (string) ($mirror['title'] ?? ''))) ?>" minlength="3" maxlength="190" required>
    </div>

    <div class="field field-wide">
      <label for="notes">Observacoes</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($mirror['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/cost-mirrors')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
