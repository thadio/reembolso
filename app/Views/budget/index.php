<?php

declare(strict_types=1);

$year = max(2000, min(2100, (int) ($year ?? date('Y'))));
$budget = is_array($budget ?? null) ? $budget : [];
$summary = is_array($budget['summary'] ?? null) ? $budget['summary'] : [];
$cycle = is_array($budget['cycle'] ?? null) ? $budget['cycle'] : [];
$organs = is_array($budget['organs'] ?? null) ? $budget['organs'] : [];
$parameters = is_array($budget['parameters'] ?? null) ? $budget['parameters'] : [];
$scenarios = is_array($budget['scenarios'] ?? null) ? $budget['scenarios'] : [];
$simulationResult = is_array($simulationResult ?? null) ? $simulationResult : null;
$canManage = (bool) ($canManage ?? false);
$canSimulate = (bool) ($canSimulate ?? false);

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
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
              <th>Custo medio mensal</th>
              <th>Observacoes</th>
              <th>Atualizacao</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($parameters as $parameter): ?>
              <tr>
                <td><?= e((string) ($parameter['organ_name'] ?? '-')) ?></td>
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
<?php endif; ?>

<?php if ($canSimulate): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Simulador de contratacao</h3>
        <p class="muted">Calculo de impacto no ano corrente e no ano seguinte.</p>
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
        <p class="muted"><?= e((string) ($simulationResult['scenario_name'] ?? 'Simulacao')) ?> · Ano <?= e((string) ($simulationResult['year'] ?? $year)) ?></p>
      </div>
      <span class="badge <?= e($riskBadgeClass($simRisk)) ?>">Risco <?= e($riskLabel($simRisk)) ?></span>
    </div>

    <div class="grid-kpi">
      <article class="card kpi-card">
        <p class="kpi-label">Quantidade</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['quantity'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Custo medio mensal</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['avg_monthly_cost'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Meses restantes no ano</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['months_remaining'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Impacto ano corrente</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['cost_current_year'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Impacto ano seguinte</p>
        <p class="kpi-value"><?= e($formatMoney((float) ($simulationResult['cost_next_year'] ?? 0))) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Capacidade maxima (antes)</p>
        <p class="kpi-value"><?= e((string) (int) ($simulationResult['max_capacity_before'] ?? 0)) ?></p>
      </article>
    </div>

    <p class="muted">
      Disponivel antes: <?= e($formatMoney((float) ($simulationResult['available_before'] ?? 0))) ?> ·
      Saldo apos simulacao: <strong class="<?= (float) ($simulationResult['remaining_after_current_year'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney((float) ($simulationResult['remaining_after_current_year'] ?? 0))) ?></strong>
      · Fonte do custo medio: <?= e((string) ($simulationResult['avg_source'] ?? 'informado')) ?>
    </p>
  </div>
<?php endif; ?>

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
