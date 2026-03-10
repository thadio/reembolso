<?php

declare(strict_types=1);

$rows = $rows ?? [];

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
      <p class="muted">Defina o limiar de risco e vencimento por etapa do pipeline, com notificacao opcional por email.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/sla-alerts')) ?>">Voltar ao painel</a>
  </div>
</div>

<?php if ($rows === []): ?>
  <div class="card">
    <div class="empty-state">
      <p>Nenhuma etapa elegivel para SLA foi encontrada.</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($rows as $row): ?>
    <div class="card">
      <form method="post" action="<?= e(url('/sla-alerts/rules/upsert')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="status_code" value="<?= e((string) ($row['status_code'] ?? '')) ?>">

        <div class="field field-wide">
          <label>Etapa</label>
          <div>
            <strong><?= e((string) ($row['status_label'] ?? '-')) ?></strong>
            <span class="muted">(<?= e((string) ($row['status_code'] ?? '-')) ?>)</span>
          </div>
        </div>

        <div class="field">
          <label for="warning_days_<?= e((string) ($row['status_code'] ?? '')) ?>">Risco (dias)</label>
          <input
            id="warning_days_<?= e((string) ($row['status_code'] ?? '')) ?>"
            type="number"
            name="warning_days"
            min="1"
            max="365"
            value="<?= e((string) (int) ($row['warning_days'] ?? 5)) ?>"
            required
          >
        </div>

        <div class="field">
          <label for="overdue_days_<?= e((string) ($row['status_code'] ?? '')) ?>">Vencido (dias)</label>
          <input
            id="overdue_days_<?= e((string) ($row['status_code'] ?? '')) ?>"
            type="number"
            name="overdue_days"
            min="1"
            max="730"
            value="<?= e((string) (int) ($row['overdue_days'] ?? 10)) ?>"
            required
          >
        </div>

        <div class="field field-wide">
          <label>
            <input type="hidden" name="notify_email" value="0">
            <input type="checkbox" name="notify_email" value="1" <?= (int) ($row['notify_email'] ?? 0) === 1 ? 'checked' : '' ?>>
            Habilitar notificacao por email para esta etapa
          </label>
        </div>

        <div class="field field-wide">
          <label for="notify_recipients_<?= e((string) ($row['status_code'] ?? '')) ?>">Destinatarios</label>
          <input
            id="notify_recipients_<?= e((string) ($row['status_code'] ?? '')) ?>"
            type="text"
            name="notify_recipients"
            value="<?= e((string) ($row['notify_recipients'] ?? '')) ?>"
            placeholder="email1@dominio;email2@dominio"
          >
        </div>

        <div class="field">
          <label for="is_active_<?= e((string) ($row['status_code'] ?? '')) ?>">Regra ativa</label>
          <select id="is_active_<?= e((string) ($row['status_code'] ?? '')) ?>" name="is_active">
            <option value="1" <?= (int) ($row['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Sim</option>
            <option value="0" <?= (int) ($row['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Nao</option>
          </select>
        </div>

        <div class="field">
          <label>Ultima atualizacao</label>
          <input type="text" value="<?= e($formatDate((string) ($row['updated_at'] ?? ''))) ?>" disabled>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Salvar regra</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
