# Checklist de Testes - Ciclo 9.9 (Historico consolidado de pessoa e orgao)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `035_phase6_consolidated_history_indexes.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `organs.view`
- [ ] Usuario com permissao `audit.view` para visualizar/exportar historico consolidado

## Orgao (historico consolidado)
- [ ] Em `GET /organs/show?id={id}`, o card `Historico consolidado de pessoa e orgao` aparece para usuario com `audit.view`
- [ ] Filtros por busca, entidade, acao e periodo funcionam
- [ ] Lista paginada exibe entidade, acao, usuario, IP e data/hora
- [ ] Expandir `Ver dados` mostra `before_data`, `after_data` e `metadata`
- [ ] `GET /organs/audit/export?organ_id={id}&audit_*={filtros}` gera CSV com os filtros aplicados

## Escopo consolidado (orgao + pessoas)
- [ ] Historico inclui eventos da entidade `organ` (CRUD do orgao)
- [ ] Historico inclui eventos de `person` para pessoas vinculadas ao orgao
- [ ] Historico inclui eventos operacionais/financeiros vinculados as pessoas do orgao (`assignment`, `timeline_event`, `document`, `reimbursement_entry`, `process_comment`, `process_admin_timeline_note`, `analyst_pending_item`)

## Perfil 360 da pessoa
- [ ] Secao `Auditoria` em `GET /people/show?id={id}` passa a incluir entidade `organ` do orgao atual da pessoa
- [ ] Secao `Auditoria` em `GET /people/show?id={id}` inclui entidade `analyst_pending_item`
- [ ] Rotulos exibem corretamente `Orgao` e `Pendencia operacional`

## Seguranca e regressao
- [ ] Usuario sem `audit.view` nao acessa `GET /organs/audit/export` (403)
- [ ] Usuario sem `audit.view` visualiza mensagem de permissao insuficiente no card de historico do orgao
- [ ] Fluxos existentes de orgaos (create/edit/delete e lista de pessoas vinculadas) permanecem funcionais
