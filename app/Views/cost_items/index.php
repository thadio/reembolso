<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'linkage' => '',
    'reimbursability' => '',
    'periodicity' => '',
    'macro_category' => '',
    'subcategory' => '',
    'expense_nature' => '',
    'predictability' => '',
    'sort' => 'cost_code',
    'dir' => 'asc',
    'per_page' => 20,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];
$items = $items ?? [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$reimbursabilityOptions = is_array($reimbursabilityOptions ?? null) ? $reimbursabilityOptions : [];
$periodicityOptions = is_array($periodicityOptions ?? null) ? $periodicityOptions : [];
$macroCategoryOptions = is_array($macroCategoryOptions ?? null) ? $macroCategoryOptions : [];
$subcategoryOptions = is_array($subcategoryOptions ?? null) ? $subcategoryOptions : [];
$expenseNatureOptions = is_array($expenseNatureOptions ?? null) ? $expenseNatureOptions : [];
$predictabilityOptions = is_array($predictabilityOptions ?? null) ? $predictabilityOptions : [];

$sort = (string) ($filters['sort'] ?? 'cost_code');
$dir = (string) ($filters['dir'] ?? 'asc');

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'macro_category' => (string) ($filters['macro_category'] ?? ''),
        'subcategory' => (string) ($filters['subcategory'] ?? ''),
        'linkage' => (string) ($filters['linkage'] ?? ''),
        'reimbursability' => (string) ($filters['reimbursability'] ?? ''),
        'expense_nature' => (string) ($filters['expense_nature'] ?? ''),
        'predictability' => (string) ($filters['predictability'] ?? ''),
        'periodicity' => (string) ($filters['periodicity'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'cost_code'),
        'dir' => (string) ($filters['dir'] ?? 'asc'),
        'per_page' => (string) ($pagination['per_page'] ?? 20),
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

$periodicityLabel = static function (string $value): string {
    return match ($value) {
        'mensal' => 'Mensal',
        'anual' => 'Anual',
        'eventual' => 'Eventual',
        'unico' => 'Unico (legado)',
        default => ucfirst($value),
    };
};

$macroLabel = static function (string $value): string {
    return match ($value) {
        'remuneracao_direta' => 'Remuneracao direta',
        'encargos_obrigacoes_legais' => 'Encargos e obrigacoes legais',
        'beneficios_provisoes_indiretos' => 'Beneficios, provisoes e indiretos',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$expenseNatureLabel = static function (string $value): string {
    return match ($value) {
        'remuneratoria' => 'Remuneratoria',
        'indenizatoria' => 'Indenizatoria',
        'encargos' => 'Encargos',
        'provisoes' => 'Provisoes',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$reimbursabilityLabel = static function (string $value): string {
    return match ($value) {
        'reembolsavel' => 'Reembolsavel',
        'parcialmente_reembolsavel' => 'Parcialmente reembolsavel',
        'nao_reembolsavel' => 'Nao reembolsavel',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$predictabilityLabel = static function (string $value): string {
    return match ($value) {
        'fixa' => 'Fixa',
        'variavel' => 'Variavel',
        'eventual' => 'Eventual',
        default => ucfirst($value),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Tipologia universal de custos de pessoal</h2>
      <p class="muted">Cadastro hierarquico por categoria macro, subcategoria e tipo de verba, com classificacoes de natureza, reembolsabilidade e previsibilidade.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/cost-items/create')) ?>">Novo tipo</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/cost-items')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Codigo, tipo ou descricao">

    <select name="macro_category">
      <option value="">Todas as categorias macro</option>
      <?php foreach ($macroCategoryOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['macro_category'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="subcategory">
      <option value="">Todas as subcategorias</option>
      <?php foreach ($subcategoryOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['subcategory'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

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

    <select name="reimbursability">
      <option value="">Toda reembolsabilidade</option>
      <?php foreach ($reimbursabilityOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['reimbursability'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="expense_nature">
      <option value="">Toda natureza</option>
      <?php foreach ($expenseNatureOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['expense_nature'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="predictability">
      <option value="">Toda previsibilidade</option>
      <?php foreach ($predictabilityOptions as $option): ?>
        <option
          value="<?= e((string) ($option['value'] ?? '')) ?>"
          <?= (string) ($filters['predictability'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
        >
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
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
      <option value="cost_code" <?= $sort === 'cost_code' ? 'selected' : '' ?>>Ordenar por codigo</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Ordenar por tipo</option>
      <option value="macro_category" <?= $sort === 'macro_category' ? 'selected' : '' ?>>Ordenar por categoria macro</option>
      <option value="subcategory" <?= $sort === 'subcategory' ? 'selected' : '' ?>>Ordenar por subcategoria</option>
      <option value="expense_nature" <?= $sort === 'expense_nature' ? 'selected' : '' ?>>Ordenar por natureza</option>
      <option value="reimbursability" <?= $sort === 'reimbursability' ? 'selected' : '' ?>>Ordenar por reembolsabilidade</option>
      <option value="predictability" <?= $sort === 'predictability' ? 'selected' : '' ?>>Ordenar por previsibilidade</option>
      <option value="linkage_code" <?= $sort === 'linkage_code' ? 'selected' : '' ?>>Ordenar por vinculo</option>
      <option value="payment_periodicity" <?= $sort === 'payment_periodicity' ? 'selected' : '' ?>>Ordenar por periodicidade</option>
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 20) === $size ? 'selected' : '' ?>><?= e((string) $size) ?>/pagina</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/cost-items')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Nenhum tipo de custo encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'cost_code', 'dir' => $nextDir('cost_code'), 'page' => 1])) ?>">Codigo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'name', 'dir' => $nextDir('name'), 'page' => 1])) ?>">Tipo de custo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'macro_category', 'dir' => $nextDir('macro_category'), 'page' => 1])) ?>">Hierarquia</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'expense_nature', 'dir' => $nextDir('expense_nature'), 'page' => 1])) ?>">Classificacao</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'payment_periodicity', 'dir' => $nextDir('payment_periodicity'), 'page' => 1])) ?>">Periodicidade</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizacao</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $itemId = (int) ($item['id'] ?? 0); ?>
            <tr>
              <td><?= e((string) ($item['cost_code'] ?? '-')) ?></td>
              <td>
                <strong><?= e((string) ($item['name'] ?? '')) ?></strong>
                <?php if (trim((string) ($item['type_description'] ?? '')) !== ''): ?>
                  <div class="muted"><?= e((string) ($item['type_description'] ?? '')) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= e($macroLabel((string) ($item['macro_category'] ?? ''))) ?><br>
                <span class="muted"><?= e((string) ($item['subcategory'] ?? '-')) ?></span>
              </td>
              <td>
                <?= e($expenseNatureLabel((string) ($item['expense_nature'] ?? ''))) ?> · <?= e($reimbursabilityLabel((string) ($item['reimbursability'] ?? ''))) ?><br>
                <span class="muted"><?= e($predictabilityLabel((string) ($item['predictability'] ?? ''))) ?> · <?= e($linkageLabel((int) ($item['linkage_code'] ?? 309))) ?></span>
              </td>
              <td><?= e($periodicityLabel((string) ($item['payment_periodicity'] ?? ''))) ?></td>
              <td><?= e((string) ($item['updated_at'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/cost-items/show?id=' . $itemId)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/cost-items/edit?id=' . $itemId)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/cost-items/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste tipo de custo?');">
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
