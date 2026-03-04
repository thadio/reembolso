# 00 - Repository Map

## Visao geral
```text
reembolso/
  README.md
  CHANGELOG.md
  .env.example
  .gitignore
  .ftpignore
  .htaccess
  serverconfig.md

  docs/
    00-audit-report.md
    00-repository-map.md
    01-getting-started.md
    02-architecture.md
    03-environment.md
    04-deploy.md
    05-operations.md
    06-troubleshooting.md
    07-security.md
    changelog-docs.md

  scripts/
    deploy.sh
    ftp-upload.sh
    healthcheck.sh
    rollback.sh

  app/
  db/
  public/
  routes/
  storage/
  tests/
  _ignore/
```

## Pontos de entrada
- App HTTP: `public/index.php`
- Rotas: `routes/web.php`
- Bootstrap: `bootstrap.php`
- Deploy: `scripts/deploy.sh`
- Health-check: `scripts/healthcheck.sh`

## Documentacao oficial
- Setup: `docs/01-getting-started.md`
- Ambiente: `docs/03-environment.md`
- Deploy: `docs/04-deploy.md`
- Seguranca: `docs/07-security.md`
