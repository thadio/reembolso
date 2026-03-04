<?php

declare(strict_types=1);

$year = max(2000, min(2100, (int) ($year ?? date('Y'))));
$budget = is_array($budget ?? null) ? $budget : [];
$summary = is_array($budget['summary'] ?? null) ? $budget['summary'] : [];
$projection = is_array($budget['projection'] ?? null) ? $budget['projection'] : [];
$projectionMonths = is_array($projection['months'] ?? null) ? $projection['months'] : [];
$nextYearScenarios = is_array($projection['next_year_scenarios'] ?? null) ? $projection['next_year_scenarios'] : [];
$cycle = is_array($budget['cycle'] ?? null) ? $budget['cycle'] : [];
$organs = is_array($budget['organs'] ?? null) ? $budget['organs'] : [];
$modalities = is_array($budget['modalities'] ?? null) ? $budget['modalities'] : [];
$parameters = is_array($budget['parameters'] ?? null) ? $budget['parameters'] : [];
$scenarioParameters = is_array($budget['scenario_parameters'] ?? null) ? $budget['scenario_parameters'] : [];
$defaultVariations = is_array($budget['default_variations'] ?? null) ? $budget['default_variations'] : [];
$insufficiencyRisks = is_array($budget['insufficiency_risks'] ?? null) ? $budget['insufficiency_risks'] : [];
$offenders = is_array($budget['offenders'] ?? null) ? $budget['offenders'] : [];
$activeAlerts = is_array($budget['active_alerts'] ?? null) ? $budget['active_alerts'] : [];
$scenarios = is_array($budget['scenarios'] ?? null) ? $budget['scenarios'] : [];
$simulationResult = is_array($simulationResult ?? null) ? $simulationResult : null;
$simulationMatrix = is_array($simulationResult['scenario_matrix'] ?? null) ? $simulationResult['scenario_matrix'] : [];
$canManage = (bool) ($canManage ?? false);
$canSimulate = (bool) ($canSimulate ?? false);

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatPercent = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return number_format($numeric, 2, ',', '.') . '%';
};

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$modalityLabel = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'geral';
    }

    $normalized = str_replace('_', ' ', $raw);

    return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
};

$movementTypeLabel = static function (?string $value): string {
    return trim((string) $value) === 'saida' ? 'Saida' : 'Entrada';
};

$scopeLabel = static function (?string $cargo, ?string $setor): string {
    $c = trim((string) $cargo);
    $s = trim((string) $setor);

    if ($c === '' && $s === '') {
        return 'Geral';
    }

    if ($c !== '' && $s !== '') {
        return 'Cargo: ' . $c . ' | Setor: ' . $s;
    }

    if ($c !== '') {
        return 'Cargo: ' . $c;
    }

    return 'Setor: ' . $s;
};

$riskLabel = static function (string $value): string {
    return match ($value) {
        'alto' => 'Alto',
        'medio' => 'Medio',
        default => 'Baixo',
    };
};

$riskBadgeClass = static function (string $value): string {
    return match ($value) {
        'alto' => 'badge-danger',
        'medio' => 'badge-warning',
        default => 'badge-success',
    };
};

