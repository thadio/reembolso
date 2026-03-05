-- Fase 9.13 - Controle de SLA e casos em atraso

CREATE TABLE IF NOT EXISTS sla_case_controls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  control_status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  owner_user_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  last_action_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_sla_case_controls_assignment (assignment_id),
  KEY idx_sla_case_controls_person (person_id),
  KEY idx_sla_case_controls_status (control_status),
  KEY idx_sla_case_controls_owner (owner_user_id),
  KEY idx_sla_case_controls_deleted_at (deleted_at),
  KEY idx_sla_case_controls_updated_at (updated_at),
  CONSTRAINT fk_sla_case_controls_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sla_case_controls_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
  CONSTRAINT fk_sla_case_controls_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sla_case_controls_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sla_case_controls_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
