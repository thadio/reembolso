<?php
// Variáveis: $errors, $success, $esc
?>
<section class="auth-card">
  <div class="pill">Retrato • Validação</div>
  <h1>Validar cadastro</h1>
  <p class="auth-lead">Confirmação do e-mail para liberar o primeiro acesso.</p>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?php echo $esc($error); ?></div>
    <?php endforeach; ?>
  <?php elseif ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>

  <div class="footer" style="justify-content:flex-start;">
    <a class="btn primary" href="login.php">Ir para login</a>
  </div>
</section>

<section class="auth-panel">
  <div class="auth-highlight">
    <div class="pill">Segurança</div>
    <h3>Conta protegida por e-mail</h3>
    <div>O acesso inicial só é liberado depois da validação do token enviado para o seu e-mail.</div>
  </div>
  <ul>
    <li>Usuários novos entram com perfil sem permissões.</li>
    <li>O administrador libera o perfil definitivo.</li>
    <li>Tokens expirados precisam de reenvio.</li>
  </ul>
  <small>Se houver problemas com o link, solicite um novo envio ao administrador.</small>
</section>
