<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
$linkageOptions = is_array($linkageOptions ?? null) ? $linkageOptions : [];
$reimbursabilityOptions = is_array($reimbursabilityOptions ?? null) ? $reimbursabilityOptions : [];
$periodicityOptions = is_array($periodicityOptions ?? null) ? $periodicityOptions : [];
$macroCategoryOptions = is_array($macroCategoryOptions ?? null) ? $macroCategoryOptions : [];
$subcategoryOptions = is_array($subcategoryOptions ?? null) ? $subcategoryOptions : [];
$expenseNatureOptions = is_array($expenseNatureOptions ?? null) ? $expenseNatureOptions : [];
$calculationBaseOptions = is_array($calculationBaseOptions ?? null) ? $calculationBaseOptions : [];
$predictabilityOptions = is_array($predictabilityOptions ?? null) ? $predictabilityOptions : [];

$old = static fn (string $key, mixed $default = '') => old($key, $item[$key] ?? $default);
?>
<div class="card">
  <form method="post" action="<?= e($action ?? '#') ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ((int) ($item['id'] ?? 0))) ?>">
    <?php endif; ?>

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
      <label for="cost_item_name">Tipo de custo *</label>
      <input
        id="cost_item_name"
        name="name"
        type="text"
        minlength="3"
        maxlength="190"
        required
        value="<?= e((string) $old('name', '')) ?>"
        placeholder="Ex.: Auxilio alimentacao"
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedMacroCategory === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
            <?= e((string) ($option['label'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="cost_item_subcategory">Subcategoria *</label>
      <select id="cost_item_subcategory" name="subcategory" required>
        <?php $selectedSubcategory = (string) $old('subcategory', 'Remuneracao Base'); ?>
        <?php foreach ($subcategoryOptions as $option): ?>
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedSubcategory === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedExpenseNature === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedCalculationBase === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedReimbursability === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedPredictability === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedLinkage === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
          <option
            value="<?= e((string) ($option['value'] ?? '')) ?>"
            <?= $selectedPeriodicity === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
          >
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
