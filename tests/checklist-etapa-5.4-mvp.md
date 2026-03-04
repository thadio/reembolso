# Checklist de Testes - Fase 5.4 (MVP Orcamento/Capacidade)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `018`, `019`, `021` e `022` do modulo orcamentario)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `budget.view` e `budget.simulate`

## Dashboard orcamentario
- [ ] `GET /budget?year={ano}` abre tela do modulo
- [ ] Ciclo anual e criado automaticamente quando nao existir
- [ ] KPIs exibem total, executado, comprometido e disponivel
- [ ] Projecao do ano seguinte e saldo projetado sao exibidos

## Parametrizacao por orgao/cargo/setor
- [ ] `POST /budget/parameters/upsert` salva custo medio por escopo (`orgao`, `cargo`, `setor`)
- [ ] Atualizacao de parametro existente funciona (upsert por escopo)
- [ ] Tabela de parametros lista orgao, escopo, custo e usuario de atualizacao
- [ ] Escopo geral (sem cargo/setor) segue disponivel para fallback
- [ ] Usuario sem `budget.manage` nao altera parametros

## Simulador de contratacao
- [ ] `POST /budget/simulate` salva cenario com orgao, data, quantidade e custo medio
- [ ] Simulacao calcula impacto no ano corrente (pro rata) e no ano seguinte
- [ ] Simulacao calcula capacidade maxima antes da contratacao
- [ ] Simulacao mostra risco (`baixo`, `medio`, `alto`) conforme saldo remanescente
- [ ] Fallback de custo medio usa prioridade de escopo (`cargo+setor` -> `cargo` -> `setor` -> `geral`)
- [ ] Sem parametro do orgao, fallback usa media global de custos ativos

## Alertas ativos
- [ ] Painel \"Alertas ativos (5.4)\" e exibido no dashboard
- [ ] Alertas incluem risco de saldo negativo e deficit projetado no proximo ano
- [ ] Alertas incluem risco mensal alto de insuficiencia quando existir
- [ ] Sem risco ativo, painel exibe estado vazio

## Historico de cenarios
- [ ] Cenarios recentes sao exibidos na tela com custo, saldo e risco
- [ ] Cenario salvo gera item em `hiring_scenario_items`

## Auditoria e eventos
- [ ] `audit_log` registra `hiring_scenario:simulate`
- [ ] `audit_log` registra `org_cost_parameter:create` e `org_cost_parameter:update`
- [ ] `system_events` registra `budget.hiring_simulated`
- [ ] `system_events` registra `budget.org_cost_parameter_upserted`

## Seguranca de acesso
- [ ] Usuario sem `budget.view` nao acessa `/budget`
- [ ] Usuario sem `budget.simulate` nao executa `/budget/simulate`
