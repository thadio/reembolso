<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
$itemKindOptions = is_array($itemKindOptions ?? null) ? $itemKindOptions : [];
$aggregatorOptions = is_array($aggregatorOptions ?? null) ? $aggregatorOptions : [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$reimbursabilityOptions = is_array($reimbursabilityOptions ?? null) ? $reimbursabilityOptions : [];
$periodicityOptions = is_array($periodicityOptions ?? null) ? $periodicityOptions : [];
$macroCategoryOptions = is_array($macroCategoryOptions ?? null) ? $macroCategoryOptions : [];
$expenseNatureOptions = is_array($expenseNatureOptions ?? null) ? $expenseNatureOptions : [];
$calculationBaseOptions = is_array($calculationBaseOptions ?? null) ? $calculationBaseOptions : [];
$predictabilityOptions = is_array($predictabilityOptions ?? null) ? $predictabilityOptions : [];

$old = static fn (string $key, mixed $default = '') => old($key, $item[$key] ?? $default);
$resolvedItemKind = (string) old(
    'item_kind',
    ((int) ($item['is_aggregator'] ?? 0) === 1 ? 'aggregator' : (string) ($_GET['item_kind'] ?? 'child'))
);
if (!in_array($resolvedItemKind, ['aggregator', 'child'], true)) {
    $resolvedItemKind = 'child';
}
$resolvedParentCostItemId = (int) old('parent_cost_item_id', (int) ($item['parent_cost_item_id'] ?? ($_GET['parent_cost_item_id'] ?? 0)));
?>
<div class="card">
  <form method="post" action="<?= e($action ?? '#') ?>" class="form-grid" data-cost-item-form>
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ((int) ($item['id'] ?? 0))) ?>">
    <?php endif; ?>

    <input type="hidden" name="subcategory" value="<?= e((string) $old('subcategory', (string) ($item['subcategory'] ?? ''))) ?>">

    <div class="field">
      <label for="cost_item_item_kind">Tipo de registro *</label>
      <select id="cost_item_item_kind" name="item_kind" required data-item-kind>
        <?php foreach ($itemKindOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $resolvedItemKind === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? $optionValue)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" data-parent-field>
      <label for="cost_item_parent_cost_item_id">Categoria agregadora *</label>
      <select id="cost_item_parent_cost_item_id" name="parent_cost_item_id" data-parent-select>
        <option value="0">Selecione uma categoria</option>
        <?php foreach ($aggregatorOptions as $aggregator): ?>
          <?php $aggregatorId = (int) ($aggregator['id'] ?? 0); ?>
          <option value="<?= e((string) $aggregatorId) ?>" <?= $resolvedParentCostItemId === $aggregatorId ? 'selected' : '' ?>>
            <?= e((string) ((int) ($aggregator['cost_code'] ?? 0))) ?> - <?= e((string) ($aggregator['name'] ?? 'Categoria')) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Itens filhos herdam macrocategoria e vinculo da categoria selecionada.</small>
    </div>

    <div class="field">
      <label for="cost_item_hierarchy_sort">Ordem na hierarquia</label>
      <input
        id="cost_item_hierarchy_sort"
        name="hierarchy_sort"
        type="number"
        min="1"
        max="9999"
        value="<?= e((string) $old('hierarchy_sort', (string) ($item['hierarchy_sort'] ?? ''))) ?>"
        placeholder="Ex.: 10"
      >
    </div>

    <div class="field">
      <label for="cost_item_cost_code">Codigo *</label>
      <input
        id="cost_item_cost_code"
        name="cost_code"
        type="number"
        min="1"
        max="9999"
        required
        value="<?= e((string) $old('cost_code', '')) ?>"
        placeholder="Ex.: 40"
      >
    </div>

    <div class="field field-wide">
      <label for="cost_item_name">Nome *</label>
      <input
        id="cost_item_name"
        name="name"
        type="text"
        minlength="3"
        maxlength="190"
        required
        value="<?= e((string) $old('name', '')) ?>"
        placeholder="Ex.: Auxilio Alimentacao"
      >
    </div>

    <div class="field field-wide">
      <label for="cost_item_type_description">Descricao operacional</label>
      <input
        id="cost_item_type_description"
        name="type_description"
        type="text"
        maxlength="255"
        value="<?= e((string) $old('type_description', '')) ?>"
        placeholder="Ex.: Vale refeicao ou alimentacao"
      >
    </div>

    <div class="field">
      <label for="cost_item_macro_category">Categoria macro *</label>
      <select id="cost_item_macro_category" name="macro_category" required>
        <?php $selectedMacroCategory = (string) $old('macro_category', 'remuneracao_direta'); ?>
        <?php foreach ($macroCategoryOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedMacroCategory === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_expense_nature">Natureza da despesa *</label>
      <select id="cost_item_expense_nature" name="expense_nature" required>
        <?php $selectedExpenseNature = (string) $old('expense_nature', 'remuneratoria'); ?>
        <?php foreach ($expenseNatureOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedExpenseNature === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_calculation_base">Base de calculo *</label>
      <select id="cost_item_calculation_base" name="calculation_base" required>
        <?php $selectedCalculationBase = (string) $old('calculation_base', 'salario_base'); ?>
        <?php foreach ($calculationBaseOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedCalculationBase === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_charge_incidence">Incide encargo? *</label>
      <select id="cost_item_charge_incidence" name="charge_incidence" required>
        <?php $selectedChargeIncidence = (string) $old('charge_incidence', '0'); ?>
        <option value="1" <?= $selectedChargeIncidence === '1' ? 'selected' : '' ?>>Sim</option>
        <option value="0" <?= $selectedChargeIncidence === '0' ? 'selected' : '' ?>>Nao</option>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_reimbursability">Reembolsabilidade *</label>
      <select id="cost_item_reimbursability" name="reimbursability" required>
        <?php $selectedReimbursability = (string) $old('reimbursability', 'nao_reembolsavel'); ?>
        <?php foreach ($reimbursabilityOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedReimbursability === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_predictability">Previsibilidade *</label>
      <select id="cost_item_predictability" name="predictability" required>
        <?php $selectedPredictability = (string) $old('predictability', 'fixa'); ?>
        <?php foreach ($predictabilityOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedPredictability === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_linkage_code">Vinculo *</label>
      <select id="cost_item_linkage_code" name="linkage_code" required>
        <?php $selectedLinkage = (string) $old('linkage_code', '309'); ?>
        <?php foreach ($linkageOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedLinkage === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_periodicity">Periodicidade *</label>
      <select id="cost_item_periodicity" name="payment_periodicity" required>
        <?php $selectedPeriodicity = (string) $old('payment_periodicity', 'mensal'); ?>
        <?php foreach ($periodicityOptions as $option): ?>
          <?php $optionValue = (string) ($option['value'] ?? ''); ?>
          <option value="<?= e($optionValue) ?>" <?= $selectedPeriodicity === $optionValue ? 'selected' : '' ?>>
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-actions field-wide">
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
      <a class="btn btn-outline" href="<?= e(url('/cost-items')) ?>">Cancelar</a>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-cost-item-form]');
    if (!form) {
      return;
    }

    var itemKind = form.querySelector('[data-item-kind]');
    var parentField = form.querySelector('[data-parent-field]');
    var parentSelect = form.querySelector('[data-parent-select]');

    var sync = function () {
      var isChild = itemKind && String(itemKind.value || '') === 'child';

      if (parentField) {
        parentField.style.display = isChild ? '' : 'none';
      }

      if (parentSelect) {
        parentSelect.required = isChild;
        if (!isChild) {
          parentSelect.value = '0';
        }
      }
    };

    if (itemKind) {
      itemKind.addEventListener('change', sync);
    }

    sync();
  });
</script>
