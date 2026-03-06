#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repositories\BudgetRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\LgpdRepository;
use App\Repositories\PeopleRepository;
use App\Repositories\PipelineRepository;
use App\Repositories\ReimbursementRepository;
use App\Repositories\SecuritySettingsRepository;
use App\Services\AuditService;
use App\Services\BudgetService;
use App\Services\EventService;
use App\Services\InvoiceService;
use App\Services\LgpdService;
use App\Services\PeopleService;
use App\Services\PipelineService;
use App\Services\ReimbursementService;
use App\Services\SecuritySettingsService;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/Core/autoload.php';

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'phase10-homologation-script';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "[erro] {$message}\n");
    exit($code);
}

function step(string $message): void
{
    echo "[step] {$message}\n";
}

/**
 * @param array<int, string> $argv
 */
function parseOptions(array $argv): array
{
    $options = [
        'year' => null,
    ];

    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $arg, 2), 2, null);
        if ($key === '--year' && $value !== null && ctype_digit($value)) {
            $year = (int) $value;
            if ($year >= 2000 && $year <= 2100) {
                $options['year'] = $year;
            }
        }
    }

    return $options;
}

/**
 * @return array<string, mixed>
 */
function firstRow(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $compactSql = preg_replace('/\s+/', ' ', trim($sql));
        throw new RuntimeException(
            $exception->getMessage() . ' | SQL: ' . (is_string($compactSql) ? $compactSql : $sql),
            0,
            $exception
        );
    }

    return is_array($row) ? $row : [];
}

function ensureMteDestination(PDO $db, string $code, string $name): int
{
    $existing = firstRow(
        $db,
        'SELECT id
         FROM mte_destinations
         WHERE code = :code
           AND deleted_at IS NULL
         LIMIT 1',
        ['code' => $code]
    );
    if (isset($existing['id'])) {
        return (int) $existing['id'];
    }

    $stmt = $db->prepare(
        'INSERT INTO mte_destinations (
            name,
            code,
            notes,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :name,
            :code,
            :notes,
            NOW(),
            NOW(),
            NULL
         )'
    );
    $stmt->execute([
        'name' => $name,
        'code' => $code,
        'notes' => 'Criado automaticamente pela homologacao da fase 10.',
    ]);

    return (int) $db->lastInsertId();
}

/**
 * @return array{trail: array<int, string>, final_assignment: array<string, mixed>}
 */
function advanceFlowFully(PipelineRepository $pipelineRepository, int $personId): array
{
    $trail = [];

    for ($attempt = 0; $attempt < 40; $attempt++) {
        $assignment = $pipelineRepository->assignmentByPersonId($personId);
        if ($assignment === null) {
            throw new RuntimeException('Movimentacao nao encontrada para avancar o fluxo.');
        }

        $currentCode = (string) ($assignment['current_status_code'] ?? '');
        if ($currentCode !== '') {
            $trail[] = $currentCode;
        }

        $flowId = (int) ($assignment['flow_id'] ?? 0);
        $currentStatusId = (int) ($assignment['current_status_id'] ?? 0);
        if ($flowId <= 0 || $currentStatusId <= 0) {
            throw new RuntimeException('Movimentacao sem fluxo/status valido para avancar.');
        }

        $transitions = $pipelineRepository->transitionsFromStatus($flowId, $currentStatusId);
        if ($transitions === []) {
            return [
                'trail' => $trail,
                'final_assignment' => $assignment,
            ];
        }

        $toStatusId = (int) ($transitions[0]['to_status_id'] ?? 0);
        $toStatusCode = (string) ($transitions[0]['to_code'] ?? '');
        if ($toStatusId <= 0 || $toStatusCode === '') {
            throw new RuntimeException('Transicao invalida ao avancar o fluxo.');
        }

        $updatedAssignment = $pipelineRepository->updateAssignmentStatus(
            assignmentId: (int) ($assignment['id'] ?? 0),
            statusId: $toStatusId,
            effectiveStartDate: null
        );
        if (!$updatedAssignment) {
            throw new RuntimeException('Falha ao atualizar status da movimentacao.');
        }

        $updatedPerson = $pipelineRepository->updatePersonStatus($personId, $toStatusCode);
        if (!$updatedPerson) {
            throw new RuntimeException('Falha ao atualizar status da pessoa no fluxo.');
        }
    }

    throw new RuntimeException('Limite de iteracoes atingido ao avancar o fluxo.');
}

function chooseIsolatedYear(PDO $db, ?int $preferred): int
{
    if ($preferred !== null) {
        return $preferred;
    }

    for ($year = 2099; $year >= 2050; $year--) {
        $stmt = $db->prepare(
            'SELECT
                (
                    (SELECT COUNT(*) FROM invoices i
                     WHERE i.deleted_at IS NULL
                       AND YEAR(i.reference_month) = :year_a)
                    +
                    (SELECT COUNT(*) FROM payments p
                     WHERE p.deleted_at IS NULL
                       AND YEAR(p.payment_date) = :year_b)
                    +
                    (SELECT COUNT(*) FROM reimbursement_entries r
                     WHERE r.deleted_at IS NULL
                       AND YEAR(COALESCE(r.reference_month, DATE(r.paid_at), DATE(r.created_at))) = :year_c)
                    +
                    (SELECT COUNT(*) FROM budget_cycles bc
                     WHERE bc.deleted_at IS NULL
                       AND bc.cycle_year = :year_d)
                ) AS total'
        );
        $stmt->execute([
            'year_a' => $year,
            'year_b' => $year,
            'year_c' => $year,
            'year_d' => $year,
        ]);

        $total = (int) ($stmt->fetchColumn() ?: 0);
        if ($total === 0) {
            return $year;
        }
    }

    return (int) date('Y');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function createPersonWithUniqueCpf(
    PeopleService $peopleService,
    array $input,
    int $userId,
    string $ip,
    string $userAgent,
    string $emailPrefix,
    string $seiPrefix
): array {
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $cpfDigits = str_pad((string) random_int(10000000000, 99999999999), 11, '0', STR_PAD_LEFT);
        $suffix = substr($cpfDigits, -6) . $attempt;

        $candidate = $input;
        $candidate['cpf'] = $cpfDigits;
        $candidate['email'] = strtolower($emailPrefix . '.' . $suffix . '@example.com');
        $candidate['sei_process_number'] = strtoupper($seiPrefix) . '-' . $suffix;

        $result = $peopleService->create($candidate, $userId, $ip, $userAgent);
        if (($result['ok'] ?? false) === true) {
            $result['generated_cpf_digits'] = $cpfDigits;
            $result['generated_email'] = (string) $candidate['email'];
            $result['generated_sei'] = (string) $candidate['sei_process_number'];

            return $result;
        }

        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $joined = mb_strtolower(implode(' ', $errors));
        if (str_contains($joined, 'ja existe pessoa cadastrada com este cpf') || str_contains($joined, 'já existe pessoa cadastrada com este cpf')) {
            continue;
        }

        throw new RuntimeException('Falha ao criar pessoa: ' . implode(' ', $errors));
    }

    throw new RuntimeException('Nao foi possivel gerar CPF unico para pessoa de teste.');
}

