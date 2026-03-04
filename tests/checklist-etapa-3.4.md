# Checklist de Testes - Fase 3.4 (Conciliacao avancada e workflow)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `016_phase3_reconciliation_workflow.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existe espelho de custo com itens para pessoa que tenha plano de custo ativo na mesma competencia

## Conciliacao item a item
- [ ] `GET /cost-mirrors/reconciliation/show?id={id}` abre painel de conciliacao avancada
- [ ] `POST /cost-mirrors/reconciliation/run` gera comparacao previsto x espelho por item
- [ ] Divergencia de item faltante no espelho aparece como `faltante_espelho`
- [ ] Divergencia de item sem previsto aparece como `faltante_previsto`
- [ ] Divergencia de valor aparece como `valor_divergente`

## Severidade e justificativa por limiar
- [ ] Divergencias possuem severidade (`baixa`, `media`, `alta`) conforme valor da diferenca
- [ ] Divergencias acima do limiar exigem justificativa
- [ ] `POST /cost-mirrors/reconciliation/justify` grava justificativa e marca divergencia resolvida
- [ ] Aprovacao e bloqueada quando ha divergencia obrigatoria sem justificativa

## Aprovacao com bloqueio de edicao
- [ ] `POST /cost-mirrors/reconciliation/approve` aprova a conferencia
- [ ] Espelho aprovado fica bloqueado para edicao de cabecalho/itens/importacao CSV/exclusao
- [ ] Tela de detalhe do espelho indica bloqueio por conciliacao aprovada

## Auditoria e eventos
- [ ] `audit_log` registra `cost_mirror_reconciliation:run`
- [ ] `audit_log` registra `cost_mirror_divergence:justify`
- [ ] `audit_log` registra `cost_mirror_reconciliation:approve`
- [ ] `system_events` registra `cost_mirror.reconciliation_ran`, `cost_mirror.divergence_justified`, `cost_mirror.reconciliation_approved`

## Seguranca de acesso
- [ ] Usuario sem `cost_mirror.view` nao acessa tela de conciliacao avancada
- [ ] Usuario sem `cost_mirror.manage` nao executa run/justificativa/aprovacao
