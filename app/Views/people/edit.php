<?php

declare(strict_types=1);

$action = url('/people/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar pessoa</h2>
    <p class="muted">Atualize dados, direção e motivo do movimento mantendo histórico auditável.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
