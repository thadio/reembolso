# Checklist de Testes - Ciclo 9.1 (RF-22: sensibilidade documental)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `028_phase6_document_sensitivity_access.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com `people.view`
- [ ] Usuario com `people.manage`
- [ ] Usuario com `people.documents.sensitive` (perfil de validacao administrativa)

## Upload e classificacao
- [ ] `POST /people/documents/store` com `sensitivity_level=public` funciona para perfil com `people.manage`
- [ ] `POST /people/documents/store` com `sensitivity_level=restricted` sem `people.documents.sensitive` retorna erro de permissao
- [ ] `POST /people/documents/store` com `sensitivity_level=sensitive` sem `people.documents.sensitive` retorna erro de permissao
- [ ] `POST /people/documents/store` com `sensitivity_level=restricted|sensitive` funciona para perfil com `people.documents.sensitive`
- [ ] Valor invalido em `sensitivity_level` retorna erro de validacao

## Listagem no Perfil 360
- [ ] Perfil sem `people.documents.sensitive` visualiza apenas documentos `public`
- [ ] Perfil com `people.documents.sensitive` visualiza documentos `public`, `restricted` e `sensitive`
- [ ] Badge de sensibilidade aparece em cada item de documento

## Download protegido
- [ ] `GET /people/documents/download` de documento `public` funciona com `people.view`
- [ ] `GET /people/documents/download` de documento `restricted` sem permissao adicional retorna acesso negado
- [ ] `GET /people/documents/download` de documento `sensitive` sem permissao adicional retorna acesso negado
- [ ] `GET /people/documents/download` de documento `restricted|sensitive` funciona com `people.documents.sensitive`

## Auditoria, eventos e LGPD
- [ ] Download permitido registra `document:download` em `audit_log`
- [ ] Download negado registra `document:download_denied` em `audit_log`
- [ ] Evento `document.downloaded` e registrado para sucesso
- [ ] Evento `document.download_denied` e registrado para negacao
- [ ] `sensitive_access_logs` registra `document_download` e `document_download_denied` com `sensitivity` coerente (`document_public|document_restricted|document_sensitive`)
