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
      <h1>Cadastro de Transportadora</h1>
      <div class="subtitle">Defina dados para logística e montagem de links de rastreio.</div>
    </div>
    <span class="pill"><?php echo $editing ? 'Editando #' . $esc((string) ($formData['id'] ?? '')) : 'Nova transportadora'; ?></span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="transportadora-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
    <div class="grid">
      <div class="field">
        <label for="name">Nome *</label>
        <input type="text" id="name" name="name" maxlength="160" required value="<?php echo $esc((string) ($formData['name'] ?? '')); ?>">
      </div>
      <div class="field">
        <label for="carrier_type">Tipo</label>
        <select id="carrier_type" name="carrier_type">
          <?php foreach ($typeOptions as $key => $label): ?>
            <option value="<?php echo $esc((string) $key); ?>" <?php echo (string) ($formData['carrier_type'] ?? 'transportadora') === (string) $key ? 'selected' : ''; ?>>
              <?php echo $esc((string) $label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="ativo" <?php echo (string) ($formData['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo (string) ($formData['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field">
        <label for="site_url">Site</label>
        <input type="url" id="site_url" name="site_url" maxlength="255" value="<?php echo $esc((string) ($formData['site_url'] ?? '')); ?>" placeholder="https://...">
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="tracking_url_template">Template URL de rastreio</label>
        <input type="text" id="tracking_url_template" name="tracking_url_template" maxlength="255" value="<?php echo $esc((string) ($formData['tracking_url_template'] ?? '')); ?>" placeholder="https://.../{{tracking_code}}">
        <div class="subtitle" style="margin-top:6px;">Use `{{tracking_code}}` para montar o link automaticamente.</div>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="3" maxlength="1000"><?php echo $esc((string) ($formData['notes'] ?? '')); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar transportadora</button>
    </div>
  </form>
</div>
