#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\PeopleRepository;
use App\Repositories\PipelineRepository;
use App\Services\BudgetService;
use App\Services\HomeDashboardService;
use App\Services\PeopleService;
use App\Services\PipelineService;

main($argv);

/**
 * @param array<int, string> $argv
 */
function main(array $argv): void
{
    if (PHP_SAPI !== 'cli') {
        fail('script disponivel apenas em CLI.');
    }

    $basePath = dirname(__DIR__);
    $options = parseOptions($argv);
    if ($options['help'] === true) {
        printUsage();
        exit(0);
    }

    /** @var App|mixed $app */
    $app = require $basePath . '/bootstrap.php';
    if (!$app instanceof App) {
        fail('falha ao inicializar aplicacao.');
    }

    $db = $app->db();
    $now = new DateTimeImmutable('now');
    $today = $now->format('Y-m-d');
    $monthStart = $now->modify('first day of this month')->format('Y-m-d');
    $monthEnd = $now->modify('last day of this month')->format('Y-m-d');
    $nextMonthStart = $now->modify('first day of next month')->format('Y-m-d');
    $nextMonthEnd = $now->modify('last day of next month')->format('Y-m-d');

    $suffix = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $assertions = [];
    $created = [
        'flow_id' => null,
        'status_ids' => [],
        'person_ids' => [],
        'cost_plan_ids' => [],
        'cost_item_ids' => [],
        'mte_destination_id' => null,
        'mte_destination_created' => false,
    ];

    try {
        runRouteAssertions($basePath, $assertions);
        runSchemaAssertions($db, $assertions);

        $userId = firstInt($db, 'SELECT id FROM users WHERE deleted_at IS NULL AND is_active = 1 ORDER BY id ASC LIMIT 1');
        if ($userId <= 0) {
            throw new RuntimeException('nenhum usuario ativo encontrado para executar homologacao.');
        }

        $organId = firstInt($db, 'SELECT id FROM organs WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        if ($organId <= 0) {
            throw new RuntimeException('nenhum orgao ativo encontrado para homologacao.');
        }

        $modalityId = firstInt($db, 'SELECT id FROM modalities WHERE is_active = 1 ORDER BY id ASC LIMIT 1');

        $mteDestinationId = firstInt($db, 'SELECT id FROM mte_destinations WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        if ($mteDestinationId <= 0) {
            $mteDestinationId = createMteDestination($db, $suffix);
            $created['mte_destination_id'] = $mteDestinationId;
            $created['mte_destination_created'] = true;
        }

        $flow = createTaggedFlow($db, $suffix);
        $created['flow_id'] = $flow['flow_id'];
        $created['status_ids'] = [$flow['status_start_id'], $flow['status_next_id']];

        $peopleService = new PeopleService(new PeopleRepository($db), $app->audit(), $app->events());
        $pipelineService = new PipelineService(
            new PipelineRepository($db),
            $app->audit(),
            $app->events(),
            $app->config(),
            lgpdService($app),
            securityService($app)
        );
        $pipelineRepository = new PipelineRepository($db);

        $entryPerson = createPersonForHomologation(
            peopleService: $peopleService,
            flowId: $flow['flow_id'],
            organId: $organId,
            modalityId: $modalityId > 0 ? $modalityId : null,
            namePrefix: 'Homolog F12 Entrada',
            suffix: $suffix . 'E',
            userId: $userId
        );
        $entryPersonId = (int) ($entryPerson['id'] ?? 0);
        if ($entryPersonId <= 0) {
            throw new RuntimeException('falha ao criar pessoa de entrada para homologacao.');
        }
        $created['person_ids'][] = $entryPersonId;

        $entryEnsure = $pipelineService->ensureAssignment(
            personId: $entryPersonId,
            modalityId: $modalityId > 0 ? $modalityId : null,
            userId: $userId,
            ip: '127.0.0.1',
            userAgent: 'phase12-homologation-script',
            movementContext: [
                'movement_direction' => 'entrada_mte',
                'financial_nature' => 'despesa_reembolso',
                'counterparty_organ_id' => $organId,
                'destination_mte_destination_id' => $mteDestinationId,
            ],
            scheduleContext: [
                'target_start_date' => $nextMonthStart,
                'requested_end_date' => $nextMonthEnd,
            ]
        );
        if ($entryEnsure === null) {
            throw new RuntimeException('falha ao inicializar movimentacao da pessoa de entrada.');
        }

        $entryAssignmentId = (int) ($entryEnsure['id'] ?? 0);
        addAssertion(
            $assertions,
            'persistencia da data prevista inicial',
            ((string) ($entryEnsure['target_start_date'] ?? '') === $nextMonthStart),
            $nextMonthStart,
            (string) ($entryEnsure['target_start_date'] ?? ''),
            'assignment.target_start_date deve refletir o contexto informado na abertura.'
        );
        addAssertion(
            $assertions,
            'persistencia da data prevista final',
            ((string) ($entryEnsure['requested_end_date'] ?? '') === $nextMonthEnd),
            $nextMonthEnd,
            (string) ($entryEnsure['requested_end_date'] ?? ''),
            'assignment.requested_end_date deve refletir o contexto informado na abertura.'
        );

        $updatedTargetStart = $nextMonthStart;
        $updatedRequestedEnd = (new DateTimeImmutable($nextMonthEnd))->modify('+7 days')->format('Y-m-d');
        $entryEnsureUpdated = $pipelineService->ensureAssignment(
            personId: $entryPersonId,
            modalityId: $modalityId > 0 ? $modalityId : null,
            userId: $userId,
            ip: '127.0.0.1',
            userAgent: 'phase12-homologation-script',
            movementContext: [
                'movement_direction' => 'entrada_mte',
                'financial_nature' => 'despesa_reembolso',
                'counterparty_organ_id' => $organId,
                'destination_mte_destination_id' => $mteDestinationId,
            ],
            scheduleContext: [
                'target_start_date' => $updatedTargetStart,
                'requested_end_date' => $updatedRequestedEnd,
            ]
        );
        if ($entryEnsureUpdated === null) {
            throw new RuntimeException('falha ao atualizar datas previstas da movimentacao.');
        }

        addAssertion(
            $assertions,
            'atualizacao de data prevista final',
            ((string) ($entryEnsureUpdated['requested_end_date'] ?? '') === $updatedRequestedEnd),
            $updatedRequestedEnd,
            (string) ($entryEnsureUpdated['requested_end_date'] ?? ''),
            'ensureAssignment deve atualizar o agendamento quando receber novo contexto.'
        );

        $scheduleAuditCount = firstInt(
            $db,
            'SELECT COUNT(*) AS total
             FROM audit_log
             WHERE entity = :entity
               AND entity_id = :entity_id
               AND action = :action',
            [
                'entity' => 'assignment',
                'entity_id' => $entryAssignmentId,
                'action' => 'schedule.update',
            ]
        );
        addAssertion(
            $assertions,
            'auditoria de schedule.update',
            $scheduleAuditCount >= 1,
            '>= 1',
            (string) $scheduleAuditCount,
            'alteracoes de datas previstas devem gerar trilha de auditoria.'
        );

        $costPlanId = createCostPlanForPerson($db, $entryPersonId, $userId, 'Homolog F12');
        $created['cost_plan_ids'][] = $costPlanId;
        $costItemAmount = 123.45;
        $costItemId = createMonthlyCostItemForPerson($db, $costPlanId, $entryPersonId, $costItemAmount, $userId);
        $created['cost_item_ids'][] = $costItemId;

        $projectedBefore = projectedMonthlyAmountForPerson($db, $entryPersonId, $monthStart, $monthEnd);
        addAssertion(
            $assertions,
            'projecao usa data prevista antes da efetiva',
            abs($projectedBefore - 0.0) < 0.01,
            '0.00',
            number_format($projectedBefore, 2, '.', ''),
            'com inicio previsto no mes seguinte, nao deve projetar custo no mes atual.'
        );

        $entryAdvance = $pipelineService->advance(
            personId: $entryPersonId,
            transitionId: null,
            userId: $userId,
            ip: '127.0.0.1',
            userAgent: 'phase12-homologation-script'
        );
        if (($entryAdvance['ok'] ?? false) !== true) {
            $message = (string) ($entryAdvance['message'] ?? 'falha ao avancar pessoa de entrada.');
            throw new RuntimeException($message);
        }

        $entryAfterAdvance = $pipelineRepository->assignmentByPersonId($entryPersonId);
        addAssertion(
            $assertions,
            'tag data_transferencia_efetiva preenche effective_start_date',
            ((string) ($entryAfterAdvance['effective_start_date'] ?? '') === $today),
            $today,
            (string) ($entryAfterAdvance['effective_start_date'] ?? ''),
            'ao avancar etapa com tag, entrada deve registrar data real de inicio.'
        );

        $projectedAfter = projectedMonthlyAmountForPerson($db, $entryPersonId, $monthStart, $monthEnd);
        addAssertion(
            $assertions,
            'data efetiva substitui prevista nas projecoes',
            abs($projectedAfter - $costItemAmount) < 0.01,
            number_format($costItemAmount, 2, '.', ''),
            number_format($projectedAfter, 2, '.', ''),
            'apos registrar data efetiva, custo mensal deve entrar na projecao do mes atual.'
        );

        $exitPerson = createPersonForHomologation(
            peopleService: $peopleService,
            flowId: $flow['flow_id'],
            organId: $organId,
            modalityId: $modalityId > 0 ? $modalityId : null,
            namePrefix: 'Homolog F12 Saida',
            suffix: $suffix . 'S',
            userId: $userId
        );
        $exitPersonId = (int) ($exitPerson['id'] ?? 0);
        if ($exitPersonId <= 0) {
            throw new RuntimeException('falha ao criar pessoa de saida para homologacao.');
        }
        $created['person_ids'][] = $exitPersonId;

        $exitEnsure = $pipelineService->ensureAssignment(
            personId: $exitPersonId,
            modalityId: $modalityId > 0 ? $modalityId : null,
            userId: $userId,
            ip: '127.0.0.1',
            userAgent: 'phase12-homologation-script',
            movementContext: [
                'movement_direction' => 'saida_mte',
                'financial_nature' => 'receita_reembolso',
                'counterparty_organ_id' => $organId,
                'origin_mte_destination_id' => $mteDestinationId,
            ],
            scheduleContext: [
                'target_start_date' => $monthStart,
                'requested_end_date' => $nextMonthEnd,
            ]
        );
        if ($exitEnsure === null) {
            throw new RuntimeException('falha ao inicializar movimentacao da pessoa de saida.');
        }

        $exitAdvance = $pipelineService->advance(
            personId: $exitPersonId,
            transitionId: null,
            userId: $userId,
            ip: '127.0.0.1',
            userAgent: 'phase12-homologation-script'
        );
        if (($exitAdvance['ok'] ?? false) !== true) {
            $message = (string) ($exitAdvance['message'] ?? 'falha ao avancar pessoa de saida.');
            throw new RuntimeException($message);
        }

        $exitAfterAdvance = $pipelineRepository->assignmentByPersonId($exitPersonId);
        addAssertion(
            $assertions,
            'tag data_transferencia_efetiva preenche effective_end_date',
            ((string) ($exitAfterAdvance['effective_end_date'] ?? '') === $today),
            $today,
            (string) ($exitAfterAdvance['effective_end_date'] ?? ''),
            'ao avancar etapa com tag, saida deve registrar data real de termino.'
        );

        $year = resolveYear($options['year']);
        $homeService = new HomeDashboardService(
            new BudgetService(new BudgetRepository($db), $app->audit(), $app->events()),
            new DashboardRepository($db)
        );
        $homeOverview = $homeService->overview($year);

        $monthlyChart = is_array($homeOverview['monthly_chart'] ?? null) ? $homeOverview['monthly_chart'] : [];
        $peopleProjection = is_array($homeOverview['people_projection'] ?? null) ? $homeOverview['people_projection'] : [];
        $summary = is_array($homeOverview['summary'] ?? null) ? $homeOverview['summary'] : [];

        addAssertion(
            $assertions,
            'novo dashboard retorna serie mensal com 12 meses',
            count($monthlyChart) === 12,
            '12',
            (string) count($monthlyChart),
            'grafico mensal real x planejado deve cobrir janeiro a dezembro.'
        );
        addAssertion(
            $assertions,
            'novo dashboard retorna projecao de pessoas com 12 meses',
            count($peopleProjection) === 12,
            '12',
            (string) count($peopleProjection),
            'grafico empilhado (ativas + pipeline) deve cobrir janeiro a dezembro.'
        );
        addAssertion(
            $assertions,
            'novo dashboard expone kpis obrigatorios',
            hasKeys($summary, ['total_budget', 'spent_year_to_date', 'available_balance', 'execution_percent']),
            'kpis obrigatorios presentes',
            implode(',', array_keys($summary)),
            'orcamento, gasto acumulado, saldo e percentual de execucao devem existir.'
        );
    } catch (Throwable $throwable) {
        $assertions[] = [
            'name' => 'execucao da homologacao',
            'pass' => false,
            'expected' => 'concluir sem excecao',
            'actual' => $throwable->getMessage(),
            'details' => $throwable->getFile() . ':' . $throwable->getLine(),
        ];
    } finally {
        if ((bool) $options['keep_data'] === false) {
            cleanupCreatedData($db, $created);
        }
    }

    $failed = array_values(array_filter($assertions, static fn (array $item): bool => (($item['pass'] ?? false) === false)));
    $payload = [
        'status' => $failed === [] ? 'ok' : 'failed',
        'generated_at' => date(DATE_ATOM),
        'totals' => [
            'assertions_total' => count($assertions),
            'assertions_failed' => count($failed),
        ],
        'assertions' => $assertions,
    ];

    output((string) $options['output'], $payload);
    exit($failed === [] ? 0 : 2);
}

