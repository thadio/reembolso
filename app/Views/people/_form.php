<?php

declare(strict_types=1);

$person = $person ?? [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="name">Nome completo *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($person['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="cpf">CPF *</label>
      <input id="cpf" name="cpf" type="text" value="<?= e(old('cpf', (string) ($person['cpf'] ?? ''))) ?>" required placeholder="000.000.000-00">
    </div>

    <div class="field">
      <label for="birth_date">Data de nascimento</label>
      <input id="birth_date" name="birth_date" type="date" value="<?= e(old('birth_date', (string) ($person['birth_date'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" value="<?= e(old('email', (string) ($person['email'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="phone">Telefone</label>
      <input id="phone" name="phone" type="text" value="<?= e(old('phone', (string) ($person['phone'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="organ_id">Órgão de origem *</label>
      <?php $selectedOrgan = (int) old('organ_id', (string) ($person['organ_id'] ?? '0')); ?>
      <select id="organ_id" name="organ_id" required>
        <option value="0">Selecione</option>
        <?php foreach (($organs ?? []) as $organ): ?>
          <option value="<?= e((string) $organ['id']) ?>" <?= $selectedOrgan === (int) $organ['id'] ? 'selected' : '' ?>><?= e((string) $organ['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="desired_modality_id">Modalidade pretendida</label>
      <?php $selectedModality = (int) old('desired_modality_id', (string) ($person['desired_modality_id'] ?? '0')); ?>
      <select id="desired_modality_id" name="desired_modality_id">
        <option value="0">Não informada</option>
        <?php foreach (($modalities ?? []) as $modality): ?>
          <option value="<?= e((string) $modality['id']) ?>" <?= $selectedModality === (int) $modality['id'] ? 'selected' : '' ?>><?= e((string) $modality['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="sei_process_number">Nº processo SEI</label>
      <input id="sei_process_number" name="sei_process_number" type="text" value="<?= e(old('sei_process_number', (string) ($person['sei_process_number'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="mte_destination">Lotação destino MTE</label>
      <?php
        $selectedDestination = trim((string) old('mte_destination', (string) ($person['mte_destination'] ?? '')));
        $knownDestinations = [];
      ?>
      <select id="mte_destination" name="mte_destination">
        <option value="">Não informada</option>
        <?php foreach (($mteDestinations ?? []) as $destination): ?>
          <?php
            $destinationName = trim((string) ($destination['name'] ?? ''));
            if ($destinationName === '') {
                continue;
            }

            $destinationCode = trim((string) ($destination['code'] ?? ''));
            $knownDestinations[$destinationName] = true;
            $destinationLabel = $destinationCode === '' ? $destinationName : ($destinationCode . ' - ' . $destinationName);
          ?>
          <option value="<?= e($destinationName) ?>" <?= $selectedDestination === $destinationName ? 'selected' : '' ?>>
            <?= e($destinationLabel) ?>
          </option>
        <?php endforeach; ?>
        <?php if ($selectedDestination !== '' && !isset($knownDestinations[$selectedDestination])): ?>
          <option value="<?= e($selectedDestination) ?>" selected><?= e($selectedDestination) ?> (valor legado)</option>
        <?php endif; ?>
      </select>
    </div>

    <div class="field">
      <label for="tags">Tags (separadas por vírgula)</label>
      <input id="tags" name="tags" type="text" value="<?= e(old('tags', (string) ($person['tags'] ?? ''))) ?>" placeholder="prioritario, ti, juridico">
    </div>

    <div class="field field-wide">
      <label for="notes">Observações</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($person['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
