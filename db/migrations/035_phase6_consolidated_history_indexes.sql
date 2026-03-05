-- Fase 6.2 - Historico consolidado de pessoa e orgao (otimizacao de leitura)

SET @idx_audit_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'audit_log'
    AND index_name = 'idx_audit_entity_entity_id_created'
);
SET @sql_audit_idx := IF(
  @idx_audit_exists = 0,
  'ALTER TABLE audit_log ADD KEY idx_audit_entity_entity_id_created (entity, entity_id, created_at)',
  'SELECT 1'
);
PREPARE stmt_audit_idx FROM @sql_audit_idx;
EXECUTE stmt_audit_idx;
DEALLOCATE PREPARE stmt_audit_idx;

SET @idx_people_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'people'
    AND index_name = 'idx_people_organ_deleted_id'
);
SET @sql_people_idx := IF(
  @idx_people_exists = 0,
  'ALTER TABLE people ADD KEY idx_people_organ_deleted_id (organ_id, deleted_at, id)',
  'SELECT 1'
);
PREPARE stmt_people_idx FROM @sql_people_idx;
EXECUTE stmt_people_idx;
DEALLOCATE PREPARE stmt_people_idx;

