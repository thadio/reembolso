# Checklist de Testes — Fase 2.5 (Conciliação previsto x real)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] Existe pessoa com versao ativa em `cost_plans` e itens em `cost_plan_items` (gerados pelo salvamento em lote da tabela de custos)
- [ ] Existem lançamentos em `reimbursement_entries` para ao menos 2 competências

## Perfil 360 — conciliação por pessoa
- [ ] Seção "Conciliação previsto x real" aparece no Perfil 360
- [ ] KPIs do mês atual exibem previsto, real lançado, real pago e desvio
- [ ] Desvio positivo aparece com destaque de risco
- [ ] Desvio negativo aparece com destaque de economia

## Conciliação por competência
- [ ] Tabela lista competências da janela analisada
- [ ] Coluna "Previsto" considera regra por tipo (`mensal`, `anual`, `unico`) e vigência
- [ ] Coluna "Real lançado" considera lançamentos `pendente` + `pago`
- [ ] Coluna "Real pago" considera somente lançamentos `pago`
- [ ] Desvios por competência batem com (`real - previsto`)

## Dashboard — KPI de conciliação
- [ ] Card "Desvio previsto x real (mês)" aparece no dashboard
- [ ] Card exibe previsto, real lançado e real pago do mês atual
- [ ] Valor de desvio global muda conforme variação dos lançamentos do mês
- [ ] Recomendação principal prioriza conciliação quando há desvio financeiro

## Regras de consistência
- [ ] Pessoa sem versão ativa de custos não quebra a conciliação (previsto = 0)
- [ ] Pessoa sem lançamentos reais não quebra a conciliação (real = 0)
- [ ] Competência sem dado real ainda exibe previsto quando aplicável
- [ ] Competência sem previsto exibe desvio igual ao valor real
