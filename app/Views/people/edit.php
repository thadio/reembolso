<?php

declare(strict_types=1);

$action = url('/people/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
$personId = (int) ($person['id'] ?? 0);
$cancelUrl = $personId > 0 ? url('/people/show?id=' . $personId) : url('/people');
?>
<div class="card people-create-hero">
  <div class="header-row">
    <div>
      <h2>Editar movimentação de pessoa</h2>
      <p class="muted">Atualize direção, dados e contexto da movimentação no MTE.</p>
    </div>
    <a class="btn btn-outline" href="<?= e($cancelUrl) ?>">Voltar ao perfil</a>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