/**
 * @param array<int, string> $argv
 * @return array{output: string, year: int|null, keep_data: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'output' => 'table',
        'year' => null,
        'keep_data' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        switch ($arg) {
            case '--output':
                $value = strtolower(readOptionValue($argv, $i, '--output'));
                if (!in_array($value, ['table', 'json'], true)) {
                    fail('--output deve ser table ou json.');
                }
                $options['output'] = $value;
                break;
            case '--year':
                $yearRaw = readOptionValue($argv, $i, '--year');
                if (!ctype_digit($yearRaw)) {
                    fail('--year deve ser numerico.');
                }
                $year = (int) $yearRaw;
                if ($year < 2000 || $year > 2100) {
                    fail('--year fora do intervalo suportado (2000-2100).');
                }
                $options['year'] = $year;
                break;
            case '--keep-data':
                $options['keep_data'] = true;
                break;
            case '--help':
            case '-h':
                $options['help'] = true;
                break;
            default:
                fail('opcao desconhecida: ' . $arg);
        }
    }

    return $options;
}

/**
 * @param array<int, string> $argv
 */
function readOptionValue(array $argv, int &$index, string $option): string
{
    $valueIndex = $index + 1;
    if (!isset($argv[$valueIndex])) {
        fail('valor ausente para ' . $option);
    }

    $index = $valueIndex;
    $value = trim((string) $argv[$valueIndex]);
    if ($value === '') {
        fail('valor invalido para ' . $option);
    }

    return $value;
}

