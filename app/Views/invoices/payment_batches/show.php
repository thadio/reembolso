<?php

declare(strict_types=1);

$batch = is_array($batch ?? null) ? $batch : [];
$items = is_array($items ?? null) ? $items : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$canManage = ($canManage ?? false) === true;
$finalApprovalSimulation = is_array($finalApprovalSimulation ?? null) ? $finalApprovalSimulation : null;

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

$statusLabel = static function (string $status): string {
    return match ($status) {
        'aberto' => 'Aberto',
        'em_processamento' => 'Em processamento',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'em_processamento' => 'badge-warning',
        'pago' => 'badge-success',
        'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$riskLabel = static function (string $risk): string {
    return match ($risk) {
        'alto' => 'Alto',
        'medio' => 'Medio',
        default => 'Baixo',
    };
};

$riskBadgeClass = static function (string $risk): string {
    return match ($risk) {
        'alto' => 'badge-danger',
        'medio' => 'badge-warning',
        default => 'badge-success',
    };
};

$batchStatus = (string) ($batch['status'] ?? '');
$allowedTransitions = match ($batchStatus) {
    'aberto' => ['aberto', 'em_processamento', 'cancelado'],
    'em_processamento' => ['em_processamento', 'pago', 'cancelado'],
    'pago' => ['pago'],
    'cancelado' => ['cancelado'],
    default => [$batchStatus],
};
$finalTransitions = array_values(array_filter(
    $allowedTransitions,
    static fn (string $value): bool => in_array($value, ['pago', 'cancelado'], true)
));
$activeFinalSimulation = null;
if (
    $finalApprovalSimulation !== null
    && (int) ($finalApprovalSimulation['batch_id'] ?? 0) === (int) ($batch['id'] ?? 0)
    && (int) ($finalApprovalSimulation['expires_at'] ?? 0) > time()
) {
    $activeFinalSimulation = $finalApprovalSimulation;
}

$simulationSummary = is_array($activeFinalSimulation['summary'] ?? null) ? $activeFinalSimulation['summary'] : [];
$simulationQuality = is_array($activeFinalSimulation['quality'] ?? null) ? $activeFinalSimulation['quality'] : [];
$simulationNotes = is_array($activeFinalSimulation['risk_notes'] ?? null) ? $activeFinalSimulation['risk_notes'] : [];
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Lote <?= e((string) ($batch['batch_code'] ?? '-')) ?></h2>
      <p class="muted">Detalhamento financeiro dos pagamentos agrupados no lote.</p>
    </div>
    <div class="actions-inline">
      <span class="badge <?= e($statusBadgeClass($batchStatus)) ?>">
        <?= e((string) ($batch['status_label'] ?? $statusLabel($batchStatus))) ?>
      </span>
      <a class="btn btn-outline" href="<?= e(url('/invoices/payment-batches')) ?>">Voltar para lotes</a>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Titulo:</strong> <?= e((string) ($batch['title'] ?? '-')) ?></div>
    <div><strong>Referencia:</strong> <?= e($formatMonth((string) ($batch['reference_month'] ?? ''))) ?></div>
    <div><strong>Data prevista:</strong> <?= e($formatDate((string) ($batch['scheduled_payment_date'] ?? ''))) ?></div>
    <div><strong>Pagamentos:</strong> <?= e((string) (int) ($batch['payments_count'] ?? 0)) ?></div>
    <div><strong>Total:</strong> <?= e($formatMoney((float) ($batch['total_amount'] ?? 0))) ?></div>
    <div><strong>Orgaos impactados:</strong> <?= e((string) (int) ($batch['organs_count'] ?? 0)) ?></div>
    <div><strong>Periodo das baixas:</strong> <?= e($formatDate((string) ($batch['payment_date_from'] ?? ''))) ?> ate <?= e($formatDate((string) ($batch['payment_date_to'] ?? ''))) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDateTime((string) ($batch['created_at'] ?? ''))) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($batch['created_by_name'] ?? 'N/I')) ?></div>
    <div><strong>Fechado em:</strong> <?= e($formatDateTime((string) ($batch['closed_at'] ?? ''))) ?></div>
    <div><strong>Fechado por:</strong> <?= e((string) ($batch['closed_by_name'] ?? 'N/I')) ?></div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($batch['notes'] ?? '-'))) ?></div>
  </div>
</div>

