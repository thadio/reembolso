<?php

declare(strict_types=1);

$items = $items ?? [];
$summary = $summary ?? [
    'total' => 0,
    'no_prazo' => 0,
    'em_risco' => 0,
    'vencido' => 0,
    'overdue_open' => 0,
    'overdue_in_progress' => 0,
    'overdue_resolved' => 0,
];
$filters = $filters ?? [
    'q' => '',
    'status_code' => '',
    'severity' => '',
    'control_status' => '',
    'sort' => 'status_order',
    'dir' => 'asc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$statusOptions = $statusOptions ?? [];
$severityOptions = $severityOptions ?? [];
$controlStatusOptions = $controlStatusOptions ?? [];
$controlOwnerOptions = $controlOwnerOptions ?? [];
$recentLogs = $recentLogs ?? [];
$severityLabel = is_callable($severityLabel ?? null)
    ? $severityLabel
    : static fn (string $severity): string => ucfirst($severity);

$sort = (string) ($filters['sort'] ?? 'status_order');
$dir = (string) ($filters['dir'] ?? 'asc');

$formatDate = static function (?string $value): string {
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

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status_code' => (string) ($filters['status_code'] ?? ''),
        'severity' => (string) ($filters['severity'] ?? ''),
        'control_status' => (string) ($filters['control_status'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'status_order'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($filters['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/sla-alerts?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};

$controlStatusLabel = static function (string $value): string {
    return match ($value) {
        'aberto' => 'Aberto',
        'em_tratamento' => 'Em tratamento',
        'resolvido' => 'Resolvido',
        default => 'N/A',
    };
};

$controlStatusBadge = static function (string $value): string {
    return match ($value) {
        'aberto' => 'badge-danger',
        'em_tratamento' => 'badge-warning',
        'resolvido' => 'badge-success',
        default => 'badge-neutral',
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Acompanhamento de etapas em risco e vencidas com regras configuraveis por status.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <div class="actions-inline">
        <a class="btn btn-outline" href="<?= e(url('/sla-alerts/rules')) ?>">Configurar regras</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid-kpi sla-kpi-grid">
    <article class="card kpi-card">
      <p class="kpi-label">Total monitorado</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['total'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">No prazo</p>
      <p class="kpi-value text-success"><?= e((string) (int) ($summary['no_prazo'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Em risco</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['em_risco'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Vencido</p>
      <p class="kpi-value text-danger"><?= e((string) (int) ($summary['vencido'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Atrasos abertos</p>
      <p class="kpi-value text-danger"><?= e((string) (int) ($summary['overdue_open'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Atrasos em tratamento</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['overdue_in_progress'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Atrasos resolvidos</p>
      <p class="kpi-value text-success"><?= e((string) (int) ($summary['overdue_resolved'] ?? 0)) ?></p>
    </article>
  </div>

  <form method="get" action="<?= e(url('/sla-alerts')) ?>" class="filters-row filters-sla">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Pessoa, orgao, SEI ou CPF">

    <select name="status_code">
      <option value="">Todos os status</option>
      <?php foreach ($statusOptions as $status): ?>
        <?php $code = (string) ($status['code'] ?? ''); ?>
        <option value="<?= e($code) ?>" <?= (string) ($filters['status_code'] ?? '') === $code ? 'selected' : '' ?>>
          <?= e((string) ($status['label'] ?? $code)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="severity">
      <?php foreach ($severityOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['severity'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="control_status">
      <option value="">Todos os controles de atraso</option>
      <?php foreach ($controlStatusOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['control_status'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort">
      <option value="status_order" <?= $sort === 'status_order' ? 'selected' : '' ?>>Ordenar por etapa</option>
      <option value="person_name" <?= $sort === 'person_name' ? 'selected' : '' ?>>Ordenar por pessoa</option>
      <option value="organ_name" <?= $sort === 'organ_name' ? 'selected' : '' ?>>Ordenar por orgao</option>
      <option value="days_in_status" <?= $sort === 'days_in_status' ? 'selected' : '' ?>>Ordenar por dias</option>
      <option value="sla_level" <?= $sort === 'sla_level' ? 'selected' : '' ?>>Ordenar por nivel SLA</option>
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([10, 20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 10) === $size ? 'selected' : '' ?>>
          <?= e((string) $size) ?>/pagina
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/sla-alerts')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/sla-alerts/dispatch-email')) ?>" class="filters-row filters-sla-dispatch">
      <?= csrf_field() ?>
      <select name="severity">
        <option value="all">Disparar para risco + vencido</option>
        <option value="em_risco">Disparar apenas em risco</option>
        <option value="vencido">Disparar apenas vencido</option>
      </select>
      <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmar disparo de notificacoes por email?');">Disparar emails</button>
      <span class="muted">Envio opcional via `mail()` do PHP, conforme ambiente do servidor.</span>
    </form>
  <?php endif; ?>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Nenhuma pendencia encontrada para os filtros selecionados.</p>
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
            <th>Regra</th>
            <th><a href="<?= e($buildUrl(['sort' => 'sla_level', 'dir' => $nextDir('sla_level'), 'page' => 1])) ?>">Nivel SLA</a></th>
            <th>Controle atraso</th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $level = (string) ($item['sla_level'] ?? 'no_prazo'); ?>
            <?php $controlStatus = (string) ($item['control_status'] ?? 'nao_aplicavel'); ?>
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
              <td>
                risco: <?= e((string) (int) ($item['warning_days'] ?? 0)) ?>d
                <br>
                vencido: <?= e((string) (int) ($item['overdue_days'] ?? 0)) ?>d
              </td>
              <td><span class="badge <?= e($levelBadgeClass($level)) ?>"><?= e($severityLabel($level)) ?></span></td>
              <td>
                <?php if ($level === 'vencido'): ?>
                  <span class="badge <?= e($controlStatusBadge($controlStatus)) ?>"><?= e($controlStatusLabel($controlStatus)) ?></span>
                  <?php if (trim((string) ($item['control_owner_name'] ?? '')) !== ''): ?>
                    <div class="muted">Responsavel: <?= e((string) ($item['control_owner_name'] ?? '')) ?></div>
                  <?php endif; ?>
                  <?php if (trim((string) ($item['control_note'] ?? '')) !== ''): ?>
                    <div class="muted"><?= e((string) ($item['control_note'] ?? '')) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">N/A</span>
                <?php endif; ?>
              </td>
              <td><?= e($formatDate((string) ($item['status_changed_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . (int) ($item['person_id'] ?? 0))) ?>">Abrir perfil</a>
                <?php if (($canManage ?? false) === true && $level === 'vencido'): ?>
                  <form method="post" action="<?= e(url('/sla-alerts/control/update')) ?>" class="inline-form mt-8">
                    <?= csrf_field() ?>
                    <input type="hidden" name="assignment_id" value="<?= e((string) (int) ($item['assignment_id'] ?? 0)) ?>">
                    <input type="hidden" name="return_to" value="<?= e($buildUrl()) ?>">
                    <select name="control_status">
                      <?php foreach ($controlStatusOptions as $option): ?>
                        <?php
                          $value = (string) ($option['value'] ?? '');
                          $label = (string) ($option['label'] ?? $value);
                        ?>
                        <option value="<?= e($value) ?>" <?= $controlStatus === $value ? 'selected' : '' ?>>
                          <?= e($label) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <select name="owner_user_id">
                      <option value="0">Sem responsavel</option>
                      <?php foreach ($controlOwnerOptions as $owner): ?>
                        <?php $ownerId = (int) ($owner['id'] ?? 0); ?>
                        <option value="<?= e((string) $ownerId) ?>" <?= (int) ($item['control_owner_user_id'] ?? 0) === $ownerId ? 'selected' : '' ?>>
                          <?= e((string) ($owner['name'] ?? 'Usuario')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="note" value="<?= e((string) ($item['control_note'] ?? '')) ?>" placeholder="Observacao">
                    <button type="submit" class="btn btn-outline">Atualizar controle</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) $pagination['total']) ?> registro(s)</span>
      <div class="pagination-links">
        <?php if ((int) $pagination['page'] > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Ultimos disparos de notificacao</h3>
  <?php if ($recentLogs === []): ?>
    <div class="empty-state">
      <p>Nenhum disparo registrado ate o momento.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Pessoa</th>
            <th>Nivel</th>
            <th>Destino</th>
            <th>Resultado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentLogs as $log): ?>
            <?php $logLevel = (string) ($log['severity'] ?? ''); ?>
            <tr>
              <td><?= e($formatDate((string) ($log['created_at'] ?? ''))) ?></td>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($log['person_id'] ?? 0))) ?>"><?= e((string) ($log['person_name'] ?? '-')) ?></a>
                <div class="muted"><?= e((string) ($log['organ_name'] ?? '-')) ?></div>
              </td>
              <td><span class="badge <?= e($levelBadgeClass($logLevel)) ?>"><?= e($severityLabel($logLevel)) ?></span></td>
              <td><?= e((string) ($log['recipient'] ?? '-')) ?></td>
              <td>
                <?php if ((int) ($log['sent_success'] ?? 0) === 1): ?>
                  <span class="text-success">Enviado</span>
                <?php else: ?>
                  <span class="text-danger">Falhou</span>
                <?php endif; ?>
                <div class="muted"><?= e((string) ($log['response_message'] ?? '')) ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
