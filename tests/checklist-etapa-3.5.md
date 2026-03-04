# Checklist de Testes - Fase 3.5 (Pagamentos completos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `017_phase3_payments.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe boleto ativo com valor total maior que zero
- [ ] Existe ao menos uma pessoa vinculada ao boleto com rateio > 0

## Registro de pagamento
- [ ] `POST /invoices/payments/store` registra pagamento parcial (menor que saldo)
- [ ] `POST /invoices/payments/store` registra pagamento total (igual ao saldo)
- [ ] Sistema bloqueia pagamento acima do saldo pendente
- [ ] Sistema bloqueia pagamento para boleto cancelado
- [ ] Sistema bloqueia novo pagamento quando boleto ja estiver quitado

## Comprovante e rastreabilidade
- [ ] Upload de comprovante aceita `PDF`, `PNG`, `JPG`, `JPEG`
- [ ] Upload invalido (extensao/MIME/tamanho) e rejeitado com erro
- [ ] `GET /invoices/payments/proof?id={paymentId}&invoice_id={invoiceId}` baixa comprovante
- [ ] Historico de pagamentos exibe data, valor, processo, comprovante e usuario

## Integracao financeira do boleto
- [ ] `invoices.paid_amount` reflete soma dos pagamentos ativos
- [ ] Status do boleto muda para `pago_parcial` apos primeira baixa parcial
- [ ] Status do boleto muda para `pago` apos quitao total
- [ ] Dashboard/telas do boleto exibem saldo a pagar coerente

## Integracao por pessoa (rateio)
- [ ] Pagamento distribui valor para `invoice_people.paid_amount` respeitando saldo de cada vinculo
- [ ] Historico do boleto mostra valor alocado para pessoas em cada pagamento
- [ ] Remocao de vinculo com valor pago e bloqueada

## Auditoria e eventos
- [ ] `audit_log` registra `payment:create`
- [ ] `audit_log` registra `payment:download_proof`
- [ ] `system_events` registra `invoice.payment_registered`
- [ ] `system_events` registra `invoice.payment_proof_downloaded`
- [ ] `system_events` registra `invoice.status_changed` quando houver mudanca de status

## Seguranca de acesso
- [ ] Usuario sem `invoice.manage` nao registra pagamento
- [ ] Usuario sem `invoice.view` nao baixa comprovante de pagamento
