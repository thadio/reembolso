<?php

declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [
    'q' => '',
    'status' => '',
    'financial_nature' => '',
    'organ_id' => 0,
    'reference_month' => '',
    'payment_date_from' => '',
    'payment_date_to' => '',
    'sort' => 'created_at',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = is_array($pagination ?? null) ? $pagination : ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$batches = is_array($batches ?? null) ? $batches : [];
$candidates = is_array($candidates ?? null) ? $candidates : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$financialNatureOptions = is_array($financialNatureOptions ?? null) ? $financialNatureOptions : [];
$organs = is_array($organs ?? null) ? $organs : [];
$canManage = ($canManage ?? false) === true;
$selectedPaymentIds = is_array($selectedPaymentIds ?? null) ? $selectedPaymentIds : [];

$selectedPaymentMap = [];
foreach ($selectedPaymentIds as $paymentId) {
    $id = (int) $paymentId;
    if ($id > 0) {
        $selectedPaymentMap[$id] = true;
    }
}

$sort = (string) ($filters['sort'] ?? 'created_at');
$dir = (string) ($filters['dir'] ?? 'desc');

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

$formatMonthInput = static function (string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}$/', $trimmed) === 1) {
        return $trimmed;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
        return substr($trimmed, 0, 7);
    }

    $timestamp = strtotime($trimmed);

    return $timestamp === false ? '' : date('Y-m', $timestamp);
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

$financialNatureLabel = static function (string $nature): string {
    return match ($nature) {
        'despesa_reembolso' => 'Despesa (a pagar)',
        'receita_reembolso' => 'Receita (a receber)',
        default => ucfirst(str_replace('_', ' ', $nature)),
    };
};

