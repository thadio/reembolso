<?php

namespace App\Controllers;

use App\Support\AuditService;
use App\Core\View;
use PDO;
use RuntimeException;

class AuditController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        if (!$pdo) {
            throw new RuntimeException('Conexão com banco indisponível para auditoria.');
        }

        $this->pdo = $pdo;
        AuditService::setPDO($pdo);
    }

    /**
     * Lista audit logs com filtros
     */
    public function list(): void
    {
        $searchQuery = trim((string) ($_GET['q'] ?? ($_GET['search'] ?? '')));
        $sortKey = trim((string) ($_GET['sort_key'] ?? ($_GET['sort'] ?? 'created_at')));
        $sortDirRaw = strtolower(trim((string) ($_GET['sort_dir'] ?? ($_GET['dir'] ?? 'desc'))));
        $sortDir = $sortDirRaw === 'asc' ? 'ASC' : 'DESC';

        // Filtros da query string
        $filters = [
            'table_name' => trim((string) ($_GET['table_name'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'user_id' => isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null,
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'record_id' => isset($_GET['record_id']) && $_GET['record_id'] !== '' ? (int) $_GET['record_id'] : null,
            'q' => $searchQuery,
            'sort_key' => $sortKey,
            'sort_dir' => $sortDir,
        ];

        // Paginação
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $limit = max(10, min(200, $limit));
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $limit;

        // Buscar logs
        $logs = AuditService::search($filters, $limit + 1, $offset); // +1 para verificar se há próxima página

        $hasMore = count($logs) > $limit;
        if ($hasMore) {
            array_pop($logs); // Remove o extra
        }

        // Enriquecer logs com informações do usuário
        $userCache = [];
        foreach ($logs as &$log) {
            $userId = isset($log['user_id']) ? (int) $log['user_id'] : 0;
            if ($userId > 0 && !isset($userCache[$userId])) {
                $stmt = $this->pdo->prepare("SELECT full_name, email FROM pessoas WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                $userCache[$userId] = $user ? $user['full_name'] . ' (' . $user['email'] . ')' : 'Usuário #' . $userId;
            }
            $log['user_name'] = $userId > 0 ? ($userCache[$userId] ?? 'Desconhecido') : 'Sistema';

            // Formatar datas
            $createdAt = trim((string) ($log['created_at'] ?? ''));
            $log['created_at_formatted'] = $createdAt !== '' ? date('d/m/Y H:i:s', strtotime($createdAt)) : '-';

            // Decodificar JSON
            $oldValuesRaw = $log['old_values'] ?? null;
            $newValuesRaw = $log['new_values'] ?? null;
            $log['old_values_decoded'] = is_string($oldValuesRaw) && $oldValuesRaw !== '' ? json_decode($oldValuesRaw, true) : null;
            $log['new_values_decoded'] = is_string($newValuesRaw) && $newValuesRaw !== '' ? json_decode($newValuesRaw, true) : null;
        }

        // Tabelas disponíveis para filtro
        $tables = ['', 'pessoas', 'orders', 'order_items', 'order_returns', 'products', 'inventory_movements', 'consignments'];
        $actions = ['', 'INSERT', 'UPDATE', 'DELETE'];

        View::render('audit/list', [
            'logs' => $logs,
            'filters' => $filters,
            'tables' => $tables,
            'actions' => $actions,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Exibe detalhes de um log específico
     */
    public function view(): void
    {
        $id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$id) {
            header('Location: /audit-log-list.php');
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM audit_log WHERE id = ?");
        $stmt->execute([$id]);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$log) {
            header('Location: /audit-log-list.php?error=not_found');
            exit;
        }

        // Enriquecer com informações do usuário
        $userId = isset($log['user_id']) ? (int) $log['user_id'] : 0;
        if ($userId > 0) {
            $stmt = $this->pdo->prepare("SELECT full_name, email FROM pessoas WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            $log['user_name'] = $user ? $user['full_name'] . ' (' . $user['email'] . ')' : 'Usuário #' . $userId;
        } else {
            $log['user_name'] = 'Sistema';
        }

        // Formatar datas
        $createdAt = trim((string) ($log['created_at'] ?? ''));
        $log['created_at_formatted'] = $createdAt !== '' ? date('d/m/Y H:i:s', strtotime($createdAt)) : '-';

        // Decodificar JSON
        $oldValuesRaw = $log['old_values'] ?? null;
        $newValuesRaw = $log['new_values'] ?? null;
        $log['old_values_decoded'] = is_string($oldValuesRaw) && $oldValuesRaw !== '' ? json_decode($oldValuesRaw, true) : null;
        $log['new_values_decoded'] = is_string($newValuesRaw) && $newValuesRaw !== '' ? json_decode($newValuesRaw, true) : null;

        // Calcular diff (campos alterados)
        $diff = [];
        if ($log['old_values_decoded'] && $log['new_values_decoded']) {
            foreach ($log['new_values_decoded'] as $key => $newValue) {
                $oldValue = $log['old_values_decoded'][$key] ?? null;
                if ($oldValue !== $newValue) {
                    $diff[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }
        $log['diff'] = $diff;

        View::render('audit/view', [
            'log' => $log,
        ]);
    }

    /**
     * Busca histórico de um registro específico
     */
    public function trail(): void
    {
        $tableName = $_GET['table_name'] ?? '';
        $recordId = isset($_GET['record_id']) && is_numeric($_GET['record_id']) ? (int)$_GET['record_id'] : 0;

        if (!$tableName || !$recordId) {
            header('Location: /audit-log-list.php?error=invalid_params');
            exit;
        }

        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $trail = AuditService::getTrail($tableName, $recordId, $limit);

        // Enriquecer com informações do usuário
        $userCache = [];
        foreach ($trail as &$log) {
            $userId = isset($log['user_id']) ? (int) $log['user_id'] : 0;
            if ($userId > 0 && !isset($userCache[$userId])) {
                $stmt = $this->pdo->prepare("SELECT full_name, email FROM pessoas WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                $userCache[$userId] = $user ? $user['full_name'] . ' (' . $user['email'] . ')' : 'Usuário #' . $userId;
            }
            $log['user_name'] = $userId > 0 ? ($userCache[$userId] ?? 'Desconhecido') : 'Sistema';
            $createdAt = trim((string) ($log['created_at'] ?? ''));
            $log['created_at_formatted'] = $createdAt !== '' ? date('d/m/Y H:i:s', strtotime($createdAt)) : '-';
        }

        View::render('audit/trail', [
            'trail' => $trail,
            'tableName' => $tableName,
            'recordId' => $recordId,
        ]);
    }
}
