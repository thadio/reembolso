<?php

declare(strict_types=1);

$settings = is_array($settings ?? null) ? $settings : [];
$canManage = ($canManage ?? false) === true;
$passwordRulesSummary = (string) ($passwordRulesSummary ?? '');

$boolChecked = static function (mixed $value): string {
    return ((int) $value === 1) ? 'checked' : '';
};
?>

<div class="card">
  <div class="header-row">
    <div>
      <h2>Seguranca reforcada (6.3)</h2>
      <p class="muted">Politica de senha, expiracao, bloqueio de login e parametros de hardening de upload.</p>
    </div>
  </div>

  <div class="card" style="margin-top: 12px;">
    <p><strong>Regras atuais de senha:</strong> <?= e($passwordRulesSummary) ?></p>
    <?php if (!$canManage): ?>
      <p class="muted">Visualizacao em modo somente leitura. Solicite permissao <code>security.manage</code> para alterar.</p>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(url('/security/update')) ?>" class="form-grid" style="margin-top: 16px;">
    <?= csrf_field() ?>

    <fieldset class="form-fieldset" style="grid-column: 1 / -1;">
      <legend>Politica de senha</legend>
      <div class="form-grid">
        <div class="field">
          <label for="password_min_length">Tamanho minimo</label>
          <input id="password_min_length" name="password_min_length" type="number" min="8" max="64" value="<?= e((string) ((int) ($settings['password_min_length'] ?? 8))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>

        <div class="field">
          <label for="password_max_length">Tamanho maximo</label>
          <input id="password_max_length" name="password_max_length" type="number" min="12" max="256" value="<?= e((string) ((int) ($settings['password_max_length'] ?? 128))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>

        <div class="field">
          <label for="password_expiration_days">Expiracao (dias, 0 desativa)</label>
          <input id="password_expiration_days" name="password_expiration_days" type="number" min="0" max="3650" value="<?= e((string) ((int) ($settings['password_expiration_days'] ?? 0))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>

        <div class="field">
          <label>
            <input type="hidden" name="password_require_upper" value="0">
            <input name="password_require_upper" type="checkbox" value="1" <?= $boolChecked($settings['password_require_upper'] ?? 0) ?> <?= $canManage ? '' : 'disabled' ?>> Exigir maiuscula
          </label>
        </div>

        <div class="field">
          <label>
            <input type="hidden" name="password_require_lower" value="0">
            <input name="password_require_lower" type="checkbox" value="1" <?= $boolChecked($settings['password_require_lower'] ?? 0) ?> <?= $canManage ? '' : 'disabled' ?>> Exigir minuscula
          </label>
        </div>

        <div class="field">
          <label>
            <input type="hidden" name="password_require_number" value="0">
            <input name="password_require_number" type="checkbox" value="1" <?= $boolChecked($settings['password_require_number'] ?? 1) ?> <?= $canManage ? '' : 'disabled' ?>> Exigir numero
          </label>
        </div>

        <div class="field">
          <label>
            <input type="hidden" name="password_require_symbol" value="0">
            <input name="password_require_symbol" type="checkbox" value="1" <?= $boolChecked($settings['password_require_symbol'] ?? 0) ?> <?= $canManage ? '' : 'disabled' ?>> Exigir simbolo
          </label>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-fieldset" style="grid-column: 1 / -1;">
      <legend>Bloqueio de login</legend>
      <div class="form-grid">
        <div class="field">
          <label for="login_max_attempts">Tentativas maximas</label>
          <input id="login_max_attempts" name="login_max_attempts" type="number" min="3" max="20" value="<?= e((string) ((int) ($settings['login_max_attempts'] ?? 5))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>

        <div class="field">
          <label for="login_window_seconds">Janela (segundos)</label>
          <input id="login_window_seconds" name="login_window_seconds" type="number" min="60" max="86400" value="<?= e((string) ((int) ($settings['login_window_seconds'] ?? 900))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>

        <div class="field">
          <label for="login_lockout_seconds">Bloqueio (segundos)</label>
          <input id="login_lockout_seconds" name="login_lockout_seconds" type="number" min="60" max="86400" value="<?= e((string) ((int) ($settings['login_lockout_seconds'] ?? 900))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-fieldset" style="grid-column: 1 / -1;">
      <legend>Hardening de upload</legend>
      <div class="form-grid">
        <div class="field">
          <label for="upload_max_file_size_mb">Limite maximo global por arquivo (MB)</label>
          <input id="upload_max_file_size_mb" name="upload_max_file_size_mb" type="number" min="2" max="100" value="<?= e((string) ((int) ($settings['upload_max_file_size_mb'] ?? 15))) ?>" <?= $canManage ? '' : 'disabled' ?>>
        </div>
      </div>
    </fieldset>

    <?php if ($canManage): ?>
      <div class="form-actions" style="grid-column: 1 / -1;">
        <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
      </div>
    <?php endif; ?>
  </form>
</div>
