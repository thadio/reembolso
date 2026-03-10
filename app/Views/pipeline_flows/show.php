<?php

declare(strict_types=1);

$flow = $flow ?? null;
$steps = $steps ?? [];
$transitions = $transitions ?? [];
$statusCatalog = $statusCatalog ?? [];
$nodeKindOptions = $nodeKindOptions ?? [
    ['value' => 'activity', 'label' => 'Atividade'],
    ['value' => 'gateway', 'label' => 'Decisão'],
    ['value' => 'final', 'label' => 'Final'],
];
$documentTypeCatalog = $documentTypeCatalog ?? [];

$nodeKindLabel = static function (string $kind): string {
    return match ($kind) {
        'gateway' => 'Decisão',
        'final' => 'Final',
        default => 'Atividade',
    };
};

$flowId = (int) ($flow['id'] ?? 0);
$diagramPayload = [
    'flow_id' => $flowId,
    'diagram_xml' => (string) ($flow['bpmn_diagram_xml'] ?? ''),
    'steps' => [],
    'transitions' => [],
];

foreach ($steps as $step) {
    $diagramPayload['steps'][] = [
        'status_id' => (int) ($step['status_id'] ?? 0),
        'status_code' => (string) ($step['status_code'] ?? ''),
        'status_label' => (string) ($step['status_label'] ?? ''),
        'node_kind' => (string) ($step['node_kind'] ?? 'activity'),
        'sort_order' => (int) ($step['sort_order'] ?? 10),
        'is_initial' => (int) ($step['is_initial'] ?? 0),
        'is_active' => (int) ($step['is_active'] ?? 0),
        'requires_evidence_close' => (int) ($step['requires_evidence_close'] ?? 0),
        'step_tags' => (string) ($step['step_tags'] ?? ''),
    ];
}

foreach ($transitions as $transition) {
    $diagramPayload['transitions'][] = [
        'id' => (int) ($transition['id'] ?? 0),
        'from_status_id' => (int) ($transition['from_status_id'] ?? 0),
        'to_status_id' => (int) ($transition['to_status_id'] ?? 0),
        'transition_label' => (string) ($transition['transition_label'] ?? ''),
        'action_label' => (string) ($transition['action_label'] ?? ''),
        'sort_order' => (int) ($transition['sort_order'] ?? 10),
        'is_active' => (int) ($transition['is_active'] ?? 0),
    ];
}

