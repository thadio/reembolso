<?php

declare(strict_types=1);

$meta = is_array($meta ?? null) ? $meta : [];
$canManage = (bool) ($canManage ?? false);

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$channelLabel = static function (?string $channel): string {
    return match ((string) $channel) {
        'sei' => 'SEI',
        'email' => 'Email',
        'protocolo_fisico' => 'Protocolo fisico',
        'sistema_externo' => 'Sistema externo',
        'outro' => 'Outro',
        default => '-',
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Metadado formal #<?= e((string) ($meta['id'] ?? 0)) ?></h2>
      <p class="muted">
        Pessoa: <a href="<?= e(url('/people/show?id=' . (int) ($meta['person_id'] ?? 0))) ?>"><?= e((string) ($meta['person_name'] ?? '-')) ?></a>
        · Orgao: <?= e((string) ($meta['organ_name'] ?? '-')) ?>
      </p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/process-meta')) ?>">Voltar</a>
      <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(url('/process-meta/edit?id=' . (int) ($meta['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Numero de oficio:</strong> <?= e((string) ($meta['office_number'] ?? '-')) ?></div>
    <div><strong>Data de envio:</strong> <?= e($formatDate((string) ($meta['office_sent_at'] ?? ''))) ?></div>
    <div><strong>Canal:</strong> <?= e($channelLabel((string) ($meta['office_channel'] ?? ''))) ?></div>
    <div><strong>Protocolo:</strong> <?= e((string) ($meta['office_protocol'] ?? '-')) ?></div>
    <div><strong>Edicao DOU:</strong> <?= e((string) ($meta['dou_edition'] ?? '-')) ?></div>
    <div><strong>Data publicacao DOU:</strong> <?= e($formatDate((string) ($meta['dou_published_at'] ?? ''))) ?></div>
    <div class="details-wide">
      <strong>Link DOU:</strong>
      <?php if (!empty($meta['dou_link'])): ?>
        <a href="<?= e((string) ($meta['dou_link'] ?? '')) ?>" target="_blank" rel="noopener"><?= e((string) ($meta['dou_link'] ?? '')) ?></a>
      <?php else: ?>
        -
      <?php endif; ?>
    </div>
    <div><strong>Entrada oficial MTE:</strong> <?= e($formatDate((string) ($meta['mte_entry_date'] ?? ''))) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($meta['created_by_name'] ?? 'N/I')) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDateTime((string) ($meta['created_at'] ?? ''))) ?></div>
    <div><strong>Atualizado em:</strong> <?= e($formatDateTime((string) ($meta['updated_at'] ?? ''))) ?></div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($meta['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="card">
  <h3>Anexo DOU</h3>
  <?php if (!empty($meta['dou_attachment_storage_path'])): ?>
    <p>
      Arquivo: <strong><?= e((string) ($meta['dou_attachment_original_name'] ?? '-')) ?></strong>
      <?php if (($meta['dou_attachment_file_size'] ?? null) !== null): ?>
        <span class="muted">(<?= e((string) ($meta['dou_attachment_file_size'] ?? 0)) ?> bytes)</span>
      <?php endif; ?>
    </p>
    <a class="btn btn-primary" href="<?= e(url('/process-meta/dou-attachment?id=' . (int) ($meta['id'] ?? 0))) ?>">Baixar anexo DOU</a>
  <?php else: ?>
    <div class="empty-state">
      <p>Sem anexo DOU neste registro.</p>
    </div>
  <?php endif; ?>
</div>
