<?php

declare(strict_types=1);

$pipeline = $pipeline ?? [
    'assignment' => null,
    'statuses' => [],
    'next_status' => null,
    'timeline' => [],
];

$assignment = $pipeline['assignment'] ?? null;
$statuses = $pipeline['statuses'] ?? [];
$nextStatus = $pipeline['next_status'] ?? null;
$timeline = $pipeline['timeline'] ?? [];

$statusLabel = static function (string $value): string {
    return match ($value) {
        'interessado' => 'Interessado',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Ofício órgão',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
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

<div class="card">
  <div class="header-row">
    <div>
      <h3>Pipeline de status</h3>
      <p class="muted">Fluxo: Interessado → Triagem → Selecionado → Ofício órgão → Custos recebidos → CDO → MGI → DOU → Ativo</p>
    </div>
    <?php if (($canManage ?? false) === true && $nextStatus !== null): ?>
      <form method="post" action="<?= e(url('/people/pipeline/advance')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? 0)) ?>">
        <button type="submit" class="btn btn-primary">
          <?= e((string) ($nextStatus['next_action_label'] ?? ('Avançar para ' . ($nextStatus['label'] ?? 'próxima etapa')))) ?>
        </button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($assignment === null): ?>
    <p class="muted">Pipeline ainda não inicializado para esta pessoa.</p>
  <?php else: ?>
    <div class="pipeline-track">
      <?php foreach ($statuses as $stage): ?>
        <?php
          $stageOrder = (int) ($stage['sort_order'] ?? 0);
          $currentOrder = (int) ($assignment['current_status_order'] ?? 0);
          $stageClass = 'is-pending';
          if ($stageOrder < $currentOrder) {
              $stageClass = 'is-done';
          } elseif ($stageOrder === $currentOrder) {
              $stageClass = 'is-current';
          }
        ?>
        <div class="pipeline-step <?= e($stageClass) ?>">
          <span class="pipeline-index"><?= e((string) $stageOrder) ?></span>
          <span><?= e((string) ($stage['label'] ?? '')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="summary-line"><strong>Status atual:</strong> <?= e((string) ($assignment['current_status_label'] ?? '-')) ?></div>
    <div class="summary-line"><strong>Próxima ação:</strong> <?= e((string) (($nextStatus['next_action_label'] ?? 'Sem próxima ação'))) ?></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Timeline</h3>
  <?php if ($timeline === []): ?>
    <p class="muted">Sem eventos registrados ainda.</p>
  <?php else: ?>
    <div class="timeline-list">
      <?php foreach ($timeline as $event): ?>
        <article class="timeline-item">
          <div class="timeline-item-header">
            <strong><?= e((string) ($event['title'] ?? 'Evento')) ?></strong>
            <span class="muted"><?= e((string) ($event['event_date'] ?? '')) ?></span>
          </div>
          <p class="muted"><?= e((string) ($event['description'] ?? '')) ?></p>
          <p class="muted">Responsável: <?= e((string) ($event['created_by_name'] ?? 'Sistema')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card card-placeholder">
  <h3>Documentos</h3>
  <p class="muted">Aba será preenchida na Etapa 1.5.</p>
</div>

<div class="card card-placeholder">
  <h3>Custos e Auditoria</h3>
  <p class="muted">Aba será expandida nas próximas fases.</p>
</div>
