-- Fase 2.1 - Custos previstos e versionamento por pessoa

CREATE TABLE IF NOT EXISTS cost_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  label VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_cost_plans_person_version (person_id, version_number),
  KEY idx_cost_plans_person_active (person_id, is_active),
  KEY idx_cost_plans_created_by (created_by),
  KEY idx_cost_plans_deleted_at (deleted_at),
  CONSTRAINT fk_cost_plans_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_cost_plans_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cost_plan_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cost_plan_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  item_name VARCHAR(190) NOT NULL,
  cost_type VARCHAR(30) NOT NULL DEFAULT 'mensal',
  amount DECIMAL(12,2) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cost_items_plan (cost_plan_id),
  KEY idx_cost_items_person (person_id),
  KEY idx_cost_items_type (cost_type),
  KEY idx_cost_items_created_by (created_by),
  KEY idx_cost_items_deleted_at (deleted_at),
  CONSTRAINT fk_cost_items_plan FOREIGN KEY (cost_plan_id) REFERENCES cost_plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_cost_items_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_cost_items_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
