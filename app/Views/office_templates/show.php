<?php

declare(strict_types=1);

$template = is_array($template ?? null) ? $template : [];
$versions = is_array($versions ?? null) ? $versions : [];
$documents = is_array($documents ?? null) ? $documents : [];
$people = is_array($people ?? null) ? $people : [];
$canManage = (bool) ($canManage ?? false);
$availableVariables = is_array($availableVariables ?? null) ? $availableVariables : [];

$typeLabel = static function (string $type): string {
    return match ($type) {
        'orgao' => 'Orgao',
        'mgi' => 'MGI',
        'cobranca' => 'Cobranca',
        'resposta' => 'Resposta',
        'outro' => 'Outro',
        default => ucfirst($type),
    };
};

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$activeVersion = null;
foreach ($versions as $version) {
    if ((int) ($version['is_active'] ?? 0) === 1) {
        $activeVersion = $version;
        break;
    }
}
if ($activeVersion === null && $versions !== []) {
    $activeVersion = $versions[0];
}
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($template['name'] ?? 'Template')) ?></h2>
      <p class="muted">
        <code><?= e((string) ($template['template_key'] ?? '')) ?></code>
        - Tipo <?= e($typeLabel((string) ($template['template_type'] ?? 'outro'))) ?>
      </p>
    </div>
    <div class="actions-inline">
      <?php if ((int) ($template['is_active'] ?? 1) === 1): ?>
        <span class="badge badge-success">Ativo</span>
      <?php else: ?>
        <span class="badge badge-neutral">Inativo</span>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= e(url('/office-templates')) ?>">Voltar</a>
      <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(url('/office-templates/edit?id=' . (int) ($template['id'] ?? 0))) ?>">Editar metadados</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Tipo:</strong> <?= e($typeLabel((string) ($template['template_type'] ?? 'outro'))) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($template['created_by_name'] ?? 'N/I')) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDate((string) ($template['created_at'] ?? ''))) ?></div>
    <div><strong>Atualizado em:</strong> <?= e($formatDate((string) ($template['updated_at'] ?? ''))) ?></div>
    <div class="details-wide"><strong>Descricao:</strong> <?= e((string) ($template['description'] ?? '-')) ?></div>
  </div>
</div>

<?php if ($activeVersion !== null): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Versao ativa (V<?= e((string) ($activeVersion['version_number'] ?? '-')) ?>)</h3>
        <p class="muted">Assunto e corpo HTML atualmente publicados.</p>
      </div>
    </div>
    <div class="details-grid">
      <div class="details-wide"><strong>Assunto:</strong> <?= e((string) ($activeVersion['subject'] ?? '-')) ?></div>
      <div class="details-wide">
        <strong>Corpo HTML:</strong>
        <pre><?= e((string) ($activeVersion['body_html'] ?? '')) ?></pre>
      </div>
      <div class="details-wide">
        <strong>Variaveis JSON:</strong>
        <pre><?= e((string) ($activeVersion['variables_json'] ?? '')) ?></pre>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Publicar nova versao</h3>
        <p class="muted">Ao publicar, a nova versao passa a ser a versao ativa do template.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/office-templates/version/create')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="template_id" value="<?= e((string) ($template['id'] ?? 0)) ?>">

      <div class="field field-wide">
        <label for="version_subject">Assunto *</label>
        <input id="version_subject" name="subject" type="text" minlength="3" maxlength="190" required value="<?= e((string) ($activeVersion['subject'] ?? '')) ?>">
      </div>

      <div class="field field-wide">
        <label for="version_body_html">Corpo HTML *</label>
        <textarea id="version_body_html" name="body_html" rows="10" required><?= e((string) ($activeVersion['body_html'] ?? '')) ?></textarea>
      </div>

      <div class="field field-wide">
        <label for="version_variables_json">Variaveis (JSON opcional)</label>
        <textarea id="version_variables_json" name="variables_json" rows="4"><?= e((string) ($activeVersion['variables_json'] ?? '')) ?></textarea>
      </div>

      <div class="field field-wide">
        <label for="version_notes">Notas da versao</label>
        <textarea id="version_notes" name="notes" rows="3"></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Publicar nova versao</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="header-row">
      <div>
        <h3>Gerar oficio</h3>
        <p class="muted">Gera documento a partir da versao escolhida com merge de variaveis de pessoa, custo e CDO.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/office-templates/generate')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="template_id" value="<?= e((string) ($template['id'] ?? 0)) ?>">

      <div class="field">
        <label for="version_id">Versao *</label>
        <select id="version_id" name="version_id" required>
          <?php foreach ($versions as $version): ?>
            <option value="<?= e((string) ($version['id'] ?? 0)) ?>" <?= (int) ($version['is_active'] ?? 0) === 1 ? 'selected' : '' ?>>
              V<?= e((string) ($version['version_number'] ?? '0')) ?> - <?= e((string) ($version['subject'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="person_id">Pessoa *</label>
        <select id="person_id" name="person_id" required>
          <option value="">Selecione uma pessoa</option>
          <?php foreach ($people as $person): ?>
            <option value="<?= e((string) ($person['id'] ?? 0)) ?>">
              <?= e((string) ($person['name'] ?? '')) ?> (<?= e((string) ($person['organ_name'] ?? '-')) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Gerar oficio</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h3>Variaveis disponiveis</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Token</th>
          <th>Descricao</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($availableVariables as $variable): ?>
          <tr>
            <td><code>{{<?= e((string) ($variable['key'] ?? '')) ?>}}</code></td>
            <td><?= e((string) ($variable['description'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3>Historico de versoes</h3>
  <?php if ($versions === []): ?>
    <div class="empty-state">
      <p>Sem versoes cadastradas.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Versao</th>
            <th>Assunto</th>
            <th>Status</th>
            <th>Criado por</th>
            <th>Criado em</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($versions as $version): ?>
            <tr>
              <td>V<?= e((string) ($version['version_number'] ?? '-')) ?></td>
              <td><?= e((string) ($version['subject'] ?? '-')) ?></td>
              <td>
                <?php if ((int) ($version['is_active'] ?? 0) === 1): ?>
                  <span class="badge badge-success">Ativa</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Historica</span>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($version['created_by_name'] ?? 'N/I')) ?></td>
              <td><?= e($formatDate((string) ($version['created_at'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Oficios gerados</h3>
  <?php if ($documents === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha oficios gerados para este template.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Versao</th>
            <th>Pessoa</th>
            <th>Orgao</th>
            <th>Assunto</th>
            <th>Gerado por</th>
            <th>Gerado em</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <tr>
              <td>#<?= e((string) ($document['id'] ?? 0)) ?></td>
              <td>V<?= e((string) ($document['version_number'] ?? '-')) ?></td>
              <td><?= e((string) ($document['person_name'] ?? '-')) ?></td>
              <td><?= e((string) ($document['organ_name'] ?? '-')) ?></td>
              <td><?= e((string) ($document['rendered_subject'] ?? '-')) ?></td>
              <td><?= e((string) ($document['generated_by_name'] ?? 'N/I')) ?></td>
              <td><?= e($formatDate((string) ($document['created_at'] ?? ''))) ?></td>
              <td class="actions-cell">
                <a class="btn btn-ghost" href="<?= e(url('/office-documents/show?id=' . (int) ($document['id'] ?? 0))) ?>">Ver</a>
                <a class="btn btn-ghost" href="<?= e(url('/office-documents/print?id=' . (int) ($document['id'] ?? 0))) ?>" target="_blank" rel="noopener">Print</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