<?php if ($canManage): ?>
  <?php if ($finalTransitions !== []): ?>
    <div class="card">
      <div class="header-row">
        <div>
          <h3>Simulacao previa de aprovacao final</h3>
          <p class="muted">Antes de finalizar em pago/cancelado, execute a simulacao para validar riscos e liberar a aprovacao.</p>
        </div>
      </div>

      <form method="post" action="<?= e(url('/invoices/payment-batches/final-approval/simulate')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="batch_id" value="<?= e((string) ($batch['id'] ?? 0)) ?>">

        <div class="field">
          <label for="final_target_status">Status final alvo</label>
          <select id="final_target_status" name="target_status" required>
            <?php foreach ($statusOptions as $option): ?>
              <?php
                $value = (string) ($option['value'] ?? '');
                $label = (string) ($option['label'] ?? $value);
                if ($value === '' || !in_array($value, $finalTransitions, true)) {
                    continue;
                }
                $selected = $activeFinalSimulation !== null
                    && $value === (string) ($activeFinalSimulation['target_status'] ?? '')
                    ? 'selected'
                    : '';
              ?>
              <option value="<?= e($value) ?>" <?= $selected ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-outline">Executar simulacao previa</button>
        </div>
      </form>

      <?php if ($activeFinalSimulation !== null): ?>
        <div class="details-grid">
          <div>
            <strong>Status alvo:</strong>
            <?= e((string) ($activeFinalSimulation['target_status_label'] ?? $statusLabel((string) ($activeFinalSimulation['target_status'] ?? '')))) ?>
          </div>
          <div>
            <strong>Risco:</strong>
            <?php $riskLevel = (string) ($activeFinalSimulation['risk_level'] ?? 'baixo'); ?>
            <span class="badge <?= e($riskBadgeClass($riskLevel)) ?>"><?= e($riskLabel($riskLevel)) ?></span>
          </div>
          <div><strong>Pagamentos:</strong> <?= e((string) (int) ($simulationSummary['payments_count'] ?? 0)) ?></div>
          <div><strong>Total simulado:</strong> <?= e($formatMoney((float) ($simulationSummary['total_amount'] ?? 0))) ?></div>
          <div><strong>Comprovantes ausentes:</strong> <?= e((string) (int) ($simulationQuality['proofs_missing_count'] ?? 0)) ?></div>
          <div><strong>Processos sem referencia:</strong> <?= e((string) (int) ($simulationQuality['process_missing_count'] ?? 0)) ?></div>
          <div><strong>Divergencia de total:</strong> <?= e($formatMoney((float) ($simulationQuality['amount_gap'] ?? 0))) ?></div>
          <div><strong>Valida ate:</strong> <?= e($formatDateTime((string) ($activeFinalSimulation['expires_at_label'] ?? ''))) ?></div>
        </div>

        <?php if ($simulationNotes !== []): ?>
          <ul>
            <?php foreach ($simulationNotes as $noteItem): ?>
              <li><?= e((string) $noteItem) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="header-row">
      <div>
        <h3>Atualizar status do lote</h3>
        <p class="muted">Fluxo recomendado: aberto -> em_processamento -> pago/cancelado. Status final exige simulacao previa valida.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/invoices/payment-batches/status/update')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" value="<?= e((string) ($batch['id'] ?? 0)) ?>">
      <?php if ($activeFinalSimulation !== null): ?>
        <input type="hidden" name="simulation_token" value="<?= e((string) ($activeFinalSimulation['token'] ?? '')) ?>">
      <?php endif; ?>

      <div class="field">
        <label for="batch_status">Status</label>
        <select id="batch_status" name="status" required>
          <?php foreach ($statusOptions as $option): ?>
            <?php
              $value = (string) ($option['value'] ?? '');
              $label = (string) ($option['label'] ?? $value);
              if ($value === '' || !in_array($value, $allowedTransitions, true)) {
                  continue;
              }
            ?>
            <option value="<?= e($value) ?>" <?= $value === $batchStatus ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field field-wide">
        <label for="batch_note">Observacao</label>
        <textarea id="batch_note" name="note" rows="3"><?= e(old('note', (string) ($batch['notes'] ?? ''))) ?></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Atualizar status</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Itens do lote</h3>
      <p class="muted">Pagamentos vinculados ao lote com referencia de boleto, orgao e comprovante.</p>
    </div>
  </div>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Este lote ainda nao possui itens de pagamento vinculados.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Valor</th>
            <th>Boleto</th>
            <th>Competencia</th>
            <th>Orgao</th>
            <th>Processo</th>
            <th>Comprovante</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($formatDate((string) ($item['payment_date'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($item['amount'] ?? 0))) ?></td>
              <td>
                <a href="<?= e(url('/invoices/show?id=' . (int) ($item['invoice_id'] ?? 0))) ?>">
                  <?= e((string) ($item['invoice_number'] ?? '-')) ?>
                </a>
              </td>
              <td><?= e($formatMonth((string) ($item['invoice_reference_month'] ?? ''))) ?></td>
              <td><?= e((string) ($item['organ_name'] ?? '-')) ?></td>
              <td><?= e((string) ($item['process_reference'] ?? '-')) ?></td>
              <td>
                <?php if (!empty($item['proof_storage_path'])): ?>
                  <?php $proofPaymentId = (int) ($item['payment_internal_id'] ?? ($item['payment_id'] ?? 0)); ?>
                  <a class="btn btn-outline" href="<?= e(url('/invoices/payments/proof?id=' . $proofPaymentId . '&invoice_id=' . (int) ($item['invoice_id'] ?? 0))) ?>">Baixar</a>
                <?php else: ?>
                  <span class="muted">Nao anexado</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
