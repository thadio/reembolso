# Checklist de Testes - Etapa 7.3 (Qualidade e regressao minima QA)

## Pre-condicoes
- [ ] Banco com migrations e seed aplicados
- [ ] Arquivo de fixture existe em `tests/fixtures/qa_regression_dataset.sql`
- [ ] Script executavel: `chmod +x scripts/qa-regression.php` (se necessario)

## Fluxo principal
- [ ] `./scripts/qa-regression.php --help` exibe opcoes de uso
- [ ] `./scripts/qa-regression.php --cleanup-only` remove dataset QA sem erro
- [ ] `./scripts/qa-regression.php` executa aplicacao de fixture + validacao
- [ ] Script retorna codigo `0` quando todas as assertions passam

## Saida e evidencias
- [ ] `--output table` exibe status, deltas e assertions
- [ ] `--output json` retorna estrutura com `status`, `delta` e `assertions`
- [ ] As deltas esperadas incluem pessoas, CDO e reconciliacao financeira no mes corrente

## Higiene de dados
- [ ] `./scripts/qa-regression.php --cleanup-after` remove dados QA ao final
- [ ] Nao restam IDs da faixa `990xxx` nas tabelas alvo apos cleanup
