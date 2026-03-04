<?php

namespace App\Repositories;

use App\Models\TimeEntry;
use PDO;

use App\Support\AuditableTrait;
class TimeEntryRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function create(TimeEntry $entry): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO ponto_registros (user_id, tipo, registrado_em, status, observacao)
                VALUES (:user_id, :tipo, :registrado_em, :status, :observacao)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($entry->toDbParams());
        $entry->id = (int) $this->pdo->lastInsertId();

        // Auditoria
        $newData = $this->findById($entry->id);
        $this->auditLog('INSERT', 'ponto_registros', $entry->id, null, $newData);
    }

    public function updateStatus(int $id, string $status, int $approvedBy): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        // Capturar dados antigos para auditoria
        $oldData = $this->findById($id);

        $stmt = $this->pdo->prepare(
            "UPDATE ponto_registros
             SET status = :status, aprovado_por = :approved_by, aprovado_em = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $status,
            ':approved_by' => $approvedBy,
            ':id' => $id,
        ]);

        // Auditoria
        $newData = $this->findById($id);
        $this->auditLog('UPDATE', 'ponto_registros', $id, $oldData, $newData);
    }

    public function list(array $filters = []): array
    {
        if (!$this->pdo) {
            return [];
        }

        [$whereSql, $params] = $this->buildListWhere($filters);
        [$sortExpr, $sortDirection] = $this->resolveListSort('registrado_em', 'DESC');

        $sql = "SELECT r.*, u.full_name, u.email, ap.full_name AS aprovado_por_nome
                FROM ponto_registros r
                LEFT JOIN usuarios u ON u.id = r.user_id
                LEFT JOIN usuarios ap ON ap.id = r.aprovado_por
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDirection}, r.id DESC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll();
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
        $sql = "SELECT COUNT(*)
                FROM ponto_registros r
                LEFT JOIN usuarios u ON u.id = r.user_id
                LEFT JOIN usuarios ap ON ap.id = r.aprovado_por
                {$whereSql}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
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
        string $sortKey = 'registrado_em',
        string $sortDir = 'DESC'
    ): array {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildListWhere($filters);
        [$sortExpr, $sortDirection] = $this->resolveListSort($sortKey, $sortDir);

        $sql = "SELECT r.*, u.full_name, u.email, ap.full_name AS aprovado_por_nome
                FROM ponto_registros r
                LEFT JOIN usuarios u ON u.id = r.user_id
                LEFT JOIN usuarios ap ON ap.id = r.aprovado_por
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDirection}, r.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lastForUser(int $userId): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM ponto_registros
             WHERE user_id = :user_id
             ORDER BY registrado_em DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildListWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'r.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $status = $filters['status'] ?? '';
        if ($status !== '' && $status !== 'todos') {
            if (is_array($status)) {
                $in = [];
                foreach ($status as $index => $value) {
                    $key = ':status_' . $index;
                    $in[] = $key;
                    $params[$key] = (string) $value;
                }
                if (!empty($in)) {
                    $where[] = 'r.status IN (' . implode(',', $in) . ')';
                }
            } else {
                $where[] = 'r.status = :status_scope';
                $params[':status_scope'] = (string) $status;
            }
        }

        $start = trim((string) ($filters['start'] ?? ''));
        if ($start !== '') {
            $where[] = 'r.registrado_em >= :start';
            $params[':start'] = $start;
        }

        $end = trim((string) ($filters['end'] ?? ''));
        if ($end !== '') {
            $where[] = 'r.registrado_em <= :end';
            $params[':end'] = $end;
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = "(CAST(r.id AS CHAR) LIKE :search
                OR CAST(r.registrado_em AS CHAR) LIKE :search
                OR COALESCE(u.full_name, '') LIKE :search
                OR COALESCE(r.tipo, '') LIKE :search
                OR COALESCE(r.status, '') LIKE :search
                OR COALESCE(ap.full_name, '') LIKE :search
                OR COALESCE(r.observacao, '') LIKE :search)";
        }

        $filterMap = [
            'filter_registrado_em' => 'CAST(r.registrado_em AS CHAR)',
            'filter_full_name' => "COALESCE(u.full_name, '')",
            'filter_tipo' => "COALESCE(r.tipo, '')",
            'filter_status' => "COALESCE(r.status, '')",
            'filter_aprovado_por_nome' => "COALESCE(ap.full_name, '')",
            'filter_observacao' => "COALESCE(r.observacao, '')",
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
                $where[] = '(' . implode(' OR ', $orParts) . ')';
                continue;
            }
            $param = ':f_' . $key;
            $params[$param] = '%' . ($parts[0] ?? $raw) . '%';
            $where[] = "{$expr} LIKE {$param}";
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveListSort(string $sortKey, string $sortDir): array
    {
        $sortMap = [
            'registrado_em' => 'r.registrado_em',
            'full_name' => "COALESCE(u.full_name, '')",
            'tipo' => "COALESCE(r.tipo, '')",
            'status' => "COALESCE(r.status, '')",
            'aprovado_por_nome' => "COALESCE(ap.full_name, '')",
            'observacao' => "COALESCE(r.observacao, '')",
        ];

        $expr = $sortMap[$sortKey] ?? 'r.registrado_em';
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        return [$expr, $direction];
    }

    private function findById(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM ponto_registros WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS ponto_registros (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          tipo VARCHAR(10) NOT NULL,
          registrado_em DATETIME NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'pendente',
          aprovado_por INT UNSIGNED NULL,
          aprovado_em DATETIME NULL,
          observacao VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_ponto_user (user_id, registrado_em),
          INDEX idx_ponto_registrado (registrado_em),
          INDEX idx_ponto_status (status),
          INDEX idx_ponto_status_registrado (status, registrado_em),
          INDEX idx_ponto_aprovado (aprovado_por)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('status', "ALTER TABLE ponto_registros ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendente' AFTER registrado_em");
        $this->ensureColumn('aprovado_por', "ALTER TABLE ponto_registros ADD COLUMN aprovado_por INT UNSIGNED NULL AFTER status");
        $this->ensureColumn('aprovado_em', "ALTER TABLE ponto_registros ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado_por");
        $this->ensureColumn('observacao', "ALTER TABLE ponto_registros ADD COLUMN observacao VARCHAR(255) NULL AFTER aprovado_em");
        $this->ensureIndex('idx_ponto_registrado', 'registrado_em');
        $this->ensureIndex('idx_ponto_status_registrado', 'status, registrado_em');
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM ponto_registros LIKE :col');
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    private function ensureIndex(string $indexName, string $columns): void
    {
        $stmt = $this->pdo->prepare('SHOW INDEX FROM ponto_registros WHERE Key_name = :name');
        $stmt->execute([':name' => $indexName]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if ($exists) {
            return;
        }
        $this->pdo->exec("ALTER TABLE ponto_registros ADD INDEX {$indexName} ({$columns})");
    }
}
