<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, $_POST) && !array_key_exists($key, $_GET)) {
            return $default;
        }

        $value = $_POST[$key] ?? $_GET[$key];

        if ($value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $_POST);
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
    }
}
