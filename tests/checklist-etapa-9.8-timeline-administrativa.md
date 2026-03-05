# Checklist de Testes - Ciclo 9.8 (Timeline administrativa completa por processo)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `034_phase6_process_admin_timeline.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para criar/editar/excluir notas administrativas

## Perfil 360 (UI)
- [ ] Card `Timeline administrativa completa` aparece em `GET /people/show?id={id}`
- [ ] KPIs (`Total`, `Abertos`, `Concluidos`, `Notas manuais`, `Entradas automaticas`) refletem o consolidado
- [ ] Filtros por busca, origem e status funcionam e mantem estado na paginacao
- [ ] Lista paginada exibe badges de origem/status/severidade, ator e data/hora do evento

## Consolidacao de fontes
- [ ] Timeline administrativa consolida notas manuais (`process_admin_timeline_notes`)
- [ ] Timeline administrativa consolida comentarios internos (`process_comments`)
- [ ] Timeline administrativa consolida pendencias operacionais (`analyst_pending_items`)
- [ ] Timeline administrativa consolida financeiro de reembolso (`reimbursement_entries`)
- [ ] Timeline administrativa consolida metadados formais (`process_metadata`) e timeline operacional (`timeline_events`)

## CRUD de nota manual
- [ ] `POST /people/process-admin-timeline/store` cria nota com `status`, `severity`, `event_at` e `is_pinned`
- [ ] `POST /people/process-admin-timeline/update` altera titulo/descricao/status/severidade/fixacao/data
- [ ] `POST /people/process-admin-timeline/update` gera transicao `status.update` ao trocar status
- [ ] `POST /people/process-admin-timeline/delete` realiza exclusao logica (`deleted_at`)
- [ ] Nota excluida deixa de aparecer na timeline administrativa

## Persistencia e trilha
- [ ] Tabela `process_admin_timeline_notes` persiste `person_id`, `assignment_id`, `status`, `severity`, `is_pinned`, `created_by`, `updated_by`, `deleted_by` e `deleted_at`
- [ ] `audit_log` registra `process_admin_timeline_note:create`, `update`, `status.update` e `delete`
- [ ] `system_events` registra `process_admin_timeline.note_created`, `note_updated`, `note_status_updated` e `note_deleted`
- [ ] Secao Auditoria do Perfil 360 exibe entidade `process_admin_timeline_note`

## Seguranca e regressao
- [ ] Endpoints `POST /people/process-admin-timeline/store|update|delete` exigem `people.manage` e CSRF valido
- [ ] Usuario sem `people.manage` recebe `403` nos endpoints da timeline administrativa
- [ ] Blocos existentes (pipeline, timeline, dossie, custos, reembolsos, comentarios internos e auditoria) permanecem funcionais
