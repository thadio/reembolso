# Checklist de Testes — Fase 2.4 (Financeiro real de reembolsos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `008_phase2_reimbursement_entries.sql`)
- [ ] Existe pessoa cadastrada no Perfil 360
- [ ] Usuario de teste possui permissao `people.manage` para validar cadastros

## Cadastro de lancamento financeiro
- [ ] `POST /people/reimbursements/store` cria lancamento `boleto` com status `pendente`
- [ ] `POST /people/reimbursements/store` cria lancamento `pagamento` e registra data de pagamento
- [ ] Valor zero/negativo ou titulo curto gera mensagem de validacao
- [ ] Competencia invalida, vencimento invalido ou data de pagamento invalida gera erro

## Perfil 360 — secao Reembolsos reais
- [ ] Secao exibe cards de resumo (pendente, pago, vencido, total)
- [ ] Valores dos cards refletem os lancamentos cadastrados
- [ ] Tabela e exibida como visao principal (mesmo padrao de leitura da aba de custos)
- [ ] Tabela lista tipo, titulo, competencia, valor, status, vencimento, pagamento e responsavel
- [ ] Lancamento pendente vencido e marcado visualmente como `Vencido`
- [ ] Formulario de inclusao/ajuste fica recolhido por padrao e abre por acao explicita

## Baixa financeira
- [ ] `POST /people/reimbursements/mark-paid` muda status para `pago`
- [ ] `paid_at` e preenchido automaticamente quando nao informado
- [ ] Lancamento ja pago nao quebra o fluxo (retorno idempotente)
- [ ] Acao de baixa nao aparece para lancamentos `pago` e `cancelado`

## Auditoria e eventos
- [ ] Criacao de lancamento gera `audit_log` com `entity=reimbursement_entry` e `action=create`
- [ ] Baixa de pagamento gera `audit_log` com `entity=reimbursement_entry` e `action=mark_paid`
- [ ] Eventos `reimbursement.entry_created`/`reimbursement.entry_paid` aparecem em `system_events`
- [ ] Secao Auditoria do Perfil 360 exibe registros de `reimbursement_entry`

## Seguranca
- [ ] Usuario sem permissao `people.manage` nao consegue postar em `/people/reimbursements/store`
- [ ] Usuario sem permissao `people.manage` nao consegue postar em `/people/reimbursements/mark-paid`
