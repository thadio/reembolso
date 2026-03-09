#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\App;

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

    $app = require $basePath . '/bootstrap.php';
    if (!$app instanceof App) {
        fail('falha ao inicializar a aplicacao.');
    }

    $csvPath = (string) $options['csv'];
    $parsed = parseCsv($csvPath);
    if (!$parsed['ok']) {
        fail((string) $parsed['error']);
    }

    $rows = (array) ($parsed['rows'] ?? []);
    if ($rows === []) {
        outputResult([
            'csv' => $csvPath,
            'mode' => $options['validate_only'] ? 'validate_only' : 'import',
            'ok' => false,
            'message' => 'CSV sem linhas validas para importacao.',
            'processed_rows' => 0,
            'inserted_count' => 0,
            'updated_count' => 0,
            'errors' => ['Nenhuma linha valida encontrada.'],
            'warnings' => [],
        ]);
        exit(1);
    }

    $db = $app->db();
    ensureRequiredColumns($db);

    if ((bool) $options['validate_only'] === true) {
        outputResult([
            'csv' => $csvPath,
            'mode' => 'validate_only',
            'ok' => true,
            'message' => sprintf('Validacao concluida com sucesso: %d linha(s) apta(s).', count($rows)),
            'processed_rows' => count($rows),
            'inserted_count' => 0,
            'updated_count' => 0,
            'errors' => [],
            'warnings' => [],
        ]);
        exit(0);
    }

    $inserted = 0;
    $updated = 0;

    try {
        $tmpTable = 'tmp_mte_uorg_import';
        $db->exec(
            'CREATE TEMPORARY TABLE IF NOT EXISTS ' . $tmpTable . ' (
                code VARCHAR(60) NOT NULL PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                acronym VARCHAR(60) NULL,
                uf CHAR(2) NULL,
                upag_code VARCHAR(20) NULL,
                parent_uorg_code VARCHAR(20) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $db->exec('TRUNCATE TABLE ' . $tmpTable);

        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }

        loadRowsIntoTempTable($db, $tmpTable, $rows);

        $updatedRow = $db->query(
            'SELECT COUNT(*) AS total
             FROM mte_destinations d
             INNER JOIN ' . $tmpTable . ' t ON t.code = d.code'
        );
        $updated = (int) (($updatedRow !== false ? $updatedRow->fetch()['total'] : 0) ?? 0);

        $db->exec(
            'UPDATE mte_destinations d
             INNER JOIN ' . $tmpTable . ' t ON t.code = d.code
             SET
                d.name = t.name,
                d.acronym = t.acronym,
                d.uf = t.uf,
                d.upag_code = t.upag_code,
                d.parent_uorg_code = t.parent_uorg_code,
                d.deleted_at = NULL,
                d.updated_at = NOW()'
        );

        $insertedResult = $db->exec(
            'INSERT INTO mte_destinations (
                name,
                code,
                acronym,
                uf,
                upag_code,
                parent_uorg_code,
                created_at,
                updated_at,
                deleted_at
            )
            SELECT
                t.name,
                t.code,
                t.acronym,
                t.uf,
                t.upag_code,
                t.parent_uorg_code,
                NOW(),
                NOW(),
                NULL
            FROM ' . $tmpTable . ' t
            LEFT JOIN mte_destinations d ON d.code = t.code
            WHERE d.id IS NULL'
        );
        $inserted = is_int($insertedResult) ? $insertedResult : 0;

        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $throwable) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        outputResult([
            'csv' => $csvPath,
            'mode' => 'import',
            'ok' => false,
            'message' => 'Falha durante importacao.',
            'processed_rows' => count($rows),
            'inserted_count' => 0,
            'updated_count' => 0,
            'errors' => [$throwable->getMessage()],
            'warnings' => [],
        ]);
        exit(1);
    }

    outputResult([
        'csv' => $csvPath,
        'mode' => 'import',
        'ok' => true,
        'message' => sprintf(
            'Importacao concluida: %d inserida(s), %d atualizada(s), total %d.',
            $inserted,
            $updated,
            count($rows)
        ),
        'processed_rows' => count($rows),
        'inserted_count' => $inserted,
        'updated_count' => $updated,
        'errors' => [],
        'warnings' => [],
    ]);
}

/**
 * @param array<int, array{
 *   code: string,
 *   name: string,
 *   acronym: ?string,
 *   uf: ?string,
 *   upag_code: ?string,
 *   parent_uorg_code: ?string
 * }> $rows
 */
