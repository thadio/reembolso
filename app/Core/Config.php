<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    public static function load(): self
    {
        return new self([
            'app' => [
                'name' => Env::get('NAME', 'Reembolso'),
                'base_url' => Env::get('BASE_URL', ''),
                'timezone' => Env::get('TIMEZONE', 'America/Sao_Paulo'),
                'env' => Env::get('APP_ENV', 'production'),
                'debug' => Env::get('APP_DEBUG', '0') === '1',
            ],
            'db' => [
                'host' => Env::get('DB_HOST', '127.0.0.1'),
                'port' => Env::get('DB_PORT', '3306'),
                'name' => Env::get('DB_NAME', ''),
                'user' => Env::get('DB_USER', ''),
                'pass' => Env::get('DB_PASS', ''),
                'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
            ],
            'security' => [
                'session_name' => Env::get('SESSION_NAME', 'reembolso_session'),
                'session_ttl' => (int) Env::get('SESSION_TTL_SECONDS', '7200'),
                'csrf_ttl' => (int) Env::get('CSRF_TTL_SECONDS', '7200'),
            ],
            'rate_limit' => [
                'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
                'login_decay_seconds' => (int) Env::get('LOGIN_DECAY_SECONDS', '900'),
            ],
            'paths' => [
                'storage_logs' => BASE_PATH . '/storage/logs/app.log',
                'storage_uploads' => BASE_PATH . '/storage/uploads',
            ],
            'seed' => [
                'admin_name' => Env::get('SEED_ADMIN_NAME', 'Administrador Sistema'),
                'admin_email' => Env::get('SEED_ADMIN_EMAIL', 'admin@reembolso.local'),
                'admin_password' => Env::get('SEED_ADMIN_PASSWORD', 'ChangeMe123!'),
            ],
        ]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->values;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
