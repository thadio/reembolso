<?php

declare(strict_types=1);

$pipeline = $pipeline ?? [
    'assignment' => null,
    'statuses' => [],
    'next_status' => null,
    'timeline' => [],
    'timeline_pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 8,
        'pages' => 1,
    ],
    'event_types' => [],
];

$assignment = $pipeline['assignment'] ?? null;
$statuses = $pipeline['statuses'] ?? [];
$nextStatus = $pipeline['next_status'] ?? null;
$timeline = $pipeline['timeline'] ?? [];
$timelinePagination = $pipeline['timeline_pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$eventTypes = $pipeline['event_types'] ?? [];
$personId = (int) ($person['id'] ?? 0);

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

$eventTypeLabel = static function (string $value): string {
    $value = str_replace(['pipeline.', '_', '.'], ['Pipeline ', ' ', ' • '], $value);

    return ucfirst(trim($value));
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$decodeMetadata = static function (mixed $metadata): array {
    if (!is_string($metadata) || trim($metadata) === '') {
        return [];
    }

    $decoded = json_decode($metadata, true);

    return is_array($decoded) ? $decoded : [];
};

$eventBadgeClass = static function (string $eventType): string {
    if ($eventType === 'retificacao') {
        return 'badge-neutral';
    }

    if (str_starts_with($eventType, 'pipeline.')) {
        return 'badge-info';
    }

    return 'badge-neutral';
};

$formatBytes = static function (int $size): string {
    if ($size <= 0) {
        return '0 B';
    }

    if ($size >= 1048576) {
        return number_format($size / 1048576, 2, ',', '.') . ' MB';
    }

    if ($size >= 1024) {
        return number_format($size / 1024, 1, ',', '.') . ' KB';
    }

    return (string) $size . ' B';
};

$buildTimelinePageUrl = static function (int $targetPage) use ($personId): string {
    return url('/people/show?id=' . $personId . '&timeline_page=' . $targetPage);
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
  <div class="header-row">
    <div>
      <h3>Timeline</h3>
      <p class="muted">Linha do tempo completa com histórico imutável e retificações.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/people/timeline/print?id=' . $personId)) ?>" target="_blank" rel="noopener">Imprimir timeline</a>
    </div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/timeline/store')) ?>" enctype="multipart/form-data" class="timeline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <div class="form-grid timeline-form-grid">
        <div class="field">
          <label for="event_type">Tipo de evento</label>
          <select id="event_type" name="event_type" required>
            <option value="">Selecione...</option>
            <?php foreach ($eventTypes as $type): ?>
              <option value="<?= e((string) ($type['name'] ?? '')) ?>"><?= e((string) ($type['description'] ?? $type['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="event_date">Data do evento</label>
          <input id="event_date" name="event_date" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="field field-wide">
          <label for="timeline_title">Título</label>
          <input id="timeline_title" name="title" type="text" minlength="3" maxlength="190" required>
        </div>
        <div class="field field-wide">
          <label for="timeline_description">Descrição</label>
          <textarea id="timeline_description" name="description" rows="4"></textarea>
        </div>
        <div class="field field-wide">
          <label for="timeline_attachments">Anexos (PDF/JPG/PNG até 10MB)</label>
          <input id="timeline_attachments" name="attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Registrar evento</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($timeline === []): ?>
    <p class="muted">Sem eventos registrados ainda.</p>
  <?php else: ?>
    <div class="timeline-list">
      <?php foreach ($timeline as $event): ?>
        <?php
          $eventType = (string) ($event['event_type'] ?? 'evento');
          $metadata = $decodeMetadata($event['metadata'] ?? null);
          $attachments = is_array($event['attachments'] ?? null) ? $event['attachments'] : [];
          $rectifiesEventId = isset($metadata['rectifies_event_id']) ? (int) $metadata['rectifies_event_id'] : 0;
          $eventId = (int) ($event['id'] ?? 0);
        ?>
        <article class="timeline-item">
          <div class="timeline-item-header">
            <div class="timeline-item-title">
              <strong><?= e((string) ($event['title'] ?? 'Evento')) ?></strong>
              <span class="badge <?= e($eventBadgeClass($eventType)) ?>"><?= e($eventTypeLabel($eventType)) ?></span>
            </div>
            <span class="muted"><?= e($formatDateTime((string) ($event['event_date'] ?? ''))) ?></span>
          </div>

          <?php if (trim((string) ($event['description'] ?? '')) !== ''): ?>
            <p class="timeline-item-description"><?= nl2br(e((string) $event['description'])) ?></p>
          <?php endif; ?>

          <?php if ($rectifiesEventId > 0): ?>
            <p class="muted">Retifica o evento #<?= e((string) $rectifiesEventId) ?> (evento original preservado).</p>
          <?php endif; ?>

          <?php if ($attachments !== []): ?>
            <div class="timeline-attachments">
              <strong>Anexos</strong>
              <ul class="attachments-list">
                <?php foreach ($attachments as $attachment): ?>
                  <li>
                    <a href="<?= e(url('/people/timeline/attachment?id=' . (int) ($attachment['id'] ?? 0) . '&person_id=' . $personId)) ?>">
                      <?= e((string) ($attachment['original_name'] ?? 'anexo')) ?>
                    </a>
                    <span class="muted">(<?= e($formatBytes((int) ($attachment['file_size'] ?? 0))) ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <p class="muted">Responsável: <?= e((string) ($event['created_by_name'] ?? 'Sistema')) ?></p>

          <?php if (($canManage ?? false) === true && $eventId > 0): ?>
            <details class="timeline-rectify-details">
              <summary>Retificar este evento</summary>
              <form method="post" action="<?= e(url('/people/timeline/rectify')) ?>" enctype="multipart/form-data" class="timeline-rectify-form">
                <?= csrf_field() ?>
                <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                <input type="hidden" name="source_event_id" value="<?= e((string) $eventId) ?>">
                <div class="field">
                  <label for="rectification_note_<?= e((string) $eventId) ?>">Justificativa da retificação</label>
                  <textarea id="rectification_note_<?= e((string) $eventId) ?>" name="rectification_note" rows="3" minlength="3" required></textarea>
                </div>
                <div class="field">
                  <label for="rectification_attachments_<?= e((string) $eventId) ?>">Anexos da retificação</label>
                  <input id="rectification_attachments_<?= e((string) $eventId) ?>" name="attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn-ghost">Registrar retificação</button>
                </div>
              </form>
            </details>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php
      $timelineTotal = (int) ($timelinePagination['total'] ?? 0);
      $timelinePage = (int) ($timelinePagination['page'] ?? 1);
      $timelinePerPage = max(1, (int) ($timelinePagination['per_page'] ?? 8));
      $timelinePages = max(1, (int) ($timelinePagination['pages'] ?? 1));
      $start = $timelineTotal > 0 ? (($timelinePage - 1) * $timelinePerPage) + 1 : 0;
      $end = min($timelineTotal, $timelinePage * $timelinePerPage);
    ?>
    <div class="pagination-row">
      <span class="muted">Exibindo <?= e((string) $start) ?>-<?= e((string) $end) ?> de <?= e((string) $timelineTotal) ?> eventos</span>
      <div class="pagination-links">
        <?php if ($timelinePage > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildTimelinePageUrl($timelinePage - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string) $timelinePage) ?> de <?= e((string) $timelinePages) ?></span>
        <?php if ($timelinePage < $timelinePages): ?>
          <a class="btn btn-outline" href="<?= e($buildTimelinePageUrl($timelinePage + 1)) ?>">Próxima</a>
        <?php endif; ?>
      </div>
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
