<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function activeDocumentTypes(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description
             FROM document_types
             WHERE is_active = 1
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateByPerson(int $personId, int $page, int $perPage, bool $includeSensitiveDocuments = false): array
    {
        $visibilityFilter = $includeSensitiveDocuments
            ? ''
            : " AND COALESCE(NULLIF(sensitivity_level, ''), 'public') = 'public'";

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM documents
             WHERE person_id = :person_id AND deleted_at IS NULL' . $visibilityFilter
        );
        $countStmt->execute(['person_id' => $personId]);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.document_type_id,
                d.title,
                d.reference_sei,
                d.document_date,
                d.tags,
                d.notes,
                d.sensitivity_level,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.file_size,
                d.storage_path,
                d.uploaded_by,
                d.created_at,
                dt.name AS document_type_name,
                u.name AS uploaded_by_name
             FROM documents d
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.person_id = :person_id
               AND d.deleted_at IS NULL' . ($includeSensitiveDocuments ? '' : " AND COALESCE(NULLIF(d.sensitivity_level, ''), 'public') = 'public'") . '
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @param array<string, mixed> $data */
    public function createDocument(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO documents (
                person_id,
                document_type_id,
                title,
                reference_sei,
                document_date,
                tags,
                notes,
                sensitivity_level,
                original_name,
                stored_name,
                mime_type,
                file_size,
                storage_path,
                uploaded_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :document_type_id,
                :title,
                :reference_sei,
                :document_date,
                :tags,
                :notes,
                :sensitivity_level,
                :original_name,
                :stored_name,
                :mime_type,
                :file_size,
                :storage_path,
                :uploaded_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'person_id' => $data['person_id'],
            'document_type_id' => $data['document_type_id'],
            'title' => $data['title'],
            'reference_sei' => $data['reference_sei'],
            'document_date' => $data['document_date'],
            'tags' => $data['tags'],
            'notes' => $data['notes'],
            'sensitivity_level' => $data['sensitivity_level'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'storage_path' => $data['storage_path'],
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForPerson(int $documentId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.document_type_id,
                d.title,
                d.reference_sei,
                d.document_date,
                d.tags,
                d.notes,
                d.sensitivity_level,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.file_size,
                d.storage_path,
                d.uploaded_by,
                d.created_at,
                dt.name AS document_type_name
             FROM documents d
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.id = :id AND d.person_id = :person_id AND d.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $documentId,
            'person_id' => $personId,
        ]);

        $document = $stmt->fetch();

        return $document === false ? null : $document;
    }
}
