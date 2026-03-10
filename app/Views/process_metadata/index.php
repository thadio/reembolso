<?php

declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? [
    'q' => '',
    'organ_id' => 0,
    'has_dou' => '',
    'sort' => 'updated_at',
    'dir' => 'desc',
    'per_page' => 10,
];
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$organs = $organs ?? [];
$canManage = (bool) ($canManage ?? false);

$sort = (string) ($filters['sort'] ?? 'updated_at');
$dir = (string) ($filters['dir'] ?? 'desc');

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
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

$buildUrl = static function (array $replace = []) use ($filters, $pagination): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'organ_id' => (string) ($filters['organ_id'] ?? ''),
        'has_dou' => (string) ($filters['has_dou'] ?? ''),
        'sort' => (string) ($filters['sort'] ?? 'updated_at'),
        'dir' => (string) ($filters['dir'] ?? 'desc'),
        'per_page' => (string) ($pagination['per_page'] ?? 10),
        'page' => (string) ($pagination['page'] ?? 1),
    ];

    foreach ($replace as $key => $value) {
        $params[$key] = (string) $value;
    }

    return url('/process-meta?' . http_build_query($params));
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
      <p class="muted">Controle formal de oficio, DOU e data oficial de entrada no MTE.</p>
    </div>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?= e(url('/process-meta/create')) ?>">Novo registro</a>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/process-meta')) ?>" class="filters-row filters-process-meta">
    <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Pessoa, orgao, oficio, protocolo, DOU">

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

    <select name="has_dou">
      <option value="">Com e sem DOU</option>
      <option value="1" <?= (string) ($filters['has_dou'] ?? '') === '1' ? 'selected' : '' ?>>Somente com DOU</option>
      <option value="0" <?= (string) ($filters['has_dou'] ?? '') === '0' ? 'selected' : '' ?>>Somente sem DOU</option>
    </select>

    <select name="sort">
      <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Ordenar por atualizacao</option>
      <option value="person_name" <?= $sort === 'person_name' ? 'selected' : '' ?>>Ordenar por pessoa</option>
      <option value="office_sent_at" <?= $sort === 'office_sent_at' ? 'selected' : '' ?>>Ordenar por envio oficio</option>
      <option value="dou_published_at" <?= $sort === 'dou_published_at' ? 'selected' : '' ?>>Ordenar por data DOU</option>
      <option value="mte_entry_date" <?= $sort === 'mte_entry_date' ? 'selected' : '' ?>>Ordenar por entrada MTE</option>
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
    <a href="<?= e(url('/process-meta')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Nenhum metadado formal encontrado com os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><a href="<?= e($buildUrl(['sort' => 'person_name', 'dir' => $nextDir('person_name'), 'page' => 1])) ?>">Pessoa</a></th>
            <th>Orgao</th>
            <th>Oficio</th>
            <th><a href="<?= e($buildUrl(['sort' => 'dou_published_at', 'dir' => $nextDir('dou_published_at'), 'page' => 1])) ?>">DOU</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'mte_entry_date', 'dir' => $nextDir('mte_entry_date'), 'page' => 1])) ?>">Entrada MTE</a></th>
            <th><a href="<?= e($buildUrl(['sort' => 'updated_at', 'dir' => $nextDir('updated_at'), 'page' => 1])) ?>">Atualizado</a></th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $id = (int) ($item['id'] ?? 0); ?>
            <tr>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($item['person_id'] ?? 0))) ?>"><?= e((string) ($item['person_name'] ?? '-')) ?></a>
                <div class="muted"><?= e((string) ($item['person_status'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) ($item['organ_name'] ?? '-')) ?></td>
              <td>
                <strong><?= e((string) ($item['office_number'] ?? '-')) ?></strong>
                <div class="muted"><?= e($formatDate((string) ($item['office_sent_at'] ?? ''))) ?> · <?= e($channelLabel((string) ($item['office_channel'] ?? ''))) ?></div>
                <?php if (!empty($item['office_protocol'])): ?>
                  <div class="muted">Protocolo: <?= e((string) ($item['office_protocol'] ?? '')) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e((string) ($item['dou_edition'] ?? '-')) ?></strong>
                <div class="muted"><?= e($formatDate((string) ($item['dou_published_at'] ?? ''))) ?></div>
                <?php if (!empty($item['dou_attachment_storage_path'])): ?>
                  <span class="badge badge-info">Com anexo</span>
                <?php endif; ?>
              </td>
              <td><?= e($formatDate((string) ($item['mte_entry_date'] ?? ''))) ?></td>
              <td><?= e($formatDate((string) ($item['updated_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/process-meta/show?id=' . $id)) ?>">Ver</a>
                <?php if ($canManage): ?>
                  <a class="btn btn-ghost" href="<?= e(url('/process-meta/edit?id=' . $id)) ?>">Editar</a>
                  <form method="post" action="<?= e(url('/process-meta/delete')) ?>" onsubmit="return confirm('Confirmar remocao deste registro formal?');">
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
