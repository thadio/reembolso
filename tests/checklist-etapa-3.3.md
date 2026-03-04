# Checklist de Testes - Fase 3.3 (Espelho de custo detalhado)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `011_phase3_cost_mirrors.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe ao menos 1 pessoa ativa com orgao vinculado

## CRUD de espelho
- [ ] `GET /cost-mirrors` abre listagem paginada com filtros
- [ ] `POST /cost-mirrors/store` exige pessoa e competencia validas
- [ ] `POST /cost-mirrors/update` permite editar status, titulo, observacoes e boleto
- [ ] `POST /cost-mirrors/delete` faz exclusao logica do espelho e dos itens

## Regras de validacao
- [ ] Bloqueia duplicidade ativa por pessoa + competencia
- [ ] Bloqueia competencia invalida no cadastro/edicao
- [ ] Boleto opcional so aceita orgao/competencia compativeis com o espelho
- [ ] Espelho sem boleto e salvo normalmente

## Itens manuais
- [ ] `POST /cost-mirrors/items/store` aceita item com valor total direto
- [ ] `POST /cost-mirrors/items/store` aceita item por quantidade + valor unitario
- [ ] Valor total do espelho recalcula apos inclusao manual
- [ ] `POST /cost-mirrors/items/delete` remove item e recalcula total

## Importacao CSV
- [ ] `POST /cost-mirrors/items/import-csv` aceita CSV valido com cabecalhos suportados
- [ ] CSV com cabecalho invalido e rejeitado com mensagem de erro
- [ ] CSV com linha invalida e rejeitado sem persistencia parcial
- [ ] Importacao valida atualiza total e quantidade de itens do espelho

## Auditoria e eventos
- [ ] `audit_log` registra `cost_mirror:create/update/delete`
- [ ] `audit_log` registra `cost_mirror_item:create/delete`
- [ ] `audit_log` registra `cost_mirror:import_csv`
- [ ] `system_events` registra `cost_mirror.created`, `cost_mirror.updated`, `cost_mirror.item_added`, `cost_mirror.item_removed` e `cost_mirror.csv_imported`

## Seguranca de acesso
- [ ] Usuario sem `cost_mirror.view` nao acessa `GET /cost-mirrors`
- [ ] Usuario sem `cost_mirror.manage` nao cria/edita/exclui/importa
