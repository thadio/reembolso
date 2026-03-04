# Changelog

## 2026-03-04 — Fase 0 concluída
- Estrutura base MVC criada (`app`, `public`, `storage`, `db`, `docs`, `tests`)
- Bootstrap, router, sessão segura, CSRF e logger implementados
- Autenticação (login/logout), rate limit e RBAC por permissão
- Auditoria (`audit_log`) e biblioteca de eventos (`record_event`) implementadas
- Health check (`/health`) com validação de banco e storage
- UI base responsiva com menu, dashboard e listas vazias de Pessoas/Órgãos
- Migrations idempotentes e seed inicial de roles/permissões/catálogos/admin
- Documentação inicial e checklist de testes da etapa
