-- Fase 7.2 - Indices adicionais para filtros de alto volume
-- Otimizacao de consultas de dashboard, auditoria e listagens principais.

ALTER TABLE people
  ADD KEY idx_people_deleted_status_organ_modality (deleted_at, status, organ_id, desired_modality_id),
  ADD KEY idx_people_deleted_created_at (deleted_at, created_at);

ALTER TABLE timeline_events
  ADD KEY idx_timeline_event_date_id (event_date, id),
  ADD KEY idx_timeline_person_event_date_id (person_id, event_date, id);

ALTER TABLE documents
  ADD KEY idx_documents_deleted_person (deleted_at, person_id);

ALTER TABLE cost_plans
  ADD KEY idx_cost_plans_deleted_active_person (deleted_at, is_active, person_id);

ALTER TABLE cost_plan_items
  ADD KEY idx_cost_items_plan_deleted_type_period (cost_plan_id, deleted_at, cost_type, start_date, end_date);

ALTER TABLE reimbursement_entries
  ADD KEY idx_reimbursement_deleted_status_reference (deleted_at, status, reference_month),
  ADD KEY idx_reimbursement_person_deleted_status_due (person_id, deleted_at, status, due_date);

ALTER TABLE audit_log
  ADD KEY idx_audit_created_at_id (created_at, id),
  ADD KEY idx_audit_entity_entity_id_created (entity, entity_id, created_at);

ALTER TABLE cdos
  ADD KEY idx_cdos_deleted_status (deleted_at, status);

ALTER TABLE cdo_people
  ADD KEY idx_cdo_people_cdo_deleted (cdo_id, deleted_at);
