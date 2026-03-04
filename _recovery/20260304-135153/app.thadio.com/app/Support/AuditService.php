<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

/**
 * Serviço de Auditoria Assíncrona
 * 
 * Performance: <1ms overhead (buffer em memória + flush no shutdown)
 * 
 * Uso:
 * ```php
 * AuditService::log('INSERT', 'pessoas', 123, null, ['nome' => 'João']);
 * AuditService::log('UPDATE', 'orders', 456, ['status' => 'pending'], ['status' => 'paid']);
 * AuditService::log('DELETE', 'products', 789, ['sku' => 'ABC123'], null);
 * ```
 * 
 * Features:
 * - Zero bloqueio (logs vão para buffer em memória)
 * - Flush automático no fim do request (register_shutdown_function)
 * - Batch insert (1 query para N logs)
 * - Degrada graciosamente (se falhar, não quebra sistema)
 */
class AuditService
{
    /** @var PDO|null PDO connection injetado */
    private static ?PDO $pdo = null;
    
    /** @var array Buffer de logs em memória */
    private static array $buffer = [];
    
    /** @var bool Flag para evitar flush duplo */
    private static bool $shutdownRegistered = false;
    
    /** @var bool Flag para ativar/desativar auditoria */
    private static bool $enabled = true;
    
    /** @var int Limite de logs no buffer antes de flush forçado */
    private static int $bufferLimit = 100;
    
