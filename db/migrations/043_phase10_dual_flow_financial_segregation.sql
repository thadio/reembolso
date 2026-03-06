-- Fase 10.2 - Duplo fluxo (entrada/saida MTE) e segregacao financeira receita x despesa

SET @has_assignments_movement_direction := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'movement_direction'
);

SET @sql_assignments_movement_direction := IF(
  @has_assignments_movement_direction = 0,
  'ALTER TABLE assignments
      ADD COLUMN movement_direction VARCHAR(20) NOT NULL DEFAULT ''entrada_mte'' AFTER modality_id',
  'SELECT 1'
);

PREPARE stmt_assignments_movement_direction FROM @sql_assignments_movement_direction;
EXECUTE stmt_assignments_movement_direction;
DEALLOCATE PREPARE stmt_assignments_movement_direction;

SET @has_assignments_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_assignments_financial_nature := IF(
  @has_assignments_financial_nature = 0,
  'ALTER TABLE assignments
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER movement_direction',
  'SELECT 1'
);

PREPARE stmt_assignments_financial_nature FROM @sql_assignments_financial_nature;
EXECUTE stmt_assignments_financial_nature;
DEALLOCATE PREPARE stmt_assignments_financial_nature;

SET @has_assignments_counterparty_organ := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'counterparty_organ_id'
);

SET @sql_assignments_counterparty_organ := IF(
  @has_assignments_counterparty_organ = 0,
  'ALTER TABLE assignments
      ADD COLUMN counterparty_organ_id BIGINT UNSIGNED NULL AFTER financial_nature',
  'SELECT 1'
);

PREPARE stmt_assignments_counterparty_organ FROM @sql_assignments_counterparty_organ;
EXECUTE stmt_assignments_counterparty_organ;
DEALLOCATE PREPARE stmt_assignments_counterparty_organ;

SET @has_assignments_origin_mte_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'origin_mte_destination_id'
);

SET @sql_assignments_origin_mte_destination := IF(
  @has_assignments_origin_mte_destination = 0,
  'ALTER TABLE assignments
      ADD COLUMN origin_mte_destination_id BIGINT UNSIGNED NULL AFTER counterparty_organ_id',
  'SELECT 1'
);

PREPARE stmt_assignments_origin_mte_destination FROM @sql_assignments_origin_mte_destination;
EXECUTE stmt_assignments_origin_mte_destination;
DEALLOCATE PREPARE stmt_assignments_origin_mte_destination;

SET @has_assignments_destination_mte_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'destination_mte_destination_id'
);

SET @sql_assignments_destination_mte_destination := IF(
  @has_assignments_destination_mte_destination = 0,
  'ALTER TABLE assignments
      ADD COLUMN destination_mte_destination_id BIGINT UNSIGNED NULL AFTER origin_mte_destination_id',
  'SELECT 1'
);

PREPARE stmt_assignments_destination_mte_destination FROM @sql_assignments_destination_mte_destination;
EXECUTE stmt_assignments_destination_mte_destination;
DEALLOCATE PREPARE stmt_assignments_destination_mte_destination;

SET @has_assignments_requested_end_date := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'requested_end_date'
);

SET @sql_assignments_requested_end_date := IF(
  @has_assignments_requested_end_date = 0,
  'ALTER TABLE assignments
      ADD COLUMN requested_end_date DATE NULL AFTER effective_start_date',
  'SELECT 1'
);

PREPARE stmt_assignments_requested_end_date FROM @sql_assignments_requested_end_date;
EXECUTE stmt_assignments_requested_end_date;
DEALLOCATE PREPARE stmt_assignments_requested_end_date;

SET @has_assignments_effective_end_date := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'effective_end_date'
);

SET @sql_assignments_effective_end_date := IF(
  @has_assignments_effective_end_date = 0,
  'ALTER TABLE assignments
      ADD COLUMN effective_end_date DATE NULL AFTER requested_end_date',
  'SELECT 1'
);

