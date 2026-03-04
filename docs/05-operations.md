# 05 - Operations

## Rotina diaria
- Verificar `GET /health`
- Verificar tamanho de logs e disco
- Confirmar escrita em `storage/logs` e `storage/uploads`
- Confirmar backup do dia e integridade de `manifest.txt`

## Runbook de release
```bash
cd /var/www/reembolso
./scripts/backup.sh --label pre_release
./scripts/deploy.sh --preflight
./scripts/deploy.sh --apply
./scripts/healthcheck.sh
```

## Runbook de seed controlado
Use seed somente quando necessario (idempotente para catalogos e papeis):
```bash
cd /var/www/reembolso
./scripts/deploy.sh --seed
```

## Runbook de backup
Backup padrao (DB + uploads):
```bash
cd /var/www/reembolso
./scripts/backup.sh --label rotina
```

Backup completo (inclui snapshot de `.env`):
```bash
./scripts/backup.sh --with-env --label pre_change
```

Local padrao e retencao:
- Diretorio padrao: `storage/backups`
- Retencao padrao: `14` dias
- Parametros opcionais via `.env`: `BACKUP_ROOT`, `BACKUP_RETENTION_DAYS`, `BACKUP_INCLUDE_ENV`

## Runbook de restore
1. Abrir janela de manutencao e bloquear alteracoes.
2. Rodar simulacao:
```bash
./scripts/restore.sh --from <backup_dir> --dry-run --yes
```
3. Rodar restore efetivo:
```bash
./scripts/restore.sh --from <backup_dir> --yes
```
4. Validar aplicacao:
```bash
./scripts/healthcheck.sh
```
5. Registrar incidente e causa raiz no historico operacional.

## Runbook de contingencia
Quando ocorrer falha grave (corrupcao de dados, deploy inconsistente, perda de uploads):
1. Congelar operacao (sem novos cadastros/baixas).
2. Escolher backup mais recente com `manifest.txt` valido.
3. Executar `restore.sh` em janela controlada.
4. Validar saude da aplicacao e pontos criticos do negocio.
5. Reabrir operacao somente apos validacao funcional.

## Observabilidade minima
- Endpoint: `/health`
- Log aplicacao: `storage/logs/app.log`
- Log web server: configuracao do Apache/PHP-FPM no servidor
