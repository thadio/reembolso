<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReconciliationRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function activePlanByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, person_id, version_number, label, is_active, created_at, updated_at
             FROM cost_plans
             WHERE person_id = :person_id
               AND is_active = 1
               AND deleted_at IS NULL
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function activePlanItemsByPerson(int $personId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.cost_plan_id,
                i.person_id,
                i.item_name,
                i.cost_type,
                i.amount,
                i.start_date,
                i.end_date,
                i.created_at
             FROM cost_plan_items i
             INNER JOIN cost_plans cp
                ON cp.id = i.cost_plan_id
               AND cp.deleted_at IS NULL
               AND cp.is_active = 1
             WHERE i.person_id = :person_id_item
               AND i.deleted_at IS NULL
               AND cp.person_id = :person_id_plan
             ORDER BY i.created_at ASC, i.id ASC'
        );
        $stmt->execute([
            'person_id_item' => $personId,
            'person_id_plan' => $personId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function reimbursementEntriesByPerson(int $personId, int $limit = 500): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.person_id,
                r.entry_type,
                r.status,
                r.amount,
                r.reference_month,
                r.due_date,
                r.paid_at,
                r.created_at,
                r.updated_at,
                r.title
             FROM reimbursement_entries r
             WHERE r.person_id = :person_id
               AND r.deleted_at IS NULL
               AND r.status IN ("pendente", "pago")
             ORDER BY
               r.competence_effective DESC,
               r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(2000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
