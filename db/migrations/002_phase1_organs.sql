-- Fase 1.1 - CRUD de Órgãos

CREATE TABLE IF NOT EXISTS organs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  acronym VARCHAR(30) NULL,
  cnpj VARCHAR(18) NULL,
  contact_name VARCHAR(120) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(30) NULL,
  address_line VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NULL,
  zip_code VARCHAR(10) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_organs_cnpj (cnpj),
  KEY idx_organs_name (name),
  KEY idx_organs_acronym (acronym),
  KEY idx_organs_deleted_at (deleted_at),
  KEY idx_organs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
