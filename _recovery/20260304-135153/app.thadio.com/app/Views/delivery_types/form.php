<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $availabilityOptions */
/** @var array $bagActionOptions */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Tipo de Entrega</h1>
      <div class="subtitle">Defina tipos de entrega e valores de frete sugeridos.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Novo tipo'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="tipo-entrega-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="name">Nome do tipo *</label>
        <input type="text" id="name" name="name" required maxlength="160" value="<?php echo $esc($formData['name']); ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field">
        <label for="base_price">Frete base (R$)</label>
  <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="base_price" name="base_price" value="<?php echo $esc($formData['base_price']); ?>">
      </div>
      <div class="field">
        <label for="south_price">Frete Sul (R$)</label>
  <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" id="south_price" name="south_price" value="<?php echo $esc($formData['south_price']); ?>" placeholder="Opcional">
      </div>
      <div class="field">
        <label for="availability">Disponibilidade</label>
        <select id="availability" name="availability">
          <?php foreach ($availabilityOptions as $key => $label): ?>
            <option value="<?php echo $esc($key); ?>" <?php echo $formData['availability'] === $key ? 'selected' : ''; ?>>
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="bag_action">Ação de sacolinha</label>
        <select id="bag_action" name="bag_action">
          <?php foreach ($bagActionOptions as $key => $label): ?>
            <option value="<?php echo $esc($key); ?>" <?php echo $formData['bag_action'] === $key ? 'selected' : ''; ?>>
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="2" maxlength="255" placeholder="Opcional"><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar tipo</button>
    </div>
  </form>
</div>
