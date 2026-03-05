# Checklist de Testes - Etapa 7.3 (Qualidade: unitario + integracao + regressao QA)

## Pre-condicoes
- [ ] Banco com migrations e seed aplicados
- [ ] Arquivo de fixture existe em `tests/fixtures/qa_regression_dataset.sql`
- [ ] Scripts executaveis (se necessario):
  - [ ] `chmod +x scripts/financial-unit-tests.php`
  - [ ] `chmod +x scripts/financial-integration-tests.php`
  - [ ] `chmod +x scripts/phase7-3-tests.php`
  - [ ] `chmod +x scripts/qa-regression.php`

## Suite unitaria
- [ ] `./scripts/financial-unit-tests.php --help` exibe opcoes de uso
- [ ] `./scripts/financial-unit-tests.php` executa assertions de formulas e bordas
- [ ] Script retorna codigo `0` quando assertions unitarias passam
- [ ] `./scripts/financial-unit-tests.php --output json` retorna `status`, `totals` e `assertions`

## Suite de integracao
- [ ] `./scripts/financial-integration-tests.php --help` exibe opcoes de uso
- [ ] `./scripts/financial-integration-tests.php` valida delta financeiro de dashboard + budget
- [ ] Simulacao orcamentaria de teste persiste cenario e 3 itens na matriz
- [ ] `--cleanup-after` remove fixture e cenarios `qa_it_7_3_*`
- [ ] `./scripts/financial-integration-tests.php --output json` retorna `status`, `dashboard_delta`, `budget_delta` e `assertions`

## Regressao fixa QA (dataset 990xxx)
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

## Runner consolidado da etapa 7.3
- [ ] `./scripts/phase7-3-tests.php --help` exibe opcoes de uso
- [ ] `./scripts/phase7-3-tests.php` executa `financial-unit`, `financial-integration` e `qa-regression`
- [ ] `./scripts/phase7-3-tests.php --output json` retorna `checks`, `totals` e `status`
- [ ] Retorno da suite consolidada e `0` quando todos os checks passam
