<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class BudgetRepository
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
    public function findCycleByYear(int $year): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                bc.id,
                bc.cycle_year,
                bc.annual_factor,
                bc.total_budget,
                bc.status,
                bc.notes,
                bc.created_by,
                bc.created_at,
                bc.updated_at,
                u.name AS created_by_name
             FROM budget_cycles bc
             LEFT JOIN users u ON u.id = bc.created_by
             WHERE bc.cycle_year = :cycle_year
               AND bc.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['cycle_year' => $year]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function estimatedTotalFromCdos(int $year): string
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $stmt = $this->db->prepare(
            'SELECT IFNULL(SUM(c.total_amount), 0) AS total
             FROM cdos c
             WHERE c.deleted_at IS NULL
               AND c.period_start <= :year_end
               AND c.period_end >= :year_start'
        );
        $stmt->execute([
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ]);

        $value = (float) ($stmt->fetch()['total'] ?? 0);

        return number_format($value, 2, '.', '');
    }

    public function createCycle(int $year, float $annualFactor, string $totalBudget, ?int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO budget_cycles (
                cycle_year,
                annual_factor,
                total_budget,
                status,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :cycle_year,
                :annual_factor,
                :total_budget,
                "aberto",
                NULL,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'cycle_year' => $year,
            'annual_factor' => number_format($annualFactor, 2, '.', ''),
            'total_budget' => $totalBudget,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed> */
    public function ensureCycle(int $year, float $annualFactor = 13.30, ?int $createdBy = null): array
    {
        $existing = $this->findCycleByYear($year);
        if ($existing !== null) {
            return $existing;
        }

        $estimated = $this->estimatedTotalFromCdos($year);
        $this->createCycle($year, $annualFactor, $estimated, $createdBy);

        $cycle = $this->findCycleByYear($year);

        return $cycle ?? [
            'id' => 0,
            'cycle_year' => $year,
            'annual_factor' => number_format($annualFactor, 2, '.', ''),
            'total_budget' => $estimated,
            'status' => 'aberto',
            'notes' => null,
            'created_by' => $createdBy,
            'created_at' => null,
            'updated_at' => null,
            'created_by_name' => null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name
             FROM organs
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
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

    /** @return array<int, array<string, mixed>> */
    public function orgParameters(): array
    {
        $stmt = $this->db->query(
            'SELECT
                p.id,
                p.organ_id,
                p.avg_monthly_cost,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name,
                u.name AS updated_by_name
             FROM org_cost_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             LEFT JOIN users u ON u.id = p.updated_by
             WHERE p.deleted_at IS NULL
             ORDER BY o.name ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findOrgParameterByOrgan(int $organId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.organ_id,
                p.avg_monthly_cost,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name
             FROM org_cost_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             WHERE p.organ_id = :organ_id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['organ_id' => $organId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function upsertOrgParameter(int $organId, string $avgMonthlyCost, ?string $notes, ?int $updatedBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO org_cost_parameters (
                organ_id,
                avg_monthly_cost,
                notes,
                updated_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :organ_id,
                :avg_monthly_cost,
                :notes,
                :updated_by,
                NOW(),
                NOW(),
                NULL
            )
            ON DUPLICATE KEY UPDATE
                avg_monthly_cost = VALUES(avg_monthly_cost),
                notes = VALUES(notes),
                updated_by = VALUES(updated_by),
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'organ_id' => $organId,
            'avg_monthly_cost' => $avgMonthlyCost,
            'notes' => $notes,
            'updated_by' => $updatedBy,
        ]);

        $row = $this->findOrgParameterByOrgan($organId);

        return (int) ($row['id'] ?? 0);
    }

    public function globalAverageMonthlyCost(): float
    {
        $stmt = $this->db->query(
            'SELECT IFNULL(AVG(src.person_monthly), 0) AS avg_monthly
             FROM (
                SELECT
                    cp.person_id,
                    SUM(
                        CASE
                            WHEN cpi.cost_type = "mensal"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(CURDATE()))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01"))
                            THEN cpi.amount
                            WHEN cpi.cost_type = "anual"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(CURDATE()))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01"))
                            THEN cpi.amount / 12
                            WHEN cpi.cost_type = "unico"
                                 AND (
                                    (cpi.start_date IS NOT NULL AND DATE_FORMAT(cpi.start_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m"))
                                    OR (cpi.start_date IS NULL AND DATE_FORMAT(cpi.created_at, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m"))
                                 )
                            THEN cpi.amount
                            ELSE 0
                        END
                    ) AS person_monthly
                FROM cost_plans cp
                INNER JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
                INNER JOIN cost_plan_items cpi ON cpi.cost_plan_id = cp.id AND cpi.deleted_at IS NULL
                WHERE cp.deleted_at IS NULL
                  AND cp.is_active = 1
                GROUP BY cp.person_id
             ) src
             WHERE src.person_monthly > 0'
        );

        return (float) ($stmt->fetch()['avg_monthly'] ?? 0.0);
    }

    /** @return array<string, mixed> */
    public function financialSnapshot(int $year): array
    {
        $nextYear = $year + 1;
        $nextYearStart = sprintf('%04d-01-01', $nextYear);
        $nextYearEnd = sprintf('%04d-12-31', $nextYear);

        $stmt = $this->db->prepare(
            'SELECT
                (SELECT IFNULL(SUM(p.amount), 0)
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id AND i.deleted_at IS NULL
                 WHERE p.deleted_at IS NULL
                   AND YEAR(p.payment_date) = :year_paid_invoices) AS paid_invoices_amount,
                (SELECT IFNULL(SUM(r.amount), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL
                   AND r.status = "pago"
                   AND YEAR(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at))) = :year_paid_reimbursements) AS paid_reimbursements_amount,
                (SELECT IFNULL(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0)
                 FROM invoices i
                 WHERE i.deleted_at IS NULL
                   AND i.status <> "cancelado"
                   AND YEAR(i.reference_month) = :year_committed_invoices) AS committed_invoices_amount,
                (SELECT IFNULL(SUM(r.amount), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL
                   AND r.status = "pendente"
                   AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :year_committed_reimbursements) AS committed_reimbursements_amount,
                (SELECT IFNULL(SUM(
                    CASE
                        WHEN cpi.cost_type = "mensal"
                             AND (cpi.start_date IS NULL OR cpi.start_date <= :next_year_end_mensal)
                             AND (cpi.end_date IS NULL OR cpi.end_date >= :next_year_start_mensal)
                        THEN cpi.amount
                        WHEN cpi.cost_type = "anual"
                             AND (cpi.start_date IS NULL OR cpi.start_date <= :next_year_end_anual)
                             AND (cpi.end_date IS NULL OR cpi.end_date >= :next_year_start_anual)
                        THEN cpi.amount / 12
                        ELSE 0
                    END
                 ), 0)
                 FROM cost_plans cp
                 INNER JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
                 INNER JOIN cost_plan_items cpi ON cpi.cost_plan_id = cp.id AND cpi.deleted_at IS NULL
                 WHERE cp.deleted_at IS NULL
                   AND cp.is_active = 1) AS projected_monthly_base'
        );
        $stmt->execute([
            'year_paid_invoices' => $year,
            'year_paid_reimbursements' => $year,
            'year_committed_invoices' => $year,
            'year_committed_reimbursements' => $year,
            'next_year_start_mensal' => $nextYearStart,
            'next_year_end_mensal' => $nextYearEnd,
            'next_year_start_anual' => $nextYearStart,
            'next_year_end_anual' => $nextYearEnd,
        ]);

        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /** @param array<string, mixed> $data */
    public function createScenario(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO hiring_scenarios (
                budget_cycle_id,
                organ_id,
                scenario_name,
                entry_date,
                quantity,
                avg_monthly_cost,
                annual_factor,
                cost_current_year,
                cost_next_year,
                available_before,
                remaining_after_current_year,
                max_capacity_before,
                risk_level,
                notes,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :budget_cycle_id,
                :organ_id,
                :scenario_name,
                :entry_date,
                :quantity,
                :avg_monthly_cost,
                :annual_factor,
                :cost_current_year,
                :cost_next_year,
                :available_before,
                :remaining_after_current_year,
                :max_capacity_before,
                :risk_level,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'budget_cycle_id' => $data['budget_cycle_id'],
            'organ_id' => $data['organ_id'],
            'scenario_name' => $data['scenario_name'],
            'entry_date' => $data['entry_date'],
            'quantity' => $data['quantity'],
            'avg_monthly_cost' => $data['avg_monthly_cost'],
            'annual_factor' => $data['annual_factor'],
            'cost_current_year' => $data['cost_current_year'],
            'cost_next_year' => $data['cost_next_year'],
            'available_before' => $data['available_before'],
            'remaining_after_current_year' => $data['remaining_after_current_year'],
            'max_capacity_before' => $data['max_capacity_before'],
            'risk_level' => $data['risk_level'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createScenarioItem(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO hiring_scenario_items (
                hiring_scenario_id,
                item_label,
                quantity,
                avg_monthly_cost,
                cost_current_year,
                cost_next_year,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :hiring_scenario_id,
                :item_label,
                :quantity,
                :avg_monthly_cost,
                :cost_current_year,
                :cost_next_year,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'hiring_scenario_id' => $data['hiring_scenario_id'],
            'item_label' => $data['item_label'],
            'quantity' => $data['quantity'],
            'avg_monthly_cost' => $data['avg_monthly_cost'],
            'cost_current_year' => $data['cost_current_year'],
            'cost_next_year' => $data['cost_next_year'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentScenarios(int $cycleId, int $limit = 20): array
    {
        $safeLimit = max(1, min(50, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                hs.id,
                hs.budget_cycle_id,
                hs.organ_id,
                hs.scenario_name,
                hs.entry_date,
                hs.quantity,
                hs.avg_monthly_cost,
                hs.annual_factor,
                hs.cost_current_year,
                hs.cost_next_year,
                hs.available_before,
                hs.remaining_after_current_year,
                hs.max_capacity_before,
                hs.risk_level,
                hs.notes,
                hs.created_by,
                hs.created_at,
                hs.updated_at,
                o.name AS organ_name,
                u.name AS created_by_name,
                COUNT(hsi.id) AS items_count
             FROM hiring_scenarios hs
             LEFT JOIN organs o ON o.id = hs.organ_id
             LEFT JOIN users u ON u.id = hs.created_by
             LEFT JOIN hiring_scenario_items hsi
               ON hsi.hiring_scenario_id = hs.id
              AND hsi.deleted_at IS NULL
             WHERE hs.budget_cycle_id = :budget_cycle_id
               AND hs.deleted_at IS NULL
             GROUP BY
                hs.id,
                hs.budget_cycle_id,
                hs.organ_id,
                hs.scenario_name,
                hs.entry_date,
                hs.quantity,
                hs.avg_monthly_cost,
                hs.annual_factor,
                hs.cost_current_year,
                hs.cost_next_year,
                hs.available_before,
                hs.remaining_after_current_year,
                hs.max_capacity_before,
                hs.risk_level,
                hs.notes,
                hs.created_by,
                hs.created_at,
                hs.updated_at,
                o.name,
                u.name
             ORDER BY hs.created_at DESC, hs.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':budget_cycle_id', $cycleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
