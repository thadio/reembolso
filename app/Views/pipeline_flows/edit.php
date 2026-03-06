<?php

declare(strict_types=1);

$action = url('/pipeline-flows/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar fluxo BPMN</h2>
    <p class="muted">Ajuste metadados do fluxo e mantenha o histórico da configuração.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
