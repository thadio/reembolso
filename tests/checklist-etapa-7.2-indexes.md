# Checklist de Testes - Etapa 7.2 (Indices de alto volume)

## Pre-condicoes
- [ ] Banco com migrations aplicadas ate `014_phase7_performance_indexes.sql`
- [ ] Base com volume suficiente para avaliar `EXPLAIN` (homologacao/producao espelhada)

## Validacao de estrutura
- [ ] `SHOW INDEX FROM people` contem `idx_people_deleted_status_organ_modality` e `idx_people_deleted_created_at`
- [ ] `SHOW INDEX FROM timeline_events` contem `idx_timeline_event_date_id` e `idx_timeline_person_event_date_id`
- [ ] `SHOW INDEX FROM documents` contem `idx_documents_deleted_person`
- [ ] `SHOW INDEX FROM cost_plans` contem `idx_cost_plans_deleted_active_person`
- [ ] `SHOW INDEX FROM cost_plan_items` contem `idx_cost_items_plan_deleted_type_period`
- [ ] `SHOW INDEX FROM reimbursement_entries` contem `idx_reimbursement_deleted_status_reference` e `idx_reimbursement_person_deleted_status_due`
- [ ] `SHOW INDEX FROM audit_log` contem `idx_audit_created_at_id` e `idx_audit_entity_entity_id_created`
- [ ] `SHOW INDEX FROM cdos` contem `idx_cdos_deleted_status`
- [ ] `SHOW INDEX FROM cdo_people` contem `idx_cdo_people_cdo_deleted`

## Validacao de plano de execucao (EXPLAIN)
- [ ] Query de resumo do dashboard com `people` usa indice composto com `deleted_at/status`
- [ ] Query de timeline recente usa indice por `event_date`
- [ ] Query de auditoria paginada usa indice por `created_at` e/ou `entity/entity_id`
- [ ] Query de reembolso por pessoa usa indice por `person_id/deleted_at/status`

## Regressao funcional
- [ ] Dashboard abre sem erro
- [ ] Perfil 360 (auditoria) pagina sem erro
- [ ] Listagem de pessoas continua com filtros funcionais
