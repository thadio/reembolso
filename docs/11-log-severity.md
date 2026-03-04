# 11 - Log Severity

Guia da rotina de severidade de logs (fase 7.4), orientada a operacao e monitoramento.

## Objetivo
- consolidar volume de `INFO`, `WARNING` e `ERROR` por janela;
- gerar serie temporal por hora/dia para acompanhamento de tendencia;
- identificar mensagens mais frequentes.

## Script
```bash
./scripts/log-severity.php
```

## Parametros principais
- `--window-hours <n>`: define janela de analise
- `--bucket <hour|day>`: granularidade da serie
- `--top-messages <n>`: limite de mensagens no ranking
- `--output json`: formato estruturado
- `--write-snapshot`: grava snapshot JSON em `storage/ops/log-severity`
- `--retention-days <n>`: retencao de snapshots
- `--fail-error-count <n>`: retorna codigo `2` quando total de `ERROR` na janela atingir o limite

## Exemplos
Resumo por dia (7 dias):
```bash
./scripts/log-severity.php --window-hours 168 --bucket day
```

Gerar snapshot:
```bash
./scripts/log-severity.php --write-snapshot --retention-days 15
```

Uso em automacao com limiar de falha:
```bash
./scripts/log-severity.php --output json --fail-error-count 10
```

## Exemplo de cron
```cron
0 */3 * * * cd /var/www/reembolso && ./scripts/log-severity.php --window-hours 24 --write-snapshot --retention-days 30 >> storage/logs/log-severity.log 2>&1
```
