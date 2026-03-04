<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($organ['name'] ?? 'Órgão')) ?></h2>
      <p class="muted">Detalhes cadastrais do órgão de origem.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/organs')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/organs/edit?id=' . (int) ($organ['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Sigla:</strong> <?= e((string) ($organ['acronym'] ?? '-')) ?></div>
    <div><strong>CNPJ:</strong> <?= e((string) ($organ['cnpj'] ?? '-')) ?></div>
    <div><strong>Contato:</strong> <?= e((string) ($organ['contact_name'] ?? '-')) ?></div>
    <div><strong>E-mail:</strong> <?= e((string) ($organ['contact_email'] ?? '-')) ?></div>
    <div><strong>Telefone:</strong> <?= e((string) ($organ['contact_phone'] ?? '-')) ?></div>
    <div><strong>Cidade/UF:</strong> <?= e((string) ($organ['city'] ?? '-')) ?><?= !empty($organ['state']) ? ' / ' . e((string) $organ['state']) : '' ?></div>
    <div><strong>CEP:</strong> <?= e((string) ($organ['zip_code'] ?? '-')) ?></div>
    <div><strong>Endereço:</strong> <?= e((string) ($organ['address_line'] ?? '-')) ?></div>
    <div class="details-wide"><strong>Observações:</strong> <?= nl2br(e((string) ($organ['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Ações rápidas</h3>
      <p class="muted">Atalhos para continuar o fluxo operacional.</p>
    </div>
  </div>
  <div class="actions-inline">
    <a class="btn btn-outline" href="<?= e(url('/people?organ_id=' . (int) ($organ['id'] ?? 0))) ?>">Ver pessoas vinculadas</a>
  </div>
</div>
