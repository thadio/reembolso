# 02 - Architecture

## Estilo
Arquitetura MVC leve, sem framework, com separacao por camadas:
- `app/Controllers`
- `app/Services`
- `app/Repositories`
- `app/Core`

## Fluxo de requisicao
1. `public/index.php` carrega `bootstrap.php`.
2. `routes/web.php` registra as rotas.
3. `Router` aplica middlewares (`auth`, `guest`, `csrf`, `permission:*`).
4. Controllers acionam Services/Repositories.
5. Views renderizam HTML.

## Componentes centrais
- `Config`: carrega variaveis de ambiente
- `Database`: conexao PDO
- `Auth`: autenticacao, rate limit e permissoes
- `UserAdminService`: administracao de usuarios (CRUD, papeis/permissoes via UI, ativacao/desativacao e fluxo de senha)
- `SecuritySettingsService`: politica de senha/expiracao, lockout de login configuravel e limite global de upload
- `LgpdService`: trilha de acesso sensivel (CPF/documentos), relatorio de acesso e politicas de retencao/anonimizacao
- `DashboardService`: consolidacao de metricas operacionais (pessoas, cobertura de dossie/custos e movimentacao recente), com reaproveitamento de snapshot KPI quando fresco
- `AuditService`: trilha de auditoria
- `EventService`: eventos de sistema
- `PipelineService`: movimentacao/status/timeline da pessoa, fila por responsavel/prioridade e checklist automatico por tipo de caso
- `DocumentService`: dossie documental, upload seguro e download protegido
- `CostPlanService`: custos previstos por pessoa com versionamento e comparacao entre versoes
- `ReimbursementService`: controle de reembolso real (boletos/pagamentos) com baixa financeira
- `ReconciliationService`: conciliacao previsto x real por pessoa e por competencia
- `CdoService`: cadastro de CDO, vinculo de pessoas, totalizador/alocacao e bloqueio por saldo
- `InvoiceService`: boletos estruturados por orgao/competencia, PDF/metadados, rateio por pessoa e baixa financeira com comprovante
- `CostMirrorService`: espelho de custo detalhado por pessoa/competencia, cadastro manual de itens e importacao CSV
- `CostMirrorReconciliationService`: conciliacao item a item (previsto x espelho), divergencias e aprovacao com bloqueio
- `BudgetService`: dashboard orcamentario anual, projecoes mensal/anual, gap mensal por risco, ranking de ofensores, alertas ativos, parametros por orgao/cargo/setor/modalidade e simulador multiparametrico de contratacao
- `ReportService`: relatorios premium (operacional + financeiro), filtros por periodo/orgao/SLA e exportacoes CSV/PDF/ZIP de prestacao
- `OfficeTemplateService`: templates versionados de oficio com merge de variaveis e geracao de documento (HTML print + PDF nativo)
- `ProcessMetadataService`: metadados formais de processo (oficio, DOU e entrada oficial no MTE) com validacoes, upload/download de anexo e trilha auditavel
- `SlaAlertService`: regras de SLA por etapa, painel de pendencias (no prazo/em risco/vencido) e disparo opcional de notificacoes por email
- `PersonAuditService`: trilha auditavel por pessoa com filtros e exportacao CSV

## Modelo de dados (resumo)
Tabelas principais:
- `users`, `roles`, `permissions`, `role_permissions`, `user_roles`
- `login_attempts`, `security_settings`
- `organs`, `people`, `assignments`, `assignment_statuses`
- `assignment_checklist_templates`, `assignment_checklist_items`
- `timeline_events`, `timeline_event_attachments`, `documents`
- `cost_plans`, `cost_plan_items`, `reimbursement_entries`
- `cdos`, `cdo_people`
- `invoices`, `invoice_people`, `payments`, `payment_people`
- `cost_mirrors`, `cost_mirror_items`, `cost_mirror_reconciliations`, `cost_mirror_divergences`
- `budget_cycles`, `org_cost_parameters`, `budget_scenario_parameters`, `hiring_scenarios`, `hiring_scenario_items`
- `office_templates`, `office_template_versions`, `office_documents`
- `process_metadata`
- `sla_rules`, `sla_notification_logs`
- `sensitive_access_logs`, `lgpd_retention_policies`, `lgpd_retention_runs`
- `audit_log`, `system_events`, `migrations`

## Compatibilidade operacional
- PHP 8.1+
- MySQL/Percona 5.7+
- Sem workers/daemons obrigatorios
