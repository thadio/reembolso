#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Bootstrap oficial de schema.
 *
 * Uso:
 *   php scripts/bootstrap-db.php
 *   php scripts/bootstrap-db.php --target=remote
 *   php scripts/bootstrap-db.php --json
 *   php scripts/bootstrap-db.php --no-verify
 */

$options = getopt('', ['help', 'json', 'no-verify', 'target:']);

if (isset($options['help'])) {
    echo "Uso: php scripts/bootstrap-db.php [--target=local|remote] [--json] [--no-verify]" . PHP_EOL;
    exit(0);
}

$targetOverride = isset($options['target']) ? strtolower(trim((string) $options['target'])) : '';
if ($targetOverride !== '') {
    if (!in_array($targetOverride, ['local', 'remote'], true)) {
        fwrite(STDERR, "Valor inválido para --target. Use local ou remote." . PHP_EOL);
        exit(2);
    }
    putenv('DB_TARGET=' . $targetOverride);
    $_ENV['DB_TARGET'] = $targetOverride;
    $_SERVER['DB_TARGET'] = $targetOverride;
}

require __DIR__ . '/../bootstrap.php';

use App\Core\SchemaGuard;
use App\Services\SchemaBootstrapService;

[$pdo, $connectionError, $config] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, 'Erro de conexão: ' . ($connectionError ?: 'desconhecido') . PHP_EOL);
    exit(1);
}

$service = new SchemaBootstrapService($pdo);
$results = $service->run();

$counts = ['ok' => 0, 'missing' => 0, 'error' => 0];
foreach ($results as $item) {
    $status = (string) ($item['status'] ?? 'error');
    if (!isset($counts[$status])) {
        $counts[$status] = 0;
    }
    $counts[$status]++;
}

$schemaError = null;
if (!isset($options['no-verify'])) {
    $schemaError = SchemaGuard::validate($pdo);
}

$payload = [
    'db_target' => getenv('DB_TARGET') ?: 'auto',
    'database' => [
        'host' => (string) ($config['host'] ?? ''),
        'port' => (string) ($config['port'] ?? ''),
        'name' => (string) ($config['name'] ?? ''),
    ],
    'summary' => $counts,
    'schema_guard' => $schemaError === null ? 'ok' : 'error',
    'schema_guard_message' => $schemaError,
    'results' => $results,
];

$hasFailures = ($counts['missing'] ?? 0) > 0 || ($counts['error'] ?? 0) > 0 || $schemaError !== null;
$exitCode = $hasFailures ? 1 : 0;

if (isset($options['json'])) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($exitCode);
}

echo '== Bootstrap de Schema ==' . PHP_EOL;
echo 'DB target: ' . ($payload['db_target'] ?: 'auto') . PHP_EOL;
echo 'Database: ' . $payload['database']['host'] . ':' . $payload['database']['port'] . '/' . $payload['database']['name'] . PHP_EOL;
echo 'Resultados: ok=' . ($counts['ok'] ?? 0) . ' missing=' . ($counts['missing'] ?? 0) . ' error=' . ($counts['error'] ?? 0) . PHP_EOL;

echo PHP_EOL . 'Detalhes:' . PHP_EOL;
foreach ($results as $item) {
    $status = strtoupper((string) ($item['status'] ?? 'error'));
    $key = (string) ($item['key'] ?? '?');
    $class = (string) ($item['class'] ?? '?');
    echo '- [' . $status . '] ' . $key . ' (' . $class . ')';
    if (!empty($item['message'])) {
        echo ' => ' . $item['message'];
    }
    echo PHP_EOL;
}

if ($schemaError === null) {
    echo PHP_EOL . 'SchemaGuard: OK' . PHP_EOL;
} else {
    echo PHP_EOL . 'SchemaGuard: ERRO' . PHP_EOL;
    echo $schemaError . PHP_EOL;
}

exit($exitCode);
