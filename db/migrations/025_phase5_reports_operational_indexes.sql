-- Fase 5.3 - Indices para relatorios operacionais
-- Suporta filtro por janela em assignments.updated_at sem funcao na coluna.

ALTER TABLE assignments
  ADD KEY idx_assignments_deleted_updated_status_person (
    deleted_at,
    updated_at,
    current_status_id,
    person_id
  );