function runRouteAssertions(string $basePath, array &$assertions): void
{
    $routesPath = $basePath . '/routes/web.php';
    $content = @file_get_contents($routesPath);
    if (!is_string($content)) {
        throw new RuntimeException('nao foi possivel ler routes/web.php.');
    }

    addAssertion(
        $assertions,
        'rota /dashboard configurada',
        str_contains($content, '$router->get(\'/dashboard\', [DashboardController::class, \'index\']'),
        'rota com DashboardController::index',
        'routes/web.php',
        'novo dashboard inicial deve ser a rota principal.'
    );

    addAssertion(
        $assertions,
        'rota /dashboard2 configurada',
        str_contains($content, '$router->get(\'/dashboard2\', [DashboardController::class, \'legacy\']'),
        'rota com DashboardController::legacy',
        'routes/web.php',
        'dashboard legado deve permanecer acessivel em /dashboard2.'
    );
}

function runSchemaAssertions(PDO $db, array &$assertions): void
{
    $requiredColumns = [
        'assignments' => ['target_start_date', 'requested_end_date', 'effective_start_date', 'effective_end_date'],
        'assignment_flow_steps' => ['step_tags'],
    ];

    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $exists = firstInt(
                $db,
                'SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name',
                ['table_name' => $table, 'column_name' => $column]
            ) > 0;

            addAssertion(
                $assertions,
                'coluna ' . $table . '.' . $column,
                $exists,
                'presente',
                $exists ? 'presente' : 'ausente',
                'schema necessario para RF-06/RF-02.'
            );
        }
    }
}

