-- Fase 16.1 - Campos semanticos adicionais na importacao de pessoas/orgaos

SET @has_people_gender := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'gender'
);

SET @sql_people_gender := IF(
  @has_people_gender = 0,
  'ALTER TABLE people ADD COLUMN gender VARCHAR(20) NULL AFTER birth_date',
  'SELECT 1'
);

PREPARE stmt_people_gender FROM @sql_people_gender;
EXECUTE stmt_people_gender;
DEALLOCATE PREPARE stmt_people_gender;

SET @has_people_server_status_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'server_status_code'
);

SET @sql_people_server_status_code := IF(
  @has_people_server_status_code = 0,
  'ALTER TABLE people ADD COLUMN server_status_code VARCHAR(30) NULL AFTER gender',
  'SELECT 1'
);

PREPARE stmt_people_server_status_code FROM @sql_people_server_status_code;
EXECUTE stmt_people_server_status_code;
DEALLOCATE PREPARE stmt_people_server_status_code;

SET @has_organs_siape_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'siape_code'
);

SET @sql_organs_siape_code := IF(
  @has_organs_siape_code = 0,
  'ALTER TABLE organs ADD COLUMN siape_code VARCHAR(20) NULL AFTER cnpj',
  'SELECT 1'
);

PREPARE stmt_organs_siape_code FROM @sql_organs_siape_code;
EXECUTE stmt_organs_siape_code;
DEALLOCATE PREPARE stmt_organs_siape_code;

SET @has_uq_organs_siape_code := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND INDEX_NAME = 'uq_organs_siape_code'
);

SET @sql_uq_organs_siape_code := IF(
  @has_uq_organs_siape_code = 0,
  'ALTER TABLE organs ADD UNIQUE KEY uq_organs_siape_code (siape_code)',
  'SELECT 1'
);

PREPARE stmt_uq_organs_siape_code FROM @sql_uq_organs_siape_code;
EXECUTE stmt_uq_organs_siape_code;
DEALLOCATE PREPARE stmt_uq_organs_siape_code;

SET @has_idx_people_server_status_code := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND INDEX_NAME = 'idx_people_server_status_code'
);

SET @sql_idx_people_server_status_code := IF(
  @has_idx_people_server_status_code = 0,
  'ALTER TABLE people ADD KEY idx_people_server_status_code (server_status_code)',
  'SELECT 1'
);

PREPARE stmt_idx_people_server_status_code FROM @sql_idx_people_server_status_code;
EXECUTE stmt_idx_people_server_status_code;
DEALLOCATE PREPARE stmt_idx_people_server_status_code;
