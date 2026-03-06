# Reembolso

Aplicacao web em PHP para gestao de movimentacao de forca de trabalho, pipeline de pessoas, timeline e reembolsos.

## Estado atual
- Fases implementadas: 0, 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 5.1, 5.2, 5.3, 5.4 (MVP de orcamento/capacidade), 6.1 (admin de usuarios/acessos), 6.2 (LGPD avancado), 6.3 (seguranca reforcada), 7.1 (backup/restore), 7.2 (performance com indices + snapshot KPI + otimizacao do dashboard), 7.3 (qualidade com suites unitaria/integracao e regressao fixa QA), 7.4 (observabilidade com painel tecnico, severidade de logs e revisao recorrente), 9.1 (compliance documental RF-22 com sensibilidade e permissao granular), 9.2 (importacao CSV em massa de pessoas com validacao/rollback), 9.3 (painel de fila por responsavel/prioridade no pipeline), 9.4 (checklist automatico por tipo de caso no Perfil 360), 9.5 (central de pendencias operacionais no pipeline), 9.6 (calculadora automatica de reembolso com memoria de calculo), 9.7 (comentarios internos por processo), 9.8 (timeline administrativa completa por processo), 9.9 (historico consolidado de pessoa e orgao), 9.10 (relatorios de auditoria CGU/TCU + exportacao completa de dossie ZIP/PDF+trilha), 9.11 (controle de versao de documentos no Perfil 360), 9.12 (painel executivo com gargalos e ranking de orgaos), 9.13 (controle de SLA e casos em atraso), 9.14 (gestao de lotes de pagamento), 9.15 (busca global unificada), 9.16 (simulacao previa para aprovacao final de lotes), 9.17 (importacao CSV em massa de orgaos com validacao/rollback), 9.18 (painel financeiro por status completo + observabilidade estruturada via UI), 9.19 (conferencia assistida por IA) e 9.20 (classificacao institucional de orgaos + importacao a partir de markdown)
- Stack: PHP 8.1+, MySQL/Percona 5.7+, Apache (shared hosting compativel)
- Deploy alvo: execucao via bash no servidor
- Modulos ativos: dashboard operacional com metricas reais e painel executivo (gargalos + ranking de orgaos por criticidade de SLA), pipeline de pessoas, timeline completa, dossie documental com upload/download seguro e controle de versoes por documento, custos previstos com versionamento, financeiro real de reembolsos (boletos/pagamentos) com calculadora automatica e memoria de calculo por lancamento, comentarios internos por processo (edicao, arquivamento/fixacao e exclusao logica), timeline administrativa completa por processo (consolidacao de fontes + notas manuais com filtros e KPI), historico consolidado de pessoa e orgao (trilha unificada de auditoria no Perfil 360 e no detalhe de orgao), conciliacao previsto x real por pessoa/competencia, CDO com vinculo 1..N de pessoas, boletos estruturados por orgao/competencia com baixa parcial/total e comprovante, lotes de pagamento com selecao de baixas elegiveis, simulacao previa de risco e fechamento por status final, importacao CSV em massa de orgaos com validacao/simulacao e rollback transacional, classificacao institucional de orgaos (tipo/esfera/poder, orgao supervisor e origem dos dados), espelho de custo detalhado por pessoa/competencia (manual + CSV), conciliacao avancada item a item com workflow de aprovacao e bloqueio, templates de oficio versionados com geracao de documento (HTML print + PDF nativo) e auditoria filtravel no Perfil 360, metadados formais de processo (oficio/DOU/entrada MTE) com anexo e trilha auditavel, busca global unificada por CPF/SEI/DOU/orgao/documento com resultados cross-modulo, painel de SLA com regras por etapa, notificacao opcional por email e controle operacional de casos vencidos, dashboard orcamentario com simulador de contratacao e cenarios salvos, relatorios premium com filtros e exportacao CSV/PDF/ZIP e painel financeiro por status (abertos/vencidos/pagos/conciliados), pacote de auditoria CGU/TCU por filtros, exportacao completa de dossie por pessoa (ZIP + PDF sintese + trilha + anexos), administracao de usuarios com papeis/permissoes via UI e fluxo de troca/reset de senha, painel LGPD com trilha de acesso sensivel, relatorio CSV e politicas de retencao/anonimizacao parametrizaveis, painel de seguranca com politica de senha/expiracao, lockout configuravel e hardening adicional de upload, observabilidade operacional com `ops-health-panel`, `log-severity`, `error-review`, `ops-quality-gate` e painel web `GET /ops/health-panel`

