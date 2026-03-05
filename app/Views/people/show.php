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
    'sensitivity_options' => [],
];
$documentItems = $documents['items'] ?? [];
$documentsPagination = $documents['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$documentTypes = $documents['document_types'] ?? [];
$documentSensitivityOptions = $documents['sensitivity_options'] ?? [];
$canViewSensitiveDocuments = ($canViewSensitiveDocuments ?? false) === true;
if ($documentSensitivityOptions === []) {
    $documentSensitivityOptions = [['value' => 'public', 'label' => 'Publico']];
}
$costs = $costs ?? [
    'active_plan' => null,
    'items' => [],
    'summary' => [
        'monthly_total' => 0,
        'annualized_total' => 0,
        'items_count' => 0,
    ],
    'versions' => [],
    'previous_plan' => null,
    'comparison' => [
        'monthly_delta' => null,
        'annualized_delta' => null,
        'previous_version_number' => null,
    ],
];
$activeCostPlan = $costs['active_plan'] ?? null;
$costItems = $costs['items'] ?? [];
$costSummary = $costs['summary'] ?? ['monthly_total' => 0, 'annualized_total' => 0, 'items_count' => 0];
$costVersions = $costs['versions'] ?? [];
$costComparison = $costs['comparison'] ?? ['monthly_delta' => null, 'annualized_delta' => null, 'previous_version_number' => null];
$conciliation = $conciliation ?? [
    'active_plan' => null,
    'summary' => [
        'current_month' => '',
        'months_analyzed' => 0,
        'expected_current' => 0,
        'actual_posted_current' => 0,
        'actual_paid_current' => 0,
        'deviation_posted_current' => 0,
        'deviation_paid_current' => 0,
        'expected_window_total' => 0,
        'actual_posted_window_total' => 0,
        'actual_paid_window_total' => 0,
        'deviation_posted_window_total' => 0,
        'deviation_paid_window_total' => 0,
    ],
    'rows' => [],
];
$conciliationSummary = $conciliation['summary'] ?? [
    'current_month' => '',
    'months_analyzed' => 0,
    'expected_current' => 0,
    'actual_posted_current' => 0,
    'actual_paid_current' => 0,
    'deviation_posted_current' => 0,
    'deviation_paid_current' => 0,
    'expected_window_total' => 0,
    'actual_posted_window_total' => 0,
    'actual_paid_window_total' => 0,
    'deviation_posted_window_total' => 0,
    'deviation_paid_window_total' => 0,
];
$conciliationRows = $conciliation['rows'] ?? [];
$reimbursements = $reimbursements ?? [
    'summary' => [
        'total_entries' => 0,
        'pending_total' => 0,
        'paid_total' => 0,
        'canceled_total' => 0,
        'overdue_total' => 0,
        'pending_count' => 0,
        'paid_count' => 0,
        'canceled_count' => 0,
        'overdue_count' => 0,
        'boletos_count' => 0,
        'payments_count' => 0,
        'adjustments_count' => 0,
    ],
    'items' => [],
];
$reimbursementSummary = $reimbursements['summary'] ?? [
    'total_entries' => 0,
    'pending_total' => 0,
    'paid_total' => 0,
    'canceled_total' => 0,
    'overdue_total' => 0,
    'pending_count' => 0,
    'paid_count' => 0,
    'canceled_count' => 0,
    'overdue_count' => 0,
    'boletos_count' => 0,
    'payments_count' => 0,
    'adjustments_count' => 0,
];
$reimbursementItems = $reimbursements['items'] ?? [];
$audit = $audit ?? [
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 10,
        'pages' => 1,
    ],
    'filters' => [
        'entity' => '',
        'action' => '',
        'q' => '',
        'from_date' => '',
        'to_date' => '',
    ],
    'options' => [
        'entities' => [],
        'actions' => [],
    ],
];
$auditItems = $audit['items'] ?? [];
$auditPagination = $audit['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$auditFilters = $audit['filters'] ?? ['entity' => '', 'action' => '', 'q' => '', 'from_date' => '', 'to_date' => ''];
$auditOptions = $audit['options'] ?? ['entities' => [], 'actions' => []];
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

$formatMoney = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatSignedMoney = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    $prefix = $numeric > 0 ? '+' : '';

    return $prefix . 'R$ ' . number_format($numeric, 2, ',', '.');
};

