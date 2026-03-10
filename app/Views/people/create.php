<?php

declare(strict_types=1);

$action = url('/people/store');
$submitLabel = 'Salvar pessoa';
$isEdit = false;
?>
<div class="card people-create-hero">
  <div class="header-row">
    <div>
      <h2>Novo cadastro de pessoa</h2>
      <p class="muted">Registre entrada ou saída com motivo do movimento, órgão de contraparte e fluxo BPMN obrigatório.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Voltar para lista</a>
  </div>
  <div class="chips-row people-create-hero-meta">
    <span class="badge badge-info">Direção obrigatória</span>
    <span class="badge badge-info">Motivo obrigatório</span>
    <span class="badge badge-info">Fluxo BPMN obrigatório</span>
  </div>
</div>
<?php require __DIR__ . '/_form.php'; ?>
