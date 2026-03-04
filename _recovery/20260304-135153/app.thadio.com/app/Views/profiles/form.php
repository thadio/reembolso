<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $modules */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Perfil</h1>
      <div class="subtitle">Monte papéis com permissões por módulo.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Novo perfil'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="perfil-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="name">Nome do perfil *</label>
        <input type="text" id="name" name="name" required maxlength="120" value="<?php echo $esc($formData['name']); ?>">
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
        <textarea id="description" name="description" rows="2" maxlength="255" placeholder="Como este perfil deve ser usado?"><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <div class="field" style="margin-top:24px;">
      <div style="display:flex;align-items:center;gap:8px;justify-content:space-between;flex-wrap:wrap;">
        <label>Permissões por módulo *</label>
        <span class="pill">Marque o que cada perfil pode fazer</span>
      </div>
      <div class="grid" style="margin-top:12px;">
        <?php foreach ($modules as $moduleKey => $module): ?>
          <div class="card" style="padding:14px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <strong><?php echo $esc($module['label']); ?></strong>
              <small style="color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;"><?php echo $esc($moduleKey); ?></small>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
              <?php foreach ($module['actions'] as $actionKey => $actionLabel): ?>
                <?php $checked = in_array($actionKey, $formData['permissions'][$moduleKey] ?? [], true); ?>
                <label style="display:flex;align-items:center;gap:6px;">
                  <input type="checkbox" name="permissions[<?php echo $esc($moduleKey); ?>][<?php echo $esc($actionKey); ?>]" <?php echo $checked ? 'checked' : ''; ?>>
                  <span><?php echo $esc($actionLabel); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar perfil</button>
    </div>
  </form>
</div>
