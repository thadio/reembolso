<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProcessAdminTimelineRepository
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

    public function assignmentBelongsToPerson(int $assignmentId, int $personId): bool
    {
        if ($assignmentId <= 0 || $personId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM assignments
             WHERE id = :id
               AND person_id = :person_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $assignmentId,
            'person_id' => $personId,
        ]);

        return $stmt->fetch() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    public function manualNotesByPerson(int $personId, int $limit = 150): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                n.id,
                n.person_id,
                n.assignment_id,
                n.title,
                n.description,
                n.status,
                n.severity,
                n.is_pinned,
                n.event_at,
                n.created_by,
                n.updated_by,
                n.created_at,
                n.updated_at,
                uc.name AS created_by_name,
                uu.name AS updated_by_name
             FROM process_admin_timeline_notes n
             LEFT JOIN users uc ON uc.id = n.created_by
             LEFT JOIN users uu ON uu.id = n.updated_by
             WHERE n.person_id = :person_id
               AND n.deleted_at IS NULL
             ORDER BY n.is_pinned DESC, n.event_at DESC, n.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(400, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findManualNoteByIdForPerson(int $noteId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                n.id,
                n.person_id,
                n.assignment_id,
                n.title,
                n.description,
                n.status,
                n.severity,
                n.is_pinned,
                n.event_at,
                n.created_by,
                n.updated_by,
                n.created_at,
                n.updated_at
             FROM process_admin_timeline_notes n
             WHERE n.id = :id
               AND n.person_id = :person_id
               AND n.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $noteId,
            'person_id' => $personId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createManualNote(
        int $personId,
        ?int $assignmentId,
        string $title,
        ?string $description,
        string $status,
        string $severity,
        bool $isPinned,
        string $eventAt,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO process_admin_timeline_notes (
                person_id,
                assignment_id,
                title,
                description,
                status,
                severity,
                is_pinned,
                event_at,
                created_by,
                updated_by,
                deleted_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :assignment_id,
                :title,
                :description,
                :status,
                :severity,
                :is_pinned,
                :event_at,
                :created_by,
                :updated_by,
                NULL,
                NOW(),
                NOW(),
                NULL
             )'
        );
        $stmt->execute([
            'person_id' => $personId,
            'assignment_id' => $assignmentId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'severity' => $severity,
            'is_pinned' => $isPinned ? 1 : 0,
            'event_at' => $eventAt,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateManualNote(
        int $noteId,
        string $title,
        ?string $description,
        string $status,
        string $severity,
        bool $isPinned,
        string $eventAt,
        ?int $updatedBy
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE process_admin_timeline_notes
             SET title = :title,
                 description = :description,
                 status = :status,
                 severity = :severity,
                 is_pinned = :is_pinned,
                 event_at = :event_at,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $noteId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'severity' => $severity,
            'is_pinned' => $isPinned ? 1 : 0,
            'event_at' => $eventAt,
            'updated_by' => $updatedBy,
        ]);
    }

    public function softDeleteManualNote(int $noteId, ?int $deletedBy): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE process_admin_timeline_notes
             SET deleted_by = :deleted_by,
                 deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $noteId,
            'deleted_by' => $deletedBy,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function processCommentsByPerson(int $personId, int $limit = 160): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.person_id,
                c.assignment_id,
                c.comment_text,
                c.status,
                c.is_pinned,
                c.created_by,
                c.updated_by,
                c.created_at,
                c.updated_at,
                uc.name AS created_by_name,
                uu.name AS updated_by_name
             FROM process_comments c
             LEFT JOIN users uc ON uc.id = c.created_by
             LEFT JOIN users uu ON uu.id = c.updated_by
             WHERE c.person_id = :person_id
               AND c.deleted_at IS NULL
             ORDER BY c.is_pinned DESC, c.created_at DESC, c.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingItemsByPerson(int $personId, int $limit = 180): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.person_id,
                p.assignment_id,
                p.pending_type,
                p.title,
                p.description,
                p.severity,
                p.status,
                p.due_date,
                p.created_by,
                p.resolved_by,
                p.created_at,
                p.updated_at,
                uc.name AS created_by_name,
                ur.name AS resolved_by_name
             FROM analyst_pending_items p
             LEFT JOIN users uc ON uc.id = p.created_by
             LEFT JOIN users ur ON ur.id = p.resolved_by
             WHERE p.person_id = :person_id
               AND p.deleted_at IS NULL
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function reimbursementsByPerson(int $personId, int $limit = 200): array
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
                r.created_by,
                r.created_at,
                r.updated_at,
                u.name AS created_by_name
             FROM reimbursement_entries r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.person_id = :person_id
               AND r.deleted_at IS NULL
             ORDER BY r.updated_at DESC, r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function processMetadataByPerson(int $personId, int $limit = 40): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                pm.id,
                pm.person_id,
                pm.office_number,
                pm.office_sent_at,
                pm.office_channel,
                pm.office_protocol,
                pm.dou_edition,
                pm.dou_published_at,
                pm.mte_entry_date,
                pm.notes,
                pm.created_by,
                pm.created_at,
                pm.updated_at,
                uc.name AS created_by_name
             FROM process_metadata pm
             LEFT JOIN users uc ON uc.id = pm.created_by
             WHERE pm.person_id = :person_id
               AND pm.deleted_at IS NULL
             ORDER BY pm.updated_at DESC, pm.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function operationalTimelineByPerson(int $personId, int $limit = 240): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                t.id,
                t.person_id,
                t.assignment_id,
                t.event_type,
                t.title,
                t.description,
                t.event_date,
                t.created_by,
                t.created_at,
                u.name AS created_by_name
             FROM timeline_events t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.person_id = :person_id
             ORDER BY t.event_date DESC, t.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(600, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
