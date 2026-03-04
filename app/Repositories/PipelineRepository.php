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

    /** @return array<int, array<string, mixed>> */
    public function activeTimelineEventTypes(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description
             FROM timeline_event_types
             WHERE is_active = 1
             ORDER BY name ASC'
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
        ?string $eventDate = null,
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
                :event_date,
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
            'event_date' => $eventDate ?? date('Y-m-d H:i:s'),
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findTimelineEventById(int $eventId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, person_id, assignment_id, event_type, title, description, event_date, metadata, created_by, created_at
             FROM timeline_events
             WHERE id = :id AND person_id = :person_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $eventId,
            'person_id' => $personId,
        ]);
        $event = $stmt->fetch();

        return $event === false ? null : $event;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function timelinePaginateByPerson(int $personId, int $page, int $perPage): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) AS total FROM timeline_events WHERE person_id = :person_id');
        $countStmt->execute(['person_id' => $personId]);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

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
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $eventIds = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $attachmentsByEvent = $this->attachmentsByEventIds($eventIds);

        foreach ($items as &$item) {
            $eventId = (int) ($item['id'] ?? 0);
            $item['attachments'] = $attachmentsByEvent[$eventId] ?? [];
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    public function createAttachment(
        int $eventId,
        int $personId,
        string $originalName,
        string $storedName,
        string $mimeType,
        int $fileSize,
        string $storagePath,
        ?int $uploadedBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO timeline_event_attachments (
                timeline_event_id,
                person_id,
                original_name,
                stored_name,
                mime_type,
                file_size,
                storage_path,
                uploaded_by,
                created_at
             ) VALUES (
                :timeline_event_id,
                :person_id,
                :original_name,
                :stored_name,
                :mime_type,
                :file_size,
                :storage_path,
                :uploaded_by,
                NOW()
             )'
        );

        $stmt->execute([
            'timeline_event_id' => $eventId,
            'person_id' => $personId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'storage_path' => $storagePath,
            'uploaded_by' => $uploadedBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<int, int> $eventIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function attachmentsByEventIds(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT
                id,
                timeline_event_id,
                person_id,
                original_name,
                stored_name,
                mime_type,
                file_size,
                storage_path,
                uploaded_by,
                created_at
             FROM timeline_event_attachments
             WHERE timeline_event_id IN ({$placeholders})
             ORDER BY id ASC"
        );

        foreach ($eventIds as $index => $eventId) {
            $stmt->bindValue($index + 1, $eventId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $eventId = (int) ($row['timeline_event_id'] ?? 0);
            if (!isset($grouped[$eventId])) {
                $grouped[$eventId] = [];
            }

            $grouped[$eventId][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    public function findAttachmentById(int $attachmentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                a.id,
                a.timeline_event_id,
                a.person_id,
                a.original_name,
                a.stored_name,
                a.mime_type,
                a.file_size,
                a.storage_path,
                a.uploaded_by,
                a.created_at,
                t.person_id AS event_person_id
             FROM timeline_event_attachments a
             INNER JOIN timeline_events t ON t.id = a.timeline_event_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $attachmentId]);
        $attachment = $stmt->fetch();

        return $attachment === false ? null : $attachment;
    }

    /** @return array<int, array<string, mixed>> */
    public function fullTimelineByPerson(int $personId, int $limit = 300): array
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
        $items = $stmt->fetchAll();

        $eventIds = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $attachmentsByEvent = $this->attachmentsByEventIds($eventIds);

        foreach ($items as &$item) {
            $eventId = (int) ($item['id'] ?? 0);
            $item['attachments'] = $attachmentsByEvent[$eventId] ?? [];
        }
        unset($item);

        return $items;
    }
}
