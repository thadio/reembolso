<?php

declare(strict_types=1);

$canImportPeople = (bool) ($canImportPeople ?? false);
$canImportOrgans = (bool) ($canImportOrgans ?? false);
$canImportCostMirrorItems = (bool) ($canImportCostMirrorItems ?? false);
$mirrorOptions = is_array($mirrorOptions ?? null) ? $mirrorOptions : [];
$selectedMirrorId = max(0, (int) ($selectedMirrorId ?? 0));

$formatMonth = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '-';
    }

    return date('m/Y', $timestamp);
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Concentrador unico para cargas em massa de pessoas, orgaos e itens de espelho de custo.</p>
    </div>
  </div>
</div>

<?php if (!$canImportPeople && !$canImportOrgans && !$canImportCostMirrorItems): ?>
  <div class="card">
    <div class="empty-state">
      <p>Nenhuma importacao em lote esta habilitada para o seu perfil.</p>
    </div>
  </div>
<?php endif; ?>

<?php if ($canImportPeople): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Importacao de pessoas (CSV)</h3>
        <p class="muted">Cabecalho minimo: <code>name, cpf, organ</code> (aceita aliases como <code>nome</code> e <code>orgao</code>).</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/people/import-csv')) ?>" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="return_to" value="/bulk-imports">

      <div class="field field-wide">
        <label for="people_csv_file">Arquivo CSV *</label>
        <input id="people_csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" required>
      </div>

      <div class="field field-wide">
        <label style="display:flex; align-items:center; gap:.45rem;">
          <input type="checkbox" name="validate_only" value="1">
          Apenas validar (sem gravar)
        </label>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Importar pessoas</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($canImportOrgans): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Importacao de orgaos (CSV)</h3>
        <p class="muted">Cabecalho minimo: <code>name</code>, com suporte aos campos institucionais adicionais ja homologados no modulo.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/organs/import-csv')) ?>" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="return_to" value="/bulk-imports">

      <div class="field field-wide">
        <label for="organs_csv_file">Arquivo CSV *</label>
        <input id="organs_csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" required>
      </div>

      <div class="field field-wide">
        <label style="display:flex; align-items:center; gap:.45rem;">
          <input type="checkbox" name="validate_only" value="1">
          Apenas validar (sem gravar)
        </label>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Importar orgaos</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($canImportCostMirrorItems): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Importacao de itens de espelho (CSV)</h3>
        <p class="muted">Cabecalhos aceitos: <code>item_name, amount, quantity, unit_amount, item_code, notes</code>.</p>
      </div>
    </div>

    <?php if ($mirrorOptions === []): ?>
      <div class="empty-state">
        <p>Nenhum espelho disponivel para importacao. Cadastre um espelho antes de importar itens.</p>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/cost-mirrors/items/import-csv')) ?>" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="/bulk-imports">

        <div class="field field-wide">
          <label for="mirror_id">Espelho de destino *</label>
          <select id="mirror_id" name="mirror_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($mirrorOptions as $mirror): ?>
              <?php
                $mirrorId = (int) ($mirror['id'] ?? 0);
                $isSelected = $selectedMirrorId > 0 && $selectedMirrorId === $mirrorId;
                $personName = trim((string) ($mirror['person_name'] ?? '-'));
                $organName = trim((string) ($mirror['organ_name'] ?? '-'));
                $month = $formatMonth((string) ($mirror['reference_month'] ?? ''));
              ?>
              <option value="<?= e((string) $mirrorId) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= e('#' . $mirrorId . ' - ' . $month . ' - ' . $personName . ' (' . $organName . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field field-wide">
          <label for="cost_mirror_csv_file">Arquivo CSV *</label>
          <input id="cost_mirror_csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" required>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Importar itens</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
