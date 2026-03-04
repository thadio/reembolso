<?php

declare(strict_types=1);

$template = is_array($template ?? null) ? $template : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$showVersionFields = (bool) ($showVersionFields ?? false);
$availableVariables = is_array($availableVariables ?? null) ? $availableVariables : [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($template['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field">
      <label for="template_key">Chave tecnica *</label>
      <input id="template_key" name="template_key" type="text" value="<?= e(old('template_key', (string) ($template['template_key'] ?? ''))) ?>" minlength="3" maxlength="80" required placeholder="ex.: oficio_orgao_padrao">
    </div>

    <div class="field">
      <label for="name">Nome *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($template['name'] ?? ''))) ?>" minlength="3" maxlength="120" required>
    </div>

    <div class="field">
      <label for="template_type">Tipo *</label>
      <?php $selectedType = old('template_type', (string) ($template['template_type'] ?? 'orgao')); ?>
      <select id="template_type" name="template_type" required>
        <?php foreach ($typeOptions as $option): ?>
          <?php
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? $value);
          ?>
          <option value="<?= e($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="is_active">Status</label>
      <?php $selectedActive = old('is_active', (string) ($template['is_active'] ?? '1')); ?>
      <select id="is_active" name="is_active">
        <option value="1" <?= $selectedActive !== '0' ? 'selected' : '' ?>>Ativo</option>
        <option value="0" <?= $selectedActive === '0' ? 'selected' : '' ?>>Inativo</option>
      </select>
    </div>

    <div class="field field-wide">
      <label for="description">Descricao</label>
      <input id="description" name="description" type="text" value="<?= e(old('description', (string) ($template['description'] ?? ''))) ?>" maxlength="255">
    </div>

    <?php if ($showVersionFields): ?>
      <div class="field field-wide">
        <label for="subject">Assunto da versao inicial *</label>
        <input id="subject" name="subject" type="text" value="<?= e(old('subject', (string) ($template['subject'] ?? ''))) ?>" minlength="3" maxlength="190" required>
      </div>

      <div class="field field-wide">
        <label for="body_html">Corpo HTML da versao inicial *</label>
        <textarea id="body_html" name="body_html" rows="10" required><?= e(old('body_html', (string) ($template['body_html'] ?? ''))) ?></textarea>
      </div>

      <div class="field field-wide">
        <label for="variables_json">Variaveis (JSON opcional)</label>
        <textarea id="variables_json" name="variables_json" rows="4" placeholder='["person_name","person_process","organ_name"]'><?= e(old('variables_json', (string) ($template['variables_json'] ?? ''))) ?></textarea>
      </div>

      <div class="field field-wide">
        <label for="notes">Notas da versao</label>
        <textarea id="notes" name="notes" rows="3"><?= e(old('notes', (string) ($template['notes'] ?? ''))) ?></textarea>
      </div>
    <?php endif; ?>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/office-templates')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>

<?php if ($availableVariables !== []): ?>
  <div class="card">
    <h3>Variaveis de merge disponiveis</h3>
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
<?php endif; ?>
