<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PeopleRepository;

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
     *   created_people: array<int, array{id: int, desired_modality_id: int|null}>
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
                'message' => 'Nao foi possivel processar o CSV.',
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

            $resolvedOrganId = $this->resolveOrganId(
                (string) ($mapped['organ'] ?? ''),
                $organsById,
                $organsByName,
                $organsByAcronym
            );
            $resolvedModalityId = $this->resolveModalityId(
                (string) ($mapped['desired_modality'] ?? ''),
                $modalitiesById,
                $modalitiesByName
            );
            $input = [
                'organ_id' => $resolvedOrganId ?? 0,
                'desired_modality_id' => $resolvedModalityId ?? 0,
                'name' => (string) ($mapped['name'] ?? ''),
                'cpf' => (string) ($mapped['cpf'] ?? ''),
                'matricula_siape' => (string) ($mapped['matricula_siape'] ?? ''),
                'birth_date' => (string) ($mapped['birth_date'] ?? ''),
                'email' => (string) ($mapped['email'] ?? ''),
                'phone' => (string) ($mapped['phone'] ?? ''),
                'sei_process_number' => (string) ($mapped['sei_process_number'] ?? ''),
                'tags' => (string) ($mapped['tags'] ?? ''),
                'notes' => (string) ($mapped['notes'] ?? ''),
            ];

            $validation = $this->validate($input);
            $rowErrors = $validation['errors'];

            if ((string) ($mapped['organ'] ?? '') !== '' && $resolvedOrganId === null) {
                $rowErrors[] = 'Orgao invalido (use ID, nome ou sigla cadastrada).';
            }

            if ((string) ($mapped['desired_modality'] ?? '') !== '' && $resolvedModalityId === null) {
                $rowErrors[] = 'Modalidade invalida (use ID ou nome cadastrado).';
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

            if ($rowErrors !== []) {
                $errors[] = sprintf('Linha %d: %s', $lineNumber, implode(' ', array_values(array_unique($rowErrors))));
                continue;
            }

            $candidates[] = [
                'line' => $lineNumber,
                'input' => $input,
                'desired_modality_id' => isset($validation['data']['desired_modality_id'])
                    ? ((int) $validation['data']['desired_modality_id'] > 0 ? (int) $validation['data']['desired_modality_id'] : null)
                    : null,
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

                $createdPeople[] = [
                    'id' => (int) $result['id'],
                    'desired_modality_id' => $candidate['desired_modality_id'],
                ];
            }

            $this->people->commit();
        } catch (\Throwable $exception) {
            $this->people->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel concluir a importacao em massa.',
                'errors' => ['Importacao cancelada com rollback completo. ' . $exception->getMessage()],
                'warnings' => [],
                'processed_rows' => $processedRows,
                'created_count' => 0,
                'created_people' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('%d pessoa(s) importada(s) com sucesso.', count($createdPeople)),
            'errors' => [],
            'warnings' => [],
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

        $validation['data']['status'] = 'interessado';

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
        $email = $this->clean($input['email'] ?? null);
        $phone = $this->clean($input['phone'] ?? null);
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

        $data = [
            'organ_id' => $organId,
            'desired_modality_id' => $modalityId > 0 ? $modalityId : null,
            'assignment_flow_id' => $flowId > 0 ? $flowId : null,
            'name' => $name,
            'cpf' => $cpf,
            'matricula_siape' => $matriculaSiape,
            'birth_date' => $birthDate,
            'email' => $email,
            'phone' => $phone,
            'status' => 'interessado',
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

        $time = strtotime($date);
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
            'name' => ['name', 'nome', 'nome_completo', 'pessoa'],
            'cpf' => ['cpf'],
            'matricula_siape' => ['matricula_siape', 'siape', 'matricula', 'matricula_siape_numero', 'numero_siape'],
            'organ' => ['organ', 'organ_id', 'orgao', 'orgao_id', 'orgao_origem'],
            'desired_modality' => ['desired_modality', 'desired_modality_id', 'modalidade', 'modalidade_id', 'modalidade_pretendida'],
            'birth_date' => ['birth_date', 'data_nascimento', 'nascimento'],
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
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $organsById[$id] ?? null;
        }

        $key = $this->normalizeLookupValue($value);
        if (isset($organsByName[$key])) {
            return $organsByName[$key];
        }

        return $organsByAcronym[$key] ?? null;
    }

    /** @param array<int, int> $modalitiesById @param array<string, int> $modalitiesByName */
    private function resolveModalityId(string $rawValue, array $modalitiesById, array $modalitiesByName): ?int
    {
        $value = trim($rawValue);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $modalitiesById[$id] ?? null;
        }

        $key = $this->normalizeLookupValue($value);

        return $modalitiesByName[$key] ?? null;
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
