<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $stmt = $this->db->query(
            'SELECT
                (SELECT COUNT(*)
                 FROM people p
                 WHERE p.deleted_at IS NULL) AS total_people,
                (SELECT COUNT(*)
                 FROM people p
                 WHERE p.deleted_at IS NULL AND p.status = "ativo") AS active_people,
                (SELECT COUNT(*)
                 FROM organs o
                 WHERE o.deleted_at IS NULL) AS total_organs,
                (SELECT COUNT(DISTINCT d.person_id)
                 FROM documents d
                 INNER JOIN people p ON p.id = d.person_id
                 WHERE d.deleted_at IS NULL AND p.deleted_at IS NULL) AS people_with_documents,
                (SELECT COUNT(DISTINCT cp.person_id)
                 FROM cost_plans cp
                 INNER JOIN people p ON p.id = cp.person_id
                 WHERE cp.deleted_at IS NULL AND cp.is_active = 1 AND p.deleted_at IS NULL) AS people_with_active_cost_plan,
                (SELECT COUNT(*)
                 FROM timeline_events t
                 WHERE t.event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS timeline_last_30_days,
                (SELECT COUNT(*)
                 FROM audit_log al
                 WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS audit_last_30_days,
                (SELECT IFNULL(SUM(
                    CASE
                        WHEN i.id IS NULL THEN 0
                        WHEN i.cost_type = "mensal"
                             AND (i.start_date IS NULL OR i.start_date <= LAST_DAY(CURDATE()))
                             AND (
                                i.end_date IS NULL
                                OR i.end_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             )
                             AND (COALESCE(a.effective_start_date, a.target_start_date) IS NULL OR COALESCE(a.effective_start_date, a.target_start_date) <= LAST_DAY(CURDATE()))
                             AND (
                                COALESCE(a.effective_end_date, a.requested_end_date) IS NULL
                                OR COALESCE(a.effective_end_date, a.requested_end_date) >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             )
                        THEN i.amount
                        WHEN i.cost_type = "anual"
                             AND (i.start_date IS NULL OR i.start_date <= LAST_DAY(CURDATE()))
                             AND (
                                i.end_date IS NULL
                                OR i.end_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             )
                             AND (COALESCE(a.effective_start_date, a.target_start_date) IS NULL OR COALESCE(a.effective_start_date, a.target_start_date) <= LAST_DAY(CURDATE()))
                             AND (
                                COALESCE(a.effective_end_date, a.requested_end_date) IS NULL
                                OR COALESCE(a.effective_end_date, a.requested_end_date) >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
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
                             AND (COALESCE(a.effective_start_date, a.target_start_date) IS NULL OR COALESCE(a.effective_start_date, a.target_start_date) <= LAST_DAY(CURDATE()))
                             AND (
                                COALESCE(a.effective_end_date, a.requested_end_date) IS NULL
                                OR COALESCE(a.effective_end_date, a.requested_end_date) >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             )
                        THEN i.amount
                        ELSE 0
                    END
                 ), 0)
                 FROM cost_plans cp
                 INNER JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
                 LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
                 LEFT JOIN cost_plan_items i ON i.cost_plan_id = cp.id AND i.deleted_at IS NULL
                 WHERE cp.deleted_at IS NULL
                   AND cp.is_active = 1) AS expected_reimbursement_current_month,
                (SELECT IFNULL(SUM(
                    CASE
                        WHEN r.status IN ("pendente", "pago")
                             AND r.competence_effective >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             AND r.competence_effective < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY), INTERVAL 1 MONTH)
                        THEN r.amount
                        ELSE 0
                    END
                 ), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people p ON p.id = r.person_id AND p.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL) AS actual_reimbursement_posted_current_month,
                (SELECT IFNULL(SUM(
                    CASE
                        WHEN r.status = "pago"
                             AND r.competence_effective >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY)
                             AND r.competence_effective < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE()) - 1 DAY), INTERVAL 1 MONTH)
                        THEN r.amount
                        ELSE 0
                    END
                 ), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people p ON p.id = r.person_id AND p.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL) AS actual_reimbursement_paid_current_month,
                (SELECT COUNT(*)
                 FROM cdos c
                 WHERE c.deleted_at IS NULL) AS total_cdos,
                (SELECT COUNT(*)
                 FROM cdos c
                 WHERE c.deleted_at IS NULL
                   AND c.status IN ("aberto", "parcial", "alocado")) AS open_cdos,
                (SELECT IFNULL(SUM(c.total_amount), 0)
                 FROM cdos c
                 WHERE c.deleted_at IS NULL) AS cdo_total_amount,
                (SELECT IFNULL(SUM(cp.allocated_amount), 0)
                 FROM cdo_people cp
                 INNER JOIN cdos c ON c.id = cp.cdo_id AND c.deleted_at IS NULL
                 WHERE cp.deleted_at IS NULL) AS cdo_allocated_amount'
        );

        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function statusDistribution(): array
    {
        $stmt = $this->db->query(
            'SELECT
                f.id AS flow_id,
                f.name AS flow_name,
                f.is_default AS flow_is_default,
                fs.sort_order AS flow_sort_order,
                s.code,
                s.label,
                s.sort_order AS status_sort_order,
                COUNT(p.id) AS total
             FROM assignment_flows f
             INNER JOIN assignment_flow_steps fs
               ON fs.flow_id = f.id
              AND fs.is_active = 1
             INNER JOIN assignment_statuses s
               ON s.id = fs.status_id
              AND s.is_active = 1
             LEFT JOIN assignments a
               ON a.flow_id = f.id
              AND a.current_status_id = s.id
              AND a.deleted_at IS NULL
             LEFT JOIN people p
               ON p.id = a.person_id
              AND p.deleted_at IS NULL
             WHERE f.deleted_at IS NULL
               AND f.is_active = 1
             GROUP BY
                f.id,
                f.name,
                f.is_default,
                fs.sort_order,
                s.id,
                s.code,
                s.label,
                s.sort_order
             ORDER BY
                f.is_default DESC,
                f.name ASC,
                fs.sort_order ASC,
                s.sort_order ASC,
                s.id ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function projectedPeopleStackByMonth(int $year): array
    {
        $yearValue = max(2000, min(2100, $year));
        $monthsSql = $this->monthsSql();
        $monthStartExpr = 'STR_TO_DATE(CONCAT(' . $yearValue . ', "-", LPAD(m.month_number, 2, "0"), "-01"), "%Y-%m-%d")';
        $monthEndExpr = 'LAST_DAY(' . $monthStartExpr . ')';
        $windowCondition = '(
            (
                COALESCE(a.effective_start_date, a.target_start_date) IS NOT NULL
                AND COALESCE(a.effective_start_date, a.target_start_date) <= ' . $monthEndExpr . '
                AND (COALESCE(a.effective_end_date, a.requested_end_date) IS NULL OR COALESCE(a.effective_end_date, a.requested_end_date) >= ' . $monthStartExpr . ')
            )
            OR (
                COALESCE(a.effective_start_date, a.target_start_date) IS NULL
                AND COALESCE(a.effective_end_date, a.requested_end_date) IS NULL
                AND p.status = "ativo"
            )
        )';

        $stmt = $this->db->query(
            'SELECT
                m.month_number,
                SUM(
                    CASE
                        WHEN p.id IS NULL THEN 0
                        WHEN ' . $windowCondition . ' AND p.status = "ativo" THEN 1
                        ELSE 0
                    END
                ) AS active_people,
                SUM(
                    CASE
                        WHEN p.id IS NULL THEN 0
                        WHEN ' . $windowCondition . ' AND p.status <> "ativo" THEN 1
                        ELSE 0
                    END
                ) AS pipeline_people,
                SUM(
                    CASE
                        WHEN p.id IS NULL THEN 0
                        WHEN ' . $windowCondition . ' THEN 1
                        ELSE 0
                    END
                ) AS total_people
             FROM (' . $monthsSql . ') m
             LEFT JOIN people p ON p.deleted_at IS NULL
             LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
             GROUP BY m.month_number
             ORDER BY m.month_number ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentTimeline(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                t.id,
                t.person_id,
                t.event_type,
                t.title,
                t.event_date,
                t.created_at,
                p.name AS person_name,
                p.status AS person_status,
                u.name AS created_by_name
             FROM timeline_events t
             INNER JOIN people p ON p.id = t.person_id AND p.deleted_at IS NULL
             LEFT JOIN users u ON u.id = t.created_by
             ORDER BY t.event_date DESC, t.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function executiveBottlenecks(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order,
                COUNT(*) AS cases_count,
                COUNT(DISTINCT p.organ_id) AS impacted_organs_count,
                IFNULL(AVG(' . $this->daysInStatusExpression() . '), 0) AS avg_days_in_status,
                IFNULL(MAX(' . $this->daysInStatusExpression() . '), 0) AS max_days_in_status,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "em_risco" THEN 1 ELSE 0 END) AS em_risco_count,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "vencido" THEN 1 ELSE 0 END) AS vencido_count
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             WHERE a.deleted_at IS NULL
               AND p.deleted_at IS NULL
               AND s.is_active = 1
               AND s.next_action_label IS NOT NULL
               AND (sr.id IS NULL OR sr.is_active = 1)
             GROUP BY s.code, s.label, s.sort_order
             ORDER BY vencido_count DESC, em_risco_count DESC, avg_days_in_status DESC, cases_count DESC, s.sort_order ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function executiveOrganRanking(int $limit = 10): array
    {
        $limit = max(1, min(30, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                o.id AS organ_id,
                o.name AS organ_name,
                COUNT(*) AS cases_count,
                IFNULL(AVG(' . $this->daysInStatusExpression() . '), 0) AS avg_days_in_status,
                IFNULL(MAX(' . $this->daysInStatusExpression() . '), 0) AS max_days_in_status,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "no_prazo" THEN 1 ELSE 0 END) AS no_prazo_count,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "em_risco" THEN 1 ELSE 0 END) AS em_risco_count,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "vencido" THEN 1 ELSE 0 END) AS vencido_count
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             WHERE a.deleted_at IS NULL
               AND p.deleted_at IS NULL
               AND o.deleted_at IS NULL
               AND s.is_active = 1
               AND s.next_action_label IS NOT NULL
               AND (sr.id IS NULL OR sr.is_active = 1)
             GROUP BY o.id, o.name
             ORDER BY vencido_count DESC, em_risco_count DESC, avg_days_in_status DESC, cases_count DESC, o.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function daysInStatusExpression(): string
    {
        return 'TIMESTAMPDIFF(DAY, a.updated_at, NOW())';
    }

    private function slaLevelExpression(): string
    {
        return sprintf(
            'CASE
                WHEN %s >= COALESCE(sr.overdue_days, 10) THEN "vencido"
                WHEN %s >= COALESCE(sr.warning_days, 5) THEN "em_risco"
                ELSE "no_prazo"
             END',
            $this->daysInStatusExpression(),
            $this->daysInStatusExpression()
        );
    }

    private function monthsSql(): string
    {
        return 'SELECT 1 AS month_number
                UNION ALL SELECT 2
                UNION ALL SELECT 3
                UNION ALL SELECT 4
                UNION ALL SELECT 5
                UNION ALL SELECT 6
                UNION ALL SELECT 7
                UNION ALL SELECT 8
                UNION ALL SELECT 9
                UNION ALL SELECT 10
                UNION ALL SELECT 11
                UNION ALL SELECT 12';
    }
}