function loadRowsIntoTempTable(PDO $db, string $table, array $rows): void
{
    $chunkSize = 250;
    $chunks = array_chunk($rows, $chunkSize);

    foreach ($chunks as $chunkIndex => $chunk) {
        $values = [];
        $params = [];

        foreach ($chunk as $rowIndex => $row) {
            $suffix = '_' . $chunkIndex . '_' . $rowIndex;
            $values[] = sprintf(
                '(:code%s, :name%s, :acronym%s, :uf%s, :upag_code%s, :parent_uorg_code%s)',
                $suffix,
                $suffix,
                $suffix,
                $suffix,
                $suffix,
                $suffix
            );

            $params['code' . $suffix] = $row['code'];
            $params['name' . $suffix] = $row['name'];
            $params['acronym' . $suffix] = $row['acronym'];
            $params['uf' . $suffix] = $row['uf'];
            $params['upag_code' . $suffix] = $row['upag_code'];
            $params['parent_uorg_code' . $suffix] = $row['parent_uorg_code'];
        }

        $sql = 'INSERT INTO ' . $table . ' (
                    code,
                    name,
                    acronym,
                    uf,
                    upag_code,
                    parent_uorg_code
                ) VALUES ' . implode(', ', $values) . '
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    acronym = VALUES(acronym),
                    uf = VALUES(uf),
                    upag_code = VALUES(upag_code),
                    parent_uorg_code = VALUES(parent_uorg_code)';

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('falha ao preparar insercao em lote da tabela temporaria.');
        }

        $stmt->execute($params);
    }
}

/**
 * @param array<int, string> $argv
 * @return array{csv: string, validate_only: bool, help: bool}
 */
function parseOptions(array $argv, string $basePath): array
{
    $csv = $basePath . '/storage/imports/mte_uorg.csv';
    $validateOnly = false;
    $help = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--csv':
                $csv = resolvePath(readOptionValue($argv, $i, '--csv'), $basePath);
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

    if ($help === false && !is_file($csv)) {
        fail(sprintf('arquivo CSV nao encontrado: %s', $csv));
    }

    return [
        'csv' => $csv,
        'validate_only' => $validateOnly,
        'help' => $help,
    ];
}

/**
 * @return array{
 *   ok: bool,
 *   error: string,
 *   rows: array<int, array{
 *     code: string,
 *     name: string,
 *     acronym: ?string,
 *     uf: ?string,
 *     upag_code: ?string,
 *     parent_uorg_code: ?string
 *   }>
 * }
 */
function parseCsv(string $csvPath): array
{
    $content = file_get_contents($csvPath);
    if (!is_string($content) || trim($content) === '') {
        return [
            'ok' => false,
            'error' => 'arquivo CSV vazio ou ilegivel.',
            'rows' => [],
        ];
    }

    $normalizedContent = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = array_values(array_filter(
        array_map(static fn (string $line): string => trim($line), explode("\n", $normalizedContent)),
        static fn (string $line): bool => $line !== ''
    ));

    if ($lines === []) {
        return [
            'ok' => false,
            'error' => 'arquivo CSV sem conteudo processavel.',
            'rows' => [],
        ];
    }

    $headerRow = str_getcsv((string) ($lines[0] ?? ''), ';', '"', '\\');
    if (!is_array($headerRow) || $headerRow === []) {
        return [
            'ok' => false,
            'error' => 'cabecalho CSV invalido.',
            'rows' => [],
        ];
    }

    $headerMap = buildHeaderMap($headerRow);
    $required = ['uorg_code', 'name'];
    $missing = [];
    foreach ($required as $requiredKey) {
        if (!isset($headerMap[$requiredKey])) {
            $missing[] = $requiredKey;
        }
    }

    if ($missing !== []) {
        return [
            'ok' => false,
            'error' => 'cabecalho obrigatorio ausente: ' . implode(', ', $missing),
            'rows' => [],
        ];
    }

    $errors = [];
    $rows = [];
    $seenCodes = [];

    for ($lineIndex = 1; $lineIndex < count($lines); $lineIndex++) {
        $lineNumber = $lineIndex + 1;
        $parsed = str_getcsv((string) $lines[$lineIndex], ';', '"', '\\');
        if (!is_array($parsed) || $parsed === []) {
            continue;
        }

        $mapped = mapRow($parsed, $headerMap);
        if (isRowEmpty($mapped)) {
            continue;
        }

        $code = normalizeField((string) ($mapped['uorg_code'] ?? ''), 60, true);
        $name = normalizeField((string) ($mapped['name'] ?? ''), 190, false);
        $acronym = normalizeField((string) ($mapped['acronym'] ?? ''), 60, true);
        $uf = normalizeField((string) ($mapped['uf'] ?? ''), 2, true);
        $upagCode = normalizeField((string) ($mapped['upag_code'] ?? ''), 20, true);
        $parentUorgCode = normalizeField((string) ($mapped['parent_uorg_code'] ?? ''), 20, true);

        if ($code === null) {
            $errors[] = sprintf('Linha %d: codigo UORG ausente.', $lineNumber);
            continue;
        }

        if ($name === null) {
            $errors[] = sprintf('Linha %d: nome da unidade ausente.', $lineNumber);
            continue;
        }

        if (isset($seenCodes[$code])) {
            $errors[] = sprintf('Linha %d: codigo UORG duplicado (ja usado na linha %d).', $lineNumber, $seenCodes[$code]);
            continue;
        }
        $seenCodes[$code] = $lineNumber;

        if ($uf !== null && preg_match('/^[A-Z]{2}$/', $uf) !== 1) {
            $errors[] = sprintf('Linha %d: UF invalida.', $lineNumber);
            continue;
        }

        $rows[] = [
            'code' => $code,
            'name' => $name,
            'acronym' => $acronym,
            'uf' => $uf,
            'upag_code' => $upagCode,
            'parent_uorg_code' => $parentUorgCode,
        ];
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'error' => implode(' ', $errors),
            'rows' => [],
        ];
    }

    return [
        'ok' => true,
        'error' => '',
        'rows' => $rows,
    ];
}

