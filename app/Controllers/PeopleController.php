<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class PeopleController extends Controller
{
    public function index(Request $request): void
    {
        $organId = max(0, (int) $request->input('organ_id', '0'));

        $this->view('people/index', [
            'title' => 'Pessoas',
            'organIdFilter' => $organId,
        ]);
    }
}