PREPARE stmt_assignments_effective_end_date FROM @sql_assignments_effective_end_date;
EXECUTE stmt_assignments_effective_end_date;
DEALLOCATE PREPARE stmt_assignments_effective_end_date;

SET @has_assignments_termination_reason := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'termination_reason'
);

SET @sql_assignments_termination_reason := IF(
  @has_assignments_termination_reason = 0,
  'ALTER TABLE assignments
      ADD COLUMN termination_reason VARCHAR(255) NULL AFTER effective_end_date',
  'SELECT 1'
);

PREPARE stmt_assignments_termination_reason FROM @sql_assignments_termination_reason;
EXECUTE stmt_assignments_termination_reason;
DEALLOCATE PREPARE stmt_assignments_termination_reason;

SET @has_assignments_movement_code := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'movement_code'
);

SET @sql_assignments_movement_code := IF(
  @has_assignments_movement_code = 0,
  'ALTER TABLE assignments
      ADD COLUMN movement_code VARCHAR(40) NULL AFTER termination_reason',
  'SELECT 1'
);

PREPARE stmt_assignments_movement_code FROM @sql_assignments_movement_code;
EXECUTE stmt_assignments_movement_code;
DEALLOCATE PREPARE stmt_assignments_movement_code;

SET @has_idx_assignments_movement_direction := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_movement_direction_deleted'
);

