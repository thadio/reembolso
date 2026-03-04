-- Fase 3.5 - Pagamentos completos por boleto

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  process_reference VARCHAR(120) NULL,
  proof_original_name VARCHAR(255) NULL,
  proof_stored_name VARCHAR(255) NULL,
  proof_mime_type VARCHAR(120) NULL,
  proof_file_size BIGINT UNSIGNED NULL,
  proof_storage_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_payments_invoice_id (invoice_id),
  KEY idx_payments_payment_date (payment_date),
  KEY idx_payments_created_by (created_by),
  KEY idx_payments_deleted_at (deleted_at),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_payments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_people (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  invoice_person_id BIGINT UNSIGNED NULL,
  person_id BIGINT UNSIGNED NULL,
  allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_payment_people_payment_id (payment_id),
  KEY idx_payment_people_invoice_id (invoice_id),
  KEY idx_payment_people_invoice_person_id (invoice_person_id),
  KEY idx_payment_people_person_id (person_id),
  KEY idx_payment_people_deleted_at (deleted_at),
  CONSTRAINT fk_payment_people_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_people_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_people_invoice_person FOREIGN KEY (invoice_person_id) REFERENCES invoice_people(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_people_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
