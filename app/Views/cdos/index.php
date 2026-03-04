<?php

declare(strict_types=1);

$filters = $filters ?? ['q' => '', 'status' => '', 'sort' => 'period_start', 'dir' => 'desc', 'per_page' => 10];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$cdos = $cdos ?? [];
$statusOptions = $statusOptions ?? [];

$sort = (string) ($filters['sort'] ?? 'period_start');
$dir = (string) ($filters['dir'] ?? 'desc');

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'aberto' => 'Aberto',
        'parcial' => 'Parcial',
        'alocado' => 'Alocado',
        'encerrado' => 'Encerrado',
        'cancelado' => 'Cancelado',
        default => ucfirst($status),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'parcial' => 'badge-warning',
        'alocado' => 'badge-success',
        'encerrado', 'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'period_start'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/cdos?' . http_build_query($params));
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
      <h2>CDOs</h2>
      <p class="muted">Controle de valor total, alocado e saldo por CDO.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/cdos/create')) ?>">Novo CDO</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/cdos')) ?>" class="filters-row filters-cdo">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Numero, UG ou acao">

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

    <select name="sort">
      <option value="period_start" <?= $sort === 'period_start' ? 'selected' : '' ?>>Ordenar por periodo</option>
      <option value="number" <?= $sort === 'number' ? 'selected' : '' ?>>Ordenar por numero</option>
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
    <a href="<?= e(url('/cdos')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($cdos === []): ?>
    <div class="empty-state">
      <p>Nenhum CDO encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'number', 'dir' => $nextDir('number'), 'page' => 1])) ?>">Numero</a></th>
            <th>Periodo</th>
            <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'total_amount', 'dir' => $nextDir('total_amount'), 'page' => 1])) ?>">Total</a></th>
            <th>Alocado</th>
            <th>Saldo</th>
            <th>Pessoas</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cdos as $cdo): ?>
            <?php
              $id = (int) ($cdo['id'] ?? 0);
              $status = (string) ($cdo['status'] ?? '');
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($cdo['number'] ?? '-')) ?></strong>
                <?php if (!empty($cdo['ug_code']) || !empty($cdo['action_code'])): ?>
                  <div class="muted">UG <?= e((string) ($cdo['ug_code'] ?? '-')) ?> · Acao <?= e((string) ($cdo['action_code'] ?? '-')) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($formatDate((string) ($cdo['period_start'] ?? ''))) ?> a <?= e($formatDate((string) ($cdo['period_end'] ?? ''))) ?></td>
              <td><span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span></td>
              <td><?= e($formatMoney((float) ($cdo['total_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($cdo['allocated_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($cdo['available_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($cdo['linked_people_count'] ?? 0)) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/cdos/show?id=' . $id)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/cdos/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/cdos/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste CDO?');">
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
