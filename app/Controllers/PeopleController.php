<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class PeopleController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('people/index', [
            'title' => 'Pessoas',
        ]);
    }
}
