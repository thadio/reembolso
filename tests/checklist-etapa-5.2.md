# Checklist de Testes - Fase 5.2 (Gap orcamentario e suplementacao)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `021_phase5_budget_gap_supplementation.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `budget.view`, `budget.simulate` e `budget.manage`
- [ ] Existem cenarios de simulacao cadastrados no ciclo

## Risco de insuficiencia por mes
- [ ] `GET /budget?year={ano}` exibe card "Risco de insuficiencia por mes (5.2)"
- [ ] Tabela mostra `orcamento acumulado`, `projecao acumulada`, `diferenca`, `pressao` e `risco`
- [ ] Mes com diferenca negativa aparece com risco `alto`
- [ ] Mes com margem <= 10% do acumulado aparece com risco `medio`

## Simulacao de impacto por entrada/saida
- [ ] Formulario de simulacao exige `Tipo de movimento` (`entrada` ou `saida`)
- [ ] Simulacao de `entrada` reduz saldo disponivel
- [ ] Simulacao de `saida` aumenta saldo disponivel (impacto negativo de custo)
- [ ] `hiring_scenarios.movement_type` persiste corretamente
- [ ] Resultado da simulacao exibe tipo de movimento

## Ranking de ofensores
- [ ] Card "Ranking de maiores ofensores (Pior Caso)" e exibido
- [ ] Ranking considera apenas cenarios de `entrada`
- [ ] Ranking usa item `pior_caso` para calcular deficit
- [ ] Ordenacao prioriza maior `deficit_amount`

## Auditoria e eventos
- [ ] `audit_log` de `hiring_scenario:simulate` inclui `movement_type`
- [ ] `system_events` de `budget.hiring_simulated` inclui `movement_type`

## Seguranca de acesso
- [ ] Usuario sem `budget.view` nao acessa `/budget`
- [ ] Usuario sem `budget.simulate` nao executa `/budget/simulate`
