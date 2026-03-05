-- Ciclo 9.6 - Calculadora automatica de reembolso com memoria de calculo

SET @has_calc_memory := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND COLUMN_NAME = 'calculation_memory'
);

SET @sql_add_calc_memory := IF(
  @has_calc_memory = 0,
  'ALTER TABLE reimbursement_entries ADD COLUMN calculation_memory LONGTEXT NULL AFTER notes',
  'SELECT 1'
);

PREPARE stmt_add_calc_memory FROM @sql_add_calc_memory;
EXECUTE stmt_add_calc_memory;
DEALLOCATE PREPARE stmt_add_calc_memory;
