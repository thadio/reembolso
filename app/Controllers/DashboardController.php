<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('dashboard/index', [
            'title' => 'Dashboard',
        ]);
    }
}
