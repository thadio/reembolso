-- Fase 12.8 - Remocao do campo legado people.mte_destination

SET @has_people_mte_destination := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'mte_destination'
);

SET @sql_drop_people_mte_destination := IF(
  @has_people_mte_destination = 1,
  'ALTER TABLE people DROP COLUMN mte_destination',
  'SELECT 1'
);

PREPARE stmt_drop_people_mte_destination FROM @sql_drop_people_mte_destination;
EXECUTE stmt_drop_people_mte_destination;
DEALLOCATE PREPARE stmt_drop_people_mte_destination;
