<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProcessMetadataRepository
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
            'person_name' => 'p.name',
            'office_sent_at' => 'pm.office_sent_at',
            'dou_published_at' => 'pm.dou_published_at',
            'mte_entry_date' => 'pm.mte_entry_date',
            'updated_at' => 'pm.updated_at',
            'created_at' => 'pm.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'pm.updated_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE pm.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                p.name LIKE :q_person
                OR o.name LIKE :q_organ
                OR pm.office_number LIKE :q_office
                OR pm.office_protocol LIKE :q_protocol
                OR pm.dou_edition LIKE :q_dou
            )';
            $search = '%' . $query . '%';
            $params['q_person'] = $search;
            $params['q_organ'] = $search;
            $params['q_office'] = $search;
            $params['q_protocol'] = $search;
            $params['q_dou'] = $search;
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND p.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $hasDouRaw = trim((string) ($filters['has_dou'] ?? ''));
        if ($hasDouRaw === '1') {
            $where .= ' AND (
                pm.dou_edition IS NOT NULL
                OR pm.dou_published_at IS NOT NULL
                OR pm.dou_link IS NOT NULL
                OR pm.dou_attachment_storage_path IS NOT NULL
            )';
        } elseif ($hasDouRaw === '0') {
            $where .= ' AND (
                pm.dou_edition IS NULL
                AND pm.dou_published_at IS NULL
                AND pm.dou_link IS NULL
                AND pm.dou_attachment_storage_path IS NULL
            )';
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM process_metadata pm
             INNER JOIN people p ON p.id = pm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                pm.id,
                pm.person_id,
                pm.office_number,
                pm.office_sent_at,
                pm.office_channel,
                pm.office_protocol,
                pm.dou_edition,
                pm.dou_published_at,
                pm.dou_link,
                pm.dou_attachment_original_name,
                pm.dou_attachment_storage_path,
                pm.mte_entry_date,
                pm.notes,
                pm.created_by,
                pm.created_at,
                pm.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                p.organ_id,
                o.name AS organ_name,
                u.name AS created_by_name
             FROM process_metadata pm
             INNER JOIN people p ON p.id = pm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN users u ON u.id = pm.created_by
             {$where}
             ORDER BY {$sortColumn} {$direction}, pm.id DESC
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
    public function findById(int $id): ?array
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
                pm.dou_link,
                pm.dou_attachment_original_name,
                pm.dou_attachment_stored_name,
                pm.dou_attachment_mime_type,
                pm.dou_attachment_file_size,
                pm.dou_attachment_storage_path,
                pm.mte_entry_date,
                pm.notes,
                pm.created_by,
                pm.created_at,
                pm.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                p.sei_process_number,
                p.organ_id,
                o.name AS organ_name,
                u.name AS created_by_name
             FROM process_metadata pm
             INNER JOIN people p ON p.id = pm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN users u ON u.id = pm.created_by
             WHERE pm.id = :id
               AND pm.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByPersonId(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                person_id,
                office_number,
                office_sent_at,
                office_channel,
                office_protocol,
                dou_edition,
                dou_published_at,
                dou_link,
                dou_attachment_original_name,
                dou_attachment_stored_name,
                dou_attachment_mime_type,
                dou_attachment_file_size,
                dou_attachment_storage_path,
                mte_entry_date,
                notes,
                created_by,
                created_at,
                updated_at
             FROM process_metadata
             WHERE person_id = :person_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
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

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $organId = 0, int $limit = 800): array
    {
        $sql = 'SELECT
                    p.id,
                    p.name,
                    p.status,
                    p.sei_process_number,
                    p.organ_id,
                    o.name AS organ_name,
                    CASE
                      WHEN EXISTS (
                        SELECT 1
                        FROM process_metadata pm
                        WHERE pm.person_id = p.id
                          AND pm.deleted_at IS NULL
                      )
                      THEN 1 ELSE 0
                    END AS has_process_meta
                FROM people p
                INNER JOIN organs o ON o.id = p.organ_id
                WHERE p.deleted_at IS NULL';

        if ($organId > 0) {
            $sql .= ' AND p.organ_id = :organ_id';
        }

        $sql .= ' ORDER BY p.name ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        if ($organId > 0) {
            $stmt->bindValue(':organ_id', $organId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', max(1, min(3000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPersonById(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.status,
                p.sei_process_number,
                p.organ_id,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.id = :id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO process_metadata (
                person_id,
                office_number,
                office_sent_at,
                office_channel,
                office_protocol,
                dou_edition,
                dou_published_at,
                dou_link,
                dou_attachment_original_name,
                dou_attachment_stored_name,
                dou_attachment_mime_type,
                dou_attachment_file_size,
                dou_attachment_storage_path,
                mte_entry_date,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :person_id,
                :office_number,
                :office_sent_at,
                :office_channel,
                :office_protocol,
                :dou_edition,
                :dou_published_at,
                :dou_link,
                :dou_attachment_original_name,
                :dou_attachment_stored_name,
                :dou_attachment_mime_type,
                :dou_attachment_file_size,
                :dou_attachment_storage_path,
                :mte_entry_date,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'person_id' => $data['person_id'],
            'office_number' => $data['office_number'],
            'office_sent_at' => $data['office_sent_at'],
            'office_channel' => $data['office_channel'],
            'office_protocol' => $data['office_protocol'],
            'dou_edition' => $data['dou_edition'],
            'dou_published_at' => $data['dou_published_at'],
            'dou_link' => $data['dou_link'],
            'dou_attachment_original_name' => $data['dou_attachment_original_name'],
            'dou_attachment_stored_name' => $data['dou_attachment_stored_name'],
            'dou_attachment_mime_type' => $data['dou_attachment_mime_type'],
            'dou_attachment_file_size' => $data['dou_attachment_file_size'],
            'dou_attachment_storage_path' => $data['dou_attachment_storage_path'],
            'mte_entry_date' => $data['mte_entry_date'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE process_metadata
             SET
                person_id = :person_id,
                office_number = :office_number,
                office_sent_at = :office_sent_at,
                office_channel = :office_channel,
                office_protocol = :office_protocol,
                dou_edition = :dou_edition,
                dou_published_at = :dou_published_at,
                dou_link = :dou_link,
                dou_attachment_original_name = :dou_attachment_original_name,
                dou_attachment_stored_name = :dou_attachment_stored_name,
                dou_attachment_mime_type = :dou_attachment_mime_type,
                dou_attachment_file_size = :dou_attachment_file_size,
                dou_attachment_storage_path = :dou_attachment_storage_path,
                mte_entry_date = :mte_entry_date,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'person_id' => $data['person_id'],
            'office_number' => $data['office_number'],
            'office_sent_at' => $data['office_sent_at'],
            'office_channel' => $data['office_channel'],
            'office_protocol' => $data['office_protocol'],
            'dou_edition' => $data['dou_edition'],
            'dou_published_at' => $data['dou_published_at'],
            'dou_link' => $data['dou_link'],
            'dou_attachment_original_name' => $data['dou_attachment_original_name'],
            'dou_attachment_stored_name' => $data['dou_attachment_stored_name'],
            'dou_attachment_mime_type' => $data['dou_attachment_mime_type'],
            'dou_attachment_file_size' => $data['dou_attachment_file_size'],
            'dou_attachment_storage_path' => $data['dou_attachment_storage_path'],
            'mte_entry_date' => $data['mte_entry_date'],
            'notes' => $data['notes'],
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE process_metadata
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findAttachmentById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                person_id,
                dou_attachment_original_name,
                dou_attachment_mime_type,
                dou_attachment_storage_path
             FROM process_metadata
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if (trim((string) ($row['dou_attachment_storage_path'] ?? '')) === '') {
            return null;
        }

        return $row;
    }
}
