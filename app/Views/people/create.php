<?php

declare(strict_types=1);

$action = url('/people/store');
$submitLabel = 'Criar movimentação';
$isEdit = false;
$cancelUrl = url('/people');
?>
<div class="card people-create-hero">
  <div class="header-row">
    <div>
      <h2>Registrar movimentação de pessoa</h2>
      <p class="muted">Entrada ou saída de servidor no MTE.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Voltar para lista</a>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
