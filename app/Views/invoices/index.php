<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'status' => '',
    'financial_nature' => '',
    'organ_id' => 0,
    'reference_month' => '',
    'sort' => 'due_date',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$invoices = $invoices ?? [];
$statusOptions = $statusOptions ?? [];
$financialNatureOptions = $financialNatureOptions ?? [];
$organs = $organs ?? [];

$sort = (string) ($filters['sort'] ?? 'due_date');
$dir = (string) ($filters['dir'] ?? 'desc');

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
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
        'sort' => (string) ($filters['sort'] ?? 'due_date'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/invoices?' . http_build_query($params));
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
      <p class="muted">Controle por orgao, competencia, metadados e rateio por pessoa.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/invoices/payment-batches')) ?>">Lotes de pagamento</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/invoices/create')) ?>">Novo boleto</a>
      <?php endif; ?>
    </div>
  </div>

  <form method="get" action="<?= e(url('/invoices')) ?>" class="filters-row filters-invoice">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Numero, titulo, linha digitavel, referencia">

    <select name="status">
      <option value="">Todos os status</option>
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

    <input type="month" name="reference_month" value="<?= e((string) ($filters['reference_month'] ?? '')) ?>">

    <select name="sort">
      <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Ordenar por vencimento</option>
      <option value="reference_month" <?= $sort === 'reference_month' ? 'selected' : '' ?>>Ordenar por competencia</option>
      <option value="invoice_number" <?= $sort === 'invoice_number' ? 'selected' : '' ?>>Ordenar por numero</option>
      <option value="total_amount" <?= $sort === 'total_amount' ? 'selected' : '' ?>>Ordenar por valor</option>
      <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Ordenar por status</option>
      <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Ordenar por cadastro</option>
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
    <a href="<?= e(url('/invoices')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($invoices === []): ?>
    <div class="empty-state">
      <p>Nenhum boleto encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'invoice_number', 'dir' => $nextDir('invoice_number'), 'page' => 1])) ?>">Numero</a></th>
            <th>Orgao</th>
            <th><a href="<?= e($buildUrl(['sort' => 'reference_month', 'dir' => $nextDir('reference_month'), 'page' => 1])) ?>">Competencia</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'due_date', 'dir' => $nextDir('due_date'), 'page' => 1])) ?>">Vencimento</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
            <th>Natureza</th>
            <th><a href="<?= e($buildUrl(['sort' => 'total_amount', 'dir' => $nextDir('total_amount'), 'page' => 1])) ?>">Total</a></th>
            <th>Rateado</th>
            <th>Saldo</th>
            <th>Pessoas</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
            <?php
              $id = (int) ($invoice['id'] ?? 0);
              $status = (string) ($invoice['status'] ?? '');
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($invoice['invoice_number'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string) ($invoice['title'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) ($invoice['organ_name'] ?? '-')) ?></td>
              <td><?= e($formatMonth((string) ($invoice['reference_month'] ?? ''))) ?></td>
              <td><?= e($formatDate((string) ($invoice['due_date'] ?? ''))) ?></td>
              <td><span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span></td>
              <?php $financialNature = (string) ($invoice['financial_nature'] ?? 'despesa_reembolso'); ?>
              <td><span class="badge <?= e($financialNatureBadgeClass($financialNature)) ?>"><?= e($financialNatureLabel($financialNature)) ?></span></td>
              <td><?= e($formatMoney((float) ($invoice['total_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($invoice['allocated_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($invoice['available_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($invoice['linked_people_count'] ?? 0)) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/invoices/show?id=' . $id)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/invoices/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/invoices/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste boleto?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                    <button type="submit" class="btn btn-danger">Excluir</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) $pagination['total']) ?> registro(s)</span>
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
