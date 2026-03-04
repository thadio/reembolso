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
$documents = $documents ?? [
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 8,
        'pages' => 1,
    ],
    'document_types' => [],
];
$documentItems = $documents['items'] ?? [];
$documentsPagination = $documents['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$documentTypes = $documents['document_types'] ?? [];
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

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
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

$buildTimelinePageUrl = static function (int $targetPage) use ($personId, $documentsPagination): string {
    $documentsPage = max(1, (int) ($documentsPagination['page'] ?? 1));

    return url('/people/show?id=' . $personId . '&timeline_page=' . $targetPage . '&documents_page=' . $documentsPage);
};

$buildDocumentsPageUrl = static function (int $targetPage) use ($personId, $timelinePagination): string {
    $timelinePage = max(1, (int) ($timelinePagination['page'] ?? 1));

    return url('/people/show?id=' . $personId . '&timeline_page=' . $timelinePage . '&documents_page=' . $targetPage);
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

<div class="card">
  <div class="header-row">
    <div>
      <h3>Documentos</h3>
      <p class="muted">Dossiê documental da pessoa com upload seguro e download protegido.</p>
    </div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/documents/store')) ?>" enctype="multipart/form-data" class="document-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <div class="form-grid">
        <div class="field">
          <label for="document_type_id">Tipo de documento</label>
          <select id="document_type_id" name="document_type_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($documentTypes as $type): ?>
              <option value="<?= e((string) ((int) ($type['id'] ?? 0))) ?>">
                <?= e((string) ($type['name'] ?? 'Tipo')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="document_date">Data do documento</label>
          <input id="document_date" name="document_date" type="date">
        </div>
        <div class="field">
          <label for="document_title">Título (opcional)</label>
          <input id="document_title" name="title" type="text" maxlength="190" placeholder="Ex.: Ofício 123/2026">
        </div>
        <div class="field">
          <label for="document_reference_sei">Referência SEI</label>
          <input id="document_reference_sei" name="reference_sei" type="text" maxlength="120" placeholder="00000.000000/2026-00">
        </div>
        <div class="field field-wide">
          <label for="document_tags">Tags</label>
          <input id="document_tags" name="tags" type="text" placeholder="oficio, resposta, cdo">
        </div>
        <div class="field field-wide">
          <label for="document_notes">Observações</label>
          <textarea id="document_notes" name="notes" rows="3"></textarea>
        </div>
        <div class="field field-wide">
          <label for="document_files">Arquivos (PDF/JPG/PNG até 10MB)</label>
          <div class="dropzone" data-input-id="document_files">
            <p class="dropzone-text muted">Arraste e solte arquivos aqui ou clique para selecionar.</p>
            <input id="document_files" class="dropzone-input" name="files[]" type="file" multiple required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enviar documentos</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($documentItems === []): ?>
    <p class="muted">Nenhum documento registrado para esta pessoa.</p>
  <?php else: ?>
    <div class="document-list">
      <?php foreach ($documentItems as $document): ?>
        <article class="document-item">
          <div class="document-item-header">
            <div class="document-title-wrap">
              <strong><?= e((string) ($document['title'] ?? 'Documento')) ?></strong>
              <span class="badge badge-neutral"><?= e((string) ($document['document_type_name'] ?? 'Tipo')) ?></span>
            </div>
            <a class="btn btn-ghost" href="<?= e(url('/people/documents/download?id=' . (int) ($document['id'] ?? 0) . '&person_id=' . $personId)) ?>">Baixar</a>
          </div>
          <p class="muted">Arquivo: <?= e((string) ($document['original_name'] ?? '-')) ?> (<?= e($formatBytes((int) ($document['file_size'] ?? 0))) ?>)</p>
          <?php if (trim((string) ($document['reference_sei'] ?? '')) !== ''): ?>
            <p class="muted">SEI: <?= e((string) $document['reference_sei']) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['document_date'] ?? '')) !== ''): ?>
            <p class="muted">Data do documento: <?= e($formatDate((string) $document['document_date'])) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['tags'] ?? '')) !== ''): ?>
            <p class="muted">Tags: <?= e((string) $document['tags']) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['notes'] ?? '')) !== ''): ?>
            <p class="muted">Observações: <?= nl2br(e((string) $document['notes'])) ?></p>
          <?php endif; ?>
          <p class="muted">Enviado por: <?= e((string) ($document['uploaded_by_name'] ?? 'Sistema')) ?> em <?= e($formatDateTime((string) ($document['created_at'] ?? ''))) ?></p>
        </article>
      <?php endforeach; ?>
    </div>

    <?php
      $documentsTotal = (int) ($documentsPagination['total'] ?? 0);
      $documentsPage = (int) ($documentsPagination['page'] ?? 1);
      $documentsPerPage = max(1, (int) ($documentsPagination['per_page'] ?? 8));
      $documentsPages = max(1, (int) ($documentsPagination['pages'] ?? 1));
      $documentsStart = $documentsTotal > 0 ? (($documentsPage - 1) * $documentsPerPage) + 1 : 0;
      $documentsEnd = min($documentsTotal, $documentsPage * $documentsPerPage);
    ?>
    <div class="pagination-row">
      <span class="muted">Exibindo <?= e((string) $documentsStart) ?>-<?= e((string) $documentsEnd) ?> de <?= e((string) $documentsTotal) ?> documentos</span>
      <div class="pagination-links">
        <?php if ($documentsPage > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildDocumentsPageUrl($documentsPage - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string) $documentsPage) ?> de <?= e((string) $documentsPages) ?></span>
        <?php if ($documentsPage < $documentsPages): ?>
          <a class="btn btn-outline" href="<?= e($buildDocumentsPageUrl($documentsPage + 1)) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card card-placeholder">
  <h3>Custos e Auditoria</h3>
  <p class="muted">Aba será expandida nas próximas fases.</p>
</div>
