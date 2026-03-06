<?php

declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'scope' => 'all', 'limit' => 20];
$searchResult = is_array($searchResult ?? null) ? $searchResult : [
    'query' => '',
    'scope' => 'all',
    'min_query_length' => 3,
    'searched' => false,
    'results' => [
        'people' => [],
        'organs' => [],
        'process_meta' => [],
        'documents' => [],
    ],
    'totals' => [
        'people' => 0,
        'organs' => 0,
        'process_meta' => 0,
        'documents' => 0,
        'all' => 0,
    ],
];
$capabilities = is_array($capabilities ?? null) ? $capabilities : [];
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [['value' => 'all', 'label' => 'Tudo']];

$query = (string) ($searchResult['query'] ?? '');
$scope = (string) ($searchResult['scope'] ?? 'all');
$searched = ($searchResult['searched'] ?? false) === true;
$minQueryLength = max(2, (int) ($searchResult['min_query_length'] ?? 3));

$results = is_array($searchResult['results'] ?? null) ? $searchResult['results'] : [];
$people = is_array($results['people'] ?? null) ? $results['people'] : [];
$organs = is_array($results['organs'] ?? null) ? $results['organs'] : [];
$processMeta = is_array($results['process_meta'] ?? null) ? $results['process_meta'] : [];
$documents = is_array($results['documents'] ?? null) ? $results['documents'] : [];

$totals = is_array($searchResult['totals'] ?? null) ? $searchResult['totals'] : [];
$totalAll = (int) ($totals['all'] ?? 0);
$canViewFullCpf = ($capabilities['cpf_full'] ?? false) === true;
$canViewSensitiveDocuments = ($capabilities['documents_sensitive'] ?? false) === true;

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