$summaryRisk = (string) ($summary['risk_level'] ?? 'baixo');
$totalBudget = (float) ($summary['total_budget'] ?? 0);
$availableAmount = (float) ($summary['available_amount'] ?? 0);
$projectedBalance = (float) ($summary['projected_balance_next_year'] ?? 0);
$annualProjectionCurrentYear = (float) ($projection['annual_projection_current_year'] ?? 0);
$annualBalanceCurrentYear = (float) ($projection['annual_balance_current_year'] ?? 0);
$defaultBaseVariation = (float) ($defaultVariations['base'] ?? 0.0);
$defaultUpdatedVariation = (float) ($defaultVariations['atualizado'] ?? 10.0);
$defaultWorstVariation = (float) ($defaultVariations['pior_caso'] ?? 25.0);
$selectedModality = mb_strtolower((string) old('modality', 'geral'));
$selectedMovementType = mb_strtolower((string) old('movement_type', 'entrada'));
$selectedCargo = (string) old('cargo', '');
$selectedSetor = (string) old('setor', '');
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Dashboard orcamentario</h2>
      <p class="muted">Ciclo <?= e((string) $year) ?> · Fator anual <?= e(number_format((float) ($summary['annual_factor'] ?? 13.3), 2, ',', '.')) ?></p>
    </div>
    <form method="get" action="<?= e(url('/budget')) ?>" class="filters-row filters-budget-year">
      <div class="field">
        <label for="year">Ano do ciclo</label>
        <input id="year" name="year" type="number" min="2000" max="2100" value="<?= e((string) $year) ?>">
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-outline">Atualizar</button>
      </div>
    </form>
  </div>

  <p class="muted">Ciclo criado em <?= e($formatDateTime((string) ($cycle['created_at'] ?? ''))) ?><?php if (!empty($cycle['created_by_name'])): ?> por <?= e((string) ($cycle['created_by_name'] ?? '')) ?><?php endif; ?>.</p>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Orcamento total</p>
    <p class="kpi-value"><?= e($formatMoney($summary['total_budget'] ?? 0)) ?></p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Executado</p>
    <p class="kpi-value"><?= e($formatMoney($summary['executed_amount'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Boletos pagos + reembolsos pagos</p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Comprometido</p>
    <p class="kpi-value"><?= e($formatMoney($summary['committed_amount'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Titulos pendentes + reembolsos pendentes</p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Disponivel</p>
    <p class="kpi-value <?= $availableAmount < 0 ? 'text-danger' : '' ?>"><?= e($formatMoney($summary['available_amount'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Risco atual: <span class="badge <?= e($riskBadgeClass($summaryRisk)) ?>"><?= e($riskLabel($summaryRisk)) ?></span></p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Projecao ano seguinte</p>
    <p class="kpi-value"><?= e($formatMoney($summary['projected_next_year'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Base mensal ativa x fator anual</p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Saldo projetado (ano seguinte)</p>
    <p class="kpi-value <?= $projectedBalance < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney($summary['projected_balance_next_year'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Comparacao com orcamento total do ciclo</p>
  </article>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Alertas ativos (5.4)</h3>
      <p class="muted">Sinais de risco orcamentario e deficit projetado para acao imediata.</p>
    </div>
  </div>

  <?php if ($activeAlerts === []): ?>
    <div class="empty-state">
      <p>Sem alertas ativos no momento para o ciclo selecionado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nivel</th>
            <th>Titulo</th>
            <th>Mensagem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeAlerts as $alert): ?>
            <?php $alertLevel = (string) ($alert['level'] ?? 'baixo'); ?>
            <tr>
              <td><span class="badge <?= e($riskBadgeClass($alertLevel)) ?>"><?= e($riskLabel($alertLevel)) ?></span></td>
              <td><?= e((string) ($alert['title'] ?? '-')) ?></td>
              <td><?= e((string) ($alert['message'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Projecoes 5.1 (mensal, anual e proximo ano)</h3>
      <p class="muted">Consolidacao mensal do ciclo e envelopes de cenario para o proximo ano.</p>
    </div>
  </div>

  <div class="grid-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Projecao mensal media</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($projection['monthly_average_projection'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Projecao anual (ciclo atual)</p>
      <p class="kpi-value"><?= e($formatMoney($annualProjectionCurrentYear)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Saldo anual projetado</p>
      <p class="kpi-value <?= $annualBalanceCurrentYear < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney($annualBalanceCurrentYear)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Prox. ano (Base)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($nextYearScenarios['base'] ?? 0))) ?></p>
      <p class="dashboard-kpi-note">Sem variacao adicional</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Prox. ano (Atualizado)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($nextYearScenarios['atualizado'] ?? 0))) ?></p>
      <p class="dashboard-kpi-note">Variacao padrao <?= e($formatPercent($defaultUpdatedVariation)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Prox. ano (Pior Caso)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($nextYearScenarios['pior_caso'] ?? 0))) ?></p>
      <p class="dashboard-kpi-note">Variacao padrao <?= e($formatPercent($defaultWorstVariation)) ?></p>
    </article>
  </div>

  <?php if ($projectionMonths === []): ?>
    <div class="empty-state">
      <p>Sem dados para montar serie mensal de projecao.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th>Mes</th>
            <th>Executado</th>
            <th>Comprometido</th>
            <th>Base projetada</th>
            <th>Total projetado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projectionMonths as $monthProjection): ?>
            <tr>
              <td><?= e((string) ($monthProjection['label'] ?? '-')) ?></td>
              <td><?= e($formatMoney((float) ($monthProjection['executed_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($monthProjection['committed_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($monthProjection['projected_base_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($monthProjection['projected_total'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Risco de insuficiencia por mes (5.2)</h3>
      <p class="muted">Compara o acumulado projetado do ciclo com o envelope acumulado de orcamento.</p>
    </div>
  </div>

  <?php if ($insufficiencyRisks === []): ?>
    <div class="empty-state">
      <p>Sem dados suficientes para calcular risco mensal de insuficiencia.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mes</th>
            <th>Orcamento acumulado</th>
            <th>Projecao acumulada</th>
            <th>Diferenca</th>
            <th>Pressao</th>
            <th>Risco</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($insufficiencyRisks as $risk): ?>
            <?php $riskCode = (string) ($risk['risk_level'] ?? 'baixo'); ?>
            <tr>
              <td><?= e((string) ($risk['label'] ?? '-')) ?></td>
              <td><?= e($formatMoney((float) ($risk['cumulative_budget'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($risk['cumulative_projection'] ?? 0))) ?></td>
              <td class="<?= (float) ($risk['difference'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                <?= e($formatMoney((float) ($risk['difference'] ?? 0))) ?>
              </td>
              <td><?= e($formatPercent((float) ($risk['pressure_percent'] ?? 0))) ?></td>
              <td><span class="badge <?= e($riskBadgeClass($riskCode)) ?>"><?= e($riskLabel($riskCode)) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Parametro de custo medio por orgao</h3>
        <p class="muted">Usado como fallback na simulacao quando o custo medio nao e informado.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/budget/parameters/upsert')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="year" value="<?= e((string) $year) ?>">

      <div class="field field-wide">
        <label for="parameter_organ_id">Orgao *</label>
        <select id="parameter_organ_id" name="parameter_organ_id" required>
          <option value="">Selecione um orgao</option>
          <?php foreach ($organs as $organ): ?>
            <option value="<?= e((string) ($organ['id'] ?? 0)) ?>" <?= old('parameter_organ_id') === (string) ($organ['id'] ?? '') ? 'selected' : '' ?>>
              <?= e((string) ($organ['name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="parameter_cargo">Cargo</label>
        <input id="parameter_cargo" name="parameter_cargo" type="text" maxlength="120" value="<?= e(old('parameter_cargo', '')) ?>" placeholder="Opcional">
      </div>

      <div class="field">
        <label for="parameter_setor">Setor</label>
        <input id="parameter_setor" name="parameter_setor" type="text" maxlength="120" value="<?= e(old('parameter_setor', '')) ?>" placeholder="Opcional">
      </div>

      <div class="field">
        <label for="parameter_avg_monthly_cost">Custo medio mensal (R$) *</label>
        <input id="parameter_avg_monthly_cost" name="parameter_avg_monthly_cost" type="text" placeholder="0,00" value="<?= e(old('parameter_avg_monthly_cost', '')) ?>" required>
      </div>

      <div class="field field-wide">
        <label for="parameter_notes">Observacoes</label>
        <textarea id="parameter_notes" name="parameter_notes" rows="3"><?= e(old('parameter_notes', '')) ?></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Salvar parametro</button>
      </div>
    </form>

    <?php if ($parameters === []): ?>
      <div class="empty-state">
        <p>Nenhum parametro por orgao cadastrado ate o momento.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Orgao</th>
              <th>Escopo</th>
              <th>Custo medio mensal</th>
              <th>Observacoes</th>
              <th>Atualizacao</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($parameters as $parameter): ?>
              <tr>
                <td><?= e((string) ($parameter['organ_name'] ?? '-')) ?></td>
                <td><?= e($scopeLabel((string) ($parameter['cargo'] ?? ''), (string) ($parameter['setor'] ?? ''))) ?></td>
                <td><?= e($formatMoney((float) ($parameter['avg_monthly_cost'] ?? 0))) ?></td>
                <td><?= nl2br(e((string) ($parameter['notes'] ?? '-'))) ?></td>
                <td>
                  <?= e($formatDateTime((string) ($parameter['updated_at'] ?? ''))) ?>
                  <div class="muted">por <?= e((string) ($parameter['updated_by_name'] ?? 'N/I')) ?></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="header-row">
      <div>
        <h3>Variacoes de cenario por orgao/modalidade</h3>
        <p class="muted">Define variacoes para cenarios Base, Atualizado e Pior Caso no simulador.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/budget/scenario-parameters/upsert')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="year" value="<?= e((string) $year) ?>">

      <div class="field field-wide">
        <label for="scenario_parameter_organ_id">Orgao *</label>
        <select id="scenario_parameter_organ_id" name="scenario_parameter_organ_id" required>
          <option value="">Selecione um orgao</option>
          <?php foreach ($organs as $organ): ?>
            <option value="<?= e((string) ($organ['id'] ?? 0)) ?>" <?= old('scenario_parameter_organ_id') === (string) ($organ['id'] ?? '') ? 'selected' : '' ?>>
              <?= e((string) ($organ['name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="scenario_parameter_modality">Modalidade *</label>
        <select id="scenario_parameter_modality" name="scenario_parameter_modality" required>
          <option value="geral" <?= mb_strtolower((string) old('scenario_parameter_modality', 'geral')) === 'geral' ? 'selected' : '' ?>>Geral</option>
          <?php foreach ($modalities as $modality): ?>
            <?php $modalityValue = mb_strtolower(trim((string) ($modality['name'] ?? ''))); ?>
            <?php if ($modalityValue === '') { continue; } ?>
            <option value="<?= e($modalityValue) ?>" <?= mb_strtolower((string) old('scenario_parameter_modality', 'geral')) === $modalityValue ? 'selected' : '' ?>>
              <?= e((string) ($modality['name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="scenario_parameter_base_variation_percent">Variacao Base (%) *</label>
        <input
          id="scenario_parameter_base_variation_percent"
          name="scenario_parameter_base_variation_percent"
          type="text"
          value="<?= e(old('scenario_parameter_base_variation_percent', number_format($defaultBaseVariation, 2, ',', '.'))) ?>"
          required
        >
      </div>

      <div class="field">
        <label for="scenario_parameter_updated_variation_percent">Variacao Atualizado (%) *</label>
        <input
          id="scenario_parameter_updated_variation_percent"
          name="scenario_parameter_updated_variation_percent"
          type="text"
          value="<?= e(old('scenario_parameter_updated_variation_percent', number_format($defaultUpdatedVariation, 2, ',', '.'))) ?>"
          required
        >
      </div>

      <div class="field">
        <label for="scenario_parameter_worst_variation_percent">Variacao Pior Caso (%) *</label>
        <input
          id="scenario_parameter_worst_variation_percent"
          name="scenario_parameter_worst_variation_percent"
          type="text"
          value="<?= e(old('scenario_parameter_worst_variation_percent', number_format($defaultWorstVariation, 2, ',', '.'))) ?>"
          required
        >
      </div>

      <div class="field field-wide">
        <label for="scenario_parameter_notes">Observacoes</label>
        <textarea id="scenario_parameter_notes" name="scenario_parameter_notes" rows="3"><?= e(old('scenario_parameter_notes', '')) ?></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Salvar variacoes</button>
      </div>
    </form>

    <?php if ($scenarioParameters === []): ?>
      <div class="empty-state">
        <p>Nenhuma variacao de cenario cadastrada para este ciclo.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Orgao</th>
              <th>Modalidade</th>
              <th>Base</th>
              <th>Atualizado</th>
              <th>Pior Caso</th>
              <th>Atualizacao</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scenarioParameters as $scenarioParameter): ?>
              <tr>
                <td><?= e((string) ($scenarioParameter['organ_name'] ?? '-')) ?></td>
                <td><?= e($modalityLabel((string) ($scenarioParameter['modality'] ?? 'geral'))) ?></td>
                <td><?= e($formatPercent((float) ($scenarioParameter['base_variation_percent'] ?? 0))) ?></td>
                <td><?= e($formatPercent((float) ($scenarioParameter['updated_variation_percent'] ?? 0))) ?></td>
                <td><?= e($formatPercent((float) ($scenarioParameter['worst_variation_percent'] ?? 0))) ?></td>
                <td>
                  <?= e($formatDateTime((string) ($scenarioParameter['updated_at'] ?? ''))) ?>
                  <div class="muted">por <?= e((string) ($scenarioParameter['updated_by_name'] ?? 'N/I')) ?></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($canSimulate): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Simulador de contratacao multiparametrico</h3>
        <p class="muted">Cenarios Base, Atualizado e Pior Caso com variacao por orgao/modalidade.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/budget/simulate')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="year" value="<?= e((string) $year) ?>">

      <div class="field field-wide">
        <label for="organ_id">Orgao *</label>
        <select id="organ_id" name="organ_id" required>
          <option value="">Selecione um orgao</option>
          <?php foreach ($organs as $organ): ?>
            <option value="<?= e((string) ($organ['id'] ?? 0)) ?>" <?= old('organ_id') === (string) ($organ['id'] ?? '') ? 'selected' : '' ?>>
              <?= e((string) ($organ['name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="modality">Modalidade *</label>
        <select id="modality" name="modality" required>
          <option value="geral" <?= $selectedModality === 'geral' ? 'selected' : '' ?>>Geral</option>
          <?php foreach ($modalities as $modality): ?>
            <?php $modalityValue = mb_strtolower(trim((string) ($modality['name'] ?? ''))); ?>
            <?php if ($modalityValue === '') { continue; } ?>
            <option value="<?= e($modalityValue) ?>" <?= $selectedModality === $modalityValue ? 'selected' : '' ?>>
              <?= e((string) ($modality['name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="movement_type">Tipo de movimento *</label>
        <select id="movement_type" name="movement_type" required>
          <option value="entrada" <?= $selectedMovementType === 'entrada' ? 'selected' : '' ?>>Entrada</option>
          <option value="saida" <?= $selectedMovementType === 'saida' ? 'selected' : '' ?>>Saida</option>
        </select>
      </div>

      <div class="field">
        <label for="cargo">Cargo</label>
        <input id="cargo" name="cargo" type="text" maxlength="120" value="<?= e($selectedCargo) ?>" placeholder="Opcional">
      </div>

      <div class="field">
        <label for="setor">Setor</label>
        <input id="setor" name="setor" type="text" maxlength="120" value="<?= e($selectedSetor) ?>" placeholder="Opcional">
      </div>

      <div class="field">
        <label for="scenario_name">Nome do cenario</label>
        <input id="scenario_name" name="scenario_name" type="text" maxlength="190" value="<?= e(old('scenario_name', '')) ?>" placeholder="Opcional">
      </div>

      <div class="field">
        <label for="entry_date">Data de entrada *</label>
        <input id="entry_date" name="entry_date" type="date" value="<?= e(old('entry_date', $year . '-01-01')) ?>" required>
      </div>

      <div class="field">
        <label for="quantity">Quantidade de pessoas *</label>
        <input id="quantity" name="quantity" type="number" min="1" max="10000" value="<?= e(old('quantity', '1')) ?>" required>
      </div>

      <div class="field">
        <label for="avg_monthly_cost">Custo medio mensal (R$)</label>
        <input id="avg_monthly_cost" name="avg_monthly_cost" type="text" value="<?= e(old('avg_monthly_cost', '')) ?>" placeholder="Opcional (usa parametro do orgao ou media global)">
      </div>

      <div class="field field-wide">
        <label for="notes">Observacoes</label>
        <textarea id="notes" name="notes" rows="3" placeholder="Opcional"><?= e(old('notes', '')) ?></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Executar simulacao</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($simulationResult !== null): ?>
  <?php $simRisk = (string) ($simulationResult['risk_level'] ?? 'baixo'); ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Resultado da ultima simulacao</h3>
        <p class="muted">
          <?= e((string) ($simulationResult['scenario_name'] ?? 'Simulacao')) ?> ·
          Ano <?= e((string) ($simulationResult['year'] ?? $year)) ?> ·
          Modalidade <?= e($modalityLabel((string) ($simulationResult['modality'] ?? 'geral'))) ?> ·
          Movimento <?= e($movementTypeLabel((string) ($simulationResult['movement_type'] ?? 'entrada'))) ?> ·
          <?= e($scopeLabel((string) ($simulationResult['cargo'] ?? ''), (string) ($simulationResult['setor'] ?? ''))) ?>
        </p>
      </div>
      <span class="badge <?= e($riskBadgeClass($simRisk)) ?>">Risco Base <?= e($riskLabel($simRisk)) ?></span>
    </div>

    <div class="grid-kpi">
      <article class="card kpi-card">
        <p class="kpi-label">Quantidade</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['quantity'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Custo medio mensal (Base)</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['avg_monthly_cost'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Meses restantes no ano</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['months_remaining'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Impacto ano corrente (Base)</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['cost_current_year'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Impacto ano seguinte (Base)</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['cost_next_year'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Capacidade maxima (Base)</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['max_capacity_before'] ?? 0)) ?></p>
      </article>
    </div>

    <p class="muted">
      Disponivel antes: <?= e($formatMoney((float) ($simulationResult['available_before'] ?? 0))) ?> ·
      Saldo apos simulacao Base:
      <strong class="<?= (float) ($simulationResult['remaining_after_current_year'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
        <?= e($formatMoney((float) ($simulationResult['remaining_after_current_year'] ?? 0))) ?>
      </strong>
      · Fonte do custo medio: <?= e((string) ($simulationResult['avg_source'] ?? 'informado')) ?>
    </p>

    <?php if ($simulationMatrix !== []): ?>
      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Cenario</th>
              <th>Variacao</th>
              <th>Custo medio mensal</th>
              <th>Impacto ano corrente</th>
              <th>Impacto ano seguinte</th>
              <th>Capacidade maxima</th>
              <th>Saldo apos simulacao</th>
              <th>Risco</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($simulationMatrix as $matrixItem): ?>
              <?php $matrixRisk = (string) ($matrixItem['risk_level'] ?? 'baixo'); ?>
              <tr>
                <td><?= e((string) ($matrixItem['label'] ?? '-')) ?></td>
                <td><?= e($formatPercent((float) ($matrixItem['variation_percent'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($matrixItem['avg_monthly_cost'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($matrixItem['cost_current_year'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($matrixItem['cost_next_year'] ?? 0))) ?></td>
                <td><?= e((string) (int) ($matrixItem['max_capacity_before'] ?? 0)) ?></td>
                <td class="<?= (float) ($matrixItem['remaining_after_current_year'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                  <?= e($formatMoney((float) ($matrixItem['remaining_after_current_year'] ?? 0))) ?>
                </td>
                <td><span class="badge <?= e($riskBadgeClass($matrixRisk)) ?>"><?= e($riskLabel($matrixRisk)) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Ranking de maiores ofensores (Pior Caso)</h3>
      <p class="muted">Cenarios de entrada com maior deficit projetado no perfil de pior caso.</p>
    </div>
  </div>

  <?php if ($offenders === []): ?>
    <div class="empty-state">
      <p>Nenhum ofensor de desvio identificado para o ciclo.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cenario</th>
            <th>Orgao</th>
            <th>Modalidade</th>
            <th>Escopo</th>
            <th>Qtd.</th>
            <th>Custo pior caso</th>
            <th>Saldo apos pior caso</th>
            <th>Deficit</th>
            <th>Registro</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($offenders as $offender): ?>
            <tr>
              <td><?= e((string) ($offender['scenario_name'] ?? '-')) ?></td>
              <td><?= e((string) ($offender['organ_name'] ?? '-')) ?></td>
              <td><?= e($modalityLabel((string) ($offender['modality'] ?? 'geral'))) ?></td>
              <td><?= e($scopeLabel((string) ($offender['cargo'] ?? ''), (string) ($offender['setor'] ?? ''))) ?></td>
              <td><?= e((string) (int) ($offender['quantity'] ?? 0)) ?></td>
              <td><?= e($formatMoney((float) ($offender['worst_cost_current_year'] ?? 0))) ?></td>
              <td class="<?= (float) ($offender['remaining_after_worst'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                <?= e($formatMoney((float) ($offender['remaining_after_worst'] ?? 0))) ?>
              </td>
              <td class="<?= (float) ($offender['deficit_amount'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>">
                <?= e($formatMoney((float) ($offender['deficit_amount'] ?? 0))) ?>
              </td>
              <td><?= e($formatDateTime((string) ($offender['created_at'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Cenarios recentes</h3>
      <p class="muted">Historico das simulacoes registradas no ciclo.</p>
    </div>
  </div>

  <?php if ($scenarios === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha cenarios de contratacao registrados neste ciclo.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cenario</th>
            <th>Orgao</th>
            <th>Modalidade</th>
            <th>Movimento</th>
            <th>Escopo</th>
            <th>Entrada</th>
            <th>Qtd.</th>
            <th>Custo ano corrente</th>
            <th>Custo ano seguinte</th>
            <th>Saldo apos simulacao</th>
            <th>Risco</th>
            <th>Registro</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scenarios as $scenario): ?>
            <?php $scenarioRisk = (string) ($scenario['risk_level'] ?? 'baixo'); ?>
            <tr>
              <td>
                <?= e((string) ($scenario['scenario_name'] ?? '-')) ?>
                <?php if (!empty($scenario['notes'])): ?>
                  <div class="muted"><?= nl2br(e((string) ($scenario['notes'] ?? ''))) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($scenario['organ_name'] ?? '-')) ?></td>
              <td><?= e($modalityLabel((string) ($scenario['modality'] ?? 'geral'))) ?></td>
              <td><?= e($movementTypeLabel((string) ($scenario['movement_type'] ?? 'entrada'))) ?></td>
              <td><?= e($scopeLabel((string) ($scenario['cargo'] ?? ''), (string) ($scenario['setor'] ?? ''))) ?></td>
              <td><?= e($formatDate((string) ($scenario['entry_date'] ?? ''))) ?></td>
              <td><?= e((string) (int) ($scenario['quantity'] ?? 0)) ?></td>
              <td><?= e($formatMoney((float) ($scenario['cost_current_year'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($scenario['cost_next_year'] ?? 0))) ?></td>
              <td class="<?= (float) ($scenario['remaining_after_current_year'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                <?= e($formatMoney((float) ($scenario['remaining_after_current_year'] ?? 0))) ?>
              </td>
              <td><span class="badge <?= e($riskBadgeClass($scenarioRisk)) ?>"><?= e($riskLabel($scenarioRisk)) ?></span></td>
              <td>
                <?= e($formatDateTime((string) ($scenario['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($scenario['created_by_name'] ?? 'N/I')) ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
