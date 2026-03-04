<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($destination['name'] ?? 'Lotação')) ?></h2>
      <p class="muted">Detalhes cadastrais da lotação do MTE.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/mte-destinations')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/mte-destinations/edit?id=' . (int) ($destination['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Código/Sigla:</strong> <?= e((string) ($destination['code'] ?? '-')) ?></div>
    <div><strong>Criado em:</strong> <?= e((string) ($destination['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizado em:</strong> <?= e((string) ($destination['updated_at'] ?? '-')) ?></div>
    <div class="details-wide"><strong>Observações:</strong> <?= nl2br(e((string) ($destination['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Ações rápidas</h3>
      <p class="muted">Continue o fluxo operacional com o cadastro atualizado.</p>
    </div>
  </div>
  <div class="actions-inline">
    <a class="btn btn-outline" href="<?= e(url('/people/create')) ?>">Cadastrar nova pessoa</a>
  </div>
</div>
