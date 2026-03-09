-- Fase 12.4 - Metadados UORG para lotacoes MTE

SET @has_mte_destinations_acronym := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND COLUMN_NAME = 'acronym'
);

SET @sql_mte_destinations_acronym := IF(
  @has_mte_destinations_acronym = 0,
  'ALTER TABLE mte_destinations ADD COLUMN acronym VARCHAR(60) NULL AFTER code',
  'SELECT 1'
);

PREPARE stmt_mte_destinations_acronym FROM @sql_mte_destinations_acronym;
EXECUTE stmt_mte_destinations_acronym;
DEALLOCATE PREPARE stmt_mte_destinations_acronym;

SET @has_mte_destinations_uf := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND COLUMN_NAME = 'uf'
);

SET @sql_mte_destinations_uf := IF(
  @has_mte_destinations_uf = 0,
  'ALTER TABLE mte_destinations ADD COLUMN uf CHAR(2) NULL AFTER acronym',
  'SELECT 1'
);

PREPARE stmt_mte_destinations_uf FROM @sql_mte_destinations_uf;
EXECUTE stmt_mte_destinations_uf;
DEALLOCATE PREPARE stmt_mte_destinations_uf;

SET @has_mte_destinations_upag_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND COLUMN_NAME = 'upag_code'
);

SET @sql_mte_destinations_upag_code := IF(
  @has_mte_destinations_upag_code = 0,
  'ALTER TABLE mte_destinations ADD COLUMN upag_code VARCHAR(20) NULL AFTER uf',
  'SELECT 1'
);

PREPARE stmt_mte_destinations_upag_code FROM @sql_mte_destinations_upag_code;
EXECUTE stmt_mte_destinations_upag_code;
DEALLOCATE PREPARE stmt_mte_destinations_upag_code;

SET @has_mte_destinations_parent_uorg_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND COLUMN_NAME = 'parent_uorg_code'
);

SET @sql_mte_destinations_parent_uorg_code := IF(
  @has_mte_destinations_parent_uorg_code = 0,
  'ALTER TABLE mte_destinations ADD COLUMN parent_uorg_code VARCHAR(20) NULL AFTER upag_code',
  'SELECT 1'
);

PREPARE stmt_mte_destinations_parent_uorg_code FROM @sql_mte_destinations_parent_uorg_code;
EXECUTE stmt_mte_destinations_parent_uorg_code;
DEALLOCATE PREPARE stmt_mte_destinations_parent_uorg_code;

SET @has_idx_mte_destinations_acronym := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND INDEX_NAME = 'idx_mte_destinations_acronym'
);

SET @sql_idx_mte_destinations_acronym := IF(
  @has_idx_mte_destinations_acronym = 0,
  'ALTER TABLE mte_destinations ADD KEY idx_mte_destinations_acronym (acronym)',
  'SELECT 1'
);

PREPARE stmt_idx_mte_destinations_acronym FROM @sql_idx_mte_destinations_acronym;
EXECUTE stmt_idx_mte_destinations_acronym;
DEALLOCATE PREPARE stmt_idx_mte_destinations_acronym;

SET @has_idx_mte_destinations_uf := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND INDEX_NAME = 'idx_mte_destinations_uf'
);

SET @sql_idx_mte_destinations_uf := IF(
  @has_idx_mte_destinations_uf = 0,
  'ALTER TABLE mte_destinations ADD KEY idx_mte_destinations_uf (uf)',
  'SELECT 1'
);

PREPARE stmt_idx_mte_destinations_uf FROM @sql_idx_mte_destinations_uf;
EXECUTE stmt_idx_mte_destinations_uf;
DEALLOCATE PREPARE stmt_idx_mte_destinations_uf;

SET @has_idx_mte_destinations_upag_code := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND INDEX_NAME = 'idx_mte_destinations_upag_code'
);

SET @sql_idx_mte_destinations_upag_code := IF(
  @has_idx_mte_destinations_upag_code = 0,
  'ALTER TABLE mte_destinations ADD KEY idx_mte_destinations_upag_code (upag_code)',
  'SELECT 1'
);

PREPARE stmt_idx_mte_destinations_upag_code FROM @sql_idx_mte_destinations_upag_code;
EXECUTE stmt_idx_mte_destinations_upag_code;
DEALLOCATE PREPARE stmt_idx_mte_destinations_upag_code;

SET @has_idx_mte_destinations_parent_uorg_code := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mte_destinations'
    AND INDEX_NAME = 'idx_mte_destinations_parent_uorg_code'
);

SET @sql_idx_mte_destinations_parent_uorg_code := IF(
  @has_idx_mte_destinations_parent_uorg_code = 0,
  'ALTER TABLE mte_destinations ADD KEY idx_mte_destinations_parent_uorg_code (parent_uorg_code)',
  'SELECT 1'
);

PREPARE stmt_idx_mte_destinations_parent_uorg_code FROM @sql_idx_mte_destinations_parent_uorg_code;
EXECUTE stmt_idx_mte_destinations_parent_uorg_code;
DEALLOCATE PREPARE stmt_idx_mte_destinations_parent_uorg_code;

UPDATE mte_destinations
SET acronym = code
WHERE (acronym IS NULL OR acronym = '')
  AND code IS NOT NULL
  AND code <> ''
  AND code NOT REGEXP '^[0-9]{14}$';
