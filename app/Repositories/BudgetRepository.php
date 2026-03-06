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
    public function findCycleByYear(int $year, string $financialNature = 'despesa_reembolso'): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                bc.id,
                bc.cycle_year,
                bc.financial_nature,
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
               AND bc.financial_nature = :financial_nature
               AND bc.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'cycle_year' => $year,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
        ]);
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

    public function createCycle(
        int $year,
        float $annualFactor,
        string $totalBudget,
        ?int $createdBy,
        string $financialNature = 'despesa_reembolso',
        ?string $notes = null,
        string $status = 'aberto'
    ): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO budget_cycles (
                cycle_year,
                financial_nature,
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
                :financial_nature,
                :annual_factor,
                :total_budget,
                :status,
                :notes,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'cycle_year' => $year,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
            'annual_factor' => number_format($annualFactor, 2, '.', ''),
            'total_budget' => $totalBudget,
            'status' => mb_substr(trim($status) !== '' ? trim($status) : 'aberto', 0, 30),
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed> */
    public function ensureCycle(
        int $year,
        float $annualFactor = 13.30,
        ?int $createdBy = null,
        string $financialNature = 'despesa_reembolso'
    ): array {
        $normalizedNature = $this->normalizeFinancialNature($financialNature);
        $existing = $this->findCycleByYear($year, $normalizedNature);
        if ($existing !== null) {
            return $existing;
        }

        $estimated = $this->estimatedTotalFromCdos($year);
        $this->createCycle(
            year: $year,
            annualFactor: $annualFactor,
            totalBudget: $estimated,
            createdBy: $createdBy,
            financialNature: $normalizedNature
        );

        $cycle = $this->findCycleByYear($year, $normalizedNature);

        return $cycle ?? [
            'id' => 0,
            'cycle_year' => $year,
            'financial_nature' => $normalizedNature,
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
    public function listCycles(string $financialNature = 'despesa_reembolso'): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                bc.id,
                bc.cycle_year,
                bc.financial_nature,
                bc.annual_factor,
                bc.total_budget,
                bc.status,
                bc.notes,
                bc.created_by,
                bc.created_at,
                bc.updated_at,
                u.name AS created_by_name,
                (
                    SELECT COUNT(*)
                    FROM hiring_scenarios hs
                    WHERE hs.budget_cycle_id = bc.id
                      AND hs.deleted_at IS NULL
                ) AS scenarios_count,
                (
                    SELECT COUNT(*)
                    FROM budget_scenario_parameters bsp
                    WHERE bsp.budget_cycle_id = bc.id
                      AND bsp.deleted_at IS NULL
                ) AS scenario_parameters_count
             FROM budget_cycles bc
             LEFT JOIN users u ON u.id = bc.created_by
             WHERE bc.deleted_at IS NULL
               AND bc.financial_nature = :financial_nature
             ORDER BY bc.cycle_year DESC'
        );
        $stmt->execute([
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findCycleById(int $cycleId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                bc.id,
                bc.cycle_year,
                bc.financial_nature,
                bc.annual_factor,
                bc.total_budget,
                bc.status,
                bc.notes,
                bc.created_by,
                bc.created_at,
                bc.updated_at,
                u.name AS created_by_name,
                (
                    SELECT COUNT(*)
                    FROM hiring_scenarios hs
                    WHERE hs.budget_cycle_id = bc.id
                      AND hs.deleted_at IS NULL
                ) AS scenarios_count,
                (
                    SELECT COUNT(*)
                    FROM budget_scenario_parameters bsp
                    WHERE bsp.budget_cycle_id = bc.id
                      AND bsp.deleted_at IS NULL
                ) AS scenario_parameters_count
             FROM budget_cycles bc
             LEFT JOIN users u ON u.id = bc.created_by
             WHERE bc.id = :id
               AND bc.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $cycleId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateCycleTotalBudget(int $cycleId, string $totalBudget): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE budget_cycles
             SET total_budget = :total_budget,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $cycleId,
            'total_budget' => $totalBudget,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** @return array{scenarios_count: int, scenario_parameters_count: int} */
    public function cycleDependencies(int $cycleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                (
                    SELECT COUNT(*)
                    FROM hiring_scenarios hs
                    WHERE hs.budget_cycle_id = :cycle_id_scenarios
                      AND hs.deleted_at IS NULL
                ) AS scenarios_count,
                (
                    SELECT COUNT(*)
                    FROM budget_scenario_parameters bsp
                    WHERE bsp.budget_cycle_id = :cycle_id_parameters
                      AND bsp.deleted_at IS NULL
                ) AS scenario_parameters_count'
        );
        $stmt->execute([
            'cycle_id_scenarios' => $cycleId,
            'cycle_id_parameters' => $cycleId,
        ]);

        $row = $stmt->fetch();

        return [
            'scenarios_count' => (int) ($row['scenarios_count'] ?? 0),
            'scenario_parameters_count' => (int) ($row['scenario_parameters_count'] ?? 0),
        ];
    }

    public function deleteScenarioParametersByCycle(int $cycleId): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM budget_scenario_parameters
             WHERE budget_cycle_id = :budget_cycle_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['budget_cycle_id' => $cycleId]);

        return $stmt->rowCount();
    }

    public function deleteCycle(int $cycleId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM budget_cycles
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $cycleId]);

        return $stmt->rowCount() > 0;
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

    /** @return array<int, array<string, mixed>> */
    public function activeModalities(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name
             FROM modalities
             WHERE is_active = 1
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
                p.cargo,
                p.setor,
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
             ORDER BY o.name ASC, p.cargo ASC, p.setor ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findOrgParameterByOrgan(int $organId): ?array
    {
        return $this->findOrgParameterExact($organId, '', '');
    }

    /** @return array<string, mixed>|null */
    public function findOrgParameterExact(int $organId, string $cargo, string $setor): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.organ_id,
                p.cargo,
                p.setor,
                p.avg_monthly_cost,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name
             FROM org_cost_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             WHERE p.organ_id = :organ_id
               AND p.cargo = :cargo
               AND p.setor = :setor
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'organ_id' => $organId,
            'cargo' => $cargo,
            'setor' => $setor,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findOrgParameterByScope(int $organId, ?string $cargo, ?string $setor): ?array
    {
        $cargoScope = $this->normalizeScopeValue($cargo);
        $setorScope = $this->normalizeScopeValue($setor);

        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.organ_id,
                p.cargo,
                p.setor,
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
               AND (
                    (p.cargo = :cargo_exact AND p.setor = :setor_exact)
                    OR (p.cargo = :cargo_fallback AND p.setor = "")
                    OR (p.cargo = "" AND p.setor = :setor_fallback)
                    OR (p.cargo = "" AND p.setor = "")
               )
             ORDER BY
                CASE
                    WHEN p.cargo = :cargo_rank AND p.setor = :setor_rank THEN 0
                    WHEN p.cargo = :cargo_rank AND p.setor = "" THEN 1
                    WHEN p.cargo = "" AND p.setor = :setor_rank THEN 2
                    ELSE 3
                END,
                p.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'organ_id' => $organId,
            'cargo_exact' => $cargoScope,
            'setor_exact' => $setorScope,
            'cargo_fallback' => $cargoScope,
            'setor_fallback' => $setorScope,
            'cargo_rank' => $cargoScope,
            'setor_rank' => $setorScope,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function upsertOrgParameter(
        int $organId,
        string $cargo,
        string $setor,
        string $avgMonthlyCost,
        ?string $notes,
        ?int $updatedBy
    ): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO org_cost_parameters (
                organ_id,
                cargo,
                setor,
                avg_monthly_cost,
                notes,
                updated_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :organ_id,
                :cargo,
                :setor,
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
            'cargo' => $cargo,
            'setor' => $setor,
            'avg_monthly_cost' => $avgMonthlyCost,
            'notes' => $notes,
            'updated_by' => $updatedBy,
        ]);

        $row = $this->findOrgParameterExact($organId, $cargo, $setor);

        return (int) ($row['id'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function scenarioParameters(int $cycleId, string $financialNature = 'despesa_reembolso'): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.budget_cycle_id,
                p.financial_nature,
                p.organ_id,
                p.modality,
                p.base_variation_percent,
                p.updated_variation_percent,
                p.worst_variation_percent,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name,
                u.name AS updated_by_name
             FROM budget_scenario_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             LEFT JOIN users u ON u.id = p.updated_by
             WHERE p.budget_cycle_id = :budget_cycle_id
               AND p.financial_nature = :financial_nature
               AND p.deleted_at IS NULL
             ORDER BY o.name ASC, p.modality ASC'
        );
        $stmt->execute([
            'budget_cycle_id' => $cycleId,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findScenarioParameterExact(
        int $cycleId,
        int $organId,
        string $modality,
        string $financialNature = 'despesa_reembolso'
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.budget_cycle_id,
                p.financial_nature,
                p.organ_id,
                p.modality,
                p.base_variation_percent,
                p.updated_variation_percent,
                p.worst_variation_percent,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name
             FROM budget_scenario_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             WHERE p.budget_cycle_id = :budget_cycle_id
               AND p.financial_nature = :financial_nature
               AND p.organ_id = :organ_id
               AND p.modality = :modality
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'budget_cycle_id' => $cycleId,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
            'organ_id' => $organId,
            'modality' => $modality,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findScenarioParameter(
        int $cycleId,
        int $organId,
        string $modality,
        string $financialNature = 'despesa_reembolso'
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.budget_cycle_id,
                p.financial_nature,
                p.organ_id,
                p.modality,
                p.base_variation_percent,
                p.updated_variation_percent,
                p.worst_variation_percent,
                p.notes,
                p.updated_by,
                p.created_at,
                p.updated_at,
                o.name AS organ_name
             FROM budget_scenario_parameters p
             INNER JOIN organs o ON o.id = p.organ_id AND o.deleted_at IS NULL
             WHERE p.budget_cycle_id = :budget_cycle_id
               AND p.financial_nature = :financial_nature
               AND p.organ_id = :organ_id
               AND p.deleted_at IS NULL
               AND (p.modality = :modality_exact OR p.modality = "geral")
             ORDER BY
                CASE
                    WHEN p.modality = :modality_priority THEN 0
                    ELSE 1
                END,
                p.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'budget_cycle_id' => $cycleId,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
            'organ_id' => $organId,
            'modality_exact' => $modality,
            'modality_priority' => $modality,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function upsertScenarioParameter(
        int $cycleId,
        string $financialNature,
        int $organId,
        string $modality,
        string $baseVariation,
        string $updatedVariation,
        string $worstVariation,
        ?string $notes,
        ?int $updatedBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO budget_scenario_parameters (
                budget_cycle_id,
                financial_nature,
                organ_id,
                modality,
                base_variation_percent,
                updated_variation_percent,
                worst_variation_percent,
                notes,
                updated_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :budget_cycle_id,
                :financial_nature,
                :organ_id,
                :modality,
                :base_variation_percent,
                :updated_variation_percent,
                :worst_variation_percent,
                :notes,
                :updated_by,
                NOW(),
                NOW(),
                NULL
            )
            ON DUPLICATE KEY UPDATE
                base_variation_percent = VALUES(base_variation_percent),
                updated_variation_percent = VALUES(updated_variation_percent),
                worst_variation_percent = VALUES(worst_variation_percent),
                notes = VALUES(notes),
                updated_by = VALUES(updated_by),
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'budget_cycle_id' => $cycleId,
            'financial_nature' => $this->normalizeFinancialNature($financialNature),
            'organ_id' => $organId,
            'modality' => $modality,
            'base_variation_percent' => $baseVariation,
            'updated_variation_percent' => $updatedVariation,
            'worst_variation_percent' => $worstVariation,
            'notes' => $notes,
            'updated_by' => $updatedBy,
        ]);

        $row = $this->findScenarioParameterExact($cycleId, $organId, $modality, $financialNature);

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
    public function financialSnapshot(int $year, string $financialNature = 'despesa_reembolso'): array
    {
        $normalizedNature = $this->normalizeFinancialNature($financialNature);
        $nextYear = $year + 1;
        $nextYearStart = sprintf('%04d-01-01', $nextYear);
        $nextYearEnd = sprintf('%04d-12-31', $nextYear);

        $stmt = $this->db->prepare(
            'SELECT
                (SELECT IFNULL(SUM(p.amount), 0)
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id AND i.deleted_at IS NULL
                 WHERE p.deleted_at IS NULL
                   AND p.financial_nature = :financial_nature_paid_invoices
                   AND i.financial_nature = :financial_nature_paid_invoices_invoice
                   AND YEAR(p.payment_date) = :year_paid_invoices) AS paid_invoices_amount,
                (SELECT IFNULL(SUM(r.amount), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL
                   AND r.financial_nature = :financial_nature_paid_reimbursements
                   AND r.status = "pago"
                   AND YEAR(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at))) = :year_paid_reimbursements) AS paid_reimbursements_amount,
                (SELECT IFNULL(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0)
                 FROM invoices i
                 WHERE i.deleted_at IS NULL
                   AND i.financial_nature = :financial_nature_committed_invoices
                   AND i.status <> "cancelado"
                   AND YEAR(i.reference_month) = :year_committed_invoices) AS committed_invoices_amount,
                (SELECT IFNULL(SUM(r.amount), 0)
                 FROM reimbursement_entries r
                 INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                 WHERE r.deleted_at IS NULL
                   AND r.financial_nature = :financial_nature_committed_reimbursements
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
            'financial_nature_paid_invoices' => $normalizedNature,
            'financial_nature_paid_invoices_invoice' => $normalizedNature,
            'year_paid_invoices' => $year,
            'financial_nature_paid_reimbursements' => $normalizedNature,
            'year_paid_reimbursements' => $year,
            'financial_nature_committed_invoices' => $normalizedNature,
            'year_committed_invoices' => $year,
            'financial_nature_committed_reimbursements' => $normalizedNature,
            'year_committed_reimbursements' => $year,
            'next_year_start_mensal' => $nextYearStart,
            'next_year_end_mensal' => $nextYearEnd,
            'next_year_start_anual' => $nextYearStart,
            'next_year_end_anual' => $nextYearEnd,
        ]);

        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function monthlyProjectionSeries(int $year, string $financialNature = 'despesa_reembolso'): array
    {
        $normalizedNature = $this->normalizeFinancialNature($financialNature);
        $monthsSql = $this->monthsSql();
        $yearLiteral = (int) $year;

        $stmt = $this->db->prepare(
            'SELECT
                m.month_number,
                IFNULL(exec_tot.total, 0) AS executed_amount,
                IFNULL(comm_tot.total, 0) AS committed_amount,
                IFNULL(base_tot.total, 0) AS projected_base_amount
             FROM (' . $monthsSql . ') m
             LEFT JOIN (
                SELECT src.month_number, SUM(src.amount) AS total
                FROM (
                    SELECT MONTH(p.payment_date) AS month_number, SUM(p.amount) AS amount
                    FROM payments p
                    INNER JOIN invoices i ON i.id = p.invoice_id AND i.deleted_at IS NULL
                    WHERE p.deleted_at IS NULL
                      AND p.financial_nature = :projection_financial_nature_exec_payments
                      AND i.financial_nature = :projection_financial_nature_exec_payments_invoice
                      AND YEAR(p.payment_date) = :projection_year_exec_payments
                    GROUP BY MONTH(p.payment_date)

                    UNION ALL

                    SELECT MONTH(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at))) AS month_number, SUM(r.amount) AS amount
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.financial_nature = :projection_financial_nature_exec_reimbursement
                      AND r.status = "pago"
                      AND YEAR(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at))) = :projection_year_exec_reimbursement
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number
             ) exec_tot ON exec_tot.month_number = m.month_number
             LEFT JOIN (
                SELECT src.month_number, SUM(src.amount) AS total
                FROM (
                    SELECT MONTH(i.reference_month) AS month_number, SUM(GREATEST(i.total_amount - i.paid_amount, 0)) AS amount
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.financial_nature = :projection_financial_nature_committed_invoices
                      AND i.status <> "cancelado"
                      AND YEAR(i.reference_month) = :projection_year_committed_invoices
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) AS month_number, SUM(r.amount) AS amount
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.financial_nature = :projection_financial_nature_committed_reimbursement
                      AND r.status = "pendente"
                      AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :projection_year_committed_reimbursement
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number
             ) comm_tot ON comm_tot.month_number = m.month_number
             LEFT JOIN (
                SELECT
                    mm.month_number,
                    IFNULL(SUM(
                        CASE
                            WHEN cpi.cost_type = "mensal"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d")))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"))
                            THEN cpi.amount
                            WHEN cpi.cost_type = "anual"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d")))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"))
                            THEN cpi.amount / 12
                            WHEN cpi.cost_type = "unico"
                                 AND DATE_FORMAT(COALESCE(cpi.start_date, DATE(cpi.created_at)), "%Y-%m") = DATE_FORMAT(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"), "%Y-%m")
                            THEN cpi.amount
                            ELSE 0
                        END
                    ), 0) AS total
                FROM (' . $monthsSql . ') mm
                LEFT JOIN cost_plans cp ON cp.deleted_at IS NULL AND cp.is_active = 1
                LEFT JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
                LEFT JOIN cost_plan_items cpi ON cpi.cost_plan_id = cp.id AND cpi.deleted_at IS NULL
                GROUP BY mm.month_number
             ) base_tot ON base_tot.month_number = m.month_number
             ORDER BY m.month_number ASC'
        );

        $stmt->execute([
            'projection_financial_nature_exec_payments' => $normalizedNature,
            'projection_financial_nature_exec_payments_invoice' => $normalizedNature,
            'projection_year_exec_payments' => $year,
            'projection_financial_nature_exec_reimbursement' => $normalizedNature,
            'projection_year_exec_reimbursement' => $year,
            'projection_financial_nature_committed_invoices' => $normalizedNature,
            'projection_year_committed_invoices' => $year,
            'projection_financial_nature_committed_reimbursement' => $normalizedNature,
            'projection_year_committed_reimbursement' => $year,
        ]);

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function createScenario(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO hiring_scenarios (
                budget_cycle_id,
                financial_nature,
                organ_id,
                modality,
                movement_type,
                cargo,
                setor,
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
                :financial_nature,
                :organ_id,
                :modality,
                :movement_type,
                :cargo,
                :setor,
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
            'financial_nature' => $this->normalizeFinancialNature((string) ($data['financial_nature'] ?? 'despesa_reembolso')),
            'organ_id' => $data['organ_id'],
            'modality' => $data['modality'],
            'movement_type' => $data['movement_type'],
            'cargo' => $data['cargo'],
            'setor' => $data['setor'],
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
                scenario_code,
                variation_percent,
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
                :scenario_code,
                :variation_percent,
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
            'scenario_code' => $data['scenario_code'],
            'variation_percent' => $data['variation_percent'],
            'quantity' => $data['quantity'],
            'avg_monthly_cost' => $data['avg_monthly_cost'],
            'cost_current_year' => $data['cost_current_year'],
            'cost_next_year' => $data['cost_next_year'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentScenarios(int $cycleId, string $financialNature = 'despesa_reembolso', int $limit = 20): array
    {
        $safeLimit = max(1, min(50, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                hs.id,
                hs.budget_cycle_id,
                hs.financial_nature,
                hs.organ_id,
                hs.modality,
                hs.movement_type,
                hs.cargo,
                hs.setor,
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
               AND hs.financial_nature = :financial_nature
               AND hs.deleted_at IS NULL
             GROUP BY
                hs.id,
                hs.budget_cycle_id,
                hs.financial_nature,
                hs.organ_id,
                hs.modality,
                hs.movement_type,
                hs.cargo,
                hs.setor,
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
        $stmt->bindValue(':financial_nature', $this->normalizeFinancialNature($financialNature));
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function topDeviationOffenders(int $cycleId, string $financialNature = 'despesa_reembolso', int $limit = 10): array
    {
        $safeLimit = max(1, min(30, $limit));

        $stmt = $this->db->prepare(
            'SELECT
                hs.id AS scenario_id,
                hs.scenario_name,
                hs.organ_id,
                hs.modality,
                hs.movement_type,
                hs.cargo,
                hs.setor,
                hs.entry_date,
                hs.quantity,
                hs.available_before,
                hsi.cost_current_year AS worst_cost_current_year,
                (hs.available_before - hsi.cost_current_year) AS remaining_after_worst,
                ABS(LEAST(hs.available_before - hsi.cost_current_year, 0)) AS deficit_amount,
                hs.created_at,
                o.name AS organ_name,
                u.name AS created_by_name
             FROM hiring_scenarios hs
             INNER JOIN hiring_scenario_items hsi
               ON hsi.hiring_scenario_id = hs.id
              AND hsi.deleted_at IS NULL
              AND hsi.scenario_code = "pior_caso"
             LEFT JOIN organs o ON o.id = hs.organ_id
             LEFT JOIN users u ON u.id = hs.created_by
             WHERE hs.budget_cycle_id = :budget_cycle_id
               AND hs.financial_nature = :financial_nature
               AND hs.deleted_at IS NULL
               AND hs.movement_type = "entrada"
             ORDER BY deficit_amount DESC, hsi.cost_current_year DESC, hs.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':budget_cycle_id', $cycleId, PDO::PARAM_INT);
        $stmt->bindValue(':financial_nature', $this->normalizeFinancialNature($financialNature));
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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

    private function normalizeScopeValue(?string $value): string
    {
        $text = trim(mb_strtolower((string) $value));
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_substr($text, 0, 120);
    }

    private function normalizeFinancialNature(string $value): string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized === 'receita_reembolso' ? 'receita_reembolso' : 'despesa_reembolso';
    }
}
