<?php

declare(strict_types=1);

$statusLabel = static function (string $value): string {
    return match ($value) {
        'interessado' => 'Interessado',
        'em_triagem' => 'Em triagem',
        'aprovado_selecao' => 'Aprovado para seleção',
        'reprovado' => 'Reprovado',
        'arquivado' => 'Arquivado',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($person['name'] ?? 'Pessoa')) ?></h2>
      <p class="muted">Perfil 360</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/people/edit?id=' . (int) ($person['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="tabs-row">
    <span class="tab-chip is-active">Resumo</span>
    <span class="tab-chip">Timeline</span>
    <span class="tab-chip">Documentos</span>
    <span class="tab-chip">Custos</span>
    <span class="tab-chip">Auditoria</span>
  </div>

  <div class="details-grid">
    <div><strong>Status:</strong> <?= e($statusLabel((string) ($person['status'] ?? ''))) ?></div>
    <div><strong>Órgão:</strong> <?= e((string) ($person['organ_name'] ?? '-')) ?></div>
    <div><strong>Modalidade:</strong> <?= e((string) ($person['modality_name'] ?? '-')) ?></div>
    <div><strong>CPF:</strong>
      <?php if (($canViewCpfFull ?? false) === true): ?>
        <?= e((string) ($person['cpf'] ?? '-')) ?>
      <?php else: ?>
        <?= e(mask_cpf((string) ($person['cpf'] ?? ''))) ?>
      <?php endif; ?>
    </div>
    <div><strong>Nascimento:</strong> <?= e((string) ($person['birth_date'] ?? '-')) ?></div>
    <div><strong>E-mail:</strong> <?= e((string) ($person['email'] ?? '-')) ?></div>
    <div><strong>Telefone:</strong> <?= e((string) ($person['phone'] ?? '-')) ?></div>
    <div><strong>Nº processo SEI:</strong> <?= e((string) ($person['sei_process_number'] ?? '-')) ?></div>
    <div><strong>Lotação MTE:</strong> <?= e((string) ($person['mte_destination'] ?? '-')) ?></div>
    <div><strong>Tags:</strong> <?= e((string) ($person['tags'] ?? '-')) ?></div>
    <div class="details-wide"><strong>Observações:</strong> <?= nl2br(e((string) ($person['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="card card-placeholder">
  <h3>Timeline</h3>
  <p class="muted">Aba será preenchida na Etapa 1.4.</p>
</div>

<div class="card card-placeholder">
  <h3>Documentos</h3>
  <p class="muted">Aba será preenchida na Etapa 1.5.</p>
</div>

<div class="card card-placeholder">
  <h3>Custos e Auditoria</h3>
  <p class="muted">Aba será expandida nas próximas fases.</p>
</div>
