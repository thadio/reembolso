<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var callable $esc */
?>
<?php
  $months = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
  ];
  $scopes = [
    'brasil' => 'Brasil',
    'mundial' => 'Mundial',
    'regional' => 'Regional',
    'setorial' => 'Setorial',
    'local' => 'Local',
  ];
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de data comemorativa</h1>
      <div class="subtitle">Registre feriados, datas especiais e comemorações por escopo.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Nova data'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="data-comemorativa-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field" style="grid-column:1 / -1;">
        <label for="name">Nome *</label>
        <input type="text" id="name" name="name" required maxlength="190" value="<?php echo $esc($formData['name']); ?>">
      </div>
      <div class="field">
        <label for="day">Dia *</label>
        <input type="number" id="day" name="day" min="1" max="31" required value="<?php echo $esc((string) $formData['day']); ?>">
      </div>
      <div class="field">
        <label for="month">Mês *</label>
        <select id="month" name="month" required>
          <option value="">Selecione</option>
          <?php foreach ($months as $value => $label): ?>
            <option value="<?php echo (int) $value; ?>" <?php echo (int) $formData['month'] === (int) $value ? 'selected' : ''; ?>><?php echo $esc($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="year">Ano (opcional)</label>
        <input type="number" id="year" name="year" min="1900" max="2200" value="<?php echo $esc((string) $formData['year']); ?>">
      </div>
      <div class="field">
        <label for="scope">Escopo</label>
        <select id="scope" name="scope">
          <?php foreach ($scopes as $value => $label): ?>
            <option value="<?php echo $esc($value); ?>" <?php echo $formData['scope'] === $value ? 'selected' : ''; ?>><?php echo $esc($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="category">Categoria</label>
        <input type="text" id="category" name="category" maxlength="80" placeholder="Ex: cultural, religioso, ambiental" value="<?php echo $esc($formData['category']); ?>">
      </div>
      <div class="field">
        <label for="source">Fonte</label>
        <input type="text" id="source" name="source" maxlength="120" placeholder="Ex: Calendarr, base interna" value="<?php echo $esc($formData['source']); ?>">
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
        <textarea id="description" name="description" rows="3" maxlength="400" placeholder="Observações da data comemorativa."><?php echo $esc($formData['description']); ?></textarea>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar data</button>
    </div>
  </form>
</div>
