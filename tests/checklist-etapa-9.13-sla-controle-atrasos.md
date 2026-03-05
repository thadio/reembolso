# Checklist de Testes - Ciclo 9.13 (Controle de SLA e casos em atraso)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `038_phase9_sla_case_controls.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `sla.view`
- [ ] Usuario com permissao `sla.manage` para atualizar controle
- [ ] Base com casos em nivel `vencido` no painel de SLA

## Painel e filtros
- [ ] Em `GET /sla-alerts`, os KPIs exibem `Atrasos abertos`, `Atrasos em tratamento` e `Atrasos resolvidos`
- [ ] Filtro `control_status` funciona para `aberto`, `em_tratamento` e `resolvido`
- [ ] Filtro de controle atua apenas sobre casos `vencido`

## Controle por caso vencido
- [ ] Em linha `vencido`, a tela exibe badge de controle (`Aberto`, `Em tratamento`, `Resolvido`)
- [ ] `POST /sla-alerts/control/update` atualiza status de controle e responsavel
- [ ] Campo de observacao (`note`) persiste e volta na listagem
- [ ] Caso nao vencido e rejeitado para controle com mensagem de validacao
- [ ] Requisicao sem alteracoes retorna sucesso sem regressao de estado

## Persistencia e trilha
- [ ] Tabela `sla_case_controls` persiste `assignment_id`, `person_id`, `control_status`, `owner_user_id`, `note` e timestamps
- [ ] `audit_log` registra `entity=sla_case_control` com `action=upsert`
- [ ] `system_events` registra `sla.case_control_updated`

## Seguranca e regressao
- [ ] Endpoint `POST /sla-alerts/control/update` exige `sla.manage` e CSRF valido
- [ ] Usuario sem `sla.manage` recebe `403` ao tentar atualizar controle
- [ ] Fluxos existentes (`/sla-alerts`, `/sla-alerts/rules`, `/sla-alerts/dispatch-email`) continuam funcionais
