<?php

declare(strict_types=1);

$action = url('/process-meta/store');
$submitLabel = 'Salvar registro formal';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo metadado formal</h2>
    <p class="muted">Registre numero/protocolo de oficio, publicacao DOU e entrada oficial no MTE.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
