<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
$itemId = (int) ($item['id'] ?? 0);
$isAggregator = (int) ($item['is_aggregator'] ?? 0) === 1;

$linkageLabel = static function (int $code): string {
    return $code === 510
        ? 'Beneficios e auxilios (custeio) (510)'
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
        'beneficios_provisoes_indiretos' => 'Beneficios, provisoes e custos indiretos',
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

$calculationBaseLabel = static function (string $value): string {
    return match ($value) {
        'salario_base' => 'Salario base',
        'total_folha' => 'Total da folha',
        'valor_fixo' => 'Valor fixo',
        'total' => 'Total',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($item['name'] ?? 'Tipo de custo')) ?></h2>
      <p class="muted"><?= $isAggregator ? 'Categoria agregadora' : 'Item filho de categoria agregadora' ?></p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/cost-items')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/cost-items/edit?id=' . $itemId)) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Codigo:</strong> <?= e((string) ($item['cost_code'] ?? '-')) ?></div>
    <div><strong>Tipo:</strong> <?= $isAggregator ? 'Categoria agregadora' : 'Item filho' ?></div>
    <div><strong>Ordem hierarquica:</strong> <?= e((string) ((int) ($item['hierarchy_sort'] ?? 0))) ?></div>
    <div><strong>Categoria pai:</strong> <?= e($isAggregator ? '-' : ((string) ($item['parent_name'] ?? '-'))) ?></div>
    <div><strong>Categoria macro:</strong> <?= e($macroLabel((string) ($item['macro_category'] ?? ''))) ?></div>
    <div><strong>Subcategoria:</strong> <?= e((string) ($item['subcategory'] ?? '-')) ?></div>
    <div><strong>Nome:</strong> <?= e((string) ($item['name'] ?? '-')) ?></div>
    <div><strong>Descricao:</strong> <?= e((string) ($item['type_description'] ?? '-')) ?></div>
    <div><strong>Natureza da despesa:</strong> <?= e($expenseNatureLabel((string) ($item['expense_nature'] ?? ''))) ?></div>
    <div><strong>Base de calculo:</strong> <?= e($calculationBaseLabel((string) ($item['calculation_base'] ?? ''))) ?></div>
    <div><strong>Incide encargo:</strong> <?= (int) ($item['charge_incidence'] ?? 0) === 1 ? 'Sim' : 'Nao' ?></div>
    <div><strong>Reembolsabilidade:</strong> <?= e($reimbursabilityLabel((string) ($item['reimbursability'] ?? ''))) ?></div>
    <div><strong>Previsibilidade:</strong> <?= e($predictabilityLabel((string) ($item['predictability'] ?? ''))) ?></div>
    <div><strong>Vinculo:</strong> <?= e($linkageLabel((int) ($item['linkage_code'] ?? 309))) ?></div>
    <div><strong>Periodicidade:</strong> <?= e($periodicityLabel((string) ($item['payment_periodicity'] ?? ''))) ?></div>
    <div><strong>Cadastro:</strong> <?= e((string) ($item['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizacao:</strong> <?= e((string) ($item['updated_at'] ?? '-')) ?></div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/cost-items/delete')) ?>" class="actions-inline" onsubmit="return confirm('Confirmar remocao deste tipo de custo?');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) $itemId) ?>">
      <button type="submit" class="btn btn-danger">Excluir tipo</button>
    </form>
  <?php endif; ?>
</div>
