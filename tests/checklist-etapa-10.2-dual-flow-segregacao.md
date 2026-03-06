# Checklist de Homologacao - Etapa 10.2 (Duplo Fluxo + Segregacao Financeira)

## 1) Escopo
- Validar migration `043_phase10_dual_flow_financial_segregation.sql`.
- Validar fluxo completo `entrada_mte` (despesa) do inicio ao status final.
- Validar fluxo completo `saida_mte` (receita) do inicio ao status final.
- Validar segregacao financeira entre `despesa_reembolso` e `receita_reembolso` em:
  - `assignments`
  - `reimbursement_entries`
  - `invoices`
  - `payments`
  - `payment_batches`
  - `budget_cycles`, `budget_scenario_parameters`, `hiring_scenarios`

## 2) Execucao realizada
- [x] Migration runner executado no ambiente: `php db/migrate.php`.
- [x] Resultado da migration alvo: `043_phase10_dual_flow_financial_segregation.sql` em estado `[skip]` (ja aplicada).
- [x] Homologacao automatizada executada via:
  - `./scripts/homologate-phase10-dual-flow.php`
- [x] Evidencia de execucao:
  - `run_id`: `20260306115943`
  - `ano_testado`: `2097`
  - `relatorio`: `storage/ops/phase10_dual_flow_homologation_20260306115943.json`

## 3) Casos e resultados esperados
- [x] Caso 01 - Migration 043 registrada na tabela `migrations`.
  Resultado esperado: linha existente para `043_phase10_dual_flow_financial_segregation.sql`.
  Resultado obtido: `executed_at = 2026-03-06 11:09:00`.

- [x] Caso 02 - Fluxo `entrada_mte` conclui ate etapa final.
  Resultado esperado: trilha de transicoes do fluxo padrao finalizando em `ativo`.
  Resultado obtido: `interessado > selecionado > oficio_orgao > custos_recebidos > cdo > mgi > dou > ativo`.
  Evidencia: `person_id=990113`, `assignment_id=17`.

- [x] Caso 03 - Fluxo `saida_mte` conclui ate etapa final.
  Resultado esperado: trilha de transicoes de saida finalizando em `saida_encerrado`.
  Resultado obtido: `saida_triagem > saida_validacao_lotacao_mte > saida_oficio_destino > saida_anuencia_destino > saida_instrumento_ressarcimento > saida_publicacao_ativacao > saida_financeiro_faturamento > saida_financeiro_recebimento > saida_encerrado`.
  Evidencia: `person_id=990114`, `assignment_id=18`.

- [x] Caso 04 - Contexto de movimento persistido corretamente em `assignments`.
  Resultado esperado:
  - entrada: `movement_direction=entrada_mte`, `financial_nature=despesa_reembolso`, `destination_mte_destination_id` preenchido e `origin_mte_destination_id` nulo.
  - saida: `movement_direction=saida_mte`, `financial_nature=receita_reembolso`, `origin_mte_destination_id` preenchido e `destination_mte_destination_id` nulo.
  Resultado obtido: conforme esperado no relatorio da execucao.

- [x] Caso 05 - Titulos e baixas de despesa segregados.
  Resultado esperado:
  - `invoice` despesa com `financial_nature=despesa_reembolso`.
  - `payment` vinculado com `financial_nature=despesa_reembolso`.
  - `payment_batch` vinculado com `financial_nature=despesa_reembolso`.
  Resultado obtido:
  - `invoice_id=5`
  - `payment_id=5`
  - `batch_id=5`
  todos em `despesa_reembolso`.

- [x] Caso 06 - Titulos e baixas de receita segregados.
  Resultado esperado:
  - `invoice` receita com `financial_nature=receita_reembolso`.
  - `payment` vinculado com `financial_nature=receita_reembolso`.
  - `payment_batch` vinculado com `financial_nature=receita_reembolso`.
  Resultado obtido:
  - `invoice_id=6`
  - `payment_id=6`
  - `batch_id=6`
  todos em `receita_reembolso`.

- [x] Caso 07 - Reembolsos reais segregados por natureza.
  Resultado esperado:
  - lancamentos de entrada em `despesa_reembolso`;
  - lancamentos de saida em `receita_reembolso`;
  - com status `pago` e `pendente` em ambos os lados.
  Resultado obtido:
  - despesa: `id=990414 (pago, 1500.00)` e `id=990415 (pendente, 250.00)`;
  - receita: `id=990416 (pago, 1700.00)` e `id=990417 (pendente, 300.00)`.

- [x] Caso 08 - Orcamento separado por natureza no mesmo ano.
  Resultado esperado:
  - existencia de ciclos distintos por `financial_nature` no ano `2097`;
  - existencia de parametros e cenarios separados por natureza;
  - resumo financeiro por natureza sem mistura entre receita e despesa.
  Resultado obtido:
  - `budget_cycles`: 1 ciclo `despesa_reembolso` + 1 ciclo `receita_reembolso`;
  - `budget_scenario_parameters`: 1 registro por natureza;
  - `hiring_scenarios`: 1 simulacao por natureza;
  - `summary_despesa`: executado `2700`, comprometido `1050`;
  - `summary_receita`: executado `2700`, comprometido `1100`.

## 4) Assertivas finais da execucao automatizada
- [x] `entrada_final_status_ativo = true`
- [x] `saida_final_status_encerrado = true`
- [x] `despesa_totals_match_expected = true`
- [x] `receita_totals_match_expected = true`
- [x] `financial_segregation_ok = true`

## 5) Comandos de reproducao
- `php db/migrate.php`
- `./scripts/homologate-phase10-dual-flow.php`
