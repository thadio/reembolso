-- Fase 1.4 - Anexos por evento da timeline

CREATE TABLE IF NOT EXISTS timeline_event_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  timeline_event_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_timeline_att_event (timeline_event_id),
  KEY idx_timeline_att_person (person_id),
  KEY idx_timeline_att_uploaded_by (uploaded_by),
  CONSTRAINT fk_timeline_att_event FOREIGN KEY (timeline_event_id) REFERENCES timeline_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_timeline_att_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_timeline_att_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
