<?php
// Variáveis: $errors, $success, $email, $esc
?>
<section class="auth-card">
  <div class="pill">Retrato • Recuperação</div>
  <h1>Esqueci minha senha</h1>
  <p class="auth-lead">Informe o e-mail do seu cadastro para receber um link de redefinição.</p>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?php echo $esc($error); ?></div>
    <?php endforeach; ?>
  <?php elseif ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
    <form method="post" class="grid" style="grid-template-columns: 1fr;">
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus value="<?php echo $esc($email ?? ''); ?>" placeholder="seu@email.com">
      </div>
      <div class="footer" style="justify-content: flex-start;">
        <button class="btn primary" type="submit">Enviar link</button>
        <a class="btn ghost" href="login.php">Voltar ao login</a>
      </div>
    </form>
  <?php else: ?>
    <div class="footer" style="justify-content: flex-start;">
      <a class="btn primary" href="login.php">Ir para login</a>
      <a class="btn ghost" href="esqueci-senha.php">Enviar outro link</a>
    </div>
  <?php endif; ?>

  <div class="auth-meta">O link expira em poucas horas. Se não receber, verifique a caixa de spam.</div>
</section>

<section class="auth-panel">
  <div class="auth-highlight">
    <div class="pill">Segurança</div>
    <h3>Redefinição com token</h3>
    <div>Enviamos um link exclusivo para o seu e-mail cadastrado. Ele expira e só pode ser usado uma vez.</div>
  </div>
  <ul>
    <li>Evite compartilhar o link com outras pessoas.</li>
    <li>Use uma senha diferente da anterior.</li>
    <li>Se não solicitou, ignore o e-mail.</li>
  </ul>
  <small>Precisa de ajuda? Fale com o administrador do sistema.</small>
</section>