$diagramPayloadJson = json_encode($diagramPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($diagramPayloadJson)) {
    $diagramPayloadJson = '{}';
}
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($flow['name'] ?? 'Fluxo BPMN')) ?></h2>
      <p class="muted"><?= e((string) ($flow['description'] ?? 'Sem descrição.')) ?></p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/pipeline-flows')) ?>">Voltar</a>
      <a class="btn btn-primary" href="<?= e(url('/pipeline-flows/edit?id=' . (int) ($flow['id'] ?? 0))) ?>">Editar fluxo</a>
    </div>
  </div>

  <div class="details-grid">
    <div>
      <strong>Fluxo padrão:</strong>
      <span class="badge <?= (int) ($flow['is_default'] ?? 0) === 1 ? 'badge-info' : 'badge-neutral' ?>">
        <?= (int) ($flow['is_default'] ?? 0) === 1 ? 'Sim' : 'Não' ?>
      </span>
    </div>
    <div>
      <strong>Status do fluxo:</strong>
      <span class="badge <?= (int) ($flow['is_active'] ?? 0) === 1 ? 'badge-success' : 'badge-neutral' ?>">
        <?= (int) ($flow['is_active'] ?? 0) === 1 ? 'Ativo' : 'Inativo' ?>
      </span>
    </div>
    <div><strong>Criado em:</strong> <?= e((string) ($flow['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizado em:</strong> <?= e((string) ($flow['updated_at'] ?? '-')) ?></div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.11.1/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.11.1/dist/assets/bpmn-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.11.1/dist/assets/bpmn-font/css/bpmn.css">

<div class="card">
  <div class="header-row">
    <div>
      <h3>Diagrama BPMN visual</h3>
      <p class="muted">Edite o fluxo por drag and drop no canvas e salve o XML BPMN.</p>
    </div>
  </div>

  <div class="bpmn-editor-toolbar">
    <button type="button" id="pipeline-bpmn-fit" class="btn btn-outline">Ajustar zoom</button>
    <button type="button" id="pipeline-bpmn-save" class="btn btn-primary">Salvar diagrama</button>
    <span id="pipeline-bpmn-status" class="bpmn-editor-status muted">Carregando modelador BPMN...</span>
  </div>

  <div id="pipeline-bpmn-editor" class="bpmn-editor-canvas" role="region" aria-label="Editor visual BPMN"></div>

  <form id="pipeline-bpmn-save-form" method="post" action="<?= e(url('/pipeline-flows/diagram/update')) ?>" class="bpmn-editor-save-form">
    <?= csrf_field() ?>
    <input type="hidden" name="flow_id" value="<?= e((string) $flowId) ?>">
    <textarea id="pipeline-bpmn-xml" name="bpmn_xml"></textarea>
  </form>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Etapas do fluxo</h3>
      <p class="muted">Etapas podem ser atividades, decisões ou finais. A ordem define o desenho principal.</p>
    </div>
  </div>

  <?php if ($steps === []): ?>
    <p class="muted">Nenhuma etapa configurada neste fluxo.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Rótulo</th>
            <th>Tipo</th>
            <th>Documentos esperados</th>
            <th>Ordem</th>
            <th>Evidência</th>
            <th>Inicial</th>
            <th>Ativa</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($steps as $step): ?>
            <?php
              $statusId = (int) ($step['status_id'] ?? 0);
              $stepNodeKind = (string) ($step['node_kind'] ?? 'activity');
              $stepSortOrder = (int) ($step['sort_order'] ?? 10);
              $stepIsInitial = (int) ($step['is_initial'] ?? 0) === 1;
              $stepIsActive = (int) ($step['is_active'] ?? 0) === 1;
              $stepRequiresEvidenceClose = (int) ($step['requires_evidence_close'] ?? 0) === 1;
              $stepTags = trim((string) ($step['step_tags'] ?? ''));
              $statusIsActive = (int) ($step['status_is_active'] ?? 0) === 1;
              $stepExpectedDocumentTypes = is_array($step['expected_document_types'] ?? null) ? $step['expected_document_types'] : [];
              $stepExpectedDocumentTypeIds = is_array($step['expected_document_type_ids'] ?? null) ? $step['expected_document_type_ids'] : [];
            ?>
            <tr>
              <td><?= e((string) ($step['status_code'] ?? '-')) ?></td>
              <td>
                <strong><?= e((string) ($step['status_label'] ?? '-')) ?></strong>
                <?php if (trim((string) ($step['status_next_action_label'] ?? '')) !== ''): ?>
                  <div class="muted">Ação: <?= e((string) $step['status_next_action_label']) ?></div>
                <?php endif; ?>
                <?php if (trim((string) ($step['status_event_type'] ?? '')) !== ''): ?>
                  <div class="muted">Evento: <?= e((string) $step['status_event_type']) ?></div>
                <?php endif; ?>
                <?php if ($stepTags !== ''): ?>
                  <div class="muted">Tags: <?= e($stepTags) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($nodeKindLabel($stepNodeKind)) ?></td>
              <td>
                <?php if ($stepExpectedDocumentTypes === []): ?>
                  <span class="muted">Nao definido</span>
                <?php else: ?>
                  <div class="bpmn-tags-list">
                    <?php foreach ($stepExpectedDocumentTypes as $expectedType): ?>
                      <?php
                        $expectedName = trim((string) ($expectedType['document_type_name'] ?? 'Tipo'));
                        $expectedActive = (int) ($expectedType['document_type_is_active'] ?? 0) === 1;
                      ?>
                      <span class="badge <?= $expectedActive ? 'badge-info' : 'badge-neutral' ?>">
                        <?= e($expectedName . ($expectedActive ? '' : ' (inativo)')) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= e((string) $stepSortOrder) ?></td>
              <td><span class="badge <?= $stepRequiresEvidenceClose ? 'badge-warning' : 'badge-neutral' ?>"><?= $stepRequiresEvidenceClose ? 'Obrigatória' : 'Opcional' ?></span></td>
              <td><span class="badge <?= $stepIsInitial ? 'badge-info' : 'badge-neutral' ?>"><?= $stepIsInitial ? 'Sim' : 'Não' ?></span></td>
              <td><span class="badge <?= $stepIsActive ? 'badge-success' : 'badge-neutral' ?>"><?= $stepIsActive ? 'Sim' : 'Não' ?></span></td>
              <td class="actions-cell">
                <details>
                  <summary>Editar etapa</summary>
                  <form method="post" action="<?= e(url('/pipeline-flows/steps/upsert')) ?>" class="form-grid sp-top-sm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">
                    <input type="hidden" name="status_id" value="<?= e((string) $statusId) ?>">
                    <div class="field field-wide">
                      <label>Rótulo da etapa</label>
                      <input name="status_label" type="text" value="<?= e((string) ($step['status_label'] ?? '')) ?>" required>
                    </div>
                    <div class="field field-wide">
                      <label>Texto da ação</label>
                      <input name="status_next_action_label" type="text" value="<?= e((string) ($step['status_next_action_label'] ?? '')) ?>" placeholder="Texto da ação">
                    </div>
                    <div class="field field-wide">
                      <label>Evento da timeline</label>
                      <input name="status_event_type" type="text" value="<?= e((string) ($step['status_event_type'] ?? '')) ?>" placeholder="Evento de timeline">
                    </div>
                    <div class="field">
                      <label>Tipo da etapa</label>
                      <select name="step_node_kind">
                        <?php foreach ($nodeKindOptions as $kindOption): ?>
                          <?php $kindValue = (string) ($kindOption['value'] ?? 'activity'); ?>
                          <option value="<?= e($kindValue) ?>" <?= $kindValue === $stepNodeKind ? 'selected' : '' ?>>
                            <?= e((string) ($kindOption['label'] ?? $kindValue)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="field">
                      <label>Ordem no fluxo</label>
                      <input name="step_sort_order" type="number" min="1" value="<?= e((string) $stepSortOrder) ?>" required>
                    </div>
                    <div class="field field-wide">
                      <label>Tags da etapa</label>
                      <input name="step_tags" type="text" value="<?= e($stepTags) ?>" placeholder="Ex.: data_transferencia_efetiva, financeiro">
                    </div>
                    <div class="field field-wide">
                      <label>Tipos de documento esperados</label>
                      <?php if ($documentTypeCatalog === []): ?>
                        <p class="muted">Nenhum tipo de documento cadastrado.</p>
                      <?php else: ?>
                        <div class="checkbox-grid">
                          <?php foreach ($documentTypeCatalog as $typeOption): ?>
                            <?php
                              $typeOptionId = (int) ($typeOption['id'] ?? 0);
                              $typeOptionIsActive = (int) ($typeOption['is_active'] ?? 0) === 1;
                              $typeChecked = in_array($typeOptionId, $stepExpectedDocumentTypeIds, true);
                            ?>
                            <label>
                              <input type="checkbox" name="step_document_type_ids[]" value="<?= e((string) $typeOptionId) ?>" <?= $typeChecked ? 'checked' : '' ?>>
                              <?= e((string) ($typeOption['name'] ?? 'Tipo')) ?><?= $typeOptionIsActive ? '' : ' (inativo)' ?>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="field field-wide">
                      <label><input type="checkbox" name="status_is_active" value="1" <?= $statusIsActive ? 'checked' : '' ?>> Status ativo no catálogo global</label>
                      <label><input type="checkbox" name="step_is_initial" value="1" <?= $stepIsInitial ? 'checked' : '' ?>> Definir como etapa inicial</label>
                      <label><input type="checkbox" name="step_is_active" value="1" <?= $stepIsActive ? 'checked' : '' ?>> Etapa ativa no fluxo</label>
                      <label><input type="checkbox" name="step_requires_evidence_close" value="1" <?= $stepRequiresEvidenceClose ? 'checked' : '' ?>> Exigir anexo ou link para encerrar etapa</label>
                    </div>
                    <div class="form-actions field-wide">
                      <button type="submit" class="btn btn-outline">Salvar etapa</button>
                    </div>
                  </form>
                </details>
                <form method="post" action="<?= e(url('/pipeline-flows/steps/delete')) ?>" onsubmit="return confirm('Remover esta etapa e suas transições?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">
                  <input type="hidden" name="status_id" value="<?= e((string) $statusId) ?>">
                  <button type="submit" class="btn btn-danger">Remover etapa</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="card sp-top-xl">
    <div class="header-row">
      <div>
        <h4>Adicionar etapa</h4>
        <p class="muted">Reutilize um status existente ou crie um novo código de etapa.</p>
      </div>
    </div>
    <form method="post" action="<?= e(url('/pipeline-flows/steps/upsert')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">

      <div class="field">
        <label for="step_existing_status_id">Status existente</label>
        <select id="step_existing_status_id" name="status_id">
          <option value="0">Criar novo status</option>
          <?php foreach ($statusCatalog as $statusOption): ?>
            <?php
              $statusOptionId = (int) ($statusOption['id'] ?? 0);
              $statusOptionActive = (int) ($statusOption['is_active'] ?? 0) === 1;
            ?>
            <option value="<?= e((string) $statusOptionId) ?>">
              <?= e((string) ($statusOption['code'] ?? '')) ?> · <?= e((string) ($statusOption['label'] ?? '')) ?><?= $statusOptionActive ? '' : ' (inativo)' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="step_status_code">Código (novo status)</label>
        <input id="step_status_code" name="status_code" type="text" maxlength="60" placeholder="ex.: validacao_mgi">
      </div>

      <div class="field">
        <label for="step_status_label">Rótulo da etapa *</label>
        <input id="step_status_label" name="status_label" type="text" required>
      </div>

      <div class="field">
        <label for="step_status_next_action_label">Texto da ação</label>
        <input id="step_status_next_action_label" name="status_next_action_label" type="text" placeholder="Ex.: Validar no MGI">
      </div>

      <div class="field">
        <label for="step_status_event_type">Evento da timeline</label>
        <input id="step_status_event_type" name="status_event_type" type="text" placeholder="Ex.: pipeline.validacao_mgi">
      </div>

      <div class="field">
        <label for="step_node_kind_new">Tipo da etapa</label>
        <select id="step_node_kind_new" name="step_node_kind">
          <?php foreach ($nodeKindOptions as $kindOption): ?>
            <option value="<?= e((string) ($kindOption['value'] ?? 'activity')) ?>">
              <?= e((string) ($kindOption['label'] ?? 'Atividade')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="step_sort_order_new">Ordem no fluxo</label>
        <input id="step_sort_order_new" name="step_sort_order" type="number" min="1" value="10" required>
      </div>
      <div class="field field-wide">
        <label for="step_tags_new">Tags da etapa</label>
        <input id="step_tags_new" name="step_tags" type="text" placeholder="Ex.: data_transferencia_efetiva, financeiro">
      </div>
      <div class="field field-wide">
        <label>Tipos de documento esperados</label>
        <?php if ($documentTypeCatalog === []): ?>
          <p class="muted">Nenhum tipo de documento cadastrado.</p>
        <?php else: ?>
          <div class="checkbox-grid">
            <?php foreach ($documentTypeCatalog as $typeOption): ?>
              <?php
                $typeOptionId = (int) ($typeOption['id'] ?? 0);
                $typeOptionIsActive = (int) ($typeOption['is_active'] ?? 0) === 1;
              ?>
              <label>
                <input type="checkbox" name="step_document_type_ids[]" value="<?= e((string) $typeOptionId) ?>">
                <?= e((string) ($typeOption['name'] ?? 'Tipo')) ?><?= $typeOptionIsActive ? '' : ' (inativo)' ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label><input type="checkbox" name="step_is_initial" value="1"> Definir como etapa inicial</label>
        <label><input type="checkbox" name="step_is_active" value="1" checked> Etapa ativa no fluxo</label>
        <label><input type="checkbox" name="status_is_active" value="1" checked> Status ativo no catálogo</label>
        <label><input type="checkbox" name="step_requires_evidence_close" value="1"> Exigir anexo ou link para encerrar etapa</label>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Adicionar etapa</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Transições BPMN</h3>
      <p class="muted">Defina caminhos entre etapas (inclusive múltiplas saídas para decisões).</p>
    </div>
  </div>

  <?php if ($transitions === []): ?>
    <p class="muted">Nenhuma transição cadastrada para este fluxo.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Origem</th>
            <th>Destino</th>
            <th>Rótulo da transição</th>
            <th>Ação exibida</th>
            <th>Evento</th>
            <th>Ordem</th>
            <th>Ativa</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transitions as $transition): ?>
            <?php
              $transitionId = (int) ($transition['id'] ?? 0);
              $fromStatusId = (int) ($transition['from_status_id'] ?? 0);
              $toStatusId = (int) ($transition['to_status_id'] ?? 0);
              $transitionIsActive = (int) ($transition['is_active'] ?? 0) === 1;
            ?>
            <tr>
              <td><?= e((string) ($transition['from_status_label'] ?? '-')) ?></td>
              <td><?= e((string) ($transition['to_status_label'] ?? '-')) ?></td>
              <td colspan="6">
                <form method="post" action="<?= e(url('/pipeline-flows/transitions/upsert')) ?>" class="form-grid">
                  <?= csrf_field() ?>
                  <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">
                  <input type="hidden" name="transition_id" value="<?= e((string) $transitionId) ?>">
                  <div class="field">
                    <label>Origem</label>
                    <select name="from_status_id" required>
                      <?php foreach ($steps as $stepOption): ?>
                        <?php $stepOptionStatusId = (int) ($stepOption['status_id'] ?? 0); ?>
                        <option value="<?= e((string) $stepOptionStatusId) ?>" <?= $stepOptionStatusId === $fromStatusId ? 'selected' : '' ?>>
                          <?= e((string) ($stepOption['status_label'] ?? $stepOption['status_code'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field">
                    <label>Destino</label>
                    <select name="to_status_id" required>
                      <?php foreach ($steps as $stepOption): ?>
                        <?php $stepOptionStatusId = (int) ($stepOption['status_id'] ?? 0); ?>
                        <option value="<?= e((string) $stepOptionStatusId) ?>" <?= $stepOptionStatusId === $toStatusId ? 'selected' : '' ?>>
                          <?= e((string) ($stepOption['status_label'] ?? $stepOption['status_code'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field">
                    <label>Rótulo da transição</label>
                    <input name="transition_label" type="text" value="<?= e((string) ($transition['transition_label'] ?? '')) ?>">
                  </div>
                  <div class="field">
                    <label>Texto da ação</label>
                    <input name="action_label" type="text" value="<?= e((string) ($transition['action_label'] ?? '')) ?>">
                  </div>
                  <div class="field">
                    <label>Evento</label>
                    <input name="event_type" type="text" value="<?= e((string) ($transition['event_type'] ?? '')) ?>">
                  </div>
                  <div class="field">
                    <label>Ordem</label>
                    <input name="sort_order" type="number" min="1" value="<?= e((string) ((int) ($transition['sort_order'] ?? 10))) ?>" required>
                  </div>
                  <div class="field">
                    <label><input type="checkbox" name="is_active" value="1" <?= $transitionIsActive ? 'checked' : '' ?>> Transição ativa</label>
                  </div>
                  <div class="form-actions field-wide">
                    <button type="submit" class="btn btn-outline">Salvar transição</button>
                  </div>
                </form>
                <form method="post" action="<?= e(url('/pipeline-flows/transitions/delete')) ?>" onsubmit="return confirm('Remover esta transição?');" class="sp-top-sm">
                  <?= csrf_field() ?>
                  <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">
                  <input type="hidden" name="transition_id" value="<?= e((string) $transitionId) ?>">
                  <button type="submit" class="btn btn-danger">Remover transição</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="card sp-top-xl">
    <div class="header-row">
      <div>
        <h4>Adicionar transição</h4>
        <p class="muted">Use múltiplas transições de uma mesma origem para pontos de decisão.</p>
      </div>
    </div>
    <form method="post" action="<?= e(url('/pipeline-flows/transitions/upsert')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="flow_id" value="<?= e((string) ((int) ($flow['id'] ?? 0))) ?>">

      <div class="field">
        <label for="transition_from_status_id">Origem *</label>
        <select id="transition_from_status_id" name="from_status_id" required>
          <option value="">Selecione</option>
          <?php foreach ($steps as $stepOption): ?>
            <option value="<?= e((string) ((int) ($stepOption['status_id'] ?? 0))) ?>">
              <?= e((string) ($stepOption['status_label'] ?? $stepOption['status_code'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="transition_to_status_id">Destino *</label>
        <select id="transition_to_status_id" name="to_status_id" required>
          <option value="">Selecione</option>
          <?php foreach ($steps as $stepOption): ?>
            <option value="<?= e((string) ((int) ($stepOption['status_id'] ?? 0))) ?>">
              <?= e((string) ($stepOption['status_label'] ?? $stepOption['status_code'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="transition_label_new">Rótulo da transição</label>
        <input id="transition_label_new" name="transition_label" type="text" placeholder="Ex.: Aprovado pelo gestor">
      </div>

      <div class="field">
        <label for="transition_action_label_new">Texto da ação</label>
        <input id="transition_action_label_new" name="action_label" type="text" placeholder="Ex.: Encaminhar para CDO">
      </div>

      <div class="field">
        <label for="transition_event_type_new">Evento</label>
        <input id="transition_event_type_new" name="event_type" type="text" placeholder="Ex.: pipeline.encaminhado_cdo">
      </div>

      <div class="field">
        <label for="transition_sort_order_new">Ordem</label>
        <input id="transition_sort_order_new" name="sort_order" type="number" min="1" value="10" required>
      </div>

      <div class="field">
        <label><input type="checkbox" name="is_active" value="1" checked> Transição ativa</label>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Adicionar transição</button>
      </div>
    </form>
  </div>
</div>
<script src="https://unpkg.com/bpmn-js@17.11.1/dist/bpmn-modeler.development.js"></script>
<script>
  window.__PIPELINE_BPMN_EDITOR__ = <?= $diagramPayloadJson ?>;
</script>