    /**
     * Log uma mudança (não-bloqueante, vai para buffer)
     *
     * @param string $action INSERT, UPDATE, DELETE
     * @param string $tableName Nome da tabela
     * @param int|null $recordId ID do registro afetado
     * @param array|null $oldValues Valores antigos (UPDATE apenas)
     * @param array|null $newValues Valores novos (INSERT/UPDATE)
     * @return void
     */
    public static function log(
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        // Se desabilitado, retorna imediatamente
        if (!self::$enabled) {
            return;
        }
        
        try {
            // Capturar contexto do usuário/request
            $userId = $_SESSION['user_id'] ?? null;
            $userEmail = $_SESSION['user_email'] ?? null;
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
            $requestUri = $_SERVER['REQUEST_URI'] ?? null;
            
            // Sanitizar valores para JSON (remover recursos/objetos complexos)
            $oldValuesJson = $oldValues ? self::sanitizeForJson($oldValues) : null;
            $newValuesJson = $newValues ? self::sanitizeForJson($newValues) : null;
            
            // Adicionar ao buffer
            self::$buffer[] = [
                'action' => strtoupper($action),
                'table_name' => $tableName,
                'record_id' => $recordId,
                'user_id' => $userId,
                'user_email' => $userEmail,
                'remote_addr' => $remoteAddr,
                'request_uri' => $requestUri,
                'old_values' => $oldValuesJson ? json_encode($oldValuesJson, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValuesJson ? json_encode($newValuesJson, JSON_UNESCAPED_UNICODE) : null,
            ];
            
            // Registrar shutdown handler na primeira chamada
            if (!self::$shutdownRegistered) {
                register_shutdown_function([self::class, 'flush']);
                self::$shutdownRegistered = true;
            }
            
            // Flush forçado se buffer estiver muito grande
            if (count(self::$buffer) >= self::$bufferLimit) {
                self::flush();
            }
            
        } catch (\Throwable $e) {
            // Silenciosamente falha (não quebrar aplicação)
            error_log('AuditService::log() failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Flush do buffer para o banco (chamado automaticamente no shutdown)
     *
     * @return void
     */
    public static function flush(): void
    {
        // Se buffer vazio, nada a fazer
        if (empty(self::$buffer)) {
            return;
        }
        
        try {
            $pdo = self::getPDO();

            if (!self::auditTableExists($pdo)) {
                self::$buffer = [];
                return;
            }
            
            // Preparar batch insert
            $sql = "INSERT INTO audit_log (
                action, table_name, record_id, user_id, user_email,
                remote_addr, request_uri, old_values, new_values
            ) VALUES ";
            
            $values = [];
            $params = [];
            $paramIndex = 0;
            
            foreach (self::$buffer as $log) {
                $placeholders = [];
                foreach ($log as $key => $value) {
                    $paramName = ":p{$paramIndex}";
                    $placeholders[] = $paramName;
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
                $values[] = '(' . implode(', ', $placeholders) . ')';
            }
            
            $sql .= implode(', ', $values);
            
            // Executar batch insert
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Limpar buffer após sucesso
            $count = count(self::$buffer);
            self::$buffer = [];
            
            // Log de debug (opcional)
            if (getenv('AUDIT_DEBUG') === 'true') {
                error_log("AuditService::flush() - {$count} logs inseridos");
            }
            
        } catch (PDOException $e) {
            // Silenciosamente falha (não quebrar aplicação)
            error_log('AuditService::flush() failed: ' . $e->getMessage());
            
            // Limpar buffer mesmo em caso de erro (evitar memory leak)
            self::$buffer = [];
        }
    }
    
    /**
     * Obter trail de auditoria para um registro específico
     *
     * @param string $tableName Nome da tabela
     * @param int $recordId ID do registro
     * @param int $limit Limite de resultados
     * @return array Lista de logs ordenados por data DESC
     */
    public static function getTrail(string $tableName, int $recordId, int $limit = 50): array
    {
        try {
            $pdo = self::getPDO();
            
            $sql = "SELECT 
                        id, action, table_name, record_id,
                        user_id, user_email, created_at,
                        remote_addr, request_uri,
                        old_values, new_values
                    FROM audit_log
                    WHERE table_name = :table_name
                      AND record_id = :record_id
                    ORDER BY created_at DESC
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
            $stmt->bindValue(':record_id', $recordId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar JSON
            foreach ($logs as &$log) {
                if ($log['old_values']) {
                    $log['old_values'] = json_decode($log['old_values'], true);
                }
                if ($log['new_values']) {
                    $log['new_values'] = json_decode($log['new_values'], true);
                }
            }
            
            return $logs;
            
        } catch (PDOException $e) {
            error_log('AuditService::getTrail() failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar logs com filtros
     *
     * @param array $filters Filtros: table_name, user_id, action, date_from, date_to, record_id, q, sort_key, sort_dir
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de logs
     */
    public static function search(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            $pdo = self::getPDO();
            $limit = max(1, min(500, $limit));
            $offset = max(0, $offset);
            
            $where = [];
            $params = [];
            
            if (!empty($filters['table_name'])) {
                $where[] = "table_name = :table_name";
                $params[':table_name'] = $filters['table_name'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = :user_id";
                $params[':user_id'] = (int) $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = "action = :action";
                $params[':action'] = strtoupper($filters['action']);
            }

            if (isset($filters['record_id']) && $filters['record_id'] !== '' && $filters['record_id'] !== null) {
                $where[] = "record_id = :record_id";
                $params[':record_id'] = (int) $filters['record_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $search = trim((string) ($filters['q'] ?? ($filters['search'] ?? '')));
            if ($search !== '') {
                $where[] = "(CAST(id AS CHAR) LIKE :q
                    OR CAST(record_id AS CHAR) LIKE :q
                    OR table_name LIKE :q
                    OR action LIKE :q
                    OR COALESCE(user_email, '') LIKE :q
                    OR COALESCE(request_uri, '') LIKE :q)";
                $params[':q'] = '%' . $search . '%';
            }

            [$sortColumn, $sortDirection] = self::normalizeSearchSort(
                (string) ($filters['sort_key'] ?? ($filters['sort'] ?? 'created_at')),
                (string) ($filters['sort_dir'] ?? ($filters['dir'] ?? 'DESC'))
            );
            
            $sql = "SELECT 
                        id, action, table_name, record_id,
                        user_id, user_email, created_at,
                        remote_addr, request_uri
                    FROM audit_log";
            
            if ($where) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " ORDER BY {$sortColumn} {$sortDirection}, id DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('AuditService::search() failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function normalizeSearchSort(string $sortKey, string $sortDir): array
    {
        $sortKey = strtolower(trim($sortKey));
        $sortDir = strtoupper(trim($sortDir)) === 'ASC' ? 'ASC' : 'DESC';

        $column = match ($sortKey) {
            'id' => 'id',
            'action' => 'action',
            'table_name', 'table' => 'table_name',
            'record_id' => 'record_id',
            'user_name', 'user_email', 'user' => 'user_email',
            'created_at', 'date' => 'created_at',
            default => 'created_at',
        };

        return [$column, $sortDir];
    }
    
    /**
     * Ativar/desativar auditoria
     *
     * @param bool $enabled
     * @return void
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
    
    /**
     * Verificar se auditoria está ativa
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
    
    /**
     * Definir PDO connection (útil para injeção de dependência)
     *
     * @param PDO $pdo
     * @return void
     */
    public static function setPDO(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
    
    /**
     * Obter PDO connection
     *
     * @return PDO
     */
    private static function getPDO(): PDO
    {
        if (self::$pdo === null) {
            // Tentar obter via bootstrap
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                self::$pdo = $GLOBALS['pdo'];
            } else {
                // Fallback: criar nova conexão
                require_once __DIR__ . '/../Core/Database.php';
                [$pdo, ] = \App\Core\Database::bootstrap();
                self::$pdo = $pdo;
            }
        }
        
        return self::$pdo;
    }
    
    /**
     * Sanitizar valores para JSON (remover recursos/objetos complexos)
     *
     * @param array $data
     * @return array
     */
    private static function sanitizeForJson(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            // Ignorar resources e objetos complexos
            if (is_resource($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                $sanitized[$key] = '[' . gettype($value) . ']';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeForJson($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Limpar buffer (útil para testes)
     *
     * @return void
     */
    public static function clearBuffer(): void
    {
        self::$buffer = [];
    }
    
    /**
     * Obter tamanho do buffer (útil para testes)
     *
     * @return int
     */
    public static function getBufferSize(): int
    {
        return count(self::$buffer);
    }

    private static function auditTableExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query(
                "SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'audit_log'
                 LIMIT 1"
            );
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable $e) {
            return false;
        }
    }
}
