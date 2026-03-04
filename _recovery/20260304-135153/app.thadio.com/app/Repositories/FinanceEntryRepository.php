<?php

namespace App\Repositories;

use App\Models\FinanceEntry;
use App\Repositories\PeopleCompatViewRepository;
use PDO;

use App\Support\AuditableTrait;
class FinanceEntryRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            PeopleCompatViewRepository::ensure($this->pdo);
            $this->ensureTable();
        }
    }

    public function list(array $filters = []): array
    {
        if (!$this->pdo) {
            return [];
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        [$sortExpr, $sortDir] = $this->resolveListSort('due', 'DESC');

        $sql = "SELECT f.*, c.name AS category_name, c.type AS category_type,
                       v.full_name AS supplier_name,
                       l.name AS lot_name,
                       ba.label AS bank_account_label, b.name AS bank_name, b.code AS bank_code,
                       pm.name AS payment_method_name,
                       pt.name AS payment_terminal_name
                " . $this->baseFromSql() . "
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDir}, f.id DESC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();

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

        [$whereSql, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(*) " . $this->baseFromSql() . " {$whereSql}";
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
        string $sortKey = 'due',
        string $sortDir = 'DESC'
    ): array {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$whereSql, $params] = $this->buildFilters($filters);
        [$sortExpr, $sortDirection] = $this->resolveListSort($sortKey, $sortDir);

        $sql = "SELECT f.*, c.name AS category_name, c.type AS category_type,
                       v.full_name AS supplier_name,
                       l.name AS lot_name,
                       ba.label AS bank_account_label, b.name AS bank_name, b.code AS bank_code,
                       pm.name AS payment_method_name,
                       pt.name AS payment_terminal_name
                " . $this->baseFromSql() . "
                {$whereSql}
                ORDER BY {$sortExpr} {$sortDirection}, f.id DESC
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

    /**
     * @param array<string, mixed> $filters
     * @return array<string, float|int>
     */
    public function statementSummary(array $filters = []): array
    {
        if (!$this->pdo) {
            return [
                'credits' => 0.0,
                'debits' => 0.0,
                'movement_count' => 0,
                'pending_receivable' => 0.0,
                'pending_payable' => 0.0,
            ];
        }

        [$whereSql, $params] = $this->buildFilters($filters);
        $settledExpr = "CASE
            WHEN f.status = 'pago' THEN CASE WHEN COALESCE(f.paid_amount, 0) > 0 THEN f.paid_amount ELSE f.amount END
            WHEN f.status = 'parcial' THEN GREATEST(COALESCE(f.paid_amount, 0), 0)
            ELSE 0
        END";
        $pendingExpr = "CASE
            WHEN f.status = 'parcial' THEN GREATEST(f.amount - GREATEST(COALESCE(f.paid_amount, 0), 0), 0)
            WHEN f.status = 'pago' THEN 0
            ELSE GREATEST(f.amount, 0)
        END";

        $sql = "SELECT
                    SUM(CASE WHEN f.type = 'receber' AND f.status <> 'cancelado' THEN {$settledExpr} ELSE 0 END) AS credits,
                    SUM(CASE WHEN f.type = 'pagar' AND f.status <> 'cancelado' THEN {$settledExpr} ELSE 0 END) AS debits,
                    SUM(CASE WHEN f.type IN ('receber', 'pagar') AND f.status <> 'cancelado' AND {$settledExpr} > 0 THEN 1 ELSE 0 END) AS movement_count,
                    SUM(CASE WHEN f.type = 'receber' AND f.status <> 'cancelado' THEN {$pendingExpr} ELSE 0 END) AS pending_receivable,
                    SUM(CASE WHEN f.type = 'pagar' AND f.status <> 'cancelado' THEN {$pendingExpr} ELSE 0 END) AS pending_payable
                " . $this->baseFromSql() . "
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'credits' => (float) ($row['credits'] ?? 0),
            'debits' => (float) ($row['debits'] ?? 0),
            'movement_count' => (int) ($row['movement_count'] ?? 0),
            'pending_receivable' => (float) ($row['pending_receivable'] ?? 0),
            'pending_payable' => (float) ($row['pending_payable'] ?? 0),
        ];
    }

    public function find(int $id): ?FinanceEntry
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM financeiro_lancamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? FinanceEntry::fromArray($row) : null;
    }

    public function save(FinanceEntry $entry): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        // Capturar old values para auditoria
        $oldValues = $entry->id ? $this->find($entry->id)?->toArray() : null;
        $action = $entry->id ? 'UPDATE' : 'INSERT';

        if ($entry->id) {
            $sql = "UPDATE financeiro_lancamentos
                    SET type = :type,
                        description = :description,
                        category_id = :category_id,
                        supplier_pessoa_id = :supplier_pessoa_id,
                        lot_id = :lot_id,
                        order_id = :order_id,
                        amount = :amount,
                        due_date = :due_date,
                        status = :status,
                        paid_at = :paid_at,
                        paid_amount = :paid_amount,
                        bank_account_id = :bank_account_id,
                        payment_method_id = :payment_method_id,
                        payment_terminal_id = :payment_terminal_id,
                        notes = :notes
                    WHERE id = :id";
            $params = $entry->toDbParams() + [':id' => $entry->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO financeiro_lancamentos (
                        type, description, category_id, supplier_pessoa_id, lot_id, order_id,
                        amount, due_date, status, paid_at, paid_amount,
                        bank_account_id, payment_method_id, payment_terminal_id, notes
                    ) VALUES (
                        :type, :description, :category_id, :supplier_pessoa_id, :lot_id, :order_id,
                        :amount, :due_date, :status, :paid_at, :paid_amount,
                        :bank_account_id, :payment_method_id, :payment_terminal_id, :notes
                    )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($entry->toDbParams());
            $entry->id = (int) $this->pdo->lastInsertId();
        }

        // Auditoria
        $newValues = $this->find($entry->id)?->toArray();
        $this->auditLog($action, 'finance_entries', $entry->id, $oldValues, $newValues);
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }
        
        // Capturar old values para auditoria
        $oldValues = $this->find($id)?->toArray();
        
        $stmt = $this->pdo->prepare("DELETE FROM financeiro_lancamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Auditoria
        $this->auditLog('DELETE', 'finance_entries', $id, $oldValues, null);
    }

    public function summary(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT type, status,
                       COUNT(*) AS total_entries,
                       SUM(amount) AS total_amount,
                       SUM(COALESCE(paid_amount, 0)) AS total_paid
                FROM financeiro_lancamentos
                GROUP BY type, status";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function overdueSummary(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT type,
                       COUNT(*) AS total_entries,
                       SUM(amount) AS total_amount
                FROM financeiro_lancamentos
                WHERE due_date IS NOT NULL
                  AND due_date < CURRENT_DATE
                  AND status IN ('pendente', 'parcial')
                GROUP BY type";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listPayablesWindow(int $pastDays = 5, int $futureDays = 30, int $limit = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $pastDays = max(0, $pastDays);
        $futureDays = max(0, $futureDays);
        $useLimit = $limit > 0;

        $sql = "SELECT f.id, f.description, f.amount, f.due_date, f.status, f.paid_at, f.paid_amount
                FROM financeiro_lancamentos f
                WHERE f.type = 'pagar'
                  AND f.due_date IS NOT NULL
                  AND f.due_date >= DATE_SUB(CURDATE(), INTERVAL :past_days DAY)
                  AND f.due_date <= DATE_ADD(CURDATE(), INTERVAL :future_days DAY)
                  AND f.status != 'cancelado'
                ORDER BY f.due_date ASC, f.id ASC";
        if ($useLimit) {
            $sql .= "\n                LIMIT :limit_entries";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':past_days', $pastDays, PDO::PARAM_INT);
        $stmt->bindValue(':future_days', $futureDays, PDO::PARAM_INT);
        if ($useLimit) {
            $stmt->bindValue(':limit_entries', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function existsByOrderId(int $orderId): bool
    {
        if (!$this->pdo || $orderId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT id FROM financeiro_lancamentos WHERE order_id = :order_id LIMIT 1");
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchColumn() !== false;
    }

    public function deleteAutoEntriesByOrderIdAndTag(int $orderId, string $tag): int
    {
        if (!$this->pdo || $orderId <= 0) {
            return 0;
        }

        $tag = trim($tag);
        if ($tag === '') {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM financeiro_lancamentos
             WHERE order_id = :order_id
               AND notes LIKE :tag_prefix"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':tag_prefix' => $tag . '%',
        ]);

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) {
            return 0;
        }

        $deleteStmt = $this->pdo->prepare("DELETE FROM financeiro_lancamentos WHERE id = :id");
        $deleted = 0;

        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }

            $oldValues = $this->find($id)?->toArray();
            $deleteStmt->execute([':id' => $id]);
            if ($deleteStmt->rowCount() > 0) {
                $deleted++;
                $this->auditLog('DELETE', 'finance_entries', $id, $oldValues, null);
            }
        }

        return $deleted;
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS financeiro_lancamentos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          type VARCHAR(20) NOT NULL,
          description VARCHAR(255) NOT NULL,
          category_id INT UNSIGNED NULL,
          supplier_pessoa_id BIGINT UNSIGNED NULL,
          lot_id INT UNSIGNED NULL,
          order_id INT UNSIGNED NULL,
          amount DECIMAL(12,2) NOT NULL DEFAULT 0,
          due_date DATE NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'pendente',
          paid_at DATETIME NULL,
          paid_amount DECIMAL(12,2) NULL,
          bank_account_id INT UNSIGNED NULL,
          payment_method_id INT UNSIGNED NULL,
          payment_terminal_id INT UNSIGNED NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_financeiro_lancamentos_type (type),
          INDEX idx_financeiro_lancamentos_status (status),
          INDEX idx_financeiro_lancamentos_due (due_date),
          INDEX idx_financeiro_lancamentos_paid (paid_at),
          INDEX idx_financeiro_lancamentos_category (category_id),
          INDEX idx_financeiro_lancamentos_supplier_pessoa (supplier_pessoa_id),
          INDEX idx_financeiro_lancamentos_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sql);
        $this->addColumnIfMissing('financeiro_lancamentos', 'supplier_pessoa_id', 'BIGINT UNSIGNED NULL', 'category_id');
        $this->addColumnIfMissing('financeiro_lancamentos', 'order_id', 'INT UNSIGNED NULL', 'lot_id');
        $this->addIndexIfMissing('financeiro_lancamentos', 'idx_financeiro_lancamentos_supplier_pessoa', 'supplier_pessoa_id');
        $this->addIndexIfMissing('financeiro_lancamentos', 'idx_financeiro_lancamentos_order', 'order_id');
        $this->addCompositeIndexIfMissing('financeiro_lancamentos', 'idx_financeiro_lancamentos_due_id', ['due_date', 'id']);
        $this->addCompositeIndexIfMissing('financeiro_lancamentos', 'idx_financeiro_lancamentos_status_due', ['status', 'due_date']);
        $this->dropIndexIfExists('financeiro_lancamentos', 'idx_financeiro_lancamentos_supplier');
        $this->dropColumnIfExists('financeiro_lancamentos', 'supplier_id_vendor');
    }

    private function baseFromSql(): string
    {
        return "FROM financeiro_lancamentos f
                LEFT JOIN financeiro_categorias c ON c.id = f.category_id
                LEFT JOIN vw_fornecedores_compat v ON v.id = f.supplier_pessoa_id
                LEFT JOIN produto_lotes l ON l.id = f.lot_id
                LEFT JOIN contas_bancarias ba ON ba.id = f.bank_account_id
                LEFT JOIN bancos b ON b.id = ba.bank_id
                LEFT JOIN metodos_pagamento pm ON pm.id = f.payment_method_id
                LEFT JOIN terminais_pagamento pt ON pt.id = f.payment_terminal_id";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveListSort(string $sortKey, string $sortDir): array
    {
        $sortMap = [
            'id' => 'f.id',
            'type' => "COALESCE(f.type, '')",
            'description' => "COALESCE(f.description, '')",
            'category' => "COALESCE(c.name, '')",
            'supplier' => "COALESCE(v.full_name, '')",
            'amount' => 'f.amount',
            'due' => $this->dueSqlExpression(),
            'status' => "COALESCE(f.status, '')",
            'paid' => 'COALESCE(f.paid_amount, 0)',
            'payment' => $this->paymentSqlExpression(),
            'origin' => $this->originSqlExpression(),
        ];

        $expr = $sortMap[$sortKey] ?? $this->dueSqlExpression();
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        return [$expr, $direction];
    }

    private function dueSqlExpression(): string
    {
        return 'COALESCE(f.due_date, DATE(f.created_at))';
    }

    private function paidSqlExpression(): string
    {
        return "CONCAT_WS(' ',
            COALESCE(CAST(f.paid_amount AS CHAR), ''),
            COALESCE(DATE_FORMAT(f.paid_at, '%d/%m/%Y'), '')
        )";
    }

    private function paymentSqlExpression(): string
    {
        return "CONCAT_WS(' ',
            COALESCE(pm.name, ''),
            COALESCE(ba.label, ''),
            COALESCE(b.name, ''),
            COALESCE(pt.name, '')
        )";
    }

    private function originSqlExpression(): string
    {
        return "CONCAT_WS(' ',
            COALESCE(CONCAT('Pedido #', f.order_id), ''),
            COALESCE(l.name, ''),
            COALESCE(CONCAT('Lote #', f.lot_id), '')
        )";
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $clauses = ['1=1'];
        $params = [];

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $clauses[] = 'f.type = :type_scope';
            $params[':type_scope'] = $type;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $clauses[] = 'f.status = :status_scope';
            $params[':status_scope'] = $status;
        }

        $category = (int) ($filters['category_id'] ?? 0);
        if ($category > 0) {
            $clauses[] = 'f.category_id = :category_id';
            $params[':category_id'] = $category;
        }

        $supplier = (int) ($filters['supplier_pessoa_id'] ?? 0);
        if ($supplier > 0) {
            $clauses[] = 'f.supplier_pessoa_id = :supplier_pessoa_id';
            $params[':supplier_pessoa_id'] = $supplier;
        }

        $supplierSearch = trim((string) ($filters['supplier_search'] ?? ''));
        if ($supplier <= 0 && $supplierSearch !== '') {
            $clauses[] = 'v.full_name COLLATE utf8mb4_unicode_ci LIKE :supplier_search';
            $params[':supplier_search'] = '%' . $supplierSearch . '%';
        }

        $bankAccount = (int) ($filters['bank_account_id'] ?? 0);
        if ($bankAccount > 0) {
            $clauses[] = 'f.bank_account_id = :bank_account_id';
            $params[':bank_account_id'] = $bankAccount;
        }

        $paymentMethod = (int) ($filters['payment_method_id'] ?? 0);
        if ($paymentMethod > 0) {
            $clauses[] = 'f.payment_method_id = :payment_method_id';
            $params[':payment_method_id'] = $paymentMethod;
        }

        $paymentTerminal = (int) ($filters['payment_terminal_id'] ?? 0);
        if ($paymentTerminal > 0) {
            $clauses[] = 'f.payment_terminal_id = :payment_terminal_id';
            $params[':payment_terminal_id'] = $paymentTerminal;
        }

        $dueFrom = trim((string) ($filters['due_from'] ?? ''));
        if ($dueFrom !== '') {
            $clauses[] = 'f.due_date >= :due_from';
            $params[':due_from'] = $dueFrom;
        }

        $dueTo = trim((string) ($filters['due_to'] ?? ''));
        if ($dueTo !== '') {
            $clauses[] = 'f.due_date <= :due_to';
            $params[':due_to'] = $dueTo;
        }

        $paidFrom = trim((string) ($filters['paid_from'] ?? ''));
        if ($paidFrom !== '') {
            $clauses[] = 'f.paid_at >= :paid_from';
            $params[':paid_from'] = $paidFrom . ' 00:00:00';
        }

        $paidTo = trim((string) ($filters['paid_to'] ?? ''));
        if ($paidTo !== '') {
            $clauses[] = 'f.paid_at <= :paid_to';
            $params[':paid_to'] = $paidTo . ' 23:59:59';
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $params[':search_global'] = '%' . $search . '%';
            $clauses[] = "(CAST(f.id AS CHAR) LIKE :search_global
                OR COALESCE(f.type, '') LIKE :search_global
                OR COALESCE(f.description, '') LIKE :search_global
                OR COALESCE(c.name, '') LIKE :search_global
                OR COALESCE(v.full_name, '') LIKE :search_global
                OR CAST(f.amount AS CHAR) LIKE :search_global
                OR CAST(" . $this->dueSqlExpression() . " AS CHAR) LIKE :search_global
                OR COALESCE(f.status, '') LIKE :search_global
                OR " . $this->paidSqlExpression() . " LIKE :search_global
                OR " . $this->paymentSqlExpression() . " LIKE :search_global
                OR " . $this->originSqlExpression() . " LIKE :search_global
                OR COALESCE(f.notes, '') LIKE :search_global)";
        }

        $filterMap = [
            'filter_id' => 'CAST(f.id AS CHAR)',
            'filter_type' => "COALESCE(f.type, '')",
            'filter_description' => "CONCAT_WS(' ', COALESCE(f.description, ''), COALESCE(f.notes, ''))",
            'filter_category' => "COALESCE(c.name, '')",
            'filter_supplier' => "COALESCE(v.full_name, '')",
            'filter_amount' => 'CAST(f.amount AS CHAR)',
            'filter_due' => "CONCAT_WS(' ', COALESCE(CAST(f.due_date AS CHAR), ''), COALESCE(DATE_FORMAT(f.due_date, '%d/%m/%Y'), ''))",
            'filter_status' => "COALESCE(f.status, '')",
            'filter_paid' => $this->paidSqlExpression(),
            'filter_payment' => $this->paymentSqlExpression(),
            'filter_origin' => $this->originSqlExpression(),
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
                $clauses[] = '(' . implode(' OR ', $orParts) . ')';
                continue;
            }
            $param = ':f_' . $key;
            $params[$param] = '%' . ($parts[0] ?? $raw) . '%';
            $clauses[] = "{$expr} LIKE {$param}";
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function addColumnIfMissing(string $table, string $column, string $definition, ?string $after = null): void
    {
        if (!$this->pdo || $this->columnExists($table, $column)) {
            return;
        }
        try {
            $afterSql = $after ? " AFTER `{$after}`" : '';
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterSql}");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar coluna {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (!$this->pdo || $this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar índice {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function addCompositeIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!$this->pdo || $this->indexExists($table, $indexName) || empty($columns)) {
            return;
        }
        try {
            $parts = array_map(static function (string $column): string {
                return '`' . $column . '`';
            }, $columns);
            $colsSql = implode(', ', $parts);
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$colsSql})");
        } catch (\Throwable $e) {
            error_log("Falha ao adicionar índice composto {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->pdo || !$this->columnExists($table, $column)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        } catch (\Throwable $e) {
            error_log("Falha ao remover coluna {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->pdo || !$this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        } catch (\Throwable $e) {
            error_log("Falha ao remover índice {$indexName} em {$table}: " . $e->getMessage());
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
        );
        $stmt->execute([':t' => $table, ':i' => $indexName]);
        return (bool) $stmt->fetchColumn();
    }
}
