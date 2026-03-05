# Checklist de Testes - Ciclo 9.6 (Calculadora automatica de reembolso)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `032_phase9_reimbursement_calculator.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para registrar lancamentos financeiros

## Calculadora automatica
- [ ] Em `POST /people/reimbursements/store`, com `use_calculator=1` e campos `calc_*` preenchidos, o valor final e calculado automaticamente
- [ ] Formula aplicada: `(Base + Transporte + Hospedagem + Alimentacao + Outros) + Ajuste - Desconto`
- [ ] Quando `amount` manual diverge do calculado, o sistema grava o total calculado e retorna aviso de substituicao
- [ ] Percentual fora da faixa (`< -100` ou `> 300`) bloqueia gravacao com erro validado
- [ ] Componentes negativos bloqueiam gravacao com erro validado
- [ ] Total calculado `<= 0` bloqueia gravacao com erro validado

## Perfil 360 (UI)
- [ ] Formulario de reembolso exibe bloco de calculadora com campos `calc_*`
- [ ] Preview de total e atualizado no cliente ao alterar componentes
- [ ] Ao listar lancamentos, entradas calculadas exibem `Memoria de calculo` com subtotal, ajuste, desconto e total
- [ ] Secao `Memorias de calculo recentes` exibe historico calculado da pessoa

## Persistencia e trilha
- [ ] `reimbursement_entries.calculation_memory` persiste JSON valido para lancamentos calculados
- [ ] `audit_log` de `reimbursement_entry:create` inclui metadados de calculo (`calculated` e `formula`)
- [ ] `system_events` (`reimbursement.entry_created` / `reimbursement.entry_paid_created`) inclui indicador `calculated`

## Seguranca e regressao
- [ ] `POST /people/reimbursements/store` exige `people.manage` e CSRF valido
- [ ] `POST /people/reimbursements/mark-paid` permanece funcional sem regressao
- [ ] KPI e listagem de reembolsos existentes no Perfil 360 permanecem funcionais
