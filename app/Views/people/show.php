<?php

declare(strict_types=1);

$pipeline = $pipeline ?? [
    'assignment' => null,
    'flow' => null,
    'statuses' => [],
    'next_status' => null,
    'available_transitions' => [],
    'timeline' => [],
    'timeline_pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 8,
        'pages' => 1,
    ],
    'event_types' => [],
    'queue_priorities' => [],
    'queue_users' => [],
    'checklist' => [
        'case_type' => 'geral',
        'case_type_label' => 'Geral',
        'items' => [],
        'summary' => [
            'total' => 0,
            'completed' => 0,
            'required_total' => 0,
            'required_completed' => 0,
            'percent' => 0,
        ],
    ],
];

$assignment = $pipeline['assignment'] ?? null;
$flow = $pipeline['flow'] ?? null;
$statuses = $pipeline['statuses'] ?? [];
$nextStatus = $pipeline['next_status'] ?? null;
$availableTransitions = is_array($pipeline['available_transitions'] ?? null) ? $pipeline['available_transitions'] : [];
$timeline = $pipeline['timeline'] ?? [];
$timelinePagination = $pipeline['timeline_pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$eventTypes = $pipeline['event_types'] ?? [];
$queuePriorities = $pipeline['queue_priorities'] ?? [];
$queueUsers = $pipeline['queue_users'] ?? [];
$checklist = is_array($pipeline['checklist'] ?? null) ? $pipeline['checklist'] : [];
$checklistItems = is_array($checklist['items'] ?? null) ? $checklist['items'] : [];
$checklistSummary = is_array($checklist['summary'] ?? null) ? $checklist['summary'] : [];
$checklistCaseTypeLabel = (string) ($checklist['case_type_label'] ?? 'Geral');
$checklistRequiredDone = (int) ($checklistSummary['required_completed'] ?? 0);
$checklistRequiredTotal = (int) ($checklistSummary['required_total'] ?? 0);
$checklistPercent = max(0, min(100, (int) ($checklistSummary['percent'] ?? 0)));
if ($queuePriorities === []) {
    $queuePriorities = [
        ['value' => 'low', 'label' => 'Baixa'],
        ['value' => 'normal', 'label' => 'Normal'],
        ['value' => 'high', 'label' => 'Alta'],
        ['value' => 'urgent', 'label' => 'Urgente'],
    ];
}
$documents = $documents ?? [
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 8,
        'pages' => 1,
    ],
    'document_types' => [],
    'sensitivity_options' => [],
    'context' => [
        'context_event_id' => null,
        'context_event_title' => '',
        'flow_id' => null,
        'status_code' => '',
        'status_label' => '',
        'expected_document_types' => [],
        'suggested_document_type_id' => null,
        'suggested_document_type_name' => '',
    ],
];
$documentItems = $documents['items'] ?? [];
$documentsPagination = $documents['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$documentTypes = $documents['document_types'] ?? [];
$documentSensitivityOptions = $documents['sensitivity_options'] ?? [];
$documentContext = is_array($documents['context'] ?? null) ? $documents['context'] : [];
$documentContextEventId = max(0, (int) ($documentContext['context_event_id'] ?? 0));
$documentContextTitle = trim((string) ($documentContext['context_event_title'] ?? ''));
$documentContextStatusCode = trim((string) ($documentContext['status_code'] ?? ''));
$documentContextStatusLabel = trim((string) ($documentContext['status_label'] ?? ''));
$documentContextExpectedTypes = is_array($documentContext['expected_document_types'] ?? null) ? $documentContext['expected_document_types'] : [];
$documentContextSuggestedTypeId = max(0, (int) ($documentContext['suggested_document_type_id'] ?? 0));
$documentContextSuggestedTypeName = trim((string) ($documentContext['suggested_document_type_name'] ?? ''));
$canViewSensitiveDocuments = ($canViewSensitiveDocuments ?? false) === true;
if ($documentSensitivityOptions === []) {
    $documentSensitivityOptions = [['value' => 'public', 'label' => 'Publico']];
}
$costs = $costs ?? [
    'active_plan' => null,
    'items' => [],
    'summary' => [
        'monthly_total' => 0,
        'annualized_total' => 0,
        'items_count' => 0,
    ],
    'versions' => [],
    'version_items' => [],
    'previous_plan' => null,
    'comparison' => [
        'monthly_delta' => null,
        'annualized_delta' => null,
        'previous_version_number' => null,
    ],
    'suggested_version_label' => 'V1 - ' . date('d/m/Y'),
    'next_version_number' => 1,
];
$costItemCatalog = is_array($costItemCatalog ?? null) ? $costItemCatalog : [];
$canManageCostItems = ($canManageCostItems ?? false) === true;
$activeCostPlan = $costs['active_plan'] ?? null;
$costItems = $costs['items'] ?? [];
$costSummary = $costs['summary'] ?? ['monthly_total' => 0, 'annualized_total' => 0, 'items_count' => 0];
$costVersions = $costs['versions'] ?? [];
$costVersionItems = is_array($costs['version_items'] ?? null) ? $costs['version_items'] : [];
$costComparison = $costs['comparison'] ?? ['monthly_delta' => null, 'annualized_delta' => null, 'previous_version_number' => null];
$costSuggestedVersionLabel = trim((string) ($costs['suggested_version_label'] ?? ''));
if ($costSuggestedVersionLabel === '') {
    $costSuggestedVersionLabel = 'V1 - ' . date('d/m/Y');
}
$costActiveRowsByCatalog = [];
foreach ($costItems as $costItemRow) {
    $catalogId = (int) ($costItemRow['cost_item_catalog_id'] ?? 0);
    if ($catalogId <= 0) {
        continue;
    }

    if (!isset($costActiveRowsByCatalog[$catalogId])) {
        $costActiveRowsByCatalog[$catalogId] = [];
    }
    $costActiveRowsByCatalog[$catalogId][] = $costItemRow;
}

$costCatalogAggregators = [];
$costCatalogChildrenByParent = [];
$costCatalogStandalone = [];
foreach ($costItemCatalog as $catalogItem) {
    $catalogId = (int) ($catalogItem['id'] ?? 0);
    if ($catalogId <= 0) {
        continue;
    }

    $isAggregator = (int) ($catalogItem['is_aggregator'] ?? 0) === 1;
    if ($isAggregator) {
        $costCatalogAggregators[$catalogId] = $catalogItem;
        continue;
    }

    $parentId = (int) ($catalogItem['parent_cost_item_id'] ?? 0);
    if ($parentId > 0) {
        if (!isset($costCatalogChildrenByParent[$parentId])) {
            $costCatalogChildrenByParent[$parentId] = [];
        }
        $costCatalogChildrenByParent[$parentId][] = $catalogItem;
        continue;
    }

    $costCatalogStandalone[] = $catalogItem;
}

$costCatalogHierarchy = [];
foreach ($costCatalogAggregators as $aggregatorId => $aggregatorItem) {
    $children = $costCatalogChildrenByParent[$aggregatorId] ?? [];
    $costCatalogHierarchy[] = [
        'category' => $aggregatorItem,
        'children' => $children,
    ];
}

if ($costCatalogStandalone !== []) {
    foreach ($costCatalogStandalone as $standaloneItem) {
        $costCatalogHierarchy[] = [
            'category' => $standaloneItem,
            'children' => [],
        ];
    }
}

$costProjectionStartDate = trim((string) ($assignment['effective_start_date'] ?? ''));
if ($costProjectionStartDate === '' || strtotime($costProjectionStartDate) === false) {
    $costProjectionStartDate = trim((string) ($assignment['target_start_date'] ?? ''));
}
if ($costProjectionStartDate === '' || strtotime($costProjectionStartDate) === false) {
    $costProjectionStartDate = '';
}

$costProjectionCurrentYear = (int) date('Y');
$costCurrentVersionLabel = trim((string) ($activeCostPlan['label'] ?? ''));
if ($costCurrentVersionLabel === '') {
    $costCurrentVersionLabel = 'Sem versão ativa';
}
$costPeriodicityOptions = [
    'mensal' => 'Mensal',
    'anual' => 'Anual',
    'eventual' => 'Eventual',
    'unico' => 'Unico (legado)',
];
$conciliation = $conciliation ?? [
    'active_plan' => null,
    'summary' => [
        'current_month' => '',
        'months_analyzed' => 0,
        'expected_current' => 0,
        'actual_posted_current' => 0,
        'actual_paid_current' => 0,
        'deviation_posted_current' => 0,
        'deviation_paid_current' => 0,
        'expected_window_total' => 0,
        'actual_posted_window_total' => 0,
        'actual_paid_window_total' => 0,
        'deviation_posted_window_total' => 0,
        'deviation_paid_window_total' => 0,
    ],
    'rows' => [],
];
$conciliationSummary = $conciliation['summary'] ?? [
    'current_month' => '',
    'months_analyzed' => 0,
    'expected_current' => 0,
    'actual_posted_current' => 0,
    'actual_paid_current' => 0,
    'deviation_posted_current' => 0,
    'deviation_paid_current' => 0,
    'expected_window_total' => 0,
    'actual_posted_window_total' => 0,
    'actual_paid_window_total' => 0,
    'deviation_posted_window_total' => 0,
    'deviation_paid_window_total' => 0,
];
$conciliationRows = $conciliation['rows'] ?? [];
$reimbursements = $reimbursements ?? [
    'summary' => [
        'total_entries' => 0,
        'pending_total' => 0,
        'paid_total' => 0,
        'canceled_total' => 0,
        'overdue_total' => 0,
        'pending_count' => 0,
        'paid_count' => 0,
        'canceled_count' => 0,
        'overdue_count' => 0,
        'boletos_count' => 0,
        'payments_count' => 0,
        'adjustments_count' => 0,
    ],
    'items' => [],
    'calculation_memories' => [],
];
$reimbursementSummary = $reimbursements['summary'] ?? [
    'total_entries' => 0,
    'pending_total' => 0,
    'paid_total' => 0,
    'canceled_total' => 0,
    'overdue_total' => 0,
    'pending_count' => 0,
    'paid_count' => 0,
    'canceled_count' => 0,
    'overdue_count' => 0,
    'boletos_count' => 0,
    'payments_count' => 0,
    'adjustments_count' => 0,
];
$reimbursementItems = $reimbursements['items'] ?? [];
$reimbursementCalculationMemories = $reimbursements['calculation_memories'] ?? [];
$processComments = $processComments ?? [
    'summary' => [
        'total_comments' => 0,
        'open_count' => 0,
        'archived_count' => 0,
        'pinned_count' => 0,
    ],
    'items' => [],
];
$processCommentSummary = $processComments['summary'] ?? [
    'total_comments' => 0,
    'open_count' => 0,
    'archived_count' => 0,
    'pinned_count' => 0,
];
$processCommentItems = $processComments['items'] ?? [];
$processCommentStatusOptions = $processCommentStatusOptions ?? [
    ['value' => 'aberto', 'label' => 'Aberto'],
    ['value' => 'arquivado', 'label' => 'Arquivado'],
];
$adminTimeline = $adminTimeline ?? [
    'summary' => [
        'total' => 0,
        'open_count' => 0,
        'closed_count' => 0,
        'manual_count' => 0,
        'automated_count' => 0,
    ],
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 14,
        'pages' => 1,
    ],
    'filters' => [
        'q' => '',
        'source' => '',
        'status_group' => '',
    ],
    'source_options' => [['value' => '', 'label' => 'Todas as origens']],
    'status_group_options' => [['value' => '', 'label' => 'Todos os status']],
];
$adminTimelineSummary = $adminTimeline['summary'] ?? [
    'total' => 0,
    'open_count' => 0,
    'closed_count' => 0,
    'manual_count' => 0,
    'automated_count' => 0,
];
$adminTimelineItems = $adminTimeline['items'] ?? [];
$adminTimelinePagination = $adminTimeline['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 14, 'pages' => 1];
$adminTimelineFilters = $adminTimeline['filters'] ?? ['q' => '', 'source' => '', 'status_group' => ''];
$adminTimelineSourceOptions = $adminTimeline['source_options'] ?? [['value' => '', 'label' => 'Todas as origens']];
$adminTimelineStatusGroupOptions = $adminTimeline['status_group_options'] ?? [['value' => '', 'label' => 'Todos os status']];
$adminTimelineNoteStatusOptions = $adminTimelineNoteStatusOptions ?? [
    ['value' => 'aberto', 'label' => 'Aberto'],
    ['value' => 'concluido', 'label' => 'Concluido'],
];
$adminTimelineNoteSeverityOptions = $adminTimelineNoteSeverityOptions ?? [
    ['value' => 'baixa', 'label' => 'Baixa'],
    ['value' => 'media', 'label' => 'Media'],
    ['value' => 'alta', 'label' => 'Alta'],
];
$audit = $audit ?? [
    'items' => [],
    'pagination' => [
        'total' => 0,
        'page' => 1,
        'per_page' => 10,
        'pages' => 1,
    ],
    'filters' => [
        'entity' => '',
        'action' => '',
        'q' => '',
        'from_date' => '',
        'to_date' => '',
    ],
    'options' => [
        'entities' => [],
        'actions' => [],
    ],
];
$auditItems = $audit['items'] ?? [];
$auditPagination = $audit['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$auditFilters = $audit['filters'] ?? ['entity' => '', 'action' => '', 'q' => '', 'from_date' => '', 'to_date' => ''];
$auditOptions = $audit['options'] ?? ['entities' => [], 'actions' => []];
$personId = (int) ($person['id'] ?? 0);
$tabOptions = ['summary', 'timeline', 'documents', 'costs', 'conciliation', 'finance', 'comments', 'admin-timeline', 'audit'];
$requestedTab = trim((string) ($_GET['tab'] ?? 'summary'));
$activeTab = in_array($requestedTab, $tabOptions, true) ? $requestedTab : 'summary';
$focusTargetMap = [
    'pipeline' => 'pipeline-overview',
    'history' => 'timeline-history',
];
$requestedFocus = trim((string) ($_GET['focus'] ?? ''));
$activeFocusTarget = $focusTargetMap[$requestedFocus] ?? '';

$statusLabel = static function (string $value): string {
    return match ($value) {
        'interessado' => 'Interessado/Triagem',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Ofício órgão',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$statusLabelByCode = [];
foreach ($statuses as $status) {
    $statusCode = trim((string) ($status['code'] ?? ''));
    $statusName = trim((string) ($status['label'] ?? ''));
    if ($statusCode === '' || $statusName === '') {
        continue;
    }

    $statusLabelByCode[$statusCode] = $statusName;
}

$resolveStatusLabel = static function (?string $code, ?string $fallbackLabel) use ($statusLabelByCode, $statusLabel): string {
    $normalizedCode = trim((string) $code);
    if ($normalizedCode !== '' && isset($statusLabelByCode[$normalizedCode])) {
        return $statusLabelByCode[$normalizedCode];
    }

    $label = trim((string) $fallbackLabel);
    if ($label !== '') {
        return $label;
    }

    if ($normalizedCode !== '') {
        return $statusLabel($normalizedCode);
    }

    return '-';
};

$nodeKindLabel = static function (string $kind): string {
    return match ($kind) {
        'gateway' => 'Decisão',
        'final' => 'Final',
        default => 'Etapa',
    };
};

$eventTypeLabel = static function (string $value): string {
    $value = str_replace(['pipeline.', '_', '.'], ['Pipeline ', ' ', ' • '], $value);

    return ucfirst(trim($value));
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatDateTimeInput = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
};

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};

$decodeMetadata = static function (mixed $metadata): array {
    if (!is_string($metadata) || trim($metadata) === '') {
        return [];
    }

    $decoded = json_decode($metadata, true);

    return is_array($decoded) ? $decoded : [];
};

$eventBadgeClass = static function (string $eventType): string {
    if ($eventType === 'retificacao') {
        return 'badge-neutral';
    }

    if (str_starts_with($eventType, 'pipeline.')) {
        return 'badge-info';
    }

    return 'badge-neutral';
};

$formatBytes = static function (int $size): string {
    if ($size <= 0) {
        return '0 B';
    }

    if ($size >= 1048576) {
        return number_format($size / 1048576, 2, ',', '.') . ' MB';
    }

    if ($size >= 1024) {
        return number_format($size / 1024, 1, ',', '.') . ' KB';
    }

    return (string) $size . ' B';
};

$formatMoney = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatSignedMoney = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    $prefix = $numeric > 0 ? '+' : '';

    return $prefix . 'R$ ' . number_format($numeric, 2, ',', '.');
};

$deviationClass = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;
    if ($numeric > 0.009) {
        return 'text-danger';
    }

    if ($numeric < -0.009) {
        return 'text-success';
    }

    return 'text-muted';
};

$costTypeLabel = static function (string $type): string {
    return match ($type) {
        'mensal' => 'Mensal',
        'anual' => 'Anual',
        'eventual' => 'Eventual',
        'unico' => 'Unico (legado)',
        default => ucfirst($type),
    };
};

$costAnnualizedAmount = static function (float $amount, string $type): float {
    return match ($type) {
        'mensal' => $amount * 12,
        'anual', 'eventual', 'unico' => $amount,
        default => 0.0,
    };
};

$costYearEndAmount = static function (float $amount, string $type, ?string $startDateRaw): float {
    if ($amount <= 0.0) {
        return 0.0;
    }

    $year = (int) date('Y');
    $yearStart = strtotime(sprintf('%04d-01-01', $year));
    $yearEnd = strtotime(sprintf('%04d-12-31', $year));
    if ($yearStart === false || $yearEnd === false) {
        return 0.0;
    }

    $startTs = strtotime((string) $startDateRaw);
    if ($startTs === false || $startTs < $yearStart) {
        $startTs = $yearStart;
    }

    if ($startTs > $yearEnd) {
        return 0.0;
    }

    if ($type === 'eventual' || $type === 'unico') {
        return $amount;
    }

    $startYear = (int) date('Y', $startTs);
    $startMonth = (int) date('n', $startTs);
    $endYear = (int) date('Y', $yearEnd);
    $endMonth = (int) date('n', $yearEnd);
    $months = (($endYear - $startYear) * 12) + ($endMonth - $startMonth) + 1;
    if ($months < 0) {
        $months = 0;
    }

    if ($type === 'anual') {
        return ($amount / 12) * $months;
    }

    if ($type === 'mensal') {
        return $amount * $months;
    }

    return 0.0;
};

$costLinkageLabel = static function (int|string|null $code, string $itemName = ''): string {
    $numericCode = (int) $code;
    if ($numericCode === 510) {
        return 'Beneficios e auxilios (510)';
    }

    if ($numericCode === 309) {
        return 'Remuneracao (309)';
    }

    $normalizedName = mb_strtolower(trim($itemName));
    if (str_contains($normalizedName, 'auxilio') || str_contains($normalizedName, 'beneficio')) {
        return 'Beneficios e auxilios (inferido)';
    }

    return 'Remuneracao (inferido)';
};

$costReimbursableLabel = static function (int|string|null $flag, string $itemName = ''): string {
    if ((int) $flag === 1) {
        return 'Reembolsavel';
    }

    if ((int) $flag === 0 && $flag !== null && (string) $flag !== '') {
        return 'Nao-reembolsavel';
    }

    $normalizedName = mb_strtolower(trim($itemName));
    if (str_contains($normalizedName, 'auxilio') || str_contains($normalizedName, 'beneficio')) {
        return 'Reembolsavel (inferido)';
    }

    return 'Nao-reembolsavel (inferido)';
};

$costMacroCategoryLabel = static function (?string $value): string {
    $normalized = mb_strtolower(trim((string) $value));

    return match ($normalized) {
        'remuneracao_direta' => 'Remuneracao direta',
        'encargos_obrigacoes_legais' => 'Encargos e obrigacoes legais',
        'beneficios_provisoes_indiretos' => 'Beneficios, provisoes e custos indiretos',
        default => '-',
    };
};

$costExpenseNatureLabel = static function (?string $value): string {
    $normalized = mb_strtolower(trim((string) $value));

    return match ($normalized) {
        'remuneratoria' => 'Remuneratoria',
        'indenizatoria' => 'Indenizatoria',
        'encargos' => 'Encargos',
        'provisoes' => 'Provisoes',
        default => '-',
    };
};

$costReimbursabilityLabel = static function (?string $value, int|string|null $legacyFlag = null, string $itemName = '') use ($costReimbursableLabel): string {
    $normalized = mb_strtolower(trim((string) $value));
    if ($normalized === 'reembolsavel') {
        return 'Reembolsavel';
    }

    if ($normalized === 'parcialmente_reembolsavel') {
        return 'Parcialmente reembolsavel';
    }

    if ($normalized === 'nao_reembolsavel') {
        return 'Nao reembolsavel';
    }

    return $costReimbursableLabel($legacyFlag, $itemName);
};

$costPredictabilityLabel = static function (?string $value): string {
    $normalized = mb_strtolower(trim((string) $value));

    return match ($normalized) {
        'fixa' => 'Fixa',
        'variavel' => 'Variavel',
        'eventual' => 'Eventual',
        default => '-',
    };
};

$reimbursementTypeLabel = static function (string $type): string {
    return match ($type) {
        'boleto' => 'Boleto',
        'pagamento' => 'Pagamento',
        'ajuste' => 'Ajuste',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
};

$reimbursementStatusLabel = static function (string $status, bool $overdue = false): string {
    if ($overdue) {
        return 'Vencido';
    }

    return match ($status) {
        'pendente' => 'Pendente',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$reimbursementStatusClass = static function (string $status, bool $overdue = false): string {
    if ($overdue) {
        return 'badge-danger';
    }

    return match ($status) {
        'pendente' => 'badge-warning',
        'pago' => 'badge-success',
        'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$formatPercent = static function (float|int|string|null $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return number_format($numeric, 2, ',', '.') . '%';
};

$processCommentStatusLabel = static function (string $status): string {
    return match ($status) {
        'arquivado' => 'Arquivado',
        default => 'Aberto',
    };
};

$processCommentStatusBadgeClass = static function (string $status): string {
    return match ($status) {
        'arquivado' => 'badge-neutral',
        default => 'badge-info',
    };
};

$adminTimelineStatusBadgeClass = static function (string $statusGroup): string {
    return $statusGroup === 'aberto' ? 'badge-warning' : 'badge-success';
};

$adminTimelineSeverityBadgeClass = static function (string $severity): string {
    return match ($severity) {
        'alta' => 'badge-danger',
        'media' => 'badge-warning',
        default => 'badge-neutral',
    };
};

$adminTimelineSourceBadgeClass = static function (string $sourceKind): string {
    return match ($sourceKind) {
        'nota_manual', 'comentario_processo' => 'badge-info',
        'pendencia_operacional' => 'badge-danger',
        default => 'badge-neutral',
    };
};

$documentSensitivityLabel = static function (string $sensitivity): string {
    return match ($sensitivity) {
        'restricted' => 'Restrito',
        'sensitive' => 'Sensivel',
        default => 'Publico',
    };
};

$documentSensitivityBadgeClass = static function (string $sensitivity): string {
    return match ($sensitivity) {
        'restricted' => 'badge-warning',
        'sensitive' => 'badge-danger',
        default => 'badge-neutral',
    };
};

$queuePriorityLabel = static function (string $priority): string {
    return match ($priority) {
        'low' => 'Baixa',
        'high' => 'Alta',
        'urgent' => 'Urgente',
        default => 'Normal',
    };
};

$queuePriorityBadgeClass = static function (string $priority): string {
    return match ($priority) {
        'low' => 'badge-neutral',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger',
        default => 'badge-info',
    };
};

$checklistItemBadgeClass = static function (bool $isDone): string {
    return $isDone ? 'badge-success' : 'badge-neutral';
};

$auditEntityLabel = static function (string $entity): string {
    return match ($entity) {
        'person' => 'Pessoa',
        'organ' => 'Orgao',
        'assignment' => 'Movimentação',
        'assignment_checklist' => 'Checklist da movimentacao',
        'assignment_checklist_item' => 'Item de checklist',
        'timeline_event' => 'Timeline',
        'document' => 'Documento',
        'cost_plan' => 'Plano de custos',
        'cost_plan_item' => 'Item de custo',
        'reimbursement_entry' => 'Reembolso real',
        'analyst_pending_item' => 'Pendencia operacional',
        'process_comment' => 'Comentario interno',
        'process_admin_timeline_note' => 'Timeline administrativa',
        default => ucfirst(str_replace('_', ' ', $entity)),
    };
};

$prettyJson = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '-';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return $value;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($pretty) && trim($pretty) !== '' ? $pretty : '-';
};

$buildProfileUrl = static function (array $overrides = [], array $remove = []) use ($personId, $timelinePagination, $documentsPagination, $adminTimelinePagination, $adminTimelineFilters, $auditPagination, $auditFilters, $activeTab): string {
    $timelinePage = max(1, (int) ($timelinePagination['page'] ?? 1));
    $documentsPage = max(1, (int) ($documentsPagination['page'] ?? 1));
    $adminTimelinePage = max(1, (int) ($adminTimelinePagination['page'] ?? 1));
    $auditPage = max(1, (int) ($auditPagination['page'] ?? 1));

    $params = [
        'id' => $personId,
        'tab' => $activeTab !== 'summary' ? $activeTab : '',
        'timeline_page' => $timelinePage,
        'documents_page' => $documentsPage,
        'admin_timeline_page' => $adminTimelinePage,
        'admin_timeline_q' => (string) ($adminTimelineFilters['q'] ?? ''),
        'admin_timeline_source' => (string) ($adminTimelineFilters['source'] ?? ''),
        'admin_timeline_status_group' => (string) ($adminTimelineFilters['status_group'] ?? ''),
        'audit_page' => $auditPage,
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/people/show?' . http_build_query($params));
};

$buildTimelinePageUrl = static fn (int $targetPage): string => $buildProfileUrl(['tab' => 'timeline', 'timeline_page' => $targetPage]);
$buildDocumentsPageUrl = static fn (int $targetPage): string => $buildProfileUrl(['tab' => 'documents', 'documents_page' => $targetPage]);
$buildAdminTimelinePageUrl = static fn (int $targetPage): string => $buildProfileUrl(['tab' => 'admin-timeline', 'admin_timeline_page' => $targetPage]);
$buildAuditPageUrl = static fn (int $targetPage): string => $buildProfileUrl(['tab' => 'audit', 'audit_page' => $targetPage]);
$buildAuditExportUrl = static function () use ($personId, $auditFilters): string {
    $params = [
        'person_id' => $personId,
        'audit_entity' => (string) ($auditFilters['entity'] ?? ''),
        'audit_action' => (string) ($auditFilters['action'] ?? ''),
        'audit_q' => (string) ($auditFilters['q'] ?? ''),
        'audit_from' => (string) ($auditFilters['from_date'] ?? ''),
        'audit_to' => (string) ($auditFilters['to_date'] ?? ''),
    ];

    foreach ($params as $key => $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($params[$key]);
        }
    }

    return url('/people/audit/export?' . http_build_query($params));
};
$buildDossierExportUrl = static fn (): string => url('/people/dossier/export?person_id=' . $personId);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($person['name'] ?? 'Pessoa')) ?></h2>
      <p class="muted">Perfil 360</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e($buildDossierExportUrl()) ?>">Exportar dossie ZIP/PDF</a>
      <a class="btn btn-outline" href="<?= e(url('/people')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/people/edit?id=' . (int) ($person['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <?php
    $movementDirection = (string) ($assignment['movement_direction'] ?? 'entrada_mte');
    $movementDirectionLabel = match ($movementDirection) {
        'saida_mte' => 'Pessoa saindo do MTE',
        default => 'Pessoa entrando no MTE',
    };
    $financialNature = (string) ($assignment['financial_nature'] ?? 'despesa_reembolso');
    $financialNatureLabel = match ($financialNature) {
        'receita_reembolso' => 'Receita de reembolso (a receber)',
        default => 'Despesa de reembolso (a pagar)',
    };
  ?>

  <div class="tabs-row" data-tab-nav>
    <button type="button" class="tab-chip is-active" data-tab-target="summary">Resumo</button>
    <button type="button" class="tab-chip" data-tab-target="timeline">Execução do fluxo</button>
    <button type="button" class="tab-chip" data-tab-target="documents">Documentos</button>
    <button type="button" class="tab-chip" data-tab-target="costs">Custos</button>
    <button type="button" class="tab-chip" data-tab-target="conciliation">Conciliação</button>
    <button type="button" class="tab-chip" data-tab-target="finance">Financeiro real</button>
    <button type="button" class="tab-chip" data-tab-target="comments">Comentarios internos</button>
    <button type="button" class="tab-chip" data-tab-target="admin-timeline">Timeline administrativa</button>
    <button type="button" class="tab-chip" data-tab-target="audit">Auditoria</button>
  </div>

  <div class="details-grid" data-tab-panel="summary">
    <div><strong>Status:</strong> <?= e($statusLabel((string) ($person['status'] ?? ''))) ?></div>
    <div><strong>Fluxo BPMN:</strong> <?= e((string) ($flow['name'] ?? $person['assignment_flow_name'] ?? '-')) ?></div>
    <div><strong>Órgão:</strong> <?= e((string) ($person['organ_name'] ?? '-')) ?></div>
    <div><strong>Modalidade:</strong> <?= e((string) ($person['modality_name'] ?? '-')) ?></div>
    <div><strong>Direção do movimento:</strong> <?= e($movementDirectionLabel) ?></div>
    <div><strong>Natureza financeira:</strong> <?= e($financialNatureLabel) ?></div>
    <div><strong>Órgão de contraparte:</strong> <?= e((string) ($assignment['counterparty_organ_name'] ?? $person['organ_name'] ?? '-')) ?></div>
    <div><strong>Lotação origem MTE:</strong> <?= e((string) ($assignment['origin_mte_destination_name'] ?? '-')) ?></div>
    <div><strong>Lotação destino MTE:</strong> <?= e((string) ($assignment['destination_mte_destination_name'] ?? '-')) ?></div>
    <div><strong>Início efetivo (previsto):</strong> <?= e($formatDate((string) ($assignment['target_start_date'] ?? ''))) ?></div>
    <div><strong>Início efetivo (real):</strong> <?= e($formatDate((string) ($assignment['effective_start_date'] ?? ''))) ?></div>
    <div><strong>Término efetivo (previsto):</strong> <?= e($formatDate((string) ($assignment['requested_end_date'] ?? ''))) ?></div>
    <div><strong>Término efetivo (real):</strong> <?= e($formatDate((string) ($assignment['effective_end_date'] ?? ''))) ?></div>
    <div><strong>CPF:</strong>
      <?php if (($canViewCpfFull ?? false) === true): ?>
        <?= e((string) ($person['cpf'] ?? '-')) ?>
      <?php else: ?>
        <?= e(mask_cpf((string) ($person['cpf'] ?? ''))) ?>
      <?php endif; ?>
    </div>
    <div><strong>Matrícula SIAPE:</strong> <?= e((string) ($person['matricula_siape'] ?? '-')) ?></div>
    <div><strong>Nascimento:</strong> <?= e((string) ($person['birth_date'] ?? '-')) ?></div>
    <div><strong>E-mail:</strong> <?= e((string) ($person['email'] ?? '-')) ?></div>
    <div><strong>Telefone:</strong> <?= e((string) ($person['phone'] ?? '-')) ?></div>
    <div><strong>Nº processo SEI:</strong> <?= e((string) ($person['sei_process_number'] ?? '-')) ?></div>
    <div><strong>Tags:</strong> <?= e((string) ($person['tags'] ?? '-')) ?></div>
    <div class="details-wide"><strong>Observações:</strong> <?= nl2br(e((string) ($person['notes'] ?? '-'))) ?></div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var chips = Array.prototype.slice.call(document.querySelectorAll('[data-tab-target]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));
    if (chips.length === 0 || panels.length === 0) {
      return;
    }

    var availableTabs = chips.map(function (chip) {
      return chip.getAttribute('data-tab-target') || '';
    });

    var syncTabOnUrl = function (tabName) {
      try {
        var nextUrl = new URL(window.location.href);
        if (tabName === 'summary') {
          nextUrl.searchParams.delete('tab');
        } else {
          nextUrl.searchParams.set('tab', tabName);
        }
        window.history.replaceState({}, '', nextUrl.toString());
      } catch (error) {
      }
    };

    var activateTab = function (targetTab, syncUrl) {
      var nextTab = availableTabs.indexOf(targetTab) === -1 ? 'summary' : targetTab;

      chips.forEach(function (chip) {
        var isActive = (chip.getAttribute('data-tab-target') || '') === nextTab;
        chip.classList.toggle('is-active', isActive);
        chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.hidden = (panel.getAttribute('data-tab-panel') || '') !== nextTab;
      });

      if (syncUrl === true) {
        syncTabOnUrl(nextTab);
      }
    };

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        activateTab(chip.getAttribute('data-tab-target') || 'summary', true);
      });
    });

    activateTab(<?= json_encode($activeTab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, false);

    var focusTargetId = <?= json_encode($activeFocusTarget, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (focusTargetId !== '' && <?= json_encode($activeTab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> === 'timeline') {
      var focusTarget = document.getElementById(focusTargetId);
      if (focusTarget) {
        focusTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      try {
        var cleanedUrl = new URL(window.location.href);
        cleanedUrl.searchParams.delete('focus');
        window.history.replaceState({}, '', cleanedUrl.toString());
      } catch (error) {
      }
    }
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.reimbursement-form');
    if (!form) {
      return;
    }

    var preview = document.getElementById('reimbursement_calc_total_preview');
    var amountInput = document.getElementById('reimbursement_amount');
    var toggle = document.getElementById('reimbursement_use_calculator');
    var fields = [
      'reimbursement_calc_base',
      'reimbursement_calc_transport',
      'reimbursement_calc_lodging',
      'reimbursement_calc_food',
      'reimbursement_calc_other',
      'reimbursement_calc_discount',
      'reimbursement_calc_adjustment'
    ].map(function (id) {
      return document.getElementById(id);
    }).filter(Boolean);

    var toNumber = function (value) {
      var normalized = String(value || '').replace(',', '.').replace(/[^0-9.-]/g, '');
      var numeric = Number(normalized);
      return Number.isFinite(numeric) ? numeric : 0;
    };

    var formatMoney = function (value) {
      return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    var recalc = function () {
      var baseInput = document.getElementById('reimbursement_calc_base');
      var transportInput = document.getElementById('reimbursement_calc_transport');
      var lodgingInput = document.getElementById('reimbursement_calc_lodging');
      var foodInput = document.getElementById('reimbursement_calc_food');
      var otherInput = document.getElementById('reimbursement_calc_other');
      var discountInput = document.getElementById('reimbursement_calc_discount');
      var adjustmentInput = document.getElementById('reimbursement_calc_adjustment');
      var base = toNumber(baseInput ? baseInput.value : '');
      var transport = toNumber(transportInput ? transportInput.value : '');
      var lodging = toNumber(lodgingInput ? lodgingInput.value : '');
      var food = toNumber(foodInput ? foodInput.value : '');
      var other = toNumber(otherInput ? otherInput.value : '');
      var discount = toNumber(discountInput ? discountInput.value : '');
      var adjustmentPercent = toNumber(adjustmentInput ? adjustmentInput.value : '');
      var subtotal = base + transport + lodging + food + other;
      var adjustment = subtotal * (adjustmentPercent / 100);
      var total = subtotal + adjustment - discount;

      if (preview) {
        preview.textContent = formatMoney(total);
      }

      if (toggle && toggle.checked && amountInput) {
        amountInput.value = total > 0 ? total.toFixed(2) : '';
      }
    };

    fields.forEach(function (field) {
      field.addEventListener('input', recalc);
    });

    if (toggle) {
      toggle.addEventListener('change', recalc);
    }

    recalc();
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var expandToggles = Array.prototype.slice.call(document.querySelectorAll('[data-cost-expand-toggle]'));
    expandToggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var token = String(toggle.getAttribute('data-cost-expand-toggle') || '');
        if (!token) {
          return;
        }

        var target = document.getElementById('cost-expand-' + token);
        if (!target) {
          return;
        }

        var willOpen = target.hidden;
        target.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });

    var reimbursementBatchForm = document.querySelector('[data-reimbursement-batch-form]');
    if (reimbursementBatchForm) {
      var reimbursementRows = Array.prototype.slice.call(reimbursementBatchForm.querySelectorAll('[data-reimbursement-row]'));
      var reimbursementAmountInputs = Array.prototype.slice.call(reimbursementBatchForm.querySelectorAll('[data-reimbursement-batch-amount]'));
      var reimbursementTotalNode = reimbursementBatchForm.querySelector('[data-reimbursement-batch-total]');
      var reimbursementCountNode = reimbursementBatchForm.querySelector('[data-reimbursement-batch-count]');

      var reimbursementParseMoney = function (value) {
        var normalized = String(value || '').replace(',', '.').replace(/[^0-9.-]/g, '');
        var numeric = Number(normalized);
        return Number.isFinite(numeric) ? numeric : 0;
      };

      var reimbursementFormatMoney = function (value) {
        return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
      };

      var reimbursementRowById = {};
      var reimbursementChildrenByParent = {};
      reimbursementRows.forEach(function (row) {
        var rowId = String(row.getAttribute('data-id') || '');
        if (!rowId) {
          return;
        }
        reimbursementRowById[rowId] = row;

        var rowLevel = String(row.getAttribute('data-level') || 'aggregator').toLowerCase();
        if (rowLevel !== 'child') {
          return;
        }

        var parentId = String(row.getAttribute('data-parent-id') || '');
        if (!parentId) {
          return;
        }
        if (!reimbursementChildrenByParent[parentId]) {
          reimbursementChildrenByParent[parentId] = [];
        }
        reimbursementChildrenByParent[parentId].push(rowId);
      });

      var reimbursementRecalc = function () {
        var rowAmountById = {};
        reimbursementRows.forEach(function (row) {
          var rowId = String(row.getAttribute('data-id') || '');
          if (!rowId) {
            return;
          }
          var amountInput = row.querySelector('[data-reimbursement-batch-amount]');
          rowAmountById[rowId] = reimbursementParseMoney(amountInput ? amountInput.value : '');
        });

        var totalAmount = 0;
        var totalCount = 0;
        var consumed = {};

        reimbursementRows.forEach(function (row) {
          var rowId = String(row.getAttribute('data-id') || '');
          var rowLevel = String(row.getAttribute('data-level') || 'aggregator').toLowerCase();
          if (!rowId || rowLevel !== 'aggregator') {
            return;
          }

          var childIds = Array.isArray(reimbursementChildrenByParent[rowId]) ? reimbursementChildrenByParent[rowId] : [];
          var childAmount = 0;
          var childCount = 0;
          childIds.forEach(function (childId) {
            var amount = Number(rowAmountById[childId] || 0);
            if (amount <= 0) {
              return;
            }
            childAmount += amount;
            childCount += 1;
            consumed[childId] = true;
          });

          row.classList.toggle('is-overridden', childCount > 0);

          if (childCount > 0) {
            totalAmount += childAmount;
            totalCount += childCount;
            consumed[rowId] = true;
            return;
          }

          var ownAmount = Number(rowAmountById[rowId] || 0);
          if (ownAmount > 0) {
            totalAmount += ownAmount;
            totalCount += 1;
          }
          consumed[rowId] = true;
        });

        reimbursementRows.forEach(function (row) {
          var rowId = String(row.getAttribute('data-id') || '');
          if (!rowId || consumed[rowId]) {
            return;
          }
          var amount = Number(rowAmountById[rowId] || 0);
          if (amount <= 0) {
            return;
          }
          totalAmount += amount;
          totalCount += 1;
        });

        if (reimbursementTotalNode) {
          reimbursementTotalNode.textContent = reimbursementFormatMoney(totalAmount);
        }
        if (reimbursementCountNode) {
          reimbursementCountNode.textContent = totalCount + ' item(ns)';
        }
      };

      reimbursementAmountInputs.forEach(function (input) {
        input.addEventListener('input', reimbursementRecalc);
        input.addEventListener('blur', reimbursementRecalc);
      });

      reimbursementRecalc();
    }

    var form = document.querySelector('[data-cost-batch-form]');
    if (!form) {
      return;
    }

    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-cost-row]'));
    if (rows.length === 0) {
      return;
    }

    var currentYearRaw = Number(form.getAttribute('data-current-year') || '');
    var currentYear = Number.isFinite(currentYearRaw) && currentYearRaw > 2000
      ? currentYearRaw
      : new Date().getFullYear();

    var parseDate = function (value) {
      var normalized = String(value || '').trim();
      if (!normalized) {
        return null;
      }

      var parts = normalized.split('-');
      if (parts.length !== 3) {
        return null;
      }

      var year = Number(parts[0]);
      var month = Number(parts[1]);
      var day = Number(parts[2]);
      if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
        return null;
      }

      var date = new Date(year, month - 1, day);
      if (Number.isNaN(date.getTime())) {
        return null;
      }

      return date;
    };

    var parseMoney = function (value) {
      var normalized = String(value || '').replace(',', '.').replace(/[^0-9.-]/g, '');
      var numeric = Number(normalized);
      return Number.isFinite(numeric) ? numeric : 0;
    };

    var formatMoney = function (value) {
      return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    var plannedStart = parseDate(form.getAttribute('data-planned-start') || '');
    var yearStart = new Date(currentYear, 0, 1);
    var yearEnd = new Date(currentYear, 11, 31);

    var maxDate = function (left, right) {
      if (!left) {
        return right;
      }

      if (!right) {
        return left;
      }

      return left.getTime() >= right.getTime() ? left : right;
    };

    var monthsInRange = function (startDate, endDate) {
      if (!startDate || !endDate || endDate.getTime() < startDate.getTime()) {
        return 0;
      }

      return ((endDate.getFullYear() - startDate.getFullYear()) * 12)
        + (endDate.getMonth() - startDate.getMonth())
        + 1;
    };

    var annualizedValue = function (amount, periodicity) {
      if (periodicity === 'mensal') {
        return amount * 12;
      }

      if (periodicity === 'anual' || periodicity === 'eventual' || periodicity === 'unico') {
        return amount;
      }

      return 0;
    };

    var valueUntilYearEnd = function (amount, periodicity, startDate) {
      if (amount <= 0) {
        return 0;
      }

      var effectiveStart = maxDate(startDate, plannedStart);
      effectiveStart = maxDate(effectiveStart, yearStart);
      if (!effectiveStart) {
        effectiveStart = yearStart;
      }

      var effectiveEnd = yearEnd;

      if (effectiveEnd.getTime() < effectiveStart.getTime()) {
        return 0;
      }

      if (periodicity === 'mensal') {
        return amount * monthsInRange(effectiveStart, effectiveEnd);
      }

      if (periodicity === 'anual') {
        return (amount / 12) * monthsInRange(effectiveStart, effectiveEnd);
      }

      if (periodicity === 'eventual' || periodicity === 'unico') {
        var singleDate = startDate || plannedStart || yearStart;
        if (!singleDate) {
          return 0;
        }

        if (singleDate.getTime() < yearStart.getTime() || singleDate.getTime() > effectiveEnd.getTime()) {
          return 0;
        }

        return amount;
      }

      return 0;
    };

    var totalPeriodNode = form.querySelector('[data-cost-total-period]');
    var totalAnnualizedNode = form.querySelector('[data-cost-total-annualized]');
    var totalYearEndNode = form.querySelector('[data-cost-total-year-end]');
    var totalItemsNode = form.querySelector('[data-cost-total-items]');
    var editableInputs = Array.prototype.slice.call(form.querySelectorAll('[data-cost-editable]'));
    var rowMetrics = {};
    var rowById = {};
    var childrenByParent = {};

    rows.forEach(function (row) {
      var rowId = String(row.getAttribute('data-cost-id') || '');
      if (rowId !== '') {
        rowById[rowId] = row;
      }

      var rowLevel = String(row.getAttribute('data-cost-level') || 'aggregator').toLowerCase();
      if (rowLevel !== 'child') {
        return;
      }

      var parentId = String(row.getAttribute('data-parent-id') || '');
      if (parentId === '') {
        return;
      }

      if (!childrenByParent[parentId]) {
        childrenByParent[parentId] = [];
      }
      childrenByParent[parentId].push(row);
    });

    var recalc = function () {
      rowMetrics = {};

      rows.forEach(function (row) {
        var periodicityInput = row.querySelector('[data-cost-type]');
        var periodicity = String(periodicityInput ? periodicityInput.value : 'mensal').toLowerCase();
        var amountInput = row.querySelector('[data-cost-amount]');
        var startInput = row.querySelector('[data-cost-start-date]');
        var annualizedNode = row.querySelector('[data-cost-annualized]');
        var yearEndNode = row.querySelector('[data-cost-year-end]');
        var rowId = String(row.getAttribute('data-cost-id') || '');

        var amount = parseMoney(amountInput ? amountInput.value : '');
        var startDate = parseDate(startInput ? startInput.value : '');
        var annualized = annualizedValue(amount, periodicity);
        var projectedYearEnd = valueUntilYearEnd(amount, periodicity, startDate);

        if (annualizedNode) {
          annualizedNode.textContent = formatMoney(annualized);
        }

        if (yearEndNode) {
          yearEndNode.textContent = formatMoney(projectedYearEnd);
        }

        if (rowId !== '') {
          rowMetrics[rowId] = {
            amount: amount,
            annualized: annualized,
            yearEnd: projectedYearEnd
          };
        }
      });

      var totalPeriod = 0;
      var totalAnnualized = 0;
      var totalYearEnd = 0;
      var totalItems = 0;
      var consumedRows = {};

      rows.forEach(function (row) {
        var rowId = String(row.getAttribute('data-cost-id') || '');
        var rowLevel = String(row.getAttribute('data-cost-level') || 'aggregator').toLowerCase();
        if (rowId === '' || rowLevel !== 'aggregator') {
          return;
        }

        var children = Array.isArray(childrenByParent[rowId]) ? childrenByParent[rowId] : [];
        var detailedChildren = [];
        children.forEach(function (childRow) {
          var childId = String(childRow.getAttribute('data-cost-id') || '');
          if (!childId) {
            return;
          }
          var childMetric = rowMetrics[childId];
          if (!childMetric || childMetric.amount <= 0) {
            return;
          }
          detailedChildren.push(childMetric);
          consumedRows[childId] = true;
        });

        var isOverridden = detailedChildren.length > 0;
        row.classList.toggle('is-overridden', isOverridden);
        if (isOverridden) {
          row.setAttribute('title', 'Categoria com filhos detalhados: valor da categoria sera ignorado no salvamento.');
          detailedChildren.forEach(function (metric) {
            totalPeriod += metric.amount;
            totalAnnualized += metric.annualized;
            totalYearEnd += metric.yearEnd;
            if (metric.amount > 0) {
              totalItems += 1;
            }
          });
          consumedRows[rowId] = true;
          return;
        }

        var metric = rowMetrics[rowId];
        if (!metric) {
          return;
        }
        totalPeriod += metric.amount;
        totalAnnualized += metric.annualized;
        totalYearEnd += metric.yearEnd;
        if (metric.amount > 0) {
          totalItems += 1;
        }
        consumedRows[rowId] = true;
      });

      rows.forEach(function (row) {
        var rowId = String(row.getAttribute('data-cost-id') || '');
        if (rowId === '' || consumedRows[rowId]) {
          return;
        }
        var metric = rowMetrics[rowId];
        if (!metric) {
          return;
        }
        totalPeriod += metric.amount;
        totalAnnualized += metric.annualized;
        totalYearEnd += metric.yearEnd;
        if (metric.amount > 0) {
          totalItems += 1;
        }
      });

      if (totalPeriodNode) {
        totalPeriodNode.textContent = formatMoney(totalPeriod);
      }

      if (totalAnnualizedNode) {
        totalAnnualizedNode.textContent = formatMoney(totalAnnualized);
      }

      if (totalYearEndNode) {
        totalYearEndNode.textContent = formatMoney(totalYearEnd);
      }

      if (totalItemsNode) {
        totalItemsNode.textContent = totalItems + ' item(ns)';
      }
    };

    var focusEditableByOffset = function (currentInput, offset) {
      var currentIndex = editableInputs.indexOf(currentInput);
      if (currentIndex < 0) {
        return;
      }

      var nextIndex = currentIndex + offset;
      if (nextIndex < 0 || nextIndex >= editableInputs.length) {
        return;
      }

      var nextInput = editableInputs[nextIndex];
      if (!nextInput) {
        return;
      }

      nextInput.focus();
      if (nextInput.select && nextInput.type !== 'date') {
        nextInput.select();
      }
    };

    var normalizeAmountInput = function (input) {
      if (!input || !input.hasAttribute('data-cost-amount')) {
        return;
      }

      var amount = parseMoney(input.value);
      input.value = amount > 0 ? amount.toFixed(2) : '';
    };

    var amountPlaceholderByPeriodicity = function (periodicity) {
      if (periodicity === 'anual') {
        return 'Ex.: 18000,00/ano';
      }

      if (periodicity === 'eventual') {
        return 'Ex.: 5000,00 (eventual)';
      }

      if (periodicity === 'unico') {
        return 'Ex.: 5000,00 (unico legado)';
      }

      return 'Ex.: 1500,00/mes';
    };

    var updateAmountPlaceholder = function (row) {
      var periodicityInput = row.querySelector('[data-cost-type]');
      var amountInput = row.querySelector('[data-cost-amount]');
      if (!periodicityInput || !amountInput) {
        return;
      }

      amountInput.placeholder = amountPlaceholderByPeriodicity(String(periodicityInput.value || '').toLowerCase());
    };

    rows.forEach(function (row) {
      var inputs = Array.prototype.slice.call(row.querySelectorAll('[data-cost-editable]'));
      inputs.forEach(function (input) {
        input.addEventListener('input', recalc);
        input.addEventListener('change', recalc);

        input.addEventListener('keydown', function (event) {
          if (event.key !== 'Enter') {
            return;
          }

          event.preventDefault();
          normalizeAmountInput(input);
          focusEditableByOffset(input, event.shiftKey ? -1 : 1);
          recalc();
        });

        if (input.hasAttribute('data-cost-amount')) {
          input.addEventListener('blur', function () {
            normalizeAmountInput(input);
            recalc();
          });
        }
      });

      var periodicityInput = row.querySelector('[data-cost-type]');
      if (periodicityInput) {
        periodicityInput.addEventListener('change', function () {
          updateAmountPlaceholder(row);
          recalc();
        });
      }

      updateAmountPlaceholder(row);
    });

    form.addEventListener('keydown', function (event) {
      var key = String(event.key || '').toLowerCase();
      if (!(event.ctrlKey || event.metaKey) || key !== 's') {
        return;
      }

      event.preventDefault();
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }

      form.submit();
    });

    recalc();
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-cost-version-toggle]'));
    var detailRows = Array.prototype.slice.call(document.querySelectorAll('.cost-version-detail-row'));
    if (toggles.length === 0 || detailRows.length === 0) {
      return;
    }

    var collapseAll = function () {
      toggles.forEach(function (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      });

      detailRows.forEach(function (row) {
        row.hidden = true;
      });
    };

    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var targetId = String(toggle.getAttribute('data-cost-version-toggle') || '');
        if (targetId === '') {
          return;
        }

        var detailRow = document.getElementById('cost-version-detail-' + targetId);
        if (!detailRow) {
          return;
        }

        var willOpen = detailRow.hidden;
        collapseAll();
        if (willOpen) {
          detailRow.hidden = false;
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
    });
  });
