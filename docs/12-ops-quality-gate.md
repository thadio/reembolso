# 12 - Ops Quality Gate

Gate operacional unificado para qualidade e observabilidade (fases 7.3 e 7.4).

## Objetivo
- executar regressao minima de KPI (`qa-regression`);
- consolidar severidade de logs (`log-severity`);
- revisar erros recorrentes (`error-review`);
- retornar codigo de processo apropriado para automacao.

## Script
```bash
./scripts/ops-quality-gate.php
```

## Parametros principais
- `--window-hours <n>`: janela de logs para `log-severity` e `error-review`
- `--bucket <hour|day>`: agregacao da serie de severidade
- `--top-messages <n>`: limite de ranking de mensagens
- `--error-threshold <n>`: falha quando `ERROR` na janela atingir o limite
- `--recurring-threshold <n>`: falha quando recorrencia de erro atingir o limite
- `--write-snapshots`: habilita snapshot do `log-severity`
- `--skip-qa`: ignora `qa-regression`
- `--output json`: saida estruturada para CI

## Exemplos
Execucao completa:
```bash
./scripts/ops-quality-gate.php
```

Somente observabilidade (sem QA):
```bash
./scripts/ops-quality-gate.php --skip-qa --window-hours 72 --bucket day
```

Uso em CI com limiares:
```bash
./scripts/ops-quality-gate.php --output json --error-threshold 10 --recurring-threshold 5
```

## Exemplo de cron
```cron
0 */6 * * * cd /var/www/reembolso && ./scripts/ops-quality-gate.php --skip-qa --window-hours 24 --write-snapshots --retention-days 30 >> storage/logs/ops-quality-gate.log 2>&1
```