/**
 * @return array{flow_id: int, status_start_id: int, status_next_id: int}
 */
function createTaggedFlow(PDO $db, string $suffix): array
{
    $sortOrderBase = firstInt($db, 'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM assignment_statuses');
    $startSort = max(1, $sortOrderBase);
    $nextSort = $startSort + 1;

    $startCode = strtolower('h12_start_' . $suffix);
    $nextCode = strtolower('h12_next_' . $suffix);

    $stmtStatus = $db->prepare(
        'INSERT INTO assignment_statuses (
            code,
            label,
            sort_order,
            next_action_label,
            event_type,
            is_active,
            created_at,
            updated_at
         ) VALUES (
            :code,
            :label,
            :sort_order,
            :next_action_label,
            :event_type,
            1,
            NOW(),
            NOW()
         )'
    );

    $stmtStatus->execute([
        'code' => $startCode,
        'label' => 'Homolog F12 - Inicio ' . $suffix,
        'sort_order' => $startSort,
        'next_action_label' => 'Avancar para proxima etapa',
        'event_type' => 'pipeline.homolog_phase12_start',
    ]);
    $startStatusId = (int) $db->lastInsertId();

    $stmtStatus->execute([
        'code' => $nextCode,
        'label' => 'Homolog F12 - Proxima etapa ' . $suffix,
        'sort_order' => $nextSort,
        'next_action_label' => null,
        'event_type' => 'pipeline.homolog_phase12_next',
    ]);
    $nextStatusId = (int) $db->lastInsertId();

    $stmtFlow = $db->prepare(
        'INSERT INTO assignment_flows (
            name,
            description,
            bpmn_diagram_xml,
            is_active,
            is_default,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :name,
            :description,
            NULL,
            1,
            0,
            NOW(),
            NOW(),
            NULL
         )'
    );
    $stmtFlow->execute([
        'name' => 'Homolog F12 Flow ' . $suffix,
        'description' => 'Fluxo temporario para validar melhorias1 fase 4.',
    ]);
    $flowId = (int) $db->lastInsertId();

    $stmtStep = $db->prepare(
        'INSERT INTO assignment_flow_steps (
            flow_id,
            status_id,
            node_kind,
            sort_order,
            is_initial,
            is_active,
            requires_evidence_close,
            step_tags,
            created_at,
            updated_at
         ) VALUES (
            :flow_id,
            :status_id,
            :node_kind,
            :sort_order,
            :is_initial,
            1,
            0,
            :step_tags,
            NOW(),
            NOW()
         )'
    );
    $stmtStep->execute([
        'flow_id' => $flowId,
        'status_id' => $startStatusId,
        'node_kind' => 'activity',
        'sort_order' => 1,
        'is_initial' => 1,
        'step_tags' => 'data_transferencia_efetiva',
    ]);
    $stmtStep->execute([
        'flow_id' => $flowId,
        'status_id' => $nextStatusId,
        'node_kind' => 'activity',
        'sort_order' => 2,
        'is_initial' => 0,
        'step_tags' => '',
    ]);

    $stmtTransition = $db->prepare(
        'INSERT INTO assignment_flow_transitions (
            flow_id,
            from_status_id,
            to_status_id,
            transition_label,
            action_label,
            event_type,
            sort_order,
            is_active,
            created_at,
            updated_at
         ) VALUES (
            :flow_id,
            :from_status_id,
            :to_status_id,
            :transition_label,
            :action_label,
            :event_type,
            1,
            1,
            NOW(),
            NOW()
         )'
    );
    $stmtTransition->execute([
        'flow_id' => $flowId,
        'from_status_id' => $startStatusId,
        'to_status_id' => $nextStatusId,
        'transition_label' => 'Avanco homolog fase 12',
        'action_label' => 'Avancar',
        'event_type' => 'pipeline.homolog_phase12_advance',
    ]);

    return [
        'flow_id' => $flowId,
        'status_start_id' => $startStatusId,
        'status_next_id' => $nextStatusId,
    ];
}

