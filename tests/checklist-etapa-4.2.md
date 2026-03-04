# Checklist de Testes - Fase 4.2 (Metadados formais de processo)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `013_phase4_process_metadata.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe ao menos 1 pessoa ativa com orgao vinculado

## CRUD de metadados formais
- [ ] `GET /process-meta` abre listagem paginada com filtros
- [ ] `POST /process-meta/store` cria registro com pessoa valida
- [ ] `POST /process-meta/update` permite editar oficio/DOU/entrada MTE
- [ ] `POST /process-meta/delete` faz exclusao logica do registro

## Regras de validacao
- [ ] Bloqueia pessoa inexistente ou removida
- [ ] Bloqueia segundo registro ativo para a mesma pessoa
- [ ] Bloqueia canal invalido fora da lista permitida
- [ ] Bloqueia URL DOU invalida
- [ ] Bloqueia data de entrada MTE anterior ao envio do oficio
- [ ] Bloqueia data de publicacao DOU anterior ao envio do oficio

## Upload e download do anexo DOU
- [ ] Upload aceita apenas `pdf`, `png`, `jpg`, `jpeg`
- [ ] Upload acima de 15MB e rejeitado
- [ ] MIME invalido e rejeitado
- [ ] Arquivo valido e salvo em `storage/uploads/process_metadata/{person_id}/{Y}/{m}`
- [ ] `GET /process-meta/dou-attachment?id={id}` baixa o arquivo quando existente

## Auditoria e eventos
- [ ] `audit_log` registra `process_metadata:create/update/delete`
- [ ] `audit_log` registra `process_metadata:download_dou_attachment`
- [ ] `system_events` registra `process_meta.created`, `process_meta.updated`, `process_meta.deleted`, `process_meta.attachment_downloaded`

## Seguranca de acesso
- [ ] Usuario sem `process_meta.view` nao acessa `GET /process-meta`
- [ ] Usuario sem `process_meta.manage` nao cria/edita/exclui
