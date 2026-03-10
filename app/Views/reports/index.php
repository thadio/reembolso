<?php

declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [];
$operational = is_array($operational ?? null) ? $operational : [];
$financial = is_array($financial ?? null) ? $financial : [];
$organs = is_array($organs ?? null) ? $organs : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$severityOptions = is_array($severityOptions ?? null) ? $severityOptions : [];
$severityLabel = is_callable($severityLabel ?? null)
    ? $severityLabel
    : static fn (string $severity): string => ucfirst($severity);

$operationalSummary = is_array($operational['summary'] ?? null) ? $operational['summary'] : [];
$operationalBottlenecks = is_array($operational['bottlenecks'] ?? null) ? $operational['bottlenecks'] : [];
$operationalItems = is_array($operational['items'] ?? null) ? $operational['items'] : [];
$pagination = is_array($operational['pagination'] ?? null) ? $operational['pagination'] : ['total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];

$financialSummary = is_array($financial['summary'] ?? null) ? $financial['summary'] : [];
$financialMonths = is_array($financial['months'] ?? null) ? $financial['months'] : [];
$financialStatusPanel = is_array($financial['status_panel'] ?? null) ? $financial['status_panel'] : [];
$financialStatusSummary = is_array($financialStatusPanel['summary'] ?? null) ? $financialStatusPanel['summary'] : [];
$financialStatusMonths = is_array($financialStatusPanel['months'] ?? null) ? $financialStatusPanel['months'] : [];

$year = (int) ($filters['year'] ?? date('Y'));
$monthFrom = (int) ($filters['month_from'] ?? 1);
$monthTo = (int) ($filters['month_to'] ?? 12);
$perPage = (int) ($filters['per_page'] ?? 20);
$sort = (string) ($filters['sort'] ?? 'days_in_status');
$dir = (string) ($filters['dir'] ?? 'desc');

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatPercent = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return number_format($numeric, 2, ',', '.') . '%';
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$levelBadgeClass = static function (string $level): string {
    return match ($level) {
        'vencido' => 'badge-danger',
        'em_risco' => 'badge-warning',
        'no_prazo' => 'badge-success',
        default => 'badge-neutral',
    };
};

$baseParams = [
    'q' => (string) ($filters['q'] ?? ''),
    'organ_id' => (string) ($filters['organ_id'] ?? '0'),
    'status_code' => (string) ($filters['status_code'] ?? ''),
    'severity' => (string) ($filters['severity'] ?? ''),
    'year' => (string) $year,
    'month_from' => (string) $monthFrom,
    'month_to' => (string) $monthTo,
    'sort' => $sort,
    'dir' => $dir,
    'per_page' => (string) $perPage,
];

$buildUrl = static function (array $replace = []) use ($baseParams): string {
    $params = array_merge($baseParams, array_map(static fn ($value): string => (string) $value, $replace));

    return url('/reports?' . http_build_query($params));
};

$buildExportUrl = static function (string $path) use ($baseParams): string {
    $params = $baseParams;
    unset($params['per_page']);

    return url($path . '?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Consolidado operacional (SLA, gargalos, tempos medios) e financeiro (previsto x efetivo + painel completo de status abertos/vencidos/pagos/conciliados).</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e($buildExportUrl('/reports/export/csv')) ?>">Exportar CSV</a>
      <a class="btn btn-primary" href="<?= e($buildExportUrl('/reports/export/pdf')) ?>">Exportar PDF</a>
      <a class="btn btn-outline" href="<?= e($buildExportUrl('/reports/export/zip')) ?>">Pacote ZIP</a>
      <a class="btn btn-outline" href="<?= e($buildExportUrl('/reports/export/audit-zip')) ?>">Pacote Auditoria CGU/TCU</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/reports')) ?>" class="filters-row filters-reports">
    <div class="field">
      <label for="q">Busca</label>
      <input id="q" name="q" type="text" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Pessoa, orgao, SEI ou CPF">
    </div>

    <div class="field">
      <label for="organ_id">Orgao</label>
      <select id="organ_id" name="organ_id">
        <option value="0">Todos os orgaos</option>
        <?php foreach ($organs as $organ): ?>
          <?php $organId = (int) ($organ['id'] ?? 0); ?>
          <option value="<?= e((string) $organId) ?>" <?= (int) ($filters['organ_id'] ?? 0) === $organId ? 'selected' : '' ?>>
            <?= e((string) ($organ['name'] ?? 'Orgao')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="status_code">Etapa</label>
      <select id="status_code" name="status_code">
        <option value="">Todas as etapas</option>
        <?php foreach ($statusOptions as $status): ?>
          <?php $statusCode = (string) ($status['code'] ?? ''); ?>
          <option value="<?= e($statusCode) ?>" <?= (string) ($filters['status_code'] ?? '') === $statusCode ? 'selected' : '' ?>>
            <?= e((string) ($status['label'] ?? $statusCode)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="severity">Nivel SLA</label>
      <select id="severity" name="severity">
        <?php foreach ($severityOptions as $option): ?>
          <?php
            $optionValue = (string) ($option['value'] ?? '');
            $optionLabel = (string) ($option['label'] ?? $optionValue);
          ?>
          <option value="<?= e($optionValue) ?>" <?= (string) ($filters['severity'] ?? '') === $optionValue ? 'selected' : '' ?>>
            <?= e($optionLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="year">Ano</label>
      <input id="year" name="year" type="number" min="2000" max="2100" value="<?= e((string) $year) ?>">
    </div>

    <div class="field">
      <label for="month_from">Mes inicial</label>
      <select id="month_from" name="month_from">
        <?php for ($month = 1; $month <= 12; $month++): ?>
          <option value="<?= e((string) $month) ?>" <?= $monthFrom === $month ? 'selected' : '' ?>><?= e((string) $month) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="field">
      <label for="month_to">Mes final</label>
      <select id="month_to" name="month_to">
        <?php for ($month = 1; $month <= 12; $month++): ?>
          <option value="<?= e((string) $month) ?>" <?= $monthTo === $month ? 'selected' : '' ?>><?= e((string) $month) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="field">
      <label for="sort">Ordenar por</label>
      <select id="sort" name="sort">
        <option value="days_in_status" <?= $sort === 'days_in_status' ? 'selected' : '' ?>>Dias em etapa</option>
        <option value="status_order" <?= $sort === 'status_order' ? 'selected' : '' ?>>Etapa</option>
        <option value="person_name" <?= $sort === 'person_name' ? 'selected' : '' ?>>Pessoa</option>
        <option value="organ_name" <?= $sort === 'organ_name' ? 'selected' : '' ?>>Orgao</option>
        <option value="sla_level" <?= $sort === 'sla_level' ? 'selected' : '' ?>>Nivel SLA</option>
        <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Atualizacao</option>
      </select>
    </div>

    <div class="field">
      <label for="dir">Direcao</label>
      <select id="dir" name="dir">
        <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
        <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
      </select>
    </div>

    <div class="field">
      <label for="per_page">Itens por pagina</label>
      <select id="per_page" name="per_page">
        <?php foreach ([10, 20, 30, 50, 100] as $size): ?>
          <option value="<?= e((string) $size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field field-actions">
      <label>&nbsp;</label>
      <div class="actions-inline">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="<?= e(url('/reports')) ?>" class="btn btn-outline">Limpar</a>
      </div>
    </div>
  </form>
</div>

<div class="grid-kpi reports-kpi-grid">
  <article class="card kpi-card">
    <p class="kpi-label">Total monitorado</p>
    <p class="kpi-value"><?= e((string) (int) ($operationalSummary['total'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">No prazo</p>
    <p class="kpi-value text-success"><?= e((string) (int) ($operationalSummary['no_prazo'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Em risco</p>
    <p class="kpi-value"><?= e((string) (int) ($operationalSummary['em_risco'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Vencido</p>
    <p class="kpi-value text-danger"><?= e((string) (int) ($operationalSummary['vencido'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Tempo medio em etapa</p>
    <p class="kpi-value"><?= e(number_format((float) ($operationalSummary['avg_days_in_status'] ?? 0), 2, ',', '.')) ?> dia(s)</p>
  </article>
</div>

<div class="card">
  <h3>Gargalos operacionais</h3>
  <?php if ($operationalBottlenecks === []): ?>
    <div class="empty-state">
      <p>Sem gargalos para o recorte atual.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Etapa</th>
            <th>Casos</th>
            <th>Media (dias)</th>
            <th>Maximo (dias)</th>
            <th>Em risco</th>
            <th>Vencido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($operationalBottlenecks as $bottleneck): ?>
            <tr>
              <td>
                <strong><?= e((string) ($bottleneck['status_label'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string) ($bottleneck['status_code'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) (int) ($bottleneck['cases_count'] ?? 0)) ?></td>
              <td><?= e(number_format((float) ($bottleneck['avg_days_in_status'] ?? 0), 2, ',', '.')) ?></td>
              <td><?= e((string) (int) ($bottleneck['max_days_in_status'] ?? 0)) ?></td>
              <td><?= e((string) (int) ($bottleneck['risco_count'] ?? 0)) ?></td>
              <td><?= e((string) (int) ($bottleneck['vencido_count'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Detalhe operacional</h3>
  <?php if ($operationalItems === []): ?>
    <div class="empty-state">
      <p>Nenhum caso encontrado para os filtros.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'person_name', 'dir' => $nextDir('person_name'), 'page' => 1])) ?>">Pessoa</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'organ_name', 'dir' => $nextDir('organ_name'), 'page' => 1])) ?>">Orgao</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'status_order', 'dir' => $nextDir('status_order'), 'page' => 1])) ?>">Etapa</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'days_in_status', 'dir' => $nextDir('days_in_status'), 'page' => 1])) ?>">Dias</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'sla_level', 'dir' => $nextDir('sla_level'), 'page' => 1])) ?>">Nivel SLA</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado</a></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($operationalItems as $item): ?>
            <?php $level = (string) ($item['sla_level'] ?? ''); ?>
            <tr>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($item['person_id'] ?? 0))) ?>"><?= e((string) ($item['person_name'] ?? '-')) ?></a>
                <div class="muted">SEI: <?= e((string) ($item['sei_process_number'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) ($item['organ_name'] ?? '-')) ?></td>
              <td>
                <strong><?= e((string) ($item['status_label'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string) ($item['status_code'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) (int) ($item['days_in_status'] ?? 0)) ?></td>
              <td><span class="badge <?= e($levelBadgeClass($level)) ?>"><?= e($severityLabel($level)) ?></span></td>
              <td><?= e($formatDateTime((string) ($item['status_changed_at'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) (int) ($pagination['total'] ?? 0)) ?> registro(s)</span>
      <div class="pagination-links">
        <?php if ((int) ($pagination['page'] ?? 1) > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= e((string) (int) ($pagination['page'] ?? 1)) ?> de <?= e((string) (int) ($pagination['pages'] ?? 1)) ?></span>
        <?php if ((int) ($pagination['page'] ?? 1) < (int) ($pagination['pages'] ?? 1)): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="grid-kpi reports-kpi-grid">
  <article class="card kpi-card">
    <p class="kpi-label">Previsto</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($financialSummary['forecast_total'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Efetivo</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($financialSummary['effective_total'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Pago</p>
    <p class="kpi-value text-success"><?= e($formatMoney((float) ($financialSummary['paid_total'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">A pagar</p>
    <p class="kpi-value <?= (float) ($financialSummary['payable_total'] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= e($formatMoney((float) ($financialSummary['payable_total'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Desvio previsto x efetivo</p>
    <p class="kpi-value <?= (float) ($financialSummary['variance_forecast_effective'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney((float) ($financialSummary['variance_forecast_effective'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Aderencia / cobertura</p>
    <p class="kpi-value"><?= e($formatPercent((float) ($financialSummary['adherence_percent'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Cobertura de pagamento: <?= e($formatPercent((float) ($financialSummary['payment_coverage_percent'] ?? 0))) ?></p>
  </article>
</div>

<div class="card">
  <h3>Painel financeiro por status</h3>
  <div class="grid-kpi reports-kpi-grid">
    <article class="card kpi-card">
      <p class="kpi-label">Abertos</p>
      <p class="kpi-value"><?= e((string) (int) ($financialStatusSummary['open_count'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note"><?= e($formatMoney((float) ($financialStatusSummary['open_amount'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Vencidos</p>
      <p class="kpi-value text-danger"><?= e((string) (int) ($financialStatusSummary['overdue_count'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note"><?= e($formatMoney((float) ($financialStatusSummary['overdue_amount'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Pagos</p>
      <p class="kpi-value text-success"><?= e((string) (int) ($financialStatusSummary['paid_count'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note"><?= e($formatMoney((float) ($financialStatusSummary['paid_amount'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Conciliados</p>
      <p class="kpi-value"><?= e((string) (int) ($financialStatusSummary['reconciled_count'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note"><?= e($formatMoney((float) ($financialStatusSummary['reconciled_amount'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Cobertura de conciliacao</p>
      <p class="kpi-value"><?= e($formatPercent((float) ($financialStatusSummary['reconciled_coverage_percent'] ?? 0))) ?></p>
      <p class="dashboard-kpi-note">Base em itens financeiros monitorados no periodo</p>
    </article>
  </div>
</div>

<div class="card">
  <h3>Status financeiro mensal</h3>
  <?php if ($financialStatusMonths === []): ?>
    <div class="empty-state">
      <p>Sem dados de status financeiro para o recorte selecionado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mes</th>
            <th>Abertos (qtd/valor)</th>
            <th>Vencidos (qtd/valor)</th>
            <th>Pagos (qtd/valor)</th>
            <th>Conciliados (qtd/valor)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($financialStatusMonths as $month): ?>
            <tr>
              <td><?= e((string) ($month['month_label'] ?? '-')) ?></td>
              <td><?= e((string) (int) ($month['open_count'] ?? 0)) ?> / <?= e($formatMoney((float) ($month['open_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($month['overdue_count'] ?? 0)) ?> / <?= e($formatMoney((float) ($month['overdue_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($month['paid_count'] ?? 0)) ?> / <?= e($formatMoney((float) ($month['paid_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($month['reconciled_count'] ?? 0)) ?> / <?= e($formatMoney((float) ($month['reconciled_amount'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Financeiro mensal</h3>
  <?php if ($financialMonths === []): ?>
    <div class="empty-state">
      <p>Sem movimentacao financeira para o recorte selecionado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Mes</th>
            <th>Previsto</th>
            <th>Efetivo</th>
            <th>Pago</th>
            <th>A pagar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($financialMonths as $month): ?>
            <tr>
              <td><?= e((string) ($month['month_label'] ?? '-')) ?></td>
              <td><?= e($formatMoney((float) ($month['forecast_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($month['effective_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($month['paid_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($month['payable_amount'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