## Inicio rapido (local)
1. Copie `.env.example` para `.env`.
2. Preencha as variaveis obrigatorias.
3. Rode migrations e seed:
```bash
php db/migrate.php
php db/seed.php
```
4. Suba o servidor local:
```bash
php -S localhost:8000 -t public
```
5. Acesse `http://localhost:8000/login`.

## Rotas de Pessoas (resumo)
- `GET /people`
- `GET /people/show?id={id}`
- `GET /people/pending`
- `POST /people/pipeline/advance`
- `POST /people/pipeline/queue/update`
- `POST /people/pipeline/checklist/update`
- `POST /people/pending/status`
- `POST /people/timeline/store`
- `POST /people/timeline/rectify`
- `GET /people/timeline/attachment?id={attachmentId}&person_id={personId}`
- `GET /people/timeline/print?id={personId}`
- `POST /people/documents/store`
- `POST /people/documents/intelligence/run`
- `GET /people/documents/download?id={documentId}&person_id={personId}`
- `POST /people/documents/version/store`
- `GET /people/documents/version/download?version_id={versionId}&document_id={documentId}&person_id={personId}`
- `POST /people/import-csv` (`validate_only=1` para simulacao sem persistencia)
- `GET /people/dossier/export?person_id={personId}`
- `people.documents.sensitive` e necessario para classificar/baixar documentos `restricted` e `sensitive`
- `POST /people/costs/version/create`
- `POST /people/costs/item/store`
- `POST /people/reimbursements/store` (suporta calculadora automatica via `use_calculator` + campos `calc_*` com memoria de calculo)
- `POST /people/reimbursements/mark-paid`
- `POST /people/process-comments/store`
- `POST /people/process-comments/update`
- `POST /people/process-comments/delete`
- `POST /people/process-admin-timeline/store`
- `POST /people/process-admin-timeline/update`
- `POST /people/process-admin-timeline/delete`
- `GET /people/audit/export?person_id={personId}&audit_*={filtros}`

## Busca Global (resumo)
- `GET /global-search?q={termo}&scope={all|people|organs|process_meta|documents}`

## Rotas de Orgaos (resumo)
- `GET /organs`
- `GET /organs/create`
- `POST /organs/store`
- `POST /organs/import-csv` (`validate_only=1` para simulacao sem persistencia)
- `GET /organs/show?id={id}`
- `GET /organs/edit?id={id}`
- `POST /organs/update`
- `POST /organs/delete`
- `GET /organs/audit/export?organ_id={organId}&audit_*={filtros}`

## Rotas de Usuarios e Acessos (resumo)
- `GET /users`
- `GET /users/create`
- `POST /users/store`
- `GET /users/show?id={id}`
- `GET /users/edit?id={id}`
- `POST /users/update`
- `POST /users/delete`
- `POST /users/toggle-active`
- `GET /users/roles`
- `POST /users/roles/update`
- `GET /users/password`
- `POST /users/password/update`
- `POST /users/reset-password`

## Rotas de LGPD (resumo)
- `GET /lgpd`
- `GET /lgpd/export/access-csv`
- `POST /lgpd/policies/upsert`
- `POST /lgpd/retention/run`

## Rotas de Seguranca (resumo)
- `GET /security`
- `POST /security/update`

## Rotas de Observabilidade (resumo)
- `GET /ops/health-panel` (painel estruturado de saude/logs/recorrencias; exige `security.view`)

## Rotas de CDO (resumo)
- `GET /cdos`
- `GET /cdos/create`
- `POST /cdos/store`
- `GET /cdos/show?id={id}`
- `GET /cdos/edit?id={id}`
- `POST /cdos/update`
- `POST /cdos/delete`
- `POST /cdos/people/link`
- `POST /cdos/people/unlink`

