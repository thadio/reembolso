<?php

declare(strict_types=1);

$action = url('/mte-destinations/store');
$submitLabel = 'Salvar lotação';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Nova lotação MTE</h2>
    <p class="muted">Cadastre as lotações disponíveis para uso no cadastro de pessoas.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
