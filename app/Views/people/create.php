<?php

declare(strict_types=1);

$action = url('/people/store');
$submitLabel = 'Salvar pessoa';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Nova pessoa</h2>
    <p class="muted">Cadastro inicial com vínculo obrigatório ao órgão de origem.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
