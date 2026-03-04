<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PipelineRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function initialStatus(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, label, sort_order, next_action_label, event_type
             FROM assignment_statuses
             WHERE code = :code AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['code' => 'interessado']);

        $status = $stmt->fetch();

        return $status === false ? null : $status;
    }

    /** @return array<string, mixed>|null */
    public function nextStatus(int $sortOrder): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, label, sort_order, next_action_label, event_type
             FROM assignment_statuses
             WHERE is_active = 1 AND sort_order > :sort_order
             ORDER BY sort_order ASC
             LIMIT 1'
        );
        $stmt->execute(['sort_order' => $sortOrder]);

        $status = $stmt->fetch();

        return $status === false ? null : $status;
    }

    /** @return array<int, array<string, mixed>> */
    public function allStatuses(): array
    {
        $stmt = $this->db->query(
            'SELECT id, code, label, sort_order, next_action_label, event_type
             FROM assignment_statuses
             WHERE is_active = 1
             ORDER BY sort_order ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function assignmentByPersonId(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                a.id,
                a.person_id,
                a.modality_id,
                a.mte_unit,
                a.target_start_date,
                a.effective_start_date,
                a.current_status_id,
                a.created_at,
                a.updated_at,
                s.code AS current_status_code,
                s.label AS current_status_label,
                s.sort_order AS current_status_order,
                s.next_action_label AS current_next_action_label,
                s.event_type AS current_event_type,
                m.name AS modality_name
             FROM assignments a
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN modalities m ON m.id = a.modality_id
             WHERE a.person_id = :person_id AND a.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $assignment = $stmt->fetch();

        return $assignment === false ? null : $assignment;
    }

    public function createAssignment(int $personId, ?int $modalityId, int $statusId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO assignments (
                person_id,
                modality_id,
                mte_unit,
                target_start_date,
                effective_start_date,
                current_status_id,
                created_at,
                updated_at
            ) VALUES (
                :person_id,
                :modality_id,
                NULL,
                NULL,
                NULL,
                :current_status_id,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'person_id' => $personId,
            'modality_id' => $modalityId,
            'current_status_id' => $statusId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateAssignmentModality(int $assignmentId, ?int $modalityId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET modality_id = :modality_id, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'modality_id' => $modalityId,
        ]);
    }

    public function updateAssignmentStatus(int $assignmentId, int $statusId, ?string $effectiveStartDate): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET current_status_id = :status_id,
                 effective_start_date = COALESCE(:effective_start_date, effective_start_date),
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'status_id' => $statusId,
            'effective_start_date' => $effectiveStartDate,
        ]);
    }

    public function updatePersonStatus(int $personId, string $statusCode): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE people
             SET status = :status, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $personId,
            'status' => $statusCode,
        ]);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function insertTimelineEvent(
        int $personId,
        ?int $assignmentId,
        string $eventType,
        string $title,
        ?string $description,
        ?int $createdBy,
        ?array $metadata = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO timeline_events (
                person_id,
                assignment_id,
                event_type,
                title,
                description,
                event_date,
                metadata,
                created_by,
                created_at
             ) VALUES (
                :person_id,
                :assignment_id,
                :event_type,
                :title,
                :description,
                NOW(),
                :metadata,
                :created_by,
                NOW()
             )'
        );

        $stmt->execute([
            'person_id' => $personId,
            'assignment_id' => $assignmentId,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function timelineByPerson(int $personId, int $limit = 30): array
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
                t.metadata,
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
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
