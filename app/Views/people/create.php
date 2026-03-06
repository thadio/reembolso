<?php

declare(strict_types=1);

$action = url('/people/store');
$submitLabel = 'Salvar pessoa';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Nova pessoa</h2>
    <p class="muted">Cadastro de entrada/saída com motivo do movimento e fluxo BPMN obrigatório.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
