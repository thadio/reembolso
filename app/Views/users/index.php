<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'status' => 'all',
    'role_id' => 0,
    'sort' => 'created_at',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$users = $users ?? [];
$roles = $roles ?? [];

$sort = (string) ($filters['sort'] ?? 'created_at');
$dir = (string) ($filters['dir'] ?? 'desc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? 'all'),
        'role_id' => (string) ($filters['role_id'] ?? '0'),
        'sort' => (string) ($filters['sort'] ?? 'created_at'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/users?' . http_build_query($params));
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
      <h2>Usuarios e acessos</h2>
      <p class="muted">Gestao de contas, status e papeis de acesso.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <div class="actions-inline">
        <a class="btn btn-outline" href="<?= e(url('/users/roles')) ?>">Papeis e permissoes</a>
        <a class="btn btn-primary" href="<?= e(url('/users/create')) ?>">Novo usuario</a>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/users')) ?>" class="filters-row filters-users">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome, e-mail ou CPF">

    <select name="status">
      <option value="all" <?= (string) ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos os status</option>
      <option value="active" <?= (string) ($filters['status'] ?? 'all') === 'active' ? 'selected' : '' ?>>Ativos</option>
      <option value="inactive" <?= (string) ($filters['status'] ?? 'all') === 'inactive' ? 'selected' : '' ?>>Inativos</option>
    </select>

    <select name="role_id">
      <option value="0">Todos os papeis</option>
      <?php foreach ($roles as $role): ?>
        <?php $roleId = (int) ($role['id'] ?? 0); ?>
        <option value="<?= e((string) $roleId) ?>" <?= (int) ($filters['role_id'] ?? 0) === $roleId ? 'selected' : '' ?>>
          <?= e((string) ($role['name'] ?? 'papel')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort">
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>Ordenar por e-mail</option>
      <option value="is_active" <?= $sort === 'is_active' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="last_login_at" <?= $sort === 'last_login_at' ? 'selected' : '' ?>>Ordenar por ultimo login</option>
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
    <a href="<?= e(url('/users')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($users === []): ?>
    <div class="empty-state">
      <p>Nenhum usuario encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Usuario</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'email', 'dir' => $nextDir('email'), 'page' => 1])) ?>">E-mail</a></th>
            <th>Papeis</th>
            <th><a href="<?= e($buildUrl(['sort' => 'is_active', 'dir' => $nextDir('is_active'), 'page' => 1])) ?>">Status</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'last_login_at', 'dir' => $nextDir('last_login_at'), 'page' => 1])) ?>">Ultimo login</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $isActive = (int) ($item['is_active'] ?? 0) === 1;
              $roleNames = is_array($item['role_names'] ?? null) ? $item['role_names'] : [];
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($item['name'] ?? '-')) ?></strong>
                <?php if (!empty($item['cpf'])): ?>
                  <div class="muted">CPF: <?= e((string) $item['cpf']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($item['email'] ?? '-')) ?></td>
              <td>
                <?php if ($roleNames === []): ?>
                  <span class="muted">Sem papeis</span>
                <?php else: ?>
                  <div class="chips-row">
                    <?php foreach ($roleNames as $roleName): ?>
                      <span class="badge badge-info"><?= e((string) $roleName) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                  <?= $isActive ? 'Ativa' : 'Inativa' ?>
                </span>
              </td>
              <td><?= !empty($item['last_login_at']) ? e((string) $item['last_login_at']) : '<span class="muted">Nunca</span>' ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/users/show?id=' . $id)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/users/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/users/toggle-active')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                    <input type="hidden" name="redirect" value="/users">
                    <button type="submit" class="btn btn-outline"><?= $isActive ? 'Desativar' : 'Ativar' ?></button>
                  </form>
                  <form method="post" action="<?= e(url('/users/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste usuario?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $id) ?>">
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
        <span>Pagina <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
