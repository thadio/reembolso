<?php

declare(strict_types=1);

$action = url('/mte-destinations/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <p class="muted">Atualize os dados mantendo histórico auditável.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
