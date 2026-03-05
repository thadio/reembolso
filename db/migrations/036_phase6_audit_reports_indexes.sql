-- Fase 6.2 - Relatorios prontos para auditoria (CGU/TCU): indices de apoio

SET @idx_audit_action_created_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'audit_log'
    AND index_name = 'idx_audit_action_created'
);
SET @sql_idx_audit_action_created := IF(
  @idx_audit_action_created_exists = 0,
  'ALTER TABLE audit_log ADD KEY idx_audit_action_created (action, created_at)',
  'SELECT 1'
);
PREPARE stmt_idx_audit_action_created FROM @sql_idx_audit_action_created;
EXECUTE stmt_idx_audit_action_created;
DEALLOCATE PREPARE stmt_idx_audit_action_created;

SET @idx_sensitive_subject_created_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sensitive_access_logs'
    AND index_name = 'idx_sensitive_access_subject_created'
);
SET @sql_idx_sensitive_subject_created := IF(
  @idx_sensitive_subject_created_exists = 0,
  'ALTER TABLE sensitive_access_logs ADD KEY idx_sensitive_access_subject_created (subject_person_id, created_at)',
  'SELECT 1'
);
PREPARE stmt_idx_sensitive_subject_created FROM @sql_idx_sensitive_subject_created;
EXECUTE stmt_idx_sensitive_subject_created;
DEALLOCATE PREPARE stmt_idx_sensitive_subject_created;

SET @idx_pending_status_created_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'analyst_pending_items'
    AND index_name = 'idx_analyst_pending_status_created'
);
SET @sql_idx_pending_status_created := IF(
  @idx_pending_status_created_exists = 0,
  'ALTER TABLE analyst_pending_items ADD KEY idx_analyst_pending_status_created (status, created_at)',
  'SELECT 1'
);
PREPARE stmt_idx_pending_status_created FROM @sql_idx_pending_status_created;
EXECUTE stmt_idx_pending_status_created;
DEALLOCATE PREPARE stmt_idx_pending_status_created;

SET @idx_divergence_required_open_created_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'cost_mirror_divergences'
    AND index_name = 'idx_divergence_required_open_created'
);
SET @sql_idx_divergence_required_open_created := IF(
  @idx_divergence_required_open_created_exists = 0,
  'ALTER TABLE cost_mirror_divergences ADD KEY idx_divergence_required_open_created (requires_justification, is_resolved, created_at)',
  'SELECT 1'
);
PREPARE stmt_idx_divergence_required_open_created FROM @sql_idx_divergence_required_open_created;
EXECUTE stmt_idx_divergence_required_open_created;
DEALLOCATE PREPARE stmt_idx_divergence_required_open_created;

