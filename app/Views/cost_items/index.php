<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'linkage' => '',
    'macro_category' => '',
    'item_kind' => '',
];
$hierarchy = is_array($hierarchy ?? null) ? $hierarchy : [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$macroCategoryOptions = is_array($macroCategoryOptions ?? null) ? $macroCategoryOptions : [];
$itemKindOptions = is_array($itemKindOptions ?? null) ? $itemKindOptions : [];

$macroLabel = static function (string $value): string {
    return match ($value) {
        'remuneracao_direta' => 'Remuneracao direta',
        'encargos_obrigacoes_legais' => 'Encargos e obrigacoes legais',
        'beneficios_provisoes_indiretos' => 'Beneficios, provisoes e custos indiretos',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
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

$totalCategories = count($hierarchy);
$totalChildren = 0;
foreach ($hierarchy as $group) {
    $totalChildren += count(is_array($group['children'] ?? null) ? $group['children'] : []);
}
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Organizacao por no maximo 10 categorias agregadoras, com itens filhos para detalhamento opcional.</p>
      <p class="muted"><?= e((string) $totalCategories) ?> categoria(s) e <?= e((string) $totalChildren) ?> item(ns) filho(s) ativos.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <div class="actions-inline">
        <a class="btn btn-outline" href="<?= e(url('/cost-items/create?item_kind=aggregator')) ?>">Nova categoria</a>
        <a class="btn btn-primary" href="<?= e(url('/cost-items/create?item_kind=child')) ?>">Novo item filho</a>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/cost-items')) ?>" class="filters-row">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Buscar por codigo, categoria, item ou descricao">

    <select name="macro_category">
      <option value="">Todas as categorias macro</option>
      <?php foreach ($macroCategoryOptions as $option): ?>
        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
        <option value="<?= e($optionValue) ?>" <?= (string) ($filters['macro_category'] ?? '') === $optionValue ? 'selected' : '' ?>>
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="linkage">
      <option value="">Todos os vinculos</option>
      <?php foreach ($linkageOptions as $option): ?>
        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
        <option value="<?= e($optionValue) ?>" <?= (string) ($filters['linkage'] ?? '') === $optionValue ? 'selected' : '' ?>>
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="item_kind">
      <option value="">Categoria e filhos</option>
      <?php foreach ($itemKindOptions as $option): ?>
        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
        <option value="<?= e($optionValue) ?>" <?= (string) ($filters['item_kind'] ?? '') === $optionValue ? 'selected' : '' ?>>
          <?= e((string) ($option['label'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/cost-items')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($hierarchy === []): ?>
    <div class="empty-state">
      <p>Nenhuma categoria ou item encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="cost-hierarchy-list">
      <?php foreach ($hierarchy as $group): ?>
        <?php
          $category = is_array($group['category'] ?? null) ? $group['category'] : [];
          $children = is_array($group['children'] ?? null) ? $group['children'] : [];
          $categoryId = (int) ($category['id'] ?? 0);
          $categoryCode = (int) ($category['cost_code'] ?? 0);
          $categoryName = trim((string) ($category['name'] ?? 'Categoria'));
          $categoryMacro = (string) ($category['macro_category'] ?? '');
          $categoryNature = (string) ($category['expense_nature'] ?? '');
          $categoryReimbursability = (string) ($category['reimbursability'] ?? '');
          $categoryPredictability = (string) ($category['predictability'] ?? '');
          $categoryLinkageCode = (int) ($category['linkage_code'] ?? 309);
          $categoryPeriodicity = (string) ($category['payment_periodicity'] ?? 'mensal');
          $categoryDescription = trim((string) ($category['type_description'] ?? ''));
        ?>
        <details class="cost-hierarchy-group" <?= trim((string) ($filters['q'] ?? '')) !== '' ? 'open' : '' ?>>
          <summary>
            <div>
              <strong><?= e((string) $categoryCode) ?> - <?= e($categoryName) ?></strong>
              <div class="muted"><?= e($macroLabel($categoryMacro)) ?></div>
              <div class="muted"><?= e($expenseNatureLabel($categoryNature)) ?> · <?= e($reimbursabilityLabel($categoryReimbursability)) ?> · <?= e($predictabilityLabel($categoryPredictability)) ?> · <?= e($linkageLabel($categoryLinkageCode)) ?> · <?= e($periodicityLabel($categoryPeriodicity)) ?></div>
              <?php if ($categoryDescription !== ''): ?>
                <div class="muted"><?= e($categoryDescription) ?></div>
              <?php endif; ?>
            </div>
            <div class="actions-inline">
              <span class="badge badge-info"><?= e((string) count($children)) ?> filho(s)</span>
              <a class="btn btn-ghost" href="<?= e(url('/cost-items/show?id=' . $categoryId)) ?>">Ver</a>
              <?php if (($canManage ?? false) === true): ?>
                <a class="btn btn-ghost" href="<?= e(url('/cost-items/edit?id=' . $categoryId)) ?>">Editar</a>
                <a class="btn btn-ghost" href="<?= e(url('/cost-items/create?item_kind=child&parent_cost_item_id=' . $categoryId)) ?>">Adicionar filho</a>
              <?php endif; ?>
            </div>
          </summary>

          <?php if ($children === []): ?>
            <p class="muted">Nenhum item filho nesta categoria.</p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Codigo</th>
                    <th>Item filho</th>
                    <th>Classificacao</th>
                    <th>Periodicidade</th>
                    <th>Acoes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($children as $child): ?>
                    <?php
                      $childId = (int) ($child['id'] ?? 0);
                      $childName = trim((string) ($child['name'] ?? 'Item'));
                      $childDescription = trim((string) ($child['type_description'] ?? ''));
                    ?>
                    <tr>
                      <td><?= e((string) ((int) ($child['cost_code'] ?? 0))) ?></td>
                      <td>
                        <strong><?= e($childName) ?></strong>
                        <?php if ($childDescription !== ''): ?>
                          <div class="muted"><?= e($childDescription) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?= e($expenseNatureLabel((string) ($child['expense_nature'] ?? ''))) ?> · <?= e($reimbursabilityLabel((string) ($child['reimbursability'] ?? ''))) ?><br>
                        <span class="muted"><?= e($predictabilityLabel((string) ($child['predictability'] ?? ''))) ?> · <?= e($linkageLabel((int) ($child['linkage_code'] ?? 309))) ?></span>
                      </td>
                      <td><?= e($periodicityLabel((string) ($child['payment_periodicity'] ?? ''))) ?></td>
                      <td class="actions-cell">
                        <a class="btn btn-ghost" href="<?= e(url('/cost-items/show?id=' . $childId)) ?>">Ver</a>
                        <?php if (($canManage ?? false) === true): ?>
                          <a class="btn btn-ghost" href="<?= e(url('/cost-items/edit?id=' . $childId)) ?>">Editar</a>
                          <form method="post" action="<?= e(url('/cost-items/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste item de custo?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $childId) ?>">
                            <button type="submit" class="btn btn-danger">Excluir</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
