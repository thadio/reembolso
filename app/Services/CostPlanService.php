<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostPlanRepository;

final class CostPlanService
{
    private const ALLOWED_COST_TYPES = ['mensal', 'anual', 'unico'];

    public function __construct(
        private CostPlanRepository $costs,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{active_plan: array<string, mixed>|null, items: array<int, array<string, mixed>>, summary: array<string, mixed>, versions: array<int, array<string, mixed>>, version_items: array<int, array<int, array<string, mixed>>>, previous_plan: array<string, mixed>|null, comparison: array<string, float|int|null>}
     */
    public function profileData(int $personId): array
    {
        $versions = $this->costs->plansByPerson($personId, 12);

        $activePlan = null;
        foreach ($versions as $version) {
            if ((int) ($version['is_active'] ?? 0) === 1) {
                $activePlan = $version;
                break;
            }
        }

        if ($activePlan === null && $versions !== []) {
            $activePlan = $versions[0];
        }

        $items = [];
        if ($activePlan !== null) {
            $items = $this->costs->itemsByPlan((int) ($activePlan['id'] ?? 0));
        }

        $versionItems = [];
        $activePlanId = (int) ($activePlan['id'] ?? 0);
        foreach ($versions as $version) {
            $versionPlanId = (int) ($version['id'] ?? 0);
            if ($versionPlanId <= 0) {
                continue;
            }

            if ($activePlanId > 0 && $versionPlanId === $activePlanId) {
                $versionItems[$versionPlanId] = $items;
                continue;
            }

            $versionItems[$versionPlanId] = $this->costs->itemsByPlan($versionPlanId);
        }

        $summary = [
            'monthly_total' => isset($activePlan['monthly_total']) ? (float) $activePlan['monthly_total'] : 0.0,
            'annualized_total' => isset($activePlan['annualized_total']) ? (float) $activePlan['annualized_total'] : 0.0,
            'items_count' => count($items),
        ];

        $previousPlan = null;
        if ($activePlan !== null) {
            foreach ($versions as $version) {
                if ((int) ($version['id'] ?? 0) === (int) ($activePlan['id'] ?? 0)) {
                    continue;
                }
                $previousPlan = $version;
                break;
            }
        }

        $comparison = [
            'monthly_delta' => null,
            'annualized_delta' => null,
            'previous_version_number' => $previousPlan !== null ? (int) ($previousPlan['version_number'] ?? 0) : null,
        ];

        if ($activePlan !== null && $previousPlan !== null) {
            $comparison['monthly_delta'] = (float) ($activePlan['monthly_total'] ?? 0) - (float) ($previousPlan['monthly_total'] ?? 0);
            $comparison['annualized_delta'] = (float) ($activePlan['annualized_total'] ?? 0) - (float) ($previousPlan['annualized_total'] ?? 0);
        }

        return [
            'active_plan' => $activePlan,
            'items' => $items,
            'summary' => $summary,
            'versions' => $versions,
            'version_items' => $versionItems,
            'previous_plan' => $previousPlan,
            'comparison' => $comparison,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function createVersion(int $personId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $label = $this->clean($input['label'] ?? null);
        $cloneCurrent = ((string) ($input['clone_current'] ?? '1')) !== '0';

        try {
            $this->costs->beginTransaction();

            $latest = $this->costs->latestPlanByPerson($personId);
            $nextVersion = $latest !== null ? ((int) ($latest['version_number'] ?? 0) + 1) : 1;

            $this->costs->deactivateActivePlans($personId);

            $finalLabel = $label !== null ? mb_substr($label, 0, 190) : 'Versão ' . $nextVersion;
            $newPlanId = $this->costs->createPlan(
                personId: $personId,
                versionNumber: $nextVersion,
                label: $finalLabel,
                createdBy: $userId,
                isActive: true
            );

            $clonedItems = 0;
            if ($cloneCurrent && $latest !== null) {
                $clonedItems = $this->costs->cloneItemsToPlan(
                    sourcePlanId: (int) $latest['id'],
                    targetPlanId: $newPlanId,
                    personId: $personId,
                    createdBy: $userId
                );
            }

            $this->audit->log(
                entity: 'cost_plan',
                entityId: $newPlanId,
                action: 'version.create',
                beforeData: $latest,
                afterData: [
                    'person_id' => $personId,
                    'version_number' => $nextVersion,
                    'label' => $finalLabel,
                    'is_active' => 1,
                ],
                metadata: [
                    'clone_current' => $cloneCurrent,
                    'cloned_items' => $clonedItems,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'cost_plan.version_created',
                payload: [
                    'plan_id' => $newPlanId,
                    'version_number' => $nextVersion,
                    'cloned_items' => $clonedItems,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->costs->commit();
        } catch (\Throwable $exception) {
            $this->costs->rollBack();

            return [
                'ok' => false,
                'message' => 'Não foi possível criar nova versão de custos.',
                'errors' => ['Falha ao persistir a nova versão. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Nova versão de custos criada (V' . $nextVersion . ').',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function addItem(int $personId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $itemName = trim((string) ($input['item_name'] ?? ''));
        $costType = mb_strtolower(trim((string) ($input['cost_type'] ?? 'mensal')));
        $amount = $this->parseMoney($input['amount'] ?? null);
        $startDateRaw = $this->clean($input['start_date'] ?? null);
        $endDateRaw = $this->clean($input['end_date'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($itemName === '' || mb_strlen($itemName) < 3) {
            $errors[] = 'Nome do item é obrigatório (mínimo 3 caracteres).';
        }

        if (!in_array($costType, self::ALLOWED_COST_TYPES, true)) {
            $errors[] = 'Tipo de custo inválido.';
        }

        if ($amount === null || (float) $amount <= 0.0) {
            $errors[] = 'Valor do item deve ser maior que zero.';
        }

        $startDate = $this->normalizeDate($startDateRaw);
        if ($startDateRaw !== null && $startDate === null) {
            $errors[] = 'Data de início inválida.';
        }

        $endDate = $this->normalizeDate($endDateRaw);
        if ($endDateRaw !== null && $endDate === null) {
            $errors[] = 'Data de fim inválida.';
        }

        if ($startDate !== null && $endDate !== null && strtotime($endDate) < strtotime($startDate)) {
            $errors[] = 'Data de fim deve ser igual ou posterior à data de início.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível incluir item de custo.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        try {
            $this->costs->beginTransaction();

            $activePlan = $this->costs->activePlanByPerson($personId);
            if ($activePlan === null) {
                $activePlan = $this->createInitialVersion($personId, $userId, $ip, $userAgent);
            }

            $planId = (int) ($activePlan['id'] ?? 0);
            $itemId = $this->costs->createItem(
                planId: $planId,
                personId: $personId,
                itemName: mb_substr($itemName, 0, 190),
                costType: $costType,
                amount: $amount,
                startDate: $startDate,
                endDate: $endDate,
                notes: $notes,
                createdBy: $userId
            );

            $this->audit->log(
                entity: 'cost_plan_item',
                entityId: $itemId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'cost_plan_id' => $planId,
                    'person_id' => $personId,
                    'item_name' => mb_substr($itemName, 0, 190),
                    'cost_type' => $costType,
                    'amount' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                metadata: [
                    'notes' => $notes,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'cost_plan.item_added',
                payload: [
                    'cost_plan_id' => $planId,
                    'item_id' => $itemId,
                    'item_name' => mb_substr($itemName, 0, 190),
                    'cost_type' => $costType,
                    'amount' => $amount,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->costs->commit();
        } catch (\Throwable $exception) {
            $this->costs->rollBack();

            return [
                'ok' => false,
                'message' => 'Não foi possível incluir item de custo.',
                'errors' => ['Falha ao persistir o item de custo. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Item de custo incluído com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function createInitialVersion(int $personId, int $userId, string $ip, string $userAgent): array
    {
        $latest = $this->costs->latestPlanByPerson($personId);
        $nextVersion = $latest !== null ? ((int) ($latest['version_number'] ?? 0) + 1) : 1;

        $newPlanId = $this->costs->createPlan(
            personId: $personId,
            versionNumber: $nextVersion,
            label: 'Versão ' . $nextVersion,
            createdBy: $userId,
            isActive: true
        );

        $this->audit->log(
            entity: 'cost_plan',
            entityId: $newPlanId,
            action: 'version.create.initial',
            beforeData: $latest,
            afterData: [
                'person_id' => $personId,
                'version_number' => $nextVersion,
                'label' => 'Versão ' . $nextVersion,
                'is_active' => 1,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'cost_plan.initial_created',
            payload: [
                'plan_id' => $newPlanId,
                'version_number' => $nextVersion,
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'id' => $newPlanId,
            'person_id' => $personId,
            'version_number' => $nextVersion,
            'label' => 'Versão ' . $nextVersion,
            'is_active' => 1,
        ];
    }

    private function parseMoney(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = $raw;
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function normalizeDate(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $time = strtotime($input);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
