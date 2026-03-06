<?php

declare(strict_types=1);

$invoice = is_array($invoice ?? null) ? $invoice : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$financialNatureOptions = is_array($financialNatureOptions ?? null) ? $financialNatureOptions : [];
$organs = is_array($organs ?? null) ? $organs : [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($invoice['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field">
      <label for="organ_id">Orgao *</label>
      <select id="organ_id" name="organ_id" required>
        <option value="">Selecione</option>
        <?php $selectedOrganId = (int) old('organ_id', (string) ($invoice['organ_id'] ?? '0')); ?>
        <?php foreach ($organs as $organ): ?>
          <?php $organId = (int) ($organ['id'] ?? 0); ?>
          <option value="<?= e((string) $organId) ?>" <?= $selectedOrganId === $organId ? 'selected' : '' ?>>
            <?= e((string) ($organ['name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="status">Status *</label>
      <select id="status" name="status" required>
        <?php $selectedStatus = old('status', (string) ($invoice['status'] ?? 'aberto')); ?>
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
      <label for="financial_nature">Natureza financeira *</label>
      <select id="financial_nature" name="financial_nature" required>
        <?php $selectedNature = old('financial_nature', (string) ($invoice['financial_nature'] ?? 'despesa_reembolso')); ?>
        <?php foreach ($financialNatureOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
            if ($value === '') {
                continue;
            }
          ?>
          <option value="<?= e($value) ?>" <?= $selectedNature === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="invoice_number">Numero do boleto *</label>
      <input id="invoice_number" name="invoice_number" type="text" value="<?= e(old('invoice_number', (string) ($invoice['invoice_number'] ?? ''))) ?>" required>
    </div>

    <div class="field field-wide">
      <label for="title">Titulo *</label>
      <input id="title" name="title" type="text" value="<?= e(old('title', (string) ($invoice['title'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="reference_month">Competencia *</label>
      <?php
        $referenceMonthRaw = old('reference_month', (string) ($invoice['reference_month'] ?? ''));
        $referenceMonth = preg_match('/^\d{4}-\d{2}$/', $referenceMonthRaw) === 1
            ? $referenceMonthRaw
            : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceMonthRaw) === 1 ? substr($referenceMonthRaw, 0, 7) : '');
      ?>
      <input id="reference_month" name="reference_month" type="month" value="<?= e($referenceMonth) ?>" required>
    </div>

    <div class="field">
      <label for="issue_date">Data de emissao</label>
      <input id="issue_date" name="issue_date" type="date" value="<?= e(old('issue_date', (string) ($invoice['issue_date'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="due_date">Data de vencimento *</label>
      <input id="due_date" name="due_date" type="date" value="<?= e(old('due_date', (string) ($invoice['due_date'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="total_amount">Valor total (R$) *</label>
      <input id="total_amount" name="total_amount" type="text" value="<?= e(old('total_amount', (string) ($invoice['total_amount'] ?? ''))) ?>" placeholder="0,00" required>
    </div>

    <div class="field field-wide">
      <label for="digitable_line">Linha digitavel</label>
      <input id="digitable_line" name="digitable_line" type="text" value="<?= e(old('digitable_line', (string) ($invoice['digitable_line'] ?? ''))) ?>" maxlength="255">
    </div>

    <div class="field">
      <label for="reference_code">Referencia</label>
      <input id="reference_code" name="reference_code" type="text" value="<?= e(old('reference_code', (string) ($invoice['reference_code'] ?? ''))) ?>" maxlength="120">
    </div>

    <div class="field field-wide">
      <label for="invoice_pdf">PDF do boleto (opcional)</label>
      <input id="invoice_pdf" name="invoice_pdf" type="file" accept="application/pdf">
      <?php if (($isEdit ?? false) === true && !empty($invoice['pdf_original_name'])): ?>
        <p class="muted">Arquivo atual: <?= e((string) $invoice['pdf_original_name']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field field-wide">
      <label for="notes">Observacoes</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($invoice['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/invoices')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
