<?php

declare(strict_types=1);

$panel = is_array($panel ?? null) ? $panel : [];

$status = (string) ($panel['status'] ?? 'unknown');
$available = ($panel['available'] ?? false) === true;
$totals = is_array($panel['totals'] ?? null) ? $panel['totals'] : [];
$checks = is_array($panel['checks'] ?? null) ? $panel['checks'] : [];
$history = is_array($panel['history'] ?? null) ? $panel['history'] : [];
$source = is_array($panel['source'] ?? null) ? $panel['source'] : [];
$kpiSnapshot = is_array($panel['kpi_snapshot'] ?? null) ? $panel['kpi_snapshot'] : [];
$logSeverity = is_array($panel['log_severity'] ?? null) ? $panel['log_severity'] : [];
$recurringErrors = is_array($panel['recurring_errors'] ?? null) ? $panel['recurring_errors'] : [];
$appLog = is_array($panel['app_log'] ?? null) ? $panel['app_log'] : [];
$commands = is_array($panel['commands'] ?? null) ? $panel['commands'] : [];

$statusLabel = static function (string $value): string {
    return match ($value) {
        'ok' => 'OK',
        'warn' => 'Atencao',
        'fail' => 'Falha',
        default => 'Sem snapshot',
    };
};

$statusBadgeClass = static function (string $value): string {
    return match ($value) {
        'ok' => 'badge-success',
        'warn' => 'badge-warning',
        'fail' => 'badge-danger',
        default => 'badge-neutral',
    };
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unitIndex = 0;

    while ($size >= 1024.0 && $unitIndex < count($units) - 1) {
        $size /= 1024.0;
        $unitIndex++;
    }

    return number_format($size, 2, ',', '.') . ' ' . $units[$unitIndex];
};

$metricsSummary = static function (array $metrics): string {
    $selectedKeys = [
        'entries_in_window',
        'warning',
        'error',
        'recurring_groups',
        'recurring_error_groups',
        'recurring_error_entries',
        'snapshot_age_minutes',
        'max_age_minutes',
        'window_hours',
    ];

    $parts = [];
    foreach ($selectedKeys as $key) {
        if (!array_key_exists($key, $metrics)) {
            continue;
        }

        $parts[] = $key . ': ' . (string) $metrics[$key];
    }

    return implode(' | ', $parts);
};
?>

<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Painel estruturado com saude tecnica, severidade de logs, recorrencias e frescor de snapshot KPI.</p>
    </div>
  </div>

  <?php if (!$available): ?>
    <div class="empty-state">
      <p>Snapshot do painel tecnico ainda nao encontrado. Gere um snapshot para habilitar a visualizacao.</p>
    </div>
  <?php endif; ?>
</div>

