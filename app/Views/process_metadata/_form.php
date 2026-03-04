<?php

declare(strict_types=1);

$meta = is_array($meta ?? null) ? $meta : [];
$people = is_array($people ?? null) ? $people : [];
$channelOptions = is_array($channelOptions ?? null) ? $channelOptions : [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($meta['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="person_id">Pessoa *</label>
      <?php $selectedPersonId = (int) old('person_id', (string) ($meta['person_id'] ?? '0')); ?>
      <select id="person_id" name="person_id" required>
        <option value="">Selecione uma pessoa</option>
        <?php foreach ($people as $person): ?>
          <?php $personId = (int) ($person['id'] ?? 0); ?>
          <option value="<?= e((string) $personId) ?>" <?= $selectedPersonId === $personId ? 'selected' : '' ?>>
            <?= e((string) ($person['name'] ?? '')) ?> (<?= e((string) ($person['organ_name'] ?? '-')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="office_number">Numero de oficio</label>
      <input id="office_number" name="office_number" type="text" value="<?= e(old('office_number', (string) ($meta['office_number'] ?? ''))) ?>" maxlength="120">
    </div>

    <div class="field">
      <label for="office_sent_at">Data de envio do oficio</label>
      <input id="office_sent_at" name="office_sent_at" type="date" value="<?= e(old('office_sent_at', (string) ($meta['office_sent_at'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="office_channel">Canal</label>
      <?php $selectedChannel = old('office_channel', (string) ($meta['office_channel'] ?? 'sei')); ?>
      <select id="office_channel" name="office_channel">
        <option value="">Selecione</option>
        <?php foreach ($channelOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
          ?>
          <option value="<?= e($value) ?>" <?= $selectedChannel === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="office_protocol">Protocolo</label>
      <input id="office_protocol" name="office_protocol" type="text" value="<?= e(old('office_protocol', (string) ($meta['office_protocol'] ?? ''))) ?>" maxlength="120">
    </div>

    <div class="field">
      <label for="dou_edition">Edicao DOU</label>
      <input id="dou_edition" name="dou_edition" type="text" value="<?= e(old('dou_edition', (string) ($meta['dou_edition'] ?? ''))) ?>" maxlength="120">
    </div>

    <div class="field">
      <label for="dou_published_at">Data de publicacao DOU</label>
      <input id="dou_published_at" name="dou_published_at" type="date" value="<?= e(old('dou_published_at', (string) ($meta['dou_published_at'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="dou_link">Link da publicacao DOU</label>
      <input id="dou_link" name="dou_link" type="url" value="<?= e(old('dou_link', (string) ($meta['dou_link'] ?? ''))) ?>" maxlength="500" placeholder="https://...">
    </div>

    <div class="field field-wide">
      <label for="dou_attachment">Anexo DOU (PDF/PNG/JPG, ate 15MB)</label>
      <input id="dou_attachment" name="dou_attachment" type="file" accept="application/pdf,image/png,image/jpeg">
      <?php if (($isEdit ?? false) === true && !empty($meta['dou_attachment_original_name'])): ?>
        <p class="muted">Anexo atual: <?= e((string) $meta['dou_attachment_original_name']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="mte_entry_date">Data oficial de entrada no MTE</label>
      <input id="mte_entry_date" name="mte_entry_date" type="date" value="<?= e(old('mte_entry_date', (string) ($meta['mte_entry_date'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="notes">Observacoes</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($meta['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/process-meta')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
