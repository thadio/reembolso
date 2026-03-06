-- Fase 11.1 - Evidencias de etapa BPMN (anexo/link) e links na timeline

SET @has_assignment_flow_steps_requires_evidence_close := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignment_flow_steps'
    AND COLUMN_NAME = 'requires_evidence_close'
);

SET @sql_assignment_flow_steps_requires_evidence_close := IF(
  @has_assignment_flow_steps_requires_evidence_close = 0,
  'ALTER TABLE assignment_flow_steps
      ADD COLUMN requires_evidence_close TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'SELECT 1'
);

PREPARE stmt_assignment_flow_steps_requires_evidence_close FROM @sql_assignment_flow_steps_requires_evidence_close;
EXECUTE stmt_assignment_flow_steps_requires_evidence_close;
DEALLOCATE PREPARE stmt_assignment_flow_steps_requires_evidence_close;

SET @has_assignment_flow_steps_step_tags := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignment_flow_steps'
    AND COLUMN_NAME = 'step_tags'
);

SET @sql_assignment_flow_steps_step_tags := IF(
  @has_assignment_flow_steps_step_tags = 0,
  'ALTER TABLE assignment_flow_steps
      ADD COLUMN step_tags VARCHAR(500) NULL AFTER requires_evidence_close',
  'SELECT 1'
);

PREPARE stmt_assignment_flow_steps_step_tags FROM @sql_assignment_flow_steps_step_tags;
EXECUTE stmt_assignment_flow_steps_step_tags;
DEALLOCATE PREPARE stmt_assignment_flow_steps_step_tags;

SET @has_idx_assignment_flow_steps_requires_evidence := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignment_flow_steps'
    AND INDEX_NAME = 'idx_assignment_flow_steps_requires_evidence'
);

SET @sql_idx_assignment_flow_steps_requires_evidence := IF(
  @has_idx_assignment_flow_steps_requires_evidence = 0,
  'ALTER TABLE assignment_flow_steps
      ADD KEY idx_assignment_flow_steps_requires_evidence (flow_id, requires_evidence_close, is_active)',
  'SELECT 1'
);

PREPARE stmt_idx_assignment_flow_steps_requires_evidence FROM @sql_idx_assignment_flow_steps_requires_evidence;
EXECUTE stmt_idx_assignment_flow_steps_requires_evidence;
DEALLOCATE PREPARE stmt_idx_assignment_flow_steps_requires_evidence;

CREATE TABLE IF NOT EXISTS timeline_event_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  timeline_event_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(1000) NOT NULL,
  label VARCHAR(190) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_timeline_links_event (timeline_event_id),
  KEY idx_timeline_links_person (person_id),
  KEY idx_timeline_links_created_by (created_by),
  CONSTRAINT fk_timeline_links_event FOREIGN KEY (timeline_event_id) REFERENCES timeline_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_timeline_links_person FOREIGN KEY (person_id) REFERENCES people(id),
  CONSTRAINT fk_timeline_links_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
