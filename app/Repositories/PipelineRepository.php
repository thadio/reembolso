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
    public function defaultFlow(): ?array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description, is_active, is_default
             FROM assignment_flows
             WHERE deleted_at IS NULL
             ORDER BY is_active DESC, is_default DESC, id ASC
             LIMIT 1'
        );
        $flow = $stmt->fetch();

        return $flow === false ? null : $flow;
    }

    /** @return array<string, mixed>|null */
    public function activeFlowById(int $flowId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, is_active, is_default
             FROM assignment_flows
             WHERE id = :id
               AND deleted_at IS NULL
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $flowId]);
        $flow = $stmt->fetch();

        return $flow === false ? null : $flow;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeFlows(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description, is_default
             FROM assignment_flows
             WHERE deleted_at IS NULL
               AND is_active = 1
             ORDER BY is_default DESC, name ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function personFlow(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.assignment_flow_id,
                f.id AS flow_id,
                f.name AS flow_name,
                f.description AS flow_description,
                f.is_active AS flow_is_active,
                f.is_default AS flow_is_default
             FROM people p
             LEFT JOIN assignment_flows f
               ON f.id = p.assignment_flow_id
              AND f.deleted_at IS NULL
             WHERE p.id = :id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function personMovementDefaults(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.organ_id,
                p.mte_destination,
                p.assignment_flow_id
             FROM people p
             WHERE p.id = :id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updatePersonFlow(int $personId, int $flowId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE people
             SET assignment_flow_id = :flow_id,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $personId,
            'flow_id' => $flowId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function initialStatus(?int $flowId = null): ?array
    {
        $targetFlowId = $flowId;
        if ($targetFlowId === null || $targetFlowId <= 0) {
            $defaultFlow = $this->defaultFlow();
            $targetFlowId = $defaultFlow !== null ? (int) ($defaultFlow['id'] ?? 0) : 0;
        }

        if ($targetFlowId > 0) {
            $stmt = $this->db->prepare(
                'SELECT
                    s.id,
                    s.code,
                    s.label,
                    fs.sort_order,
                    s.next_action_label,
                    s.event_type,
                    fs.node_kind
                 FROM assignment_flow_steps fs
                 INNER JOIN assignment_statuses s ON s.id = fs.status_id
                 WHERE fs.flow_id = :flow_id
                   AND fs.is_active = 1
                   AND s.is_active = 1
                 ORDER BY fs.is_initial DESC, fs.sort_order ASC, fs.id ASC
                 LIMIT 1'
            );
            $stmt->execute(['flow_id' => $targetFlowId]);
            $status = $stmt->fetch();
            if ($status !== false) {
                return $status;
            }
        }

        $fallback = $this->db->prepare(
            'SELECT id, code, label, sort_order, next_action_label, event_type, "activity" AS node_kind
             FROM assignment_statuses
             WHERE code = :code
               AND is_active = 1
             LIMIT 1'
        );
        $fallback->execute(['code' => 'interessado']);
        $row = $fallback->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function nextStatus(int $sortOrder, ?int $flowId = null): ?array
    {
        if ($flowId !== null && $flowId > 0) {
            $statuses = $this->statusesForFlow($flowId);
            foreach ($statuses as $status) {
                if ((int) ($status['sort_order'] ?? 0) > $sortOrder) {
                    return $status;
                }
            }
        }

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
            'SELECT id, code, label, sort_order, next_action_label, event_type, is_active
             FROM assignment_statuses
             WHERE is_active = 1
             ORDER BY sort_order ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function statusesForFlow(int $flowId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                s.id,
                s.code,
                s.label,
                fs.sort_order,
                s.next_action_label,
                s.event_type,
                fs.node_kind,
                fs.is_initial,
                fs.requires_evidence_close,
                fs.step_tags
             FROM assignment_flow_steps fs
             INNER JOIN assignment_statuses s ON s.id = fs.status_id
             WHERE fs.flow_id = :flow_id
               AND fs.is_active = 1
               AND s.is_active = 1
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
                fs.id,
                fs.flow_id,
                fs.status_id,
                fs.node_kind,
                fs.sort_order,
                fs.is_initial,
                fs.is_active,
                fs.requires_evidence_close,
                fs.step_tags,
                s.code AS status_code,
                s.label AS status_label
             FROM assignment_flow_steps fs
             INNER JOIN assignment_statuses s ON s.id = fs.status_id
             WHERE fs.flow_id = :flow_id
               AND fs.status_id = :status_id
               AND fs.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'flow_id' => $flowId,
            'status_id' => $statusId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function expectedDocumentTypesForFlowStatusCode(int $flowId, string $statusCode): array
    {
        $normalizedCode = trim($statusCode);
        if ($flowId <= 0 || $normalizedCode === '') {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                dt.id,
                dt.name,
                dt.description,
                m.is_required
             FROM assignment_flow_steps fs
             INNER JOIN assignment_statuses s ON s.id = fs.status_id
             INNER JOIN assignment_flow_step_document_types m ON m.flow_step_id = fs.id
             INNER JOIN document_types dt ON dt.id = m.document_type_id
             WHERE fs.flow_id = :flow_id
               AND s.code = :status_code
               AND fs.is_active = 1
               AND dt.is_active = 1
             ORDER BY m.is_required DESC, dt.name ASC, dt.id ASC'
        );
        $stmt->execute([
            'flow_id' => $flowId,
            'status_code' => $normalizedCode,
        ]);

        return $stmt->fetchAll();
    }

    public function hasEvidenceForStatus(int $personId, string $statusCode): bool
    {
        $normalizedCode = trim($statusCode);
        if ($personId <= 0 || $normalizedCode === '') {
            return false;
        }

        $escapedCode = str_replace(['\\', '"'], ['\\\\', '\\"'], $normalizedCode);
        $pattern = '%"pipeline_status_code":"' . $escapedCode . '"%';

        $stmt = $this->db->prepare(
            'SELECT
                EXISTS (
                    SELECT 1
                    FROM timeline_events t
                    LEFT JOIN (
                        SELECT timeline_event_id, COUNT(*) AS total_attachments
                        FROM timeline_event_attachments
                        GROUP BY timeline_event_id
                    ) ta ON ta.timeline_event_id = t.id
                    LEFT JOIN (
                        SELECT timeline_event_id, COUNT(*) AS total_links
                        FROM timeline_event_links
                        GROUP BY timeline_event_id
                    ) tl ON tl.timeline_event_id = t.id
                    WHERE t.person_id = :person_id
                      AND t.metadata LIKE :status_pattern
                      AND (
                        IFNULL(ta.total_attachments, 0) > 0
                        OR IFNULL(tl.total_links, 0) > 0
                      )
                    LIMIT 1
                ) AS has_evidence'
        );
        $stmt->execute([
            'person_id' => $personId,
            'status_pattern' => $pattern,
        ]);
        $row = $stmt->fetch();

        return (int) ($row['has_evidence'] ?? 0) === 1;
    }

    /** @return array<int, array<string, mixed>> */
    public function transitionsFromStatus(int $flowId, int $fromStatusId): array
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
                sf.code AS from_code,
                sf.label AS from_label,
                st.code AS to_code,
                st.label AS to_label,
                fs_to.node_kind AS to_node_kind
             FROM assignment_flow_transitions t
             INNER JOIN assignment_statuses sf ON sf.id = t.from_status_id
             INNER JOIN assignment_statuses st ON st.id = t.to_status_id
             LEFT JOIN assignment_flow_steps fs_to
               ON fs_to.flow_id = t.flow_id
              AND fs_to.status_id = t.to_status_id
              AND fs_to.is_active = 1
             WHERE t.flow_id = :flow_id
               AND t.from_status_id = :from_status_id
               AND t.is_active = 1
             ORDER BY t.sort_order ASC, t.id ASC'
        );
        $stmt->execute([
            'flow_id' => $flowId,
            'from_status_id' => $fromStatusId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function transitionById(int $flowId, int $transitionId): ?array
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
                sf.code AS from_code,
                sf.label AS from_label,
                st.code AS to_code,
                st.label AS to_label,
                fs_to.node_kind AS to_node_kind
             FROM assignment_flow_transitions t
             INNER JOIN assignment_statuses sf ON sf.id = t.from_status_id
             INNER JOIN assignment_statuses st ON st.id = t.to_status_id
             LEFT JOIN assignment_flow_steps fs_to
               ON fs_to.flow_id = t.flow_id
              AND fs_to.status_id = t.to_status_id
              AND fs_to.is_active = 1
             WHERE t.id = :id
               AND t.flow_id = :flow_id
               AND t.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $transitionId,
            'flow_id' => $flowId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function transitionsForFlow(int $flowId): array
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
                sf.code AS from_code,
                sf.label AS from_label,
                st.code AS to_code,
                st.label AS to_label
             FROM assignment_flow_transitions t
             INNER JOIN assignment_statuses sf ON sf.id = t.from_status_id
             INNER JOIN assignment_statuses st ON st.id = t.to_status_id
             WHERE t.flow_id = :flow_id
               AND t.is_active = 1
             ORDER BY t.sort_order ASC, t.id ASC'
        );
        $stmt->execute(['flow_id' => $flowId]);

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
                a.flow_id,
                a.modality_id,
                a.movement_direction,
                a.financial_nature,
                a.counterparty_organ_id,
                a.origin_mte_destination_id,
                a.destination_mte_destination_id,
                a.requested_end_date,
                a.effective_end_date,
                a.termination_reason,
                a.movement_code,
                a.assigned_user_id,
                a.mte_unit,
                a.target_start_date,
                a.effective_start_date,
                a.current_status_id,
                a.priority_level,
                a.created_at,
                a.updated_at,
                s.code AS current_status_code,
                s.label AS current_status_label,
                COALESCE(fs.sort_order, s.sort_order) AS current_status_order,
                COALESCE(fs.node_kind, "activity") AS current_node_kind,
                s.next_action_label AS current_next_action_label,
                s.event_type AS current_event_type,
                f.name AS flow_name,
                f.description AS flow_description,
                f.is_default AS flow_is_default,
                m.name AS modality_name,
                au.name AS assigned_user_name,
                co.name AS counterparty_organ_name,
                omd.name AS origin_mte_destination_name,
                dmd.name AS destination_mte_destination_name
             FROM assignments a
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN assignment_flow_steps fs
               ON fs.flow_id = a.flow_id
              AND fs.status_id = a.current_status_id
              AND fs.is_active = 1
             LEFT JOIN assignment_flows f ON f.id = a.flow_id AND f.deleted_at IS NULL
             LEFT JOIN modalities m ON m.id = a.modality_id
             LEFT JOIN users au ON au.id = a.assigned_user_id AND au.deleted_at IS NULL
             LEFT JOIN organs co ON co.id = a.counterparty_organ_id AND co.deleted_at IS NULL
             LEFT JOIN mte_destinations omd ON omd.id = a.origin_mte_destination_id AND omd.deleted_at IS NULL
             LEFT JOIN mte_destinations dmd ON dmd.id = a.destination_mte_destination_id AND dmd.deleted_at IS NULL
             WHERE a.person_id = :person_id AND a.deleted_at IS NULL
             ORDER BY a.updated_at DESC, a.id DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $assignment = $stmt->fetch();

        return $assignment === false ? null : $assignment;
    }

    public function createAssignment(
        int $personId,
        ?int $flowId,
        ?int $modalityId,
        int $statusId,
        ?int $assignedUserId = null,
        string $priorityLevel = 'normal',
        ?string $movementDirection = null,
        ?string $financialNature = null,
        ?int $counterpartyOrganId = null,
        ?int $originMteDestinationId = null,
        ?int $destinationMteDestinationId = null,
        ?string $movementCode = null,
        ?string $targetStartDate = null,
        ?string $requestedEndDate = null
    ): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO assignments (
                person_id,
                flow_id,
                modality_id,
                movement_direction,
                financial_nature,
                counterparty_organ_id,
                origin_mte_destination_id,
                destination_mte_destination_id,
                movement_code,
                assigned_user_id,
                mte_unit,
                target_start_date,
                effective_start_date,
                requested_end_date,
                effective_end_date,
                current_status_id,
                priority_level,
                created_at,
                updated_at
            ) VALUES (
                :person_id,
                :flow_id,
                :modality_id,
                :movement_direction,
                :financial_nature,
                :counterparty_organ_id,
                :origin_mte_destination_id,
                :destination_mte_destination_id,
                :movement_code,
                :assigned_user_id,
                NULL,
                :target_start_date,
                NULL,
                :requested_end_date,
                NULL,
                :current_status_id,
                :priority_level,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'person_id' => $personId,
            'flow_id' => $flowId,
            'modality_id' => $modalityId,
            'movement_direction' => $movementDirection ?? 'entrada_mte',
            'financial_nature' => $financialNature ?? 'despesa_reembolso',
            'counterparty_organ_id' => $counterpartyOrganId,
            'origin_mte_destination_id' => $originMteDestinationId,
            'destination_mte_destination_id' => $destinationMteDestinationId,
            'movement_code' => $movementCode,
            'assigned_user_id' => $assignedUserId,
            'target_start_date' => $targetStartDate,
            'requested_end_date' => $requestedEndDate,
            'current_status_id' => $statusId,
            'priority_level' => $priorityLevel,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateAssignmentSchedule(int $assignmentId, ?string $targetStartDate, ?string $requestedEndDate): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET target_start_date = :target_start_date,
                 requested_end_date = :requested_end_date,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'target_start_date' => $targetStartDate,
            'requested_end_date' => $requestedEndDate,
        ]);
    }

    public function updateAssignmentMovementContext(
        int $assignmentId,
        string $movementDirection,
        string $financialNature,
        ?int $counterpartyOrganId,
        ?int $originMteDestinationId,
        ?int $destinationMteDestinationId
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET movement_direction = :movement_direction,
                 financial_nature = :financial_nature,
                 counterparty_organ_id = :counterparty_organ_id,
                 origin_mte_destination_id = :origin_mte_destination_id,
                 destination_mte_destination_id = :destination_mte_destination_id,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'movement_direction' => $movementDirection,
            'financial_nature' => $financialNature,
            'counterparty_organ_id' => $counterpartyOrganId,
            'origin_mte_destination_id' => $originMteDestinationId,
            'destination_mte_destination_id' => $destinationMteDestinationId,
        ]);
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

    public function updateAssignmentStatus(
        int $assignmentId,
        int $statusId,
        ?string $effectiveStartDate,
        ?string $effectiveEndDate = null
    ): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET current_status_id = :status_id,
                 effective_start_date = COALESCE(:effective_start_date, effective_start_date),
                 effective_end_date = COALESCE(:effective_end_date, effective_end_date),
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'status_id' => $statusId,
            'effective_start_date' => $effectiveStartDate,
            'effective_end_date' => $effectiveEndDate,
        ]);
    }

    public function updateAssignmentFlow(int $assignmentId, int $flowId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET flow_id = :flow_id,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'flow_id' => $flowId,
        ]);
    }

    public function updateAssignmentQueue(int $assignmentId, ?int $assignedUserId, string $priorityLevel): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE assignments
             SET assigned_user_id = :assigned_user_id,
                 priority_level = :priority_level,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $assignmentId,
            'assigned_user_id' => $assignedUserId,
            'priority_level' => $priorityLevel,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAssignableUsers(int $limit = 400): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name
             FROM users
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(4000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM users
             WHERE id = :id AND deleted_at IS NULL AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch() !== false;
    }

    public function organExists(int $organId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM organs
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $organId]);

        return $stmt->fetch() !== false;
    }

    public function findMteOrganId(): ?int
    {
        $byAcronym = $this->db->query(
            'SELECT id
             FROM organs
             WHERE deleted_at IS NULL
               AND UPPER(TRIM(IFNULL(acronym, \'\'))) = \'MTE\'
             ORDER BY id ASC
             LIMIT 1'
        );
        $row = $byAcronym === false ? false : $byAcronym->fetch();
        if ($row !== false) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $byName = $this->db->prepare(
            'SELECT id
             FROM organs
             WHERE deleted_at IS NULL
               AND LOWER(IFNULL(name, \'\')) LIKE :name_like
             ORDER BY id ASC
             LIMIT 1'
        );
        $byName->execute(['name_like' => '%trabalho%emprego%']);
        $nameRow = $byName->fetch();
        if ($nameRow === false) {
            return null;
        }

        $id = (int) ($nameRow['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function mteDestinationExistsById(int $destinationId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM mte_destinations
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $destinationId]);

        return $stmt->fetch() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeChecklistTemplatesForCaseType(string $caseType): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                case_type,
                code,
                label,
                description,
                is_required,
                sort_order
             FROM assignment_checklist_templates
             WHERE is_active = 1
               AND case_type IN ("geral", :case_type)
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['case_type' => $caseType]);

        return $stmt->fetchAll();
    }

    public function upsertChecklistItemFromTemplate(
        int $assignmentId,
        int $templateId,
        string $caseType,
        string $code,
        string $label,
        ?string $description,
        int $isRequired
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO assignment_checklist_items (
                assignment_id,
                template_id,
                case_type,
                item_code,
                item_label,
                item_description,
                is_required,
                created_at,
                updated_at
             ) VALUES (
                :assignment_id,
                :template_id,
                :case_type,
                :item_code,
                :item_label,
                :item_description,
                :is_required,
                NOW(),
                NOW()
             )
             ON DUPLICATE KEY UPDATE
                template_id = VALUES(template_id),
                item_label = VALUES(item_label),
                item_description = VALUES(item_description),
                is_required = VALUES(is_required),
                updated_at = NOW()'
        );

        return $stmt->execute([
            'assignment_id' => $assignmentId,
            'template_id' => $templateId,
            'case_type' => $caseType,
            'item_code' => $code,
            'item_label' => $label,
            'item_description' => $description,
            'is_required' => $isRequired,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function checklistItemsByAssignment(int $assignmentId, string $caseType): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.assignment_id,
                i.template_id,
                i.case_type,
                i.item_code,
                i.item_label,
                i.item_description,
                i.is_required,
                i.is_done,
                i.done_at,
                i.done_by,
                i.notes,
                i.created_at,
                i.updated_at,
                COALESCE(t.sort_order, 9999) AS template_sort_order,
                u.name AS done_by_name
             FROM assignment_checklist_items i
             LEFT JOIN assignment_checklist_templates t ON t.id = i.template_id
             LEFT JOIN users u ON u.id = i.done_by AND u.deleted_at IS NULL
             WHERE i.assignment_id = :assignment_id
               AND i.case_type IN ("geral", :case_type)
             ORDER BY template_sort_order ASC, i.id ASC'
        );
        $stmt->execute([
            'assignment_id' => $assignmentId,
            'case_type' => $caseType,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function checklistItemById(int $assignmentId, int $itemId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.assignment_id,
                i.template_id,
                i.case_type,
                i.item_code,
                i.item_label,
                i.item_description,
                i.is_required,
                i.is_done,
                i.done_at,
                i.done_by,
                i.notes,
                i.created_at,
                i.updated_at,
                u.name AS done_by_name
             FROM assignment_checklist_items i
             LEFT JOIN users u ON u.id = i.done_by AND u.deleted_at IS NULL
             WHERE i.id = :id
               AND i.assignment_id = :assignment_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $itemId,
            'assignment_id' => $assignmentId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateChecklistItemStatus(
        int $assignmentId,
        int $itemId,
        bool $isDone,
        ?int $doneBy
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE assignment_checklist_items
             SET is_done = :is_done,
                 done_at = CASE WHEN :is_done = 1 THEN NOW() ELSE NULL END,
                 done_by = CASE WHEN :is_done = 1 THEN :done_by ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id
               AND assignment_id = :assignment_id'
        );

        return $stmt->execute([
            'is_done' => $isDone ? 1 : 0,
            'done_by' => $doneBy,
            'id' => $itemId,
            'assignment_id' => $assignmentId,
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
        $linksByEvent = $this->linksByEventIds($eventIds);

        foreach ($items as &$item) {
            $eventId = (int) ($item['id'] ?? 0);
            $item['attachments'] = $attachmentsByEvent[$eventId] ?? [];
            $item['links'] = $linksByEvent[$eventId] ?? [];
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

    public function createEventLink(
        int $eventId,
        int $personId,
        string $url,
        ?string $label,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO timeline_event_links (
                timeline_event_id,
                person_id,
                url,
                label,
                created_by,
                created_at
             ) VALUES (
                :timeline_event_id,
                :person_id,
                :url,
                :label,
                :created_by,
                NOW()
             )'
        );

        $stmt->execute([
            'timeline_event_id' => $eventId,
            'person_id' => $personId,
            'url' => $url,
            'label' => $label,
            'created_by' => $createdBy,
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

    /**
     * @param array<int, int> $eventIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function linksByEventIds(array $eventIds): array
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
                url,
                label,
                created_by,
                created_at
             FROM timeline_event_links
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
        $linksByEvent = $this->linksByEventIds($eventIds);

        foreach ($items as &$item) {
            $eventId = (int) ($item['id'] ?? 0);
            $item['attachments'] = $attachmentsByEvent[$eventId] ?? [];
            $item['links'] = $linksByEvent[$eventId] ?? [];
        }
        unset($item);

        return $items;
    }
}
