-- Fase 5.1 - Projecoes e cenarios multiparametricos

CREATE TABLE IF NOT EXISTS budget_scenario_parameters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  budget_cycle_id BIGINT UNSIGNED NOT NULL,
  organ_id BIGINT UNSIGNED NOT NULL,
  modality VARCHAR(80) NOT NULL DEFAULT 'geral',
  base_variation_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  updated_variation_percent DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  worst_variation_percent DECIMAL(6,2) NOT NULL DEFAULT 25.00,
  notes TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_budget_scenario_parameters_scope (budget_cycle_id, organ_id, modality),
  KEY idx_budget_scenario_parameters_organ (organ_id),
  KEY idx_budget_scenario_parameters_updated_by (updated_by),
  KEY idx_budget_scenario_parameters_deleted_at (deleted_at),
  CONSTRAINT fk_budget_scenario_parameters_cycle FOREIGN KEY (budget_cycle_id) REFERENCES budget_cycles(id),
  CONSTRAINT fk_budget_scenario_parameters_organ FOREIGN KEY (organ_id) REFERENCES organs(id),
  CONSTRAINT fk_budget_scenario_parameters_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE hiring_scenarios
  ADD COLUMN modality VARCHAR(80) NOT NULL DEFAULT 'geral' AFTER organ_id,
  ADD KEY idx_hiring_scenarios_modality (modality);

ALTER TABLE hiring_scenario_items
  ADD COLUMN scenario_code VARCHAR(30) NOT NULL DEFAULT 'base' AFTER item_label,
  ADD COLUMN variation_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER scenario_code,
  ADD KEY idx_hiring_scenario_items_code (scenario_code);
