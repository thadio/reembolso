<?php

namespace App\Repositories;

use PDO;

/**
 * DashRefreshLogRepository
 * 
 * Gerencia logs de execução dos refreshes de materializações.
 */
class DashRefreshLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if (\function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    /**
     * Cria tabela se não existir
     */
    public function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS dash_refresh_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                refresh_type ENUM('sales_daily','stock_snapshot','all') NOT NULL,
                refresh_date DATE NULL COMMENT 'Data específica do refresh (para sales_daily)',
                status ENUM('success','error') NOT NULL,
                duration_ms INT DEFAULT 0 COMMENT 'Duração em milissegundos',
                rows_affected INT DEFAULT 0 COMMENT 'Número de registros afetados',
                error_message TEXT NULL,
                metadata JSON NULL COMMENT 'Dados adicionais: days, dry_run, etc.',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_refresh_type (refresh_type),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_refresh_date (refresh_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $this->pdo->exec($sql);
    }

    /**
     * Registra execução de um refresh
     * 
     * @param string $refreshType Tipo: 'sales_daily', 'stock_snapshot', 'all'
     * @param string $status Status: 'success', 'error'
     * @param array $data Dados: duration_ms, rows_affected, error_message, metadata, refresh_date
     * @return int ID do log inserido
     */
    public function log(string $refreshType, string $status, array $data): int
    {
        $sql = "
            INSERT INTO dash_refresh_log (
                refresh_type,
                refresh_date,
                status,
                duration_ms,
                rows_affected,
                error_message,
                metadata
            ) VALUES (
                :refresh_type,
                :refresh_date,
                :status,
                :duration_ms,
                :rows_affected,
                :error_message,
                :metadata
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        
        $metadata = isset($data['metadata']) && is_array($data['metadata']) 
            ? json_encode($data['metadata']) 
            : null;

        $stmt->execute([
            ':refresh_type' => $refreshType,
            ':refresh_date' => $data['refresh_date'] ?? null,
            ':status' => $status,
            ':duration_ms' => (int)($data['duration_ms'] ?? 0),
            ':rows_affected' => (int)($data['rows_affected'] ?? 0),
            ':error_message' => $data['error_message'] ?? null,
            ':metadata' => $metadata,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Busca última execução de um tipo de refresh
     * 
     * @param string $refreshType Tipo do refresh
     * @return array|null
     */
    public function getLastRun(string $refreshType): ?array
    {
        $sql = "
            SELECT 
                id,
                refresh_type,
                refresh_date,
                status,
                duration_ms,
                rows_affected,
                error_message,
                metadata,
                created_at
            FROM dash_refresh_log
            WHERE refresh_type = :refresh_type
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':refresh_type' => $refreshType]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['metadata']) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }
        
        return $result ?: null;
    }

    /**
     * Busca histórico de execuções
     * 
     * @param int $limit Número de registros
     * @param string|null $refreshType Filtrar por tipo (opcional)
     * @param string|null $status Filtrar por status (opcional)
     * @return array
     */
    public function getHistory(int $limit = 50, ?string $refreshType = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($refreshType) {
            $where[] = "refresh_type = :refresh_type";
            $params[':refresh_type'] = $refreshType;
        }

        if ($status) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT 
                id,
                refresh_type,
                refresh_date,
                status,
                duration_ms,
                rows_affected,
                error_message,
                metadata,
                created_at
            FROM dash_refresh_log
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON metadata
        foreach ($results as &$result) {
            if ($result['metadata']) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
        }

        return $results;
    }

    /**
     * Estatísticas de execução
     * 
     * @param int $days Últimos N dias
     * @return array
     */
    public function getStats(int $days = 30): array
    {
        $sql = "
            SELECT 
                refresh_type,
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_runs,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_runs,
                AVG(duration_ms) as avg_duration_ms,
                MIN(duration_ms) as min_duration_ms,
                MAX(duration_ms) as max_duration_ms,
                SUM(rows_affected) as total_rows_affected
            FROM dash_refresh_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY refresh_type
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove logs antigos
     * 
     * @param int $daysToKeep Manter apenas N dias recentes
     * @return int Número de registros deletados
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        $sql = "
            DELETE FROM dash_refresh_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }

    /**
     * Verifica se houve erro recente
     * 
     * @param string $refreshType Tipo do refresh
     * @param int $minutes Últimos N minutos
     * @return bool
     */
    public function hasRecentError(string $refreshType, int $minutes = 60): bool
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM dash_refresh_log
            WHERE refresh_type = :refresh_type
              AND status = 'error'
              AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':refresh_type' => $refreshType,
            ':minutes' => $minutes,
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0) > 0;
    }

    /**
     * Taxa de sucesso por tipo
     * 
     * @param int $days Últimos N dias
     * @return array
     */
    public function getSuccessRate(int $days = 7): array
    {
        $sql = "
            SELECT 
                refresh_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes,
                ROUND(
                    (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                    2
                ) as success_rate_percent
            FROM dash_refresh_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY refresh_type
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