$deviationClass = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    if ($numeric > 0.009) {
        return 'text-danger';
    }

    if ($numeric < -0.009) {
        return 'text-success';
    }

    return 'text-muted';
};

$costTypeLabel = static function (string $type): string {
    return match ($type) {
        'mensal' => 'Mensal',
        'anual' => 'Anual',
        'unico' => 'Único',
        default => ucfirst($type),
    };
};

$reimbursementTypeLabel = static function (string $type): string {
    return match ($type) {
        'boleto' => 'Boleto',
        'pagamento' => 'Pagamento',
        'ajuste' => 'Ajuste',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
};

$reimbursementStatusLabel = static function (string $status, bool $overdue = false): string {
    if ($overdue) {
        return 'Vencido';
    }

    return match ($status) {
        'pendente' => 'Pendente',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$reimbursementStatusClass = static function (string $status, bool $overdue = false): string {
    if ($overdue) {
        return 'badge-danger';
    }

    return match ($status) {
        'pendente' => 'badge-warning',
        'pago' => 'badge-success',
        'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$documentSensitivityLabel = static function (string $sensitivity): string {
    return match ($sensitivity) {
        'restricted' => 'Restrito',
        'sensitive' => 'Sensivel',
        default => 'Publico',
    };
};

$documentSensitivityBadgeClass = static function (string $sensitivity): string {
    return match ($sensitivity) {
        'restricted' => 'badge-warning',
        'sensitive' => 'badge-danger',
        default => 'badge-neutral',
    };
};

$auditEntityLabel = static function (string $entity): string {
    return match ($entity) {
        'person' => 'Pessoa',
        'assignment' => 'Movimentação',
        'timeline_event' => 'Timeline',
        'document' => 'Documento',
        'cost_plan' => 'Plano de custos',
        'cost_plan_item' => 'Item de custo',
        'reimbursement_entry' => 'Reembolso real',
        default => ucfirst(str_replace('_', ' ', $entity)),
    };
};

$prettyJson = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '-';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return $value;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($pretty) && trim($pretty) !== '' ? $pretty : '-';
};

$buildProfileUrl = static function (array $overrides = [], array $remove = []) use ($personId, $timelinePagination, $documentsPagination, $auditPagination, $auditFilters): string {
    $timelinePage = max(1, (int) ($timelinePagination['page'] ?? 1));
    $documentsPage = max(1, (int) ($documentsPagination['page'] ?? 1));
    $auditPage = max(1, (int) ($auditPagination['page'] ?? 1));

    $params = [
        'id' => $personId,
        'timeline_page' => $timelinePage,
        'documents_page' => $documentsPage,
        'audit_page' => $auditPage,
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/people/show?' . http_build_query($params));
};

$buildTimelinePageUrl = static fn (int $targetPage): string => $buildProfileUrl(['timeline_page' => $targetPage]);
$buildDocumentsPageUrl = static fn (int $targetPage): string => $buildProfileUrl(['documents_page' => $targetPage]);
$buildAuditPageUrl = static fn (int $targetPage): string => $buildProfileUrl(['audit_page' => $targetPage]);
$buildAuditExportUrl = static function () use ($personId, $auditFilters): string {
    $params = [
        'person_id' => $personId,
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/people/audit/export?' . http_build_query($params));
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
    <span class="tab-chip">Conciliação</span>
    <span class="tab-chip">Financeiro real</span>
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

  <?php if (!$canViewSensitiveDocuments): ?>
    <p class="muted">Somente documentos classificados como Publico sao exibidos para o seu perfil.</p>
  <?php endif; ?>

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
          <label for="document_sensitivity_level">Sensibilidade</label>
          <select id="document_sensitivity_level" name="sensitivity_level" required>
            <?php foreach ($documentSensitivityOptions as $option): ?>
              <option value="<?= e((string) ($option['value'] ?? 'public')) ?>" <?= (($option['value'] ?? '') === 'public') ? 'selected' : '' ?>>
                <?= e((string) ($option['label'] ?? 'Publico')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$canViewSensitiveDocuments): ?>
            <small class="muted">Classificacoes Restrito/Sensivel exigem permissao adicional.</small>
          <?php endif; ?>
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
        <?php $documentSensitivity = mb_strtolower(trim((string) ($document['sensitivity_level'] ?? 'public'))); ?>
        <article class="document-item">
          <div class="document-item-header">
            <div class="document-title-wrap">
              <strong><?= e((string) ($document['title'] ?? 'Documento')) ?></strong>
              <span class="badge badge-neutral"><?= e((string) ($document['document_type_name'] ?? 'Tipo')) ?></span>
              <span class="badge <?= e($documentSensitivityBadgeClass($documentSensitivity)) ?>">
                <?= e($documentSensitivityLabel($documentSensitivity)) ?>
              </span>
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

<div class="card">
  <div class="header-row">
    <div>
      <h3>Custos previstos</h3>
      <p class="muted">Planejamento financeiro por versão com histórico e comparação.</p>
    </div>
    <div class="actions-inline">
      <?php if ($activeCostPlan !== null): ?>
        <span class="badge badge-info">Versão ativa: V<?= e((string) ((int) ($activeCostPlan['version_number'] ?? 0))) ?></span>
      <?php else: ?>
        <span class="badge badge-neutral">Sem versão ativa</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Total mensal equivalente</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($costSummary['monthly_total'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Total anualizado</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($costSummary['annualized_total'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Itens na versão ativa</p>
      <p class="kpi-value"><?= e((string) ((int) ($costSummary['items_count'] ?? 0))) ?></p>
    </article>
  </div>

  <?php if (($costComparison['previous_version_number'] ?? null) !== null): ?>
    <p class="muted">
      Comparação com V<?= e((string) ((int) $costComparison['previous_version_number'])) ?>:
      mensal <?= e($formatMoney((float) ($costComparison['monthly_delta'] ?? 0))) ?> |
      anualizado <?= e($formatMoney((float) ($costComparison['annualized_delta'] ?? 0))) ?>
    </p>
  <?php endif; ?>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/costs/version/create')) ?>" class="cost-version-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <div class="form-grid">
        <div class="field">
          <label for="cost_version_label">Rótulo da nova versão</label>
          <input id="cost_version_label" name="label" type="text" maxlength="190" placeholder="Ex.: Revisão abril/2026">
        </div>
        <div class="field">
          <label for="cost_clone_current">Clonar itens atuais</label>
          <select id="cost_clone_current" name="clone_current">
            <option value="1">Sim</option>
            <option value="0">Não</option>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-outline">Criar nova versão</button>
      </div>
    </form>

    <form method="post" action="<?= e(url('/people/costs/item/store')) ?>" class="cost-item-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <div class="form-grid">
        <div class="field">
          <label for="cost_item_name">Item de custo</label>
          <input id="cost_item_name" name="item_name" type="text" minlength="3" maxlength="190" required placeholder="Ex.: Auxílio transporte">
        </div>
        <div class="field">
          <label for="cost_type">Tipo</label>
          <select id="cost_type" name="cost_type">
            <option value="mensal">Mensal</option>
            <option value="anual">Anual</option>
            <option value="unico">Único</option>
          </select>
        </div>
        <div class="field">
          <label for="cost_amount">Valor</label>
          <input id="cost_amount" name="amount" type="number" min="0" step="0.01" required>
        </div>
        <div class="field">
          <label for="cost_start_date">Início da vigência</label>
          <input id="cost_start_date" name="start_date" type="date">
        </div>
        <div class="field">
          <label for="cost_end_date">Fim da vigência</label>
          <input id="cost_end_date" name="end_date" type="date">
        </div>
        <div class="field field-wide">
          <label for="cost_notes">Observações</label>
          <textarea id="cost_notes" name="notes" rows="3"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Adicionar item</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($costItems === []): ?>
    <p class="muted">Nenhum item de custo registrado na versão ativa.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Tipo</th>
            <th>Valor informado</th>
            <th>Início</th>
            <th>Fim</th>
            <th>Responsável</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($costItems as $item): ?>
            <tr>
              <td>
                <strong><?= e((string) ($item['item_name'] ?? '-')) ?></strong>
                <?php if (trim((string) ($item['notes'] ?? '')) !== ''): ?>
                  <div class="muted"><?= e((string) $item['notes']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($costTypeLabel((string) ($item['cost_type'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($item['amount'] ?? 0))) ?></td>
              <td><?= e($formatDate((string) ($item['start_date'] ?? ''))) ?></td>
              <td><?= e($formatDate((string) ($item['end_date'] ?? ''))) ?></td>
              <td><?= e((string) ($item['created_by_name'] ?? 'Sistema')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($costVersions !== []): ?>
    <div class="cost-versions">
      <h4>Histórico de versões</h4>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Versão</th>
              <th>Rótulo</th>
              <th>Itens</th>
              <th>Total mensal</th>
              <th>Total anualizado</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($costVersions as $version): ?>
              <tr>
                <td>V<?= e((string) ((int) ($version['version_number'] ?? 0))) ?></td>
                <td><?= e((string) ($version['label'] ?? '-')) ?></td>
                <td><?= e((string) ((int) ($version['items_count'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($version['monthly_total'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($version['annualized_total'] ?? 0))) ?></td>
                <td>
                  <?php if ((int) ($version['is_active'] ?? 0) === 1): ?>
                    <span class="badge badge-info">Ativa</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Histórica</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Conciliação previsto x real</h3>
      <p class="muted">Comparativo por competência entre custos previstos (versão ativa) e reembolsos reais.</p>
    </div>
    <?php if ($activeCostPlan !== null): ?>
      <span class="badge badge-info">Base prevista: V<?= e((string) ((int) ($activeCostPlan['version_number'] ?? 0))) ?></span>
    <?php endif; ?>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Previsto (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['expected_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Real lançado (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['actual_posted_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Real pago (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['actual_paid_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Desvio lançado (mês atual)</p>
      <p class="kpi-value <?= e($deviationClass($conciliationSummary['deviation_posted_current'] ?? 0)) ?>">
        <?= e($formatSignedMoney((float) ($conciliationSummary['deviation_posted_current'] ?? 0))) ?>
      </p>
    </article>
  </div>

  <?php if ($conciliationRows === []): ?>
    <p class="muted">Sem dados suficientes para conciliação por competência.</p>
  <?php else: ?>
    <p class="muted">
      Janela analisada: <?= e((string) ((int) ($conciliationSummary['months_analyzed'] ?? 0))) ?> competência(s) |
      Desvio acumulado (lançado): <span class="<?= e($deviationClass($conciliationSummary['deviation_posted_window_total'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($conciliationSummary['deviation_posted_window_total'] ?? 0))) ?></span>
    </p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Competência</th>
            <th>Previsto</th>
            <th>Real lançado</th>
            <th>Real pago</th>
            <th>Desvio lançado</th>
            <th>Desvio pago</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($conciliationRows as $row): ?>
            <tr>
              <td><?= e($formatDate((string) ($row['competence'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($row['expected'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['actual_posted'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['actual_paid'] ?? 0))) ?></td>
              <td class="<?= e($deviationClass($row['deviation_posted'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($row['deviation_posted'] ?? 0))) ?></td>
              <td class="<?= e($deviationClass($row['deviation_paid'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($row['deviation_paid'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Reembolsos reais</h3>
      <p class="muted">Controle financeiro de boletos, pagamentos e ajustes executados.</p>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Pendente</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['pending_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['pending_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Pago</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['paid_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['paid_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Vencido</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['overdue_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['overdue_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Total de lançamentos</p>
      <p class="kpi-value"><?= e((string) ((int) ($reimbursementSummary['total_entries'] ?? 0))) ?></p>
      <p class="muted">Boletos: <?= e((string) ((int) ($reimbursementSummary['boletos_count'] ?? 0))) ?> | Pagamentos: <?= e((string) ((int) ($reimbursementSummary['payments_count'] ?? 0))) ?></p>
    </article>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/reimbursements/store')) ?>" class="reimbursement-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <div class="form-grid">
        <div class="field">
          <label for="reimbursement_entry_type">Tipo</label>
          <select id="reimbursement_entry_type" name="entry_type">
            <option value="boleto">Boleto</option>
            <option value="pagamento">Pagamento</option>
            <option value="ajuste">Ajuste</option>
          </select>
        </div>
        <div class="field">
          <label for="reimbursement_status">Status</label>
          <select id="reimbursement_status" name="status">
            <option value="pendente">Pendente</option>
            <option value="pago">Pago</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div class="field field-wide">
          <label for="reimbursement_title">Título do lançamento</label>
          <input id="reimbursement_title" name="title" type="text" minlength="3" maxlength="190" required placeholder="Ex.: Boleto órgão de origem - março/2026">
        </div>
        <div class="field">
          <label for="reimbursement_amount">Valor</label>
          <input id="reimbursement_amount" name="amount" type="number" min="0" step="0.01" required>
        </div>
        <div class="field">
          <label for="reimbursement_reference_month">Competência</label>
          <input id="reimbursement_reference_month" name="reference_month" type="month">
        </div>
        <div class="field">
          <label for="reimbursement_due_date">Vencimento</label>
          <input id="reimbursement_due_date" name="due_date" type="date">
        </div>
        <div class="field">
          <label for="reimbursement_paid_at">Data do pagamento</label>
          <input id="reimbursement_paid_at" name="paid_at" type="date">
        </div>
        <div class="field field-wide">
          <label for="reimbursement_notes">Observações</label>
          <textarea id="reimbursement_notes" name="notes" rows="3"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Registrar lançamento</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($reimbursementItems === []): ?>
    <p class="muted">Nenhum lançamento financeiro registrado para esta pessoa.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Título</th>
            <th>Competência</th>
            <th>Valor</th>
            <th>Status</th>
            <th>Vencimento</th>
            <th>Pago em</th>
            <th>Responsável</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reimbursementItems as $entry): ?>
            <?php
              $entryStatus = (string) ($entry['status'] ?? '');
              $dueDateRaw = (string) ($entry['due_date'] ?? '');
              $isOverdue = $entryStatus === 'pendente'
                  && trim($dueDateRaw) !== ''
                  && strtotime($dueDateRaw) !== false
                  && strtotime($dueDateRaw) < strtotime(date('Y-m-d'));
              $canMarkAsPaid = (($canManage ?? false) === true)
                  && $entryStatus !== 'pago'
                  && $entryStatus !== 'cancelado';
            ?>
            <tr>
              <td><?= e($reimbursementTypeLabel((string) ($entry['entry_type'] ?? ''))) ?></td>
              <td>
                <strong><?= e((string) ($entry['title'] ?? '-')) ?></strong>
                <?php if (trim((string) ($entry['notes'] ?? '')) !== ''): ?>
                  <div class="muted"><?= nl2br(e((string) $entry['notes'])) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($formatDate((string) ($entry['reference_month'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($entry['amount'] ?? 0))) ?></td>
              <td>
                <span class="badge <?= e($reimbursementStatusClass($entryStatus, $isOverdue)) ?>">
                  <?= e($reimbursementStatusLabel($entryStatus, $isOverdue)) ?>
                </span>
              </td>
              <td><?= e($formatDate($dueDateRaw)) ?></td>
              <td><?= e($formatDateTime((string) ($entry['paid_at'] ?? ''))) ?></td>
              <td><?= e((string) ($entry['created_by_name'] ?? 'Sistema')) ?></td>
              <td class="actions-cell">
                <?php if ($canMarkAsPaid): ?>
                  <form method="post" action="<?= e(url('/people/reimbursements/mark-paid')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                    <input type="hidden" name="entry_id" value="<?= e((string) ((int) ($entry['id'] ?? 0))) ?>">
                    <input type="hidden" name="paid_at" value="<?= e(date('Y-m-d')) ?>">
                    <button type="submit" class="btn btn-ghost">Marcar como pago</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Auditoria</h3>
      <p class="muted">Histórico de alterações e ações relacionadas a esta pessoa.</p>
    </div>
  </div>

  <?php if (($canViewAudit ?? false) !== true): ?>
    <p class="muted">Você não possui permissão para visualizar a trilha de auditoria.</p>
  <?php else: ?>
    <form method="get" action="<?= e(url('/people/show')) ?>" class="audit-filters">
      <input type="hidden" name="id" value="<?= e((string) $personId) ?>">
      <input type="hidden" name="timeline_page" value="<?= e((string) ((int) ($timelinePagination['page'] ?? 1))) ?>">
      <input type="hidden" name="documents_page" value="<?= e((string) ((int) ($documentsPagination['page'] ?? 1))) ?>">
      <input type="hidden" name="audit_page" value="1">
      <div class="form-grid">
        <div class="field">
          <label for="audit_q">Busca</label>
          <input id="audit_q" name="audit_q" type="text" value="<?= e((string) ($auditFilters['q'] ?? '')) ?>" placeholder="Entidade, ação ou usuário">
        </div>
        <div class="field">
          <label for="audit_entity">Entidade</label>
          <select id="audit_entity" name="audit_entity">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['entities'] ?? []) as $entityOption): ?>
              <?php $entityOption = (string) $entityOption; ?>
              <option value="<?= e($entityOption) ?>" <?= $entityOption === (string) ($auditFilters['entity'] ?? '') ? 'selected' : '' ?>>
                <?= e($auditEntityLabel($entityOption)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_action">Ação</label>
          <select id="audit_action" name="audit_action">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['actions'] ?? []) as $actionOption): ?>
              <?php $actionOption = (string) $actionOption; ?>
              <option value="<?= e($actionOption) ?>" <?= $actionOption === (string) ($auditFilters['action'] ?? '') ? 'selected' : '' ?>>
                <?= e($actionOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_from">De</label>
          <input id="audit_from" name="audit_from" type="date" value="<?= e((string) ($auditFilters['from_date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="audit_to">Até</label>
          <input id="audit_to" name="audit_to" type="date" value="<?= e((string) ($auditFilters['to_date'] ?? '')) ?>">
        </div>
      </div>
      <div class="form-actions">
        <a class="btn btn-outline" href="<?= e($buildProfileUrl(['audit_page' => 1], ['audit_entity', 'audit_action', 'audit_q', 'audit_from', 'audit_to'])) ?>">Limpar</a>
        <a class="btn btn-outline" href="<?= e($buildAuditExportUrl()) ?>">Exportar CSV</a>
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>

    <?php if ($auditItems === []): ?>
      <p class="muted">Nenhum registro de auditoria encontrado para os filtros informados.</p>
    <?php else: ?>
      <div class="audit-list">
        <?php foreach ($auditItems as $entry): ?>
          <?php
            $entity = (string) ($entry['entity'] ?? '');
            $entityId = isset($entry['entity_id']) ? (int) $entry['entity_id'] : 0;
            $beforeData = $prettyJson($entry['before_data'] ?? null);
            $afterData = $prettyJson($entry['after_data'] ?? null);
            $metadataData = $prettyJson($entry['metadata'] ?? null);
            $hasDetails = $beforeData !== '-' || $afterData !== '-' || $metadataData !== '-';
          ?>
          <article class="audit-item">
            <div class="audit-item-head">
              <div class="audit-item-title">
                <span class="badge badge-neutral"><?= e($auditEntityLabel($entity)) ?></span>
                <strong><?= e((string) ($entry['action'] ?? '-')) ?></strong>
                <?php if ($entityId > 0): ?>
                  <span class="muted">#<?= e((string) $entityId) ?></span>
                <?php endif; ?>
              </div>
              <span class="muted"><?= e($formatDateTime((string) ($entry['created_at'] ?? ''))) ?></span>
            </div>

            <div class="audit-meta">
              <span><strong>Usuário:</strong> <?= e((string) ($entry['user_name'] ?? 'Sistema')) ?></span>
              <span><strong>IP:</strong> <?= e((string) ($entry['ip'] ?? '-')) ?></span>
            </div>

            <?php if ($hasDetails): ?>
              <details class="audit-details">
                <summary>Ver dados</summary>
                <div class="audit-payload-grid">
                  <div>
                    <strong>Antes</strong>
                    <pre><?= e($beforeData) ?></pre>
                  </div>
                  <div>
                    <strong>Depois</strong>
                    <pre><?= e($afterData) ?></pre>
                  </div>
                  <div class="audit-payload-wide">
                    <strong>Metadata</strong>
                    <pre><?= e($metadataData) ?></pre>
                  </div>
                </div>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <?php
        $auditTotal = (int) ($auditPagination['total'] ?? 0);
        $auditPage = (int) ($auditPagination['page'] ?? 1);
        $auditPerPage = max(1, (int) ($auditPagination['per_page'] ?? 10));
        $auditPages = max(1, (int) ($auditPagination['pages'] ?? 1));
        $auditStart = $auditTotal > 0 ? (($auditPage - 1) * $auditPerPage) + 1 : 0;
        $auditEnd = min($auditTotal, $auditPage * $auditPerPage);
      ?>
      <div class="pagination-row">
        <span class="muted">Exibindo <?= e((string) $auditStart) ?>-<?= e((string) $auditEnd) ?> de <?= e((string) $auditTotal) ?> registros</span>
        <div class="pagination-links">
          <?php if ($auditPage > 1): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage - 1)) ?>">Anterior</a>
          <?php endif; ?>
          <span class="muted">Página <?= e((string) $auditPage) ?> de <?= e((string) $auditPages) ?></span>
          <?php if ($auditPage < $auditPages): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage + 1)) ?>">Próxima</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
