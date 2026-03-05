# Checklist de Testes - Ciclo 9.4 (Checklist automatico por tipo de caso)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `030_phase9_case_checklist_automation.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `people.manage` para marcar itens

## Geracao automatica
- [ ] Ao abrir `GET /people/show?id={id}` com assignment ativo, checklist e gerado automaticamente
- [ ] Checklist inclui itens de `geral` + itens do tipo de caso (modalidade)
- [ ] Modalidade `Cessao` gera itens especificos de cessao
- [ ] Modalidade `Composicao de Forca de Trabalho` gera itens especificos de CFT
- [ ] Modalidade `Requisicao` gera itens especificos de requisicao

## Perfil 360 e interacao
- [ ] Card de pipeline exibe bloco de checklist com progresso percentual
- [ ] Itens exibem status `Pendente/Concluido` e flag `Obrigatorio/Opcional`
- [ ] `POST /people/pipeline/checklist/update` marca item como concluido
- [ ] `POST /people/pipeline/checklist/update` permite voltar item para pendente
- [ ] Atualizacao sem mudanca retorna mensagem de "sem alteracoes" sem erro
- [ ] Usuario sem `people.manage` recebe `403` no endpoint

## Persistencia e trilha
- [ ] `assignment_checklist_items.is_done` persiste status do item
- [ ] `assignment_checklist_items.done_at` e `done_by` sao preenchidos ao concluir item
- [ ] `audit_log` registra `assignment_checklist_item:status.update`
- [ ] `system_events` registra `pipeline.checklist_item_updated`

## Seguranca e regressao
- [ ] Endpoint de checklist exige CSRF valido
- [ ] Rotas existentes de fila (`/people/pipeline/queue/update`) permanecem funcionais
- [ ] Timeline e documentos do Perfil 360 continuam sem regressao visual/funcional
