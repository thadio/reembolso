<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CostMirrorRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'reference_month' => 'cm.reference_month',
            'person_name' => 'p.name',
            'organ_name' => 'o.name',
            'total_amount' => 'cm.total_amount',
            'status' => 'cm.status',
            'created_at' => 'cm.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'reference_month');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'cm.reference_month';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE cm.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                cm.title LIKE :q_title
                OR p.name LIKE :q_person
                OR i.invoice_number LIKE :q_invoice_number
                OR i.title LIKE :q_invoice_title
                OR EXISTS (
                    SELECT 1
                    FROM cost_mirror_items cmi_search
                    WHERE cmi_search.cost_mirror_id = cm.id
                      AND cmi_search.deleted_at IS NULL
                      AND cmi_search.item_name LIKE :q_item
                )
            )';
            $search = '%' . $query . '%';
            $params['q_title'] = $search;
            $params['q_person'] = $search;
            $params['q_invoice_number'] = $search;
            $params['q_invoice_title'] = $search;
            $params['q_item'] = $search;
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND cm.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $personId = (int) ($filters['person_id'] ?? 0);
        if ($personId > 0) {
            $where .= ' AND cm.person_id = :person_id';
            $params['person_id'] = $personId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND cm.status = :status';
            $params['status'] = $status;
        }

        $referenceMonth = trim((string) ($filters['reference_month'] ?? ''));
        if ($referenceMonth !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                $where .= ' AND 1 = 0';
            } else {
                $where .= ' AND cm.reference_month >= :reference_month_start
                    AND cm.reference_month < :reference_month_end';
                $params['reference_month_start'] = $referenceRange['start'];
                $params['reference_month_end'] = $referenceRange['end'];
            }
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM cost_mirrors cm
             INNER JOIN people p ON p.id = cm.person_id
             INNER JOIN organs o ON o.id = cm.organ_id
             LEFT JOIN invoices i ON i.id = cm.invoice_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                cm.id,
                cm.person_id,
                cm.organ_id,
                cm.invoice_id,
                cm.reference_month,
                cm.title,
                cm.source,
                cm.status,
                cm.total_amount,
                cm.notes,
                cm.created_by,
                cm.created_at,
                cm.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                o.name AS organ_name,
                i.invoice_number,
                i.title AS invoice_title,
                i.status AS invoice_status,
                u.name AS created_by_name,
                (
                    SELECT COUNT(*)
                    FROM cost_mirror_items cmi
                    WHERE cmi.cost_mirror_id = cm.id
                      AND cmi.deleted_at IS NULL
                ) AS items_count
             FROM cost_mirrors cm
             INNER JOIN people p ON p.id = cm.person_id
             INNER JOIN organs o ON o.id = cm.organ_id
             LEFT JOIN invoices i ON i.id = cm.invoice_id
             LEFT JOIN users u ON u.id = cm.created_by
             {$where}
             ORDER BY {$sortColumn} {$direction}, cm.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'items' => $listStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cm.id,
                cm.person_id,
                cm.organ_id,
                cm.invoice_id,
                cm.reference_month,
                cm.title,
                cm.source,
                cm.status,
                cm.total_amount,
                cm.notes,
                cm.created_by,
                cm.created_at,
                cm.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                o.name AS organ_name,
                i.invoice_number,
                i.title AS invoice_title,
                i.status AS invoice_status,
                u.name AS created_by_name,
                (
                    SELECT COUNT(*)
                    FROM cost_mirror_items cmi
                    WHERE cmi.cost_mirror_id = cm.id
                      AND cmi.deleted_at IS NULL
                ) AS items_count
             FROM cost_mirrors cm
             INNER JOIN people p ON p.id = cm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = cm.organ_id
             LEFT JOIN invoices i ON i.id = cm.invoice_id AND i.deleted_at IS NULL
             LEFT JOIN users u ON u.id = cm.created_by
             WHERE cm.id = :id
               AND cm.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function itemsByMirror(int $mirrorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cmi.id,
                cmi.cost_mirror_id,
                cmi.item_name,
                cmi.item_code,
                cmi.quantity,
                cmi.unit_amount,
                cmi.amount,
                cmi.notes,
                cmi.created_by,
                cmi.created_at,
                cmi.updated_at,
                u.name AS created_by_name
             FROM cost_mirror_items cmi
             LEFT JOIN users u ON u.id = cmi.created_by
             WHERE cmi.cost_mirror_id = :cost_mirror_id
               AND cmi.deleted_at IS NULL
             ORDER BY cmi.created_at ASC, cmi.id ASC'
        );
        $stmt->execute(['cost_mirror_id' => $mirrorId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findItemById(int $mirrorId, int $itemId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                cost_mirror_id,
                item_name,
                item_code,
                quantity,
                unit_amount,
                amount,
                notes,
                created_by,
                created_at,
                updated_at
             FROM cost_mirror_items
             WHERE id = :id
               AND cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $itemId,
            'cost_mirror_id' => $mirrorId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name
             FROM organs
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $organId = 0, int $limit = 500): array
    {
        $sql = 'SELECT
                    p.id,
                    p.name,
                    p.status,
                    p.organ_id,
                    o.name AS organ_name
                FROM people p
                INNER JOIN organs o ON o.id = p.organ_id
                WHERE p.deleted_at IS NULL';

        if ($organId > 0) {
            $sql .= ' AND p.organ_id = :organ_id';
        }

        $sql .= ' ORDER BY p.name ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        if ($organId > 0) {
            $stmt->bindValue(':organ_id', $organId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', max(1, min(2000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeInvoices(int $organId = 0, ?string $referenceMonth = null, int $limit = 300): array
    {
        $sql = 'SELECT
                    i.id,
                    i.organ_id,
                    i.invoice_number,
                    i.title,
                    i.reference_month,
                    i.due_date,
                    i.status,
                    o.name AS organ_name
                FROM invoices i
                INNER JOIN organs o ON o.id = i.organ_id
                WHERE i.deleted_at IS NULL';

        if ($organId > 0) {
            $sql .= ' AND i.organ_id = :organ_id';
        }

        if ($referenceMonth !== null && trim($referenceMonth) !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                return [];
            }
            $sql .= ' AND i.reference_month >= :reference_month_start
                AND i.reference_month < :reference_month_end';
        }

        $sql .= ' ORDER BY i.reference_month DESC, i.due_date DESC, i.id DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        if ($organId > 0) {
            $stmt->bindValue(':organ_id', $organId, PDO::PARAM_INT);
        }
        if ($referenceMonth !== null && trim($referenceMonth) !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                return [];
            }
            $stmt->bindValue(':reference_month_start', $referenceRange['start']);
            $stmt->bindValue(':reference_month_end', $referenceRange['end']);
        }
        $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array{start: string, end: string}|null */
    private function monthRange(string $value): ?array
    {
        $month = trim($value);
        if ($month === '' || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return null;
        }

        $start = \DateTimeImmutable::createFromFormat('!Y-m', $month);
        if (!$start instanceof \DateTimeImmutable) {
            return null;
        }

        return [
            'start' => $start->format('Y-m-01'),
            'end' => $start->modify('+1 month')->format('Y-m-01'),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findPersonById(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.status,
                p.organ_id,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.id = :id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findInvoiceById(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.organ_id,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.status
             FROM invoices i
             WHERE i.id = :id
               AND i.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $invoiceId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByPersonAndMonth(int $personId, string $referenceMonth, ?int $ignoreId = null): ?array
    {
        $sql = 'SELECT
                    id,
                    person_id,
                    reference_month,
                    title
                FROM cost_mirrors
                WHERE person_id = :person_id
                  AND reference_month = :reference_month
                  AND deleted_at IS NULL';
        $params = [
            'person_id' => $personId,
            'reference_month' => $referenceMonth,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_mirrors (
                person_id,
                organ_id,
                invoice_id,
                reference_month,
                title,
                source,
                status,
                total_amount,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :person_id,
                :organ_id,
                :invoice_id,
                :reference_month,
                :title,
                :source,
                :status,
                :total_amount,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'person_id' => $data['person_id'],
            'organ_id' => $data['organ_id'],
            'invoice_id' => $data['invoice_id'],
            'reference_month' => $data['reference_month'],
            'title' => $data['title'],
            'source' => $data['source'],
            'status' => $data['status'],
            'total_amount' => $data['total_amount'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirrors
             SET
                person_id = :person_id,
                organ_id = :organ_id,
                invoice_id = :invoice_id,
                reference_month = :reference_month,
                title = :title,
                source = :source,
                status = :status,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'person_id' => $data['person_id'],
            'organ_id' => $data['organ_id'],
            'invoice_id' => $data['invoice_id'],
            'reference_month' => $data['reference_month'],
            'title' => $data['title'],
            'source' => $data['source'],
            'status' => $data['status'],
            'notes' => $data['notes'],
        ]);
    }

    public function updateSource(int $id, string $source): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirrors
             SET source = :source, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'source' => $source,
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirrors
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function softDeleteItemsByMirror(int $mirrorId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirror_items
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['cost_mirror_id' => $mirrorId]);
    }

    public function softDeleteItem(int $mirrorId, int $itemId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirror_items
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $itemId,
            'cost_mirror_id' => $mirrorId,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function createItem(int $mirrorId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_mirror_items (
                cost_mirror_id,
                item_name,
                item_code,
                quantity,
                unit_amount,
                amount,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :cost_mirror_id,
                :item_name,
                :item_code,
                :quantity,
                :unit_amount,
                :amount,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'cost_mirror_id' => $mirrorId,
            'item_name' => $data['item_name'],
            'item_code' => $data['item_code'],
            'quantity' => $data['quantity'],
            'unit_amount' => $data['unit_amount'],
            'amount' => $data['amount'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function recalculateTotal(int $mirrorId): string
    {
        $sumStmt = $this->db->prepare(
            'SELECT IFNULL(SUM(amount), 0) AS total
             FROM cost_mirror_items
             WHERE cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL'
        );
        $sumStmt->execute(['cost_mirror_id' => $mirrorId]);
        $total = (float) ($sumStmt->fetch()['total'] ?? 0);

        $formatted = number_format($total, 2, '.', '');
        $updateStmt = $this->db->prepare(
            'UPDATE cost_mirrors
             SET total_amount = :total_amount, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $updateStmt->execute([
            'id' => $mirrorId,
            'total_amount' => $formatted,
        ]);

        return $formatted;
    }

    public function isLockedByReconciliation(int $mirrorId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM cost_mirror_reconciliations
             WHERE cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL
               AND status = "aprovado"
               AND lock_editing = 1
             LIMIT 1'
        );
        $stmt->execute(['cost_mirror_id' => $mirrorId]);

        return $stmt->fetch() !== false;
    }
}
