<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class PersonAuditRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, string|null> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateByPerson(int $personId, array $filters, int $page, int $perPage): array
    {
        ['where' => $where, 'params' => $params] = $this->buildWhereAndParams($personId, $filters);

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
    public function listByPerson(int $personId, array $filters, int $limit = 2000): array
    {
        ['where' => $where, 'params' => $params] = $this->buildWhereAndParams($personId, $filters);

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
    public function entitiesByPerson(int $personId): array
    {
        $params = [];
        $scope = $this->scopeSql($personId, $params);

        $stmt = $this->db->prepare(
            'SELECT DISTINCT a.entity
             FROM audit_log a
             WHERE ' . $scope . '
             ORDER BY a.entity ASC
             LIMIT 50'
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
    public function actionsByPerson(int $personId, ?string $entity = null): array
    {
        $params = [];
        $where = [$this->scopeSql($personId, $params)];
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
    private function scopeSql(int $personId, array &$params): string
    {
        $params['person_id_person'] = $personId;
        $params['person_id_assignment'] = $personId;
        $params['person_id_timeline'] = $personId;
        $params['person_id_document'] = $personId;
        $params['person_id_cost_plan'] = $personId;
        $params['person_id_cost_item'] = $personId;
        $params['person_id_reimbursement'] = $personId;

        return '(
            (a.entity = "person" AND a.entity_id = :person_id_person)
            OR (a.entity = "assignment" AND EXISTS (
                SELECT 1 FROM assignments ass
                WHERE ass.id = a.entity_id
                  AND ass.person_id = :person_id_assignment
            ))
            OR (a.entity = "timeline_event" AND EXISTS (
                SELECT 1 FROM timeline_events te
                WHERE te.id = a.entity_id
                  AND te.person_id = :person_id_timeline
            ))
            OR (a.entity = "document" AND EXISTS (
                SELECT 1 FROM documents d
                WHERE d.id = a.entity_id
                  AND d.person_id = :person_id_document
            ))
            OR (a.entity = "cost_plan" AND EXISTS (
                SELECT 1 FROM cost_plans cp
                WHERE cp.id = a.entity_id
                  AND cp.person_id = :person_id_cost_plan
            ))
            OR (a.entity = "cost_plan_item" AND EXISTS (
                SELECT 1 FROM cost_plan_items cpi
                WHERE cpi.id = a.entity_id
                  AND cpi.person_id = :person_id_cost_item
            ))
            OR (a.entity = "reimbursement_entry" AND EXISTS (
                SELECT 1 FROM reimbursement_entries re
                WHERE re.id = a.entity_id
                  AND re.person_id = :person_id_reimbursement
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
            $where[] = '(a.entity LIKE :filter_q OR a.action LIKE :filter_q OR COALESCE(u.name, "") LIKE :filter_q)';
            $params['filter_q'] = '%' . $q . '%';
        }

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== '') {
            $where[] = 'a.created_at >= :filter_from_date';
            $params['filter_from_date'] = $fromDate . ' 00:00:00';
        }

        $toDate = trim((string) ($filters['to_date'] ?? ''));
        if ($toDate !== '') {
            $where[] = 'a.created_at <= :filter_to_date';
            $params['filter_to_date'] = $toDate . ' 23:59:59';
        }
    }

    /**
     * @param array<string, string|null> $filters
     * @return array{where: array<int, string>, params: array<string, int|string>}
     */
    private function buildWhereAndParams(int $personId, array $filters): array
    {
        $params = [];
        $where = [$this->scopeSql($personId, $params)];
        $this->appendFilters($filters, $where, $params);

        return ['where' => $where, 'params' => $params];
    }

    /**
     * @param array<string, int|string> $params
     */
    private function bindNamedParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $name, $value, $type);
        }
    }
}
