<?php

declare(strict_types=1);

$filters = $filters ?? ['q' => '', 'sort' => 'name', 'dir' => 'asc', 'per_page' => 10];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$destinations = $destinations ?? [];

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

    return url('/mte-destinations?' . http_build_query($params));
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
      <p class="muted">Cadastro de UORGs para uso nos campos de origem e destino no MTE.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/mte-destinations/create')) ?>">Nova UORG</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/mte-destinations')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome, UORG, sigla ou UF">

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="acronym" <?= $sort === 'acronym' ? 'selected' : '' ?>>Ordenar por sigla</option>
      <option value="code" <?= $sort === 'code' ? 'selected' : '' ?>>Ordenar por código</option>
      <option value="uf" <?= $sort === 'uf' ? 'selected' : '' ?>>Ordenar por UF</option>
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
    <a href="<?= e(url('/mte-destinations')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($destinations === []): ?>
    <div class="empty-state">
      <p>Nenhuma unidade encontrada com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'acronym', 'dir' => $nextDir('acronym'), 'page' => 1])) ?>">Sigla</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'code', 'dir' => $nextDir('code'), 'page' => 1])) ?>">UORG</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'uf', 'dir' => $nextDir('uf'), 'page' => 1])) ?>">UF</a></th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($destinations as $destination): ?>
            <tr>
              <td><?= e((string) ($destination['name'] ?? '')) ?></td>
              <td><?= e((string) ($destination['acronym'] ?? '-')) ?></td>
              <td><?= e((string) ($destination['code'] ?? '-')) ?></td>
              <td><?= e((string) ($destination['uf'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/mte-destinations/show?id=' . (int) $destination['id'])) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/mte-destinations/edit?id=' . (int) $destination['id'])) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/mte-destinations/delete')) ?>" onsubmit="return confirm('Confirmar remoção desta unidade?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $destination['id']) ?>">
                    <button type="submit" class="btn btn-danger">Excluir</button>
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
        <span>Página <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
