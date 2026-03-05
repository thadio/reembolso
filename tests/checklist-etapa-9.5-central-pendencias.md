# Checklist de Testes - Ciclo 9.5 (Central de pendencias)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `031_phase9_pending_center.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para resolver/reabrir pendencias

## Painel consolidado
- [ ] `GET /people/pending` exibe KPI de total, abertas, resolvidas, documentos, divergencias e retornos
- [ ] Filtros por `pending_type`, `status`, `severity`, `queue_scope` e `responsible_id` funcionam
- [ ] Ordenacao por pessoa, tipo, severidade, status, responsavel, prazo e atualizacao funciona
- [ ] Link "Central de pendencias" na listagem de pessoas abre o painel

## Geracao automatica de pendencias
- [ ] Pendencia de documento e criada quando etapa exige documento ausente no dossie
- [ ] Pendencia de divergencia e criada para divergencia financeira com justificativa obrigatoria nao resolvida
- [ ] Pendencia de retorno e criada para caso em etapa de retorno sem avancar apos o prazo
- [ ] Pendencia automatica e resolvida quando a causa deixa de existir (sincronizacao)

## Atualizacao de status
- [ ] `POST /people/pending/status` marca pendencia como resolvida
- [ ] `POST /people/pending/status` permite reabrir pendencia resolvida
- [ ] Atualizacao sem mudanca retorna mensagem de "sem alteracoes" sem erro
- [ ] Usuario sem `people.manage` recebe `403` no endpoint

## Persistencia e trilha
- [ ] `analyst_pending_items` persiste tipo, severidade, status e source_key
- [ ] `resolved_at` e `resolved_by` sao preenchidos ao resolver pendencia
- [ ] `audit_log` registra create/sync/update/status da entidade `analyst_pending_item`
- [ ] `system_events` registra eventos `pending.created`, `pending.auto_resolved` e `pending.status_updated`
