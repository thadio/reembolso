<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostMirrorReconciliationRepository;
use DateTimeImmutable;

final class CostMirrorReconciliationService
{
    private const JUSTIFICATION_THRESHOLD = 500.00;

    public function __construct(
        private CostMirrorReconciliationRepository $reconciliation,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{
     *   mirror: array<string, mixed>|null,
     *   review: array<string, mixed>|null,
     *   divergences: array<int, array<string, mixed>>,
     *   summary: array<string, int|float>
     * }
     */
    public function detailData(int $mirrorId): array
    {
        $mirror = $this->reconciliation->findMirrorById($mirrorId);
        $review = $this->reconciliation->findReconciliationByMirror($mirrorId);
        $divergences = $review === null
            ? []
            : $this->reconciliation->divergencesByReconciliation((int) ($review['id'] ?? 0));

        $summary = [
            'total' => count($divergences),
            'baixa' => 0,
            'media' => 0,
            'alta' => 0,
            'pendentes_justificativa' => 0,
        ];

        foreach ($divergences as $row) {
            $severity = (string) ($row['severity'] ?? '');
            if (isset($summary[$severity])) {
                $summary[$severity] = (int) $summary[$severity] + 1;
            }

            if ((int) ($row['requires_justification'] ?? 0) === 1 && (int) ($row['is_resolved'] ?? 0) !== 1) {
                $summary['pendentes_justificativa'] = (int) $summary['pendentes_justificativa'] + 1;
            }
        }

        return [
            'mirror' => $mirror,
            'review' => $review,
            'divergences' => $divergences,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>, reconciliation_id?: int}
     */
    public function run(int $mirrorId, int $userId, string $ip, string $userAgent): array
    {
        $mirror = $this->reconciliation->findMirrorById($mirrorId);
        if ($mirror === null) {
            return [
                'ok' => false,
                'message' => 'Espelho nao encontrado para conciliacao.',
                'errors' => ['Espelho nao encontrado para conciliacao.'],
            ];
        }

        if ($this->reconciliation->isMirrorLocked($mirrorId)) {
            return [
                'ok' => false,
                'message' => 'Espelho aprovado e bloqueado para nova conciliacao.',
                'errors' => ['Espelho aprovado e bloqueado para nova conciliacao.'],
            ];
        }

        $personId = (int) ($mirror['person_id'] ?? 0);
        $referenceMonth = (string) ($mirror['reference_month'] ?? '');

        $planItems = $this->reconciliation->activePlanItemsByPerson($personId);
        $mirrorItems = $this->reconciliation->mirrorItems($mirrorId);

        $expectedByKey = $this->expectedByKey($planItems, $referenceMonth);
        $actualByKey = $this->actualByKey($mirrorItems);

        $allKeys = array_values(array_unique(array_merge(array_keys($expectedByKey), array_keys($actualByKey))));

        $divergences = [];
        foreach ($allKeys as $key) {
            $expected = isset($expectedByKey[$key]['amount']) ? (float) $expectedByKey[$key]['amount'] : 0.0;
            $actual = isset($actualByKey[$key]['amount']) ? (float) $actualByKey[$key]['amount'] : 0.0;
            $difference = round($actual - $expected, 2);

            if (abs($difference) <= 0.009) {
                continue;
            }

            $type = $this->divergenceType($expected, $actual);
            $severity = $this->severity(abs($difference));
            $requiresJustification = abs($difference) >= self::JUSTIFICATION_THRESHOLD;

            $divergences[] = [
                'match_key' => mb_substr((string) (isset($expectedByKey[$key]['label']) ? $expectedByKey[$key]['label'] : ($actualByKey[$key]['label'] ?? $key)), 0, 190),
                'divergence_type' => $type,
                'severity' => $severity,
                'expected_amount' => number_format($expected, 2, '.', ''),
                'actual_amount' => number_format($actual, 2, '.', ''),
                'difference_amount' => number_format($difference, 2, '.', ''),
                'threshold_amount' => number_format(self::JUSTIFICATION_THRESHOLD, 2, '.', ''),
                'requires_justification' => $requiresJustification ? 1 : 0,
                'is_resolved' => $requiresJustification ? 0 : 1,
                'cost_plan_item_id' => $expectedByKey[$key]['item_id'] ?? null,
                'mirror_item_id' => $actualByKey[$key]['item_id'] ?? null,
            ];
        }

        $highSeverityTotal = 0;
        foreach ($divergences as $divergence) {
            if ((string) ($divergence['severity'] ?? '') === 'alta') {
                $highSeverityTotal++;
            }
        }

        try {
            $this->reconciliation->beginTransaction();

            $reconciliationId = $this->reconciliation->upsertReconciliation([
                'cost_mirror_id' => $mirrorId,
                'person_id' => $personId,
                'reference_month' => $referenceMonth,
                'compared_at' => date('Y-m-d H:i:s'),
                'compared_by' => $userId > 0 ? $userId : null,
                'divergences_total' => count($divergences),
                'high_severity_total' => $highSeverityTotal,
                'status' => 'pendente',
                'lock_editing' => 0,
                'approval_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            if ($reconciliationId <= 0) {
                throw new \RuntimeException('Falha ao criar cabecalho de conciliacao.');
            }

            $this->reconciliation->softDeleteDivergencesByReconciliation($reconciliationId);

            foreach ($divergences as $divergence) {
                $this->reconciliation->createDivergence([
                    'reconciliation_id' => $reconciliationId,
                    'cost_mirror_id' => $mirrorId,
                    'person_id' => $personId,
                    'cost_plan_item_id' => $divergence['cost_plan_item_id'],
                    'mirror_item_id' => $divergence['mirror_item_id'],
                    'match_key' => $divergence['match_key'],
                    'divergence_type' => $divergence['divergence_type'],
                    'severity' => $divergence['severity'],
                    'expected_amount' => $divergence['expected_amount'],
                    'actual_amount' => $divergence['actual_amount'],
                    'difference_amount' => $divergence['difference_amount'],
                    'threshold_amount' => $divergence['threshold_amount'],
                    'requires_justification' => $divergence['requires_justification'],
                    'justification_text' => null,
                    'justification_by' => null,
                    'justified_at' => null,
                    'is_resolved' => $divergence['is_resolved'],
                    'created_by' => $userId > 0 ? $userId : null,
                ]);
            }

            if (count($divergences) > 0) {
                $this->reconciliation->markMirrorStatus($mirrorId, 'conferido');
            }

            $this->audit->log(
                entity: 'cost_mirror_reconciliation',
                entityId: $reconciliationId,
                action: 'run',
                beforeData: null,
                afterData: [
                    'cost_mirror_id' => $mirrorId,
                    'person_id' => $personId,
                    'divergences_total' => count($divergences),
                    'high_severity_total' => $highSeverityTotal,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.reconciliation_ran',
                payload: [
                    'cost_mirror_id' => $mirrorId,
                    'person_id' => $personId,
                    'divergences_total' => count($divergences),
                    'high_severity_total' => $highSeverityTotal,
                ],
                entityId: $mirrorId,
                userId: $userId
            );

            $this->reconciliation->commit();
        } catch (\Throwable $exception) {
            $this->reconciliation->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel executar a conciliacao avancada.',
                'errors' => ['Falha ao processar conciliacao avancada.'],
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Conciliacao executada com sucesso. %d divergencia(s) encontrada(s).',
                count($divergences)
            ),
            'errors' => [],
            'reconciliation_id' => $reconciliationId,
        ];
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>} */
    public function justify(int $mirrorId, int $divergenceId, string $justification, int $userId, string $ip, string $userAgent): array
    {
        $divergence = $this->reconciliation->findDivergenceById($divergenceId);
        if ($divergence === null || (int) ($divergence['cost_mirror_id'] ?? 0) !== $mirrorId) {
            return [
                'ok' => false,
                'message' => 'Divergencia nao encontrada.',
                'errors' => ['Divergencia nao encontrada.'],
            ];
        }

        if ((int) ($divergence['lock_editing'] ?? 0) === 1 || (string) ($divergence['reconciliation_status'] ?? '') === 'aprovado') {
            return [
                'ok' => false,
                'message' => 'Conciliacao aprovada e bloqueada para alteracoes.',
                'errors' => ['Conciliacao aprovada e bloqueada para alteracoes.'],
            ];
        }

        $text = trim($justification);
        if ($text === '' || mb_strlen($text) < 10) {
            return [
                'ok' => false,
                'message' => 'Justificativa invalida.',
                'errors' => ['Justificativa obrigatoria com minimo de 10 caracteres.'],
            ];
        }

        $this->reconciliation->justifyDivergence($divergenceId, mb_substr($text, 0, 4000), $userId);

        $this->audit->log(
            entity: 'cost_mirror_divergence',
            entityId: $divergenceId,
            action: 'justify',
            beforeData: [
                'justification_text' => $divergence['justification_text'] ?? null,
                'is_resolved' => (int) ($divergence['is_resolved'] ?? 0),
            ],
            afterData: [
                'justification_text' => mb_substr($text, 0, 4000),
                'is_resolved' => 1,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cost_mirror',
            type: 'cost_mirror.divergence_justified',
            payload: [
                'cost_mirror_id' => $mirrorId,
                'divergence_id' => $divergenceId,
            ],
            entityId: $mirrorId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Justificativa registrada com sucesso.',
            'errors' => [],
        ];
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>} */
    public function approve(int $mirrorId, ?string $notes, int $userId, string $ip, string $userAgent): array
    {
        $mirror = $this->reconciliation->findMirrorById($mirrorId);
        if ($mirror === null) {
            return [
                'ok' => false,
                'message' => 'Espelho nao encontrado para aprovacao.',
                'errors' => ['Espelho nao encontrado para aprovacao.'],
            ];
        }

        $review = $this->reconciliation->findReconciliationByMirror($mirrorId);
        if ($review === null) {
            return [
                'ok' => false,
                'message' => 'Execute a conciliacao antes de aprovar.',
                'errors' => ['Execute a conciliacao antes de aprovar.'],
            ];
        }

        if ((int) ($review['lock_editing'] ?? 0) === 1 || (string) ($review['status'] ?? '') === 'aprovado') {
            return [
                'ok' => true,
                'message' => 'Conciliacao ja estava aprovada.',
                'errors' => [],
            ];
        }

        if ($this->reconciliation->hasPendingRequiredJustifications((int) ($review['id'] ?? 0))) {
            return [
                'ok' => false,
                'message' => 'Existem divergencias acima do limiar sem justificativa.',
                'errors' => ['Justifique todas as divergencias acima do limiar antes da aprovacao.'],
            ];
        }

        try {
            $this->reconciliation->beginTransaction();

            $this->reconciliation->approveReconciliation(
                reconciliationId: (int) ($review['id'] ?? 0),
                userId: $userId,
                notes: $this->clean($notes)
            );
            $this->reconciliation->markMirrorStatus($mirrorId, 'conciliado');

            $this->audit->log(
                entity: 'cost_mirror_reconciliation',
                entityId: (int) ($review['id'] ?? 0),
                action: 'approve',
                beforeData: [
                    'status' => $review['status'] ?? 'pendente',
                    'lock_editing' => (int) ($review['lock_editing'] ?? 0),
                ],
                afterData: [
                    'status' => 'aprovado',
                    'lock_editing' => 1,
                    'approval_notes' => $this->clean($notes),
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.reconciliation_approved',
                payload: [
                    'cost_mirror_id' => $mirrorId,
                    'reconciliation_id' => (int) ($review['id'] ?? 0),
                ],
                entityId: $mirrorId,
                userId: $userId
            );

            $this->reconciliation->commit();
        } catch (\Throwable $exception) {
            $this->reconciliation->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel aprovar a conciliacao.',
                'errors' => ['Falha ao aprovar conciliacao e bloquear edicoes.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Conciliacao aprovada. Espelho bloqueado para edicao.',
            'errors' => [],
        ];
    }

    public function isMirrorLocked(int $mirrorId): bool
    {
        return $this->reconciliation->isMirrorLocked($mirrorId);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array{amount: float, item_id: int|null, label: string}>
     */
    private function expectedByKey(array $items, string $referenceMonth): array
    {
        $map = [];
        $monthStart = $this->asDate($referenceMonth);
        if ($monthStart === null) {
            return $map;
        }

        $monthStart = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), 1)->setTime(0, 0, 0);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        foreach ($items as $item) {
            $amount = $this->expectedItemAmount($item, $monthStart, $monthEnd);
            if ($amount <= 0.0) {
                continue;
            }

            $label = (string) ($item['item_name'] ?? 'item');
            $key = $this->normalizeKey($label);
            if ($key === '') {
                continue;
            }

            if (!isset($map[$key])) {
                $map[$key] = [
                    'amount' => 0.0,
                    'item_id' => (int) ($item['id'] ?? 0),
                    'label' => $label,
                ];
            }

            $map[$key]['amount'] += $amount;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array{amount: float, item_id: int|null, label: string}>
     */
    private function actualByKey(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $label = (string) ($item['item_name'] ?? 'item');
            $key = $this->normalizeKey($label);
            if ($key === '') {
                continue;
            }

            $amount = $this->toFloat($item['amount'] ?? 0);
            if (!isset($map[$key])) {
                $map[$key] = [
                    'amount' => 0.0,
                    'item_id' => (int) ($item['id'] ?? 0),
                    'label' => $label,
                ];
            }

            $map[$key]['amount'] += $amount;
        }

        return $map;
    }

    /** @param array<string, mixed> $item */
    private function expectedItemAmount(array $item, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd): float
    {
        $amount = $this->toFloat($item['amount'] ?? 0);
        if ($amount <= 0.0) {
            return 0.0;
        }

        $type = (string) ($item['cost_type'] ?? '');

        if ($type === 'mensal') {
            return $this->isItemActiveInMonth($item, $monthStart, $monthEnd) ? $amount : 0.0;
        }

        if ($type === 'anual') {
            return $this->isItemActiveInMonth($item, $monthStart, $monthEnd) ? ($amount / 12.0) : 0.0;
        }

        if ($type === 'unico') {
            $effectiveDate = $this->singleItemEffectiveDate($item);
            if ($effectiveDate === null) {
                return 0.0;
            }

            return $effectiveDate->format('Y-m') === $monthStart->format('Y-m') ? $amount : 0.0;
        }

        return 0.0;
    }

    /** @param array<string, mixed> $item */
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

    private function divergenceType(float $expected, float $actual): string
    {
        if ($expected > 0.009 && $actual <= 0.009) {
            return 'faltante_espelho';
        }

        if ($actual > 0.009 && $expected <= 0.009) {
            return 'faltante_previsto';
        }

        return 'valor_divergente';
    }

    private function severity(float $absDifference): string
    {
        if ($absDifference >= 1000.0) {
            return 'alta';
        }

        if ($absDifference >= 300.0) {
            return 'media';
        }

        return 'baixa';
    }

    private function normalizeKey(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
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

    private function clean(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
