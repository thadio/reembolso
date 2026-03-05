# Reembolso

Aplicacao web em PHP para gestao de movimentacao de forca de trabalho, pipeline de pessoas, timeline e reembolsos.

## Estado atual
- Fases implementadas: 0, 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 5.1, 5.2, 5.3, 5.4 (MVP de orcamento/capacidade), 6.1 (admin de usuarios/acessos) e 6.2 (LGPD avancado)
- Stack: PHP 8.1+, MySQL/Percona 5.7+, Apache (shared hosting compativel)
- Deploy alvo: execucao via bash no servidor
- Modulos ativos: dashboard operacional com metricas reais, pipeline de pessoas, timeline completa, dossie documental com upload/download seguro, custos previstos com versionamento, financeiro real de reembolsos (boletos/pagamentos), conciliacao previsto x real por pessoa/competencia, CDO com vinculo 1..N de pessoas, boletos estruturados por orgao/competencia com baixa parcial/total e comprovante, espelho de custo detalhado por pessoa/competencia (manual + CSV), conciliacao avancada item a item com workflow de aprovacao e bloqueio, templates de oficio versionados com geracao de documento (HTML print + PDF nativo) e auditoria filtravel no Perfil 360, metadados formais de processo (oficio/DOU/entrada MTE) com anexo e trilha auditavel, painel de SLA com regras por etapa e notificacao opcional por email, dashboard orcamentario com simulador de contratacao e cenarios salvos, relatorios premium com filtros e exportacao CSV/PDF/ZIP, administracao de usuarios com papeis/permissoes via UI e fluxo de troca/reset de senha, painel LGPD com trilha de acesso sensivel, relatorio CSV e politicas de retencao/anonimizacao parametrizaveis

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
- `POST /people/pipeline/advance`
- `POST /people/timeline/store`
- `POST /people/timeline/rectify`
- `GET /people/timeline/attachment?id={attachmentId}&person_id={personId}`
- `GET /people/timeline/print?id={personId}`
- `POST /people/documents/store`
- `GET /people/documents/download?id={documentId}&person_id={personId}`
- `POST /people/costs/version/create`
- `POST /people/costs/item/store`
- `POST /people/reimbursements/store`
- `POST /people/reimbursements/mark-paid`
- `GET /people/audit/export?person_id={personId}&audit_*={filtros}`

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

## Rotas de Orcamento/Capacidade (resumo)
- `GET /budget?year={ano}`
- `POST /budget/simulate`
- `POST /budget/parameters/upsert`

## Rotas de Relatorios Premium (resumo)
- `GET /reports`
- `GET /reports/export/csv`
- `GET /reports/export/pdf`
- `GET /reports/export/zip`

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
- `docs/14-lgpd-advanced.md`
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
