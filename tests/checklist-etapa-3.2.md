# Checklist de Testes - Fase 3.2 (Boletos estruturados)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `010_phase3_invoices.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe ao menos 1 orgao ativo com pessoas cadastradas

## CRUD de boletos
- [ ] `GET /invoices` abre com listagem paginada
- [ ] `POST /invoices/store` exige orgao, numero, titulo, competencia, vencimento e valor > 0
- [ ] `POST /invoices/update` permite alterar metadados e status
- [ ] `POST /invoices/delete` faz exclusao logica do boleto

## Upload e metadados
- [ ] Upload de PDF valido e aceito no cadastro/edicao
- [ ] Arquivo nao-PDF ou acima de 15MB e bloqueado com mensagem de validacao
- [ ] `GET /invoices/pdf?id={id}` baixa arquivo salvo com permissao `invoice.view`
- [ ] Metadados de boleto (linha digitavel e referencia) persistem apos edicao

## Vinculo boleto x pessoas
- [ ] Vinculo em `POST /invoices/people/link` aceita multiplas pessoas no mesmo boleto
- [ ] Pessoa de outro orgao nao pode ser vinculada ao boleto
- [ ] Vinculo duplicado da mesma pessoa no mesmo boleto e bloqueado
- [ ] Rateio opcional vazio cria vinculo sem alocacao de valor
- [ ] Rateio acima do saldo do boleto e bloqueado
- [ ] Remocao em `POST /invoices/people/unlink` recalcula saldo corretamente

## Status e consistencia
- [ ] Boleto com vencimento passado e sem pagamento aparece como `vencido`
- [ ] Boleto com vencimento futuro e sem pagamento aparece como `aberto`
- [ ] Status `pago` e `cancelado` bloqueiam alteracao de vinculos
- [ ] `total_amount` nao pode ser reduzido abaixo do total rateado para pessoas

## Auditoria e eventos
- [ ] `audit_log` registra `invoice:create/update/delete`
- [ ] `audit_log` registra `invoice_person:link/unlink`
- [ ] `audit_log` registra `invoice:download_pdf` no download do arquivo
- [ ] `system_events` registra `invoice.created`, `invoice.updated`, `invoice.deleted` e eventos de vinculo

## Seguranca de acesso
- [ ] Usuario sem `invoice.view` nao acessa `GET /invoices`
- [ ] Usuario sem `invoice.manage` nao consegue criar/editar/excluir/vincular
