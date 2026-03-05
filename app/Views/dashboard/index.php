<?php

declare(strict_types=1);

$dashboard = $dashboard ?? [
    'summary' => [
        'total_people' => 0,
        'active_people' => 0,
        'in_progress_people' => 0,
        'total_organs' => 0,
        'people_with_documents' => 0,
        'people_with_active_cost_plan' => 0,
        'people_without_documents' => 0,
        'people_without_cost_plan' => 0,
        'documents_coverage_percent' => 0,
        'cost_plan_coverage_percent' => 0,
        'timeline_last_30_days' => 0,
        'audit_last_30_days' => 0,
        'expected_reimbursement_current_month' => 0,
        'actual_reimbursement_posted_current_month' => 0,
        'actual_reimbursement_paid_current_month' => 0,
        'reconciliation_deviation_posted_current' => 0,
        'reconciliation_deviation_paid_current' => 0,
        'total_cdos' => 0,
        'open_cdos' => 0,
        'cdo_total_amount' => 0,
        'cdo_allocated_amount' => 0,
        'cdo_available_amount' => 0,
    ],
    'status_distribution' => [],
    'recent_timeline' => [],
    'executive_panel' => [
        'summary' => [
            'total_organs_monitored' => 0,
            'organs_with_critical_cases' => 0,
            'total_cases_monitored' => 0,
            'critical_cases' => 0,
            'overdue_cases' => 0,
            'risk_cases' => 0,
            'critical_share_percent' => 0,
        ],
        'bottlenecks' => [],
        'organ_ranking' => [],
    ],
    'recommendation' => [
        'title' => 'Sem recomendacao',
        'description' => 'Dados insuficientes para sugerir proxima acao.',
        'label' => 'Abrir pessoas',
        'path' => '/people',
    ],
    'generated_at' => '',
    'data_source' => 'live',
];

$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$statusDistribution = is_array($dashboard['status_distribution'] ?? null) ? $dashboard['status_distribution'] : [];
$recentTimeline = is_array($dashboard['recent_timeline'] ?? null) ? $dashboard['recent_timeline'] : [];
$executivePanel = is_array($dashboard['executive_panel'] ?? null) ? $dashboard['executive_panel'] : [];
$executiveSummary = is_array($executivePanel['summary'] ?? null) ? $executivePanel['summary'] : [];
$executiveBottlenecks = is_array($executivePanel['bottlenecks'] ?? null) ? $executivePanel['bottlenecks'] : [];
$executiveOrganRanking = is_array($executivePanel['organ_ranking'] ?? null) ? $executivePanel['organ_ranking'] : [];
$recommendation = is_array($dashboard['recommendation'] ?? null) ? $dashboard['recommendation'] : [];
$generatedAt = (string) ($dashboard['generated_at'] ?? '');
$dataSource = (string) ($dashboard['data_source'] ?? 'live');

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatPercent = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return number_format($numeric, 1, ',', '.') . '%';
};

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatSignedMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    $prefix = $numeric > 0 ? '+' : '';

    return $prefix . 'R$ ' . number_format($numeric, 2, ',', '.');
};

$deviationClass = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    if ($numeric > 0.009) {
        return 'text-danger';
    }

    if ($numeric < -0.009) {
        return 'text-success';
    }

    return 'text-muted';
};

$statusBadgeClass = static function (string $status): string {
    return $status === 'ativo' ? 'badge-success' : 'badge-neutral';
};

