<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $bankOptions */
/** @var array $pixTypeOptions */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Conta Bancaria</h1>
      <div class="subtitle">Use contas para PIX e recebimentos bancarios.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Nova conta'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="conta-bancaria-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="bank_id">Banco *</label>
        <select id="bank_id" name="bank_id" required>
          <option value="" <?php echo $formData['bank_id'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($bankOptions as $bank): ?>
            <?php $bankId = (string) ($bank['id'] ?? ''); ?>
            <option value="<?php echo $esc($bankId); ?>" <?php echo $formData['bank_id'] === $bankId ? 'selected' : ''; ?>>
              <?php echo $esc((string) ($bank['name'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="label">Identificacao da conta *</label>
        <input type="text" id="label" name="label" required maxlength="160" value="<?php echo $esc($formData['label']); ?>" placeholder="Ex: Itau conta A">
      </div>
      <div class="field">
        <label for="holder">Titular</label>
        <input type="text" id="holder" name="holder" maxlength="160" value="<?php echo $esc($formData['holder']); ?>">
      </div>
      <div class="field">
        <label for="branch">Agencia</label>
        <input type="text" id="branch" name="branch" maxlength="40" value="<?php echo $esc($formData['branch']); ?>">
      </div>
      <div class="field">
        <label for="account_number">Conta</label>
        <input type="text" id="account_number" name="account_number" maxlength="60" value="<?php echo $esc($formData['account_number']); ?>">
      </div>
      <div class="field">
        <label for="pix_key">Chave PIX</label>
        <input type="text" id="pix_key" name="pix_key" maxlength="160" value="<?php echo $esc($formData['pix_key']); ?>">
      </div>
      <div class="field">
        <label for="pix_key_type">Tipo da chave PIX</label>
        <select id="pix_key_type" name="pix_key_type">
          <option value="" <?php echo $formData['pix_key_type'] === '' ? 'selected' : ''; ?>>Selecione</option>
          <?php foreach ($pixTypeOptions as $pixKey => $pixLabel): ?>
            <option value="<?php echo $esc($pixKey); ?>" <?php echo $formData['pix_key_type'] === $pixKey ? 'selected' : ''; ?>>
              <?php echo $esc($pixLabel); ?>
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
      <button class="primary" type="submit">Salvar conta</button>
    </div>
  </form>
</div>
