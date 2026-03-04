-- Fase 5.4 (complemento) - Parametrizacao por cargo/setor e alertas ativos

ALTER TABLE org_cost_parameters
  DROP INDEX uq_org_cost_parameters_organ,
  ADD COLUMN cargo VARCHAR(120) NOT NULL DEFAULT '' AFTER organ_id,
  ADD COLUMN setor VARCHAR(120) NOT NULL DEFAULT '' AFTER cargo,
  ADD KEY idx_org_cost_parameters_cargo_setor (cargo, setor),
  ADD UNIQUE KEY uq_org_cost_parameters_scope (organ_id, cargo, setor);

ALTER TABLE hiring_scenarios
  ADD COLUMN cargo VARCHAR(120) NOT NULL DEFAULT '' AFTER movement_type,
  ADD COLUMN setor VARCHAR(120) NOT NULL DEFAULT '' AFTER cargo,
  ADD KEY idx_hiring_scenarios_cargo_setor (cargo, setor);
