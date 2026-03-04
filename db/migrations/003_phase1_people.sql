-- Fase 1.2 - Pessoas com vínculo obrigatório ao órgão

CREATE TABLE IF NOT EXISTS people (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organ_id BIGINT UNSIGNED NOT NULL,
  desired_modality_id INT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  cpf VARCHAR(14) NOT NULL,
  birth_date DATE NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'interessado',
  sei_process_number VARCHAR(60) NULL,
  mte_destination VARCHAR(190) NULL,
  tags VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_people_organ_id (organ_id),
  KEY idx_people_modality (desired_modality_id),
  KEY idx_people_name (name),
  KEY idx_people_cpf (cpf),
  KEY idx_people_status (status),
  KEY idx_people_deleted_at (deleted_at),
  KEY idx_people_created_at (created_at),
  CONSTRAINT fk_people_organ FOREIGN KEY (organ_id) REFERENCES organs(id),
  CONSTRAINT fk_people_modality FOREIGN KEY (desired_modality_id) REFERENCES modalities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
