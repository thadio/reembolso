<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class HomeDashboardService
{
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
        private BudgetService $budgetService,
        private DashboardRepository $dashboardRepository
    ) {
    }

    /**
     * @return array{
     *   year: int,
     *   generated_at: string,
     *   summary: array<string, float|int>,
     *   monthly_chart: array<int, array<string, float|int|string>>,
     *   people_projection: array<int, array<string, float|int|string>>,
     *   links: array<string, string>
     * }
     */
    public function overview(int $year): array
    {
        $cycleYear = $this->normalizeYear($year);
        $budget = $this->budgetService->dashboard($cycleYear, 'despesa_reembolso');
        $summary = is_array($budget['summary'] ?? null) ? $budget['summary'] : [];
        $projection = is_array($budget['projection'] ?? null) ? $budget['projection'] : [];
        $projectionMonths = is_array($projection['months'] ?? null) ? $projection['months'] : [];

        $totalBudget = max(0.0, (float) ($summary['total_budget'] ?? 0.0));
        $spentYearToDate = max(0.0, (float) ($summary['executed_amount'] ?? 0.0));
        $committedAmount = max(0.0, (float) ($summary['committed_amount'] ?? 0.0));
        $availableBalance = round(max(0.0, (float) ($summary['available_amount'] ?? ($totalBudget - $spentYearToDate))), 2);
        $executionPercent = $totalBudget > 0.0 ? round(($spentYearToDate / $totalBudget) * 100, 2) : 0.0;
        $budgetLimitPerMonth = $totalBudget > 0.0 ? round($totalBudget / 12, 2) : 0.0;
        $projectedBalanceYearEnd = round((float) ($projection['annual_balance_current_year'] ?? 0.0), 2);
        $projectedSpentNextYear = round(max(0.0, (float) ($summary['projected_next_year'] ?? 0.0)), 2);

        $monthlyChart = [];
        for ($month = 1; $month <= 12; $month++) {
            $row = $projectionMonths[$month - 1] ?? [];
            $monthlyChart[] = [
                'month' => $month,
                'label' => self::MONTH_LABELS[$month] ?? sprintf('%02d', $month),
                'real_amount' => round(max(0.0, (float) ($row['executed_amount'] ?? 0.0)), 2),
                'planned_amount' => round(max(0.0, (float) ($row['projected_base_amount'] ?? 0.0)), 2),
                'budget_limit' => $budgetLimitPerMonth,
            ];
        }

        $projectionRows = $this->dashboardRepository->projectedPeopleStackByMonth($cycleYear);
        $projectionByMonth = [];
        foreach ($projectionRows as $row) {
            $monthNumber = max(1, min(12, (int) ($row['month_number'] ?? 0)));
            $projectionByMonth[$monthNumber] = $row;
        }

        $peopleProjection = [];
        for ($month = 1; $month <= 12; $month++) {
            $row = $projectionByMonth[$month] ?? [];
            $activePeople = max(0, (int) ($row['active_people'] ?? 0));
            $pipelinePeople = max(0, (int) ($row['pipeline_people'] ?? 0));
            $totalPeople = max($activePeople + $pipelinePeople, (int) ($row['total_people'] ?? 0));

            $peopleProjection[] = [
                'month' => $month,
                'label' => self::MONTH_LABELS[$month] ?? sprintf('%02d', $month),
                'active_people' => $activePeople,
                'pipeline_people' => $pipelinePeople,
                'total_people' => $totalPeople,
            ];
        }

        return [
            'year' => $cycleYear,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_budget' => round($totalBudget, 2),
                'spent_year_to_date' => round($spentYearToDate, 2),
                'committed_amount' => round($committedAmount, 2),
                'available_balance' => $availableBalance,
                'execution_percent' => $executionPercent,
                'projected_balance_year_end' => $projectedBalanceYearEnd,
                'projected_spent_next_year' => $projectedSpentNextYear,
            ],
            'monthly_chart' => $monthlyChart,
            'people_projection' => $peopleProjection,
            'links' => [
                'budget' => '/budget?year=' . $cycleYear . '&financial_nature=despesa_reembolso',
                'dashboard2' => '/dashboard2',
            ],
        ];
    }

    private function normalizeYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }
}
