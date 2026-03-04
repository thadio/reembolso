# Checklist de Testes — Fase 2.1 (Custos previstos e versionamento)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado com `007_phase2_cost_plans.sql`
- [ ] `php db/seed.php` executado
- [ ] Existe pessoa cadastrada com acesso ao Perfil 360

## Criacao de versao
- [ ] `POST /people/costs/version/create` cria versao inicial quando ainda nao existe plano de custos
- [ ] Criacao de nova versao desativa a anterior e mantem apenas uma versao ativa
- [ ] Opcao `clone_current=1` replica itens da versao anterior para a nova versao
- [ ] Opcao `clone_current=0` cria nova versao vazia

## Inclusao de itens
- [ ] `POST /people/costs/item/store` inclui item valido para versao ativa
- [ ] Tipos `mensal`, `anual` e `unico` sao aceitos
- [ ] Valor menor/igual a zero e rejeitado com mensagem de erro
- [ ] Vigencia com `end_date < start_date` e rejeitada
- [ ] Sem versao ativa, o sistema cria versao inicial automaticamente antes de inserir o item

## Perfil 360 (visual)
- [ ] Secao "Custos previstos" exibe versao ativa e KPIs
- [ ] Tabela de itens mostra tipo, valor, vigencia e responsavel
- [ ] Historico de versoes exibe totais por versao (mensal e anualizado)
- [ ] Comparacao com versao anterior exibe deltas mensal e anualizado

## Auditoria e eventos
- [ ] Criacao de versao registra `audit_log` (`entity=cost_plan`, `action=version.create`)
- [ ] Inclusao de item registra `audit_log` (`entity=cost_plan_item`, `action=create`)
- [ ] `system_events` registra `cost_plan.initial_created`, `cost_plan.version_created` e `cost_plan.item_added`

## Seguranca
- [ ] Usuario sem `people.manage` recebe 403 nos endpoints de custos
- [ ] Endpoints `POST` de custos exigem CSRF valido
