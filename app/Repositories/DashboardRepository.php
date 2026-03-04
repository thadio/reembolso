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
                             AND (i.end_date IS NULL OR i.end_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01"))
                        THEN i.amount
                        WHEN i.cost_type = "anual"
                             AND (i.start_date IS NULL OR i.start_date <= LAST_DAY(CURDATE()))
                             AND (i.end_date IS NULL OR i.end_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01"))
                        THEN i.amount / 12
                        WHEN i.cost_type = "unico"
                             AND (
                               (i.start_date IS NOT NULL AND DATE_FORMAT(i.start_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m"))
                               OR (i.start_date IS NULL AND DATE_FORMAT(i.created_at, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m"))
                             )
                        THEN i.amount
                        ELSE 0
                    END
                 ), 0)
                 FROM cost_plans cp
                 INNER JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
                 LEFT JOIN cost_plan_items i ON i.cost_plan_id = cp.id AND i.deleted_at IS NULL
                 WHERE cp.deleted_at IS NULL
                   AND cp.is_active = 1) AS expected_reimbursement_current_month,
                (SELECT IFNULL(SUM(
                    CASE
                        WHEN r.status IN ("pendente", "pago")
                             AND DATE_FORMAT(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at)), "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")
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
                             AND DATE_FORMAT(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at)), "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")
                        THEN r.amount
                        ELSE 0
                    END
                 ), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people p ON p.id = r.person_id AND p.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL) AS actual_reimbursement_paid_current_month'
        );

        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function statusDistribution(): array
    {
        $stmt = $this->db->query(
            'SELECT
                s.code,
                s.label,
                s.sort_order,
                COUNT(p.id) AS total
             FROM assignment_statuses s
             LEFT JOIN people p
               ON p.status = s.code
              AND p.deleted_at IS NULL
             WHERE s.is_active = 1
             GROUP BY s.id, s.code, s.label, s.sort_order
             ORDER BY s.sort_order ASC'
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
}
