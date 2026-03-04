-- Fase 3.1 - CDO completo (cadastro e vinculo com pessoas)

CREATE TABLE IF NOT EXISTS cdos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(80) NOT NULL,
  ug_code VARCHAR(30) NULL,
  action_code VARCHAR(30) NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_cdos_number (number),
  KEY idx_cdos_status (status),
  KEY idx_cdos_period_start (period_start),
  KEY idx_cdos_period_end (period_end),
  KEY idx_cdos_created_by (created_by),
  KEY idx_cdos_deleted_at (deleted_at),
  CONSTRAINT fk_cdos_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cdo_people (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cdo_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  allocated_amount DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cdo_people_cdo (cdo_id),
  KEY idx_cdo_people_person (person_id),
  KEY idx_cdo_people_created_by (created_by),
  KEY idx_cdo_people_deleted_at (deleted_at),
  CONSTRAINT fk_cdo_people_cdo FOREIGN KEY (cdo_id) REFERENCES cdos(id),
  CONSTRAINT fk_cdo_people_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_cdo_people_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cdo.view', 'Visualizar CDO', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cdo.manage', 'Gerenciar CDO', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cdo.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cdo.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
