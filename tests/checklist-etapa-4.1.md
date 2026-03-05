# Checklist de Testes - Fase 4.1 (Templates de oficio)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `012_phase4_office_templates.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe ao menos 1 pessoa ativa com orgao e processo SEI preenchido

## Catalogo de templates
- [ ] `GET /office-templates` abre listagem paginada com filtros
- [ ] `POST /office-templates/store` exige chave, nome, tipo, assunto e corpo HTML
- [ ] `POST /office-templates/update` permite editar metadados do template
- [ ] `POST /office-templates/delete` faz exclusao logica de template, versoes e documentos gerados

## Versionamento
- [ ] Template novo cria automaticamente versao inicial V1 ativa
- [ ] `POST /office-templates/version/create` cria nova versao e marca versao anterior como historica
- [ ] Historico de versoes e exibido no detalhe do template
- [ ] Variaveis JSON invalidas sao bloqueadas na criacao de versao

## Merge e geracao
- [ ] `POST /office-templates/generate` exige template, versao e pessoa validos
- [ ] Tokens como `{{person_name}}`, `{{organ_name}}`, `{{person_process}}` sao substituidos no documento
- [ ] Valores de custo (`{{cost_monthly_total}}`, `{{cost_annual_total}}`) sao renderizados quando houver plano ativo
- [ ] Dados de CDO (`{{cdo_number}}`, `{{cdo_allocated_amount}}`) sao renderizados quando houver vinculo
- [ ] Documento gerado fica disponivel em `GET /office-documents/show?id={id}`

## HTML print
- [ ] `GET /office-documents/print?id={id}` abre versao print-friendly do oficio
- [ ] Conteudo renderizado no print corresponde ao documento salvo

## PDF nativo
- [ ] `GET /office-documents/pdf?id={id}` baixa PDF nativo do oficio
- [ ] Conteudo textual do PDF corresponde ao documento renderizado

## Auditoria e eventos
- [ ] `audit_log` registra `office_template:create/update/delete`
- [ ] `audit_log` registra `office_template_version:create`
- [ ] `audit_log` registra `office_document:generate`
- [ ] `audit_log` registra `office_document:export_pdf`
- [ ] `system_events` registra `office_template.created`, `office_template.version_created`, `office_document.generated`, `office_document.pdf_exported`

## Seguranca de acesso
- [ ] Usuario sem `office_template.view` nao acessa `/office-templates`
- [ ] Usuario sem `office_template.manage` nao cria template/versao e nao gera oficio
