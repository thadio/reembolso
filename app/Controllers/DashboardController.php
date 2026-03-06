<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\BudgetService;
use App\Services\DashboardService;
use App\Services\HomeDashboardService;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $year = (int) $request->input('year', (string) date('Y'));
        $homeDashboard = $this->homeService()->overview($year);

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
}
