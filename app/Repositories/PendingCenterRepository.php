<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PendingCenterRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAssignmentsForSync(int $limit = 600): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                a.id AS assignment_id,
                a.person_id,
                a.assigned_user_id,
                a.updated_at AS assignment_updated_at,
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order AS status_order,
                p.name AS person_name,
                p.sei_process_number,
                o.name AS organ_name
             FROM assignments a
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE a.deleted_at IS NULL
               AND p.deleted_at IS NULL
             ORDER BY a.updated_at DESC, a.id DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', max(1, min(3000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array<int, int> $personIds
     *  @return array<int, array<string, mixed>>
     */
    public function documentTypesByPersonIds(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($personIds) as $idx => $personId) {
            $key = ':person_' . $idx;
            $placeholders[] = $key;
            $params['person_' . $idx] = $personId;
        }

        $sql = 'SELECT
                    d.person_id,
                    dt.name AS document_type_name
                FROM documents d
                INNER JOIN document_types dt ON dt.id = d.document_type_id
                WHERE d.deleted_at IS NULL
                  AND d.person_id IN (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array<int, int> $personIds
     *  @return array<int, array<string, mixed>>
     */
    public function openRequiredDivergencesByPersonIds(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($personIds) as $idx => $personId) {
            $key = ':person_' . $idx;
            $placeholders[] = $key;
            $params['person_' . $idx] = $personId;
        }

        $sql = 'SELECT
                    d.id,
                    d.person_id,
                    d.cost_mirror_id,
                    d.severity,
                    d.requires_justification,
                    d.is_resolved,
                    d.difference_amount,
                    d.created_at
                FROM cost_mirror_divergences d
                WHERE d.deleted_at IS NULL
                  AND d.requires_justification = 1
                  AND d.is_resolved = 0
                  AND d.person_id IN (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findBySource(int $personId, string $pendingType, string $sourceKey): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                person_id,
                assignment_id,
                pending_type,
                source_key,
                title,
                description,
                severity,
                status,
                due_date,
                assigned_user_id,
                metadata,
                resolved_at,
                resolved_by,
                created_by,
                created_at,
                updated_at
             FROM analyst_pending_items
             WHERE person_id = :person_id
               AND pending_type = :pending_type
               AND source_key = :source_key
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'person_id' => $personId,
            'pending_type' => $pendingType,
            'source_key' => $sourceKey,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO analyst_pending_items (
                person_id,
                assignment_id,
                pending_type,
                source_key,
                title,
                description,
                severity,
                status,
                due_date,
                assigned_user_id,
                metadata,
                resolved_at,
                resolved_by,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :assignment_id,
                :pending_type,
                :source_key,
                :title,
                :description,
                :severity,
                :status,
                :due_date,
                :assigned_user_id,
                :metadata,
                :resolved_at,
                :resolved_by,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                assignment_id = VALUES(assignment_id),
                title = VALUES(title),
                description = VALUES(description),
                severity = VALUES(severity),
                status = "aberta",
                due_date = VALUES(due_date),
                assigned_user_id = VALUES(assigned_user_id),
                metadata = VALUES(metadata),
                resolved_at = NULL,
                resolved_by = NULL,
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'person_id' => $data['person_id'],
            'assignment_id' => $data['assignment_id'],
            'pending_type' => $data['pending_type'],
            'source_key' => $data['source_key'],
            'title' => $data['title'],
            'description' => $data['description'],
            'severity' => $data['severity'],
            'status' => $data['status'],
            'due_date' => $data['due_date'],
            'assigned_user_id' => $data['assigned_user_id'],
            'metadata' => $data['metadata'],
            'resolved_at' => $data['resolved_at'],
            'resolved_by' => $data['resolved_by'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function syncOpen(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE analyst_pending_items
             SET assignment_id = :assignment_id,
                 title = :title,
                 description = :description,
                 severity = :severity,
                 status = "aberta",
                 due_date = :due_date,
                 assigned_user_id = :assigned_user_id,
                 metadata = :metadata,
                 resolved_at = NULL,
                 resolved_by = NULL,
                 updated_at = NOW(),
                 deleted_at = NULL
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'assignment_id' => $data['assignment_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'severity' => $data['severity'],
            'due_date' => $data['due_date'],
            'assigned_user_id' => $data['assigned_user_id'],
            'metadata' => $data['metadata'],
        ]);
    }

    /** @param array<int, string> $activeSourceHashes
     *  @return array<int, array<string, mixed>>
     */
    public function openItemsNotInSourceHashes(array $activeSourceHashes, int $limit = 2000): array
    {
        $params = [];

        $sql = 'SELECT
                    id,
                    person_id,
                    assignment_id,
                    pending_type,
                    source_key,
                    title,
                    description,
                    severity,
                    status,
                    due_date,
                    assigned_user_id,
                    metadata,
                    resolved_at,
                    resolved_by,
                    created_by,
                    created_at,
                    updated_at,
                    CONCAT(person_id, "|", pending_type, "|", source_key) AS source_hash
                FROM analyst_pending_items
                WHERE deleted_at IS NULL
                  AND status = "aberta"
                  AND pending_type IN ("documento", "divergencia", "retorno")';

        if ($activeSourceHashes !== []) {
            $placeholders = [];
            foreach (array_values($activeSourceHashes) as $idx => $hash) {
                $key = ':source_' . $idx;
                $placeholders[] = $key;
                $params['source_' . $idx] = $hash;
            }

            $sql .= ' AND CONCAT(person_id, "|", pending_type, "|", source_key) NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY updated_at ASC, id ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(5000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                person_id,
                assignment_id,
                pending_type,
                source_key,
                title,
                description,
                severity,
                status,
                due_date,
                assigned_user_id,
                metadata,
                resolved_at,
                resolved_by,
                created_by,
                created_at,
                updated_at
             FROM analyst_pending_items
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateStatus(int $id, string $status, ?int $resolvedBy): bool
    {
        $isResolved = $status === 'resolvida' ? 1 : 0;

        $stmt = $this->db->prepare(
            'UPDATE analyst_pending_items
             SET status = :status,
                 resolved_at = CASE WHEN :is_resolved_at = 1 THEN NOW() ELSE NULL END,
                 resolved_by = CASE WHEN :is_resolved_by = 1 THEN :resolved_by ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => $status,
            'is_resolved_at' => $isResolved,
            'is_resolved_by' => $isResolved,
            'resolved_by' => $resolvedBy,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAssignableUsers(int $limit = 300): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name
             FROM users
             WHERE deleted_at IS NULL
               AND is_active = 1
             ORDER BY name ASC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', max(1, min(3000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'updated_at' => 'pi.updated_at',
            'created_at' => 'pi.created_at',
            'due_date' => 'pi.due_date',
            'person_name' => 'p.name',
            'pending_type' => 'pi.pending_type',
            'status' => 'pi.status',
            'severity' => 'CASE pi.severity WHEN "alta" THEN 1 WHEN "media" THEN 2 WHEN "baixa" THEN 3 ELSE 4 END',
            'responsible' => 'responsible_name',
        ];

        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'pi.updated_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [];
        $where = $this->buildWhere($filters, $params);

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM analyst_pending_items pi
             INNER JOIN people p ON p.id = pi.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN assignments a ON a.id = pi.assignment_id AND a.deleted_at IS NULL
             LEFT JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
             LEFT JOIN users pu ON pu.id = pi.assigned_user_id AND pu.deleted_at IS NULL
             ' . $where
        );

        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            'SELECT
                pi.id,
                pi.person_id,
                pi.assignment_id,
                pi.pending_type,
                pi.source_key,
                pi.title,
                pi.description,
                pi.severity,
                pi.status,
                pi.due_date,
                pi.assigned_user_id,
                pi.metadata,
                pi.resolved_at,
                pi.resolved_by,
                pi.created_by,
                pi.created_at,
                pi.updated_at,
                p.name AS person_name,
                p.sei_process_number,
                o.name AS organ_name,
                a.assigned_user_id AS queue_assigned_user_id,
                s.code AS assignment_status_code,
                s.label AS assignment_status_label,
                COALESCE(pu.name, au.name, "-") AS responsible_name,
                ru.name AS resolved_by_name
             FROM analyst_pending_items pi
             INNER JOIN people p ON p.id = pi.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN assignments a ON a.id = pi.assignment_id AND a.deleted_at IS NULL
             LEFT JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
             LEFT JOIN users pu ON pu.id = pi.assigned_user_id AND pu.deleted_at IS NULL
             LEFT JOIN users ru ON ru.id = pi.resolved_by AND ru.deleted_at IS NULL
             ' . $where . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', pi.id DESC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $listStmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

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

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function summary(array $filters): array
    {
        $local = $filters;
        unset($local['status']);

        $params = [];
        $where = $this->buildWhere($local, $params);

        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN pi.status = "aberta" THEN 1 ELSE 0 END) AS abertas,
                SUM(CASE WHEN pi.status = "resolvida" THEN 1 ELSE 0 END) AS resolvidas,
                SUM(CASE WHEN pi.pending_type = "documento" THEN 1 ELSE 0 END) AS documentos,
                SUM(CASE WHEN pi.pending_type = "divergencia" THEN 1 ELSE 0 END) AS divergencias,
                SUM(CASE WHEN pi.pending_type = "retorno" THEN 1 ELSE 0 END) AS retornos
             FROM analyst_pending_items pi
             INNER JOIN people p ON p.id = pi.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN assignments a ON a.id = pi.assignment_id AND a.deleted_at IS NULL
             LEFT JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
             LEFT JOIN users pu ON pu.id = pi.assigned_user_id AND pu.deleted_at IS NULL
             ' . $where
        );

        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'abertas' => (int) ($row['abertas'] ?? 0),
            'resolvidas' => (int) ($row['resolvidas'] ?? 0),
            'documentos' => (int) ($row['documentos'] ?? 0),
            'divergencias' => (int) ($row['divergencias'] ?? 0),
            'retornos' => (int) ($row['retornos'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function buildWhere(array $filters, array &$params): string
    {
        $where = 'WHERE pi.deleted_at IS NULL AND p.deleted_at IS NULL';

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                p.name LIKE :q
                OR o.name LIKE :q
                OR p.sei_process_number LIKE :q
                OR pi.title LIKE :q
                OR pi.description LIKE :q
            )';
            $params['q'] = '%' . $query . '%';
        }

        $pendingType = mb_strtolower(trim((string) ($filters['pending_type'] ?? '')));
        if ($pendingType !== '') {
            $where .= ' AND pi.pending_type = :pending_type';
            $params['pending_type'] = $pendingType;
        }

        $status = mb_strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where .= ' AND pi.status = :status';
            $params['status'] = $status;
        }

        $severity = mb_strtolower(trim((string) ($filters['severity'] ?? '')));
        if ($severity !== '') {
            $where .= ' AND pi.severity = :severity';
            $params['severity'] = $severity;
        }

        $responsibleId = max(0, (int) ($filters['responsible_id'] ?? 0));
        if ($responsibleId > 0) {
            $where .= ' AND COALESCE(pi.assigned_user_id, a.assigned_user_id, 0) = :responsible_id';
            $params['responsible_id'] = $responsibleId;
        }

        $queueScope = mb_strtolower(trim((string) ($filters['queue_scope'] ?? 'all')));
        $queueUserId = max(0, (int) ($filters['queue_user_id'] ?? 0));
        if ($queueScope === 'mine' && $queueUserId > 0) {
            $where .= ' AND COALESCE(pi.assigned_user_id, a.assigned_user_id, 0) = :queue_user_id';
            $params['queue_user_id'] = $queueUserId;
        } elseif ($queueScope === 'unassigned') {
            $where .= ' AND COALESCE(pi.assigned_user_id, a.assigned_user_id) IS NULL';
        }

        return $where;
    }
}