function createMteDestination(PDO $db, string $suffix): int
{
    $code = 'H12' . substr($suffix, 0, 4);
    $code20 = mb_substr($code, 0, 20);
    $stmt = $db->prepare(
        'INSERT INTO mte_destinations (
            name,
            code,
            acronym,
            upag_code,
            parent_uorg_code,
            notes,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :name,
            :code,
            :acronym,
            :upag_code,
            :parent_uorg_code,
            :notes,
            NOW(),
            NOW(),
            NULL
         )'
    );
    $stmt->execute([
        'name' => 'Lotacao homolog fase 12 ' . $suffix,
        'code' => $code,
        'acronym' => $code,
        'upag_code' => $code20,
        'parent_uorg_code' => $code20,
        'notes' => 'Criado automaticamente para homologacao da fase 12.',
    ]);

    return (int) $db->lastInsertId();
}

/**
 * @return array<string, mixed>
 */
function createPersonForHomologation(
    PeopleService $peopleService,
    int $flowId,
    int $organId,
    ?int $modalityId,
    string $namePrefix,
    string $suffix,
    int $userId
): array {
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $cpf = randomCpfDigits();
        $name = $namePrefix . ' ' . $suffix . ' #' . ($attempt + 1);
        $emailSuffix = strtolower($suffix . $attempt);
        $input = [
            'organ_id' => $organId,
            'desired_modality_id' => $modalityId,
            'assignment_flow_id' => $flowId,
            'name' => $name,
            'cpf' => $cpf,
            'birth_date' => '1990-01-01',
            'email' => 'homolog.f12.' . $emailSuffix . '@example.com',
            'phone' => '11999990000',
            'sei_process_number' => 'H12-' . strtoupper($suffix) . '-' . str_pad((string) ($attempt + 1), 2, '0', STR_PAD_LEFT),
            'mte_destination' => null,
            'tags' => 'homolog_fase12',
            'notes' => 'Pessoa temporaria para homologacao automatizada da fase 12.',
        ];

        $result = $peopleService->create($input, $userId, '127.0.0.1', 'phase12-homologation-script');
        if (($result['ok'] ?? false) === true) {
            return $result;
        }

        $errors = implode(' ', is_array($result['errors'] ?? null) ? $result['errors'] : []);
        $normalized = mb_strtolower($errors);
        if (str_contains($normalized, 'cpf')) {
            continue;
        }

        throw new RuntimeException('falha ao criar pessoa: ' . $errors);
    }

    throw new RuntimeException('nao foi possivel criar pessoa com CPF unico para homologacao.');
}

