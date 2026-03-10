<?php

declare(strict_types=1);

$filters = $filters ?? ['q' => '', 'status' => '', 'sort' => 'name', 'dir' => 'asc', 'per_page' => 10];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$types = $types ?? [];

$sort = (string) ($filters['sort'] ?? 'name');
$dir = (string) ($filters['dir'] ?? 'asc');
$status = (string) ($filters['status'] ?? '');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'name'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/document-types?' . http_build_query($params));
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
      <p class="muted">Catálogo utilizado no dossiê documental e no mapeamento das etapas BPMN.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/document-types/create')) ?>">Novo tipo</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/document-types')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome ou descricao">

    <select name="status">
      <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos os status</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Somente ativos</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Somente inativos</option>
    </select>

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="is_active" <?= $sort === 'is_active' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([10, 20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 10) === $size ? 'selected' : '' ?>><?= e((string) $size) ?>/pagina</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/document-types')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($types === []): ?>
    <div class="empty-state">
      <p>Nenhum tipo de documento encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
            <th>Descricao</th>
            <th><a href="<?= e($buildUrl(['sort' => 'is_active', 'dir' => $nextDir('is_active'), 'page' => 1])) ?>">Status</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizacao</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($types as $type): ?>
            <?php $typeIsActive = (int) ($type['is_active'] ?? 0) === 1; ?>
            <tr>
              <td><strong><?= e((string) ($type['name'] ?? '')) ?></strong></td>
              <td><?= e((string) ($type['description'] ?? '-')) ?></td>
              <td>
                <span class="badge <?= $typeIsActive ? 'badge-success' : 'badge-neutral' ?>">
                  <?= $typeIsActive ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td><?= e((string) ($type['updated_at'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/document-types/show?id=' . (int) ($type['id'] ?? 0))) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/document-types/edit?id=' . (int) ($type['id'] ?? 0))) ?>">Editar</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) ($pagination['total'] ?? 0)) ?> registro(s)</span>
      <div class="pagination-links">
        <?php if ((int) ($pagination['page'] ?? 1) > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= e((string) ($pagination['page'] ?? 1)) ?> de <?= e((string) ($pagination['pages'] ?? 1)) ?></span>
        <?php if ((int) ($pagination['page'] ?? 1) < (int) ($pagination['pages'] ?? 1)): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
