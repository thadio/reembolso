-- Fase 6.2 - LGPD avancado (acesso sensivel, relatorio e retencao/anonimizacao)

CREATE TABLE IF NOT EXISTS sensitive_access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity VARCHAR(120) NOT NULL,
  entity_id BIGINT NULL,
  action VARCHAR(120) NOT NULL,
  sensitivity VARCHAR(60) NOT NULL,
  subject_person_id BIGINT UNSIGNED NULL,
  subject_label VARCHAR(190) NULL,
  context_path VARCHAR(255) NULL,
  metadata LONGTEXT NULL,
  user_id BIGINT UNSIGNED NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sensitive_access_created_at (created_at),
  KEY idx_sensitive_access_user_created (user_id, created_at),
  KEY idx_sensitive_access_action_created (action, created_at),
  KEY idx_sensitive_access_sensitivity_created (sensitivity, created_at),
  KEY idx_sensitive_access_subject_person (subject_person_id),
  CONSTRAINT fk_sensitive_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sensitive_access_subject_person FOREIGN KEY (subject_person_id) REFERENCES people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_retention_policies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  policy_key VARCHAR(80) NOT NULL,
  policy_label VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  retention_days INT UNSIGNED NOT NULL DEFAULT 365,
  anonymize_after_days INT UNSIGNED NULL,
  supports_anonymization TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_lgpd_retention_policy_key (policy_key),
  KEY idx_lgpd_retention_policies_is_active (is_active),
  KEY idx_lgpd_retention_policies_created_by (created_by),
  KEY idx_lgpd_retention_policies_updated_by (updated_by),
  KEY idx_lgpd_retention_policies_deleted_at (deleted_at),
  CONSTRAINT fk_lgpd_retention_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_lgpd_retention_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_retention_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_mode VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL,
  summary LONGTEXT NULL,
  initiated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lgpd_retention_runs_mode_created (run_mode, created_at),
  KEY idx_lgpd_retention_runs_status_created (status, created_at),
  KEY idx_lgpd_retention_runs_initiated_by (initiated_by),
  CONSTRAINT fk_lgpd_retention_runs_user FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('lgpd.view', 'Visualizar acessos sensiveis e politicas LGPD', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('lgpd.manage', 'Gerenciar politicas LGPD e executar retencao/anonimizacao', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'lgpd.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'lgpd.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO lgpd_retention_policies (
  policy_key,
  policy_label,
  description,
  retention_days,
  anonymize_after_days,
  supports_anonymization,
  is_active,
  created_by,
  updated_by,
  created_at,
  updated_at,
  deleted_at
) VALUES
  (
    'sensitive_access_logs',
    'Logs de acesso sensivel',
    'Retencao dos registros de visualizacao/download de dados sensiveis.',
    365,
    NULL,
    0,
    1,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
  ),
  (
    'audit_log',
    'Trilha de auditoria geral',
    'Retencao de eventos gerais da tabela audit_log.',
    730,
    NULL,
    0,
    1,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
  ),
  (
    'people_soft_deleted',
    'Anonimizacao de pessoas removidas',
    'Anonimiza dados pessoais de pessoas em soft delete apos o prazo configurado.',
    0,
    180,
    1,
    1,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
  ),
  (
    'users_soft_deleted',
    'Anonimizacao de usuarios removidos',
    'Anonimiza dados pessoais de usuarios em soft delete apos o prazo configurado.',
    0,
    365,
    1,
    1,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
  )
ON DUPLICATE KEY UPDATE
  policy_label = VALUES(policy_label),
  description = VALUES(description),
  supports_anonymization = VALUES(supports_anonymization),
  is_active = VALUES(is_active),
  updated_at = NOW(),
  deleted_at = NULL;
