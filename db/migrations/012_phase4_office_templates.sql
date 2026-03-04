-- Fase 4.1 - Templates de oficio com versionamento e geracao de documento

CREATE TABLE IF NOT EXISTS office_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  template_type VARCHAR(30) NOT NULL DEFAULT 'orgao',
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_office_templates_key (template_key),
  KEY idx_office_templates_type (template_type),
  KEY idx_office_templates_active (is_active),
  KEY idx_office_templates_created_by (created_by),
  KEY idx_office_templates_deleted_at (deleted_at),
  CONSTRAINT fk_office_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_template_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  body_html LONGTEXT NOT NULL,
  variables_json LONGTEXT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_office_template_version (template_id, version_number),
  KEY idx_office_template_versions_template (template_id),
  KEY idx_office_template_versions_active (is_active),
  KEY idx_office_template_versions_created_by (created_by),
  KEY idx_office_template_versions_deleted_at (deleted_at),
  CONSTRAINT fk_office_template_versions_template FOREIGN KEY (template_id) REFERENCES office_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_office_template_versions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  template_version_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  organ_id BIGINT UNSIGNED NULL,
  rendered_subject VARCHAR(190) NOT NULL,
  rendered_html LONGTEXT NOT NULL,
  context_json LONGTEXT NULL,
  generated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_office_documents_template (template_id),
  KEY idx_office_documents_template_version (template_version_id),
  KEY idx_office_documents_person (person_id),
  KEY idx_office_documents_organ (organ_id),
  KEY idx_office_documents_generated_by (generated_by),
  KEY idx_office_documents_deleted_at (deleted_at),
  CONSTRAINT fk_office_documents_template FOREIGN KEY (template_id) REFERENCES office_templates(id),
  CONSTRAINT fk_office_documents_template_version FOREIGN KEY (template_version_id) REFERENCES office_template_versions(id),
  CONSTRAINT fk_office_documents_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL,
  CONSTRAINT fk_office_documents_organ FOREIGN KEY (organ_id) REFERENCES organs(id) ON DELETE SET NULL,
  CONSTRAINT fk_office_documents_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('office_template.view', 'Visualizar templates e oficios gerados', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('office_template.manage', 'Gerenciar templates e gerar oficios', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'office_template.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'office_template.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
