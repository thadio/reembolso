<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'template_type' => '',
    'is_active' => '',
    'sort' => 'updated_at',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$templates = $templates ?? [];
$typeOptions = $typeOptions ?? [];
$canManage = (bool) ($canManage ?? false);

$sort = (string) ($filters['sort'] ?? 'updated_at');
$dir = (string) ($filters['dir'] ?? 'desc');

$typeLabel = static function (string $type): string {
    return match ($type) {
        'orgao' => 'Orgao',
        'mgi' => 'MGI',
        'cobranca' => 'Cobranca',
        'resposta' => 'Resposta',
        'outro' => 'Outro',
        default => ucfirst($type),
    };
};

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'template_type' => (string) ($filters['template_type'] ?? ''),
        'is_active' => (string) ($filters['is_active'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'updated_at'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/office-templates?' . http_build_query($params));
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
      <h2>Templates de oficio</h2>
      <p class="muted">Catalogo versionado para gerar oficios com merge de variaveis.</p>
    </div>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?= e(url('/office-templates/create')) ?>">Novo template</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/office-templates')) ?>" class="filters-row filters-office-template">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome, chave, descricao, assunto">

    <select name="template_type">
      <option value="">Todos os tipos</option>
      <?php foreach ($typeOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['template_type'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="is_active">
      <option value="">Ativos e inativos</option>
      <option value="1" <?= (string) ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Somente ativos</option>
      <option value="0" <?= (string) ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Somente inativos</option>
    </select>

    <select name="sort">
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="template_key" <?= $sort === 'template_key' ? 'selected' : '' ?>>Ordenar por chave</option>
      <option value="template_type" <?= $sort === 'template_type' ? 'selected' : '' ?>>Ordenar por tipo</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por criacao</option>
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
    <a href="<?= e(url('/office-templates')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($templates === []): ?>
    <div class="empty-state">
      <p>Nenhum template encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'template_key', 'dir' => $nextDir('template_key'), 'page' => 1])) ?>">Chave</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Nome</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'template_type', 'dir' => $nextDir('template_type'), 'page' => 1])) ?>">Tipo</a></th>
            <th>Versao ativa</th>
            <th>Versoes</th>
            <th>Oficios gerados</th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado em</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $template): ?>
            <?php $id = (int) ($template['id'] ?? 0); ?>
            <tr>
              <td><code><?= e((string) ($template['template_key'] ?? '-')) ?></code></td>
              <td>
                <strong><?= e((string) ($template['name'] ?? '-')) ?></strong>
                <?php if ((int) ($template['is_active'] ?? 1) === 1): ?>
                  <span class="badge badge-success">Ativo</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Inativo</span>
                <?php endif; ?>
              </td>
              <td><?= e($typeLabel((string) ($template['template_type'] ?? ''))) ?></td>
              <td>
                <?php if (!empty($template['active_version_number'])): ?>
                  V<?= e((string) ($template['active_version_number'] ?? '')) ?>
                <?php else: ?>
                  <span class="muted">Sem versao</span>
                <?php endif; ?>
              </td>
              <td><?= e((string) (int) ($template['versions_count'] ?? 0)) ?></td>
              <td><?= e((string) (int) ($template['generated_count'] ?? 0)) ?></td>
              <td><?= e($formatDate((string) ($template['updated_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/office-templates/show?id=' . $id)) ?>">Ver</a>
                <?php if ($canManage): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/office-templates/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/office-templates/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste template?');">
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
