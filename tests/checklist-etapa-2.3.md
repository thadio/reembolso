# Checklist de Testes — Fase 2.3 (Dashboard operacional)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` executado
- [ ] Existem pessoas e orgaos cadastrados para validar agregacoes

## KPIs principais
- [ ] Card "Pessoas no pipeline" exibe total real de pessoas ativas (nao deletadas)
- [ ] Card "Orgaos cadastrados" exibe total real de orgaos ativos
- [ ] Card "Cobertura documental" apresenta percentual consistente com pessoas com/sem documentos
- [ ] Card "Cobertura de custos" apresenta percentual consistente com pessoas com versao ativa
- [ ] Card "Eventos na timeline (30 dias)" exibe quantidade real para o periodo
- [ ] Card de saude aponta para `/health` e exibe volume de auditoria dos ultimos 30 dias

## Distribuicao do pipeline
- [ ] Secao "Distribuicao do pipeline" lista os fluxos BPMN ativos (`assignment_flows`)
- [ ] Cada fluxo exibe suas etapas ativas configuradas em `assignment_flow_steps` + `assignment_statuses`
- [ ] Quantidade por etapa corresponde ao status atual das pessoas no respectivo fluxo
- [ ] Percentuais por etapa exibem participacao no fluxo e no total geral de pessoas
- [ ] Barra visual acompanha o percentual exibido dentro do fluxo

## Recomendacao operacional
- [ ] Bloco "Proxima acao" muda conforme gap identificado (sem pessoas, sem documentos, sem custos, etc.)
- [ ] Botao de acao do bloco direciona para rota valida (`/people` ou `/people/create`)

## Movimentacoes recentes
- [ ] Secao "Ultimas movimentacoes" exibe eventos mais recentes da timeline
- [ ] Cada item mostra data/hora, evento, titulo, pessoa e responsavel
- [ ] Link da pessoa abre o Perfil 360 correto (`/people/show?id={id}`)

## Seguranca e permissao
- [ ] Usuario sem permissao `dashboard.view` recebe 403 em `/dashboard`
- [ ] Usuario com permissao `dashboard.view` acessa dashboard normalmente
