<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CdoRepository
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
            'number' => 'c.number',
            'period_start' => 'c.period_start',
            'total_amount' => 'c.total_amount',
            'status' => 'c.status',
            'created_at' => 'c.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'period_start');
        $dir = (string) ($filters['dir'] ?? 'desc');

        $sortColumn = $sortMap[$sort] ?? 'c.period_start';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE c.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (c.number LIKE :q_number OR c.ug_code LIKE :q_ug OR c.action_code LIKE :q_action)';
            $search = '%' . $query . '%';
            $params['q_number'] = $search;
            $params['q_ug'] = $search;
            $params['q_action'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }

        $countSql = "SELECT COUNT(*) AS total FROM cdos c {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                c.id,
                c.number,
                c.ug_code,
                c.action_code,
                c.period_start,
                c.period_end,
                c.total_amount,
                c.status,
                c.notes,
                c.created_by,
                c.created_at,
                c.updated_at,
                u.name AS created_by_name,
                IFNULL(SUM(cp.allocated_amount), 0) AS allocated_amount,
                (c.total_amount - IFNULL(SUM(cp.allocated_amount), 0)) AS available_amount,
                COUNT(cp.id) AS linked_people_count
            FROM cdos c
            LEFT JOIN cdo_people cp
              ON cp.cdo_id = c.id
             AND cp.deleted_at IS NULL
            LEFT JOIN users u ON u.id = c.created_by
            {$where}
            GROUP BY
                c.id,
                c.number,
                c.ug_code,
                c.action_code,
                c.period_start,
                c.period_end,
                c.total_amount,
                c.status,
                c.notes,
                c.created_by,
                c.created_at,
                c.updated_at,
                u.name
            ORDER BY {$sortColumn} {$direction}, c.id DESC
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
                c.id,
                c.number,
                c.ug_code,
                c.action_code,
                c.period_start,
                c.period_end,
                c.total_amount,
                c.status,
                c.notes,
                c.created_by,
                c.created_at,
                c.updated_at,
                u.name AS created_by_name,
                IFNULL(SUM(cp.allocated_amount), 0) AS allocated_amount,
                (c.total_amount - IFNULL(SUM(cp.allocated_amount), 0)) AS available_amount,
                COUNT(cp.id) AS linked_people_count
             FROM cdos c
             LEFT JOIN cdo_people cp
               ON cp.cdo_id = c.id
              AND cp.deleted_at IS NULL
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = :id
               AND c.deleted_at IS NULL
             GROUP BY
                c.id,
                c.number,
                c.ug_code,
                c.action_code,
                c.period_start,
                c.period_end,
                c.total_amount,
                c.status,
                c.notes,
                c.created_by,
                c.created_at,
                c.updated_at,
                u.name
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function linksByCdo(int $cdoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cp.id,
                cp.cdo_id,
                cp.person_id,
                cp.allocated_amount,
                cp.notes,
                cp.created_by,
                cp.created_at,
                cp.updated_at,
                p.name AS person_name,
                p.status AS person_status,
                o.name AS organ_name,
                u.name AS created_by_name
             FROM cdo_people cp
             INNER JOIN people p
               ON p.id = cp.person_id
              AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE cp.cdo_id = :cdo_id
               AND cp.deleted_at IS NULL
             ORDER BY cp.created_at DESC, cp.id DESC'
        );
        $stmt->execute(['cdo_id' => $cdoId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function availablePeopleForLinking(int $cdoId, int $limit = 300): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.status,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.deleted_at IS NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM cdo_people cp
                    WHERE cp.cdo_id = :cdo_id
                      AND cp.person_id = p.id
                      AND cp.deleted_at IS NULL
               )
             ORDER BY p.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':cdo_id', $cdoId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPersonLinkById(int $linkId, int $cdoId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cp.id,
                cp.cdo_id,
                cp.person_id,
                cp.allocated_amount,
                cp.notes,
                cp.created_by,
                cp.created_at,
                cp.updated_at,
                p.name AS person_name
             FROM cdo_people cp
             INNER JOIN people p
               ON p.id = cp.person_id
              AND p.deleted_at IS NULL
             WHERE cp.id = :id
               AND cp.cdo_id = :cdo_id
               AND cp.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $linkId,
            'cdo_id' => $cdoId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cdos (
                number,
                ug_code,
                action_code,
                period_start,
                period_end,
                total_amount,
                status,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :number,
                :ug_code,
                :action_code,
                :period_start,
                :period_end,
                :total_amount,
                :status,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'number' => $data['number'],
            'ug_code' => $data['ug_code'],
            'action_code' => $data['action_code'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'total_amount' => $data['total_amount'],
            'status' => $data['status'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cdos
             SET
                number = :number,
                ug_code = :ug_code,
                action_code = :action_code,
                period_start = :period_start,
                period_end = :period_end,
                total_amount = :total_amount,
                status = :status,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'number' => $data['number'],
            'ug_code' => $data['ug_code'],
            'action_code' => $data['action_code'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'total_amount' => $data['total_amount'],
            'status' => $data['status'],
            'notes' => $data['notes'],
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cdos
             SET status = :status, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function softDeleteLinksByCdo(int $cdoId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cdo_people
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE cdo_id = :cdo_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['cdo_id' => $cdoId]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cdos
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function createPersonLink(int $cdoId, int $personId, string $allocatedAmount, ?string $notes, ?int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cdo_people (
                cdo_id,
                person_id,
                allocated_amount,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :cdo_id,
                :person_id,
                :allocated_amount,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'cdo_id' => $cdoId,
            'person_id' => $personId,
            'allocated_amount' => $allocatedAmount,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function softDeletePersonLink(int $linkId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cdo_people
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $linkId]);
    }

    public function activeLinkExists(int $cdoId, int $personId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM cdo_people
             WHERE cdo_id = :cdo_id
               AND person_id = :person_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'cdo_id' => $cdoId,
            'person_id' => $personId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function cdoNumberExists(string $number, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM cdos WHERE number = :number LIMIT 1';
        $params = ['number' => $number];

        if ($ignoreId !== null) {
            $sql = 'SELECT id FROM cdos WHERE number = :number AND id <> :id LIMIT 1';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function personExists(int $personId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM people
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);

        return $stmt->fetch() !== false;
    }
}
