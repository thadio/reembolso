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
            $where .= ' AND (p.name LIKE :q_name OR p.cpf LIKE :q_cpf OR p.matricula_siape LIKE :q_siape OR o.name LIKE :q_organ OR p.sei_process_number LIKE :q_sei)';
            $search = '%' . $query . '%';
            $params['q_name'] = $search;
            $params['q_cpf'] = $search;
            $params['q_siape'] = $search;
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

        $movementBucket = mb_strtolower(trim((string) ($filters['movement_bucket'] ?? '')));
        if (in_array($movementBucket, ['entrando', 'saindo'], true)) {
            $movementDirection = $movementBucket === 'saindo' ? 'saida_mte' : 'entrada_mte';
            $where .= ' AND a.movement_direction = :movement_direction';
            $params['movement_direction'] = $movementDirection;

            $allowedModalityIds = $this->movementBucketModalityIds($movementBucket);
            if ($allowedModalityIds === []) {
                $where .= ' AND 1 = 0';
            } else {
                $placeholders = [];
                foreach ($allowedModalityIds as $index => $allowedModalityId) {
                    $paramName = 'movement_modality_' . $index;
                    $placeholders[] = ':' . $paramName;
                    $params[$paramName] = $allowedModalityId;
                }

                $where .= ' AND p.desired_modality_id IN (' . implode(', ', $placeholders) . ')';
            }
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

        $monthlyEquivalentExpr = 'CASE
            WHEN i.cost_type = "mensal"
                 AND (i.start_date IS NULL OR i.start_date <= LAST_DAY(CURDATE()))
                 AND (
                    i.end_date IS NULL
                    OR i.end_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                 )
            THEN i.amount
            WHEN i.cost_type = "anual"
                 AND (i.start_date IS NULL OR i.start_date <= LAST_DAY(CURDATE()))
                 AND (
                    i.end_date IS NULL
                    OR i.end_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                 )
            THEN i.amount / 12
            WHEN i.cost_type IN ("eventual", "unico")
                 AND (
                    (i.start_date IS NOT NULL
                     AND i.start_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                     AND i.start_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY), INTERVAL 1 MONTH))
                    OR (i.start_date IS NULL
                     AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                     AND i.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY), INTERVAL 1 MONTH))
                 )
            THEN i.amount
            ELSE 0
        END';

        $isAuxilioExpr = '((ci.linkage_code = 510) OR (ci.id IS NULL AND (LOWER(i.item_name) LIKE "%auxilio%" OR LOWER(i.item_name) LIKE "%beneficio%")))';

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
                p.matricula_siape,
                p.status,
                p.sei_process_number,
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
                COALESCE(a.updated_at, p.updated_at) AS status_changed_at,
                au.name AS assigned_user_name,
                IFNULL(cost.monthly_total, 0) AS monthly_total,
                IFNULL(cost.monthly_auxilios, 0) AS monthly_auxilios,
                (IFNULL(cost.monthly_total, 0) - IFNULL(cost.monthly_auxilios, 0)) AS monthly_remuneration
            FROM people p
            INNER JOIN organs o ON o.id = p.organ_id
            LEFT JOIN modalities m ON m.id = p.desired_modality_id
            LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
            LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
            LEFT JOIN (
                SELECT
                    cp.person_id,
                    IFNULL(SUM(' . $monthlyEquivalentExpr . '), 0) AS monthly_total,
                    IFNULL(SUM(CASE WHEN ' . $isAuxilioExpr . ' THEN ' . $monthlyEquivalentExpr . ' ELSE 0 END), 0) AS monthly_auxilios
                FROM cost_plans cp
                LEFT JOIN cost_plan_items i ON i.cost_plan_id = cp.id AND i.deleted_at IS NULL
                LEFT JOIN cost_item_catalog ci ON ci.id = i.cost_item_catalog_id
                WHERE cp.deleted_at IS NULL
                  AND cp.is_active = 1
                GROUP BY cp.person_id
            ) cost ON cost.person_id = p.id
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
                p.assignment_flow_id,
                p.name,
                p.cpf,
                p.matricula_siape,
                p.birth_date,
                p.email,
                p.phone,
                p.status,
                p.sei_process_number,
                p.tags,
                p.notes,
                p.created_at,
                p.updated_at,
                o.name AS organ_name,
                m.name AS modality_name,
                f.name AS assignment_flow_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN modalities m ON m.id = p.desired_modality_id
             LEFT JOIN assignment_flows f ON f.id = p.assignment_flow_id AND f.deleted_at IS NULL
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
                assignment_flow_id,
                name,
                cpf,
                matricula_siape,
                birth_date,
                email,
                phone,
                status,
                sei_process_number,
                tags,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :organ_id,
                :desired_modality_id,
                :assignment_flow_id,
                :name,
                :cpf,
                :matricula_siape,
                :birth_date,
                :email,
                :phone,
                :status,
                :sei_process_number,
                :tags,
                :notes,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'organ_id' => $data['organ_id'],
            'desired_modality_id' => $data['desired_modality_id'],
            'assignment_flow_id' => $data['assignment_flow_id'],
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'matricula_siape' => $data['matricula_siape'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'sei_process_number' => $data['sei_process_number'],
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
                assignment_flow_id = :assignment_flow_id,
                name = :name,
                cpf = :cpf,
                matricula_siape = :matricula_siape,
                birth_date = :birth_date,
                email = :email,
                phone = :phone,
                status = :status,
                sei_process_number = :sei_process_number,
                tags = :tags,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'organ_id' => $data['organ_id'],
            'desired_modality_id' => $data['desired_modality_id'],
            'assignment_flow_id' => $data['assignment_flow_id'],
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'matricula_siape' => $data['matricula_siape'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'sei_process_number' => $data['sei_process_number'],
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

    public function siapeExists(string $siape, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM people WHERE matricula_siape = :matricula_siape';
        $params = ['matricula_siape' => $siape];

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
            'SELECT id, name, code, acronym, uf
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

    public function assignmentFlowExists(int $flowId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM assignment_flows
             WHERE id = :id
               AND deleted_at IS NULL
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $flowId]);

        return $stmt->fetch() !== false;
    }

    public function defaultAssignmentFlowId(): ?int
    {
        $stmt = $this->db->query(
            'SELECT id
             FROM assignment_flows
             WHERE deleted_at IS NULL
               AND is_active = 1
             ORDER BY is_default DESC, id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $flowId = (int) ($row['id'] ?? 0);

        return $flowId > 0 ? $flowId : null;
    }

    /** @return array<int, string> */
    public function activeStatusCodes(): array
    {
        $stmt = $this->db->query(
            'SELECT code
             FROM assignment_statuses
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['code'] ?? ''),
            $rows
        ), static fn (string $code): bool => trim($code) !== ''));
    }

    /** @return array<int, int> */
    private function movementBucketModalityIds(string $bucket): array
    {
        $normalizedBucket = mb_strtolower(trim($bucket));
        if (!in_array($normalizedBucket, ['entrando', 'saindo'], true)) {
            return [];
        }

        $allowedReasonTypes = $normalizedBucket === 'saindo'
            ? ['cessao', 'requisicao', 'cft']
            : ['cessao', 'cft'];

        $stmt = $this->db->query(
            'SELECT id, name
             FROM modalities
             WHERE is_active = 1
             ORDER BY id ASC'
        );
        $rows = $stmt->fetchAll();

        $allowedIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $reasonType = $this->movementReasonType((string) ($row['name'] ?? ''));
            if ($reasonType === null || !in_array($reasonType, $allowedReasonTypes, true)) {
                continue;
            }

            $allowedIds[] = $id;
        }

        return $allowedIds;
    }

    private function movementReasonType(string $modalityName): ?string
    {
        $normalized = $this->normalizeLookup($modalityName);
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'cess')) {
            return 'cessao';
        }

        if (str_contains($normalized, 'requis')) {
            return 'requisicao';
        }

        if (str_contains($normalized, 'forca') || str_contains($normalized, 'cft')) {
            return 'cft';
        }

        return null;
    }

    private function normalizeLookup(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        return strtr($normalized, [
            'ã' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'õ' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
    }
}
