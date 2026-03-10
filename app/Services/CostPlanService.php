<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostItemCatalogRepository;
use App\Repositories\CostPlanRepository;
use DateTimeImmutable;

final class CostPlanService
{
    private const ALLOWED_COST_TYPES = ['mensal', 'anual', 'eventual', 'unico'];

    public function __construct(
        private CostPlanRepository $costs,
        private CostItemCatalogRepository $catalogItems,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{active_plan: array<string, mixed>|null, items: array<int, array<string, mixed>>, summary: array<string, mixed>, versions: array<int, array<string, mixed>>, version_items: array<int, array<int, array<string, mixed>>>, previous_plan: array<string, mixed>|null, comparison: array<string, float|int|null>, suggested_version_label: string, next_version_number: int}
     */
    public function profileData(int $personId): array
    {
        $versions = $this->costs->plansByPerson($personId, 12);
        $latestVersion = $versions[0] ?? null;
        $nextVersion = $latestVersion !== null ? ((int) ($latestVersion['version_number'] ?? 0) + 1) : 1;
        $suggestedVersionLabel = $this->buildAutoVersionLabel($nextVersion);

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
            'suggested_version_label' => $suggestedVersionLabel,
            'next_version_number' => $nextVersion,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function catalogOptions(): array
    {
        return $this->catalogItems->activeList();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function createVersion(int $personId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $cloneCurrent = ((string) ($input['clone_current'] ?? '1')) !== '0';

        try {
            $this->costs->beginTransaction();

            $latest = $this->costs->latestPlanByPerson($personId);
            $nextVersion = $latest !== null ? ((int) ($latest['version_number'] ?? 0) + 1) : 1;

            $this->costs->deactivateActivePlans($personId);

            $finalLabel = $this->buildAutoVersionLabel($nextVersion);
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
        $catalogId = max(0, (int) ($input['cost_item_catalog_id'] ?? 0));
        $amount = $this->parseMoney($input['amount'] ?? null);
        $startDateRaw = $this->clean($input['start_date'] ?? null);
        $endDateRaw = $this->clean($input['end_date'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $catalogItem = $catalogId > 0 ? $this->catalogItems->findActiveById($catalogId) : null;
        $itemName = trim((string) ($catalogItem['name'] ?? ''));
        $costType = mb_strtolower(trim((string) ($catalogItem['payment_periodicity'] ?? '')));

        $errors = [];

        if ($catalogId <= 0) {
            $errors[] = 'Selecione o item de custo no catálogo.';
        }

        if ($catalogItem === null) {
            $errors[] = 'Item de custo inexistente ou inativo no catálogo.';
        }

        if (!in_array($costType, self::ALLOWED_COST_TYPES, true)) {
            $errors[] = 'Periodicidade do item selecionado e invalida.';
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
                costItemCatalogId: $catalogId > 0 ? $catalogId : null,
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
                    'cost_item_catalog_id' => $catalogId,
                    'item_name' => mb_substr($itemName, 0, 190),
                    'cost_type' => $costType,
                    'amount' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                metadata: [
                    'notes' => $notes,
                    'cost_code' => (int) ($catalogItem['cost_code'] ?? 0),
                    'macro_category' => (string) ($catalogItem['macro_category'] ?? ''),
                    'subcategory' => (string) ($catalogItem['subcategory'] ?? ''),
                    'expense_nature' => (string) ($catalogItem['expense_nature'] ?? ''),
                    'calculation_base' => (string) ($catalogItem['calculation_base'] ?? ''),
                    'charge_incidence' => (int) ($catalogItem['charge_incidence'] ?? 0),
                    'reimbursability' => (string) ($catalogItem['reimbursability'] ?? ''),
                    'predictability' => (string) ($catalogItem['predictability'] ?? ''),
                    'linkage_code' => (int) ($catalogItem['linkage_code'] ?? 0),
                    'is_reimbursable' => (int) ($catalogItem['is_reimbursable'] ?? 0),
                    'payment_periodicity' => (string) ($catalogItem['payment_periodicity'] ?? ''),
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
                    'cost_item_catalog_id' => $catalogId,
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

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function saveTable(int $personId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $parsed = $this->parseTableRows($input);
        if ($parsed['errors'] !== []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível salvar os custos da tabela.',
                'errors' => $parsed['errors'],
                'warnings' => $parsed['warnings'],
            ];
        }

        $rows = $parsed['rows'];
        $warnings = $parsed['warnings'];
        if ($rows === []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível salvar os custos da tabela.',
                'errors' => ['Informe ao menos um custo maior que zero para salvar a versão.'],
                'warnings' => $warnings,
            ];
        }

        try {
            $this->costs->beginTransaction();

            $latest = $this->costs->latestPlanByPerson($personId);
            $nextVersion = $latest !== null ? ((int) ($latest['version_number'] ?? 0) + 1) : 1;
            $finalLabel = $this->buildAutoVersionLabel($nextVersion);

            $this->costs->deactivateActivePlans($personId);
            $newPlanId = $this->costs->createPlan(
                personId: $personId,
                versionNumber: $nextVersion,
                label: $finalLabel,
                createdBy: $userId,
                isActive: true
            );

            $itemsCount = 0;
            $monthlyTotal = 0.0;
            $annualizedTotal = 0.0;

            foreach ($rows as $row) {
                $amount = (string) ($row['amount'] ?? '0.00');
                $costType = (string) ($row['cost_type'] ?? 'mensal');
                $itemName = mb_substr((string) ($row['item_name'] ?? ''), 0, 190);
                $catalogId = (int) ($row['catalog_id'] ?? 0);
                $startDate = isset($row['start_date']) ? (string) $row['start_date'] : null;
                if ($startDate === '') {
                    $startDate = null;
                }
                $endDate = isset($row['end_date']) ? (string) $row['end_date'] : null;
                if ($endDate === '') {
                    $endDate = null;
                }
                $notes = isset($row['notes']) ? (string) $row['notes'] : null;
                if ($notes !== null && trim($notes) === '') {
                    $notes = null;
                }

                $itemId = $this->costs->createItem(
                    planId: $newPlanId,
                    personId: $personId,
                    costItemCatalogId: $catalogId > 0 ? $catalogId : null,
                    itemName: $itemName,
                    costType: $costType,
                    amount: $amount,
                    startDate: $startDate,
                    endDate: $endDate,
                    notes: $notes,
                    createdBy: $userId
                );

                $itemsCount++;
                $monthlyTotal += $this->monthlyEquivalent((float) $amount, $costType);
                $annualizedTotal += $this->annualizedTotal((float) $amount, $costType);

                $this->audit->log(
                    entity: 'cost_plan_item',
                    entityId: $itemId,
                    action: 'create',
                    beforeData: null,
                    afterData: [
                        'cost_plan_id' => $newPlanId,
                        'person_id' => $personId,
                        'cost_item_catalog_id' => $catalogId,
                        'item_name' => $itemName,
                        'cost_type' => $costType,
                        'amount' => $amount,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    metadata: [
                        'notes' => $notes,
                        'cost_code' => (int) ($row['cost_code'] ?? 0),
                        'macro_category' => (string) ($row['macro_category'] ?? ''),
                        'subcategory' => (string) ($row['subcategory'] ?? ''),
                        'expense_nature' => (string) ($row['expense_nature'] ?? ''),
                        'calculation_base' => (string) ($row['calculation_base'] ?? ''),
                        'charge_incidence' => (int) ($row['charge_incidence'] ?? 0),
                        'reimbursability' => (string) ($row['reimbursability'] ?? ''),
                        'predictability' => (string) ($row['predictability'] ?? ''),
                        'linkage_code' => (int) ($row['linkage_code'] ?? 0),
                        'is_reimbursable' => (int) ($row['is_reimbursable'] ?? 0),
                        'payment_periodicity' => (string) ($row['payment_periodicity'] ?? ''),
                        'source' => 'table_batch_save',
                    ],
                    userId: $userId,
                    ip: $ip,
                    userAgent: $userAgent
                );
            }

            $this->audit->log(
                entity: 'cost_plan',
                entityId: $newPlanId,
                action: 'version.create.auto_table',
                beforeData: $latest,
                afterData: [
                    'person_id' => $personId,
                    'version_number' => $nextVersion,
                    'label' => $finalLabel,
                    'is_active' => 1,
                    'items_count' => $itemsCount,
                    'monthly_total' => number_format($monthlyTotal, 2, '.', ''),
                    'annualized_total' => number_format($annualizedTotal, 2, '.', ''),
                ],
                metadata: [
                    'source' => 'people.costs.table',
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'cost_plan.table_saved',
                payload: [
                    'plan_id' => $newPlanId,
                    'version_number' => $nextVersion,
                    'items_count' => $itemsCount,
                    'monthly_total' => round($monthlyTotal, 2),
                    'annualized_total' => round($annualizedTotal, 2),
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->costs->commit();
        } catch (\Throwable $exception) {
            $this->costs->rollBack();

            return [
                'ok' => false,
                'message' => 'Não foi possível salvar os custos da tabela.',
                'errors' => ['Falha ao persistir a nova versão automática. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Versão V' . $nextVersion . ' salva automaticamente com ' . count($rows) . ' item(ns).',
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, mixed> */
    private function createInitialVersion(int $personId, int $userId, string $ip, string $userAgent): array
    {
        $latest = $this->costs->latestPlanByPerson($personId);
        $nextVersion = $latest !== null ? ((int) ($latest['version_number'] ?? 0) + 1) : 1;
        $label = $this->buildAutoVersionLabel($nextVersion);

        $newPlanId = $this->costs->createPlan(
            personId: $personId,
            versionNumber: $nextVersion,
            label: $label,
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
                'label' => $label,
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
            'label' => $label,
            'is_active' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, warnings: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function parseTableRows(array $input): array
    {
        $rowsInput = is_array($input['items'] ?? null) ? $input['items'] : [];
        $catalogItems = $this->catalogItems->activeList();
        $errors = [];
        $warnings = [];
        $rows = [];

        if ($catalogItems === []) {
            return [
                'errors' => ['Nenhum item de custo ativo encontrado no catálogo.'],
                'warnings' => [],
                'rows' => [],
            ];
        }

        $aggregators = [];
        $aggregatorIds = [];
        $childrenByParent = [];

        foreach ($catalogItems as $catalogItem) {
            $catalogId = (int) ($catalogItem['id'] ?? 0);
            if ($catalogId <= 0) {
                continue;
            }

            $isAggregator = (int) ($catalogItem['is_aggregator'] ?? 0) === 1;
            if ($isAggregator) {
                $aggregators[] = $catalogItem;
                $aggregatorIds[$catalogId] = true;
                continue;
            }

            $parentId = (int) ($catalogItem['parent_cost_item_id'] ?? 0);
            if ($parentId > 0) {
                $childrenByParent[$parentId][] = $catalogItem;
                continue;
            }

            $parsedStandalone = $this->parseCatalogTableRow($catalogItem, $rowsInput);
            if ($parsedStandalone['errors'] !== []) {
                foreach ($parsedStandalone['errors'] as $error) {
                    $errors[] = $error;
                }
            }
            if (is_array($parsedStandalone['row'])) {
                $rows[] = $parsedStandalone['row'];
            }
        }

        foreach ($aggregators as $aggregator) {
            $aggregatorId = (int) ($aggregator['id'] ?? 0);
            if ($aggregatorId <= 0) {
                continue;
            }

            $parsedAggregator = $this->parseCatalogTableRow($aggregator, $rowsInput);
            if ($parsedAggregator['errors'] !== []) {
                foreach ($parsedAggregator['errors'] as $error) {
                    $errors[] = $error;
                }
            }

            $children = is_array($childrenByParent[$aggregatorId] ?? null) ? $childrenByParent[$aggregatorId] : [];
            $parsedChildRows = [];

            foreach ($children as $child) {
                $parsedChild = $this->parseCatalogTableRow($child, $rowsInput);
                if ($parsedChild['errors'] !== []) {
                    foreach ($parsedChild['errors'] as $error) {
                        $errors[] = $error;
                    }
                }
                if (is_array($parsedChild['row'])) {
                    $parsedChildRows[] = $parsedChild['row'];
                }
            }

            if ($parsedChildRows !== []) {
                foreach ($parsedChildRows as $parsedChildRow) {
                    $rows[] = $parsedChildRow;
                }

                if (is_array($parsedAggregator['row'])) {
                    $warnings[] = 'Valor informado na categoria "'
                        . (string) ($aggregator['name'] ?? 'Categoria')
                        . '" foi ignorado porque existem itens filhos detalhados.';
                }
                continue;
            }

            if (is_array($parsedAggregator['row'])) {
                $rows[] = $parsedAggregator['row'];
            }
        }

        foreach ($childrenByParent as $parentId => $orphans) {
            if ($parentId <= 0 || isset($aggregatorIds[$parentId])) {
                continue;
            }

            foreach ($orphans as $orphan) {
                $parsedOrphan = $this->parseCatalogTableRow($orphan, $rowsInput);
                if ($parsedOrphan['errors'] !== []) {
                    foreach ($parsedOrphan['errors'] as $error) {
                        $errors[] = $error;
                    }
                }
                if (is_array($parsedOrphan['row'])) {
                    $rows[] = $parsedOrphan['row'];
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $catalogItem
     * @param array<string|int, mixed> $rowsInput
     * @return array{errors: array<int, string>, row: ?array<string, mixed>}
     */
    private function parseCatalogTableRow(array $catalogItem, array $rowsInput): array
    {
        $catalogId = (int) ($catalogItem['id'] ?? 0);
        if ($catalogId <= 0) {
            return [
                'errors' => [],
                'row' => null,
            ];
        }

        $rowInput = [];
        $rawRow = $rowsInput[$catalogId] ?? $rowsInput[(string) $catalogId] ?? null;
        if (is_array($rawRow)) {
            $rowInput = $rawRow;
        }

        $amount = $this->parseMoney($rowInput['amount'] ?? null);
        if ($amount === null || (float) $amount <= 0.0) {
            return [
                'errors' => [],
                'row' => null,
            ];
        }

        $itemName = trim((string) ($catalogItem['name'] ?? ('Item #' . $catalogId)));
        $catalogCostType = mb_strtolower(trim((string) ($catalogItem['payment_periodicity'] ?? '')));
        $requestedCostType = mb_strtolower(trim((string) ($rowInput['cost_type'] ?? '')));
        $costType = $requestedCostType !== '' ? $requestedCostType : $catalogCostType;

        $errors = [];
        if (!in_array($costType, self::ALLOWED_COST_TYPES, true)) {
            $errors[] = 'Periodicidade inválida para o item "' . $itemName . '".';
        }

        $startDateRaw = $this->clean($rowInput['start_date'] ?? null);
        $endDateRaw = $this->clean($rowInput['end_date'] ?? null);
        $notes = $this->clean($rowInput['notes'] ?? null);

        $startDate = $this->normalizeDate($startDateRaw);
        if ($startDateRaw !== null && $startDate === null) {
            $errors[] = 'Data de início inválida para o item "' . $itemName . '".';
        }

        $endDate = $this->normalizeDate($endDateRaw);
        if ($endDateRaw !== null && $endDate === null) {
            $errors[] = 'Data de fim inválida para o item "' . $itemName . '".';
        }

        if ($startDate !== null && $endDate !== null && strtotime($endDate) < strtotime($startDate)) {
            $errors[] = 'Data de fim deve ser igual ou posterior ao início para o item "' . $itemName . '".';
        }

        if ($errors !== []) {
            return [
                'errors' => $errors,
                'row' => null,
            ];
        }

        return [
            'errors' => [],
            'row' => [
                'catalog_id' => $catalogId,
                'parent_catalog_id' => (int) ($catalogItem['parent_cost_item_id'] ?? 0),
                'is_aggregator' => (int) ($catalogItem['is_aggregator'] ?? 0),
                'item_name' => $itemName,
                'cost_type' => $costType,
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => $notes,
                'cost_code' => (int) ($catalogItem['cost_code'] ?? 0),
                'macro_category' => (string) ($catalogItem['macro_category'] ?? ''),
                'subcategory' => (string) ($catalogItem['subcategory'] ?? ''),
                'expense_nature' => (string) ($catalogItem['expense_nature'] ?? ''),
                'calculation_base' => (string) ($catalogItem['calculation_base'] ?? ''),
                'charge_incidence' => (int) ($catalogItem['charge_incidence'] ?? 0),
                'reimbursability' => (string) ($catalogItem['reimbursability'] ?? ''),
                'predictability' => (string) ($catalogItem['predictability'] ?? ''),
                'linkage_code' => (int) ($catalogItem['linkage_code'] ?? 0),
                'is_reimbursable' => (int) ($catalogItem['is_reimbursable'] ?? 0),
                'payment_periodicity' => (string) ($catalogItem['payment_periodicity'] ?? ''),
                'selected_periodicity' => $costType,
            ],
        ];
    }

    private function monthlyEquivalent(float $amount, string $costType): float
    {
        return match ($costType) {
            'mensal' => $amount,
            'anual', 'eventual', 'unico' => $amount / 12,
            default => 0.0,
        };
    }

    private function annualizedTotal(float $amount, string $costType): float
    {
        return match ($costType) {
            'mensal' => $amount * 12,
            'anual', 'eventual', 'unico' => $amount,
            default => 0.0,
        };
    }

    private function buildAutoVersionLabel(int $versionNumber): string
    {
        $today = new DateTimeImmutable('now');

        return 'V' . max(1, $versionNumber) . ' - ' . $today->format('d/m/Y');
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
