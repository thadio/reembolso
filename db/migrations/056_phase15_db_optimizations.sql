-- Fase 15 - Otimizacoes de banco (busca global, filtros por competencia, snapshots e escopo de auditoria)
-- Compatibilidade: MySQL/Percona 5.7

-- 1) CPF normalizado para busca indexada
SET @has_people_cpf_digits := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'cpf_digits'
);

SET @sql_add_people_cpf_digits := IF(
  @has_people_cpf_digits = 0,
  'ALTER TABLE people
      ADD COLUMN cpf_digits VARCHAR(20)
      GENERATED ALWAYS AS (
        REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(cpf, ''''), ''.'', ''''), ''-'', ''''), ''/'', ''''), '' '', '''')
      ) STORED
      AFTER cpf',
  'SELECT 1'
);

PREPARE stmt_add_people_cpf_digits FROM @sql_add_people_cpf_digits;
EXECUTE stmt_add_people_cpf_digits;
DEALLOCATE PREPARE stmt_add_people_cpf_digits;

SET @has_idx_people_cpf_digits_deleted := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND INDEX_NAME = 'idx_people_cpf_digits_deleted'
);

SET @sql_add_idx_people_cpf_digits_deleted := IF(
  @has_idx_people_cpf_digits_deleted = 0,
  'ALTER TABLE people
      ADD KEY idx_people_cpf_digits_deleted (cpf_digits, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_add_idx_people_cpf_digits_deleted FROM @sql_add_idx_people_cpf_digits_deleted;
EXECUTE stmt_add_idx_people_cpf_digits_deleted;
DEALLOCATE PREPARE stmt_add_idx_people_cpf_digits_deleted;

-- 2) Fulltext para busca global
SET @has_ft_people_search := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND INDEX_NAME = 'ft_people_search'
);

SET @sql_add_ft_people_search := IF(
  @has_ft_people_search = 0,
  'ALTER TABLE people
      ADD FULLTEXT KEY ft_people_search (name, sei_process_number, tags, notes)',
  'SELECT 1'
);

PREPARE stmt_add_ft_people_search FROM @sql_add_ft_people_search;
EXECUTE stmt_add_ft_people_search;
DEALLOCATE PREPARE stmt_add_ft_people_search;

SET @has_ft_organs_search := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organs'
    AND INDEX_NAME = 'ft_organs_search'
);

SET @sql_add_ft_organs_search := IF(
  @has_ft_organs_search = 0,
  'ALTER TABLE organs
      ADD FULLTEXT KEY ft_organs_search (name, acronym, city, state, notes)',
  'SELECT 1'
);

PREPARE stmt_add_ft_organs_search FROM @sql_add_ft_organs_search;
EXECUTE stmt_add_ft_organs_search;
DEALLOCATE PREPARE stmt_add_ft_organs_search;

SET @has_ft_documents_search := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'documents'
    AND INDEX_NAME = 'ft_documents_search'
);

SET @sql_add_ft_documents_search := IF(
  @has_ft_documents_search = 0,
  'ALTER TABLE documents
      ADD FULLTEXT KEY ft_documents_search (title, reference_sei, tags, original_name, notes)',
  'SELECT 1'
);

PREPARE stmt_add_ft_documents_search FROM @sql_add_ft_documents_search;
EXECUTE stmt_add_ft_documents_search;
DEALLOCATE PREPARE stmt_add_ft_documents_search;

SET @has_ft_process_metadata_search := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'process_metadata'
    AND INDEX_NAME = 'ft_process_metadata_search'
);

SET @sql_add_ft_process_metadata_search := IF(
  @has_ft_process_metadata_search = 0,
  'ALTER TABLE process_metadata
      ADD FULLTEXT KEY ft_process_metadata_search (office_number, office_protocol, dou_edition, dou_link, notes)',
  'SELECT 1'
);

PREPARE stmt_add_ft_process_metadata_search FROM @sql_add_ft_process_metadata_search;
EXECUTE stmt_add_ft_process_metadata_search;
DEALLOCATE PREPARE stmt_add_ft_process_metadata_search;

-- 3) Desnormalizacao de competencia efetiva em reembolsos
SET @has_reimbursement_competence_effective := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND COLUMN_NAME = 'competence_effective'
);

SET @sql_add_reimbursement_competence_effective := IF(
  @has_reimbursement_competence_effective = 0,
  'ALTER TABLE reimbursement_entries
      ADD COLUMN competence_effective DATE
      GENERATED ALWAYS AS (
        COALESCE(reference_month, DATE(paid_at), due_date, DATE(created_at))
      ) STORED
      AFTER reference_month',
  'SELECT 1'
);

PREPARE stmt_add_reimbursement_competence_effective FROM @sql_add_reimbursement_competence_effective;
EXECUTE stmt_add_reimbursement_competence_effective;
DEALLOCATE PREPARE stmt_add_reimbursement_competence_effective;

SET @has_reimbursement_paid_competence_effective := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND COLUMN_NAME = 'paid_competence_effective'
);

SET @sql_add_reimbursement_paid_competence_effective := IF(
  @has_reimbursement_paid_competence_effective = 0,
  'ALTER TABLE reimbursement_entries
      ADD COLUMN paid_competence_effective DATE
      GENERATED ALWAYS AS (
        COALESCE(DATE(paid_at), reference_month, DATE(created_at))
      ) STORED
      AFTER paid_at',
  'SELECT 1'
);

PREPARE stmt_add_reimbursement_paid_competence_effective FROM @sql_add_reimbursement_paid_competence_effective;
EXECUTE stmt_add_reimbursement_paid_competence_effective;
DEALLOCATE PREPARE stmt_add_reimbursement_paid_competence_effective;

SET @has_idx_reimbursement_competence := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND INDEX_NAME = 'idx_reimbursement_comp_status_fin_deleted'
);

SET @sql_add_idx_reimbursement_competence := IF(
  @has_idx_reimbursement_competence = 0,
  'ALTER TABLE reimbursement_entries
      ADD KEY idx_reimbursement_comp_status_fin_deleted (competence_effective, status, financial_nature, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_add_idx_reimbursement_competence FROM @sql_add_idx_reimbursement_competence;
EXECUTE stmt_add_idx_reimbursement_competence;
DEALLOCATE PREPARE stmt_add_idx_reimbursement_competence;

SET @has_idx_reimbursement_paid_competence := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND INDEX_NAME = 'idx_reimbursement_paid_comp_status_fin_deleted'
);

SET @sql_add_idx_reimbursement_paid_competence := IF(
  @has_idx_reimbursement_paid_competence = 0,
  'ALTER TABLE reimbursement_entries
      ADD KEY idx_reimbursement_paid_comp_status_fin_deleted (paid_competence_effective, status, financial_nature, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_add_idx_reimbursement_paid_competence FROM @sql_add_idx_reimbursement_paid_competence;
EXECUTE stmt_add_idx_reimbursement_paid_competence;
DEALLOCATE PREPARE stmt_add_idx_reimbursement_paid_competence;

SET @has_idx_reimbursement_person_competence := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND INDEX_NAME = 'idx_reimbursement_person_comp_deleted'
);

SET @sql_add_idx_reimbursement_person_competence := IF(
  @has_idx_reimbursement_person_competence = 0,
  'ALTER TABLE reimbursement_entries
      ADD KEY idx_reimbursement_person_comp_deleted (person_id, competence_effective, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_add_idx_reimbursement_person_competence FROM @sql_add_idx_reimbursement_person_competence;
EXECUTE stmt_add_idx_reimbursement_person_competence;
DEALLOCATE PREPARE stmt_add_idx_reimbursement_person_competence;

-- 4) Indices compostos para filtros de mes/competencia e pendencias
SET @has_idx_invoices_deleted_organ_ref := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND INDEX_NAME = 'idx_invoices_deleted_organ_reference'
);

SET @sql_add_idx_invoices_deleted_organ_ref := IF(
  @has_idx_invoices_deleted_organ_ref = 0,
  'ALTER TABLE invoices
      ADD KEY idx_invoices_deleted_organ_reference (deleted_at, organ_id, reference_month)',
  'SELECT 1'
);

PREPARE stmt_add_idx_invoices_deleted_organ_ref FROM @sql_add_idx_invoices_deleted_organ_ref;
EXECUTE stmt_add_idx_invoices_deleted_organ_ref;
DEALLOCATE PREPARE stmt_add_idx_invoices_deleted_organ_ref;

SET @has_idx_invoices_deleted_fin_ref := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND INDEX_NAME = 'idx_invoices_deleted_fin_status_ref'
);

SET @sql_add_idx_invoices_deleted_fin_ref := IF(
  @has_idx_invoices_deleted_fin_ref = 0,
  'ALTER TABLE invoices
      ADD KEY idx_invoices_deleted_fin_status_ref (deleted_at, financial_nature, status, reference_month)',
  'SELECT 1'
);

PREPARE stmt_add_idx_invoices_deleted_fin_ref FROM @sql_add_idx_invoices_deleted_fin_ref;
EXECUTE stmt_add_idx_invoices_deleted_fin_ref;
DEALLOCATE PREPARE stmt_add_idx_invoices_deleted_fin_ref;

SET @has_idx_cost_mirrors_deleted_organ_ref := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_mirrors'
    AND INDEX_NAME = 'idx_cost_mirrors_deleted_organ_ref_status'
);

SET @sql_add_idx_cost_mirrors_deleted_organ_ref := IF(
  @has_idx_cost_mirrors_deleted_organ_ref = 0,
  'ALTER TABLE cost_mirrors
      ADD KEY idx_cost_mirrors_deleted_organ_ref_status (deleted_at, organ_id, reference_month, status)',
  'SELECT 1'
);

PREPARE stmt_add_idx_cost_mirrors_deleted_organ_ref FROM @sql_add_idx_cost_mirrors_deleted_organ_ref;
EXECUTE stmt_add_idx_cost_mirrors_deleted_organ_ref;
DEALLOCATE PREPARE stmt_add_idx_cost_mirrors_deleted_organ_ref;

SET @has_idx_payment_batches_deleted_ref_status := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_batches'
    AND INDEX_NAME = 'idx_payment_batches_deleted_ref_status_fin'
);

SET @sql_add_idx_payment_batches_deleted_ref_status := IF(
  @has_idx_payment_batches_deleted_ref_status = 0,
  'ALTER TABLE payment_batches
      ADD KEY idx_payment_batches_deleted_ref_status_fin (deleted_at, reference_month, status, financial_nature)',
  'SELECT 1'
);

PREPARE stmt_add_idx_payment_batches_deleted_ref_status FROM @sql_add_idx_payment_batches_deleted_ref_status;
EXECUTE stmt_add_idx_payment_batches_deleted_ref_status;
DEALLOCATE PREPARE stmt_add_idx_payment_batches_deleted_ref_status;

SET @has_idx_payments_deleted_fin_date := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_deleted_fin_date_invoice'
);

SET @sql_add_idx_payments_deleted_fin_date := IF(
  @has_idx_payments_deleted_fin_date = 0,
  'ALTER TABLE payments
      ADD KEY idx_payments_deleted_fin_date_invoice (deleted_at, financial_nature, payment_date, invoice_id)',
  'SELECT 1'
);

PREPARE stmt_add_idx_payments_deleted_fin_date FROM @sql_add_idx_payments_deleted_fin_date;
EXECUTE stmt_add_idx_payments_deleted_fin_date;
DEALLOCATE PREPARE stmt_add_idx_payments_deleted_fin_date;

SET @has_idx_pending_status_assigned_assignment := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'analyst_pending_items'
    AND INDEX_NAME = 'idx_pending_status_assigned_assignment'
);

SET @sql_add_idx_pending_status_assigned_assignment := IF(
  @has_idx_pending_status_assigned_assignment = 0,
  'ALTER TABLE analyst_pending_items
      ADD KEY idx_pending_status_assigned_assignment (status, deleted_at, assigned_user_id, assignment_id)',
  'SELECT 1'
);

PREPARE stmt_add_idx_pending_status_assigned_assignment FROM @sql_add_idx_pending_status_assigned_assignment;
EXECUTE stmt_add_idx_pending_status_assigned_assignment;
DEALLOCATE PREPARE stmt_add_idx_pending_status_assigned_assignment;

-- 5) Snapshot mensal para painel financeiro
CREATE TABLE IF NOT EXISTS financial_monthly_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snapshot_year SMALLINT UNSIGNED NOT NULL,
  snapshot_month TINYINT UNSIGNED NOT NULL,
  organ_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  financial_nature VARCHAR(30) NOT NULL DEFAULT 'despesa_reembolso',
  forecast_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  effective_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  payable_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  open_count INT UNSIGNED NOT NULL DEFAULT 0,
  open_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  overdue_count INT UNSIGNED NOT NULL DEFAULT 0,
  overdue_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_count INT UNSIGNED NOT NULL DEFAULT 0,
  paid_status_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  reconciled_count INT UNSIGNED NOT NULL DEFAULT 0,
  reconciled_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  refreshed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_financial_monthly_snapshots_scope (snapshot_year, snapshot_month, organ_id, financial_nature),
  KEY idx_financial_monthly_snapshots_scope_year (organ_id, financial_nature, snapshot_year, snapshot_month),
  KEY idx_financial_monthly_snapshots_refreshed_at (refreshed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Escopo de orgao na auditoria
SET @has_audit_scope_organ_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_log'
    AND COLUMN_NAME = 'scope_organ_id'
);

SET @sql_add_audit_scope_organ_id := IF(
  @has_audit_scope_organ_id = 0,
  'ALTER TABLE audit_log
      ADD COLUMN scope_organ_id BIGINT UNSIGNED NULL AFTER entity_id',
  'SELECT 1'
);

PREPARE stmt_add_audit_scope_organ_id FROM @sql_add_audit_scope_organ_id;
EXECUTE stmt_add_audit_scope_organ_id;
DEALLOCATE PREPARE stmt_add_audit_scope_organ_id;

SET @has_idx_audit_scope_created := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_log'
    AND INDEX_NAME = 'idx_audit_scope_created'
);

SET @sql_add_idx_audit_scope_created := IF(
  @has_idx_audit_scope_created = 0,
  'ALTER TABLE audit_log
      ADD KEY idx_audit_scope_created (scope_organ_id, created_at, id)',
  'SELECT 1'
);

PREPARE stmt_add_idx_audit_scope_created FROM @sql_add_idx_audit_scope_created;
EXECUTE stmt_add_idx_audit_scope_created;
DEALLOCATE PREPARE stmt_add_idx_audit_scope_created;

SET @has_idx_audit_scope_action_created := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_log'
    AND INDEX_NAME = 'idx_audit_scope_action_created'
);

SET @sql_add_idx_audit_scope_action_created := IF(
  @has_idx_audit_scope_action_created = 0,
  'ALTER TABLE audit_log
      ADD KEY idx_audit_scope_action_created (scope_organ_id, action, created_at)',
  'SELECT 1'
);

PREPARE stmt_add_idx_audit_scope_action_created FROM @sql_add_idx_audit_scope_action_created;
EXECUTE stmt_add_idx_audit_scope_action_created;
DEALLOCATE PREPARE stmt_add_idx_audit_scope_action_created;

UPDATE audit_log a
INNER JOIN organs o ON o.id = a.entity_id
SET a.scope_organ_id = o.id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'organ';

UPDATE audit_log a
INNER JOIN people p ON p.id = a.entity_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'person';

UPDATE audit_log a
INNER JOIN assignments ass ON ass.id = a.entity_id
INNER JOIN people p ON p.id = ass.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity IN ('assignment', 'assignment_checklist');

UPDATE audit_log a
INNER JOIN assignment_checklist_items aci ON aci.id = a.entity_id
INNER JOIN assignments ass ON ass.id = aci.assignment_id
INNER JOIN people p ON p.id = ass.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'assignment_checklist_item';

UPDATE audit_log a
INNER JOIN timeline_events te ON te.id = a.entity_id
INNER JOIN people p ON p.id = te.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'timeline_event';

UPDATE audit_log a
INNER JOIN timeline_event_attachments ta ON ta.id = a.entity_id
INNER JOIN people p ON p.id = ta.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'timeline_attachment';

UPDATE audit_log a
INNER JOIN documents d ON d.id = a.entity_id
INNER JOIN people p ON p.id = d.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'document';

UPDATE audit_log a
INNER JOIN process_metadata pm ON pm.id = a.entity_id
INNER JOIN people p ON p.id = pm.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'process_metadata';

UPDATE audit_log a
INNER JOIN cost_plans cp ON cp.id = a.entity_id
INNER JOIN people p ON p.id = cp.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_plan';

UPDATE audit_log a
INNER JOIN cost_plan_items cpi ON cpi.id = a.entity_id
INNER JOIN people p ON p.id = cpi.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_plan_item';

UPDATE audit_log a
INNER JOIN reimbursement_entries re ON re.id = a.entity_id
INNER JOIN people p ON p.id = re.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'reimbursement_entry';

UPDATE audit_log a
INNER JOIN process_comments pc ON pc.id = a.entity_id
INNER JOIN people p ON p.id = pc.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'process_comment';

UPDATE audit_log a
INNER JOIN process_admin_timeline_notes patn ON patn.id = a.entity_id
INNER JOIN people p ON p.id = patn.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'process_admin_timeline_note';

UPDATE audit_log a
INNER JOIN analyst_pending_items api ON api.id = a.entity_id
INNER JOIN people p ON p.id = api.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'analyst_pending_item';

UPDATE audit_log a
INNER JOIN invoices i ON i.id = a.entity_id
SET a.scope_organ_id = i.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'invoice';

UPDATE audit_log a
INNER JOIN invoice_people ip ON ip.id = a.entity_id
INNER JOIN invoices i ON i.id = ip.invoice_id
SET a.scope_organ_id = i.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'invoice_person';

UPDATE audit_log a
INNER JOIN payments pmt ON pmt.id = a.entity_id
INNER JOIN invoices i ON i.id = pmt.invoice_id
SET a.scope_organ_id = i.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'payment';

UPDATE audit_log a
INNER JOIN (
  SELECT
    pbi.batch_id,
    MIN(i.organ_id) AS scope_organ_id
  FROM payment_batch_items pbi
  INNER JOIN invoices i ON i.id = pbi.invoice_id
  GROUP BY pbi.batch_id
  HAVING COUNT(DISTINCT i.organ_id) = 1
) pb_scope ON pb_scope.batch_id = a.entity_id
SET a.scope_organ_id = pb_scope.scope_organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'payment_batch';

UPDATE audit_log a
INNER JOIN cost_mirrors cm ON cm.id = a.entity_id
SET a.scope_organ_id = cm.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_mirror';

UPDATE audit_log a
INNER JOIN cost_mirror_items cmi ON cmi.id = a.entity_id
INNER JOIN cost_mirrors cm ON cm.id = cmi.cost_mirror_id
SET a.scope_organ_id = cm.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_mirror_item';

UPDATE audit_log a
INNER JOIN cost_mirror_reconciliations cmr ON cmr.id = a.entity_id
INNER JOIN cost_mirrors cm ON cm.id = cmr.cost_mirror_id
SET a.scope_organ_id = cm.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_mirror_reconciliation';

UPDATE audit_log a
INNER JOIN cost_mirror_divergences cmd ON cmd.id = a.entity_id
INNER JOIN cost_mirrors cm ON cm.id = cmd.cost_mirror_id
SET a.scope_organ_id = cm.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cost_mirror_divergence';

UPDATE audit_log a
INNER JOIN cdo_people cp ON cp.id = a.entity_id
INNER JOIN people p ON p.id = cp.person_id
SET a.scope_organ_id = p.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'cdo_person';

UPDATE audit_log a
INNER JOIN hiring_scenarios hs ON hs.id = a.entity_id
SET a.scope_organ_id = hs.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'hiring_scenario'
  AND hs.organ_id IS NOT NULL;

UPDATE audit_log a
INNER JOIN budget_scenario_parameters bsp ON bsp.id = a.entity_id
SET a.scope_organ_id = bsp.organ_id
WHERE a.scope_organ_id IS NULL
  AND a.entity = 'budget_scenario_parameter'
  AND bsp.organ_id IS NOT NULL;

SET @has_fk_audit_scope_organ := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_audit_scope_organ'
);

SET @sql_add_fk_audit_scope_organ := IF(
  @has_fk_audit_scope_organ = 0,
  'ALTER TABLE audit_log
      ADD CONSTRAINT fk_audit_scope_organ
      FOREIGN KEY (scope_organ_id) REFERENCES organs(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_add_fk_audit_scope_organ FROM @sql_add_fk_audit_scope_organ;
EXECUTE stmt_add_fk_audit_scope_organ;
DEALLOCATE PREPARE stmt_add_fk_audit_scope_organ;
