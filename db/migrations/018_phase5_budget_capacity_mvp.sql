-- Fase 5.4 (MVP) - Orcamento e capacidade de contratacao

CREATE TABLE IF NOT EXISTS budget_cycles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cycle_year SMALLINT UNSIGNED NOT NULL,
  annual_factor DECIMAL(5,2) NOT NULL DEFAULT 13.30,
  total_budget DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_budget_cycles_year (cycle_year),
  KEY idx_budget_cycles_status (status),
  KEY idx_budget_cycles_created_by (created_by),
  KEY idx_budget_cycles_deleted_at (deleted_at),
  CONSTRAINT fk_budget_cycles_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_cost_parameters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organ_id BIGINT UNSIGNED NOT NULL,
  avg_monthly_cost DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_org_cost_parameters_organ (organ_id),
  KEY idx_org_cost_parameters_updated_by (updated_by),
  KEY idx_org_cost_parameters_deleted_at (deleted_at),
  CONSTRAINT fk_org_cost_parameters_organ FOREIGN KEY (organ_id) REFERENCES organs(id),
  CONSTRAINT fk_org_cost_parameters_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hiring_scenarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  budget_cycle_id BIGINT UNSIGNED NOT NULL,
  organ_id BIGINT UNSIGNED NULL,
  scenario_name VARCHAR(190) NOT NULL,
  entry_date DATE NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  avg_monthly_cost DECIMAL(12,2) NOT NULL,
  annual_factor DECIMAL(5,2) NOT NULL,
  cost_current_year DECIMAL(14,2) NOT NULL,
  cost_next_year DECIMAL(14,2) NOT NULL,
  available_before DECIMAL(14,2) NOT NULL,
  remaining_after_current_year DECIMAL(14,2) NOT NULL,
  max_capacity_before INT UNSIGNED NOT NULL DEFAULT 0,
  risk_level VARCHAR(20) NOT NULL DEFAULT 'baixo',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_hiring_scenarios_budget_cycle (budget_cycle_id),
  KEY idx_hiring_scenarios_organ (organ_id),
  KEY idx_hiring_scenarios_entry_date (entry_date),
  KEY idx_hiring_scenarios_risk_level (risk_level),
  KEY idx_hiring_scenarios_created_by (created_by),
  KEY idx_hiring_scenarios_deleted_at (deleted_at),
  CONSTRAINT fk_hiring_scenarios_budget_cycle FOREIGN KEY (budget_cycle_id) REFERENCES budget_cycles(id),
  CONSTRAINT fk_hiring_scenarios_organ FOREIGN KEY (organ_id) REFERENCES organs(id) ON DELETE SET NULL,
  CONSTRAINT fk_hiring_scenarios_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hiring_scenario_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hiring_scenario_id BIGINT UNSIGNED NOT NULL,
  item_label VARCHAR(190) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  avg_monthly_cost DECIMAL(12,2) NOT NULL,
  cost_current_year DECIMAL(14,2) NOT NULL,
  cost_next_year DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_hiring_scenario_items_scenario (hiring_scenario_id),
  KEY idx_hiring_scenario_items_deleted_at (deleted_at),
  CONSTRAINT fk_hiring_scenario_items_scenario FOREIGN KEY (hiring_scenario_id) REFERENCES hiring_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('budget.view', 'Visualizar dashboard orcamentario e capacidade', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('budget.manage', 'Gerenciar parametros orcamentarios e ciclo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('budget.simulate', 'Executar simulacoes de contratacao', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('budget.approve', 'Aprovar reservas e cenarios orcamentarios', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'budget.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'budget.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'budget.simulate'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'budget.approve'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
