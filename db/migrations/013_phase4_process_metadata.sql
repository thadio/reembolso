-- Fase 4.2 - Metadados formais de processo (oficio, DOU e entrada oficial no MTE)

CREATE TABLE IF NOT EXISTS process_metadata (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  office_number VARCHAR(120) NULL,
  office_sent_at DATE NULL,
  office_channel VARCHAR(80) NULL,
  office_protocol VARCHAR(120) NULL,
  dou_edition VARCHAR(120) NULL,
  dou_published_at DATE NULL,
  dou_link VARCHAR(500) NULL,
  dou_attachment_original_name VARCHAR(255) NULL,
  dou_attachment_stored_name VARCHAR(255) NULL,
  dou_attachment_mime_type VARCHAR(120) NULL,
  dou_attachment_file_size BIGINT UNSIGNED NULL,
  dou_attachment_storage_path VARCHAR(255) NULL,
  mte_entry_date DATE NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_process_metadata_person (person_id),
  KEY idx_process_metadata_office_sent_at (office_sent_at),
  KEY idx_process_metadata_dou_published_at (dou_published_at),
  KEY idx_process_metadata_mte_entry_date (mte_entry_date),
  KEY idx_process_metadata_created_by (created_by),
  KEY idx_process_metadata_deleted_at (deleted_at),
  CONSTRAINT fk_process_metadata_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_process_metadata_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('process_meta.view', 'Visualizar metadados formais de processo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('process_meta.manage', 'Gerenciar metadados formais de processo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'process_meta.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'process_meta.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
