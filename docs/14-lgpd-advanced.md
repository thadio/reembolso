# 14 - LGPD Avancado

Guia do modulo da fase 6.2: trilha de acesso sensivel, relatorio e politicas de retencao/anonimizacao.

## Escopo
- Registro de visualizacao de CPF completo e acessos a documentos sensiveis.
- Relatorio consolidado de acessos sensiveis em `/lgpd`.
- Exportacao CSV de acessos via `GET /lgpd/export/access-csv`.
- Politicas parametrizaveis de retencao e anonimizacao.
- Execucao manual de rotina LGPD (preview/apply) via UI.

## Estrutura de dados
Migration: `db/migrations/026_phase6_lgpd_advanced.sql`

Tabelas:
- `sensitive_access_logs`
- `lgpd_retention_policies`
- `lgpd_retention_runs`

Permissoes:
- `lgpd.view`
- `lgpd.manage`

## Politicas padrao
- `sensitive_access_logs`: retencao de trilhas de acesso sensivel.
- `audit_log`: retencao de trilha geral de auditoria.
- `people_soft_deleted`: anonimizacao de pessoas em soft delete apos prazo.
- `users_soft_deleted`: anonimizacao de usuarios em soft delete apos prazo.

## Operacao
1. Ajustar politicas no painel `/lgpd`.
2. Executar `preview` para validar volume de candidatos.
3. Executar `apply` para aplicar retencao/anonimizacao.
4. Acompanhar historico em `lgpd_retention_runs`.

## Observacoes
- A trilha LGPD nao interrompe o fluxo principal caso o registro falhe.
- A anonimizacao atua em registros com `deleted_at` (soft delete), preservando consistencia relacional.
- Exportacoes CSV devem respeitar permissao `lgpd.view`.
