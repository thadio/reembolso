<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrganRepository;

final class OrganService
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
    private const REQUIRED_IMPORT_COLUMNS = ['name'];
    private const ALLOWED_GOVERNMENT_LEVELS = ['federal', 'estadual', 'municipal', 'distrital'];
    private const ALLOWED_GOVERNMENT_BRANCHES = ['executivo', 'legislativo', 'judiciario', 'autonomo'];
    private const ALLOWED_COMPANY_DEPENDENCY_TYPES = ['independente', 'dependente', 'em_liquidacao'];

    public function __construct(
        private OrganRepository $organs,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->organs->paginate($query, $sort, $dir, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->organs->findById($id);
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{
     *   ok: bool,
     *   message: string,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     *   processed_rows: int,
     *   created_count: int
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
            ];
        }

        $errors = [];
        $warnings = [];
        $candidates = [];
        $seenCnpjs = [];
        $processedRows = 0;
        $lineNumber = 1;

        foreach ($parsed['rows'] as $row) {
            $lineNumber++;
            $mapped = $this->mapRowByHeader($row, $headerMap);
            if ($this->isCsvRowEmpty($mapped)) {
                continue;
            }

            $processedRows++;

            $input = [
                'name' => (string) ($mapped['name'] ?? ''),
                'acronym' => (string) ($mapped['acronym'] ?? ''),
                'cnpj' => (string) ($mapped['cnpj'] ?? ''),
                'company_nire' => (string) ($mapped['company_nire'] ?? ''),
                'organ_type' => (string) ($mapped['organ_type'] ?? ''),
                'company_dependency_type' => (string) ($mapped['company_dependency_type'] ?? ''),
                'government_level' => (string) ($mapped['government_level'] ?? ''),
                'government_branch' => (string) ($mapped['government_branch'] ?? ''),
                'supervising_organ' => (string) ($mapped['supervising_organ'] ?? ''),
                'federative_entity' => (string) ($mapped['federative_entity'] ?? ''),
                'contact_name' => (string) ($mapped['contact_name'] ?? ''),
                'contact_email' => (string) ($mapped['contact_email'] ?? ''),
                'contact_phone' => (string) ($mapped['contact_phone'] ?? ''),
                'address_line' => (string) ($mapped['address_line'] ?? ''),
                'city' => (string) ($mapped['city'] ?? ''),
                'state' => (string) ($mapped['state'] ?? ''),
                'zip_code' => (string) ($mapped['zip_code'] ?? ''),
                'notes' => (string) ($mapped['notes'] ?? ''),
                'source_name' => (string) ($mapped['source_name'] ?? ''),
                'source_url' => (string) ($mapped['source_url'] ?? ''),
                'company_objective' => (string) ($mapped['company_objective'] ?? ''),
                'capital_information' => (string) ($mapped['capital_information'] ?? ''),
                'creation_act' => (string) ($mapped['creation_act'] ?? ''),
                'internal_regulations' => (string) ($mapped['internal_regulations'] ?? ''),
                'subsidiaries' => (string) ($mapped['subsidiaries'] ?? ''),
                'official_website' => (string) ($mapped['official_website'] ?? ''),
            ];

            $validation = $this->validate($input);
            $rowErrors = $validation['errors'];

            $normalizedCnpj = (string) ($validation['data']['cnpj'] ?? '');
            if ($normalizedCnpj !== '') {
                if (isset($seenCnpjs[$normalizedCnpj])) {
                    $rowErrors[] = 'CNPJ duplicado no CSV (linha ' . $seenCnpjs[$normalizedCnpj] . ').';
                } else {
                    $seenCnpjs[$normalizedCnpj] = $lineNumber;
                }

                if ($this->organs->cnpjExists($normalizedCnpj)) {
                    $rowErrors[] = 'Ja existe orgao cadastrado com este CNPJ.';
                }
            }

            if ($rowErrors !== []) {
                $errors[] = sprintf('Linha %d: %s', $lineNumber, implode(' ', array_values(array_unique($rowErrors))));
                continue;
            }

            $candidates[] = [
                'line' => $lineNumber,
                'input' => $input,
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
            ];
        }

        $createdCount = 0;

        try {
            $this->organs->beginTransaction();

            foreach ($candidates as $candidate) {
                $result = $this->create($candidate['input'], $userId, $ip, $userAgent);
                if (!$result['ok'] || !isset($result['id'])) {
                    $line = (int) ($candidate['line'] ?? 0);
                    $lineErrors = $result['errors'] ?? ['Falha ao cadastrar orgao.'];
                    throw new \RuntimeException('Linha ' . $line . ': ' . implode(' ', $lineErrors));
                }

                $createdCount++;
            }

            $this->organs->commit();
        } catch (\Throwable $exception) {
            $this->organs->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel concluir a importacao em massa.',
                'errors' => ['Importacao cancelada com rollback completo. ' . $exception->getMessage()],
                'warnings' => [],
                'processed_rows' => $processedRows,
                'created_count' => 0,
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('%d orgao(s) importado(s) com sucesso.', $createdCount),
            'errors' => [],
            'warnings' => [],
            'processed_rows' => $processedRows,
            'created_count' => $createdCount,
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

        if ($validation['data']['cnpj'] !== null && $this->organs->cnpjExists((string) $validation['data']['cnpj'])) {
            return [
                'ok' => false,
                'errors' => ['Já existe um órgão com este CNPJ.'],
                'data' => $validation['data'],
            ];
        }

        $id = $this->organs->create($validation['data']);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.created',
            payload: ['name' => $validation['data']['name']],
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
        $before = $this->organs->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Órgão não encontrado.'],
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

        if ($validation['data']['cnpj'] !== null && $this->organs->cnpjExists((string) $validation['data']['cnpj'], $id)) {
            return [
                'ok' => false,
                'errors' => ['Já existe um órgão com este CNPJ.'],
                'data' => $validation['data'],
            ];
        }

        $this->organs->update($id, $validation['data']);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.updated',
            payload: ['name' => $validation['data']['name']],
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
        $before = $this->organs->findById($id);
        if ($before === null) {
            return false;
        }

        $this->organs->softDelete($id);

        $this->audit->log(
            entity: 'organ',
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
            entity: 'organ',
            type: 'organ.deleted',
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
        $name = $this->clean($input['name'] ?? null);
        $acronym = $this->clean($input['acronym'] ?? null);
        $cnpj = $this->normalizeCnpj($this->clean($input['cnpj'] ?? null));
        $companyNire = $this->truncate($this->clean($input['company_nire'] ?? null), 40);
        $organType = $this->normalizeOrganType($this->clean($input['organ_type'] ?? null));
        $rawCompanyDependencyType = $this->clean($input['company_dependency_type'] ?? null);
        $companyDependencyType = $this->normalizeCompanyDependencyType($rawCompanyDependencyType);
        $rawGovernmentLevel = $this->clean($input['government_level'] ?? null);
        $rawGovernmentBranch = $this->clean($input['government_branch'] ?? null);
        $governmentLevel = $this->normalizeGovernmentLevel($rawGovernmentLevel);
        $governmentBranch = $this->normalizeGovernmentBranch($rawGovernmentBranch);
        $supervisingOrgan = $this->clean($input['supervising_organ'] ?? null);
        $federativeEntity = $this->truncate($this->clean($input['federative_entity'] ?? null), 120);
        $contactName = $this->clean($input['contact_name'] ?? null);
        $contactEmail = $this->clean($input['contact_email'] ?? null);
        $contactPhone = $this->clean($input['contact_phone'] ?? null);
        $addressLine = $this->clean($input['address_line'] ?? null);
        $city = $this->clean($input['city'] ?? null);
        $state = $this->clean($input['state'] ?? null);
        $zipCode = $this->clean($input['zip_code'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);
        $sourceName = $this->clean($input['source_name'] ?? null);
        $sourceUrl = $this->normalizeUrl($this->clean($input['source_url'] ?? null));
        $companyObjective = $this->clean($input['company_objective'] ?? null);
        $capitalInformation = $this->clean($input['capital_information'] ?? null);
        $creationAct = $this->clean($input['creation_act'] ?? null);
        $internalRegulations = $this->clean($input['internal_regulations'] ?? null);
        $subsidiaries = $this->clean($input['subsidiaries'] ?? null);
        $officialWebsite = $this->normalizeUrl($this->clean($input['official_website'] ?? null));

        $errors = [];

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do órgão é obrigatório e deve ter ao menos 3 caracteres.';
        }

        if ($contactEmail !== null && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail de contato inválido.';
        }

        if ($cnpj !== null && mb_strlen($cnpj) !== 18) {
            $errors[] = 'CNPJ inválido. Informe 14 dígitos.';
        }

        if ($state !== null) {
            $state = mb_strtoupper($state);
            if (mb_strlen($state) !== 2) {
                $errors[] = 'UF deve conter exatamente 2 caracteres.';
            }
        }

        if ($rawGovernmentLevel !== null && $governmentLevel === null) {
            $errors[] = 'Esfera invalida. Use federal, estadual, municipal ou distrital.';
        }

        if ($rawGovernmentBranch !== null && $governmentBranch === null) {
            $errors[] = 'Poder invalido. Use executivo, legislativo, judiciario ou autonomo.';
        }

        if ($rawCompanyDependencyType !== null && $companyDependencyType === null) {
            $errors[] = 'Vinculacao empresarial invalida. Use independente, dependente ou em_liquidacao.';
        }

        if ($sourceUrl !== null && !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL de referencia invalida.';
        }

        if ($officialWebsite !== null && !filter_var($officialWebsite, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL do site oficial invalida.';
        }

        $data = [
            'name' => $name,
            'acronym' => $acronym === null ? null : mb_strtoupper($acronym),
            'cnpj' => $cnpj,
            'company_nire' => $companyNire,
            'organ_type' => $organType,
            'company_dependency_type' => $companyDependencyType,
            'government_level' => $governmentLevel,
            'government_branch' => $governmentBranch,
            'supervising_organ' => $supervisingOrgan,
            'federative_entity' => $federativeEntity,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'address_line' => $addressLine,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zipCode,
            'notes' => $notes,
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'company_objective' => $companyObjective,
            'capital_information' => $capitalInformation,
            'creation_act' => $creationAct,
            'internal_regulations' => $internalRegulations,
            'subsidiaries' => $subsidiaries,
            'official_website' => $officialWebsite,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
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
            'name' => ['name', 'nome', 'orgao', 'orgao_nome'],
            'acronym' => ['acronym', 'sigla'],
            'cnpj' => ['cnpj'],
            'company_nire' => ['company_nire', 'nire'],
            'organ_type' => ['organ_type', 'tipo_orgao', 'tipo', 'classificacao', 'natureza'],
            'company_dependency_type' => ['company_dependency_type', 'vinculacao_empresa', 'dependencia_empresa', 'status_empresa', 'tipo_vinculacao'],
            'government_level' => ['government_level', 'esfera', 'nivel_governo', 'esfera_governo'],
            'government_branch' => ['government_branch', 'poder', 'poder_governo'],
            'supervising_organ' => ['supervising_organ', 'orgao_supervisor', 'orgao_vinculador', 'ministerio_vinculado'],
            'federative_entity' => ['federative_entity', 'ente_federativo', 'ente', 'vinculo_federativo'],
            'contact_name' => ['contact_name', 'contato', 'contato_nome', 'nome_contato'],
            'contact_email' => ['contact_email', 'email', 'email_contato'],
            'contact_phone' => ['contact_phone', 'telefone', 'telefone_contato', 'fone'],
            'address_line' => ['address_line', 'endereco', 'logradouro'],
            'city' => ['city', 'cidade'],
            'state' => ['state', 'uf'],
            'zip_code' => ['zip_code', 'cep'],
            'notes' => ['notes', 'observacoes', 'observacao', 'notas'],
            'source_name' => ['source_name', 'fonte', 'origem_dado', 'origem'],
            'source_url' => ['source_url', 'fonte_url', 'referencia', 'referencia_url', 'link_fonte'],
            'company_objective' => ['company_objective', 'objetivo_empresa', 'objetivo'],
            'capital_information' => ['capital_information', 'capital_social'],
            'creation_act' => ['creation_act', 'ato_criacao', 'ato_de_criacao'],
            'internal_regulations' => ['internal_regulations', 'regulamentacao_interna'],
            'subsidiaries' => ['subsidiaries', 'subsidiarias'],
            'official_website' => ['official_website', 'website', 'site_oficial', 'url_site_oficial'],
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

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cnpj);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (mb_strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2)
            . '.' . substr($digits, 2, 3)
            . '.' . substr($digits, 5, 3)
            . '/' . substr($digits, 8, 4)
            . '-' . substr($digits, 12, 2);
    }

    private function normalizeOrganType(?string $organType): ?string
    {
        if ($organType === null) {
            return null;
        }

        $lookup = $this->normalizeLookupValue($organType);
        $map = [
            'administracao_direta' => 'administracao_direta',
            'adm_direta' => 'administracao_direta',
            'ministerio' => 'administracao_direta',
            'autarquia' => 'autarquia',
            'autarquia_especial' => 'autarquia_especial',
            'agencia_reguladora' => 'autarquia_especial',
            'fundacao_publica' => 'fundacao_publica',
            'empresa_publica' => 'empresa_publica',
            'sociedade_economia_mista' => 'sociedade_economia_mista',
            'sociedade_de_economia_mista' => 'sociedade_economia_mista',
        ];

        $normalized = $map[$lookup] ?? $this->normalizeCsvHeader($organType);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 50) {
            return mb_substr($normalized, 0, 50);
        }

        return $normalized;
    }

    private function normalizeGovernmentLevel(?string $governmentLevel): ?string
    {
        if ($governmentLevel === null) {
            return null;
        }

        $lookup = $this->normalizeLookupValue($governmentLevel);
        $map = [
            'federal' => 'federal',
            'uniao' => 'federal',
            'estadual' => 'estadual',
            'estado' => 'estadual',
            'municipal' => 'municipal',
            'municipio' => 'municipal',
            'distrital' => 'distrital',
            'distrito_federal' => 'distrital',
        ];

        $normalized = $map[$lookup] ?? null;
        if ($normalized === null || !in_array($normalized, self::ALLOWED_GOVERNMENT_LEVELS, true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeGovernmentBranch(?string $governmentBranch): ?string
    {
        if ($governmentBranch === null) {
            return null;
        }

        $lookup = $this->normalizeLookupValue($governmentBranch);
        $map = [
            'executivo' => 'executivo',
            'legislativo' => 'legislativo',
            'judiciario' => 'judiciario',
            'autonomo' => 'autonomo',
            'independente' => 'autonomo',
        ];

        $normalized = $map[$lookup] ?? null;
        if ($normalized === null || !in_array($normalized, self::ALLOWED_GOVERNMENT_BRANCHES, true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeCompanyDependencyType(?string $dependencyType): ?string
    {
        if ($dependencyType === null) {
            return null;
        }

        $lookup = $this->normalizeLookupValue($dependencyType);
        $map = [
            'independente' => 'independente',
            'dependente' => 'dependente',
            'em_liquidacao' => 'em_liquidacao',
            'em_liquidacao_' => 'em_liquidacao',
            'liquidacao' => 'em_liquidacao',
        ];

        $normalized = $map[$lookup] ?? null;
        if ($normalized === null || !in_array($normalized, self::ALLOWED_COMPANY_DEPENDENCY_TYPES, true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $normalized = trim($url);
        if ($normalized === '') {
            return null;
        }

        if (!preg_match('#^[a-z]+://#i', $normalized) && preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}.*$/i', $normalized)) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        return $normalized;
    }

    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function normalizeLookupValue(string $value): string
    {
        return $this->normalizeCsvHeader($value);
    }
}