try {
    $options = parseOptions($argv);

    Env::load(BASE_PATH . '/.env');
    $config = Config::load();
    date_default_timezone_set((string) $config->get('app.timezone', 'America/Sao_Paulo'));

    $db = Database::connect($config);
    step('Conexao com banco estabelecida');

    $audit = new AuditService($db);
    $events = new EventService($db, $audit);
    $lgpd = new LgpdService(new LgpdRepository($db), $audit, $events);
    $security = new SecuritySettingsService(new SecuritySettingsRepository($db), $config, $audit, $events);

    $peopleService = new PeopleService(new PeopleRepository($db), $audit, $events);
    $pipelineRepository = new PipelineRepository($db);
    $pipelineService = new PipelineService(
        $pipelineRepository,
        $audit,
        $events,
        $config,
        $lgpd,
        $security
    );
    $invoiceService = new InvoiceService(
        new InvoiceRepository($db),
        $audit,
        $events,
        $config,
        $lgpd,
        $security
    );
    $reimbursementService = new ReimbursementService(new ReimbursementRepository($db), $audit, $events);
    $budgetRepository = new BudgetRepository($db);
    $budgetService = new BudgetService($budgetRepository, $audit, $events);
    step('Servicos inicializados');

    $runId = date('YmdHis');
    $tag = 'PH10-' . $runId;
    $ip = '127.0.0.1';
    $userAgent = 'phase10-homologation-script';
    $testYear = chooseIsolatedYear($db, is_int($options['year']) ? $options['year'] : null);

    $migration043 = firstRow(
        $db,
        'SELECT migration, executed_at
         FROM migrations
         WHERE migration = :migration
         LIMIT 1',
        ['migration' => '043_phase10_dual_flow_financial_segregation.sql']
    );

    $user = firstRow(
        $db,
        'SELECT id, name
         FROM users
         WHERE deleted_at IS NULL
         ORDER BY id ASC
         LIMIT 1'
    );
    if (!isset($user['id'])) {
        fail('Nenhum usuario ativo encontrado para executar a homologacao.');
    }
    $userId = (int) $user['id'];

    $modality = firstRow(
        $db,
        'SELECT id, name
         FROM modalities
         WHERE is_active = 1
         ORDER BY id ASC
         LIMIT 1'
    );
    if (!isset($modality['id'])) {
        fail('Nenhuma modalidade ativa encontrada.');
    }
    $modalityId = (int) $modality['id'];

    $flowDefault = firstRow(
        $db,
        'SELECT id, name
         FROM assignment_flows
         WHERE deleted_at IS NULL
           AND is_active = 1
         ORDER BY is_default DESC, id ASC
         LIMIT 1'
    );
    if (!isset($flowDefault['id'])) {
        fail('Fluxo padrao nao encontrado.');
    }

    $flowSaida = firstRow(
        $db,
        'SELECT id, name
         FROM assignment_flows
         WHERE deleted_at IS NULL
           AND is_active = 1
           AND name = :name
         ORDER BY id ASC
         LIMIT 1',
        ['name' => 'Fluxo saida MTE']
    );
    if (!isset($flowSaida['id'])) {
        fail('Fluxo "Fluxo saida MTE" nao encontrado.');
    }

    $organsStmt = $db->query(
        'SELECT id, acronym, name
         FROM organs
         WHERE deleted_at IS NULL
         ORDER BY id ASC
         LIMIT 2'
    );
    $organs = $organsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($organs) || count($organs) < 2) {
        fail('Sao necessarios ao menos 2 orgaos ativos para homologacao.');
    }

    $organEntrada = $organs[0];
    $organSaida = $organs[1];
    $organEntradaId = (int) ($organEntrada['id'] ?? 0);
    $organSaidaId = (int) ($organSaida['id'] ?? 0);
    if ($organEntradaId <= 0 || $organSaidaId <= 0) {
        fail('Orgaos invalidos para homologacao.');
    }

    $mteDestinationEntradaCode = 'QA-ENT-' . $runId;
    $mteDestinationSaidaCode = 'QA-SAI-' . $runId;
    $mteDestinationEntradaName = 'QA Destino Entrada ' . $runId;
    $mteDestinationSaidaName = 'QA Origem Saida ' . $runId;

    $mteDestinationEntradaId = ensureMteDestination($db, $mteDestinationEntradaCode, $mteDestinationEntradaName);
    $mteDestinationSaidaId = ensureMteDestination($db, $mteDestinationSaidaCode, $mteDestinationSaidaName);
    step('Contexto base carregado (usuario, fluxo, orgaos, lotacoes)');

    $personEntradaInput = [
        'organ_id' => $organEntradaId,
        'desired_modality_id' => $modalityId,
        'assignment_flow_id' => (int) $flowDefault['id'],
        'name' => 'QA Entrada ' . $runId,
        'cpf' => '',
        'birth_date' => '1990-01-01',
        'email' => '',
        'phone' => '61999990001',
        'sei_process_number' => '',
        'mte_destination' => $mteDestinationEntradaName,
        'tags' => 'qa,phase10,entrada',
        'notes' => 'Homologacao fase 10 - entrada',
    ];
    $personEntradaResult = createPersonWithUniqueCpf(
        peopleService: $peopleService,
        input: $personEntradaInput,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent,
        emailPrefix: 'qa.entrada.' . $runId,
        seiPrefix: 'SEI-QA-ENT-' . $runId
    );
    if (!isset($personEntradaResult['id'])) {
        fail('Falha ao criar pessoa de entrada.');
    }
    $personEntradaId = (int) $personEntradaResult['id'];

    $personSaidaInput = [
        'organ_id' => $organSaidaId,
        'desired_modality_id' => $modalityId,
        'assignment_flow_id' => (int) $flowSaida['id'],
        'name' => 'QA Saida ' . $runId,
        'cpf' => '',
        'birth_date' => '1991-02-02',
        'email' => '',
        'phone' => '61999990002',
        'sei_process_number' => '',
        'mte_destination' => $mteDestinationSaidaName,
        'tags' => 'qa,phase10,saida',
        'notes' => 'Homologacao fase 10 - saida',
    ];
    $personSaidaResult = createPersonWithUniqueCpf(
        peopleService: $peopleService,
        input: $personSaidaInput,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent,
        emailPrefix: 'qa.saida.' . $runId,
        seiPrefix: 'SEI-QA-SAI-' . $runId
    );
    if (!isset($personSaidaResult['id'])) {
        fail('Falha ao criar pessoa de saida.');
    }
    $personSaidaId = (int) $personSaidaResult['id'];
    step('Pessoas de teste criadas');

    $movementEntrada = [
        'movement_direction' => 'entrada_mte',
        'financial_nature' => 'despesa_reembolso',
        'counterparty_organ_id' => $organEntradaId,
        'destination_mte_destination_id' => $mteDestinationEntradaId,
    ];
    $movementSaida = [
        'movement_direction' => 'saida_mte',
        'financial_nature' => 'receita_reembolso',
        'counterparty_organ_id' => $organSaidaId,
        'origin_mte_destination_id' => $mteDestinationSaidaId,
    ];

    $validationEntrada = $pipelineService->validateMovementContext($movementEntrada);
    if (($validationEntrada['ok'] ?? false) !== true) {
        $errors = is_array($validationEntrada['errors'] ?? null) ? $validationEntrada['errors'] : [];
        fail('Contexto de movimento de entrada invalido. ' . implode(' ', $errors));
    }

    $validationSaida = $pipelineService->validateMovementContext($movementSaida);
    if (($validationSaida['ok'] ?? false) !== true) {
        $errors = is_array($validationSaida['errors'] ?? null) ? $validationSaida['errors'] : [];
        fail('Contexto de movimento de saida invalido. ' . implode(' ', $errors));
    }

    $assignmentEntrada = $pipelineService->ensureAssignment(
        personId: $personEntradaId,
        modalityId: $modalityId,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent,
        movementContext: $movementEntrada
    );
    if (!is_array($assignmentEntrada) || !isset($assignmentEntrada['id'])) {
        fail('Falha ao inicializar movimentacao da pessoa de entrada.');
    }

    $assignmentSaida = $pipelineService->ensureAssignment(
        personId: $personSaidaId,
        modalityId: $modalityId,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent,
        movementContext: $movementSaida
    );
    if (!is_array($assignmentSaida) || !isset($assignmentSaida['id'])) {
        fail('Falha ao inicializar movimentacao da pessoa de saida.');
    }
    step('Movimentacoes inicializadas');

    $entradaFlow = advanceFlowFully($pipelineRepository, $personEntradaId);
    $saidaFlow = advanceFlowFully($pipelineRepository, $personSaidaId);
    step('Fluxos avancados ate etapa final');

    $entradaFinalCode = (string) ($entradaFlow['final_assignment']['current_status_code'] ?? '');
    $saidaFinalCode = (string) ($saidaFlow['final_assignment']['current_status_code'] ?? '');
    if ($entradaFinalCode !== 'ativo') {
        fail('Fluxo de entrada nao chegou ao status final esperado (ativo).');
    }
    if ($saidaFinalCode !== 'saida_encerrado') {
        fail('Fluxo de saida nao chegou ao status final esperado (saida_encerrado).');
    }

    $referenceMonth = sprintf('%04d-03', $testYear);
    $referenceMonthDate = sprintf('%04d-03-01', $testYear);
    $issueDate = sprintf('%04d-03-01', $testYear);
    $dueDate = sprintf('%04d-03-20', $testYear);
    $paymentDate = sprintf('%04d-03-18', $testYear);
    $paidAtDateTime = sprintf('%04d-03-18 10:00:00', $testYear);

    $reimbursementEntradaPaid = $reimbursementService->createEntry(
        personId: $personEntradaId,
        input: [
            'entry_type' => 'boleto',
            'status' => 'pago',
            'title' => 'QA Reembolso Despesa Pago ' . $runId,
            'amount' => '1500.00',
            'reference_month' => $referenceMonth,
            'due_date' => $dueDate,
            'paid_at' => $paidAtDateTime,
            'notes' => 'Entrada despesa paga - ' . $tag,
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($reimbursementEntradaPaid['ok'] ?? false) !== true) {
        $errors = is_array($reimbursementEntradaPaid['errors'] ?? null) ? $reimbursementEntradaPaid['errors'] : [];
        fail('Falha ao criar reembolso de entrada (pago). ' . implode(' ', $errors));
    }

    $reimbursementEntradaPending = $reimbursementService->createEntry(
        personId: $personEntradaId,
        input: [
            'entry_type' => 'boleto',
            'status' => 'pendente',
            'title' => 'QA Reembolso Despesa Pendente ' . $runId,
            'amount' => '250.00',
            'reference_month' => $referenceMonth,
            'due_date' => $dueDate,
            'notes' => 'Entrada despesa pendente - ' . $tag,
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($reimbursementEntradaPending['ok'] ?? false) !== true) {
        $errors = is_array($reimbursementEntradaPending['errors'] ?? null) ? $reimbursementEntradaPending['errors'] : [];
        fail('Falha ao criar reembolso de entrada (pendente). ' . implode(' ', $errors));
    }
    step('Reembolsos de entrada registrados');

    $reimbursementEntradaRows = $db->prepare(
        'SELECT id, financial_nature, status, amount
         FROM reimbursement_entries
         WHERE person_id = :person_id
           AND title IN (:title_paid, :title_pending)
           AND deleted_at IS NULL
         ORDER BY id ASC'
    );
    $reimbursementEntradaRows->execute([
        'person_id' => $personEntradaId,
        'title_paid' => 'QA Reembolso Despesa Pago ' . $runId,
        'title_pending' => 'QA Reembolso Despesa Pendente ' . $runId,
    ]);
    $entradaReimbursements = $reimbursementEntradaRows->fetchAll(PDO::FETCH_ASSOC);
    if (count($entradaReimbursements) !== 2) {
        fail('Nao foi possivel localizar os 2 reembolsos de entrada criados.');
    }

    $insertReimbursementSaida = $db->prepare(
        'INSERT INTO reimbursement_entries (
            person_id,
            assignment_id,
            entry_type,
            financial_nature,
            status,
            title,
            amount,
            reference_month,
            due_date,
            paid_at,
            notes,
            calculation_memory,
            created_by,
            created_at,
            updated_at,
            deleted_at
         ) VALUES (
            :person_id,
            :assignment_id,
            :entry_type,
            :financial_nature,
            :status,
            :title,
            :amount,
            :reference_month,
            :due_date,
            :paid_at,
            :notes,
            NULL,
            :created_by,
            NOW(),
            NOW(),
            NULL
         )'
    );

    $insertReimbursementSaida->execute([
        'person_id' => $personSaidaId,
        'assignment_id' => (int) ($assignmentSaida['id'] ?? 0),
        'entry_type' => 'boleto',
        'financial_nature' => 'receita_reembolso',
        'status' => 'pago',
        'title' => 'QA Reembolso Receita Pago ' . $runId,
        'amount' => '1700.00',
        'reference_month' => $referenceMonthDate,
        'due_date' => $dueDate,
        'paid_at' => $paidAtDateTime,
        'notes' => 'Saida receita paga - ' . $tag,
        'created_by' => $userId,
    ]);
    $reimbursementSaidaPaidId = (int) $db->lastInsertId();

    $insertReimbursementSaida->execute([
        'person_id' => $personSaidaId,
        'assignment_id' => (int) ($assignmentSaida['id'] ?? 0),
        'entry_type' => 'boleto',
        'financial_nature' => 'receita_reembolso',
        'status' => 'pendente',
        'title' => 'QA Reembolso Receita Pendente ' . $runId,
        'amount' => '300.00',
        'reference_month' => $referenceMonthDate,
        'due_date' => $dueDate,
        'paid_at' => null,
        'notes' => 'Saida receita pendente - ' . $tag,
        'created_by' => $userId,
    ]);
    $reimbursementSaidaPendingId = (int) $db->lastInsertId();
    step('Reembolsos de saida registrados');

    $invoiceEntrada = $invoiceService->create(
        input: [
            'organ_id' => $organEntradaId,
            'invoice_number' => 'QA-INV-DESP-' . $runId,
            'title' => 'QA Boleto Despesa ' . $runId,
            'reference_month' => $referenceMonth,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => '2000.00',
            'status' => 'aberto',
            'financial_nature' => 'despesa_reembolso',
            'notes' => 'Teste de segregacao despesa - ' . $tag,
        ],
        file: null,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($invoiceEntrada['ok'] ?? false) !== true || !isset($invoiceEntrada['id'])) {
        $errors = is_array($invoiceEntrada['errors'] ?? null) ? $invoiceEntrada['errors'] : [];
        fail('Falha ao criar boleto de despesa. ' . implode(' ', $errors));
    }
    $invoiceEntradaId = (int) $invoiceEntrada['id'];

    $invoiceSaida = $invoiceService->create(
        input: [
            'organ_id' => $organSaidaId,
            'invoice_number' => 'QA-INV-REC-' . $runId,
            'title' => 'QA Boleto Receita ' . $runId,
            'reference_month' => $referenceMonth,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => '1800.00',
            'status' => 'aberto',
            'financial_nature' => 'receita_reembolso',
            'notes' => 'Teste de segregacao receita - ' . $tag,
        ],
        file: null,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($invoiceSaida['ok'] ?? false) !== true || !isset($invoiceSaida['id'])) {
        $errors = is_array($invoiceSaida['errors'] ?? null) ? $invoiceSaida['errors'] : [];
        fail('Falha ao criar boleto de receita. ' . implode(' ', $errors));
    }
    $invoiceSaidaId = (int) $invoiceSaida['id'];
    step('Boletos de despesa e receita criados');

    $linkEntrada = $invoiceService->linkPerson(
        invoiceId: $invoiceEntradaId,
        input: [
            'person_id' => $personEntradaId,
            'allocated_amount' => '2000.00',
            'notes' => 'Rateio QA despesa',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($linkEntrada['ok'] ?? false) !== true) {
        $errors = is_array($linkEntrada['errors'] ?? null) ? $linkEntrada['errors'] : [];
        fail('Falha ao vincular pessoa ao boleto de despesa. ' . implode(' ', $errors));
    }

    $linkSaida = $invoiceService->linkPerson(
        invoiceId: $invoiceSaidaId,
        input: [
            'person_id' => $personSaidaId,
            'allocated_amount' => '1800.00',
            'notes' => 'Rateio QA receita',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($linkSaida['ok'] ?? false) !== true) {
        $errors = is_array($linkSaida['errors'] ?? null) ? $linkSaida['errors'] : [];
        fail('Falha ao vincular pessoa ao boleto de receita. ' . implode(' ', $errors));
    }
    step('Rateios de boletos realizados');

    $paymentEntrada = $invoiceService->registerPayment(
        invoiceId: $invoiceEntradaId,
        input: [
            'payment_date' => $paymentDate,
            'amount' => '1200.00',
            'process_reference' => 'PAG-QA-DESP-' . $runId,
            'notes' => 'Baixa parcial despesa',
        ],
        file: null,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($paymentEntrada['ok'] ?? false) !== true) {
        $errors = is_array($paymentEntrada['errors'] ?? null) ? $paymentEntrada['errors'] : [];
        fail('Falha ao registrar pagamento de despesa. ' . implode(' ', $errors));
    }

    $paymentSaida = $invoiceService->registerPayment(
        invoiceId: $invoiceSaidaId,
        input: [
            'payment_date' => $paymentDate,
            'amount' => '1000.00',
            'process_reference' => 'PAG-QA-REC-' . $runId,
            'notes' => 'Baixa parcial receita',
        ],
        file: null,
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($paymentSaida['ok'] ?? false) !== true) {
        $errors = is_array($paymentSaida['errors'] ?? null) ? $paymentSaida['errors'] : [];
        fail('Falha ao registrar pagamento de receita. ' . implode(' ', $errors));
    }
    step('Pagamentos de boletos registrados');

    $paymentEntradaRow = firstRow(
        $db,
        'SELECT id, financial_nature, amount
         FROM payments
         WHERE invoice_id = :invoice_id
           AND process_reference = :process_reference
           AND deleted_at IS NULL
         ORDER BY id DESC
         LIMIT 1',
        [
            'invoice_id' => $invoiceEntradaId,
            'process_reference' => 'PAG-QA-DESP-' . $runId,
        ]
    );
    $paymentSaidaRow = firstRow(
        $db,
        'SELECT id, financial_nature, amount
         FROM payments
         WHERE invoice_id = :invoice_id
           AND process_reference = :process_reference
           AND deleted_at IS NULL
         ORDER BY id DESC
         LIMIT 1',
        [
            'invoice_id' => $invoiceSaidaId,
            'process_reference' => 'PAG-QA-REC-' . $runId,
        ]
    );
    if (!isset($paymentEntradaRow['id']) || !isset($paymentSaidaRow['id'])) {
        fail('Falha ao localizar pagamentos registrados.');
    }

    $batchEntrada = $invoiceService->createPaymentBatch(
        input: [
            'title' => 'QA Lote Despesa ' . $runId,
            'reference_month' => $referenceMonth,
            'scheduled_payment_date' => $paymentDate,
            'notes' => 'Lote QA despesa',
            'payment_ids' => [(int) $paymentEntradaRow['id']],
            'financial_nature' => 'despesa_reembolso',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($batchEntrada['ok'] ?? false) !== true || !isset($batchEntrada['id'])) {
        $errors = is_array($batchEntrada['errors'] ?? null) ? $batchEntrada['errors'] : [];
        fail('Falha ao criar lote de pagamento de despesa. ' . implode(' ', $errors));
    }
    $batchEntradaId = (int) $batchEntrada['id'];

    $batchSaida = $invoiceService->createPaymentBatch(
        input: [
            'title' => 'QA Lote Receita ' . $runId,
            'reference_month' => $referenceMonth,
            'scheduled_payment_date' => $paymentDate,
            'notes' => 'Lote QA receita',
            'payment_ids' => [(int) $paymentSaidaRow['id']],
            'financial_nature' => 'receita_reembolso',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($batchSaida['ok'] ?? false) !== true || !isset($batchSaida['id'])) {
        $errors = is_array($batchSaida['errors'] ?? null) ? $batchSaida['errors'] : [];
        fail('Falha ao criar lote de pagamento de receita. ' . implode(' ', $errors));
    }
    $batchSaidaId = (int) $batchSaida['id'];
    step('Lotes de pagamento criados');

    $cycleDespesaCreate = $budgetService->createAnnualBudgetCycle(
        input: [
            'cycle_year' => $testYear,
            'financial_nature' => 'despesa_reembolso',
            'cycle_total_budget' => '500000.00',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($cycleDespesaCreate['ok'] ?? false) !== true) {
        $errors = is_array($cycleDespesaCreate['errors'] ?? null) ? $cycleDespesaCreate['errors'] : [];
        $joined = implode(' ', $errors);
        if (!str_contains(mb_strtolower($joined), 'ja existe ciclo')) {
            fail('Falha ao criar ciclo de despesa. ' . $joined);
        }
    }

    $cycleReceitaCreate = $budgetService->createAnnualBudgetCycle(
        input: [
            'cycle_year' => $testYear,
            'financial_nature' => 'receita_reembolso',
            'cycle_total_budget' => '400000.00',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($cycleReceitaCreate['ok'] ?? false) !== true) {
        $errors = is_array($cycleReceitaCreate['errors'] ?? null) ? $cycleReceitaCreate['errors'] : [];
        $joined = implode(' ', $errors);
        if (!str_contains(mb_strtolower($joined), 'ja existe ciclo')) {
            fail('Falha ao criar ciclo de receita. ' . $joined);
        }
    }

    $scenarioParamDespesa = $budgetService->upsertScenarioParameter(
        year: $testYear,
        input: [
            'financial_nature' => 'despesa_reembolso',
            'organ_id' => $organEntradaId,
            'modality' => 'cessao',
            'base_variation_percent' => '2.00',
            'updated_variation_percent' => '8.00',
            'worst_variation_percent' => '15.00',
            'notes' => 'QA parametro despesa ' . $tag,
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($scenarioParamDespesa['ok'] ?? false) !== true) {
        $errors = is_array($scenarioParamDespesa['errors'] ?? null) ? $scenarioParamDespesa['errors'] : [];
        fail('Falha ao salvar parametro de cenario despesa. ' . implode(' ', $errors));
    }

    $scenarioParamReceita = $budgetService->upsertScenarioParameter(
        year: $testYear,
        input: [
            'financial_nature' => 'receita_reembolso',
            'organ_id' => $organSaidaId,
            'modality' => 'cessao',
            'base_variation_percent' => '1.00',
            'updated_variation_percent' => '6.00',
            'worst_variation_percent' => '12.00',
            'notes' => 'QA parametro receita ' . $tag,
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($scenarioParamReceita['ok'] ?? false) !== true) {
        $errors = is_array($scenarioParamReceita['errors'] ?? null) ? $scenarioParamReceita['errors'] : [];
        fail('Falha ao salvar parametro de cenario receita. ' . implode(' ', $errors));
    }

    $simulationDespesa = $budgetService->simulate(
        year: $testYear,
        input: [
            'financial_nature' => 'despesa_reembolso',
            'organ_id' => $organEntradaId,
            'modality' => 'cessao',
            'movement_type' => 'entrada',
            'cargo' => 'analista',
            'setor' => 'setor_qa',
            'entry_date' => sprintf('%04d-06-01', $testYear),
            'quantity' => 1,
            'avg_monthly_cost' => '7000.00',
            'scenario_name' => 'QA Simulacao Despesa ' . $runId,
            'notes' => 'Simulacao QA despesa',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($simulationDespesa['ok'] ?? false) !== true) {
        $errors = is_array($simulationDespesa['errors'] ?? null) ? $simulationDespesa['errors'] : [];
        fail('Falha na simulacao de despesa. ' . implode(' ', $errors));
    }

    $simulationReceita = $budgetService->simulate(
        year: $testYear,
        input: [
            'financial_nature' => 'receita_reembolso',
            'organ_id' => $organSaidaId,
            'modality' => 'cessao',
            'movement_type' => 'saida',
            'cargo' => 'analista',
            'setor' => 'setor_qa',
            'entry_date' => sprintf('%04d-06-01', $testYear),
            'quantity' => 1,
            'avg_monthly_cost' => '6500.00',
            'scenario_name' => 'QA Simulacao Receita ' . $runId,
            'notes' => 'Simulacao QA receita',
        ],
        userId: $userId,
        ip: $ip,
        userAgent: $userAgent
    );
    if (($simulationReceita['ok'] ?? false) !== true) {
        $errors = is_array($simulationReceita['errors'] ?? null) ? $simulationReceita['errors'] : [];
        fail('Falha na simulacao de receita. ' . implode(' ', $errors));
    }
    step('Simulacoes orcamentarias executadas');

    $dashboardDespesa = $budgetService->dashboard($testYear, 'despesa_reembolso');
    $dashboardReceita = $budgetService->dashboard($testYear, 'receita_reembolso');

    $summaryDespesa = is_array($dashboardDespesa['summary'] ?? null) ? $dashboardDespesa['summary'] : [];
    $summaryReceita = is_array($dashboardReceita['summary'] ?? null) ? $dashboardReceita['summary'] : [];

    $expectedDespesaExecuted = 1200.00 + 1500.00;
    $expectedDespesaCommitted = 800.00 + 250.00;
    $expectedReceitaExecuted = 1000.00 + 1700.00;
    $expectedReceitaCommitted = 800.00 + 300.00;

    $actualDespesaExecuted = (float) ($summaryDespesa['executed_amount'] ?? 0);
    $actualDespesaCommitted = (float) ($summaryDespesa['committed_amount'] ?? 0);
    $actualReceitaExecuted = (float) ($summaryReceita['executed_amount'] ?? 0);
    $actualReceitaCommitted = (float) ($summaryReceita['committed_amount'] ?? 0);

    $tolerance = 0.01;
    if (
        abs($actualDespesaExecuted - $expectedDespesaExecuted) > $tolerance
        || abs($actualDespesaCommitted - $expectedDespesaCommitted) > $tolerance
        || abs($actualReceitaExecuted - $expectedReceitaExecuted) > $tolerance
        || abs($actualReceitaCommitted - $expectedReceitaCommitted) > $tolerance
    ) {
        fail('Resumo orcamentario nao refletiu os valores esperados por natureza financeira.');
    }
    step('Segregacao orcamentaria validada');

    $assignmentEntradaFinal = firstRow(
        $db,
        'SELECT
            a.id,
            a.person_id,
            a.movement_direction,
            a.financial_nature,
            a.counterparty_organ_id,
            a.origin_mte_destination_id,
            a.destination_mte_destination_id,
            s.code AS current_status_code
         FROM assignments a
         INNER JOIN assignment_statuses s ON s.id = a.current_status_id
         WHERE a.person_id = :person_id
           AND a.deleted_at IS NULL
         LIMIT 1',
        ['person_id' => $personEntradaId]
    );
    $assignmentSaidaFinal = firstRow(
        $db,
        'SELECT
            a.id,
            a.person_id,
            a.movement_direction,
            a.financial_nature,
            a.counterparty_organ_id,
            a.origin_mte_destination_id,
            a.destination_mte_destination_id,
            s.code AS current_status_code
         FROM assignments a
         INNER JOIN assignment_statuses s ON s.id = a.current_status_id
         WHERE a.person_id = :person_id
           AND a.deleted_at IS NULL
         LIMIT 1',
        ['person_id' => $personSaidaId]
    );

    $invoiceRows = [];
    $invoiceRows[] = firstRow(
        $db,
        'SELECT id, invoice_number, financial_nature, status, total_amount, paid_amount
         FROM invoices
         WHERE id = :id
         LIMIT 1',
        ['id' => $invoiceEntradaId]
    );
    $invoiceRows[] = firstRow(
        $db,
        'SELECT id, invoice_number, financial_nature, status, total_amount, paid_amount
         FROM invoices
         WHERE id = :id
         LIMIT 1',
        ['id' => $invoiceSaidaId]
    );

    $paymentRows = [];
    $paymentRows[] = firstRow(
        $db,
        'SELECT id, invoice_id, financial_nature, amount, payment_date
         FROM payments
         WHERE id = :id
         LIMIT 1',
        ['id' => (int) $paymentEntradaRow['id']]
    );
    $paymentRows[] = firstRow(
        $db,
        'SELECT id, invoice_id, financial_nature, amount, payment_date
         FROM payments
         WHERE id = :id
         LIMIT 1',
        ['id' => (int) $paymentSaidaRow['id']]
    );

    $batchRows = [];
    $batchRows[] = firstRow(
        $db,
        'SELECT id, batch_code, financial_nature, status, total_amount, payments_count
         FROM payment_batches
         WHERE id = :id
         LIMIT 1',
        ['id' => $batchEntradaId]
    );
    $batchRows[] = firstRow(
        $db,
        'SELECT id, batch_code, financial_nature, status, total_amount, payments_count
         FROM payment_batches
         WHERE id = :id
         LIMIT 1',
        ['id' => $batchSaidaId]
    );

    $reimbursementRows = [];
    foreach ($entradaReimbursements as $item) {
        $reimbursementRows[] = $item;
    }
    $reimbursementRows[] = firstRow(
        $db,
        'SELECT id, financial_nature, status, amount
         FROM reimbursement_entries
         WHERE id = :id
         LIMIT 1',
        ['id' => $reimbursementSaidaPaidId]
    );
    $reimbursementRows[] = firstRow(
        $db,
        'SELECT id, financial_nature, status, amount
         FROM reimbursement_entries
         WHERE id = :id
         LIMIT 1',
        ['id' => $reimbursementSaidaPendingId]
    );

    $budgetNatureCountsStmt = $db->prepare(
        'SELECT financial_nature, COUNT(*) AS total
         FROM budget_cycles
         WHERE deleted_at IS NULL
           AND cycle_year = :cycle_year
         GROUP BY financial_nature
         ORDER BY financial_nature ASC'
    );
    $budgetNatureCountsStmt->execute(['cycle_year' => $testYear]);
    $budgetNatureCounts = $budgetNatureCountsStmt->fetchAll(PDO::FETCH_ASSOC);

    $scenarioNatureCountsStmt = $db->prepare(
        'SELECT p.financial_nature, COUNT(*) AS total
         FROM budget_scenario_parameters p
         INNER JOIN budget_cycles c ON c.id = p.budget_cycle_id
         WHERE p.deleted_at IS NULL
           AND c.deleted_at IS NULL
           AND c.cycle_year = :cycle_year
         GROUP BY p.financial_nature
         ORDER BY p.financial_nature ASC'
    );
    $scenarioNatureCountsStmt->execute(['cycle_year' => $testYear]);
    $scenarioNatureCounts = $scenarioNatureCountsStmt->fetchAll(PDO::FETCH_ASSOC);

    $hiringNatureCountsStmt = $db->prepare(
        'SELECT h.financial_nature, COUNT(*) AS total
         FROM hiring_scenarios h
         INNER JOIN budget_cycles c ON c.id = h.budget_cycle_id
         WHERE h.deleted_at IS NULL
           AND c.deleted_at IS NULL
           AND c.cycle_year = :cycle_year
         GROUP BY h.financial_nature
         ORDER BY h.financial_nature ASC'
    );
    $hiringNatureCountsStmt->execute(['cycle_year' => $testYear]);
    $hiringNatureCounts = $hiringNatureCountsStmt->fetchAll(PDO::FETCH_ASSOC);

    $report = [
        'run_id' => $runId,
        'tag' => $tag,
        'generated_at' => date('c'),
        'test_year' => $testYear,
        'migration' => [
            'migration_043_found' => isset($migration043['migration']),
            'migration_043_executed_at' => $migration043['executed_at'] ?? null,
        ],
        'actors' => [
            'user_id' => $userId,
            'user_name' => (string) ($user['name'] ?? ''),
            'modality_id' => $modalityId,
            'modality_name' => (string) ($modality['name'] ?? ''),
            'flow_default_id' => (int) ($flowDefault['id'] ?? 0),
            'flow_default_name' => (string) ($flowDefault['name'] ?? ''),
            'flow_saida_id' => (int) ($flowSaida['id'] ?? 0),
            'flow_saida_name' => (string) ($flowSaida['name'] ?? ''),
        ],
        'movements' => [
            'entrada' => [
                'person_id' => $personEntradaId,
                'person_name' => (string) ($personEntradaInput['name'] ?? ''),
                'trail' => $entradaFlow['trail'],
                'final_status_code' => $entradaFinalCode,
                'assignment' => $assignmentEntradaFinal,
            ],
            'saida' => [
                'person_id' => $personSaidaId,
                'person_name' => (string) ($personSaidaInput['name'] ?? ''),
                'trail' => $saidaFlow['trail'],
                'final_status_code' => $saidaFinalCode,
                'assignment' => $assignmentSaidaFinal,
            ],
        ],
        'financial_records' => [
            'invoices' => $invoiceRows,
            'payments' => $paymentRows,
            'payment_batches' => $batchRows,
            'reimbursement_entries' => $reimbursementRows,
        ],
        'budget' => [
            'summary_despesa' => $summaryDespesa,
            'summary_receita' => $summaryReceita,
            'expected' => [
                'despesa_executed_amount' => $expectedDespesaExecuted,
                'despesa_committed_amount' => $expectedDespesaCommitted,
                'receita_executed_amount' => $expectedReceitaExecuted,
                'receita_committed_amount' => $expectedReceitaCommitted,
            ],
            'actual' => [
                'despesa_executed_amount' => $actualDespesaExecuted,
                'despesa_committed_amount' => $actualDespesaCommitted,
                'receita_executed_amount' => $actualReceitaExecuted,
                'receita_committed_amount' => $actualReceitaCommitted,
            ],
            'cycles_by_nature' => $budgetNatureCounts,
            'scenario_parameters_by_nature' => $scenarioNatureCounts,
            'hiring_scenarios_by_nature' => $hiringNatureCounts,
        ],
        'assertions' => [
            'entrada_final_status_ativo' => $entradaFinalCode === 'ativo',
            'saida_final_status_encerrado' => $saidaFinalCode === 'saida_encerrado',
            'despesa_totals_match_expected' =>
                abs($actualDespesaExecuted - $expectedDespesaExecuted) <= $tolerance
                && abs($actualDespesaCommitted - $expectedDespesaCommitted) <= $tolerance,
            'receita_totals_match_expected' =>
                abs($actualReceitaExecuted - $expectedReceitaExecuted) <= $tolerance
                && abs($actualReceitaCommitted - $expectedReceitaCommitted) <= $tolerance,
            'financial_segregation_ok' =>
                abs((float) ($summaryDespesa['paid_invoices_amount'] ?? 0) - 1200.00) <= $tolerance
                && abs((float) ($summaryDespesa['paid_reimbursements_amount'] ?? 0) - 1500.00) <= $tolerance
                && abs((float) ($summaryDespesa['committed_invoices_amount'] ?? 0) - 800.00) <= $tolerance
                && abs((float) ($summaryDespesa['committed_reimbursements_amount'] ?? 0) - 250.00) <= $tolerance
                && abs((float) ($summaryReceita['paid_invoices_amount'] ?? 0) - 1000.00) <= $tolerance
                && abs((float) ($summaryReceita['paid_reimbursements_amount'] ?? 0) - 1700.00) <= $tolerance
                && abs((float) ($summaryReceita['committed_invoices_amount'] ?? 0) - 800.00) <= $tolerance
                && abs((float) ($summaryReceita['committed_reimbursements_amount'] ?? 0) - 300.00) <= $tolerance,
        ],
        'ids' => [
            'mte_destination_entrada_id' => $mteDestinationEntradaId,
            'mte_destination_saida_id' => $mteDestinationSaidaId,
            'person_entrada_id' => $personEntradaId,
            'person_entrada_cpf' => (string) ($personEntradaResult['generated_cpf_digits'] ?? ''),
            'person_entrada_email' => (string) ($personEntradaResult['generated_email'] ?? ''),
            'person_entrada_sei' => (string) ($personEntradaResult['generated_sei'] ?? ''),
            'person_saida_id' => $personSaidaId,
            'person_saida_cpf' => (string) ($personSaidaResult['generated_cpf_digits'] ?? ''),
            'person_saida_email' => (string) ($personSaidaResult['generated_email'] ?? ''),
            'person_saida_sei' => (string) ($personSaidaResult['generated_sei'] ?? ''),
            'assignment_entrada_id' => (int) ($assignmentEntradaFinal['id'] ?? 0),
            'assignment_saida_id' => (int) ($assignmentSaidaFinal['id'] ?? 0),
            'invoice_entrada_id' => $invoiceEntradaId,
            'invoice_saida_id' => $invoiceSaidaId,
            'payment_entrada_id' => (int) ($paymentEntradaRow['id'] ?? 0),
            'payment_saida_id' => (int) ($paymentSaidaRow['id'] ?? 0),
            'batch_entrada_id' => $batchEntradaId,
            'batch_saida_id' => $batchSaidaId,
            'reimbursement_saida_paid_id' => $reimbursementSaidaPaidId,
            'reimbursement_saida_pending_id' => $reimbursementSaidaPendingId,
        ],
    ];

    $reportDir = BASE_PATH . '/storage/ops';
    if (!is_dir($reportDir) && !mkdir($reportDir, 0775, true) && !is_dir($reportDir)) {
        fail('Nao foi possivel criar diretorio de relatorio em storage/ops.');
    }

    $reportPath = $reportDir . '/phase10_dual_flow_homologation_' . $runId . '.json';
    $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        fail('Falha ao serializar relatorio JSON.');
    }

    file_put_contents($reportPath, $encoded);

    echo "[ok] Homologacao concluida.\n";
    echo "run_id: {$runId}\n";
    echo "ano_testado: {$testYear}\n";
    echo "pessoa_entrada_id: {$personEntradaId}\n";
    echo "pessoa_saida_id: {$personSaidaId}\n";
    echo "invoice_entrada_id: {$invoiceEntradaId}\n";
    echo "invoice_saida_id: {$invoiceSaidaId}\n";
    echo "batch_entrada_id: {$batchEntradaId}\n";
    echo "batch_saida_id: {$batchSaidaId}\n";
    echo "relatorio: {$reportPath}\n";
    exit(0);
} catch (Throwable $exception) {
    fail($exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
}