$statusLabel = static function (string $status): string {
    if (trim($status) === '') {
        return 'N/I';
    }

    return match ($status) {
        'interessado' => 'Interessado',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Ofício órgão',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$eventTypeLabel = static function (string $value): string {
    $value = str_replace(['pipeline.', '_', '.'], ['Pipeline ', ' ', ' • '], $value);

    return ucfirst(trim($value));
};

$slaLevelLabel = static function (string $value): string {
    return match ($value) {
        'vencido' => 'Vencido',
        'em_risco' => 'Em risco',
        default => 'No prazo',
    };
};

$slaLevelBadgeClass = static function (string $value): string {
    return match ($value) {
        'vencido' => 'badge-danger',
        'em_risco' => 'badge-warning',
        default => 'badge-success',
    };
};
?>
<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Pessoas no pipeline</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['total_people'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Em andamento: <?= e((string) (int) ($summary['in_progress_people'] ?? 0)) ?> · Ativas: <?= e((string) (int) ($summary['active_people'] ?? 0)) ?></p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Órgãos cadastrados</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['total_organs'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Base ativa de órgãos vinculáveis</p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Cobertura documental</p>
    <p class="kpi-value"><?= e($formatPercent((float) ($summary['documents_coverage_percent'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Com dossiê: <?= e((string) (int) ($summary['people_with_documents'] ?? 0)) ?> · Sem: <?= e((string) (int) ($summary['people_without_documents'] ?? 0)) ?></p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Cobertura de custos</p>
    <p class="kpi-value"><?= e($formatPercent((float) ($summary['cost_plan_coverage_percent'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Com versão ativa: <?= e((string) (int) ($summary['people_with_active_cost_plan'] ?? 0)) ?> · Sem: <?= e((string) (int) ($summary['people_without_cost_plan'] ?? 0)) ?></p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Desvio previsto x real (mês)</p>
    <p class="kpi-value <?= e($deviationClass((float) ($summary['reconciliation_deviation_posted_current'] ?? 0))) ?>">
      <?= e($formatSignedMoney((float) ($summary['reconciliation_deviation_posted_current'] ?? 0))) ?>
    </p>
    <p class="dashboard-kpi-note">
      Previsto: <?= e($formatMoney((float) ($summary['expected_reimbursement_current_month'] ?? 0))) ?> |
      Real lançado: <?= e($formatMoney((float) ($summary['actual_reimbursement_posted_current_month'] ?? 0))) ?> |
      Real pago: <?= e($formatMoney((float) ($summary['actual_reimbursement_paid_current_month'] ?? 0))) ?>
    </p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Cobertura CDO</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['total_cdos'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">
      Em aberto: <?= e((string) (int) ($summary['open_cdos'] ?? 0)) ?> |
      Total: <?= e($formatMoney((float) ($summary['cdo_total_amount'] ?? 0))) ?> |
      Saldo: <?= e($formatMoney((float) ($summary['cdo_available_amount'] ?? 0))) ?>
    </p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Eventos na timeline (30 dias)</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['timeline_last_30_days'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Movimentações recentes registradas</p>
  </article>

  <article class="card kpi-card">
    <p class="kpi-label">Saúde do sistema</p>
    <p class="kpi-value"><a href="<?= e(url('/health')) ?>" target="_blank" rel="noopener">/health</a></p>
    <p class="dashboard-kpi-note">Auditoria (30 dias): <?= e((string) (int) ($summary['audit_last_30_days'] ?? 0)) ?> registros</p>
  </article>
</div>

<div class="card">
  <div class="header-row">
    <h2><?= e((string) ($recommendation['title'] ?? 'Próxima ação')) ?></h2>
    <span class="muted">
      <?php if ($generatedAt !== ''): ?>
        Atualizado em <?= e($formatDateTime($generatedAt)) ?>
      <?php endif; ?>
      <?php if ($dataSource === 'snapshot'): ?>
        · Fonte: snapshot KPI
      <?php else: ?>
        · Fonte: calculo ao vivo
      <?php endif; ?>
    </span>
  </div>
  <p><?= e((string) ($recommendation['description'] ?? 'Sem recomendação no momento.')) ?></p>
  <?php if (!empty($recommendation['path'])): ?>
    <a class="btn btn-outline" href="<?= e(url((string) $recommendation['path'])) ?>"><?= e((string) ($recommendation['label'] ?? 'Abrir')) ?></a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h2>Painel executivo</h2>
      <p class="muted">Gargalos operacionais e ranking de órgãos por criticidade de SLA.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/reports')) ?>">Abrir relatórios premium</a>
  </div>

  <div class="grid-kpi reports-kpi-grid">
    <article class="card kpi-card">
      <p class="kpi-label">Órgãos monitorados</p>
      <p class="kpi-value"><?= e((string) (int) ($executiveSummary['total_organs_monitored'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note">Com criticidade: <?= e((string) (int) ($executiveSummary['organs_with_critical_cases'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Casos monitorados</p>
      <p class="kpi-value"><?= e((string) (int) ($executiveSummary['total_cases_monitored'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note">Criticidade total: <?= e($formatPercent((float) ($executiveSummary['critical_share_percent'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Em risco</p>
      <p class="kpi-value"><?= e((string) (int) ($executiveSummary['risk_cases'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note">SLA com necessidade de ação preventiva</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Vencidos</p>
      <p class="kpi-value text-danger"><?= e((string) (int) ($executiveSummary['overdue_cases'] ?? 0)) ?></p>
      <p class="dashboard-kpi-note">Prioridade crítica de regularização</p>
    </article>
  </div>

  <?php if ($executiveBottlenecks === [] && $executiveOrganRanking === []): ?>
    <div class="empty-state">
      <p class="muted">Sem dados executivos suficientes para montagem de gargalos e ranking.</p>
    </div>
  <?php else: ?>
    <h3>Gargalos por etapa</h3>
    <?php if ($executiveBottlenecks === []): ?>
      <div class="empty-state">
        <p class="muted">Sem gargalos operacionais identificados no momento.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Etapa</th>
              <th>Casos</th>
              <th>Órgãos impactados</th>
              <th>Média (dias)</th>
              <th>Em risco</th>
              <th>Vencido</th>
              <th>Criticidade</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($executiveBottlenecks as $row): ?>
              <?php
                $riskCount = (int) ($row['em_risco_count'] ?? 0);
                $overdueCount = (int) ($row['vencido_count'] ?? 0);
                $level = $overdueCount > 0 ? 'vencido' : ($riskCount > 0 ? 'em_risco' : 'no_prazo');
              ?>
              <tr>
                <td>
                  <strong><?= e((string) ($row['status_label'] ?? '-')) ?></strong>
                  <div class="muted"><?= e((string) ($row['status_code'] ?? '-')) ?></div>
                </td>
                <td><?= e((string) (int) ($row['cases_count'] ?? 0)) ?></td>
                <td><?= e((string) (int) ($row['impacted_organs_count'] ?? 0)) ?></td>
                <td><?= e(number_format((float) ($row['avg_days_in_status'] ?? 0), 2, ',', '.')) ?></td>
                <td><?= e((string) $riskCount) ?></td>
                <td><?= e((string) $overdueCount) ?></td>
                <td>
                  <span class="badge <?= e($slaLevelBadgeClass($level)) ?>"><?= e($slaLevelLabel($level)) ?></span>
                  <span class="muted"><?= e($formatPercent((float) ($row['critical_share_percent'] ?? 0))) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h3>Ranking de órgãos</h3>
    <?php if ($executiveOrganRanking === []): ?>
      <div class="empty-state">
        <p class="muted">Sem órgãos suficientes para ranking no recorte atual.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Órgão</th>
              <th>Casos</th>
              <th>Em risco</th>
              <th>Vencido</th>
              <th>Criticidade</th>
              <th>Média (dias)</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($executiveOrganRanking as $index => $row): ?>
              <?php
                $riskCount = (int) ($row['em_risco_count'] ?? 0);
                $overdueCount = (int) ($row['vencido_count'] ?? 0);
                $level = $overdueCount > 0 ? 'vencido' : ($riskCount > 0 ? 'em_risco' : 'no_prazo');
                $organId = (int) ($row['organ_id'] ?? 0);
              ?>
              <tr>
                <td><?= e((string) ($index + 1)) ?></td>
                <td>
                  <?php if ($organId > 0): ?>
                    <a href="<?= e(url('/organs/show?id=' . $organId)) ?>"><?= e((string) ($row['organ_name'] ?? '-')) ?></a>
                  <?php else: ?>
                    <?= e((string) ($row['organ_name'] ?? '-')) ?>
                  <?php endif; ?>
                </td>
                <td><?= e((string) (int) ($row['cases_count'] ?? 0)) ?></td>
                <td><?= e((string) $riskCount) ?></td>
                <td><?= e((string) $overdueCount) ?></td>
                <td>
                  <span class="badge <?= e($slaLevelBadgeClass($level)) ?>"><?= e($slaLevelLabel($level)) ?></span>
                  <span class="muted"><?= e($formatPercent((float) ($row['critical_share_percent'] ?? 0))) ?></span>
                </td>
                <td><?= e(number_format((float) ($row['avg_days_in_status'] ?? 0), 2, ',', '.')) ?></td>
                <td><?= e(number_format((float) ($row['severity_score'] ?? 0), 1, ',', '.')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <h2>Distribuição do pipeline</h2>
    <span class="muted">Total considerado: <?= e((string) (int) ($summary['total_people'] ?? 0)) ?> pessoa(s)</span>
  </div>

  <?php if ($statusDistribution === []): ?>
    <div class="empty-state">
      <p class="muted">Sem estágios ativos de pipeline para exibição.</p>
    </div>
  <?php else: ?>
    <div class="dashboard-status-list">
      <?php foreach ($statusDistribution as $statusRow): ?>
        <?php
          $statusCode = (string) ($statusRow['code'] ?? '');
          $statusTotal = max(0, (int) ($statusRow['total'] ?? 0));
          $statusShare = max(0.0, min(100.0, (float) ($statusRow['share'] ?? 0.0)));
        ?>
        <article class="dashboard-status-row">
          <div class="dashboard-status-head">
            <strong><?= e((string) ($statusRow['label'] ?? $statusCode)) ?></strong>
            <span class="dashboard-status-meta">
              <span class="badge <?= e($statusBadgeClass($statusCode)) ?>"><?= e((string) $statusTotal) ?></span>
              <span class="muted"><?= e($formatPercent($statusShare)) ?></span>
            </span>
          </div>
          <div class="dashboard-status-bar" role="img" aria-label="Participação de <?= e((string) ($statusRow['label'] ?? $statusCode)) ?> no pipeline">
            <span class="dashboard-status-fill" style="width: <?= e(number_format($statusShare, 1, '.', '')) ?>%"></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <h2>Últimas movimentações</h2>
    <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Abrir Pessoas</a>
  </div>

  <?php if ($recentTimeline === []): ?>
    <div class="empty-state">
      <p class="muted">Ainda não há eventos recentes na timeline.</p>
    </div>
  <?php else: ?>
    <div class="timeline-list">
      <?php foreach ($recentTimeline as $item): ?>
        <?php
          $personId = (int) ($item['person_id'] ?? 0);
          $personStatus = (string) ($item['person_status'] ?? '');
        ?>
        <article class="timeline-item">
          <div class="timeline-item-header">
            <div>
              <p class="kpi-label"><?= e($formatDateTime((string) ($item['event_date'] ?? ''))) ?> · <?= e($eventTypeLabel((string) ($item['event_type'] ?? ''))) ?></p>
              <p class="dashboard-recent-title"><strong><?= e((string) ($item['title'] ?? '-')) ?></strong></p>
              <p class="dashboard-recent-meta">
                Pessoa:
                <?php if ($personId > 0): ?>
                  <a href="<?= e(url('/people/show?id=' . $personId)) ?>"><?= e((string) ($item['person_name'] ?? ('#' . $personId))) ?></a>
                <?php else: ?>
                  <?= e((string) ($item['person_name'] ?? '-')) ?>
                <?php endif; ?>
                · Responsável: <?= e((string) ($item['created_by_name'] ?? 'Sistema')) ?>
              </p>
            </div>
            <span class="badge <?= e($statusBadgeClass($personStatus)) ?>"><?= e($statusLabel($personStatus)) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
