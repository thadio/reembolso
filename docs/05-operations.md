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

## Snapshot de KPIs (fase 7.2)
Geracao manual:
```bash
cd /var/www/reembolso
./scripts/kpi-snapshot.php
```

Uso no dashboard:
- quando existir snapshot recente em `OPS_KPI_SNAPSHOT_DIR`, o dashboard reutiliza esse snapshot;
- se snapshot estiver ausente/desatualizado, o dashboard faz calculo ao vivo automaticamente.
- janela de frescor configuravel por `OPS_KPI_SNAPSHOT_MAX_AGE_MINUTES` (default: `240`).

Simulacao sem gravar:
```bash
./scripts/kpi-snapshot.php --dry-run
```

Exemplo de cron (a cada 4 horas):
```cron
0 */4 * * * cd /var/www/reembolso && ./scripts/kpi-snapshot.php >> storage/logs/kpi-snapshot.log 2>&1
```

## Suite de qualidade financeira (fase 7.3)
Suite unitaria (regras de formula e bordas):
```bash
./scripts/financial-unit-tests.php
```

Suite de integracao (dashboard + simulacao orcamentaria com fixture):
```bash
./scripts/financial-integration-tests.php --cleanup-after
```

Runner consolidado da etapa 7.3:
```bash
./scripts/phase7-3-tests.php
```

Saida JSON para automacao:
```bash
./scripts/phase7-3-tests.php --output json
```

## Observabilidade operacional (fase 7.4)
Painel web estruturado:
- `GET /ops/health-panel` (requer permissao `security.view`; consome snapshots em `storage/ops`)

Resumo de severidade no log:
```bash
./scripts/log-severity.php --output table
```

Revisao de erros recorrentes:
```bash
./scripts/error-review.php --output table
```

Painel tecnico de saude (sem dependencia de endpoint HTTP):
```bash
./scripts/ops-health-panel.php --skip-health --output table
```

Gate operacional consolidado:
```bash
./scripts/ops-quality-gate.php --output table
```

Exemplo de rotina cron (a cada 2 horas):
```cron
0 */2 * * * cd /var/www/reembolso && ./scripts/ops-health-panel.php --skip-health --write-snapshot >> storage/logs/ops-health-panel.log 2>&1
10 */2 * * * cd /var/www/reembolso && ./scripts/ops-quality-gate.php --skip-qa --output json >> storage/logs/ops-quality-gate.log 2>&1
```
