<?php

declare(strict_types=1);

$action = url('/cdos/update');
$submitLabel = 'Salvar alteracoes';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <p class="muted">Atualize status, periodo e valor mantendo trilha de auditoria.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
