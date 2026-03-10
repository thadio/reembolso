<?php

declare(strict_types=1);

$person = $person ?? [];
$assignment = is_array($assignment ?? null) ? $assignment : [];
$movementDirectionOptions = is_array($movementDirectionOptions ?? null) ? $movementDirectionOptions : [];
$financialNatureOptions = is_array($financialNatureOptions ?? null) ? $financialNatureOptions : [];
$mteOrganId = max(0, (int) ($mteOrganId ?? 0));

$selectedMovementDirection = (string) old(
    'movement_direction',
    (string) ($assignment['movement_direction'] ?? $person['movement_direction'] ?? '')
);
$selectedFinancialNature = (string) old(
    'financial_nature',
    (string) ($assignment['financial_nature'] ?? $person['financial_nature'] ?? '')
);

if ($selectedMovementDirection === 'saida_mte') {
    $selectedFinancialNature = 'receita_reembolso';
} elseif ($selectedMovementDirection === 'entrada_mte') {
    $selectedFinancialNature = 'despesa_reembolso';
}

$financialNatureLabels = [];
foreach ($financialNatureOptions as $option) {
    $value = trim((string) ($option['value'] ?? ''));
    $label = trim((string) ($option['label'] ?? $value));
    if ($value === '') {
        continue;
    }

    $financialNatureLabels[$value] = $label;
}

$isEntryDirection = $selectedMovementDirection === 'entrada_mte';
$isExitDirection = $selectedMovementDirection === 'saida_mte';
$organFieldLabel = $isExitDirection
    ? 'Órgão de destino *'
    : ($isEntryDirection ? 'Órgão de origem *' : 'Órgão de origem/destino *');
$organHelpText = $isExitDirection
    ? 'Obrigatório para informar o órgão de destino da pessoa.'
    : ($isEntryDirection
        ? 'Obrigatório. Para entrada no MTE, selecione um órgão de origem diferente do MTE.'
        : 'Selecione primeiro a direção do movimento para definir se o órgão é de origem ou destino.');

