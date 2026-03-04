<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Responsável por entregar uma conexão PDO e garantir que o banco existe.
 * Expõe o contrato de conexão usado pelo bootstrap procedural.
 */
class Database
{
    public static function config(): array
    {
        $target = self::resolveDbTarget();

        if ($target === 'remote') {
            return [
                'host' => getenv('DB_REMOTE_HOST') ?: '',
                'port' => getenv('DB_REMOTE_PORT') ?: '3306',
                'name' => getenv('DB_REMOTE_NAME') ?: '',
                'user' => getenv('DB_REMOTE_USER') ?: '',
                'pass' => getenv('DB_REMOTE_PASS') ?: '',
                'charset' => getenv('DB_REMOTE_CHARSET') ?: 'utf8mb4',
            ];
        }

        return [
            'host' => getenv('DB_LOCAL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => getenv('DB_LOCAL_PORT') ?: (getenv('DB_PORT') ?: '3306'),
            'name' => getenv('DB_LOCAL_NAME') ?: (getenv('DB_NAME') ?: 'brecho_app'),
            'user' => getenv('DB_LOCAL_USER') ?: (getenv('DB_USER') ?: 'root'),
            'pass' => getenv('DB_LOCAL_PASS') ?: (getenv('DB_PASS') ?: '12345678'),
            'charset' => getenv('DB_LOCAL_CHARSET') ?: (getenv('DB_CHARSET') ?: 'utf8mb4'),
        ];
    }

    public static function makeDsn(array $config, bool $withDb = true): string
    {
        $base = "mysql:host={$config['host']};port={$config['port']}";
        if ($withDb) {
            $base .= ";dbname={$config['name']}";
        }
        $base .= ";charset={$config['charset']}";
        return $base;
    }

    /**
     * @return array{0: PDO|null, 1: string|null, 2: array}
     */
    public static function bootstrap(): array
    {
        $config = self::config();
        $databaseError = self::ensureDatabaseExists($config);

        $pdo = null;
        $connectionError = null;

        try {
            // Use plain PDO connection now that audit logging has been removed.
            $persistentValue = strtolower((string) (getenv('DB_PERSISTENT') ?: ''));
            $persistent = in_array($persistentValue, ['1', 'true', 'yes'], true);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_PERSISTENT => $persistent,
            ];
            $pdo = new PDO(self::makeDsn($config), $config['user'], $config['pass'], $options);
            // Ensure connection collation matches table collation (utf8mb4_unicode_ci)
            // to prevent "Illegal mix of collations" errors with CAST/LIKE.
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::applyTimezone($pdo);
        } catch (PDOException $e) {
            $connectionError = $e->getMessage();
        }

        if (!$pdo && $databaseError !== null) {
            $connectionError = 'Falha ao garantir o banco de dados: ' . $databaseError
                . ' | Falha ao conectar: ' . ($connectionError ?? 'erro indefinido');
        }

        return [$pdo, $connectionError, $config];
    }

    private static function applyTimezone(PDO $pdo): void
    {
        $target = self::resolveDbTarget();
        $profileTimezone = $target === 'remote'
            ? getenv('DB_REMOTE_TIMEZONE')
            : getenv('DB_LOCAL_TIMEZONE');
        $timezone = $profileTimezone ?: (getenv('DB_TIMEZONE') ?: (getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo'));
        $fallback = '-03:00';

        try {
            $pdo->exec('SET time_zone = ' . $pdo->quote($timezone));
            return;
        } catch (PDOException $e) {
            // fallback below
        }

        if ($timezone === $fallback) {
            return;
        }

        try {
            $pdo->exec('SET time_zone = ' . $pdo->quote($fallback));
        } catch (PDOException $e) {
            // ignore if server does not allow changing session timezone
        }
    }

    public static function ensureDatabaseExists(array $config): ?string
    {
        static $attempted = false;
        static $lastError = null;
        if ($attempted) {
            return $lastError;
        }
        $attempted = true;

        try {
            $pdo = new PDO(self::makeDsn($config, false), $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $dbName = str_replace('`', '``', $config['name']);
            $charset = $config['charset'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
            $lastError = null;
            return null;
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
            return $lastError;
        }
    }

    private static function resolveDbTarget(): string
    {
        $target = strtolower(trim((string) (getenv('DB_TARGET') ?: 'auto')));

        if ($target === 'local' || $target === 'remote') {
            return $target;
        }

        if (self::isLocalRuntime()) {
            return 'local';
        }

        return 'remote';
    }

    private static function isLocalRuntime(): bool
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

        // Em execucao CLI, preferimos local por seguranca (evita escrita acidental em producao).
        if (PHP_SAPI === 'cli') {
            return true;
        }

        return false;
    }
}
