<?php

declare(strict_types=1);

$action = url('/cost-mirrors/store');
$submitLabel = 'Salvar espelho';
$isEdit = false;
?>
<div class="header-row">
  <div>
    <h2>Novo espelho de custo</h2>
    <p class="muted">Cadastre espelho por pessoa/competencia com vinculo opcional ao boleto.</p>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
