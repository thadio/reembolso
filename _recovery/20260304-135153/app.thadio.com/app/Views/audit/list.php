<?php
/**
 * @var array $logs Lista de audit logs
 * @var array $filters Filtros aplicados
 * @var array $tables Tabelas disponíveis
 * @var array $actions Ações disponíveis
 * @var int $page Página atual
 * @var int $limit Registros por página
 * @var bool $hasMore Tem mais páginas
 * @var string $sortKey Chave de ordenação
 * @var string $sortDir Direção da ordenação
 */

use App\Support\Html;

$title = 'Auditoria - Log de Alterações';
$sortKey = isset($sortKey) ? (string) $sortKey : 'created_at';
$sortDir = strtoupper((string) ($sortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-history"></i> Auditoria - Log de Alterações
        </h1>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="/audit-log-list.php" class="row g-3">
                <input type="hidden" name="sort_key" value="<?= Html::esc($sortKey) ?>">
                <input type="hidden" name="sort" value="<?= Html::esc($sortKey) ?>">
                <input type="hidden" name="sort_dir" value="<?= Html::esc(strtolower($sortDir)) ?>">
                <input type="hidden" name="dir" value="<?= Html::esc(strtolower($sortDir)) ?>">

                <div class="col-md-3">
                    <label for="table_name" class="form-label">Tabela</label>
                    <select name="table_name" id="table_name" class="form-select">
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= Html::esc($table) ?>" <?= $filters['table_name'] === $table ? 'selected' : '' ?>>
                                <?= $table ?: '-- Todas --' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="action" class="form-label">Ação</label>
                    <select name="action" id="action" class="form-select">
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= Html::esc($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                                <?= $action ?: '-- Todas --' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="record_id" class="form-label">ID Registro</label>
                    <input type="number" name="record_id" id="record_id" class="form-control" 
                           value="<?= Html::esc($filters['record_id'] ?? '') ?>" placeholder="Ex: 123">
                </div>

                <div class="col-md-3">
                    <label for="q" class="form-label">Busca global</label>
                    <input type="search" name="q" id="q" class="form-control"
                           value="<?= Html::esc((string) ($filters['q'] ?? '')) ?>"
                           placeholder="ID, tabela, ação, usuário, rota"
                           data-filter-global>
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="form-label">Data Início</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?= Html::esc($filters['date_from']) ?>">
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">Data Fim</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?= Html::esc($filters['date_to']) ?>">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Logs -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Nenhum log encontrado com os filtros aplicados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" data-table="interactive" data-filter-mode="server">
                        <thead>
                            <tr>
                                <th data-sort-key="id" aria-sort="<?= $sortKey === 'id' ? strtolower($sortDir) : 'none' ?>">ID</th>
                                <th data-sort-key="action" aria-sort="<?= $sortKey === 'action' ? strtolower($sortDir) : 'none' ?>">Ação</th>
                                <th data-sort-key="table_name" aria-sort="<?= $sortKey === 'table_name' ? strtolower($sortDir) : 'none' ?>">Tabela</th>
                                <th data-sort-key="record_id" aria-sort="<?= $sortKey === 'record_id' ? strtolower($sortDir) : 'none' ?>">ID Registro</th>
                                <th data-sort-key="user_name" aria-sort="<?= in_array($sortKey, ['user_name', 'user_email', 'user'], true) ? strtolower($sortDir) : 'none' ?>">Usuário</th>
                                <th data-sort-key="created_at" aria-sort="<?= $sortKey === 'created_at' ? strtolower($sortDir) : 'none' ?>">Data/Hora</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td data-value="<?= $log['id'] ?>"><?= Html::esc($log['id']) ?></td>
                                    <td data-value="<?= Html::esc($log['action']) ?>">
                                        <?php
                                        $actionClass = match($log['action']) {
                                            'INSERT' => 'success',
                                            'UPDATE' => 'warning',
                                            'DELETE' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $actionClass ?>"><?= Html::esc($log['action']) ?></span>
                                    </td>
                                    <td data-value="<?= Html::esc($log['table_name']) ?>">
                                        <code><?= Html::esc($log['table_name']) ?></code>
                                    </td>
                                    <td data-value="<?= $log['record_id'] ?>">
                                        <?= Html::esc($log['record_id']) ?>
                                    </td>
                                    <td data-value="<?= Html::esc($log['user_name']) ?>">
                                        <?= Html::esc($log['user_name']) ?>
                                    </td>
                                    <td data-value="<?= strtotime($log['created_at']) ?>">
                                        <?= Html::esc($log['created_at_formatted']) ?>
                                    </td>
                                    <td>
                                        <a href="/audit-log-view.php?id=<?= $log['id'] ?>" 
                                           class="btn btn-sm btn-info" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/audit-log-trail.php?table_name=<?= urlencode($log['table_name']) ?>&record_id=<?= $log['record_id'] ?>" 
                                           class="btn btn-sm btn-secondary" title="Ver histórico completo">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Exibindo <?= count($logs) ?> registro(s)
                    </div>
                    <div>
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1, 'limit' => $limit])) ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <span class="mx-2">Página <?= $page ?></span>
                        
                        <?php if ($hasMore): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1, 'limit' => $limit])) ?>" 
                               class="btn btn-sm btn-outline-primary">
                                Próxima <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
