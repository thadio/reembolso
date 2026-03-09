<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReconciliationRepository;
use DateTimeImmutable;

final class ReconciliationService
{
    public function __construct(private ReconciliationRepository $repository)
    {
    }

    /**
     * @return array{
     *   active_plan: array<string, mixed>|null,
     *   summary: array<string, int|float|string>,
     *   rows: array<int, array<string, int|float|string>>
     * }
     */
    public function profileData(int $personId, int $months = 8): array
    {
        $months = max(3, min(24, $months));

        $activePlan = $this->repository->activePlanByPerson($personId);
        $costItems = $this->repository->activePlanItemsByPerson($personId);
        $entries = $this->repository->reimbursementEntriesByPerson($personId, 1200);

        $actualByMonth = [];
        foreach ($entries as $entry) {
            $month = $this->resolveCompetenceMonth($entry);
            if ($month === null) {
                continue;
            }

            $amount = $this->toFloat($entry['amount'] ?? 0);
            if (!isset($actualByMonth[$month])) {
                $actualByMonth[$month] = [
                    'posted' => 0.0,
                    'paid' => 0.0,
                ];
            }

            $actualByMonth[$month]['posted'] += $amount;
            if ((string) ($entry['status'] ?? '') === 'pago') {
                $actualByMonth[$month]['paid'] += $amount;
            }
        }

        $monthsList = $this->buildMonthsList(array_keys($actualByMonth), $months);
        $rows = [];

        foreach ($monthsList as $month) {
            $expected = $this->expectedForMonth($costItems, $month);
            $posted = isset($actualByMonth[$month]['posted']) ? (float) $actualByMonth[$month]['posted'] : 0.0;
            $paid = isset($actualByMonth[$month]['paid']) ? (float) $actualByMonth[$month]['paid'] : 0.0;

            $rows[] = [
                'competence' => $month,
                'expected' => $this->roundMoney($expected),
                'actual_posted' => $this->roundMoney($posted),
                'actual_paid' => $this->roundMoney($paid),
                'deviation_posted' => $this->roundMoney($posted - $expected),
                'deviation_paid' => $this->roundMoney($paid - $expected),
            ];
        }

        $summary = $this->buildSummary($rows);

        return [
            'active_plan' => $activePlan,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int|float|string>
     */
    private function buildSummary(array $rows): array
    {
        $currentMonth = date('Y-m-01');
        $expectedCurrent = 0.0;
        $actualPostedCurrent = 0.0;
        $actualPaidCurrent = 0.0;

        $expectedWindow = 0.0;
        $actualPostedWindow = 0.0;
        $actualPaidWindow = 0.0;

        foreach ($rows as $row) {
            $expected = $this->toFloat($row['expected'] ?? 0);
            $posted = $this->toFloat($row['actual_posted'] ?? 0);
            $paid = $this->toFloat($row['actual_paid'] ?? 0);

            $expectedWindow += $expected;
            $actualPostedWindow += $posted;
            $actualPaidWindow += $paid;

            if ((string) ($row['competence'] ?? '') === $currentMonth) {
                $expectedCurrent = $expected;
                $actualPostedCurrent = $posted;
                $actualPaidCurrent = $paid;
            }
        }

        return [
            'current_month' => $currentMonth,
            'months_analyzed' => count($rows),
            'expected_current' => $this->roundMoney($expectedCurrent),
            'actual_posted_current' => $this->roundMoney($actualPostedCurrent),
            'actual_paid_current' => $this->roundMoney($actualPaidCurrent),
            'deviation_posted_current' => $this->roundMoney($actualPostedCurrent - $expectedCurrent),
            'deviation_paid_current' => $this->roundMoney($actualPaidCurrent - $expectedCurrent),
            'expected_window_total' => $this->roundMoney($expectedWindow),
            'actual_posted_window_total' => $this->roundMoney($actualPostedWindow),
            'actual_paid_window_total' => $this->roundMoney($actualPaidWindow),
            'deviation_posted_window_total' => $this->roundMoney($actualPostedWindow - $expectedWindow),
            'deviation_paid_window_total' => $this->roundMoney($actualPaidWindow - $expectedWindow),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $costItems
     */
    private function expectedForMonth(array $costItems, string $month): float
    {
        $monthStart = $this->asDate($month);
        if ($monthStart === null) {
            return 0.0;
        }

        $monthStart = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), 1)->setTime(0, 0, 0);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        $total = 0.0;

        foreach ($costItems as $item) {
            $amount = $this->toFloat($item['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $type = (string) ($item['cost_type'] ?? '');

            if ($type === 'mensal' || $type === 'anual') {
                if (!$this->isItemActiveInMonth($item, $monthStart, $monthEnd)) {
                    continue;
                }

                $total += $type === 'mensal' ? $amount : ($amount / 12);
                continue;
            }

            if ($type === 'eventual' || $type === 'unico') {
                $effectiveDate = $this->singleItemEffectiveDate($item);
                if ($effectiveDate === null) {
                    continue;
                }

                if ($effectiveDate->format('Y-m') === $monthStart->format('Y-m')) {
                    $total += $amount;
                }
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isItemActiveInMonth(array $item, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd): bool
    {
        $startDate = $this->asDate($item['start_date'] ?? null);
        $endDate = $this->asDate($item['end_date'] ?? null);

        if ($startDate !== null && $startDate > $monthEnd) {
            return false;
        }

        if ($endDate !== null && $endDate < $monthStart) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function singleItemEffectiveDate(array $item): ?DateTimeImmutable
    {
        $startDate = $this->asDate($item['start_date'] ?? null);
        if ($startDate !== null) {
            return $startDate;
        }

        return $this->asDate($item['created_at'] ?? null);
    }

    /**
     * @param array<int, string> $monthsFromEntries
     * @return array<int, string>
     */
    private function buildMonthsList(array $monthsFromEntries, int $limit): array
    {
        $monthsSet = [];
        $currentMonth = date('Y-m-01');
        $monthsSet[$currentMonth] = true;

        foreach ($monthsFromEntries as $month) {
            $normalized = $this->normalizeMonth($month);
            if ($normalized === null) {
                continue;
            }

            $monthsSet[$normalized] = true;
        }

        $cursor = new DateTimeImmutable($currentMonth);
        while (count($monthsSet) < $limit) {
            $cursor = $cursor->modify('-1 month');
            $monthsSet[$cursor->format('Y-m-01')] = true;
        }

        $months = array_keys($monthsSet);
        rsort($months);

        return array_slice($months, 0, $limit);
    }

    /** @param array<string, mixed> $entry */
    private function resolveCompetenceMonth(array $entry): ?string
    {
        $referenceMonth = $this->normalizeMonth($entry['reference_month'] ?? null);
        if ($referenceMonth !== null) {
            return $referenceMonth;
        }

        $paidMonth = $this->normalizeMonth($entry['paid_at'] ?? null);
        if ($paidMonth !== null) {
            return $paidMonth;
        }

        return $this->normalizeMonth($entry['created_at'] ?? null);
    }

    private function normalizeMonth(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-01', $timestamp);
    }

    private function asDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric((string) $value) ? (float) $value : 0.0;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}