$normalizeLookup = static function (string $value): string {
    $normalized = mb_strtolower(trim($value));
    if ($normalized === '') {
        return '';
    }

    return strtr($normalized, [
        'ã' => 'a',
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'õ' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);
};

$movementReasonType = static function (string $modalityName) use ($normalizeLookup): string {
    $normalized = $normalizeLookup($modalityName);
    if ($normalized === '') {
        return '';
    }

    if (str_contains($normalized, 'cess')) {
        return 'cessao';
    }

    if (str_contains($normalized, 'requis')) {
        return 'requisicao';
    }

    if (str_contains($normalized, 'forca') || str_contains($normalized, 'cft')) {
        return 'cft';
    }

    return '';
};
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? '')) ?>">
    <?php endif; ?>

    <?php $selectedOrgan = (int) old('organ_id', (string) ($person['organ_id'] ?? '0')); ?>
    <?php
      $selectedOriginDestination = (int) old(
          'origin_mte_destination_id',
          (string) ($assignment['origin_mte_destination_id'] ?? $person['origin_mte_destination_id'] ?? '0')
      );
    ?>
    <?php
      $selectedDestination = (int) old(
          'destination_mte_destination_id',
          (string) ($assignment['destination_mte_destination_id'] ?? $person['destination_mte_destination_id'] ?? '0')
      );
    ?>

    <div class="field field-wide">
      <label>Direção do movimento *</label>
      <div class="chips-row" id="movement_direction_group">
        <?php $directionIndex = 0; ?>
        <?php foreach ($movementDirectionOptions as $option): ?>
          <?php
            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? $value));
            if ($value === '') {
                continue;
            }
          ?>
          <label>
            <input
              type="radio"
              name="movement_direction"
              value="<?= e($value) ?>"
              <?= $selectedMovementDirection === $value ? 'checked' : '' ?>
              <?= $directionIndex === 0 ? 'required' : '' ?>
            >
            <?= e($label) ?>
          </label>
          <?php $directionIndex++; ?>
        <?php endforeach; ?>
      </div>
      <p class="muted">Selecione a direção para liberar os campos aplicáveis no cadastro.</p>
    </div>

    <div class="field" id="mte_origin_fixed_field" <?= $isExitDirection ? '' : 'style="display: none;"' ?>>
      <label for="mte_origin_name">Órgão de origem</label>
      <input id="mte_origin_name" type="text" value="MTE - Ministério do Trabalho e Emprego" readonly>
    </div>

    <div class="field" id="organ_id_field">
      <label id="organ_id_label" for="organ_id"><?= e($organFieldLabel) ?></label>
      <select id="organ_id" name="organ_id">
        <option value="0">Selecione</option>
        <?php foreach (($organs ?? []) as $organ): ?>
          <?php
            $organId = (int) ($organ['id'] ?? 0);
            $isMteOption = $mteOrganId > 0 && $organId === $mteOrganId;
            $organAcronym = trim((string) ($organ['acronym'] ?? ''));
            $organName = trim((string) ($organ['name'] ?? ''));
            $organLabel = $organName;
            if ($organAcronym !== '') {
                $organLabel .= ' (' . $organAcronym . ')';
            }
          ?>
          <option
            value="<?= e((string) $organId) ?>"
            data-is-mte="<?= $isMteOption ? '1' : '0' ?>"
            <?= $selectedOrgan === $organId ? 'selected' : '' ?>
          >
            <?= e($organLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted" id="organ_id_help"><?= e($organHelpText) ?></p>
    </div>

    <div class="field">
      <label for="financial_nature_label">Natureza financeira *</label>
      <input type="hidden" id="financial_nature" name="financial_nature" value="<?= e($selectedFinancialNature) ?>">
      <input
        id="financial_nature_label"
        type="text"
        value="<?= e((string) ($financialNatureLabels[$selectedFinancialNature] ?? '')) ?>"
        readonly
        disabled
      >
      <p class="muted">A natureza financeira é definida automaticamente pela direção do movimento.</p>
    </div>

    <div class="field" id="origin_mte_destination_wrapper" <?= $isExitDirection ? '' : 'style="display: none;"' ?>>
      <label for="origin_mte_destination_id">Lotação de origem no MTE</label>
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
            $destinationAcronym = trim((string) ($destination['acronym'] ?? ''));
            $destinationUf = trim((string) ($destination['uf'] ?? ''));

            $prefix = $destinationAcronym !== '' ? $destinationAcronym : $destinationCode;
            if ($destinationUf !== '') {
                $prefix = $prefix === '' ? $destinationUf : ($prefix . '/' . $destinationUf);
            }
            if ($destinationCode !== '' && $destinationAcronym !== '' && $destinationCode !== $destinationAcronym) {
                $prefix .= ' [' . $destinationCode . ']';
            }

            $destinationLabel = $prefix === '' ? $destinationName : ($prefix . ' - ' . $destinationName);
          ?>
          <option value="<?= e((string) $destinationId) ?>" <?= $selectedOriginDestination === $destinationId ? 'selected' : '' ?>>
            <?= e($destinationLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Obrigatória quando a direção for "Pessoa saindo do MTE".</p>
    </div>

    <div class="field" id="destination_mte_destination_wrapper" <?= $isEntryDirection ? '' : 'style="display: none;"' ?>>
      <label for="destination_mte_destination_search">Lotação de destino no MTE</label>
      <div class="destination-search-shell">
        <input
          id="destination_mte_destination_search"
          type="search"
          placeholder="Digite para buscar lotação de destino"
          autocomplete="off"
          spellcheck="false"
        >
        <div
          id="destination_mte_destination_suggestions"
          class="destination-suggestions"
          role="listbox"
          aria-label="Sugestões de lotação de destino no MTE"
          aria-live="polite"
        ></div>
      </div>
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
            $destinationAcronym = trim((string) ($destination['acronym'] ?? ''));
            $destinationUf = trim((string) ($destination['uf'] ?? ''));

            $prefix = $destinationAcronym !== '' ? $destinationAcronym : $destinationCode;
            if ($destinationUf !== '') {
                $prefix = $prefix === '' ? $destinationUf : ($prefix . '/' . $destinationUf);
            }
            if ($destinationCode !== '' && $destinationAcronym !== '' && $destinationCode !== $destinationAcronym) {
                $prefix .= ' [' . $destinationCode . ']';
            }

            $destinationLabel = $prefix === '' ? $destinationName : ($prefix . ' - ' . $destinationName);
          ?>
          <option value="<?= e((string) $destinationId) ?>" <?= $selectedDestination === $destinationId ? 'selected' : '' ?>>
            <?= e($destinationLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Digite ao menos 2 caracteres para busca incremental com sugestões. Obrigatória quando a direção for "Pessoa entrando no MTE".</p>
    </div>

    <?php
      $targetStartDate = (string) old(
          'target_start_date',
          (string) ($assignment['target_start_date'] ?? $person['target_start_date'] ?? '')
      );
      $requestedEndDate = (string) old(
          'requested_end_date',
          (string) ($assignment['requested_end_date'] ?? $person['requested_end_date'] ?? '')
      );
    ?>

    <div class="field">
      <label for="target_start_date">Data prevista de início efetivo</label>
      <input id="target_start_date" name="target_start_date" type="date" value="<?= e($targetStartDate) ?>">
      <p class="muted">Usada nas projeções financeiras até o registro da data efetiva.</p>
    </div>

    <div class="field">
      <label for="requested_end_date">Data prevista de término efetivo</label>
      <input id="requested_end_date" name="requested_end_date" type="date" value="<?= e($requestedEndDate) ?>">
      <p class="muted">Para movimentações com término previsto, substituída automaticamente quando houver data efetiva.</p>
    </div>

    <div class="field field-wide">
      <label for="name">Nome completo *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($person['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="cpf">CPF *</label>
      <input id="cpf" name="cpf" type="text" value="<?= e(old('cpf', (string) ($person['cpf'] ?? ''))) ?>" required placeholder="000.000.000-00">
    </div>

    <div class="field">
      <label for="matricula_siape">Matrícula SIAPE</label>
      <input
        id="matricula_siape"
        name="matricula_siape"
        type="text"
        value="<?= e(old('matricula_siape', (string) ($person['matricula_siape'] ?? ''))) ?>"
        inputmode="numeric"
        maxlength="20"
        pattern="[0-9]+"
        placeholder="Somente números"
      >
      <p class="muted">Quando informada, deve ser numérica e única por pessoa.</p>
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
      <label for="desired_modality_id">Motivo do movimento *</label>
      <?php $selectedModality = (int) old('desired_modality_id', (string) ($person['desired_modality_id'] ?? '0')); ?>
      <select id="desired_modality_id" name="desired_modality_id" required>
        <option value="0">Selecione</option>
        <?php foreach (($modalities ?? []) as $modality): ?>
          <?php
            $modalityName = (string) ($modality['name'] ?? '');
            $reasonType = $movementReasonType($modalityName);
            if ($reasonType === '') {
                continue;
            }

            $allowedDirections = $reasonType === 'requisicao'
                ? 'saida_mte'
                : 'entrada_mte,saida_mte';
          ?>
          <option
            value="<?= e((string) $modality['id']) ?>"
            data-reason-type="<?= e($reasonType) ?>"
            data-allowed-directions="<?= e($allowedDirections) ?>"
            <?= $selectedModality === (int) $modality['id'] ? 'selected' : '' ?>
          >
            <?= e($modalityName) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Entrada: Cessão ou Composição de Força de Trabalho. Saída: Cessão, Requisição ou Composição de Força de Trabalho.</p>
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
    var directionRadios = document.querySelectorAll('input[name="movement_direction"]');
    var organField = document.getElementById('organ_id');
    var organLabel = document.getElementById('organ_id_label');
    var organHelp = document.getElementById('organ_id_help');
    var mteOriginField = document.getElementById('mte_origin_fixed_field');
    var financialNatureInput = document.getElementById('financial_nature');
    var financialNatureLabel = document.getElementById('financial_nature_label');
    var reasonField = document.getElementById('desired_modality_id');
    var originField = document.getElementById('origin_mte_destination_id');
    var destinationField = document.getElementById('destination_mte_destination_id');
    var destinationSearchField = document.getElementById('destination_mte_destination_search');
    var destinationSuggestions = document.getElementById('destination_mte_destination_suggestions');
    var originWrapper = document.getElementById('origin_mte_destination_wrapper');
    var destinationWrapper = document.getElementById('destination_mte_destination_wrapper');
    if (
      !directionRadios.length ||
      !organField ||
      !financialNatureInput ||
      !financialNatureLabel ||
      !reasonField ||
      !originField ||
      !destinationField ||
      !destinationSearchField ||
      !destinationSuggestions ||
      !originWrapper ||
      !destinationWrapper
    ) {
      return;
    }

    var financialNatureLabels = {
      despesa_reembolso: 'Despesa de reembolso (a pagar)',
      receita_reembolso: 'Receita de reembolso (a receber)'
    };
    var destinationSearchDebounce = null;
    var destinationSuggestionLimit = 8;
    var destinationCatalog = [];

    function normalizeLookup(value) {
      if (typeof value !== 'string') {
        return '';
      }

      var normalized = value.toLowerCase().trim();
      if (typeof normalized.normalize === 'function') {
        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }

      return normalized
        .replace(/[ãáàâ]/g, 'a')
        .replace(/[éê]/g, 'e')
        .replace(/[í]/g, 'i')
        .replace(/[õóô]/g, 'o')
        .replace(/[ú]/g, 'u')
        .replace(/[ç]/g, 'c');
    }

    function matchesLookup(normalizedLabel, normalizedQuery) {
      if (normalizedQuery === '') {
        return true;
      }

      var terms = normalizedQuery.split(/\s+/);
      for (var i = 0; i < terms.length; i += 1) {
        var term = terms[i].trim();
        if (term !== '' && normalizedLabel.indexOf(term) === -1) {
          return false;
        }
      }

      return true;
    }

    function rebuildDestinationCatalog() {
      destinationCatalog = [];
      Array.prototype.forEach.call(destinationField.options, function (option) {
        var value = option.value || '';
        if (value === '0') {
          return;
        }

        var label = (option.textContent || '').trim();
        destinationCatalog.push({
          value: value,
          label: label,
          normalized: normalizeLookup(label)
        });
      });
    }

    function findDestinationLabelByValue(value) {
      for (var i = 0; i < destinationCatalog.length; i += 1) {
        if (destinationCatalog[i].value === value) {
          return destinationCatalog[i].label;
        }
      }

      return '';
    }

    function hideDestinationSuggestions() {
      destinationSuggestions.innerHTML = '';
      destinationSuggestions.classList.remove('is-open');
    }

    function filterDestinationOptions(searchValue) {
      var normalizedQuery = normalizeLookup(searchValue);
      var selectedValue = destinationField.value || '';

      Array.prototype.forEach.call(destinationField.options, function (option) {
        var value = option.value || '';
        if (value === '0') {
          option.hidden = false;
          option.disabled = false;
          return;
        }

        var label = (option.textContent || '').trim();
        var isMatch = matchesLookup(normalizeLookup(label), normalizedQuery);
        var isSelected = value === selectedValue;
        var enabled = normalizedQuery === '' || isMatch || isSelected;

        option.hidden = !enabled;
        option.disabled = !enabled;
      });
    }

    function renderDestinationSuggestions(searchValue) {
      hideDestinationSuggestions();

      if (destinationField.disabled) {
        return;
      }

      var normalizedQuery = normalizeLookup(searchValue);
      if (normalizedQuery.length < 2) {
        return;
      }

      var matches = [];
      for (var i = 0; i < destinationCatalog.length; i += 1) {
        var candidate = destinationCatalog[i];
        if (!matchesLookup(candidate.normalized, normalizedQuery)) {
          continue;
        }

        matches.push(candidate);
        if (matches.length >= destinationSuggestionLimit) {
          break;
        }
      }

      if (matches.length === 0) {
        var emptyMessage = document.createElement('div');
        emptyMessage.className = 'destination-suggestion-empty';
        emptyMessage.textContent = 'Nenhuma lotação encontrada.';
        destinationSuggestions.appendChild(emptyMessage);
        destinationSuggestions.classList.add('is-open');
        return;
      }

      for (var matchIndex = 0; matchIndex < matches.length; matchIndex += 1) {
        var match = matches[matchIndex];
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'destination-suggestion-btn';
        button.setAttribute('data-value', match.value);
        button.setAttribute('data-label', match.label);
        button.textContent = match.label;
        destinationSuggestions.appendChild(button);
      }

      destinationSuggestions.classList.add('is-open');
    }

    function applyDestinationSearch(searchValue) {
      filterDestinationOptions(searchValue);
      renderDestinationSuggestions(searchValue);
    }

    function scheduleDestinationSearch() {
      if (destinationSearchDebounce !== null) {
        window.clearTimeout(destinationSearchDebounce);
      }

      destinationSearchDebounce = window.setTimeout(function () {
        applyDestinationSearch(destinationSearchField.value || '');
      }, 250);
    }

    function syncDestinationSearchFromSelect() {
      var value = destinationField.value || '';
      if (value === '' || value === '0') {
        if (document.activeElement !== destinationSearchField) {
          destinationSearchField.value = '';
        }
        return;
      }

      destinationSearchField.value = findDestinationLabelByValue(value);
    }

    function resetDestinationSearch() {
      destinationSearchField.value = '';
      filterDestinationOptions('');
      hideDestinationSuggestions();
    }

    function selectedDirection() {
      var selected = '';
      Array.prototype.forEach.call(directionRadios, function (radio) {
        if (radio.checked) {
          selected = radio.value || '';
        }
      });

      return selected;
    }

    function syncReasonOptions(directionValue) {
      var hasSelectedAllowed = false;
      var selectedValue = reasonField.value;

      Array.prototype.forEach.call(reasonField.options, function (option) {
        var value = option.value || '';
        if (value === '0') {
          option.hidden = false;
          option.disabled = false;
          return;
        }

        var allowed = (option.getAttribute('data-allowed-directions') || '').split(',').map(function (item) {
          return item.trim();
        });
        var enabled = directionValue !== '' && allowed.indexOf(directionValue) >= 0;
        option.hidden = !enabled;
        option.disabled = !enabled;

        if (enabled && value === selectedValue) {
          hasSelectedAllowed = true;
        }
      });

      if (!hasSelectedAllowed) {
        reasonField.value = '0';
      }
    }

    function syncOrganOptions(directionValue) {
      var isEntrada = directionValue === 'entrada_mte';
      var hasSelectedAllowed = false;

      Array.prototype.forEach.call(organField.options, function (option) {
        var value = option.value || '';
        if (value === '0') {
          option.hidden = false;
          option.disabled = false;
          return;
        }

        var isMte = option.getAttribute('data-is-mte') === '1';
        var enabled = !(isEntrada && isMte);
        option.hidden = !enabled;
        option.disabled = !enabled;

        if (enabled && value === organField.value) {
          hasSelectedAllowed = true;
        }
      });

      if (!hasSelectedAllowed) {
        organField.value = '0';
      }
    }

    function syncFinancialNature(directionValue) {
      var financialNature = '';
      if (directionValue === 'saida_mte') {
        financialNature = 'receita_reembolso';
      } else if (directionValue === 'entrada_mte') {
        financialNature = 'despesa_reembolso';
      }

      financialNatureInput.value = financialNature;
      financialNatureLabel.value = financialNatureLabels[financialNature] || '';
    }

    function syncByDirection() {
      var directionValue = selectedDirection();
      var isSaida = directionValue === 'saida_mte';
      var isEntrada = directionValue === 'entrada_mte';

      syncFinancialNature(directionValue);
      syncReasonOptions(directionValue);
      syncOrganOptions(directionValue);

      if (mteOriginField) {
        mteOriginField.style.display = isSaida ? '' : 'none';
      }

      if (organLabel) {
        organLabel.textContent = isSaida
          ? 'Órgão de destino *'
          : (isEntrada ? 'Órgão de origem *' : 'Órgão de origem/destino *');
      }

      if (organHelp) {
        organHelp.textContent = isSaida
          ? 'Obrigatório para informar o órgão de destino da pessoa.'
          : (isEntrada
            ? 'Obrigatório. Para entrada no MTE, selecione um órgão de origem diferente do MTE.'
            : 'Selecione primeiro a direção do movimento para definir se o órgão é de origem ou destino.');
      }

      organField.required = isSaida || isEntrada;

      originWrapper.style.display = isSaida ? '' : 'none';
      destinationWrapper.style.display = isEntrada ? '' : 'none';
      originField.disabled = !isSaida;
      destinationField.disabled = !isEntrada;
      destinationSearchField.disabled = !isEntrada;
      originField.required = isSaida;
      destinationField.required = isEntrada;

      if (!isSaida) {
        originField.value = '0';
      }

      if (!isEntrada) {
        destinationField.value = '0';
        resetDestinationSearch();
      } else {
        syncDestinationSearchFromSelect();
        filterDestinationOptions(destinationSearchField.value || '');
      }
    }

    destinationSearchField.addEventListener('input', function () {
      var currentQuery = destinationSearchField.value || '';
      var normalizedQuery = normalizeLookup(currentQuery);
      var selectedLabel = findDestinationLabelByValue(destinationField.value || '');

      if (normalizedQuery !== '' && normalizedQuery !== normalizeLookup(selectedLabel)) {
        destinationField.value = '0';
      }

      scheduleDestinationSearch();
    });

    destinationSearchField.addEventListener('focus', function () {
      var query = destinationSearchField.value || '';
      if (normalizeLookup(query).length >= 2) {
        applyDestinationSearch(query);
      }
    });

    destinationSearchField.addEventListener('blur', function () {
      window.setTimeout(function () {
        hideDestinationSuggestions();
      }, 140);
    });

    destinationSuggestions.addEventListener('mousedown', function (event) {
      var node = event.target;
      var button = null;
      while (node && node !== destinationSuggestions) {
        if (
          node.tagName === 'BUTTON'
          && node.getAttribute('data-value')
        ) {
          button = node;
          break;
        }

        node = node.parentNode;
      }

      if (!button) {
        return;
      }

      event.preventDefault();
      destinationField.value = button.getAttribute('data-value') || '0';
      destinationSearchField.value = button.getAttribute('data-label') || '';
      filterDestinationOptions('');
      hideDestinationSuggestions();
      if (typeof Event === 'function') {
        destinationField.dispatchEvent(new Event('change'));
      } else {
        var changeEvent = document.createEvent('Event');
        changeEvent.initEvent('change', true, true);
        destinationField.dispatchEvent(changeEvent);
      }
    });

    destinationField.addEventListener('change', function () {
      syncDestinationSearchFromSelect();
      filterDestinationOptions('');
    });

    Array.prototype.forEach.call(directionRadios, function (radio) {
      radio.addEventListener('change', syncByDirection);
    });
    rebuildDestinationCatalog();
    syncDestinationSearchFromSelect();
    filterDestinationOptions('');
    syncByDirection();
  })();
</script>
<script>
  (function () {
    var siapeField = document.getElementById('matricula_siape');
    if (!siapeField) {
      return;
    }

    siapeField.addEventListener('input', function () {
      var digits = (siapeField.value || '').replace(/\D+/g, '');
      if (digits !== siapeField.value) {
        siapeField.value = digits;
      }
    });
  })();
</script>