$personStatusLabel = static function (string $status): string {
    return match ($status) {
        'interessado' => 'Interessado/Triagem',
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

$sensitivityLabel = static function (string $level): string {
    return match ($level) {
        'sensitive' => 'Sensivel',
        'restricted' => 'Restrito',
        default => 'Publico',
    };
};

$sensitivityBadgeClass = static function (string $level): string {
    return match ($level) {
        'sensitive' => 'badge-danger',
        'restricted' => 'badge-warning',
        default => 'badge-success',
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Busca global</h2>
      <p class="muted">Consulta unificada por CPF, SEI, DOU, orgao e documento.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Voltar ao dashboard</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/global-search')) ?>" class="filters-row filters-invoice">
    <input
      type="text"
      name="q"
      value="<?= e($query) ?>"
      placeholder="Digite CPF, SEI, numero de oficio, DOU, orgao, titulo do documento"
      minlength="<?= e((string) $minQueryLength) ?>"
      required
    >

    <select name="scope">
      <?php foreach ($scopeOptions as $option): ?>
        <?php
          $value = (string) ($option['value'] ?? 'all');
          $label = (string) ($option['label'] ?? $value);
        ?>
        <option value="<?= e($value) ?>" <?= $scope === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">Buscar</button>
    <a href="<?= e(url('/global-search')) ?>" class="btn btn-outline">Limpar</a>
  </form>

  <?php if (!$searched): ?>
    <div class="empty-state">
      <p>Informe ao menos <?= e((string) $minQueryLength) ?> caracteres para iniciar a busca global.</p>
    </div>
  <?php else: ?>
    <div class="grid-kpi">
      <article class="card kpi-card">
        <p class="kpi-label">Total encontrado</p>
        <p class="kpi-value"><?= e((string) $totalAll) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Pessoas</p>
        <p class="kpi-value"><?= e((string) (int) ($totals['people'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Orgaos</p>
        <p class="kpi-value"><?= e((string) (int) ($totals['organs'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Processo/DOU</p>
        <p class="kpi-value"><?= e((string) (int) ($totals['process_meta'] ?? 0)) ?></p>
      </article>
      <article class="card kpi-card">
        <p class="kpi-label">Documentos</p>
        <p class="kpi-value"><?= e((string) (int) ($totals['documents'] ?? 0)) ?></p>
      </article>
    </div>

    <?php if ($totalAll <= 0): ?>
      <div class="empty-state">
        <p>Nenhum resultado encontrado para a consulta atual.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($searched && $people !== []): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Pessoas</h3>
        <p class="muted">Correspondencias por nome, CPF, SEI, tags e orgao.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pessoa</th>
            <th>CPF</th>
            <th>SEI</th>
            <th>Orgao</th>
            <th>Status</th>
            <th>Tags</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($people as $person): ?>
            <?php $personId = (int) ($person['id'] ?? 0); ?>
            <tr>
              <td><?= e((string) ($person['name'] ?? '-')) ?></td>
              <td><?= e($canViewFullCpf ? (string) ($person['cpf'] ?? '-') : mask_cpf((string) ($person['cpf'] ?? ''))) ?></td>
              <td><?= e((string) ($person['sei_process_number'] ?? '-')) ?></td>
              <td><?= e((string) ($person['organ_name'] ?? '-')) ?></td>
              <td><?= e($personStatusLabel((string) ($person['status'] ?? ''))) ?></td>
              <td><?= e((string) ($person['tags'] ?? '-')) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . $personId)) ?>">Ver pessoa</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($searched && $organs !== []): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Orgaos</h3>
        <p class="muted">Correspondencias por nome, sigla, CNPJ e localidade.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Orgao</th>
            <th>Sigla</th>
            <th>CNPJ</th>
            <th>Localidade</th>
            <th>Atualizado</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($organs as $organ): ?>
            <?php $organId = (int) ($organ['id'] ?? 0); ?>
            <tr>
              <td><?= e((string) ($organ['name'] ?? '-')) ?></td>
              <td><?= e((string) ($organ['acronym'] ?? '-')) ?></td>
              <td><?= e((string) ($organ['cnpj'] ?? '-')) ?></td>
              <td><?= e(trim((string) ($organ['city'] ?? '') . '/' . (string) ($organ['state'] ?? ''), '/')) ?></td>
              <td><?= e($formatDateTime((string) ($organ['updated_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/organs/show?id=' . $organId)) ?>">Ver orgao</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($searched && $processMeta !== []): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Processo formal e DOU</h3>
        <p class="muted">Correspondencias por oficios, protocolo, DOU, pessoa, SEI e orgao.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pessoa</th>
            <th>SEI</th>
            <th>Orgao</th>
            <th>Oficio</th>
            <th>DOU</th>
            <th>Entrada MTE</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($processMeta as $meta): ?>
            <?php
              $metaId = (int) ($meta['id'] ?? 0);
              $personId = (int) ($meta['person_id'] ?? 0);
            ?>
            <tr>
              <td><?= e((string) ($meta['person_name'] ?? '-')) ?></td>
              <td><?= e((string) ($meta['sei_process_number'] ?? '-')) ?></td>
              <td><?= e((string) ($meta['organ_name'] ?? '-')) ?></td>
              <td>
                <?= e((string) ($meta['office_number'] ?? '-')) ?>
                <div class="muted">Prot.: <?= e((string) ($meta['office_protocol'] ?? '-')) ?></div>
              </td>
              <td>
                <?= e((string) ($meta['dou_edition'] ?? '-')) ?>
                <div class="muted"><?= e($formatDate((string) ($meta['dou_published_at'] ?? ''))) ?></div>
              </td>
              <td><?= e($formatDate((string) ($meta['mte_entry_date'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/process-meta/show?id=' . $metaId)) ?>">Ver processo</a>
                <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . $personId)) ?>">Ver pessoa</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($searched && $documents !== []): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Documentos</h3>
        <p class="muted">Correspondencias por titulo, tipo, SEI, tags, arquivo e orgao.</p>
      </div>
    </div>
    <?php if (!$canViewSensitiveDocuments): ?>
      <p class="muted">Documentos com sensibilidade `restricted/sensitive` nao sao exibidos para o perfil atual.</p>
    <?php endif; ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Documento</th>
            <th>Tipo</th>
            <th>Pessoa/SEI</th>
            <th>Orgao</th>
            <th>Sensibilidade</th>
            <th>Arquivo</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php
              $documentId = (int) ($document['id'] ?? 0);
              $personId = (int) ($document['person_id'] ?? 0);
              $sensitivity = (string) ($document['sensitivity_level'] ?? 'public');
            ?>
            <tr>
              <td>
                <?= e((string) ($document['title'] ?? '-')) ?>
                <div class="muted">Ref.: <?= e((string) ($document['reference_sei'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) ($document['document_type_name'] ?? '-')) ?></td>
              <td>
                <?= e((string) ($document['person_name'] ?? '-')) ?>
                <div class="muted">SEI: <?= e((string) ($document['sei_process_number'] ?? '-')) ?></div>
              </td>
              <td><?= e((string) ($document['organ_name'] ?? '-')) ?></td>
              <td><span class="badge <?= e($sensitivityBadgeClass($sensitivity)) ?>"><?= e($sensitivityLabel($sensitivity)) ?></span></td>
              <td>
                <?= e((string) ($document['original_name'] ?? '-')) ?>
                <div class="muted"><?= e($formatDateTime((string) ($document['created_at'] ?? ''))) ?></div>
              </td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/people/show?id=' . $personId)) ?>">Ver pessoa</a>
                <a class="btn btn-ghost" href="<?= e(url('/people/documents/download?id=' . $documentId . '&person_id=' . $personId)) ?>">Baixar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