function randomCpfDigits(): string
{
    return str_pad((string) random_int(10000000000, 99999999999), 11, '0', STR_PAD_LEFT);
}

function createCostPlanForPerson(PDO $db, int $personId, int $userId, string $label): int
{
    $stmt = $db->prepare(
        'INSERT INTO cost_plans (
            person_id,
            version_number,
            label,
            is_active,
            created_by,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :person_id,
            1,
            :label,
            1,
            :created_by,
            NOW(),
            NOW(),
            NULL
         )'
    );
    $stmt->execute([
        'person_id' => $personId,
        'label' => $label,
        'created_by' => $userId,
    ]);

    return (int) $db->lastInsertId();
}

function createMonthlyCostItemForPerson(
    PDO $db,
    int $costPlanId,
    int $personId,
    float $amount,
    int $userId
): int {
    $stmt = $db->prepare(
        'INSERT INTO cost_plan_items (
            cost_plan_id,
            person_id,
            item_name,
            cost_type,
            amount,
            start_date,
            end_date,
            notes,
            created_by,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :cost_plan_id,
            :person_id,
            :item_name,
            :cost_type,
            :amount,
            NULL,
            NULL,
            :notes,
            :created_by,
            NOW(),
            NOW(),
            NULL
         )'
    );
    $stmt->execute([
        'cost_plan_id' => $costPlanId,
        'person_id' => $personId,
        'item_name' => 'Custo mensal homolog fase 12',
        'cost_type' => 'mensal',
        'amount' => number_format($amount, 2, '.', ''),
        'notes' => 'Item criado para validar uso de data prevista/efetiva na projecao.',
        'created_by' => $userId,
    ]);

    return (int) $db->lastInsertId();
}

