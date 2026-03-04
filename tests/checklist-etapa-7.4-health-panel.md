# Checklist de Testes - Etapa 7.4 (Painel tecnico de saude)

## Pre-condicoes
- [ ] Scripts disponiveis: `healthcheck.sh`, `log-severity.php`, `error-review.php`, `ops-health-panel.php`
- [ ] `storage/logs/app.log` com permissao de leitura
- [ ] Diretorio `storage/ops/kpi_snapshots` existe (ou cenario de aviso validado)

## Execucao basica
- [ ] `./scripts/ops-health-panel.php --help` exibe opcoes
- [ ] `./scripts/ops-health-panel.php --skip-health` executa painel sem depender de endpoint HTTP
- [ ] Saida em tabela mostra `health_endpoint`, `log_severity`, `error_review`, `kpi_snapshot_freshness`

## Saida JSON
- [ ] `./scripts/ops-health-panel.php --skip-health --output json` retorna JSON valido
- [ ] JSON contem `status`, `checks`, `totals` e `config`

## Limiar e codigo de saida
- [ ] `--error-threshold` com limite baixo pode forcar retorno `2`
- [ ] `--recurring-threshold` com limite baixo pode forcar retorno `2`
- [ ] Sem falha critica, retorno e `0` (mesmo com warnings)

## Snapshot operacional
- [ ] `./scripts/ops-health-panel.php --skip-health --write-snapshot` cria JSON em `storage/ops/health-panel`
- [ ] `--retention-days` remove snapshots antigos conforme configuracao
