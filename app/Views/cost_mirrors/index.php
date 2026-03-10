<?php

declare(strict_types=1);

$filters = $filters ?? [
    'q' => '',
    'organ_id' => 0,
    'person_id' => 0,
    'status' => '',
    'reference_month' => '',
    'sort' => 'reference_month',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$mirrors = $mirrors ?? [];
$statusOptions = $statusOptions ?? [];
$organs = $organs ?? [];
$people = $people ?? [];
$sourceLabel = is_callable($sourceLabel ?? null)
    ? $sourceLabel
    : static fn (string $source): string => ucfirst($source);

$sort = (string) ($filters['sort'] ?? 'reference_month');
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
        'conferido' => 'Conferido',
        'conciliado' => 'Conciliado',
        default => ucfirst($status),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'conferido' => 'badge-warning',
        'conciliado' => 'badge-success',
        default => 'badge-neutral',
    };
};

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'organ_id' => (string) ($filters['organ_id'] ?? ''),
        'person_id' => (string) ($filters['person_id'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'reference_month' => (string) ($filters['reference_month'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'reference_month'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/cost-mirrors?' . http_build_query($params));
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
      <p class="muted">Controle item a item por pessoa e competencia, com vinculo opcional a boleto.</p>
    </div>
    <?php if (($canManage ?? false) === true): ?>
      <a class="btn btn-primary" href="<?= e(url('/cost-mirrors/create')) ?>">Novo espelho</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/cost-mirrors')) ?>" class="filters-row filters-cost-mirror">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Pessoa, titulo, boleto ou item">

    <select name="organ_id">
      <option value="0">Todos os orgaos</option>
      <?php $selectedOrganId = (int) ($filters['organ_id'] ?? 0); ?>
      <?php foreach ($organs as $organ): ?>
        <?php $organId = (int) ($organ['id'] ?? 0); ?>
        <option value="<?= e((string) $organId) ?>" <?= $selectedOrganId === $organId ? 'selected' : '' ?>>
          <?= e((string) ($organ['name'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="person_id">
      <option value="0">Todas as pessoas</option>
      <?php $selectedPersonId = (int) ($filters['person_id'] ?? 0); ?>
      <?php foreach ($people as $person): ?>
        <?php $personId = (int) ($person['id'] ?? 0); ?>
        <option value="<?= e((string) $personId) ?>" <?= $selectedPersonId === $personId ? 'selected' : '' ?>>
          <?= e((string) ($person['name'] ?? '')) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="status">
      <option value="">Todos os status</option>
      <?php foreach ($statusOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? '');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="month" name="reference_month" value="<?= e((string) ($filters['reference_month'] ?? '')) ?>">

    <select name="sort">
      <option value="reference_month" <?= $sort === 'reference_month' ? 'selected' : '' ?>>Ordenar por competencia</option>
      <option value="person_name" <?= $sort === 'person_name' ? 'selected' : '' ?>>Ordenar por pessoa</option>
      <option value="organ_name" <?= $sort === 'organ_name' ? 'selected' : '' ?>>Ordenar por orgao</option>
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
        <option value="<?= e((string) $size) ?>" <?= (int) ($filters['per_page'] ?? 10) === $size ? 'selected' : '' ?>>
          <?= e((string) $size) ?>/pagina
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="<?= e(url('/cost-mirrors')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($mirrors === []): ?>
    <div class="empty-state">
      <p>Nenhum espelho encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'reference_month', 'dir' => $nextDir('reference_month'), 'page' => 1])) ?>">Competencia</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'person_name', 'dir' => $nextDir('person_name'), 'page' => 1])) ?>">Pessoa</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'organ_name', 'dir' => $nextDir('organ_name'), 'page' => 1])) ?>">Orgao</a></th>
            <th>Titulo</th>
            <th>Boleto</th>
            <th>Fonte</th>
            <th><a href="<?= e($buildUrl(['sort' => 'status', 'dir' => $nextDir('status'), 'page' => 1])) ?>">Status</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'total_amount', 'dir' => $nextDir('total_amount'), 'page' => 1])) ?>">Total</a></th>
            <th>Itens</th>
            <th><a href="<?= e($buildUrl(['sort' => 'created_at', 'dir' => $nextDir('created_at'), 'page' => 1])) ?>">Criado</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mirrors as $mirror): ?>
            <?php
              $id = (int) ($mirror['id'] ?? 0);
              $status = (string) ($mirror['status'] ?? '');
            ?>
            <tr>
              <td><?= e($formatMonth((string) ($mirror['reference_month'] ?? ''))) ?></td>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($mirror['person_id'] ?? 0))) ?>">
                  <?= e((string) ($mirror['person_name'] ?? '-')) ?>
                </a>
              </td>
              <td><?= e((string) ($mirror['organ_name'] ?? '-')) ?></td>
              <td><?= e((string) ($mirror['title'] ?? '-')) ?></td>
              <td>
                <?php if (!empty($mirror['invoice_id'])): ?>
                  <a href="<?= e(url('/invoices/show?id=' . (int) ($mirror['invoice_id'] ?? 0))) ?>">
                    <?= e((string) ($mirror['invoice_number'] ?? '-')) ?>
                  </a>
                <?php else: ?>
                  <span class="muted">Nao vinculado</span>
                <?php endif; ?>
              </td>
              <td><?= e($sourceLabel((string) ($mirror['source'] ?? 'manual'))) ?></td>
              <td><span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span></td>
              <td><?= e($formatMoney((float) ($mirror['total_amount'] ?? 0))) ?></td>
              <td><?= e((string) (int) ($mirror['items_count'] ?? 0)) ?></td>
              <td><?= e($formatDate((string) ($mirror['created_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/cost-mirrors/show?id=' . $id)) ?>">Ver</a>
                <?php if (($canManage ?? false) === true): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/cost-mirrors/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/cost-mirrors/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste espelho?');">
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
