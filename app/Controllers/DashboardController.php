<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\DashboardRepository;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $dashboard = $this->service()->overview(8);

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'dashboard' => $dashboard,
        ]);
    }

    private function service(): DashboardService
    {
        return new DashboardService(
            new DashboardRepository($this->app->db())
        );
    }
}
