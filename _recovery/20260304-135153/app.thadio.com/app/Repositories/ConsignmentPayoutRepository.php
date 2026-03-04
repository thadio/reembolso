<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;

class ConsignmentPayoutRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private const TABLE = 'consignment_payouts';

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

    // ─── QUERIES ────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        if (!$this->pdo) {
            return ['items' => [], 'total' => 0];
        }

        $where = [];
        $params = [];

        if (!empty($filters['supplier_pessoa_id'])) {
            $where[] = "py.supplier_pessoa_id = :supplier";
            $params[':supplier'] = (int) $filters['supplier_pessoa_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = "py.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "py.payout_date >= :dfrom";
            $params[':dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "py.payout_date <= :dto";
            $params[':dto'] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(
                CAST(py.id AS CHAR) LIKE :search
                OR COALESCE(f.full_name, '') LIKE :search
                OR COALESCE(py.method, '') LIKE :search
                OR COALESCE(py.reference, '') LIKE :search
                OR COALESCE(py.status, '') LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['filter_id'])) {
            $where[] = "CAST(py.id AS CHAR) LIKE :filter_id";
            $params[':filter_id'] = '%' . $filters['filter_id'] . '%';
        }
        if (!empty($filters['filter_supplier_name'])) {
            $where[] = "(COALESCE(f.full_name, '') LIKE :filter_supplier_name OR CAST(py.supplier_pessoa_id AS CHAR) LIKE :filter_supplier_name)";
            $params[':filter_supplier_name'] = '%' . $filters['filter_supplier_name'] . '%';
        }
        if (!empty($filters['filter_method'])) {
            $where[] = "COALESCE(py.method, '') LIKE :filter_method";
            $params[':filter_method'] = '%' . $filters['filter_method'] . '%';
        }
        if (!empty($filters['filter_status'])) {
            $rawStatus = trim((string) $filters['filter_status']);
            if (strpos($rawStatus, ',') !== false) {
                $values = array_values(array_filter(array_map('trim', explode(',', $rawStatus))));
                if (!empty($values)) {
                    $statusPlaceholders = [];
                    foreach ($values as $idx => $value) {
                        $key = ':filter_status_' . $idx;
                        $statusPlaceholders[] = $key;
                        $params[$key] = $value;
                    }
                    $where[] = 'py.status IN (' . implode(',', $statusPlaceholders) . ')';
                }
            } else {
                $where[] = "py.status LIKE :filter_status";
                $params[':filter_status'] = '%' . $rawStatus . '%';
            }
        }
        if (!empty($filters['filter_reference'])) {
            $where[] = "COALESCE(py.reference, '') LIKE :filter_reference";
            $params[':filter_reference'] = '%' . $filters['filter_reference'] . '%';
        }
        if (!empty($filters['filter_total_amount'])) {
            $where[] = "CAST(COALESCE(py.total_amount, 0) AS CHAR) LIKE :filter_total_amount";
            $params[':filter_total_amount'] = '%' . $filters['filter_total_amount'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM " . self::TABLE . " py
                     LEFT JOIN vw_fornecedores_compat f ON f.id = py.supplier_pessoa_id
                     {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT py.*,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', py.supplier_pessoa_id)) AS supplier_name
                FROM " . self::TABLE . " py
                LEFT JOIN vw_fornecedores_compat f ON f.id = py.supplier_pessoa_id
                {$whereClause}
                ORDER BY py.payout_date DESC, py.id DESC
                LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * List payouts for a supplier.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBySupplier(int $supplierPessoaId, ?string $status = null): array
    {
        if (!$this->pdo || $supplierPessoaId <= 0) {
            return [];
        }

        $sql = "SELECT * FROM " . self::TABLE . " WHERE supplier_pessoa_id = :sid";
        $params = [':sid' => $supplierPessoaId];
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY payout_date DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    /**
     * Create a new payout. Returns the inserted ID.
     */
    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO " . self::TABLE . " (
                  supplier_pessoa_id, payout_date, method, total_amount, items_count,
                  status, reference, pix_key, origin_bank_account_id,
                  voucher_account_id, finance_entry_id, notes,
                  confirmed_at, confirmed_by, canceled_at, canceled_by,
                  cancelation_reason, created_by
                ) VALUES (
                  :supplier_pessoa_id, :payout_date, :method, :total_amount, :items_count,
                  :status, :reference, :pix_key, :origin_bank_account_id,
                  :voucher_account_id, :finance_entry_id, :notes,
                  :confirmed_at, :confirmed_by, :canceled_at, :canceled_by,
                  :cancelation_reason, :created_by
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':supplier_pessoa_id' => $data['supplier_pessoa_id'],
            ':payout_date' => $data['payout_date'] ?? date('Y-m-d'),
            ':method' => $data['method'] ?? 'pix',
            ':total_amount' => $data['total_amount'] ?? 0,
            ':items_count' => $data['items_count'] ?? 0,
            ':status' => $data['status'] ?? 'rascunho',
            ':reference' => $data['reference'] ?? null,
            ':pix_key' => $data['pix_key'] ?? null,
            ':origin_bank_account_id' => $data['origin_bank_account_id'] ?? null,
            ':voucher_account_id' => $data['voucher_account_id'] ?? null,
            ':finance_entry_id' => $data['finance_entry_id'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':confirmed_at' => $data['confirmed_at'] ?? null,
            ':confirmed_by' => $data['confirmed_by'] ?? null,
            ':canceled_at' => $data['canceled_at'] ?? null,
            ':canceled_by' => $data['canceled_by'] ?? null,
            ':cancelation_reason' => $data['cancelation_reason'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLog('create', 'consignment_payout', $id, null, $data);
        return $id;
    }

    /**
     * Update a payout.
     */
    public function update(int $id, array $data): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }

        $old = $this->find($id);

        $updatable = [
            'payout_date', 'method', 'total_amount', 'items_count',
            'status', 'reference', 'pix_key', 'origin_bank_account_id',
            'voucher_account_id', 'finance_entry_id', 'notes',
            'confirmed_at', 'confirmed_by', 'canceled_at', 'canceled_by',
            'cancelation_reason',
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($updatable as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->auditLog('update', 'consignment_payout', $id, $old, $data);
    }

    /**
     * Mark a payout as confirmed.
     */
    public function confirm(int $id, int $userId): void
    {
        $this->update($id, [
            'status' => 'confirmado',
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirmed_by' => $userId,
        ]);
    }

    /**
     * Mark a payout as canceled.
     */
    public function cancel(int $id, int $userId, string $reason): void
    {
        $this->update($id, [
            'status' => 'cancelado',
            'canceled_at' => date('Y-m-d H:i:s'),
            'canceled_by' => $userId,
            'cancelation_reason' => $reason,
        ]);
    }

    // ─── SCHEMA ─────────────────────────────────────────────────

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          supplier_pessoa_id BIGINT UNSIGNED NOT NULL,
          payout_date DATE NOT NULL,
          method ENUM('pix','transferencia','dinheiro','outro') NOT NULL DEFAULT 'pix',
          total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          items_count INT UNSIGNED NOT NULL DEFAULT 0,
          status ENUM('rascunho','confirmado','cancelado') NOT NULL DEFAULT 'rascunho',
          reference VARCHAR(255) NULL COMMENT 'Chave PIX / TXID / descrição',
          pix_key VARCHAR(255) NULL,
          origin_bank_account_id INT UNSIGNED NULL COMMENT 'FK bank_accounts',
          voucher_account_id INT UNSIGNED NULL COMMENT 'FK cupons_creditos',
          finance_entry_id BIGINT UNSIGNED NULL COMMENT 'FK finance_entries',
          notes TEXT NULL,
          confirmed_at DATETIME NULL,
          confirmed_by BIGINT UNSIGNED NULL,
          canceled_at DATETIME NULL,
          canceled_by BIGINT UNSIGNED NULL,
          cancelation_reason TEXT NULL,
          created_by BIGINT UNSIGNED NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_consign_payout_supplier (supplier_pessoa_id),
          INDEX idx_consign_payout_status (status),
          INDEX idx_consign_payout_date (payout_date),
          INDEX idx_consign_payout_voucher (voucher_account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
