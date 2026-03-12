<?php

declare(strict_types=1);

$person = $person ?? [];
$assignment = is_array($assignment ?? null) ? $assignment : [];
$movementDirectionOptions = is_array($movementDirectionOptions ?? null) ? $movementDirectionOptions : [];
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

$isEntryDirection = $selectedMovementDirection === 'entrada_mte';
$isExitDirection = $selectedMovementDirection === 'saida_mte';
$organFieldLabel = $isExitDirection
    ? 'Órgão de destino *'
    : ($isEntryDirection ? 'Órgão de origem *' : 'Órgão de origem/destino *');
$organSearchPlaceholder = $isExitDirection
    ? 'Digite para buscar órgão de destino'
    : ($isEntryDirection ? 'Digite para buscar órgão de origem' : 'Selecione primeiro a direção');

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

$isEditMode = ($isEdit ?? false) === true;
$selectedOrgan = (int) old('organ_id', (string) ($person['organ_id'] ?? '0'));
$selectedOriginDestination = (int) old(
    'origin_mte_destination_id',
    (string) ($assignment['origin_mte_destination_id'] ?? $person['origin_mte_destination_id'] ?? '0')
);
$selectedDestination = (int) old(
    'destination_mte_destination_id',
    (string) ($assignment['destination_mte_destination_id'] ?? $person['destination_mte_destination_id'] ?? '0')
);
$selectedModality = (int) old('desired_modality_id', (string) ($person['desired_modality_id'] ?? '0'));
$selectedFlow = (int) old('assignment_flow_id', (string) ($person['assignment_flow_id'] ?? '0'));
$targetStartDate = (string) old(
    'target_start_date',
    (string) ($assignment['target_start_date'] ?? $person['target_start_date'] ?? '')
);
$requestedEndDate = (string) old(
    'requested_end_date',
    (string) ($assignment['requested_end_date'] ?? $person['requested_end_date'] ?? '')
);
$wizardInitialStep = max(1, min(3, (int) old('wizard_step', '1')));
$cancelUrl = (string) ($cancelUrl ?? url('/people'));

$additionalInfoHasValues = trim((string) old('sei_process_number', (string) ($person['sei_process_number'] ?? ''))) !== ''
    || trim((string) old('tags', (string) ($person['tags'] ?? ''))) !== ''
    || trim((string) old('notes', (string) ($person['notes'] ?? ''))) !== '';

