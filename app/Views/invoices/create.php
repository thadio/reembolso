<?php

declare(strict_types=1);

$action = url('/invoices/store');
$submitLabel = 'Salvar boleto';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <p class="muted">Cadastre o boleto por orgao, competencia e vencimento, com PDF e metadados.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
