<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var int $nextUserId */
/** @var string $zeroProfileName */
/** @var bool $publicOnboarding */
/** @var callable $esc */
?>
<?php if ($publicOnboarding): ?>
<section class="auth-card">
<?php else: ?>
<div>
<?php endif; ?>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1><?php echo $publicOnboarding ? 'Criar conta' : 'Cadastro de Usuário'; ?></h1>
      <div class="subtitle">
        <?php echo $publicOnboarding ? 'Cadastre-se e confirme o e-mail para liberar o primeiro acesso.' : 'E-mail único, senha com hash e validação do primeiro acesso.'; ?>
      </div>
    </div>
    <?php if (!$publicOnboarding): ?>
      <span class="pill">
        <?php echo $editing ? 'Editando ID #' . $esc((string) $formData['id']) : 'Próximo ID #' . $esc((string) $nextUserId); ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <?php if (empty($profileOptions)): ?>
    <div class="alert error">Nenhum perfil ativo encontrado. Cadastre um perfil de acesso antes de criar usuários.</div>
  <?php endif; ?>

  <?php if ($publicOnboarding): ?>
    <div class="alert muted">Ao concluir, enviamos um link de validação para seu e-mail.</div>
  <?php endif; ?>

  <form method="post" action="usuario-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="fullName">Nome completo *</label>
        <input type="text" id="fullName" name="fullName" required maxlength="200" value="<?php echo $esc($formData['fullName']); ?>">
      </div>
      <div class="field">
        <label for="email">E-mail *</label>
        <input type="email" id="email" name="email" required maxlength="180" value="<?php echo $esc($formData['email']); ?>">
      </div>
      <div class="field">
        <label for="phone">Telefone</label>
        <input type="text" id="phone" name="phone" maxlength="50" value="<?php echo $esc($formData['phone']); ?>">
      </div>
      <?php if ($editing): ?>
        <div class="field">
          <label for="profileId">Perfil de acesso *</label>
          <select id="profileId" name="profileId" required>
            <option value="">Selecione</option>
            <?php foreach ($profileOptions as $option): ?>
              <option value="<?php echo $esc((string) $option['id']); ?>" <?php echo (string) $formData['profileId'] === (string) $option['id'] ? 'selected' : ''; ?>>
                <?php echo $esc($option['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
            <option value="pendente" <?php echo $formData['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
            <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
          </select>
        </div>
      <?php else: ?>
        <div class="field">
          <label>Perfil inicial</label>
          <div class="pill"><?php echo $esc($zeroProfileName); ?> (sem permissões)</div>
          <input type="hidden" name="profileId" value="<?php echo $esc((string) $formData['profileId']); ?>">
        </div>
        <div class="field">
          <label>Status inicial</label>
          <div class="pill">Pendente (aguardando validação)</div>
          <input type="hidden" name="status" value="pendente">
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="password">Senha <?php echo $editing ? '(deixe em branco para manter)' : '*'; ?></label>
        <input type="password" id="password" name="password" <?php echo $editing ? '' : 'required'; ?>>
      </div>
      <div class="field">
        <label for="confirmPassword">Confirmar senha <?php echo $editing ? '(opcional)' : '*'; ?></label>
        <input type="password" id="confirmPassword" name="confirmPassword" <?php echo $editing ? '' : 'required'; ?>>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit"><?php echo $publicOnboarding ? 'Criar conta' : 'Salvar usuário'; ?></button>
    </div>
  </form>
<?php if ($publicOnboarding): ?>
</section>
<section class="auth-panel">
  <div class="auth-highlight">
    <div class="pill">Como funciona</div>
    <h3>Validação obrigatória</h3>
    <div>Após confirmar o e-mail, sua conta é liberada apenas para login, sem permissões iniciais.</div>
  </div>
  <ul>
    <li>Usuários novos começam com perfil zerado.</li>
    <li>O administrador libera as permissões depois.</li>
    <li>Se o link expirar, solicite um novo envio.</li>
  </ul>
  <small>Segurança primeiro: apenas contas validadas podem entrar.</small>
</section>
<?php else: ?>
</div>
<?php endif; ?>
