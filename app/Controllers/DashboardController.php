<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\BudgetService;
use App\Services\DashboardService;
use App\Services\HomeDashboardService;
use Throwable;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $year = (int) $request->input('year', (string) date('Y'));
        try {
            $homeDashboard = $this->homeService()->overview($year);
        } catch (Throwable $throwable) {
            Logger::error('Dashboard home overview failed', [
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'year' => $year,
            ]);
            $homeDashboard = $this->fallbackHomeDashboard($year);
        }

        $this->view('dashboard/home', [
            'title' => 'Dashboard',
            'homeDashboard' => $homeDashboard,
        ]);
    }

    public function legacy(Request $request): void
    {
        $dashboard = $this->service()->overview(8);

        $this->view('dashboard/index', [
            'title' => 'Dashboard 2',
            'dashboard' => $dashboard,
        ]);
    }

    private function service(): DashboardService
    {
        return new DashboardService(
            new DashboardRepository($this->app->db()),
            $this->app->config()
        );
    }

    private function homeService(): HomeDashboardService
    {
        return new HomeDashboardService(
            new BudgetService(
                new BudgetRepository($this->app->db()),
                $this->app->audit(),
                $this->app->events()
            ),
            new DashboardRepository($this->app->db())
        );
    }

    /** @return array<string, mixed> */
    private function fallbackHomeDashboard(int $year): array
    {
        $cycleYear = ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
        $labels = [
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

        $monthlyChart = [];
        $peopleProjection = [];
        for ($month = 1; $month <= 12; $month++) {
            $label = $labels[$month] ?? sprintf('%02d', $month);
            $monthlyChart[] = [
                'month' => $month,
                'label' => $label,
                'real_amount' => 0.0,
                'planned_amount' => 0.0,
                'budget_limit' => 0.0,
            ];
            $peopleProjection[] = [
                'month' => $month,
                'label' => $label,
                'active_people' => 0,
                'pipeline_people' => 0,
                'total_people' => 0,
            ];
        }

        return [
            'year' => $cycleYear,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_budget' => 0.0,
                'spent_year_to_date' => 0.0,
                'committed_amount' => 0.0,
                'available_balance' => 0.0,
                'execution_percent' => 0.0,
                'projected_balance_year_end' => 0.0,
                'projected_spent_next_year' => 0.0,
            ],
            'monthly_chart' => $monthlyChart,
            'people_projection' => $peopleProjection,
            'links' => [
                'budget' => '/budget?year=' . $cycleYear . '&financial_nature=despesa_reembolso',
                'dashboard2' => '/dashboard2',
            ],
        ];
    }
}
