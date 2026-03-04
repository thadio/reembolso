<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(Config $config): void
    {
        if (PHP_SAPI === 'cli' || self::$started) {
            return;
        }

        $sessionName = (string) $config->get('security.session_name', 'reembolso_session');
        session_name($sessionName);

        session_set_cookie_params([
            'lifetime' => (int) $config->get('security.session_ttl', 7200),
            'path' => '/',
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        self::$started = true;
        self::ageFlashData();
    }

    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    public static function flash(string $key, mixed $value): void
    {
        if (!isset($_SESSION['_flash_next']) || !is_array($_SESSION['_flash_next'])) {
            $_SESSION['_flash_next'] = [];
        }

        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            return $default;
        }

        return $_SESSION['_flash'][$key] ?? $default;
    }

    /** @param array<string, mixed> $input */
    public static function flashInput(array $input): void
    {
        self::flash('_old', $input);
    }

    private static function ageFlashData(): void
    {
        $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    private static function isHttps(): bool
    {
        if (!isset($_SERVER['HTTPS'])) {
            return false;
        }

        return $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
    }
}
