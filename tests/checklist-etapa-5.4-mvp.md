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

## Gestao de ciclos anuais
- [ ] Atualizacao de valor anual do ciclo funciona na tabela de ciclos
- [ ] Exclusao de ciclo remove o ciclo selecionado
- [ ] Exclusao de ciclo tambem remove simulacoes e parametros vinculados ao mesmo ciclo
- [ ] Mensagem de confirmacao da exclusao informa os impactos (simulacoes/parametros)

## Custo medio automatico por orgao
- [ ] Tabela de custo medio lista orgao, valor calculado e base historica usada
- [ ] Valor por orgao prioriza historico de espelhos de custo (`cost_mirrors`)
- [ ] Sem espelho suficiente, calculo usa historico de custos planejados ativos
- [ ] Sem base por orgao, simulador usa media historica global automaticamente

## Simulador de contratacao
- [ ] `POST /budget/simulate` salva cenario com orgao, data, quantidade e custo medio
- [ ] Simulacao calcula impacto no ano corrente (pro rata) e no ano seguinte
- [ ] Simulacao calcula capacidade maxima antes da contratacao
- [ ] Simulacao mostra risco (`baixo`, `medio`, `alto`) conforme saldo remanescente
- [ ] Simulacao nao aceita entrada manual de custo medio na interface
- [ ] Fonte exibida na simulacao indica se veio de historico do orgao ou media historica global

## Alertas ativos
- [ ] Painel \"Alertas ativos\" e exibido no dashboard
- [ ] Alertas incluem risco de saldo negativo e deficit projetado no proximo ano
- [ ] Alertas incluem risco mensal alto de insuficiencia quando existir
- [ ] Sem risco ativo, painel exibe estado vazio

## Historico de cenarios
- [ ] Cenarios recentes sao exibidos na tela com custo, saldo e risco
- [ ] Cenario salvo gera item em `hiring_scenario_items`

## Auditoria e eventos
- [ ] `audit_log` registra `hiring_scenario:simulate`
- [ ] `system_events` registra `budget.hiring_simulated`

## Seguranca de acesso
- [ ] Usuario sem `budget.view` nao acessa `/budget`
- [ ] Usuario sem `budget.simulate` nao executa `/budget/simulate`
