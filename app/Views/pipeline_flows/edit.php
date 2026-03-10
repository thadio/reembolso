<?php

declare(strict_types=1);

$action = url('/pipeline-flows/update');
$submitLabel = 'Salvar alterações';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <p class="muted">Ajuste metadados do fluxo e mantenha o histórico da configuração.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