function projectedMonthlyAmountForPerson(PDO $db, int $personId, string $monthStart, string $monthEnd): float
{
    $stmt = $db->prepare(
        'SELECT IFNULL(SUM(
            CASE
                WHEN cpi.cost_type = "mensal"
                     AND (cpi.start_date IS NULL OR cpi.start_date <= :month_end)
                     AND (cpi.end_date IS NULL OR cpi.end_date >= :month_start)
                     AND (COALESCE(a.effective_start_date, a.target_start_date) IS NULL OR COALESCE(a.effective_start_date, a.target_start_date) <= :month_end_gate)
                     AND (COALESCE(a.effective_end_date, a.requested_end_date) IS NULL OR COALESCE(a.effective_end_date, a.requested_end_date) >= :month_start_gate)
                THEN cpi.amount
                ELSE 0
            END
         ), 0) AS total
         FROM cost_plans cp
         INNER JOIN people p ON p.id = cp.person_id AND p.deleted_at IS NULL
         LEFT JOIN assignments a ON a.person_id = p.id AND a.deleted_at IS NULL
         INNER JOIN cost_plan_items cpi ON cpi.cost_plan_id = cp.id AND cpi.deleted_at IS NULL
         WHERE cp.deleted_at IS NULL
           AND cp.is_active = 1
           AND cp.person_id = :person_id'
    );
    $stmt->execute([
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
        'month_start_gate' => $monthStart,
        'month_end_gate' => $monthEnd,
        'person_id' => $personId,
    ]);

    return (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.0);
}

function cleanupCreatedData(PDO $db, array $created): void
{
    $personIds = array_values(array_filter(array_map('intval', $created['person_ids'] ?? [])));
    $costItemIds = array_values(array_filter(array_map('intval', $created['cost_item_ids'] ?? [])));
    $costPlanIds = array_values(array_filter(array_map('intval', $created['cost_plan_ids'] ?? [])));
    $statusIds = array_values(array_filter(array_map('intval', $created['status_ids'] ?? [])));
    $flowId = isset($created['flow_id']) ? (int) $created['flow_id'] : 0;
    $mteDestinationCreated = ($created['mte_destination_created'] ?? false) === true;
    $mteDestinationId = isset($created['mte_destination_id']) ? (int) $created['mte_destination_id'] : 0;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }

        if ($personIds !== []) {
            deleteWhereIn($db, 'timeline_event_attachments', 'person_id', $personIds);
            deleteWhereIn($db, 'timeline_event_links', 'person_id', $personIds);
            deleteWhereIn($db, 'timeline_events', 'person_id', $personIds);
            deleteWhereIn($db, 'reimbursement_entries', 'person_id', $personIds);
            deleteWhereIn($db, 'process_comments', 'person_id', $personIds);
            deleteWhereIn($db, 'process_admin_timeline_notes', 'person_id', $personIds);
            deleteWhereIn($db, 'documents', 'person_id', $personIds);
            deleteWhereIn($db, 'assignments', 'person_id', $personIds);
        }

        if ($costItemIds !== []) {
            deleteWhereIn($db, 'cost_plan_items', 'id', $costItemIds);
        }
        if ($costPlanIds !== []) {
            deleteWhereIn($db, 'cost_plans', 'id', $costPlanIds);
        }
        if ($personIds !== []) {
            deleteWhereIn($db, 'people', 'id', $personIds);
        }

        if ($flowId > 0) {
            deleteWhereIn($db, 'assignment_flow_transitions', 'flow_id', [$flowId]);
            deleteWhereIn($db, 'assignment_flow_steps', 'flow_id', [$flowId]);
            deleteWhereIn($db, 'assignment_flows', 'id', [$flowId]);
        }
        if ($statusIds !== []) {
            deleteWhereIn($db, 'assignment_statuses', 'id', $statusIds);
        }

        if ($mteDestinationCreated && $mteDestinationId > 0) {
            deleteWhereIn($db, 'mte_destinations', 'id', [$mteDestinationId]);
        }

        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

