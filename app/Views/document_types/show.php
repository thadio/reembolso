<?php

declare(strict_types=1);

$type = is_array($type ?? null) ? $type : [];
$typeId = (int) ($type['id'] ?? 0);
$isActive = (int) ($type['is_active'] ?? 0) === 1;
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($type['name'] ?? 'Tipo de documento')) ?></h2>
      <p class="muted">Detalhes do tipo de documento e controle de atividade.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/document-types')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/document-types/edit?id=' . $typeId)) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div>
      <strong>Status:</strong>
      <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>">
        <?= $isActive ? 'Ativo' : 'Inativo' ?>
      </span>
    </div>
    <div><strong>Cadastro:</strong> <?= e((string) ($type['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizacao:</strong> <?= e((string) ($type['updated_at'] ?? '-')) ?></div>
    <div class="field-wide">
      <strong>Descricao:</strong>
      <p class="muted"><?= nl2br(e((string) ($type['description'] ?? '-'))) ?></p>
    </div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/document-types/toggle-active')) ?>" class="actions-inline" onsubmit="return confirm('Confirmar alteracao de status deste tipo de documento?');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) $typeId) ?>">
      <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $isActive ? 'btn-danger' : 'btn-outline' ?>">
        <?= $isActive ? 'Inativar tipo' : 'Ativar tipo' ?>
      </button>
    </form>
  <?php endif; ?>
</div>
