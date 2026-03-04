<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $formData */
/** @var string $brandTaxonomy */
/** @var callable $esc */
?>
<form method="post" action="marca-cadastro.php">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1><?php echo $editing ? 'Editar marca' : 'Nova marca'; ?></h1>
      <div class="subtitle">CRUD interno com listagem no app.</div>
      <?php if ($brandTaxonomy !== ''): ?>
        <div style="color:var(--muted);font-size:12px;">Taxonomia: <?php echo $esc($brandTaxonomy); ?></div>
      <?php endif; ?>
    </div>
    <?php if ($editing && $formData['id'] !== ''): ?>
      <span class="pill">ID #<?php echo $esc((string) $formData['id']); ?></span>
    <?php endif; ?>
  </div>

  <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <div class="grid">
    <div class="field">
      <label for="name">Nome *</label>
      <input type="text" id="name" name="name" required maxlength="200" value="<?php echo $esc($formData['name']); ?>">
    </div>
    <div class="field">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" maxlength="200" value="<?php echo $esc($formData['slug']); ?>">
    </div>
    <div class="field" style="grid-column:1 / -1;">
      <label for="description">Descrição</label>
      <textarea id="description" name="description" rows="3" maxlength="1000"><?php echo $esc($formData['description']); ?></textarea>
    </div>
  </div>

  <div class="footer">
    <button class="ghost" type="reset">Limpar</button>
    <button class="primary" type="submit"><?php echo $editing ? 'Salvar marca' : 'Criar marca'; ?></button>
  </div>
</form>

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
        .replace(/[^a-zA-Z0-9]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '')
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
