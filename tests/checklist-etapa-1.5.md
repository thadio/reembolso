# Checklist de Testes — Fase 1.5 (Dossie documental)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado com `006_phase1_documents_dossier.sql`
- [ ] `php db/seed.php` executado
- [ ] Existe pessoa cadastrada (com acesso ao Perfil 360)

## Upload seguro
- [ ] `POST /people/documents/store` registra upload de arquivo valido
- [ ] Upload multiplo registra mais de um documento no mesmo envio
- [ ] Arquivo invalido (extensao/MIME/tamanho) gera erro/aviso e nao corrompe o fluxo
- [ ] Arquivo fisico salvo em `storage/uploads/{person_id}/documents/...`
- [ ] Metadados opcionais persistem (`reference_sei`, `document_date`, `tags`, `notes`)

## Listagem e paginação
- [ ] Perfil 360 exibe secao de Documentos com registros enviados
- [ ] Exibe tipo, titulo, arquivo original, tamanho e responsavel
- [ ] Paginacao de documentos funciona com `documents_page`

## Download protegido
- [ ] `GET /people/documents/download?id={id}&person_id={personId}` retorna arquivo para usuario autorizado
- [ ] Download com `person_id` divergente bloqueia acesso
- [ ] Download de id inexistente retorna erro e redireciona

## Auditoria e eventos
- [ ] Upload registra `audit_log` (`entity=document`, `action=upload`)
- [ ] Download registra `audit_log` (`entity=document`, `action=download`)
- [ ] `system_events` registra `document.uploaded` e `document.downloaded`

## Permissoes e CSRF
- [ ] Usuario sem `people.manage` recebe 403 no upload de documentos
- [ ] Usuario sem `people.view` recebe 403 no download de documentos
- [ ] Endpoint de upload exige CSRF valido
