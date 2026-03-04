<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static string $logFile = '';

    public static function init(string $file): void
    {
        self::$logFile = $file;
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        if (self::$logFile === '') {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
