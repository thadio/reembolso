# 09 - QA Regression

Guia da regressao minima com dataset fixo (fase 7.3), executada sem interface web.

## Objetivo
- validar rapidamente regras de KPI financeiro no dashboard;
- garantir comportamento minimo esperado apos mudancas de backend;
- usar dataset deterministico com IDs reservados (`990xxx`).

## Artefatos
- Fixture SQL: `tests/fixtures/qa_regression_dataset.sql`
- Runner de regressao: `scripts/qa-regression.php`
- Suite unitaria financeira: `scripts/financial-unit-tests.php`
- Suite de integracao financeira: `scripts/financial-integration-tests.php`
- Homologacao automatizada melhorias1 fase 4: `scripts/homologate-phase12-melhorias1.php`
- Runner consolidado da etapa 7.3: `scripts/phase7-3-tests.php`

## Execucao
Limpar dados de fixture:

```bash
./scripts/qa-regression.php --cleanup-only
```

Executar regressao:

```bash
./scripts/qa-regression.php
```

Executar e limpar ao final:

```bash
./scripts/qa-regression.php --cleanup-after
```

Saida estruturada (JSON):

```bash
./scripts/qa-regression.php --output json
```

## Suite completa da etapa 7.3
Executar todos os checks (unitario + integracao + regressao fixa + homologacao fase 12):

```bash
./scripts/phase7-3-tests.php
```

Executar em JSON para pipeline:

```bash
./scripts/phase7-3-tests.php --output json
```

Executar sem homologacao da fase 12 (modo rapido):

```bash
./scripts/phase7-3-tests.php --skip-homolog-phase12
```

## Resultado esperado (delta da fixture)
- `total_organs`: `+1`
- `total_people`: `+2`
- `active_people`: `+1`
- `people_with_active_cost_plan`: `+1`
- `expected_reimbursement_current_month`: `+450.00`
- `actual_reimbursement_posted_current_month`: `+500.00`
- `actual_reimbursement_paid_current_month`: `+200.00`
- `total_cdos`: `+1`
- `cdo_total_amount`: `+1000.00`
- `cdo_allocated_amount`: `+250.00`

Se qualquer assertion falhar, o script encerra com codigo `2`.
