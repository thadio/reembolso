<?php

declare(strict_types=1);

$mirror = is_array($mirror ?? null) ? $mirror : [];
$review = is_array($review ?? null) ? $review : null;
$divergences = is_array($divergences ?? null) ? $divergences : [];
$summary = is_array($summary ?? null) ? $summary : [
    'total' => 0,
    'baixa' => 0,
    'media' => 0,
    'alta' => 0,
    'pendentes_justificativa' => 0,
];
$canManage = (bool) ($canManage ?? false);
$isLocked = (bool) ($isLocked ?? false);

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatMonth = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('m/Y', $timestamp);
};

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$severityLabel = static function (string $severity): string {
    return match ($severity) {
        'alta' => 'Alta',
        'media' => 'Media',
        'baixa' => 'Baixa',
        default => 'N/A',
    };
};

$severityBadgeClass = static function (string $severity): string {
    return match ($severity) {
        'alta' => 'badge-danger',
        'media' => 'badge-warning',
        'baixa' => 'badge-info',
        default => 'badge-neutral',
    };
};

$typeLabel = static function (string $type): string {
    return match ($type) {
        'faltante_espelho' => 'Previsto sem item no espelho',
        'faltante_previsto' => 'Item no espelho sem previsto ativo',
        'valor_divergente' => 'Valor divergente',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">
        <?= e((string) ($mirror['title'] ?? 'Espelho')) ?> ·
        <?= e((string) ($mirror['person_name'] ?? '-')) ?> ·
        Competencia <?= e($formatMonth((string) ($mirror['reference_month'] ?? ''))) ?>
      </p>
    </div>
    <div class="actions-inline">
      <?php if ($isLocked): ?>
        <span class="badge badge-success">Aprovado e bloqueado</span>
      <?php elseif ($review !== null): ?>
        <span class="badge badge-warning">Em analise</span>
      <?php else: ?>
        <span class="badge badge-info">Sem analise</span>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= e(url('/cost-mirrors/show?id=' . (int) ($mirror['id'] ?? 0))) ?>">Voltar ao espelho</a>
    </div>
  </div>

  <div class="actions-inline sp-top-md">
    <?php if ($canManage && !$isLocked): ?>
      <form method="post" action="<?= e(url('/cost-mirrors/reconciliation/run')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="cost_mirror_id" value="<?= e((string) (int) ($mirror['id'] ?? 0)) ?>">
        <button type="submit" class="btn btn-primary">Executar conciliacao</button>
      </form>
    <?php endif; ?>

    <?php if ($review !== null): ?>
      <span class="muted">
        Ultima execucao: <?= e($formatDateTime((string) ($review['compared_at'] ?? ''))) ?>
        <?php if (!empty($review['compared_by_name'])): ?>
          por <?= e((string) ($review['compared_by_name'] ?? '')) ?>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </div>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Divergencias</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['total'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Alta severidade</p>
    <p class="kpi-value text-danger"><?= e((string) (int) ($summary['alta'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Pendentes de justificativa</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['pendentes_justificativa'] ?? 0)) ?></p>
  </article>
</div>

<?php if ($review !== null && $canManage): ?>
  <div class="card">
    <h3>Aprovacao de conferencia</h3>
    <p class="muted">Aprovacao bloqueia edicoes de itens e metadados do espelho.</p>
    <?php if ($isLocked): ?>
      <p class="text-success">
        Aprovado em <?= e($formatDateTime((string) ($review['approved_at'] ?? ''))) ?>
        <?php if (!empty($review['approved_by_name'])): ?>
          por <?= e((string) ($review['approved_by_name'] ?? '')) ?>
        <?php endif; ?>.
      </p>
      <?php if (!empty($review['approval_notes'])): ?>
        <p><strong>Observacao:</strong> <?= nl2br(e((string) ($review['approval_notes'] ?? ''))) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <form method="post" action="<?= e(url('/cost-mirrors/reconciliation/approve')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="cost_mirror_id" value="<?= e((string) (int) ($mirror['id'] ?? 0)) ?>">
        <div class="field field-wide">
          <label for="approval_notes">Observacoes da aprovacao</label>
          <textarea id="approval_notes" name="approval_notes" rows="3" placeholder="Opcional"></textarea>
        </div>
        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmar aprovacao da conciliacao e bloqueio de edicao?');">Aprovar e bloquear edicao</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h3>Divergencias identificadas</h3>
  <?php if ($divergences === []): ?>
    <div class="empty-state">
      <p>Nenhuma divergencia detectada na ultima conciliacao.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Tipo</th>
            <th>Severidade</th>
            <th>Previsto</th>
            <th>Espelho</th>
            <th>Diferenca</th>
            <th>Justificativa</th>
            <th>Situacao</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($divergences as $row): ?>
            <?php
              $requires = (int) ($row['requires_justification'] ?? 0) === 1;
              $resolved = (int) ($row['is_resolved'] ?? 0) === 1;
            ?>
            <tr>
              <td><?= e((string) ($row['match_key'] ?? '-')) ?></td>
              <td><?= e($typeLabel((string) ($row['divergence_type'] ?? ''))) ?></td>
              <td><span class="badge <?= e($severityBadgeClass((string) ($row['severity'] ?? ''))) ?>"><?= e($severityLabel((string) ($row['severity'] ?? ''))) ?></span></td>
              <td><?= e($formatMoney((float) ($row['expected_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['actual_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['difference_amount'] ?? 0))) ?></td>
              <td>
                <?php if (!empty($row['justification_text'])): ?>
                  <?= nl2br(e((string) ($row['justification_text'] ?? ''))) ?>
                  <div class="muted">
                    <?= e($formatDateTime((string) ($row['justified_at'] ?? ''))) ?>
                    <?php if (!empty($row['justification_by_name'])): ?>
                      · <?= e((string) ($row['justification_by_name'] ?? '')) ?>
                    <?php endif; ?>
                  </div>
                <?php elseif ($canManage && !$isLocked): ?>
                  <form method="post" action="<?= e(url('/cost-mirrors/reconciliation/justify')) ?>" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cost_mirror_id" value="<?= e((string) (int) ($mirror['id'] ?? 0)) ?>">
                    <input type="hidden" name="divergence_id" value="<?= e((string) (int) ($row['id'] ?? 0)) ?>">
                    <textarea name="justification_text" rows="2" placeholder="Justificar divergencia" <?= $requires ? 'required' : '' ?>></textarea>
                    <button type="submit" class="btn btn-outline">Salvar justificativa</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($requires): ?>
                  <?php if ($resolved): ?>
                    <span class="badge badge-success">Justificada</span>
                  <?php else: ?>
                    <span class="badge badge-danger">Pendente</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-neutral">Nao obrigatoria</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
