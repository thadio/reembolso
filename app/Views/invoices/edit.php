<?php

declare(strict_types=1);

$action = url('/invoices/update');
$submitLabel = 'Salvar alteracoes';
$isEdit = true;
?>
<div class="header-row">
  <div>
    <h2>Editar boleto</h2>
    <p class="muted">Atualize status, vencimento, valor e metadados mantendo trilha de auditoria.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
