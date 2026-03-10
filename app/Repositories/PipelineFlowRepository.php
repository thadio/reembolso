<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PipelineFlowRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        $sortMap = [
            'name' => 'f.name',
            'is_default' => 'f.is_default',
            'is_active' => 'f.is_active',
            'updated_at' => 'f.updated_at',
            'created_at' => 'f.created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? 'f.name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE f.deleted_at IS NULL';
        $params = [];

        if (trim($query) !== '') {
            $where .= ' AND (f.name LIKE :q_name OR f.description LIKE :q_desc)';
            $search = '%' . trim($query) . '%';
            $params['q_name'] = $search;
            $params['q_desc'] = $search;
        }

        $countSql = "SELECT COUNT(*) AS total FROM assignment_flows f {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                f.id,
                f.name,
                f.description,
                f.is_active,
                f.is_default,
                f.created_at,
                f.updated_at,
                IFNULL(step_stats.total_steps, 0) AS total_steps,
                IFNULL(step_stats.initial_steps, 0) AS initial_steps,
                IFNULL(transition_stats.total_transitions, 0) AS total_transitions
            FROM assignment_flows f
            LEFT JOIN (
                SELECT
                    flow_id,
                    COUNT(*) AS total_steps,
                    SUM(CASE WHEN is_initial = 1 AND is_active = 1 THEN 1 ELSE 0 END) AS initial_steps
                FROM assignment_flow_steps
                GROUP BY flow_id
            ) step_stats ON step_stats.flow_id = f.id
            LEFT JOIN (
                SELECT
                    flow_id,
                    COUNT(*) AS total_transitions
                FROM assignment_flow_transitions
                GROUP BY flow_id
            ) transition_stats ON transition_stats.flow_id = f.id
            {$where}
            ORDER BY {$sortColumn} {$direction}, f.id ASC
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
    public function findFlowById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                name,
                description,
                bpmn_diagram_xml,
                is_active,
                is_default,
                created_at,
                updated_at
             FROM assignment_flows
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $flow = $stmt->fetch();

        return $flow === false ? null : $flow;
    }

    public function flowNameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM assignment_flows WHERE name = :name AND deleted_at IS NULL';
        $params = ['name' => $name];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /** @param array<string, mixed> $data */
    public function createFlow(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO assignment_flows (
                name,
                description,
                is_active,
                is_default,
                created_at,
                updated_at
             ) VALUES (
                :name,
                :description,
                :is_active,
                :is_default,
                NOW(),
                NOW()
             )'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
            'is_default' => $data['is_default'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateFlow(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_flows
             SET name = :name,
                 description = :description,
                 is_active = :is_active,
                 is_default = :is_default,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
            'is_default' => $data['is_default'],
        ]);
    }

    public function updateFlowDiagramXml(int $id, string $diagramXml): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_flows
             SET bpmn_diagram_xml = :bpmn_diagram_xml,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'bpmn_diagram_xml' => $diagramXml,
        ]);
    }

    public function softDeleteFlow(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_flows
             SET deleted_at = NOW(),
                 is_active = 0,
                 is_default = 0,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function reassignPeopleFlow(int $fromFlowId, int $toFlowId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE people
             SET assignment_flow_id = :to_flow_id,
                 updated_at = NOW()
             WHERE assignment_flow_id = :from_flow_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'from_flow_id' => $fromFlowId,
            'to_flow_id' => $toFlowId,
        ]);
    }

    public function reassignAssignmentsFlow(int $fromFlowId, int $toFlowId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET flow_id = :to_flow_id,
                 updated_at = NOW()
             WHERE flow_id = :from_flow_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'from_flow_id' => $fromFlowId,
            'to_flow_id' => $toFlowId,
        ]);
    }

    public function setDefaultFlow(int $flowId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_flows
             SET is_default = CASE WHEN id = :id THEN 1 ELSE 0 END,
                 updated_at = NOW()
             WHERE deleted_at IS NULL'
        );
        $stmt->execute(['id' => $flowId]);
    }

    public function countActiveFlows(): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) AS total
             FROM assignment_flows
             WHERE deleted_at IS NULL
               AND is_active = 1'
        );
        $row = $stmt->fetch();

        return max(0, (int) ($row['total'] ?? 0));
    }

    public function fallbackFlowId(?int $excludeId = null): ?int
    {
        $sql = 'SELECT id
                FROM assignment_flows
                WHERE deleted_at IS NULL
                  AND is_active = 1';
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' ORDER BY is_default DESC, id ASC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function statusCatalog(): array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                code,
                label,
                sort_order,
                next_action_label,
                event_type,
                is_active
             FROM assignment_statuses
             ORDER BY is_active DESC, sort_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function documentTypeCatalog(): array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                name,
                description,
                is_active
             FROM document_types
             ORDER BY is_active DESC, name ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @param array<int, int> $documentTypeIds
     * @return array<int, int>
     */
    public function existingDocumentTypeIds(array $documentTypeIds): array
    {
        $documentTypeIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $documentTypeIds
        ), static fn (int $id): bool => $id > 0)));

        if ($documentTypeIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($documentTypeIds as $index => $id) {
            $key = ':type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT id
                FROM document_types
                WHERE id IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_values(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        ));
    }

    /** @return array<string, mixed>|null */
    public function statusById(int $statusId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                code,
                label,
                sort_order,
                next_action_label,
                event_type,
                is_active
             FROM assignment_statuses
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $statusId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function statusByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                code,
                label,
                sort_order,
                next_action_label,
                event_type,
                is_active
             FROM assignment_statuses
             WHERE code = :code
             LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function nextStatusSortOrder(): int
    {
        $stmt = $this->db->query('SELECT IFNULL(MAX(sort_order), 0) + 1 AS next_sort FROM assignment_statuses');
        $row = $stmt->fetch();

        return max(1, (int) ($row['next_sort'] ?? 1));
    }

    /** @param array<string, mixed> $data */
    public function createStatus(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO assignment_statuses (
                code,
                label,
                sort_order,
                next_action_label,
                event_type,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :code,
                :label,
                :sort_order,
                :next_action_label,
                :event_type,
                :is_active,
                NOW(),
                NOW()
             )'
        );
        $stmt->execute([
            'code' => $data['code'],
            'label' => $data['label'],
            'sort_order' => $data['sort_order'],
            'next_action_label' => $data['next_action_label'],
            'event_type' => $data['event_type'],
            'is_active' => $data['is_active'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int $statusId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_statuses
             SET label = :label,
                 next_action_label = :next_action_label,
                 event_type = :event_type,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $statusId,
            'label' => $data['label'],
            'next_action_label' => $data['next_action_label'],
            'event_type' => $data['event_type'],
            'is_active' => $data['is_active'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function flowSteps(int $flowId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                fs.id,
                fs.flow_id,
                fs.status_id,
                fs.node_kind,
                fs.sort_order,
                fs.is_initial,
                fs.is_active,
                fs.requires_evidence_close,
                fs.step_tags,
                fs.created_at,
                fs.updated_at,
                s.code AS status_code,
                s.label AS status_label,
                s.next_action_label AS status_next_action_label,
                s.event_type AS status_event_type,
                s.is_active AS status_is_active
             FROM assignment_flow_steps fs
             INNER JOIN assignment_statuses s ON s.id = fs.status_id
             WHERE fs.flow_id = :flow_id
             ORDER BY fs.sort_order ASC, fs.id ASC'
        );
        $stmt->execute(['flow_id' => $flowId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function flowStepByStatus(int $flowId, int $statusId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                flow_id,
                status_id,
                node_kind,
                sort_order,
                is_initial,
                is_active,
                requires_evidence_close,
                step_tags
             FROM assignment_flow_steps
             WHERE flow_id = :flow_id
               AND status_id = :status_id
             LIMIT 1'
        );
        $stmt->execute([
            'flow_id' => $flowId,
            'status_id' => $statusId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function clearInitialStep(int $flowId, ?int $exceptStatusId = null): bool
    {
        $sql = 'UPDATE assignment_flow_steps
                SET is_initial = 0, updated_at = NOW()
                WHERE flow_id = :flow_id';
        $params = ['flow_id' => $flowId];
        if ($exceptStatusId !== null) {
            $sql .= ' AND status_id <> :status_id';
            $params['status_id'] = $exceptStatusId;
        }

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function upsertFlowStep(
        int $flowId,
        int $statusId,
        string $nodeKind,
        int $sortOrder,
        bool $isInitial,
        bool $isActive,
        bool $requiresEvidenceClose,
        ?string $stepTags
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO assignment_flow_steps (
                flow_id,
                status_id,
                node_kind,
                sort_order,
                is_initial,
                is_active,
                requires_evidence_close,
                step_tags,
                created_at,
                updated_at
             ) VALUES (
                :flow_id,
                :status_id,
                :node_kind,
                :sort_order,
                :is_initial,
                :is_active,
                :requires_evidence_close,
                :step_tags,
                NOW(),
                NOW()
             )
             ON DUPLICATE KEY UPDATE
                node_kind = VALUES(node_kind),
                sort_order = VALUES(sort_order),
                is_initial = VALUES(is_initial),
                is_active = VALUES(is_active),
                requires_evidence_close = VALUES(requires_evidence_close),
                step_tags = VALUES(step_tags),
                updated_at = NOW()'
        );

        return $stmt->execute([
            'flow_id' => $flowId,
            'status_id' => $statusId,
            'node_kind' => $nodeKind,
            'sort_order' => $sortOrder,
            'is_initial' => $isInitial ? 1 : 0,
            'is_active' => $isActive ? 1 : 0,
            'requires_evidence_close' => $requiresEvidenceClose ? 1 : 0,
            'step_tags' => $stepTags,
        ]);
    }

    public function removeFlowStep(int $flowId, int $statusId): bool
    {
        $deleteTransitions = $this->db->prepare(
            'DELETE
             FROM assignment_flow_transitions
             WHERE flow_id = :flow_id
               AND (from_status_id = :from_status_id OR to_status_id = :to_status_id)'
        );
        $deleteTransitions->execute([
            'flow_id' => $flowId,
            'from_status_id' => $statusId,
            'to_status_id' => $statusId,
        ]);

        $stmt = $this->db->prepare(
            'DELETE
             FROM assignment_flow_steps
             WHERE flow_id = :flow_id
               AND status_id = :status_id'
        );

        return $stmt->execute([
            'flow_id' => $flowId,
            'status_id' => $statusId,
        ]);
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function flowStepDocumentTypesMap(int $flowId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                m.flow_step_id,
                m.document_type_id,
                m.is_required,
                dt.name AS document_type_name,
                dt.is_active AS document_type_is_active
             FROM assignment_flow_step_document_types m
             INNER JOIN assignment_flow_steps fs ON fs.id = m.flow_step_id
             INNER JOIN document_types dt ON dt.id = m.document_type_id
             WHERE fs.flow_id = :flow_id
             ORDER BY m.flow_step_id ASC, m.is_required DESC, dt.name ASC, dt.id ASC'
        );
        $stmt->execute(['flow_id' => $flowId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $stepId = (int) ($row['flow_step_id'] ?? 0);
            if ($stepId <= 0) {
                continue;
            }

            if (!isset($map[$stepId]) || !is_array($map[$stepId])) {
                $map[$stepId] = [];
            }

            $map[$stepId][] = [
                'document_type_id' => (int) ($row['document_type_id'] ?? 0),
                'is_required' => (int) ($row['is_required'] ?? 0),
                'document_type_name' => (string) ($row['document_type_name'] ?? ''),
                'document_type_is_active' => (int) ($row['document_type_is_active'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $documentTypeIds
     */
    public function replaceFlowStepDocumentTypes(int $flowStepId, array $documentTypeIds): void
    {
        $deleteStmt = $this->db->prepare(
            'DELETE
             FROM assignment_flow_step_document_types
             WHERE flow_step_id = :flow_step_id'
        );
        $deleteStmt->execute(['flow_step_id' => $flowStepId]);

        $documentTypeIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $documentTypeIds
        ), static fn (int $id): bool => $id > 0)));

        if ($documentTypeIds === []) {
            return;
        }

        $insertStmt = $this->db->prepare(
            'INSERT INTO assignment_flow_step_document_types (
                flow_step_id,
                document_type_id,
                is_required,
                created_at,
                updated_at
             ) VALUES (
                :flow_step_id,
                :document_type_id,
                0,
                NOW(),
                NOW()
             )'
        );

        foreach ($documentTypeIds as $documentTypeId) {
            $insertStmt->execute([
                'flow_step_id' => $flowStepId,
                'document_type_id' => $documentTypeId,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function flowTransitions(int $flowId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                t.id,
                t.flow_id,
                t.from_status_id,
                t.to_status_id,
                t.transition_label,
                t.action_label,
                t.event_type,
                t.sort_order,
                t.is_active,
                t.created_at,
                t.updated_at,
                sf.code AS from_status_code,
                sf.label AS from_status_label,
                st.code AS to_status_code,
                st.label AS to_status_label
             FROM assignment_flow_transitions t
             INNER JOIN assignment_statuses sf ON sf.id = t.from_status_id
             INNER JOIN assignment_statuses st ON st.id = t.to_status_id
             WHERE t.flow_id = :flow_id
             ORDER BY t.sort_order ASC, t.id ASC'
        );
        $stmt->execute(['flow_id' => $flowId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function flowTransitionById(int $flowId, int $transitionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                flow_id,
                from_status_id,
                to_status_id,
                transition_label,
                action_label,
                event_type,
                sort_order,
                is_active
             FROM assignment_flow_transitions
             WHERE id = :id
               AND flow_id = :flow_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $transitionId,
            'flow_id' => $flowId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function createFlowTransition(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO assignment_flow_transitions (
                flow_id,
                from_status_id,
                to_status_id,
                transition_label,
                action_label,
                event_type,
                sort_order,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :flow_id,
                :from_status_id,
                :to_status_id,
                :transition_label,
                :action_label,
                :event_type,
                :sort_order,
                :is_active,
                NOW(),
                NOW()
             )'
        );
        $stmt->execute([
            'flow_id' => $data['flow_id'],
            'from_status_id' => $data['from_status_id'],
            'to_status_id' => $data['to_status_id'],
            'transition_label' => $data['transition_label'],
            'action_label' => $data['action_label'],
            'event_type' => $data['event_type'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateFlowTransition(int $transitionId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignment_flow_transitions
             SET from_status_id = :from_status_id,
                 to_status_id = :to_status_id,
                 transition_label = :transition_label,
                 action_label = :action_label,
                 event_type = :event_type,
                 sort_order = :sort_order,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id
               AND flow_id = :flow_id'
        );

        return $stmt->execute([
            'id' => $transitionId,
            'flow_id' => $data['flow_id'],
            'from_status_id' => $data['from_status_id'],
            'to_status_id' => $data['to_status_id'],
            'transition_label' => $data['transition_label'],
            'action_label' => $data['action_label'],
            'event_type' => $data['event_type'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function deleteFlowTransition(int $flowId, int $transitionId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE
             FROM assignment_flow_transitions
             WHERE id = :id
               AND flow_id = :flow_id'
        );

        return $stmt->execute([
            'id' => $transitionId,
            'flow_id' => $flowId,
        ]);
    }
}
