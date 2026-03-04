<?php

namespace App\Integrations;

use PDO;
use PDOException;

class CepDatabase
{
    public static function config(): array
    {
        return [
            'host' => getenv('CEP_DB_HOST') ?: '',
            'port' => getenv('CEP_DB_PORT') ?: '3306',
            'name' => getenv('CEP_DB_NAME') ?: '',
            'user' => getenv('CEP_DB_USER') ?: '',
            'pass' => getenv('CEP_DB_PASS') ?: '',
            'charset' => getenv('CEP_DB_CHARSET') ?: 'utf8mb4',
        ];
    }

    public static function makeDsn(array $config): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );
    }

    /**
     * @return array{0: PDO|null, 1: string|null, 2: array}
     */
    public static function bootstrap(): array
    {
        $config = self::config();
        $pdo = null;
        $error = null;

        $missing = [];
        foreach (['host', 'name', 'user', 'pass'] as $field) {
            if ($config[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing) {
            $error = 'Configuração do banco de CEP incompleta.';
            return [$pdo, $error, $config];
        }

        try {
            $pdo = new PDO(self::makeDsn($config), $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }

        return [$pdo, $error, $config];
    }
}
