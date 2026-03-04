-- Fase 4.3 - SLA e alertas de pendencia

CREATE TABLE IF NOT EXISTS sla_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  status_code VARCHAR(60) NOT NULL,
  warning_days INT UNSIGNED NOT NULL DEFAULT 5,
  overdue_days INT UNSIGNED NOT NULL DEFAULT 10,
  notify_email TINYINT(1) NOT NULL DEFAULT 0,
  notify_recipients VARCHAR(700) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_sla_rules_status (status_code),
  KEY idx_sla_rules_active (is_active),
  KEY idx_sla_rules_created_by (created_by),
  KEY idx_sla_rules_deleted_at (deleted_at),
  CONSTRAINT fk_sla_rules_status_code FOREIGN KEY (status_code) REFERENCES assignment_statuses(code),
  CONSTRAINT fk_sla_rules_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sla_notification_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_id BIGINT UNSIGNED NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  status_code VARCHAR(60) NOT NULL,
  severity VARCHAR(20) NOT NULL,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_preview TEXT NULL,
  sent_success TINYINT(1) NOT NULL DEFAULT 0,
  response_message TEXT NULL,
  sent_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sla_notification_logs_rule (rule_id),
  KEY idx_sla_notification_logs_assignment (assignment_id),
  KEY idx_sla_notification_logs_person (person_id),
  KEY idx_sla_notification_logs_status (status_code),
  KEY idx_sla_notification_logs_severity (severity),
  KEY idx_sla_notification_logs_created_at (created_at),
  KEY idx_sla_notification_logs_sent_by (sent_by),
  CONSTRAINT fk_sla_notification_logs_rule FOREIGN KEY (rule_id) REFERENCES sla_rules(id) ON DELETE SET NULL,
  CONSTRAINT fk_sla_notification_logs_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sla_notification_logs_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
  CONSTRAINT fk_sla_notification_logs_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('sla.view', 'Visualizar painel de SLA e pendencias', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('sla.manage', 'Gerenciar regras de SLA e notificacoes', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'sla.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'sla.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
