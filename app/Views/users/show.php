<?php

declare(strict_types=1);

$isActive = (int) ($user['is_active'] ?? 0) === 1;
$roleNames = is_array($user['role_names'] ?? null) ? $user['role_names'] : [];
$permissionNames = is_array($user['permission_names'] ?? null) ? $user['permission_names'] : [];
$passwordRulesSummary = (string) ($passwordRulesSummary ?? '');
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($user['name'] ?? 'Usuario')) ?></h2>
      <p class="muted">Conta administrativa e perfil de acesso.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/users')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/users/edit?id=' . (int) ($user['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>E-mail:</strong> <?= e((string) ($user['email'] ?? '-')) ?></div>
    <div><strong>CPF:</strong> <?= e((string) ($user['cpf'] ?? '-')) ?></div>
    <div>
      <strong>Status:</strong>
      <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>"><?= $isActive ? 'Ativa' : 'Inativa' ?></span>
    </div>
    <div><strong>Ultimo login:</strong> <?= !empty($user['last_login_at']) ? e((string) $user['last_login_at']) : 'Nunca' ?></div>
    <div><strong>Senha alterada em:</strong> <?= !empty($user['password_changed_at']) ? e((string) $user['password_changed_at']) : 'Nao registrado' ?></div>
    <div><strong>Senha expira em:</strong> <?= !empty($user['password_expires_at']) ? e((string) $user['password_expires_at']) : 'Sem expiracao' ?></div>
    <div><strong>Criado em:</strong> <?= e((string) ($user['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizado em:</strong> <?= e((string) ($user['updated_at'] ?? '-')) ?></div>
    <div class="details-wide">
      <strong>Papeis:</strong>
      <?php if ($roleNames === []): ?>
        <span class="muted">Sem papeis vinculados.</span>
      <?php else: ?>
        <div class="chips-row">
          <?php foreach ($roleNames as $role): ?>
            <span class="badge badge-info"><?= e((string) $role) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="details-wide">
      <strong>Permissoes efetivas:</strong>
      <?php if ($permissionNames === []): ?>
        <span class="muted">Nenhuma permissao efetiva encontrada.</span>
      <?php else: ?>
        <div class="chips-row">
          <?php foreach ($permissionNames as $permission): ?>
            <span class="badge badge-neutral"><?= e((string) $permission) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (($canManage ?? false) === true): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Acoes de conta</h3>
        <p class="muted">Ative/desative a conta e execute reset de senha administrativo.</p>
      </div>
    </div>

    <div class="actions-inline sp-bottom-lg">
      <form method="post" action="<?= e(url('/users/toggle-active')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) ($user['id'] ?? 0)) ?>">
        <input type="hidden" name="redirect" value="<?= e('/users/show?id=' . (int) ($user['id'] ?? 0)) ?>">
        <button type="submit" class="btn btn-outline" <?= ($isSelf ?? false) === true && $isActive ? 'disabled' : '' ?>>
          <?= $isActive ? 'Desativar conta' : 'Ativar conta' ?>
        </button>
      </form>

      <?php if (($isSelf ?? false) !== true): ?>
        <form method="post" action="<?= e(url('/users/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste usuario?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string) ($user['id'] ?? 0)) ?>">
          <button type="submit" class="btn btn-danger">Excluir usuario</button>
        </form>
      <?php endif; ?>

      <a class="btn btn-outline" href="<?= e(url('/users/roles')) ?>">Gerenciar papeis/permissoes</a>
    </div>

    <form method="post" action="<?= e(url('/users/reset-password')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) ($user['id'] ?? 0)) ?>">

      <?php if ($passwordRulesSummary !== ''): ?>
        <div class="field field-wide">
          <p class="muted"><strong>Regras de senha:</strong> <?= e($passwordRulesSummary) ?></p>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="reset_password">Nova senha *</label>
        <input id="reset_password" name="reset_password" type="password" required>
      </div>

      <div class="field">
        <label for="reset_password_confirmation">Confirmacao da nova senha *</label>
        <input id="reset_password_confirmation" name="reset_password_confirmation" type="password" required>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Redefinir senha</button>
      </div>
    </form>
  </div>
<?php endif; ?>
