<?php

declare(strict_types=1);

$passwordRulesSummary = (string) ($passwordRulesSummary ?? '');
?>
<div class="card" style="max-width: 720px;">
  <div class="header-row">
    <div>
      <h2>Trocar senha</h2>
      <p class="muted">Atualize sua senha de acesso com validacao da senha atual.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Cancelar</a>
    </div>
  </div>

  <form method="post" action="<?= e(url('/users/password/update')) ?>" class="form-grid">
    <?= csrf_field() ?>

    <?php if ($passwordRulesSummary !== ''): ?>
      <div class="field field-wide">
        <p class="muted"><strong>Regras de senha:</strong> <?= e($passwordRulesSummary) ?></p>
      </div>
    <?php endif; ?>

    <div class="field field-wide">
      <label for="current_password">Senha atual *</label>
      <input id="current_password" name="current_password" type="password" required>
    </div>

    <div class="field">
      <label for="new_password">Nova senha *</label>
      <input id="new_password" name="new_password" type="password" required>
    </div>

    <div class="field">
      <label for="new_password_confirmation">Confirmacao da nova senha *</label>
      <input id="new_password_confirmation" name="new_password_confirmation" type="password" required>
    </div>

    <div class="form-actions field-wide">
      <button type="submit" class="btn btn-primary">Salvar nova senha</button>
    </div>
  </form>
</div>
