-- Fase 9.14 - Gestao de lotes de pagamento

CREATE TABLE IF NOT EXISTS payment_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_code VARCHAR(80) NOT NULL,
  title VARCHAR(190) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'aberto',
  reference_month DATE NULL,
  scheduled_payment_date DATE NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payments_count INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  closed_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_payment_batches_code (batch_code),
  KEY idx_payment_batches_status (status),
  KEY idx_payment_batches_reference_month (reference_month),
  KEY idx_payment_batches_scheduled_date (scheduled_payment_date),
  KEY idx_payment_batches_created_by (created_by),
  KEY idx_payment_batches_closed_by (closed_by),
  KEY idx_payment_batches_deleted_at (deleted_at),
  CONSTRAINT fk_payment_batches_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_batches_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_batch_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_batch_items_payment (payment_id),
  UNIQUE KEY uq_payment_batch_items_batch_payment (batch_id, payment_id),
  KEY idx_payment_batch_items_batch (batch_id),
  KEY idx_payment_batch_items_invoice (invoice_id),
  KEY idx_payment_batch_items_payment_date (payment_date),
  CONSTRAINT fk_payment_batch_items_batch FOREIGN KEY (batch_id) REFERENCES payment_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_batch_items_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_batch_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
