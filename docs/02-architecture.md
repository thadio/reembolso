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
- `AuditService`: trilha de auditoria
- `EventService`: eventos de sistema
- `PipelineService`: movimentacao/status/timeline da pessoa
- `DocumentService`: dossie documental, upload seguro e download protegido
- `CostPlanService`: custos previstos por pessoa com versionamento e comparacao entre versoes

## Modelo de dados (resumo)
Tabelas principais:
- `users`, `roles`, `permissions`, `role_permissions`, `user_roles`
- `organs`, `people`, `assignments`, `assignment_statuses`
- `timeline_events`, `timeline_event_attachments`, `documents`
- `cost_plans`, `cost_plan_items`
- `audit_log`, `system_events`, `migrations`

## Compatibilidade operacional
- PHP 8.1+
- MySQL/Percona 5.7+
- Sem workers/daemons obrigatorios
