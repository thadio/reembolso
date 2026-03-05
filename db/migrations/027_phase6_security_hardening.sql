-- Fase 6.3 - Seguranca reforcada
-- Politica de senha/expiracao configuravel, bloqueio por tentativas e hardening operacional.

SET @users_password_changed_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password_changed_at'
);
SET @sql_users_password_changed := IF(
    @users_password_changed_exists = 0,
    'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER last_login_at',
    'SELECT 1'
);
PREPARE stmt_users_password_changed FROM @sql_users_password_changed;
EXECUTE stmt_users_password_changed;
DEALLOCATE PREPARE stmt_users_password_changed;

SET @users_password_expires_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password_expires_at'
);
SET @sql_users_password_expires := IF(
    @users_password_expires_exists = 0,
    'ALTER TABLE users ADD COLUMN password_expires_at DATETIME NULL AFTER password_changed_at',
    'SELECT 1'
);
PREPARE stmt_users_password_expires FROM @sql_users_password_expires;
EXECUTE stmt_users_password_expires;
DEALLOCATE PREPARE stmt_users_password_expires;

SET @users_password_expires_idx_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_password_expires_at'
);
SET @sql_users_password_expires_idx := IF(
    @users_password_expires_idx_exists = 0,
    'ALTER TABLE users ADD KEY idx_users_password_expires_at (password_expires_at)',
    'SELECT 1'
);
PREPARE stmt_users_password_expires_idx FROM @sql_users_password_expires_idx;
EXECUTE stmt_users_password_expires_idx;
DEALLOCATE PREPARE stmt_users_password_expires_idx;

SET @login_attempts_lockout_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'login_attempts'
      AND COLUMN_NAME = 'lockout_until'
);
SET @sql_login_attempts_lockout := IF(
    @login_attempts_lockout_exists = 0,
    'ALTER TABLE login_attempts ADD COLUMN lockout_until DATETIME NULL AFTER last_attempt_at',
    'SELECT 1'
);
PREPARE stmt_login_attempts_lockout FROM @sql_login_attempts_lockout;
EXECUTE stmt_login_attempts_lockout;
DEALLOCATE PREPARE stmt_login_attempts_lockout;

SET @login_attempts_lockout_idx_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'login_attempts'
      AND INDEX_NAME = 'idx_login_attempts_lockout_until'
);
SET @sql_login_attempts_lockout_idx := IF(
    @login_attempts_lockout_idx_exists = 0,
    'ALTER TABLE login_attempts ADD KEY idx_login_attempts_lockout_until (lockout_until)',
    'SELECT 1'
);
PREPARE stmt_login_attempts_lockout_idx FROM @sql_login_attempts_lockout_idx;
EXECUTE stmt_login_attempts_lockout_idx;
DEALLOCATE PREPARE stmt_login_attempts_lockout_idx;

CREATE TABLE IF NOT EXISTS security_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) NOT NULL,
  password_min_length INT UNSIGNED NOT NULL DEFAULT 8,
  password_max_length INT UNSIGNED NOT NULL DEFAULT 128,
  password_require_upper TINYINT(1) NOT NULL DEFAULT 0,
  password_require_lower TINYINT(1) NOT NULL DEFAULT 0,
  password_require_number TINYINT(1) NOT NULL DEFAULT 1,
  password_require_symbol TINYINT(1) NOT NULL DEFAULT 0,
  password_expiration_days INT UNSIGNED NOT NULL DEFAULT 0,
  login_max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  login_window_seconds INT UNSIGNED NOT NULL DEFAULT 900,
  login_lockout_seconds INT UNSIGNED NOT NULL DEFAULT 900,
  upload_max_file_size_mb INT UNSIGNED NOT NULL DEFAULT 15,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_security_settings_key (setting_key),
  KEY idx_security_settings_updated_by (updated_by),
  CONSTRAINT fk_security_settings_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_security_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO security_settings (
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
  'default',
  8,
  128,
  0,
  0,
  1,
  0,
  0,
  5,
  900,
  900,
  15,
  NULL,
  NULL,
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('security.view', 'Visualizar politicas de seguranca e lockout', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('security.manage', 'Gerenciar politicas de senha, lockout e hardening de upload', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'security.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'security.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