$directionCardMeta = [
    'entrada_mte' => [
        'title' => 'Entrada no MTE',
        'description' => 'Recebe pessoa de outro órgão',
    ],
    'saida_mte' => [
        'title' => 'Saída do MTE',
        'description' => 'Pessoa segue para outro órgão',
    ],
];
?>
<div class="card people-wizard-card">
  <form
    method="post"
    action="<?= e($action) ?>"
    class="form-grid people-form-grid people-wizard-form"
    data-cpf-lookup-url="<?= e(url('/people/lookup-cpf')) ?>"
  >
    <?= csrf_field() ?>
    <?php if ($isEditMode): ?>
      <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? '')) ?>">
    <?php endif; ?>

    <input type="hidden" id="wizard_step" name="wizard_step" value="<?= e((string) $wizardInitialStep) ?>">
    <input type="hidden" id="financial_nature" name="financial_nature" value="<?= e($selectedFinancialNature) ?>">

    <div class="field field-wide">
      <ol class="people-wizard-progress" aria-label="Etapas do cadastro">
        <li class="people-wizard-progress-item" data-step-indicator="1">
          <span class="step-number">1</span>
          <span class="step-label">Movimentação</span>
        </li>
        <li class="people-wizard-progress-item" data-step-indicator="2">
          <span class="step-number">2</span>
          <span class="step-label">Dados da pessoa</span>
        </li>
        <li class="people-wizard-progress-item" data-step-indicator="3">
          <span class="step-number">3</span>
          <span class="step-label">Complementares</span>
        </li>
      </ol>
    </div>

    <section class="people-wizard-step field-wide" data-step="1">
      <div class="people-step-header">
        <h3>Etapa 1: Tipo de movimentação</h3>
        <p class="muted">Defina direção, motivo, fluxo e origem/destino.</p>
      </div>

      <div class="people-step-grid">
        <div class="field field-wide">
          <label>Direção do movimento *</label>
          <div class="movement-direction-grid" id="movement_direction_group">
            <?php $directionIndex = 0; ?>
            <?php foreach ($movementDirectionOptions as $option): ?>
              <?php
                $value = trim((string) ($option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
                if ($value === '') {
                    continue;
                }

                $cardMeta = $directionCardMeta[$value] ?? [
                    'title' => $label,
                    'description' => 'Selecione para continuar o cadastro.',
                ];
              ?>
              <label class="movement-direction-card">
                <input
                  type="radio"
                  name="movement_direction"
                  value="<?= e($value) ?>"
                  <?= $selectedMovementDirection === $value ? 'checked' : '' ?>
                  <?= $directionIndex === 0 ? 'required' : '' ?>
                >
                <span class="movement-direction-title"><?= e((string) ($cardMeta['title'] ?? $label)) ?></span>
                <span class="movement-direction-description"><?= e((string) ($cardMeta['description'] ?? '')) ?></span>
              </label>
              <?php $directionIndex++; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="desired_modality_id">Motivo *</label>
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
        </div>

        <div class="field">
          <label for="assignment_flow_id">Fluxo BPMN *</label>
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

        <div class="field" id="mte_origin_fixed_field" <?= $isExitDirection ? '' : 'style="display: none;"' ?>>
          <label for="mte_origin_name">Órgão de origem (fixo)</label>
          <input id="mte_origin_name" type="text" value="MTE - Ministério do Trabalho e Emprego" readonly>
        </div>

        <div class="field" id="organ_id_field">
          <label id="organ_id_label" for="organ_id_search"><?= e($organFieldLabel) ?></label>
          <div class="lookup-shell" id="organ_id_lookup_shell">
            <input
              id="organ_id_search"
              type="search"
              placeholder="<?= e($organSearchPlaceholder) ?>"
              autocomplete="off"
              spellcheck="false"
            >
            <div
              id="organ_id_suggestions"
              class="lookup-suggestions"
              role="listbox"
              aria-label="Sugestões de órgão"
              aria-live="polite"
            ></div>
            <select
              id="organ_id"
              name="organ_id"
              class="lookup-fallback-select"
              <?= ($isExitDirection || $isEntryDirection) ? 'required' : '' ?>
            >
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
          </div>
        </div>

        <div class="field" id="origin_mte_destination_wrapper" <?= $isExitDirection ? '' : 'style="display: none;"' ?>>
          <label for="origin_mte_destination_id">Lotação de origem no MTE *</label>
          <select
            id="origin_mte_destination_id"
            name="origin_mte_destination_id"
            <?= $isExitDirection ? 'required' : '' ?>
            <?= $isExitDirection ? '' : 'disabled' ?>
          >
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
        </div>

        <div class="field" id="destination_mte_destination_wrapper" <?= $isEntryDirection ? '' : 'style="display: none;"' ?>>
          <label for="destination_mte_destination_search">Lotação de destino no MTE *</label>
          <div class="lookup-shell" id="destination_lookup_shell">
            <input
              id="destination_mte_destination_search"
              type="search"
              placeholder="Digite para buscar lotação de destino"
              autocomplete="off"
              spellcheck="false"
            >
            <div
              id="destination_mte_destination_suggestions"
              class="lookup-suggestions"
              role="listbox"
              aria-label="Sugestões de lotação de destino no MTE"
              aria-live="polite"
            ></div>
            <select
              id="destination_mte_destination_id"
              name="destination_mte_destination_id"
              class="lookup-fallback-select"
              <?= $isEntryDirection ? 'required' : '' ?>
              <?= $isEntryDirection ? '' : 'disabled' ?>
            >
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
          </div>
        </div>
      </div>

      <div class="form-actions field-wide people-step-actions">
        <a class="btn btn-outline" href="<?= e($cancelUrl) ?>">Cancelar</a>
        <button type="button" class="btn btn-primary" data-step-next="1">Continuar</button>
      </div>
    </section>

    <section class="people-wizard-step field-wide" data-step="2">
      <div class="people-step-header">
        <h3>Etapa 2: Dados da pessoa</h3>
        <p class="muted">Cadastre identificação e contato.</p>
      </div>

      <div class="people-step-grid">
        <div class="field">
          <label for="cpf">CPF *</label>
          <div class="inline-field">
            <input
              id="cpf"
              name="cpf"
              type="text"
              value="<?= e(old('cpf', (string) ($person['cpf'] ?? ''))) ?>"
              required
              placeholder="000.000.000-00"
              inputmode="numeric"
              autocomplete="off"
            >
            <button type="button" class="btn btn-ghost" id="cpf_lookup_button">Buscar</button>
          </div>
          <p class="form-feedback" id="cpf_feedback" aria-live="polite"></p>
        </div>

        <div class="field field-wide">
          <label for="name">Nome completo *</label>
          <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($person['name'] ?? ''))) ?>" required>
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
        </div>

        <div class="field">
          <label for="birth_date">Data de nascimento</label>
          <input id="birth_date" name="birth_date" type="date" value="<?= e(old('birth_date', (string) ($person['birth_date'] ?? ''))) ?>">
        </div>

        <div class="field">
          <label for="gender">Sexo</label>
          <?php $selectedGender = strtoupper(trim((string) old('gender', (string) ($person['gender'] ?? '')))); ?>
          <select id="gender" name="gender">
            <option value="">Selecione</option>
            <option value="F" <?= $selectedGender === 'F' ? 'selected' : '' ?>>Feminino (F)</option>
            <option value="M" <?= $selectedGender === 'M' ? 'selected' : '' ?>>Masculino (M)</option>
          </select>
        </div>

        <div class="field">
          <label for="server_status_code">Situação do servidor (código)</label>
          <input
            id="server_status_code"
            name="server_status_code"
            type="text"
            value="<?= e(old('server_status_code', (string) ($person['server_status_code'] ?? ''))) ?>"
            maxlength="30"
            placeholder="Ex.: 44"
          >
        </div>

        <div class="field">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" value="<?= e(old('email', (string) ($person['email'] ?? ''))) ?>">
        </div>

        <div class="field">
          <label for="phone">Telefone</label>
          <input id="phone" name="phone" type="text" value="<?= e(old('phone', (string) ($person['phone'] ?? ''))) ?>">
        </div>
      </div>

      <div class="form-actions field-wide people-step-actions">
        <button type="button" class="btn btn-outline" data-step-prev="2">Voltar</button>
        <button type="button" class="btn btn-primary" data-step-next="2">Continuar</button>
      </div>
    </section>

    <section class="people-wizard-step field-wide" data-step="3">
      <div class="people-step-header">
        <h3>Etapa 3: Informações complementares</h3>
        <p class="muted">Defina período e adicione dados opcionais.</p>
      </div>

      <div class="people-step-grid">
        <div class="field field-wide">
          <label>Período da movimentação</label>
          <div class="people-date-range">
            <div class="field">
              <label for="target_start_date">Início</label>
              <input id="target_start_date" name="target_start_date" type="date" value="<?= e($targetStartDate) ?>">
            </div>
            <div class="field">
              <label for="sei_process_number">Processo SEI</label>
              <input id="sei_process_number" name="sei_process_number" type="text" value="<?= e(old('sei_process_number', (string) ($person['sei_process_number'] ?? ''))) ?>">
            </div>
          </div>
        </div>

        <details class="people-optional-details field-wide" <?= $additionalInfoHasValues ? 'open' : '' ?>>
          <summary>Informações adicionais (opcional)</summary>
          <div class="people-optional-grid">
            <div class="field">
              <label for="requested_end_date">Data Fim - De Encerramento da Movimentação (Apenas se aplicável)</label>
              <input id="requested_end_date" name="requested_end_date" type="date" value="<?= e($requestedEndDate) ?>">
            </div>
            <div class="field">
              <label for="tags">Tags</label>
              <input id="tags" name="tags" type="text" value="<?= e(old('tags', (string) ($person['tags'] ?? ''))) ?>" placeholder="prioritario, ti, juridico">
            </div>

            <div class="field field-wide">
              <label for="notes">Observações</label>
              <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($person['notes'] ?? ''))) ?></textarea>
            </div>
          </div>
        </details>
      </div>

      <div class="form-actions field-wide people-form-actions">
        <button type="button" class="btn btn-outline" data-step-prev="3">Voltar</button>
        <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
      </div>
    </section>
  </form>
