-- Fase 1.3 - Movimentação, pipeline de status e timeline automática

CREATE TABLE IF NOT EXISTS assignment_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT UNSIGNED NOT NULL,
  next_action_label VARCHAR(180) NULL,
  event_type VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment_statuses_code (code),
  UNIQUE KEY uq_assignment_statuses_sort (sort_order),
  KEY idx_assignment_statuses_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  modality_id INT UNSIGNED NULL,
  mte_unit VARCHAR(190) NULL,
  target_start_date DATE NULL,
  effective_start_date DATE NULL,
  current_status_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_assignments_person (person_id),
  KEY idx_assignments_modality (modality_id),
  KEY idx_assignments_status (current_status_id),
  KEY idx_assignments_deleted_at (deleted_at),
  CONSTRAINT fk_assignments_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_assignments_modality FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_status FOREIGN KEY (current_status_id) REFERENCES assignment_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timeline_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(120) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  event_date DATETIME NOT NULL,
  metadata LONGTEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_timeline_person (person_id),
  KEY idx_timeline_assignment (assignment_id),
  KEY idx_timeline_type (event_type),
  KEY idx_timeline_event_date (event_date),
  CONSTRAINT fk_timeline_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_timeline_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_timeline_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
