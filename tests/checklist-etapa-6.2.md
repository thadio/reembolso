# Checklist de Testes - Fase 6.2 (LGPD avancado)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `026_phase6_lgpd_advanced.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `lgpd.view` para consultar o painel
- [ ] Usuario com permissao `lgpd.manage` para editar politicas e executar rotina

## Trilha de acesso sensivel
- [ ] Visualizacao de CPF completo em `GET /people` grava `cpf_view_list` em `sensitive_access_logs`
- [ ] Visualizacao de CPF completo em `GET /people/show?id={id}` grava `cpf_view_profile`
- [ ] `GET /people/documents/download` grava `document_download`
- [ ] `GET /people/timeline/attachment` grava `timeline_attachment_download`
- [ ] `GET /invoices/payments/proof` grava `payment_proof_download`
- [ ] `GET /process-meta/dou-attachment` grava `process_meta_attachment_download`

## Relatorio de acesso sensivel
- [ ] `GET /lgpd` abre painel com KPIs e listagem paginada
- [ ] Filtros por acao/sensibilidade/usuario/periodo funcionam
- [ ] `GET /lgpd/export/access-csv` exporta CSV coerente com filtros aplicados

## Politicas de retencao e anonimizacao
- [ ] `POST /lgpd/policies/upsert` atualiza `retention_days` e `is_active`
- [ ] Politicas com anonimizacao aceitam `anonymize_after_days`
- [ ] Politicas sem anonimizacao ignoram `anonymize_after_days`

## Execucao de rotina LGPD
- [ ] `POST /lgpd/retention/run` com `mode=preview` gera linha em `lgpd_retention_runs`
- [ ] `POST /lgpd/retention/run` com `mode=apply` aplica retencao/anonimizacao e registra resumo
- [ ] Historico de execucoes aparece no painel `/lgpd`

## Controle de acesso
- [ ] Usuario sem `lgpd.view` recebe `403` em `GET /lgpd`
- [ ] Usuario sem `lgpd.manage` recebe `403` nos `POST /lgpd/policies/upsert` e `/lgpd/retention/run`

## Auditoria e eventos
- [ ] `audit_log` registra `lgpd_policy:upsert`
- [ ] `audit_log` registra `lgpd_retention:run_preview` e `lgpd_retention:run_apply`
- [ ] `audit_log` registra `lgpd_access:export_csv`
- [ ] `system_events` registra `lgpd.policy_upserted`, `lgpd.retention_executed` e `lgpd.access_exported_csv`
