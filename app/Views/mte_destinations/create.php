<?php

declare(strict_types=1);

$action = url('/mte-destinations/store');
$submitLabel = 'Salvar UORG';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <p class="muted">Cadastre UORGs para uso no cadastro de pessoas (origem/destino MTE).</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
