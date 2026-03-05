<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\GlobalSearchRepository;
use App\Services\GlobalSearchService;

final class GlobalSearchController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'scope' => (string) $request->input('scope', 'all'),
            'limit' => 20,
        ];

        $capabilities = [
            'people' => $this->app->auth()->hasPermission('people.view'),
            'organs' => $this->app->auth()->hasPermission('organs.view'),
            'process_meta' => $this->app->auth()->hasPermission('process_meta.view'),
            'documents' => $this->app->auth()->hasPermission('people.view'),
            'documents_sensitive' => $this->app->auth()->hasPermission('people.documents.sensitive'),
            'cpf_full' => $this->app->auth()->hasPermission('people.cpf.full'),
        ];

        $result = $this->service()->searchAll(
            filters: $filters,
            capabilities: $capabilities,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->view('global_search/index', [
            'title' => 'Busca global',
            'filters' => $filters,
            'searchResult' => $result,
            'capabilities' => $capabilities,
            'scopeOptions' => $this->scopeOptions($capabilities),
        ]);
    }

    /** @param array<string, bool> $capabilities
     *  @return array<int, array{value: string, label: string}>
     */
    private function scopeOptions(array $capabilities): array
    {
        $options = [
            ['value' => 'all', 'label' => 'Tudo'],
        ];

        if (($capabilities['people'] ?? false) === true) {
            $options[] = ['value' => 'people', 'label' => 'Pessoas'];
        }
        if (($capabilities['organs'] ?? false) === true) {
            $options[] = ['value' => 'organs', 'label' => 'Orgaos'];
        }
        if (($capabilities['process_meta'] ?? false) === true) {
            $options[] = ['value' => 'process_meta', 'label' => 'Processo formal/DOU'];
        }
        if (($capabilities['documents'] ?? false) === true) {
            $options[] = ['value' => 'documents', 'label' => 'Documentos'];
        }

        return $options;
    }

    private function service(): GlobalSearchService
    {
        return new GlobalSearchService(
            new GlobalSearchRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
