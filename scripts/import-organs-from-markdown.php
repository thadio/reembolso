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

    $app = require $basePath . '/bootstrap.php';
    if (!$app instanceof App) {
        fail('falha ao inicializar a aplicacao.');
    }

    $markdownPath = (string) $options['markdown'];
    $csvBody = extractCsvBlock($markdownPath);

    $targetCsvPath = (string) $options['output_csv'];
    ensureDirectory(dirname($targetCsvPath));
    $bytes = file_put_contents($targetCsvPath, $csvBody . "\n");
    if (!is_int($bytes) || $bytes <= 0) {
        fail('falha ao escrever CSV temporario para importacao.');
    }

    $size = filesize($targetCsvPath);
    if (!is_int($size) || $size <= 0) {
        fail('arquivo CSV gerado esta vazio.');
    }

    $service = new OrganService(
        new OrganRepository($app->db()),
        $app->audit(),
        $app->events()
    );

    $result = $service->importCsv(
        [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $targetCsvPath,
            'name' => basename($targetCsvPath),
            'size' => $size,
        ],
        (int) $options['user_id'],
        (string) $options['ip'],
        (string) $options['user_agent'],
        (bool) $options['validate_only']
    );

    outputResult($result, $markdownPath, $targetCsvPath, (bool) $options['validate_only']);

    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

/**
 * @param array<int, string> $argv
 * @return array{markdown: string, output_csv: string, user_id: int, ip: string, user_agent: string, validate_only: bool, help: bool}
 */
function parseOptions(array $argv, string $basePath): array
{
    $markdown = $basePath . '/docs/orgaos_estatais_autarquias_etc.md';
    $outputCsv = $basePath . '/storage/imports/orgaos_estatais_autarquias_etc.csv';
    $userId = 1;
    $ip = '127.0.0.1';
    $userAgent = 'cli/import-organs-from-markdown';
    $validateOnly = false;
    $help = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        switch ($arg) {
            case '--markdown':
                $markdown = resolvePath(readOptionValue($argv, $i, '--markdown'), $basePath);
                break;
            case '--output-csv':
                $outputCsv = resolvePath(readOptionValue($argv, $i, '--output-csv'), $basePath);
                break;
            case '--user-id':
                $userId = parseIntOption(readOptionValue($argv, $i, '--user-id'), '--user-id', 0, 999999999);
                break;
            case '--ip':
                $ip = trim(readOptionValue($argv, $i, '--ip'));
                if ($ip === '') {
                    fail('valor invalido para --ip');
                }
                break;
            case '--user-agent':
                $userAgent = trim(readOptionValue($argv, $i, '--user-agent'));
                if ($userAgent === '') {
                    fail('valor invalido para --user-agent');
                }
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
        'output_csv' => $outputCsv,
        'user_id' => $userId,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'validate_only' => $validateOnly,
        'help' => $help,
    ];
}

function extractCsvBlock(string $markdownPath): string
{
    $content = file_get_contents($markdownPath);
    if (!is_string($content) || trim($content) === '') {
        fail('arquivo markdown vazio ou ilegivel para extracao CSV.');
    }

    if (!preg_match('/```csv\s*(.*?)```/is', $content, $matches)) {
        fail('bloco CSV nao encontrado no markdown.');
    }

    $csv = trim((string) ($matches[1] ?? ''));
    if ($csv === '') {
        fail('bloco CSV encontrado, mas sem conteudo.');
    }

    return $csv;
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

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail(sprintf('nao foi possivel criar diretorio: %s', $directory));
    }
}

/** @param array<string, mixed> $result */
function outputResult(array $result, string $markdownPath, string $csvPath, bool $validateOnly): void
{
    $payload = [
        'markdown' => $markdownPath,
        'csv_materialized' => $csvPath,
        'mode' => $validateOnly ? 'validate_only' : 'import',
        'ok' => (bool) ($result['ok'] ?? false),
        'message' => (string) ($result['message'] ?? ''),
        'processed_rows' => (int) ($result['processed_rows'] ?? 0),
        'created_count' => (int) ($result['created_count'] ?? 0),
        'errors' => array_values(array_map('strval', (array) ($result['errors'] ?? []))),
        'warnings' => array_values(array_map('strval', (array) ($result['warnings'] ?? []))),
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function printUsage(): void
{
    echo <<<TXT
Uso:
  php scripts/import-organs-from-markdown.php [opcoes]

Opcoes:
  --markdown <arquivo>      Markdown fonte (padrao: docs/orgaos_estatais_autarquias_etc.md)
  --output-csv <arquivo>    CSV materializado (padrao: storage/imports/orgaos_estatais_autarquias_etc.csv)
  --user-id <id>            Usuario para auditoria/eventos (padrao: 1)
  --ip <ip>                 IP de origem para auditoria (padrao: 127.0.0.1)
  --user-agent <texto>      User-Agent para auditoria (padrao: cli/import-organs-from-markdown)
  --validate-only           Executa apenas validacao (nao grava)
  --help, -h                Exibe esta ajuda

TXT;
}

function fail(string $message): void
{
    fwrite(STDERR, '[erro] ' . $message . PHP_EOL);
    exit(1);
}
