<?php

namespace App\Repositories;

use App\Models\VoucherAccount;
use PDO;

use App\Support\AuditableTrait;
class VoucherAccountRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function all(?bool $trashed = null): array
    {
        if (!$this->pdo) {
            return [];
        }
        $where = '';
        if ($trashed === true) {
            $where = 'WHERE deleted_at IS NOT NULL';
        } elseif ($trashed === false) {
            $where = 'WHERE deleted_at IS NULL';
        }

        $sql = "SELECT id, pessoa_id, customer_id, customer_name, customer_email, label, type, code, description, status, balance, deleted_at, deleted_by, created_at
                FROM cupons_creditos
                {$where}
                ORDER BY created_at DESC, id DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countForList(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        [$whereSql, $params] = $this->buildListWhere($filters);
        $sql = "SELECT COUNT(*) FROM cupons_creditos c {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginateForList(
        array $filters = [],
        int $limit = 100,
        int $offset = 0,
        string $sortKey = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildListWhere($filters);
        [$sortExpr, $sortDirection] = $this->resolveListSort($sortKey, $sortDir);

        $sql = "SELECT c.id,
                       c.pessoa_id,
                       c.customer_id,
                       c.customer_name,
                       c.customer_email,
                       c.label,
                       c.type,
                       c.scope,
                       c.code,
                       c.description,
                       c.status,
                       c.balance,
                       c.deleted_at,
                       c.deleted_by,
                       c.created_at,
                       c.updated_at
                FROM cupons_creditos c
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDirection}, c.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, pessoa_id, customer_id, customer_name, customer_email, label, type, code, status, balance
            FROM cupons_creditos
            WHERE status = 'ativo' AND deleted_at IS NULL
            ORDER BY label ASC, id ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listActiveWithBalance(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT id, pessoa_id, customer_id, customer_name, customer_email, label, type, code, status, balance, description
                FROM cupons_creditos
                WHERE status = 'ativo'
                  AND deleted_at IS NULL
                  AND balance > 0
                ORDER BY balance DESC, id DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listByPerson(int $personId, bool $includeTrashed = false): array
    {
        if (!$this->pdo || $personId <= 0) {
            return [];
        }
        $sql = "SELECT * FROM cupons_creditos WHERE pessoa_id = :pessoa_id";
        if (!$includeTrashed) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " ORDER BY created_at ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pessoa_id' => $personId]);
        return $stmt->fetchAll();
    }

    public function listByCustomer(int $customerId, bool $includeTrashed = false): array
    {
        return $this->listByPerson($customerId, $includeTrashed);
    }

    public function findActiveCreditByPerson(int $personId): ?VoucherAccount
    {
        if (!$this->pdo || $personId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM cupons_creditos
             WHERE pessoa_id = :pessoa_id
               AND type = 'credito'
               AND status = 'ativo'
               AND deleted_at IS NULL
             ORDER BY created_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([':pessoa_id' => $personId]);
        $row = $stmt->fetch();
        return $row ? VoucherAccount::fromArray($row) : null;
    }

    public function findActiveCreditByCustomer(int $customerId): ?VoucherAccount
    {
        return $this->findActiveCreditByPerson($customerId);
    }

    public function find(int $id, bool $includeTrashed = false): ?VoucherAccount
    {
        if (!$this->pdo) {
            return null;
        }
        $sql = "SELECT * FROM cupons_creditos WHERE id = :id";
        if (!$includeTrashed) {
            $sql .= " AND deleted_at IS NULL";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? VoucherAccount::fromArray($row) : null;
    }

    public function save(VoucherAccount $account): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $personId = $account->personId > 0 ? $account->personId : null;
        $customerId = $account->customerId > 0
            ? $account->customerId
            : ($personId !== null ? (int) $personId : null);
        $isUpdate = (bool) $account->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM cupons_creditos WHERE id = :id");
            $stmt->execute([':id' => $account->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($account->id) {
            $set = [
                'pessoa_id = :pessoa_id',
                'customer_id = :customer_id',
                'customer_name = :customer_name',
                'customer_email = :customer_email',
                'label = :label',
                'type = :type',
                'scope = :scope',
                'code = :code',
                'description = :description',
                'status = :status',
                'balance = :balance',
            ];
            $sql = "UPDATE cupons_creditos SET
                    " . implode(",\n                    ", $set) . "
                WHERE id = :id";
            $params = $account->toDbParams();
            unset($params[':deleted_at'], $params[':deleted_by']);
            $params[':pessoa_id'] = $personId;
            $params[':customer_id'] = $customerId;
            $params[':id'] = $account->id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $columns = ['pessoa_id', 'customer_id', 'customer_name', 'customer_email', 'label', 'type', 'scope', 'code', 'description', 'status', 'balance', 'deleted_at', 'deleted_by'];
            $values = [':pessoa_id', ':customer_id', ':customer_name', ':customer_email', ':label', ':type', ':scope', ':code', ':description', ':status', ':balance', ':deleted_at', ':deleted_by'];
            $sql = "INSERT INTO cupons_creditos
              (" . implode(', ', $columns) . ")
              VALUES
              (" . implode(', ', $values) . ")";
            $stmt = $this->pdo->prepare($sql);
            $params = $account->toDbParams();
            $params[':pessoa_id'] = $personId;
            $params[':customer_id'] = $customerId;
            $stmt->execute($params);
            $account->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM cupons_creditos WHERE id = :id");
        $stmt->execute([':id' => $account->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'cupons_creditos',
            $account->id,
            $oldData,
            $newData
        );
    }

    public function trash(int $id, string $deletedAt, ?string $deletedBy = null): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE cupons_creditos SET deleted_at = :deleted_at, deleted_by = :deleted_by WHERE id = :id");
        $stmt->execute([
            ':deleted_at' => $deletedAt,
            ':deleted_by' => $deletedBy,
            ':id' => $id,
        ]);
    }

    public function restore(int $id): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE cupons_creditos SET deleted_at = NULL, deleted_by = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function forceDelete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare("DELETE FROM cupons_creditos WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function debitBalance(int $id, float $amount): void
    {
        if (!$this->pdo || $amount <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE cupons_creditos SET balance = balance - :amount WHERE id = :id");
        $stmt->execute([
            ':amount' => $amount,
            ':id' => $id,
        ]);
    }

    public function creditBalance(int $id, float $amount): void
    {
        if (!$this->pdo || $amount <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE cupons_creditos SET balance = balance + :amount WHERE id = :id");
        $stmt->execute([
            ':amount' => $amount,
            ':id' => $id,
        ]);
    }

    public function setBalance(int $id, float $amount): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE cupons_creditos SET balance = :amount WHERE id = :id");
        $stmt->execute([
            ':amount' => $amount,
            ':id' => $id,
        ]);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cupons_creditos (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    pessoa_id BIGINT UNSIGNED NOT NULL,
          customer_id BIGINT UNSIGNED NOT NULL,
          customer_name VARCHAR(160) NULL,
          customer_email VARCHAR(160) NULL,
          label VARCHAR(160) NOT NULL,
          type VARCHAR(20) NOT NULL DEFAULT 'credito',
          code VARCHAR(80) NULL,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          balance DECIMAL(10,2) NOT NULL DEFAULT 0,
          deleted_at DATETIME NULL,
          deleted_by VARCHAR(160) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_cupons_creditos_pessoa (pessoa_id),
          INDEX idx_cupons_creditos_customer (customer_id),
          INDEX idx_cupons_creditos_status (status),
          INDEX idx_cupons_creditos_deleted (deleted_at),
          INDEX idx_cupons_creditos_type (type),
          UNIQUE KEY uk_cupons_creditos_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('pessoa_id', "ALTER TABLE cupons_creditos ADD COLUMN pessoa_id BIGINT UNSIGNED NULL");
        $this->ensureColumn('customer_id', "ALTER TABLE cupons_creditos ADD COLUMN customer_id BIGINT UNSIGNED NULL");
        $this->backfillPessoaId();
        $this->ensureColumnType('pessoa_id', 'BIGINT UNSIGNED NOT NULL');
        $this->ensureColumnType('customer_id', 'BIGINT UNSIGNED NOT NULL');
        $this->ensureIndex('idx_cupons_creditos_pessoa', ['pessoa_id'], false);
        $this->ensureIndex('idx_cupons_creditos_customer', ['customer_id'], false);
        $this->ensureIndex('idx_cupons_creditos_deleted_created', ['deleted_at', 'created_at', 'id'], false);
        $this->ensureIndex('idx_cupons_creditos_status_deleted', ['status', 'deleted_at'], false);

        // Saneamento: sincronizar customer_name/email/customer_id com pessoas
        $this->sanitizeCustomerData();

        // Módulo de consignação: campo scope para distinguir contas de consignação
        $this->ensureColumn('scope', "ALTER TABLE cupons_creditos ADD COLUMN scope VARCHAR(30) NULL DEFAULT NULL AFTER type");
        $this->ensureIndex('idx_cupons_creditos_scope', ['scope'], false);
        $this->ensureIndex('idx_cupons_creditos_scope_deleted', ['scope', 'deleted_at'], false);
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM cupons_creditos LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    private function ensureColumnType(string $column, string $expectedDefinition): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM cupons_creditos WHERE Field = :col");
        $stmt->execute([':col' => $column]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        if (!$row) {
            return;
        }
        $currentType = strtoupper((string) ($row['Type'] ?? ''));
        $expected = strtoupper($expectedDefinition);
        if (strpos($currentType, 'BIGINT') !== false && strpos($expected, 'BIGINT') !== false && strpos($currentType, 'UNSIGNED') !== false) {
            return;
        }
        $this->pdo->exec("ALTER TABLE cupons_creditos MODIFY {$column} {$expectedDefinition}");
    }

    /**
     * @param string[] $columns
     */
    private function ensureIndex(string $name, array $columns, bool $unique): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM cupons_creditos WHERE Key_name = :name");
        $stmt->execute([':name' => $name]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        if (!$rows) {
            $this->createIndex($name, $columns, $unique);
            return;
        }

        $current = [];
        foreach ($rows as $row) {
            $seq = (int) ($row['Seq_in_index'] ?? 0);
            if ($seq > 0) {
                $current[$seq] = (string) ($row['Column_name'] ?? '');
            }
        }
        ksort($current);
        $current = array_values(array_filter($current, static function (string $col): bool {
            return $col !== '';
        }));

        if ($current !== $columns) {
            $this->pdo->exec("ALTER TABLE cupons_creditos DROP INDEX {$name}");
            $this->createIndex($name, $columns, $unique);
        }
    }

    /**
     * @param string[] $columns
     */
    private function createIndex(string $name, array $columns, bool $unique): void
    {
        $cols = implode(', ', $columns);
        $type = $unique ? 'UNIQUE KEY' : 'INDEX';
        $this->pdo->exec("ALTER TABLE cupons_creditos ADD {$type} {$name} ({$cols})");
    }

    private function backfillPessoaId(): void
    {
        if (!$this->columnExists('pessoa_id') || !$this->columnExists('customer_id')) {
            return;
        }

        $this->pdo->exec(
            "UPDATE cupons_creditos
             SET customer_id = pessoa_id
             WHERE (customer_id IS NULL OR customer_id = 0)
               AND pessoa_id IS NOT NULL
               AND pessoa_id > 0"
        );

        $this->pdo->exec(
            "UPDATE cupons_creditos
             SET pessoa_id = customer_id
             WHERE (pessoa_id IS NULL OR pessoa_id = 0)
               AND customer_id IS NOT NULL
               AND customer_id > 0"
        );
    }

    /**
     * Saneamento de dados históricos: sincroniza customer_name, customer_email
     * e customer_id com a tabela pessoas (fonte canônica).
     *
     * Corrige:
     * - customer_name/customer_email divergentes do nome real em pessoas
     * - customer_id divergente de pessoa_id
     *
     * Executado automaticamente no ensureTable (uma vez por deploy/migração).
     */
    public function sanitizeCustomerData(): array
    {
        if (!$this->pdo) {
            return ['fixed' => 0, 'errors' => ['Sem conexão com banco.']];
        }

        $fixed = 0;
        $errors = [];

        try {
            // 1) Corrigir customer_id divergente de pessoa_id
            $stmt = $this->pdo->exec(
                "UPDATE cupons_creditos
                 SET customer_id = pessoa_id
                 WHERE pessoa_id > 0
                   AND customer_id != pessoa_id"
            );
            if ($stmt > 0) {
                $fixed += $stmt;
            }

            // 2) Sincronizar customer_name/customer_email com tabela pessoas
            $stmt = $this->pdo->exec(
                "UPDATE cupons_creditos c
                 INNER JOIN pessoas p ON p.id = c.pessoa_id
                 SET c.customer_name = p.full_name,
                     c.customer_email = p.email
                 WHERE c.pessoa_id > 0
                   AND (
                     c.customer_name IS NULL
                     OR c.customer_name != p.full_name
                     OR (c.customer_email IS NULL AND p.email IS NOT NULL)
                     OR (c.customer_email IS NOT NULL AND p.email IS NULL)
                     OR (c.customer_email IS NOT NULL AND p.email IS NOT NULL AND c.customer_email != p.email)
                   )"
            );
            if ($stmt > 0) {
                $fixed += $stmt;
            }
        } catch (\Throwable $e) {
            $errors[] = 'Erro no saneamento de cupons: ' . $e->getMessage();
        }

        return ['fixed' => $fixed, 'errors' => $errors];
    }

    /**
     * Sincroniza customer_name/customer_email de UM cupom específico
     * com a tabela pessoas. Retorna true se corrigiu algo.
     */
    public function syncCustomerDataForAccount(int $accountId): bool
    {
        if (!$this->pdo || $accountId <= 0) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE cupons_creditos c
                 INNER JOIN pessoas p ON p.id = c.pessoa_id
                 SET c.customer_name = p.full_name,
                     c.customer_email = p.email,
                     c.customer_id = c.pessoa_id
                 WHERE c.id = :id
                   AND c.pessoa_id > 0
                   AND (
                     c.customer_id != c.pessoa_id
                     OR c.customer_name IS NULL
                     OR c.customer_name != p.full_name
                     OR (c.customer_email IS NULL AND p.email IS NOT NULL)
                     OR (c.customer_email IS NOT NULL AND p.email IS NULL)
                     OR (c.customer_email IS NOT NULL AND p.email IS NOT NULL AND c.customer_email != p.email)
                   )"
            );
            $stmt->execute([':id' => $accountId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('Falha ao sincronizar cupom #' . $accountId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se um cupom tem movimentações no extrato.
     */
    public function hasMovements(int $accountId): bool
    {
        if (!$this->pdo || $accountId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM cupons_creditos_movimentos WHERE voucher_account_id = :id"
        );
        $stmt->execute([':id' => $accountId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildListWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'trash') {
            $conditions[] = 'c.deleted_at IS NOT NULL';
        } else {
            $conditions[] = 'c.deleted_at IS NULL';
            if ($status !== '') {
                $params[':status_scope'] = $status;
                $conditions[] = 'c.status = :status_scope';
            }
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $conditions[] = "(CAST(c.id AS CHAR) COLLATE utf8mb4_unicode_ci LIKE :search
                OR CAST(c.pessoa_id AS CHAR) COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.customer_name, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.customer_email, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.type, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.code, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.status, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.label, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR COALESCE(c.description, '') COLLATE utf8mb4_unicode_ci LIKE :search
                OR CAST(c.balance AS CHAR) COLLATE utf8mb4_unicode_ci LIKE :search)";
        }

        $filterMap = [
            'filter_id' => 'CAST(c.id AS CHAR) COLLATE utf8mb4_unicode_ci',
            'filter_customer' => "CONCAT_WS(' ', COALESCE(c.customer_name, ''), COALESCE(c.customer_email, ''), CAST(c.pessoa_id AS CHAR) COLLATE utf8mb4_unicode_ci) COLLATE utf8mb4_unicode_ci",
            'filter_type' => "COALESCE(c.type, '') COLLATE utf8mb4_unicode_ci",
            'filter_code' => "COALESCE(c.code, '') COLLATE utf8mb4_unicode_ci",
            'filter_balance' => 'CAST(c.balance AS CHAR) COLLATE utf8mb4_unicode_ci',
            'filter_status' => "COALESCE(c.status, '') COLLATE utf8mb4_unicode_ci",
            'filter_description' => "CONCAT_WS(' ', COALESCE(c.label, ''), COALESCE(c.description, '')) COLLATE utf8mb4_unicode_ci",
        ];

        foreach ($filterMap as $key => $expr) {
            $raw = trim((string) ($filters[$key] ?? ''));
            if ($raw === '') {
                continue;
            }

            $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static function (string $value): bool {
                return $value !== '';
            }));

            if (count($parts) > 1) {
                $orParts = [];
                foreach ($parts as $index => $part) {
                    $param = ':f_' . $key . '_' . $index;
                    $params[$param] = '%' . $part . '%';
                    $orParts[] = "{$expr} LIKE {$param}";
                }
                $conditions[] = '(' . implode(' OR ', $orParts) . ')';
                continue;
            }

            $param = ':f_' . $key;
            $params[$param] = '%' . ($parts[0] ?? $raw) . '%';
            $conditions[] = "{$expr} LIKE {$param}";
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveListSort(string $sortKey, string $sortDir): array
    {
        $sortMap = [
            'id' => 'c.id',
            'customer' => "COALESCE(c.customer_name, '')",
            'type' => "COALESCE(c.type, '')",
            'code' => "COALESCE(c.code, '')",
            'balance' => 'c.balance',
            'status' => "COALESCE(c.status, '')",
            'description' => "COALESCE(c.description, '')",
            'created_at' => 'c.created_at',
        ];

        $expr = $sortMap[$sortKey] ?? 'c.created_at';
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        return [$expr, $direction];
    }

    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM cupons_creditos LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        return $exists;
    }
}
