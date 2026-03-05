# Checklist de Testes - Ciclo 9.11 (Controle de versao de documentos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `037_phase9_document_version_control.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage`
- [ ] Usuario com permissao `people.documents.sensitive` para cenarios de documentos `restricted/sensitive`

## Fluxo funcional de versionamento
- [ ] Em `GET /people/show?id={id}`, cada card de documento exibe badge da versao atual (`Vn`)
- [ ] Cada card exibe o bloco `Historico de versoes` com as versoes registradas
- [ ] `POST /people/documents/version/store` cria nova versao para documento valido e retorna mensagem de sucesso
- [ ] Apos criar versao, o documento passa a exibir a nova versao como atual (ex.: V2)
- [ ] `GET /people/documents/download` continua baixando o arquivo da versao atual
- [ ] `GET /people/documents/version/download` baixa corretamente versao historica selecionada

## Seguranca e permissao
- [ ] Usuario sem `people.manage` nao acessa `POST /people/documents/version/store` (403)
- [ ] Usuario sem `people.view` nao acessa `GET /people/documents/version/download` (403)
- [ ] Documento `restricted/sensitive` sem permissao `people.documents.sensitive` bloqueia versionamento
- [ ] Documento `restricted/sensitive` sem permissao `people.documents.sensitive` bloqueia download de versao

## Integridade e trilha
- [ ] `document_versions` recebe registro V1 para documentos legados apos migration
- [ ] Upload novo em `POST /people/documents/store` registra automaticamente versao V1
- [ ] Criacao de nova versao grava auditoria (`version_upload`) e evento `document.version_created`
- [ ] Download de versao grava auditoria (`version_download`) e evento `document.version_downloaded`
- [ ] Download negado por sensibilidade gera trilha de negacao (`version_download_denied`)

## Regressao
- [ ] Upload inicial de documentos (`POST /people/documents/store`) continua funcionando
- [ ] Download atual (`GET /people/documents/download`) continua funcionando
- [ ] Exportacao de dossie (`GET /people/dossier/export`) continua sem regressao
