<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;

if (!function_exists('app')) {
    function app(): App
    {
        /** @var App $app */
        $app = $GLOBALS['app'];

        return $app;
    }
}

if (!function_exists('e')) {
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, string $message): void
    {
        Session::flash($key, $message);
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $oldInput = Session::getFlash('_old', []);

        return (string) ($oldInput[$key] ?? $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $configuredBase = rtrim((string) app()->config()->get('app.base_url', ''), '/');
        $path = '/' . ltrim($path, '/');

        // Detect local development context and prefer request host in that case.
        $serverHost = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ($_SERVER['SERVER_ADDR'] ?? '')));
        $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $hostLooksLocal = $serverHost !== '' && (str_contains($serverHost, 'localhost') || str_starts_with($serverHost, '127.') || str_ends_with($serverHost, '.local'));
        $addrLooksLocal = $remoteAddr !== '' && ($remoteAddr === '::1' || str_starts_with($remoteAddr, '127.'));

        if ($hostLooksLocal || $addrLooksLocal || $configuredBase === '') {
            // build base from current request
            $isHttps = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443');
            $scheme = $isHttps ? 'https' : 'http';
            $rawHost = $serverHost !== '' ? $serverHost : (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $parsed = parse_url($scheme . '://' . $rawHost);
            $host = (string) ($parsed['host'] ?? $rawHost);
            $port = (string) ($parsed['port'] ?? ($_SERVER['SERVER_PORT'] ?? ''));

            if (str_contains($host, ':') && !str_starts_with($host, '[')) {
                $host = '[' . $host . ']';
            }

            // Omit standard ports
            if ($port !== '' && (($scheme === 'http' && $port !== '80') || ($scheme === 'https' && $port !== '443'))) {
                $host = $host . ':' . $port;
            }

            $baseUrl = $scheme . '://' . $host;
        } else {
            $baseUrl = $configuredBase;
        }

        return $baseUrl . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('mask_cpf')) {
    function mask_cpf(?string $cpf): string
    {
        if ($cpf === null || $cpf === '') {
            return '';
        }

        return '***.***.***-**';
    }
}

if (!function_exists('record_event')) {
    function record_event(string $entity, string $type, array $payload = [], ?int $entityId = null): void
    {
        app()->events()->recordEvent($entity, $type, $payload, $entityId);
    }
}
