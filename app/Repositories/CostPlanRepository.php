<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CostPlanRepository
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

    /** @return array<string, mixed>|null */
    public function activePlanByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, person_id, version_number, label, is_active, created_by, created_at, updated_at
             FROM cost_plans
             WHERE person_id = :person_id AND is_active = 1 AND deleted_at IS NULL
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $plan = $stmt->fetch();

        return $plan === false ? null : $plan;
    }

    /** @return array<string, mixed>|null */
    public function latestPlanByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, person_id, version_number, label, is_active, created_by, created_at, updated_at
             FROM cost_plans
             WHERE person_id = :person_id AND deleted_at IS NULL
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $plan = $stmt->fetch();

        return $plan === false ? null : $plan;
    }

    /** @return array<int, array<string, mixed>> */
    public function plansByPerson(int $personId, int $limit = 8): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.person_id,
                p.version_number,
                p.label,
                p.is_active,
                p.created_by,
                p.created_at,
                p.updated_at,
                u.name AS created_by_name,
                IFNULL(SUM(CASE i.cost_type
                    WHEN "mensal" THEN i.amount
                    WHEN "anual" THEN i.amount / 12
                    WHEN "eventual" THEN i.amount / 12
                    WHEN "unico" THEN i.amount / 12
                    ELSE 0
                END), 0) AS monthly_total,
                IFNULL(SUM(CASE i.cost_type
                    WHEN "mensal" THEN i.amount * 12
                    WHEN "anual" THEN i.amount
                    WHEN "eventual" THEN i.amount
                    WHEN "unico" THEN i.amount
                    ELSE 0
                END), 0) AS annualized_total,
                COUNT(i.id) AS items_count
             FROM cost_plans p
             LEFT JOIN cost_plan_items i
                ON i.cost_plan_id = p.id
               AND i.deleted_at IS NULL
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.person_id = :person_id
               AND p.deleted_at IS NULL
             GROUP BY
                p.id,
                p.person_id,
                p.version_number,
                p.label,
                p.is_active,
                p.created_by,
                p.created_at,
                p.updated_at,
                u.name
             ORDER BY p.version_number DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function itemsByPlan(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.cost_plan_id,
                i.person_id,
                i.cost_item_catalog_id,
                i.item_name,
                i.cost_type,
                i.amount,
                i.start_date,
                i.end_date,
                i.notes,
                i.created_by,
                i.created_at,
                c.cost_code AS catalog_cost_code,
                c.type_description AS catalog_type_description,
                c.macro_category AS catalog_macro_category,
                c.subcategory AS catalog_subcategory,
                c.expense_nature AS catalog_expense_nature,
                c.calculation_base AS catalog_calculation_base,
                c.charge_incidence AS catalog_charge_incidence,
                c.reimbursability AS catalog_reimbursability,
                c.predictability AS catalog_predictability,
                c.linkage_code AS catalog_linkage_code,
                c.is_reimbursable AS catalog_is_reimbursable,
                c.payment_periodicity AS catalog_payment_periodicity,
                u.name AS created_by_name
             FROM cost_plan_items i
             LEFT JOIN cost_item_catalog c ON c.id = i.cost_item_catalog_id
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.cost_plan_id = :cost_plan_id
               AND i.deleted_at IS NULL
             ORDER BY i.created_at DESC, i.id DESC'
        );
        $stmt->execute(['cost_plan_id' => $planId]);

        return $stmt->fetchAll();
    }

    public function createPlan(int $personId, int $versionNumber, ?string $label, ?int $createdBy, bool $isActive = true): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_plans (
                person_id,
                version_number,
                label,
                is_active,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :person_id,
                :version_number,
                :label,
                :is_active,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'person_id' => $personId,
            'version_number' => $versionNumber,
            'label' => $label,
            'is_active' => $isActive ? 1 : 0,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deactivateActivePlans(int $personId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_plans
             SET is_active = 0, updated_at = NOW()
             WHERE person_id = :person_id AND deleted_at IS NULL AND is_active = 1'
        );

        return $stmt->execute(['person_id' => $personId]);
    }

    public function createItem(
        int $planId,
        int $personId,
        ?int $costItemCatalogId,
        string $itemName,
        string $costType,
        string $amount,
        ?string $startDate,
        ?string $endDate,
        ?string $notes,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_plan_items (
                cost_plan_id,
                person_id,
                cost_item_catalog_id,
                item_name,
                cost_type,
                amount,
                start_date,
                end_date,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :cost_plan_id,
                :person_id,
                :cost_item_catalog_id,
                :item_name,
                :cost_type,
                :amount,
                :start_date,
                :end_date,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'cost_plan_id' => $planId,
            'person_id' => $personId,
            'cost_item_catalog_id' => $costItemCatalogId,
            'item_name' => $itemName,
            'cost_type' => $costType,
            'amount' => $amount,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function cloneItemsToPlan(int $sourcePlanId, int $targetPlanId, int $personId, ?int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_plan_items (
                cost_plan_id,
                person_id,
                cost_item_catalog_id,
                item_name,
                cost_type,
                amount,
                start_date,
                end_date,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
             )
             SELECT
                :target_plan_id,
                :person_id,
                cost_item_catalog_id,
                item_name,
                cost_type,
                amount,
                start_date,
                end_date,
                notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
             FROM cost_plan_items
             WHERE cost_plan_id = :source_plan_id
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'target_plan_id' => $targetPlanId,
            'person_id' => $personId,
            'created_by' => $createdBy,
            'source_plan_id' => $sourcePlanId,
        ]);

        return $stmt->rowCount();
    }
}
