<?php

namespace App\Support;

/**
 * Controla quando o sistema deve garantir os esquemas do banco de dados.
 * Ao executar o bootstrap manualmente, habilitamos o modo para que os
 * repositórios rodem as DDLs durante a inicialização.
 */
class SchemaBootstrapper
{
    private static bool $enabled = false;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}
