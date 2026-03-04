<?php
/**
 * @var array $log Detalhes do log de auditoria
 */

use App\Support\Html;

$title = 'Detalhes do Log de Auditoria';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-alt"></i> Detalhes do Log #<?= Html::esc($log['id']) ?>
        </h1>
        <a href="/audit-log-list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="row">
        <!-- Informações Gerais -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle"></i> Informações Gerais
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th style="width: 150px;">ID:</th>
                            <td><?= Html::esc($log['id']) ?></td>
                        </tr>
                        <tr>
                            <th>Ação:</th>
                            <td>
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
                        </tr>
                        <tr>
                            <th>Tabela:</th>
                            <td><code><?= Html::esc($log['table_name']) ?></code></td>
                        </tr>
                        <tr>
                            <th>ID do Registro:</th>
                            <td><?= Html::esc($log['record_id']) ?></td>
                        </tr>
                        <tr>
                            <th>Usuário:</th>
                            <td><?= Html::esc($log['user_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Data/Hora:</th>
                            <td><?= Html::esc($log['created_at_formatted']) ?></td>
                        </tr>
                    </table>

                    <a href="/audit-log-trail.php?table_name=<?= urlencode($log['table_name']) ?>&record_id=<?= $log['record_id'] ?>" 
                       class="btn btn-sm btn-secondary w-100">
                        <i class="fas fa-history"></i> Ver Histórico Completo deste Registro
                    </a>
                </div>
            </div>
        </div>

        <!-- Alterações (Diff) -->
        <div class="col-md-6">
            <?php if (!empty($log['diff'])): ?>
                <div class="card mb-3">
                    <div class="card-header bg-warning">
                        <i class="fas fa-exchange-alt"></i> Campos Alterados (<?= count($log['diff']) ?>)
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Campo</th>
                                    <th>Valor Anterior</th>
                                    <th>Valor Novo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($log['diff'] as $field => $values): ?>
                                    <tr>
                                        <td><strong><?= Html::esc($field) ?></strong></td>
                                        <td>
                                            <code class="text-danger">
                                                <?= Html::esc(is_array($values['old']) ? json_encode($values['old']) : (string)$values['old']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <code class="text-success">
                                                <?= Html::esc(is_array($values['new']) ? json_encode($values['new']) : (string)$values['new']) ?>
                                            </code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dados Completos: Valores Antigos -->
    <?php if ($log['old_values_decoded']): ?>
        <div class="card mb-3">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-database"></i> Valores Anteriores (Estado Antigo)
            </div>
            <div class="card-body">
                <pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"><?= Html::esc(json_encode($log['old_values_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <!-- Dados Completos: Valores Novos -->
    <?php if ($log['new_values_decoded']): ?>
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <i class="fas fa-database"></i> Valores Novos (Estado Novo)
            </div>
            <div class="card-body">
                <pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"><?= Html::esc(json_encode($log['new_values_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>
