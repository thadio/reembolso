<?php
/** @var array $formData */
/** @var array $errors */
/** @var array $brands */
/** @var array $categories */
/** @var array $statusOptions */
/** @var array $visibilityOptions */
/** @var bool $editing */
/** @var callable $esc */
?>
<?php
  $formData = $formData ?? [];
  $errors = $errors ?? [];
  $brands = $brands ?? [];
  $categories = $categories ?? [];
  $statusOptions = $statusOptions ?? [];
  $visibilityOptions = $visibilityOptions ?? [];
  $editing = $editing ?? false;
  $formTitle = $editing ? 'Editar Produto' : 'Novo Produto';
  $submitLabel = $editing ? 'Atualizar Produto' : 'Criar Produto';
  $statusLegacyMap = [
    'publish' => 'disponivel',
    'active' => 'disponivel',
    'pending' => 'draft',
    'private' => 'archived',
  ];
  $statusValueRaw = strtolower(trim((string) ($formData['status'] ?? 'draft')));
  $statusValue = $statusLegacyMap[$statusValueRaw] ?? $statusValueRaw;
  if ($statusValue === '') {
    $statusValue = 'draft';
  }
  $normalizedStatusOptions = [];
  foreach ($statusOptions as $value => $label) {
    $normalized = strtolower(trim((string) $value));
    $normalized = $statusLegacyMap[$normalized] ?? $normalized;
    if ($normalized === '') {
      continue;
    }
    if (!isset($normalizedStatusOptions[$normalized])) {
      $normalizedStatusOptions[$normalized] = (string) $label;
    }
  }
  if (empty($normalizedStatusOptions)) {
    $normalizedStatusOptions = [
      'draft' => 'Rascunho',
      'disponivel' => 'Disponível',
      'reservado' => 'Reservado',
      'esgotado' => 'Esgotado',
      'baixado' => 'Baixado',
      'archived' => 'Arquivado',
    ];
  }
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
  <div>
    <h1><?php echo $esc($formTitle); ?></h1>
    <div class="subtitle">Catálogo interno de produtos</div>
  </div>
  <a class="btn ghost" href="produto-list.php">Voltar para lista</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error" style="margin-bottom:20px;">
    <?php echo $esc(implode(' ', $errors)); ?>
  </div>
<?php endif; ?>

