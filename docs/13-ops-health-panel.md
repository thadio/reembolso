# 13 - Ops Health Panel

Painel tecnico de saude operacional (fase 7.4) para consolidar checks criticos em um unico comando.

## Objetivo
- validar disponibilidade do endpoint de health;
- consolidar severidade de logs da janela;
- detectar recorrencia de erros relevantes;
- verificar frescor do ultimo snapshot de KPI.

## Script
```bash
./scripts/ops-health-panel.php
```

## Parametros principais
- `--window-hours <n>`: janela para analise de logs
- `--bucket <hour|day>`: agregacao usada em severidade
- `--top-messages <n>`: top recorrencias/mensagens para os scripts auxiliares
- `--error-threshold <n>`: falha se `error` na janela atingir o limite
- `--recurring-threshold <n>`: falha se recorrencia de erro atingir o limite
- `--kpi-max-age-minutes <n>`: idade maxima permitida para ultimo snapshot KPI
- `--skip-health`: ignora check HTTP de health endpoint
- `--output json`: saida estruturada para CI/automacao
- `--write-snapshot`: grava snapshot JSON em `storage/ops/health-panel`
- `--retention-days <n>`: retencao de snapshots do painel

## Exemplos
Execucao local sem HTTP:
```bash
./scripts/ops-health-panel.php --skip-health
```

Execucao com limiares de falha:
```bash
./scripts/ops-health-panel.php --error-threshold 10 --recurring-threshold 5 --kpi-max-age-minutes 180
```

Saida JSON + snapshot:
```bash
./scripts/ops-health-panel.php --skip-health --output json --write-snapshot --retention-days 30
```

## Exemplo de cron
```cron
0 */6 * * * cd /var/www/reembolso && ./scripts/ops-health-panel.php --window-hours 24 --error-threshold 20 --recurring-threshold 8 --kpi-max-age-minutes 360 --write-snapshot --retention-days 30 >> storage/logs/ops-health-panel.log 2>&1
```