SET @sql_idx_assignments_movement_direction := IF(
  @has_idx_assignments_movement_direction = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_movement_direction_deleted (movement_direction, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_movement_direction FROM @sql_idx_assignments_movement_direction;
EXECUTE stmt_idx_assignments_movement_direction;
DEALLOCATE PREPARE stmt_idx_assignments_movement_direction;

SET @has_idx_assignments_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_fin_nature_status_deleted'
);

SET @sql_idx_assignments_financial_nature := IF(
  @has_idx_assignments_financial_nature = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_fin_nature_status_deleted (financial_nature, current_status_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_financial_nature FROM @sql_idx_assignments_financial_nature;
EXECUTE stmt_idx_assignments_financial_nature;
DEALLOCATE PREPARE stmt_idx_assignments_financial_nature;

SET @has_idx_assignments_counterparty_organ := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_counterparty_organ_deleted'
);

SET @sql_idx_assignments_counterparty_organ := IF(
  @has_idx_assignments_counterparty_organ = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_counterparty_organ_deleted (counterparty_organ_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_counterparty_organ FROM @sql_idx_assignments_counterparty_organ;
EXECUTE stmt_idx_assignments_counterparty_organ;
DEALLOCATE PREPARE stmt_idx_assignments_counterparty_organ;

SET @has_idx_assignments_origin_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_origin_destination_deleted'
);

SET @sql_idx_assignments_origin_destination := IF(
  @has_idx_assignments_origin_destination = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_origin_destination_deleted (origin_mte_destination_id, destination_mte_destination_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_origin_destination FROM @sql_idx_assignments_origin_destination;
EXECUTE stmt_idx_assignments_origin_destination;
DEALLOCATE PREPARE stmt_idx_assignments_origin_destination;

SET @has_uq_assignments_movement_code := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'uq_assignments_movement_code'
);

SET @sql_uq_assignments_movement_code := IF(
  @has_uq_assignments_movement_code = 0,
  'ALTER TABLE assignments
      ADD UNIQUE KEY uq_assignments_movement_code (movement_code)',
  'SELECT 1'
);

PREPARE stmt_uq_assignments_movement_code FROM @sql_uq_assignments_movement_code;
EXECUTE stmt_uq_assignments_movement_code;
DEALLOCATE PREPARE stmt_uq_assignments_movement_code;

SET @has_fk_assignments_counterparty_organ := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'counterparty_organ_id'
    AND REFERENCED_TABLE_NAME = 'organs'
);

SET @sql_fk_assignments_counterparty_organ := IF(
  @has_fk_assignments_counterparty_organ = 0,
  'ALTER TABLE assignments
      ADD CONSTRAINT fk_assignments_counterparty_organ
      FOREIGN KEY (counterparty_organ_id) REFERENCES organs(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_assignments_counterparty_organ FROM @sql_fk_assignments_counterparty_organ;
EXECUTE stmt_fk_assignments_counterparty_organ;
DEALLOCATE PREPARE stmt_fk_assignments_counterparty_organ;

SET @has_fk_assignments_origin_mte_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'origin_mte_destination_id'
    AND REFERENCED_TABLE_NAME = 'mte_destinations'
);

SET @sql_fk_assignments_origin_mte_destination := IF(
  @has_fk_assignments_origin_mte_destination = 0,
  'ALTER TABLE assignments
      ADD CONSTRAINT fk_assignments_origin_mte_destination
      FOREIGN KEY (origin_mte_destination_id) REFERENCES mte_destinations(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_assignments_origin_mte_destination FROM @sql_fk_assignments_origin_mte_destination;
EXECUTE stmt_fk_assignments_origin_mte_destination;
DEALLOCATE PREPARE stmt_fk_assignments_origin_mte_destination;

SET @has_fk_assignments_destination_mte_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'destination_mte_destination_id'
    AND REFERENCED_TABLE_NAME = 'mte_destinations'
);

SET @sql_fk_assignments_destination_mte_destination := IF(
  @has_fk_assignments_destination_mte_destination = 0,
  'ALTER TABLE assignments
      ADD CONSTRAINT fk_assignments_destination_mte_destination
      FOREIGN KEY (destination_mte_destination_id) REFERENCES mte_destinations(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_assignments_destination_mte_destination FROM @sql_fk_assignments_destination_mte_destination;
EXECUTE stmt_fk_assignments_destination_mte_destination;
DEALLOCATE PREPARE stmt_fk_assignments_destination_mte_destination;

UPDATE assignments a
INNER JOIN people p ON p.id = a.person_id
SET a.counterparty_organ_id = p.organ_id
WHERE a.deleted_at IS NULL
  AND a.counterparty_organ_id IS NULL;

UPDATE assignments a
INNER JOIN people p ON p.id = a.person_id
INNER JOIN mte_destinations d
  ON d.deleted_at IS NULL
 AND TRIM(d.name) = TRIM(p.mte_destination)
SET a.destination_mte_destination_id = d.id
WHERE a.deleted_at IS NULL
  AND a.movement_direction = 'entrada_mte'
  AND a.destination_mte_destination_id IS NULL
  AND p.mte_destination IS NOT NULL
  AND TRIM(p.mte_destination) <> '';

UPDATE assignments
SET movement_code = CONCAT('MOV-', id),
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND (movement_code IS NULL OR movement_code = '');

SET @has_reimbursement_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_reimbursement_financial_nature := IF(
  @has_reimbursement_financial_nature = 0,
  'ALTER TABLE reimbursement_entries
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER entry_type',
  'SELECT 1'
);

PREPARE stmt_reimbursement_financial_nature FROM @sql_reimbursement_financial_nature;
EXECUTE stmt_reimbursement_financial_nature;
DEALLOCATE PREPARE stmt_reimbursement_financial_nature;

SET @has_idx_reimbursement_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND INDEX_NAME = 'idx_reimbursement_financial_nature'
);

SET @sql_idx_reimbursement_financial_nature := IF(
  @has_idx_reimbursement_financial_nature = 0,
  'ALTER TABLE reimbursement_entries
      ADD KEY idx_reimbursement_financial_nature (financial_nature, status, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_reimbursement_financial_nature FROM @sql_idx_reimbursement_financial_nature;
EXECUTE stmt_idx_reimbursement_financial_nature;
DEALLOCATE PREPARE stmt_idx_reimbursement_financial_nature;

SET @has_invoices_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_invoices_financial_nature := IF(
  @has_invoices_financial_nature = 0,
  'ALTER TABLE invoices
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER status',
  'SELECT 1'
);

PREPARE stmt_invoices_financial_nature FROM @sql_invoices_financial_nature;
EXECUTE stmt_invoices_financial_nature;
DEALLOCATE PREPARE stmt_invoices_financial_nature;

SET @has_idx_invoices_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND INDEX_NAME = 'idx_invoices_financial_nature'
);

SET @sql_idx_invoices_financial_nature := IF(
  @has_idx_invoices_financial_nature = 0,
  'ALTER TABLE invoices
      ADD KEY idx_invoices_financial_nature (financial_nature, status, reference_month)',
  'SELECT 1'
);

PREPARE stmt_idx_invoices_financial_nature FROM @sql_idx_invoices_financial_nature;
EXECUTE stmt_idx_invoices_financial_nature;
DEALLOCATE PREPARE stmt_idx_invoices_financial_nature;

SET @has_payments_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_payments_financial_nature := IF(
  @has_payments_financial_nature = 0,
  'ALTER TABLE payments
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER amount',
  'SELECT 1'
);

PREPARE stmt_payments_financial_nature FROM @sql_payments_financial_nature;
EXECUTE stmt_payments_financial_nature;
DEALLOCATE PREPARE stmt_payments_financial_nature;

SET @has_idx_payments_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_financial_nature'
);

SET @sql_idx_payments_financial_nature := IF(
  @has_idx_payments_financial_nature = 0,
  'ALTER TABLE payments
      ADD KEY idx_payments_financial_nature (financial_nature, payment_date, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_payments_financial_nature FROM @sql_idx_payments_financial_nature;
EXECUTE stmt_idx_payments_financial_nature;
DEALLOCATE PREPARE stmt_idx_payments_financial_nature;

SET @has_payment_batches_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_batches'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_payment_batches_financial_nature := IF(
  @has_payment_batches_financial_nature = 0,
  'ALTER TABLE payment_batches
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER status',
  'SELECT 1'
);

PREPARE stmt_payment_batches_financial_nature FROM @sql_payment_batches_financial_nature;
EXECUTE stmt_payment_batches_financial_nature;
DEALLOCATE PREPARE stmt_payment_batches_financial_nature;

SET @has_idx_payment_batches_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_batches'
    AND INDEX_NAME = 'idx_payment_batches_financial_nature'
);

SET @sql_idx_payment_batches_financial_nature := IF(
  @has_idx_payment_batches_financial_nature = 0,
  'ALTER TABLE payment_batches
      ADD KEY idx_payment_batches_financial_nature (financial_nature, status, reference_month)',
  'SELECT 1'
);

PREPARE stmt_idx_payment_batches_financial_nature FROM @sql_idx_payment_batches_financial_nature;
EXECUTE stmt_idx_payment_batches_financial_nature;
DEALLOCATE PREPARE stmt_idx_payment_batches_financial_nature;

UPDATE payments p
INNER JOIN invoices i ON i.id = p.invoice_id
SET p.financial_nature = i.financial_nature
WHERE p.deleted_at IS NULL
  AND (p.financial_nature IS NULL OR p.financial_nature = '');

SET @has_budget_cycles_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_cycles'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_budget_cycles_financial_nature := IF(
  @has_budget_cycles_financial_nature = 0,
  'ALTER TABLE budget_cycles
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER cycle_year',
  'SELECT 1'
);

PREPARE stmt_budget_cycles_financial_nature FROM @sql_budget_cycles_financial_nature;
EXECUTE stmt_budget_cycles_financial_nature;
DEALLOCATE PREPARE stmt_budget_cycles_financial_nature;

SET @has_budget_cycles_uq_year_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_cycles'
    AND INDEX_NAME = 'uq_budget_cycles_year_nature'
);

SET @has_budget_cycles_uq_year_legacy := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_cycles'
    AND INDEX_NAME = 'uq_budget_cycles_year'
);

SET @sql_budget_cycles_drop_uq_year_legacy := IF(
  @has_budget_cycles_uq_year_legacy > 0,
  'ALTER TABLE budget_cycles
      DROP INDEX uq_budget_cycles_year',
  'SELECT 1'
);

PREPARE stmt_budget_cycles_drop_uq_year_legacy FROM @sql_budget_cycles_drop_uq_year_legacy;
EXECUTE stmt_budget_cycles_drop_uq_year_legacy;
DEALLOCATE PREPARE stmt_budget_cycles_drop_uq_year_legacy;

SET @sql_budget_cycles_uq_year_nature := IF(
  @has_budget_cycles_uq_year_nature = 0,
  'ALTER TABLE budget_cycles
      ADD UNIQUE KEY uq_budget_cycles_year_nature (cycle_year, financial_nature)',
  'SELECT 1'
);

PREPARE stmt_budget_cycles_uq_year_nature FROM @sql_budget_cycles_uq_year_nature;
EXECUTE stmt_budget_cycles_uq_year_nature;
DEALLOCATE PREPARE stmt_budget_cycles_uq_year_nature;

SET @has_budget_scenario_parameters_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_scenario_parameters'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_budget_scenario_parameters_financial_nature := IF(
  @has_budget_scenario_parameters_financial_nature = 0,
  'ALTER TABLE budget_scenario_parameters
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER budget_cycle_id',
  'SELECT 1'
);

PREPARE stmt_budget_scenario_parameters_financial_nature FROM @sql_budget_scenario_parameters_financial_nature;
EXECUTE stmt_budget_scenario_parameters_financial_nature;
DEALLOCATE PREPARE stmt_budget_scenario_parameters_financial_nature;

SET @has_budget_scenario_parameters_uq_scope_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_scenario_parameters'
    AND INDEX_NAME = 'uq_budget_scenario_parameters_scope_nature'
);

SET @sql_budget_scenario_parameters_uq_scope_nature := IF(
  @has_budget_scenario_parameters_uq_scope_nature = 0,
  'ALTER TABLE budget_scenario_parameters
      ADD UNIQUE KEY uq_budget_scenario_parameters_scope_nature (budget_cycle_id, financial_nature, organ_id, modality)',
  'SELECT 1'
);

PREPARE stmt_budget_scenario_parameters_uq_scope_nature FROM @sql_budget_scenario_parameters_uq_scope_nature;
EXECUTE stmt_budget_scenario_parameters_uq_scope_nature;
DEALLOCATE PREPARE stmt_budget_scenario_parameters_uq_scope_nature;

SET @has_budget_scenario_parameters_uq_scope_nature_after := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_scenario_parameters'
    AND INDEX_NAME = 'uq_budget_scenario_parameters_scope_nature'
);

