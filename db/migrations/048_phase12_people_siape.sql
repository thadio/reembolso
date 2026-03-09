-- Fase 12.3 - Matricula SIAPE no cadastro de pessoas

SET @has_people_matricula_siape := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'matricula_siape'
);

SET @sql_people_matricula_siape := IF(
  @has_people_matricula_siape = 0,
  'ALTER TABLE people ADD COLUMN matricula_siape VARCHAR(20) NULL AFTER cpf',
  'SELECT 1'
);

PREPARE stmt_people_matricula_siape FROM @sql_people_matricula_siape;
EXECUTE stmt_people_matricula_siape;
DEALLOCATE PREPARE stmt_people_matricula_siape;

SET @has_uq_people_matricula_siape := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND INDEX_NAME = 'uq_people_matricula_siape'
);

SET @sql_uq_people_matricula_siape := IF(
  @has_uq_people_matricula_siape = 0,
  'ALTER TABLE people ADD UNIQUE KEY uq_people_matricula_siape (matricula_siape)',
  'SELECT 1'
);

PREPARE stmt_uq_people_matricula_siape FROM @sql_uq_people_matricula_siape;
EXECUTE stmt_uq_people_matricula_siape;
DEALLOCATE PREPARE stmt_uq_people_matricula_siape;
