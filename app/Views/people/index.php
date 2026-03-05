<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'status' => '',
    'organ_id' => 0,
    'modality_id' => 0,
    'tag' => '',
    'queue_scope' => 'all',
    'priority' => '',
    'responsible_id' => 0,
    'sort' => 'name',
    'dir' => 'asc',
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$people = $people ?? [];
$statuses = $statuses ?? [];
$organs = $organs ?? [];
$modalities = $modalities ?? [];
$previewPerson = $previewPerson ?? null;
$queuePriorities = $queuePriorities ?? [];
$queueUsers = $queueUsers ?? [];
$authUserId = (int) ($authUserId ?? 0);

$sort = (string) ($filters['sort'] ?? 'name');
$dir = (string) ($filters['dir'] ?? 'asc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'organ_id' => (string) ($filters['organ_id'] ?? 0),
        'modality_id' => (string) ($filters['modality_id'] ?? 0),
        'tag' => (string) ($filters['tag'] ?? ''),
        'queue_scope' => (string) ($filters['queue_scope'] ?? 'all'),
        'priority' => (string) ($filters['priority'] ?? ''),
        'responsible_id' => (string) ($filters['responsible_id'] ?? 0),
        'sort' => (string) ($filters['sort'] ?? 'name'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/people?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};

$statusLabel = static function (string $value): string {
    return match ($value) {
        'interessado' => 'Interessado',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Ofício órgão',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$priorityLabel = static function (string $value): string {
    return match ($value) {
        'low' => 'Baixa',
        'high' => 'Alta',
        'urgent' => 'Urgente',
        default => 'Normal',
    };
};

$priorityBadgeClass = static function (string $value): string {
    return match ($value) {
        'low' => 'badge-neutral',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger',
        default => 'badge-info',
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Pessoas</h2>
      <p class="muted">Filtros por status, modalidade, órgão e tags.</p>
    </div>
    <div class="actions-inline">
      <?php if ($authUserId > 0): ?>
        <a class="btn btn-outline" href="<?= e($buildUrl(['queue_scope' => 'mine', 'responsible_id' => (string) $authUserId, 'page' => 1])) ?>">Minha fila</a>
      <?php endif; ?>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/people/create')) ?>">Nova pessoa</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/import-csv')) ?>" enctype="multipart/form-data" class="filters-row">
      <?= csrf_field() ?>
      <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required>
      <label class="muted" style="display:flex; align-items:center; gap:.35rem;">
        <input type="checkbox" name="validate_only" value="1">
        Apenas validar (sem gravar)
      </label>
      <button type="submit" class="btn btn-outline">Importar CSV</button>
      <span class="muted">Cabecalho minimo: <code>name, cpf, organ</code> (aceita aliases: <code>nome, orgao</code>).</span>
    </form>
  <?php endif; ?>

  <form method="get" action="<?= e(url('/people')) ?>" class="filters-row filters-people">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome, CPF, órgão, SEI">

    <select name="status">
      <option value="">Status (todos)</option>
      <?php foreach ($statuses as $status): ?>
        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($statusLabel($status)) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="organ_id">
      <option value="0">Órgão (todos)</option>
      <?php foreach ($organs as $organ): ?>
        <option value="<?= e((string) $organ['id']) ?>" <?= (int) ($filters['organ_id'] ?? 0) === (int) $organ['id'] ? 'selected' : '' ?>><?= e((string) $organ['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="modality_id">
      <option value="0">Modalidade (todas)</option>
      <?php foreach ($modalities as $modality): ?>
        <option value="<?= e((string) $modality['id']) ?>" <?= (int) ($filters['modality_id'] ?? 0) === (int) $modality['id'] ? 'selected' : '' ?>><?= e((string) $modality['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <input type="text" name="tag" value="<?= e((string) ($filters['tag'] ?? '')) ?>" placeholder="Tag">

    <select name="queue_scope">
      <?php $queueScope = (string) ($filters['queue_scope'] ?? 'all'); ?>
      <option value="all" <?= $queueScope === 'all' ? 'selected' : '' ?>>Fila (todas)</option>
      <option value="mine" <?= $queueScope === 'mine' ? 'selected' : '' ?>>Minha fila</option>
      <option value="unassigned" <?= $queueScope === 'unassigned' ? 'selected' : '' ?>>Sem responsável</option>
    </select>

    <select name="priority">
      <option value="">Prioridade (todas)</option>
      <?php foreach ($queuePriorities as $priority): ?>
        <?php $priorityValue = (string) ($priority['value'] ?? ''); ?>
        <option value="<?= e($priorityValue) ?>" <?= (string) ($filters['priority'] ?? '') === $priorityValue ? 'selected' : '' ?>>
          <?= e((string) ($priority['label'] ?? $priorityValue)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="responsible_id">
      <option value="0">Responsável (todos)</option>
      <?php foreach ($queueUsers as $user): ?>
        <?php $userId = (int) ($user['id'] ?? 0); ?>
        <option value="<?= e((string) $userId) ?>" <?= (int) ($filters['responsible_id'] ?? 0) === $userId ? 'selected' : '' ?>>
          <?= e((string) ($user['name'] ?? ('Usuário #' . $userId))) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="organ" <?= $sort === 'organ' ? 'selected' : '' ?>>Ordenar por órgão</option>
      <option value="responsible" <?= $sort === 'responsible' ? 'selected' : '' ?>>Ordenar por responsável</option>
      <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Ordenar por prioridade</option>
      <option value="assignment_updated_at" <?= $sort === 'assignment_updated_at' ? 'selected' : '' ?>>Ordenar por atualização da fila</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([10, 20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($pagination['per_page'] ?? 10) === $size ? 'selected' : '' ?>><?= e((string) $size) ?>/página</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/people')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($people === []): ?>
    <div class="empty-state">
      <p>Nenhuma pessoa encontrada com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="list-with-aside">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
              <th>CPF</th>
              <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
              <th><a href="<?= e($buildUrl(['sort' => 'organ', 'dir' => $nextDir('organ'), 'page' => 1])) ?>">Órgão</a></th>
              <th>Modalidade</th>
              <th><a href="<?= e($buildUrl(['sort' => 'responsible', 'dir' => $nextDir('responsible'), 'page' => 1])) ?>">Responsável</a></th>
              <th><a href="<?= e($buildUrl(['sort' => 'priority', 'dir' => $nextDir('priority'), 'page' => 1])) ?>">Prioridade</a></th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($people as $person): ?>
              <tr>
                <td>
                  <strong><?= e((string) ($person['name'] ?? '')) ?></strong>
                  <?php if (!empty($person['tags'])): ?>
                    <div class="muted"><?= e((string) $person['tags']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (($canViewCpfFull ?? false) === true): ?>
                    <?= e((string) ($person['cpf'] ?? '')) ?>
                  <?php else: ?>
                    <?= e(mask_cpf((string) ($person['cpf'] ?? ''))) ?>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-neutral"><?= e($statusLabel((string) ($person['status'] ?? ''))) ?></span></td>
                <td><?= e((string) ($person['organ_name'] ?? '-')) ?></td>
                <td><?= e((string) ($person['modality_name'] ?? '-')) ?></td>
                <td><?= e((string) ($person['assigned_user_name'] ?? 'Não definido')) ?></td>
                <td>
                  <?php $priority = mb_strtolower((string) ($person['assignment_priority'] ?? 'normal')); ?>
                  <span class="badge <?= e($priorityBadgeClass($priority)) ?>"><?= e($priorityLabel($priority)) ?></span>
                </td>
                <td class="actions-cell">
                  <a class="btn btn-ghost" href="<?= e($buildUrl(['preview_id' => (int) $person['id']])) ?>">Resumo</a>
                  <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . (int) $person['id'])) ?>">Perfil 360</a>
                  <?php if (($canManage ?? false) === true): ?>
                    <a class="btn btn-ghost" href="<?= e(url('/people/edit?id=' . (int) $person['id'])) ?>">Editar</a>
                    <form method="post" action="<?= e(url('/people/delete')) ?>" onsubmit="return confirm('Confirmar remoção desta pessoa?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= e((string) $person['id']) ?>">
                      <button type="submit" class="btn btn-danger">Excluir</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <aside class="summary-panel card">
        <h3>Resumo rápido</h3>
        <?php if ($previewPerson === null): ?>
          <p class="muted">Selecione uma pessoa na lista para visualizar resumo.</p>
        <?php else: ?>
          <div class="summary-line"><strong>Nome:</strong> <?= e((string) ($previewPerson['name'] ?? '-')) ?></div>
          <div class="summary-line"><strong>CPF:</strong>
            <?php if (($canViewCpfFull ?? false) === true): ?>
              <?= e((string) ($previewPerson['cpf'] ?? '-')) ?>
            <?php else: ?>
              <?= e(mask_cpf((string) ($previewPerson['cpf'] ?? ''))) ?>
            <?php endif; ?>
          </div>
          <div class="summary-line"><strong>Status:</strong> <?= e($statusLabel((string) ($previewPerson['status'] ?? ''))) ?></div>
          <div class="summary-line"><strong>Órgão:</strong> <?= e((string) ($previewPerson['organ_name'] ?? '-')) ?></div>
          <div class="summary-line"><strong>Modalidade:</strong> <?= e((string) ($previewPerson['modality_name'] ?? '-')) ?></div>
          <div class="summary-line"><strong>Responsável:</strong> <?= e((string) ($previewPerson['assigned_user_name'] ?? 'Não definido')) ?></div>
          <div class="summary-line"><strong>Prioridade:</strong> <?= e($priorityLabel((string) ($previewPerson['assignment_priority'] ?? 'normal'))) ?></div>
          <div class="summary-line"><strong>SEI:</strong> <?= e((string) ($previewPerson['sei_process_number'] ?? '-')) ?></div>
          <div class="summary-line"><strong>Lotação MTE:</strong> <?= e((string) ($previewPerson['mte_destination'] ?? '-')) ?></div>
          <div class="summary-line"><strong>Tags:</strong> <?= e((string) ($previewPerson['tags'] ?? '-')) ?></div>
        <?php endif; ?>
      </aside>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) $pagination['total']) ?> registro(s)</span>
      <div class="pagination-links">
        <?php if ((int) $pagination['page'] > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Página <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
