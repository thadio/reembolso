# 05 - Operations

## Rotina diaria
- Verificar `GET /health`
- Verificar tamanho de logs e disco
- Confirmar escrita em `storage/logs` e `storage/uploads`

## Runbook de release
```bash
cd /var/www/reembolso
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

## Backups (recomendado)
- Backup de banco antes de release
- Backup de `.env` fora do git
- Backup de `storage/uploads`

## Observabilidade minima
- Endpoint: `/health`
- Log aplicacao: `storage/logs/app.log`
- Log web server: configuracao do Apache/PHP-FPM no servidor
