<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $typeOptions */
/** @var array $feeTypeOptions */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Método de Pagamento</h1>
      <div class="subtitle">Defina tipos, taxas e requisitos para uso no pedido.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Novo método'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="metodo-pagamento-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="name">Nome do método *</label>
        <input type="text" id="name" name="name" required maxlength="160" value="<?php echo $esc($formData['name']); ?>">
      </div>
      <div class="field">
        <label for="type">Tipo</label>
        <select id="type" name="type">
          <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
            <option value="<?php echo $esc($typeKey); ?>" <?php echo $formData['type'] === $typeKey ? 'selected' : ''; ?>>
              <?php echo $esc($typeLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field">
        <label for="fee_type">Tipo de taxa</label>
        <select id="fee_type" name="fee_type">
          <?php foreach ($feeTypeOptions as $feeKey => $feeLabel): ?>
            <option value="<?php echo $esc($feeKey); ?>" <?php echo $formData['fee_type'] === $feeKey ? 'selected' : ''; ?>>
              <?php echo $esc($feeLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fee_value">Valor da taxa</label>
  <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="fee_value" name="fee_value" value="<?php echo $esc($formData['fee_value']); ?>">
      </div>
      <div class="field">
        <label>Requisitos</label>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="requires_bank_account" value="1" <?php echo !empty($formData['requires_bank_account']) ? 'checked' : ''; ?>>
            <span>Exigir banco/PIX</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="requires_terminal" value="1" <?php echo !empty($formData['requires_terminal']) ? 'checked' : ''; ?>>
            <span>Exigir maquininha/sistema</span>
          </label>
        </div>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="2" maxlength="255" placeholder="Opcional"><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar método</button>
    </div>
  </form>
</div>
