<?php
/** @var array $errors */
/** @var array $formData */
/** @var array $statusOptions */
/** @var bool $editing */
/** @var callable $esc */
?>
<?php
  $formData = $formData ?? [];
  $errors = $errors ?? [];
  $statusOptions = $statusOptions ?? [];
  $editing = $editing ?? false;
  $formTitle = $editing ? 'Editar Marca' : 'Nova Marca';
  $submitLabel = $editing ? 'Atualizar Marca' : 'Criar Marca';
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
  <div>
    <h1><?php echo $esc($formTitle); ?></h1>
    <div class="subtitle">Catálogo interno de marcas</div>
  </div>
  <a class="btn ghost" href="marca-list.php">Voltar para lista</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error" style="margin-bottom:20px;">
    <?php echo $esc(implode(' ', $errors)); ?>
  </div>
<?php endif; ?>

<form action="marca-cadastro.php<?php echo $editing && isset($formData['id']) ? '?id=' . (int)$formData['id'] : ''; ?>" method="post" class="form-grid">
  <?php if ($editing && isset($formData['id'])): ?>
    <input type="hidden" name="id" value="<?php echo (int)$formData['id']; ?>">
  <?php endif; ?>

  <!-- Informações Básicas -->
  <fieldset class="form-section">
    <legend>Informações Básicas</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="name" class="required">Nome *</label>
        <input 
          type="text" 
          id="name" 
          name="name" 
          value="<?php echo $esc($formData['name'] ?? ''); ?>"
          required
          maxlength="200"
          placeholder="Nome da marca"
        >
      </div>

      <div class="form-group">
        <label for="slug">Slug</label>
        <input 
          type="text" 
          id="slug" 
          name="slug" 
          value="<?php echo $esc($formData['slug'] ?? ''); ?>"
          maxlength="120"
          placeholder="slug-da-marca"
        >
        <small class="form-help">Será gerado automaticamente se deixado em branco</small>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Descrição</label>
      <textarea 
        id="description" 
        name="description" 
        rows="4"
        maxlength="1000"
        placeholder="Descrição da marca (opcional)"
      ><?php echo $esc($formData['description'] ?? ''); ?></textarea>
    </div>
  </fieldset>

  <!-- Status -->
  <fieldset class="form-section">
    <legend>Status</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="status">Status *</label>
        <select id="status" name="status" required>
          <?php foreach ($statusOptions as $value => $label): ?>
            <option 
              value="<?php echo $esc($value); ?>"
              <?php echo isset($formData['status']) && $formData['status'] === $value ? 'selected' : (!isset($formData['status']) && $value === 'ativa' ? 'selected' : ''); ?>
            >
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="form-help">ativa = visível | inativa = oculta</small>
      </div>
    </div>
  </fieldset>

  <!-- Ações -->
  <div class="form-actions" style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
    <a href="marca-list.php" class="btn ghost">Cancelar</a>
    <button type="submit" class="btn primary"><?php echo $esc($submitLabel); ?></button>
  </div>
</form>

<style>
.form-grid {
  max-width: 900px;
  margin: 0 auto;
}

.form-section {
  border: 1px solid var(--border, #ddd);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 24px;
}

.form-section legend {
  font-size: 18px;
  font-weight: 600;
  padding: 0 8px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.form-row:last-child {
  margin-bottom: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 500;
  font-size: 14px;
}

.form-group label.required::after {
  content: ' *';
  color: var(--danger, #dc3545);
}

.form-group input[type="text"],
.form-group select,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid var(--border, #ddd);
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input[type="text"]:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary, #007bff);
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.form-help {
  font-size: 12px;
  color: var(--muted, #6c757d);
}
</style>

<script>
  (function() {
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const SLUG_AUTO = 'autoslug';

    if (slugInput) {
      slugInput.dataset[SLUG_AUTO] = slugInput.value.trim() === '' ? 'true' : 'false';
    }

    function slugify(value) {
      return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ç/gi, 'c')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();
    }

    function updateSlug() {
      if (!nameInput || !slugInput || slugInput.dataset[SLUG_AUTO] === 'false') return;
      slugInput.value = slugify(nameInput.value);
    }

    nameInput && nameInput.addEventListener('input', updateSlug);
    slugInput && slugInput.addEventListener('input', () => { slugInput.dataset[SLUG_AUTO] = 'false'; });
    updateSlug();
  })();
</script>
