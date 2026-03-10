<?php

declare(strict_types=1);

$action = url('/cost-mirrors/update');
$submitLabel = 'Salvar alteracoes';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <p class="muted">Atualize metadados do espelho mantendo rastreabilidade de auditoria.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
