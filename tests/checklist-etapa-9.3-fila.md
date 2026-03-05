# Checklist de Testes - Ciclo 9.3 (Painel "Minha fila")

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `029_phase9_analyst_queue_productivity.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para atualizar fila

## Listagem de pessoas (painel de fila)
- [ ] `GET /people` exibe colunas `Responsável` e `Prioridade`
- [ ] Filtro `queue_scope=mine` retorna apenas registros do usuario logado
- [ ] Filtro `queue_scope=unassigned` retorna apenas registros sem responsavel
- [ ] Filtro por `priority` funciona para `low`, `normal`, `high` e `urgent`
- [ ] Filtro por `responsible_id` funciona para usuario especifico
- [ ] Botao `Minha fila` aplica filtros corretos

## Perfil 360 (gestao de fila)
- [ ] Card de pipeline exibe responsável e prioridade atuais
- [ ] `POST /people/pipeline/queue/update` altera responsavel
- [ ] `POST /people/pipeline/queue/update` altera prioridade
- [ ] Atualizacao sem mudanca retorna mensagem de "sem alteracoes" sem erro
- [ ] Usuario sem `people.manage` recebe `403` no endpoint

## Persistencia e trilha
- [ ] `assignments.assigned_user_id` persiste o responsavel selecionado
- [ ] `assignments.priority_level` persiste a prioridade selecionada
- [ ] `audit_log` registra `assignment:queue.update`
- [ ] `system_events` registra `pipeline.queue_updated`

## Regras de criacao de assignment
- [ ] Novo cadastro de pessoa inicia assignment com `assigned_user_id` do usuario criador
- [ ] Novo cadastro de pessoa inicia assignment com `priority_level=normal`
