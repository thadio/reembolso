<?php

declare(strict_types=1);

$action = url('/office-templates/store');
$submitLabel = 'Criar template';
$isEdit = false;
$showVersionFields = true;
?>
<div class="header-row">
  <div>
    <h2>Novo template de oficio</h2>
    <p class="muted">Cadastre metadados e publique a versao inicial do template em HTML.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
