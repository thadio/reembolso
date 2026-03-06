<?php

declare(strict_types=1);

$person = $person ?? [];
$assignment = is_array($assignment ?? null) ? $assignment : [];
$movementDirectionOptions = is_array($movementDirectionOptions ?? null) ? $movementDirectionOptions : [];
$financialNatureOptions = is_array($financialNatureOptions ?? null) ? $financialNatureOptions : [];
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
      <label for="movement_direction">Direção do movimento *</label>
      <?php
        $selectedMovementDirection = (string) old(
            'movement_direction',
            (string) ($assignment['movement_direction'] ?? $person['movement_direction'] ?? 'entrada_mte')
        );
      ?>
      <select id="movement_direction" name="movement_direction" required>
        <?php foreach ($movementDirectionOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
            if ($value === '') {
                continue;
            }
          ?>
          <option value="<?= e($value) ?>" <?= $selectedMovementDirection === $value ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="financial_nature">Natureza financeira *</label>
      <?php
        $selectedFinancialNature = (string) old(
            'financial_nature',
            (string) ($assignment['financial_nature'] ?? $person['financial_nature'] ?? 'despesa_reembolso')
        );
      ?>
      <select id="financial_nature" name="financial_nature" required>
        <?php foreach ($financialNatureOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
            if ($value === '') {
                continue;
            }
          ?>
          <option value="<?= e($value) ?>" <?= $selectedFinancialNature === $value ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">A natureza é ajustada automaticamente conforme a direção.</p>
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
      <label for="assignment_flow_id">Fluxo BPMN *</label>
      <?php $selectedFlow = (int) old('assignment_flow_id', (string) ($person['assignment_flow_id'] ?? '0')); ?>
      <select id="assignment_flow_id" name="assignment_flow_id" required>
        <option value="0">Selecione</option>
        <?php foreach (($assignmentFlows ?? []) as $flow): ?>
          <?php $flowId = (int) ($flow['id'] ?? 0); ?>
          <option value="<?= e((string) $flowId) ?>" <?= $selectedFlow === $flowId ? 'selected' : '' ?>>
            <?= e((string) ($flow['name'] ?? ('Fluxo #' . $flowId))) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="sei_process_number">Nº processo SEI</label>
      <input id="sei_process_number" name="sei_process_number" type="text" value="<?= e(old('sei_process_number', (string) ($person['sei_process_number'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="origin_mte_destination_id">Lotação de origem no MTE</label>
      <?php
        $selectedOriginDestination = (int) old(
            'origin_mte_destination_id',
            (string) ($assignment['origin_mte_destination_id'] ?? $person['origin_mte_destination_id'] ?? '0')
        );
      ?>
      <select id="origin_mte_destination_id" name="origin_mte_destination_id">
        <option value="0">Selecione</option>
        <?php foreach (($mteDestinations ?? []) as $destination): ?>
          <?php
            $destinationId = (int) ($destination['id'] ?? 0);
            $destinationName = trim((string) ($destination['name'] ?? ''));
            if ($destinationName === '') {
                continue;
            }

            $destinationCode = trim((string) ($destination['code'] ?? ''));
            $destinationLabel = $destinationCode === '' ? $destinationName : ($destinationCode . ' - ' . $destinationName);
          ?>
          <option value="<?= e((string) $destinationId) ?>" <?= $selectedOriginDestination === $destinationId ? 'selected' : '' ?>>
            <?= e($destinationLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Obrigatória quando a direção for "Cessão para fora do MTE".</p>
    </div>

    <div class="field">
      <label for="destination_mte_destination_id">Lotação de destino no MTE</label>
      <?php
        $selectedDestination = (int) old(
            'destination_mte_destination_id',
            (string) ($assignment['destination_mte_destination_id'] ?? $person['destination_mte_destination_id'] ?? '0')
        );
      ?>
      <select id="destination_mte_destination_id" name="destination_mte_destination_id">
        <option value="0">Selecione</option>
        <?php foreach (($mteDestinations ?? []) as $destination): ?>
          <?php
            $destinationId = (int) ($destination['id'] ?? 0);
            $destinationName = trim((string) ($destination['name'] ?? ''));
            if ($destinationName === '') {
                continue;
            }

            $destinationCode = trim((string) ($destination['code'] ?? ''));
            $destinationLabel = $destinationCode === '' ? $destinationName : ($destinationCode . ' - ' . $destinationName);
          ?>
          <option value="<?= e((string) $destinationId) ?>" <?= $selectedDestination === $destinationId ? 'selected' : '' ?>>
            <?= e($destinationLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Obrigatória quando a direção for "Recebimento no MTE".</p>
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
<script>
  (function () {
    var direction = document.getElementById('movement_direction');
    var nature = document.getElementById('financial_nature');
    var originField = document.getElementById('origin_mte_destination_id');
    var destinationField = document.getElementById('destination_mte_destination_id');
    if (!direction || !nature || !originField || !destinationField) {
      return;
    }

    function syncByDirection() {
      var isSaida = direction.value === 'saida_mte';
      nature.value = isSaida ? 'receita_reembolso' : 'despesa_reembolso';
      originField.disabled = !isSaida;
      destinationField.disabled = isSaida;
    }

    direction.addEventListener('change', syncByDirection);
    syncByDirection();
  })();
</script>
