-- Fase 1.5 - Dossiê documental por pessoa

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  document_type_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  reference_sei VARCHAR(120) NULL,
  document_date DATE NULL,
  tags VARCHAR(255) NULL,
  notes TEXT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_documents_person (person_id),
  KEY idx_documents_type (document_type_id),
  KEY idx_documents_uploaded_by (uploaded_by),
  KEY idx_documents_deleted_at (deleted_at),
  KEY idx_documents_created_at (created_at),
  CONSTRAINT fk_documents_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_documents_type FOREIGN KEY (document_type_id) REFERENCES document_types(id),
  CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