SET @has_budget_scenario_parameters_uq_scope_legacy := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'budget_scenario_parameters'
    AND INDEX_NAME = 'uq_budget_scenario_parameters_scope'
);

SET @sql_budget_scenario_parameters_drop_uq_scope_legacy := IF(
  @has_budget_scenario_parameters_uq_scope_legacy > 0
  AND @has_budget_scenario_parameters_uq_scope_nature_after > 0,
  'ALTER TABLE budget_scenario_parameters
      DROP INDEX uq_budget_scenario_parameters_scope',
  'SELECT 1'
);

PREPARE stmt_budget_scenario_parameters_drop_uq_scope_legacy FROM @sql_budget_scenario_parameters_drop_uq_scope_legacy;
EXECUTE stmt_budget_scenario_parameters_drop_uq_scope_legacy;
DEALLOCATE PREPARE stmt_budget_scenario_parameters_drop_uq_scope_legacy;

SET @has_hiring_scenarios_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hiring_scenarios'
    AND COLUMN_NAME = 'financial_nature'
);

SET @sql_hiring_scenarios_financial_nature := IF(
  @has_hiring_scenarios_financial_nature = 0,
  'ALTER TABLE hiring_scenarios
      ADD COLUMN financial_nature VARCHAR(30) NOT NULL DEFAULT ''despesa_reembolso'' AFTER budget_cycle_id',
  'SELECT 1'
);

