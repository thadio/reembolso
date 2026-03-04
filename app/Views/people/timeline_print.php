<?php

declare(strict_types=1);

$personId = (int) ($person['id'] ?? 0);
$events = is_array($timeline ?? null) ? $timeline : [];

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

$eventTypeLabel = static function (string $value): string {
    $value = str_replace(['pipeline.', '_', '.'], ['Pipeline ', ' ', ' • '], $value);

    return ucfirst(trim($value));
};

$eventBadgeClass = static function (string $eventType): string {
    if (str_starts_with($eventType, 'pipeline.')) {
        return 'badge badge-info';
    }

    return 'badge';
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
?>
<div class="print-shell">
  <header class="print-header">
    <div>
      <h1 class="print-title">Timeline da Pessoa #<?= e((string) $personId) ?></h1>
      <p class="muted"><?= e((string) ($person['name'] ?? 'Pessoa')) ?> · <?= e((string) ($person['status'] ?? '-')) ?></p>
      <p class="muted">Gerado em <?= e(date('d/m/Y H:i')) ?> por <?= e((string) ($authUser['name'] ?? 'Sistema')) ?></p>
    </div>
    <div class="print-actions">
      <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
      <a class="btn-print" href="<?= e(url('/people/show?id=' . $personId)) ?>">Voltar</a>
    </div>
  </header>

  <section class="summary-grid">
    <article class="summary-item"><strong>Órgão</strong><br><?= e((string) ($person['organ_name'] ?? '-')) ?></article>
    <article class="summary-item"><strong>Modalidade</strong><br><?= e((string) ($person['modality_name'] ?? '-')) ?></article>
    <article class="summary-item"><strong>Total de eventos</strong><br><?= e((string) count($events)) ?></article>
  </section>

  <?php if ($events === []): ?>
    <p class="muted">Sem eventos registrados.</p>
  <?php else: ?>
    <section class="timeline-list">
      <?php foreach ($events as $event): ?>
        <?php
          $eventType = (string) ($event['event_type'] ?? 'evento');
          $metadata = $decodeMetadata($event['metadata'] ?? null);
          $attachments = is_array($event['attachments'] ?? null) ? $event['attachments'] : [];
          $rectifiesEventId = isset($metadata['rectifies_event_id']) ? (int) $metadata['rectifies_event_id'] : 0;
        ?>
        <article class="timeline-item">
          <div class="timeline-row">
            <div>
              <strong><?= e((string) ($event['title'] ?? 'Evento')) ?></strong>
              <span class="<?= e($eventBadgeClass($eventType)) ?>"><?= e($eventTypeLabel($eventType)) ?></span>
            </div>
            <span class="muted"><?= e($formatDateTime((string) ($event['event_date'] ?? ''))) ?></span>
          </div>

          <?php if (trim((string) ($event['description'] ?? '')) !== ''): ?>
            <p><?= nl2br(e((string) $event['description'])) ?></p>
          <?php endif; ?>

          <?php if ($rectifiesEventId > 0): ?>
            <p class="muted">Retificação do evento #<?= e((string) $rectifiesEventId) ?>.</p>
          <?php endif; ?>

          <?php if ($attachments !== []): ?>
            <strong>Anexos</strong>
            <ul class="attachments">
              <?php foreach ($attachments as $attachment): ?>
                <li><?= e((string) ($attachment['original_name'] ?? 'anexo')) ?> (<?= e($formatBytes((int) ($attachment['file_size'] ?? 0))) ?>)</li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <p class="muted">Responsável: <?= e((string) ($event['created_by_name'] ?? 'Sistema')) ?></p>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
