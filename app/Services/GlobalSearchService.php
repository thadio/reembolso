<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GlobalSearchRepository;

final class GlobalSearchService
{
    private const MIN_QUERY_LENGTH = 3;
    private const ALLOWED_SCOPE = ['all', 'people', 'organs', 'process_meta', 'documents'];

    public function __construct(
        private GlobalSearchRepository $search,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, bool> $capabilities
     * @return array{
     *   query: string,
     *   scope: string,
     *   min_query_length: int,
     *   searched: bool,
     *   results: array<string, array<int, array<string, mixed>>>,
     *   totals: array<string, int>
     * }
     */
    public function searchAll(
        array $filters,
        array $capabilities,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $query = trim((string) ($filters['q'] ?? ''));
        $scopeRaw = mb_strtolower(trim((string) ($filters['scope'] ?? 'all')));
        $scope = in_array($scopeRaw, self::ALLOWED_SCOPE, true) ? $scopeRaw : 'all';

        $emptyResult = [
            'people' => [],
            'organs' => [],
            'process_meta' => [],
            'documents' => [],
        ];

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [
                'query' => $query,
                'scope' => $scope,
                'min_query_length' => self::MIN_QUERY_LENGTH,
                'searched' => false,
                'results' => $emptyResult,
                'totals' => [
                    'people' => 0,
                    'organs' => 0,
                    'process_meta' => 0,
                    'documents' => 0,
                    'all' => 0,
                ],
            ];
        }

        $limit = max(5, min(60, (int) ($filters['limit'] ?? 20)));

        $canViewPeople = ($capabilities['people'] ?? false) === true;
        $canViewOrgans = ($capabilities['organs'] ?? false) === true;
        $canViewProcessMeta = ($capabilities['process_meta'] ?? false) === true;
        $canViewDocuments = ($capabilities['documents'] ?? false) === true;
        $canViewSensitiveDocuments = ($capabilities['documents_sensitive'] ?? false) === true;

        $runPeople = $canViewPeople && ($scope === 'all' || $scope === 'people');
        $runOrgans = $canViewOrgans && ($scope === 'all' || $scope === 'organs');
        $runProcessMeta = $canViewProcessMeta && ($scope === 'all' || $scope === 'process_meta');
        $runDocuments = $canViewDocuments && ($scope === 'all' || $scope === 'documents');

        $results = $emptyResult;
        if ($runPeople) {
            $results['people'] = $this->search->searchPeople($query, $limit);
        }
        if ($runOrgans) {
            $results['organs'] = $this->search->searchOrgans($query, $limit);
        }
        if ($runProcessMeta) {
            $results['process_meta'] = $this->search->searchProcessMetadata($query, $limit);
        }
        if ($runDocuments) {
            $results['documents'] = $this->search->searchDocuments($query, $limit, $canViewSensitiveDocuments);
        }

        $totals = [
            'people' => count($results['people']),
            'organs' => count($results['organs']),
            'process_meta' => count($results['process_meta']),
            'documents' => count($results['documents']),
        ];
        $totals['all'] = $totals['people'] + $totals['organs'] + $totals['process_meta'] + $totals['documents'];

        $this->audit->log(
            entity: 'global_search',
            entityId: null,
            action: 'search',
            beforeData: null,
            afterData: [
                'query' => $query,
                'scope' => $scope,
                'totals' => $totals,
            ],
            metadata: [
                'sections_enabled' => [
                    'people' => $runPeople,
                    'organs' => $runOrgans,
                    'process_meta' => $runProcessMeta,
                    'documents' => $runDocuments,
                ],
            ],
            userId: $userId > 0 ? $userId : null,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'system',
            type: 'global_search.executed',
            payload: [
                'scope' => $scope,
                'results_total' => $totals['all'],
                'query_length' => mb_strlen($query),
            ],
            entityId: null,
            userId: $userId > 0 ? $userId : null
        );

        return [
            'query' => $query,
            'scope' => $scope,
            'min_query_length' => self::MIN_QUERY_LENGTH,
            'searched' => true,
            'results' => $results,
            'totals' => $totals,
        ];
    }
}
