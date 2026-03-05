# Checklist de Testes - Ciclo 9.16 (Simulacao previa da aprovacao final)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `invoice.manage`
- [ ] Existir ao menos 1 lote de pagamento em `aberto` ou `em_processamento`

## Fluxo de simulacao previa
- [ ] `POST /invoices/payment-batches/final-approval/simulate` executa com CSRF valido
- [ ] Simulacao aceita apenas status alvo final (`pago` ou `cancelado`)
- [ ] Simulacao mostra risco, totais e indicadores de qualidade (comprovante/processo)
- [ ] Simulacao expõe validade temporal para aprovacao final

## Gate de aprovacao final
- [ ] Atualizacao para `pago` sem `simulation_token` e bloqueada
- [ ] Atualizacao para `cancelado` sem `simulation_token` e bloqueada
- [ ] Atualizacao final com token valido e aceita
- [ ] Token expirado bloqueia aprovacao final
- [ ] Simulacao para status alvo diferente bloqueia aprovacao final

## Governanca e trilha
- [ ] `audit_log` registra `payment_batch` com `action=final_approval.simulate`
- [ ] `system_events` registra `payment_batch.final_approval_simulated`
- [ ] `audit_log` de `status.update` registra `final_approval_simulation_applied=true` em transicao final
- [ ] Fluxos nao finais (`aberto -> em_processamento`) continuam funcionando sem regressao
