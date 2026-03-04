# Checklist de Testes - Etapa 7.4 (Logs por severidade)

## Pre-condicoes
- [ ] Existe `storage/logs/app.log` com permissao de leitura
- [ ] Script executavel: `chmod +x scripts/log-severity.php` (se necessario)

## Execucao basica
- [ ] `./scripts/log-severity.php --help` exibe as opcoes
- [ ] `./scripts/log-severity.php` mostra totais por severidade
- [ ] `./scripts/log-severity.php --bucket day` agrega a serie por dia

## Saida estruturada
- [ ] `./scripts/log-severity.php --output json` retorna JSON valido
- [ ] JSON contem `totals`, `by_bucket` e `top_messages`

## Snapshot operacional
- [ ] `./scripts/log-severity.php --write-snapshot` cria arquivo em `storage/ops/log-severity`
- [ ] `--retention-days` remove snapshots antigos conforme configuracao

## Gatilho de automacao
- [ ] `--fail-error-count` retorna codigo `2` quando total de ERROR na janela atinge o limite
- [ ] Sem atingir o limite, retorno permanece `0`
