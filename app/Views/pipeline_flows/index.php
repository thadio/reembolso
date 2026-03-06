<?php

declare(strict_types=1);

$filters = $filters ?? ['q' => '', 'sort' => 'name', 'dir' => 'asc', 'per_page' => 10];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$flows = $flows ?? [];

$sort = (string) ($filters['sort'] ?? 'name');
$dir = (string) ($filters['dir'] ?? 'asc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'name'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/pipeline-flows?' . http_build_query($params));
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
      <h2>Fluxos BPMN</h2>
      <p class="muted">Gerencie fluxos, etapas e transições de decisão do pipeline.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/pipeline-flows/create')) ?>">Novo fluxo</a>
  </div>

  <form method="get" action="<?= e(url('/pipeline-flows')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome ou descrição">

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="is_default" <?= $sort === 'is_default' ? 'selected' : '' ?>>Ordenar por padrão</option>
      <option value="is_active" <?= $sort === 'is_active' ? 'selected' : '' ?>>Ordenar por ativo</option>
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualização</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([10, 20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 10) === $size ? 'selected' : '' ?>><?= e((string) $size) ?>/página</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/pipeline-flows')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($flows === []): ?>
    <div class="empty-state">
      <p>Nenhum fluxo encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'is_default', 'dir' => $nextDir('is_default'), 'page' => 1])) ?>">Padrão</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'is_active', 'dir' => $nextDir('is_active'), 'page' => 1])) ?>">Ativo</a></th>
            <th>Etapas</th>
            <th>Transições</th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado em</a></th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($flows as $flow): ?>
            <?php
              $flowId = (int) ($flow['id'] ?? 0);
              $isDefault = (int) ($flow['is_default'] ?? 0) === 1;
              $isActive = (int) ($flow['is_active'] ?? 1) === 1;
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($flow['name'] ?? 'Fluxo')) ?></strong>
                <?php if (trim((string) ($flow['description'] ?? '')) !== ''): ?>
                  <div class="muted"><?= e((string) $flow['description']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $isDefault ? 'badge-info' : 'badge-neutral' ?>">
                  <?= $isDefault ? 'Sim' : 'Não' ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>">
                  <?= $isActive ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td><?= e((string) ((int) ($flow['total_steps'] ?? 0))) ?></td>
              <td><?= e((string) ((int) ($flow['total_transitions'] ?? 0))) ?></td>
              <td><?= e((string) ($flow['updated_at'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/pipeline-flows/show?id=' . $flowId)) ?>">Ver</a>
                <a class="btn btn-ghost" href="<?= e(url('/pipeline-flows/edit?id=' . $flowId)) ?>">Editar</a>
                <form method="post" action="<?= e(url('/pipeline-flows/delete')) ?>" onsubmit="return confirm('Remover este fluxo? Pessoas e movimentações serão realocadas para o fluxo padrão.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((string) $flowId) ?>">
                  <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
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
        <span>Página <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