PREPARE stmt_hiring_scenarios_financial_nature FROM @sql_hiring_scenarios_financial_nature;
EXECUTE stmt_hiring_scenarios_financial_nature;
DEALLOCATE PREPARE stmt_hiring_scenarios_financial_nature;

SET @has_idx_hiring_scenarios_financial_nature := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hiring_scenarios'
    AND INDEX_NAME = 'idx_hiring_scenarios_financial_nature'
);

SET @sql_idx_hiring_scenarios_financial_nature := IF(
  @has_idx_hiring_scenarios_financial_nature = 0,
  'ALTER TABLE hiring_scenarios
      ADD KEY idx_hiring_scenarios_financial_nature (financial_nature, risk_level, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_hiring_scenarios_financial_nature FROM @sql_idx_hiring_scenarios_financial_nature;
EXECUTE stmt_idx_hiring_scenarios_financial_nature;
DEALLOCATE PREPARE stmt_idx_hiring_scenarios_financial_nature;

UPDATE budget_scenario_parameters bsp
INNER JOIN budget_cycles bc ON bc.id = bsp.budget_cycle_id
SET bsp.financial_nature = bc.financial_nature
WHERE bsp.deleted_at IS NULL
  AND (bsp.financial_nature IS NULL OR bsp.financial_nature = '');

UPDATE hiring_scenarios hs
INNER JOIN budget_cycles bc ON bc.id = hs.budget_cycle_id
SET hs.financial_nature = bc.financial_nature
WHERE hs.deleted_at IS NULL
  AND (hs.financial_nature IS NULL OR hs.financial_nature = '');

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_triagem',
  'Saida - Triagem',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Validar lotacao de origem MTE',
  'pipeline.saida_validacao_lotacao_mte',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_triagem'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_validacao_lotacao_mte',
  'Saida - Validacao lotacao MTE',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Emitir oficio ao destino',
  'pipeline.saida_oficio_destino',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_validacao_lotacao_mte'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_oficio_destino',
  'Saida - Oficio destino',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar anuencia do destino',
  'pipeline.saida_anuencia_destino',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_oficio_destino'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_anuencia_destino',
  'Saida - Anuencia destino',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Formalizar instrumento de ressarcimento',
  'pipeline.saida_instrumento_ressarcimento',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_anuencia_destino'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_instrumento_ressarcimento',
  'Saida - Instrumento de ressarcimento',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Publicar e ativar no destino',
  'pipeline.saida_publicacao_ativacao',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_instrumento_ressarcimento'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_publicacao_ativacao',
  'Saida - Publicacao e ativacao',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Iniciar faturamento',
  'pipeline.saida_financeiro_faturamento',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_publicacao_ativacao'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_financeiro_faturamento',
  'Saida - Financeiro faturamento',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar recebimentos',
  'pipeline.saida_financeiro_recebimento',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_financeiro_faturamento'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_financeiro_recebimento',
  'Saida - Financeiro recebimento',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Encerrar caso de saida',
  'pipeline.saida_encerrado',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_financeiro_recebimento'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'saida_encerrado',
  'Saida - Encerrado',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  NULL,
  'pipeline.saida_encerrado',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'saida_encerrado'
);

INSERT INTO assignment_flows (name, description, is_active, is_default, created_at, updated_at)
SELECT
  'Fluxo saida MTE',
  'Fluxo operacional para cessao do MTE para orgao/empresa destino, com controle de recebimentos.',
  1,
  0,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM assignment_flows
  WHERE name = 'Fluxo saida MTE'
    AND deleted_at IS NULL
);

