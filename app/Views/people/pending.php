<?php

declare(strict_types=1);

$items = $items ?? [];
$summary = $summary ?? [
    'total' => 0,
    'abertas' => 0,
    'resolvidas' => 0,
    'documentos' => 0,
    'divergencias' => 0,
    'retornos' => 0,
];
$filters = $filters ?? [
    'q' => '',
    'pending_type' => '',
    'status' => '',
    'severity' => '',
    'queue_scope' => 'all',
    'responsible_id' => 0,
    'sort' => 'updated_at',
    'dir' => 'desc',
    'per_page' => 20,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];
$typeOptions = $typeOptions ?? [];
$statusOptions = $statusOptions ?? [];
$severityOptions = $severityOptions ?? [];
$responsibleOptions = $responsibleOptions ?? [];
$authUserId = (int) ($authUserId ?? 0);
$canManage = ($canManage ?? false) === true;

$sort = (string) ($filters['sort'] ?? 'updated_at');
$dir = (string) ($filters['dir'] ?? 'desc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'pending_type' => (string) ($filters['pending_type'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'severity' => (string) ($filters['severity'] ?? ''),
        'queue_scope' => (string) ($filters['queue_scope'] ?? 'all'),
        'responsible_id' => (string) ($filters['responsible_id'] ?? 0),
        'sort' => (string) ($filters['sort'] ?? 'updated_at'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($filters['per_page'] ?? 20),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/people/pending?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $time = strtotime($value);

    return $time === false ? $value : date('d/m/Y H:i', $time);
};

$typeLabel = static function (string $value): string {
    return match ($value) {
        'documento' => 'Documento',
        'divergencia' => 'Divergencia',
        'retorno' => 'Retorno',
        default => ucfirst($value),
    };
};

$statusLabel = static function (string $value): string {
    return match ($value) {
        'aberta' => 'Aberta',
        'resolvida' => 'Resolvida',
        default => ucfirst($value),
    };
};

$severityLabel = static function (string $value): string {
    return match ($value) {
        'alta' => 'Alta',
        'baixa' => 'Baixa',
        default => 'Media',
    };
};

$severityBadgeClass = static function (string $value): string {
    return match ($value) {
        'alta' => 'badge-danger',
        'baixa' => 'badge-neutral',
        default => 'badge-warning',
    };
};

$statusBadgeClass = static function (string $value): string {
    return match ($value) {
        'resolvida' => 'badge-success',
        default => 'badge-warning',
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Consolida pendencias automaticas de documentos, divergencias e retornos por pessoa.</p>
    </div>
    <div class="actions-inline">
      <?php if ($authUserId > 0): ?>
        <a class="btn btn-outline" href="<?= e($buildUrl(['queue_scope' => 'mine', 'responsible_id' => (string) $authUserId, 'page' => 1])) ?>">Minha fila</a>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Voltar para pessoas</a>
    </div>
  </div>

  <div class="grid-kpi sla-kpi-grid">
    <article class="card kpi-card">
      <p class="kpi-label">Total</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['total'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Abertas</p>
      <p class="kpi-value text-danger"><?= e((string) (int) ($summary['abertas'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Resolvidas</p>
      <p class="kpi-value text-success"><?= e((string) (int) ($summary['resolvidas'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Documentos</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['documentos'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Divergencias</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['divergencias'] ?? 0)) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Retornos</p>
      <p class="kpi-value"><?= e((string) (int) ($summary['retornos'] ?? 0)) ?></p>
    </article>
  </div>

  <form method="get" action="<?= e(url('/people/pending')) ?>" class="filters-row filters-sla">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Pessoa, orgao, SEI, titulo da pendencia">

    <select name="pending_type">
      <?php foreach ($typeOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['pending_type'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="status">
      <?php foreach ($statusOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
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

    <select name="queue_scope">
      <?php $queueScope = (string) ($filters['queue_scope'] ?? 'all'); ?>
      <option value="all" <?= $queueScope === 'all' ? 'selected' : '' ?>>Fila (todas)</option>
      <option value="mine" <?= $queueScope === 'mine' ? 'selected' : '' ?>>Minha fila</option>
      <option value="unassigned" <?= $queueScope === 'unassigned' ? 'selected' : '' ?>>Sem responsavel</option>
    </select>

    <select name="responsible_id">
      <option value="0">Responsavel (todos)</option>
      <?php foreach ($responsibleOptions as $responsible): ?>
        <?php $responsibleId = (int) ($responsible['id'] ?? 0); ?>
        <option value="<?= e((string) $responsibleId) ?>" <?= (int) ($filters['responsible_id'] ?? 0) === $responsibleId ? 'selected' : '' ?>>
          <?= e((string) ($responsible['name'] ?? ('Usuario #' . $responsibleId))) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort">
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por criacao</option>
      <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Ordenar por prazo</option>
      <option value="person_name" <?= $sort === 'person_name' ? 'selected' : '' ?>>Ordenar por pessoa</option>
      <option value="pending_type" <?= $sort === 'pending_type' ? 'selected' : '' ?>>Ordenar por tipo</option>
      <option value="severity" <?= $sort === 'severity' ? 'selected' : '' ?>>Ordenar por severidade</option>
      <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="responsible" <?= $sort === 'responsible' ? 'selected' : '' ?>>Ordenar por responsavel</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([20, 30, 40, 60] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 20) === $size ? 'selected' : '' ?>>
          <?= e((string) $size) ?>/pagina
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/people/pending')) ?>" class="btn btn-outline">Limpar</a>
  </form>

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
            <th><a href="<?= e($buildUrl(['sort' => 'pending_type', 'dir' => $nextDir('pending_type'), 'page' => 1])) ?>">Tipo</a></th>
            <th>Pendencia</th>
            <th><a href="<?= e($buildUrl(['sort' => 'severity', 'dir' => $nextDir('severity'), 'page' => 1])) ?>">Severidade</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'responsible', 'dir' => $nextDir('responsible'), 'page' => 1])) ?>">Responsavel</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'due_date', 'dir' => $nextDir('due_date'), 'page' => 1])) ?>">Prazo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php
              $itemStatus = mb_strtolower(trim((string) ($item['status'] ?? 'aberta')));
              $itemType = mb_strtolower(trim((string) ($item['pending_type'] ?? '')));
              $itemSeverity = mb_strtolower(trim((string) ($item['severity'] ?? 'media')));
            ?>
            <tr>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($item['person_id'] ?? 0))) ?>"><?= e((string) ($item['person_name'] ?? '-')) ?></a>
                <div class="muted">SEI: <?= e((string) ($item['sei_process_number'] ?? '-')) ?></div>
                <div class="muted"><?= e((string) ($item['organ_name'] ?? '-')) ?></div>
              </td>
              <td><span class="badge badge-neutral"><?= e($typeLabel($itemType)) ?></span></td>
              <td>
                <strong><?= e((string) ($item['title'] ?? '-')) ?></strong>
                <?php if (trim((string) ($item['description'] ?? '')) !== ''): ?>
                  <div class="muted"><?= e((string) ($item['description'] ?? '')) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= e($severityBadgeClass($itemSeverity)) ?>"><?= e($severityLabel($itemSeverity)) ?></span></td>
              <td><span class="badge <?= e($statusBadgeClass($itemStatus)) ?>"><?= e($statusLabel($itemStatus)) ?></span></td>
              <td><?= e((string) ($item['responsible_name'] ?? '-')) ?></td>
              <td><?= e($formatDateTime((string) ($item['due_date'] ?? ''))) ?></td>
              <td>
                <?= e($formatDateTime((string) ($item['updated_at'] ?? ''))) ?>
                <?php if ($itemStatus === 'resolvida' && trim((string) ($item['resolved_by_name'] ?? '')) !== ''): ?>
                  <div class="muted">por <?= e((string) ($item['resolved_by_name'] ?? '')) ?></div>
                <?php endif; ?>
              </td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . (int) ($item['person_id'] ?? 0))) ?>">Abrir perfil</a>
                <?php if ($canManage): ?>
                  <form method="post" action="<?= e(url('/people/pending/status')) ?>" class="actions-inline sp-top-xs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pending_id" value="<?= e((string) ((int) ($item['id'] ?? 0))) ?>">
                    <input type="hidden" name="status" value="<?= $itemStatus === 'resolvida' ? 'aberta' : 'resolvida' ?>">
                    <button type="submit" class="btn btn-outline">
                      <?= $itemStatus === 'resolvida' ? 'Reabrir' : 'Resolver' ?>
                    </button>
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