$financialNatureBadgeClass = static function (string $nature): string {
    return match ($nature) {
        'despesa_reembolso' => 'badge-warning',
        'receita_reembolso' => 'badge-success',
        default => 'badge-neutral',
    };
};

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'financial_nature' => (string) ($filters['financial_nature'] ?? ''),
        'organ_id' => (string) ($filters['organ_id'] ?? ''),
        'reference_month' => (string) ($filters['reference_month'] ?? ''),
        'payment_date_from' => (string) ($filters['payment_date_from'] ?? ''),
        'payment_date_to' => (string) ($filters['payment_date_to'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'created_at'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/invoices/payment-batches?' . http_build_query($params));
};

$nextDir = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'asc';
    }

    return $dir === 'asc' ? 'desc' : 'asc';
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Agrupamento de baixas para fechamento financeiro, rastreabilidade e governanca.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/invoices')) ?>">Voltar para boletos</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/invoices/payment-batches')) ?>" class="filters-row filters-invoice">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Codigo, titulo, observacoes, boleto, orgao, processo">

    <select name="status">
      <?php foreach ($statusOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="financial_nature">
      <?php foreach ($financialNatureOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['financial_nature'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="organ_id">
      <option value="0">Todos os orgaos</option>
      <?php $selectedOrganId = (int) ($filters['organ_id'] ?? 0); ?>
      <?php foreach ($organs as $organ): ?>
        <?php $organId = (int) ($organ['id'] ?? 0); ?>
        <option value="<?= e((string) $organId) ?>" <?= $selectedOrganId === $organId ? 'selected' : '' ?>><?= e((string) ($organ['name'] ?? '')) ?></option>
      <?php endforeach; ?>
    </select>

    <input type="month" name="reference_month" value="<?= e($formatMonthInput((string) ($filters['reference_month'] ?? ''))) ?>">
    <input type="date" name="payment_date_from" value="<?= e((string) ($filters['payment_date_from'] ?? '')) ?>">
    <input type="date" name="payment_date_to" value="<?= e((string) ($filters['payment_date_to'] ?? '')) ?>">

    <select name="sort">
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por criacao</option>
      <option value="batch_code" <?= $sort === 'batch_code' ? 'selected' : '' ?>>Ordenar por codigo</option>
      <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="scheduled_payment_date" <?= $sort === 'scheduled_payment_date' ? 'selected' : '' ?>>Ordenar por data prevista</option>
      <option value="payments_count" <?= $sort === 'payments_count' ? 'selected' : '' ?>>Ordenar por qtd pagamentos</option>
      <option value="total_amount" <?= $sort === 'total_amount' ? 'selected' : '' ?>>Ordenar por valor</option>
    </select>

    <select name="dir">
      <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
    </select>

    <select name="per_page">
      <?php foreach ([10, 20, 30, 50] as $size): ?>
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 10) === $size ? 'selected' : '' ?>><?= e((string) $size) ?>/pagina</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/invoices/payment-batches')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($batches === []): ?>
    <div class="empty-state">
      <p>Nenhum lote encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'batch_code', 'dir' => $nextDir('batch_code'), 'page' => 1])) ?>">Codigo</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
            <th>Natureza</th>
            <th>Referencia</th>
            <th><a href="<?= e($buildUrl(['sort' => 'scheduled_payment_date', 'dir' => $nextDir('scheduled_payment_date'), 'page' => 1])) ?>">Previsto</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'payments_count', 'dir' => $nextDir('payments_count'), 'page' => 1])) ?>">Pagamentos</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'total_amount', 'dir' => $nextDir('total_amount'), 'page' => 1])) ?>">Total</a></th>
            <th>Intervalo de baixas</th>
            <th><a href="<?= e($buildUrl(['sort' => 'created_at', 'dir' => $nextDir('created_at'), 'page' => 1])) ?>">Criado em</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php
              $batchId = (int) ($batch['id'] ?? 0);
              $batchStatus = (string) ($batch['status'] ?? '');
              $paymentFrom = (string) ($batch['payment_date_from'] ?? '');
              $paymentTo = (string) ($batch['payment_date_to'] ?? '');
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($batch['batch_code'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string) ($batch['title'] ?? '-')) ?></div>
              </td>
              <td>
                <span class="badge <?= e($statusBadgeClass($batchStatus)) ?>">
                  <?= e((string) ($batch['status_label'] ?? $statusLabel($batchStatus))) ?>
                </span>
              </td>
              <?php $batchNature = (string) ($batch['financial_nature'] ?? 'despesa_reembolso'); ?>
              <td><span class="badge <?= e($financialNatureBadgeClass($batchNature)) ?>"><?= e($financialNatureLabel($batchNature)) ?></span></td>
              <td><?= e($formatMonth((string) ($batch['reference_month'] ?? ''))) ?></td>
              <td><?= e($formatDate((string) ($batch['scheduled_payment_date'] ?? ''))) ?></td>
              <td>
                <?= e((string) (int) ($batch['payments_count'] ?? 0)) ?>
                <div class="muted">Orgaos: <?= e((string) (int) ($batch['organs_count'] ?? 0)) ?></div>
              </td>
              <td><?= e($formatMoney((float) ($batch['total_amount'] ?? 0))) ?></td>
              <td>
                <?= e($formatDate($paymentFrom)) ?>
                <div class="muted">ate <?= e($formatDate($paymentTo)) ?></div>
              </td>
              <td>
                <?= e($formatDateTime((string) ($batch['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($batch['created_by_name'] ?? 'Nao informado')) ?></div>
              </td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/invoices/payment-batches/show?id=' . $batchId)) ?>">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) $pagination['total']) ?> lote(s)</span>
      <div class="pagination-links">
        <?php if ((int) $pagination['page'] > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= e((string) $pagination['page']) ?> de <?= e((string) $pagination['pages']) ?></span>
        <?php if ((int) $pagination['page'] < (int) $pagination['pages']): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Criar lote</h3>
        <p class="muted">Selecione pagamentos elegiveis e consolide um lote financeiro auditavel.</p>
      </div>
      <div class="muted"><?= e((string) count($candidates)) ?> pagamento(s) elegivel(is) no recorte atual</div>
    </div>

    <?php if ($candidates === []): ?>
      <div class="empty-state">
        <p>Nenhum pagamento elegivel com os filtros atuais. Ajuste os filtros ou registre novas baixas.</p>
      </div>
    <?php else: ?>
      <?php
        $referenceMonthValue = old('reference_month', (string) ($filters['reference_month'] ?? ''));
        $referenceMonthInput = $formatMonthInput($referenceMonthValue);
      ?>
      <form method="post" action="<?= e(url('/invoices/payment-batches/store')) ?>" class="form-grid">
        <?= csrf_field() ?>

        <div class="field">
          <label for="batch_financial_nature">Natureza financeira *</label>
          <?php $selectedBatchNature = (string) old('financial_nature', (string) ($filters['financial_nature'] ?? '')); ?>
          <select id="batch_financial_nature" name="financial_nature" required>
            <?php foreach ($financialNatureOptions as $option): ?>
              <?php
                $value = (string) ($option['value'] ?? '');
                $label = (string) ($option['label'] ?? $value);
                if ($value === '') {
                    continue;
                }
              ?>
              <option value="<?= e($value) ?>" <?= $selectedBatchNature === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field field-wide">
          <label for="batch_title">Titulo do lote</label>
          <input id="batch_title" name="title" type="text" maxlength="190" value="<?= e(old('title', '')) ?>" placeholder="Ex.: Lote FOPAG orgaos federais">
        </div>

        <div class="field">
          <label for="batch_reference_month">Referencia</label>
          <input id="batch_reference_month" name="reference_month" type="month" value="<?= e($referenceMonthInput) ?>">
        </div>

        <div class="field">
          <label for="batch_scheduled_date">Data prevista de pagamento</label>
          <input id="batch_scheduled_date" name="scheduled_payment_date" type="date" value="<?= e(old('scheduled_payment_date', '')) ?>">
        </div>

        <div class="field field-wide">
          <label for="batch_notes">Observacoes</label>
          <textarea id="batch_notes" name="notes" rows="3"><?= e(old('notes', '')) ?></textarea>
        </div>

        <div class="field field-wide">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>
                    <input
                      id="payment_candidate_all"
                      type="checkbox"
                      onclick="document.querySelectorAll('.js-payment-candidate').forEach(function(node){ node.checked = document.getElementById('payment_candidate_all').checked; });"
                    >
                  </th>
                  <th>Data</th>
                  <th>Valor</th>
                  <th>Boleto</th>
                  <th>Competencia</th>
                  <th>Orgao</th>
                  <th>Natureza</th>
                  <th>Processo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($candidates as $candidate): ?>
                  <?php $candidateId = (int) ($candidate['payment_id'] ?? 0); ?>
                  <tr>
                    <td>
                      <input
                        class="js-payment-candidate"
                        type="checkbox"
                        name="payment_ids[]"
                        value="<?= e((string) $candidateId) ?>"
                        <?= isset($selectedPaymentMap[$candidateId]) ? 'checked' : '' ?>
                      >
                    </td>
                    <td><?= e($formatDate((string) ($candidate['payment_date'] ?? ''))) ?></td>
                    <td><?= e($formatMoney((float) ($candidate['amount'] ?? 0))) ?></td>
                    <td>
                      <a href="<?= e(url('/invoices/show?id=' . (int) ($candidate['invoice_id'] ?? 0))) ?>">
                        <?= e((string) ($candidate['invoice_number'] ?? '-')) ?>
                      </a>
                    </td>
                    <td><?= e($formatMonth((string) ($candidate['invoice_reference_month'] ?? ''))) ?></td>
                    <td><?= e((string) ($candidate['organ_name'] ?? '-')) ?></td>
                    <?php $candidateNature = (string) ($candidate['invoice_financial_nature'] ?? ($candidate['financial_nature'] ?? 'despesa_reembolso')); ?>
                    <td><span class="badge <?= e($financialNatureBadgeClass($candidateNature)) ?>"><?= e($financialNatureLabel($candidateNature)) ?></span></td>
                    <td><?= e((string) ($candidate['process_reference'] ?? '-')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="muted">Limite operacional: ate 600 pagamentos por lote.</p>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Criar lote de pagamento</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
