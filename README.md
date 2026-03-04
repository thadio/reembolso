# Reembolso

Aplicacao web em PHP para gestao de movimentacao de forca de trabalho, pipeline de pessoas, timeline e reembolsos.

## Estado atual
- Fases implementadas: 0, 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2 e 2.3
- Stack: PHP 8.1+, MySQL/Percona 5.7+, Apache (shared hosting compativel)
- Deploy alvo: execucao via bash no servidor
- Modulos ativos: dashboard operacional com metricas reais, pipeline de pessoas, timeline completa, dossie documental com upload/download seguro, custos previstos com versionamento e auditoria filtravel no Perfil 360

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
- `GET /people/audit/export?person_id={personId}&audit_*={filtros}`

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
- `docs/changelog-docs.md`

## Deploy (resumo)
Fluxo recomendado no servidor:
```bash
# na raiz do projeto
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

## Regras de seguranca
- Nunca versionar segredos (`.env`, chaves, dumps, tokens).
- Nunca colocar credenciais reais em markdown.
- Rotacionar credenciais imediatamente em caso de vazamento.