/**
 * @param array<int, string> $headers
 * @return array<string, int>
 */
function buildHeaderMap(array $headers): array
{
    $aliases = [
        'uorg_code' => ['gr_identificacao_uorg'],
        'name' => ['it_no_unidade_organizacional'],
        'acronym' => ['it_sg_unidade_organizacional'],
        'uf' => ['it_sg_uf'],
        'upag_code' => ['gr_cest_identificacao_upag'],
        'parent_uorg_code' => ['it_co_uorg_vinculacao'],
    ];

    $lookup = [];
    foreach ($aliases as $canonical => $options) {
        foreach ($options as $option) {
            $lookup[normalizeHeader($option)] = $canonical;
        }
    }

    $map = [];
    foreach ($headers as $index => $header) {
        $normalized = normalizeHeader($header);
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
function mapRow(array $row, array $headerMap): array
{
    $mapped = [];
    foreach ($headerMap as $key => $index) {
        $mapped[$key] = trim((string) ($row[$index] ?? ''));
    }

    return $mapped;
}

/** @param array<string, string> $row */
function isRowEmpty(array $row): bool
{
    foreach ($row as $value) {
        if (trim($value) !== '') {
            return false;
        }
    }

    return true;
}

function normalizeField(string $value, int $maxLength, bool $upper): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if ($upper) {
        $value = mb_strtoupper($value);
    }

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function ensureRequiredColumns(PDO $db): void
{
    $stmt = $db->query(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'mte_destinations'
           AND COLUMN_NAME IN ('acronym', 'uf', 'upag_code', 'parent_uorg_code')"
    );

    $rows = $stmt !== false ? $stmt->fetchAll() : [];
    $present = [];
    foreach ($rows as $row) {
        $present[] = (string) ($row['COLUMN_NAME'] ?? '');
    }

    $missing = array_values(array_diff(
        ['acronym', 'uf', 'upag_code', 'parent_uorg_code'],
        $present
    ));

    if ($missing !== []) {
        fail('schema desatualizado. execute "php db/migrate.php" antes de importar UORG.');
    }
}

function normalizeHeader(string $header): string
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

    return trim($header, '_');
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
    $value = trim((string) $argv[$valueIndex]);
    if ($value === '') {
        fail(sprintf('valor invalido para %s', $option));
    }

    return $value;
}

/** @param array<string, mixed> $result */
function outputResult(array $result): void
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
}

function printUsage(): void
{
    echo <<<TXT
Uso:
  php scripts/import-mte-uorg-csv.php [opcoes]

Opcoes:
  --csv <arquivo>    CSV fonte de UORG (padrao: storage/imports/mte_uorg.csv)
  --validate-only    Executa apenas validacao (nao grava)
  --help, -h         Exibe esta ajuda

TXT;
}

function fail(string $message): void
{
    fwrite(STDERR, '[erro] ' . $message . PHP_EOL);
    exit(1);
}
