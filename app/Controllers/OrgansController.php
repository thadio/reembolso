<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class OrgansController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('organs/index', [
            'title' => 'Órgãos',
        ]);
    }
}
