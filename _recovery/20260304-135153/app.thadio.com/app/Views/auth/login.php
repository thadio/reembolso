<?php
// Variáveis: $errors, $email, $redirect, $connectionError, $esc
?>
<section class="auth-card">
  <div class="pill">Retrato • Acesso</div>
  <h1>Entrar</h1>
  <p class="auth-lead">Use o e-mail e a senha cadastrados para acessar o dashboard.</p>

  <?php if (!empty($connectionError)): ?>
    <div class="alert error"><?php echo $esc($connectionError); ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?php echo $esc($error); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" class="grid" style="grid-template-columns: 1fr;">
    <input type="hidden" name="redirect" value="<?php echo $esc($redirect ?? 'index.php'); ?>">
    <div class="field">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" required autofocus value="<?php echo $esc($email ?? ''); ?>" placeholder="seu@email.com">
    </div>
    <div class="field">
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" required placeholder="••••••••">
    </div>
    <div class="footer" style="justify-content: flex-start;">
      <button class="btn primary" type="submit">Entrar</button>
      <a class="btn ghost" href="esqueci-senha.php">Esqueci minha senha</a>
    </div>
  </form>

  <div class="auth-meta">Ainda não tem acesso? Cadastre-se e confirme o e-mail para liberar o primeiro login.</div>
  <div class="footer" style="justify-content:flex-start;margin-top:10px;">
    <a class="btn ghost" href="cadastro.php">Criar conta</a>
  </div>
</section>

<section class="auth-panel">
  <div class="auth-highlight">
    <div class="pill">Camadas de controle</div>
    <h3>Login ligado ao cadastro</h3>
    <div>Reutiliza a tabela de usuários, respeita status "ativo" e envia o usuário para a página solicitada após autenticar.</div>
  </div>
  <ul>
    <li>Senha validada com o hash já salvo na tabela.</li>
    <li>Usuários pendentes só acessam após validar o e-mail.</li>
    <li>Sem cadastro duplicado: basta manter o registro de usuário.</li>
  </ul>
  <small>Atualize as senhas em Usuários &gt; Editar e mantenha apenas contas ativas liberadas.</small>
</section>
