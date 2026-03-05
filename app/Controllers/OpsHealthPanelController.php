<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\OpsHealthPanelService;

final class OpsHealthPanelController extends Controller
{
    public function index(Request $request): void
    {
        $panel = $this->service()->overview();

        $this->view('ops/health_panel', [
            'title' => 'Observabilidade operacional',
            'panel' => $panel,
        ]);
    }

    private function service(): OpsHealthPanelService
    {
        return new OpsHealthPanelService($this->app->config());
    }
}
