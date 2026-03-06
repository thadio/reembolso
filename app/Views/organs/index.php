<?php

declare(strict_types=1);

$filters = $filters ?? ['q' => '', 'sort' => 'name', 'dir' => 'asc', 'per_page' => 10];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$organs = $organs ?? [];

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

    return url('/organs?' . http_build_query($params));
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
      <h2>Órgãos de Origem</h2>
      <p class="muted">Busca por nome, sigla, CNPJ e classificacao institucional.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/organs/create')) ?>">Novo órgão</a>
    <?php endif; ?>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/organs/import-csv')) ?>" enctype="multipart/form-data" class="filters-row">
      <?= csrf_field() ?>
      <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required>
      <label class="muted" style="display:flex; align-items:center; gap:.35rem;">
        <input type="checkbox" name="validate_only" value="1">
        Apenas validar (sem gravar)
      </label>
      <button type="submit" class="btn btn-outline">Importar CSV</button>
      <span class="muted">Cabecalho minimo: <code>name</code>. Tambem aceita <code>organ_type, government_level, government_branch, supervising_organ, source_name, source_url</code>.</span>
    </form>
  <?php endif; ?>

  <form method="get" action="<?= e(url('/organs')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome, sigla, CNPJ ou classificacao">

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="acronym" <?= $sort === 'acronym' ? 'selected' : '' ?>>Ordenar por sigla</option>
      <option value="cnpj" <?= $sort === 'cnpj' ? 'selected' : '' ?>>Ordenar por CNPJ</option>
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
    <a href="<?= e(url('/organs')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($organs === []): ?>
    <div class="empty-state">
      <p>Nenhum órgão encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'acronym', 'dir' => $nextDir('acronym'), 'page' => 1])) ?>">Sigla</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'cnpj', 'dir' => $nextDir('cnpj'), 'page' => 1])) ?>">CNPJ</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'organ_type', 'dir' => $nextDir('organ_type'), 'page' => 1])) ?>">Classificacao</a></th>
            <th>Contato</th>
            <th>Localidade</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($organs as $organ): ?>
            <tr>
              <td><?= e((string) ($organ['name'] ?? '')) ?></td>
              <td><?= e((string) ($organ['acronym'] ?? '-')) ?></td>
              <td><?= e((string) ($organ['cnpj'] ?? '-')) ?></td>
              <td>
                <?= e((string) (!empty($organ['organ_type']) ? ucfirst(str_replace('_', ' ', (string) $organ['organ_type'])) : '-')) ?>
                <?php if (!empty($organ['government_level'])): ?>
                  <div class="muted"><?= e((string) ucfirst(str_replace('_', ' ', (string) $organ['government_level']))) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= e((string) ($organ['contact_email'] ?? '-')) ?>
                <?php if (!empty($organ['contact_phone'])): ?>
                  <div class="muted"><?= e((string) $organ['contact_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= e((string) ($organ['city'] ?? '-')) ?>
                <?php if (!empty($organ['state'])): ?>
                  / <?= e((string) $organ['state']) ?>
                <?php endif; ?>
              </td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/organs/show?id=' . (int) $organ['id'])) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/organs/edit?id=' . (int) $organ['id'])) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/organs/delete')) ?>" onsubmit="return confirm('Confirmar remoção deste órgão?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $organ['id']) ?>">
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
