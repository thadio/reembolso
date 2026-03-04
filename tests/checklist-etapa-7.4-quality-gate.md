# Checklist de Testes - Etapa 7.4 (Gate operacional)

## Pre-condicoes
- [ ] Scripts existem: `qa-regression.php`, `log-severity.php`, `error-review.php`, `ops-quality-gate.php`
- [ ] Permissao de execucao em `scripts/`
- [ ] `storage/logs/app.log` acessivel

## Execucao basica
- [ ] `./scripts/ops-quality-gate.php --help` exibe opcoes
- [ ] `./scripts/ops-quality-gate.php` executa checks e retorna status agregado
- [ ] Resultado em tabela mostra cada check com `ok/fail` e `exit`

## JSON para automacao
- [ ] `./scripts/ops-quality-gate.php --output json` retorna objeto com `checks` e `totals`
- [ ] Quando todos os checks passam, codigo de saida e `0`

## Cenarios de falha controlada
- [ ] `--error-threshold` com limite baixo pode forcar retorno `2`
- [ ] `--recurring-threshold` com limite baixo pode forcar retorno `2`
- [ ] `--skip-qa` executa apenas checks de observabilidade
