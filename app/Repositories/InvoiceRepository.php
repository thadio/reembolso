<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InvoiceRepository
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
            'invoice_number' => 'i.invoice_number',
            'reference_month' => 'i.reference_month',
            'due_date' => 'i.due_date',
            'total_amount' => 'i.total_amount',
            'status' => 'i.status',
            'financial_nature' => 'i.financial_nature',
            'created_at' => 'i.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'due_date');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'i.due_date';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE i.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                i.invoice_number LIKE :q_number
                OR i.title LIKE :q_title
                OR i.digitable_line LIKE :q_digitable
                OR i.reference_code LIKE :q_reference
                OR o.name LIKE :q_organ
            )';
            $search = '%' . $query . '%';
            $params['q_number'] = $search;
            $params['q_title'] = $search;
            $params['q_digitable'] = $search;
            $params['q_reference'] = $search;
            $params['q_organ'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND i.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $referenceMonth = trim((string) ($filters['reference_month'] ?? ''));
        if ($referenceMonth !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                $where .= ' AND 1 = 0';
            } else {
                $where .= ' AND i.reference_month >= :reference_month_start
                    AND i.reference_month < :reference_month_end';
                $params['reference_month_start'] = $referenceRange['start'];
                $params['reference_month_end'] = $referenceRange['end'];
            }
        }

        $financialNature = trim((string) ($filters['financial_nature'] ?? ''));
        if ($financialNature !== '') {
            $where .= ' AND i.financial_nature = :financial_nature';
            $params['financial_nature'] = $financialNature;
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM invoices i
             INNER JOIN organs o ON o.id = i.organ_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                i.id,
                i.organ_id,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.status,
                i.financial_nature,
                i.digitable_line,
                i.reference_code,
                i.pdf_original_name,
                i.pdf_storage_path,
                i.notes,
                i.created_by,
                i.created_at,
                i.updated_at,
                o.name AS organ_name,
                u.name AS created_by_name,
                IFNULL(SUM(ip.allocated_amount), 0) AS allocated_amount,
                IFNULL(SUM(ip.paid_amount), 0) AS linked_paid_amount,
                (i.total_amount - IFNULL(SUM(ip.allocated_amount), 0)) AS available_amount,
                COUNT(ip.id) AS linked_people_count
            FROM invoices i
            INNER JOIN organs o ON o.id = i.organ_id
            LEFT JOIN users u ON u.id = i.created_by
            LEFT JOIN invoice_people ip
              ON ip.invoice_id = i.id
             AND ip.deleted_at IS NULL
            {$where}
            GROUP BY
                i.id,
                i.organ_id,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.status,
                i.financial_nature,
                i.digitable_line,
                i.reference_code,
                i.pdf_original_name,
                i.pdf_storage_path,
                i.notes,
                i.created_by,
                i.created_at,
                i.updated_at,
                o.name,
                u.name
            ORDER BY {$sortColumn} {$direction}, i.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $listStmt = $this->db->prepare($listSql);
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
                i.id,
                i.organ_id,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.status,
                i.financial_nature,
                i.digitable_line,
                i.reference_code,
                i.pdf_original_name,
                i.pdf_stored_name,
                i.pdf_mime_type,
                i.pdf_file_size,
                i.pdf_storage_path,
                i.notes,
                i.created_by,
                i.created_at,
                i.updated_at,
                o.name AS organ_name,
                u.name AS created_by_name,
                IFNULL(SUM(ip.allocated_amount), 0) AS allocated_amount,
                IFNULL(SUM(ip.paid_amount), 0) AS linked_paid_amount,
                (i.total_amount - IFNULL(SUM(ip.allocated_amount), 0)) AS available_amount,
                COUNT(ip.id) AS linked_people_count
             FROM invoices i
             INNER JOIN organs o ON o.id = i.organ_id
             LEFT JOIN users u ON u.id = i.created_by
             LEFT JOIN invoice_people ip
               ON ip.invoice_id = i.id
              AND ip.deleted_at IS NULL
             WHERE i.id = :id
               AND i.deleted_at IS NULL
             GROUP BY
                i.id,
                i.organ_id,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.status,
                i.financial_nature,
                i.digitable_line,
                i.reference_code,
                i.pdf_original_name,
                i.pdf_stored_name,
                i.pdf_mime_type,
                i.pdf_file_size,
                i.pdf_storage_path,
                i.notes,
                i.created_by,
                i.created_at,
                i.updated_at,
                o.name,
                u.name
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function linksByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                ip.id,
                ip.invoice_id,
                ip.person_id,
                ip.allocated_amount,
                ip.paid_amount,
                ip.notes,
                ip.created_by,
                ip.created_at,
                ip.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                o.name AS person_organ_name,
                u.name AS created_by_name
             FROM invoice_people ip
             INNER JOIN people p
               ON p.id = ip.person_id
              AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN users u ON u.id = ip.created_by
             WHERE ip.invoice_id = :invoice_id
               AND ip.deleted_at IS NULL
             ORDER BY ip.created_at DESC, ip.id DESC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function paymentsByInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.invoice_id,
                p.payment_date,
                p.amount,
                p.financial_nature,
                p.process_reference,
                p.proof_original_name,
                p.proof_storage_path,
                p.notes,
                p.created_by,
                p.created_at,
                p.updated_at,
                u.name AS created_by_name,
                IFNULL(SUM(pp.allocated_amount), 0) AS allocated_amount
             FROM payments p
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN payment_people pp
               ON pp.payment_id = p.id
              AND pp.deleted_at IS NULL
             WHERE p.invoice_id = :invoice_id
               AND p.deleted_at IS NULL
             GROUP BY
                p.id,
                p.invoice_id,
                p.payment_date,
                p.amount,
                p.financial_nature,
                p.process_reference,
                p.proof_original_name,
                p.proof_storage_path,
                p.notes,
                p.created_by,
                p.created_at,
                p.updated_at,
                u.name
             ORDER BY p.payment_date DESC, p.id DESC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginatePaymentBatches(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'batch_code' => 'pb.batch_code',
            'status' => 'pb.status',
            'scheduled_payment_date' => 'pb.scheduled_payment_date',
            'payments_count' => 'pb.payments_count',
            'total_amount' => 'pb.total_amount',
            'created_at' => 'pb.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'pb.created_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE pb.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                pb.batch_code LIKE :q_code
                OR pb.title LIKE :q_title
                OR pb.notes LIKE :q_notes
            )';
            $search = '%' . $query . '%';
            $params['q_code'] = $search;
            $params['q_title'] = $search;
            $params['q_notes'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND pb.status = :status';
            $params['status'] = $status;
        }

        $financialNature = trim((string) ($filters['financial_nature'] ?? ''));
        if ($financialNature !== '') {
            $where .= ' AND pb.financial_nature = :financial_nature';
            $params['financial_nature'] = $financialNature;
        }

        $referenceMonth = trim((string) ($filters['reference_month'] ?? ''));
        if ($referenceMonth !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                $where .= ' AND 1 = 0';
            } else {
                $where .= ' AND pb.reference_month >= :reference_month_start
                    AND pb.reference_month < :reference_month_end';
                $params['reference_month_start'] = $referenceRange['start'];
                $params['reference_month_end'] = $referenceRange['end'];
            }
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM payment_batch_items pbi_filter
                INNER JOIN invoices i_filter ON i_filter.id = pbi_filter.invoice_id AND i_filter.deleted_at IS NULL
                WHERE pbi_filter.batch_id = pb.id
                  AND i_filter.organ_id = :organ_id
            )';
            $params['organ_id'] = $organId;
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM payment_batches pb
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                pb.id,
                pb.batch_code,
                pb.title,
                pb.status,
                pb.financial_nature,
                pb.reference_month,
                pb.scheduled_payment_date,
                pb.total_amount,
                pb.payments_count,
                pb.notes,
                pb.created_by,
                pb.closed_by,
                pb.closed_at,
                pb.created_at,
                pb.updated_at,
                u1.name AS created_by_name,
                u2.name AS closed_by_name,
                (
                    SELECT COUNT(DISTINCT i_scope.organ_id)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN invoices i_scope ON i_scope.id = pbi_scope.invoice_id AND i_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS organs_count,
                (
                    SELECT MIN(p_scope.payment_date)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN payments p_scope ON p_scope.id = pbi_scope.payment_id AND p_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS payment_date_from,
                (
                    SELECT MAX(p_scope.payment_date)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN payments p_scope ON p_scope.id = pbi_scope.payment_id AND p_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS payment_date_to
             FROM payment_batches pb
             LEFT JOIN users u1 ON u1.id = pb.created_by
             LEFT JOIN users u2 ON u2.id = pb.closed_by
             {$where}
             ORDER BY {$sortColumn} {$direction}, pb.id DESC
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
    public function findPaymentBatchById(int $batchId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                pb.id,
                pb.batch_code,
                pb.title,
                pb.status,
                pb.financial_nature,
                pb.reference_month,
                pb.scheduled_payment_date,
                pb.total_amount,
                pb.payments_count,
                pb.notes,
                pb.created_by,
                pb.closed_by,
                pb.closed_at,
                pb.created_at,
                pb.updated_at,
                u1.name AS created_by_name,
                u2.name AS closed_by_name,
                (
                    SELECT COUNT(DISTINCT i_scope.organ_id)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN invoices i_scope ON i_scope.id = pbi_scope.invoice_id AND i_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS organs_count,
                (
                    SELECT MIN(p_scope.payment_date)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN payments p_scope ON p_scope.id = pbi_scope.payment_id AND p_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS payment_date_from,
                (
                    SELECT MAX(p_scope.payment_date)
                    FROM payment_batch_items pbi_scope
                    INNER JOIN payments p_scope ON p_scope.id = pbi_scope.payment_id AND p_scope.deleted_at IS NULL
                    WHERE pbi_scope.batch_id = pb.id
                ) AS payment_date_to
             FROM payment_batches pb
             LEFT JOIN users u1 ON u1.id = pb.created_by
             LEFT JOIN users u2 ON u2.id = pb.closed_by
             WHERE pb.id = :id
               AND pb.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $batchId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function paymentBatchItems(int $batchId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                pbi.id,
                pbi.batch_id,
                pbi.payment_id,
                pbi.invoice_id,
                pbi.amount,
                pbi.payment_date,
                p.id AS payment_internal_id,
                p.financial_nature,
                p.process_reference,
                p.proof_original_name,
                p.proof_storage_path,
                p.notes AS payment_notes,
                p.created_at AS payment_created_at,
                i.invoice_number,
                i.reference_month AS invoice_reference_month,
                i.financial_nature AS invoice_financial_nature,
                o.name AS organ_name
             FROM payment_batch_items pbi
             INNER JOIN payments p ON p.id = pbi.payment_id AND p.deleted_at IS NULL
             INNER JOIN invoices i ON i.id = pbi.invoice_id AND i.deleted_at IS NULL
             INNER JOIN organs o ON o.id = i.organ_id
             WHERE pbi.batch_id = :batch_id
             ORDER BY pbi.payment_date ASC, pbi.id ASC'
        );
        $stmt->execute(['batch_id' => $batchId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paymentBatchCandidates(array $filters, int $limit = 220): array
    {
        $where = 'WHERE p.deleted_at IS NULL
            AND i.deleted_at IS NULL
            AND NOT EXISTS (
                SELECT 1
                FROM payment_batch_items pbi
                WHERE pbi.payment_id = p.id
            )';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                i.invoice_number LIKE :q_invoice
                OR i.title LIKE :q_title
                OR o.name LIKE :q_organ
                OR p.process_reference LIKE :q_process
            )';
            $search = '%' . $query . '%';
            $params['q_invoice'] = $search;
            $params['q_title'] = $search;
            $params['q_organ'] = $search;
            $params['q_process'] = $search;
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND i.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $referenceMonth = trim((string) ($filters['reference_month'] ?? ''));
        if ($referenceMonth !== '') {
            $referenceRange = $this->monthRange($referenceMonth);
            if ($referenceRange === null) {
                $where .= ' AND 1 = 0';
            } else {
                $where .= ' AND i.reference_month >= :reference_month_start
                    AND i.reference_month < :reference_month_end';
                $params['reference_month_start'] = $referenceRange['start'];
                $params['reference_month_end'] = $referenceRange['end'];
            }
        }

        $financialNature = trim((string) ($filters['financial_nature'] ?? ''));
        if ($financialNature !== '') {
            $where .= ' AND i.financial_nature = :financial_nature';
            $params['financial_nature'] = $financialNature;
        }

        $paymentDateFrom = trim((string) ($filters['payment_date_from'] ?? ''));
        if ($paymentDateFrom !== '') {
            $where .= ' AND p.payment_date >= :payment_date_from';
            $params['payment_date_from'] = $paymentDateFrom;
        }

        $paymentDateTo = trim((string) ($filters['payment_date_to'] ?? ''));
        if ($paymentDateTo !== '') {
            $where .= ' AND p.payment_date <= :payment_date_to';
            $params['payment_date_to'] = $paymentDateTo;
        }

        $stmt = $this->db->prepare(
            "SELECT
                p.id AS payment_id,
                p.invoice_id,
                p.payment_date,
                p.amount,
                p.financial_nature,
                p.process_reference,
                i.invoice_number,
                i.reference_month AS invoice_reference_month,
                i.financial_nature AS invoice_financial_nature,
                o.id AS organ_id,
                o.name AS organ_name
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             INNER JOIN organs o ON o.id = i.organ_id
             {$where}
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<int, int> $paymentIds
     * @return array<int, array<string, mixed>>
     */
    public function findEligiblePaymentsForBatchByIds(array $paymentIds): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $paymentIds
        ), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':payment_' . $index;
            $placeholders[] = $key;
            $params['payment_' . $index] = $id;
        }

        $sql = 'SELECT
                    p.id AS payment_id,
                    p.invoice_id,
                    p.payment_date,
                    p.amount,
                    p.financial_nature,
                    p.process_reference,
                    i.invoice_number,
                    i.reference_month AS invoice_reference_month,
                    i.financial_nature AS invoice_financial_nature,
                    o.id AS organ_id,
                    o.name AS organ_name
                FROM payments p
                INNER JOIN invoices i ON i.id = p.invoice_id AND i.deleted_at IS NULL
                INNER JOIN organs o ON o.id = i.organ_id
                LEFT JOIN payment_batch_items pbi ON pbi.payment_id = p.id
                WHERE p.deleted_at IS NULL
                  AND pbi.id IS NULL
                  AND p.id IN (' . implode(', ', $placeholders) . ')
                ORDER BY p.id ASC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function availablePeopleForLinking(int $invoiceId, int $limit = 300): array
    {
        $invoice = $this->findById($invoiceId);
        if ($invoice === null) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.status,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.deleted_at IS NULL
               AND p.organ_id = :organ_id
               AND NOT EXISTS (
                    SELECT 1
                    FROM invoice_people ip
                    WHERE ip.invoice_id = :invoice_id
                      AND ip.person_id = p.id
                      AND ip.deleted_at IS NULL
               )
             ORDER BY p.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':organ_id', (int) ($invoice['organ_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
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
    public function findPersonLinkById(int $linkId, int $invoiceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                ip.id,
                ip.invoice_id,
                ip.person_id,
                ip.allocated_amount,
                ip.paid_amount,
                ip.notes,
                ip.created_by,
                ip.created_at,
                ip.updated_at,
                p.name AS person_name,
                p.organ_id AS person_organ_id
             FROM invoice_people ip
             INNER JOIN people p
               ON p.id = ip.person_id
              AND p.deleted_at IS NULL
             WHERE ip.id = :id
               AND ip.invoice_id = :invoice_id
               AND ip.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $linkId,
            'invoice_id' => $invoiceId,
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

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoices (
                organ_id,
                invoice_number,
                title,
                reference_month,
                issue_date,
                due_date,
                total_amount,
                paid_amount,
                status,
                financial_nature,
                digitable_line,
                reference_code,
                pdf_original_name,
                pdf_stored_name,
                pdf_mime_type,
                pdf_file_size,
                pdf_storage_path,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :organ_id,
                :invoice_number,
                :title,
                :reference_month,
                :issue_date,
                :due_date,
                :total_amount,
                :paid_amount,
                :status,
                :financial_nature,
                :digitable_line,
                :reference_code,
                :pdf_original_name,
                :pdf_stored_name,
                :pdf_mime_type,
                :pdf_file_size,
                :pdf_storage_path,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'organ_id' => $data['organ_id'],
            'invoice_number' => $data['invoice_number'],
            'title' => $data['title'],
            'reference_month' => $data['reference_month'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'total_amount' => $data['total_amount'],
            'paid_amount' => $data['paid_amount'] ?? '0.00',
            'status' => $data['status'],
            'financial_nature' => $data['financial_nature'] ?? 'despesa_reembolso',
            'digitable_line' => $data['digitable_line'],
            'reference_code' => $data['reference_code'],
            'pdf_original_name' => $data['pdf_original_name'],
            'pdf_stored_name' => $data['pdf_stored_name'],
            'pdf_mime_type' => $data['pdf_mime_type'],
            'pdf_file_size' => $data['pdf_file_size'],
            'pdf_storage_path' => $data['pdf_storage_path'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices
             SET
                organ_id = :organ_id,
                invoice_number = :invoice_number,
                title = :title,
                reference_month = :reference_month,
                issue_date = :issue_date,
                due_date = :due_date,
                total_amount = :total_amount,
                status = :status,
                financial_nature = :financial_nature,
                digitable_line = :digitable_line,
                reference_code = :reference_code,
                pdf_original_name = :pdf_original_name,
                pdf_stored_name = :pdf_stored_name,
                pdf_mime_type = :pdf_mime_type,
                pdf_file_size = :pdf_file_size,
                pdf_storage_path = :pdf_storage_path,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'organ_id' => $data['organ_id'],
            'invoice_number' => $data['invoice_number'],
            'title' => $data['title'],
            'reference_month' => $data['reference_month'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'total_amount' => $data['total_amount'],
            'status' => $data['status'],
            'financial_nature' => $data['financial_nature'] ?? 'despesa_reembolso',
            'digitable_line' => $data['digitable_line'],
            'reference_code' => $data['reference_code'],
            'pdf_original_name' => $data['pdf_original_name'],
            'pdf_stored_name' => $data['pdf_stored_name'],
            'pdf_mime_type' => $data['pdf_mime_type'],
            'pdf_file_size' => $data['pdf_file_size'],
            'pdf_storage_path' => $data['pdf_storage_path'],
            'notes' => $data['notes'],
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices
             SET status = :status, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function softDeleteLinksByInvoice(int $invoiceId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice_people
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE invoice_id = :invoice_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['invoice_id' => $invoiceId]);
    }

    public function softDeletePaymentPeopleByInvoice(int $invoiceId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE payment_people
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE invoice_id = :invoice_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['invoice_id' => $invoiceId]);
    }

    public function softDeletePaymentsByInvoice(int $invoiceId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE payments
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE invoice_id = :invoice_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['invoice_id' => $invoiceId]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function createPersonLink(int $invoiceId, int $personId, string $allocatedAmount, ?string $notes, ?int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoice_people (
                invoice_id,
                person_id,
                allocated_amount,
                paid_amount,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :invoice_id,
                :person_id,
                :allocated_amount,
                0,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'invoice_id' => $invoiceId,
            'person_id' => $personId,
            'allocated_amount' => $allocatedAmount,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createPaymentBatch(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payment_batches (
                batch_code,
                title,
                status,
                financial_nature,
                reference_month,
                scheduled_payment_date,
                total_amount,
                payments_count,
                notes,
                created_by,
                closed_by,
                closed_at,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :batch_code,
                :title,
                :status,
                :financial_nature,
                :reference_month,
                :scheduled_payment_date,
                :total_amount,
                :payments_count,
                :notes,
                :created_by,
                :closed_by,
                :closed_at,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'batch_code' => $data['batch_code'],
            'title' => $data['title'],
            'status' => $data['status'],
            'financial_nature' => $data['financial_nature'] ?? 'despesa_reembolso',
            'reference_month' => $data['reference_month'],
            'scheduled_payment_date' => $data['scheduled_payment_date'],
            'total_amount' => $data['total_amount'],
            'payments_count' => $data['payments_count'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
            'closed_by' => $data['closed_by'],
            'closed_at' => $data['closed_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addPaymentToBatch(int $batchId, int $paymentId, int $invoiceId, string $amount, string $paymentDate): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payment_batch_items (
                batch_id,
                payment_id,
                invoice_id,
                amount,
                payment_date,
                created_at,
                updated_at
            ) VALUES (
                :batch_id,
                :payment_id,
                :invoice_id,
                :amount,
                :payment_date,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'batch_id' => $batchId,
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePaymentBatchStatus(
        int $batchId,
        string $status,
        ?string $notes,
        ?int $closedBy,
        ?string $closedAt
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE payment_batches
             SET status = :status,
                 notes = :notes,
                 closed_by = :closed_by,
                 closed_at = :closed_at,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $batchId,
            'status' => $status,
            'notes' => $notes,
            'closed_by' => $closedBy,
            'closed_at' => $closedAt,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLinksForPayment(int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                invoice_id,
                person_id,
                allocated_amount,
                paid_amount
             FROM invoice_people
             WHERE invoice_id = :invoice_id
               AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function createPayment(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (
                invoice_id,
                payment_date,
                amount,
                financial_nature,
                process_reference,
                proof_original_name,
                proof_stored_name,
                proof_mime_type,
                proof_file_size,
                proof_storage_path,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :invoice_id,
                :payment_date,
                :amount,
                :financial_nature,
                :process_reference,
                :proof_original_name,
                :proof_stored_name,
                :proof_mime_type,
                :proof_file_size,
                :proof_storage_path,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'invoice_id' => $data['invoice_id'],
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'financial_nature' => $data['financial_nature'] ?? 'despesa_reembolso',
            'process_reference' => $data['process_reference'],
            'proof_original_name' => $data['proof_original_name'],
            'proof_stored_name' => $data['proof_stored_name'],
            'proof_mime_type' => $data['proof_mime_type'],
            'proof_file_size' => $data['proof_file_size'],
            'proof_storage_path' => $data['proof_storage_path'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function incrementPersonLinkPaidAmount(int $invoicePersonId, string $amount): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice_people
             SET paid_amount = LEAST(allocated_amount, paid_amount + :amount), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $invoicePersonId,
            'amount' => $amount,
        ]);
    }

    public function createPaymentPersonAllocation(int $paymentId, int $invoiceId, int $invoicePersonId, int $personId, string $amount): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payment_people (
                payment_id,
                invoice_id,
                invoice_person_id,
                person_id,
                allocated_amount,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :payment_id,
                :invoice_id,
                :invoice_person_id,
                :person_id,
                :allocated_amount,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'invoice_person_id' => $invoicePersonId,
            'person_id' => $personId,
            'allocated_amount' => $amount,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function sumPaymentsByInvoice(int $invoiceId): string
    {
        $stmt = $this->db->prepare(
            'SELECT IFNULL(SUM(amount), 0) AS total_paid
             FROM payments
             WHERE invoice_id = :invoice_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);
        $total = (float) ($stmt->fetch()['total_paid'] ?? 0);

        return number_format($total, 2, '.', '');
    }

    public function updateInvoicePaidAmountAndStatus(int $invoiceId, string $paidAmount, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices
             SET
                paid_amount = :paid_amount,
                status = :status,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $invoiceId,
            'paid_amount' => $paidAmount,
            'status' => $status,
        ]);
    }

    public function softDeletePersonLink(int $linkId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice_people
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $linkId]);
    }

    public function activeLinkExists(int $invoiceId, int $personId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM invoice_people
             WHERE invoice_id = :invoice_id
               AND person_id = :person_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'invoice_id' => $invoiceId,
            'person_id' => $personId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function invoiceNumberExists(string $invoiceNumber, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM invoices WHERE invoice_number = :invoice_number AND deleted_at IS NULL LIMIT 1';
        $params = ['invoice_number' => $invoiceNumber];

        if ($ignoreId !== null) {
            $sql = 'SELECT id
                    FROM invoices
                    WHERE invoice_number = :invoice_number
                      AND id <> :id
                      AND deleted_at IS NULL
                    LIMIT 1';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function personBelongsToOrgan(int $personId, int $organId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM people
             WHERE id = :id
               AND organ_id = :organ_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $personId,
            'organ_id' => $organId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function organExists(int $organId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM organs
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $organId]);

        return $stmt->fetch() !== false;
    }

    /** @return array<string, mixed>|null */
    public function findPdfById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                invoice_number,
                pdf_original_name,
                pdf_mime_type,
                pdf_storage_path
             FROM invoices
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if (trim((string) ($row['pdf_storage_path'] ?? '')) === '') {
            return null;
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findPaymentProofById(int $paymentId, ?int $invoiceId = null): ?array
    {
        $sql = 'SELECT
                    p.id,
                    p.invoice_id,
                    p.payment_date,
                    p.amount,
                    p.proof_original_name,
                    p.proof_mime_type,
                    p.proof_storage_path,
                    i.invoice_number
                FROM payments p
                INNER JOIN invoices i ON i.id = p.invoice_id AND i.deleted_at IS NULL
                WHERE p.id = :id
                  AND p.deleted_at IS NULL';
        $params = ['id' => $paymentId];

        if ($invoiceId !== null) {
            $sql .= ' AND p.invoice_id = :invoice_id';
            $params['invoice_id'] = $invoiceId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if (trim((string) ($row['proof_storage_path'] ?? '')) === '') {
            return null;
        }

        return $row;
    }
}
