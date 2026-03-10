<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\CostMirrorRepository;
use App\Services\CostMirrorService;

final class BulkImportsController extends Controller
{
    public function index(Request $request): void
    {
        $canImportPeople = $this->app->auth()->hasPermission('people.import_bulk');
        $canImportOrgans = $this->app->auth()->hasPermission('organs.import_bulk');
        $canImportCostMirrorItems = $this->app->auth()->hasPermission('cost_mirror.import_bulk');

        $mirrorOptions = [];
        if ($canImportCostMirrorItems) {
            $mirrorOptions = $this->costMirrorService()->paginate([
                'q' => '',
                'status' => '',
                'organ_id' => 0,
                'person_id' => 0,
                'reference_month' => '',
                'sort' => 'reference_month',
                'dir' => 'desc',
            ], 1, 250)['items'];
        }

        $this->view('bulk_imports/index', [
            'title' => 'Importacoes em lote',
            'canImportPeople' => $canImportPeople,
            'canImportOrgans' => $canImportOrgans,
            'canImportCostMirrorItems' => $canImportCostMirrorItems,
            'mirrorOptions' => $mirrorOptions,
            'selectedMirrorId' => max(0, (int) $request->input('mirror_id', old('mirror_id', '0'))),
        ]);
    }

    private function costMirrorService(): CostMirrorService
    {
        return new CostMirrorService(
            new CostMirrorRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
