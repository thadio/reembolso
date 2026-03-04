# Changelog

## 2026-03-04 — Fase 1.5 concluída (Dossiê documental)
- Criada migration `006_phase1_documents_dossier.sql` com tabela `documents`
- Implementado `DocumentRepository` e `DocumentService` para:
  - upload múltiplo com validação de extensão/MIME/tamanho
  - armazenamento seguro em `storage/uploads/{person_id}/documents/{Y}/{m}`
  - listagem paginada de documentos no Perfil 360
  - download protegido por pessoa/permissão
- `PeopleController` e rotas atualizados com:
  - `POST /people/documents/store`
  - `GET /people/documents/download`
- Perfil 360 atualizado na seção de documentos com:
  - formulário de upload múltiplo
  - drag-and-drop
  - metadados (tipo, SEI, data, tags, observações)
  - paginação e ação de download por item
- Auditoria implementada para:
  - upload (`entity=document`, `action=upload`)
  - download (`entity=document`, `action=download`)
- Checklist da etapa adicionado em `tests/checklist-etapa-1.5.md`

## 2026-03-04 — Reorganizacao DevOps/Docs
- Documentacao centralizada em `/docs` com estrutura numerada (`01` a `07`)
- `README.md` transformado em portal da documentacao
- `docs/03-environment.md` definido como fonte canonica de ambiente
- `docs/04-deploy.md` reescrito para deploy via bash no servidor
- `scripts/deploy.sh` reescrito para fluxo idempotente no servidor atual
- Adicionados `scripts/healthcheck.sh` e `scripts/rollback.sh`
- `serverconfig.md` da raiz convertido para arquivo deprecado com ponteiro
- Conteudos legados em `_ignore/docs` removidos para evitar duplicidade e riscos
- `.env.example` simplificado e padronizado com placeholders seguros
- `.gitignore` reforcado para segredos/chaves/backups/dumps

## 2026-03-04 — Fase 1.4 concluída (Timeline completa)
- Criada migration `005_phase1_timeline_attachments.sql` com tabela `timeline_event_attachments`
- `PipelineRepository` expandido com:
  - paginação de timeline com anexos
  - consulta completa para exportação/impressão
  - persistência e consulta de anexos por evento
- `PipelineService` expandido com:
  - criação de evento manual (`addManualEvent`)
  - retificação não destrutiva (`rectifyEvent`)
  - upload seguro de anexos (PDF/JPG/PNG até 10MB)
  - download protegido de anexo por pessoa
- `PeopleController` e rotas atualizados com:
  - `POST /people/timeline/store`
  - `POST /people/timeline/rectify`
  - `GET /people/timeline/attachment`
  - `GET /people/timeline/print`
- Perfil 360 atualizado com:
  - formulário de evento manual
  - anexos por evento com download
  - retificação por evento (sem apagar histórico)
  - paginação da timeline
  - botão de impressão/exportação HTML print-friendly
- Novos templates:
  - `app/Views/print_layout.php`
  - `app/Views/people/timeline_print.php`
- Checklist de testes adicionado em `tests/checklist-etapa-1.4.md`

## 2026-03-04 — Fase 1.3 concluída (Movimentação + Pipeline)
- Criada migration `004_phase1_pipeline_assignments.sql` com:
  - `assignment_statuses`
  - `assignments`
  - `timeline_events`
- Implementado `PipelineRepository` e `PipelineService`
- Pipeline de status configurável com sequência padrão:
  - Interessado → Triagem → Selecionado → Ofício órgão → Custos recebidos → CDO → MGI → DOU → Ativo
- Ao criar pessoa, assignment inicial é criado automaticamente
- Endpoint de avanço de pipeline implementado em `POST /people/pipeline/advance`
- Ao avançar status, sistema registra automaticamente:
  - `audit_log`
  - `system_events`
  - `timeline_events`
- Perfil 360 atualizado com:
  - trilha visual do pipeline
  - botão de próxima ação guiada
  - timeline cronológica
- Checklist de testes adicionado em `tests/checklist-etapa-1.3.md`

## 2026-03-04 — Fase 1.2 concluída (Pessoas)
- Criada migration `003_phase1_people.sql` com tabela `people` e vínculo obrigatório a `organs`
- Implementado `PeopleRepository` com filtros (status/modalidade/órgão/tags), busca, ordenação e paginação
- Implementado `PeopleService` com validações, regras de CPF e auditoria/eventos
- Implementado CRUD completo de Pessoas:
  - lista filtrável com painel lateral de resumo
  - criação
  - edição
  - exclusão lógica
  - Perfil 360 com abas base (Resumo, Timeline, Documentos, Custos, Auditoria)
- RBAC atualizado com permissões:
  - `people.manage`
  - `people.cpf.full`
- CPF mascarado em listagens para perfis sem permissão de visualização completa
- Checklist de testes adicionado em `tests/checklist-etapa-1.2.md`

## 2026-03-04 — Fase 1.1 concluída (Órgãos)
- Criada migration `002_phase1_organs.sql` com tabela `organs` (soft delete e índices)
- Implementado `OrganRepository` com busca, ordenação e paginação
- Implementado `OrganService` com validação, auditoria e eventos
- Implementado CRUD completo de Órgãos:
  - lista com filtros/paginação
  - criação
  - detalhe
  - edição
  - exclusão lógica
- RBAC atualizado com permissão `organs.manage`
- UI responsiva ampliada (tabela, formulários, paginação e ações)
- Ação rápida no detalhe: “Ver pessoas vinculadas”
- Checklist de testes adicionado em `tests/checklist-etapa-1.1.md`

## 2026-03-04 — Fase 0 concluída
- Estrutura base MVC criada (`app`, `public`, `storage`, `db`, `docs`, `tests`)
- Bootstrap, router, sessão segura, CSRF e logger implementados
- Autenticação (login/logout), rate limit e RBAC por permissão
- Auditoria (`audit_log`) e biblioteca de eventos (`record_event`) implementadas
- Health check (`/health`) com validação de banco e storage
- UI base responsiva com menu, dashboard e listas vazias de Pessoas/Órgãos
- Migrations idempotentes e seed inicial de roles/permissões/catálogos/admin
- Documentação inicial e checklist de testes da etapa
