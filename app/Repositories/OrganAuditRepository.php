<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class OrganAuditRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, string|null> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateByOrgan(int $organId, array $filters, int $page, int $perPage): array
    {
        ['where' => $where, 'params' => $params] = $this->buildWhereAndParams($organId, $filters);

        $countSql = sprintf(
            'SELECT COUNT(*) AS total
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE %s',
            implode(' AND ', $where)
        );

        $countStmt = $this->db->prepare($countSql);
        $this->bindNamedParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $itemsSql = sprintf(
            'SELECT
                a.id,
                a.entity,
                a.entity_id,
                a.action,
                a.before_data,
                a.after_data,
                a.metadata,
                a.user_id,
                a.ip,
                a.user_agent,
                a.created_at,
                u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE %s
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit OFFSET :offset',
            implode(' AND ', $where)
        );

        $itemsStmt = $this->db->prepare($itemsSql);
        $this->bindNamedParams($itemsStmt, $params);
        $itemsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $itemsStmt->execute();

        return [
            'items' => $itemsStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * @param array<string, string|null> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listByOrgan(int $organId, array $filters, int $limit = 2000): array
    {
        ['where' => $where, 'params' => $params] = $this->buildWhereAndParams($organId, $filters);

        $sql = sprintf(
            'SELECT
                a.id,
                a.entity,
                a.entity_id,
                a.action,
                a.before_data,
                a.after_data,
                a.metadata,
                a.user_id,
                a.ip,
                a.user_agent,
                a.created_at,
                u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE %s
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit',
            implode(' AND ', $where)
        );

        $stmt = $this->db->prepare($sql);
        $this->bindNamedParams($stmt, $params);
        $stmt->bindValue(':limit', max(1, min(5000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, string> */
    public function entitiesByOrgan(int $organId): array
    {
        $params = [];
        $scope = $this->scopeSql($organId, $params);

        $stmt = $this->db->prepare(
            'SELECT DISTINCT a.entity
             FROM audit_log a
             WHERE ' . $scope . '
             ORDER BY a.entity ASC
             LIMIT 60'
        );
        $this->bindNamedParams($stmt, $params);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['entity'] ?? ''),
            $rows
        )));
    }

    /** @return array<int, string> */
    public function actionsByOrgan(int $organId, ?string $entity = null): array
    {
        $params = [];
        $where = [$this->scopeSql($organId, $params)];
        if ($entity !== null && trim($entity) !== '') {
            $where[] = 'a.entity = :filter_entity_actions';
            $params['filter_entity_actions'] = trim($entity);
        }

        $stmt = $this->db->prepare(
            'SELECT DISTINCT a.action
             FROM audit_log a
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.action ASC
             LIMIT 120'
        );
        $this->bindNamedParams($stmt, $params);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['action'] ?? ''),
            $rows
        )));
    }

    /**
     * @param array<string, int|string> $params
     */
    private function scopeSql(int $organId, array &$params): string
    {
        $params['organ_id_organ'] = $organId;
        $params['organ_id_person'] = $organId;
        $params['organ_id_assignment'] = $organId;
        $params['organ_id_assignment_checklist'] = $organId;
        $params['organ_id_assignment_checklist_item'] = $organId;
        $params['organ_id_timeline'] = $organId;
        $params['organ_id_document'] = $organId;
        $params['organ_id_cost_plan'] = $organId;
        $params['organ_id_cost_item'] = $organId;
        $params['organ_id_reimbursement'] = $organId;
        $params['organ_id_process_comment'] = $organId;
        $params['organ_id_process_admin_timeline'] = $organId;
        $params['organ_id_pending_item'] = $organId;

        return '(
            (a.entity = "organ" AND a.entity_id = :organ_id_organ)
            OR (a.entity = "person" AND EXISTS (
                SELECT 1 FROM people p
                WHERE p.id = a.entity_id
                  AND p.organ_id = :organ_id_person
            ))
            OR (a.entity = "assignment" AND EXISTS (
                SELECT 1
                FROM assignments ass
                INNER JOIN people p ON p.id = ass.person_id
                WHERE ass.id = a.entity_id
                  AND p.organ_id = :organ_id_assignment
            ))
            OR (a.entity = "assignment_checklist" AND EXISTS (
                SELECT 1
                FROM assignments ass
                INNER JOIN people p ON p.id = ass.person_id
                WHERE ass.id = a.entity_id
                  AND p.organ_id = :organ_id_assignment_checklist
            ))
            OR (a.entity = "assignment_checklist_item" AND EXISTS (
                SELECT 1
                FROM assignment_checklist_items aci
                INNER JOIN assignments ass ON ass.id = aci.assignment_id
                INNER JOIN people p ON p.id = ass.person_id
                WHERE aci.id = a.entity_id
                  AND p.organ_id = :organ_id_assignment_checklist_item
            ))
            OR (a.entity = "timeline_event" AND EXISTS (
                SELECT 1
                FROM timeline_events te
                INNER JOIN people p ON p.id = te.person_id
                WHERE te.id = a.entity_id
                  AND p.organ_id = :organ_id_timeline
            ))
            OR (a.entity = "document" AND EXISTS (
                SELECT 1
                FROM documents d
                INNER JOIN people p ON p.id = d.person_id
                WHERE d.id = a.entity_id
                  AND p.organ_id = :organ_id_document
            ))
            OR (a.entity = "cost_plan" AND EXISTS (
                SELECT 1
                FROM cost_plans cp
                INNER JOIN people p ON p.id = cp.person_id
                WHERE cp.id = a.entity_id
                  AND p.organ_id = :organ_id_cost_plan
            ))
            OR (a.entity = "cost_plan_item" AND EXISTS (
                SELECT 1
                FROM cost_plan_items cpi
                INNER JOIN people p ON p.id = cpi.person_id
                WHERE cpi.id = a.entity_id
                  AND p.organ_id = :organ_id_cost_item
            ))
            OR (a.entity = "reimbursement_entry" AND EXISTS (
                SELECT 1
                FROM reimbursement_entries re
                INNER JOIN people p ON p.id = re.person_id
                WHERE re.id = a.entity_id
                  AND p.organ_id = :organ_id_reimbursement
            ))
            OR (a.entity = "process_comment" AND EXISTS (
                SELECT 1
                FROM process_comments pc
                INNER JOIN people p ON p.id = pc.person_id
                WHERE pc.id = a.entity_id
                  AND p.organ_id = :organ_id_process_comment
            ))
            OR (a.entity = "process_admin_timeline_note" AND EXISTS (
                SELECT 1
                FROM process_admin_timeline_notes patn
                INNER JOIN people p ON p.id = patn.person_id
                WHERE patn.id = a.entity_id
                  AND p.organ_id = :organ_id_process_admin_timeline
            ))
            OR (a.entity = "analyst_pending_item" AND EXISTS (
                SELECT 1
                FROM analyst_pending_items api
                INNER JOIN people p ON p.id = api.person_id
                WHERE api.id = a.entity_id
                  AND p.organ_id = :organ_id_pending_item
            ))
        )';
    }

    /**
     * @param array<string, string|null> $filters
     * @param array<int, string> $where
     * @param array<string, int|string> $params
     */
    private function appendFilters(array $filters, array &$where, array &$params): void
    {
        $entity = trim((string) ($filters['entity'] ?? ''));
        if ($entity !== '') {
            $where[] = 'a.entity = :filter_entity';
            $params['filter_entity'] = $entity;
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'a.action LIKE :filter_action';
            $params['filter_action'] = '%' . $action . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(a.entity LIKE :filter_q OR a.action LIKE :filter_q OR u.name LIKE :filter_q OR a.ip LIKE :filter_q)';
            $params['filter_q'] = '%' . $q . '%';
        }

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== '') {
            $where[] = 'DATE(a.created_at) >= :filter_from_date';
            $params['filter_from_date'] = $fromDate;
        }

        $toDate = trim((string) ($filters['to_date'] ?? ''));
        if ($toDate !== '') {
            $where[] = 'DATE(a.created_at) <= :filter_to_date';
            $params['filter_to_date'] = $toDate;
        }
    }

    /**
     * @param array<string, string|null> $filters
     * @return array{where: array<int, string>, params: array<string, int|string>}
     */
    private function buildWhereAndParams(int $organId, array $filters): array
    {
        $params = [];
        $where = [$this->scopeSql($organId, $params)];
        $this->appendFilters($filters, $where, $params);

        return ['where' => $where, 'params' => $params];
    }

    /**
     * @param array<string, int|string> $params
     */
    private function bindNamedParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
    }
}

