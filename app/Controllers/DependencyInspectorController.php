<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\DependencyInspectorService;

final class DependencyInspectorController extends Controller
{
    public function index(Request $request): void
    {
        $service = $this->service();
        $entity = trim((string) $request->input('entity', ''));
        $idRaw = trim((string) $request->input('id', ''));

        $inspection = null;
        if ($entity !== '' && $idRaw !== '') {
            $inspection = $service->inspect($entity, (int) $idRaw);
        }

        $this->view('system/dependencies', [
            'title' => 'Diagnostico de dependencias',
            'catalog' => $service->catalog(),
            'overview' => $service->systemOverview(),
            'selectedEntity' => $entity,
            'selectedId' => $idRaw,
            'inspection' => $inspection,
        ]);
    }

    private function service(): DependencyInspectorService
    {
        return new DependencyInspectorService($this->app->db());
    }
}
