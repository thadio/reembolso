<?php

declare(strict_types=1);

$action = url('/users/store');
$submitLabel = 'Salvar usuario';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo usuario</h2>
    <p class="muted">Cadastro administrativo com perfil de acesso e status de conta.</p>
  </div>
  <div class="actions-inline">
    <a class="btn btn-outline" href="<?= e(url('/users/roles')) ?>">Papeis e permissoes</a>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
