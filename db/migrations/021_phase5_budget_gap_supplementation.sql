-- Fase 5.2 (parcial) - Gap orcamentario e suplementacao
-- Suporte a simulacao de entrada/saida no historico de cenarios.

ALTER TABLE hiring_scenarios
  ADD COLUMN movement_type VARCHAR(20) NOT NULL DEFAULT 'entrada' AFTER modality,
  ADD KEY idx_hiring_scenarios_movement_type (movement_type);
