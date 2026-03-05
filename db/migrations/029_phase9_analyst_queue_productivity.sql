-- Ciclo 9.3 - Produtividade do analista: fila por responsavel e prioridade

SET @has_assignments_assigned_user := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'assigned_user_id'
);

SET @sql_assignments_assigned_user := IF(
  @has_assignments_assigned_user = 0,
  'ALTER TABLE assignments
      ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER modality_id',
  'SELECT 1'
);

PREPARE stmt_assignments_assigned_user FROM @sql_assignments_assigned_user;
EXECUTE stmt_assignments_assigned_user;
DEALLOCATE PREPARE stmt_assignments_assigned_user;

SET @has_assignments_priority := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'priority_level'
);

SET @sql_assignments_priority := IF(
  @has_assignments_priority = 0,
  'ALTER TABLE assignments
      ADD COLUMN priority_level VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER current_status_id',
  'SELECT 1'
);

PREPARE stmt_assignments_priority FROM @sql_assignments_priority;
EXECUTE stmt_assignments_priority;
DEALLOCATE PREPARE stmt_assignments_priority;

UPDATE assignments a
LEFT JOIN users u ON u.id = a.assigned_user_id
SET a.assigned_user_id = NULL
WHERE a.assigned_user_id IS NOT NULL
  AND u.id IS NULL;

UPDATE assignments
SET priority_level = 'normal'
WHERE priority_level IS NULL
   OR priority_level = ''
   OR priority_level NOT IN ('low', 'normal', 'high', 'urgent');

SET @has_idx_assignments_assigned_deleted := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_assigned_deleted'
);

SET @sql_idx_assignments_assigned_deleted := IF(
  @has_idx_assignments_assigned_deleted = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_assigned_deleted (assigned_user_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_assigned_deleted FROM @sql_idx_assignments_assigned_deleted;
EXECUTE stmt_idx_assignments_assigned_deleted;
DEALLOCATE PREPARE stmt_idx_assignments_assigned_deleted;

SET @has_idx_assignments_priority_deleted := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_priority_deleted'
);

SET @sql_idx_assignments_priority_deleted := IF(
  @has_idx_assignments_priority_deleted = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_priority_deleted (priority_level, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_priority_deleted FROM @sql_idx_assignments_priority_deleted;
EXECUTE stmt_idx_assignments_priority_deleted;
DEALLOCATE PREPARE stmt_idx_assignments_priority_deleted;

SET @has_fk_assignments_assigned_user := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'assigned_user_id'
    AND REFERENCED_TABLE_NAME = 'users'
);

SET @sql_fk_assignments_assigned_user := IF(
  @has_fk_assignments_assigned_user = 0,
  'ALTER TABLE assignments
      ADD CONSTRAINT fk_assignments_assigned_user
      FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_assignments_assigned_user FROM @sql_fk_assignments_assigned_user;
EXECUTE stmt_fk_assignments_assigned_user;
DEALLOCATE PREPARE stmt_fk_assignments_assigned_user;
