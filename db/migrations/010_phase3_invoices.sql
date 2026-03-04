-- Fase 3.2 - Boletos estruturados por orgao e competencia

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organ_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(120) NOT NULL,
  title VARCHAR(190) NOT NULL,
  reference_month DATE NOT NULL,
  issue_date DATE NULL,
  due_date DATE NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  digitable_line VARCHAR(255) NULL,
  reference_code VARCHAR(120) NULL,
  pdf_original_name VARCHAR(255) NULL,
  pdf_stored_name VARCHAR(255) NULL,
  pdf_mime_type VARCHAR(120) NULL,
  pdf_file_size BIGINT UNSIGNED NULL,
  pdf_storage_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_invoices_number (invoice_number),
  KEY idx_invoices_organ_id (organ_id),
  KEY idx_invoices_reference_month (reference_month),
  KEY idx_invoices_due_date (due_date),
  KEY idx_invoices_status (status),
  KEY idx_invoices_created_by (created_by),
  KEY idx_invoices_deleted_at (deleted_at),
  CONSTRAINT fk_invoices_organ FOREIGN KEY (organ_id) REFERENCES organs(id),
  CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_people (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_invoice_people_invoice (invoice_id),
  KEY idx_invoice_people_person (person_id),
  KEY idx_invoice_people_created_by (created_by),
  KEY idx_invoice_people_deleted_at (deleted_at),
  CONSTRAINT fk_invoice_people_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_invoice_people_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_invoice_people_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('invoice.view', 'Visualizar boletos estruturados', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('invoice.manage', 'Gerenciar boletos estruturados', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'invoice.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'invoice.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
