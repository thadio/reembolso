<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProcessCommentRepository
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

    /** @return array<string, mixed> */
    public function summaryByPerson(int $personId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_comments,
                IFNULL(SUM(CASE WHEN pc.status = "aberto" THEN 1 ELSE 0 END), 0) AS open_count,
                IFNULL(SUM(CASE WHEN pc.status = "arquivado" THEN 1 ELSE 0 END), 0) AS archived_count,
                IFNULL(SUM(CASE WHEN pc.is_pinned = 1 THEN 1 ELSE 0 END), 0) AS pinned_count
             FROM process_comments pc
             WHERE pc.person_id = :person_id
               AND pc.deleted_at IS NULL'
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
                pc.id,
                pc.person_id,
                pc.assignment_id,
                pc.comment_text,
                pc.status,
                pc.is_pinned,
                pc.created_by,
                pc.updated_by,
                pc.created_at,
                pc.updated_at,
                uc.name AS created_by_name,
                uu.name AS updated_by_name
             FROM process_comments pc
             LEFT JOIN users uc ON uc.id = pc.created_by
             LEFT JOIN users uu ON uu.id = pc.updated_by
             WHERE pc.person_id = :person_id
               AND pc.deleted_at IS NULL
             ORDER BY pc.is_pinned DESC, pc.created_at DESC, pc.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForPerson(int $commentId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                pc.id,
                pc.person_id,
                pc.assignment_id,
                pc.comment_text,
                pc.status,
                pc.is_pinned,
                pc.created_by,
                pc.updated_by,
                pc.deleted_by,
                pc.created_at,
                pc.updated_at,
                pc.deleted_at
             FROM process_comments pc
             WHERE pc.id = :id
               AND pc.person_id = :person_id
               AND pc.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $commentId,
            'person_id' => $personId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createComment(
        int $personId,
        ?int $assignmentId,
        string $commentText,
        string $status,
        bool $isPinned,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO process_comments (
                person_id,
                assignment_id,
                comment_text,
                status,
                is_pinned,
                created_by,
                updated_by,
                deleted_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :assignment_id,
                :comment_text,
                :status,
                :is_pinned,
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
            'comment_text' => $commentText,
            'status' => $status,
            'is_pinned' => $isPinned ? 1 : 0,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateComment(
        int $commentId,
        string $commentText,
        string $status,
        bool $isPinned,
        ?int $updatedBy
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE process_comments
             SET comment_text = :comment_text,
                 status = :status,
                 is_pinned = :is_pinned,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $commentId,
            'comment_text' => $commentText,
            'status' => $status,
            'is_pinned' => $isPinned ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
    }

    public function softDelete(int $commentId, ?int $deletedBy): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE process_comments
             SET deleted_by = :deleted_by,
                 deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $commentId,
            'deleted_by' => $deletedBy,
        ]);
    }
}
