<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'linkage' => '',
    'reimbursable' => '',
    'periodicity' => '',
    'sort' => 'name',
    'dir' => 'asc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$items = $items ?? [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$reimbursableOptions = is_array($reimbursableOptions ?? null) ? $reimbursableOptions : [];
$periodicityOptions = is_array($periodicityOptions ?? null) ? $periodicityOptions : [];

$sort = (string) ($filters['sort'] ?? 'name');
$dir = (string) ($filters['dir'] ?? 'asc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'linkage' => (string) ($filters['linkage'] ?? ''),
        'reimbursable' => (string) ($filters['reimbursable'] ?? ''),
        'periodicity' => (string) ($filters['periodicity'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'name'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/cost-items?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};

$linkageLabel = static function (int $code): string {
    return $code === 510
        ? 'Beneficios e auxilios (510)'
        : 'Remuneracao (309)';
};

$reimbursableLabel = static function (int $flag): string {
    return $flag === 1 ? 'Reembolsavel' : 'Nao-reembolsavel';
};

$periodicityLabel = static function (string $value): string {
    return match ($value) {
        'mensal' => 'Mensal',
        'anual' => 'Anual',
        'unico' => 'Unico',
        default => ucfirst($value),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Itens de custo</h2>
      <p class="muted">Catalogo padrao para correlacionar os itens usados no planejamento de custos por pessoa.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/cost-items/create')) ?>">Novo item</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/cost-items')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Nome do item ou codigo do vinculo">

    <select name="linkage">
      <option value="">Todos os vinculos</option>
      <?php foreach ($linkageOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['linkage'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="reimbursable">
      <option value="">Todas as parcelas</option>
      <option value="reimbursable" <?= (string) ($filters['reimbursable'] ?? '') === 'reimbursable' ? 'selected' : '' ?>>Somente reembolsavel</option>
      <option value="non_reimbursable" <?= (string) ($filters['reimbursable'] ?? '') === 'non_reimbursable' ? 'selected' : '' ?>>Somente nao-reembolsavel</option>
    </select>

    <select name="periodicity">
      <option value="">Todas as periodicidades</option>
      <?php foreach ($periodicityOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['periodicity'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort">
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por nome</option>
      <option value="linkage_code" <?= $sort === 'linkage_code' ? 'selected' : '' ?>>Ordenar por vinculo</option>
      <option value="is_reimbursable" <?= $sort === 'is_reimbursable' ? 'selected' : '' ?>>Ordenar por parcela</option>
      <option value="payment_periodicity" <?= $sort === 'payment_periodicity' ? 'selected' : '' ?>>Ordenar por periodicidade</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
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
    <a href="<?= e(url('/cost-items')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Nenhum item de custo encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Item de custo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'linkage_code', 'dir' => $nextDir('linkage_code'), 'page' => 1])) ?>">Vinculo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'is_reimbursable', 'dir' => $nextDir('is_reimbursable'), 'page' => 1])) ?>">Parcela</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'payment_periodicity', 'dir' => $nextDir('payment_periodicity'), 'page' => 1])) ?>">Periodicidade</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizacao</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $itemId = (int) ($item['id'] ?? 0); ?>
            <tr>
              <td><strong><?= e((string) ($item['name'] ?? '')) ?></strong></td>
              <td><?= e($linkageLabel((int) ($item['linkage_code'] ?? 309))) ?></td>
              <td><?= e($reimbursableLabel((int) ($item['is_reimbursable'] ?? 0))) ?></td>
              <td><?= e($periodicityLabel((string) ($item['payment_periodicity'] ?? ''))) ?></td>
              <td><?= e((string) ($item['updated_at'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/cost-items/show?id=' . $itemId)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/cost-items/edit?id=' . $itemId)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/cost-items/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste item de custo?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $itemId) ?>">
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
