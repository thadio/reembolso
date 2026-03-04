<?php
require_once __DIR__ . '/app/autoload.php';

function loadEnvFile(string $path): void {
  if (!is_readable($path)) {
    return;
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) {
    return;
  }

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
      continue;
    }
    if (strpos($line, '=') === false) {
      continue;
    }
    [$name, $value] = explode('=', $line, 2);
    $name = trim($name);
    if ($name === '') {
      continue;
    }
    $value = trim($value);
    $quote = $value !== '' ? $value[0] : '';
    if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
      $value = substr($value, 1, -1);
    }

    if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
      putenv($name . '=' . $value);
      $_ENV[$name] = $value;
      $_SERVER[$name] = $value;
    }
  }
}

function setEnvValue(string $name, string $value): void
{
  putenv($name . '=' . $value);
  $_ENV[$name] = $value;
  $_SERVER[$name] = $value;
}

function envVarExists(string $name): bool
{
  return array_key_exists($name, $_ENV) || getenv($name) !== false;
}

function isLocalRuntime(): bool
{
  $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
  $host = explode(':', $host)[0];

  if ($host !== '') {
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
      return true;
    }

    if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
      return true;
    }

    return false;
  }

  $serverAddr = strtolower((string) ($_SERVER['SERVER_ADDR'] ?? ''));
  if ($serverAddr !== '') {
    if ($serverAddr === '127.0.0.1' || $serverAddr === '::1') {
      return true;
    }

    return false;
  }

  // Em CLI, preferimos local por padrão para reduzir risco de alterações acidentais em produção.
  if (PHP_SAPI === 'cli') {
    return true;
  }

  return false;
}

function resolveProfileTarget(string $selectorVar, ?string $fallbackTarget = null): string
{
  $target = strtolower(trim((string) (getenv($selectorVar) ?: 'auto')));
  if ($target === 'local' || $target === 'remote') {
    return $target;
  }

  if ($fallbackTarget === 'local' || $fallbackTarget === 'remote') {
    return $fallbackTarget;
  }

  return isLocalRuntime() ? 'local' : 'remote';
}

function applyScopedProfileValue(string $scope, string $target, string $key, ?string $genericVar = null): void
{
  $profileVar = $scope . '_' . strtoupper($target) . '_' . $key;
  if (!envVarExists($profileVar)) {
    return;
  }

  $genericVar ??= $scope . '_' . $key;
  $value = (string) (getenv($profileVar) !== false ? getenv($profileVar) : ($_ENV[$profileVar] ?? ''));
  setEnvValue($genericVar, $value);
}

function applyScopedProfile(string $scope, string $target, array $keys): void
{
  foreach ($keys as $key) {
    applyScopedProfileValue($scope, $target, $key);
  }
}

loadEnvFile(__DIR__ . '/.env');

$appTarget = resolveProfileTarget('APP_TARGET');
setEnvValue('APP_TARGET_RESOLVED', $appTarget);
applyScopedProfile('APP', $appTarget, [
  'ENV',
  'DEBUG',
  'NAME',
  'BASE_URL',
  'TIMEZONE',
  'UPLOAD_DIR',
  'UPLOAD_BASE_URL',
  'UPLOAD_TEMP',
  'LOG_LEVEL',
  'FEATURE_FLAGS',
]);

$mailTarget = resolveProfileTarget('MAIL_TARGET', $appTarget);
setEnvValue('MAIL_TARGET_RESOLVED', $mailTarget);
applyScopedProfile('MAIL', $mailTarget, [
  'HOST',
  'PORT',
  'USERNAME',
  'PASSWORD',
  'FROM_EMAIL',
  'FROM_NAME',
]);
applyScopedProfileValue('MAIL', $mailTarget, 'MAILER_DRIVER', 'MAILER_DRIVER');

$cepDbTarget = resolveProfileTarget('CEP_DB_TARGET', $appTarget);
setEnvValue('CEP_DB_TARGET_RESOLVED', $cepDbTarget);
applyScopedProfile('CEP_DB', $cepDbTarget, [
  'HOST',
  'PORT',
  'NAME',
  'USER',
  'PASS',
  'CHARSET',
]);

date_default_timezone_set((string) (getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo'));

use App\Core\Database;
use App\Core\SchemaGuard;
use App\Support\Auth;
use App\Support\SchemaBootstrapper;

/**
 * Helper centralizado para configurar o banco automaticamente.
 * Expõe o contrato procedural para chamadas existentes
 * mas delega para a classe Database com POO.
 */
function dbConfig(): array {
  return Database::config();
}

function makeDsn(array $config, bool $withDb = true): string {
  return Database::makeDsn($config, $withDb);
}

/**
 * Tenta criar o banco (idempotente). Se não tiver permissão, apenas retorna o erro.
 */
function ensureDatabaseExists(array $config): ?string {
  return Database::ensureDatabaseExists($config);
}

/**
 * Retorna [PDO|null, string|null, array $config].
 * A string contém o erro acumulado de criação/conexão se algo falhar.
 */
function bootstrapPdo(): array {
  static $cachedPdo = null;
  static $cachedConfig = null;
  static $cachedError = null;

  [$pdo, $connectionError, $config] = Database::bootstrap();

  if ($pdo && PHP_SAPI !== 'cli' && !shouldRunSchemaMigrations()) {
    $schemaError = SchemaGuard::validate($pdo);
    if ($schemaError !== null) {
      return [null, $schemaError, $config];
    }
  }

  return [$pdo, $connectionError, $config];
}

/**
 * Controla se as rotinas de criação/alteração de esquema devem ser executadas
 * automaticamente em cada request. O modo padrão é desligado, e somente o
 * bootstrap manual ativa esse comportamento temporariamente.
 *
 * Uso: repositories chamam `shouldRunSchemaMigrations()` antes de executar DDL.
 */
function shouldRunSchemaMigrations(): bool
{
  return SchemaBootstrapper::isEnabled();
}

if (PHP_SAPI !== 'cli') {
  Auth::start();
  maybeSyncPeople();
}

function currentUser(): ?array {
  return Auth::user();
}

function requireLogin(?PDO $pdo = null): void {
  Auth::requireLogin($pdo);
}

function requirePermission(?PDO $pdo, string $permission): void {
  Auth::requirePermission($permission, $pdo);
}

function userCan(string $permission): bool {
  return Auth::can($permission);
}

function image_url(string $path, string $kind = 'original', ?int $size = null): string
{
  return \App\Support\Image::imageUrl($path, $kind, $size);
}

function maybeSyncPeople(): void
{
  $autoSync = strtolower((string) (getenv('AUTO_SYNC_PEOPLE') ?: 'on'));
  if (in_array($autoSync, ['off', 'false', '0', 'no'], true)) {
    return;
  }
  static $ran = false;
  if ($ran) {
    return;
  }
  $ran = true;
  [$pdo] = bootstrapPdo();
  if (!$pdo) {
    return;
  }
}
