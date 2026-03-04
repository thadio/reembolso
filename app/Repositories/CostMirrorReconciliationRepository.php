<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CostMirrorReconciliationRepository
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

    /** @return array<string, mixed>|null */
    public function findMirrorById(int $mirrorId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cm.id,
                cm.person_id,
                cm.organ_id,
                cm.invoice_id,
                cm.reference_month,
                cm.title,
                cm.status,
                cm.total_amount,
                cm.created_at,
                cm.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                o.name AS organ_name,
                i.invoice_number
             FROM cost_mirrors cm
             INNER JOIN people p ON p.id = cm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = cm.organ_id
             LEFT JOIN invoices i ON i.id = cm.invoice_id AND i.deleted_at IS NULL
             WHERE cm.id = :id
               AND cm.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $mirrorId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function mirrorItems(int $mirrorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                cost_mirror_id,
                item_name,
                item_code,
                amount,
                quantity,
                unit_amount,
                notes,
                created_at
             FROM cost_mirror_items
             WHERE cost_mirror_id = :cost_mirror_id
               AND deleted_at IS NULL
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['cost_mirror_id' => $mirrorId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activePlanItemsByPerson(int $personId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.cost_plan_id,
                i.person_id,
                i.item_name,
                i.cost_type,
                i.amount,
                i.start_date,
                i.end_date,
                i.created_at
             FROM cost_plan_items i
             INNER JOIN cost_plans cp
                ON cp.id = i.cost_plan_id
               AND cp.person_id = :person_id_plan
               AND cp.deleted_at IS NULL
               AND cp.is_active = 1
             WHERE i.person_id = :person_id_item
               AND i.deleted_at IS NULL
             ORDER BY i.created_at ASC, i.id ASC'
        );
        $stmt->execute([
            'person_id_plan' => $personId,
            'person_id_item' => $personId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findReconciliationByMirror(int $mirrorId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.cost_mirror_id,
                r.person_id,
                r.reference_month,
                r.compared_at,
                r.compared_by,
                r.divergences_total,
                r.high_severity_total,
                r.status,
                r.lock_editing,
                r.approval_notes,
                r.approved_by,
                r.approved_at,
                r.created_at,
                r.updated_at,
                u1.name AS compared_by_name,
                u2.name AS approved_by_name
             FROM cost_mirror_reconciliations r
             LEFT JOIN users u1 ON u1.id = r.compared_by
             LEFT JOIN users u2 ON u2.id = r.approved_by
             WHERE r.cost_mirror_id = :cost_mirror_id
               AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['cost_mirror_id' => $mirrorId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertReconciliation(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_mirror_reconciliations (
                cost_mirror_id,
                person_id,
                reference_month,
                compared_at,
                compared_by,
                divergences_total,
                high_severity_total,
                status,
                lock_editing,
                approval_notes,
                approved_by,
                approved_at,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :cost_mirror_id,
                :person_id,
                :reference_month,
                :compared_at,
                :compared_by,
                :divergences_total,
                :high_severity_total,
                :status,
                :lock_editing,
                :approval_notes,
                :approved_by,
                :approved_at,
                NOW(),
                NOW(),
                NULL
            )
            ON DUPLICATE KEY UPDATE
                person_id = VALUES(person_id),
                reference_month = VALUES(reference_month),
                compared_at = VALUES(compared_at),
                compared_by = VALUES(compared_by),
                divergences_total = VALUES(divergences_total),
                high_severity_total = VALUES(high_severity_total),
                status = VALUES(status),
                lock_editing = VALUES(lock_editing),
                approval_notes = VALUES(approval_notes),
                approved_by = VALUES(approved_by),
                approved_at = VALUES(approved_at),
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'cost_mirror_id' => $data['cost_mirror_id'],
            'person_id' => $data['person_id'],
            'reference_month' => $data['reference_month'],
            'compared_at' => $data['compared_at'],
            'compared_by' => $data['compared_by'],
            'divergences_total' => $data['divergences_total'],
            'high_severity_total' => $data['high_severity_total'],
            'status' => $data['status'],
            'lock_editing' => $data['lock_editing'],
            'approval_notes' => $data['approval_notes'],
            'approved_by' => $data['approved_by'],
            'approved_at' => $data['approved_at'],
        ]);

        $row = $this->findReconciliationByMirror((int) $data['cost_mirror_id']);

        return (int) ($row['id'] ?? 0);
    }

    public function softDeleteDivergencesByReconciliation(int $reconciliationId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirror_divergences
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE reconciliation_id = :reconciliation_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['reconciliation_id' => $reconciliationId]);
    }

    /** @param array<string, mixed> $data */
    public function createDivergence(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_mirror_divergences (
                reconciliation_id,
                cost_mirror_id,
                person_id,
                cost_plan_item_id,
                mirror_item_id,
                match_key,
                divergence_type,
                severity,
                expected_amount,
                actual_amount,
                difference_amount,
                threshold_amount,
                requires_justification,
                justification_text,
                justification_by,
                justified_at,
                is_resolved,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :reconciliation_id,
                :cost_mirror_id,
                :person_id,
                :cost_plan_item_id,
                :mirror_item_id,
                :match_key,
                :divergence_type,
                :severity,
                :expected_amount,
                :actual_amount,
                :difference_amount,
                :threshold_amount,
                :requires_justification,
                :justification_text,
                :justification_by,
                :justified_at,
                :is_resolved,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'reconciliation_id' => $data['reconciliation_id'],
            'cost_mirror_id' => $data['cost_mirror_id'],
            'person_id' => $data['person_id'],
            'cost_plan_item_id' => $data['cost_plan_item_id'],
            'mirror_item_id' => $data['mirror_item_id'],
            'match_key' => $data['match_key'],
            'divergence_type' => $data['divergence_type'],
            'severity' => $data['severity'],
            'expected_amount' => $data['expected_amount'],
            'actual_amount' => $data['actual_amount'],
            'difference_amount' => $data['difference_amount'],
            'threshold_amount' => $data['threshold_amount'],
            'requires_justification' => $data['requires_justification'],
            'justification_text' => $data['justification_text'],
            'justification_by' => $data['justification_by'],
            'justified_at' => $data['justified_at'],
            'is_resolved' => $data['is_resolved'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function divergencesByReconciliation(int $reconciliationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.reconciliation_id,
                d.cost_mirror_id,
                d.person_id,
                d.cost_plan_item_id,
                d.mirror_item_id,
                d.match_key,
                d.divergence_type,
                d.severity,
                d.expected_amount,
                d.actual_amount,
                d.difference_amount,
                d.threshold_amount,
                d.requires_justification,
                d.justification_text,
                d.justification_by,
                d.justified_at,
                d.is_resolved,
                d.created_by,
                d.created_at,
                d.updated_at,
                u1.name AS justification_by_name,
                u2.name AS created_by_name
             FROM cost_mirror_divergences d
             LEFT JOIN users u1 ON u1.id = d.justification_by
             LEFT JOIN users u2 ON u2.id = d.created_by
             WHERE d.reconciliation_id = :reconciliation_id
               AND d.deleted_at IS NULL
             ORDER BY
               CASE d.severity WHEN "alta" THEN 3 WHEN "media" THEN 2 WHEN "baixa" THEN 1 ELSE 0 END DESC,
               ABS(d.difference_amount) DESC,
               d.id ASC'
        );
        $stmt->execute(['reconciliation_id' => $reconciliationId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findDivergenceById(int $divergenceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.reconciliation_id,
                d.cost_mirror_id,
                d.person_id,
                d.match_key,
                d.divergence_type,
                d.severity,
                d.expected_amount,
                d.actual_amount,
                d.difference_amount,
                d.requires_justification,
                d.justification_text,
                d.justification_by,
                d.justified_at,
                d.is_resolved,
                r.status AS reconciliation_status,
                r.lock_editing
             FROM cost_mirror_divergences d
             INNER JOIN cost_mirror_reconciliations r ON r.id = d.reconciliation_id AND r.deleted_at IS NULL
             WHERE d.id = :id
               AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $divergenceId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function justifyDivergence(int $divergenceId, string $justification, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirror_divergences
             SET
                justification_text = :justification_text,
                justification_by = :justification_by,
                justified_at = NOW(),
                is_resolved = 1,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $divergenceId,
            'justification_text' => $justification,
            'justification_by' => $userId,
        ]);
    }

    public function hasPendingRequiredJustifications(int $reconciliationId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM cost_mirror_divergences
             WHERE reconciliation_id = :reconciliation_id
               AND deleted_at IS NULL
               AND requires_justification = 1
               AND is_resolved = 0
             LIMIT 1'
        );
        $stmt->execute(['reconciliation_id' => $reconciliationId]);

        return $stmt->fetch() !== false;
    }

    public function approveReconciliation(int $reconciliationId, int $userId, ?string $notes): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirror_reconciliations
             SET
                status = "aprovado",
                lock_editing = 1,
                approval_notes = :approval_notes,
                approved_by = :approved_by,
                approved_at = NOW(),
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $reconciliationId,
            'approval_notes' => $notes,
            'approved_by' => $userId,
        ]);
    }

    public function markMirrorStatus(int $mirrorId, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_mirrors
             SET status = :status, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $mirrorId,
            'status' => $status,
        ]);
    }

    public function isMirrorLocked(int $mirrorId): bool
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
