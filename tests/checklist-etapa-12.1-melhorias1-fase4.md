# Checklist de Testes - Etapa 12.1 (melhorias1 Fase 4)

## Pre-condicoes
- [x] `php db/migrate.php` aplicado sem erros
- [x] `php db/seed.php` aplicado sem erros
- [ ] Usuario com permissao `dashboard.view` e `people.manage`
- [ ] Fluxo BPMN com etapa contendo tag `data_transferencia_efetiva`

## RF-06 - Datas previstas/efetivas no cadastro
- [ ] Em `GET /people/create`, campos `Data prevista de inicio efetivo` e `Data prevista de termino efetivo` aparecem no formulario
- [ ] Em `GET /people/edit?id={id}`, os campos carregam valores ja salvos
- [ ] Validacao bloqueia `termino previsto < inicio previsto`
- [ ] Apos salvar pessoa, os valores ficam persistidos em `assignments.target_start_date` e `assignments.requested_end_date`
- [ ] Em `GET /people/show?id={id}`, o resumo exibe:
  - inicio previsto/real
  - termino previsto/real

## RF-06 - Substituicao automatica por data efetiva
- [x] Avancar etapa marcada com tag `data_transferencia_efetiva` registra data real automaticamente
- [x] Para movimento de entrada, preenche `assignments.effective_start_date`
- [x] Para movimento de saida, preenche `assignments.effective_end_date`
- [x] Projecoes financeiras passam a considerar `COALESCE(data_real, data_prevista)` sem regressao dos totais

## RF-05 - Novo dashboard inicial e dashboard legado
- [x] `GET /dashboard` abre o novo dashboard inicial
- [x] `GET /dashboard2` abre o dashboard operacional legado
- [ ] Menu principal continua apontando para `/dashboard`
- [x] No novo dashboard, KPIs obrigatorios aparecem:
  - orcamento anual vigente
  - gasto acumulado no ano
  - saldo disponivel
- [x] Grafico mensal apresenta real x planejado + linha de limite orcamentario
- [x] Grafico empilhado apresenta pessoas ativas + pessoas em pipeline

## Regressao minima
- [ ] Fluxos de Pessoas (cadastro, edicao, timeline, documentos) continuam operando
- [ ] Modulo `/budget` continua sem regressao funcional
- [x] Suite `./scripts/phase7-3-tests.php` executa com status `ok`
