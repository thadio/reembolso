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
            $where .= ' AND DATE_FORMAT(i.reference_month, "%Y-%m") = :reference_month';
            $params['reference_month'] = $referenceMonth;
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
