<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BudgetRepository;
use DateTimeImmutable;

final class BudgetService
{
    private const DEFAULT_ANNUAL_FACTOR = 13.30;
    private const ALLOWED_FINANCIAL_NATURES = ['despesa_reembolso', 'receita_reembolso'];

    /** @var array<string, float> */
    private const DEFAULT_SCENARIO_VARIATIONS = [
        'base' => 0.0,
        'atualizado' => 10.0,
        'pior_caso' => 25.0,
    ];

    /** @var array<int, string> */
    private const MONTH_LABELS = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez',
    ];

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
     *   projection: array<string, mixed>,
     *   cycles: array<int, array<string, mixed>>,
     *   organs: array<int, array<string, mixed>>,
     *   modalities: array<int, array<string, mixed>>,
     *   parameters: array<int, array<string, mixed>>,
     *   scenario_parameters: array<int, array<string, mixed>>,
     *   default_variations: array<string, float>,
     *   insufficiency_risks: array<int, array<string, mixed>>,
     *   offenders: array<int, array<string, mixed>>,
     *   active_alerts: array<int, array<string, mixed>>,
     *   scenarios: array<int, array<string, mixed>>
     * }
     */
    public function dashboard(int $year, string $financialNature = 'despesa_reembolso'): array
    {
        $normalizedYear = $this->normalizeYear($year);
        $normalizedFinancialNature = $this->normalizeFinancialNature($financialNature);
        $cycle = $this->budget->ensureCycle(
            year: $normalizedYear,
            annualFactor: self::DEFAULT_ANNUAL_FACTOR,
            createdBy: null,
            financialNature: $normalizedFinancialNature
        );

        $annualFactor = max(0.1, $this->toFloat($cycle['annual_factor'] ?? self::DEFAULT_ANNUAL_FACTOR));
        $totalBudget = max(0.0, $this->toFloat($cycle['total_budget'] ?? 0));

        $snapshot = $this->budget->financialSnapshot($normalizedYear, $normalizedFinancialNature);
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

        $projection = $this->buildProjection(
            year: $normalizedYear,
            seriesRows: $this->budget->monthlyProjectionSeries($normalizedYear, $normalizedFinancialNature),
            annualFactor: $annualFactor,
            totalBudget: $totalBudget
        );

        $cycleId = (int) ($cycle['id'] ?? 0);
        $insufficiencyRisks = $this->buildMonthlyInsufficiencyRisks(
            projectionMonths: is_array($projection['months'] ?? null) ? $projection['months'] : [],
            totalBudget: $totalBudget
        );
        $offenders = $cycleId > 0 ? $this->budget->topDeviationOffenders($cycleId, $normalizedFinancialNature, 10) : [];
        $activeAlerts = $this->buildActiveAlerts(
            totalBudget: $totalBudget,
            availableAmount: $availableAmount,
            projectedBalanceNextYear: $projectedBalanceNextYear,
            insufficiencyRisks: $insufficiencyRisks,
            offenders: $offenders
        );

        return [
            'cycle' => $cycle,
            'summary' => [
                'year' => $normalizedYear,
                'financial_nature' => $normalizedFinancialNature,
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
            'projection' => $projection,
            'cycles' => $this->budget->listCycles($normalizedFinancialNature),
            'organs' => $this->budget->activeOrgans(),
            'modalities' => $this->budget->activeModalities(),
            'parameters' => $this->budget->orgParameters(),
            'scenario_parameters' => $cycleId > 0 ? $this->budget->scenarioParameters($cycleId, $normalizedFinancialNature) : [],
            'default_variations' => self::DEFAULT_SCENARIO_VARIATIONS,
            'insufficiency_risks' => $insufficiencyRisks,
            'offenders' => $offenders,
            'active_alerts' => $activeAlerts,
            'scenarios' => $this->budget->recentScenarios($cycleId, $normalizedFinancialNature, 20),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function financialNatureOptions(bool $includeAll = false): array
    {
        $options = [];
        if ($includeAll) {
            $options[] = ['value' => '', 'label' => 'Todas as naturezas'];
        }

        $options[] = ['value' => 'despesa_reembolso', 'label' => 'Despesa de reembolso (a pagar)'];
        $options[] = ['value' => 'receita_reembolso', 'label' => 'Receita de reembolso (a receber)'];

        return $options;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, simulation?: array<string, mixed>}
     */
    public function simulate(int $year, array $input, int $userId, string $ip, string $userAgent): array
    {
        $financialNature = $this->normalizeFinancialNature((string) ($input['financial_nature'] ?? ''));
        $dashboard = $this->dashboard($year, $financialNature);
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
        $modality = $this->normalizeModality($this->clean($input['modality'] ?? null));
        $movementType = $this->normalizeMovementType($this->clean($input['movement_type'] ?? null));
        $cargo = $this->normalizeScopeValue($this->clean($input['cargo'] ?? null));
        $setor = $this->normalizeScopeValue($this->clean($input['setor'] ?? null));
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
                $parameter = $this->budget->findOrgParameterByScope(
                    organId: $organId,
                    cargo: $cargo !== '' ? $cargo : null,
                    setor: $setor !== '' ? $setor : null
                );
                $paramAvg = max(0.0, $this->toFloat($parameter['avg_monthly_cost'] ?? 0));
                if ($paramAvg > 0.0) {
                    $avgMonthly = $paramAvg;
                    $avgSource = $this->resolveParameterSource($parameter, $cargo, $setor);
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

        $scenarioParameter = $this->budget->findScenarioParameter($cycleId, $organId, $modality, $financialNature);
        $variations = $this->resolveScenarioVariations($scenarioParameter);
        $movementDirection = $movementType === 'saida' ? -1 : 1;

        $scenarioMatrix = [];
        foreach ($this->scenarioDefinitions() as $scenarioDef) {
            $variation = $this->normalizeVariation($variations[$scenarioDef['code']] ?? 0.0);
            $adjustedAvgMonthly = round(max(0.0, (float) $avgMonthly * (1 + ($variation / 100))), 2);

            $costCurrentYearPerPerson = round($adjustedAvgMonthly * $monthsRemaining, 2);
            $costCurrentYear = round($costCurrentYearPerPerson * $quantity * $movementDirection, 2);
            $costNextYear = round($adjustedAvgMonthly * $annualFactor * $quantity * $movementDirection, 2);

            $maxCapacityBefore = $movementType === 'entrada' && $costCurrentYearPerPerson > 0.0
                ? max(0, (int) floor(max(0.0, $availableBefore) / $costCurrentYearPerPerson))
                : 0;

            $remainingAfterCurrentYear = round($availableBefore - $costCurrentYear, 2);

            $scenarioMatrix[] = [
                'code' => $scenarioDef['code'],
                'label' => $scenarioDef['label'],
                'movement_type' => $movementType,
                'variation_percent' => round($variation, 2),
                'avg_monthly_cost' => $adjustedAvgMonthly,
                'cost_current_year_per_person' => $costCurrentYearPerPerson,
                'cost_current_year' => $costCurrentYear,
                'cost_next_year' => $costNextYear,
                'available_before' => round($availableBefore, 2),
                'remaining_after_current_year' => $remainingAfterCurrentYear,
                'max_capacity_before' => $maxCapacityBefore,
                'risk_level' => $this->riskLevel($totalBudget, $remainingAfterCurrentYear),
            ];
        }

        /** @var array<string, mixed> $baseScenario */
        $baseScenario = $scenarioMatrix[0];
        /** @var array<string, mixed> $worstScenario */
        $worstScenario = $scenarioMatrix[count($scenarioMatrix) - 1];

        $scenarioName = $scenarioNameInput !== null
            ? mb_substr($scenarioNameInput, 0, 190)
            : 'Simulacao ' . date('d/m/Y H:i');

        $payload = [
            'budget_cycle_id' => $cycleId,
            'financial_nature' => $financialNature,
            'organ_id' => $organId,
            'modality' => $modality,
            'movement_type' => $movementType,
            'cargo' => $cargo,
            'setor' => $setor,
            'scenario_name' => $scenarioName,
            'entry_date' => $entryDate,
            'quantity' => $quantity,
            'avg_monthly_cost' => number_format((float) $baseScenario['avg_monthly_cost'], 2, '.', ''),
            'annual_factor' => number_format($annualFactor, 2, '.', ''),
            'cost_current_year' => number_format((float) $baseScenario['cost_current_year'], 2, '.', ''),
            'cost_next_year' => number_format((float) $baseScenario['cost_next_year'], 2, '.', ''),
            'available_before' => number_format($availableBefore, 2, '.', ''),
            'remaining_after_current_year' => number_format((float) $baseScenario['remaining_after_current_year'], 2, '.', ''),
            'max_capacity_before' => (int) ($baseScenario['max_capacity_before'] ?? 0),
            'risk_level' => (string) ($baseScenario['risk_level'] ?? 'baixo'),
            'notes' => $notes === null ? null : mb_substr($notes, 0, 4000),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        try {
            $this->budget->beginTransaction();

            $scenarioId = $this->budget->createScenario($payload);
            if ($scenarioId <= 0) {
                throw new \RuntimeException('Falha ao criar cenario de contratacao.');
            }

            foreach ($scenarioMatrix as $scenarioItem) {
                $this->budget->createScenarioItem([
                    'hiring_scenario_id' => $scenarioId,
                    'item_label' => (string) $scenarioItem['label'],
                    'scenario_code' => (string) $scenarioItem['code'],
                    'variation_percent' => number_format((float) $scenarioItem['variation_percent'], 2, '.', ''),
                    'quantity' => $quantity,
                    'avg_monthly_cost' => number_format((float) $scenarioItem['avg_monthly_cost'], 2, '.', ''),
                    'cost_current_year' => number_format((float) $scenarioItem['cost_current_year'], 2, '.', ''),
                    'cost_next_year' => number_format((float) $scenarioItem['cost_next_year'], 2, '.', ''),
                ]);
            }

            $this->audit->log(
                entity: 'hiring_scenario',
                entityId: $scenarioId,
                action: 'simulate',
                beforeData: null,
                afterData: [
                    'budget_cycle_id' => $cycleId,
                    'financial_nature' => $financialNature,
                    'organ_id' => $organId,
                    'modality' => $modality,
                    'movement_type' => $movementType,
                    'cargo' => $cargo,
                    'setor' => $setor,
                    'entry_date' => $entryDate,
                    'quantity' => $quantity,
                    'avg_monthly_cost_base' => number_format((float) $baseScenario['avg_monthly_cost'], 2, '.', ''),
                    'months_remaining' => $monthsRemaining,
                    'available_before' => number_format($availableBefore, 2, '.', ''),
                    'risk_level_base' => (string) $baseScenario['risk_level'],
                    'risk_level_worst' => (string) $worstScenario['risk_level'],
                    'avg_source' => $avgSource,
                    'variation_profile' => $variations,
                    'scenario_parameter_id' => (int) ($scenarioParameter['id'] ?? 0),
                    'scenario_matrix' => $scenarioMatrix,
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
                    'financial_nature' => $financialNature,
                    'hiring_scenario_id' => $scenarioId,
                    'organ_id' => $organId,
                    'modality' => $modality,
                    'movement_type' => $movementType,
                    'cargo' => $cargo,
                    'setor' => $setor,
                    'quantity' => $quantity,
                    'risk_level_base' => (string) $baseScenario['risk_level'],
                    'risk_level_worst' => (string) $worstScenario['risk_level'],
                    'cost_current_year_base' => number_format((float) $baseScenario['cost_current_year'], 2, '.', ''),
                    'cost_current_year_worst' => number_format((float) $worstScenario['cost_current_year'], 2, '.', ''),
                    'cost_next_year_base' => number_format((float) $baseScenario['cost_next_year'], 2, '.', ''),
                    'cost_next_year_worst' => number_format((float) $worstScenario['cost_next_year'], 2, '.', ''),
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
            'message' => 'Simulacao multiparametrica executada com sucesso.',
            'errors' => [],
            'simulation' => [
                'year' => $yearValue,
                'financial_nature' => $financialNature,
                'scenario_name' => $scenarioName,
                'modality' => $modality,
                'movement_type' => $movementType,
                'cargo' => $cargo,
                'setor' => $setor,
                'entry_date' => $entryDate,
                'quantity' => $quantity,
                'avg_monthly_cost' => round((float) $baseScenario['avg_monthly_cost'], 2),
                'avg_source' => $avgSource,
                'months_remaining' => $monthsRemaining,
                'cost_current_year_per_person' => round((float) $baseScenario['cost_current_year_per_person'], 2),
                'cost_current_year' => round((float) $baseScenario['cost_current_year'], 2),
                'cost_next_year' => round((float) $baseScenario['cost_next_year'], 2),
                'available_before' => round($availableBefore, 2),
                'remaining_after_current_year' => round((float) $baseScenario['remaining_after_current_year'], 2),
                'max_capacity_before' => (int) ($baseScenario['max_capacity_before'] ?? 0),
                'risk_level' => (string) ($baseScenario['risk_level'] ?? 'baixo'),
                'scenario_matrix' => $scenarioMatrix,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, year?: int}
     */
    public function createAnnualBudgetCycle(array $input, int $userId, string $ip, string $userAgent): array
    {
        $year = $this->parseYear($input['cycle_year'] ?? null);
        $financialNature = $this->parseFinancialNature($input['financial_nature'] ?? null);
        $totalBudget = $this->parseMoneyNullable($input['cycle_total_budget'] ?? null);

        $errors = [];

        if ($year === null) {
            $errors[] = 'Ano do ciclo invalido (use um valor entre 2000 e 2100).';
        }

        if ($financialNature === null) {
            $errors[] = 'Natureza financeira invalida para o ciclo orcamentario.';
        }

        if ($totalBudget === null || $totalBudget < 0.0) {
            $errors[] = 'Orcamento anual MTE invalido (use valor numerico maior ou igual a zero).';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel cadastrar orcamento anual do MTE.',
                'errors' => $errors,
            ];
        }

        if ($year !== null && $financialNature !== null && $this->budget->findCycleByYear($year, $financialNature) !== null) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel cadastrar orcamento anual do MTE.',
                'errors' => ['Ja existe ciclo cadastrado para este ano e natureza financeira. Use a edicao para ajustar o valor.'],
                'year' => $year,
                'financial_nature' => $financialNature,
            ];
        }

        try {
            $cycleId = $this->budget->createCycle(
                year: (int) $year,
                annualFactor: self::DEFAULT_ANNUAL_FACTOR,
                totalBudget: number_format((float) $totalBudget, 2, '.', ''),
                createdBy: $userId > 0 ? $userId : null,
                financialNature: (string) $financialNature
            );
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel cadastrar orcamento anual do MTE.',
                'errors' => ['Falha ao persistir ciclo orcamentario do ano informado.'],
                'year' => (int) $year,
                'financial_nature' => $financialNature,
            ];
        }

        if ($cycleId <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel cadastrar orcamento anual do MTE.',
                'errors' => ['Falha ao persistir ciclo orcamentario do ano informado.'],
                'year' => (int) $year,
                'financial_nature' => $financialNature,
            ];
        }

        $after = $this->budget->findCycleById($cycleId);

        $this->audit->log(
            entity: 'budget_cycle',
            entityId: $cycleId,
            action: 'create',
            beforeData: null,
            afterData: $after,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'budget',
            type: 'budget.cycle_created',
            payload: [
                'budget_cycle_id' => $cycleId,
                'cycle_year' => (int) $year,
                'financial_nature' => (string) $financialNature,
                'total_budget' => number_format((float) $totalBudget, 2, '.', ''),
            ],
            entityId: $cycleId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Orcamento anual do MTE cadastrado com sucesso.',
            'errors' => [],
            'year' => (int) $year,
            'financial_nature' => $financialNature,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, year?: int}
     */
    public function updateAnnualBudgetCycle(array $input, int $userId, string $ip, string $userAgent): array
    {
        $cycleId = max(0, (int) ($input['cycle_id'] ?? 0));
        $totalBudget = $this->parseMoneyNullable($input['cycle_total_budget'] ?? null);

        $errors = [];

        if ($cycleId <= 0) {
            $errors[] = 'Ciclo orcamentario invalido para atualizacao.';
        }

        if ($totalBudget === null || $totalBudget < 0.0) {
            $errors[] = 'Orcamento anual MTE invalido (use valor numerico maior ou igual a zero).';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar orcamento anual do MTE.',
                'errors' => $errors,
            ];
        }

        $before = $this->budget->findCycleById($cycleId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar orcamento anual do MTE.',
                'errors' => ['Ciclo orcamentario nao encontrado.'],
            ];
        }

        $updated = $this->budget->updateCycleTotalBudget(
            cycleId: $cycleId,
            totalBudget: number_format((float) $totalBudget, 2, '.', '')
        );

        if (!$updated) {
            $beforeTotal = round($this->toFloat($before['total_budget'] ?? 0), 2);
            if (abs($beforeTotal - (float) $totalBudget) <= 0.0001) {
                return [
                    'ok' => true,
                    'message' => 'Orcamento anual do MTE mantido sem alteracoes.',
                    'errors' => [],
                    'year' => (int) ($before['cycle_year'] ?? date('Y')),
                    'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
                ];
            }

            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar orcamento anual do MTE.',
                'errors' => ['Nenhuma alteracao foi aplicada ao ciclo orcamentario selecionado.'],
                'year' => (int) ($before['cycle_year'] ?? date('Y')),
                'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
            ];
        }

        $after = $this->budget->findCycleById($cycleId);

        $this->audit->log(
            entity: 'budget_cycle',
            entityId: $cycleId,
            action: 'update',
            beforeData: $before,
            afterData: $after,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'budget',
            type: 'budget.cycle_updated',
            payload: [
                'budget_cycle_id' => $cycleId,
                'cycle_year' => (int) ($before['cycle_year'] ?? 0),
                'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
                'total_budget' => number_format((float) $totalBudget, 2, '.', ''),
            ],
            entityId: $cycleId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Orcamento anual do MTE atualizado com sucesso.',
            'errors' => [],
            'year' => (int) ($before['cycle_year'] ?? date('Y')),
            'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, year?: int}
     */
    public function deleteAnnualBudgetCycle(array $input, int $userId, string $ip, string $userAgent): array
    {
        $cycleId = max(0, (int) ($input['cycle_id'] ?? 0));

        if ($cycleId <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover orcamento anual do MTE.',
                'errors' => ['Ciclo orcamentario invalido para remocao.'],
            ];
        }

        $before = $this->budget->findCycleById($cycleId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover orcamento anual do MTE.',
                'errors' => ['Ciclo orcamentario nao encontrado.'],
            ];
        }

        $dependencies = $this->budget->cycleDependencies($cycleId);
        $scenariosCount = (int) ($dependencies['scenarios_count'] ?? 0);
        $scenarioParametersCount = (int) ($dependencies['scenario_parameters_count'] ?? 0);

        if ($scenariosCount > 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover orcamento anual do MTE.',
                'errors' => ['Este ciclo possui cenarios de simulacao vinculados e nao pode ser removido.'],
                'year' => (int) ($before['cycle_year'] ?? date('Y')),
                'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
            ];
        }

        try {
            $this->budget->beginTransaction();

            if ($scenarioParametersCount > 0) {
                $this->budget->deleteScenarioParametersByCycle($cycleId);
            }

            $deleted = $this->budget->deleteCycle($cycleId);
            if (!$deleted) {
                throw new \RuntimeException('Falha ao remover ciclo orcamentario.');
            }

            $this->audit->log(
                entity: 'budget_cycle',
                entityId: $cycleId,
                action: 'delete',
                beforeData: $before,
                afterData: null,
                metadata: [
                    'removed_scenario_parameters' => $scenarioParametersCount,
                    'removed_scenarios' => $scenariosCount,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'budget',
                type: 'budget.cycle_deleted',
                payload: [
                    'budget_cycle_id' => $cycleId,
                    'cycle_year' => (int) ($before['cycle_year'] ?? 0),
                    'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
                    'removed_scenario_parameters' => $scenarioParametersCount,
                    'removed_scenarios' => $scenariosCount,
                ],
                entityId: $cycleId,
                userId: $userId
            );

            $this->budget->commit();
        } catch (\Throwable $exception) {
            $this->budget->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover orcamento anual do MTE.',
                'errors' => ['Falha ao remover ciclo orcamentario do banco de dados.'],
                'year' => (int) ($before['cycle_year'] ?? date('Y')),
                'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
            ];
        }

        $remainingCycles = $this->budget->listCycles((string) ($before['financial_nature'] ?? 'despesa_reembolso'));
        $redirectYear = (int) ($remainingCycles[0]['cycle_year'] ?? date('Y'));
        if ($redirectYear < 2000 || $redirectYear > 2100) {
            $redirectYear = (int) date('Y');
        }

        return [
            'ok' => true,
            'message' => 'Orcamento anual do MTE removido com sucesso.',
            'errors' => [],
            'year' => $redirectYear,
            'financial_nature' => (string) ($before['financial_nature'] ?? 'despesa_reembolso'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function upsertOrgParameter(array $input, int $userId, string $ip, string $userAgent): array
    {
        $organId = max(0, (int) ($input['organ_id'] ?? 0));
        $cargo = $this->normalizeScopeValue($this->clean($input['cargo'] ?? null));
        $setor = $this->normalizeScopeValue($this->clean($input['setor'] ?? null));
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

        $before = $this->budget->findOrgParameterExact($organId, $cargo, $setor);
        $id = $this->budget->upsertOrgParameter(
            organId: $organId,
            cargo: $cargo,
            setor: $setor,
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

        $after = $this->budget->findOrgParameterExact($organId, $cargo, $setor);

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
                'cargo' => $cargo,
                'setor' => $setor,
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

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function upsertScenarioParameter(int $year, array $input, int $userId, string $ip, string $userAgent): array
    {
        $normalizedYear = $this->normalizeYear($year);
        $financialNature = $this->parseFinancialNature($input['financial_nature'] ?? null);
        if ($financialNature === null) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametros do cenario.',
                'errors' => ['Natureza financeira invalida para parametrizacao do cenario.'],
            ];
        }

        $cycle = $this->budget->ensureCycle(
            year: $normalizedYear,
            annualFactor: self::DEFAULT_ANNUAL_FACTOR,
            createdBy: null,
            financialNature: $financialNature
        );
        $cycleId = (int) ($cycle['id'] ?? 0);

        if ($cycleId <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametros do cenario.',
                'errors' => ['Ciclo orcamentario indisponivel para parametrizacao.'],
            ];
        }

        $organId = max(0, (int) ($input['organ_id'] ?? 0));
        $modality = $this->normalizeModality($this->clean($input['modality'] ?? null));

        $baseVariation = $this->parsePercentageNullable($input['base_variation_percent'] ?? null);
        $updatedVariation = $this->parsePercentageNullable($input['updated_variation_percent'] ?? null);
        $worstVariation = $this->parsePercentageNullable($input['worst_variation_percent'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($organId <= 0 || !$this->budget->organExists($organId)) {
            $errors[] = 'Orgao invalido para parametrizacao de variacao.';
        }

        if ($baseVariation === null) {
            $errors[] = 'Variacao Base invalida.';
        }

        if ($updatedVariation === null) {
            $errors[] = 'Variacao Atualizado invalida.';
        }

        if ($worstVariation === null) {
            $errors[] = 'Variacao Pior Caso invalida.';
        }

        if ($baseVariation !== null && ($baseVariation < -95.0 || $baseVariation > 500.0)) {
            $errors[] = 'Variacao Base fora da faixa permitida (-95% a 500%).';
        }

        if ($updatedVariation !== null && ($updatedVariation < -95.0 || $updatedVariation > 500.0)) {
            $errors[] = 'Variacao Atualizado fora da faixa permitida (-95% a 500%).';
        }

        if ($worstVariation !== null && ($worstVariation < -95.0 || $worstVariation > 500.0)) {
            $errors[] = 'Variacao Pior Caso fora da faixa permitida (-95% a 500%).';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametros do cenario.',
                'errors' => $errors,
            ];
        }

        $before = $this->budget->findScenarioParameterExact($cycleId, $organId, $modality, $financialNature);

        $id = $this->budget->upsertScenarioParameter(
            cycleId: $cycleId,
            financialNature: $financialNature,
            organId: $organId,
            modality: $modality,
            baseVariation: number_format((float) $baseVariation, 2, '.', ''),
            updatedVariation: number_format((float) $updatedVariation, 2, '.', ''),
            worstVariation: number_format((float) $worstVariation, 2, '.', ''),
            notes: $notes === null ? null : mb_substr($notes, 0, 4000),
            updatedBy: $userId > 0 ? $userId : null
        );

        if ($id <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel salvar parametros do cenario.',
                'errors' => ['Falha ao persistir variacoes por orgao/modalidade.'],
            ];
        }

        $after = $this->budget->findScenarioParameterExact($cycleId, $organId, $modality, $financialNature);

        $this->audit->log(
            entity: 'budget_scenario_parameter',
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
            type: 'budget.scenario_parameter_upserted',
            payload: [
                'budget_cycle_id' => $cycleId,
                'financial_nature' => $financialNature,
                'organ_id' => $organId,
                'modality' => $modality,
                'base_variation_percent' => number_format((float) $baseVariation, 2, '.', ''),
                'updated_variation_percent' => number_format((float) $updatedVariation, 2, '.', ''),
                'worst_variation_percent' => number_format((float) $worstVariation, 2, '.', ''),
                'parameter_id' => $id,
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Parametros de cenario salvos com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $seriesRows
     * @return array<string, mixed>
     */
    private function buildProjection(int $year, array $seriesRows, float $annualFactor, float $totalBudget): array
    {
        $months = [];
        $annualExecuted = 0.0;
        $annualCommitted = 0.0;
        $annualProjectedBase = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $row = $seriesRows[$month - 1] ?? [];

            $executed = round($this->toFloat($row['executed_amount'] ?? 0), 2);
            $committed = round($this->toFloat($row['committed_amount'] ?? 0), 2);
            $projectedBase = round($this->toFloat($row['projected_base_amount'] ?? 0), 2);
            $projectedTotal = round($executed + $committed + $projectedBase, 2);

            $annualExecuted += $executed;
            $annualCommitted += $committed;
            $annualProjectedBase += $projectedBase;

            $months[] = [
                'month' => $month,
                'label' => self::MONTH_LABELS[$month] ?? sprintf('%02d', $month),
                'executed_amount' => $executed,
                'committed_amount' => $committed,
                'projected_base_amount' => $projectedBase,
                'projected_total' => $projectedTotal,
            ];
        }

        $annualExecuted = round($annualExecuted, 2);
        $annualCommitted = round($annualCommitted, 2);
        $annualProjectedBase = round($annualProjectedBase, 2);
        $annualProjectionCurrentYear = round($annualExecuted + $annualCommitted + $annualProjectedBase, 2);
        $monthlyAverageProjection = round($annualProjectionCurrentYear / 12, 2);

        $projectedNextYearBase = round(max(0.0, $annualProjectedBase / 12) * max(0.1, $annualFactor), 2);
        $projectedNextYearUpdated = round($projectedNextYearBase * (1 + (self::DEFAULT_SCENARIO_VARIATIONS['atualizado'] / 100)), 2);
        $projectedNextYearWorst = round($projectedNextYearBase * (1 + (self::DEFAULT_SCENARIO_VARIATIONS['pior_caso'] / 100)), 2);

        return [
            'year' => $year,
            'months' => $months,
            'monthly_average_projection' => $monthlyAverageProjection,
            'annual_executed' => $annualExecuted,
            'annual_committed' => $annualCommitted,
            'annual_projected_base' => $annualProjectedBase,
            'annual_projection_current_year' => $annualProjectionCurrentYear,
            'annual_balance_current_year' => round($totalBudget - $annualProjectionCurrentYear, 2),
            'next_year_scenarios' => [
                'base' => $projectedNextYearBase,
                'atualizado' => $projectedNextYearUpdated,
                'pior_caso' => $projectedNextYearWorst,
            ],
        ];
    }

    /** @return array<int, array{code: string, label: string}> */
    private function scenarioDefinitions(): array
    {
        return [
            ['code' => 'base', 'label' => 'Base'],
            ['code' => 'atualizado', 'label' => 'Atualizado'],
            ['code' => 'pior_caso', 'label' => 'Pior Caso'],
        ];
    }

    /**
     * @param array<string, mixed>|null $scenarioParameter
     * @return array<string, float>
     */
    private function resolveScenarioVariations(?array $scenarioParameter): array
    {
        $base = $this->normalizeVariation($this->toFloat($scenarioParameter['base_variation_percent'] ?? self::DEFAULT_SCENARIO_VARIATIONS['base']));
        $updated = $this->normalizeVariation($this->toFloat($scenarioParameter['updated_variation_percent'] ?? self::DEFAULT_SCENARIO_VARIATIONS['atualizado']));
        $worst = $this->normalizeVariation($this->toFloat($scenarioParameter['worst_variation_percent'] ?? self::DEFAULT_SCENARIO_VARIATIONS['pior_caso']));

        return [
            'base' => $base,
            'atualizado' => $updated,
            'pior_caso' => $worst,
        ];
    }

    private function normalizeVariation(float $value): float
    {
        return max(-95.0, min(500.0, round($value, 2)));
    }

    /**
     * @param array<int, array<string, mixed>> $projectionMonths
     * @return array<int, array<string, mixed>>
     */
    private function buildMonthlyInsufficiencyRisks(array $projectionMonths, float $totalBudget): array
    {
        $result = [];
        $cumulativeProjection = 0.0;

        foreach ($projectionMonths as $month) {
            $monthNumber = max(1, min(12, (int) ($month['month'] ?? 1)));
            $monthProjection = round($this->toFloat($month['projected_total'] ?? 0), 2);
            $cumulativeProjection = round($cumulativeProjection + $monthProjection, 2);

            $cumulativeBudget = round(($totalBudget / 12) * $monthNumber, 2);
            $difference = round($cumulativeBudget - $cumulativeProjection, 2);
            $pressure = $cumulativeBudget > 0.009
                ? round(($cumulativeProjection / $cumulativeBudget) * 100, 2)
                : 0.0;

            $riskLevel = 'baixo';
            if ($difference < -0.009) {
                $riskLevel = 'alto';
            } elseif ($cumulativeBudget > 0.009 && $difference <= ($cumulativeBudget * 0.10)) {
                $riskLevel = 'medio';
            }

            $result[] = [
                'month' => $monthNumber,
                'label' => (string) ($month['label'] ?? sprintf('%02d', $monthNumber)),
                'cumulative_budget' => $cumulativeBudget,
                'cumulative_projection' => $cumulativeProjection,
                'difference' => $difference,
                'pressure_percent' => $pressure,
                'risk_level' => $riskLevel,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $insufficiencyRisks
     * @param array<int, array<string, mixed>> $offenders
     * @return array<int, array<string, mixed>>
     */
    private function buildActiveAlerts(
        float $totalBudget,
        float $availableAmount,
        float $projectedBalanceNextYear,
        array $insufficiencyRisks,
        array $offenders
    ): array {
        $alerts = [];

        if ($availableAmount < -0.009) {
            $alerts[] = [
                'code' => 'available_negative',
                'level' => 'alto',
                'title' => 'Saldo disponivel negativo no ciclo',
                'message' => sprintf('Deficit atual de R$ %s no saldo disponivel.', number_format(abs($availableAmount), 2, ',', '.')),
            ];
        } elseif ($totalBudget > 0.009 && $availableAmount <= ($totalBudget * 0.10)) {
            $alerts[] = [
                'code' => 'available_low_margin',
                'level' => 'medio',
                'title' => 'Margem de saldo no limite',
                'message' => 'Saldo disponivel em faixa de atencao (<= 10% do orcamento anual).',
            ];
        }

        if ($projectedBalanceNextYear < -0.009) {
            $alerts[] = [
                'code' => 'next_year_deficit',
                'level' => 'alto',
                'title' => 'Deficit projetado para o proximo ano',
                'message' => sprintf('Projecao indica deficit de R$ %s no proximo ano.', number_format(abs($projectedBalanceNextYear), 2, ',', '.')),
            ];
        }

        foreach ($insufficiencyRisks as $risk) {
            $riskLevel = (string) ($risk['risk_level'] ?? 'baixo');
            if ($riskLevel !== 'alto') {
                continue;
            }

            $alerts[] = [
                'code' => 'monthly_insufficiency',
                'level' => 'alto',
                'title' => 'Risco alto de insuficiencia mensal',
                'message' => sprintf(
                    'Mes %s com pressao de %s e diferenca acumulada de R$ %s.',
                    (string) ($risk['label'] ?? '-'),
                    number_format((float) ($risk['pressure_percent'] ?? 0), 2, ',', '.') . '%',
                    number_format((float) abs((float) ($risk['difference'] ?? 0)), 2, ',', '.')
                ),
            ];
            break;
        }

        if ($offenders !== []) {
            $topOffender = $offenders[0];
            $deficit = max(0.0, $this->toFloat($topOffender['deficit_amount'] ?? 0));
            if ($deficit > 0.009) {
                $alerts[] = [
                    'code' => 'top_offender_deficit',
                    'level' => 'medio',
                    'title' => 'Ofensor relevante de desvio no pior caso',
                    'message' => sprintf(
                        '%s (%s) projeta deficit de R$ %s.',
                        (string) ($topOffender['scenario_name'] ?? 'Cenario'),
                        (string) ($topOffender['organ_name'] ?? 'Orgao'),
                        number_format($deficit, 2, ',', '.')
                    ),
                ];
            }
        }

        return $alerts;
    }

    private function normalizeYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function parseYear(mixed $value): ?int
    {
        $raw = trim((string) $value);
        if (!preg_match('/^\d{4}$/', $raw)) {
            return null;
        }

        $year = (int) $raw;

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }

    private function parseFinancialNature(mixed $value, bool $allowDefault = true): ?string
    {
        $normalized = trim(mb_strtolower((string) $value));
        if ($normalized === '') {
            return $allowDefault ? 'despesa_reembolso' : null;
        }

        return in_array($normalized, self::ALLOWED_FINANCIAL_NATURES, true) ? $normalized : null;
    }

    private function normalizeFinancialNature(string $value): string
    {
        $parsed = $this->parseFinancialNature($value);

        return $parsed ?? 'despesa_reembolso';
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

    private function parsePercentageNullable(mixed $value): ?float
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

    private function normalizeModality(?string $value): string
    {
        $text = trim(mb_strtolower((string) $value));
        if ($text === '') {
            return 'geral';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_substr($text, 0, 80);
    }

    private function normalizeMovementType(?string $value): string
    {
        $text = trim(mb_strtolower((string) $value));

        return $text === 'saida' ? 'saida' : 'entrada';
    }

    private function resolveParameterSource(?array $parameter, string $inputCargo, string $inputSetor): string
    {
        $paramCargo = $this->normalizeScopeValue((string) ($parameter['cargo'] ?? ''));
        $paramSetor = $this->normalizeScopeValue((string) ($parameter['setor'] ?? ''));
        $cargo = $this->normalizeScopeValue($inputCargo);
        $setor = $this->normalizeScopeValue($inputSetor);

        if ($paramCargo !== '' && $paramSetor !== '' && $paramCargo === $cargo && $paramSetor === $setor) {
            return 'parametro_orgao_cargo_setor';
        }

        if ($paramCargo !== '' && $paramCargo === $cargo && $paramSetor === '') {
            return 'parametro_orgao_cargo';
        }

        if ($paramSetor !== '' && $paramSetor === $setor && $paramCargo === '') {
            return 'parametro_orgao_setor';
        }

        return 'parametro_orgao';
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
