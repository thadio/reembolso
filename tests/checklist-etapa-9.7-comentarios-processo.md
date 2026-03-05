# Checklist de Testes - Ciclo 9.7 (Comentarios internos por processo)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `033_phase9_process_internal_comments.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para criar/editar/excluir comentarios

## Perfil 360 (UI)
- [ ] Card `Comentarios internos do processo` aparece em `GET /people/show?id={id}`
- [ ] KPIs de comentarios (`Total`, `Abertos`, `Arquivados`, `Fixados`) refletem a base
- [ ] Formulario de novo comentario permite definir texto, status e flag de fixacao
- [ ] Lista exibe comentarios com badges de status/fixado, autor e timestamps

## CRUD operacional
- [ ] `POST /people/process-comments/store` cria comentario interno com `status=aberto`
- [ ] `POST /people/process-comments/store` aceita `is_pinned=1`
- [ ] `POST /people/process-comments/update` altera texto e status para `arquivado`
- [ ] `POST /people/process-comments/update` permite fixar/desfixar comentario
- [ ] `POST /people/process-comments/delete` realiza exclusao logica (`deleted_at`)
- [ ] Comentario excluido deixa de aparecer na lista do Perfil 360

## Persistencia e trilha
- [ ] Tabela `process_comments` persiste `person_id`, `assignment_id`, `status`, `is_pinned`, `created_by`, `updated_by` e `deleted_at`
- [ ] `audit_log` registra `process_comment:create`, `process_comment:update`, `process_comment:status.update` e `process_comment:delete`
- [ ] `system_events` registra `process_comment.created`, `process_comment.updated`, `process_comment.status_updated` e `process_comment.deleted`
- [ ] Secao Auditoria do Perfil 360 passa a exibir entidade `process_comment`

## Seguranca e regressao
- [ ] Endpoints `POST /people/process-comments/store|update|delete` exigem `people.manage` e CSRF valido
- [ ] Usuario sem `people.manage` recebe `403` nos endpoints de comentarios internos
- [ ] Blocos existentes (pipeline, timeline, dossie, custos, reembolsos e auditoria) permanecem funcionais
