# Changelog

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
