# Checklist de Testes - Fase 4.3 (SLA e alertas de pendencia)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `015_phase4_sla_alerts.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Existem pessoas em etapas intermediarias do pipeline (nao finais)

## Painel de pendencias
- [ ] `GET /sla-alerts` abre painel com KPIs (total, no prazo, em risco, vencido)
- [ ] Filtros por busca, status e severidade funcionam em conjunto
- [ ] Ordenacao por etapa, dias e nivel SLA funciona
- [ ] Lista mostra pessoa, orgao, etapa, dias e nivel SLA

## Regras configuraveis por etapa
- [ ] `GET /sla-alerts/rules` lista etapas elegiveis para SLA
- [ ] `POST /sla-alerts/rules/upsert` salva limiares de risco/vencido por etapa
- [ ] Bloqueia vencido menor/igual ao risco
- [ ] Bloqueia etapa invalida para configuracao
- [ ] Permite ativar/desativar regra por etapa

## Notificacao opcional por email
- [ ] `POST /sla-alerts/dispatch-email` dispara notificacoes para itens elegiveis
- [ ] Disparo respeita filtro de severidade (`all` = `em_risco` + `vencido`, `em_risco`, `vencido`)
- [ ] Disparo `all` nao envia item `no_prazo`
- [ ] Disparo registra logs em `sla_notification_logs`
- [ ] Ausencia de destinatario invalida envio da regra

## Auditoria e eventos
- [ ] `audit_log` registra `sla_rule:upsert`
- [ ] `audit_log` registra `sla:dispatch_notifications`
- [ ] `system_events` registra `sla.rule_upserted` e `sla.notifications_dispatched`

## Seguranca de acesso
- [ ] Usuario sem `sla.view` nao acessa `GET /sla-alerts`
- [ ] Usuario sem `sla.manage` nao acessa regras nem dispara emails
