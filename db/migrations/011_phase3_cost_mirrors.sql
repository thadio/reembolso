-- Fase 3.3 - Espelho de custo detalhado por pessoa e competencia

CREATE TABLE IF NOT EXISTS cost_mirrors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  organ_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NULL,
  reference_month DATE NOT NULL,
  title VARCHAR(190) NOT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'manual',
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cost_mirrors_person (person_id),
  KEY idx_cost_mirrors_organ (organ_id),
  KEY idx_cost_mirrors_invoice (invoice_id),
  KEY idx_cost_mirrors_reference_month (reference_month),
  KEY idx_cost_mirrors_status (status),
  KEY idx_cost_mirrors_created_by (created_by),
  KEY idx_cost_mirrors_deleted_at (deleted_at),
  CONSTRAINT fk_cost_mirrors_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_cost_mirrors_organ FOREIGN KEY (organ_id) REFERENCES organs(id),
  CONSTRAINT fk_cost_mirrors_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_cost_mirrors_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cost_mirror_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cost_mirror_id BIGINT UNSIGNED NOT NULL,
  item_name VARCHAR(190) NOT NULL,
  item_code VARCHAR(80) NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cost_mirror_items_mirror (cost_mirror_id),
  KEY idx_cost_mirror_items_name (item_name),
  KEY idx_cost_mirror_items_created_by (created_by),
  KEY idx_cost_mirror_items_deleted_at (deleted_at),
  CONSTRAINT fk_cost_mirror_items_mirror FOREIGN KEY (cost_mirror_id) REFERENCES cost_mirrors(id) ON DELETE CASCADE,
  CONSTRAINT fk_cost_mirror_items_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cost_mirror.view', 'Visualizar espelhos de custo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cost_mirror.manage', 'Gerenciar espelhos de custo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cost_mirror.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cost_mirror.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
