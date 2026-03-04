<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        $host = (string) $config->get('db.host', '127.0.0.1');
        $port = (string) $config->get('db.port', '3306');
        $database = (string) $config->get('db.name', '');
        $charset = (string) $config->get('db.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

        return new PDO($dsn, (string) $config->get('db.user', ''), (string) $config->get('db.pass', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
