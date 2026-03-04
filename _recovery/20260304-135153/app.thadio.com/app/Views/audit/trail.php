<?php
/**
 * @var array $trail Histórico de alterações do registro
 * @var string $tableName Nome da tabela
 * @var int $recordId ID do registro
 */

use App\Support\Html;

$title = "Histórico: {$tableName} #{$recordId}";
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-history"></i> Histórico de Alterações
        </h1>
        <a href="/audit-log-list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="alert alert-info">
        <strong>Tabela:</strong> <code><?= Html::esc($tableName) ?></code> | 
        <strong>ID do Registro:</strong> <?= Html::esc($recordId) ?>
    </div>

    <?php if (empty($trail)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Nenhum histórico encontrado para este registro.
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($trail as $index => $log): ?>
                <div class="card mb-3 <?= $index === 0 ? 'border-primary' : '' ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <?php
                            $actionClass = match($log['action']) {
                                'INSERT' => 'success',
                                'UPDATE' => 'warning',
                                'DELETE' => 'danger',
                                default => 'secondary'
                            };
                            $actionIcon = match($log['action']) {
                                'INSERT' => 'plus-circle',
                                'UPDATE' => 'edit',
                                'DELETE' => 'trash',
                                default => 'question-circle'
                            };
                            ?>
                            <span class="badge bg-<?= $actionClass ?>">
                                <i class="fas fa-<?= $actionIcon ?>"></i> <?= Html::esc($log['action']) ?>
                            </span>
                            <span class="ms-2">
                                <i class="fas fa-user"></i> <?= Html::esc($log['user_name']) ?>
                            </span>
                            <span class="ms-2 text-muted">
                                <i class="fas fa-clock"></i> <?= Html::esc($log['created_at_formatted']) ?>
                            </span>
                        </div>
                        <div>
                            <a href="/audit-log-view.php?id=<?= $log['id'] ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $oldValues = $log['old_values'] ? json_decode($log['old_values'], true) : null;
                        $newValues = $log['new_values'] ? json_decode($log['new_values'], true) : null;
                        
                        // Calcular diff
                        $diff = [];
                        if ($oldValues && $newValues) {
                            foreach ($newValues as $key => $newValue) {
                                $oldValue = $oldValues[$key] ?? null;
                                if ($oldValue !== $newValue) {
                                    $diff[$key] = [
                                        'old' => $oldValue,
                                        'new' => $newValue,
                                    ];
                                }
                            }
                        }
                        ?>

                        <?php if ($log['action'] === 'INSERT'): ?>
                            <p class="mb-0">
                                <i class="fas fa-info-circle"></i> Registro criado com <?= count($newValues ?? []) ?> campo(s).
                            </p>
                        <?php elseif ($log['action'] === 'UPDATE' && !empty($diff)): ?>
                            <p class="mb-2"><strong>Campos alterados (<?= count($diff) ?>):</strong></p>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 200px;">Campo</th>
                                        <th>Valor Anterior</th>
                                        <th>Valor Novo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($diff as $field => $values): ?>
                                        <tr>
                                            <td><strong><?= Html::esc($field) ?></strong></td>
                                            <td class="bg-danger-subtle">
                                                <code><?= Html::esc(is_array($values['old']) ? json_encode($values['old']) : (string)$values['old']) ?></code>
                                            </td>
                                            <td class="bg-success-subtle">
                                                <code><?= Html::esc(is_array($values['new']) ? json_encode($values['new']) : (string)$values['new']) ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($log['action'] === 'DELETE'): ?>
                            <p class="mb-0 text-danger">
                                <i class="fas fa-trash"></i> Registro excluído.
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-info-circle"></i> Nenhuma alteração de campo detectada.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-secondary mt-3">
            <i class="fas fa-info-circle"></i> Total de <?= count($trail) ?> alteração(ões) encontrada(s).
        </div>
    <?php endif; ?>
</div>

<style>
.bg-danger-subtle {
    background-color: #f8d7da !important;
}
.bg-success-subtle {
    background-color: #d1e7dd !important;
}
</style>
