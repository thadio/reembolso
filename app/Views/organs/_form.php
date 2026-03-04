<?php

declare(strict_types=1);

$organ = $organ ?? [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($organ['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="name">Nome do órgão *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($organ['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="acronym">Sigla</label>
      <input id="acronym" name="acronym" type="text" value="<?= e(old('acronym', (string) ($organ['acronym'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="cnpj">CNPJ</label>
      <input id="cnpj" name="cnpj" type="text" value="<?= e(old('cnpj', (string) ($organ['cnpj'] ?? ''))) ?>" placeholder="00.000.000/0000-00">
    </div>

    <div class="field">
      <label for="contact_name">Contato</label>
      <input id="contact_name" name="contact_name" type="text" value="<?= e(old('contact_name', (string) ($organ['contact_name'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="contact_email">E-mail de contato</label>
      <input id="contact_email" name="contact_email" type="email" value="<?= e(old('contact_email', (string) ($organ['contact_email'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="contact_phone">Telefone</label>
      <input id="contact_phone" name="contact_phone" type="text" value="<?= e(old('contact_phone', (string) ($organ['contact_phone'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="address_line">Endereço</label>
      <input id="address_line" name="address_line" type="text" value="<?= e(old('address_line', (string) ($organ['address_line'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="city">Cidade</label>
      <input id="city" name="city" type="text" value="<?= e(old('city', (string) ($organ['city'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="state">UF</label>
      <input id="state" name="state" type="text" value="<?= e(old('state', (string) ($organ['state'] ?? ''))) ?>" maxlength="2">
    </div>

    <div class="field">
      <label for="zip_code">CEP</label>
      <input id="zip_code" name="zip_code" type="text" value="<?= e(old('zip_code', (string) ($organ['zip_code'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="notes">Observações</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($organ['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/organs')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