/**
 * @param array<int, int> $values
 */
function deleteWhereIn(PDO $db, string $table, string $column, array $values): void
{
    $filtered = array_values(array_filter(array_map('intval', $values), static fn (int $v): bool => $v > 0));
    if ($filtered === []) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($filtered), '?'));
    $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' IN (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    foreach ($filtered as $index => $value) {
        $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
}

function firstInt(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row[array_key_first($row ?: ['v' => 0])] ?? 0);
}

function resolveYear(?int $year): int
{
    if ($year !== null && $year >= 2000 && $year <= 2100) {
        return $year;
    }

    return (int) date('Y');
}

function hasKeys(array $payload, array $keys): bool
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $payload)) {
            return false;
        }
    }

    return true;
}

function addAssertion(
    array &$assertions,
    string $name,
    bool $pass,
    string $expected,
    string $actual,
    string $details
): void {
    $assertions[] = [
        'name' => $name,
        'pass' => $pass,
        'expected' => $expected,
        'actual' => $actual,
        'details' => $details,
    ];
}

/**
 * @param array<string, mixed> $payload
 */
function output(string $mode, array $payload): void
{
    if ($mode === 'json') {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            fail('falha ao serializar saida json.');
        }
        fwrite(STDOUT, $json . PHP_EOL);

        return;
    }

    fwrite(STDOUT, '[phase12-homologation] status: ' . (string) ($payload['status'] ?? 'unknown') . PHP_EOL);
    $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
    fwrite(
        STDOUT,
        sprintf(
            '[phase12-homologation] assertions: total=%d failed=%d',
            (int) ($totals['assertions_total'] ?? 0),
            (int) ($totals['assertions_failed'] ?? 0)
        ) . PHP_EOL
    );
    fwrite(STDOUT, '[phase12-homologation] details:' . PHP_EOL);

    $assertions = is_array($payload['assertions'] ?? null) ? $payload['assertions'] : [];
    foreach ($assertions as $assertion) {
        $ok = (($assertion['pass'] ?? false) === true);
        $marker = $ok ? 'ok' : 'erro';
        $name = (string) ($assertion['name'] ?? 'assertion');
        $expected = (string) ($assertion['expected'] ?? '');
        $actual = (string) ($assertion['actual'] ?? '');
        $details = (string) ($assertion['details'] ?? '');

        fwrite(STDOUT, sprintf(' - [%s] %s | expected=%s | actual=%s', $marker, $name, $expected, $actual) . PHP_EOL);
        if ($details !== '') {
            fwrite(STDOUT, '   ' . $details . PHP_EOL);
        }
    }
}

function printUsage(): void
{
    $usage = <<<TXT
Uso:
  ./scripts/homologate-phase12-melhorias1.php [opcoes]

Opcoes:
  --output <table|json>  Formato de saida (padrao: table)
  --year <YYYY>          Ano de referencia para validar payload do dashboard
  --keep-data            Nao remove os dados temporarios gerados pelo script
  --help                 Exibe esta ajuda
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[erro] ' . $message . PHP_EOL);
    exit(1);
}

/**
 * @return \App\Services\LgpdService
 */
function lgpdService(App $app)
{
    return new \App\Services\LgpdService(
        new \App\Repositories\LgpdRepository($app->db()),
        $app->audit(),
        $app->events()
    );
}

/**
 * @return \App\Services\SecuritySettingsService
 */
function securityService(App $app)
{
    return new \App\Services\SecuritySettingsService(
        new \App\Repositories\SecuritySettingsRepository($app->db()),
        $app->config(),
        $app->audit(),
        $app->events()
    );
}
