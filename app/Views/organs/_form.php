<?php

declare(strict_types=1);

$organ = $organ ?? [];
?>
<div class="card">
  <form method="post" action="<?= e($action) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (($isEdit ?? false) === true): ?>
      <input type="hidden" name="id" value="<?= e((string) ($organ['id'] ?? '')) ?>">
    <?php endif; ?>

    <div class="field field-wide">
      <label for="name">Nome do órgão *</label>
      <input id="name" name="name" type="text" value="<?= e(old('name', (string) ($organ['name'] ?? ''))) ?>" required>
    </div>

    <div class="field">
      <label for="acronym">Sigla</label>
      <input id="acronym" name="acronym" type="text" value="<?= e(old('acronym', (string) ($organ['acronym'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="cnpj">CNPJ</label>
      <input id="cnpj" name="cnpj" type="text" value="<?= e(old('cnpj', (string) ($organ['cnpj'] ?? ''))) ?>" placeholder="00.000.000/0000-00">
    </div>

    <div class="field">
      <label for="company_nire">NIRE</label>
      <input id="company_nire" name="company_nire" type="text" value="<?= e(old('company_nire', (string) ($organ['company_nire'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="organ_type">Tipo institucional</label>
      <?php $selectedOrganType = old('organ_type', (string) ($organ['organ_type'] ?? '')); ?>
      <select id="organ_type" name="organ_type">
        <option value="">Selecionar</option>
        <option value="administracao_direta" <?= $selectedOrganType === 'administracao_direta' ? 'selected' : '' ?>>Administracao direta</option>
        <option value="autarquia" <?= $selectedOrganType === 'autarquia' ? 'selected' : '' ?>>Autarquia</option>
        <option value="autarquia_especial" <?= $selectedOrganType === 'autarquia_especial' ? 'selected' : '' ?>>Autarquia especial</option>
        <option value="fundacao_publica" <?= $selectedOrganType === 'fundacao_publica' ? 'selected' : '' ?>>Fundacao publica</option>
        <option value="empresa_publica" <?= $selectedOrganType === 'empresa_publica' ? 'selected' : '' ?>>Empresa publica</option>
        <option value="sociedade_economia_mista" <?= $selectedOrganType === 'sociedade_economia_mista' ? 'selected' : '' ?>>Sociedade de economia mista</option>
      </select>
    </div>

    <div class="field">
      <label for="company_dependency_type">Vinculacao da empresa</label>
      <?php $selectedDependencyType = old('company_dependency_type', (string) ($organ['company_dependency_type'] ?? '')); ?>
      <select id="company_dependency_type" name="company_dependency_type">
        <option value="">Selecionar</option>
        <option value="independente" <?= $selectedDependencyType === 'independente' ? 'selected' : '' ?>>Independente</option>
        <option value="dependente" <?= $selectedDependencyType === 'dependente' ? 'selected' : '' ?>>Dependente</option>
        <option value="em_liquidacao" <?= $selectedDependencyType === 'em_liquidacao' ? 'selected' : '' ?>>Em liquidacao</option>
      </select>
    </div>

    <div class="field">
      <label for="government_level">Esfera</label>
      <?php $selectedGovernmentLevel = old('government_level', (string) ($organ['government_level'] ?? '')); ?>
      <select id="government_level" name="government_level">
        <option value="">Selecionar</option>
        <option value="federal" <?= $selectedGovernmentLevel === 'federal' ? 'selected' : '' ?>>Federal</option>
        <option value="estadual" <?= $selectedGovernmentLevel === 'estadual' ? 'selected' : '' ?>>Estadual</option>
        <option value="municipal" <?= $selectedGovernmentLevel === 'municipal' ? 'selected' : '' ?>>Municipal</option>
        <option value="distrital" <?= $selectedGovernmentLevel === 'distrital' ? 'selected' : '' ?>>Distrital</option>
      </select>
    </div>

    <div class="field">
      <label for="government_branch">Poder</label>
      <?php $selectedGovernmentBranch = old('government_branch', (string) ($organ['government_branch'] ?? '')); ?>
      <select id="government_branch" name="government_branch">
        <option value="">Selecionar</option>
        <option value="executivo" <?= $selectedGovernmentBranch === 'executivo' ? 'selected' : '' ?>>Executivo</option>
        <option value="legislativo" <?= $selectedGovernmentBranch === 'legislativo' ? 'selected' : '' ?>>Legislativo</option>
        <option value="judiciario" <?= $selectedGovernmentBranch === 'judiciario' ? 'selected' : '' ?>>Judiciario</option>
        <option value="autonomo" <?= $selectedGovernmentBranch === 'autonomo' ? 'selected' : '' ?>>Autonomo</option>
      </select>
    </div>

    <div class="field field-wide">
      <label for="supervising_organ">Orgao supervisor/vinculador</label>
      <input id="supervising_organ" name="supervising_organ" type="text" value="<?= e(old('supervising_organ', (string) ($organ['supervising_organ'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="federative_entity">Ente federativo vinculado</label>
      <input id="federative_entity" name="federative_entity" type="text" value="<?= e(old('federative_entity', (string) ($organ['federative_entity'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="contact_name">Contato</label>
      <input id="contact_name" name="contact_name" type="text" value="<?= e(old('contact_name', (string) ($organ['contact_name'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="contact_email">E-mail de contato</label>
      <input id="contact_email" name="contact_email" type="email" value="<?= e(old('contact_email', (string) ($organ['contact_email'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="contact_phone">Telefone</label>
      <input id="contact_phone" name="contact_phone" type="text" value="<?= e(old('contact_phone', (string) ($organ['contact_phone'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="address_line">Endereço</label>
      <input id="address_line" name="address_line" type="text" value="<?= e(old('address_line', (string) ($organ['address_line'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="city">Cidade</label>
      <input id="city" name="city" type="text" value="<?= e(old('city', (string) ($organ['city'] ?? ''))) ?>">
    </div>

    <div class="field">
      <label for="state">UF</label>
      <input id="state" name="state" type="text" value="<?= e(old('state', (string) ($organ['state'] ?? ''))) ?>" maxlength="2">
    </div>

    <div class="field">
      <label for="zip_code">CEP</label>
      <input id="zip_code" name="zip_code" type="text" value="<?= e(old('zip_code', (string) ($organ['zip_code'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="notes">Observações</label>
      <textarea id="notes" name="notes" rows="4"><?= e(old('notes', (string) ($organ['notes'] ?? ''))) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="company_objective">Objetivo da empresa</label>
      <textarea id="company_objective" name="company_objective" rows="4"><?= e(old('company_objective', (string) ($organ['company_objective'] ?? ''))) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="capital_information">Capital social</label>
      <textarea id="capital_information" name="capital_information" rows="3"><?= e(old('capital_information', (string) ($organ['capital_information'] ?? ''))) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="creation_act">Ato de criacao</label>
      <textarea id="creation_act" name="creation_act" rows="3"><?= e(old('creation_act', (string) ($organ['creation_act'] ?? ''))) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="internal_regulations">Regulamentacao interna</label>
      <textarea id="internal_regulations" name="internal_regulations" rows="3"><?= e(old('internal_regulations', (string) ($organ['internal_regulations'] ?? ''))) ?></textarea>
    </div>

    <div class="field field-wide">
      <label for="subsidiaries">Subsidiarias</label>
      <textarea id="subsidiaries" name="subsidiaries" rows="3"><?= e(old('subsidiaries', (string) ($organ['subsidiaries'] ?? ''))) ?></textarea>
    </div>

    <div class="field">
      <label for="source_name">Fonte do cadastro</label>
      <input id="source_name" name="source_name" type="text" value="<?= e(old('source_name', (string) ($organ['source_name'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="source_url">URL de referencia</label>
      <input id="source_url" name="source_url" type="url" value="<?= e(old('source_url', (string) ($organ['source_url'] ?? ''))) ?>">
    </div>

    <div class="field field-wide">
      <label for="official_website">Site oficial</label>
      <input id="official_website" name="official_website" type="url" value="<?= e(old('official_website', (string) ($organ['official_website'] ?? ''))) ?>">
    </div>

    <div class="form-actions field-wide">
      <a class="btn btn-outline" href="<?= e(url('/organs')) ?>">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= e($submitLabel ?? 'Salvar') ?></button>
    </div>
  </form>
</div>
