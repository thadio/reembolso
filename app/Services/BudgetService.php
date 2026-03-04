<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BudgetRepository;
use DateTimeImmutable;

final class BudgetService
{
    private const DEFAULT_ANNUAL_FACTOR = 13.30;

    public function __construct(
        private BudgetRepository $budget,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{
     *   cycle: array<string, mixed>,
     *   summary: array<string, int|float|string>,
     *   organs: array<int, array<string, mixed>>,
     *   parameters: array<int, array<string, mixed>>,
     *   scenarios: array<int, array<string, mixed>>
     * }
     */
    public function dashboard(int $year): array
    {
        $normalizedYear = $this->normalizeYear($year);
        $cycle = $this->budget->ensureCycle($normalizedYear, self::DEFAULT_ANNUAL_FACTOR, null);

        $annualFactor = max(0.1, $this->toFloat($cycle['annual_factor'] ?? self::DEFAULT_ANNUAL_FACTOR));
        $totalBudget = max(0.0, $this->toFloat($cycle['total_budget'] ?? 0));

        $snapshot = $this->budget->financialSnapshot($normalizedYear);
        $paidInvoices = max(0.0, $this->toFloat($snapshot['paid_invoices_amount'] ?? 0));
        $paidReimbursements = max(0.0, $this->toFloat($snapshot['paid_reimbursements_amount'] ?? 0));
        $committedInvoices = max(0.0, $this->toFloat($snapshot['committed_invoices_amount'] ?? 0));
        $committedReimbursements = max(0.0, $this->toFloat($snapshot['committed_reimbursements_amount'] ?? 0));
        $projectedMonthlyBase = max(0.0, $this->toFloat($snapshot['projected_monthly_base'] ?? 0));

        $executedAmount = round($paidInvoices + $paidReimbursements, 2);
        $committedAmount = round($committedInvoices + $committedReimbursements, 2);
        $availableAmount = round($totalBudget - $executedAmount - $committedAmount, 2);
        $projectedNextYear = round($projectedMonthlyBase * $annualFactor, 2);
        $projectedBalanceNextYear = round($totalBudget - $projectedNextYear, 2);

        $globalAverage = round($this->budget->globalAverageMonthlyCost(), 2);

        return [
            'cycle' => $cycle,
            'summary' => [
                'year' => $normalizedYear,
                'annual_factor' => round($annualFactor, 2),
                'total_budget' => round($totalBudget, 2),
                'executed_amount' => $executedAmount,
                'committed_amount' => $committedAmount,
                'available_amount' => $availableAmount,
                'paid_invoices_amount' => round($paidInvoices, 2),
                'paid_reimbursements_amount' => round($paidReimbursements, 2),
                'committed_invoices_amount' => round($committedInvoices, 2),
                'committed_reimbursements_amount' => round($committedReimbursements, 2),
                'projected_monthly_base' => round($projectedMonthlyBase, 2),
                'projected_next_year' => $projectedNextYear,
                'projected_balance_next_year' => $projectedBalanceNextYear,
                'global_average_monthly_cost' => $globalAverage,
                'risk_level' => $this->riskLevel($totalBudget, $availableAmount),
            ],
            'organs' => $this->budget->activeOrgans(),
            'parameters' => $this->budget->orgParameters(),
            'scenarios' => $this->budget->recentScenarios((int) ($cycle['id'] ?? 0), 20),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, simulation?: array<string, mixed>}
     */
    public function simulate(int $year, array $input, int $userId, string $ip, string $userAgent): array
    {
        $dashboard = $this->dashboard($year);
        $summary = $dashboard['summary'];
        $cycle = $dashboard['cycle'];

        $cycleId = (int) ($cycle['id'] ?? 0);
        if ($cycleId <= 0) {
            return [
                'ok' => false,
                'message' => 'Ciclo orcamentario indisponivel para simulacao.',
                'errors' => ['Nao foi possivel localizar/gerar ciclo orcamentario.'],
            ];
        }

        $organId = max(0, (int) ($input['organ_id'] ?? 0));
        $entryDate = $this->normalizeDate($this->clean($input['entry_date'] ?? null));
        $quantity = max(0, (int) ($input['quantity'] ?? 0));
        $scenarioNameInput = $this->clean($input['scenario_name'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $avgMonthlyRaw = $this->parseMoneyNullable($input['avg_monthly_cost'] ?? null);
        $avgMonthly = $avgMonthlyRaw;
        $avgSource = 'informado';

        $errors = [];

        if ($organId <= 0 || !$this->budget->organExists($organId)) {
            $errors[] = 'Orgao invalido para simulacao de contratacao.';
        }

        if ($entryDate === null) {
            $errors[] = 'Data de entrada invalida para simulacao.';
        }

        if ($quantity <= 0) {
            $errors[] = 'Quantidade deve ser maior que zero.';
        }

        $yearValue = (int) ($summary['year'] ?? date('Y'));
        if ($entryDate !== null && (int) substr($entryDate, 0, 4) !== $yearValue) {
            $errors[] = sprintf('Data de entrada deve estar dentro do ciclo %d.', $yearValue);
        }

        if ($avgMonthly === null || $avgMonthly <= 0.0) {
            if ($organId > 0) {
                $parameter = $this->budget->findOrgParameterByOrgan($organId);
                $paramAvg = max(0.0, $this->toFloat($parameter['avg_monthly_cost'] ?? 0));
                if ($paramAvg > 0.0) {
                    $avgMonthly = $paramAvg;
                    $avgSource = 'parametro_orgao';
                }
            }

            if (($avgMonthly === null || $avgMonthly <= 0.0)) {
                $globalAvg = max(0.0, $this->budget->globalAverageMonthlyCost());
                if ($globalAvg > 0.0) {
                    $avgMonthly = $globalAvg;
                    $avgSource = 'media_global';
                }
            }
        }

        if ($avgMonthly === null || $avgMonthly <= 0.0) {
            $errors[] = 'Custo medio mensal invalido e sem parametro de fallback disponivel.';
        }

        $monthsRemaining = 0;
        if ($entryDate !== null) {
            $monthsRemaining = $this->monthsRemainingInYear($entryDate, $yearValue);
            if ($monthsRemaining <= 0) {
                $errors[] = 'Data de entrada nao gera meses restantes no ano corrente.';
            }
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel executar simulacao de contratacao.',
                'errors' => $errors,
            ];
        }

        $annualFactor = max(0.1, $this->toFloat($summary['annual_factor'] ?? self::DEFAULT_ANNUAL_FACTOR));
        $availableBefore = (float) ($summary['available_amount'] ?? 0.0);
        $totalBudget = (float) ($summary['total_budget'] ?? 0.0);

        $costCurrentYearPerPerson = round((float) $avgMonthly * $monthsRemaining, 2);
        $costCurrentYear = round($costCurrentYearPerPerson * $quantity, 2);
        $costNextYear = round((float) $avgMonthly * $annualFactor * $quantity, 2);

        $maxCapacityBefore = $costCurrentYearPerPerson > 0.0
            ? max(0, (int) floor(max(0.0, $availableBefore) / $costCurrentYearPerPerson))
            : 0;

        $remainingAfterCurrentYear = round($availableBefore - $costCurrentYear, 2);
        $riskLevel = $this->riskLevel($totalBudget, $remainingAfterCurrentYear);

        $scenarioName = $scenarioNameInput !== null
            ? mb_substr($scenarioNameInput, 0, 190)
            : 'Simulacao ' . date('d/m/Y H:i');

        $payload = [
            'budget_cycle_id' => $cycleId,
            'organ_id' => $organId,
            'scenario_name' => $scenarioName,
            'entry_date' => $entryDate,
            'quantity' => $quantity,
            'avg_monthly_cost' => number_format((float) $avgMonthly, 2, '.', ''),
            'annual_factor' => number_format($annualFactor, 2, '.', ''),
            'cost_current_year' => number_format($costCurrentYear, 2, '.', ''),
            'cost_next_year' => number_format($costNextYear, 2, '.', ''),
            'available_before' => number_format($availableBefore, 2, '.', ''),
            'remaining_after_current_year' => number_format($remainingAfterCurrentYear, 2, '.', ''),
            'max_capacity_before' => $maxCapacityBefore,
            'risk_level' => $riskLevel,
            'notes' => $notes === null ? null : mb_substr($notes, 0, 4000),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        try {
            $this->budget->beginTransaction();

            $scenarioId = $this->budget->createScenario($payload);
            if ($scenarioId <= 0) {
                throw new \RuntimeException('Falha ao criar cenario de contratacao.');
            }

            $this->budget->createScenarioItem([
                'hiring_scenario_id' => $scenarioId,
                'item_label' => 'Quantidade simulada',
                'quantity' => $quantity,
                'avg_monthly_cost' => number_format((float) $avgMonthly, 2, '.', ''),
                'cost_current_year' => number_format($costCurrentYear, 2, '.', ''),
                'cost_next_year' => number_format($costNextYear, 2, '.', ''),
            ]);

            $this->audit->log(
                entity: 'hiring_scenario',
                entityId: $scenarioId,
                action: 'simulate',
                beforeData: null,
                afterData: [
                    'budget_cycle_id' => $cycleId,
                    'organ_id' => $organId,
                    'entry_date' => $entryDate,
                    'quantity' => $quantity,
                    'avg_monthly_cost' => number_format((float) $avgMonthly, 2, '.', ''),
                    'months_remaining' => $monthsRemaining,
                    'cost_current_year' => number_format($costCurrentYear, 2, '.', ''),
                    'cost_next_year' => number_format($costNextYear, 2, '.', ''),
                    'available_before' => number_format($availableBefore, 2, '.', ''),
                    'remaining_after_current_year' => number_format($remainingAfterCurrentYear, 2, '.', ''),
                    'max_capacity_before' => $maxCapacityBefore,
                    'risk_level' => $riskLevel,
                    'avg_source' => $avgSource,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'budget',
                type: 'budget.hiring_simulated',
                payload: [
                    'budget_cycle_id' => $cycleId,
                    'hiring_scenario_id' => $scenarioId,
                    'organ_id' => $organId,
                    'quantity' => $quantity,
                    'risk_level' => $riskLevel,
                    'cost_current_year' => number_format($costCurrentYear, 2, '.', ''),
                    'cost_next_year' => number_format($costNextYear, 2, '.', ''),
                ],
                entityId: $cycleId,
                userId: $userId
            );

            $this->budget->commit();
        } catch (\Throwable $exception) {
            $this->budget->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar simulacao de contratacao.',
                'errors' => ['Falha ao persistir cenario e itens de simulacao.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Simulacao de contratacao executada com sucesso.',
            'errors' => [],
            'simulation' => [
                'year' => $yearValue,
                'scenario_name' => $scenarioName,
                'entry_date' => $entryDate,
                'quantity' => $quantity,
                'avg_monthly_cost' => round((float) $avgMonthly, 2),
                'avg_source' => $avgSource,
                'months_remaining' => $monthsRemaining,
                'cost_current_year_per_person' => $costCurrentYearPerPerson,
                'cost_current_year' => $costCurrentYear,
                'cost_next_year' => $costNextYear,
                'available_before' => round($availableBefore, 2),
                'remaining_after_current_year' => $remainingAfterCurrentYear,
                'max_capacity_before' => $maxCapacityBefore,
                'risk_level' => $riskLevel,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function upsertOrgParameter(array $input, int $userId, string $ip, string $userAgent): array
    {
        $organId = max(0, (int) ($input['organ_id'] ?? 0));
        $avgMonthlyCost = $this->parseMoneyNullable($input['avg_monthly_cost'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($organId <= 0 || !$this->budget->organExists($organId)) {
            $errors[] = 'Orgao invalido para parametrizacao de custo medio.';
        }

        if ($avgMonthlyCost === null || $avgMonthlyCost <= 0.0) {
            $errors[] = 'Custo medio mensal invalido (deve ser maior que zero).';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametro de custo medio.',
                'errors' => $errors,
            ];
        }

        $before = $this->budget->findOrgParameterByOrgan($organId);
        $id = $this->budget->upsertOrgParameter(
            organId: $organId,
            avgMonthlyCost: number_format((float) $avgMonthlyCost, 2, '.', ''),
            notes: $notes === null ? null : mb_substr($notes, 0, 4000),
            updatedBy: $userId > 0 ? $userId : null
        );

        if ($id <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametro de custo medio.',
                'errors' => ['Falha ao persistir parametro por orgao.'],
            ];
        }

        $after = $this->budget->findOrgParameterByOrgan($organId);

        $this->audit->log(
            entity: 'org_cost_parameter',
            entityId: $id,
            action: $before === null ? 'create' : 'update',
            beforeData: $before,
            afterData: $after,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'budget',
            type: 'budget.org_cost_parameter_upserted',
            payload: [
                'organ_id' => $organId,
                'avg_monthly_cost' => number_format((float) $avgMonthlyCost, 2, '.', ''),
                'parameter_id' => $id,
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Parametro de custo medio salvo com sucesso.',
            'errors' => [],
        ];
    }

    private function normalizeYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function monthsRemainingInYear(string $entryDate, int $year): int
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $entryDate);
        if ($date === false) {
            return 0;
        }

        if ((int) $date->format('Y') !== $year) {
            return 0;
        }

        $month = (int) $date->format('n');

        return max(0, 13 - $month);
    }

    private function riskLevel(float $totalBudget, float $remaining): string
    {
        if ($remaining < -0.009) {
            return 'alto';
        }

        if ($totalBudget > 0.009 && $remaining <= ($totalBudget * 0.10)) {
            return 'medio';
        }

        return 'baixo';
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseMoneyNullable(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = $raw;
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function clean(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric((string) $value) ? (float) $value : 0.0;
    }
}
