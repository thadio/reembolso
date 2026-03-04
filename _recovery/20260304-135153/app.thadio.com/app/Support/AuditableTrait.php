<?php

namespace App\Support;

use App\Support\AuditService;

/**
 * Trait para simplificar integração de auditoria em repositórios
 * 
 * Usage:
 * 
 * class MyRepository {
 *     use AuditableTrait;
 * 
 *     public function save(array $data): int {
 *         $id = $data['id'] ?? null;
 *         $oldValues = $id ? $this->find($id) : null;
 *         
 *         // ... executar INSERT/UPDATE ...
 *         
 *         $this->auditLog($id ? 'UPDATE' : 'INSERT', 'table_name', $id, $oldValues, $this->find($id));
 *         return $id;
 *     }
 * }
 */
trait AuditableTrait
{
    /**
     * Registra log de auditoria (com graceful degradation)
     * 
     * @param string $action INSERT, UPDATE ou DELETE
     * @param string $tableName Nome da tabela
     * @param int $recordId ID do registro
     * @param array|null $oldValues Estado anterior (null para INSERT)
     * @param array|null $newValues Estado novo (null para DELETE)
     * @return void
     */
    protected function auditLog(
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            // Obter PDO connection
            if (property_exists($this, 'pdo') && $this->pdo) {
                AuditService::setPDO($this->pdo);
            } elseif (method_exists($this, 'getPdo')) {
                $pdo = $this->getPdo();
                if ($pdo) {
                    AuditService::setPDO($pdo);
                }
            }

            AuditService::log($action, $tableName, $recordId, $oldValues, $newValues);
        } catch (\Throwable $e) {
            // Graceful degradation: log error but don't break operation
            error_log(sprintf(
                'Falha ao registrar auditoria (%s::%s): %s',
                get_class($this),
                $action,
                $e->getMessage()
            ));
        }
    }
}
