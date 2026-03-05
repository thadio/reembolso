<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\SecuritySettingsRepository;

final class SecuritySettingsService
{
    public function __construct(
        private SecuritySettingsRepository $security,
        private Config $config,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $defaults = $this->defaults();
        $stored = $this->security->defaultSettings();

        if ($stored === null) {
            return $defaults;
        }

        return [
            'id' => (int) ($stored['id'] ?? 0),
            'setting_key' => 'default',
            'password_min_length' => (int) ($stored['password_min_length'] ?? $defaults['password_min_length']),
            'password_max_length' => (int) ($stored['password_max_length'] ?? $defaults['password_max_length']),
            'password_require_upper' => (int) ($stored['password_require_upper'] ?? $defaults['password_require_upper']),
            'password_require_lower' => (int) ($stored['password_require_lower'] ?? $defaults['password_require_lower']),
            'password_require_number' => (int) ($stored['password_require_number'] ?? $defaults['password_require_number']),
            'password_require_symbol' => (int) ($stored['password_require_symbol'] ?? $defaults['password_require_symbol']),
            'password_expiration_days' => (int) ($stored['password_expiration_days'] ?? $defaults['password_expiration_days']),
            'login_max_attempts' => (int) ($stored['login_max_attempts'] ?? $defaults['login_max_attempts']),
            'login_window_seconds' => (int) ($stored['login_window_seconds'] ?? $defaults['login_window_seconds']),
            'login_lockout_seconds' => (int) ($stored['login_lockout_seconds'] ?? $defaults['login_lockout_seconds']),
            'upload_max_file_size_mb' => (int) ($stored['upload_max_file_size_mb'] ?? $defaults['upload_max_file_size_mb']),
            'created_by' => $stored['created_by'] ?? null,
            'updated_by' => $stored['updated_by'] ?? null,
            'created_at' => (string) ($stored['created_at'] ?? ''),
            'updated_at' => (string) ($stored['updated_at'] ?? ''),
        ];
    }

    /** @return array<string, int> */
    public function passwordPolicy(): array
    {
        $settings = $this->current();

        return [
            'password_min_length' => (int) ($settings['password_min_length'] ?? 8),
            'password_max_length' => (int) ($settings['password_max_length'] ?? 128),
            'password_require_upper' => (int) ($settings['password_require_upper'] ?? 0),
            'password_require_lower' => (int) ($settings['password_require_lower'] ?? 0),
            'password_require_number' => (int) ($settings['password_require_number'] ?? 1),
            'password_require_symbol' => (int) ($settings['password_require_symbol'] ?? 0),
            'password_expiration_days' => (int) ($settings['password_expiration_days'] ?? 0),
        ];
    }

    /** @return array<string, int> */
    public function loginPolicy(): array
    {
        $settings = $this->current();

        return [
            'login_max_attempts' => (int) ($settings['login_max_attempts'] ?? 5),
            'login_window_seconds' => (int) ($settings['login_window_seconds'] ?? 900),
            'login_lockout_seconds' => (int) ($settings['login_lockout_seconds'] ?? 900),
        ];
    }

