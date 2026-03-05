# Checklist de Testes - Ciclo 9.12 (Painel executivo com gargalos e ranking de orgaos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `dashboard.view`
- [ ] Base com casos em etapas diferentes e ao menos 2 orgaos ativos

## Painel executivo no dashboard
- [ ] Em `GET /dashboard`, card `Painel executivo` aparece abaixo da recomendacao principal
- [ ] KPIs executivos exibem: `Orgaos monitorados`, `Casos monitorados`, `Em risco`, `Vencidos`
- [ ] KPI de criticidade total e coerente com a relacao entre casos criticos e casos monitorados

## Gargalos por etapa
- [ ] Tabela `Gargalos por etapa` exibe etapa, casos, orgaos impactados, media de dias, em risco, vencido e criticidade
- [ ] Etapas com vencidos aparecem com criticidade `Vencido`
- [ ] Etapas com apenas risco (sem vencido) aparecem com criticidade `Em risco`

## Ranking de orgaos
- [ ] Tabela `Ranking de orgaos` exibe ordenacao por severidade
- [ ] Colunas exibidas: orgao, casos, em risco, vencido, criticidade, media de dias e score
- [ ] Link do orgao na tabela abre `GET /organs/show?id={id}`

## Snapshot e consistencia
- [ ] `php scripts/kpi-snapshot.php --dry-run` executa sem erro
- [ ] `php scripts/kpi-snapshot.php` gera snapshot com chave `executive_panel`
- [ ] Dashboard com fonte `snapshot KPI` continua exibindo o painel executivo

## Regressao
- [ ] Blocos existentes do dashboard (KPIs operacionais, distribuicao pipeline, ultimas movimentacoes) continuam funcionando
- [ ] Modulo `/reports` continua acessivel e sem regressao de filtros/exportacoes