</div>
<script>
  (function () {
    var form = document.querySelector('.people-wizard-form');
    if (!form) {
      return;
    }

    var directionRadios = form.querySelectorAll('input[name="movement_direction"]');
    var organField = document.getElementById('organ_id');
    var organLabel = document.getElementById('organ_id_label');
    var organSearchField = document.getElementById('organ_id_search');
    var organSuggestions = document.getElementById('organ_id_suggestions');
    var organLookupShell = document.getElementById('organ_id_lookup_shell');
    var mteOriginField = document.getElementById('mte_origin_fixed_field');
    var financialNatureInput = document.getElementById('financial_nature');
    var reasonField = document.getElementById('desired_modality_id');
    var originField = document.getElementById('origin_mte_destination_id');
    var destinationField = document.getElementById('destination_mte_destination_id');
    var destinationSearchField = document.getElementById('destination_mte_destination_search');
    var destinationSuggestions = document.getElementById('destination_mte_destination_suggestions');
    var destinationLookupShell = document.getElementById('destination_lookup_shell');
    var originWrapper = document.getElementById('origin_mte_destination_wrapper');
    var destinationWrapper = document.getElementById('destination_mte_destination_wrapper');
    var cpfField = document.getElementById('cpf');
    var cpfFeedback = document.getElementById('cpf_feedback');
    var cpfLookupButton = document.getElementById('cpf_lookup_button');
    var wizardStepField = document.getElementById('wizard_step');
    var stepPanels = form.querySelectorAll('[data-step]');
    var stepIndicators = form.querySelectorAll('[data-step-indicator]');
    var nextButtons = form.querySelectorAll('[data-step-next]');
    var prevButtons = form.querySelectorAll('[data-step-prev]');
    var totalSteps = stepPanels.length;
    var currentStep = parseInt((wizardStepField && wizardStepField.value) || '1', 10);

    if (!currentStep || currentStep < 1 || currentStep > totalSteps) {
      currentStep = 1;
    }

    if (
      !directionRadios.length ||
      !organField ||
      !reasonField ||
      !originField ||
      !destinationField ||
      !originWrapper ||
      !destinationWrapper ||
      !financialNatureInput
    ) {
      return;
    }

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

    function initLookup(selectField, searchField, suggestionsNode, shellNode, minChars) {
      if (!selectField || !searchField || !suggestionsNode || !shellNode) {
        return null;
      }

      var catalog = [];
      var debounce = null;
      shellNode.classList.add('is-enhanced');

      function rebuildCatalog() {
        catalog = [];
        Array.prototype.forEach.call(selectField.options, function (option) {
          var value = option.value || '';
          if (value === '0' || option.disabled || option.hidden) {
            return;
          }

          var label = (option.textContent || '').trim();
          if (label === '') {
            return;
          }

          catalog.push({
            value: value,
            label: label,
            normalized: normalizeLookup(label)
          });
        });
      }

      function findLabelByValue(value) {
        for (var i = 0; i < catalog.length; i += 1) {
          if (catalog[i].value === value) {
            return catalog[i].label;
          }
        }

        return '';
      }

      function hideSuggestions() {
        suggestionsNode.innerHTML = '';
        suggestionsNode.classList.remove('is-open');
      }

      function renderSuggestions(searchValue) {
        hideSuggestions();
        if (searchField.disabled) {
          return;
        }

        var normalizedQuery = normalizeLookup(searchValue);
        if (normalizedQuery.length < minChars) {
          return;
        }

        var matches = [];
        for (var i = 0; i < catalog.length; i += 1) {
          if (!matchesLookup(catalog[i].normalized, normalizedQuery)) {
            continue;
          }

          matches.push(catalog[i]);
          if (matches.length >= 8) {
            break;
          }
        }

        if (matches.length === 0) {
          var emptyMessage = document.createElement('div');
          emptyMessage.className = 'lookup-suggestion-empty';
          emptyMessage.textContent = 'Nenhum resultado encontrado.';
          suggestionsNode.appendChild(emptyMessage);
          suggestionsNode.classList.add('is-open');
          return;
        }

        for (var index = 0; index < matches.length; index += 1) {
          var match = matches[index];
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'lookup-suggestion-btn';
          button.setAttribute('data-value', match.value);
          button.setAttribute('data-label', match.label);
          button.textContent = match.label;
          suggestionsNode.appendChild(button);
        }

        suggestionsNode.classList.add('is-open');
      }

      function syncSearchFromSelect() {
        var value = selectField.value || '';
        if (value === '' || value === '0') {
          if (document.activeElement !== searchField) {
            searchField.value = '';
          }
          return;
        }

        searchField.value = findLabelByValue(value);
      }

      function scheduleRender() {
        if (debounce !== null) {
          window.clearTimeout(debounce);
        }

        debounce = window.setTimeout(function () {
          renderSuggestions(searchField.value || '');
        }, 200);
      }

      searchField.addEventListener('input', function () {
        var currentQuery = searchField.value || '';
        var selectedLabel = findLabelByValue(selectField.value || '');

        if (normalizeLookup(currentQuery) !== '' && normalizeLookup(currentQuery) !== normalizeLookup(selectedLabel)) {
          selectField.value = '0';
        }

        scheduleRender();
      });

      searchField.addEventListener('focus', function () {
        renderSuggestions(searchField.value || '');
      });

      searchField.addEventListener('blur', function () {
        window.setTimeout(function () {
          hideSuggestions();
        }, 120);
      });

      selectField.addEventListener('change', function () {
        syncSearchFromSelect();
      });

      suggestionsNode.addEventListener('mousedown', function (event) {
        var node = event.target;
        var selectedButton = null;

        while (node && node !== suggestionsNode) {
          if (node.tagName === 'BUTTON' && node.getAttribute('data-value')) {
            selectedButton = node;
            break;
          }

          node = node.parentNode;
        }

        if (!selectedButton) {
          return;
        }

        event.preventDefault();
        selectField.value = selectedButton.getAttribute('data-value') || '0';
        searchField.value = selectedButton.getAttribute('data-label') || '';
        hideSuggestions();

        if (typeof Event === 'function') {
          selectField.dispatchEvent(new Event('change'));
        } else {
          var changeEvent = document.createEvent('Event');
          changeEvent.initEvent('change', true, true);
          selectField.dispatchEvent(changeEvent);
        }
      });

      rebuildCatalog();
      syncSearchFromSelect();

      return {
        rebuildCatalog: rebuildCatalog,
        hideSuggestions: hideSuggestions,
        syncSearchFromSelect: syncSearchFromSelect,
        clear: function () {
          searchField.value = '';
          hideSuggestions();
        }
      };
    }

    var organLookup = initLookup(organField, organSearchField, organSuggestions, organLookupShell, 2);
    var destinationLookup = initLookup(destinationField, destinationSearchField, destinationSuggestions, destinationLookupShell, 2);

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
      if (directionValue === 'saida_mte') {
        financialNatureInput.value = 'receita_reembolso';
        return;
      }

      if (directionValue === 'entrada_mte') {
        financialNatureInput.value = 'despesa_reembolso';
        return;
      }

      financialNatureInput.value = '';
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

      if (organSearchField) {
        organSearchField.placeholder = isSaida
          ? 'Digite para buscar órgão de destino'
          : (isEntrada ? 'Digite para buscar órgão de origem' : 'Selecione primeiro a direção');
        organSearchField.disabled = !(isSaida || isEntrada);
      }

      organField.required = isSaida || isEntrada;

      originWrapper.style.display = isSaida ? '' : 'none';
      destinationWrapper.style.display = isEntrada ? '' : 'none';
      originField.disabled = !isSaida;
      destinationField.disabled = !isEntrada;

      if (destinationSearchField) {
        destinationSearchField.disabled = !isEntrada;
      }

      originField.required = isSaida;
      destinationField.required = isEntrada;

      if (!isSaida) {
        originField.value = '0';
      }

      if (!isEntrada) {
        destinationField.value = '0';
      }

      if (organLookup) {
        organLookup.rebuildCatalog();
        organLookup.syncSearchFromSelect();
        if (!(isSaida || isEntrada)) {
          organLookup.clear();
        }
      }

      if (destinationLookup) {
        destinationLookup.rebuildCatalog();
        destinationLookup.syncSearchFromSelect();
        if (!isEntrada) {
          destinationLookup.clear();
        }
      }
    }

    function panelByStep(step) {
      for (var i = 0; i < stepPanels.length; i += 1) {
        if (parseInt(stepPanels[i].getAttribute('data-step') || '0', 10) === step) {
          return stepPanels[i];
        }
      }

      return null;
    }

    function focusFirstField(step) {
      var panel = panelByStep(step);
      if (!panel) {
        return;
      }

      var field = panel.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
      if (field && typeof field.focus === 'function') {
        field.focus();
      }
    }

    function showStep(step, shouldFocus) {
      var clamped = step;
      if (clamped < 1) {
        clamped = 1;
      }
      if (clamped > totalSteps) {
        clamped = totalSteps;
      }

      currentStep = clamped;
      if (wizardStepField) {
        wizardStepField.value = String(clamped);
      }

      for (var i = 0; i < stepPanels.length; i += 1) {
        var panel = stepPanels[i];
        var panelStep = parseInt(panel.getAttribute('data-step') || '0', 10);
        var isActive = panelStep === clamped;
        panel.hidden = !isActive;
        panel.classList.toggle('is-active', isActive);
      }

      for (var index = 0; index < stepIndicators.length; index += 1) {
        var indicator = stepIndicators[index];
        var indicatorStep = parseInt(indicator.getAttribute('data-step-indicator') || '0', 10);
        indicator.classList.toggle('is-active', indicatorStep === clamped);
        indicator.classList.toggle('is-done', indicatorStep < clamped);
      }

      if (shouldFocus) {
        focusFirstField(clamped);
      }
    }

    function isFieldVisible(field) {
      if (!field || field.disabled || field.type === 'hidden') {
        return false;
      }

      var style = window.getComputedStyle(field);
      if (style.display === 'none' || style.visibility === 'hidden') {
        return false;
      }

      var parent = field.parentElement;
      while (parent) {
        if (parent.hidden) {
          return false;
        }

        var parentStyle = window.getComputedStyle(parent);
        if (parentStyle.display === 'none' || parentStyle.visibility === 'hidden') {
          return false;
        }

        if (parent === form) {
          break;
        }

        parent = parent.parentElement;
      }

      return true;
    }

    function validateStep(step) {
      var panel = panelByStep(step);
      if (!panel) {
        return true;
      }

      var fields = panel.querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i += 1) {
        var field = fields[i];
        if (!isFieldVisible(field)) {
          continue;
        }

        if (!field.checkValidity()) {
          field.reportValidity();
          return false;
        }
      }

      return true;
    }

    function validateCpfDigits(digits) {
      if (digits.length !== 11 || /^([0-9])\1{10}$/.test(digits)) {
        return false;
      }

      var sum = 0;
      for (var i = 0; i < 9; i += 1) {
        sum += parseInt(digits.charAt(i), 10) * (10 - i);
      }
      var firstCheck = (sum * 10) % 11;
      if (firstCheck === 10) {
        firstCheck = 0;
      }

      if (firstCheck !== parseInt(digits.charAt(9), 10)) {
        return false;
      }

      sum = 0;
      for (var j = 0; j < 10; j += 1) {
        sum += parseInt(digits.charAt(j), 10) * (11 - j);
      }
      var secondCheck = (sum * 10) % 11;
      if (secondCheck === 10) {
        secondCheck = 0;
      }

      return secondCheck === parseInt(digits.charAt(10), 10);
    }

    function formatCpf(rawValue) {
      var digits = (rawValue || '').replace(/\D+/g, '').slice(0, 11);
      if (digits.length <= 3) {
        return digits;
      }

      if (digits.length <= 6) {
        return digits.slice(0, 3) + '.' + digits.slice(3);
      }

      if (digits.length <= 9) {
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
      }

      return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
    }

    function setCpfFeedback(message, tone) {
      if (!cpfFeedback) {
        return;
      }

      cpfFeedback.textContent = message;
      cpfFeedback.classList.remove('is-error', 'is-success', 'is-info');
      if (tone) {
        cpfFeedback.classList.add(tone);
      }
    }

    function syncCpfValidity() {
      if (!cpfField) {
        return;
      }

      var formatted = formatCpf(cpfField.value || '');
      if (formatted !== cpfField.value) {
        cpfField.value = formatted;
      }

      var digits = formatted.replace(/\D+/g, '');
      if (digits.length === 0) {
        cpfField.setCustomValidity('');
        setCpfFeedback('', '');
      } else if (digits.length < 11) {
        cpfField.setCustomValidity('CPF deve ter 11 dígitos.');
        setCpfFeedback('Informe 11 dígitos para validar o CPF.', 'is-info');
      } else if (!validateCpfDigits(digits)) {
        cpfField.setCustomValidity('CPF inválido.');
        setCpfFeedback('CPF inválido.', 'is-error');
      } else {
        cpfField.setCustomValidity('');
        setCpfFeedback('CPF válido.', 'is-success');
      }

      if (cpfLookupButton) {
        cpfLookupButton.disabled = digits.length !== 11;
      }
    }

    function normalizeDateInput(value) {
      if (typeof value !== 'string') {
        return '';
      }

      var clean = value.trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(clean)) {
        return clean;
      }

      return '';
    }

    function fillFieldIfEmpty(fieldId, value) {
      var field = document.getElementById(fieldId);
      if (!field || typeof value !== 'string' || value.trim() === '') {
        return;
      }

      if ((field.value || '').trim() === '') {
        field.value = value.trim();
      }
    }

    function lookupCpf() {
      if (!cpfField || !cpfLookupButton) {
        return;
      }

      var endpoint = form.getAttribute('data-cpf-lookup-url') || '';
      var digits = (cpfField.value || '').replace(/\D+/g, '');
      if (endpoint === '' || digits.length !== 11) {
        return;
      }

      cpfLookupButton.disabled = true;
      cpfLookupButton.textContent = 'Buscando...';
      setCpfFeedback('Consultando cadastro por CPF...', 'is-info');

      var url = endpoint + '?cpf=' + encodeURIComponent(digits);
      fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'application/json'
        }
      })
        .then(function (response) {
          return response.json().then(function (payload) {
            return {
              ok: response.ok,
              status: response.status,
              payload: payload
            };
          }).catch(function () {
            return {
              ok: false,
              status: response.status,
              payload: null
            };
          });
        })
        .then(function (result) {
          var errorMessage = '';
          if (result.payload && typeof result.payload.error === 'string') {
            errorMessage = result.payload.error;
          }

          if (!result.ok || !result.payload || result.payload.ok !== true) {
            setCpfFeedback(errorMessage !== '' ? errorMessage : 'Não foi possível consultar o CPF agora.', 'is-error');
            return;
          }

          if (result.payload.found !== true) {
            setCpfFeedback('Nenhum cadastro encontrado para este CPF.', 'is-info');
            return;
          }

          var person = result.payload.person || {};
          fillFieldIfEmpty('name', person.name || '');
          fillFieldIfEmpty('matricula_siape', person.matricula_siape || '');
          fillFieldIfEmpty('gender', (person.gender || '').toString().toUpperCase());
          fillFieldIfEmpty('server_status_code', person.server_status_code || '');
          fillFieldIfEmpty('email', person.email || '');
          fillFieldIfEmpty('phone', person.phone || '');

          var birthField = document.getElementById('birth_date');
          var birthValue = normalizeDateInput(person.birth_date || '');
          if (birthField && birthValue !== '' && (birthField.value || '').trim() === '') {
            birthField.value = birthValue;
          }

          setCpfFeedback('Dados localizados e preenchidos automaticamente.', 'is-success');
        })
        .catch(function () {
          setCpfFeedback('Falha de conexão ao consultar CPF.', 'is-error');
        })
        .finally(function () {
          var currentDigits = (cpfField.value || '').replace(/\D+/g, '');
          cpfLookupButton.disabled = currentDigits.length !== 11;
          cpfLookupButton.textContent = 'Buscar';
        });
    }

    Array.prototype.forEach.call(directionRadios, function (radio) {
      radio.addEventListener('change', syncByDirection);
    });

    Array.prototype.forEach.call(nextButtons, function (button) {
      button.addEventListener('click', function () {
        if (!validateStep(currentStep)) {
          return;
        }

        showStep(currentStep + 1, true);
      });
    });

    Array.prototype.forEach.call(prevButtons, function (button) {
      button.addEventListener('click', function () {
        showStep(currentStep - 1, true);
      });
    });

    Array.prototype.forEach.call(stepIndicators, function (indicator) {
      indicator.addEventListener('click', function () {
        var targetStep = parseInt(indicator.getAttribute('data-step-indicator') || '1', 10);
        if (!targetStep || targetStep === currentStep) {
          return;
        }

        if (targetStep > currentStep && !validateStep(currentStep)) {
          return;
        }

        showStep(targetStep, true);
      });
    });

    form.addEventListener('submit', function (event) {
      if (currentStep < totalSteps) {
        event.preventDefault();
        if (validateStep(currentStep)) {
          showStep(currentStep + 1, true);
        }
        return;
      }

      for (var step = 1; step <= totalSteps; step += 1) {
        if (!validateStep(step)) {
          event.preventDefault();
          showStep(step, true);
          return;
        }
      }
    });

    if (cpfField) {
      cpfField.addEventListener('input', syncCpfValidity);
      cpfField.addEventListener('blur', syncCpfValidity);
      syncCpfValidity();
    }

    if (cpfLookupButton) {
      cpfLookupButton.addEventListener('click', lookupCpf);
    }

    var siapeField = document.getElementById('matricula_siape');
    if (siapeField) {
      siapeField.addEventListener('input', function () {
        var digits = (siapeField.value || '').replace(/\D+/g, '');
        if (digits !== siapeField.value) {
          siapeField.value = digits;
        }
      });
    }

    syncByDirection();
    showStep(currentStep, false);
  })();
</script>
