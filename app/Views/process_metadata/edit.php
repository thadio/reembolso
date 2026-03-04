<?php

declare(strict_types=1);

$action = url('/process-meta/update');
$submitLabel = 'Salvar alteracoes';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar metadado formal</h2>
    <p class="muted">Atualize dados de oficio, DOU, anexo e data oficial de entrada no MTE.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
