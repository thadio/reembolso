<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $typeOptions */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Categoria Financeira</h1>
      <div class="subtitle">Use categorias para classificar lançamentos.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Nova categoria'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="financeiro-categoria-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="name">Nome *</label>
        <input type="text" id="name" name="name" required maxlength="160" value="<?php echo $esc($formData['name']); ?>" placeholder="Ex: Marketing, Fornecedores">
      </div>
      <div class="field">
        <label for="type">Tipo *</label>
        <select id="type" name="type" required>
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
      <div class="field" style="grid-column:1 / -1;">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="2" maxlength="255" placeholder="Opcional"><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar categoria</button>
    </div>
  </form>
</div>
