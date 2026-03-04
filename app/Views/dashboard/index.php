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
    ],
    'status_distribution' => [],
    'recent_timeline' => [],
    'recommendation' => [
        'title' => 'Sem recomendacao',
        'description' => 'Dados insuficientes para sugerir proxima acao.',
        'label' => 'Abrir pessoas',
        'path' => '/people',
    ],
    'generated_at' => '',
];

$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$statusDistribution = is_array($dashboard['status_distribution'] ?? null) ? $dashboard['status_distribution'] : [];
$recentTimeline = is_array($dashboard['recent_timeline'] ?? null) ? $dashboard['recent_timeline'] : [];
$recommendation = is_array($dashboard['recommendation'] ?? null) ? $dashboard['recommendation'] : [];
$generatedAt = (string) ($dashboard['generated_at'] ?? '');

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
    <?php if ($generatedAt !== ''): ?>
      <span class="muted">Atualizado em <?= e($formatDateTime($generatedAt)) ?></span>
    <?php endif; ?>
  </div>
  <p><?= e((string) ($recommendation['description'] ?? 'Sem recomendação no momento.')) ?></p>
  <?php if (!empty($recommendation['path'])): ?>
    <a class="btn btn-outline" href="<?= e(url((string) $recommendation['path'])) ?>"><?= e((string) ($recommendation['label'] ?? 'Abrir')) ?></a>
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
