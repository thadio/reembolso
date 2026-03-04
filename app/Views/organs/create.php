<?php

declare(strict_types=1);

$action = url('/organs/store');
$submitLabel = 'Salvar órgão';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo órgão de origem</h2>
    <p class="muted">Preencha os dados principais para inclusão no pipeline.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