SET @saida_flow_id := (
  SELECT id
  FROM assignment_flows
  WHERE name = 'Fluxo saida MTE'
    AND deleted_at IS NULL
  ORDER BY id ASC
  LIMIT 1
);

INSERT INTO assignment_flow_steps (flow_id, status_id, node_kind, sort_order, is_initial, is_active, created_at, updated_at)
SELECT
  @saida_flow_id,
  s.id,
  CASE WHEN s.code = 'saida_encerrado' THEN 'final' ELSE 'activity' END,
  CASE s.code
    WHEN 'saida_triagem' THEN 10
    WHEN 'saida_validacao_lotacao_mte' THEN 20
    WHEN 'saida_oficio_destino' THEN 30
    WHEN 'saida_anuencia_destino' THEN 40
    WHEN 'saida_instrumento_ressarcimento' THEN 50
    WHEN 'saida_publicacao_ativacao' THEN 60
    WHEN 'saida_financeiro_faturamento' THEN 70
    WHEN 'saida_financeiro_recebimento' THEN 80
    WHEN 'saida_encerrado' THEN 90
    ELSE 999
  END,
  CASE WHEN s.code = 'saida_triagem' THEN 1 ELSE 0 END,
  1,
  NOW(),
  NOW()
FROM assignment_statuses s
WHERE s.code IN (
    'saida_triagem',
    'saida_validacao_lotacao_mte',
    'saida_oficio_destino',
    'saida_anuencia_destino',
    'saida_instrumento_ressarcimento',
    'saida_publicacao_ativacao',
    'saida_financeiro_faturamento',
    'saida_financeiro_recebimento',
    'saida_encerrado'
)
  AND s.is_active = 1
  AND @saida_flow_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM assignment_flow_steps fs
    WHERE fs.flow_id = @saida_flow_id
      AND fs.status_id = s.id
  );

