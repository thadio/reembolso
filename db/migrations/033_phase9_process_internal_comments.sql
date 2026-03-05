-- Ciclo 9.7 - Comentarios internos por processo

CREATE TABLE IF NOT EXISTS process_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  comment_text TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'aberto',
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_process_comments_person_deleted (person_id, deleted_at),
  KEY idx_process_comments_assignment_deleted (assignment_id, deleted_at),
  KEY idx_process_comments_status_pinned (status, is_pinned, deleted_at),
  KEY idx_process_comments_created_by (created_by),
  KEY idx_process_comments_updated_by (updated_by),
  KEY idx_process_comments_deleted_by (deleted_by),
  CONSTRAINT fk_process_comments_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_process_comments_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_process_comments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_process_comments_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_process_comments_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
