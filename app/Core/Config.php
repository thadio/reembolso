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
            'security_policy' => [
                'password_min_length' => (int) Env::get('PASSWORD_MIN_LENGTH', '8'),
                'password_max_length' => (int) Env::get('PASSWORD_MAX_LENGTH', '128'),
                'password_require_upper' => Env::get('PASSWORD_REQUIRE_UPPER', '0') === '1' ? 1 : 0,
                'password_require_lower' => Env::get('PASSWORD_REQUIRE_LOWER', '0') === '1' ? 1 : 0,
                'password_require_number' => Env::get('PASSWORD_REQUIRE_NUMBER', '1') === '1' ? 1 : 0,
                'password_require_symbol' => Env::get('PASSWORD_REQUIRE_SYMBOL', '0') === '1' ? 1 : 0,
                'password_expiration_days' => (int) Env::get('PASSWORD_EXPIRATION_DAYS', '0'),
                'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
                'login_window_seconds' => (int) Env::get('LOGIN_WINDOW_SECONDS', Env::get('LOGIN_DECAY_SECONDS', '900') ?? '900'),
                'login_lockout_seconds' => (int) Env::get('LOGIN_LOCKOUT_SECONDS', Env::get('LOGIN_DECAY_SECONDS', '900') ?? '900'),
                'upload_max_file_size_mb' => (int) Env::get('UPLOAD_MAX_FILE_SIZE_MB', '15'),
            ],
            'rate_limit' => [
                'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
                'login_decay_seconds' => (int) Env::get('LOGIN_WINDOW_SECONDS', Env::get('LOGIN_DECAY_SECONDS', '900') ?? '900'),
            ],
            'paths' => [
                'storage_logs' => BASE_PATH . '/storage/logs/app.log',
                'storage_uploads' => BASE_PATH . '/storage/uploads',
            ],
            'ops' => [
                'kpi_snapshot_dir' => Env::get('OPS_KPI_SNAPSHOT_DIR', 'storage/ops/kpi_snapshots'),
                'kpi_snapshot_retention_days' => (int) Env::get('OPS_KPI_SNAPSHOT_RETENTION_DAYS', '30'),
                'kpi_snapshot_max_age_minutes' => (int) Env::get('OPS_KPI_SNAPSHOT_MAX_AGE_MINUTES', '240'),
                'health_panel_snapshot_dir' => Env::get('OPS_HEALTH_PANEL_SNAPSHOT_DIR', 'storage/ops/health-panel'),
                'log_severity_snapshot_dir' => Env::get('OPS_LOG_SEVERITY_SNAPSHOT_DIR', 'storage/ops/log-severity'),
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
