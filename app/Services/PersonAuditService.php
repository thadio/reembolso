<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PersonAuditRepository;

final class PersonAuditService
{
    public function __construct(private PersonAuditRepository $audits)
    {
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   pagination: array<string, int>,
     *   filters: array{entity: string, action: string, q: string, from_date: string, to_date: string},
     *   options: array{entities: array<int, string>, actions: array<int, string>}
     * }
     */
    public function profileData(int $personId, array $inputFilters, int $page = 1, int $perPage = 12): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));

        $result = $this->audits->paginateByPerson($personId, $filters, $page, $perPage);

        return [
            'items' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => $filters,
            'options' => [
                'entities' => $this->audits->entitiesByPerson($personId),
                'actions' => $this->audits->actionsByPerson($personId, $filters['entity'] !== '' ? $filters['entity'] : null),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{rows: array<int, array<string, mixed>>, filters: array{entity: string, action: string, q: string, from_date: string, to_date: string}}
     */
    public function exportRows(int $personId, array $inputFilters, int $limit = 2000): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        $limit = max(1, min(5000, $limit));

        return [
            'rows' => $this->audits->listByPerson($personId, $filters, $limit),
            'filters' => $filters,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{entity: string, action: string, q: string, from_date: string, to_date: string}
     */
    private function normalizeFilters(array $input): array
    {
        $entity = $this->sanitizeText($input['entity'] ?? '', 120);
        $action = $this->sanitizeText($input['action'] ?? '', 120);
        $q = $this->sanitizeText($input['q'] ?? '', 180);

        $fromDate = $this->normalizeDate($input['from_date'] ?? null);
        $toDate = $this->normalizeDate($input['to_date'] ?? null);

        if ($fromDate !== '' && $toDate !== '' && strtotime($fromDate) > strtotime($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'entity' => $entity,
            'action' => $action,
            'q' => $q,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    private function sanitizeText(mixed $value, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function normalizeDate(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }
}
