<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PeopleRepository;
use DateTimeImmutable;

final class PeopleService
{
    private const CSV_ALLOWED_EXTENSIONS = ['csv', 'txt'];
    private const CSV_ALLOWED_MIME = [
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'text/comma-separated-values',
        'application/octet-stream',
    ];
    private const MAX_CSV_SIZE = 5242880; // 5MB
    private const REQUIRED_IMPORT_COLUMNS = ['name', 'cpf', 'organ'];

    public function __construct(
        private PeopleRepository $people,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->people->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->people->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findByCpf(string $cpf): ?array
    {
        $normalizedCpf = $this->normalizeCpf($this->clean($cpf));
        if ($normalizedCpf === null || mb_strlen($normalizedCpf) !== 14) {
            return null;
        }

        return $this->people->findByCpf($normalizedCpf);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        return $this->people->activeOrgans();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeModalities(): array
    {
        return $this->people->activeModalities();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeMteDestinations(): array
    {
        return $this->people->activeMteDestinations();
    }

    /** @return array<int, string> */
    public function statuses(): array
    {
        return $this->people->activeStatusCodes();
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{
     *   ok: bool,
     *   message: string,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     *   processed_rows: int,
     *   created_count: int,
     *   created_people: array<int, array{
     *     id: int,
     *     desired_modality_id: int|null,
     *     movement_context: array<string, mixed>|null,
     *     schedule_context: array{target_start_date: string|null, requested_end_date: string|null}|null,
     *     initial_status_code: string|null
     *   }>
     * }
     */
    public function importCsv(
        ?array $file,
        int $userId,
        string $ip,
        string $userAgent,
        bool $validateOnly = false
    ): array {
        $parsed = $this->readCsvFile($file);
        if (!$parsed['ok']) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel processar o arquivo de importacao.',
                'errors' => [$parsed['error']],
                'warnings' => [],
                'processed_rows' => 0,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        $headerMap = $this->buildHeaderMap($parsed['headers']);
        $missingColumns = [];
        foreach (self::REQUIRED_IMPORT_COLUMNS as $required) {
            if (!isset($headerMap[$required])) {
                $missingColumns[] = $required;
            }
        }

        if ($missingColumns !== []) {
            return [
                'ok' => false,
                'message' => 'CSV rejeitado por cabecalho incompleto.',
                'errors' => ['Cabecalho obrigatorio ausente: ' . implode(', ', $missingColumns) . '.'],
                'warnings' => [],
                'processed_rows' => 0,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        $organs = $this->activeOrgans();
        $modalities = $this->activeModalities();
        $flows = $this->people->activeAssignmentFlows();
        $statusCodes = $this->statuses();
        $mteDestinations = $this->activeMteDestinations();

        $organsById = [];
        $organsByName = [];
        $organsByAcronym = [];
        foreach ($organs as $organ) {
            $id = (int) ($organ['id'] ?? 0);
            $name = trim((string) ($organ['name'] ?? ''));
            $acronym = trim((string) ($organ['acronym'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $organsById[$id] = $id;
            $organsByName[$this->normalizeLookupValue($name)] = $id;
            if ($acronym !== '') {
                $organsByAcronym[$this->normalizeLookupValue($acronym)] = $id;
            }
        }

        $modalitiesById = [];
        $modalitiesByName = [];
        foreach ($modalities as $modality) {
            $id = (int) ($modality['id'] ?? 0);
            $name = trim((string) ($modality['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $modalitiesById[$id] = $id;
            $modalitiesByName[$this->normalizeLookupValue($name)] = $id;
        }

        $flowsById = [];
        $flowsByName = [];
        foreach ($flows as $flow) {
            $id = (int) ($flow['id'] ?? 0);
            $name = trim((string) ($flow['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $flowsById[$id] = $id;
            $flowsByName[$this->normalizeLookupValue($name)] = $id;
        }

        $statusByNormalized = [];
        foreach ($statusCodes as $statusCode) {
            $normalizedCode = $this->normalizeLookupValue($statusCode);
            if ($normalizedCode === '') {
                continue;
            }
            $statusByNormalized[$normalizedCode] = $statusCode;
        }

        $mteDestinationsById = [];
        $mteDestinationsByCode = [];
        $mteDestinationsByAcronym = [];
        foreach ($mteDestinations as $destination) {
            $id = (int) ($destination['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $mteDestinationsById[$id] = $id;

            $code = trim((string) ($destination['code'] ?? ''));
            if ($code !== '') {
                $this->registerDestinationCodeVariants($mteDestinationsByCode, $code, $id);
            }

            $acronym = trim((string) ($destination['acronym'] ?? ''));
            if ($acronym !== '') {
                $mteDestinationsByAcronym[$this->normalizeLookupValue($acronym)] = $id;
            }
        }

        $errors = [];
        $warnings = [];
        $candidates = [];
        $seenCpfs = [];
        $seenSiapes = [];
        $processedRows = 0;
        $lineNumber = 1;

        foreach ($parsed['rows'] as $row) {
            $lineNumber++;
            $mapped = $this->mapRowByHeader($row, $headerMap);
            if ($this->isCsvRowEmpty($mapped)) {
                continue;
            }

            $processedRows++;

            $rawOrgan = (string) ($mapped['organ'] ?? '');
            $rawDesiredModality = (string) ($mapped['desired_modality'] ?? '');
            $rawAssignmentFlow = (string) ($mapped['assignment_flow'] ?? '');
            $rawMovementDirection = (string) ($mapped['movement_direction'] ?? '');
            $rawStatus = (string) ($mapped['status'] ?? '');
            $rawMteUorgCode = (string) ($mapped['mte_uorg_code'] ?? '');
            $rawTargetStartDate = (string) ($mapped['target_start_date'] ?? '');
            $rawOriginOrganSiapeCode = (string) ($mapped['origin_organ_siape_code'] ?? '');

            $resolvedOrganId = $this->resolveOrganId(
                $rawOrgan,
                $organsById,
                $organsByName,
                $organsByAcronym
            );
            $resolvedModalityId = $this->resolveModalityId(
                $rawDesiredModality,
                $modalitiesById,
                $modalitiesByName
            );
            $resolvedFlowId = $this->resolveAssignmentFlowId(
                $rawAssignmentFlow,
                $flowsById,
                $flowsByName
            );
            $resolvedMovementDirection = $this->resolveMovementDirection($rawMovementDirection);
            $resolvedStatusCode = $this->resolveStatusCode(
                $rawStatus,
                $statusByNormalized
            );
            $resolvedMteDestinationId = $this->resolveMteDestinationId(
                $rawMteUorgCode,
                $mteDestinationsById,
                $mteDestinationsByCode,
                $mteDestinationsByAcronym
            );
            $normalizedTargetStartDate = $this->normalizeDate($rawTargetStartDate);
            $normalizedOriginOrganSiapeCode = $this->normalizeNumericCode($rawOriginOrganSiapeCode);

            $input = [
                'organ_id' => $resolvedOrganId ?? 0,
                'desired_modality_id' => $resolvedModalityId ?? 0,
                'assignment_flow_id' => $resolvedFlowId ?? 0,
                'name' => (string) ($mapped['name'] ?? ''),
                'cpf' => (string) ($mapped['cpf'] ?? ''),
                'matricula_siape' => (string) ($mapped['matricula_siape'] ?? ''),
                'birth_date' => (string) ($mapped['birth_date'] ?? ''),
                'gender' => (string) ($mapped['gender'] ?? ''),
                'server_status_code' => (string) ($mapped['server_status_code'] ?? ''),
                'email' => (string) ($mapped['email'] ?? ''),
                'phone' => (string) ($mapped['phone'] ?? ''),
                'status' => $resolvedStatusCode ?? 'interessado',
                'sei_process_number' => (string) ($mapped['sei_process_number'] ?? ''),
                'tags' => (string) ($mapped['tags'] ?? ''),
                'notes' => (string) ($mapped['notes'] ?? ''),
            ];

            $validation = $this->validate($input);
            $rowErrors = $validation['errors'];

            if (!$this->isImportPlaceholderValue($rawOrgan) && $resolvedOrganId === null) {
                $rowErrors[] = 'Orgao invalido (use ID, nome ou sigla cadastrada).';
            }

            if (
                !$this->isImportPlaceholderValue($rawDesiredModality, ['outro'])
                && $resolvedModalityId === null
            ) {
                $rowErrors[] = 'Modalidade invalida (use ID ou nome cadastrado).';
            }

            if (!$this->isImportPlaceholderValue($rawAssignmentFlow) && $resolvedFlowId === null) {
                $rowErrors[] = 'Fluxo BPMN invalido (use ID ou nome cadastrado).';
            }

            if (!$this->isImportPlaceholderValue($rawMovementDirection) && $resolvedMovementDirection === null) {
                $rowErrors[] = 'Direcao de movimento invalida (use ENTRADA/SAIDA no MTE).';
            }

            if (!$this->isImportPlaceholderValue($rawStatus) && $resolvedStatusCode === null) {
                $rowErrors[] = 'Etapa/status invalida para o fluxo atual.';
            }

            if (!$this->isImportPlaceholderValue($rawMteUorgCode) && $resolvedMteDestinationId === null) {
                $rowErrors[] = 'Codigo de lotacao MTE invalido (UORG nao encontrada).';
            }

            if (!$this->isImportPlaceholderValue($rawTargetStartDate) && $normalizedTargetStartDate === null) {
                $rowErrors[] = 'Data de inicio invalida (use YYYY-MM-DD ou DD/MM/AAAA).';
            }

            if (!$this->isImportPlaceholderValue($rawOriginOrganSiapeCode) && $normalizedOriginOrganSiapeCode === null) {
                $rowErrors[] = 'Codigo SIAPE do orgao de origem invalido.';
            }

            $normalizedCpf = (string) ($validation['data']['cpf'] ?? '');
            if ($normalizedCpf !== '') {
                if (isset($seenCpfs[$normalizedCpf])) {
                    $rowErrors[] = 'CPF duplicado no CSV (linha ' . $seenCpfs[$normalizedCpf] . ').';
                } else {
                    $seenCpfs[$normalizedCpf] = $lineNumber;
                }
            }

            if ($normalizedCpf !== '' && $this->people->cpfExists($normalizedCpf)) {
                $rowErrors[] = 'Ja existe pessoa cadastrada com este CPF.';
            }

            $normalizedSiape = (string) ($validation['data']['matricula_siape'] ?? '');
            if ($normalizedSiape !== '') {
                if (isset($seenSiapes[$normalizedSiape])) {
                    $rowErrors[] = 'Matricula SIAPE duplicada no CSV (linha ' . $seenSiapes[$normalizedSiape] . ').';
                } else {
                    $seenSiapes[$normalizedSiape] = $lineNumber;
                }
            }

            if ($normalizedSiape !== '' && $this->people->siapeExists($normalizedSiape)) {
                $rowErrors[] = 'Ja existe pessoa cadastrada com esta matricula SIAPE.';
            }

            if ($normalizedOriginOrganSiapeCode !== null && $resolvedOrganId === null) {
                $rowErrors[] = 'Codigo SIAPE do orgao exige orgao de origem valido na mesma linha.';
            }

            if ($rowErrors !== []) {
                $errors[] = sprintf('Linha %d: %s', $lineNumber, implode(' ', array_values(array_unique($rowErrors))));
                continue;
            }

            $movementContext = null;
            if ($resolvedMovementDirection !== null || $resolvedMteDestinationId !== null) {
                $direction = $resolvedMovementDirection ?? 'entrada_mte';
                $movementContext = [
                    'movement_direction' => $direction,
                    'financial_nature' => $direction === 'saida_mte' ? 'receita_reembolso' : 'despesa_reembolso',
                    'counterparty_organ_id' => $resolvedOrganId,
                    'origin_mte_destination_id' => $direction === 'saida_mte' ? $resolvedMteDestinationId : null,
                    'destination_mte_destination_id' => $direction === 'entrada_mte' ? $resolvedMteDestinationId : null,
                ];
            }

            $scheduleContext = null;
            if ($normalizedTargetStartDate !== null) {
                $scheduleContext = [
                    'target_start_date' => $normalizedTargetStartDate,
                    'requested_end_date' => null,
                ];
            }

            $candidates[] = [
                'line' => $lineNumber,
                'input' => $input,
                'desired_modality_id' => isset($validation['data']['desired_modality_id'])
                    ? ((int) $validation['data']['desired_modality_id'] > 0 ? (int) $validation['data']['desired_modality_id'] : null)
                    : null,
                'movement_context' => $movementContext,
                'schedule_context' => $scheduleContext,
                'initial_status_code' => $resolvedStatusCode,
                'organ_id' => $resolvedOrganId,
                'origin_organ_siape_code' => $normalizedOriginOrganSiapeCode,
            ];
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'CSV rejeitado por erros de validacao.',
                'errors' => $errors,
                'warnings' => $warnings,
                'processed_rows' => $processedRows,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        if ($candidates === []) {
            return [
                'ok' => false,
                'message' => 'CSV sem linhas validas para importacao.',
                'errors' => ['Nenhuma linha valida encontrada no CSV.'],
                'warnings' => [],
                'processed_rows' => $processedRows,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        if ($validateOnly) {
            return [
                'ok' => true,
                'message' => sprintf('Validacao concluida com sucesso: %d linha(s) apta(s) para importacao.', count($candidates)),
                'errors' => [],
                'warnings' => [],
                'processed_rows' => $processedRows,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        $createdPeople = [];

        try {
            $this->people->beginTransaction();

            foreach ($candidates as $candidate) {
                $result = $this->create($candidate['input'], $userId, $ip, $userAgent);
                if (!$result['ok'] || !isset($result['id'])) {
                    $line = (int) ($candidate['line'] ?? 0);
                    $lineErrors = $result['errors'] ?? ['Falha ao cadastrar pessoa.'];
                    throw new \RuntimeException('Linha ' . $line . ': ' . implode(' ', $lineErrors));
                }

                $organId = (int) ($candidate['organ_id'] ?? 0);
                $originOrganSiapeCode = trim((string) ($candidate['origin_organ_siape_code'] ?? ''));
                if ($organId > 0 && $originOrganSiapeCode !== '') {
                    $currentOrganSiapeCode = $this->people->organSiapeCodeById($organId);
                    if ($currentOrganSiapeCode === null) {
                        throw new \RuntimeException('Linha ' . (int) ($candidate['line'] ?? 0) . ': orgao de origem nao encontrado para gravar codigo SIAPE.');
                    }

                    if ($currentOrganSiapeCode === '') {
                        try {
                            $updated = $this->people->updateOrganSiapeCode($organId, $originOrganSiapeCode);
                            if (!$updated) {
                                $warnings[] = 'Linha ' . (int) ($candidate['line'] ?? 0)
                                    . ': nao foi possivel atualizar o codigo SIAPE do orgao.';
                            }
                        } catch (\Throwable) {
                            $warnings[] = 'Linha ' . (int) ($candidate['line'] ?? 0)
                                . ': codigo SIAPE do orgao nao atualizado por restricao de unicidade.';
                        }
                    } elseif ($currentOrganSiapeCode !== $originOrganSiapeCode) {
                        $warnings[] = 'Linha ' . (int) ($candidate['line'] ?? 0)
                            . ': codigo SIAPE do orgao divergente, mantido o codigo ja cadastrado ('
                            . $currentOrganSiapeCode . ').';
                    }
                }

                $createdPeople[] = [
                    'id' => (int) $result['id'],
                    'desired_modality_id' => $candidate['desired_modality_id'],
                    'movement_context' => is_array($candidate['movement_context'] ?? null) ? $candidate['movement_context'] : null,
                    'schedule_context' => is_array($candidate['schedule_context'] ?? null) ? $candidate['schedule_context'] : null,
                    'initial_status_code' => isset($candidate['initial_status_code']) && trim((string) $candidate['initial_status_code']) !== ''
                        ? (string) $candidate['initial_status_code']
                        : null,
                ];
            }

            $this->people->commit();
        } catch (\Throwable $exception) {
            $this->people->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel concluir a importacao em massa.',
                'errors' => ['Importacao cancelada com rollback completo. ' . $exception->getMessage()],
                'warnings' => $warnings,
                'processed_rows' => $processedRows,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('%d pessoa(s) importada(s) com sucesso.', count($createdPeople)),
            'errors' => [],
            'warnings' => $warnings,
            'processed_rows' => $processedRows,
            'created_count' => count($createdPeople),
            'created_people' => $createdPeople,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $status = trim((string) ($validation['data']['status'] ?? ''));
        $validation['data']['status'] = $status !== '' ? $status : 'interessado';

        if ($this->people->cpfExists((string) $validation['data']['cpf'])) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com este CPF.'],
                'data' => $validation['data'],
            ];
        }

        $matriculaSiape = (string) ($validation['data']['matricula_siape'] ?? '');
        if ($matriculaSiape !== '' && $this->people->siapeExists($matriculaSiape)) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com esta matrícula SIAPE.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->people->create($validation['data']);

        $this->audit->log(
            entity: 'person',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'person.created',
            payload: [
                'name' => $validation['data']['name'],
                'status' => $validation['data']['status'],
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->people->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Pessoa não encontrada.'],
                'data' => [],
            ];
        }

        $validation = $this->validate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        if ($this->people->cpfExists((string) $validation['data']['cpf'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com este CPF.'],
                'data' => $validation['data'],
            ];
        }

        $matriculaSiape = (string) ($validation['data']['matricula_siape'] ?? '');
        if ($matriculaSiape !== '' && $this->people->siapeExists($matriculaSiape, $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe pessoa cadastrada com esta matrícula SIAPE.'],
                'data' => $validation['data'],
            ];
        }

        $validation['data']['status'] = (string) ($before['status'] ?? 'interessado');

        $this->people->update($id, $validation['data']);

        $this->audit->log(
            entity: 'person',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'person.updated',
            payload: [
                'name' => $validation['data']['name'],
                'status' => $validation['data']['status'],
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    public function delete(int $id, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->people->findById($id);
        if ($before === null) {
            return false;
        }

        $this->people->softDelete($id);

        $this->audit->log(
            entity: 'person',
            entityId: $id,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'person.deleted',
            payload: ['name' => $before['name']],
            entityId: $id,
            userId: $userId
        );

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validate(array $input): array
    {
        $organId = (int) ($input['organ_id'] ?? 0);
        $modalityId = (int) ($input['desired_modality_id'] ?? 0);
        $flowId = (int) ($input['assignment_flow_id'] ?? 0);
        $name = $this->clean($input['name'] ?? null);
        $cpf = $this->normalizeCpf($this->clean($input['cpf'] ?? null));
        $matriculaSiape = $this->clean($input['matricula_siape'] ?? null);
        $birthDate = $this->normalizeDate($this->clean($input['birth_date'] ?? null));
        $gender = $this->normalizeGender($this->clean($input['gender'] ?? null));
        $serverStatusCode = $this->normalizeServerStatusCode($this->clean($input['server_status_code'] ?? null));
        $email = $this->clean($input['email'] ?? null);
        $phone = $this->clean($input['phone'] ?? null);
        $statusInput = $this->clean($input['status'] ?? null);
        $sei = $this->clean($input['sei_process_number'] ?? null);
        $tags = $this->normalizeTags($this->clean($input['tags'] ?? null));
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($organId <= 0 || !$this->people->organExists($organId)) {
            $errors[] = 'Órgão de origem é obrigatório.';
        }

        if ($modalityId > 0 && !$this->people->modalityExists($modalityId)) {
            $errors[] = 'Modalidade pretendida inválida.';
        }

        if ($flowId <= 0) {
            $defaultFlowId = $this->people->defaultAssignmentFlowId();
            $flowId = $defaultFlowId !== null ? $defaultFlowId : 0;
        }

        if ($flowId <= 0 || !$this->people->assignmentFlowExists($flowId)) {
            $errors[] = 'Fluxo do pipeline é obrigatório.';
        }

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome da pessoa é obrigatório e deve ter ao menos 3 caracteres.';
        }

        if ($cpf === null || mb_strlen($cpf) !== 14) {
            $errors[] = 'CPF inválido. Informe 11 dígitos.';
        }

        if ($matriculaSiape !== null && preg_match('/^\d+$/', $matriculaSiape) !== 1) {
            $errors[] = 'Matrícula SIAPE inválida. Informe apenas números.';
        }

        if ($matriculaSiape !== null && mb_strlen($matriculaSiape) > 20) {
            $errors[] = 'Matrícula SIAPE inválida. Limite de 20 dígitos.';
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }

        $statusByNormalized = [];
        foreach ($this->people->activeStatusCodes() as $statusCode) {
            $key = $this->normalizeLookupValue($statusCode);
            if ($key !== '') {
                $statusByNormalized[$key] = $statusCode;
            }
        }

        $status = $statusInput !== null
            ? $this->resolveStatusCode($statusInput, $statusByNormalized)
            : null;
        if ($statusInput !== null && $status === null) {
            $errors[] = 'Status do pipeline invalido.';
        }

        $data = [
            'organ_id' => $organId,
            'desired_modality_id' => $modalityId > 0 ? $modalityId : null,
            'assignment_flow_id' => $flowId > 0 ? $flowId : null,
            'name' => $name,
            'cpf' => $cpf,
            'matricula_siape' => $matriculaSiape,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'server_status_code' => $serverStatusCode,
            'email' => $email,
            'phone' => $phone,
            'status' => $status ?? 'interessado',
            'sei_process_number' => $sei,
            'tags' => $tags,
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeCpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (mb_strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3)
            . '.' . substr($digits, 3, 3)
            . '.' . substr($digits, 6, 3)
            . '-' . substr($digits, 9, 2);
    }

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $trimmed = trim($date);
        if ($trimmed === '' || $this->isImportPlaceholderValue($trimmed)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
            if ($parsed !== false && $parsed->format('Y-m-d') === $trimmed) {
                return $parsed->format('Y-m-d');
            }
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('d/m/Y', $trimmed);
            if ($parsed !== false && $parsed->format('d/m/Y') === $trimmed) {
                return $parsed->format('Y-m-d');
            }
        }

        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $trimmed) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('d-m-Y', $trimmed);
            if ($parsed !== false && $parsed->format('d-m-Y') === $trimmed) {
                return $parsed->format('Y-m-d');
            }
        }

        $time = strtotime($trimmed);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function normalizeTags(?string $tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        $parts = array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $tags)));
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, error: string, headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    private function readCsvFile(?array $file): array
    {
        if ($file === null || !isset($file['error'])) {
            return [
                'ok' => false,
                'error' => 'Arquivo CSV nao enviado.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [
                'ok' => false,
                'error' => 'Selecione um arquivo CSV para importacao.',
                'headers' => [],
                'rows' => [],
            ];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'error' => 'Falha no upload do CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_CSV_SIZE) {
            return [
                'ok' => false,
                'error' => 'CSV fora do limite permitido (5MB).',
                'headers' => [],
                'rows' => [],
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::CSV_ALLOWED_EXTENSIONS, true)) {
            return [
                'ok' => false,
                'error' => 'Extensao invalida. Envie arquivo CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $mime = '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string) finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
        if ($mime !== '' && !in_array($mime, self::CSV_ALLOWED_MIME, true)) {
            return [
                'ok' => false,
                'error' => 'Tipo de arquivo invalido para importacao CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $handle = fopen($tmpName, 'rb');
        if ($handle === false) {
            return [
                'ok' => false,
                'error' => 'Nao foi possivel ler o CSV enviado.',
                'headers' => [],
                'rows' => [],
            ];
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return [
                    'ok' => false,
                    'error' => 'CSV vazio.',
                    'headers' => [],
                    'rows' => [],
                ];
            }

            $delimiter = $this->detectCsvDelimiter($firstLine);
            rewind($handle);

            $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if (!is_array($headerRow) || $headerRow === []) {
                return [
                    'ok' => false,
                    'error' => 'CSV sem cabecalho valido.',
                    'headers' => [],
                    'rows' => [],
                ];
            }

            $headers = [];
            foreach ($headerRow as $index => $header) {
                $value = trim((string) $header);
                if ($index === 0) {
                    $value = $this->stripUtf8Bom($value);
                }

                $headers[] = $value;
            }

            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $rows[] = array_map(static fn (mixed $value): string => trim((string) $value), $row);
            }

            return [
                'ok' => true,
                'error' => '',
                'headers' => $headers,
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $aliases = [
            'name' => ['name', 'nome', 'nome_completo', 'pessoa', 'nome_do_servidor'],
            'cpf' => ['cpf', 'cpf_compontuacao', 'cpf_com_pontuacao'],
            'matricula_siape' => ['matricula_siape', 'siape', 'matricula', 'matricula_siape_numero', 'numero_siape', 'matricula_do_servidor'],
            'organ' => ['organ', 'organ_id', 'orgao', 'orgao_id', 'orgao_origem', 'empresa_orgao_de_origem'],
            'desired_modality' => ['desired_modality', 'desired_modality_id', 'modalidade', 'modalidade_id', 'modalidade_pretendida', 'motivo'],
            'assignment_flow' => ['assignment_flow', 'assignment_flow_id', 'fluxo'],
            'movement_direction' => ['movement_direction', 'direcao_movimento', 'entrada_saida'],
            'status' => ['status', 'status_code', 'etapa_atual'],
            'mte_uorg_code' => ['mte_uorg_code', 'uorg', 'uorg_lotacao', 'it_co_uorg_lotacao_servidor'],
            'target_start_date' => ['target_start_date', 'data_inicio', 'data_inicio_efetivo', 'it_da_ocor_ingr_orgao_serv'],
            'origin_organ_siape_code' => ['origin_organ_siape_code', 'siape_orgao_origem', 'it_co_orgao_origem'],
            'birth_date' => ['birth_date', 'data_nascimento', 'nascimento'],
            'gender' => ['gender', 'sexo', 'it_co_sexo'],
            'server_status_code' => ['server_status_code', 'situacao_servidor', 'it_co_situacao_servidor'],
            'email' => ['email', 'e_mail'],
            'phone' => ['phone', 'telefone', 'celular'],
            'sei_process_number' => ['sei_process_number', 'sei', 'processo_sei', 'numero_processo_sei'],
            'tags' => ['tags', 'etiquetas'],
            'notes' => ['notes', 'observacoes', 'observacao'],
        ];

        $lookup = [];
        foreach ($aliases as $canonical => $values) {
            foreach ($values as $value) {
                $lookup[$this->normalizeCsvHeader($value)] = $canonical;
            }
        }

        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeCsvHeader($header);
            if ($normalized === '') {
                continue;
            }

            $canonical = $lookup[$normalized] ?? null;
            if ($canonical === null || isset($map[$canonical])) {
                continue;
            }

            $map[$canonical] = $index;
        }

        return $map;
    }

    /**
     * @param array<int, string> $row
     * @param array<string, int> $headerMap
     * @return array<string, string>
     */
    private function mapRowByHeader(array $row, array $headerMap): array
    {
        $mapped = [];
        foreach ($headerMap as $key => $index) {
            $mapped[$key] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    /** @param array<string, string> $row */
    private function isCsvRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, int> $organsById @param array<string, int> $organsByName @param array<string, int> $organsByAcronym */
    private function resolveOrganId(string $rawValue, array $organsById, array $organsByName, array $organsByAcronym): ?int
    {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value)) {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $organsById[$id] ?? null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        if (isset($organsByName[$key])) {
            return $organsByName[$key];
        }
        if (isset($organsByAcronym[$key])) {
            return $organsByAcronym[$key];
        }

        foreach ($organsByName as $candidateKey => $candidateId) {
            if (str_contains($candidateKey, $key) || str_contains($key, $candidateKey)) {
                return $candidateId;
            }
        }

        $collapsed = str_replace('_', '', $key);
        $bestDistance = PHP_INT_MAX;
        $bestId = null;
        foreach ($organsByName as $candidateKey => $candidateId) {
            $distance = levenshtein($collapsed, str_replace('_', '', $candidateKey));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestId = $candidateId;
            }
        }

        if ($bestId !== null) {
            $threshold = mb_strlen($collapsed) >= 12 ? 4 : 2;
            if ($bestDistance <= $threshold) {
                return $bestId;
            }
        }

        foreach ($organsByAcronym as $candidateKey => $candidateId) {
            if (str_contains($candidateKey, $key) || str_contains($key, $candidateKey)) {
                return $candidateId;
            }
        }

        return null;
    }

    /** @param array<int, int> $modalitiesById @param array<string, int> $modalitiesByName */
    private function resolveModalityId(string $rawValue, array $modalitiesById, array $modalitiesByName): ?int
    {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value, ['outro'])) {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $modalitiesById[$id] ?? null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        if (isset($modalitiesByName[$key])) {
            return $modalitiesByName[$key];
        }

        if (str_contains($key, 'requisit')) {
            foreach ($modalitiesByName as $candidateKey => $candidateId) {
                if (str_contains($candidateKey, 'requis')) {
                    return $candidateId;
                }
            }
        }

        if (str_contains($key, 'forca') || str_contains($key, 'cft') || str_contains($key, 'composicao')) {
            foreach ($modalitiesByName as $candidateKey => $candidateId) {
                if (str_contains($candidateKey, 'forca') || str_contains($candidateKey, 'cft')) {
                    return $candidateId;
                }
            }
        }

        if (str_contains($key, 'cess')) {
            foreach ($modalitiesByName as $candidateKey => $candidateId) {
                if (str_contains($candidateKey, 'cess')) {
                    return $candidateId;
                }
            }
        }

        foreach ($modalitiesByName as $candidateKey => $candidateId) {
            if (str_contains($candidateKey, $key) || str_contains($key, $candidateKey)) {
                return $candidateId;
            }
        }

        return null;
    }

    /** @param array<int, int> $flowsById @param array<string, int> $flowsByName */
    private function resolveAssignmentFlowId(string $rawValue, array $flowsById, array $flowsByName): ?int
    {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value)) {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $flowsById[$id] ?? null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        if (isset($flowsByName[$key])) {
            return $flowsByName[$key];
        }

        foreach ($flowsByName as $candidateKey => $candidateId) {
            if (str_contains($candidateKey, $key) || str_contains($key, $candidateKey)) {
                return $candidateId;
            }
        }

        if (str_contains($key, 'padrao')) {
            foreach ($flowsByName as $candidateKey => $candidateId) {
                if (str_contains($candidateKey, 'padrao')) {
                    return $candidateId;
                }
            }
        }

        if (str_contains($key, 'saida')) {
            foreach ($flowsByName as $candidateKey => $candidateId) {
                if (str_contains($candidateKey, 'saida')) {
                    return $candidateId;
                }
            }
        }

        return null;
    }

    private function resolveMovementDirection(string $rawValue): ?string
    {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value)) {
            return null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        if (
            str_contains($key, 'entrada')
            || str_contains($key, 'entrando')
            || str_contains($key, 'ingresso')
        ) {
            return 'entrada_mte';
        }

        if (
            str_contains($key, 'saida')
            || str_contains($key, 'saindo')
            || str_contains($key, 'egresso')
        ) {
            return 'saida_mte';
        }

        return null;
    }

    /** @param array<string, string> $statusByNormalized */
    private function resolveStatusCode(string $rawValue, array $statusByNormalized): ?string
    {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value)) {
            return null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        if (isset($statusByNormalized[$key])) {
            return $statusByNormalized[$key];
        }

        $collapsedKey = str_replace('_', '', $key);
        foreach ($statusByNormalized as $candidateKey => $candidateCode) {
            if (str_replace('_', '', $candidateKey) === $collapsedKey) {
                return $candidateCode;
            }
        }

        if (str_contains($collapsedKey, 'ativo') && isset($statusByNormalized['ativo'])) {
            return $statusByNormalized['ativo'];
        }

        if (str_contains($collapsedKey, 'interessado') && isset($statusByNormalized['interessado'])) {
            return $statusByNormalized['interessado'];
        }

        foreach ($statusByNormalized as $candidateKey => $candidateCode) {
            if (str_contains($candidateKey, $key) || str_contains($key, $candidateKey)) {
                return $candidateCode;
            }
        }

        return null;
    }

    /**
     * @param array<int, int> $mteDestinationsById
     * @param array<string, int> $mteDestinationsByCode
     * @param array<string, int> $mteDestinationsByAcronym
     */
    private function resolveMteDestinationId(
        string $rawValue,
        array $mteDestinationsById,
        array $mteDestinationsByCode,
        array $mteDestinationsByAcronym
    ): ?int {
        $value = trim($rawValue);
        if ($this->isImportPlaceholderValue($value)) {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;
            if ($id > 0 && isset($mteDestinationsById[$id])) {
                return $mteDestinationsById[$id];
            }
        }

        $key = $this->normalizeLookupValue($value);
        if ($key !== '') {
            if (isset($mteDestinationsByCode[$key])) {
                return $mteDestinationsByCode[$key];
            }
            if (isset($mteDestinationsByAcronym[$key])) {
                return $mteDestinationsByAcronym[$key];
            }
        }

        $numericCode = $this->normalizeNumericCode($value, allowZero: false, maxLength: 20);
        if ($numericCode !== null) {
            $variants = [$numericCode, ltrim($numericCode, '0')];
            if (mb_strlen($numericCode) > 3) {
                $variants[] = ltrim(substr($numericCode, 3), '0');
            }

            foreach (array_unique(array_filter($variants, static fn (string $item): bool => $item !== '')) as $variant) {
                $variantKey = $this->normalizeLookupValue($variant);
                if (isset($mteDestinationsByCode[$variantKey])) {
                    return $mteDestinationsByCode[$variantKey];
                }
            }
        }

        return null;
    }

    /** @param array<string, int> $mteDestinationsByCode */
    private function registerDestinationCodeVariants(array &$mteDestinationsByCode, string $code, int $destinationId): void
    {
        $variants = [];
        $normalizedCode = $this->normalizeLookupValue($code);
        if ($normalizedCode !== '') {
            $variants[] = $normalizedCode;
        }

        $numericCode = $this->normalizeNumericCode($code, allowZero: false, maxLength: 20);
        if ($numericCode !== null) {
            $variants[] = $this->normalizeLookupValue($numericCode);
            $variants[] = $this->normalizeLookupValue(ltrim($numericCode, '0'));
            if (mb_strlen($numericCode) > 3) {
                $variants[] = $this->normalizeLookupValue(ltrim(substr($numericCode, 3), '0'));
            }
        }

        foreach (array_unique(array_filter($variants, static fn (string $item): bool => $item !== '')) as $variant) {
            if (!isset($mteDestinationsByCode[$variant])) {
                $mteDestinationsByCode[$variant] = $destinationId;
            }
        }
    }

    private function normalizeNumericCode(string $value, bool $allowZero = false, int $maxLength = 20): ?string
    {
        $trimmed = trim($value);
        if ($this->isImportPlaceholderValue($trimmed)) {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $trimmed) === 1) {
            $trimmed = strstr($trimmed, '.', true) ?: $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if ($allowZero === false && preg_match('/^0+$/', $digits) === 1) {
            return null;
        }

        if (mb_strlen($digits) > $maxLength) {
            return null;
        }

        return $digits;
    }

    private function normalizeServerStatusCode(?string $value): ?string
    {
        if ($value === null || $this->isImportPlaceholderValue($value)) {
            return null;
        }

        $numeric = $this->normalizeNumericCode($value, allowZero: false, maxLength: 30);
        if ($numeric !== null) {
            return $numeric;
        }

        $normalized = mb_strtoupper(trim($value));
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 30);
    }

    private function normalizeGender(?string $value): ?string
    {
        if ($value === null || $this->isImportPlaceholderValue($value)) {
            return null;
        }

        $key = $this->normalizeLookupValue($value);
        if ($key === '') {
            return null;
        }

        return match ($key) {
            'm', 'masculino', 'masc' => 'M',
            'f', 'feminino', 'fem' => 'F',
            default => mb_substr(mb_strtoupper(trim($value)), 0, 20),
        };
    }

    /**
     * @param array<int, string> $additionalPlaceholders
     */
    private function isImportPlaceholderValue(string $value, array $additionalPlaceholders = []): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return true;
        }

        $normalized = $this->normalizeLookupValue($trimmed);
        if ($normalized === '') {
            return true;
        }

        $placeholders = array_merge([
            'na',
            'n_a',
            'n_d',
            'nd',
            'null',
            'none',
            'nao_informado',
            'nao_aplicavel',
            'sem_informacao',
            'sem_dados',
            '#n_a',
            '0',
        ], array_map(fn (string $item): string => $this->normalizeLookupValue($item), $additionalPlaceholders));

        return in_array($normalized, array_values(array_filter($placeholders, static fn (string $item): bool => $item !== '')), true);
    }

    private function detectCsvDelimiter(string $sample): string
    {
        $candidate = str_replace(["\r", "\n"], '', $sample);

        $semicolonCount = substr_count($candidate, ';');
        $commaCount = substr_count($candidate, ',');
        $tabCount = substr_count($candidate, "\t");

        if ($semicolonCount >= $commaCount && $semicolonCount >= $tabCount) {
            return ';';
        }

        if ($tabCount > $commaCount) {
            return "\t";
        }

        return ',';
    }

    private function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        if ($header === '') {
            return '';
        }

        $header = strtr($header, [
            'ã' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'õ' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ç' => 'c',
        ]);

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if (is_string($ascii) && trim($ascii) !== '') {
            $header = $ascii;
        }

        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        $header = trim($header, '_');

        return $header;
    }

    private function normalizeLookupValue(string $value): string
    {
        return $this->normalizeCsvHeader($value);
    }
}
