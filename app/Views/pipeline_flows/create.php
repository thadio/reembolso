<?php

declare(strict_types=1);

$action = url('/pipeline-flows/store');
$submitLabel = 'Salvar fluxo';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo fluxo BPMN</h2>
    <p class="muted">Cadastre fluxos com etapas, decisões e transições não lineares.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
