-- Fase 12.7 - Metadados completos de empresas publicas e sociedades mistas

SET @has_organs_company_nire := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'company_nire'
);

SET @sql_organs_company_nire := IF(
  @has_organs_company_nire = 0,
  'ALTER TABLE organs ADD COLUMN company_nire VARCHAR(40) NULL AFTER cnpj',
  'SELECT 1'
);

PREPARE stmt_organs_company_nire FROM @sql_organs_company_nire;
EXECUTE stmt_organs_company_nire;
DEALLOCATE PREPARE stmt_organs_company_nire;

SET @has_organs_company_dependency_type := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'company_dependency_type'
);

SET @sql_organs_company_dependency_type := IF(
  @has_organs_company_dependency_type = 0,
  'ALTER TABLE organs ADD COLUMN company_dependency_type VARCHAR(30) NULL AFTER organ_type',
  'SELECT 1'
);

PREPARE stmt_organs_company_dependency_type FROM @sql_organs_company_dependency_type;
EXECUTE stmt_organs_company_dependency_type;
DEALLOCATE PREPARE stmt_organs_company_dependency_type;

SET @has_organs_federative_entity := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'federative_entity'
);

SET @sql_organs_federative_entity := IF(
  @has_organs_federative_entity = 0,
  'ALTER TABLE organs ADD COLUMN federative_entity VARCHAR(120) NULL AFTER supervising_organ',
  'SELECT 1'
);

PREPARE stmt_organs_federative_entity FROM @sql_organs_federative_entity;
EXECUTE stmt_organs_federative_entity;
DEALLOCATE PREPARE stmt_organs_federative_entity;

SET @has_organs_company_objective := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'company_objective'
);

SET @sql_organs_company_objective := IF(
  @has_organs_company_objective = 0,
  'ALTER TABLE organs ADD COLUMN company_objective TEXT NULL AFTER notes',
  'SELECT 1'
);

PREPARE stmt_organs_company_objective FROM @sql_organs_company_objective;
EXECUTE stmt_organs_company_objective;
DEALLOCATE PREPARE stmt_organs_company_objective;

SET @has_organs_capital_information := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'capital_information'
);

SET @sql_organs_capital_information := IF(
  @has_organs_capital_information = 0,
  'ALTER TABLE organs ADD COLUMN capital_information TEXT NULL AFTER company_objective',
  'SELECT 1'
);

PREPARE stmt_organs_capital_information FROM @sql_organs_capital_information;
EXECUTE stmt_organs_capital_information;
DEALLOCATE PREPARE stmt_organs_capital_information;

SET @has_organs_creation_act := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'creation_act'
);

SET @sql_organs_creation_act := IF(
  @has_organs_creation_act = 0,
  'ALTER TABLE organs ADD COLUMN creation_act TEXT NULL AFTER capital_information',
  'SELECT 1'
);

PREPARE stmt_organs_creation_act FROM @sql_organs_creation_act;
EXECUTE stmt_organs_creation_act;
DEALLOCATE PREPARE stmt_organs_creation_act;

SET @has_organs_internal_regulations := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'internal_regulations'
);

SET @sql_organs_internal_regulations := IF(
  @has_organs_internal_regulations = 0,
  'ALTER TABLE organs ADD COLUMN internal_regulations TEXT NULL AFTER creation_act',
  'SELECT 1'
);

PREPARE stmt_organs_internal_regulations FROM @sql_organs_internal_regulations;
EXECUTE stmt_organs_internal_regulations;
DEALLOCATE PREPARE stmt_organs_internal_regulations;

SET @has_organs_subsidiaries := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'subsidiaries'
);

SET @sql_organs_subsidiaries := IF(
  @has_organs_subsidiaries = 0,
  'ALTER TABLE organs ADD COLUMN subsidiaries TEXT NULL AFTER internal_regulations',
  'SELECT 1'
);

PREPARE stmt_organs_subsidiaries FROM @sql_organs_subsidiaries;
EXECUTE stmt_organs_subsidiaries;
DEALLOCATE PREPARE stmt_organs_subsidiaries;

SET @has_organs_official_website := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND COLUMN_NAME = 'official_website'
);

SET @sql_organs_official_website := IF(
  @has_organs_official_website = 0,
  'ALTER TABLE organs ADD COLUMN official_website VARCHAR(255) NULL AFTER source_url',
  'SELECT 1'
);

PREPARE stmt_organs_official_website FROM @sql_organs_official_website;
EXECUTE stmt_organs_official_website;
DEALLOCATE PREPARE stmt_organs_official_website;

SET @has_idx_organs_company_nire := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND INDEX_NAME = 'idx_organs_company_nire'
);

SET @sql_idx_organs_company_nire := IF(
  @has_idx_organs_company_nire = 0,
  'ALTER TABLE organs ADD KEY idx_organs_company_nire (company_nire)',
  'SELECT 1'
);

PREPARE stmt_idx_organs_company_nire FROM @sql_idx_organs_company_nire;
EXECUTE stmt_idx_organs_company_nire;
DEALLOCATE PREPARE stmt_idx_organs_company_nire;

SET @has_idx_organs_company_dependency_type := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND INDEX_NAME = 'idx_organs_company_dependency_type'
);

SET @sql_idx_organs_company_dependency_type := IF(
  @has_idx_organs_company_dependency_type = 0,
  'ALTER TABLE organs ADD KEY idx_organs_company_dependency_type (company_dependency_type)',
  'SELECT 1'
);

PREPARE stmt_idx_organs_company_dependency_type FROM @sql_idx_organs_company_dependency_type;
EXECUTE stmt_idx_organs_company_dependency_type;
DEALLOCATE PREPARE stmt_idx_organs_company_dependency_type;

SET @has_idx_organs_federative_entity := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND INDEX_NAME = 'idx_organs_federative_entity'
);

SET @sql_idx_organs_federative_entity := IF(
  @has_idx_organs_federative_entity = 0,
  'ALTER TABLE organs ADD KEY idx_organs_federative_entity (federative_entity)',
  'SELECT 1'
);

PREPARE stmt_idx_organs_federative_entity FROM @sql_idx_organs_federative_entity;
EXECUTE stmt_idx_organs_federative_entity;
DEALLOCATE PREPARE stmt_idx_organs_federative_entity;
