<?php
// Variáveis: $errors, $success, $token, $tokenValid, $esc
?>
<section class="auth-card">
  <div class="pill">Retrato • Nova senha</div>
  <h1>Redefinir senha</h1>
  <p class="auth-lead">Crie uma nova senha segura para acessar o painel.</p>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?php echo $esc($error); ?></div>
    <?php endforeach; ?>
  <?php elseif ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="footer" style="justify-content: flex-start;">
      <a class="btn primary" href="login.php">Ir para login</a>
    </div>
  <?php elseif ($tokenValid): ?>
    <form method="post" class="grid" style="grid-template-columns: 1fr;">
      <input type="hidden" name="token" value="<?php echo $esc($token); ?>">
      <div class="field">
        <label for="password">Nova senha</label>
        <input type="password" id="password" name="password" required placeholder="••••••••">
      </div>
      <div class="field">
        <label for="confirmPassword">Confirmar nova senha</label>
        <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="••••••••">
      </div>
      <div class="footer" style="justify-content: flex-start;">
        <button class="btn primary" type="submit">Salvar nova senha</button>
        <a class="btn ghost" href="login.php">Cancelar</a>
      </div>
    </form>
  <?php else: ?>
    <div class="footer" style="justify-content: flex-start;">
      <a class="btn primary" href="esqueci-senha.php">Solicitar novo link</a>
      <a class="btn ghost" href="login.php">Voltar ao login</a>
    </div>
  <?php endif; ?>

  <div class="auth-meta">Use uma senha forte e exclusiva para proteger sua conta.</div>
</section>

<section class="auth-panel">
  <div class="auth-highlight">
    <div class="pill">Boas práticas</div>
    <h3>Senha segura, acesso seguro</h3>
    <div>Combine letras, números e símbolos. Evite senhas reutilizadas em outros serviços.</div>
  </div>
  <ul>
    <li>Tokens expiram e só podem ser usados uma vez.</li>
    <li>Após salvar, faça login normalmente.</li>
    <li>Se não solicitou, ignore o e-mail recebido.</li>
  </ul>
  <small>Em caso de erro, solicite um novo link de redefinição.</small>
</section>
