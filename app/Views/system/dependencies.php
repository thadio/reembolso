<?php

declare(strict_types=1);

$catalog = is_array($catalog ?? null) ? $catalog : [];
$overview = is_array($overview ?? null) ? $overview : [];
$inspection = is_array($inspection ?? null) ? $inspection : null;
$selectedEntity = trim((string) ($selectedEntity ?? ''));
$selectedId = trim((string) ($selectedId ?? ''));

$ruleBadgeClass = static function (string $rule): string {
    return match (strtoupper(trim($rule))) {
        'CASCADE' => 'badge-warning',
        'SET NULL' => 'badge-info',
        default => 'badge-danger',
    };
};

$boolBadgeClass = static function (bool $value): string {
    return $value ? 'badge-success' : 'badge-neutral';
};

$deleteStateLabel = static function (mixed $deletedAt): string {
    $value = trim((string) $deletedAt);

    return $value === '' ? 'Ativo' : 'Soft delete';
};

$inspectionOk = is_array($inspection) && (($inspection['ok'] ?? false) === true);
$inspectionErrors = is_array($inspection) ? (is_array($inspection['errors'] ?? null) ? $inspection['errors'] : []) : [];
$inspectionEntity = $inspectionOk ? (is_array($inspection['entity'] ?? null) ? $inspection['entity'] : []) : [];
$inspectionRecord = $inspectionOk ? (is_array($inspection['record'] ?? null) ? $inspection['record'] : []) : [];
$inspectionSummary = $inspectionOk ? (is_array($inspection['summary'] ?? null) ? $inspection['summary'] : []) : [];
$inspectionDependencies = $inspectionOk ? (is_array($inspection['dependencies'] ?? null) ? $inspection['dependencies'] : []) : [];

$recordLabel = '';
foreach (['name', 'title', 'code'] as $candidate) {
    $value = trim((string) ($inspectionRecord[$candidate] ?? ''));
    if ($value !== '') {
        $recordLabel = $value;
        break;
    }
}
?>

