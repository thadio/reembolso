# Checklist de Testes - Fase 5.1 (Projecoes e cenarios multiparametricos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `019_phase5_budget_projections_scenarios.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `budget.view`, `budget.simulate` e `budget.manage`
- [ ] Existe ao menos 1 orgao ativo e 1 modalidade ativa

## Projecao mensal/anual/proximo ano
- [ ] `GET /budget?year={ano}` exibe painel de projecoes da etapa 5.1
- [ ] Tabela mensal mostra 12 linhas (jan-dez)
- [ ] Valores mensais exibem `executado`, `comprometido`, `base projetada` e `total projetado`
- [ ] KPI de projecao anual do ciclo corrente e exibido
- [ ] KPI de saldo anual projetado e exibido
- [ ] Envelope do proximo ano exibe Base, Atualizado e Pior Caso

## Parametrizacao por orgao/modalidade
- [ ] `POST /budget/scenario-parameters/upsert` cria variacoes por orgao/modalidade
- [ ] Reenvio para o mesmo orgao/modalidade atualiza registro (upsert)
- [ ] Modalidade `geral` funciona como fallback quando nao existe modalidade especifica
- [ ] Tabela de variacoes lista orgao, modalidade e percentuais (Base/Atualizado/Pior Caso)
- [ ] Usuario sem `budget.manage` nao altera variacoes de cenario

## Simulador multiparametrico
- [ ] `POST /budget/simulate` exige orgao, modalidade, data e quantidade
- [ ] Simulacao salva cabecalho em `hiring_scenarios` com `modality`
- [ ] Simulacao salva 3 itens em `hiring_scenario_items` (`base`, `atualizado`, `pior_caso`)
- [ ] Resultado exibe matriz com variacao, custo mensal, impacto anual, saldo e risco por cenario
- [ ] Sem variacao especifica por modalidade, simulador usa variacao `geral` do orgao
- [ ] Sem variacao do orgao, simulador usa variacoes padrao (0%, 10%, 25%)

## Auditoria e eventos
- [ ] `audit_log` registra `budget_scenario_parameter:create/update`
- [ ] `system_events` registra `budget.scenario_parameter_upserted`
- [ ] `audit_log` de `hiring_scenario:simulate` inclui matriz multiparametrica
- [ ] `system_events` de `budget.hiring_simulated` inclui base e pior caso

## Seguranca de acesso
- [ ] Usuario sem `budget.view` nao acessa `/budget`
- [ ] Usuario sem `budget.simulate` nao executa `/budget/simulate`
- [ ] Usuario sem `budget.manage` nao executa `/budget/scenario-parameters/upsert`
