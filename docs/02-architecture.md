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
- `DashboardService`: consolidacao de metricas operacionais e painel executivo (gargalos por etapa + ranking de orgaos por criticidade SLA), com reaproveitamento de snapshot KPI quando fresco
- `AuditService`: trilha de auditoria
- `EventService`: eventos de sistema
- `OrganService`: CRUD de orgaos de origem e importacao CSV em massa com validacao/simulacao e rollback transacional
- `PipelineService`: movimentacao/status/timeline da pessoa, fila por responsavel/prioridade e checklist automatico por tipo de caso
- `DocumentService`: dossie documental, upload seguro, controle de versoes e download protegido (documento atual ou historico)
- `DocumentIntelligenceService`: conferencia assistida por IA no Perfil 360 (extracao de campos documentais, deteccao de inconsistencias/anomalias e sugestoes de justificativa para divergencias recorrentes)
- `CostPlanService`: custos previstos por pessoa com versionamento e comparacao entre versoes
- `ReimbursementService`: controle de reembolso real (boletos/pagamentos) com baixa financeira, calculadora automatica e memoria de calculo por lancamento
- `ReconciliationService`: conciliacao previsto x real por pessoa e por competencia
- `CdoService`: cadastro de CDO, vinculo de pessoas, totalizador/alocacao e bloqueio por saldo
- `InvoiceService`: boletos estruturados por orgao/competencia, PDF/metadados, rateio por pessoa, baixa financeira com comprovante, gestao de lotes de pagamento e simulacao previa obrigatoria para aprovacao final
- `CostMirrorService`: espelho de custo detalhado por pessoa/competencia, cadastro manual de itens e importacao CSV
- `CostMirrorReconciliationService`: conciliacao item a item (previsto x espelho), divergencias e aprovacao com bloqueio
- `BudgetService`: dashboard orcamentario anual, projecoes mensal/anual, gap mensal por risco, ranking de ofensores, alertas ativos, parametros por orgao/cargo/setor/modalidade e simulador multiparametrico de contratacao
- `ReportService`: relatorios premium (operacional + financeiro), filtros por periodo/orgao/SLA, painel financeiro por status (abertos/vencidos/pagos/conciliados) e exportacoes CSV/PDF/ZIP de prestacao
- `OpsHealthPanelService`: painel web de observabilidade operacional com leitura de snapshots de saude tecnica (`ops-health-panel`), severidade de logs e recorrencias
- `OfficeTemplateService`: templates versionados de oficio com merge de variaveis e geracao de documento (HTML print + PDF nativo)
- `ProcessMetadataService`: metadados formais de processo (oficio, DOU e entrada oficial no MTE) com validacoes, upload/download de anexo e trilha auditavel
- `SlaAlertService`: regras de SLA por etapa, painel de pendencias (no prazo/em risco/vencido), controle de atraso por caso vencido e disparo opcional de notificacoes por email
- `PersonAuditService`: trilha auditavel por pessoa com filtros e exportacao CSV
- `PersonDossierExportService`: exportacao completa de dossie por pessoa (ZIP com CSVs, PDF de sintese, trilha e anexos)
- `OrganAuditService`: historico consolidado por orgao (orgao + pessoas vinculadas + entidades relacionadas) com filtros, paginacao e exportacao CSV
- `PendingCenterService`: central de pendencias operacionais do analista (documentos, divergencias e retornos) com sincronizacao automatica e status por item
- `ProcessCommentService`: comentarios internos por processo com edicao, arquivamento/fixacao e exclusao logica, incluindo trilha de auditoria/eventos
- `ProcessAdminTimelineService`: timeline administrativa consolidada por processo (fontes operacionais/financeiras + notas manuais com filtros, KPI, paginacao e trilha auditavel)
- `GlobalSearchService`: busca global unificada (CPF/SEI/DOU/orgao/documento) com resultados cross-modulo de pessoas, orgaos, metadados formais e dossie documental

## Modelo de dados (resumo)
Tabelas principais:
- `users`, `roles`, `permissions`, `role_permissions`, `user_roles`
- `login_attempts`, `security_settings`
- `organs`, `people`, `assignments`, `assignment_statuses`
- `assignment_checklist_templates`, `assignment_checklist_items`
- `process_comments`
- `process_admin_timeline_notes`
- `analyst_pending_items`
- `timeline_events`, `timeline_event_attachments`, `documents`, `document_versions`, `document_ai_reviews`, `document_ai_extractions`, `document_ai_findings`
- `cost_plans`, `cost_plan_items`, `reimbursement_entries` (inclui `calculation_memory` para rastreabilidade da formula aplicada)
- `cdos`, `cdo_people`
- `invoices`, `invoice_people`, `payments`, `payment_people`, `payment_batches`, `payment_batch_items`
- `cost_mirrors`, `cost_mirror_items`, `cost_mirror_reconciliations`, `cost_mirror_divergences`
- `budget_cycles`, `org_cost_parameters`, `budget_scenario_parameters`, `hiring_scenarios`, `hiring_scenario_items`
- `office_templates`, `office_template_versions`, `office_documents`
- `process_metadata`
- `sla_rules`, `sla_notification_logs`, `sla_case_controls`
- `sensitive_access_logs`, `lgpd_retention_policies`, `lgpd_retention_runs`
- `audit_log`, `system_events`, `migrations`

## Compatibilidade operacional
- PHP 8.1+
- MySQL/Percona 5.7+
- Sem workers/daemons obrigatorios