    public function passwordExpiresAtFromNow(): ?string
    {
        $days = (int) ($this->passwordPolicy()['password_expiration_days'] ?? 0);
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + ($days * 86400));
    }

    public function uploadMaxBytes(): int
    {
        $settings = $this->current();
        $mb = max(2, min(100, (int) ($settings['upload_max_file_size_mb'] ?? 15)));

        return $mb * 1024 * 1024;
    }

    public function passwordRulesSummary(): string
    {
        $policy = $this->passwordPolicy();
        $parts = [];

        $parts[] = 'Minimo de ' . (int) $policy['password_min_length'] . ' caracteres';
        $parts[] = 'maximo de ' . (int) $policy['password_max_length'] . ' caracteres';

        if ((int) $policy['password_require_upper'] === 1) {
            $parts[] = 'ao menos 1 letra maiuscula';
        }

        if ((int) $policy['password_require_lower'] === 1) {
            $parts[] = 'ao menos 1 letra minuscula';
        }

        if ((int) $policy['password_require_number'] === 1) {
            $parts[] = 'ao menos 1 numero';
        }

        if ((int) $policy['password_require_symbol'] === 1) {
            $parts[] = 'ao menos 1 simbolo';
        }

        $expirationDays = (int) ($policy['password_expiration_days'] ?? 0);
        if ($expirationDays > 0) {
            $parts[] = 'expiracao em ' . $expirationDays . ' dia(s)';
        } else {
            $parts[] = 'sem expiracao automatica';
        }

        return implode('; ', $parts) . '.';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(array $input, int $userId, string $ip, string $userAgent): array
    {
        $settings = [
            'password_min_length' => (int) ($input['password_min_length'] ?? 0),
            'password_max_length' => (int) ($input['password_max_length'] ?? 0),
            'password_require_upper' => (int) ($input['password_require_upper'] ?? 0) === 1 ? 1 : 0,
            'password_require_lower' => (int) ($input['password_require_lower'] ?? 0) === 1 ? 1 : 0,
            'password_require_number' => (int) ($input['password_require_number'] ?? 0) === 1 ? 1 : 0,
            'password_require_symbol' => (int) ($input['password_require_symbol'] ?? 0) === 1 ? 1 : 0,
            'password_expiration_days' => (int) ($input['password_expiration_days'] ?? 0),
            'login_max_attempts' => (int) ($input['login_max_attempts'] ?? 0),
            'login_window_seconds' => (int) ($input['login_window_seconds'] ?? 0),
            'login_lockout_seconds' => (int) ($input['login_lockout_seconds'] ?? 0),
            'upload_max_file_size_mb' => (int) ($input['upload_max_file_size_mb'] ?? 0),
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ];

        $errors = [];

        if ($settings['password_min_length'] < 8 || $settings['password_min_length'] > 64) {
            $errors[] = 'Tamanho minimo de senha deve estar entre 8 e 64.';
        }

        if ($settings['password_max_length'] < 12 || $settings['password_max_length'] > 256) {
            $errors[] = 'Tamanho maximo de senha deve estar entre 12 e 256.';
        }

        if ($settings['password_max_length'] < $settings['password_min_length']) {
            $errors[] = 'Tamanho maximo deve ser maior ou igual ao minimo.';
        }

        if ($settings['password_expiration_days'] < 0 || $settings['password_expiration_days'] > 3650) {
            $errors[] = 'Expiracao de senha deve estar entre 0 e 3650 dias.';
        }

        if ($settings['login_max_attempts'] < 3 || $settings['login_max_attempts'] > 20) {
            $errors[] = 'Tentativas maximas devem estar entre 3 e 20.';
        }

        if ($settings['login_window_seconds'] < 60 || $settings['login_window_seconds'] > 86400) {
            $errors[] = 'Janela de tentativas deve estar entre 60 e 86400 segundos.';
        }

        if ($settings['login_lockout_seconds'] < 60 || $settings['login_lockout_seconds'] > 86400) {
            $errors[] = 'Tempo de bloqueio deve estar entre 60 e 86400 segundos.';
        }

        if ($settings['upload_max_file_size_mb'] < 2 || $settings['upload_max_file_size_mb'] > 100) {
            $errors[] = 'Limite de upload deve estar entre 2MB e 100MB.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'data' => $settings,
            ];
        }

        $before = $this->security->defaultSettings();
        $this->security->upsertDefaultSettings($settings);
        $after = $this->security->defaultSettings();

        $this->audit->log(
            entity: 'security_settings',
            entityId: (int) ($after['id'] ?? 0),
            action: 'update',
            beforeData: $before,
            afterData: $after,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'security',
            type: 'security.settings_updated',
            payload: [
                'password_min_length' => $settings['password_min_length'],
                'password_expiration_days' => $settings['password_expiration_days'],
                'login_max_attempts' => $settings['login_max_attempts'],
                'login_window_seconds' => $settings['login_window_seconds'],
                'login_lockout_seconds' => $settings['login_lockout_seconds'],
                'upload_max_file_size_mb' => $settings['upload_max_file_size_mb'],
            ],
            entityId: (int) ($after['id'] ?? 0),
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $settings,
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'id' => 0,
            'setting_key' => 'default',
            'password_min_length' => max(8, (int) $this->config->get('security_policy.password_min_length', 8)),
            'password_max_length' => max(12, (int) $this->config->get('security_policy.password_max_length', 128)),
            'password_require_upper' => (int) $this->config->get('security_policy.password_require_upper', 0) === 1 ? 1 : 0,
            'password_require_lower' => (int) $this->config->get('security_policy.password_require_lower', 0) === 1 ? 1 : 0,
            'password_require_number' => (int) $this->config->get('security_policy.password_require_number', 1) === 1 ? 1 : 0,
            'password_require_symbol' => (int) $this->config->get('security_policy.password_require_symbol', 0) === 1 ? 1 : 0,
            'password_expiration_days' => max(0, (int) $this->config->get('security_policy.password_expiration_days', 0)),
            'login_max_attempts' => max(3, (int) $this->config->get('security_policy.login_max_attempts', 5)),
            'login_window_seconds' => max(60, (int) $this->config->get('security_policy.login_window_seconds', 900)),
            'login_lockout_seconds' => max(60, (int) $this->config->get('security_policy.login_lockout_seconds', 900)),
            'upload_max_file_size_mb' => max(2, (int) $this->config->get('security_policy.upload_max_file_size_mb', 15)),
            'created_by' => null,
            'updated_by' => null,
            'created_at' => '',
            'updated_at' => '',
        ];
    }
}