INSERT INTO assignment_flow_transitions (
  flow_id,
  from_status_id,
  to_status_id,
  transition_label,
  action_label,
  event_type,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  @saida_flow_id,
  sf.id,
  st.id,
  transition_data.transition_label,
  transition_data.action_label,
  transition_data.event_type,
  transition_data.sort_order,
  1,
  NOW(),
  NOW()
FROM (
  SELECT 'saida_triagem' AS from_code, 'saida_validacao_lotacao_mte' AS to_code, 'Triagem concluida' AS transition_label, 'Validar lotacao de origem MTE' AS action_label, 'pipeline.saida_validacao_lotacao_mte' AS event_type, 10 AS sort_order
  UNION ALL SELECT 'saida_validacao_lotacao_mte', 'saida_oficio_destino', 'Validacao concluida', 'Emitir oficio ao destino', 'pipeline.saida_oficio_destino', 20
  UNION ALL SELECT 'saida_oficio_destino', 'saida_anuencia_destino', 'Oficio enviado', 'Registrar anuencia do destino', 'pipeline.saida_anuencia_destino', 30
  UNION ALL SELECT 'saida_anuencia_destino', 'saida_instrumento_ressarcimento', 'Anuencia recebida', 'Formalizar instrumento de ressarcimento', 'pipeline.saida_instrumento_ressarcimento', 40
  UNION ALL SELECT 'saida_instrumento_ressarcimento', 'saida_publicacao_ativacao', 'Instrumento formalizado', 'Publicar e ativar no destino', 'pipeline.saida_publicacao_ativacao', 50
  UNION ALL SELECT 'saida_publicacao_ativacao', 'saida_financeiro_faturamento', 'Ativacao registrada', 'Iniciar faturamento', 'pipeline.saida_financeiro_faturamento', 60
  UNION ALL SELECT 'saida_financeiro_faturamento', 'saida_financeiro_recebimento', 'Faturamento emitido', 'Registrar recebimentos', 'pipeline.saida_financeiro_recebimento', 70
  UNION ALL SELECT 'saida_financeiro_recebimento', 'saida_encerrado', 'Ciclo finalizado', 'Encerrar caso de saida', 'pipeline.saida_encerrado', 80
) transition_data
INNER JOIN assignment_statuses sf ON sf.code = transition_data.from_code
INNER JOIN assignment_statuses st ON st.code = transition_data.to_code
WHERE @saida_flow_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM assignment_flow_transitions ft
    WHERE ft.flow_id = @saida_flow_id
      AND ft.from_status_id = sf.id
      AND ft.to_status_id = st.id
  );
