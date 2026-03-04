# Checklist de Testes — Fase 1.3 (Movimentação + Pipeline + Timeline)

## Pré-condições
- [ ] `php db/migrate.php` aplicado com `004_phase1_pipeline_assignments.sql`
- [ ] `php db/seed.php` executado (status pipeline populados)
- [ ] Existe pessoa cadastrada na etapa 1.2

## Pipeline
- [ ] Ao cadastrar pessoa, assignment inicial é criado automaticamente
- [ ] Status inicial do assignment e da pessoa é `interessado`
- [ ] `POST /people/pipeline/advance` avança para próxima etapa
- [ ] Não permite avançar além do status final (`ativo`)
- [ ] Ao avançar para `ativo`, registra `effective_start_date`

## Timeline automática
- [ ] Criação do pipeline gera evento em `timeline_events`
- [ ] Mudança de status gera novo evento em `timeline_events`
- [ ] Perfil 360 exibe timeline em ordem decrescente

## Auditoria e eventos
- [ ] Criação de assignment registra `audit_log` (`entity=assignment`, `action=create`)
- [ ] Avanço de status registra `audit_log` (`action=status.advance`)
- [ ] `system_events` registra `pipeline.started` e `pipeline.status_changed`

## Perfil 360
- [ ] Card de pipeline exibe status atual
- [ ] Botão de próxima ação usa rótulo configurado em `assignment_statuses.next_action_label`
- [ ] Trilha visual do pipeline marca etapas concluídas/atual/pendentes

## Segurança
- [ ] Usuário sem `people.manage` recebe 403 no endpoint de avanço de pipeline
- [ ] Endpoint de avanço exige CSRF
