<?php

declare(strict_types=1);

$audit = $audit ?? [
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 10,
        'pages' => 1,
    ],
    'filters' => [
        'entity' => '',
        'action' => '',
        'q' => '',
        'from_date' => '',
        'to_date' => '',
    ],
    'options' => [
        'entities' => [],
        'actions' => [],
    ],
];
$auditItems = $audit['items'] ?? [];
$auditPagination = $audit['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$auditFilters = $audit['filters'] ?? ['entity' => '', 'action' => '', 'q' => '', 'from_date' => '', 'to_date' => ''];
$auditOptions = $audit['options'] ?? ['entities' => [], 'actions' => []];
$organId = (int) ($organ['id'] ?? 0);

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatDimension = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', trim($value)));
};

$auditEntityLabel = static function (string $entity): string {
    return match ($entity) {
        'organ' => 'Orgao',
        'person' => 'Pessoa',
        'assignment' => 'Movimentacao',
        'assignment_checklist' => 'Checklist da movimentacao',
        'assignment_checklist_item' => 'Item de checklist',
        'timeline_event' => 'Timeline',
        'document' => 'Documento',
        'cost_plan' => 'Plano de custos',
        'cost_plan_item' => 'Item de custo',
        'reimbursement_entry' => 'Reembolso real',
        'analyst_pending_item' => 'Pendencia operacional',
        'process_comment' => 'Comentario interno',
        'process_admin_timeline_note' => 'Timeline administrativa',
        default => ucfirst(str_replace('_', ' ', $entity)),
    };
};

$prettyJson = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '-';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return $value;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($pretty) && trim($pretty) !== '' ? $pretty : '-';
};

