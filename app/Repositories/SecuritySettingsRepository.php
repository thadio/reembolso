<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class SecuritySettingsRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function defaultSettings(): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT
                    id,
                    setting_key,
                    password_min_length,
                    password_max_length,
                    password_require_upper,
                    password_require_lower,
                    password_require_number,
                    password_require_symbol,
                    password_expiration_days,
                    login_max_attempts,
                    login_window_seconds,
                    login_lockout_seconds,
                    upload_max_file_size_mb,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                 FROM security_settings
                 WHERE setting_key = :setting_key
                 LIMIT 1'
            );

            $stmt->execute(['setting_key' => 'default']);
            $row = $stmt->fetch();
        } catch (Throwable $throwable) {
            return null;
        }

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function upsertDefaultSettings(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO security_settings (
                setting_key,
                password_min_length,
                password_max_length,
                password_require_upper,
                password_require_lower,
                password_require_number,
                password_require_symbol,
                password_expiration_days,
                login_max_attempts,
                login_window_seconds,
                login_lockout_seconds,
                upload_max_file_size_mb,
                created_by,
                updated_by,
                created_at,
                updated_at
             ) VALUES (
                :setting_key,
                :password_min_length,
                :password_max_length,
                :password_require_upper,
                :password_require_lower,
                :password_require_number,
                :password_require_symbol,
                :password_expiration_days,
                :login_max_attempts,
                :login_window_seconds,
                :login_lockout_seconds,
                :upload_max_file_size_mb,
                :created_by,
                :updated_by,
                NOW(),
                NOW()
             )
             ON DUPLICATE KEY UPDATE
                password_min_length = VALUES(password_min_length),
                password_max_length = VALUES(password_max_length),
                password_require_upper = VALUES(password_require_upper),
                password_require_lower = VALUES(password_require_lower),
                password_require_number = VALUES(password_require_number),
                password_require_symbol = VALUES(password_require_symbol),
                password_expiration_days = VALUES(password_expiration_days),
                login_max_attempts = VALUES(login_max_attempts),
                login_window_seconds = VALUES(login_window_seconds),
                login_lockout_seconds = VALUES(login_lockout_seconds),
                upload_max_file_size_mb = VALUES(upload_max_file_size_mb),
                updated_by = VALUES(updated_by),
                updated_at = NOW()'
        );

        $stmt->execute([
            'setting_key' => 'default',
            'password_min_length' => $data['password_min_length'],
            'password_max_length' => $data['password_max_length'],
            'password_require_upper' => $data['password_require_upper'],
            'password_require_lower' => $data['password_require_lower'],
            'password_require_number' => $data['password_require_number'],
            'password_require_symbol' => $data['password_require_symbol'],
            'password_expiration_days' => $data['password_expiration_days'],
            'login_max_attempts' => $data['login_max_attempts'],
            'login_window_seconds' => $data['login_window_seconds'],
            'login_lockout_seconds' => $data['login_lockout_seconds'],
            'upload_max_file_size_mb' => $data['upload_max_file_size_mb'],
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }
}
