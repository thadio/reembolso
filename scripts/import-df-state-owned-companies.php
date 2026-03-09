#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\OrganRepository;
use App\Services\OrganService;

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
    $options = parseOptions($argv, $basePath);

    if ($options['help'] === true) {
        printUsage();
        exit(0);
    }

    $content = file_get_contents((string) $options['markdown']);
    if (!is_string($content) || trim($content) === '') {
        fail('arquivo markdown vazio ou ilegivel.');
    }

    $companies = parseCompaniesFromMarkdown($content, (string) $options['source_name'], (string) $options['source_url']);
    if ($companies === []) {
        fail('nenhuma empresa encontrada no markdown informado.');
    }

    $app = require $basePath . '/bootstrap.php';
    if (!$app instanceof App) {
        fail('falha ao inicializar a aplicacao.');
    }

    $repository = new OrganRepository($app->db());
    $service = new OrganService($repository, $app->audit(), $app->events());

    $stats = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
    ];
    $warnings = [];

    $lookupByCnpj = $app->db()->prepare('SELECT id FROM organs WHERE cnpj = :cnpj AND deleted_at IS NULL LIMIT 1');
    $lookupByName = $app->db()->prepare('SELECT id FROM organs WHERE name = :name AND deleted_at IS NULL LIMIT 1');

    $keys = [
        'name',
        'acronym',
        'cnpj',
        'company_nire',
        'organ_type',
        'company_dependency_type',
        'government_level',
        'government_branch',
        'supervising_organ',
        'federative_entity',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_line',
        'city',
        'state',
        'zip_code',
        'notes',
        'source_name',
        'source_url',
        'company_objective',
        'capital_information',
        'creation_act',
        'internal_regulations',
        'subsidiaries',
        'official_website',
    ];

    $userId = (int) $options['user_id'];
    $ip = (string) $options['ip'];
    $userAgent = (string) $options['user_agent'];
    $validateOnly = (bool) $options['validate_only'];

    $repository->beginTransaction();

    try {
        foreach ($companies as $company) {
            $stats['processed']++;

            $input = [
                'name' => $company['name'],
                'acronym' => $company['acronym'],
                'cnpj' => $company['cnpj'],
                'company_nire' => $company['company_nire'],
                'organ_type' => $company['organ_type'],
                'company_dependency_type' => $company['company_dependency_type'],
                'government_level' => 'distrital',
                'government_branch' => 'executivo',
                'supervising_organ' => 'Governo do Distrito Federal',
                'federative_entity' => 'Distrito Federal',
                'contact_name' => null,
                'contact_email' => $company['contact_email'],
                'contact_phone' => $company['contact_phone'],
                'address_line' => $company['address_line'],
                'city' => 'Brasilia',
                'state' => 'DF',
                'zip_code' => $company['zip_code'],
                'notes' => $company['notes'],
                'source_name' => $company['source_name'],
                'source_url' => $company['source_url'],
                'company_objective' => $company['company_objective'],
                'capital_information' => $company['capital_information'],
                'creation_act' => $company['creation_act'],
                'internal_regulations' => $company['internal_regulations'],
                'subsidiaries' => $company['subsidiaries'],
                'official_website' => $company['official_website'],
            ];

            $existingId = null;
            if ($input['cnpj'] !== null) {
                $lookupByCnpj->execute(['cnpj' => $input['cnpj']]);
                $existing = $lookupByCnpj->fetch();
                if (is_array($existing) && isset($existing['id'])) {
                    $existingId = (int) $existing['id'];
                }
            }

            if ($existingId === null) {
                $lookupByName->execute(['name' => (string) $input['name']]);
                $existing = $lookupByName->fetch();
                if (is_array($existing) && isset($existing['id'])) {
                    $existingId = (int) $existing['id'];
                }
            }

            if ($existingId !== null && $existingId > 0) {
                $before = $repository->findById($existingId);
                if ($before === null) {
                    throw new RuntimeException('registro nao encontrado para update (id=' . $existingId . ').');
                }

                $payload = [];
                foreach ($keys as $key) {
                    $incoming = $input[$key] ?? null;
                    $existingValue = $before[$key] ?? null;
                    $payload[$key] = $incoming !== null && $incoming !== '' ? $incoming : ($existingValue === '' ? null : $existingValue);
                }

                $changed = false;
                foreach ($keys as $key) {
                    $beforeValue = $before[$key] ?? null;
                    $beforeValue = $beforeValue === '' ? null : $beforeValue;
                    $afterValue = $payload[$key] ?? null;
                    if ($beforeValue !== $afterValue) {
                        $changed = true;
                        break;
                    }
                }

                if (!$changed) {
                    $stats['unchanged']++;
                    continue;
                }

                $result = $service->update($existingId, $payload, $userId, $ip, $userAgent);
                if (($result['ok'] ?? false) !== true) {
                    $message = implode(' ', array_map('strval', (array) ($result['errors'] ?? ['falha ao atualizar registro'])));
                    throw new RuntimeException('update falhou para "' . $input['name'] . '": ' . $message);
                }

                $stats['updated']++;
                continue;
            }

            $result = $service->create($input, $userId, $ip, $userAgent);
            if (($result['ok'] ?? false) !== true) {
                $message = implode(' ', array_map('strval', (array) ($result['errors'] ?? ['falha ao criar registro'])));
                throw new RuntimeException('create falhou para "' . $input['name'] . '": ' . $message);
            }

            $stats['created']++;
        }

        if ($validateOnly) {
            $repository->rollBack();
        } else {
            $repository->commit();
        }
    } catch (Throwable $throwable) {
        $repository->rollBack();
        fail($throwable->getMessage());
    }

    $payload = [
        'ok' => true,
        'mode' => $validateOnly ? 'validate_only' : 'import',
        'source_markdown' => (string) $options['markdown'],
        'processed' => $stats['processed'],
        'created' => $stats['created'],
        'updated' => $stats['updated'],
        'unchanged' => $stats['unchanged'],
        'warnings' => $warnings,
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/**
 * @param array<int, string> $argv
 * @return array{
 *   markdown: string,
 *   source_name: string,
 *   source_url: string,
 *   user_id: int,
 *   ip: string,
 *   user_agent: string,
 *   validate_only: bool,
 *   help: bool
 * }
 */
function parseOptions(array $argv, string $basePath): array
{
    $markdown = $basePath . '/docs/empresaspublicasdf.md';
    $sourceName = 'Casa Civil do Distrito Federal - Empresas Estatais do Distrito Federal';
    $sourceUrl = 'https://www.casacivil.df.gov.br/as-empresas-estatais-do-distrito-federal/';
    $userId = 1;
    $ip = '127.0.0.1';
    $userAgent = 'cli/import-df-state-owned-companies';
    $validateOnly = false;
    $help = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--markdown':
                $markdown = resolvePath(readOptionValue($argv, $i, '--markdown'), $basePath);
                break;
            case '--source-name':
                $sourceName = readOptionValue($argv, $i, '--source-name');
                break;
            case '--source-url':
                $sourceUrl = readOptionValue($argv, $i, '--source-url');
                break;
            case '--user-id':
                $userId = parseIntOption(readOptionValue($argv, $i, '--user-id'), '--user-id', 0, 999999999);
                break;
            case '--ip':
                $ip = readOptionValue($argv, $i, '--ip');
                break;
            case '--user-agent':
                $userAgent = readOptionValue($argv, $i, '--user-agent');
                break;
            case '--validate-only':
                $validateOnly = true;
                break;
            case '--help':
            case '-h':
                $help = true;
                break;
            default:
                fail(sprintf('opcao desconhecida: %s', $arg));
        }
    }

    if (!is_file($markdown)) {
        fail(sprintf('arquivo markdown nao encontrado: %s', $markdown));
    }

    return [
        'markdown' => $markdown,
        'source_name' => trim($sourceName),
        'source_url' => trim($sourceUrl),
        'user_id' => $userId,
        'ip' => trim($ip),
        'user_agent' => trim($userAgent),
        'validate_only' => $validateOnly,
        'help' => $help,
    ];
}

