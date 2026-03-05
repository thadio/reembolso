# Checklist de Testes - Ciclo 9.14 (Gestao de lotes de pagamento)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `039_phase9_payment_batches.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `invoice.view`
- [ ] Usuario com permissao `invoice.manage` para criar/atualizar lotes
- [ ] Base com pagamentos existentes em `payments` ainda nao vinculados a lote

## Painel de lotes
- [ ] `GET /invoices/payment-batches` carrega listagem paginada sem erro
- [ ] Filtros por `q`, `status`, `organ_id`, `reference_month` e intervalo de datas funcionam
- [ ] Ordenacao por codigo/status/data prevista/qtd/valor/created_at funciona
- [ ] Botao de acesso `Lotes de pagamento` aparece na listagem de boletos (`/invoices`)

## Criacao de lote
- [ ] Formulario permite selecionar pagamentos elegiveis (`payment_ids[]`) e criar lote em `POST /invoices/payment-batches/store`
- [ ] Validacoes bloqueiam criacao sem pagamentos selecionados
- [ ] Pagamento ja vinculado a outro lote e rejeitado na validacao de elegibilidade
- [ ] Total (`total_amount`) e quantidade (`payments_count`) do lote refletem os itens selecionados
- [ ] Codigo do lote (`batch_code`) e gerado automaticamente

## Detalhe e status
- [ ] `GET /invoices/payment-batches/show?id={batchId}` exibe metadados do lote e itens vinculados
- [ ] Link de boleto em cada item abre `GET /invoices/show?id={invoiceId}`
- [ ] Link de comprovante em item com anexo chama `GET /invoices/payments/proof`
- [ ] `POST /invoices/payment-batches/status/update` permite transicoes validas (`aberto -> em_processamento -> pago/cancelado`)
- [ ] Tentativa de transicao invalida retorna erro sem alterar status persistido

## Persistencia e trilha
- [ ] `payment_batches` persiste codigo, status, referencia, previsto, totais, criacao e fechamento
- [ ] `payment_batch_items` persiste vinculo lote/pagamento/boleto, valor e data de pagamento
- [ ] `audit_log` registra `entity=payment_batch` para create e status.update
- [ ] `system_events` registra `payment_batch.created` e `payment_batch.status_updated`

## Seguranca e regressao
- [ ] Endpoints `POST /invoices/payment-batches/store` e `POST /invoices/payment-batches/status/update` exigem `invoice.manage` e CSRF valido
- [ ] Usuario sem `invoice.manage` recebe `403` ao tentar criar/atualizar lote
- [ ] Fluxos existentes de boletos/pagamentos (`/invoices`, `/invoices/show`, `/invoices/payments/store`) continuam funcionando