</script>

<div id="pipeline-overview" class="card" data-tab-panel="timeline">
  <div class="header-row">
    <div>
      <h3>Execução do Pipeline BPMN</h3>
      <p class="muted">
        Fluxo selecionado:
        <?= e((string) ($flow['name'] ?? $assignment['flow_name'] ?? 'Não definido')) ?>
      </p>
    </div>
    <?php if (($canManage ?? false) === true && $assignment !== null): ?>
      <?php if (count($availableTransitions) > 1): ?>
        <form method="post" action="<?= e(url('/people/pipeline/advance')) ?>" class="actions-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? 0)) ?>">
          <select name="transition_id" required>
            <option value="">Escolha a transição</option>
            <?php foreach ($availableTransitions as $transitionOption): ?>
              <?php
                $transitionOptionId = (int) ($transitionOption['id'] ?? 0);
                $transitionOptionLabel = trim((string) ($transitionOption['action_label'] ?? ''));
                if ($transitionOptionLabel === '') {
                    $transitionOptionLabel = trim((string) ($transitionOption['transition_label'] ?? ''));
                }
                if ($transitionOptionLabel === '') {
                    $transitionOptionLabel = 'Avançar para ' . (string) ($transitionOption['to_label'] ?? 'próxima etapa');
                }
              ?>
              <option value="<?= e((string) $transitionOptionId) ?>">
                <?= e($transitionOptionLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary">Aplicar transição</button>
        </form>
      <?php elseif ($nextStatus !== null): ?>
        <form method="post" action="<?= e(url('/people/pipeline/advance')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string) ($person['id'] ?? 0)) ?>">
          <?php if ((int) ($nextStatus['transition_id'] ?? 0) > 0): ?>
            <input type="hidden" name="transition_id" value="<?= e((string) ((int) $nextStatus['transition_id'])) ?>">
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">
            <?= e((string) ($nextStatus['next_action_label'] ?? ('Avançar para ' . ($nextStatus['label'] ?? 'próxima etapa')))) ?>
          </button>
        </form>
      <?php else: ?>
        <span class="badge badge-success">Fluxo concluído</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($assignment === null): ?>
    <p class="muted">Pipeline ainda não inicializado para esta pessoa. Defina um fluxo no cadastro e tente novamente.</p>
  <?php else: ?>
    <?php
      $transitionTargetStatusIds = array_values(array_unique(array_filter(array_map(
          static fn (array $transition): int => (int) ($transition['to_status_id'] ?? 0),
          $availableTransitions
      ))));
    ?>
    <div class="pipeline-track">
      <?php foreach ($statuses as $stage): ?>
        <?php
          $stageId = (int) ($stage['id'] ?? 0);
          $stageOrder = (int) ($stage['sort_order'] ?? 0);
          $stageKind = (string) ($stage['node_kind'] ?? 'activity');
          $currentStatusId = (int) ($assignment['current_status_id'] ?? 0);
          $stageClass = 'is-pending';
          if ($stageId > 0 && $stageId === $currentStatusId) {
              $stageClass = 'is-current';
          } elseif ($stageId > 0 && in_array($stageId, $transitionTargetStatusIds, true)) {
              $stageClass = 'is-available';
          }
        ?>
        <div class="pipeline-step <?= e($stageClass) ?>">
          <span class="pipeline-index"><?= e((string) ($stageOrder > 0 ? $stageOrder : '•')) ?></span>
          <span>
            <strong><?= e((string) ($stage['label'] ?? '')) ?></strong><br>
            <small class="muted"><?= e($nodeKindLabel($stageKind)) ?></small>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php
      $currentAssignedUserId = (int) ($assignment['assigned_user_id'] ?? 0);
      $currentPriority = mb_strtolower(trim((string) ($assignment['priority_level'] ?? 'normal')));
    ?>
    <div class="summary-line"><strong>Status atual:</strong> <?= e((string) ($assignment['current_status_label'] ?? '-')) ?></div>
    <?php if ($availableTransitions === []): ?>
      <div class="summary-line"><strong>Próxima ação:</strong> Sem transições disponíveis.</div>
    <?php else: ?>
      <div class="summary-line"><strong>Próxima ação:</strong> <?= e((string) count($availableTransitions)) ?> opção(ões) disponível(is).</div>
      <ul class="attachments-list">
        <?php foreach ($availableTransitions as $transition): ?>
          <?php
            $transitionLabel = trim((string) ($transition['transition_label'] ?? ''));
            if ($transitionLabel === '') {
                $transitionLabel = trim((string) ($transition['action_label'] ?? ''));
            }
            if ($transitionLabel === '') {
                $transitionLabel = 'Avançar';
            }
          ?>
          <li>
            <strong><?= e($transitionLabel) ?></strong>
            <span class="muted">→ <?= e((string) ($transition['to_label'] ?? '-')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="summary-line"><strong>Responsável da fila:</strong> <?= e((string) ($assignment['assigned_user_name'] ?? 'Não definido')) ?></div>
    <div class="summary-line">
      <strong>Prioridade:</strong>
      <span class="badge <?= e($queuePriorityBadgeClass($currentPriority)) ?>"><?= e($queuePriorityLabel($currentPriority)) ?></span>
    </div>

    <?php if (($canManage ?? false) === true): ?>
      <form method="post" action="<?= e(url('/people/pipeline/queue/update')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
        <input type="hidden" name="assignment_id" value="<?= e((string) ((int) ($assignment['id'] ?? 0))) ?>">
        <div class="field">
          <label for="queue_assigned_user_id">Responsável</label>
          <select id="queue_assigned_user_id" name="assigned_user_id">
            <option value="0">Não definido</option>
            <?php foreach ($queueUsers as $queueUser): ?>
              <?php $queueUserId = (int) ($queueUser['id'] ?? 0); ?>
              <option value="<?= e((string) $queueUserId) ?>" <?= $currentAssignedUserId === $queueUserId ? 'selected' : '' ?>>
                <?= e((string) ($queueUser['name'] ?? ('Usuário #' . $queueUserId))) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="queue_priority_level">Prioridade</label>
          <select id="queue_priority_level" name="priority_level" required>
            <?php foreach ($queuePriorities as $priorityOption): ?>
              <?php $priorityValue = (string) ($priorityOption['value'] ?? 'normal'); ?>
              <option value="<?= e($priorityValue) ?>" <?= $currentPriority === $priorityValue ? 'selected' : '' ?>>
                <?= e((string) ($priorityOption['label'] ?? $priorityValue)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-outline">Atualizar fila</button>
        </div>
      </form>
    <?php endif; ?>

    <div class="header-row">
      <div>
        <h4>Checklist automatico</h4>
        <p class="muted">
          Caso: <?= e($checklistCaseTypeLabel) ?> ·
          Obrigatorios concluidos: <?= e((string) $checklistRequiredDone) ?>/<?= e((string) $checklistRequiredTotal) ?> ·
          Progresso: <?= e((string) $checklistPercent) ?>%
        </p>
      </div>
    </div>

    <?php if ($checklistItems === []): ?>
      <p class="muted">Nenhum item de checklist disponivel para este tipo de caso.</p>
    <?php else: ?>
      <div class="timeline-list">
        <?php foreach ($checklistItems as $checklistItem): ?>
          <?php
            $checklistItemId = (int) ($checklistItem['id'] ?? 0);
            $checklistIsDone = (int) ($checklistItem['is_done'] ?? 0) === 1;
            $checklistIsRequired = (int) ($checklistItem['is_required'] ?? 1) === 1;
          ?>
          <article class="timeline-item">
            <div class="timeline-item-header">
              <div class="timeline-item-title">
                <strong><?= e((string) ($checklistItem['item_label'] ?? 'Item')) ?></strong>
                <span class="badge <?= e($checklistItemBadgeClass($checklistIsDone)) ?>">
                  <?= $checklistIsDone ? 'Concluido' : 'Pendente' ?>
                </span>
                <?php if ($checklistIsRequired): ?>
                  <span class="badge badge-warning">Obrigatorio</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Opcional</span>
                <?php endif; ?>
              </div>
              <span class="muted">
                <?php if ($checklistIsDone): ?>
                  <?= e($formatDateTime((string) ($checklistItem['done_at'] ?? ''))) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </span>
            </div>

            <?php if (trim((string) ($checklistItem['item_description'] ?? '')) !== ''): ?>
              <p class="timeline-item-description"><?= e((string) ($checklistItem['item_description'] ?? '')) ?></p>
            <?php endif; ?>

            <?php if ($checklistIsDone): ?>
              <p class="muted">Concluido por: <?= e((string) ($checklistItem['done_by_name'] ?? 'Sistema')) ?></p>
            <?php endif; ?>

            <?php if (($canManage ?? false) === true && $checklistItemId > 0): ?>
              <form method="post" action="<?= e(url('/people/pipeline/checklist/update')) ?>" class="actions-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                <input type="hidden" name="assignment_id" value="<?= e((string) ((int) ($assignment['id'] ?? 0))) ?>">
                <input type="hidden" name="checklist_item_id" value="<?= e((string) $checklistItemId) ?>">
                <input type="hidden" name="is_done" value="<?= $checklistIsDone ? '0' : '1' ?>">
                <button type="submit" class="btn btn-outline">
                  <?= $checklistIsDone ? 'Marcar pendente' : 'Marcar concluido' ?>
                </button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<div id="timeline-history" class="card" data-tab-panel="timeline">
  <div class="header-row">
    <div>
      <h3>Timeline do fluxo</h3>
      <p class="muted">Histórico operacional atrelado ao pipeline, com trilha imutável e retificações.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/people/timeline/print?id=' . $personId)) ?>" target="_blank" rel="noopener">Imprimir timeline</a>
    </div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <?php
      $timelineContextEventId = max(0, (int) ($_GET['timeline_context_event_id'] ?? '0'));
      $timelineContextEvent = null;
      foreach ($timeline as $timelineCandidate) {
          if ((int) ($timelineCandidate['id'] ?? 0) === $timelineContextEventId) {
              $timelineContextEvent = $timelineCandidate;
              break;
          }
      }
      $timelineContextMetadata = $timelineContextEvent !== null ? $decodeMetadata($timelineContextEvent['metadata'] ?? null) : [];
      $timelineContextStatusCode = trim((string) ($timelineContextMetadata['pipeline_status_code'] ?? ($timelineContextMetadata['status_code'] ?? '')));
      $timelineContextStatusLabel = trim((string) ($timelineContextMetadata['pipeline_status_label'] ?? ($timelineContextMetadata['status_label'] ?? '')));
      $timelineContextTitle = trim((string) ($timelineContextEvent['title'] ?? ''));
      $timelineDefaultTitle = '';
      if ($timelineContextEvent !== null && $timelineContextTitle !== '') {
          $timelineDefaultTitle = mb_substr('Evidencia complementar - ' . $timelineContextTitle, 0, 190);
      }
      $closeStepTransitionCount = count($availableTransitions);
      $singleCloseTransition = $closeStepTransitionCount === 1 ? $availableTransitions[0] : null;
    ?>
    <form method="post" action="<?= e(url('/people/timeline/store')) ?>" enctype="multipart/form-data" class="timeline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <?php if ($timelineContextEvent !== null): ?>
        <?php
          $timelineContextLabel = trim((string) ($timelineContextEvent['title'] ?? 'evento de timeline'));
          $timelineContextType = trim((string) ($timelineContextEvent['event_type'] ?? 'evento'));
        ?>
        <div id="timeline-evidence-form" class="timeline-context-alert">
          <strong>Evidencia contextual ativa</strong>
          <p class="muted">
            Novo registro vinculado ao evento #<?= e((string) ((int) ($timelineContextEvent['id'] ?? 0))) ?>
            (<?= e($eventTypeLabel($timelineContextType)) ?>: <?= e($timelineContextLabel) ?>).
          </p>
          <div class="actions-inline">
            <a class="btn btn-ghost" href="<?= e($buildProfileUrl(['tab' => 'timeline'], ['timeline_context_event_id'])) ?>#timeline-evidence-form">Remover contexto</a>
          </div>
        </div>
        <input type="hidden" name="context_event_id" value="<?= e((string) ((int) ($timelineContextEvent['id'] ?? 0))) ?>">
        <?php if ($timelineContextStatusCode !== ''): ?>
          <input type="hidden" name="context_status_code" value="<?= e($timelineContextStatusCode) ?>">
        <?php endif; ?>
        <?php if ($timelineContextStatusLabel !== ''): ?>
          <input type="hidden" name="context_status_label" value="<?= e($timelineContextStatusLabel) ?>">
        <?php endif; ?>
      <?php else: ?>
        <div id="timeline-evidence-form"></div>
      <?php endif; ?>

      <div class="form-grid timeline-form-grid">
        <div class="field">
          <label for="event_type">Tipo de evento</label>
          <select id="event_type" name="event_type" required>
            <option value="">Selecione...</option>
            <?php foreach ($eventTypes as $type): ?>
              <option value="<?= e((string) ($type['name'] ?? '')) ?>"><?= e((string) ($type['description'] ?? $type['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="event_date">Data do evento</label>
          <input id="event_date" name="event_date" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="field field-wide">
          <label for="timeline_title">Título</label>
          <input id="timeline_title" name="title" type="text" minlength="3" maxlength="190" required value="<?= e($timelineDefaultTitle) ?>">
        </div>
        <div class="field field-wide">
          <label for="timeline_description">Descrição</label>
          <textarea id="timeline_description" name="description" rows="4"></textarea>
        </div>
        <div class="field field-wide">
          <label for="timeline_attachments">Anexos (PDF/JPG/PNG até 10MB)</label>
          <input id="timeline_attachments" name="attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
        </div>
        <div class="field field-wide">
          <label for="timeline_evidence_links">Links de evidência (1 por linha, opcional)</label>
          <textarea id="timeline_evidence_links" name="evidence_links" rows="3" placeholder="https://exemplo.gov.br/documento&#10;Portal SEI | https://sei.exemplo.gov.br/consulta"></textarea>
        </div>
        <?php if ($assignment !== null && $closeStepTransitionCount > 1): ?>
          <div class="field field-wide">
            <label for="timeline_close_transition_id">Transição para encerrar etapa (opcional)</label>
            <select id="timeline_close_transition_id" name="close_transition_id">
              <option value="">Selecionar no momento do encerramento</option>
              <?php foreach ($availableTransitions as $transitionOption): ?>
                <?php
                  $closeTransitionId = (int) ($transitionOption['id'] ?? 0);
                  $closeTransitionLabel = trim((string) ($transitionOption['action_label'] ?? ''));
                  if ($closeTransitionLabel === '') {
                      $closeTransitionLabel = trim((string) ($transitionOption['transition_label'] ?? ''));
                  }
                  if ($closeTransitionLabel === '') {
                      $closeTransitionLabel = 'Avancar para ' . (string) ($transitionOption['to_label'] ?? 'proxima etapa');
                  }
                ?>
                <option value="<?= e((string) $closeTransitionId) ?>"><?= e($closeTransitionLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="muted">Campo usado apenas quando acionar "Salvar e encerrar etapa".</p>
          </div>
        <?php elseif ($assignment !== null && $singleCloseTransition !== null): ?>
          <input type="hidden" name="close_transition_id" value="<?= e((string) ((int) ($singleCloseTransition['id'] ?? 0))) ?>">
          <div class="field field-wide">
            <p class="muted">
              Encerramento rapido disponivel para:
              <?= e((string) ($singleCloseTransition['action_label'] ?? $singleCloseTransition['transition_label'] ?? 'Proxima etapa')) ?>
            </p>
          </div>
        <?php endif; ?>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Registrar evento</button>
        <?php if ($assignment !== null && $availableTransitions !== []): ?>
          <button
            type="submit"
            class="btn btn-outline"
            name="close_step"
            value="1"
            onclick="return confirm('Salvar a evidencia e tentar encerrar a etapa atual agora?');"
          >
            Salvar e encerrar etapa
          </button>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($timeline === []): ?>
    <p class="muted">Sem eventos registrados ainda.</p>
  <?php else: ?>
    <div class="timeline-list">
      <?php foreach ($timeline as $event): ?>
        <?php
          $eventType = (string) ($event['event_type'] ?? 'evento');
          $metadata = $decodeMetadata($event['metadata'] ?? null);
          $attachments = is_array($event['attachments'] ?? null) ? $event['attachments'] : [];
          $links = is_array($event['links'] ?? null) ? $event['links'] : [];
          $rectifiesEventId = isset($metadata['rectifies_event_id']) ? (int) $metadata['rectifies_event_id'] : 0;
          $contextEventId = isset($metadata['context_event_id']) ? (int) $metadata['context_event_id'] : 0;
          $contextEventTitle = trim((string) ($metadata['context_event_title'] ?? ''));
          $eventId = (int) ($event['id'] ?? 0);
          $eventFromCode = trim((string) ($metadata['from_code'] ?? ''));
          $eventFromLabel = trim((string) ($metadata['from_label'] ?? ''));
          $eventToCode = trim((string) ($metadata['to_code'] ?? ''));
          $eventToLabel = trim((string) ($metadata['to_label'] ?? ''));
          $eventStatusCode = trim((string) ($metadata['pipeline_status_code'] ?? ($metadata['status_code'] ?? '')));
          $eventStatusLabel = trim((string) ($metadata['pipeline_status_label'] ?? ($metadata['status_label'] ?? '')));
          $eventTransitionLabel = trim((string) ($metadata['transition_label'] ?? ''));
          $resolvedFromLabel = ($eventFromCode !== '' || $eventFromLabel !== '')
              ? $resolveStatusLabel($eventFromCode, $eventFromLabel)
              : '';
          $resolvedToLabel = ($eventToCode !== '' || $eventToLabel !== '')
              ? $resolveStatusLabel($eventToCode, $eventToLabel)
              : '';
          $resolvedStatusLabel = ($eventStatusCode !== '' || $eventStatusLabel !== '')
              ? $resolveStatusLabel($eventStatusCode, $eventStatusLabel)
              : '';
          $hasPipelineBinding = str_starts_with($eventType, 'pipeline.')
              || $resolvedFromLabel !== ''
              || $resolvedToLabel !== ''
              || $resolvedStatusLabel !== '';
        ?>
        <article class="timeline-item">
          <div class="timeline-item-header">
            <div class="timeline-item-title">
              <strong><?= e((string) ($event['title'] ?? 'Evento')) ?></strong>
              <span class="badge <?= e($eventBadgeClass($eventType)) ?>"><?= e($eventTypeLabel($eventType)) ?></span>
            </div>
            <span class="muted"><?= e($formatDateTime((string) ($event['event_date'] ?? ''))) ?></span>
          </div>

          <?php if (trim((string) ($event['description'] ?? '')) !== ''): ?>
            <p class="timeline-item-description"><?= nl2br(e((string) $event['description'])) ?></p>
          <?php endif; ?>

          <?php if ($hasPipelineBinding): ?>
            <div class="timeline-stage-meta">
              <span class="badge badge-info">Fluxo BPMN</span>
              <?php if ($resolvedFromLabel !== '' && $resolvedToLabel !== ''): ?>
                <span class="muted">Etapa: <?= e($resolvedFromLabel) ?> → <?= e($resolvedToLabel) ?></span>
              <?php elseif ($resolvedStatusLabel !== ''): ?>
                <span class="muted">Etapa: <?= e($resolvedStatusLabel) ?></span>
              <?php endif; ?>
              <?php if ($eventTransitionLabel !== ''): ?>
                <span class="badge badge-neutral"><?= e($eventTransitionLabel) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($rectifiesEventId > 0): ?>
            <p class="muted">Retifica o evento #<?= e((string) $rectifiesEventId) ?> (evento original preservado).</p>
          <?php endif; ?>

          <?php if ($contextEventId > 0): ?>
            <p class="muted">
              Evidencia complementar vinculada ao evento #<?= e((string) $contextEventId) ?>
              <?php if ($contextEventTitle !== ''): ?>
                (<?= e($contextEventTitle) ?>)
              <?php endif; ?>.
            </p>
          <?php endif; ?>

          <?php if ($attachments !== []): ?>
            <div class="timeline-attachments">
              <strong>Anexos</strong>
              <ul class="attachments-list">
                <?php foreach ($attachments as $attachment): ?>
                  <li>
                    <a href="<?= e(url('/people/timeline/attachment?id=' . (int) ($attachment['id'] ?? 0) . '&person_id=' . $personId)) ?>">
                      <?= e((string) ($attachment['original_name'] ?? 'anexo')) ?>
                    </a>
                    <span class="muted">(<?= e($formatBytes((int) ($attachment['file_size'] ?? 0))) ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if ($links !== []): ?>
            <div class="timeline-attachments">
              <strong>Links</strong>
              <ul class="attachments-list">
                <?php foreach ($links as $link): ?>
                  <?php
                    $linkUrl = trim((string) ($link['url'] ?? ''));
                    $linkLabel = trim((string) ($link['label'] ?? ''));
                  ?>
                  <?php if ($linkUrl !== ''): ?>
                    <li>
                      <a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <?= e($linkLabel !== '' ? $linkLabel : $linkUrl) ?>
                      </a>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <p class="muted">Responsável: <?= e((string) ($event['created_by_name'] ?? 'Sistema')) ?></p>

          <?php if (($canManage ?? false) === true && $eventId > 0): ?>
            <div class="actions-inline timeline-item-actions">
              <a class="btn btn-outline" href="<?= e($buildProfileUrl(['tab' => 'timeline', 'timeline_context_event_id' => $eventId])) ?>#timeline-evidence-form">Adicionar evidencia desta etapa</a>
              <a class="btn btn-ghost" href="<?= e($buildProfileUrl(['tab' => 'documents', 'document_context_event_id' => $eventId])) ?>#document-upload-form">Anexar documento desta etapa</a>
            </div>

            <details class="timeline-rectify-details">
              <summary>Retificar este evento</summary>
              <form method="post" action="<?= e(url('/people/timeline/rectify')) ?>" enctype="multipart/form-data" class="timeline-rectify-form">
                <?= csrf_field() ?>
                <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                <input type="hidden" name="source_event_id" value="<?= e((string) $eventId) ?>">
                <div class="field">
                  <label for="rectification_note_<?= e((string) $eventId) ?>">Justificativa da retificação</label>
                  <textarea id="rectification_note_<?= e((string) $eventId) ?>" name="rectification_note" rows="3" minlength="3" required></textarea>
                </div>
                <div class="field">
                  <label for="rectification_attachments_<?= e((string) $eventId) ?>">Anexos da retificação</label>
                  <input id="rectification_attachments_<?= e((string) $eventId) ?>" name="attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                </div>
                <div class="field">
                  <label for="rectification_links_<?= e((string) $eventId) ?>">Links de evidência (1 por linha)</label>
                  <textarea id="rectification_links_<?= e((string) $eventId) ?>" name="evidence_links" rows="3" placeholder="https://exemplo.gov.br/documento&#10;Portal SEI | https://sei.exemplo.gov.br/consulta"></textarea>
                </div>
                <?php if ($assignment !== null && count($availableTransitions) > 1): ?>
                  <div class="field">
                    <label for="rectification_close_transition_<?= e((string) $eventId) ?>">Transição para encerrar etapa (opcional)</label>
                    <select id="rectification_close_transition_<?= e((string) $eventId) ?>" name="close_transition_id">
                      <option value="">Selecionar no momento do encerramento</option>
                      <?php foreach ($availableTransitions as $transitionOption): ?>
                        <?php
                          $closeTransitionId = (int) ($transitionOption['id'] ?? 0);
                          $closeTransitionLabel = trim((string) ($transitionOption['action_label'] ?? ''));
                          if ($closeTransitionLabel === '') {
                              $closeTransitionLabel = trim((string) ($transitionOption['transition_label'] ?? ''));
                          }
                          if ($closeTransitionLabel === '') {
                              $closeTransitionLabel = 'Avancar para ' . (string) ($transitionOption['to_label'] ?? 'proxima etapa');
                          }
                        ?>
                        <option value="<?= e((string) $closeTransitionId) ?>"><?= e($closeTransitionLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php elseif ($assignment !== null && count($availableTransitions) === 1): ?>
                  <input type="hidden" name="close_transition_id" value="<?= e((string) ((int) ($availableTransitions[0]['id'] ?? 0))) ?>">
                <?php endif; ?>
                <div class="form-actions">
                  <button type="submit" class="btn btn-ghost">Registrar retificação</button>
                  <?php if ($assignment !== null && $availableTransitions !== []): ?>
                    <button
                      type="submit"
                      class="btn btn-outline"
                      name="close_step"
                      value="1"
                      onclick="return confirm('Salvar a retificacao e tentar encerrar a etapa atual agora?');"
                    >
                      Salvar e encerrar etapa
                    </button>
                  <?php endif; ?>
                </div>
              </form>
            </details>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php
      $timelineTotal = (int) ($timelinePagination['total'] ?? 0);
      $timelinePage = (int) ($timelinePagination['page'] ?? 1);
      $timelinePerPage = max(1, (int) ($timelinePagination['per_page'] ?? 8));
      $timelinePages = max(1, (int) ($timelinePagination['pages'] ?? 1));
      $start = $timelineTotal > 0 ? (($timelinePage - 1) * $timelinePerPage) + 1 : 0;
      $end = min($timelineTotal, $timelinePage * $timelinePerPage);
    ?>
    <div class="pagination-row">
      <span class="muted">Exibindo <?= e((string) $start) ?>-<?= e((string) $end) ?> de <?= e((string) $timelineTotal) ?> eventos</span>
      <div class="pagination-links">
        <?php if ($timelinePage > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildTimelinePageUrl($timelinePage - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string) $timelinePage) ?> de <?= e((string) $timelinePages) ?></span>
        <?php if ($timelinePage < $timelinePages): ?>
          <a class="btn btn-outline" href="<?= e($buildTimelinePageUrl($timelinePage + 1)) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<div class="card" data-tab-panel="documents">
  <div class="header-row">
    <div>
      <h3>Documentos</h3>
      <p class="muted">Dossiê documental da pessoa com upload seguro e download protegido.</p>
    </div>
  </div>

  <?php if (!$canViewSensitiveDocuments): ?>
    <p class="muted">Somente documentos classificados como Publico sao exibidos para o seu perfil.</p>
  <?php endif; ?>

  <?php if ($documentItems === []): ?>
    <p class="muted">Nenhum documento registrado para esta pessoa.</p>
  <?php else: ?>
    <div class="document-list">
      <?php foreach ($documentItems as $document): ?>
        <?php $documentSensitivity = mb_strtolower(trim((string) ($document['sensitivity_level'] ?? 'public'))); ?>
        <?php
          $documentVersions = is_array($document['versions'] ?? null) ? $document['versions'] : [];
          $documentCurrentVersion = max(1, (int) ($document['current_version_number'] ?? ($documentVersions[0]['version_number'] ?? 1)));
        ?>
        <article class="document-item">
          <div class="document-item-header">
            <div class="document-title-wrap">
              <strong><?= e((string) ($document['title'] ?? 'Documento')) ?></strong>
              <span class="badge badge-neutral"><?= e((string) ($document['document_type_name'] ?? 'Tipo')) ?></span>
              <span class="badge <?= e($documentSensitivityBadgeClass($documentSensitivity)) ?>">
                <?= e($documentSensitivityLabel($documentSensitivity)) ?>
              </span>
              <span class="badge badge-info">V<?= e((string) $documentCurrentVersion) ?></span>
            </div>
            <a class="btn btn-ghost" href="<?= e(url('/people/documents/download?id=' . (int) ($document['id'] ?? 0) . '&person_id=' . $personId)) ?>">Baixar</a>
          </div>
          <p class="muted">Arquivo: <?= e((string) ($document['original_name'] ?? '-')) ?> (<?= e($formatBytes((int) ($document['file_size'] ?? 0))) ?>)</p>
          <p class="muted">Versões registradas: <?= e((string) count($documentVersions)) ?></p>
          <?php if (trim((string) ($document['reference_sei'] ?? '')) !== ''): ?>
            <p class="muted">SEI: <?= e((string) $document['reference_sei']) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['document_date'] ?? '')) !== ''): ?>
            <p class="muted">Data do documento: <?= e($formatDate((string) $document['document_date'])) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['tags'] ?? '')) !== ''): ?>
            <p class="muted">Tags: <?= e((string) $document['tags']) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($document['notes'] ?? '')) !== ''): ?>
            <p class="muted">Observações: <?= nl2br(e((string) $document['notes'])) ?></p>
          <?php endif; ?>
          <p class="muted">Enviado por: <?= e((string) ($document['uploaded_by_name'] ?? 'Sistema')) ?> em <?= e($formatDateTime((string) ($document['created_at'] ?? ''))) ?></p>

          <?php if (($canManage ?? false) === true): ?>
            <form method="post" action="<?= e(url('/people/documents/version/store')) ?>" enctype="multipart/form-data" class="document-version-form">
              <?= csrf_field() ?>
              <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
              <input type="hidden" name="document_id" value="<?= e((string) ((int) ($document['id'] ?? 0))) ?>">
              <div class="field">
                <label for="document_version_file_<?= e((string) ((int) ($document['id'] ?? 0))) ?>">Nova versão (PDF/JPG/PNG até 10MB)</label>
                <input id="document_version_file_<?= e((string) ((int) ($document['id'] ?? 0))) ?>" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-outline">Enviar nova versão</button>
              </div>
            </form>
          <?php endif; ?>

          <details class="document-version-history">
            <summary>Histórico de versões</summary>
            <?php if ($documentVersions === []): ?>
              <p class="muted">Nenhuma versão registrada.</p>
            <?php else: ?>
              <ul class="document-version-list">
                <?php foreach ($documentVersions as $version): ?>
                  <?php $versionId = (int) ($version['id'] ?? 0); ?>
                  <li class="document-version-item">
                    <div>
                      <strong>V<?= e((string) ((int) ($version['version_number'] ?? 1))) ?></strong>
                      <span class="muted"><?= e((string) ($version['original_name'] ?? '-')) ?> (<?= e($formatBytes((int) ($version['file_size'] ?? 0))) ?>)</span>
                    </div>
                    <div class="actions-inline">
                      <span class="muted">
                        <?= e((string) ($version['uploaded_by_name'] ?? 'Sistema')) ?> em <?= e($formatDateTime((string) ($version['created_at'] ?? ''))) ?>
                      </span>
                      <?php if ($versionId > 0): ?>
                        <a class="btn btn-ghost" href="<?= e(url('/people/documents/version/download?version_id=' . $versionId . '&document_id=' . (int) ($document['id'] ?? 0) . '&person_id=' . $personId)) ?>">Baixar versão</a>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </details>
        </article>
      <?php endforeach; ?>
    </div>

    <?php
      $documentsTotal = (int) ($documentsPagination['total'] ?? 0);
      $documentsPage = (int) ($documentsPagination['page'] ?? 1);
      $documentsPerPage = max(1, (int) ($documentsPagination['per_page'] ?? 8));
      $documentsPages = max(1, (int) ($documentsPagination['pages'] ?? 1));
      $documentsStart = $documentsTotal > 0 ? (($documentsPage - 1) * $documentsPerPage) + 1 : 0;
      $documentsEnd = min($documentsTotal, $documentsPage * $documentsPerPage);
    ?>
    <div class="pagination-row">
      <span class="muted">Exibindo <?= e((string) $documentsStart) ?>-<?= e((string) $documentsEnd) ?> de <?= e((string) $documentsTotal) ?> documentos</span>
      <div class="pagination-links">
        <?php if ($documentsPage > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildDocumentsPageUrl($documentsPage - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string) $documentsPage) ?> de <?= e((string) $documentsPages) ?></span>
        <?php if ($documentsPage < $documentsPages): ?>
          <a class="btn btn-outline" href="<?= e($buildDocumentsPageUrl($documentsPage + 1)) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($canManage ?? false) === true): ?>
    <details id="document-upload-form" class="document-form-toggle" <?= $documentContextEventId > 0 ? 'open' : '' ?>>
      <summary class="btn btn-outline">Inserir documento</summary>
      <form method="post" action="<?= e(url('/people/documents/store')) ?>" enctype="multipart/form-data" class="document-form">
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
        <?php if ($documentContextEventId > 0): ?>
          <input type="hidden" name="context_event_id" value="<?= e((string) $documentContextEventId) ?>">
          <div class="document-context-alert">
            <strong>Contexto da etapa ativo</strong>
            <p class="muted">
              Upload vinculado ao evento #<?= e((string) $documentContextEventId) ?>
              <?php if ($documentContextTitle !== ''): ?>
                (<?= e($documentContextTitle) ?>)
              <?php endif; ?>.
            </p>
            <?php if ($documentContextStatusCode !== '' || $documentContextStatusLabel !== ''): ?>
              <p class="muted">
                Etapa de referencia:
                <?= e($resolveStatusLabel($documentContextStatusCode, $documentContextStatusLabel)) ?>
              </p>
            <?php endif; ?>
            <?php if ($documentContextExpectedTypes !== []): ?>
              <div class="bpmn-tags-list">
                <?php foreach ($documentContextExpectedTypes as $expectedType): ?>
                  <?php $expectedTypeName = trim((string) ($expectedType['name'] ?? 'Tipo')); ?>
                  <span class="badge badge-info"><?= e($expectedTypeName) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="muted">Nao ha tipos mapeados para esta etapa. Selecione o tipo manualmente.</p>
            <?php endif; ?>
            <div class="actions-inline">
              <a class="btn btn-ghost" href="<?= e($buildProfileUrl(['tab' => 'documents'], ['document_context_event_id'])) ?>#document-upload-form">Remover contexto</a>
            </div>
          </div>
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="document_type_id">Tipo de documento</label>
            <select id="document_type_id" name="document_type_id" required>
              <option value="">Selecione...</option>
              <?php foreach ($documentTypes as $type): ?>
                <?php
                  $typeId = (int) ($type['id'] ?? 0);
                  $isSuggestedType = $documentContextEventId > 0
                      && $documentContextSuggestedTypeId > 0
                      && $typeId === $documentContextSuggestedTypeId;
                ?>
                <option value="<?= e((string) $typeId) ?>" <?= $isSuggestedType ? 'selected' : '' ?>>
                  <?= e((string) ($type['name'] ?? 'Tipo')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($documentContextEventId > 0 && $documentContextSuggestedTypeId > 0 && $documentContextSuggestedTypeName !== ''): ?>
              <small class="muted">Sugestao automatica da etapa: <?= e($documentContextSuggestedTypeName) ?>.</small>
            <?php endif; ?>
          </div>
          <div class="field">
            <label for="document_sensitivity_level">Sensibilidade</label>
            <select id="document_sensitivity_level" name="sensitivity_level" required>
              <?php foreach ($documentSensitivityOptions as $option): ?>
                <option value="<?= e((string) ($option['value'] ?? 'public')) ?>" <?= (($option['value'] ?? '') === 'public') ? 'selected' : '' ?>>
                  <?= e((string) ($option['label'] ?? 'Publico')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$canViewSensitiveDocuments): ?>
              <small class="muted">Classificacoes Restrito/Sensivel exigem permissao adicional.</small>
            <?php endif; ?>
          </div>
          <div class="field">
            <label for="document_date">Data do documento</label>
            <input id="document_date" name="document_date" type="date">
          </div>
          <div class="field">
            <label for="document_title">Título (opcional)</label>
            <input id="document_title" name="title" type="text" maxlength="190" placeholder="Ex.: Ofício 123/2026">
          </div>
          <div class="field">
            <label for="document_reference_sei">Referência SEI</label>
            <input id="document_reference_sei" name="reference_sei" type="text" maxlength="120" placeholder="00000.000000/2026-00">
          </div>
          <div class="field field-wide">
            <label for="document_tags">Tags</label>
            <input id="document_tags" name="tags" type="text" placeholder="oficio, resposta, cdo">
          </div>
          <div class="field field-wide">
            <label for="document_notes">Observações</label>
            <textarea id="document_notes" name="notes" rows="3"></textarea>
          </div>
          <div class="field field-wide">
            <label for="document_files">Arquivos (PDF/JPG/PNG até 10MB)</label>
            <div class="dropzone" data-input-id="document_files">
              <p class="dropzone-text muted">Arraste e solte arquivos aqui ou clique para selecionar.</p>
              <input id="document_files" class="dropzone-input" name="files[]" type="file" multiple required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Enviar documentos</button>
        </div>
      </form>
    </details>
  <?php endif; ?>
</div>

<div class="card" data-tab-panel="costs">
  <div class="header-row">
    <div>
      <h3>Custos previstos</h3>
      <p class="muted">Conforme <?= e($costCurrentVersionLabel) ?></p>
    </div>
    <div class="actions-inline">
      <?php if ($activeCostPlan !== null): ?>
        <span class="badge badge-info">Versão ativa: V<?= e((string) ((int) ($activeCostPlan['version_number'] ?? 0))) ?></span>
      <?php else: ?>
        <span class="badge badge-neutral">Sem versão ativa</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Total mensal equivalente</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($costSummary['monthly_total'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Total anualizado</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($costSummary['annualized_total'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Itens na versão ativa</p>
      <p class="kpi-value"><?= e((string) ((int) ($costSummary['items_count'] ?? 0))) ?></p>
    </article>
  </div>

  <?php if (($costComparison['previous_version_number'] ?? null) !== null): ?>
    <p class="muted">
      Comparação com V<?= e((string) ((int) $costComparison['previous_version_number'])) ?>:
      mensal <?= e($formatMoney((float) ($costComparison['monthly_delta'] ?? 0))) ?> |
      anualizado <?= e($formatMoney((float) ($costComparison['annualized_delta'] ?? 0))) ?>
    </p>
  <?php endif; ?>

  <?php if ($costCatalogHierarchy === []): ?>
    <p class="muted">Nenhum item de custo ativo no catálogo. Cadastre ao menos um item para lançar custos.</p>
    <?php if ($canManageCostItems): ?>
      <div class="actions-inline">
        <a class="btn btn-ghost" href="<?= e(url('/cost-items')) ?>">Gerenciar catálogo de itens de custo</a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="cost-current-table">
        <thead>
          <tr>
            <th>Categoria agregadora</th>
            <th>Periodicidade</th>
            <th>Valor no período</th>
            <th>Início da vigência</th>
            <th>Valor anualizado</th>
            <th>Valor até fim do ano</th>
            <th>Observações</th>
            <th>Detalhamento</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($costCatalogHierarchy as $group): ?>
            <?php
              $category = is_array($group['category'] ?? null) ? $group['category'] : [];
              $children = is_array($group['children'] ?? null) ? $group['children'] : [];
              $categoryId = (int) ($category['id'] ?? 0);
              if ($categoryId <= 0) {
                  continue;
              }
              $categoryName = trim((string) ($category['name'] ?? ('Categoria #' . $categoryId)));
              $categoryLinkage = $costLinkageLabel((int) ($category['linkage_code'] ?? 0), $categoryName);
              $categoryParcel = $costReimbursabilityLabel(
                  (string) ($category['reimbursability'] ?? ''),
                  (int) ($category['is_reimbursable'] ?? 0),
                  $categoryName
              );
              $categoryMacroCategory = $costMacroCategoryLabel((string) ($category['macro_category'] ?? ''));
              $categorySubcategory = trim((string) ($category['subcategory'] ?? ''));
              $categoryExpenseNature = $costExpenseNatureLabel((string) ($category['expense_nature'] ?? ''));
              $categoryPredictability = $costPredictabilityLabel((string) ($category['predictability'] ?? ''));
              $categoryPeriodicityRaw = trim((string) ($category['payment_periodicity'] ?? 'mensal'));

              $categoryRows = is_array($costActiveRowsByCatalog[$categoryId] ?? null) ? $costActiveRowsByCatalog[$categoryId] : [];
              $categoryRow = is_array($categoryRows[0] ?? null) ? $categoryRows[0] : [];

              $categoryAmount = is_numeric((string) ($categoryRow['amount'] ?? null))
                  ? (float) ($categoryRow['amount'] ?? 0)
                  : 0.0;
              $categoryStartDate = trim((string) ($categoryRow['start_date'] ?? ''));
              if ($categoryStartDate === '' || strtotime($categoryStartDate) === false) {
                  $categoryStartDate = $costProjectionStartDate;
              }
              $categoryType = trim((string) ($categoryRow['cost_type'] ?? $categoryPeriodicityRaw));
              if (!isset($costPeriodicityOptions[$categoryType])) {
                  $categoryType = isset($costPeriodicityOptions[$categoryPeriodicityRaw]) ? $categoryPeriodicityRaw : 'mensal';
              }
              $categoryNotes = trim((string) ($categoryRow['notes'] ?? ''));

              $detailedRows = [];
              foreach ($children as $childItem) {
                  $childId = (int) ($childItem['id'] ?? 0);
                  if ($childId <= 0) {
                      continue;
                  }
                  $childRows = is_array($costActiveRowsByCatalog[$childId] ?? null) ? $costActiveRowsByCatalog[$childId] : [];
                  $childRow = is_array($childRows[0] ?? null) ? $childRows[0] : [];
                  $childAmount = is_numeric((string) ($childRow['amount'] ?? null))
                      ? (float) ($childRow['amount'] ?? 0)
                      : 0.0;
                  if ($childAmount <= 0) {
                      continue;
                  }
                  $detailedRows[] = [
                      'catalog' => $childItem,
                      'row' => $childRow,
                      'amount' => $childAmount,
                  ];
              }

              $useDetailed = $detailedRows !== [];
              $displayAmount = 0.0;
              $displayAnnualized = 0.0;
              $displayYearEnd = 0.0;
              $displayStartDate = $categoryStartDate;
              $displayType = $categoryType;
              $displayNotes = $categoryNotes;

              if ($useDetailed) {
                  $displayType = 'detalhado';
                  $displayNotes = 'Detalhado em ' . count($detailedRows) . ' item(ns) filho(s).';
                  $earliestStart = null;
                  foreach ($detailedRows as $detail) {
                      $detailCatalog = is_array($detail['catalog'] ?? null) ? $detail['catalog'] : [];
                      $detailRow = is_array($detail['row'] ?? null) ? $detail['row'] : [];
                      $detailAmount = (float) ($detail['amount'] ?? 0);
                      $detailType = trim((string) ($detailRow['cost_type'] ?? ($detailCatalog['payment_periodicity'] ?? 'mensal')));
                      if (!isset($costPeriodicityOptions[$detailType])) {
                          $detailType = 'mensal';
                      }
                      $detailStart = trim((string) ($detailRow['start_date'] ?? ''));
                      if ($detailStart === '' || strtotime($detailStart) === false) {
                          $detailStart = $costProjectionStartDate;
                      }
                      if ($earliestStart === null && $detailStart !== '') {
                          $earliestStart = $detailStart;
                      } elseif ($detailStart !== '' && strtotime($detailStart) !== false && strtotime((string) $earliestStart) !== false && strtotime($detailStart) < strtotime((string) $earliestStart)) {
                          $earliestStart = $detailStart;
                      }

                      $displayAmount += $detailAmount;
                      $displayAnnualized += $costAnnualizedAmount($detailAmount, $detailType);
                      $displayYearEnd += $costYearEndAmount($detailAmount, $detailType, $detailStart);
                  }
                  $displayStartDate = $earliestStart ?? $costProjectionStartDate;
              } else {
                  $displayAmount = $categoryAmount;
                  $displayAnnualized = $costAnnualizedAmount($displayAmount, $displayType);
                  $displayYearEnd = $costYearEndAmount($displayAmount, $displayType, $displayStartDate);
              }
            ?>
            <tr>
              <td>
                <strong><?= e($categoryName) ?></strong>
                <div class="muted"><?= e($categoryMacroCategory) ?> · <?= e($categorySubcategory !== '' ? $categorySubcategory : '-') ?></div>
                <div class="muted"><?= e($categoryExpenseNature) ?> · <?= e($categoryParcel) ?> · <?= e($categoryPredictability) ?> · <?= e($categoryLinkage) ?></div>
              </td>
              <td><?= e($useDetailed ? 'Detalhado' : $costTypeLabel($displayType)) ?></td>
              <td class="is-numeric"><?= e($displayAmount > 0.0 ? $formatMoney($displayAmount) : '-') ?></td>
              <td><?= e($displayStartDate !== '' ? $formatDate($displayStartDate) : '-') ?></td>
              <td class="is-numeric"><?= e($displayAmount > 0.0 ? $formatMoney($displayAnnualized) : '-') ?></td>
              <td class="is-numeric"><?= e($displayAmount > 0.0 ? $formatMoney($displayYearEnd) : '-') ?></td>
              <td><?= e($displayNotes !== '' ? $displayNotes : '-') ?></td>
              <td>
                <?php if ($children !== []): ?>
                  <button type="button" class="btn btn-ghost" data-cost-expand-toggle="planned-<?= e((string) $categoryId) ?>" aria-expanded="false">Expandir filhos</button>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($children !== []): ?>
              <tr id="cost-expand-planned-<?= e((string) $categoryId) ?>" class="cost-expand-row" hidden>
                <td colspan="8">
                  <div class="table-wrap">
                    <table class="cost-children-table">
                      <thead>
                        <tr>
                          <th>Item filho</th>
                          <th>Periodicidade</th>
                          <th>Valor no período</th>
                          <th>Início</th>
                          <th>Anualizado</th>
                          <th>Até fim do ano</th>
                          <th>Observações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($children as $childItem): ?>
                          <?php
                            $childId = (int) ($childItem['id'] ?? 0);
                            if ($childId <= 0) {
                                continue;
                            }
                            $childName = trim((string) ($childItem['name'] ?? ('Item #' . $childId)));
                            $childRows = is_array($costActiveRowsByCatalog[$childId] ?? null) ? $costActiveRowsByCatalog[$childId] : [];
                            $childRow = is_array($childRows[0] ?? null) ? $childRows[0] : [];
                            $childAmount = is_numeric((string) ($childRow['amount'] ?? null))
                                ? (float) ($childRow['amount'] ?? 0)
                                : 0.0;
                            $childType = trim((string) ($childRow['cost_type'] ?? ($childItem['payment_periodicity'] ?? 'mensal')));
                            if (!isset($costPeriodicityOptions[$childType])) {
                                $childType = 'mensal';
                            }
                            $childStartDate = trim((string) ($childRow['start_date'] ?? ''));
                            if ($childStartDate === '' || strtotime($childStartDate) === false) {
                                $childStartDate = $costProjectionStartDate;
                            }
                            $childNotes = trim((string) ($childRow['notes'] ?? ''));
                            $childAnnualized = $costAnnualizedAmount($childAmount, $childType);
                            $childYearEnd = $costYearEndAmount($childAmount, $childType, $childStartDate);
                          ?>
                          <tr>
                            <td><?= e((string) ((int) ($childItem['cost_code'] ?? 0))) ?> - <?= e($childName) ?></td>
                            <td><?= e($costTypeLabel($childType)) ?></td>
                            <td class="is-numeric"><?= e($childAmount > 0.0 ? $formatMoney($childAmount) : '-') ?></td>
                            <td><?= e($childStartDate !== '' ? $formatDate($childStartDate) : '-') ?></td>
                            <td class="is-numeric"><?= e($childAmount > 0.0 ? $formatMoney($childAnnualized) : '-') ?></td>
                            <td class="is-numeric"><?= e($childAmount > 0.0 ? $formatMoney($childYearEnd) : '-') ?></td>
                            <td><?= e($childNotes !== '' ? $childNotes : '-') ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (($canManage ?? false) === true && $costCatalogHierarchy !== []): ?>
    <details class="cost-edit-mode">
      <summary class="btn btn-outline">Ajustar/alterar e gerar nova versão de custos</summary>
      <form
        method="post"
        action="<?= e(url('/people/costs/item/store')) ?>"
        class="cost-batch-form"
        data-cost-batch-form
        data-current-year="<?= e((string) $costProjectionCurrentYear) ?>"
        data-planned-start="<?= e($costProjectionStartDate) ?>"
      >
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">

        <div class="cost-batch-meta">
          <div class="field cost-readonly-field">
            <label for="cost_next_version_label">Rótulo automático da nova versão</label>
            <input id="cost_next_version_label" type="text" value="<?= e($costSuggestedVersionLabel) ?>" readonly>
          </div>
        </div>

        <div class="table-wrap">
          <table class="cost-batch-table">
            <colgroup>
              <col class="cost-col-item">
              <col class="cost-col-periodicity">
              <col class="cost-col-amount">
              <col class="cost-col-start">
              <col class="cost-col-annualized">
              <col class="cost-col-year-end">
              <col class="cost-col-notes">
              <col class="cost-col-details">
            </colgroup>
            <thead>
              <tr>
                <th>Categoria agregadora</th>
                <th>Periodicidade</th>
                <th>Valor no período</th>
                <th>Início da vigência</th>
                <th>Valor anualizado</th>
                <th>Valor até fim do ano</th>
                <th>Observações</th>
                <th>Detalhamento</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($costCatalogHierarchy as $group): ?>
                <?php
                  $category = is_array($group['category'] ?? null) ? $group['category'] : [];
                  $children = is_array($group['children'] ?? null) ? $group['children'] : [];
                  $categoryId = (int) ($category['id'] ?? 0);
                  if ($categoryId <= 0) {
                      continue;
                  }
                  $categoryName = trim((string) ($category['name'] ?? ('Categoria #' . $categoryId)));
                  $categoryLinkage = $costLinkageLabel((int) ($category['linkage_code'] ?? 0), $categoryName);
                  $categoryParcel = $costReimbursabilityLabel(
                      (string) ($category['reimbursability'] ?? ''),
                      (int) ($category['is_reimbursable'] ?? 0),
                      $categoryName
                  );
                  $categoryMacroCategory = $costMacroCategoryLabel((string) ($category['macro_category'] ?? ''));
                  $categorySubcategory = trim((string) ($category['subcategory'] ?? ''));
                  $categoryExpenseNature = $costExpenseNatureLabel((string) ($category['expense_nature'] ?? ''));
                  $categoryPredictability = $costPredictabilityLabel((string) ($category['predictability'] ?? ''));
                  $categoryPeriodicityRaw = trim((string) ($category['payment_periodicity'] ?? 'mensal'));
                  $categoryRows = is_array($costActiveRowsByCatalog[$categoryId] ?? null) ? $costActiveRowsByCatalog[$categoryId] : [];
                  $categoryRow = is_array($categoryRows[0] ?? null) ? $categoryRows[0] : [];
                  $rawAmount = $categoryRow['amount'] ?? null;
                  $rowAmount = (is_numeric((string) $rawAmount) && (float) $rawAmount > 0.0)
                      ? number_format((float) $rawAmount, 2, '.', '')
                      : '';
                  $rowStartDate = trim((string) ($categoryRow['start_date'] ?? ''));
                  if ($rowStartDate === '' || strtotime($rowStartDate) === false) {
                      $rowStartDate = $costProjectionStartDate;
                  }
                  $rowNotes = trim((string) ($categoryRow['notes'] ?? ''));
                  $selectedType = trim((string) ($categoryRow['cost_type'] ?? $categoryPeriodicityRaw));
                  if (!isset($costPeriodicityOptions[$selectedType])) {
                      $selectedType = isset($costPeriodicityOptions[$categoryPeriodicityRaw]) ? $categoryPeriodicityRaw : 'mensal';
                  }
                ?>
                <tr data-cost-row data-cost-level="aggregator" data-cost-id="<?= e((string) $categoryId) ?>">
                  <td>
                    <strong><?= e($categoryName) ?></strong>
                    <div class="muted"><?= e($categoryMacroCategory) ?> · <?= e($categorySubcategory !== '' ? $categorySubcategory : '-') ?></div>
                    <div class="muted"><?= e($categoryExpenseNature) ?> · <?= e($categoryParcel) ?> · <?= e($categoryPredictability) ?> · <?= e($categoryLinkage) ?></div>
                  </td>
                  <td>
                    <select name="items[<?= e((string) $categoryId) ?>][cost_type]" data-cost-type data-cost-editable>
                      <?php foreach ($costPeriodicityOptions as $optionValue => $optionLabel): ?>
                        <option value="<?= e($optionValue) ?>" <?= $optionValue === $selectedType ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      name="items[<?= e((string) $categoryId) ?>][amount]"
                      value="<?= e($rowAmount) ?>"
                      placeholder="<?= e(match ($selectedType) {
                          'anual' => 'Ex.: 18000,00/ano',
                          'eventual' => 'Ex.: 5000,00 (eventual)',
                          'unico' => 'Ex.: 5000,00 (unico legado)',
                          default => 'Ex.: 1500,00/mes',
                      }) ?>"
                      inputmode="decimal"
                      autocomplete="off"
                      data-cost-amount
                      data-cost-editable
                    >
                  </td>
                  <td>
                    <input
                      type="date"
                      name="items[<?= e((string) $categoryId) ?>][start_date]"
                      value="<?= e($rowStartDate) ?>"
                      title="Data inicial de vigencia do custo"
                      autocomplete="off"
                      data-cost-start-date
                      data-cost-editable
                    >
                  </td>
                  <td class="is-numeric" data-cost-annualized>R$ 0,00</td>
                  <td class="is-numeric" data-cost-year-end>R$ 0,00</td>
                  <td>
                    <input
                      type="text"
                      maxlength="500"
                      name="items[<?= e((string) $categoryId) ?>][notes]"
                      value="<?= e($rowNotes) ?>"
                      placeholder="Ex.: reajuste previsto para o periodo"
                      autocomplete="off"
                      data-cost-notes
                      data-cost-editable
                    >
                  </td>
                  <td>
                    <?php if ($children !== []): ?>
                      <button type="button" class="btn btn-ghost" data-cost-expand-toggle="batch-<?= e((string) $categoryId) ?>" aria-expanded="false">Expandir filhos</button>
                    <?php else: ?>
                      <span class="muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ($children !== []): ?>
                  <tr id="cost-expand-batch-<?= e((string) $categoryId) ?>" class="cost-expand-row" hidden>
                    <td colspan="8">
                      <div class="table-wrap">
                        <table class="cost-children-table">
                          <thead>
                            <tr>
                              <th>Item filho</th>
                              <th>Periodicidade</th>
                              <th>Valor no período</th>
                              <th>Início da vigência</th>
                              <th>Valor anualizado</th>
                              <th>Valor até fim do ano</th>
                              <th>Observações</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($children as $childItem): ?>
                              <?php
                                $childId = (int) ($childItem['id'] ?? 0);
                                if ($childId <= 0) {
                                    continue;
                                }
                                $childName = trim((string) ($childItem['name'] ?? ('Item #' . $childId)));
                                $childRows = is_array($costActiveRowsByCatalog[$childId] ?? null) ? $costActiveRowsByCatalog[$childId] : [];
                                $childRow = is_array($childRows[0] ?? null) ? $childRows[0] : [];
                                $childRawAmount = $childRow['amount'] ?? null;
                                $childAmount = (is_numeric((string) $childRawAmount) && (float) $childRawAmount > 0.0)
                                    ? number_format((float) $childRawAmount, 2, '.', '')
                                    : '';
                                $childStartDate = trim((string) ($childRow['start_date'] ?? ''));
                                if ($childStartDate === '' || strtotime($childStartDate) === false) {
                                    $childStartDate = $costProjectionStartDate;
                                }
                                $childNotes = trim((string) ($childRow['notes'] ?? ''));
                                $childType = trim((string) ($childRow['cost_type'] ?? ($childItem['payment_periodicity'] ?? 'mensal')));
                                if (!isset($costPeriodicityOptions[$childType])) {
                                    $childType = 'mensal';
                                }
                              ?>
                              <tr data-cost-row data-cost-level="child" data-cost-id="<?= e((string) $childId) ?>" data-parent-id="<?= e((string) $categoryId) ?>">
                                <td>
                                  <strong><?= e((string) ((int) ($childItem['cost_code'] ?? 0))) ?> - <?= e($childName) ?></strong>
                                </td>
                                <td>
                                  <select name="items[<?= e((string) $childId) ?>][cost_type]" data-cost-type data-cost-editable>
                                    <?php foreach ($costPeriodicityOptions as $optionValue => $optionLabel): ?>
                                      <option value="<?= e($optionValue) ?>" <?= $optionValue === $childType ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                </td>
                                <td>
                                  <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="items[<?= e((string) $childId) ?>][amount]"
                                    value="<?= e($childAmount) ?>"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    data-cost-amount
                                    data-cost-editable
                                  >
                                </td>
                                <td>
                                  <input
                                    type="date"
                                    name="items[<?= e((string) $childId) ?>][start_date]"
                                    value="<?= e($childStartDate) ?>"
                                    autocomplete="off"
                                    data-cost-start-date
                                    data-cost-editable
                                  >
                                </td>
                                <td class="is-numeric" data-cost-annualized>R$ 0,00</td>
                                <td class="is-numeric" data-cost-year-end>R$ 0,00</td>
                                <td>
                                  <input
                                    type="text"
                                    maxlength="500"
                                    name="items[<?= e((string) $childId) ?>][notes]"
                                    value="<?= e($childNotes) ?>"
                                    autocomplete="off"
                                    data-cost-notes
                                    data-cost-editable
                                  >
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2"><strong>Totais da nova versão</strong></td>
                <td class="is-numeric" data-cost-total-period>R$ 0,00</td>
                <td></td>
                <td class="is-numeric" data-cost-total-annualized>R$ 0,00</td>
                <td class="is-numeric" data-cost-total-year-end>R$ 0,00</td>
                <td class="is-numeric" data-cost-total-items>0 item(ns)</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="cost-batch-actions">
          <?php if ($canManageCostItems): ?>
            <a class="btn btn-ghost" href="<?= e(url('/cost-items')) ?>">Gerenciar catálogo</a>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">Salvar nova versão</button>
        </div>
        <p class="muted cost-batch-shortcuts">
          Atalhos: <strong>Ctrl/Cmd + S</strong> salva a nova versão, <strong>Enter</strong> vai para o próximo campo, <strong>Shift + Enter</strong> volta para o campo anterior.
          Se houver valores em itens filhos, o valor da categoria agregadora correspondente e ignorado.
        </p>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($costVersions !== []): ?>
    <div class="cost-versions">
      <h4>Histórico de versões</h4>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Versão</th>
              <th>Rótulo</th>
              <th>Itens</th>
              <th>Total mensal</th>
              <th>Total anualizado</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($costVersions as $version): ?>
              <?php
                $versionPlanId = (int) ($version['id'] ?? 0);
                $versionItemsList = is_array($costVersionItems[$versionPlanId] ?? null) ? $costVersionItems[$versionPlanId] : [];
                $versionNumber = (int) ($version['version_number'] ?? 0);
              ?>
              <tr>
                <td>
                  <?php if ($versionPlanId > 0): ?>
                    <button
                      type="button"
                      class="btn btn-ghost cost-version-toggle"
                      data-cost-version-toggle="<?= e((string) $versionPlanId) ?>"
                      aria-controls="cost-version-detail-<?= e((string) $versionPlanId) ?>"
                      aria-expanded="false"
                    >
                      V<?= e((string) $versionNumber) ?>
                    </button>
                  <?php else: ?>
                    V<?= e((string) $versionNumber) ?>
                  <?php endif; ?>
                </td>
                <td><?= e((string) ($version['label'] ?? '-')) ?></td>
                <td><?= e((string) ((int) ($version['items_count'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($version['monthly_total'] ?? 0))) ?></td>
                <td><?= e($formatMoney((float) ($version['annualized_total'] ?? 0))) ?></td>
                <td>
                  <?php if ((int) ($version['is_active'] ?? 0) === 1): ?>
                    <span class="badge badge-info">Ativa</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Histórica</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php if ($versionPlanId > 0): ?>
                <tr id="cost-version-detail-<?= e((string) $versionPlanId) ?>" class="cost-version-detail-row" hidden>
                  <td colspan="6">
                    <div class="cost-version-detail">
                      <p class="muted">Detalhamento da versão V<?= e((string) $versionNumber) ?>.</p>
                      <?php if ($versionItemsList === []): ?>
                        <p class="muted">Nenhum item registrado nesta versão.</p>
                      <?php else: ?>
                        <div class="table-wrap">
                          <table>
                            <thead>
                              <tr>
                                <th>Item</th>
                                <th>Vínculo</th>
                                <th>Parcela</th>
                                <th>Tipo</th>
                                <th>Valor informado</th>
                                <th>Início</th>
                                <th>Responsável</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($versionItemsList as $versionItem): ?>
                                <?php
                                  $versionItemName = (string) ($versionItem['item_name'] ?? '');
                                  $versionItemLinkage = $costLinkageLabel($versionItem['catalog_linkage_code'] ?? null, $versionItemName);
                                  $versionItemParcel = $costReimbursabilityLabel(
                                      (string) ($versionItem['catalog_reimbursability'] ?? ''),
                                      $versionItem['catalog_is_reimbursable'] ?? null,
                                      $versionItemName
                                  );
                                  $versionItemMacro = $costMacroCategoryLabel((string) ($versionItem['catalog_macro_category'] ?? ''));
                                  $versionItemSubcategory = trim((string) ($versionItem['catalog_subcategory'] ?? ''));
                                  $versionItemExpenseNature = $costExpenseNatureLabel((string) ($versionItem['catalog_expense_nature'] ?? ''));
                                  $versionItemPredictability = $costPredictabilityLabel((string) ($versionItem['catalog_predictability'] ?? ''));
                                ?>
                                <tr>
                                  <td>
                                    <strong><?= e($versionItemName !== '' ? $versionItemName : '-') ?></strong>
                                    <div class="muted"><?= e($versionItemMacro) ?> · <?= e($versionItemSubcategory !== '' ? $versionItemSubcategory : '-') ?></div>
                                    <div class="muted"><?= e($versionItemExpenseNature) ?> · <?= e($versionItemPredictability) ?></div>
                                    <?php if (trim((string) ($versionItem['notes'] ?? '')) !== ''): ?>
                                      <div class="muted"><?= e((string) ($versionItem['notes'] ?? '')) ?></div>
                                    <?php endif; ?>
                                  </td>
                                  <td><?= e($versionItemLinkage) ?></td>
                                  <td><?= e($versionItemParcel) ?></td>
                                  <td><?= e($costTypeLabel((string) ($versionItem['cost_type'] ?? ''))) ?></td>
                                  <td><?= e($formatMoney((float) ($versionItem['amount'] ?? 0))) ?></td>
                                  <td><?= e($formatDate((string) ($versionItem['start_date'] ?? ''))) ?></td>
                                  <td><?= e((string) ($versionItem['created_by_name'] ?? 'Sistema')) ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card" data-tab-panel="conciliation">
  <div class="header-row">
    <div>
      <h3>Conciliação previsto x real</h3>
      <p class="muted">Comparativo por competência entre custos previstos (versão ativa) e reembolsos reais.</p>
    </div>
    <?php if ($activeCostPlan !== null): ?>
      <span class="badge badge-info">Base prevista: V<?= e((string) ((int) ($activeCostPlan['version_number'] ?? 0))) ?></span>
    <?php endif; ?>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Previsto (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['expected_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Real lançado (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['actual_posted_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Real pago (mês atual)</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($conciliationSummary['actual_paid_current'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Desvio lançado (mês atual)</p>
      <p class="kpi-value <?= e($deviationClass($conciliationSummary['deviation_posted_current'] ?? 0)) ?>">
        <?= e($formatSignedMoney((float) ($conciliationSummary['deviation_posted_current'] ?? 0))) ?>
      </p>
    </article>
  </div>

  <?php if ($conciliationRows === []): ?>
    <p class="muted">Sem dados suficientes para conciliação por competência.</p>
  <?php else: ?>
    <p class="muted">
      Janela analisada: <?= e((string) ((int) ($conciliationSummary['months_analyzed'] ?? 0))) ?> competência(s) |
      Desvio acumulado (lançado): <span class="<?= e($deviationClass($conciliationSummary['deviation_posted_window_total'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($conciliationSummary['deviation_posted_window_total'] ?? 0))) ?></span>
    </p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Competência</th>
            <th>Previsto</th>
            <th>Real lançado</th>
            <th>Real pago</th>
            <th>Desvio lançado</th>
            <th>Desvio pago</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($conciliationRows as $row): ?>
            <tr>
              <td><?= e($formatDate((string) ($row['competence'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($row['expected'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['actual_posted'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($row['actual_paid'] ?? 0))) ?></td>
              <td class="<?= e($deviationClass($row['deviation_posted'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($row['deviation_posted'] ?? 0))) ?></td>
              <td class="<?= e($deviationClass($row['deviation_paid'] ?? 0)) ?>"><?= e($formatSignedMoney((float) ($row['deviation_paid'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" data-tab-panel="finance">
  <div class="header-row">
    <div>
      <h3>Reembolsos reais</h3>
      <p class="muted">Controle financeiro de boletos, pagamentos e ajustes executados.</p>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Pendente</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['pending_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['pending_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Pago</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['paid_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['paid_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Vencido</p>
      <p class="kpi-value"><?= e($formatMoney((float) ($reimbursementSummary['overdue_total'] ?? 0))) ?></p>
      <p class="muted"><?= e((string) ((int) ($reimbursementSummary['overdue_count'] ?? 0))) ?> lançamento(s)</p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Total de lançamentos</p>
      <p class="kpi-value"><?= e((string) ((int) ($reimbursementSummary['total_entries'] ?? 0))) ?></p>
      <p class="muted">Boletos: <?= e((string) ((int) ($reimbursementSummary['boletos_count'] ?? 0))) ?> | Pagamentos: <?= e((string) ((int) ($reimbursementSummary['payments_count'] ?? 0))) ?></p>
    </article>
  </div>

  <?php if ($reimbursementItems === []): ?>
    <p class="muted">Nenhum lançamento financeiro registrado para esta pessoa.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Item de custo</th>
            <th>Título</th>
            <th>Competência</th>
            <th>Valor</th>
            <th>Status</th>
            <th>Vencimento</th>
            <th>Pago em</th>
            <th>Responsável</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reimbursementItems as $entry): ?>
            <?php
              $entryStatus = (string) ($entry['status'] ?? '');
              $dueDateRaw = (string) ($entry['due_date'] ?? '');
              $isOverdue = $entryStatus === 'pendente'
                  && trim($dueDateRaw) !== ''
                  && strtotime($dueDateRaw) !== false
                  && strtotime($dueDateRaw) < strtotime(date('Y-m-d'));
              $entryCalculation = $decodeMetadata($entry['calculation_memory'] ?? null);
              $hasCalculationMemory = $entryCalculation !== [];
              $canMarkAsPaid = (($canManage ?? false) === true)
                  && $entryStatus !== 'pago'
                  && $entryStatus !== 'cancelado';
            ?>
            <tr>
              <td><?= e($reimbursementTypeLabel((string) ($entry['entry_type'] ?? ''))) ?></td>
              <td>
                <?php
                  $entryCatalogName = trim((string) ($entry['catalog_name'] ?? ''));
                  $entryCatalogCode = (int) ($entry['catalog_cost_code'] ?? 0);
                  $entryCatalogSubcategory = trim((string) ($entry['catalog_subcategory'] ?? ''));
                  $entryCatalogMacro = $costMacroCategoryLabel((string) ($entry['catalog_macro_category'] ?? ''));
                  $entryCatalogKind = (int) ($entry['catalog_is_aggregator'] ?? 0) === 1 ? 'Categoria agregadora' : 'Item filho';
                ?>
                <?php if ($entryCatalogName !== ''): ?>
                  <strong><?= e((string) $entryCatalogCode) ?> - <?= e($entryCatalogName) ?></strong>
                  <div class="muted"><?= e($entryCatalogKind) ?> · <?= e($entryCatalogMacro) ?> · <?= e($entryCatalogSubcategory !== '' ? $entryCatalogSubcategory : '-') ?></div>
                <?php else: ?>
                  <span class="muted">Nao vinculado</span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e((string) ($entry['title'] ?? '-')) ?></strong>
                <?php if (trim((string) ($entry['notes'] ?? '')) !== ''): ?>
                  <div class="muted"><?= nl2br(e((string) $entry['notes'])) ?></div>
                <?php endif; ?>
                <?php if ($hasCalculationMemory): ?>
                  <?php
                    $components = is_array($entryCalculation['components'] ?? null) ? $entryCalculation['components'] : [];
                    $base = (float) ($components['base'] ?? 0);
                    $transporte = (float) ($components['transporte'] ?? 0);
                    $hospedagem = (float) ($components['hospedagem'] ?? 0);
                    $alimentacao = (float) ($components['alimentacao'] ?? 0);
                    $outros = (float) ($components['outros'] ?? 0);
                    $desconto = (float) ($components['desconto'] ?? 0);
                    $subtotalMem = (float) ($entryCalculation['subtotal'] ?? 0);
                    $adjustmentPercentMem = (float) ($entryCalculation['adjustment_percent'] ?? 0);
                    $adjustmentAmountMem = (float) ($entryCalculation['adjustment_amount'] ?? 0);
                    $totalMem = (float) ($entryCalculation['total'] ?? 0);
                  ?>
                  <details style="margin-top:8px;">
                    <summary>Memória de cálculo</summary>
                    <div class="muted">
                      Base <?= e($formatMoney($base)) ?> + Transporte <?= e($formatMoney($transporte)) ?> + Hospedagem <?= e($formatMoney($hospedagem)) ?> + Alimentação <?= e($formatMoney($alimentacao)) ?> + Outros <?= e($formatMoney($outros)) ?>
                    </div>
                    <div class="muted">
                      Subtotal <?= e($formatMoney($subtotalMem)) ?> | Ajuste <?= e($formatPercent($adjustmentPercentMem)) ?> (<?= e($formatMoney($adjustmentAmountMem)) ?>) | Desconto <?= e($formatMoney($desconto)) ?>
                    </div>
                    <div class="muted">
                      Total calculado: <strong><?= e($formatMoney($totalMem)) ?></strong>
                    </div>
                  </details>
                <?php endif; ?>
              </td>
              <td><?= e($formatDate((string) ($entry['reference_month'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($entry['amount'] ?? 0))) ?></td>
              <td>
                <span class="badge <?= e($reimbursementStatusClass($entryStatus, $isOverdue)) ?>">
                  <?= e($reimbursementStatusLabel($entryStatus, $isOverdue)) ?>
                </span>
              </td>
              <td><?= e($formatDate($dueDateRaw)) ?></td>
              <td><?= e($formatDateTime((string) ($entry['paid_at'] ?? ''))) ?></td>
              <td><?= e((string) ($entry['created_by_name'] ?? 'Sistema')) ?></td>
              <td class="actions-cell">
                <?php if ($canMarkAsPaid): ?>
                  <form method="post" action="<?= e(url('/people/reimbursements/mark-paid')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                    <input type="hidden" name="entry_id" value="<?= e((string) ((int) ($entry['id'] ?? 0))) ?>">
                    <input type="hidden" name="paid_at" value="<?= e(date('Y-m-d')) ?>">
                    <button type="submit" class="btn btn-ghost">Marcar como pago</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (($canManage ?? false) === true): ?>
    <details class="reimbursement-edit-mode">
      <summary class="btn btn-outline">Adicionar lançamentos reais em lote (10 categorias)</summary>
      <form method="post" action="<?= e(url('/people/reimbursements/store')) ?>" class="reimbursement-batch-form" data-reimbursement-batch-form>
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
        <input type="hidden" name="entry_mode" value="batch_table">
        <div class="form-grid">
          <div class="field">
            <label for="reimbursement_batch_entry_type">Tipo</label>
            <select id="reimbursement_batch_entry_type" name="entry_type">
              <option value="boleto">Boleto</option>
              <option value="pagamento">Pagamento</option>
              <option value="ajuste">Ajuste</option>
            </select>
          </div>
          <div class="field">
            <label for="reimbursement_batch_status">Status</label>
            <select id="reimbursement_batch_status" name="status">
              <option value="pendente">Pendente</option>
              <option value="pago">Pago</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div class="field">
            <label for="reimbursement_batch_reference_month">Competência</label>
            <input id="reimbursement_batch_reference_month" name="reference_month" type="month">
          </div>
          <div class="field">
            <label for="reimbursement_batch_due_date">Vencimento</label>
            <input id="reimbursement_batch_due_date" name="due_date" type="date">
          </div>
          <div class="field">
            <label for="reimbursement_batch_paid_at">Data do pagamento</label>
            <input id="reimbursement_batch_paid_at" name="paid_at" type="date">
          </div>
        </div>

        <div class="table-wrap">
          <table class="reimbursement-batch-table">
            <thead>
              <tr>
                <th>Categoria agregadora</th>
                <th>Valor</th>
                <th>Observações</th>
                <th>Detalhamento</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($costCatalogHierarchy as $group): ?>
                <?php
                  $category = is_array($group['category'] ?? null) ? $group['category'] : [];
                  $children = is_array($group['children'] ?? null) ? $group['children'] : [];
                  $categoryId = (int) ($category['id'] ?? 0);
                  if ($categoryId <= 0) {
                      continue;
                  }
                  $categoryName = trim((string) ($category['name'] ?? ('Categoria #' . $categoryId)));
                  $categoryMacro = $costMacroCategoryLabel((string) ($category['macro_category'] ?? ''));
                  $categoryCode = (int) ($category['cost_code'] ?? 0);
                ?>
                <tr data-reimbursement-row data-level="aggregator" data-id="<?= e((string) $categoryId) ?>">
                  <td>
                    <strong><?= e((string) $categoryCode) ?> - <?= e($categoryName) ?></strong>
                    <div class="muted"><?= e($categoryMacro) ?></div>
                  </td>
                  <td>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      name="batch_items[<?= e((string) $categoryId) ?>][amount]"
                      placeholder="0,00"
                      inputmode="decimal"
                      autocomplete="off"
                      data-reimbursement-batch-amount
                    >
                  </td>
                  <td>
                    <input
                      type="text"
                      maxlength="500"
                      name="batch_items[<?= e((string) $categoryId) ?>][notes]"
                      placeholder="Ex.: lançamento consolidado da categoria"
                      autocomplete="off"
                    >
                  </td>
                  <td>
                    <?php if ($children !== []): ?>
                      <button type="button" class="btn btn-ghost" data-cost-expand-toggle="effective-<?= e((string) $categoryId) ?>" aria-expanded="false">Expandir filhos</button>
                    <?php else: ?>
                      <span class="muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ($children !== []): ?>
                  <tr id="cost-expand-effective-<?= e((string) $categoryId) ?>" class="cost-expand-row" hidden>
                    <td colspan="4">
                      <div class="table-wrap">
                        <table class="cost-children-table">
                          <thead>
                            <tr>
                              <th>Item filho</th>
                              <th>Valor</th>
                              <th>Observações</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($children as $childItem): ?>
                              <?php
                                $childId = (int) ($childItem['id'] ?? 0);
                                if ($childId <= 0) {
                                    continue;
                                }
                                $childName = trim((string) ($childItem['name'] ?? ('Item #' . $childId)));
                              ?>
                              <tr data-reimbursement-row data-level="child" data-id="<?= e((string) $childId) ?>" data-parent-id="<?= e((string) $categoryId) ?>">
                                <td>
                                  <strong><?= e((string) ((int) ($childItem['cost_code'] ?? 0))) ?> - <?= e($childName) ?></strong>
                                </td>
                                <td>
                                  <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="batch_items[<?= e((string) $childId) ?>][amount]"
                                    placeholder="0,00"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    data-reimbursement-batch-amount
                                  >
                                </td>
                                <td>
                                  <input
                                    type="text"
                                    maxlength="500"
                                    name="batch_items[<?= e((string) $childId) ?>][notes]"
                                    placeholder="Ex.: detalhamento do filho"
                                    autocomplete="off"
                                  >
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td><strong>Total em lote</strong></td>
                <td class="is-numeric" data-reimbursement-batch-total>R$ 0,00</td>
                <td class="is-numeric" data-reimbursement-batch-count>0 item(ns)</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Registrar lançamentos em lote</button>
        </div>
        <p class="muted cost-batch-shortcuts">
          Preencha apenas as 10 categorias para visão macro, ou expanda e informe itens filhos para detalhamento.
          Quando filhos tiverem valor, o valor da categoria agregadora correspondente será ignorado.
        </p>
      </form>
    </details>

    <details class="reimbursement-edit-mode">
      <summary class="btn btn-outline">Adicionar lançamento unitário (manual/calculadora)</summary>
      <form method="post" action="<?= e(url('/people/reimbursements/store')) ?>" class="reimbursement-form">
        <?= csrf_field() ?>
        <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
        <div class="form-grid">
          <div class="field">
            <label for="reimbursement_entry_type">Tipo</label>
            <select id="reimbursement_entry_type" name="entry_type">
              <option value="boleto">Boleto</option>
              <option value="pagamento">Pagamento</option>
              <option value="ajuste">Ajuste</option>
            </select>
          </div>
          <div class="field">
            <label for="reimbursement_status">Status</label>
            <select id="reimbursement_status" name="status">
              <option value="pendente">Pendente</option>
              <option value="pago">Pago</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div class="field field-wide">
            <label for="reimbursement_cost_item_catalog_id">Item de custo (opcional)</label>
            <select id="reimbursement_cost_item_catalog_id" name="cost_item_catalog_id">
              <option value="0">Sem vinculo com catalogo</option>
              <?php foreach ($costCatalogHierarchy as $group): ?>
                <?php
                  $category = is_array($group['category'] ?? null) ? $group['category'] : [];
                  $children = is_array($group['children'] ?? null) ? $group['children'] : [];
                  $categoryId = (int) ($category['id'] ?? 0);
                ?>
                <?php if ($categoryId > 0): ?>
                  <option value="<?= e((string) $categoryId) ?>">
                    [CAT] <?= e((string) ((int) ($category['cost_code'] ?? 0))) ?> - <?= e((string) ($category['name'] ?? 'Categoria')) ?>
                  </option>
                <?php endif; ?>
                <?php foreach ($children as $childItem): ?>
                  <?php $childId = (int) ($childItem['id'] ?? 0); ?>
                  <?php if ($childId > 0): ?>
                    <option value="<?= e((string) $childId) ?>">
                      &nbsp;&nbsp;[FILHO] <?= e((string) ((int) ($childItem['cost_code'] ?? 0))) ?> - <?= e((string) ($childItem['name'] ?? 'Item')) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field-wide">
            <label for="reimbursement_title">Título do lançamento</label>
            <input id="reimbursement_title" name="title" type="text" minlength="3" maxlength="190" required placeholder="Ex.: Boleto órgão de origem - março/2026">
          </div>
          <div class="field field-wide">
            <label for="reimbursement_use_calculator" style="display:flex; align-items:center; gap:8px;">
              <input id="reimbursement_use_calculator" name="use_calculator" type="checkbox" value="1">
              Usar calculadora automática com memória de cálculo
            </label>
            <p class="muted">Fórmula: (Base + Transporte + Hospedagem + Alimentação + Outros) + Ajuste - Desconto.</p>
          </div>
          <div class="field">
            <label for="reimbursement_calc_base">Base</label>
            <input id="reimbursement_calc_base" name="calc_base_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_transport">Transporte</label>
            <input id="reimbursement_calc_transport" name="calc_transport_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_lodging">Hospedagem</label>
            <input id="reimbursement_calc_lodging" name="calc_lodging_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_food">Alimentação</label>
            <input id="reimbursement_calc_food" name="calc_food_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_other">Outros</label>
            <input id="reimbursement_calc_other" name="calc_other_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_adjustment">Ajuste (%)</label>
            <input id="reimbursement_calc_adjustment" name="calc_adjustment_percent" type="number" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_calc_discount">Desconto</label>
            <input id="reimbursement_calc_discount" name="calc_discount_amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field field-wide">
            <p class="muted">Total calculado: <strong id="reimbursement_calc_total_preview">R$ 0,00</strong></p>
          </div>
          <div class="field">
            <label for="reimbursement_amount">Valor</label>
            <input id="reimbursement_amount" name="amount" type="number" min="0" step="0.01" placeholder="0,00">
          </div>
          <div class="field">
            <label for="reimbursement_reference_month">Competência</label>
            <input id="reimbursement_reference_month" name="reference_month" type="month">
          </div>
          <div class="field">
            <label for="reimbursement_due_date">Vencimento</label>
            <input id="reimbursement_due_date" name="due_date" type="date">
          </div>
          <div class="field">
            <label for="reimbursement_paid_at">Data do pagamento</label>
            <input id="reimbursement_paid_at" name="paid_at" type="date">
          </div>
          <div class="field field-wide">
            <label for="reimbursement_notes">Observações</label>
            <textarea id="reimbursement_notes" name="notes" rows="3"></textarea>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Registrar lançamento</button>
        </div>
      </form>
    </details>
  <?php endif; ?>

  <div style="margin-top: 14px;">
    <h4>Memórias de cálculo recentes</h4>
    <?php if ($reimbursementCalculationMemories === []): ?>
      <p class="muted">Nenhuma memória de cálculo registrada.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Lançamento</th>
              <th>Tipo</th>
              <th>Competência</th>
              <th>Total</th>
              <th>Criado em</th>
              <th>Responsável</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reimbursementCalculationMemories as $memoryRow): ?>
              <?php
                $memoryPayload = $decodeMetadata($memoryRow['calculation_memory'] ?? null);
                $memoryTotal = (float) ($memoryPayload['total'] ?? ($memoryRow['amount'] ?? 0));
              ?>
              <tr>
                <td>
                  <strong><?= e((string) ($memoryRow['title'] ?? '-')) ?></strong>
                  <?php if ($memoryPayload !== []): ?>
                    <div class="muted">
                      Subtotal <?= e($formatMoney((float) ($memoryPayload['subtotal'] ?? 0))) ?>
                      | Ajuste <?= e($formatPercent((float) ($memoryPayload['adjustment_percent'] ?? 0))) ?>
                      | Desconto <?= e($formatMoney((float) (($memoryPayload['components']['desconto'] ?? 0)))) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= e($reimbursementTypeLabel((string) ($memoryRow['entry_type'] ?? ''))) ?></td>
                <td><?= e($formatDate((string) ($memoryRow['reference_month'] ?? ''))) ?></td>
                <td><?= e($formatMoney($memoryTotal)) ?></td>
                <td><?= e($formatDateTime((string) ($memoryRow['created_at'] ?? ''))) ?></td>
                <td><?= e((string) ($memoryRow['created_by_name'] ?? 'Sistema')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" data-tab-panel="comments">
  <div class="header-row">
    <div>
      <h3>Comentarios internos do processo</h3>
      <p class="muted">Notas operacionais internas da movimentacao desta pessoa.</p>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Total</p>
      <p class="kpi-value"><?= e((string) ((int) ($processCommentSummary['total_comments'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Abertos</p>
      <p class="kpi-value"><?= e((string) ((int) ($processCommentSummary['open_count'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Arquivados</p>
      <p class="kpi-value"><?= e((string) ((int) ($processCommentSummary['archived_count'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Fixados</p>
      <p class="kpi-value"><?= e((string) ((int) ($processCommentSummary['pinned_count'] ?? 0))) ?></p>
    </article>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/process-comments/store')) ?>" class="process-comment-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <input type="hidden" name="assignment_id" value="<?= e((string) ((int) (is_array($assignment) ? ($assignment['id'] ?? 0) : 0))) ?>">
      <div class="form-grid">
        <div class="field field-wide">
          <label for="process_comment_text">Novo comentario interno</label>
          <textarea id="process_comment_text" name="comment_text" rows="3" minlength="3" maxlength="5000" required placeholder="Descreva observacoes internas, riscos e proximas acoes."></textarea>
        </div>
        <div class="field">
          <label for="process_comment_status">Status</label>
          <select id="process_comment_status" name="status">
            <?php foreach ($processCommentStatusOptions as $statusOption): ?>
              <?php $statusValue = (string) ($statusOption['value'] ?? 'aberto'); ?>
              <option value="<?= e($statusValue) ?>"><?= e((string) ($statusOption['label'] ?? ucfirst($statusValue))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="process_comment_pinned" style="display:flex; align-items:center; gap:8px;">
            <input type="hidden" name="is_pinned" value="0">
            <input id="process_comment_pinned" name="is_pinned" type="checkbox" value="1">
            Fixar comentario no topo
          </label>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Registrar comentario</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($processCommentItems === []): ?>
    <p class="muted">Nenhum comentario interno registrado para este processo.</p>
  <?php else: ?>
    <div class="comment-list">
      <?php foreach ($processCommentItems as $comment): ?>
        <?php
          $commentId = (int) ($comment['id'] ?? 0);
          $commentStatus = (string) ($comment['status'] ?? 'aberto');
          $isPinned = (int) ($comment['is_pinned'] ?? 0) === 1;
          $commentText = (string) ($comment['comment_text'] ?? '');
        ?>
        <article class="comment-item">
          <div class="comment-item-head">
            <div class="comment-item-title">
              <span class="badge <?= e($processCommentStatusBadgeClass($commentStatus)) ?>"><?= e($processCommentStatusLabel($commentStatus)) ?></span>
              <?php if ($isPinned): ?>
                <span class="badge badge-warning">Fixado</span>
              <?php endif; ?>
            </div>
            <span class="muted"><?= e($formatDateTime((string) ($comment['created_at'] ?? ''))) ?></span>
          </div>
          <p><?= nl2br(e($commentText)) ?></p>
          <div class="comment-meta">
            <span><strong>Autor:</strong> <?= e((string) ($comment['created_by_name'] ?? 'Sistema')) ?></span>
            <span><strong>Atualizacao:</strong> <?= e($formatDateTime((string) ($comment['updated_at'] ?? ''))) ?></span>
          </div>

          <?php if (($canManage ?? false) === true): ?>
            <details class="comment-edit">
              <summary>Editar comentario</summary>
              <form method="post" action="<?= e(url('/people/process-comments/update')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                <input type="hidden" name="comment_id" value="<?= e((string) $commentId) ?>">
                <div class="field field-wide">
                  <label for="comment_text_<?= e((string) $commentId) ?>">Texto</label>
                  <textarea id="comment_text_<?= e((string) $commentId) ?>" name="comment_text" rows="3" minlength="3" maxlength="5000" required><?= e($commentText) ?></textarea>
                </div>
                <div class="field">
                  <label for="comment_status_<?= e((string) $commentId) ?>">Status</label>
                  <select id="comment_status_<?= e((string) $commentId) ?>" name="status">
                    <?php foreach ($processCommentStatusOptions as $statusOption): ?>
                      <?php $statusValue = (string) ($statusOption['value'] ?? 'aberto'); ?>
                      <option value="<?= e($statusValue) ?>" <?= $statusValue === $commentStatus ? 'selected' : '' ?>>
                        <?= e((string) ($statusOption['label'] ?? ucfirst($statusValue))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="comment_pinned_<?= e((string) $commentId) ?>" style="display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="is_pinned" value="0">
                    <input id="comment_pinned_<?= e((string) $commentId) ?>" name="is_pinned" type="checkbox" value="1" <?= $isPinned ? 'checked' : '' ?>>
                    Fixado
                  </label>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn-outline">Salvar alteracoes</button>
                </div>
              </form>
            </details>

            <form method="post" action="<?= e(url('/people/process-comments/delete')) ?>" onsubmit="return confirm('Remover este comentario interno?');">
              <?= csrf_field() ?>
              <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
              <input type="hidden" name="comment_id" value="<?= e((string) $commentId) ?>">
              <button type="submit" class="btn btn-ghost">Excluir comentario</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" data-tab-panel="admin-timeline">
  <div class="header-row">
    <div>
      <h3>Timeline administrativa completa</h3>
      <p class="muted">Consolidacao administrativa por processo (notas manuais + fontes operacionais e financeiras).</p>
    </div>
  </div>

  <div class="grid-kpi costs-kpi">
    <article class="card kpi-card">
      <p class="kpi-label">Total</p>
      <p class="kpi-value"><?= e((string) ((int) ($adminTimelineSummary['total'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Abertos</p>
      <p class="kpi-value"><?= e((string) ((int) ($adminTimelineSummary['open_count'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Concluidos</p>
      <p class="kpi-value"><?= e((string) ((int) ($adminTimelineSummary['closed_count'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Notas manuais</p>
      <p class="kpi-value"><?= e((string) ((int) ($adminTimelineSummary['manual_count'] ?? 0))) ?></p>
    </article>
    <article class="card kpi-card">
      <p class="kpi-label">Entradas automaticas</p>
      <p class="kpi-value"><?= e((string) ((int) ($adminTimelineSummary['automated_count'] ?? 0))) ?></p>
    </article>
  </div>

  <form method="get" action="<?= e(url('/people/show')) ?>" class="admin-timeline-filters">
    <input type="hidden" name="id" value="<?= e((string) $personId) ?>">
    <input type="hidden" name="timeline_page" value="<?= e((string) ((int) ($timelinePagination['page'] ?? 1))) ?>">
    <input type="hidden" name="documents_page" value="<?= e((string) ((int) ($documentsPagination['page'] ?? 1))) ?>">
    <input type="hidden" name="audit_page" value="<?= e((string) ((int) ($auditPagination['page'] ?? 1))) ?>">
    <input type="hidden" name="audit_entity" value="<?= e((string) ($auditFilters['entity'] ?? '')) ?>">
    <input type="hidden" name="audit_action" value="<?= e((string) ($auditFilters['action'] ?? '')) ?>">
    <input type="hidden" name="audit_q" value="<?= e((string) ($auditFilters['q'] ?? '')) ?>">
    <input type="hidden" name="audit_from" value="<?= e((string) ($auditFilters['from_date'] ?? '')) ?>">
    <input type="hidden" name="audit_to" value="<?= e((string) ($auditFilters['to_date'] ?? '')) ?>">
    <input type="hidden" name="admin_timeline_page" value="1">
    <div class="form-grid">
      <div class="field field-wide">
        <label for="admin_timeline_q">Busca</label>
        <input id="admin_timeline_q" name="admin_timeline_q" type="text" value="<?= e((string) ($adminTimelineFilters['q'] ?? '')) ?>" placeholder="Titulo, descricao, ator ou status">
      </div>
      <div class="field">
        <label for="admin_timeline_source">Origem</label>
        <select id="admin_timeline_source" name="admin_timeline_source">
          <?php foreach ($adminTimelineSourceOptions as $sourceOption): ?>
            <?php $sourceValue = (string) ($sourceOption['value'] ?? ''); ?>
            <option value="<?= e($sourceValue) ?>" <?= $sourceValue === (string) ($adminTimelineFilters['source'] ?? '') ? 'selected' : '' ?>>
              <?= e((string) ($sourceOption['label'] ?? ($sourceValue === '' ? 'Todas' : $sourceValue))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="admin_timeline_status_group">Status</label>
        <select id="admin_timeline_status_group" name="admin_timeline_status_group">
          <?php foreach ($adminTimelineStatusGroupOptions as $statusOption): ?>
            <?php $statusValue = (string) ($statusOption['value'] ?? ''); ?>
            <option value="<?= e($statusValue) ?>" <?= $statusValue === (string) ($adminTimelineFilters['status_group'] ?? '') ? 'selected' : '' ?>>
              <?= e((string) ($statusOption['label'] ?? ($statusValue === '' ? 'Todos' : $statusValue))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-outline" href="<?= e($buildProfileUrl(['admin_timeline_page' => 1], ['admin_timeline_q', 'admin_timeline_source', 'admin_timeline_status_group'])) ?>">Limpar</a>
      <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>
  </form>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/people/process-admin-timeline/store')) ?>" class="admin-timeline-note-form">
      <?= csrf_field() ?>
      <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
      <input type="hidden" name="assignment_id" value="<?= e((string) ((int) (is_array($assignment) ? ($assignment['id'] ?? 0) : 0))) ?>">
      <div class="form-grid">
        <div class="field field-wide">
          <label for="admin_timeline_note_title">Nova nota administrativa</label>
          <input id="admin_timeline_note_title" name="title" type="text" minlength="3" maxlength="190" required placeholder="Titulo objetivo da nota">
        </div>
        <div class="field field-wide">
          <label for="admin_timeline_note_description">Descricao</label>
          <textarea id="admin_timeline_note_description" name="description" rows="3" maxlength="5000" placeholder="Detalhes, contexto e proxima acao."></textarea>
        </div>
        <div class="field">
          <label for="admin_timeline_note_status">Status</label>
          <select id="admin_timeline_note_status" name="status">
            <?php foreach ($adminTimelineNoteStatusOptions as $statusOption): ?>
              <?php $statusValue = (string) ($statusOption['value'] ?? 'aberto'); ?>
              <option value="<?= e($statusValue) ?>"><?= e((string) ($statusOption['label'] ?? ucfirst($statusValue))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="admin_timeline_note_severity">Severidade</label>
          <select id="admin_timeline_note_severity" name="severity">
            <?php foreach ($adminTimelineNoteSeverityOptions as $severityOption): ?>
              <?php $severityValue = (string) ($severityOption['value'] ?? 'media'); ?>
              <option value="<?= e($severityValue) ?>"><?= e((string) ($severityOption['label'] ?? ucfirst($severityValue))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="admin_timeline_note_event_at">Data/hora do evento</label>
          <input id="admin_timeline_note_event_at" name="event_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="field">
          <label for="admin_timeline_note_pinned" style="display:flex; align-items:center; gap:8px;">
            <input type="hidden" name="is_pinned" value="0">
            <input id="admin_timeline_note_pinned" name="is_pinned" type="checkbox" value="1">
            Fixar na timeline
          </label>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Registrar nota administrativa</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($adminTimelineItems === []): ?>
    <p class="muted">Nenhuma entrada disponivel para a timeline administrativa deste processo.</p>
  <?php else: ?>
    <div class="admin-timeline-list">
      <?php foreach ($adminTimelineItems as $entry): ?>
        <?php
          $entrySourceKind = (string) ($entry['source_kind'] ?? '');
          $entrySourceLabel = (string) ($entry['source_label'] ?? 'Origem');
          $entryTitle = (string) ($entry['title'] ?? 'Entrada administrativa');
          $entryDescription = (string) ($entry['description'] ?? '');
          $entryStatusLabel = (string) ($entry['status_label'] ?? '-');
          $entryStatusGroup = (string) ($entry['status_group'] ?? 'concluido');
          $entrySeverity = (string) ($entry['severity'] ?? 'baixa');
          $entrySeverityLabel = (string) ($entry['severity_label'] ?? ucfirst($entrySeverity));
          $entryEventAt = (string) ($entry['event_at'] ?? '');
          $entryActor = (string) ($entry['actor_name'] ?? 'Sistema');
          $entryIsPinned = (int) ($entry['is_pinned'] ?? 0) === 1;
          $entryIsManual = (int) ($entry['is_manual'] ?? 0) === 1;
          $entryCanEdit = (int) ($entry['can_edit'] ?? 0) === 1;
          $entrySourceId = (int) ($entry['source_id'] ?? 0);
          $entryNoteId = $entryIsManual ? $entrySourceId : 0;
          $entryAssignmentId = isset($entry['assignment_id']) ? (int) $entry['assignment_id'] : 0;
          $entryEventAtInput = $formatDateTimeInput($entryEventAt);
          $entryStatusRaw = (string) ($entry['status_raw'] ?? 'aberto');
        ?>
        <article class="admin-timeline-item<?= $entryIsManual ? ' is-manual' : '' ?>">
          <div class="admin-timeline-item-head">
            <div class="admin-timeline-item-title">
              <span class="badge <?= e($adminTimelineSourceBadgeClass($entrySourceKind)) ?>"><?= e($entrySourceLabel) ?></span>
              <span class="badge <?= e($adminTimelineStatusBadgeClass($entryStatusGroup)) ?>"><?= e($entryStatusLabel) ?></span>
              <span class="badge <?= e($adminTimelineSeverityBadgeClass($entrySeverity)) ?>"><?= e($entrySeverityLabel) ?></span>
              <?php if ($entryIsPinned): ?>
                <span class="badge badge-warning">Fixado</span>
              <?php endif; ?>
              <strong><?= e($entryTitle) ?></strong>
            </div>
            <span class="muted"><?= e($formatDateTime($entryEventAt)) ?></span>
          </div>

          <?php if (trim($entryDescription) !== ''): ?>
            <p class="admin-timeline-item-description"><?= nl2br(e($entryDescription)) ?></p>
          <?php endif; ?>

          <div class="admin-timeline-meta">
            <span><strong>Responsavel:</strong> <?= e($entryActor) ?></span>
            <?php if ($entryAssignmentId > 0): ?>
              <span><strong>Movimentacao:</strong> #<?= e((string) $entryAssignmentId) ?></span>
            <?php endif; ?>
            <?php if ($entrySourceId > 0): ?>
              <span><strong>ID origem:</strong> #<?= e((string) $entrySourceId) ?></span>
            <?php endif; ?>
          </div>

          <?php if (($canManage ?? false) === true && $entryCanEdit && $entryNoteId > 0): ?>
            <details class="admin-timeline-edit">
              <summary>Editar nota manual</summary>
              <form method="post" action="<?= e(url('/people/process-admin-timeline/update')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
                <input type="hidden" name="note_id" value="<?= e((string) $entryNoteId) ?>">
                <div class="field field-wide">
                  <label for="admin_timeline_title_<?= e((string) $entryNoteId) ?>">Titulo</label>
                  <input id="admin_timeline_title_<?= e((string) $entryNoteId) ?>" name="title" type="text" minlength="3" maxlength="190" required value="<?= e($entryTitle) ?>">
                </div>
                <div class="field field-wide">
                  <label for="admin_timeline_description_<?= e((string) $entryNoteId) ?>">Descricao</label>
                  <textarea id="admin_timeline_description_<?= e((string) $entryNoteId) ?>" name="description" rows="3" maxlength="5000"><?= e($entryDescription) ?></textarea>
                </div>
                <div class="field">
                  <label for="admin_timeline_status_<?= e((string) $entryNoteId) ?>">Status</label>
                  <select id="admin_timeline_status_<?= e((string) $entryNoteId) ?>" name="status">
                    <?php foreach ($adminTimelineNoteStatusOptions as $statusOption): ?>
                      <?php $statusValue = (string) ($statusOption['value'] ?? 'aberto'); ?>
                      <option value="<?= e($statusValue) ?>" <?= $statusValue === $entryStatusRaw ? 'selected' : '' ?>>
                        <?= e((string) ($statusOption['label'] ?? ucfirst($statusValue))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="admin_timeline_severity_<?= e((string) $entryNoteId) ?>">Severidade</label>
                  <select id="admin_timeline_severity_<?= e((string) $entryNoteId) ?>" name="severity">
                    <?php foreach ($adminTimelineNoteSeverityOptions as $severityOption): ?>
                      <?php $severityValue = (string) ($severityOption['value'] ?? 'media'); ?>
                      <option value="<?= e($severityValue) ?>" <?= $severityValue === $entrySeverity ? 'selected' : '' ?>>
                        <?= e((string) ($severityOption['label'] ?? ucfirst($severityValue))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="admin_timeline_event_at_<?= e((string) $entryNoteId) ?>">Data/hora do evento</label>
                  <input id="admin_timeline_event_at_<?= e((string) $entryNoteId) ?>" name="event_at" type="datetime-local" value="<?= e($entryEventAtInput) ?>">
                </div>
                <div class="field">
                  <label for="admin_timeline_pinned_<?= e((string) $entryNoteId) ?>" style="display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="is_pinned" value="0">
                    <input id="admin_timeline_pinned_<?= e((string) $entryNoteId) ?>" name="is_pinned" type="checkbox" value="1" <?= $entryIsPinned ? 'checked' : '' ?>>
                    Fixado
                  </label>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn-outline">Salvar alteracoes</button>
                </div>
              </form>
            </details>

            <form method="post" action="<?= e(url('/people/process-admin-timeline/delete')) ?>" onsubmit="return confirm('Remover esta nota administrativa?');">
              <?= csrf_field() ?>
              <input type="hidden" name="person_id" value="<?= e((string) $personId) ?>">
              <input type="hidden" name="note_id" value="<?= e((string) $entryNoteId) ?>">
              <button type="submit" class="btn btn-ghost">Excluir nota</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php
      $adminTimelineTotal = (int) ($adminTimelinePagination['total'] ?? 0);
      $adminTimelinePage = (int) ($adminTimelinePagination['page'] ?? 1);
      $adminTimelinePerPage = max(1, (int) ($adminTimelinePagination['per_page'] ?? 14));
      $adminTimelinePages = max(1, (int) ($adminTimelinePagination['pages'] ?? 1));
      $adminTimelineStart = $adminTimelineTotal > 0 ? (($adminTimelinePage - 1) * $adminTimelinePerPage) + 1 : 0;
      $adminTimelineEnd = min($adminTimelineTotal, $adminTimelinePage * $adminTimelinePerPage);
    ?>
    <div class="pagination-row">
      <span class="muted">Exibindo <?= e((string) $adminTimelineStart) ?>-<?= e((string) $adminTimelineEnd) ?> de <?= e((string) $adminTimelineTotal) ?> entradas</span>
      <div class="pagination-links">
        <?php if ($adminTimelinePage > 1): ?>
          <a class="btn btn-outline" href="<?= e($buildAdminTimelinePageUrl($adminTimelinePage - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?= e((string) $adminTimelinePage) ?> de <?= e((string) $adminTimelinePages) ?></span>
        <?php if ($adminTimelinePage < $adminTimelinePages): ?>
          <a class="btn btn-outline" href="<?= e($buildAdminTimelinePageUrl($adminTimelinePage + 1)) ?>">Próxima</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="card" data-tab-panel="audit">
  <div class="header-row">
    <div>
      <h3>Auditoria</h3>
      <p class="muted">Histórico de alterações e ações relacionadas a esta pessoa.</p>
    </div>
  </div>

  <?php if (($canViewAudit ?? false) !== true): ?>
    <p class="muted">Você não possui permissão para visualizar a trilha de auditoria.</p>
  <?php else: ?>
    <form method="get" action="<?= e(url('/people/show')) ?>" class="audit-filters">
      <input type="hidden" name="id" value="<?= e((string) $personId) ?>">
      <input type="hidden" name="timeline_page" value="<?= e((string) ((int) ($timelinePagination['page'] ?? 1))) ?>">
      <input type="hidden" name="documents_page" value="<?= e((string) ((int) ($documentsPagination['page'] ?? 1))) ?>">
      <input type="hidden" name="admin_timeline_page" value="<?= e((string) ((int) ($adminTimelinePagination['page'] ?? 1))) ?>">
      <input type="hidden" name="admin_timeline_q" value="<?= e((string) ($adminTimelineFilters['q'] ?? '')) ?>">
      <input type="hidden" name="admin_timeline_source" value="<?= e((string) ($adminTimelineFilters['source'] ?? '')) ?>">
      <input type="hidden" name="admin_timeline_status_group" value="<?= e((string) ($adminTimelineFilters['status_group'] ?? '')) ?>">
      <input type="hidden" name="audit_page" value="1">
      <div class="form-grid">
        <div class="field">
          <label for="audit_q">Busca</label>
          <input id="audit_q" name="audit_q" type="text" value="<?= e((string) ($auditFilters['q'] ?? '')) ?>" placeholder="Entidade, ação ou usuário">
        </div>
        <div class="field">
          <label for="audit_entity">Entidade</label>
          <select id="audit_entity" name="audit_entity">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['entities'] ?? []) as $entityOption): ?>
              <?php $entityOption = (string) $entityOption; ?>
              <option value="<?= e($entityOption) ?>" <?= $entityOption === (string) ($auditFilters['entity'] ?? '') ? 'selected' : '' ?>>
                <?= e($auditEntityLabel($entityOption)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_action">Ação</label>
          <select id="audit_action" name="audit_action">
            <option value="">Todas</option>
            <?php foreach ((array) ($auditOptions['actions'] ?? []) as $actionOption): ?>
              <?php $actionOption = (string) $actionOption; ?>
              <option value="<?= e($actionOption) ?>" <?= $actionOption === (string) ($auditFilters['action'] ?? '') ? 'selected' : '' ?>>
                <?= e($actionOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="audit_from">De</label>
          <input id="audit_from" name="audit_from" type="date" value="<?= e((string) ($auditFilters['from_date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="audit_to">Até</label>
          <input id="audit_to" name="audit_to" type="date" value="<?= e((string) ($auditFilters['to_date'] ?? '')) ?>">
        </div>
      </div>
      <div class="form-actions">
        <a class="btn btn-outline" href="<?= e($buildProfileUrl(['audit_page' => 1], ['audit_entity', 'audit_action', 'audit_q', 'audit_from', 'audit_to'])) ?>">Limpar</a>
        <a class="btn btn-outline" href="<?= e($buildAuditExportUrl()) ?>">Exportar CSV</a>
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>

    <?php if ($auditItems === []): ?>
      <p class="muted">Nenhum registro de auditoria encontrado para os filtros informados.</p>
    <?php else: ?>
      <div class="audit-list">
        <?php foreach ($auditItems as $entry): ?>
          <?php
            $entity = (string) ($entry['entity'] ?? '');
            $entityId = isset($entry['entity_id']) ? (int) $entry['entity_id'] : 0;
            $beforeData = $prettyJson($entry['before_data'] ?? null);
            $afterData = $prettyJson($entry['after_data'] ?? null);
            $metadataData = $prettyJson($entry['metadata'] ?? null);
            $hasDetails = $beforeData !== '-' || $afterData !== '-' || $metadataData !== '-';
          ?>
          <article class="audit-item">
            <div class="audit-item-head">
              <div class="audit-item-title">
                <span class="badge badge-neutral"><?= e($auditEntityLabel($entity)) ?></span>
                <strong><?= e((string) ($entry['action'] ?? '-')) ?></strong>
                <?php if ($entityId > 0): ?>
                  <span class="muted">#<?= e((string) $entityId) ?></span>
                <?php endif; ?>
              </div>
              <span class="muted"><?= e($formatDateTime((string) ($entry['created_at'] ?? ''))) ?></span>
            </div>

            <div class="audit-meta">
              <span><strong>Usuário:</strong> <?= e((string) ($entry['user_name'] ?? 'Sistema')) ?></span>
              <span><strong>IP:</strong> <?= e((string) ($entry['ip'] ?? '-')) ?></span>
            </div>

            <?php if ($hasDetails): ?>
              <details class="audit-details">
                <summary>Ver dados</summary>
                <div class="audit-payload-grid">
                  <div>
                    <strong>Antes</strong>
                    <pre><?= e($beforeData) ?></pre>
                  </div>
                  <div>
                    <strong>Depois</strong>
                    <pre><?= e($afterData) ?></pre>
                  </div>
                  <div class="audit-payload-wide">
                    <strong>Metadata</strong>
                    <pre><?= e($metadataData) ?></pre>
                  </div>
                </div>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <?php
        $auditTotal = (int) ($auditPagination['total'] ?? 0);
        $auditPage = (int) ($auditPagination['page'] ?? 1);
        $auditPerPage = max(1, (int) ($auditPagination['per_page'] ?? 10));
        $auditPages = max(1, (int) ($auditPagination['pages'] ?? 1));
        $auditStart = $auditTotal > 0 ? (($auditPage - 1) * $auditPerPage) + 1 : 0;
        $auditEnd = min($auditTotal, $auditPage * $auditPerPage);
      ?>
      <div class="pagination-row">
        <span class="muted">Exibindo <?= e((string) $auditStart) ?>-<?= e((string) $auditEnd) ?> de <?= e((string) $auditTotal) ?> registros</span>
        <div class="pagination-links">
          <?php if ($auditPage > 1): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage - 1)) ?>">Anterior</a>
          <?php endif; ?>
          <span class="muted">Página <?= e((string) $auditPage) ?> de <?= e((string) $auditPages) ?></span>
          <?php if ($auditPage < $auditPages): ?>
            <a class="btn btn-outline" href="<?= e($buildAuditPageUrl($auditPage + 1)) ?>">Próxima</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