$buildShowUrl = static function (array $overrides = [], array $remove = []) use ($organId, $auditPagination, $auditFilters): string {
    $params = [
        'id' => $organId,
        'audit_page' => max(1, (int) ($auditPagination['page'] ?? 1)),
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/organs/show?' . http_build_query($params));
};

$buildAuditPageUrl = static fn (int $targetPage): string => $buildShowUrl(['audit_page' => $targetPage]);
$buildAuditExportUrl = static function () use ($organId, $auditFilters): string {
    $params = [
        'organ_id' => $organId,
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/organs/audit/export?' . http_build_query($params));
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($organ['name'] ?? 'Orgao')) ?></h2>
      <p class="muted">Detalhes cadastrais do orgao de origem.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/organs')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/organs/edit?id=' . (int) ($organ['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Sigla:</strong> <?= e((string) ($organ['acronym'] ?? '-')) ?></div>
    <div><strong>CNPJ:</strong> <?= e((string) ($organ['cnpj'] ?? '-')) ?></div>
    <div><strong>Tipo institucional:</strong> <?= e($formatDimension((string) ($organ['organ_type'] ?? ''))) ?></div>
    <div><strong>Esfera:</strong> <?= e($formatDimension((string) ($organ['government_level'] ?? ''))) ?></div>
    <div><strong>Poder:</strong> <?= e($formatDimension((string) ($organ['government_branch'] ?? ''))) ?></div>
    <div><strong>Orgao supervisor:</strong> <?= e((string) ($organ['supervising_organ'] ?? '-')) ?></div>
    <div><strong>Contato:</strong> <?= e((string) ($organ['contact_name'] ?? '-')) ?></div>
    <div><strong>E-mail:</strong> <?= e((string) ($organ['contact_email'] ?? '-')) ?></div>
    <div><strong>Telefone:</strong> <?= e((string) ($organ['contact_phone'] ?? '-')) ?></div>
    <div><strong>Cidade/UF:</strong> <?= e((string) ($organ['city'] ?? '-')) ?><?= !empty($organ['state']) ? ' / ' . e((string) $organ['state']) : '' ?></div>
    <div><strong>CEP:</strong> <?= e((string) ($organ['zip_code'] ?? '-')) ?></div>
    <div><strong>Endereco:</strong> <?= e((string) ($organ['address_line'] ?? '-')) ?></div>
    <div><strong>Fonte:</strong> <?= e((string) ($organ['source_name'] ?? '-')) ?></div>
    <div>
      <strong>Referencia:</strong>
      <?php if (!empty($organ['source_url'])): ?>
        <a href="<?= e((string) $organ['source_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir link</a>
      <?php else: ?>
        -
      <?php endif; ?>
    </div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($organ['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Acoes rapidas</h3>
      <p class="muted">Atalhos para continuar o fluxo operacional.</p>
    </div>
  </div>
  <div class="actions-inline">
    <a class="btn btn-outline" href="<?= e(url('/people?organ_id=' . (int) ($organ['id'] ?? 0))) ?>">Ver pessoas vinculadas</a>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Historico consolidado de pessoa e orgao</h3>
      <p class="muted">Trilha unificada do orgao e das pessoas vinculadas (auditoria consolidada).</p>
    </div>
  </div>

  <?php if (($canViewAudit ?? false) !== true): ?>
    <p class="muted">Voce nao possui permissao para visualizar a trilha de auditoria.</p>
  <?php else: ?>
    <form method="get" action="<?= e(url('/organs/show')) ?>" class="audit-filters">
      <input type="hidden" name="id" value="<?= e((string) $organId) ?>">
      <input type="hidden" name="audit_page" value="1">
      <div class="form-grid">
        <div class="field">
          <label for="audit_q">Busca</label>
          <input id="audit_q" name="audit_q" type="text" value="<?= e((string) ($auditFilters['q'] ?? '')) ?>" placeholder="Entidade, acao, usuario ou IP">
        </div>
        <div class="field">
          <label for="audit_entity">Entidade</label>
          <select id="audit_entity" name="audit_entity">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['entities'] ?? []) as $entityOption): ?>
              <?php $entityOption = (string) $entityOption; ?>
              <option value="<?= e($entityOption) ?>" <?= $entityOption === (string) ($auditFilters['entity'] ?? '') ? 'selected' : '' ?>>
                <?= e($auditEntityLabel($entityOption)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_action">Acao</label>
          <select id="audit_action" name="audit_action">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['actions'] ?? []) as $actionOption): ?>
              <?php $actionOption = (string) $actionOption; ?>
              <option value="<?= e($actionOption) ?>" <?= $actionOption === (string) ($auditFilters['action'] ?? '') ? 'selected' : '' ?>>
                <?= e($actionOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_from">De</label>
          <input id="audit_from" name="audit_from" type="date" value="<?= e((string) ($auditFilters['from_date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="audit_to">Ate</label>
          <input id="audit_to" name="audit_to" type="date" value="<?= e((string) ($auditFilters['to_date'] ?? '')) ?>">
        </div>
      </div>
      <div class="form-actions">
        <a class="btn btn-outline" href="<?= e($buildShowUrl(['audit_page' => 1], ['audit_entity', 'audit_action', 'audit_q', 'audit_from', 'audit_to'])) ?>">Limpar</a>
        <a class="btn btn-outline" href="<?= e($buildAuditExportUrl()) ?>">Exportar CSV</a>
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>

    <?php if ($auditItems === []): ?>
      <p class="muted">Nenhum registro encontrado para os filtros informados.</p>
    <?php else: ?>
      <div class="audit-list">
        <?php foreach ($auditItems as $entry): ?>
          <?php
            $entity = (string) ($entry['entity'] ?? '');
            $entityId = isset($entry['entity_id']) ? (int) $entry['entity_id'] : 0;
            $beforeData = $prettyJson($entry['before_data'] ?? null);
            $afterData = $prettyJson($entry['after_data'] ?? null);
            $metadataData = $prettyJson($entry['metadata'] ?? null);
            $hasDetails = $beforeData !== '-' || $afterData !== '-' || $metadataData !== '-';
          ?>
          <article class="audit-item">
            <div class="audit-item-head">
              <div class="audit-item-title">
                <span class="badge badge-neutral"><?= e($auditEntityLabel($entity)) ?></span>
                <strong><?= e((string) ($entry['action'] ?? '-')) ?></strong>
                <?php if ($entityId > 0): ?>
                  <span class="muted">#<?= e((string) $entityId) ?></span>
                <?php endif; ?>
              </div>
              <span class="muted"><?= e($formatDateTime((string) ($entry['created_at'] ?? ''))) ?></span>
            </div>

            <div class="audit-meta">
              <span><strong>Usuario:</strong> <?= e((string) ($entry['user_name'] ?? 'Sistema')) ?></span>
              <span><strong>IP:</strong> <?= e((string) ($entry['ip'] ?? '-')) ?></span>
            </div>

            <?php if ($hasDetails): ?>
              <details class="audit-details">
                <summary>Ver dados</summary>
                <div class="audit-payload-grid">
                  <div>
                    <strong>Antes</strong>
                    <pre><?= e($beforeData) ?></pre>
                  </div>
                  <div>
                    <strong>Depois</strong>
                    <pre><?= e($afterData) ?></pre>
                  </div>
                  <div class="audit-payload-wide">
                    <strong>Metadata</strong>
                    <pre><?= e($metadataData) ?></pre>
                  </div>
                </div>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <?php
        $auditTotal = (int) ($auditPagination['total'] ?? 0);
        $auditPage = (int) ($auditPagination['page'] ?? 1);
        $auditPerPage = max(1, (int) ($auditPagination['per_page'] ?? 10));
        $auditPages = max(1, (int) ($auditPagination['pages'] ?? 1));
        $auditStart = $auditTotal > 0 ? (($auditPage - 1) * $auditPerPage) + 1 : 0;
        $auditEnd = min($auditTotal, $auditPage * $auditPerPage);
      ?>
      <div class="pagination-row">
        <span class="muted">Exibindo <?= e((string) $auditStart) ?>-<?= e((string) $auditEnd) ?> de <?= e((string) $auditTotal) ?> registros</span>
        <div class="pagination-links">
          <?php if ($auditPage > 1): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage - 1)) ?>">Anterior</a>
          <?php endif; ?>
          <span class="muted">Pagina <?= e((string) $auditPage) ?> de <?= e((string) $auditPages) ?></span>
          <?php if ($auditPage < $auditPages): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage + 1)) ?>">Proxima</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