<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Visao de bloqueios por FK para reduzir falhas silenciosas antes de excluir registros.</p>
    </div>
  </div>

  <form method="get" action="<?= e(url('/integrity/dependencies')) ?>" class="form-grid sp-top-lg">
    <div class="field field-wide">
      <label for="entity">Entidade</label>
      <select id="entity" name="entity" required>
        <option value="">Selecione uma entidade</option>
        <?php foreach ($catalog as $item): ?>
          <?php
            $table = (string) ($item['table'] ?? '');
            $label = (string) ($item['label'] ?? $table);
            $exists = (($item['exists'] ?? false) === true);
            if ($table === '') {
                continue;
            }
          ?>
          <option value="<?= e($table) ?>" <?= $selectedEntity === $table ? 'selected' : '' ?> <?= $exists ? '' : 'disabled' ?>>
            <?= e($label) ?><?= $exists ? '' : ' (indisponivel neste ambiente)' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="id">ID do registro</label>
      <input id="id" name="id" type="number" min="1" step="1" value="<?= e($selectedId) ?>" required>
    </div>

    <div class="form-actions field-wide">
      <button type="submit" class="btn btn-primary">Analisar dependencias</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Mapa de risco por entidade</h3>
  <p class="muted">Resumo estrutural do schema para antecipar onde exclusoes tendem a bloquear.</p>

  <?php if ($overview === []): ?>
    <div class="empty-state">
      <p>Nenhuma entidade mapeada para diagnostico.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap sp-top-lg">
      <table>
        <thead>
          <tr>
            <th>Entidade</th>
            <th>FK recebidas</th>
            <th>Bloqueantes</th>
            <th>Cascade</th>
            <th>Set null</th>
            <th>Soft delete</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($overview as $item): ?>
            <?php
              $table = (string) ($item['table'] ?? '');
              $label = (string) ($item['label'] ?? $table);
              $exists = (($item['exists'] ?? false) === true);
              $hasDeletedAt = (($item['has_deleted_at'] ?? false) === true);
            ?>
            <tr>
              <td><?= e($label) ?></td>
              <td><?= e((string) (int) ($item['incoming_constraints'] ?? 0)) ?></td>
              <td class="<?= (int) ($item['restrictive_constraints'] ?? 0) > 0 ? 'text-danger' : '' ?>">
                <?= e((string) (int) ($item['restrictive_constraints'] ?? 0)) ?>
              </td>
              <td><?= e((string) (int) ($item['cascade_constraints'] ?? 0)) ?></td>
              <td><?= e((string) (int) ($item['set_null_constraints'] ?? 0)) ?></td>
              <td><span class="badge <?= e($boolBadgeClass($hasDeletedAt)) ?>"><?= $hasDeletedAt ? 'Sim' : 'Nao' ?></span></td>
              <td>
                <?php if ($exists): ?>
                  <a class="btn btn-outline" href="<?= e(url('/integrity/dependencies?entity=' . urlencode($table))) ?>">Selecionar</a>
                <?php else: ?>
                  <span class="muted">Indisponivel</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($inspection !== null): ?>
  <div class="card">
    <h3>Resultado da analise</h3>

    <?php if (!$inspectionOk): ?>
      <div class="empty-state">
        <p>
          <?= e($inspectionErrors !== [] ? implode(' ', array_map(static fn (mixed $value): string => (string) $value, $inspectionErrors)) : 'Nao foi possivel analisar o registro informado.') ?>
        </p>
      </div>
    <?php else: ?>
      <p class="muted sp-bottom-md">
        Entidade <strong><?= e((string) ($inspectionEntity['label'] ?? '')) ?></strong>
        · ID <strong><?= e((string) ($inspectionRecord['id'] ?? '-')) ?></strong>
        <?php if ($recordLabel !== ''): ?>
          · <?= e($recordLabel) ?>
        <?php endif; ?>
        · Estado: <span class="badge <?= e($deleteStateLabel($inspectionRecord['deleted_at'] ?? null) === 'Ativo' ? 'badge-success' : 'badge-neutral') ?>"><?= e($deleteStateLabel($inspectionRecord['deleted_at'] ?? null)) ?></span>
      </p>

      <div class="grid-kpi reports-kpi-grid">
        <article class="card kpi-card">
          <p class="kpi-label">Dependencias</p>
          <p class="kpi-value"><?= e((string) (int) ($inspectionSummary['dependencies_count'] ?? 0)) ?></p>
        </article>
        <article class="card kpi-card">
          <p class="kpi-label">Referencias ativas</p>
          <p class="kpi-value"><?= e((string) (int) ($inspectionSummary['active_references_total'] ?? 0)) ?></p>
        </article>
        <article class="card kpi-card">
          <p class="kpi-label">Bloqueios</p>
          <p class="kpi-value <?= (int) ($inspectionSummary['blocking_rows'] ?? 0) > 0 ? 'text-danger' : '' ?>">
            <?= e((string) (int) ($inspectionSummary['blocking_rows'] ?? 0)) ?>
          </p>
          <p class="dashboard-kpi-note">constraints bloqueantes: <?= e((string) (int) ($inspectionSummary['blocking_constraints'] ?? 0)) ?></p>
        </article>
      </div>

      <?php if ($inspectionDependencies === []): ?>
        <div class="empty-state sp-top-lg">
          <p>Sem dependencias FK recebidas para este registro.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap sp-top-lg">
          <table>
            <thead>
              <tr>
                <th>Tabela origem</th>
                <th>Coluna</th>
                <th>Regra ON DELETE</th>
                <th>Ativos</th>
                <th>Total</th>
                <th>Impacto</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inspectionDependencies as $dependency): ?>
                <?php
                  $rule = (string) ($dependency['delete_rule'] ?? 'NO ACTION');
                  $blocksDelete = (($dependency['blocks_delete'] ?? false) === true);
                ?>
                <tr>
                  <td><?= e((string) ($dependency['source_table'] ?? '-')) ?></td>
                  <td><?= e((string) ($dependency['source_column'] ?? '-')) ?></td>
                  <td><span class="badge <?= e($ruleBadgeClass($rule)) ?>"><?= e($rule) ?></span></td>
                  <td class="<?= $blocksDelete ? 'text-danger' : '' ?>"><?= e((string) (int) ($dependency['active'] ?? 0)) ?></td>
                  <td><?= e((string) (int) ($dependency['total'] ?? 0)) ?></td>
                  <td>
                    <?= e((string) ($dependency['impact_label'] ?? '-')) ?>
                    <?php if ($blocksDelete): ?>
                      <div class="muted">A exclusao sera bloqueada enquanto houver registros.</div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="actions-cell sp-top-lg">
        <?php if (is_string($inspectionEntity['list_path'] ?? null) && (string) $inspectionEntity['list_path'] !== ''): ?>
          <a class="btn btn-outline" href="<?= e(url((string) $inspectionEntity['list_path'])) ?>">Abrir lista da entidade</a>
        <?php endif; ?>
        <?php if (is_string($inspectionEntity['show_path'] ?? null) && (string) $inspectionEntity['show_path'] !== ''): ?>
          <a class="btn btn-outline" href="<?= e(url((string) $inspectionEntity['show_path'])) ?>">Abrir registro</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