/**
 * @return array<int, array<string, string|null>>
 */
function parseCompaniesFromMarkdown(string $content, string $sourceName, string $sourceUrl): array
{
    $sourceName = trim($sourceName);
    $sourceUrl = normalizeUrl(trim($sourceUrl));

    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $updatedDate = extractUpdatedDate($content);
    if ($updatedDate !== null) {
        $sourceName = trim($sourceName . ' (atualizado em ' . $updatedDate . ')');
    }

    $classificationByCnpjDigits = [
        '00000208000100' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'independente', 'acronym' => 'BRB'],
        '00082024000137' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'independente', 'acronym' => 'CAESB'],
        '00314310000180' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'independente', 'acronym' => 'CEASA-DF'],
        '00070698000111' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'independente', 'acronym' => 'CEB'],
        '23284932000109' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'independente', 'acronym' => 'DF GESTAO DE ATIVOS'],
        '00359877000173' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'independente', 'acronym' => 'TERRACAP'],
        '38070074000177' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'METRO-DF'],
        '09335575000130' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'CODHAB'],
        '00037127000185' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'TCB'],
        '00509612000104' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'EMATER-DF'],
        '00046060000145' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'CODEPLAN'],
        '00037457000170' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'dependente', 'acronym' => 'NOVACAP'],
        '00037226000167' => ['organ_type' => 'empresa_publica', 'company_dependency_type' => 'em_liquidacao', 'acronym' => 'SAB'],
        '00338079000165' => ['organ_type' => 'sociedade_economia_mista', 'company_dependency_type' => 'em_liquidacao', 'acronym' => 'PROFLORA'],
    ];

    $blocks = preg_split('/\n(?=\s*Raz[aã]o Social\s*:)/ui', $content);
    if (!is_array($blocks)) {
        return [];
    }

    $companies = [];

    foreach ($blocks as $block) {
        if (!preg_match('/Raz[aã]o Social\s*:/ui', $block)) {
            continue;
        }

        $name = extractLineField($block, 'Raz[aã]o Social');
        if ($name === null) {
            continue;
        }

        $cnpjRaw = extractLineField($block, 'CNPJ');
        $cnpjData = normalizeCnpj($cnpjRaw);
        if ($cnpjData['formatted'] === null || $cnpjData['digits'] === null) {
            continue;
        }

        $classification = $classificationByCnpjDigits[$cnpjData['digits']] ?? null;
        if ($classification === null) {
            continue;
        }

        $nire = extractLineField($block, 'NIRE');
        $objective = extractBetween($block, 'Objetivo da empresa\s*:', ['Capital Social\s*:', 'Ato de Cria[cç][aã]o\s*:', 'Lei de Cria[cç][aã]o\s*:', 'Regulamenta[cç][aã]o Interna\s*:']);
        $capital = extractBetween($block, 'Capital Social\s*:', ['Ato de Cria[cç][aã]o\s*:', 'Lei de Cria[cç][aã]o\s*:', 'Regulamenta[cç][aã]o Interna\s*:']);
        $creationAct = extractBetween($block, '(?:Ato|Lei) de Cria[cç][aã]o\s*:', ['Regulamenta[cç][aã]o Interna\s*:', 'Contato\s*:']);
        $internalRegulations = normalizeMultiline(extractBetween($block, 'Regulamenta[cç][aã]o Interna\s*:', ['Subsidi[aá]rias\s*:', 'Contato\s*:']));
        $subsidiaries = normalizeMultiline(extractBetween($block, 'Subsidi[aá]rias\s*:', ['Contato\s*:']));

        $phone = extractLineField($block, 'Telefone(?:\s+ao\s+p[uú]blico)?');
        $email = extractLineField($block, 'E-?mail');
        $website = normalizeUrl(extractLineField($block, 'Website'));
        $address = extractLineField($block, 'Endere(?:c|ç)o');
        $zipCode = extractZipCode($address);

        $normalizedEmail = normalizeInline($email);
        if ($normalizedEmail !== null && !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $normalizedEmail = null;
        }

        $companies[] = [
            'name' => normalizeCompanyName($name),
            'acronym' => $classification['acronym'],
            'cnpj' => $cnpjData['formatted'],
            'company_nire' => normalizeInline($nire),
            'organ_type' => $classification['organ_type'],
            'company_dependency_type' => $classification['company_dependency_type'],
            'contact_phone' => normalizeInline($phone),
            'contact_email' => $normalizedEmail,
            'address_line' => normalizeInline($address),
            'zip_code' => $zipCode,
            'notes' => 'Cadastro detalhado da empresa estatal do Distrito Federal.',
            'source_name' => $sourceName !== '' ? $sourceName : null,
            'source_url' => $sourceUrl,
            'company_objective' => normalizeInline($objective),
            'capital_information' => normalizeInline($capital),
            'creation_act' => normalizeInline($creationAct),
            'internal_regulations' => $internalRegulations,
            'subsidiaries' => $subsidiaries,
            'official_website' => $website,
        ];
    }

    return $companies;
}

