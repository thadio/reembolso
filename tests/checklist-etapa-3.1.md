# Checklist de Testes — Fase 3.1 (CDO completo)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `009_phase3_cdos.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existem pessoas ativas cadastradas para vinculo

## Cadastro e CRUD de CDO
- [ ] Tela `GET /cdos` abre com listagem paginada
- [ ] Cadastro em `POST /cdos/store` exige numero, periodo e valor total > 0
- [ ] Edicao em `POST /cdos/update` registra alteracoes de valor/status
- [ ] Exclusao logica em `POST /cdos/delete` remove CDO da listagem principal

## Vinculo CDO x pessoas
- [ ] Detalhe do CDO mostra total, alocado e saldo disponivel
- [ ] Vinculo em `POST /cdos/people/link` bloqueia valor acima do saldo
- [ ] Um CDO aceita vinculo de multiplas pessoas
- [ ] Ao vincular/remover pessoa, totalizadores do CDO sao recalculados
- [ ] Ao remover vinculo em `POST /cdos/people/unlink`, saldo aumenta corretamente

## Status e consistencia
- [ ] Sem vinculos: status automatico fica `aberto`
- [ ] Com vinculo parcial: status automatico fica `parcial`
- [ ] Com saldo zerado: status automatico fica `alocado`
- [ ] CDO `encerrado` ou `cancelado` bloqueia novos vinculos
- [ ] Edicao bloqueia reduzir `valor total` abaixo do ja alocado

## Auditoria e eventos
- [ ] `audit_log` registra `cdo:create/update/delete`
- [ ] `audit_log` registra `cdo_person:link/unlink`
- [ ] `audit_log` registra `cdo:status.auto_update` quando aplicavel
- [ ] `system_events` registra `cdo.value_changed` e `cdo.status_changed` quando houver alteracao

## Dashboard
- [ ] Card "Cobertura CDO" aparece no dashboard
- [ ] Card mostra total de CDOs, quantidade em aberto e saldo disponivel
- [ ] Valores mudam apos vincular/desvincular pessoas em CDO