<form action="produto-cadastro.php<?php echo $editing && isset($formData['id']) ? '?id=' . (int)$formData['id'] : ''; ?>" method="post" class="form-grid">
  <?php if ($editing && isset($formData['id'])): ?>
    <input type="hidden" name="id" value="<?php echo (int)$formData['id']; ?>">
  <?php endif; ?>

  <!-- Informações Básicas -->
  <fieldset class="form-section">
    <legend>Informações Básicas</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="sku">SKU</label>
        <input 
          type="text" 
          id="sku" 
          name="sku" 
          value="<?php echo $esc($formData['sku'] ?? ''); ?>"
          placeholder="Código único do produto"
        >
        <small class="form-help">Código único de identificação. Se deixar em branco, será gerado automaticamente.</small>
      </div>

      <div class="form-group">
        <label for="name" class="required">Nome *</label>
        <input 
          type="text" 
          id="name" 
          name="name" 
          value="<?php echo $esc($formData['name'] ?? ''); ?>"
          required
          placeholder="Nome do produto"
        >
      </div>
    </div>

    <div class="form-group">
      <label for="short_description">Descrição Curta</label>
      <textarea 
        id="short_description" 
        name="short_description" 
        rows="3"
        placeholder="Breve descrição do produto (aparece em listagens)"
      ><?php echo $esc($formData['short_description'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
      <label for="description">Descrição Completa</label>
      <textarea 
        id="description" 
        name="description" 
        rows="6"
        placeholder="Descrição detalhada do produto"
      ><?php echo $esc($formData['description'] ?? ''); ?></textarea>
    </div>
  </fieldset>

  <!-- Classificação -->
  <fieldset class="form-section">
    <legend>Classificação</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="brand_id">Marca</label>
        <select id="brand_id" name="brand_id">
          <option value="">Sem marca</option>
          <?php foreach ($brands as $brand): ?>
            <option 
              value="<?php echo (int)$brand['id']; ?>"
              <?php echo isset($formData['brand_id']) && (int)$formData['brand_id'] === (int)$brand['id'] ? 'selected' : ''; ?>
            >
              <?php echo $esc($brand['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="category_id">Categoria</label>
        <select id="category_id" name="category_id">
          <option value="">Sem categoria</option>
          <?php foreach ($categories as $category): ?>
            <option 
              value="<?php echo (int)$category['id']; ?>"
              <?php echo isset($formData['category_id']) && (int)$formData['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>
            >
              <?php echo $esc($category['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </fieldset>

  <!-- Preços e Custos -->
  <fieldset class="form-section">
    <legend>Preços e Custos</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="price">Preço de Venda *</label>
        <input 
          type="number" 
          id="price" 
          name="price" 
          step="0.01" 
          min="0"
          value="<?php echo $esc($formData['price'] ?? ''); ?>"
          placeholder="0.00"
          required
        >
        <small class="form-help">Preço final de venda ao cliente</small>
      </div>

      <div class="form-group">
        <label for="cost">Custo</label>
        <input 
          type="number" 
          id="cost" 
          name="cost" 
          step="0.01" 
          min="0"
          value="<?php echo $esc($formData['cost'] ?? ''); ?>"
          placeholder="0.00"
        >
        <small class="form-help">Custo de aquisição ou produção</small>
      </div>

      <div class="form-group">
        <label for="suggested_price">Preço Sugerido</label>
        <input 
          type="number" 
          id="suggested_price" 
          name="suggested_price" 
          step="0.01" 
          min="0"
          value="<?php echo $esc($formData['suggested_price'] ?? ''); ?>"
          placeholder="0.00"
        >
        <small class="form-help">Preço sugerido ou de referência</small>
      </div>

      <div class="form-group">
        <label for="margin">Margem (%)</label>
        <input 
          type="number" 
          id="margin" 
          name="margin" 
          step="0.01" 
          min="0"
          max="100"
          value="<?php echo $esc($formData['margin'] ?? ''); ?>"
          placeholder="0.00"
        >
        <small class="form-help">Margem de lucro em percentual</small>
      </div>
    </div>
  </fieldset>

  <!-- Status e Visibilidade -->
  <fieldset class="form-section">
    <legend>Status e Visibilidade</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="status">Status *</label>
        <select id="status" name="status" required>
          <?php foreach ($normalizedStatusOptions as $value => $label): ?>
            <option 
              value="<?php echo $esc($value); ?>"
              <?php echo $statusValue === $value ? 'selected' : ''; ?>
            >
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="form-help">draft = rascunho | disponivel = vendável | reservado/esgotado/baixado/archived = indisponível</small>
      </div>

      <div class="form-group">
        <label for="visibility">Visibilidade *</label>
        <select id="visibility" name="visibility" required>
          <?php foreach ($visibilityOptions as $value => $label): ?>
            <option 
              value="<?php echo $esc($value); ?>"
              <?php echo isset($formData['visibility']) && $formData['visibility'] === $value ? 'selected' : ''; ?>
            >
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="form-help">public = público | catalog = só catálogo | search = só busca | hidden = oculto</small>
      </div>
    </div>
  </fieldset>

  <!-- Disponibilidade -->
  <fieldset class="form-section">
    <legend>Disponibilidade</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="quantity">Quantidade</label>
        <input
          type="number"
          id="quantity"
          name="quantity"
          min="0"
          step="1"
          value="<?php echo (int) ($formData['quantity'] ?? 1); ?>"
          placeholder="1"
        >
        <small class="form-help">Quantidade disponível para venda.</small>
      </div>

      <div class="form-group">
        <label for="source">Origem</label>
        <select id="source" name="source">
          <option value="compra" <?php echo (($formData['source'] ?? 'compra') === 'compra') ? 'selected' : ''; ?>>Compra</option>
          <option value="consignacao" <?php echo (($formData['source'] ?? '') === 'consignacao') ? 'selected' : ''; ?>>Consignação</option>
          <option value="doacao" <?php echo (($formData['source'] ?? '') === 'doacao') ? 'selected' : ''; ?>>Doação</option>
        </select>
        <small class="form-help">Origem do produto.</small>
      </div>
    </div>
  </fieldset>

  <!-- Dados Físicos -->
  <fieldset class="form-section">
    <legend>Dados Físicos</legend>
    
    <div class="form-row">
      <div class="form-group">
        <label for="weight">Peso (kg)</label>
        <input 
          type="number" 
          id="weight" 
          name="weight" 
          step="0.01" 
          min="0"
          value="<?php echo $esc($formData['weight'] ?? ''); ?>"
          placeholder="0.00"
        >
        <small class="form-help">Peso em quilogramas</small>
      </div>
    </div>
  </fieldset>

  <!-- Ações -->
  <div class="form-actions" style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
    <a href="produto-list.php" class="btn ghost">Cancelar</a>
    <button type="submit" class="btn primary"><?php echo $esc($submitLabel); ?></button>
  </div>
</form>

<style>
.form-grid {
  max-width: 1200px;
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
.form-group input[type="number"],
.form-group select,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid var(--border, #ddd);
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus,
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

.form-group input[type="checkbox"] {
  margin-right: 8px;
}
</style>