function extractUpdatedDate(string $content): ?string
{
    if (!preg_match('/Atualizado em\s+(\d{2}\/\d{2}\/\d{2})/iu', $content, $matches)) {
        return null;
    }

    $raw = trim((string) ($matches[1] ?? ''));
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $raw, $parts)) {
        return null;
    }

    $day = (int) $parts[1];
    $month = (int) $parts[2];
    $year = 2000 + (int) $parts[3];

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function extractBetween(string $block, string $startPattern, array $endPatterns): ?string
{
    $end = implode('|', $endPatterns);
    $pattern = '/(?:' . $startPattern . ')\s*(.+?)(?=\n\s*(?:' . $end . ')|\z)/uis';
    if (!preg_match($pattern, $block, $matches)) {
        return null;
    }

    return trim((string) ($matches[1] ?? ''));
}

function extractLineField(string $block, string $labelPattern): ?string
{
    $pattern = '/^\s*' . $labelPattern . '\s*:\s*(.*)$/umi';
    if (!preg_match($pattern, $block, $matches)) {
        return null;
    }

    return trim((string) ($matches[1] ?? ''));
}

function normalizeInline(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value, " \t\n\r\0\x0B-");

    return $value === '' ? null : $value;
}

function normalizeMultiline(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);
    $normalized = [];
    foreach ($lines as $line) {
        $line = normalizeInline($line);
        if ($line === null) {
            continue;
        }
        $normalized[] = $line;
    }

    if ($normalized === []) {
        return null;
    }

    return implode("\n", array_values(array_unique($normalized)));
}