<div class="grid-kpi reports-kpi-grid">
  <article class="card kpi-card">
    <p class="kpi-label">Status geral</p>
    <p class="kpi-value"><span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span></p>
    <p class="dashboard-kpi-note">Gerado em <?= e($formatDateTime((string) ($panel['generated_at'] ?? ''))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Checks</p>
    <p class="kpi-value"><?= e((string) (int) ($totals['checks_total'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">
      OK <?= e((string) (int) ($totals['ok'] ?? 0)) ?> · Warn <?= e((string) (int) ($totals['warn'] ?? 0)) ?> · Fail <?= e((string) (int) ($totals['fail'] ?? 0)) ?>
    </p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Recorrencias</p>
    <p class="kpi-value"><?= e((string) (int) ($recurringErrors['groups'] ?? 0)) ?></p>
    <p class="dashboard-kpi-note">Grupos de erro: <?= e((string) (int) ($recurringErrors['error_groups'] ?? 0)) ?> · Entradas: <?= e((string) (int) ($recurringErrors['error_entries'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Snapshot KPI</p>
    <?php $kpiAge = $kpiSnapshot['age_minutes'] ?? null; ?>
    <p class="kpi-value"><?= $kpiAge === null ? '-' : e((string) (int) $kpiAge . ' min') ?></p>
    <p class="dashboard-kpi-note">
      Limite <?= e((string) (int) ($kpiSnapshot['max_age_minutes'] ?? 0)) ?> min ·
      <?= (($kpiSnapshot['is_stale'] ?? true) === true) ? 'desatualizado' : 'fresco' ?>
    </p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Log da aplicacao</p>
    <p class="kpi-value"><?= e($formatBytes((int) ($appLog['size_bytes'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Atualizado em <?= e($formatDateTime((string) ($appLog['updated_at'] ?? ''))) ?></p>
  </article>
</div>

<div class="card">
  <h3>Checks tecnicos</h3>
  <?php if ($checks === []): ?>
    <div class="empty-state">
      <p>Sem checks carregados no snapshot atual.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Check</th>
            <th>Status</th>
            <th>Mensagem</th>
            <th>Metricas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($checks as $check): ?>
            <?php
              $checkName = (string) ($check['label'] ?? $check['name'] ?? '-');
              $checkStatus = (string) ($check['status'] ?? 'unknown');
              $checkMessage = (string) ($check['message'] ?? '');
              $checkMetrics = is_array($check['metrics'] ?? null) ? $check['metrics'] : [];
            ?>
            <tr>
              <td><?= e($checkName) ?></td>
              <td><span class="badge <?= e($statusBadgeClass($checkStatus)) ?>"><?= e($statusLabel($checkStatus)) ?></span></td>
              <td><?= e($checkMessage !== '' ? $checkMessage : '-') ?></td>
              <td class="muted"><?= e($metricsSummary($checkMetrics)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Historico recente do painel tecnico</h3>
  <?php if ($history === []): ?>
    <div class="empty-state">
      <p>Nenhum historico de snapshot encontrado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Gerado em</th>
            <th>Status</th>
            <th>Checks</th>
            <th>OK / Warn / Fail</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $item): ?>
            <?php $itemStatus = (string) ($item['status'] ?? 'unknown'); ?>
            <tr>
              <td><?= e($formatDateTime((string) ($item['generated_at'] ?? ''))) ?></td>
              <td><span class="badge <?= e($statusBadgeClass($itemStatus)) ?>"><?= e($statusLabel($itemStatus)) ?></span></td>
              <td><?= e((string) (int) ($item['checks_total'] ?? 0)) ?></td>
              <td><?= e((string) (int) ($item['ok'] ?? 0)) ?> / <?= e((string) (int) ($item['warn'] ?? 0)) ?> / <?= e((string) (int) ($item['fail'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Severidade de logs (ultimo snapshot)</h3>
  <?php if (($logSeverity['exists'] ?? false) !== true): ?>
    <div class="empty-state">
      <p>Sem snapshot de severidade de logs para exibir.</p>
    </div>
  <?php else: ?>
    <?php $logTotals = is_array($logSeverity['totals'] ?? null) ? $logSeverity['totals'] : []; ?>
    <div class="grid-kpi reports-kpi-grid">
      <article class="card kpi-card">
        <p class="kpi-label">Janela</p>
        <p class="kpi-value"><?= e((string) (int) ($logSeverity['window_hours'] ?? 0)) ?>h</p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Entradas</p>
        <p class="kpi-value"><?= e((string) (int) ($logTotals['entries_in_window'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Warnings</p>
        <p class="kpi-value"><?= e((string) (int) ($logTotals['warning'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Errors</p>
        <p class="kpi-value text-danger"><?= e((string) (int) ($logTotals['error'] ?? 0)) ?></p>
      </article>
    </div>

    <?php $topMessages = is_array($logSeverity['top_messages'] ?? null) ? $logSeverity['top_messages'] : []; ?>
    <?php if ($topMessages !== []): ?>
      <div class="table-wrap sp-top-lg">
        <table>
          <thead>
            <tr>
              <th>Nivel</th>
              <th>Mensagem</th>
              <th>Ocorrencias</th>
              <th>Ultima ocorrencia</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topMessages as $message): ?>
              <tr>
                <td><?= e((string) ($message['level'] ?? '-')) ?></td>
                <td><?= e((string) ($message['message'] ?? '-')) ?></td>
                <td><?= e((string) (int) ($message['count'] ?? 0)) ?></td>
                <td><?= e($formatDateTime((string) ($message['last_seen'] ?? ''))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Fonte e comandos operacionais</h3>
  <p class="muted">Snapshot do painel: <?= e((string) ($source['file'] ?? '-')) ?></p>
  <p class="muted">Snapshot KPI: <?= e((string) ($kpiSnapshot['file'] ?? '-')) ?></p>
  <p class="muted">Snapshot log severity: <?= e((string) ($logSeverity['file'] ?? '-')) ?></p>
  <p class="muted">Log da aplicacao: <?= e((string) ($appLog['path'] ?? '-')) ?></p>

  <?php if ($commands !== []): ?>
    <div class="table-wrap sp-top-lg">
      <table>
        <thead>
          <tr>
            <th>Comando recomendado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($commands as $command): ?>
            <tr>
              <td><code><?= e((string) $command) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
