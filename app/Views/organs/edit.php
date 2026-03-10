<?php

declare(strict_types=1);

$action = url('/organs/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <p class="muted">Atualize os dados e mantenha a rastreabilidade do cadastro.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