/**
 * @return array{formatted: ?string, digits: ?string}
 */
function normalizeCnpj(?string $value): array
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return ['formatted' => null, 'digits' => null];
    }

    $raw = str_replace('/00001-', '/0001-', $raw);
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits) || $digits === '') {
        return ['formatted' => null, 'digits' => null];
    }

    if (strlen($digits) === 13) {
        $digits = '0' . $digits;
    }

    if (strlen($digits) === 15 && preg_match('/^(\d{8})0(\d{4})(\d{2})$/', $digits, $parts)) {
        $digits = $parts[1] . $parts[2] . $parts[3];
    }

    if (strlen($digits) !== 14) {
        return ['formatted' => null, 'digits' => null];
    }

    $formatted = substr($digits, 0, 2)
        . '.' . substr($digits, 2, 3)
        . '.' . substr($digits, 5, 3)
        . '/' . substr($digits, 8, 4)
        . '-' . substr($digits, 12, 2);

    return ['formatted' => $formatted, 'digits' => $digits];
}

function normalizeCompanyName(string $name): string
{
    $name = normalizeInline($name) ?? '';
    $name = preg_replace('/\s*[\'"“”‘’]?\s*em\s+liquida[cç][aã]o\s*[\'"“”‘’]?\s*$/iu', '', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return trim($name);
}

function normalizeUrl(?string $value): ?string
{
    $value = normalizeInline($value);
    if ($value === null) {
        return null;
    }

    if (!preg_match('#^[a-z]+://#i', $value) && preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}.*$/i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    return $value;
}

function extractZipCode(?string $address): ?string
{
    if ($address === null) {
        return null;
    }

    if (!preg_match('/(\d{2}[.\-]?\d{3}[.\-]?\d{3})/u', $address, $matches)) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', (string) ($matches[1] ?? ''));
    if (!is_string($digits) || strlen($digits) !== 8) {
        return null;
    }

    return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
}

function resolvePath(string $path, string $basePath): string
{
    if ($path === '') {
        return $basePath;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    return $basePath . '/' . ltrim($path, '/');
}

/**
 * @param array<int, string> $argv
 */
function readOptionValue(array $argv, int &$index, string $option): string
{
    $valueIndex = $index + 1;
    if (!isset($argv[$valueIndex])) {
        fail(sprintf('valor ausente para %s', $option));
    }

    $index = $valueIndex;
    $value = trim($argv[$valueIndex]);
    if ($value === '') {
        fail(sprintf('valor invalido para %s', $option));
    }

    return $value;
}

function parseIntOption(string $value, string $option, int $min, int $max): int
{
    if (!preg_match('/^\d+$/', $value)) {
        fail(sprintf('%s deve ser inteiro.', $option));
    }

    $intValue = (int) $value;
    if ($intValue < $min || $intValue > $max) {
        fail(sprintf('%s deve estar entre %d e %d.', $option, $min, $max));
    }

    return $intValue;
}

function printUsage(): void
{
    echo <<<TXT
Uso:
  php scripts/import-df-state-owned-companies.php [opcoes]

Opcoes:
  --markdown <arquivo>      Markdown fonte (padrao: docs/empresaspublicasdf.md)
  --source-name <texto>     Nome da fonte de dados
  --source-url <url>        URL da fonte de dados
  --user-id <id>            Usuario para auditoria/eventos (padrao: 1)
  --ip <ip>                 IP de origem para auditoria (padrao: 127.0.0.1)
  --user-agent <texto>      User-Agent para auditoria
  --validate-only           Executa somente validacao (rollback no fim)
  --help, -h                Exibe esta ajuda

TXT;
}

function fail(string $message): void
{
    fwrite(STDERR, '[erro] ' . $message . PHP_EOL);
    exit(1);
}
