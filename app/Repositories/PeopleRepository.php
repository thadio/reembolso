<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PeopleRepository
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
            'name' => 'p.name',
            'status' => 'p.status',
            'organ' => 'o.name',
            'responsible' => 'au.name',
            'priority' => "CASE COALESCE(NULLIF(a.priority_level, ''), 'normal')
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'normal' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END",
            'assignment_updated_at' => 'a.updated_at',
            'created_at' => 'p.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'name');
        $dir = (string) ($filters['dir'] ?? 'asc');

        $sortColumn = $sortMap[$sort] ?? 'p.name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE p.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (p.name LIKE :q_name OR p.cpf LIKE :q_cpf OR o.name LIKE :q_organ OR p.sei_process_number LIKE :q_sei)';
            $search = '%' . $query . '%';
            $params['q_name'] = $search;
            $params['q_cpf'] = $search;
            $params['q_organ'] = $search;
            $params['q_sei'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND p.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $modalityId = (int) ($filters['modality_id'] ?? 0);
        if ($modalityId > 0) {
            $where .= ' AND p.desired_modality_id = :modality_id';
            $params['modality_id'] = $modalityId;
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            $where .= ' AND p.tags LIKE :tag';
            $params['tag'] = '%' . $tag . '%';
        }

        $queueScope = mb_strtolower(trim((string) ($filters['queue_scope'] ?? 'all')));
        $responsibleId = max(0, (int) ($filters['responsible_id'] ?? 0));
        $assignedUserId = max(0, (int) ($filters['assigned_user_id'] ?? 0));
        if ($assignedUserId > 0) {
            $responsibleId = $assignedUserId;
        }

        if ($queueScope === 'mine' && $responsibleId > 0) {
            $where .= ' AND a.assigned_user_id = :responsible_id';
            $params['responsible_id'] = $responsibleId;
        } elseif ($queueScope === 'unassigned') {
            $where .= ' AND a.assigned_user_id IS NULL';
        } elseif ($responsibleId > 0) {
            $where .= ' AND a.assigned_user_id = :responsible_id';
            $params['responsible_id'] = $responsibleId;
        }

        $priority = mb_strtolower(trim((string) ($filters['priority'] ?? '')));
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $where .= " AND COALESCE(NULLIF(a.priority_level, ''), 'normal') = :priority";
            $params['priority'] = $priority;
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM people p
            INNER JOIN organs o ON o.id = p.organ_id
            LEFT JOIN modalities m ON m.id = p.desired_modality_id
            LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
            LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
            {$where}
        ";

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                p.id,
                p.name,
                p.cpf,
                p.status,
                p.sei_process_number,
                p.mte_destination,
                p.tags,
                p.created_at,
                o.id AS organ_id,
                o.name AS organ_name,
                m.id AS modality_id,
                m.name AS modality_name,
                a.id AS assignment_id,
                a.assigned_user_id,
                COALESCE(NULLIF(a.priority_level, ''), 'normal') AS assignment_priority,
                a.updated_at AS assignment_updated_at,
                au.name AS assigned_user_name
            FROM people p
            INNER JOIN organs o ON o.id = p.organ_id
            LEFT JOIN modalities m ON m.id = p.desired_modality_id
            LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
            LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
            {$where}
            ORDER BY {$sortColumn} {$direction}, p.id ASC
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
                p.id,
                p.organ_id,
                p.desired_modality_id,
                p.name,
                p.cpf,
                p.birth_date,
                p.email,
                p.phone,
                p.status,
                p.sei_process_number,
                p.mte_destination,
                p.tags,
                p.notes,
                p.created_at,
                p.updated_at,
                o.name AS organ_name,
                m.name AS modality_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN modalities m ON m.id = p.desired_modality_id
             WHERE p.id = :id AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        return $person === false ? null : $person;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO people (
                organ_id,
                desired_modality_id,
                name,
                cpf,
                birth_date,
                email,
                phone,
                status,
                sei_process_number,
                mte_destination,
                tags,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :organ_id,
                :desired_modality_id,
                :name,
                :cpf,
                :birth_date,
                :email,
                :phone,
                :status,
                :sei_process_number,
                :mte_destination,
                :tags,
                :notes,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'organ_id' => $data['organ_id'],
            'desired_modality_id' => $data['desired_modality_id'],
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'sei_process_number' => $data['sei_process_number'],
            'mte_destination' => $data['mte_destination'],
            'tags' => $data['tags'],
            'notes' => $data['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE people
             SET
                organ_id = :organ_id,
                desired_modality_id = :desired_modality_id,
                name = :name,
                cpf = :cpf,
                birth_date = :birth_date,
                email = :email,
                phone = :phone,
                status = :status,
                sei_process_number = :sei_process_number,
                mte_destination = :mte_destination,
                tags = :tags,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'organ_id' => $data['organ_id'],
            'desired_modality_id' => $data['desired_modality_id'],
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'sei_process_number' => $data['sei_process_number'],
            'mte_destination' => $data['mte_destination'],
            'tags' => $data['tags'],
            'notes' => $data['notes'],
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE people SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute(['id' => $id]);
    }

    public function cpfExists(string $cpf, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM people WHERE cpf = :cpf AND deleted_at IS NULL';
        $params = ['cpf' => $cpf];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function organExists(int $organId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM organs WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $organId]);

        return $stmt->fetch() !== false;
    }

    public function modalityExists(int $modalityId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM modalities WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $modalityId]);

        return $stmt->fetch() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        $stmt = $this->db->query('SELECT id, name, acronym FROM organs WHERE deleted_at IS NULL ORDER BY name ASC');

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeModalities(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM modalities WHERE is_active = 1 ORDER BY name ASC');

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeMteDestinations(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, code
             FROM mte_destinations
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    public function mteDestinationExists(string $name): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM mte_destinations
             WHERE name = :name AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['name' => $name]);

        return $stmt->fetch() !== false;
    }
}
