-- Ciclo 9.11 - Controle de versao de documentos

CREATE TABLE IF NOT EXISTS document_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  reference_sei VARCHAR(120) NULL,
  document_date DATE NULL,
  tags VARCHAR(255) NULL,
  notes TEXT NULL,
  sensitivity_level VARCHAR(20) NOT NULL DEFAULT 'public',
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_document_versions_doc_version (document_id, version_number),
  KEY idx_document_versions_document (document_id),
  KEY idx_document_versions_person (person_id),
  KEY idx_document_versions_uploaded_by (uploaded_by),
  KEY idx_document_versions_deleted_at (deleted_at),
  KEY idx_document_versions_created_at (created_at),
  CONSTRAINT fk_document_versions_document FOREIGN KEY (document_id) REFERENCES documents(id),
  CONSTRAINT fk_document_versions_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_document_versions_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_versions (
  document_id,
  person_id,
  version_number,
  title,
  reference_sei,
  document_date,
  tags,
  notes,
  sensitivity_level,
  original_name,
  stored_name,
  mime_type,
  file_size,
  storage_path,
  uploaded_by,
  created_at,
  updated_at,
  deleted_at
)
SELECT
  d.id AS document_id,
  d.person_id,
  1 AS version_number,
  d.title,
  d.reference_sei,
  d.document_date,
  d.tags,
  d.notes,
  COALESCE(NULLIF(d.sensitivity_level, ''), 'public') AS sensitivity_level,
  d.original_name,
  d.stored_name,
  d.mime_type,
  d.file_size,
  d.storage_path,
  d.uploaded_by,
  d.created_at,
  d.updated_at,
  d.deleted_at
FROM documents d
LEFT JOIN document_versions dv
  ON dv.document_id = d.id
 AND dv.version_number = 1
WHERE dv.id IS NULL;
