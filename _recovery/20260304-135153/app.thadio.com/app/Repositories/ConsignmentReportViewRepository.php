<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;
use PDOException;

/**
 * ConsignmentReportViewRepository
 *
 * Persiste modelos de visualização (views) para o relatório dinâmico
 * de consignação. Cada view define quais campos/colunas ficam visíveis,
 * a ordem, agrupamento, nível de detalhamento e filtros salvos.
 */
class ConsignmentReportViewRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ?bool $tableAvailable = null;
    private const TABLE = 'consignment_report_views';

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            $this->tableAvailable = true;
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    // ─── QUERIES ────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $this->ensureReadableTable();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * List all views, optionally filtered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(?int $createdBy = null, bool $includeSystem = true): array
    {
        if (!$this->pdo) {
            return [];
        }
        $this->ensureReadableTable();

        $where = [];
        $params = [];

        if ($createdBy !== null) {
            if ($includeSystem) {
                $where[] = "(created_by = :uid OR is_system = 1)";
            } else {
                $where[] = "created_by = :uid";
            }
            $params[':uid'] = $createdBy;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM " . self::TABLE . " {$whereClause} ORDER BY is_system DESC, name ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Find the default view for a specific supplier, or global default.
     */
    public function findDefault(?int $supplierPessoaId = null): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $this->ensureReadableTable();

        try {
            // Try supplier-specific default first
            if ($supplierPessoaId !== null && $supplierPessoaId > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM " . self::TABLE . " WHERE default_for_supplier_id = :sid LIMIT 1"
                );
                $stmt->execute([':sid' => $supplierPessoaId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $this->hydrateRow($row);
                }
            }

            // Global default
            $stmt = $this->pdo->prepare(
                "SELECT * FROM " . self::TABLE . " WHERE is_default = 1 AND default_for_supplier_id IS NULL LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->hydrateRow($row) : null;
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    public function create(array $data): int
    {
        $this->ensureWritableTable();

        $sql = "INSERT INTO " . self::TABLE . " (
                  name, description, fields_config, detail_level, sort_config,
                  group_by, filters_config, is_default, default_for_supplier_id,
                  is_system, created_by
                ) VALUES (
                  :name, :description, :fields_config, :detail_level, :sort_config,
                  :group_by, :filters_config, :is_default, :default_for_supplier_id,
                  :is_system, :created_by
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':name'                     => $data['name'],
                ':description'              => $data['description'] ?? null,
                ':fields_config'            => is_string($data['fields_config'] ?? null) ? $data['fields_config'] : json_encode($data['fields_config'] ?? [], JSON_UNESCAPED_UNICODE),
                ':detail_level'             => $data['detail_level'] ?? 'both',
                ':sort_config'              => is_string($data['sort_config'] ?? null) ? $data['sort_config'] : json_encode($data['sort_config'] ?? [], JSON_UNESCAPED_UNICODE),
                ':group_by'                 => $data['group_by'] ?? null,
                ':filters_config'           => is_string($data['filters_config'] ?? null) ? $data['filters_config'] : json_encode($data['filters_config'] ?? [], JSON_UNESCAPED_UNICODE),
                ':is_default'               => (int) ($data['is_default'] ?? 0),
                ':default_for_supplier_id'  => $data['default_for_supplier_id'] ?? null,
                ':is_system'                => (int) ($data['is_system'] ?? 0),
                ':created_by'               => $data['created_by'] ?? null,
            ]);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLog('INSERT', self::TABLE, $id, null, $data);
        return $id;
    }

    public function update(int $id, array $data): void
    {
        if ($id <= 0) {
            return;
        }
        $this->ensureWritableTable();

        $old = $this->find($id);

        $updatable = [
            'name', 'description', 'fields_config', 'detail_level', 'sort_config',
            'group_by', 'filters_config', 'is_default', 'default_for_supplier_id',
            'is_system',
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($updatable as $col) {
            if (array_key_exists($col, $data)) {
                $value = $data[$col];
                if (in_array($col, ['fields_config', 'sort_config', 'filters_config']) && !is_string($value)) {
                    $value = json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
                }
                if (in_array($col, ['is_default', 'is_system'])) {
                    $value = (int) $value;
                }
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $value;
            }
        }

        if (empty($fields)) {
            return;
        }

        try {
            $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }

        $this->auditLog('UPDATE', self::TABLE, $id, $old, $data);
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $this->ensureWritableTable();

        $old = $this->find($id);
        try {
            $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id AND is_system = 0");
            $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }

        if ($old) {
            $this->auditLog('DELETE', self::TABLE, $id, $old, null);
        }
    }

    /**
     * Clone an existing view with a new name.
     */
    public function cloneView(int $sourceId, string $newName, ?int $createdBy = null): int
    {
        $source = $this->find($sourceId);
        if (!$source) {
            throw new \RuntimeException("Modelo de relatório #{$sourceId} não encontrado.");
        }

        return $this->create([
            'name'                     => $newName,
            'description'              => ($source['description'] ?? '') . ' (cópia)',
            'fields_config'            => $source['fields_config'] ?? [],
            'detail_level'             => $source['detail_level'] ?? 'both',
            'sort_config'              => $source['sort_config'] ?? [],
            'group_by'                 => $source['group_by'] ?? null,
            'filters_config'           => $source['filters_config'] ?? [],
            'is_default'               => 0,
            'default_for_supplier_id'  => null,
            'is_system'                => 0,
            'created_by'               => $createdBy,
        ]);
    }

    /**
     * Clear all other defaults when setting a new default.
     */
    public function clearDefaults(?int $supplierPessoaId = null): void
    {
        $this->ensureWritableTable();

        try {
            if ($supplierPessoaId !== null && $supplierPessoaId > 0) {
                $stmt = $this->pdo->prepare(
                    "UPDATE " . self::TABLE . " SET is_default = 0, default_for_supplier_id = NULL WHERE default_for_supplier_id = :sid"
                );
                $stmt->execute([':sid' => $supplierPessoaId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE " . self::TABLE . " SET is_default = 0 WHERE is_default = 1 AND default_for_supplier_id IS NULL"
                );
                $stmt->execute();
            }
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                $this->tableAvailable = false;
                throw new \RuntimeException($this->schemaUnavailableMessage(), 0, $e);
            }
            throw $e;
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────

    private function hasTable(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '" . self::TABLE . "'");
            if ($stmt && $stmt->fetch(PDO::FETCH_NUM)) {
                $this->tableAvailable = true;
                return true;
            }
        } catch (PDOException $e) {
            $this->tableAvailable = false;
            return false;
        }
        $this->tableAvailable = false;
        return false;
    }

    private function ensureReadableTable(): void
    {
        if (!$this->hasTable()) {
            throw new \RuntimeException($this->schemaUnavailableMessage());
        }
    }

    private function ensureWritableTable(): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        if ($this->hasTable()) {
            return;
        }
        throw new \RuntimeException($this->schemaUnavailableMessage());
    }

    private function isMissingTableException(PDOException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '42S02') {
            return true;
        }
        return stripos($e->getMessage(), 'Base table or view not found') !== false;
    }

    private function schemaUnavailableMessage(): string
    {
        return 'Tabela de modelos de relatório indisponível. Execute: php scripts/bootstrap-db.php';
    }

    private function hydrateRow(array $row): array
    {
        foreach (['fields_config', 'sort_config', 'filters_config'] as $jsonCol) {
            if (isset($row[$jsonCol]) && is_string($row[$jsonCol])) {
                $decoded = json_decode($row[$jsonCol], true);
                $row[$jsonCol] = is_array($decoded) ? $decoded : [];
            }
        }
        $row['is_default'] = (bool) ($row['is_default'] ?? false);
        $row['is_system'] = (bool) ($row['is_system'] ?? false);
        return $row;
    }

    // ─── SCHEMA ─────────────────────────────────────────────────

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(120) NOT NULL,
          description TEXT NULL,
          fields_config JSON NOT NULL COMMENT 'Campos visíveis, ordem e categorias',
          detail_level ENUM('summary','items','both') NOT NULL DEFAULT 'both',
          sort_config JSON NULL COMMENT 'Configuração de ordenação',
          group_by VARCHAR(60) NULL COMMENT 'Campo de agrupamento (ex: consignment_status)',
          filters_config JSON NULL COMMENT 'Filtros pré-configurados',
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          default_for_supplier_id BIGINT UNSIGNED NULL COMMENT 'Modelo padrão para fornecedor específico',
          is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Modelo do sistema (não pode ser deletado)',
          created_by BIGINT UNSIGNED NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_report_views_default (is_default),
          INDEX idx_report_views_supplier (default_for_supplier_id),
          INDEX idx_report_views_system (is_system)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
