<?php

declare(strict_types=1);
?>
<div class="auth-wrapper">
  <div class="auth-card card">
    <h1>Entrar no Reembolso</h1>
    <p class="muted">Acesse com sua conta para continuar.</p>
    <form method="post" action="<?= e(url('/login')) ?>" class="form-stack">
      <?= csrf_field() ?>
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="email">

      <label for="password">Senha</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">

      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
  </div>
</div>
