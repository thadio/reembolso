# Checklist de Testes — Fase 2.1 (Custos previstos e versionamento)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado com `007_phase2_cost_plans.sql`
- [ ] `php db/seed.php` executado
- [ ] Existe pessoa cadastrada com acesso ao Perfil 360
- [ ] Existe ao menos 1 item ativo em `cost_item_catalog`

## Fluxo em tabela (oficial)
- [ ] `POST /people/costs/item/store` com ao menos 1 valor > 0 cria versao automatica (`V1`) quando ainda nao existe plano
- [ ] Novo salvamento da tabela gera nova versao automatica (`V2`, `V3`, ...) e desativa a versao anterior
- [ ] Rotulo da proxima versao aparece previamente no formulario no padrao `Vn - dd/mm/aaaa`
- [ ] Salvar tabela com todos os valores vazios retorna erro de validacao
- [ ] Linhas com valor > 0 sao persistidas em lote na nova versao

## Validacoes por linha da tabela
- [ ] Campo `Periodicidade` inicia com valor do catalogo e permite alterar para `mensal`, `anual` ou `unico`
- [ ] Valor invalido (nao numerico) nao e salvo como item
- [ ] Campo `Fim da vigencia` nao e exibido na tabela de custos
- [ ] `Inicio da vigencia` vem preenchido por `Inicio efetivo (real)` e, sem esse dado, usa `Inicio efetivo (previsto)`
- [ ] Data de inicio invalida e rejeitada com mensagem de erro

## Perfil 360 (visual)
- [ ] Secao "Custos previstos" exibe versao ativa e KPIs
- [ ] Texto de apoio da secao exibe `Conforme {versao}`
- [ ] Tabela principal lista todos os itens ativos do catalogo em modo somente leitura
- [ ] Edicao da tabela so aparece apos acionar "Ajustar/alterar e gerar nova versao de custos"
- [ ] Placeholders de valor orientam o usuario conforme periodicidade
- [ ] Totais da tabela (periodo, anualizado e ate fim do ano) recalculam automaticamente durante digitacao
- [ ] Atalho `Ctrl/Cmd + S` dispara salvamento da tabela
- [ ] `Enter` avanca para o proximo campo e `Shift + Enter` volta para o campo anterior
- [ ] Botao final da edicao exibe somente "Salvar nova versao"
- [ ] Historico de versoes exibe totais por versao (mensal e anualizado)
- [ ] Comparacao com versao anterior exibe deltas mensal e anualizado

## Auditoria e eventos
- [ ] Salvamento em lote registra `audit_log` de versao (`entity=cost_plan`, `action=version.create.auto_table`)
- [ ] Cada linha salva registra `audit_log` (`entity=cost_plan_item`, `action=create`)
- [ ] `system_events` registra `cost_plan.table_saved` para cada salvamento da tabela

## Seguranca
- [ ] Usuario sem `people.manage` recebe 403 no endpoint oficial de tabela (`POST /people/costs/item/store`)
- [ ] Endpoint `POST /people/costs/item/store` exige CSRF valido
