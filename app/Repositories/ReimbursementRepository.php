<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReimbursementRepository
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

    /** @return array<string, mixed> */
    public function summaryByPerson(int $personId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_entries,
                IFNULL(SUM(CASE WHEN r.status = "pendente" THEN r.amount ELSE 0 END), 0) AS pending_total,
                IFNULL(SUM(CASE WHEN r.status = "pago" THEN r.amount ELSE 0 END), 0) AS paid_total,
                IFNULL(SUM(CASE WHEN r.status = "cancelado" THEN r.amount ELSE 0 END), 0) AS canceled_total,
                IFNULL(SUM(CASE WHEN r.status = "pendente" AND r.due_date IS NOT NULL AND r.due_date < CURDATE() THEN r.amount ELSE 0 END), 0) AS overdue_total,
                IFNULL(SUM(CASE WHEN r.status = "pendente" THEN 1 ELSE 0 END), 0) AS pending_count,
                IFNULL(SUM(CASE WHEN r.status = "pago" THEN 1 ELSE 0 END), 0) AS paid_count,
                IFNULL(SUM(CASE WHEN r.status = "cancelado" THEN 1 ELSE 0 END), 0) AS canceled_count,
                IFNULL(SUM(CASE WHEN r.status = "pendente" AND r.due_date IS NOT NULL AND r.due_date < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue_count,
                IFNULL(SUM(CASE WHEN r.entry_type = "boleto" THEN 1 ELSE 0 END), 0) AS boletos_count,
                IFNULL(SUM(CASE WHEN r.entry_type = "pagamento" THEN 1 ELSE 0 END), 0) AS payments_count,
                IFNULL(SUM(CASE WHEN r.entry_type = "ajuste" THEN 1 ELSE 0 END), 0) AS adjustments_count
             FROM reimbursement_entries r
             WHERE r.person_id = :person_id
               AND r.deleted_at IS NULL'
        );
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listByPerson(int $personId, int $limit = 80): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.person_id,
                r.assignment_id,
                r.entry_type,
                r.status,
                r.title,
                r.amount,
                r.reference_month,
                r.due_date,
                r.paid_at,
                r.notes,
                r.created_by,
                r.created_at,
                r.updated_at,
                u.name AS created_by_name
             FROM reimbursement_entries r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.person_id = :person_id
               AND r.deleted_at IS NULL
             ORDER BY
               CASE WHEN r.status = "pendente" THEN 0 WHEN r.status = "pago" THEN 1 ELSE 2 END ASC,
               r.due_date IS NULL ASC,
               r.due_date ASC,
               r.created_at DESC,
               r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForPerson(int $entryId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.person_id,
                r.assignment_id,
                r.entry_type,
                r.status,
                r.title,
                r.amount,
                r.reference_month,
                r.due_date,
                r.paid_at,
                r.notes,
                r.created_by,
                r.created_at,
                r.updated_at,
                u.name AS created_by_name
             FROM reimbursement_entries r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.id = :id
               AND r.person_id = :person_id
               AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $entryId,
            'person_id' => $personId,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function createEntry(
        int $personId,
        ?int $assignmentId,
        string $entryType,
        string $status,
        string $title,
        string $amount,
        ?string $referenceMonth,
        ?string $dueDate,
        ?string $paidAt,
        ?string $notes,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO reimbursement_entries (
                person_id,
                assignment_id,
                entry_type,
                status,
                title,
                amount,
                reference_month,
                due_date,
                paid_at,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :assignment_id,
                :entry_type,
                :status,
                :title,
                :amount,
                :reference_month,
                :due_date,
                :paid_at,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'person_id' => $personId,
            'assignment_id' => $assignmentId,
            'entry_type' => $entryType,
            'status' => $status,
            'title' => $title,
            'amount' => $amount,
            'reference_month' => $referenceMonth,
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markAsPaid(int $entryId, string $paidAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reimbursement_entries
             SET status = "pago",
                 paid_at = :paid_at,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $entryId,
            'paid_at' => $paidAt,
        ]);
    }
}