## Rotas de Boletos (resumo)
- `GET /invoices`
- `GET /invoices/create`
- `POST /invoices/store`
- `GET /invoices/show?id={id}`
- `GET /invoices/edit?id={id}`
- `POST /invoices/update`
- `POST /invoices/delete`
- `POST /invoices/people/link`
- `POST /invoices/people/unlink`
- `POST /invoices/payments/store`
- `GET /invoices/payments/proof?id={paymentId}&invoice_id={invoiceId}`
- `GET /invoices/pdf?id={id}`
- `GET /invoices/payment-batches`
- `GET /invoices/payment-batches/show?id={batchId}`
- `POST /invoices/payment-batches/store`
- `POST /invoices/payment-batches/final-approval/simulate`
- `POST /invoices/payment-batches/status/update`

## Rotas de Orcamento/Capacidade (resumo)
- `GET /budget?year={ano}`
- `POST /budget/simulate`
- `POST /budget/parameters/upsert`

## Rotas de Relatorios Premium (resumo)
- `GET /reports`
- `GET /reports/export/csv`
- `GET /reports/export/pdf`
- `GET /reports/export/zip`
- `GET /reports/export/audit-zip`

## Rotas de Espelhos de Custo (resumo)
- `GET /cost-mirrors`
- `GET /cost-mirrors/create`
- `POST /cost-mirrors/store`
- `GET /cost-mirrors/show?id={id}`
- `GET /cost-mirrors/edit?id={id}`
- `POST /cost-mirrors/update`
- `POST /cost-mirrors/delete`
- `POST /cost-mirrors/items/store`
- `POST /cost-mirrors/items/import-csv`
- `POST /cost-mirrors/items/delete`
- `GET /cost-mirrors/reconciliation/show?id={id}`
- `POST /cost-mirrors/reconciliation/run`
- `POST /cost-mirrors/reconciliation/justify`
- `POST /cost-mirrors/reconciliation/approve`

## Rotas de Templates de Oficio (resumo)
- `GET /office-templates`
- `GET /office-templates/create`
- `POST /office-templates/store`
- `GET /office-templates/show?id={id}`
- `GET /office-templates/edit?id={id}`
- `POST /office-templates/update`
- `POST /office-templates/delete`
- `POST /office-templates/version/create`
- `POST /office-templates/generate`
- `GET /office-documents/show?id={id}`
- `GET /office-documents/print?id={id}`
- `GET /office-documents/pdf?id={id}`

## Rotas de Metadados Formais (resumo)
- `GET /process-meta`
- `GET /process-meta/create`
- `POST /process-meta/store`
- `GET /process-meta/show?id={id}`
- `GET /process-meta/edit?id={id}`
- `POST /process-meta/update`
- `POST /process-meta/delete`
- `GET /process-meta/dou-attachment?id={id}`

## Rotas de SLA e Pendencias (resumo)
- `GET /sla-alerts`
- `GET /sla-alerts/rules`
- `POST /sla-alerts/rules/upsert`
- `POST /sla-alerts/dispatch-email`
- `POST /sla-alerts/control/update`

## Portal de documentacao
A documentacao oficial esta centralizada em `/docs`:
- `docs/00-audit-report.md`
- `docs/00-repository-map.md`
- `docs/01-getting-started.md`
- `docs/02-architecture.md`
- `docs/03-environment.md`
- `docs/04-deploy.md`
- `docs/05-operations.md`
- `docs/06-troubleshooting.md`
- `docs/07-security.md`
- `docs/09-qa-regression.md`
- `docs/14-lgpd-advanced.md`
- `docs/15-security-hardening.md`
- `docs/changelog-docs.md`

## Deploy (resumo)
Fluxo recomendado no servidor:
```bash
# na raiz do projeto
./scripts/backup.sh --label pre_deploy
./scripts/deploy.sh --preflight
./scripts/deploy.sh --apply
```

Primeiro deploy (quando necessario popular dados iniciais):
```bash
./scripts/deploy.sh --apply --with-seed
```

Health-check:
```bash
./scripts/healthcheck.sh
```

Upload FTP (ex.: via task do VS Code):
```bash
./scripts/ftp-upload.sh --dry-run
./scripts/ftp-upload.sh
```

Backup e restore operacional:
```bash
./scripts/backup.sh --with-env --label manual
./scripts/restore.sh --from <backup_dir> --yes
```

## Regras de seguranca
- Nunca versionar segredos (`.env`, chaves, dumps, tokens).
- Nunca colocar credenciais reais em markdown.
- Rotacionar credenciais imediatamente em caso de vazamento.
