<?php

declare(strict_types=1);

$action = url('/cdos/store');
$submitLabel = 'Salvar CDO';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo CDO</h2>
    <p class="muted">Cadastre o credito disponivel para vinculacao de pessoas.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
