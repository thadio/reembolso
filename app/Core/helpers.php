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
        $baseUrl = rtrim((string) app()->config()->get('app.base_url', ''), '/');
        $path = '/' . ltrim($path, '/');

        if ($baseUrl === '') {
            return $path;
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
