<?php

declare(strict_types=1);

$document = is_array($document ?? null) ? $document : [];

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Oficio gerado #<?= e((string) ($document['id'] ?? 0)) ?></h2>
      <p class="muted">
        Template <?= e((string) ($document['template_name'] ?? '-')) ?>
        (V<?= e((string) ($document['version_number'] ?? '-')) ?>)
      </p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/office-templates/show?id=' . (int) ($document['template_id'] ?? 0))) ?>">Voltar</a>
      <a class="btn btn-primary" href="<?= e(url('/office-documents/print?id=' . (int) ($document['id'] ?? 0))) ?>" target="_blank" rel="noopener">Abrir para impressao</a>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Assunto:</strong> <?= e((string) ($document['rendered_subject'] ?? '-')) ?></div>
    <div><strong>Pessoa:</strong> <?= e((string) ($document['person_name'] ?? '-')) ?></div>
    <div><strong>Orgao:</strong> <?= e((string) ($document['organ_name'] ?? '-')) ?></div>
    <div><strong>Gerado por:</strong> <?= e((string) ($document['generated_by_name'] ?? 'N/I')) ?></div>
    <div><strong>Gerado em:</strong> <?= e($formatDate((string) ($document['created_at'] ?? ''))) ?></div>
    <div><strong>Chave template:</strong> <code><?= e((string) ($document['template_key'] ?? '-')) ?></code></div>
  </div>
</div>

<div class="card">
  <h3>Conteudo renderizado</h3>
  <div class="document-item">
    <h4><?= e((string) ($document['rendered_subject'] ?? '')) ?></h4>
    <div><?= (string) ($document['rendered_html'] ?? '') ?></div>
  </div>
</div>

<div class="card">
  <h3>Contexto de merge (JSON)</h3>
  <pre><?= e((string) ($document['context_json'] ?? '{}')) ?></pre>
</div>
