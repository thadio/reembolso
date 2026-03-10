<?php

declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [];
$logs = is_array($logs ?? null) ? $logs : [];
$pagination = is_array($pagination ?? null) ? $pagination : ['total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];
$summary = is_array($summary ?? null) ? $summary : [];
$actions = is_array($actions ?? null) ? $actions : [];
$sensitivities = is_array($sensitivities ?? null) ? $sensitivities : [];
$users = is_array($users ?? null) ? $users : [];
$policies = is_array($policies ?? null) ? $policies : [];
$runs = is_array($runs ?? null) ? $runs : [];
$latestRun = is_array($latestRun ?? null) ? $latestRun : null;
$canManage = ($canManage ?? false) === true;
$sensitivityLabel = is_callable($sensitivityLabel ?? null)
    ? $sensitivityLabel
    : static fn (string $value): string => ucfirst(str_replace('_', ' ', $value));

$sort = (string) ($filters['sort'] ?? 'created_at');
$dir = (string) ($filters['dir'] ?? 'desc');
$perPage = (int) ($filters['per_page'] ?? 20);

$baseParams = [
    'q' => (string) ($filters['q'] ?? ''),
    'action' => (string) ($filters['action'] ?? ''),
    'sensitivity' => (string) ($filters['sensitivity'] ?? ''),
    'user_id' => (string) ($filters['user_id'] ?? '0'),
    'from_date' => (string) ($filters['from_date'] ?? ''),
    'to_date' => (string) ($filters['to_date'] ?? ''),
    'sort' => $sort,
    'dir' => $dir,
    'per_page' => (string) $perPage,
    'page' => (string) ($pagination['page'] ?? 1),
];

$buildUrl = static function (array $replace = []) use ($baseParams): string {
    $params = array_merge($baseParams, array_map(static fn ($value): string => (string) $value, $replace));

    return url('/lgpd?' . http_build_query($params));
};

$buildExportUrl = static function () use ($baseParams): string {
    $params = $baseParams;
    unset($params['page'], $params['per_page']);

    return url('/lgpd/export/access-csv?' . http_build_query($params));
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$normalizeMetadata = static function (mixed $payload): string {
    if (!is_string($payload) || trim($payload) === '') {
        return '';
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return trim($payload);
    }

    $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : trim($payload);
};

$decodeRunSummary = static function (mixed $payload): array {
    if (!is_string($payload) || trim($payload) === '') {
        return [];
    }

    $decoded = json_decode($payload, true);

    return is_array($decoded) ? $decoded : [];
};
?>

<div class="card">
  <div class="header-row">
    <div>
      <p class="muted">Trilha de acesso sensivel, relatorio consolidado e politicas parametrizaveis de retencao/anonimizacao.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e($buildExportUrl()) ?>">Exportar CSV</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/lgpd')) ?>" class="filters-row filters-reports">
    <div class="field">
      <label for="q">Busca</label>
      <input id="q" name="q" type="text" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Acao, entidade, alvo, usuario ou IP">
    </div>

    <div class="field">
      <label for="action">Acao</label>
      <select id="action" name="action">
        <option value="">Todas</option>
        <?php foreach ($actions as $option): ?>
          <?php $value = (string) ($option['action'] ?? ''); ?>
          <option value="<?= e($value) ?>" <?= (string) ($filters['action'] ?? '') === $value ? 'selected' : '' ?>>
            <?= e($value) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="sensitivity">Sensibilidade</label>
      <select id="sensitivity" name="sensitivity">
        <option value="">Todas</option>
        <?php foreach ($sensitivities as $option): ?>
          <?php $value = (string) ($option['sensitivity'] ?? ''); ?>
          <option value="<?= e($value) ?>" <?= (string) ($filters['sensitivity'] ?? '') === $value ? 'selected' : '' ?>>
            <?= e($sensitivityLabel($value)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="user_id">Usuario</label>
      <select id="user_id" name="user_id">
        <option value="0">Todos</option>
        <?php foreach ($users as $user): ?>
          <?php $userId = (int) ($user['id'] ?? 0); ?>
          <option value="<?= e((string) $userId) ?>" <?= (int) ($filters['user_id'] ?? 0) === $userId ? 'selected' : '' ?>>
            <?= e((string) ($user['name'] ?? 'Usuario')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="from_date">Data inicial</label>
      <input id="from_date" name="from_date" type="date" value="<?= e((string) ($filters['from_date'] ?? '')) ?>">
    </div>

    <div class="field">
      <label for="to_date">Data final</label>
      <input id="to_date" name="to_date" type="date" value="<?= e((string) ($filters['to_date'] ?? '')) ?>">
    </div>

    <div class="field">
      <label for="sort">Ordenar por</label>
      <select id="sort" name="sort">
        <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Data</option>
        <option value="user" <?= $sort === 'user' ? 'selected' : '' ?>>Usuario</option>
        <option value="action" <?= $sort === 'action' ? 'selected' : '' ?>>Acao</option>
        <option value="sensitivity" <?= $sort === 'sensitivity' ? 'selected' : '' ?>>Sensibilidade</option>
        <option value="entity" <?= $sort === 'entity' ? 'selected' : '' ?>>Entidade</option>
      </select>
    </div>

    <div class="field">
      <label for="dir">Direcao</label>
      <select id="dir" name="dir">
        <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Decrescente</option>
        <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Crescente</option>
      </select>
    </div>

    <div class="field">
      <label for="per_page">Itens por pagina</label>
      <select id="per_page" name="per_page">
        <?php foreach ([10, 20, 30, 50, 100] as $size): ?>
          <option value="<?= e((string) $size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field field-actions">
      <label>&nbsp;</label>
      <div class="actions-inline">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a class="btn btn-outline" href="<?= e(url('/lgpd')) ?>">Limpar</a>
      </div>
    </div>
  </form>
</div>

<div class="grid-kpi reports-kpi-grid">
  <article class="card kpi-card">
    <p class="kpi-label">Registros</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['total'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Acessos CPF</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['cpf_access'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Acessos a documentos</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['document_access'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Usuarios distintos</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['distinct_users'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Ultimas 24h</p>
    <p class="kpi-value"><?= e((string) (int) ($summary['last_24h'] ?? 0)) ?></p>
  </article>
</div>

<div class="card">
  <h3>Trilha de acesso sensivel</h3>
  <?php if ($logs === []): ?>
    <div class="empty-state">
      <p>Nenhum acesso encontrado para os filtros atuais.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data/hora</th>
            <th>Usuario</th>
            <th>Acao</th>
            <th>Sensibilidade</th>
            <th>Entidade</th>
            <th>Alvo</th>
            <th>Contexto</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $row): ?>
            <?php
              $subjectPersonId = isset($row['subject_person_id']) ? (int) $row['subject_person_id'] : 0;
              $metadata = $normalizeMetadata($row['metadata'] ?? null);
            ?>
            <tr>
              <td><?= e($formatDateTime((string) ($row['created_at'] ?? ''))) ?></td>
              <td>
                <strong><?= e((string) ($row['user_name'] ?? 'Sistema')) ?></strong>
                <div class="muted"><?= e((string) ($row['user_email'] ?? '-')) ?></div>
              </td>
              <td>
                <?= e((string) ($row['action'] ?? '-')) ?>
                <?php if ($metadata !== ''): ?>
                  <div class="muted" title="<?= e($metadata) ?>">metadata: <?= e(mb_substr($metadata, 0, 120)) ?><?= mb_strlen($metadata) > 120 ? '...' : '' ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($sensitivityLabel((string) ($row['sensitivity'] ?? ''))) ?></td>
              <td>
                <?= e((string) ($row['entity'] ?? '-')) ?>
                <?php if (isset($row['entity_id']) && (int) $row['entity_id'] > 0): ?>
                  <div class="muted">#<?= e((string) ((int) $row['entity_id'])) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= e((string) ($row['subject_label'] ?? '-')) ?>
                <?php if ($subjectPersonId > 0): ?>
                  <div class="muted">Pessoa #<?= e((string) $subjectPersonId) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($row['context_path'] ?? '-')) ?></td>
              <td><?= e((string) ($row['ip'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <span class="muted"><?= e((string) ($pagination['total'] ?? 0)) ?> registro(s)</span>
      <div class="pagination-links">
        <?php if ((int) ($pagination['page'] ?? 1) > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= e((string) ($pagination['page'] ?? 1)) ?> de <?= e((string) ($pagination['pages'] ?? 1)) ?></span>
        <?php if ((int) ($pagination['page'] ?? 1) < (int) ($pagination['pages'] ?? 1)): ?>
          <a class="btn btn-outline" href="<?= e($buildUrl(['page' => (int) ($pagination['page'] ?? 1) + 1])) ?>">Proxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Politicas de retencao e anonimizacao</h3>
  <?php if ($policies === []): ?>
    <div class="empty-state">
      <p>Sem politicas configuradas.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Politica</th>
            <th>Retencao (dias)</th>
            <th>Anonimizacao (dias)</th>
            <th>Status</th>
            <th>Atualizada em</th>
            <?php if ($canManage): ?>
              <th>Acoes</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($policies as $policy): ?>
            <?php
              $supportsAnonymization = (int) ($policy['supports_anonymization'] ?? 0) === 1;
              $isActive = (int) ($policy['is_active'] ?? 0) === 1;
              $formId = 'policy-form-' . preg_replace('/[^a-z0-9_\\-]/i', '_', (string) ($policy['policy_key'] ?? 'policy'));
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($policy['policy_label'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string) ($policy['description'] ?? '')) ?></div>
                <div class="muted"><code><?= e((string) ($policy['policy_key'] ?? '')) ?></code></div>
              </td>
              <?php if ($canManage): ?>
                <td>
                  <input
                    form="<?= e($formId) ?>"
                    type="number"
                    name="retention_days"
                    min="0"
                    max="3650"
                    value="<?= e((string) ((int) ($policy['retention_days'] ?? 0))) ?>"
                    style="max-width: 120px;"
                  >
                </td>
                <td>
                    <?php if ($supportsAnonymization): ?>
                      <input
                        form="<?= e($formId) ?>"
                        type="number"
                        name="anonymize_after_days"
                        min="1"
                        max="3650"
                        value="<?= e((string) ((int) ($policy['anonymize_after_days'] ?? 0))) ?>"
                        style="max-width: 120px;"
                      >
                    <?php else: ?>
                      <span class="muted">N/A</span>
                      <input form="<?= e($formId) ?>" type="hidden" name="anonymize_after_days" value="">
                    <?php endif; ?>
                </td>
                <td>
                    <select form="<?= e($formId) ?>" name="is_active" style="max-width: 130px;">
                      <option value="1" <?= $isActive ? 'selected' : '' ?>>Ativa</option>
                      <option value="0" <?= !$isActive ? 'selected' : '' ?>>Inativa</option>
                    </select>
                </td>
                <td><?= e($formatDateTime((string) ($policy['updated_at'] ?? ''))) ?></td>
                <td>
                    <form id="<?= e($formId) ?>" method="post" action="<?= e(url('/lgpd/policies/upsert')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="policy_key" value="<?= e((string) ($policy['policy_key'] ?? '')) ?>">
                    </form>
                    <button form="<?= e($formId) ?>" type="submit" class="btn btn-primary">Salvar</button>
                </td>
              <?php else: ?>
                <td><?= e((string) ((int) ($policy['retention_days'] ?? 0))) ?></td>
                <td>
                  <?php if ($supportsAnonymization): ?>
                    <?= e((string) ((int) ($policy['anonymize_after_days'] ?? 0))) ?>
                  <?php else: ?>
                    <span class="muted">N/A</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>"><?= $isActive ? 'Ativa' : 'Inativa' ?></span>
                </td>
                <td><?= e($formatDateTime((string) ($policy['updated_at'] ?? ''))) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($canManage): ?>
    <div class="header-row" style="margin-top: 16px;">
      <div>
        <h4>Executar rotina LGPD</h4>
        <p class="muted">Use preview para validar os volumes antes da aplicacao definitiva.</p>
      </div>
      <div class="actions-inline">
        <form method="post" action="<?= e(url('/lgpd/retention/run')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="preview">
          <button type="submit" class="btn btn-outline">Executar preview</button>
        </form>
        <form method="post" action="<?= e(url('/lgpd/retention/run')) ?>" onsubmit="return confirm('Confirmar aplicacao da rotina LGPD (retencao/anonimizacao)?');">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="apply">
          <button type="submit" class="btn btn-danger">Aplicar rotina</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <h4>Historico recente de execucoes</h4>
  <?php if ($runs === []): ?>
    <div class="empty-state">
      <p>Nenhuma execucao registrada.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data/hora</th>
            <th>Modo</th>
            <th>Status</th>
            <th>Executado por</th>
            <th>Resumo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($runs as $run): ?>
            <?php
              $runSummary = $decodeRunSummary($run['summary'] ?? null);
              $runStats = is_array($runSummary['stats'] ?? null) ? $runSummary['stats'] : [];
            ?>
            <tr>
              <td><?= e($formatDateTime((string) ($run['created_at'] ?? ''))) ?></td>
              <td><?= e((string) ($run['run_mode'] ?? '-')) ?></td>
              <td>
                <?php $ok = (string) ($run['status'] ?? '') === 'ok'; ?>
                <span class="badge <?= $ok ? 'badge-success' : 'badge-danger' ?>"><?= e((string) ($run['status'] ?? '-')) ?></span>
              </td>
              <td><?= e((string) ($run['user_name'] ?? 'Sistema')) ?></td>
              <td class="muted">
                candidatos logs/audit/pessoas/usuarios:
                <?= e((string) (int) ($runStats['sensitive_access_candidates'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['audit_log_candidates'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['people_candidates'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['users_candidates'] ?? 0)) ?>
                <br>
                aplicados logs/audit/pessoas/usuarios:
                <?= e((string) (int) ($runStats['sensitive_access_purged'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['audit_log_purged'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['people_anonymized'] ?? 0)) ?>/
                <?= e((string) (int) ($runStats['users_anonymized'] ?? 0)) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (is_array($latestRun)): ?>
    <p class="muted" style="margin-top: 10px;">Ultima execucao: <?= e($formatDateTime((string) ($latestRun['created_at'] ?? ''))) ?> (<?= e((string) ($latestRun['run_mode'] ?? '-')) ?>).</p>
  <?php endif; ?>
</div>
