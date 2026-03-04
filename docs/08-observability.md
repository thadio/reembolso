# 08 - Observability

Este guia descreve a rotina operacional de revisao de erros recorrentes (fase 7.4), sem depender de interface web.

## Objetivo
- detectar erros repetidos no `app.log`;
- priorizar recorrencias por frequencia e ultima ocorrencia;
- gerar artefato versionavel para acompanhamento operacional.

## Script
Executar na raiz do projeto:

```bash
./scripts/error-review.php
```

## Parametros principais
- `--window-hours <n>`: janela em horas (default: `24`)
- `--levels <csv>`: filtro de nivel (`INFO`, `WARNING`, `ERROR`)
- `--top <n>`: quantidade maxima de grupos recorrentes no ranking
- `--output json`: saida estruturada para integracao
- `--report-file <path>`: grava relatorio markdown
- `--fail-threshold <n>`: retorna codigo `2` quando `recurring_error_entries >= n`

## Exemplos
Analise de 72h, apenas erro e warning:

```bash
./scripts/error-review.php --window-hours 72 --levels ERROR,WARNING
```

Gerar relatorio markdown:

```bash
./scripts/error-review.php --report-file storage/ops/error-review.md
```

Uso em automacao com falha quando recorrencia de erro passar do limite:

```bash
./scripts/error-review.php --output json --fail-threshold 5
```

## Exemplo de cron
Execucao a cada 6 horas com relatorio persistido:

```cron
0 */6 * * * cd /var/www/reembolso && ./scripts/error-review.php --report-file storage/ops/error-review.md >> storage/logs/error-review.log 2>&1
```
