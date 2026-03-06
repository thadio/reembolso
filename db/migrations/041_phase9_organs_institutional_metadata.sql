-- Fase 9.20 - Classificacao institucional para orgaos estatais/autarquias

SET @col_organ_type_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'organ_type'
);
SET @sql_add_organ_type := IF(
  @col_organ_type_exists = 0,
  'ALTER TABLE organs ADD COLUMN organ_type VARCHAR(50) NULL AFTER cnpj',
  'SELECT 1'
);
PREPARE stmt_add_organ_type FROM @sql_add_organ_type;
EXECUTE stmt_add_organ_type;
DEALLOCATE PREPARE stmt_add_organ_type;

SET @col_government_level_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'government_level'
);
SET @sql_add_government_level := IF(
  @col_government_level_exists = 0,
  'ALTER TABLE organs ADD COLUMN government_level VARCHAR(20) NULL AFTER organ_type',
  'SELECT 1'
);
PREPARE stmt_add_government_level FROM @sql_add_government_level;
EXECUTE stmt_add_government_level;
DEALLOCATE PREPARE stmt_add_government_level;

SET @col_government_branch_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'government_branch'
);
SET @sql_add_government_branch := IF(
  @col_government_branch_exists = 0,
  'ALTER TABLE organs ADD COLUMN government_branch VARCHAR(20) NULL AFTER government_level',
  'SELECT 1'
);
PREPARE stmt_add_government_branch FROM @sql_add_government_branch;
EXECUTE stmt_add_government_branch;
DEALLOCATE PREPARE stmt_add_government_branch;

SET @col_supervising_organ_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'supervising_organ'
);
SET @sql_add_supervising_organ := IF(
  @col_supervising_organ_exists = 0,
  'ALTER TABLE organs ADD COLUMN supervising_organ VARCHAR(180) NULL AFTER government_branch',
  'SELECT 1'
);
PREPARE stmt_add_supervising_organ FROM @sql_add_supervising_organ;
EXECUTE stmt_add_supervising_organ;
DEALLOCATE PREPARE stmt_add_supervising_organ;

SET @col_source_name_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'source_name'
);
SET @sql_add_source_name := IF(
  @col_source_name_exists = 0,
  'ALTER TABLE organs ADD COLUMN source_name VARCHAR(120) NULL AFTER notes',
  'SELECT 1'
);
PREPARE stmt_add_source_name FROM @sql_add_source_name;
EXECUTE stmt_add_source_name;
DEALLOCATE PREPARE stmt_add_source_name;

SET @col_source_url_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND column_name = 'source_url'
);
SET @sql_add_source_url := IF(
  @col_source_url_exists = 0,
  'ALTER TABLE organs ADD COLUMN source_url VARCHAR(255) NULL AFTER source_name',
  'SELECT 1'
);
PREPARE stmt_add_source_url FROM @sql_add_source_url;
EXECUTE stmt_add_source_url;
DEALLOCATE PREPARE stmt_add_source_url;

SET @idx_organs_type_level_branch_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND index_name = 'idx_organs_type_level_branch'
);
SET @sql_add_idx_organs_type_level_branch := IF(
  @idx_organs_type_level_branch_exists = 0,
  'ALTER TABLE organs ADD KEY idx_organs_type_level_branch (organ_type, government_level, government_branch)',
  'SELECT 1'
);
PREPARE stmt_add_idx_organs_type_level_branch FROM @sql_add_idx_organs_type_level_branch;
EXECUTE stmt_add_idx_organs_type_level_branch;
DEALLOCATE PREPARE stmt_add_idx_organs_type_level_branch;

SET @idx_organs_supervising_organ_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'organs'
    AND index_name = 'idx_organs_supervising_organ'
);
SET @sql_add_idx_organs_supervising_organ := IF(
  @idx_organs_supervising_organ_exists = 0,
  'ALTER TABLE organs ADD KEY idx_organs_supervising_organ (supervising_organ)',
  'SELECT 1'
);
PREPARE stmt_add_idx_organs_supervising_organ FROM @sql_add_idx_organs_supervising_organ;
EXECUTE stmt_add_idx_organs_supervising_organ;
DEALLOCATE PREPARE stmt_add_idx_organs_supervising_organ;
