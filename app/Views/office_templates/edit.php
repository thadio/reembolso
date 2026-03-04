<?php

declare(strict_types=1);

$action = url('/office-templates/update');
$submitLabel = 'Salvar metadados';
$isEdit = true;
$showVersionFields = false;
?>
<div class="header-row">
  <div>
    <h2>Editar template</h2>
    <p class="muted">Atualize chave, tipo e status. O conteudo HTML e versionado no detalhe do template.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
