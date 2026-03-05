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

    /** @param array<string, mixed> $data */
    public function createVersion(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_versions (
                document_id,
                person_id,
                version_number,
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
                :document_id,
                :person_id,
                :version_number,
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
            'document_id' => $data['document_id'],
            'person_id' => $data['person_id'],
            'version_number' => $data['version_number'],
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

    public function nextVersionNumber(int $documentId): int
    {
        $stmt = $this->db->prepare(
            'SELECT IFNULL(MAX(version_number), 0) + 1 AS next_version
             FROM document_versions
             WHERE document_id = :document_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['document_id' => $documentId]);

        return max(1, (int) ($stmt->fetch()['next_version'] ?? 1));
    }

    /** @param array<string, mixed> $data */
    public function updateDocumentCurrentFile(int $documentId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE documents
             SET original_name = :original_name,
                 stored_name = :stored_name,
                 mime_type = :mime_type,
                 file_size = :file_size,
                 storage_path = :storage_path,
                 uploaded_by = :uploaded_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $documentId,
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'storage_path' => $data['storage_path'],
            'uploaded_by' => $data['uploaded_by'],
        ]);
    }

    /** @param array<int, int> $documentIds */
    public function versionsByDocumentIds(array $documentIds): array
    {
        $documentIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $documentIds
        ), static fn (int $id): bool => $id > 0));

        if ($documentIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($documentIds as $index => $id) {
            $key = ':doc_' . $index;
            $placeholders[] = $key;
            $params['doc_' . $index] = $id;
        }

        $sql = 'SELECT
                    v.id,
                    v.document_id,
                    v.person_id,
                    v.version_number,
                    v.title,
                    v.reference_sei,
                    v.document_date,
                    v.tags,
                    v.notes,
                    v.sensitivity_level,
                    v.original_name,
                    v.stored_name,
                    v.mime_type,
                    v.file_size,
                    v.storage_path,
                    v.uploaded_by,
                    v.created_at,
                    u.name AS uploaded_by_name
                FROM document_versions v
                LEFT JOIN users u ON u.id = v.uploaded_by
                WHERE v.deleted_at IS NULL
                  AND v.document_id IN (' . implode(', ', $placeholders) . ')
                ORDER BY v.document_id ASC, v.version_number DESC, v.id DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
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

    /** @return array<string, mixed>|null */
    public function findVersionByIdForPerson(int $versionId, int $documentId, int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                v.id,
                v.document_id,
                v.person_id,
                v.version_number,
                v.title,
                v.reference_sei,
                v.document_date,
                v.tags,
                v.notes,
                v.sensitivity_level,
                v.original_name,
                v.stored_name,
                v.mime_type,
                v.file_size,
                v.storage_path,
                v.uploaded_by,
                v.created_at,
                d.document_type_id,
                dt.name AS document_type_name
             FROM document_versions v
             INNER JOIN documents d ON d.id = v.document_id
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             WHERE v.id = :id
               AND v.document_id = :document_id
               AND v.person_id = :person_id
               AND v.deleted_at IS NULL
               AND d.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $versionId,
            'document_id' => $documentId,
            'person_id' => $personId,
        ]);

        $version = $stmt->fetch();

        return $version === false ? null : $version;
    }
}
