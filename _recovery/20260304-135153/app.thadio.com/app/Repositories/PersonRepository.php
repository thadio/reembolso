<?php

namespace App\Repositories;

use App\Models\Person;
use App\Support\AuditService;
use PDO;

class PersonRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
                $this->ensureIndexes();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela pessoas: ' . $e->getMessage());
            }
        }
    }

    public function find(int $id): ?Person
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM pessoas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Person::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?Person
    {
        if (!$this->pdo) {
            return null;
        }
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pessoas WHERE LOWER(email) = LOWER(:email) OR LOWER(email2) = LOWER(:email2) LIMIT 1'
        );
        $stmt->execute([':email' => $email, ':email2' => $email]);
        $row = $stmt->fetch();
        return $row ? Person::fromArray($row) : null;
    }

    /**
     * @param int[] $ids
     * @return array<int, Person>
     */
    public function findByIds(array $ids): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM pessoas WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $person = Person::fromArray($row);
            if ($person->id) {
                $map[$person->id] = $person;
            }
        }

        return $map;
    }

    public function save(Person $person): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        
        if (!$person->id) {
            throw new \RuntimeException('Pessoa sem ID definido para salvar.');
        }
        
        // Capturar valores antigos para auditoria (se registro existir)
        $oldValues = null;
        $isUpdate = false;
        try {
            $existing = $this->find($person->id);
            if ($existing) {
                $isUpdate = true;
                $oldValues = $existing->toArray();
            }
        } catch (\Throwable $e) {
            // Se falhar, assume INSERT
        }
        
        $sql = 'INSERT INTO pessoas (
            id, full_name, email, email2, phone, cpf_cnpj, pix_key, instagram, country, state, city, 
            neighborhood, number, street, street2, zip, status, metadata, last_synced_at
        ) VALUES (
            :id, :full_name, :email, :email2, :phone, :cpf_cnpj, :pix_key, :instagram, :country, :state, :city,
            :neighborhood, :number, :street, :street2, :zip, :status, :metadata, :last_synced_at
        )
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            email = VALUES(email),
            email2 = VALUES(email2),
            phone = VALUES(phone),
            cpf_cnpj = VALUES(cpf_cnpj),
            pix_key = VALUES(pix_key),
            instagram = VALUES(instagram),
            country = VALUES(country),
            state = VALUES(state),
            city = VALUES(city),
            neighborhood = VALUES(neighborhood),
            number = VALUES(number),
            street = VALUES(street),
            street2 = VALUES(street2),
            zip = VALUES(zip),
            status = VALUES(status),
            metadata = VALUES(metadata),
            last_synced_at = VALUES(last_synced_at)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($person->toDbParams());
        
        // Auditar operação (não-bloqueante)
        try {
            AuditService::log(
                action: $isUpdate ? 'UPDATE' : 'INSERT',
                tableName: 'pessoas',
                recordId: $person->id,
                oldValues: $oldValues,
                newValues: $person->toArray()
            );
        } catch (\Throwable $e) {
            // Auditoria falhou, mas não quebra operação principal
            error_log('Falha ao auditar pessoa: ' . $e->getMessage());
        }
    }

    /**
     * Lista pessoas com filtros opcionais
     * 
     * @param array $filters {
     *   'role': string (cliente|fornecedor|usuario_retratoapp),
     *   'status': string (ativo|inativo|trash),
     *   'search': string (busca em nome/email/telefone)
     * }
     * @return Person[]
     */
    public function listAll(array $filters = []): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $conditions = [];
        $params = [];
        
        // Filtro de papéis (via metadata JSON)
        if (!empty($filters['role'])) {
            switch ($filters['role']) {
                case 'cliente':
                    $conditions[] = "JSON_EXTRACT(metadata, '$.is_cliente') = TRUE";
                    break;
                case 'fornecedor':
                    $conditions[] = "JSON_EXTRACT(metadata, '$.is_fornecedor') = TRUE";
                    break;
                case 'usuario_retratoapp':
                    $conditions[] = "JSON_EXTRACT(metadata, '$.is_usuario_retratoapp') = TRUE";
                    break;
            }
        }
        
        // Filtro de status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trash') {
                // Buscar apenas deletados (precisa coluna deleted_at)
                // Se não existir, retorna array vazio
                try {
                    $stmt = $this->pdo->query("SHOW COLUMNS FROM pessoas LIKE 'deleted_at'");
                    if ($stmt && $stmt->rowCount() > 0) {
                        $conditions[] = "deleted_at IS NOT NULL";
                    } else {
                        return []; // Sem coluna deleted_at, não há lixeira
                    }
                } catch (\Throwable $e) {
                    return [];
                }
            } else {
                $conditions[] = "status = :status";
                $params[':status'] = $filters['status'];
            }
        }
        
        // Padrão: excluir deletados (se coluna existir)
        if (empty($filters['status']) || $filters['status'] !== 'trash') {
            try {
                $stmt = $this->pdo->query("SHOW COLUMNS FROM pessoas LIKE 'deleted_at'");
                if ($stmt && $stmt->rowCount() > 0) {
                    $conditions[] = "deleted_at IS NULL";
                }
            } catch (\Throwable $e) {
                // Sem coluna deleted_at, ignora filtro
            }
        }
        
        // Busca textual
        if (!empty($filters['search'])) {
            $conditions[] = "(full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM pessoas {$where} ORDER BY full_name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        return array_map([Person::class, 'fromArray'], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countForList(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        [$whereSql, $params] = $this->buildPaginatedListWhere($filters);
        $sql = "SELECT COUNT(*) FROM pessoas p {$whereSql}";
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
        int $limit = 50,
        int $offset = 0,
        string $sortKey = 'full_name',
        string $sortDir = 'ASC'
    ): array {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildPaginatedListWhere($filters);
        [$sortExpr, $sortDirection] = $this->resolvePaginatedListSort($sortKey, $sortDir);

        $sql = "SELECT p.id,
                       p.full_name,
                       p.email,
                       p.email2,
                       p.phone,
                       p.status,
                       p.city,
                       p.state,
                       COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.vendor_code')), '') AS vendor_code,
                       IF(JSON_EXTRACT(p.metadata, '$.is_cliente') = TRUE, 1, 0) AS is_cliente,
                       IF(JSON_EXTRACT(p.metadata, '$.is_fornecedor') = TRUE, 1, 0) AS is_fornecedor,
                       IF(JSON_EXTRACT(p.metadata, '$.is_usuario_retratoapp') = TRUE, 1, 0) AS is_usuario_retratoapp
                FROM pessoas p
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDirection}, p.id DESC
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

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS pessoas (
          id BIGINT UNSIGNED PRIMARY KEY,
          full_name VARCHAR(200) NOT NULL,
          email VARCHAR(255) NULL,
          phone VARCHAR(50) NULL,
          cpf_cnpj VARCHAR(40) NULL,
          pix_key VARCHAR(180) NULL,
          instagram VARCHAR(120) NULL,
          country VARCHAR(60) NULL,
          state VARCHAR(60) NULL,
          city VARCHAR(120) NULL,
          neighborhood VARCHAR(120) NULL,
          number VARCHAR(40) NULL,
          street VARCHAR(200) NULL,
          street2 VARCHAR(200) NULL,
          zip VARCHAR(30) NULL,
          status ENUM('ativo','inativo','pendente') NOT NULL DEFAULT 'ativo',
          metadata JSON NULL,
          last_synced_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_pessoas_email (email),
          INDEX idx_pessoas_full_name (full_name),
          INDEX idx_pessoas_status (status),
          INDEX idx_pessoas_updated_at (updated_at),
          INDEX idx_pessoas_status_updated_at (status, updated_at),
          INDEX idx_pessoas_last_synced (last_synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    private function ensureIndexes(): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->ensureIndex('pessoas', 'idx_pessoas_full_name', 'full_name');
        $this->ensureIndex('pessoas', 'idx_pessoas_updated_at', 'updated_at');
        $this->ensureIndex('pessoas', 'idx_pessoas_status_updated_at', 'status, updated_at');
    }

    private function ensureIndex(string $table, string $indexName, string $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD INDEX %s (%s)', $table, $indexName, $columns));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND index_name = :index'
        );
        $stmt->execute([
            ':table' => $table,
            ':index' => $indexName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildPaginatedListWhere(array $filters): array
    {
        $params = [];
        $conditions = ['1=1'];

        $roleExpr = $this->roleSqlExpression();
        $cityStateExpr = $this->cityStateSqlExpression();
        $vendorExpr = $this->vendorCodeSqlExpression();
        $hasDeletedAt = $this->hasDeletedAtColumn();

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $roleMap = [
                'cliente' => '$.is_cliente',
                'fornecedor' => '$.is_fornecedor',
                'usuario_retratoapp' => '$.is_usuario_retratoapp',
            ];
            if (isset($roleMap[$role])) {
                $conditions[] = "JSON_EXTRACT(p.metadata, '{$roleMap[$role]}') = TRUE";
            }
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'trash') {
            if ($hasDeletedAt) {
                $conditions[] = 'p.deleted_at IS NOT NULL';
            } else {
                $conditions[] = '1=0';
            }
        } elseif ($status !== '') {
            $params[':status'] = $status;
            $conditions[] = 'p.status = :status';
            if ($hasDeletedAt) {
                $conditions[] = 'p.deleted_at IS NULL';
            }
        } elseif ($hasDeletedAt) {
            $conditions[] = 'p.deleted_at IS NULL';
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $conditions[] = "(CAST(p.id AS CHAR) LIKE :search
                OR p.full_name LIKE :search
                OR COALESCE(p.email, '') LIKE :search
                OR COALESCE(p.email2, '') LIKE :search
                OR COALESCE(p.phone, '') LIKE :search
                OR {$roleExpr} LIKE :search
                OR {$cityStateExpr} LIKE :search
                OR {$vendorExpr} LIKE :search
                OR COALESCE(p.status, '') LIKE :search)";
        }

        $filterMap = [
            'filter_id' => 'CAST(p.id AS CHAR)',
            'filter_full_name' => 'p.full_name',
            'filter_email' => "CONCAT_WS(' ', COALESCE(p.email, ''), COALESCE(p.email2, ''))",
            'filter_phone' => 'COALESCE(p.phone, \'\')',
            'filter_roles' => $roleExpr,
            'filter_status' => 'COALESCE(p.status, \'\')',
            'filter_city_state' => $cityStateExpr,
            'filter_vendor' => $vendorExpr,
        ];

        foreach ($filterMap as $key => $expr) {
            $raw = trim((string) ($filters[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            $values = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== ''));
            if (count($values) > 1) {
                $orParts = [];
                foreach ($values as $index => $value) {
                    $param = ':f_' . $key . '_' . $index;
                    $params[$param] = '%' . $value . '%';
                    $orParts[] = "{$expr} LIKE {$param}";
                }
                $conditions[] = '(' . implode(' OR ', $orParts) . ')';
                continue;
            }
            $param = ':f_' . $key;
            $params[$param] = '%' . ($values[0] ?? $raw) . '%';
            $conditions[] = "{$expr} LIKE {$param}";
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePaginatedListSort(string $sortKey, string $sortDir): array
    {
        $sortMap = [
            'id' => 'p.id',
            'full_name' => 'p.full_name',
            'email' => 'COALESCE(p.email, \'\')',
            'phone' => 'COALESCE(p.phone, \'\')',
            'roles' => $this->roleSqlExpression(),
            'status' => 'COALESCE(p.status, \'\')',
            'city_state' => $this->cityStateSqlExpression(),
            'vendor' => $this->vendorCodeSqlExpression(),
        ];

        $expr = $sortMap[$sortKey] ?? 'p.full_name';
        $direction = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        return [$expr, $direction];
    }

    private function hasDeletedAtColumn(): bool
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM pessoas LIKE 'deleted_at'");
        return $stmt && $stmt->rowCount() > 0;
    }

    private function roleSqlExpression(): string
    {
        return "TRIM(BOTH ', ' FROM CONCAT_WS(', ',
            IF(JSON_EXTRACT(p.metadata, '$.is_cliente') = TRUE, 'cliente', NULL),
            IF(JSON_EXTRACT(p.metadata, '$.is_fornecedor') = TRUE, 'fornecedor', NULL),
            IF(JSON_EXTRACT(p.metadata, '$.is_usuario_retratoapp') = TRUE, 'usuario_retratoapp', NULL)
        ))";
    }

    private function cityStateSqlExpression(): string
    {
        return "TRIM(BOTH '/' FROM CONCAT(COALESCE(p.city, ''), '/', COALESCE(p.state, '')))";
    }

    private function vendorCodeSqlExpression(): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.vendor_code')), '')";
    }
}
