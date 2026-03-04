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
- `DashboardService`: consolidacao de metricas operacionais (pessoas, cobertura de dossie/custos e movimentacao recente)
- `AuditService`: trilha de auditoria
- `EventService`: eventos de sistema
- `PipelineService`: movimentacao/status/timeline da pessoa
- `DocumentService`: dossie documental, upload seguro e download protegido
- `CostPlanService`: custos previstos por pessoa com versionamento e comparacao entre versoes
- `ReimbursementService`: controle de reembolso real (boletos/pagamentos) com baixa financeira
- `ReconciliationService`: conciliacao previsto x real por pessoa e por competencia
- `CdoService`: cadastro de CDO, vinculo de pessoas, totalizador/alocacao e bloqueio por saldo
- `InvoiceService`: boletos estruturados por orgao/competencia, PDF/metadados, rateio por pessoa e baixa financeira com comprovante
- `CostMirrorService`: espelho de custo detalhado por pessoa/competencia, cadastro manual de itens e importacao CSV
- `CostMirrorReconciliationService`: conciliacao item a item (previsto x espelho), divergencias e aprovacao com bloqueio
- `BudgetService`: dashboard orcamentario anual, projecoes mensal/anual, gap mensal por risco, ranking de ofensores, alertas ativos, parametros por orgao/cargo/setor/modalidade e simulador multiparametrico de contratacao
- `OfficeTemplateService`: templates versionados de oficio com merge de variaveis e geracao de documento HTML
- `PersonAuditService`: trilha auditavel por pessoa com filtros e exportacao CSV

## Modelo de dados (resumo)
Tabelas principais:
- `users`, `roles`, `permissions`, `role_permissions`, `user_roles`
- `organs`, `people`, `assignments`, `assignment_statuses`
- `timeline_events`, `timeline_event_attachments`, `documents`
- `cost_plans`, `cost_plan_items`, `reimbursement_entries`
- `cdos`, `cdo_people`
- `invoices`, `invoice_people`, `payments`, `payment_people`
- `cost_mirrors`, `cost_mirror_items`, `cost_mirror_reconciliations`, `cost_mirror_divergences`
- `budget_cycles`, `org_cost_parameters`, `budget_scenario_parameters`, `hiring_scenarios`, `hiring_scenario_items`
- `office_templates`, `office_template_versions`, `office_documents`
- `audit_log`, `system_events`, `migrations`

## Compatibilidade operacional
- PHP 8.1+
- MySQL/Percona 5.7+
- Sem workers/daemons obrigatorios
