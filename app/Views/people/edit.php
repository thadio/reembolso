<?php

declare(strict_types=1);

$action = url('/people/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar pessoa</h2>
    <p class="muted">Atualize os dados da pessoa mantendo histórico auditável.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
