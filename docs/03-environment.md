# 03 - Environment (Source of Truth)

Este arquivo e a referencia canonica de ambiente e configuracao de servidor.

## Matriz de ambientes
- `local`: desenvolvimento
- `production`: servidor final

## Variaveis de ambiente
Arquivo de exemplo: `.env.example`

### Obrigatorias para runtime
- `NAME`
- `BASE_URL`
- `TIMEZONE`
- `APP_ENV`
- `APP_DEBUG`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `SESSION_NAME`
- `SESSION_TTL_SECONDS`
- `CSRF_TTL_SECONDS`
- `LOGIN_MAX_ATTEMPTS`
- `LOGIN_WINDOW_SECONDS` (ou `LOGIN_DECAY_SECONDS` para retrocompatibilidade)
- `LOGIN_LOCKOUT_SECONDS`
- `SEED_ADMIN_NAME`
- `SEED_ADMIN_EMAIL`
- `SEED_ADMIN_PASSWORD`

### Opcionais para politica de seguranca (fase 6.3)
- `PASSWORD_MIN_LENGTH`
- `PASSWORD_MAX_LENGTH`
- `PASSWORD_REQUIRE_UPPER`
- `PASSWORD_REQUIRE_LOWER`
- `PASSWORD_REQUIRE_NUMBER`
- `PASSWORD_REQUIRE_SYMBOL`
- `PASSWORD_EXPIRATION_DAYS`
- `UPLOAD_MAX_FILE_SIZE_MB`

### Opcionais para operacao/deploy
- `DEPLOY_RESTART_COMMAND` (ex.: restart de PHP-FPM/Apache quando aplicavel)
- `DEPLOY_HEALTH_PATH` (default: `/health`)

### Opcionais para backup/restore
- `BACKUP_ROOT` (default: `storage/backups`)
- `BACKUP_RETENTION_DAYS` (default: `14`)
- `BACKUP_INCLUDE_ENV` (`0` ou `1`, default: `0`)

### Opcionais para snapshots de KPI (fase 7.2)
- `OPS_KPI_SNAPSHOT_DIR` (default: `storage/ops/kpi_snapshots`)
- `OPS_KPI_SNAPSHOT_RETENTION_DAYS` (default: `30`)
- `OPS_KPI_SNAPSHOT_MAX_AGE_MINUTES` (default: `240`, controla uso de snapshot no dashboard)
- `OPS_HEALTH_PANEL_SNAPSHOT_DIR` (default: `storage/ops/health-panel`, usado pelo painel web de observabilidade)
- `OPS_LOG_SEVERITY_SNAPSHOT_DIR` (default: `storage/ops/log-severity`, usado pelo painel web de observabilidade)

### Opcionais para upload FTP
- `FTP_HOST`
- `FTP_PORT`
- `FTP_USER`
- `FTP_PASS`
- `FTP_REMOTE_ROOT`
- `FTP_IGNORE_FILE` (default `.ftpignore`)
- `FTP_DELETE` (`0` ou `1`)
- `FTP_SSL_ALLOW` (`0` ou `1`)
- `FTP_SSL_FORCE` (`0` ou `1`)
- `FTP_SSL_VERIFY` (`0` ou `1`)
- `FTP_PARALLEL` (default `2`)

## Requisitos do servidor
- Apache 2.4+
- PHP 8.1+
- MySQL/Percona 5.7+
- `DocumentRoot` apontando para `public/` (preferencial)

## Permissoes de runtime
Diretorios obrigatorios:
- `storage/logs`
- `storage/uploads`

Permissoes recomendadas:
```bash
chmod 775 storage storage/logs storage/uploads
```

## Decisao sobre serverconfig duplicado
- Arquivo canonico: **`docs/03-environment.md`**
- `serverconfig.md` (raiz) passa a ser apenas ponteiro para este documento
- Arquivos legados em `_ignore/docs` foram removidos/depreciados para evitar duplicidade
