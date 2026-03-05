<?php

declare(strict_types=1);

$action = url('/users/update');
$submitLabel = 'Salvar alteracoes';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar usuario</h2>
    <p class="muted">Atualize dados, status da conta e papeis vinculados.</p>
  </div>
  <div class="actions-inline">
    <a class="btn btn-outline" href="<?= e(url('/users/show?id=' . (int) ($user['id'] ?? 0))) ?>">Voltar ao detalhe</a>
    <a class="btn btn-outline" href="<?= e(url('/users/roles')) ?>">Papeis e permissoes</a>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
