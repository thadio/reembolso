<?php

declare(strict_types=1);

$invoice = is_array($invoice ?? null) ? $invoice : [];
$links = is_array($links ?? null) ? $links : [];
$payments = is_array($payments ?? null) ? $payments : [];
$availablePeople = is_array($availablePeople ?? null) ? $availablePeople : [];
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
        'vencido' => 'Vencido',
        'pago_parcial' => 'Pago parcial',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        default => ucfirst($status),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'vencido' => 'badge-danger',
        'pago_parcial' => 'badge-warning',
        'pago' => 'badge-success',
        'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$personStatusLabel = static function (string $status): string {
    return match ($status) {
        'interessado' => 'Interessado',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Oficio orgao',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$status = (string) ($invoice['status'] ?? '');
$isFinalStatus = in_array($status, ['pago', 'cancelado'], true);
$paidAmount = max(0.0, (float) ($invoice['paid_amount'] ?? 0));
$totalAmount = max(0.0, (float) ($invoice['total_amount'] ?? 0));
$remainingPayable = max(0.0, $totalAmount - $paidAmount);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Boleto <?= e((string) ($invoice['invoice_number'] ?? '-')) ?></h2>
      <p class="muted">Competencia <?= e($formatMonth((string) ($invoice['reference_month'] ?? ''))) ?> · Vencimento <?= e($formatDate((string) ($invoice['due_date'] ?? ''))) ?></p>
    </div>
    <div class="actions-inline">
      <span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span>
      <a class="btn btn-outline" href="<?= e(url('/invoices')) ?>">Voltar</a>
      <?php if (!empty($invoice['pdf_storage_path'])): ?>
        <a class="btn btn-outline" href="<?= e(url('/invoices/pdf?id=' . (int) ($invoice['id'] ?? 0))) ?>">Baixar PDF</a>
      <?php endif; ?>
      <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(url('/invoices/edit?id=' . (int) ($invoice['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Orgao:</strong> <?= e((string) ($invoice['organ_name'] ?? '-')) ?></div>
    <div><strong>Titulo:</strong> <?= e((string) ($invoice['title'] ?? '-')) ?></div>
    <div><strong>Emissao:</strong> <?= e($formatDate((string) ($invoice['issue_date'] ?? ''))) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDateTime((string) ($invoice['created_at'] ?? ''))) ?></div>
    <div><strong>Linha digitavel:</strong> <?= e((string) ($invoice['digitable_line'] ?? '-')) ?></div>
    <div><strong>Referencia:</strong> <?= e((string) ($invoice['reference_code'] ?? '-')) ?></div>
    <div><strong>PDF:</strong> <?= e((string) ($invoice['pdf_original_name'] ?? 'Nao anexado')) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($invoice['created_by_name'] ?? 'N/I')) ?></div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($invoice['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Valor total</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($invoice['total_amount'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Pago no boleto</p>
    <p class="kpi-value"><?= e($formatMoney($paidAmount)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Saldo a pagar</p>
    <p class="kpi-value"><?= e($formatMoney($remainingPayable)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Valor rateado</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($invoice['allocated_amount'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Saldo disponivel</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($invoice['available_amount'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Pessoas vinculadas: <?= e((string) (int) ($invoice['linked_people_count'] ?? 0)) ?></p>
  </article>
</div>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Vincular pessoa</h3>
        <p class="muted">Rateio opcional: deixe em branco para vincular sem valor.</p>
      </div>
    </div>

    <?php if ($isFinalStatus): ?>
      <div class="empty-state">
        <p>Boleto em status final (<?= e($statusLabel($status)) ?>). Nao e possivel alterar vinculos.</p>
      </div>
    <?php elseif ($availablePeople === []): ?>
      <div class="empty-state">
        <p>Sem pessoas disponiveis para vinculo neste boleto.</p>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/invoices/people/link')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">

        <div class="field field-wide">
          <label for="person_id">Pessoa *</label>
          <select id="person_id" name="person_id" required>
            <option value="">Selecione uma pessoa</option>
            <?php foreach ($availablePeople as $person): ?>
              <option value="<?= e((string) ($person['id'] ?? 0)) ?>">
                <?= e((string) ($person['name'] ?? '')) ?>
                (<?= e((string) ($person['organ_name'] ?? '-')) ?> · <?= e($personStatusLabel((string) ($person['status'] ?? ''))) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="allocated_amount">Valor de rateio (R$)</label>
          <input id="allocated_amount" name="allocated_amount" type="text" placeholder="0,00">
        </div>

        <div class="field field-wide">
          <label for="notes">Observacoes</label>
          <textarea id="notes" name="notes" rows="3"></textarea>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Vincular pessoa</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Registrar pagamento</h3>
        <p class="muted">Baixa parcial ou total com comprovante opcional.</p>
      </div>
    </div>

    <?php if ($status === 'cancelado'): ?>
      <div class="empty-state">
        <p>Boleto cancelado. Nao e possivel registrar pagamento.</p>
      </div>
    <?php elseif ($remainingPayable <= 0.009): ?>
      <div class="empty-state">
        <p>Boleto quitado. Nenhuma baixa pendente.</p>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/invoices/payments/store')) ?>" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">

        <div class="field">
          <label for="payment_date">Data do pagamento *</label>
          <input id="payment_date" name="payment_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>

        <div class="field">
          <label for="payment_amount">Valor pago (R$) *</label>
          <input id="payment_amount" name="amount" type="text" placeholder="0,00" required>
        </div>

        <div class="field field-wide">
          <label for="process_reference">Processo/comprovante</label>
          <input id="process_reference" name="process_reference" type="text" maxlength="120" placeholder="Ex.: SEI 12345.000001/2026-10">
        </div>

        <div class="field field-wide">
          <label for="payment_proof">Comprovante</label>
          <input id="payment_proof" name="payment_proof" type="file" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg">
          <p class="muted">Formatos aceitos: PDF, PNG, JPG e JPEG (max. 15MB).</p>
        </div>

        <div class="field field-wide">
          <label for="payment_notes">Observacoes</label>
          <textarea id="payment_notes" name="notes" rows="3"></textarea>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Registrar pagamento</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Historico de pagamentos</h3>
      <p class="muted">Lancamentos de baixa deste boleto.</p>
    </div>
  </div>

  <?php if ($payments === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha pagamentos registrados para este boleto.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Valor</th>
            <th>Alocado em pessoas</th>
            <th>Processo</th>
            <th>Comprovante</th>
            <th>Registro</th>
            <th>Observacoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?= e($formatDate((string) ($payment['payment_date'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($payment['amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($payment['allocated_amount'] ?? 0))) ?></td>
              <td><?= e((string) ($payment['process_reference'] ?? '-')) ?></td>
              <td>
                <?php if (!empty($payment['proof_storage_path'])): ?>
                  <a class="btn btn-outline" href="<?= e(url('/invoices/payments/proof?id=' . (int) ($payment['id'] ?? 0) . '&invoice_id=' . (int) ($invoice['id'] ?? 0))) ?>">Baixar</a>
                <?php else: ?>
                  <span class="muted">Nao anexado</span>
                <?php endif; ?>
              </td>
              <td>
                <?= e($formatDateTime((string) ($payment['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($payment['created_by_name'] ?? 'N/I')) ?></div>
              </td>
              <td><?= nl2br(e((string) ($payment['notes'] ?? '-'))) ?></td>
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
      <h3>Pessoas vinculadas</h3>
      <p class="muted">Rateio por pessoa para este boleto.</p>
    </div>
  </div>

  <?php if ($links === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha pessoas vinculadas a este boleto.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pessoa</th>
            <th>Status pipeline</th>
            <th>Rateio</th>
            <th>Pago</th>
            <th>Registro</th>
            <th>Observacoes</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($links as $link): ?>
            <tr>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($link['person_id'] ?? 0))) ?>"><?= e((string) ($link['person_name'] ?? '-')) ?></a>
                <div class="muted"><?= e((string) ($link['person_organ_name'] ?? '-')) ?></div>
              </td>
              <td><?= e($personStatusLabel((string) ($link['person_status'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($link['allocated_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($link['paid_amount'] ?? 0))) ?></td>
              <td>
                <?= e($formatDateTime((string) ($link['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($link['created_by_name'] ?? 'N/I')) ?></div>
              </td>
              <td><?= nl2br(e((string) ($link['notes'] ?? '-'))) ?></td>
              <td class="actions-cell">
                <?php if ($canManage && !$isFinalStatus && (float) ($link['paid_amount'] ?? 0) <= 0.009): ?>
                  <form method="post" action="<?= e(url('/invoices/people/unlink')) ?>" onsubmit="return confirm('Remover vinculo desta pessoa com o boleto?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                    <input type="hidden" name="link_id" value="<?= e((string) ($link['id'] ?? 0)) ?>">
                    <button type="submit" class="btn btn-danger">Remover</button>
                  </form>
                <?php else: ?>
                  <span class="muted"><?= (float) ($link['paid_amount'] ?? 0) > 0.009 ? 'Com pagamento' : '-' ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
